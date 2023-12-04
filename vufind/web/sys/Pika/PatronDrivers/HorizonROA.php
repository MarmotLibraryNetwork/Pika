<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Horizon ROA web services driver.
 *
 * @category Pika
 * @author   Pascal Brammeier
 * @author   Chris Froese
 *
 */

namespace Pika\PatronDrivers;

use User;
use \Pika\Logger;
use \Pika\Cache;

abstract class HorizonROA implements \DriverInterface {
	private static $sessionIdsForUsers = [];
	private $webServiceURL;
	private $clientId;

	/** @var  \AccountProfile $accountProfile */
	public $accountProfile;
	private Cache $cache;
	protected Logger $logger;
	public function __construct($accountProfile){
		global $configArray;
		$this->clientId       = $configArray['Catalog']['clientId'];
		$this->accountProfile = $accountProfile;
		$cache                = initCache();
		$this->cache          = new Cache($cache);
		$this->logger         = new Logger(__CLASS__);
	}

	/**
	 * @return Logger
	 */
	function getLogger(){
		if (!isset($this->logger)){
			$this->logger = new Logger(__CLASS__);
		}
		return $this->logger;
	}

	/**
	 * Split a name into lastName, firstName.
	 *
	 * Assumes the name is entered as LastName, FirstName MiddleName
	 * @param $fullName
	 * @return array
	 */
	public function splitFullName($fullName){
		$nameParts = explode(',', $fullName);
		$lastName  = strtolower($nameParts[0]);
		$firstName = isset($nameParts[1]) ? strtolower(trim($nameParts[1])) : '';
		return [$lastName, $firstName];
	}

	public function getWebServiceURL(){
		if (empty($this->webServiceURL)){
			$webServiceURL = null;
			if (!empty($this->accountProfile->patronApiUrl)){
				$webServiceURL = trim($this->accountProfile->patronApiUrl);
			}elseif (!empty($configArray['Catalog']['webServiceUrl'])){
				$webServiceURL = $configArray['Catalog']['webServiceUrl'];
				$this->getLogger()->warning('Using ini setting webServiceUrl. Account Profile should have the web service url instead');
			}else{
				$this->getLogger()->critical('No Web Service URL defined in Horizon ROA API Driver');
			}
			$this->webServiceURL = rtrim($webServiceURL, '/');
			// remove any trailing slash because other functions will add it.
		}
		return $this->webServiceURL;
	}

	/**
	 * @param string $apiCall           The Specific call to build the URL with-- URL endpoint
	 * @param null $params               Any call parameters needed
	 * @param null $sessionToken        The API sessionToken for the logged in user
	 * @param string|null $customRequest is for curl, can be 'PUT', 'DELETE', 'POST'
	 * @param null $additionalHeaders    Any request headers to include with the standard request headers
	 * @return false|mixed
	 */
	public function getWebServiceResponse(string $apiCall, $params = null, $sessionToken = null, $customRequest = null, $additionalHeaders = null){
		$url = $this->getWebServiceURL() . $apiCall;
		$this->getLogger()->info('WebServiceURL : ' . $url);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		$headers  = [
			'Accept: application/json',
			'Content-Type: application/json',
			'SD-Originating-App-Id: Pika',
			'x-sirs-clientID: ' . $this->clientId,
		];
		if ($sessionToken != null){
			$headers[] = 'x-sirs-sessionToken: ' . $sessionToken;
		}
		if (!empty($additionalHeaders) && is_array($additionalHeaders)){
			$headers = array_merge($headers, $additionalHeaders);
		}
		if (empty($customRequest)){
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		}elseif ($customRequest == 'POST'){
			curl_setopt($ch, CURLOPT_POST, true);
		}else{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customRequest);
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		//global $instanceName;
/*		if (stripos($instanceName, 'localhost') !== false){
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);     //
		}*/
		if ($params != null){
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		}
		$json = curl_exec($ch);


/*		if (stripos($instanceName, 'localhost') !== false){
			$err           = curl_getinfo($ch);
			$headerRequest = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		}*/

		$this->getLogger()->debug("Web service response\r\n$json");
		global $timer;

		curl_close($ch);

		if ($json !== false && $json !== 'false'){
			return json_decode($json);
		}else{
			$this->getLogger()->warn('Curl problem in getWebServiceResponse');
			return false;
		}
	}

	/**
	 * Log in a user via the Horizon ROA web service.
	 *
	 * This function attempts to log in a user by making a request to the Horizon ROA web service login endpoint.
	 *
	 * @param string $barcode  The barcode associated with the user.
	 * @param string $password The password for the user.
	 *
	 * @return array An array containing login information. The array has three elements:
	 *               - [0] (bool): Indicates if the login is valid (true) or not (false).
	 *               - [1] (mixed): The session token if the login was successful, otherwise false.
	 *               - [2] (mixed): The user ID if the login was successful, otherwise false.
	 *
	 * @throws Exception If there is an error in processing the web service response.
	 */
	protected function loginViaWebService($barcode, $password) {
		$memCacheKey = "horizon_ROA_session_token_info_$barcode";
		$session     = $this->cache->get($memCacheKey);

		if ($session){
			return $session;
		}
		// $loginDescribeResponse = $this->getWebServiceResponse( '/user/patron/login/describe');
		$session           = [false, false, false];
		$loginUserUrl      =  '/user/patron/login';
		$params            = [
			'barcode'  => $barcode,
			'password' => $password,
		];
		$loginUserResponse = $this->getWebServiceResponse($loginUserUrl, $params);
		if (!empty($loginUserResponse->sessionToken)) {
			//We got at valid user (A bad call will have isset($loginUserResponse->messageList) )
			$horizonRoaUserID                            = $loginUserResponse->patronKey;
			$sessionToken                                = $loginUserResponse->sessionToken;

			$session = [true, $sessionToken, $horizonRoaUserID];
			global $configArray;
			$this->cache->set($memCacheKey, $session, $configArray['Caching']['horizon_roa_session_token']);
		} elseif (isset($loginUserResponse->messageList)) {
			$errorMessage = 'Horizon ROA Webservice Login Error: ';
			foreach ($loginUserResponse->messageList as $error){
				$errorMessage .= $error->message.'; ';
			}
			$this->getLogger()->error($errorMessage);
		}

		return $session;
	}

	private function getSessionToken(User $patron){
		$barcode = $patron->barcode;
		$memCacheKey = "horizon_ROA_session_token_info_$barcode";
		$session     = $this->cache->get($memCacheKey);

		//Get the session token for the user
		if ($session){
			return $session[1];
		}else{
			[, $sessionToken] = $this->loginViaWebService($patron->barcode, $patron->getPassword());
			return $sessionToken;
		}
	}

	/**
	 * Delete the session token associated with a user.
	 *
	 * This function removes the session token for a given user from the internal storage.
	 *
	 * @param User $patron The User object for which to delete the session token.
	 *
	 * @return void
	 */
	private function deleteRoaSessionToken(User $patron): void
	{
		$barcode = $patron->barcode;
		$memCacheKey = "horizon_ROA_session_token_info_$barcode";
		$r = $this->cache->delete($memCacheKey);
	}

