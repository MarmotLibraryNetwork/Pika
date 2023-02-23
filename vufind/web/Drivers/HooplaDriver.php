<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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
 * Driver to handle Hoopla API user actions.
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/8/2018
 */
use Pika\Cache;
use Pika\Logger;

require_once ROOT_DIR . '/services/SourceAndId.php';

class HooplaDriver
{
	const memCacheKey = 'hoopla_api_access_token';
	public $hooplaAPIBaseURL = 'https://hoopla-erc.hoopladigital.com';
	private $accessToken;
	private $hooplaEnabled = false;
	private $cache;
	private $logger;
	private $connectionTimeout = 5;
	private $timeout = 10;


	public function __construct(){
		$this->cache  = new Cache();
		$this->logger = new Logger(get_class($this));
		global $configArray;
		if (!empty($configArray['Hoopla']['HooplaAPIUser']) && !empty($configArray['Hoopla']['HooplaAPIpassword'])){
			$this->hooplaEnabled = true;
			if (isset($configArray['Hoopla']['HooplaConnectionTimeOut']) && $configArray['Hoopla']['HooplaConnectionTimeOut'] != ''){
				$this->connectionTimeout = $configArray['Hoopla']['HooplaConnectionTimeOut'];
			}
			if (isset($configArray['Hoopla']['HooplaTimeOut']) && $configArray['Hoopla']['HooplaTimeOut'] != ''){
				$this->timeout = $configArray['Hoopla']['HooplaTimeOut'];
			}
			if (!empty($configArray['Hoopla']['APIBaseURL'])){
				$this->hooplaAPIBaseURL = $configArray['Hoopla']['APIBaseURL'];
				$this->getAccessToken();
			}
		}
	}

	/**
	 * Clean an assumed Hoopla RecordID to Hoopla ID number for the API
	 * NOTE: these Ids may be different numbers now.
	 *
	 * @param SourceAndId $hooplaRecordId  The Id of the Marc Record
	 * @param HooplaRecordDriver|null $hooplaRecord  RecordDriver for the marc record
	 * @return string The id for the Hoopla API
	 */
	public static function recordIDtoHooplaID(SourceAndId $hooplaRecordId, HooplaRecordDriver $hooplaRecord = null){
		require_once ROOT_DIR . '/sys/Hoopla/HooplaExtract.php';
		$hooplaId      = preg_replace('/^MWT/', '', $hooplaRecordId->getRecordId());
		$hooplaExtract = new HooplaExtract();
		$success       = $hooplaExtract->get('hooplaId', $hooplaId);
		if (!$success){
			if (empty($hooplaRecord)){
				require_once ROOT_DIR . '/RecordDrivers/HooplaRecordDriver.php';
				$hooplaRecord = new HooplaRecordDriver($hooplaRecordId);
			}
			if ($hooplaRecord->isValid()){
				foreach ($hooplaRecord->getAccessLink() as $link){
					if (preg_match('/title\/(\d*)/', $link['url'], $matches)){
						$hooplaId = $matches[1];
						break;
					}
				}
			}

		}

		return $hooplaId;
	}

	/**
	 * @return bool
	 */
	public function isHooplaEnabled(): bool{
		return $this->hooplaEnabled;
	}

	// Originally copied from SirsiDynixROA Driver
	// $customRequest is for curl, can be 'PUT', 'DELETE', 'POST'
	private function getAPIResponse($url, $params = null, $customRequest = null, $additionalHeaders = null)
	{
		$this->logger->info('Hoopla API URL :' .$url);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout );
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		$headers  = [
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->accessToken,
			'Originating-App-Id: Pika',
		];
		if (!empty($additionalHeaders) && is_array($additionalHeaders)) {
			$headers = array_merge($headers, $additionalHeaders);
		}
		if (empty($customRequest)) {
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		} elseif ($customRequest == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}
		else {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customRequest);
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		global $instanceName;
		if (stripos($instanceName, 'localhost') !== false) {
			// For local debugging only
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);

		}
		if ($params !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		}
		$json = curl_exec($ch);
