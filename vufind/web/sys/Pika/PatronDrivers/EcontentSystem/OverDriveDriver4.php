<?php
/*
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Pika\PatronDrivers\eContentSystem;

use Pika\Cache;
use Pika\Logger;
use Curl\Curl;
use User;
use OverDriveRecordDriver;

class OverDriveDriver4 {
	const VERSION = 4;

	private array $requirePin;
	private array $ILSName;
	private array $websiteId;

	const FORMAT_MAP = [
		'audiobook-mp3'       => 'OverDrive MP3 Audiobook',  // download option
		'audiobook-overdrive' => 'OverDrive Listen',         // online option
		'ebook-epub-adobe'    => 'Adobe EPUB eBook',         // download option
		'ebook-epub-open'     => 'Open EPUB eBook',          // download option
		'ebook-pdf-adobe'     => 'Adobe PDF eBook',          // download option
		'ebook-pdf-open'      => 'Open PDF eBook',           // download option
		'ebook-kindle'        => 'Kindle Book',              // download option
		'ebook-mediado'       => 'MediaDo Reader',           // download option
		'ebook-overdrive'     => 'OverDrive Read',           // online option
		'magazine-overdrive'  => 'OverDrive Magazine',       // online option
		'video-streaming'     => 'OverDrive Video',          // online option
		//		'ebook-disney'        => 'Disney Online Book',
		//		'ebook-microsoft'     => 'Microsoft eBook',
		//		'audiobook-wma'       => 'OverDrive WMA Audiobook',
		//		'audiobook-streaming' => 'Streaming Audiobook',
		//		'music-wma'           => 'OverDrive Music',
		//		'video-wmv'           => 'OverDrive Video',
		//		'video-wmv-mobile'    => 'OverDrive Video (mobile)',
		//		'periodicals-nook'    => 'NOOK Periodicals',
	];

	private Logger $logger;
	private Cache $cache;
	private $patronApi;
	private array $defaultCurlOptions;

	public function __construct(){
		global $configArray;
		$this->logger             = new Logger(__CLASS__);
		$this->cache              = new Cache();
		$this->patronApi          = $configArray['OverDrive']['patronApiUrl'] ?? 'https://patron.api.overdrive.com';
		$userAgent                = empty($configArray['Catalog']['catalogUserAgent']) ? 'Pika' : $configArray['Catalog']['catalogUserAgent'];
		$this->defaultCurlOptions = [
			CURLOPT_USERAGENT      => $userAgent,
			CURLOPT_CONNECTTIMEOUT => 2,  // A low connect time out prevents Pika from slowing down when there is an Overdrive outage
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER         => false,
			CURLOPT_AUTOREFERER    => true,
			//CURLOPT_SSL_VERIFYPEER => false,
			//CURLOPT_SSL_VERIFYHOST => false,
			//CURLOPT_HEADER         => true, // debugging only
			//CURLOPT_VERBOSE        => true, // debugging only
		];
	}

	/**
	 * Initialize a new Curl Object with default options set.
	 *
	 * @param array $headers Optional headers to set on the curl call
	 * @return Curl;
	 */
	private function initCurlObject($headers = []){
		$curl = new Curl();
		$curl->setOpts($this->defaultCurlOptions);
		$curl->setHeaders($headers);
		return $curl;
	}

	/**
	 * @param false $forceNewConnection
	 * @return false|mixed|null
	 */
	private function _connectToAPI($forceNewConnection = false){
		global $serverName;
		$memCacheKey = 'overdrive_token' . $serverName;
		$tokenData   = $this->cache->get($memCacheKey);
		if (empty($tokenData) || $forceNewConnection){
			$url     = 'https://oauth.overdrive.com/token';
			$headers = $this->_authorizationHeaders($url);
			if ($headers){
				$curl      = $this->initCurlObject($headers);
				$tokenData = $curl->post($url, 'grant_type=client_credentials');
				if (isset($tokenData->access_token)){
					$this->cache->set($memCacheKey, $tokenData, $tokenData->expires_in - 10);
				}else{
					$this->logger->error('Failed to connect to the OverDrive API ', ['overdrive_connect_response' => $tokenData]);
					return false;
				}
			}else{
				// OverDrive is not configured
				return false;
			}
		}
		return $tokenData;
	}

	/**
	 * @param User $user
	 * @param bool $forceNewConnection
	 * @return array|bool|mixed|string
	 */
	private function _connectToPatronAPI($user, $forceNewConnection = false){
		$memCacheKey     = $this->cache->makePatronKey('overdrive_patron_token', $user->id);
		$patronTokenData = $this->cache->get($memCacheKey);
		if (empty($patronTokenData) || $forceNewConnection){

			$tokenData = $this->_connectToAPI($forceNewConnection);
			if ($tokenData){
				global $timer;
				$timer->logTime("Connected to OverDrive API");

				$websiteId = $this->getWebSiteId($user);
				if (!$websiteId){
					return false;
				}

				$ILSName = $this->getILSName($user);
				if (!$ILSName){
					return false;
				}

				$patronBarcode = $user->getBarcode();
				if ($this->getRequirePin($user)){
					$pinProperty = $user->getAccountProfile()->loginConfiguration == 'barcode_pin' ? 'cat_password' : 'cat_username';
					$patronPin   = $user->$pinProperty; // determine which column is the pin by using the opposing field to the barcode. (between pin & username)

					$postFields = "grant_type=password&username={$patronBarcode}&password={$patronPin}&password_required=true&scope=websiteId:{$websiteId}+ilsname:{$ILSName}";
				}else{
					$postFields = "grant_type=password&username={$patronBarcode}&password=ignore&password_required=false&scope=websiteId:{$websiteId}+ilsname:{$ILSName}";
				}

				$url             = 'https://oauth-patron.overdrive.com/patrontoken';
				$headers         = $this->_authorizationHeaders($url);
				$curl            = $this->initCurlObject($headers);
				$patronTokenData = $curl->post($url, $postFields);
				//$curlInfo        = $curl->getInfo(); // for debugging
				$timer->logTime("Logged User {$user->id} into OverDrive API");
				if (isset($patronTokenData->expires_in)){
					$this->cache->set($memCacheKey, $patronTokenData, $patronTokenData->expires_in - 10);
					return $patronTokenData;
				}
				if (isset($patronTokenData->error)){
					if ($patronTokenData->error == 'unauthorized_client'){
						global $configArray;
						if ($configArray['System']['debug']){
							$this->logger->warn('Error connecting to OverDrive patron APIs', ['overdrive_error' => $patronTokenData]);
						}
					}else{
						$this->logger->error('Error connecting to OverDrive patron APIs', ['overdrive_error' => $patronTokenData]);
					}
				}

			}
			return false;
		}
		return $patronTokenData;
	}

	/**
	 * Generate the Request Headers for establishing an authorized connection to the OverDrive APIs
	 *
	 * @param string $url The Authorization URL
	 * @return array|false
	 */
	private function _authorizationHeaders($url){
		global $configArray;
		if (!empty($configArray['OverDrive']['clientKey']) && !empty($configArray['OverDrive']['clientSecret'])){
			$requestAuth = base64_encode($configArray['OverDrive']['clientKey'] . ':' . $configArray['OverDrive']['clientSecret']);
			return [
				'Host'          => parse_url($url, PHP_URL_HOST),
				'Authorization' => 'Basic ' . $requestAuth,
				'Content-Type'  => ' application/x-www-form-urlencoded;charset=UTF-8',
			];
		}
		return false;
	}

	/**
	 * @param $tokenData
	 * @param $url
	 * @return array
	 */
	private function _patronRequestHeaders($tokenData, $url){
		$authorizationData = $tokenData->token_type . ' ' . $tokenData->access_token;
		return [
			'Host'          => parse_url($url, PHP_URL_HOST),
			'Authorization' => $authorizationData,
			'Content-Type'  => 'application/json; charset=utf-8',
		];
	}

	public function _callUrl($url){
		$tokenData = $this->_connectToAPI();
		if ($tokenData){
			$curl      = $this->initCurlObject(['Authorization' => "{$tokenData->token_type} {$tokenData->access_token}"]);
			$returnVal = $curl->get($url);
			if ($returnVal != null){
				if (!isset($returnVal->message) || $returnVal->message != 'An unexpected error has occurred.'){
					return $returnVal;
				}
			}
		}
		return null;
	}

	/**
	 * @param User $user
	 * @return false|string
	 */
	private function getWebSiteId(User $user){
		if (!isset($this->websiteId[$user->id])){
			global $configArray;
			$patronWebsiteIdSetting = $configArray['OverDrive']['patronWebsiteId'];
			if (empty($patronWebsiteIdSetting)){
				return false; //we could get websiteId by fetching info for the user's home library's shared collection setting
			}elseif (strpos($patronWebsiteIdSetting, ',') > 0){
				//Multiple Overdrive Accounts
				$patronWebsiteIds            = explode(',', $patronWebsiteIdSetting);
				$homeLibrary                 = $user->getHomeLibrary();
				$overdriveSharedCollectionId = $homeLibrary->sharedOverdriveCollection;
				// Shared collection Id numbers are negative and based on the order accountIds of $configArray['OverDrive']['accountId']
				// (patron website ids need to have the same matching order)
				$indexOfSiteToUse           = abs($overdriveSharedCollectionId) - 1;
				$this->websiteId[$user->id] = $patronWebsiteIds[$indexOfSiteToUse];
			}else{
				$this->websiteId[$user->id] = $patronWebsiteIdSetting;
			}
		}
		return $this->websiteId[$user->id];
	}

	/**
	 * @param User $user
	 * @return mixed
	 */
	private function getILSName(User $user){
		if (!isset($this->ILSName[$user->id])){
			// use library setting if it has a value. if no library setting, use the configuration setting.
			global $library, $configArray;
			$patronHomeLibrary = $user->getHomeLibrary();
			if (!empty($patronHomeLibrary->overdriveAuthenticationILSName)){
				$this->ILSName[$user->id] = $patronHomeLibrary->overdriveAuthenticationILSName;
			}elseif (!empty($library->overdriveAuthenticationILSName)){
				$this->ILSName[$user->id] = $library->overdriveAuthenticationILSName;
			}elseif (!empty($configArray['OverDrive']['LibraryCardILS'])){
				$this->ILSName[$user->id] = $configArray['OverDrive']['LibraryCardILS'];
			}
		}
		return $this->ILSName[$user->id];
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	private function getRequirePin(User $user){
		if (!isset($this->requirePin[$user->id])){
			// use library setting if it has a value. if no library setting, use the configuration setting.
			global $library, $configArray;
			$patronHomeLibrary           = $user->getHomeLibrary();
			$this->requirePin[$user->id] = $patronHomeLibrary->overdriveRequirePin ?? $library->overdriveRequirePin
				?? $configArray['OverDrive']['requirePin'] ?? false;
		}
		return $this->requirePin[$user->id];
	}

	/**
	 * @param User $user
	 * @param string $url
	 * @param array|null $postParams
	 * @param bool $put Whether or not to do an HTTP PUT request
	 * @return bool|mixed
	 */
	public function _callPatronUrl(User $user, $url, $postParams = null, $put = false){
		$tokenData = $this->_connectToPatronAPI($user);
		if ($tokenData){
			$headers = $this->_patronRequestHeaders($tokenData, $url);
			$curl    = $this->initCurlObject($headers);

			if ($postParams != null){
				//Restructure Array into format expected by the overdrive api
				$jsonData = ['fields' => []];
				foreach ($postParams as $key => $value){
					$jsonData['fields'][] = [
						'name'  => $key,
						'value' => $value,
					];
				}
				$returnVal = $put ? $curl->put($url, $jsonData) : $curl->post($url, $jsonData);
			}else{
				$returnVal = $curl->get($url);
			}
			//$curlInfo = $curl->getInfo(); // for debugging

			if (empty($returnVal)){
				return $curl->httpStatusCode == 204; // Code 204 is success
			}elseif (!isset($returnVal->message) || $returnVal->message != 'An unexpected error has occurred.'){
				return $returnVal;
			}

		}
		return false;
	}

	/**
	 * @param User $user
	 * @param string $url
	 * @return bool|mixed
	 */
	private function _callPatronDeleteUrl(User $user, $url){
		$tokenData = $this->_connectToPatronAPI($user);
		if ($tokenData){
			$headers   = $this->_patronRequestHeaders($tokenData, $url);
			$curl      = $this->initCurlObject($headers);
			$returnVal = $curl->delete($url);
			//$curlInfo  = $curl->getInfo(); // for debugging

			if (empty($returnVal)){
				return $curl->httpStatusCode == 204; // Code 204 is success
			}elseif (!isset($returnVal->message) || $returnVal->message != 'An unexpected error has occurred.'){
				return $returnVal;
			}
		}
		return false;
	}

	/**
	 * Get All the enabled shared OverDrive Collections for this Pika site.
	 *
	 * @return array|false
	 */
	static function getOverDriveAccountIds(){
		global $configArray;
		if (!empty($configArray['OverDrive']['accountId'])){
			$sharedOverdriveCollectionChoices = [];
			$overdriveAccounts                = explode(',', $configArray['OverDrive']['accountId']);
			$sharedCollectionIdNum            = -1; // default shared libraryId for overdrive items
			foreach ($overdriveAccounts as $overdriveAccountId){
				$overdriveAccountId                                       = trim($overdriveAccountId);
				$sharedOverdriveCollectionChoices[$sharedCollectionIdNum] = $overdriveAccountId;
				$sharedCollectionIdNum--;
			}
			return $sharedOverdriveCollectionChoices;
		}
		return false;
	}

	public function getLibraryAccountInformation($overdriveAccountId){
		return $this->_callUrl("https://api.overdrive.com/v1/libraries/$overdriveAccountId");
	}

	public function getAdvantageAccountInformation($overdriveAccountId){
		return $this->_callUrl("https://api.overdrive.com/v1/libraries/$overdriveAccountId/advantageAccounts");
	}

	public function getProductsInAccount($overdriveProductsKey, $productsUrl = null, $start = 0, $limit = 25){
		if ($productsUrl == null){
			$productsUrl = "https://api.overdrive.com/v1/collections/$overdriveProductsKey/products";
		}
		$productsUrl .= "?offset=$start&limit=$limit";
		return $this->_callUrl($productsUrl);
	}

	public function getProductById($overDriveId, $productsKey){
		$productsUrl = "https://api.overdrive.com/v1/collections/$productsKey/products";
		$productsUrl .= "?crossRefId=$overDriveId";
		return $this->_callUrl($productsUrl);
	}

	public function getProductMetadata($overDriveId, $productsKey){
		$overDriveId = strtoupper($overDriveId);
		$metadataUrl = "https://api.overdrive.com/v1/collections/$productsKey/products/$overDriveId/metadata";
		return $this->_callUrl($metadataUrl);
	}

	public function getProductAvailability($overDriveId, $productsKey){
		$availabilityUrl = "https://api.overdrive.com/v2/collections/$productsKey/products/$overDriveId/availability";
		return $this->_callUrl($availabilityUrl);
	}

	public function getProductAvailabilityAlt($overDriveId, $productsKey){
		$availabilityUrl = "https://api.overdrive.com/v2/collections/$productsKey/availability?products=$overDriveId";
		return $this->_callUrl($availabilityUrl);
	}

	private array $checkouts = [];

	/**
	 * Fetch a summary of the OverDrive Checkouts for a User.
	 *
	 * @param User $user
	 * @param bool $forGetOverDriveCounts
	 * @return array
	 */
	public function getOverDriveCheckouts($user, $forGetOverDriveCounts = false){
		if (isset($this->checkouts[$user->id])){
			return $this->checkouts[$user->id];
		}
		$checkedOutTitles = [];
		if ($this->isUserValidForOverDrive($user)){
			$url      = $this->patronApi . '/v1/patrons/me/checkouts';
			$response = $this->_callPatronUrl($user, $url);
			if (isset($response->checkouts)){
				$supplementalTitles = [];
				foreach ($response->checkouts as $curTitle){
					//Load data from api
					$bookshelfItem                   = [];
					$bookshelfItem['checkoutSource'] = 'OverDrive';
					$bookshelfItem['overDriveId']    = $curTitle->reserveId;
					$bookshelfItem['user']           = $user->getNameAndLibraryLabel();
					$bookshelfItem['userId']         = $user->id;
					if (!empty($curTitle->links->bundledChildren)){
						foreach ($curTitle->links->bundledChildren as $supplementalTitle){
							$supplementalTitleId                                      = ltrim(strrchr($supplementalTitle->href, '/'), '/'); // The Overdrive ID of a supplemental title is at the end of the url
							$bookshelfItem['supplementalTitle'][$supplementalTitleId] = [];
							$supplementalTitles[]                                     = $supplementalTitleId;
						}
					}
					if (!$forGetOverDriveCounts){
						$bookshelfItem['expiresOn']        = $curTitle->expires;
						$expirationDate                    = new \DateTime($curTitle->expires);
						$bookshelfItem['dueDate']          = $expirationDate->getTimestamp();
						$checkOutDate                      = new \DateTime($curTitle->checkoutDate);
						$bookshelfItem['checkoutdate']     = $checkOutDate->getTimestamp();
						$bookshelfItem['overdriveRead']    = false;
						$bookshelfItem['isFormatSelected'] = isset($curTitle->isFormatLockedIn) && $curTitle->isFormatLockedIn == 1;
						$bookshelfItem['formats']          = []; //TODO: refactor as downloadableFormats

						// Download options for when a format isn't locked in
						if (!$bookshelfItem['isFormatSelected'] && isset($curTitle->actions->format)){
							$actionFormats = $this->getFormatsFromActionsObject($curTitle->actions);
							if (!empty($actionFormats)){
								$bookshelfItem['formats'] = $actionFormats;
							}
						}

						// Get Online Options and the locked-in formation option
						if (isset($curTitle->formats)){
							foreach ($curTitle->formats as $format){
								$curFormatType = $format->formatType;
								$curFormat     = [
									'formatType' => $curFormatType,
									'name'       => self::FORMAT_MAP[$curFormatType],
								];

								switch ($curFormatType){
									//The cases other than the default are always available to read/listen/watch online regardless of download formats
									case 'ebook-overdrive':
										$bookshelfItem['overdriveRead'] = true;
										break;
									case 'ebook-mediado':
										$bookshelfItem['mediadoRead'] = true;
										break;
									case 'audiobook-overdrive':
										$bookshelfItem['overdriveListen'] = true;
										break;
									case 'video-streaming':
										$bookshelfItem['overdriveVideo'] = true;
										$bookshelfItem['selectedFormat'] = $curFormat;
										break;
									case 'magazine-overdrive':
										$bookshelfItem['overdriveMagazine'] = true;
										$bookshelfItem['selectedFormat']    = $curFormat;
										$bookshelfItem['isFormatSelected']  = true;  // so that the format gets displayed (had to add an exception to the download section to skip magazines)
										break;
									default:
										// Download option for locked in formats (It won't be any of the above)
										//This is how we set the download options
										$bookshelfItem['selectedFormat'] = $curFormat;
//										if (!isset($format->links->self)){
//											// What is the point of this??
//											//TODO: this seems redundant
//											$bookshelfItem['formats'][] = $curFormat;
//										}
										break;
								}
							}
						}

						if (isset($curTitle->actions->earlyReturn)){
							$bookshelfItem['earlyReturn'] = true;
						}

						$overDriveRecord = new OverDriveRecordDriver($bookshelfItem['overDriveId']);
						if ($overDriveRecord->isValid()){
							$bookshelfItem['recordId'] = $overDriveRecord->getUniqueID();
							$groupedWorkId             = $overDriveRecord->getGroupedWorkId();
							if ($groupedWorkId != null){
								$bookshelfItem['groupedWorkId'] = $groupedWorkId;
							}
							// The $bookshelfItem['format'] is used by reading history cron process
							if ($bookshelfItem['isFormatSelected']){
								$bookshelfItem['format'] = $bookshelfItem['selectedFormat']['name'];
							} else{
								$formats                 = $overDriveRecord->getFormats();
								$bookshelfItem['format'] = reset($formats);
							}
							$bookshelfItem['coverUrl']   = $overDriveRecord->getCoverUrl('medium');
							$bookshelfItem['recordUrl']  = $overDriveRecord->getRecordUrl();
							$bookshelfItem['title']      = $overDriveRecord->getTitle();
							$bookshelfItem['author']     = $overDriveRecord->getAuthor();
							$bookshelfItem['linkUrl']    = $overDriveRecord->getLinkUrl();
							$bookshelfItem['ratingData'] = $overDriveRecord->getRatingData();
						}

					}
					$key                    = $bookshelfItem['checkoutSource'] . $bookshelfItem['overDriveId'] . $bookshelfItem['user'];
					$checkedOutTitles[$key] = $bookshelfItem;
				}
				if (!empty($supplementalTitles)){
					foreach ($supplementalTitles as $supplementalTitleId){
						$key               = $bookshelfItem['checkoutSource'] . $supplementalTitleId;
						$supplementalTitle = $checkedOutTitles[$key];
						unset($checkedOutTitles[$key]);
						foreach ($checkedOutTitles as &$checkedOutTitle){
							if (!empty($checkedOutTitle['supplementalTitle']) && in_array($supplementalTitleId, array_keys($checkedOutTitle['supplementalTitle']))){
								$checkedOutTitle['supplementalTitle'][$supplementalTitleId] = $supplementalTitle;
							}
						}
					}
				}
			}
			if (!$forGetOverDriveCounts){
				$this->checkouts[$user->id] = $checkedOutTitles;
			}
		}
		return $checkedOutTitles;
	}

	private function getFormatsFromActionsObject($actionsObject){
		$return = [];
		if ($actionsObject->format->fields[1]->name == 'formatType'){
			//Typically it is the second entry in the array we want, if not do the foreach loop to find it
			$formatField = $actionsObject->format->fields[1];
		}else{
			foreach ($actionsObject->format->fields as $curFieldIndex => $curField){
				if ($curField->name == 'formatType'){
					$formatField = $curField;
					break;
				}
			}
		}
		// The options field has the list of formats
		if (isset($formatField->options)){
			foreach ($formatField->options as $index => $format){
				$return[] = [
					'formatType' => $format,
					'name'       => self::FORMAT_MAP[$format],
				];
			}
		}
		return $return;
	}

	private array $holds = [];

	/**
	 * Fetch a summary of the OverDrive Holds for a User.
	 *
	 * @param User $user
	 * @param bool $forGetOverDriveCounts
	 * @return array
	 */
	public function getOverDriveHolds($user, $forGetOverDriveCounts = false){
		//Cache holds for the user just for this call.
		if (isset($this->holds[$user->id])){
			return $this->holds[$user->id];
		}
		$holds = [
			'available'   => [],
			'unavailable' => []
		];
		if ($this->isUserValidForOverDrive($user)){
			$url      = $this->patronApi . '/v1/patrons/me/holds';
			$response = $this->_callPatronUrl($user, $url);
			if (isset($response->holds)){
				foreach ($response->holds as $curTitle){
					$hold                = [];
					$hold['holdSource']  = 'OverDrive';
					$hold['overDriveId'] = $curTitle->reserveId;
					$hold['user']        = $user->getNameAndLibraryLabel();
					$hold['userId']      = $user->id;
					$hold['available']   = isset($curTitle->actions->checkout);
					if (!$forGetOverDriveCounts){
//				if (!empty($curTitle->emailAddress)){
//					$hold['notifyEmail'] = $curTitle->emailAddress;
//				}
						$datePlaced = strtotime($curTitle->holdPlacedDate);
						if ($datePlaced){
							$hold['create'] = $datePlaced;
						}
						$hold['holdQueueLength']   = $curTitle->numberOfHolds;
						$hold['holdQueuePosition'] = $curTitle->holdListPosition;
						$hold['position']          = $curTitle->holdListPosition;  // this is so that overdrive holds can be sorted by hold position with the IlS holds
						$hold['frozen']            = isset($curTitle->holdSuspension);
						if ($hold['available']){
							$hold['expire'] = strtotime($curTitle->holdExpires);
						}
						if ($hold['frozen']){
							if (isset($curTitle->holdSuspension->suspensionType)){
								$hold['suspensionType'] = ucfirst($curTitle->holdSuspension->suspensionType);
								if ($curTitle->holdSuspension->suspensionType == 'limited'){
									$hold['thawDate'] = strtotime('+' . $curTitle->holdSuspension->numberOfDays . ' days');
								}
							}
						}

						$overDriveRecord    = new OverDriveRecordDriver($hold['overDriveId']);
						$hold['recordId']   = $overDriveRecord->getUniqueID();
						$hold['coverUrl']   = $overDriveRecord->getCoverUrl('medium');
						$hold['recordUrl']  = $overDriveRecord->getRecordUrl();
						$hold['title']      = $overDriveRecord->getTitle();
						$hold['sortTitle']  = $overDriveRecord->getTitle();
						$hold['author']     = $overDriveRecord->getAuthor();
						$hold['linkUrl']    = $overDriveRecord->getLinkUrl();
						$hold['format']     = $overDriveRecord->getFormats();
						$hold['ratingData'] = $overDriveRecord->getRatingData();
					}
					$key = $hold['holdSource'] . $hold['overDriveId'] . $hold['user'];
					if ($hold['available']){
						$holds['available'][$key] = $hold;
					}else{
						$holds['unavailable'][$key] = $hold;
					}

				}
				if (!$forGetOverDriveCounts){
					$this->holds[$user->id] = $holds;
				}
			}
		}
		return $holds;
	}

	/**
	 * Returns counts of the items checked on and on hold on the user's account in OverDrive.
	 *
	 * @param User $user
	 *
	 * @return array
	 */
	public function getOverDriveCounts($user){
		global $configArray;
		global $timer;

		if ($user == false){
			return [
				'numCheckedOut'       => 0,
				'numAvailableHolds'   => 0,
				'numUnavailableHolds' => 0,
			];
		}

		$memCacheKey = $this->cache->makePatronKey('overdrive_counts', $user->id);
		$counts      = $this->cache->get($memCacheKey);
		if ($counts == false || isset($_REQUEST['reload'])){

			//Get account information from api
			$counts                        = [];
			$checkedOutItems               = $this->getOverDriveCheckouts($user, true);
			$counts['numCheckedOut']       = count($checkedOutItems);
			$holds                         = $this->getOverDriveHolds($user, true);
			$counts['numAvailableHolds']   = count($holds['available']);
			$counts['numUnavailableHolds'] = count($holds['unavailable']);

			$timer->logTime("Finished loading titles from overdrive counts");
			$this->cache->set($memCacheKey, $counts, $configArray['Caching']['overdrive_counts']);
		}

		return $counts;
	}

	/**
	 * Places a hold on an title in OverDrive
	 *
	 * @param string $overDriveId
	 * @param User $user
	 * @param string|null $email
	 * @return array (result, message)
	 */
	public function placeOverDriveHold($overDriveId, User $user, $email = null){
		$email    ??= $user->overDriveEmail;
		$result   = [];
		$url      = $this->patronApi . '/v1/patrons/me/holds/' . $overDriveId;
		$params   = [
			'reserveId'    => $overDriveId,
			'emailAddress' => trim($email),
		];
		$response = $this->_callPatronUrl($user, $url, $params);
		if (isset($response->holdListPosition)){
			$result['success'] = true;
			$result['message'] = 'Your hold was placed successfully.  You are number ' . $response->holdListPosition . ' on the wait list.';
		}else{
			$result['success'] = false;
			$result['message'] = 'Sorry, but we could not place a hold for you on this title.';
			if (isset($response->message)){
				$result['message'] .= "  {$response->message}";
			}
		}
		$this->clearCachedOverDriveUserInfo($user);
		return $result;
	}

	/**
	 * Freeze an OverDrive hold.
	 *
	 * @param string $overDriveId
	 * @param User $user
	 * @param string|null $email
	 * @param int|null $daysToSuspend
	 * @return array (result, message)
	 */
	public function freezeOverDriveHold($overDriveId, User $user, $email = null, $daysToSuspend = null){
		$email               ??= $user->overDriveEmail;
		$url                 = $this->patronApi . '/v1/patrons/me/holds/' . $overDriveId . '/suspension';
		$isLimitedSuspension = is_numeric($daysToSuspend);
		$suspensionType      = $isLimitedSuspension ? 'limited' : 'indefinite';
		$params              = [
			'emailAddress'   => trim($email),
			'suspensionType' => $suspensionType,
		];
		if ($isLimitedSuspension){
			$params['numberOfDays'] = $daysToSuspend;
		}

		$response = $this->_callPatronUrl($user, $url, $params);

		$result = [];
		if (isset($response->holdSuspension)){
			$frozen            = translate('frozen');
			$result['success'] = true;
			$result['message'] = "Your hold was $frozen successfully";
			$result['title']   = ucwords($result['message']);
		}else{
			$freeze            = translate('freeze');
			$Freezing          = ucfirst(translate('freezing'));
			$result['success'] = false;
			$result['message'] = "Sorry, but we could not $freeze your hold for you on this title.";
			$result['title']   = "Error $Freezing OverDrive Hold";
			if (isset($response->message)){
				$result['message'] .= "  {$response->message}";
			}
		}
		$this->clearCachedOverDriveUserInfo($user);
		return $result;
	}

	/**
	 * Cancel an OverDrive Hold.
	 *
	 * @param string $overDriveId
	 * @param User $user
	 * @return array
	 */
	public function cancelOverDriveHold($overDriveId, User $user){
		$url      = $this->patronApi . '/v1/patrons/me/holds/' . $overDriveId;
		$response = $this->_callPatronDeleteUrl($user, $url);
		$result   = [
			'success' => false,
			'message' => '',
		];
		if ($response === true){
			$result['success'] = true;
			$result['message'] = 'Your hold was cancelled successfully.';
		}else{
			$result['message'] = 'There was an error cancelling your hold.';
			if (isset($response->message)){
				$result['message'] .= "  {$response->message}";
			}
		}
		$this->clearCachedOverDriveUserInfo($user);
		return $result;
	}


	/**
	 * Thaw an OverDrive hold, or end a hold suspension.
	 *
	 * @param string $overDriveId
	 * @param User $user
	 * @return array
	 */
	public function thawOverDriveHold($overDriveId, User $user){
		$url      = $this->patronApi . '/v1/patrons/me/holds/' . $overDriveId . '/suspension';
		$response = $this->_callPatronDeleteUrl($user, $url);
		$result   = [
			'success' => false,
			'message' => '',
		];
		if ($response === true){
			$result['success'] = true;
			$result['message'] = 'Your hold was thawed successfully.';
		}else{
			$result['message'] = 'There was an error thawing your hold.';
			if (isset($response->message)){
				$result['message'] .= "  {$response->message}";
			}
		}
		$this->clearCachedOverDriveUserInfo($user);
		return $result;
	}

	/**
	 * Check out an OverDrive title.
	 *
	 * @param string $overDriveId
	 * @param User $user
	 * @param int|null $lendingPeriod
	 * @param string|null $formatType
	 * @return array results [success, message]
	 */
	public function checkoutOverDriveTitle($overDriveId, User $user, $lendingPeriod = null, $formatType = null){
		$url    = $this->patronApi . '/v1/patrons/me/checkouts';
		$params = ['reserveId' => $overDriveId];

		if (!empty($lendingPeriod)){
			$params['lendingPeriod'] = $lendingPeriod;
			$params['units']         = 'days';
		}
		if (!empty($formatType)){
			$params['formatType'] = $formatType;
		}

		$response = $this->_callPatronUrl($user, $url, $params);
		if (isset($response->expires)){
			// Successful checkout
			$result['success'] = true;
			$result['message'] = 'Your title was checked out successfully. You may now view the title in your account.';
			if (count($response->formats) == 1){
				//This should be the read online option
				$result['formatType'] = $response->formats[0]->formatType;
			}
		}else{
			$result['success'] = false;
			$result['message'] = 'Sorry, we could not checkout this title to you.'; // add pre-amble to error messages
			if (!empty($response->errorCode)){
				switch ($response->errorCode){
					case 'PatronHasExceededCheckoutLimit' :
						$result['message'] .= "\r\n\r\nYou have reached the maximum number of OverDrive titles you can checkout one time.";
						break;
					case 'NoCopiesAvailable' :
						$result['noCopies'] = true;
						$result['message']  .= "\r\n\r\nWould you like to place a hold instead?";
						break;
					case 'TitleAlreadyCheckedOut' :
						$result['alreadyCheckedOut'] = true;
						break;
					default :
						if (isset($response->message)){
							$result['message'] .= "\r\n\r\n {$response->message}";
						}
				}
			}else{
				//Give more information about why it might gave failed, ie expired card or too much fines
				$this->logger->error('Unexpected response from OverDrive checkout call: ' . var_export($response, true));
				if (isset($response->message)){
					$result['message'] .= "\r\n\r\n  {$response->message}";
				}else{
					$result['message']      = "Unknown Error <br><br>" . $result['message'] . " Attempt to place a hold instead?.";
					$result['promptNeeded'] = true;
					$result['buttons']      = '<input class="btn btn-primary" type="submit" name="submit" value="Place Hold OverDrive" onclick="return Pika.OverDrive.placeOverDriveHold(\'' . $overDriveId . '\');">';
				}
			}
		}

		$this->clearCachedOverDriveUserInfo($user);
		return $result;
	}

	/**
	 *  Return the User's checked out title early.
	 *
	 * @param string $overDriveId
	 * @param User $user
	 * @return array
	 */
	public function returnOverDriveItem($overDriveId, User $user){
		$url      = $this->patronApi . '/v1/patrons/me/checkouts/' . $overDriveId;
		$response = $this->_callPatronDeleteUrl($user, $url);
		$result   = [
			'success' => false,
			'message' => '',
		];
		if ($response === true){
			$result['success'] = true;
			$result['message'] = 'Your item was returned successfully.';
		}else{
			$result['message'] = 'There was an error returning this item.';
			if (isset($response->message)){
				$result['message'] .= "  {$response->message}";
			}
		}

		$this->clearCachedOverDriveUserInfo($user);
		return $result;
	}

	/**
	 * Set the format to download for the User. The User can only choose one format for download per checkout.
	 *
	 * @param string $overDriveId
	 * @param string $formatType
	 * @param User $user
	 * @return mixed
	 */
	public function selectOverDriveDownloadFormat($overDriveId, $formatType, User $user){
		$url      = $this->patronApi . '/v1/patrons/me/checkouts/' . $overDriveId . '/formats';
		$params   = [
			'reserveId'  => $overDriveId,
			'formatType' => $formatType,
		];
		$response = $this->_callPatronUrl($user, $url, $params);
		if (isset($response->linkTemplates->downloadLink)){
			$result['success'] = true;
			$result['message'] = 'This format was locked in';
			$downloadLink      = $this->getDownloadLink($overDriveId, $formatType, $user);
			$result            = $downloadLink;
		}else{
			$result['success'] = false;
			$result['message'] = 'Sorry, but we could not select a format for you.';
			if (isset($response->message)){
				$result['message'] .= "  {$response->message}";
			}
		}
		$this->clearCachedOverDriveUserInfo($user);
		return $result;
	}

	/**
	 * Check if OverDrive considers the user valid
	 *
	 * @param User $user
	 * @return bool
	 */
	public function isUserValidForOverDrive(User $user){
		global $timer;
		$tokenData = $this->_connectToPatronAPI($user);
		$timer->logTime("Checked to see if the user {$user->id} is valid for OverDrive");
		return !empty($tokenData) && !array_key_exists('error', $tokenData);
	}

	/**
	 * Ask the OverDrive API to generate the content download link for the User.
	 * This needs to be delivered to the User at the moment of the request for download
	 * because the content links will expire within a minute.
	 *
	 * @param string $overDriveId
	 * @param string $format
	 * @param User $user
	 * @return array
	 */
	public function getDownloadLink($overDriveId, $format, User $user){
		global $configArray;

		$errorUrl = urlencode($configArray['Site']['url'] . "/OverDrive/$overDriveId/eContentSupport");
		$url      = $this->patronApi . "/v1/patrons/me/checkouts/{$overDriveId}/formats/{$format}/downloadlink";
		$url      .= '?errorurl=' . $errorUrl;
		switch ($format){
			case 'ebook-overdrive':
			case 'audiobook-overdrive':
			case 'ebook-mediado':
				$url .= '&odreadauthurl=' . $errorUrl;
				break;
			case 'video-streaming':
				$url .= '&streamingauthurl=' . $errorUrl;
				break;
		}

		$response = $this->_callPatronUrl($user, $url);
		if (isset($response->links->contentlink)){
			$result['success']     = true;
			$result['message']     = 'Created Download Link';
			$result['downloadUrl'] = $response->links->contentlink->href;
		}else{
			$result['success'] = false;
			$result['message'] = 'Sorry, but we could not get a download link for you.';
			if (isset($response->message)){
				$result['message'] .= "  {$response->message}";
			}
		}

		return $result;
	}

	/**
	 * Fetch the User's OverDrive settings from the API
	 *
	 * @param User $user
	 * @return array
	 */
	public function getUserOverDriveAccountSettings(User $user){
		$url      = $this->patronApi . '/v1/patrons/me/';
		$response = $this->_callPatronUrl($user, $url);
		$result   = [];
		if (empty($response->existingPatron)){
			$this->logger->error('Failed to fetch user settings from OverDrive. ' . ($response->message ?? ''));
		}else{
			$result['overDriveWebsite'] = 'https://link.overdrive.com/?websiteID=' . $response->websiteId;
			$result['holdLimit']        = $response->holdLimit;
			$result['checkoutLimit']    = $response->checkoutLimit;
			foreach ($response->lendingPeriods as $lendingPeriod){
				// Set the format Type as the index for the array
				$result['lendingPeriods'][$lendingPeriod->formatType] = $lendingPeriod;
			}
			foreach ($response->actions as $lendingPeriodAction){
				// Navigate the edit lending period Actions JSON to find and assign the available lending options to each format class
				// currently: eBook, Audiobook, Video
				$formatClass       = '';
				$lendingPeriodDays = [];
				foreach ($lendingPeriodAction->editLendingPeriod->fields as $editPeriodFields){
					if (isset($editPeriodFields->value)){
						$formatClass = $editPeriodFields->value;
					}elseif (isset($editPeriodFields->options)){
						$lendingPeriodDays = $editPeriodFields->options;
					}
				}
				if (!empty($lendingPeriodDays) && isset($result['lendingPeriods'][$formatClass])){
					// Now add the options array to the lendingPeriods array
					$result['lendingPeriods'][$formatClass]->options = $lendingPeriodDays;
				}
			}
			global $configArray;
			$memCacheKey = $this->cache->makePatronKey('overdrive_settings', $user->id);
			$this->cache->set($memCacheKey, $result, $configArray['Caching']['overdrive_counts']);
		}
		return $result;
	}

	/**
	 * Update the User's default lending period setting for the formatClass (eBook, Audiobook, Video).
	 *
	 * @param User $user
	 * @param string $formatClass
	 * @param int $lendingPeriodDays
	 * @return bool|mixed
	 */
	public function updateLendingPeriod(User $user, $formatClass, $lendingPeriodDays){
		$url     = $this->patronApi . '/v1/patrons/me/';
		$params  = [
			'formatClass'       => $formatClass,
			'lendingPeriodDays' => $lendingPeriodDays,
		];
		$success = $this->_callPatronUrl($user, $url, $params, true);
		if ($success !== true){
			$this->logger->error('Failed to update user lending period setting in OverDrive for user ' . $user->id, ['update_overdrive_lending_period' => $success]);
		} else {
			$memCacheKey = $this->cache->makePatronKey('overdrive_settings', $user->id);
			$this->cache->delete($memCacheKey);
		}
		return $success;
	}

	/**
	 * Remove cached counts of the User's holds and checkouts.
	 *
	 * @param User $user
	 */
	private function clearCachedOverDriveUserInfo(User $user){
		$memCacheKey = $this->cache->makePatronKey('overdrive_counts', $user->id);
		$this->cache->delete($memCacheKey);
		$user->clearCache();
	}

}