	/**
	 * @param $barcode
	 * @param $password
	 * @param $validatedViaSSO
	 * @return false|User
	 */
	public function patronLogin($barcode, $password, $validatedViaSSO){
		//TODO: check which login style in use. Right now assuming barcode_pin
		$barcode  = preg_replace('/[\s]/', '', $barcode); // remove all space characters
		$password = trim($password);

		//Authenticate the user via WebService
		//First call loginUser
		global $timer;
		$timer->logTime('Logging in through Horizon ROA APIs');
		[$userValid, $sessionToken, $horizonRoaUserID] = $this->loginViaWebService($barcode, $password);
		if ($validatedViaSSO) {
			$userValid = true;
		}
		if ($userValid) {
			$timer->logTime('User is valid in horizon');

			$userExistsInDB = false;
			/** @var User $user */
			$user            = new User();
			$user->source    = $this->accountProfile->name;
			$user->ilsUserId = $horizonRoaUserID;
			if ($user->find(true)) {
				$userExistsInDB = true;
				$patronObjectCacheKey = $this->cache->makePatronKey('patron', $user->id);
				if ($userObject = $this->cache->get($patronObjectCacheKey)) {
					$this->getLogger()->info('Found patron in memcache:' . $patronObjectCacheKey);
					return $userObject;
				}
			}

			// Calls that show how patron-related data is represented
			// $patronDescribeResponse = $this->getWebServiceResponse( '/user/patron/describe', null, $sessionToken);
			// $patronDSearchescribeResponse = $this->getWebServiceResponse( '/user/patron/search/describe', null, $sessionToken)
			// $patronSearchResponse = $this->getWebServiceResponse( '/user/patron/search', array('q' => 'borr|2:22046027101218'), $sessionToken);
			// $patronTypesQuery = $this->getWebServiceResponse( '/policy/patronType/simpleQuery?key=*&includeFields=*', null, $sessionToken);

			$includeFields = urlencode('displayName,privilegeExpiresDate,primaryAddress,primaryPhone,library,'
				. 'patronType,holdRecordList{status},circRecordList,blockList{amount,owed}');
			$acountInfoLookupURL =  '/user/patron/key/' . $horizonRoaUserID . '?includeFields=' .$includeFields;

			// get a new token if the session has timed out
			$lookupMyAccountInfoResponse = $this->getWebServiceResponse($acountInfoLookupURL, null, $sessionToken);
			if (isset($lookupMyAccountInfoResponse->messageList[0]->code) && $lookupMyAccountInfoResponse->messageList[0]->code === 'sessionTimedOut') {
				// remove old cache key and get a new one
				$memCacheKey = "horizon_ROA_session_token_info_$barcode";
				$this->cache->delete($memCacheKey);
				[$userValid, $sessionToken, $horizonRoaUserID] = $this->loginViaWebService($barcode, $password);
				$lookupMyAccountInfoResponse = $this->getWebServiceResponse($acountInfoLookupURL, null, $sessionToken);
			}

			if ($lookupMyAccountInfoResponse && !isset($lookupMyAccountInfoResponse->messageList)) {
				$fullName = $lookupMyAccountInfoResponse->fields->displayName;
				if (strpos($fullName, ',')) {
					[$lastName, $firstName] = $this->splitFullName($fullName);
				}

				$forceDisplayNameUpdate = false;
				$firstName              = $firstName ?? '';
				if ($user->firstname != $firstName) {
					$user->firstname        = $firstName;
					$forceDisplayNameUpdate = true;
				}
				$lastName = $lastName ?? '';
				if ($user->lastname != $lastName) {
					$user->lastname         = $lastName ?? '';
					$forceDisplayNameUpdate = true;
				}
				if ($forceDisplayNameUpdate) {
					$user->displayName = '';
				}
				$user->fullname     = $fullName ?? '';
				$user->barcode      = $barcode;
				// update password if not a match
				if($password != $user->getPassword()) {
					$user->updatePassword($password);
				} else{
					$user->setPassword($password);
				}

				$Address1    = "";
				$City        = "";
				$State       = "";
				$zipCode     = "";
				if (isset($lookupMyAccountInfoResponse->fields->primaryAddress)) {
					$preferredAddress = $lookupMyAccountInfoResponse->fields->primaryAddress->fields;
					// Set for Account Updating
					//$cityState = $preferredAddress->area;

					if (!empty($preferredAddress->area)){
						if ($preferredAddress->area == 'other'){
							$this->getLogger()->debug("Horizon User address area is 'other'.", ['APIresponse' => json_encode($lookupMyAccountInfoResponse)]);
							if (!empty($preferredAddress->address3)){
								$cityState = $preferredAddress->address3;
							}elseif (!empty($preferredAddress->line3)){
								$cityState = $preferredAddress->line3;
							}
						} else {
							$cityState = $preferredAddress->area;
						}
					}elseif (!empty($preferredAddress->line3)){
						$cityState = $preferredAddress->line3;
					}elseif (!empty($preferredAddress->address3)){
						$cityState = $preferredAddress->address3;
					}

					if (strpos($cityState, ', ')) {
						[$City, $State] = explode(', ', $cityState);
					} else {
						$this->getLogger()->warn("Bad Horizon User CityState string '$cityState'", ['APIresponse' => json_encode($lookupMyAccountInfoResponse)]);
					}
					$Address1 = $preferredAddress->line1;
					if (!empty($preferredAddress->line2)){
						//apt number
						$Address1 .= ' '. $preferredAddress->line2;
					}
					$zipCode = $preferredAddress->postalCode;

					$user->email = $preferredAddress->emailAddress ?? null;
				}

				$user->phone = $lookupMyAccountInfoResponse->fields->primaryPhone ?? null;
				$pType       = $lookupMyAccountInfoResponse->fields->patronType->key ?? 0;

				//Get additional information about the patron's home branch for display.
				if (isset($lookupMyAccountInfoResponse->fields->library->key)) {
					$user->setUserHomeLocations($lookupMyAccountInfoResponse->fields->library->key);
				} else {
					$this->getLogger()->error('No Home Library Location or Hold location found in account look-up. User : ' . $user->id);
					// The code below will attempt to find a location for the library anyway if the homeLocation is already set
				}

				$dateString = '';
				if (isset($lookupMyAccountInfoResponse->fields->privilegeExpiresDate)) {
					[$yearExp, $monthExp, $dayExp] = explode('-', $lookupMyAccountInfoResponse->fields->privilegeExpiresDate);
					$dateString = $monthExp . '/' . $dayExp . '/' . $yearExp;
				}
				$user->setUserExpirationSettings($dateString);

				//Get information about fines
				$finesVal = 0;

				if (count($lookupMyAccountInfoResponse->fields->blockList) >= 1) {
					foreach ($lookupMyAccountInfoResponse->fields->blockList as $patronBlock) {
						$block = $patronBlock->fields;
							$fineAmount = (float) $block->owed->amount;
							$finesVal   += $fineAmount;
					}
				}

				$numHolds          = 0;
				$numHoldsAvailable = 0;
				$numHoldsRequested = 0;
				if (count($lookupMyAccountInfoResponse->fields->holdRecordList) >= 1) {
					$numHolds = count($lookupMyAccountInfoResponse->fields->holdRecordList);
					foreach ($lookupMyAccountInfoResponse->fields->holdRecordList as $patronHold) {
						$hold = $patronHold->fields;

							if ($hold->status == 'BEING_HELD') {
								$numHoldsAvailable++;
							} elseif ($hold->status != 'EXPIRED') {
								$numHoldsRequested++;
							}
						}
					}

				$numCheckedOut = 0;
				if (!empty($lookupMyAccountInfoResponse->fields->circRecordList)) {
					$numCheckedOut = count($lookupMyAccountInfoResponse->fields->circRecordList);
				}

				$user->address1              = $Address1;
				$user->address2              = $City . ', ' . $State; //TODO: Is there a reason to do this?
				$user->city                  = $City;
				$user->state                 = $State;
				$user->zip                   = $zipCode;
				$user->fines                 = sprintf('$%01.2f', $finesVal);
				$user->finesVal              = $finesVal;
				$user->numCheckedOutIls      = $numCheckedOut;
				$user->numHoldsIls           = $numHolds;
				$user->numHoldsAvailableIls  = $numHoldsAvailable;
				$user->numHoldsRequestedIls  = $numHoldsRequested;
				$user->patronType            = $pType;
				$user->notices               = '-';
				$user->noticePreferenceLabel = 'E-mail';
				$user->webNote               = '';

				if ($userExistsInDB) {
					$user->update();
					// update password if needed
					if($password != $user->getPassword()) {
						$user->updatePassword($password);
					}
				} else {
					$user->created = date('Y-m-d');
					$user->insert();
					// update password after user is created
					$user->updatePassword($password);
				}

				if(isset($user->id)) {
					global $configArray;
					$patronObjectCacheKey = $this->cache->makePatronKey('patron', $user->id);
					$this->logger->debug('Saving patron to memcache:' . $patronObjectCacheKey);
					$this->cache->set($patronObjectCacheKey, $user, $configArray['Caching']['user']);
				}

				$timer->logTime('patron logged in successfully');
				return $user;
			} else {
				if (isset($lookupMyAccountInfoResponse->messageList[0]->code) && $lookupMyAccountInfoResponse->messageList[0]->code == 'sessionTimedOut') {
					//If it was just a session timeout, just clear out the session
					$memCacheKey = "horizon_ROA_session_token_info_$barcode";
					$this->cache->delete($memCacheKey);
				} else {
					$timer->logTime('lookupMyAccountInfo failed');
					$this->getLogger()->error('Horizon ROA API call lookupMyAccountInfo failed.');
				}
				return false;
			}
		}
	}