//		// For debugging only
//		if (stripos($instanceName, 'localhost') !== false) {
//		$err  = curl_getinfo($ch);
//		$headerRequest = curl_getinfo($ch, CURLINFO_HEADER_OUT);
//		}
		if (!$json && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 401) {
			$this->logger->warn('401 Response in getAPIResponse. Attempting to renew access token');
			$this->renewAccessToken();
			return false;
		}

		$this->logger->debug("Hoopla API response\r\n$json");
		if($errno = curl_errno($ch)){
			$error_message = curl_strerror($errno);
			$this->logger->warn('Curl error in getAPIResponse: ' . $error_message);
		}
		curl_close($ch);

		if ($json !== false && $json !== 'false') {
			return json_decode($json);
		} else {
			return false;
		}
	}

	/**
	 * Simplified CURL call for returning a title. Success is determined by receiving a http status code of 204
	 * @param $url
	 * @return bool
	 */
	private function getAPIResponseReturnHooplaTitle($url)
	{
		$ch = curl_init();
		$headers  = array(
			'Authorization: Bearer ' . $this->accessToken,
			'Originating-App-Id: Pika',
		);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout );
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		global $instanceName;
		if (stripos($instanceName, 'localhost') !== false) {
			// For local debugging only
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		}

		curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//		// For debugging only