	public function hasNativeReadingHistory(){
		return false;
	}

	/**
	 * Return the number of holds that are on a record
	 * @param  string|int $bibId
	 * @return bool|int
	 */
	public function getNumHoldsOnRecord($bibId){
		// TODO: make ROA call
		//This uses the standard / REST method to retrieve this information from the ILS.
		// It isn't an ROA call.
		$lookupTitleInfoUrl      = '/rest/standard/lookupTitleInfo?titleKey=' . $bibId . '&includeItemInfo=false&includeHoldCount=true';
		$lookupTitleInfoResponse = $this->getWebServiceResponse($lookupTitleInfoUrl);
		if (!empty($lookupTitleInfoResponse->titleInfo)){
			if (is_array($lookupTitleInfoResponse->titleInfo) && isset($lookupTitleInfoResponse->titleInfo[0]->holdCount)){
				return (int)$lookupTitleInfoResponse->titleInfo[0]->holdCount;
			}elseif (isset($lookupTitleInfoResponse->titleInfo->holdCount)){
				//TODO: I suspect that this never occurs
				return (int)$lookupTitleInfoResponse->titleInfo->holdCount;
			}
		}
		return false;
	}

	/**
	 * Get Patron Transactions
	 *
	 * This is responsible for retrieving all transactions (i.e. checked out items)
	 * by a specific patron.
	 *
	 * @param User $patron The user to load transactions for
	 *
	 * @return array        Array of the patron's transactions on success
	 * @access public
	 */
	public function getMyCheckouts($patron){
		$checkedOutTitles = [];

		//Get the session token for the user
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return $checkedOutTitles;
		}

		// Now that we have the session token, get checkout  information
		//Get a list of checkouts for the user
		$includeFields   = urlencode('circRecordList{checkOutDate,dueDate,overdue,renewalCount,checkOutFee,'
		. 'item{bib{title,titleHoldCount,holdRecordList},barcode,itemType}}');
		$patronCheckouts = $this->getWebServiceResponse('/user/patron/key/' . $patron->ilsUserId . '?includeFields=' . $includeFields, null, $sessionToken);

		if (empty($patronCheckouts->fields->circRecordList)){
			return $checkedOutTitles;
		}

		require_once ROOT_DIR . '/RecordDrivers/Factory.php';
		//$time_start = microtime(true); // time
		foreach ($patronCheckouts->fields->circRecordList as $circRecord){
			$checkOutKey = $circRecord->key;

			$itemId       = $circRecord->fields->item->key;
			$bibId        = $circRecord->fields->item->fields->bib->key;
			$barcode      = $circRecord->fields->item->fields->barcode;
			$itemType     = $circRecord->fields->item->fields->itemType->key;
			$dueDate      = $circRecord->fields->dueDate;
			$checkOutDate = $circRecord->fields->checkOutDate;
			$fine         = $circRecord->fields->checkOutFee->amount;
			if (!empty($fine) && (float)$fine <= 0){
				// handle case of string '0.00'
				$fine = null;
			}
			if (!empty($bibId)){
				$curTitle                    = [];
				$curTitle['checkoutSource']  = $this->accountProfile->recordSource;
				$curTitle['recordId']        = $bibId;
				$curTitle['shortId']         = $bibId;
				$curTitle['id']              = $bibId;
				$curTitle['itemid']          = $itemId;
				$curTitle['barcode']         = $barcode;
				$curTitle['renewIndicator']  = $itemId;
				$curTitle['dueDate']         = strtotime($dueDate);
				$curTitle['checkoutdate']    = strtotime($checkOutDate);
				$curTitle['renewCount']      = $circRecord->fields->renewalCount;
				$curTitle['canrenew']        = $this->canRenew($itemType);
				$curTitle['format']          = 'Unknown';                    //TODO: I think this makes sorting working better
				$curTitle['overdue']         = $circRecord->fields->overdue; // (optional) CatalogConnection method will calculate this based on due date
				$curTitle['fine']            = $fine;
				$curTitle['holdQueueLength'] = $circRecord->fields->item->fields->bib->fields->titleHoldCount;

				$recordDriver = \RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $bibId);
				if ($recordDriver->isValid()){
					$curTitle['coverUrl']      = $recordDriver->getBookcoverUrl('medium');
					$curTitle['groupedWorkId'] = $recordDriver->getGroupedWorkId();
					$curTitle['format']        = $recordDriver->getPrimaryFormat();
					$curTitle['title']         = $recordDriver->getTitle();
					$curTitle['title_sort']    = $recordDriver->getSortableTitle();
					$curTitle['author']        = $recordDriver->getPrimaryAuthor();
					$curTitle['link']          = $recordDriver->getLinkUrl();
					$curTitle['ratingData']    = $recordDriver->getRatingData();
				}else{
					// If we don't have good marc record, ask the ILS for title info
					[$title, $author] = $this->getTitleAuthorForBib($bibId, $patron);
					$simpleSortTitle        = preg_replace('/^The\s|^A\s/i', '', $title); // remove beginning The or A
					$curTitle['title']      = $title;
					$curTitle['title_sort'] = empty($simpleSortTitle) ? $title : $simpleSortTitle;
					$curTitle['author']     = $author;
				}

				$checkedOutTitles[] = $curTitle;

			}
		}
		//$time_end = microtime(true); // time
		//$time = $time_end - $time_start; // time
		//$this->logger->warn('Build checkouts finished in '.$time);
		return $checkedOutTitles;
	}

	/**
	 * @return boolean true if the driver can renew all titles in a single pass
	 */
	public function hasFastRenewAll(){
		return false;
	}

	/**
	 * Renew all titles currently checked out to the user
	 *
	 * @param $patron  User
	 * @return array
	 */
	public function renewAll($patron){
		return [
			'success' => false,
			'message' => 'Renew All not supported directly, call through Catalog Connection',
		];
	}

	/**
	 * Renew a single title currently checked out to the user
	 *
	 * @param $patron     User
	 * @param $recordId   string
	 * @param $itemId     string
	 * @param $itemIndex  string
	 * @return array
	 */
	public function renewItem($patron, $recordId, $itemId, $itemIndex = null){
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return [
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again'
			];
		}

		$params = [
			'item' => [
				'key'      => $itemId,
				'resource' => '/catalog/item'
			]
		];

		$renewCheckOutResponse = $this->getWebServiceResponse( '/circulation/circRecord/renew', $params, $sessionToken, 'POST');
		if (!empty($renewCheckOutResponse->circRecord)){
			return [
				'itemId'  => $itemId,
				'success' => true,
				'message' => 'Your item was successfully renewed.'
			];
		}elseif (isset($renewCheckOutResponse->messageList)){
			$messages = [];
			foreach ($renewCheckOutResponse->messageList as $message){
				$messages[] = $message->message;
			}
			$errorMessage = 'Horizon ROA Renew Item Error: ' . ($messages ? implode('; ', $messages) : '');
			$this->getLogger()->error($errorMessage);

			return [
				'itemId'  => $itemId,
				'success' => false,
				'message' => 'Failed to renew item : ' . implode('; ', $messages)
			];
		}else{
			return [
				'itemId'  => $itemId,
				'success' => false,
				'message' => 'Failed to renew item : Unknown error'
			];
		}

	}

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron The user to load transactions for
	 *
	 * @return array          Array of the patron's holds
	 * @access public
	 */
	public function getMyHolds($patron){
		$availableHolds   = [];
		$unavailableHolds = [];
		$holds            = [
			'available'   => $availableHolds,
			'unavailable' => $unavailableHolds
		];

		//Get the session token for the user
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return $holds;
		}

		//Get a list of holds for the user
		$logger = $this->getLogger();
		$logger->info('Using bracket notation to fetch patron holds');
		$includeFields = urlencode('holdRecordList{suspendEndDate,fillByDate,queuePosition,status,expirationDate,"
		. "item{barcode,call{callNumber}},bib{title,author,bibStatus{displayName}},pickupLibrary{displayName}}');
		$response = $this->getWebServiceResponse( '/user/patron/key/' . $patron->ilsUserId . '?includeFields='.$includeFields, null, $sessionToken);
		$patronHolds = $response->fields->holdRecordList;

		if(count($patronHolds) == 0) {
			return $holds;
		}

		require_once ROOT_DIR . '/RecordDrivers/Factory.php';
		foreach ($patronHolds as $patronHold) {
			$hold = $patronHold->fields;
			//TODO: Volume for title?
			//TODO: AvailableTime (availableTime only referenced in ilsHolds template and Holds Excel function)

				$bibId          = $hold->bib->key;
				$expireDate     = empty($hold->expirationDate) ? null : $hold->expirationDate;
				$reactivateDate = empty($hold->suspendEndDate) ? null : $hold->suspendEndDate;
				$createDate     = empty($hold->placedDate) ? null : $hold->placedDate;
				// fillByDate appears to be traditional expires date.
				$fillByDate     = empty($hold->fillByDate) ? null : $hold->fillByDate;

				$curHold                         = [];
				$curHold['id']                    = $bibId; // Template uses record Id for the ID instead of the hold ID
				$curHold['recordId']              = $bibId;
				$curHold['shortId']               = $bibId;
				$curHold['holdSource']            = 'ILS';
				$curHold['itemId']                = empty($hold->key) ? '' : $hold->key; //TODO: test
				$curHold['cancelId']              = $patronHold->key;
				$curHold['position']              = empty($hold->queuePosition) ? null : $hold->queuePosition;
				$curHold['status']                = ucfirst(strtolower($hold->status));
				$curHold['create']                = strtotime($createDate);
				$curHold['expire']                = strtotime($expireDate);
				$curHold['automaticCancellation'] = strtotime($fillByDate);
				$curHold['reactivate']            = $reactivateDate;
				$curHold['reactivateTime']        = strtotime($reactivateDate);
				$curHold['cancelable']            = strcasecmp($curHold['status'], 'Suspended') != 0 && strcasecmp($curHold['status'], 'Expired') != 0;
				$curHold['frozen']                = strcasecmp($curHold['status'], 'Suspended') == 0;
				$curHold['freezeable']            = true;
				if (strcasecmp($curHold['status'], 'Transit') == 0 || strcasecmp($curHold['status'], 'Expired') == 0) {
					$curHold['freezeable'] = false;
				}
				$curHold['locationUpdateable']    = true;
				if (strcasecmp($curHold['status'], 'Transit') == 0 || strcasecmp($curHold['status'], 'Expired') == 0) {
					$curHold['locationUpdateable'] = false;
				}
				$curPickupBranch       = new \Location();
				$curPickupBranch->code = $hold->pickupLibrary->key;
				if ($curPickupBranch->find(true)) {
					$curPickupBranch->fetch();
					$curHold['currentPickupId']   = $curPickupBranch->locationId;
					$curHold['currentPickupName'] = $curPickupBranch->displayName;
					$curHold['location']          = $curPickupBranch->displayName;
				}

				$recordDriver = \RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $bibId);
				if ($recordDriver->isValid()) {
					$curHold['title']           = $recordDriver->getTitle();
					$curHold['author']          = $recordDriver->getPrimaryAuthor();
					$curHold['sortTitle']       = $recordDriver->getSortableTitle();
					$curHold['format']          = $recordDriver->getFormat();
					$curHold['isbn']            = $recordDriver->getCleanISBN();
					$curHold['upc']             = $recordDriver->getCleanUPC();
					$curHold['coverUrl']        = $recordDriver->getBookcoverUrl('medium');
					$curHold['link']            = $recordDriver->getRecordUrl();
					$curHold['ratingData']      = $recordDriver->getRatingData(); //Load rating information

					//TODO: WCPL doesn't do item level holds
//						if ($hold->fields->holdType == 'COPY') {
//
//							$curHold['title2'] = $hold->fields->item->fields->itemType->key . ' - ' . $hold->fields->item->fields->call->fields->callNumber;
//
//
////						$itemInfo = $this->getWebServiceResponse( '/v1' . $hold->fields->selectedItem->resource . '/key/' . $hold->fields->selectedItem->key. '?includeFields=barcode,call{*}', null, $sessionToken);
////						$curHold['title2'] = $itemInfo->fields->itemType->key . ' - ' . $itemInfo->fields->call->fields->callNumber;
//							//TODO: Verify that this matches the title2 built below
////						if (isset($itemInfo->fields)){
////							$barcode = $itemInfo->fields->barcode;
////							$copies = $recordDriver->getCopies();
////							foreach ($copies as $copy){
////								if ($copy['itemId'] == $barcode){
////									$curHold['title2'] = $copy['shelfLocation'] . ' - ' . $copy['callNumber'];
////									break;
////								}
////							}
////						}
//						}

				} else {
					// If we don't have good marc record, ask the ILS for title info
					[$title, $author] = $this->getTitleAuthorForBib($bibId, $patron);
					$simpleSortTitle      = preg_replace('/^The\s|^A\s/i', '', $title); // remove beginning The or A
					$curHold['title']     = $title;
					$curHold['sortTitle'] = empty($simpleSortTitle) ? $title : $simpleSortTitle;
					$curHold['author']    = $author;
				}

				if (!isset($curHold['status']) || strcasecmp($curHold['status'], 'being_held') != 0) {
					$holds['unavailable'][] = $curHold;
				} else {
					$holds['available'][] = $curHold;
				}

		}

		return $holds;
	}


	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param   User    $patron          The User to place a hold for
	 * @param   string  $recordId        The id of the bib record
	 * @param   string  $pickupBranch    The branch where the user wants to pickup the item when available
	 * @param   null|string $cancelDate  The date to cancel the Hold if it isn't filled
	 * @return  array                                 Array of (success and message) to be used for an AJAX response
	 * @access  public
	 */
	public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null) {
		$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch, $cancelDate);
		return $result;

		// WCPL doesn't have item-level holds, so there is no need for this at this point.