//		if (stripos($instanceName, 'localhost') !== false) {
//		$err  = curl_getinfo($ch);
//		$headerRequest = curl_getinfo($ch, CURLINFO_HEADER_OUT);
//		}
		if($errno = curl_errno($ch)){
			$error_message = curl_strerror($errno);
			$this->logger->warn('Curl error: ' . $error_message);
		}
		curl_close($ch);
		return $http_code == 204;
	}


	private static $hooplaLibraryIdsForUser;

	/**
	 * @param $user User
	 */
	public function getHooplaLibraryID($user) {
		if ($this->hooplaEnabled) {
			if (isset(self::$hooplaLibraryIdsForUser[$user->id])) {
				return self::$hooplaLibraryIdsForUser[$user->id]['libraryId'];
			} else {
				$library                                               = $user->getHomeLibrary();
				$hooplaID                                              = $library->hooplaLibraryID;
				self::$hooplaLibraryIdsForUser[$user->id]['libraryId'] = $hooplaID;
				return $hooplaID;
			}
		}
		return false;
	}

	/**
	 * @param $user User
	 */
	private function getHooplaBasePatronURL($user) {
		$url = null;
		if ($this->hooplaEnabled) {
			$hooplaLibraryID = $this->getHooplaLibraryID($user);
			$barcode         = $user->getBarcode();
			if (!empty($hooplaLibraryID) && !empty($barcode)) {
				$url = $this->hooplaAPIBaseURL . '/api/v1/libraries/' . $hooplaLibraryID . '/patrons/' . $barcode;
			}
		}
		return $url;
		}

	private $hooplaPatronStatuses = array();
	/**
	 * @param $user User
	 */
	public function getHooplaPatronStatus($user) {
		if ($this->hooplaEnabled) {
			if (isset($this->hooplaPatronStatuses[$user->id])) {
				return $this->hooplaPatronStatuses[$user->id];
			} else {
				$getPatronStatusURL = $this->getHooplaBasePatronURL($user);
				if (!empty($getPatronStatusURL)) {
					$getPatronStatusURL         .= '/status';
					$hooplaPatronStatusResponse = $this->getAPIResponse($getPatronStatusURL);
					if (!empty($hooplaPatronStatusResponse) && !isset($hooplaPatronStatusResponse->message)) {
						$this->hooplaPatronStatuses[$user->id] = $hooplaPatronStatusResponse;
						return $hooplaPatronStatusResponse;
					} else {
						$hooplaErrorMessage = empty($hooplaPatronStatusResponse->message) ? '' : ' Hoopla Message :' . $hooplaPatronStatusResponse->message;
						$this->logger->notice('Error retrieving patron status from Hoopla. User ID : ' . $user->id, ['hoopla_error_message' => $hooplaErrorMessage]);
						$this->hooplaPatronStatuses[$user->id] = false; // Don't do status call again for this user
					}
				}
			}
		}
		return false;
	}

	/**
	 * @param $user User
	 */
	public function getHooplaCheckedOutItems($user){
		$checkedOutItems = [];
		if ($this->hooplaEnabled){
			$hooplaCheckedOutTitlesURL = $this->getHooplaBasePatronURL($user);
			if (!empty($hooplaCheckedOutTitlesURL)){
				$hooplaCheckedOutTitlesURL .= '/checkouts/current';
				$checkOutsResponse         = $this->getAPIResponse($hooplaCheckedOutTitlesURL);
				if (is_array($checkOutsResponse)){
					if (count($checkOutsResponse)){ // Only get Patron status if there are checkouts
						$hooplaPatronStatus = $this->getHooplaPatronStatus($user);
					}
					foreach ($checkOutsResponse as $checkOut){
						$hooplaRecordID  = 'MWT' . $checkOut->contentId;
						$simpleSortTitle = preg_replace('/^The\s|^A\s/i', '', $checkOut->title);   // remove beginning The or A

						$currentTitle = [
							'checkoutSource' => 'Hoopla',
							'user'           => $user->getNameAndLibraryLabel(),
							'userId'         => $user->id,
							'hooplaId'       => $checkOut->contentId,
							'title'          => $checkOut->title,
							'title_sort'     => empty($simpleSortTitle) ? $checkOut->title : $simpleSortTitle,
							'author'         => isset($checkOut->author) ? $checkOut->author : null,
							'format'         => $checkOut->kind,
							'checkoutdate'   => $checkOut->borrowed,
							'dueDate'        => $checkOut->due,
							'hooplaUrl'      => $checkOut->url
						];

						if (isset($hooplaPatronStatus->borrowsRemaining)){
							$currentTitle['borrowsRemaining'] = $hooplaPatronStatus->borrowsRemaining;
						}

						require_once ROOT_DIR . '/RecordDrivers/HooplaRecordDriver.php';
//						$hooplaRecordDriver = new HooplaRecordDriver($hooplaRecordID);
						$hooplaRecordDriver = new HooplaRecordDriver('hoopla:' . $hooplaRecordID); //TODO: need a proper solution here
						if ($hooplaRecordDriver->isValid()){
							// Get Record For other details
							$currentTitle['coverUrl']      = $hooplaRecordDriver->getBookcoverUrl('medium');
							$currentTitle['linkUrl']       = $hooplaRecordDriver->getLinkUrl();
							$currentTitle['groupedWorkId'] = $hooplaRecordDriver->getGroupedWorkId();
							$currentTitle['ratingData']    = $hooplaRecordDriver->getRatingData();
							$currentTitle['title_sort']    = $hooplaRecordDriver->getSortableTitle();
							$currentTitle['author']        = $hooplaRecordDriver->getPrimaryAuthor();
							$currentTitle['format']        = implode(', ', $hooplaRecordDriver->getFormat());
						}
						$key                   = $currentTitle['checkoutSource'] . $currentTitle['hooplaId']; // This matches the key naming scheme in the Overdrive Driver
						$checkedOutItems[$key] = $currentTitle;
					}
				}else{
					$this->logger->warn('Error retrieving checkouts from Hoopla.');
				}
			}
		}
		return $checkedOutItems;
	}

	/**
	 * @return string
	 */
	private function getAccessToken(){
		if (empty($this->accessToken)){
			$accessToken = $this->cache->get(self::memCacheKey);
			if (empty($accessToken)){
				$this->renewAccessToken();
			}else{
				$this->accessToken = $accessToken;
			}

		}
		return $this->accessToken;
	}

	private function renewAccessToken(){
		global $configArray;
		if (!empty($configArray['Hoopla']['HooplaAPIUser']) && !empty($configArray['Hoopla']['HooplaAPIpassword'])) {
			$url = 'https://' . str_replace(['http://', 'https://'],'', $this->hooplaAPIBaseURL) . '/v2/token';
			// Ensure https is used

			$username = $configArray['Hoopla']['HooplaAPIUser'];
			$password = $configArray['Hoopla']['HooplaAPIpassword'];

			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
			curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, array());
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout );
			curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

			global $instanceName;
			if (stripos($instanceName, 'localhost') !== false) {
				// For local debugging only
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			}
			$response = curl_exec($curl);
			if($errno = curl_errno($curl)){
				$error_message = curl_strerror($errno);
				$this->logger->warn('Curl error in getAPIResponse: ' . $error_message);
			}