//		$result = array();
//		$needsItemHold = false;
//
//		$holdableItems = array();
//		/** @var MarcRecord $recordDriver */
//		$recordDriver = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);
//		if ($recordDriver->isValid()){
//			$result['title'] = $recordDriver->getTitle();
//
//			$items = $recordDriver->getCopies();
//			$firstCallNumber = null;
//			foreach ($items as $item){
//				$itemNumber = $item['itemId'];
//				if ($itemNumber && $item['holdable']){
//					$itemCallNumber = $item['callNumber'];
//					if ($firstCallNumber == null){
//						$firstCallNumber = $itemCallNumber;
//					}else if ($firstCallNumber != $itemCallNumber){
//						$needsItemHold = true;
//					}
//
//					$holdableItems[] = array(
//						'itemNumber' => $item['itemId'],
//						'location'   => $item['shelfLocation'],
//						'callNumber' => $itemCallNumber,
//						'status'     => $item['status'],
//					);
//				}
//			}
//		}
//
//		if (!$needsItemHold){
//			$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch, 'request', $cancelDate);
//		}else{
//			$result['items'] = $holdableItems;
//			if (count($holdableItems) > 0){
//				$message = 'This title requires item level holds, please select an item to place a hold on.';
//			}else{
//				$message = 'There are no holdable items for this title.';
//			}
//			$result['success'] = false;
//			$result['message'] = $message;
//		}
//
//		return $result;
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param   User $patron                          The User to place a hold for
	 * @param   string $recordId                      The id of the bib record
	 * @param   string $itemId                        The id of the item to hold
	 * @param   string $pickUpLocation                The Pickup Location
	 * @param   null|string $cancelIfNotFilledByDate  The date to cancel the Hold if it isn't filled
	 * @return  array                                 Array of (success and message) to be used for an AJAX response
	 * @access  public
	 */
	function placeItemHold($patron, $recordId, $itemId, $pickUpLocation = null, $cancelIfNotFilledByDate = null){
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return [
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again'
			];
		}

		if (empty($pickUpLocation)){
			$pickUpLocation = $patron->homeLocationCode;
		}
		//create the hold using the web service

		$holdData = [
			'patronBarcode' => $patron->getBarcode(),
			'pickupLibrary' => [
				'resource' => '/policy/library',
				'key'      => strtoupper($pickUpLocation)
			],
		];

		if (!empty($itemId)) {
			//TODO: item-level holds haven't been tested yet.
			$holdData['itemBarcode'] = $itemId;
			$holdData['holdType']    = 'COPY';
		} else {
			$holdData['holdType'] = 'TITLE';
			$holdData['bib']      = [
				'resource' => '/catalog/bib',
				'key'      => $recordId
			];
		}

		if (!empty($cancelIfNotFilledByDate)) {
			$timestamp = strtotime($cancelIfNotFilledByDate);
			if ($timestamp) {
				$holdData['fillByDate'] = date('Y-m-d', $timestamp);
			}
		}
//				$holdRecordDescribe = $this->getWebServiceResponse( "/circulation/holdRecord/describe", null, $sessionToken);
//				$placeHoldDescribe  = $this->getWebServiceResponse( "/circulation/holdRecord/placeHold/describe", null, $sessionToken);
		$createHoldResponse = $this->getWebServiceResponse( "/circulation/holdRecord/placeHold", $holdData, $sessionToken);

		$hold_result = [
			'success' => false,
			'message' => 'Your hold could not be placed. '
		];
		if (isset($createHoldResponse->messageList)) {
			$errorMessage = '';
			foreach ($createHoldResponse->messageList as $error){
				$errorMessage .= $error->message.'; ';
			}
			$hold_result['message'] .= $errorMessage;

			$this->getLogger()->error('Horizon ROA Place Hold Error: ' . $errorMessage);

		} elseif (!empty($createHoldResponse->holdRecord)) {
			$hold_result['success'] = true;
			$hold_result['message'] = 'Your hold was placed successfully.';
		}

		// Retrieve Full Marc Record
		require_once ROOT_DIR . '/RecordDrivers/Factory.php';
		$record = \RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);
		if (!$record) {
			$title = null;
		} else {
			$title = $record->getTitle();
		}

		$hold_result['title'] = $title;
		$hold_result['bid']   = $recordId; //TODO: bid or bib

		return $hold_result;
	}


	function cancelHold($patron, $recordId, $cancelId){
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return [
				'success' => false,
				'message' => 'Sorry, we could not connect to the circulation system.'
			];
		}

		//create the hold using the web service
		$cancelHoldResponse = $this->getWebServiceResponse("/circulation/holdRecord/key/$cancelId", null, $sessionToken, 'DELETE');

		if (empty($cancelHoldResponse)){
			return [
				'success' => true,
				'message' => 'The hold was successfully canceled'
			];
		}else{
			$errorMessage = 'Horizon ROA Cancel Hold Error: ';
			foreach ($cancelHoldResponse->messageList as $error){
				$errorMessage .= $error->message . '; ';
			}
			$this->getLogger()->error($errorMessage);

			return [
				'success' => false,
				'message' => 'Sorry, the hold was not canceled'];
		}
	}

	function freezeHold($patron, $recordId, $holdToFreezeId, $dateToReactivate){
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return [
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again'
			];
		}

		$formattedDateToReactivate = $dateToReactivate ? date('Y-m-d', strtotime($dateToReactivate)) : null;

		$params = [
			'suspendEndDate' => $formattedDateToReactivate,
			'holdRecord'     => [
				'key'      => $holdToFreezeId,
				'resource' => '/circulation/holdRecord',
			]
		];

//		$describe  = $this->getWebServiceResponse( "/circulation/holdRecord/unsuspendHold/describe", null, $sessionToken);
		$updateHoldResponse = $this->getWebServiceResponse( "/circulation/holdRecord/suspendHold", $params, $sessionToken, 'POST');

		if (!empty($updateHoldResponse->holdRecord)){
			$frozen = translate('frozen');
			return [
				'success' => true,
				'message' => "The hold has been $frozen."
			];
		}else{
			$messages = [];
			if (isset($updateHoldResponse->messageList)){
				foreach ($updateHoldResponse->messageList as $message){
					$messages[] = $message->message;
				}
			}
			$freeze = translate('freeze');

			$errorMessage = 'Horizon ROA Freeze Hold Error: ' . ($messages ? implode('; ', $messages) : '');
			$this->getLogger()->error($errorMessage);

			return [
				'success' => false,
				'message' => "Failed to $freeze hold : " . implode('; ', $messages)
			];
		}
	}

	function thawHold($patron, $recordId, $holdToThawId){
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return [
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again'
			];
		}

		$params = [
			'holdRecord' => [
				'key'      => $holdToThawId,
				'resource' => '/circulation/holdRecord',
			]
		];

//		$describe           = $this->getWebServiceResponse( '/circulation/holdRecord/unsuspendHold/describe', null, $sessionToken);
//		$describe           = $this->getWebServiceResponse( '/circulation/holdRecord/changePickupLibrary/describe', null, $sessionToken);
		$updateHoldResponse = $this->getWebServiceResponse( '/circulation/holdRecord/unsuspendHold', $params, $sessionToken, 'POST');

		if (!empty($updateHoldResponse->holdRecord)){
			$thawed = translate('thawed');
			return [
				'success' => true,
				'message' => "The hold has been $thawed."
			];
		}else{
			$messages = [];
			if (isset($updateHoldResponse->messageList)){
				foreach ($updateHoldResponse->messageList as $message){
					$messages[] = $message->message;
				}
			}
			$thaw = translate('thaw');

			$errorMessage = 'Horizon ROA Thaw Hold Error: ' . ($messages ? implode('; ', $messages) : '');
			$this->getLogger()->error($errorMessage);

			return [
				'success' => false,
				'message' => "Failed to $thaw hold : " . implode('; ', $messages)
			];
		}
	}


	function changeHoldPickupLocation($patron, $recordId, $holdToUpdateId, $newPickupLocation){
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return [
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again'
			];
		}

		$params = [
			'pickupLibrary' => [
				'key'      => $newPickupLocation,
				'resource' => '/policy/library',
			],
			'holdRecord'    => [
				'key'      => $holdToUpdateId,
				'resource' => '/circulation/holdRecord',
			]
		];

		//$describe           = $this->getWebServiceResponse( "/circulation/holdRecord/changePickupLibrary/describe", null, $sessionToken);
		$updateHoldResponse = $this->getWebServiceResponse( "/circulation/holdRecord/changePickupLibrary", $params, $sessionToken, 'POST');

		if (!empty($updateHoldResponse->holdRecord)){
			return [
				'success' => true,
				'message' => 'The pickup location has been updated.'
			];
		}else{
			$messages = [];
			if (isset($updateHoldResponse->messageList)){
				foreach ($updateHoldResponse->messageList as $message){
					$messages[] = $message->message;
				}
			}
			$errorMessage = 'Horizon ROA Change Hold Pickup Location Error: ' . ($messages ? implode('; ', $messages) : '');
			$this->getLogger()->error($errorMessage);

			return [
				'success' => false,
				'message' => 'Failed to update the pickup location : ' . implode('; ', $messages)
			];
		}
	}

	/**
	 * Look up information about an item record in the ILS.
	 *
	 * @param string $itemId  Id of the Item to lookup
	 * @param User   $patron  User object to create a sesion with
	 * @return array          An array of Bib ID, Item Barcode, and the Item Type
	 */
	function getItemInfo($itemId, $patron){
		$itemInfo = [
			null, // bibId
			null, // barcode
			null, // item Type
		];
		if (!empty($itemId)){
			$memCacheKeyPrefix = 'horizon_ROA_bib_info_for_item';
			$memCacheKey       = "{$memCacheKeyPrefix}_$itemId";
			$itemInfo          = $this->cache->get($memCacheKey);

			if (!$itemInfo || isset($_REQUEST['reload'])){
				$sessionToken  = $this->getSessionToken($patron);

//				$itemInfoLookupResponse  = $this->getWebServiceResponse( "/catalog/item/key/" . $itemId, null, $sessionToken);
				$itemInfoLookupResponse = $this->getWebServiceResponse( "/catalog/item/key/" . $itemId . '?includeFields=bib,barcode,itemType', null, $sessionToken);
				if (!empty($itemInfoLookupResponse->fields)){
					$bibId    = $itemInfoLookupResponse->fields->bib->key;
					$barcode  = $itemInfoLookupResponse->fields->barcode;
					$itemType = $itemInfoLookupResponse->fields->itemType->key;

					$itemInfo = [
						$bibId,
						$barcode,
						$itemType,
					];

					global $configArray;
					$this->cache->set($memCacheKey, $itemInfo, $configArray['Caching'][$memCacheKeyPrefix]);
				}
			}
		}
		return $itemInfo;
	}

	function getTitleAuthorForBib($bibId, $patron){
		$bibInfo = [
			null, // title
			null, // author
		];
		if (!empty($bibId)){
			$memCacheKeyPrefix = 'horizon_ROA_title_info_for_bib';
			$memCacheKey       = "{$memCacheKeyPrefix}_$bibId";
			$bibInfo           = $this->cache->get($memCacheKey);

			if (!$bibInfo || isset($_REQUEST['reload'])){
				$sessionToken  = $this->getSessionToken($patron);

//				$bibInfoLookupResponse = $this->getWebServiceResponse( '/catalog/bib/key/' . $bibId . '?includeFields=*', null, $sessionToken);
				$bibInfoLookupResponse = $this->getWebServiceResponse( '/catalog/bib/key/' . $bibId . '?includeFields=title,author', null, $sessionToken);
				if (!empty($bibInfoLookupResponse->fields)){
					$title      = $bibInfoLookupResponse->fields->title;
					$shortTitle = strstr($title, '/', true); //drop everything from title after '/' character (author info)
					$title      = ($shortTitle) ? $shortTitle : $title;
					$title      = trim($title);
					$author     = $bibInfoLookupResponse->fields->author;

					$bibInfo = [
						$title,
						$author,
					];

					global $configArray;
					$this->cache->set($memCacheKey, $bibInfo, $configArray['Caching'][$memCacheKeyPrefix]);
				}
			}
		}
		return $bibInfo;
	}

	/**
	 * @param User $patron
	 * @param $includeMessages
	 * @return array
	 */
	public function getMyFines(User $patron, $includeMessages){
		$fines = [];
		//Get the session token for the user
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken){
			return $fines;
		}

		$logger = $this->getLogger();
		$logger->info('Using bracket notation to fetch patron fines');
		$includeFields = urlencode('blockList{item{key,bib{title}},createDate,amount,block{key},comment,owed}');
		$response = $this->getWebServiceResponse( '/user/patron/key/' . $patron->ilsUserId . '?includeFields='.$includeFields, null, $sessionToken);
		if(empty($response)) {
			return $fines;
		}

		$patronBlockList = $response->fields->blockList;
		if(count($patronBlockList) == 0) {
			return $fines;
		}

		foreach ($patronBlockList as $patronBlock){
			$block = $patronBlock->fields;
			// handle title
			// if there is no item associated with block, use message as title
			// this is currently the display on WCPL production 8/2/23
			$title = '';
			if(isset($block->item)) {
				$title = $block->item->fields->bib->fields->title;
			} else {
				if(isset($block->comment) AND $block->comment != null) {
					$title = $block->comment;
				}
			}
			$reason  = $this->getBlockPolicy($patronBlock->fields->block->key, $patron);
			$fines[] = [
				'reason'            => $reason,
				'amount'            => $block->amount->amount,
				'message'           => $title,
				'amountOutstanding' => $block->owed->amount,
				'date'              => date('n/j/Y', strtotime($block->createDate))
			];
		}

		return $fines;
	}

	/**
	 *  Get details about a fines charge
	 *
	 * @param string $blockPolicyKey
	 * @param User $patron
	 * @return mixed|null
	 */
	private function getBlockPolicy($blockPolicyKey, $patron) {
		$memCacheKey     = "horizon_ROA_block_policy_$blockPolicyKey";
		$blockPolicy = $this->cache->get($memCacheKey);
		if (!$blockPolicy) {
			$sessionToken  = $this->getSessionToken($patron);
			$lookupBlockPolicy = $this->getWebServiceResponse( '/policy/block/key/' . $blockPolicyKey, null, $sessionToken);
			if (!empty($lookupBlockPolicy->fields)) {
				$blockPolicy = empty($lookupBlockPolicy->fields->description) ? null : $lookupBlockPolicy->fields->description;
				global $configArray;
				$this->cache->set($memCacheKey, $blockPolicy, $configArray['Caching']['horizon_ROA_block_policy']);
			}
		}
		return $blockPolicy;
	}

	/**
	 * Update the PIN for a patron.
	 *
	 * This function updates the PIN for a patron by making a request to the appropriate web service endpoint. This method
	 * is called when a patron updates PIN from MyAccount
	 *
	 * @param User   $patron           The patron for whom the PIN should be updated.
	 * @param string $oldPin           The current PIN of the patron.
	 * @param string $newPin           The new PIN to be set for the patron.
	 * @param string $confirmNewPin    Confirmation of the new PIN to be set.
	 *
	 * @return string A message indicating the success or failure of the PIN update process.
	 */
	public function updatePin($patron, $oldPin, $newPin, $confirmNewPin): string {

		$sessionToken = $this->getSessionToken($patron);
		if(!$sessionToken) {
			return "Sorry, it does not look like you are logged in currently. Please log in and try again";
		}
		$params = [
			'currentPin' => $oldPin,
			'newPin'     => $newPin
		];

		$res = $this->getWebServiceResponse('/user/patron/changeMyPin', $params, $sessionToken, 'POST');
		if (isset($res->messageList)) {
			$errors = '';
			foreach ($res->messageList as $errorMessage) {
				$errors .= $errorMessage->message . ';';
			}
			$this->getLogger()->error('Horizon ROA Driver error updating user\'s Pin :'.$errors);
			return 'Sorry, we encountered an error while attempting to update your ' . translate('pin') . '. Please contact your local library.';
		} elseif (!empty($res->sessionToken)) {
			$patron->updatePassword($newPin);
			// remove session token from cache
			$this->deleteRoaSessionToken($patron);
			// remove user object from cache
			$patronObjectCacheKey = $this->cache->makePatronKey('patron', $patron->id);
			$this->cache->delete($patronObjectCacheKey);
			// login user
			$this->loginViaWebService($patron->barcode, $newPin);

			return 'Your ' . translate('pin') . ' was updated successfully.';
		}
		return 'Sorry, we could not update your ' . translate('pin') . '. Please try again later.';
	}

	/**
	 * Change the PIN for a patron.
	 *
	 * This function changes the PIN for a patron by making a request to the appropriate web service endpoint.
	 *
	 * @param User        $patron        The patron for whom the PIN should be changed.
	 * @param string      $newPin        The new PIN to be set for the patron.
	 * @param string|null $currentPin    The current PIN of the patron. Optional, defaults to null.
	 * @param string|null $resetToken    The reset token for changing the PIN. Optional, defaults to null.
	 *
	 * @return string The response from the web service endpoint after attempting to change the PIN.
	 */
	private function changeMyPin($patron, $newPin, $currentPin = null, $resetToken = null){
		$updatePinUrl   = '/user/patron/changeMyPin';
		if (empty($resetToken)){
			$sessionToken = $this->getSessionToken($patron);
			if (!$sessionToken){
				return 'Sorry, it does not look like you are logged in currently. Please log in and try again';
			}
			if (!empty($newPin) && !empty($currentPin)){
				$jsonParameters = [
					'currentPin' => $currentPin,
					'newPin'     => $newPin
				];
			}else{
				return 'Sorry the current ' . translate('pin') . ' or new ' . translate('pin') . ' is blank';
			}
		}else{
			$sessionToken   = null;
			$jsonParameters = [
				'newPin'        => $newPin,
				'resetPinToken' => $resetToken
			];
		}
		$r = $this->getWebServiceResponse($updatePinUrl, $jsonParameters, empty($sessionToken) ? null : $sessionToken, 'POST', empty($xtraHeaders) ? null : $xtraHeaders);
		return $r;
	}

	public function emailResetPin($barcode){
		if (!empty($barcode)){

			$patron = new User;
			$patron->get('barcode', $barcode); // This will always be for barcode/pin configurations
			if (!empty($patron->id)){
//				global $configArray;
				$userID = $patron->id;
			}else{
				//TODO: Look up user in Horizon
				$this->getLogger()->warning('For Pin Reset did not find user in Pika Database for barcode : '. $barcode);
			}

			if (!empty($userID)){
				// Apparently pin resetting does not require a version number in the operation url
				$resetPinAPICall = '/user/patron/resetMyPin';
				$pikaUrl         = $_SERVER['REQUEST_SCHEME'] . '://' . $patron->getHomeLibrary()->catalogUrl;
				$jsonPOST        = [
					'barcode'     => $barcode,
					'resetPinUrl' => $pikaUrl . '/MyAccount/ResetPin?resetToken=<RESET_PIN_TOKEN>&uid=' . $userID
				];

				$resetPinResponse = $this->getWebServiceResponse($resetPinAPICall, $jsonPOST, null, 'POST');
				// Reset Pin Response is empty JSON on success.

				if (!empty($resetPinResponse) && is_object($resetPinResponse) && !isset($resetPinResponse->messageList)){
					// Successful response is an empty json object "{}"
					return [
						'success' => true,
					];
				}else{
					$result = [
						'error' => 'Sorry, we could not e-mail your ' . translate('pin') . ' to you.  Please visit the library to reset your ' . translate('pin') . '.'
					];
					if (isset($resetPinResponse['messageList'])){
						$errors = '';
						foreach ($resetPinResponse['messageList'] as $errorMessage){
							$errors .= $errorMessage['message'] . ';';
						}
						$this->getLogger()->error('Horizon ROA Driver error updating user\'s Pin :' . $errors);
					}
					return $result;
				}
			}else{
				return [
					'error' => 'Sorry, we did not find the card number you entered or you have not logged into the catalog previously.  Please contact your library to reset your ' . translate('pin') . '.'
				];
			}
		}
	}

	/**
	 * @param User $patron
	 * @param $newPin
	 * @param $resetToken
	 * @return array|bool
	 */
	public function resetPin(User $patron, $newPin, $resetToken){
		if (empty($resetToken)){
			$this->getLogger()->error('No Reset Token passed to resetPin function');
			return [
				'error' => 'Sorry, we could not update your ' . translate('pin') . '. The reset token is missing. Please try again later'
			];
		}

		$changeMyPinResponse = $this->changeMyPin($patron, $newPin, null, $resetToken);
		if (isset($changeMyPinResponse->messageList)){
			$errors = '';
			foreach ($changeMyPinResponse->messageList as $errorMessage){
				$errors .= $errorMessage->message . ';';
			}
			$this->getLogger()->error('Horizon ROA Driver error updating user\'s Pin :' . $errors);
			return [
				'error' => 'Sorry, we encountered an error while attempting to update your ' . translate('pin') . '. Please contact your local library.'
			];
		}elseif (!empty($changeMyPinResponse->sessionToken)){
			if ($patron->ilsUserId == $changeMyPinResponse->patronKey){ // Check that the ILS user matches the Pika user
				$patron->updatePassword($newPin);
			}
			return true;
		}else{
			return [
				'error' => 'Sorry, we could not update your ' . translate('pin') . ' number. Please try again later.'
			];
		}
	}