//			// Use for debugging
//			if (stripos($instanceName, 'localhost') !== false) {
//				$err  = curl_getinfo($curl);
//				$headerRequest = curl_getinfo($curl, CURLINFO_HEADER_OUT);
//			}
			curl_close($curl);

			if ($response) {
				$json = json_decode($response);
				if (!empty($json->access_token)) {
					$this->accessToken = $json->access_token;
					$this->cache->set(self::memCacheKey, $this->accessToken, $configArray['Caching']['hoopla_api_access_token']);
					return true;

				} else {
					$this->logger->error('Hoopla API retrieve access token call did not contain an access token');
				}
			} else {
				$this->logger->error('Curl Error in Hoopla API call to retrieve access token');
			}
		} else {
			$this->logger->error('Hoopla API user and/or password not set. Can not retrieve access token');
		}
		return false;
	}

	/**
	 * Checkout a title from hoopla for the user.
	 *
	 * @param SourceAndId $hooplaId
	 * @param $user User
	 *
	 * @return array
	 */
	public function checkoutHooplaItem(SourceAndId $hooplaId, $user){
		if ($this->hooplaEnabled){
			$checkoutURL = $this->getHooplaBasePatronURL($user);
			if (!empty($checkoutURL)){

				$hooplaId         = self::recordIDtoHooplaID($hooplaId);
				$checkoutURL      .= '/' . $hooplaId;
				$checkoutResponse = $this->getAPIResponse($checkoutURL, [], 'POST');
				if ($checkoutResponse){
					if (!empty($checkoutResponse->contentId)){
						return [
							'success'   => true,
							'message'   => $checkoutResponse->message,
							'title'     => $checkoutResponse->title,
							'HooplaURL' => $checkoutResponse->url,
							'due'       => $checkoutResponse->due
						];
						// Example Success Response
						//{
						//	'contentId': 10051356,
						//  'title': 'The Night Before Christmas',
						//  'borrowed': 1515799430,
						//  'due': 1517613830,
						//  'kind': 'AUDIOBOOK',
						//  'url': 'https://www-dev.hoopladigital.com/title/10051356',
						//  'message': 'You can now enjoy this title through Friday, February 2.  You can stream it to your browser, or download it for offline viewing using our Amazon, Android, or iOS mobile apps.'
						//}
					}else{
						return [
							'success' => false,
							'message' => isset($checkoutResponse->message) ? $checkoutResponse->message : 'An error occurred checking out the Hoopla title.'
						];
					}

				}else{
					return [
						'success' => false,
						'message' => 'An error occurred checking out the Hoopla title.'
					];
				}
			}elseif (!$this->getHooplaLibraryID($user)){
				return [
					'success' => false,
					'message' => 'Your library does not have Hoopla integration enabled.'
				];
			}else{
				return [
					'success' => false,
					'message' => 'There was an error retrieving your library card number.'
				];
			}
		}else{
			return [
				'success' => false,
				'message' => 'Hoopla integration is not enabled.'
			];
		}
	}

	/**
	 * Return a checked out Hoopla title for the user.
	 *
	 * @param SourceAndId $hooplaId
	 * @param User $user
	 *
	 * @return array
	 */
	public function returnHooplaItem(SourceAndId $hooplaId, $user){
		if ($this->hooplaEnabled){
			$returnHooplaItemURL = $this->getHooplaBasePatronURL($user);
			if (!empty($returnHooplaItemURL)){
				$itemId              = self::recordIDtoHooplaID($hooplaId);
				$returnHooplaItemURL .= "/$itemId";
				$result              = $this->getAPIResponseReturnHooplaTitle($returnHooplaItemURL);
				if ($result){
					return [
						'success' => true,
						'message' => 'The title was successfully returned.'
					];
				}else{
					return [
						'success' => false,
						'message' => 'There was an error returning this title.'
					];
				}

			}elseif (!$this->getHooplaLibraryID($user)){
				return [
					'success' => false,
					'message' => 'Your library does not have Hoopla integration enabled.'
				];
			}else{
				return [
					'success' => false,
					'message' => 'There was an error retrieving your library card number.'
				];
			}
		}else{
			return [
				'success' => false,
				'message' => 'Hoopla integration is not enabled.'
			];
		}
	}

	public function getLibraryHooplaTotalCheckOuts($libraryId, $startTime, $endTime){
		$url = $this->hooplaAPIBaseURL . '/api/v1/libraries/' . $libraryId
			. '/checkouts/total?startTime=' . $startTime . '&endTime=' . $endTime;

		$result = $this->getAPIResponse($url);
		return $result;

	}

	public function getHooplaRecordMetaData($libraryId, $hooplaId){
		$url = $this->hooplaAPIBaseURL . '/api/v1/libraries/' . $libraryId
			. "/content?limit=1&startToken=" . ($hooplaId - 1);

		$result = $this->getAPIResponse($url);
		return $result;
	}
}