//	private function getStaffSessionToken(){
//		global $configArray;
//
//		//Get a staff token
//		$staffUser   = $configArray['Catalog']['webServiceStaffUser'];
//		$staffPass   = $configArray['Catalog']['webServiceStaffPass'];
//		$body        = ['login' => $staffUser, 'password' => $staffPass];
//		$xtraHeaders = ['sd-originating-app-id' => 'Pika'];
//		$res         = $this->getWebServiceResponse($this->webServiceURL . '/user/staff/login', $body, null, "POST", $xtraHeaders);
//
//		if (!$res || !isset($res->sessionToken)){
//			return false;
//		}
//
//		return $res->sessionToken;
//	}

	/**
	 * @param User $patron                   The User Object to make updates to
	 * @param boolean $canUpdateContactInfo  Permission check that updating is allowed
	 * @return array                         Array of error messages for errors that occurred
	 */
	function updatePatronInfo($patron, $canUpdateContactInfo) {
		$updateErrors = [];
		if ($canUpdateContactInfo) {
			$sessionToken = $this->getSessionToken($patron);
			if ($sessionToken) {
				$horizonRoaUserId = $patron->ilsUserId;

				$updatePatronInfoParameters = [
					'resource' => '/user/patron',
					'key'      => $horizonRoaUserId,
					'fields'   => [],
				];
				if (isset($_REQUEST['phone'])){
					$updatePatronInfoParameters['fields']['primaryPhone'] = trim($_REQUEST['primaryPhone']);
				}

//				$emailAddress = trim($_REQUEST['email']);
//				if (is_array($emailAddress)) {
//					$emailAddress = '';
//				}
//				$primaryAddress = [
//					// TODO: check this may need to add address from patron.
//					'ROAObject' => '/ROAObject/primaryPatronAddressObject',
//					'fields'    => [
//						'line1'        => '4020 Carya Dr',
//						//'line2'        => null,
//						//'line3'        => null,
//						//'line4'        => null,
//						'line3'        => 'Raleigh, NC',
//						'postalCode'   => '27610',
//						'emailAddress' => $emailAddress,
//					]
//				];
//				$updatePatronInfoParameters['fields'][] = $primaryAddress;

				//$staffSessionToken = $this->getStaffSessionToken();

//				$updateAccountInfoResponse = $this->getWebServiceResponse( '/adminws/clientID/describe', null, $sessionToken);
				//$updateAccountInfoResponse = $this->getWebServiceResponse( '/adminws/selfRegConfig/describe', null, $sessionToken);
//				$updateAccountInfoResponse = $this->getWebServiceResponse( '/user/patron/register/describe', null, $sessionToken, 'PUT');
				$updateAccountInfoResponse = $this->getWebServiceResponse( '/user/patron/key/' . $horizonRoaUserId, $updatePatronInfoParameters, $sessionToken, 'PUT');

				if (isset($updateAccountInfoResponse->messageList)) {
					foreach ($updateAccountInfoResponse->messageList as $message) {
						$updateErrors[] = $message->message;
					}
					$this->getLogger()->error('Horizon ROA Driver - Patron Info Update Error - Error from ILS : '.implode(';', $updateErrors));
				}

			} else {
				$updateErrors[] = 'Sorry, it does not look like you are logged in currently.  Please log in and try again';
			}
		} else {
			$updateErrors[] = 'You do not have permission to update profile information.';
		}
		return $updateErrors;
	}


	public function selfRegister() {
		return ['success' => false, 'barcode' => ''];

//		global $configArray;
//		// global $interface;
//		// Get a staff token
//		if(!$staffSessionToken = $this->getStaffSessionToken()) {
//			return ['success' => false, 'barcode' => ''];
//		}
//
//		// remove things from post
//		unset($_POST['objectAction']);
//		unset($_POST['id']);
//		unset($_POST['submit']);
//
//		$profile = $configArray['Catalog']['webServiceSelfRegProfile'];
//		$entries = [];
//		foreach ($_POST as $column=>$value) {
//			$column = trim($column);
//			$value  = trim($value);
//			$entry  = ["column"=>$column, "value"=>$value];
//			$entries[] = $entry;
//		}
//
//		$body = [
//			"profile" => $profile,
//			"entries" => $entries
//		];
//		//$body = json_encode($body); // gets encoded in getWebServiceResponse
//
//		$secret = $configArray['Catalog']['webServiceSecret'];
//		$xtraHeaders = ['x-sirs-secret'=>$secret];
//		$res = $this->getWebServiceResponse($this->webServiceURL . '/rest/standard/createSelfRegisteredPatron', $body, $staffSessionToken, "POST", $xtraHeaders);
//
//		if(!$res || isset($res->Fault)) {
//			return ['success' => false, 'barcode' => ''];
//		}
//
//		return ['success' => true, 'barcode' => $res];
	}

	/**
	 * Get self registration fields from Horizon web services.
	 *
	 * Checks if self registration is enabled. Gets self registration fields from web service and builds form fields.
	 *
	 * @return array|bool An array of form fields or false if user registration isn't enabled (or something goes wrong)
	 */
//	public function getSelfRegistrationFields(){
//		global $configArray;
//
//		// SelfRegistrationEnabled?
//		$wsProfile = $configArray['Catalog']['webServiceSelfRegProfile'];
//		$r         = $this->getWebServiceResponse($this->webServiceURL . '/rest/standard/isPatronSelfRegistrationEnabled?profile=' . $wsProfile);
//		// get sef reg fields
//		$res = $this->getWebServiceResponse($this->webServiceURL . '/rest/standard/lookupSelfRegistrationFields');
//		if (!$res){
//			return false;
//		}
//		// build form fields
//		foreach ($res->registrationField as $field){
//			$f = [
//				'property'  => $field->column,
//				'label'     => $field->label,
//				'maxLength' => $field->length,
//				'required'  => $field->required,
//			];
//			if (isset($field->values)){
//				// select list
//				$f['type'] = 'enum';
//				$values    = [];
//				foreach ($field->values->value as $value){
//					$key          = $value->code;
//					$values[$key] = $value->description;
//				}
//				$f['values'] = $values;
//			}else{
//				$f['type'] = 'text';
//			}
//			$fields[] = $f;
//		}
//		return $fields;
//	}

	/**
	 * A place holder method to override with site specific logic
	 *
	 * @return bool
	 */
	public function canRenew($itemType = null){
		return true;
	}

}
