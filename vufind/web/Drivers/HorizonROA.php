<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/10/2018
 *
 */

require_once 'DriverInterface.php';

abstract class HorizonROA implements DriverInterface
{

	private static $sessionIdsForUsers = array();

	public function __construct($accountProfile){
		$this->accountProfile = $accountProfile;
	}

	public function getWebServiceURL()
	{
		$webServiceURL = null;
		if (!empty($this->accountProfile->patronApiUrl)) {
			$webServiceURL = $this->accountProfile->patronApiUrl;
		} elseif (!empty($configArray['Catalog']['webServiceUrl'])) {
			$webServiceURL = $configArray['Catalog']['webServiceUrl'];
		} else {
			global $logger;
			$logger->log('No Web Service URL defined in Horizon ROA API Driver', PEAR_LOG_CRIT);
		}
		return $webServiceURL;
	}

	// $customRequest is for curl, can be 'PUT', 'DELETE', 'POST'
	public function getWebServiceResponse($url, $params = null, $sessionToken = null, $customRequest = null, $additionalHeaders = null, $alternateClientId = null)
	{
		global $configArray;
		global $logger;
		$logger->log('WebServiceURL :' .$url, PEAR_LOG_INFO);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		$clientId = empty($alternateClientId) ? $configArray['Catalog']['clientId'] : $alternateClientId;
		$headers  = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'SD-Originating-App-Id: Pika',
			'x-sirs-clientID: ' . $clientId,
		);
		if ($sessionToken != null) {
			$headers[] = 'x-sirs-sessionToken: ' . $sessionToken;
		}
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
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // TODO: debugging only: comment out for production
			curl_setopt($ch, CURLINFO_HEADER_OUT, true); //TODO: For debugging
		}
		if ($params != null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		}
		$json = curl_exec($ch);

		// TODO: debugging only, comment out later.
		if (stripos($instanceName, 'localhost') !== false) {
			$err  = curl_getinfo($ch);
			$headerRequest = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		}

		$logger->log("Web service response\r\n$json", PEAR_LOG_DEBUG); //TODO: For debugging
		curl_close($ch);

		if ($json !== false && $json !== 'false') {
			return json_decode($json);
		} else {
			$logger->log('Curl problem in getWebServiceResponse', PEAR_LOG_WARNING);
			return false;
		}
	}


	protected function loginViaWebService($username, $password)
	{
		/** @var Memcache $memCache */
		global $memCache;
		$memCacheKey = "horizon_ROA_session_token_info_$username";
		$session = $memCache->get($memCacheKey);
		if ($session) {
			list(, $sessionToken, $horizonRoaUserID) = $session;
			self::$sessionIdsForUsers[$horizonRoaUserID] = $sessionToken;
		} else {
			$session = array(false, false, false);
			$webServiceURL = $this->getWebServiceURL();
//		$loginDescribeResponse = $this->getWebServiceResponse($webServiceURL . '/user/patron/login/describe');
			$loginUserUrl      = $webServiceURL . '/user/patron/login';
			$params            = array(
				'login'    => $username,
				'password' => $password,
			);
			$loginUserResponse = $this->getWebServiceResponse($loginUserUrl, $params);
			if ($loginUserResponse && isset($loginUserResponse->sessionToken)) {
				//We got at valid user (A bad call will have isset($loginUserResponse->messageList) )

				$horizonRoaUserID                            = $loginUserResponse->patronKey;
				$sessionToken                                = $loginUserResponse->sessionToken;
				self::$sessionIdsForUsers[$horizonRoaUserID] = $sessionToken;
				$session = array(true, $sessionToken, $horizonRoaUserID);
				global $configArray;
				$memCache->set($memCacheKey, $session, 0, $configArray['Caching']['horizon_roa_session_token']);
			} elseif (isset($loginUserResponse->messageList)) {
				global $logger;
				$errorMessage = 'Horizon ROA Webservice Login Error: ';
				foreach ($loginUserResponse->messageList as $error){
					$errorMessage .= $error->message.'; ';
				}
				$logger->log($errorMessage, PEAR_LOG_ERR);
			}
		}
		return $session;
	}

	private function getSessionToken($patron)
	{
		$horizonRoaUserId = $patron->username;

		//Get the session token for the user
		if (isset(self::$sessionIdsForUsers[$horizonRoaUserId])) {
			return self::$sessionIdsForUsers[$horizonRoaUserId];
		} else {
			list(, $sessionToken) = $this->loginViaWebService($patron->cat_username, $patron->cat_password);
			return $sessionToken;
		}
	}


	public function patronLogin($username, $password, $validatedViaSSO)
	{
		global $timer;
		global $logger;

		//Remove any spaces from the barcode
		$username = trim($username);
		$password = trim($password);

		//Authenticate the user via WebService
		//First call loginUser
		$timer->logTime("Logging in through Horizon APIs");
		list($userValid, $sessionToken, $horizonRoaUserID) = $this->loginViaWebService($username, $password);
		if ($validatedViaSSO) {
			$userValid = true;
		}
		if ($userValid) {
			$timer->logTime("User is valid in horizon");
			$webServiceURL = $this->getWebServiceURL();

//  Calls that show how patron-related data is represented
//			$patronDescribeResponse           = $this->getWebServiceResponse($webServiceURL . '/v1/user/patron/describe', null, $sessionToken);

			$acountInfoLookupURL         = $webServiceURL . '/v1/user/patron/key/' . $horizonRoaUserID
			. '?includeFields=displayName,birthDate,privilegeExpiresDate,primaryAddress,primaryPhone,library'
			. ',holdRecordList,circRecordList,blockList'
//			. ",estimatedOverdueAmount,blockList,circRecordList"  // fields to play with
			// {*} notation doesn't work here
		;

			// phoneList is for texting notification preferences

			$lookupMyAccountInfoResponse = $this->getWebServiceResponse($acountInfoLookupURL, null, $sessionToken);
			if ($lookupMyAccountInfoResponse && !isset($lookupMyAccountInfoResponse->messageList)) {
				$fullName = $lookupMyAccountInfoResponse->fields->displayName;
				if (strpos($fullName, ',')) {
					list($lastName, $firstName) = explode(', ', $fullName);
				}

				$userExistsInDB = false;
				/** @var User $user */
				$user           = new User();
				$user->source   = $this->accountProfile->name;
				$user->username = $horizonRoaUserID;
				if ($user->find(true)) {
					$userExistsInDB = true;
				}

				$forceDisplayNameUpdate = false;
				$firstName              = isset($firstName) ? $firstName : '';
				if ($user->firstname != $firstName) {
					$user->firstname        = $firstName;
					$forceDisplayNameUpdate = true;
				}
				$lastName = isset($lastName) ? $lastName : '';
				if ($user->lastname != $lastName) {
					$user->lastname         = isset($lastName) ? $lastName : '';
					$forceDisplayNameUpdate = true;
				}
				if ($forceDisplayNameUpdate) {
					$user->displayName = '';
				}
				$user->fullname     = isset($fullName) ? $fullName : '';
				$user->cat_username = $username;
				$user->cat_password = $password;

				$Address1    = "";
				$City        = "";
				$State       = "";
				$Zip         = "";

				if (isset($lookupMyAccountInfoResponse->fields->primaryAddress)) {
					$preferredAddress = $lookupMyAccountInfoResponse->fields->primaryAddress->fields;
					// Set for Account Updating
//					self::$userPreferredAddresses[$horizonRoaUserID] = $preferredAddress;
					//TODO: Needed?
					// Used by My Account Profile to update Contact Info

					$cityState = $preferredAddress->area;
					if (strpos($cityState, ', ')) {
						list($City, $State) = explode(', ', $cityState);
					} else {
						//TODO: is there ever an exception?
					}
					$Address1 = $preferredAddress->line1;
					//TODO: combine additional Lines? (lines 2 - 4)

					$email = $preferredAddress->emailAddress;
					$user->email = $email;

					$Zip = $preferredAddress->postalCode;

					$phone = $lookupMyAccountInfoResponse->fields->primaryPhone;
					$user->phone = $phone;

				}

				//Get additional information about the patron's home branch for display.
				if (isset($lookupMyAccountInfoResponse->fields->library->key)) {
					$homeBranchCode = strtolower(trim($lookupMyAccountInfoResponse->fields->library->key));
					//Translate home branch to plain text
					/** @var \Location $location */
					$location       = new Location();
					$location->code = $homeBranchCode;
					if (!$location->find(true)) {
						unset($location);
					}
				} else {
					global $logger;
					$logger->log('HorizonROA Driver: No Home Library Location or Hold location found in account look-up. User : ' . $user->id, PEAR_LOG_ERR);
					// The code below will attempt to find a location for the library anyway if the homeLocation is already set
				}

				if (empty($user->homeLocationId) || (isset($location) && $user->homeLocationId != $location->locationId)) { // When homeLocation isn't set or has changed
					if (empty($user->homeLocationId) && !isset($location)) {
						// homeBranch Code not found in location table and the user doesn't have an assigned homelocation,
						// try to find the main branch to assign to user
						// or the first location for the library
						global $library;

						/** @var \Location $location */
						$location            = new Location();
						$location->libraryId = $library->libraryId;
						$location->orderBy('isMainBranch desc'); // gets the main branch first or the first location
						if (!$location->find(true)) {
							// Seriously no locations even?
							global $logger;
							$logger->log('Failed to find any location to assign to user as home location', PEAR_LOG_ERR);
							unset($location);
						}
					}
					if (isset($location)) {
						$user->homeLocationId = $location->locationId;
						if (empty($user->myLocation1Id)) {
							$user->myLocation1Id  = ($location->nearbyLocation1 > 0) ? $location->nearbyLocation1 : $location->locationId;
							/** @var /Location $location */
							//Get display name for preferred location 1
							$myLocation1             = new Location();
							$myLocation1->locationId = $user->myLocation1Id;
							if ($myLocation1->find(true)) {
								$user->myLocation1 = $myLocation1->displayName;
							}
						}

						if (empty($user->myLocation2Id)){
							$user->myLocation2Id  = ($location->nearbyLocation2 > 0) ? $location->nearbyLocation2 : $location->locationId;
							//Get display name for preferred location 2
							$myLocation2             = new Location();
							$myLocation2->locationId = $user->myLocation2Id;
							if ($myLocation2->find(true)) {
								$user->myLocation2 = $myLocation2->displayName;
							}
						}
					}
				}

				if (isset($location)) {
					//Get display names that aren't stored
					$user->homeLocationCode = $location->code;
					$user->homeLocation     = $location->displayName;
				}

				if (isset($lookupMyAccountInfoResponse->fields->privilegeExpiresDate)) {
					$user->expires = $lookupMyAccountInfoResponse->fields->privilegeExpiresDate;
					list ($yearExp, $monthExp, $dayExp) = explode("-", $user->expires);
					$timeExpire   = strtotime($monthExp . "/" . $dayExp . "/" . $yearExp);
					if ($timeExpire) {
						$timeNow      = time();
						$timeToExpire = $timeExpire - $timeNow;
						if ($timeToExpire <= 30 * 24 * 60 * 60) {
							//TODO: Sirsi ROA has an expire soon flag in the patronStatusInfo, does Horizon ROA?
							if ($timeToExpire <= 0) {
								$user->expired = 1;
							}
							$user->expireClose = 1;
						}
					}
				}

				//Get additional information about fines, etc

				//TODO: Make Additional Calls to calculate these values
//				$finesVal = 0;
//				if (isset($lookupMyAccountInfoResponse->fields->blockList)) {
//					foreach ($lookupMyAccountInfoResponse->fields->blockList as $block) {
//						// $block is a simplexml object with attribute info about currency, type casting as below seems to work for adding up. plb 3-27-2015
//						$fineAmount = (float)$block->fields->owed->amount;
//						$finesVal   += $fineAmount;
//
//					}
//				}
//
//				$numHoldsAvailable = 0;
//				$numHoldsRequested = 0;
//				if (isset($lookupMyAccountInfoResponse->fields->holdRecordList)) {
//					foreach ($lookupMyAccountInfoResponse->fields->holdRecordList as $hold) {
//						if ($hold->fields->status == 'BEING_HELD') {
//							$numHoldsAvailable++;
//						} elseif ($hold->fields->status != 'EXPIRED') {
//							$numHoldsRequested++;
//						}
//					}
//				}
//
//				$numCheckedOut = 0;
//				if (isset($lookupMyAccountInfoResponse->fields->circRecordList)) {
//					foreach ($lookupMyAccountInfoResponse->fields->circRecordList as $checkedOut) {
//						if (empty($checkedOut->fields->claimsReturnedDate) && $checkedOut->fields->status != 'INACTIVE') {
//							$numCheckedOut++;
//						}
//					}
//				}

				$user->address1              = $Address1;
				$user->address2              = $City . ', ' . $State;
				$user->city                  = $City;
				$user->state                 = $State;
				$user->zip                   = $Zip;
//				$user->fines                 = sprintf('$%01.2f', $finesVal);
//				$user->finesVal              = $finesVal;
//				$user->numCheckedOutIls      = $numCheckedOut;
//				$user->numHoldsIls           = $numHoldsAvailable + $numHoldsRequested;
//				$user->numHoldsAvailableIls  = $numHoldsAvailable;
//				$user->numHoldsRequestedIls  = $numHoldsRequested;
				$user->patronType            = 0;
				$user->notices               = '-';
				$user->noticePreferenceLabel = 'E-mail';
				$user->web_note              = '';

				if ($userExistsInDB) {
					$user->update();
				} else {
					$user->created = date('Y-m-d');
					$user->insert();
				}

				$timer->logTime("patron logged in successfully");
				return $user;
			} else {
				if (isset($lookupMyAccountInfoResponse->messageList[0]->code) && $lookupMyAccountInfoResponse->messageList[0]->code == 'sessionTimedOut') {
					//If it was just a session timeout, just clear out the session
					/** @var Memcache $memCache */
					global $memCache;
					$memCacheKey = "horizon_ROA_session_token_info_$username";
					$memCache->delete($memCacheKey);
				} else {
					$timer->logTime("lookupMyAccountInfo failed");
					global $logger;
					$logger->log('Horizon ROA API call lookupMyAccountInfo failed.', PEAR_LOG_ERR);
				}
				return null;
			}
		}
	}


	public function hasNativeReadingHistory()
	{
		// TODO: Implement hasNativeReadingHistory() method.
	}

	public function getNumHolds($id)
	{
		// TODO: Implement getNumHolds() method.
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
	public function getMyCheckouts($patron)
	{
		$checkedOutTitles = array();

		//Get the session token for the user
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken) {
			return $checkedOutTitles;
		}

		//Now that we have the session token, get holds information
		$webServiceURL = $this->getWebServiceURL();

//		$circRecordDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/circRecord/describe", null, $sessionToken);
//		$circRecordRenewDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/circRecord/renew/describe", null, $sessionToken);
//		$itemDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/item/describe", null, $sessionToken);
//		$callDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/call/describe", null, $sessionToken);
//		$copyDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/copy/describe", null, $sessionToken);


		//Get a list of holds for the user
		$patronCheckouts = $this->getWebServiceResponse($webServiceURL . '/v1/user/patron/key/' . $patron->username . '?includeFields=circRecordList', null, $sessionToken);

		if (!empty($patronCheckouts->fields->circRecordList)) {
			$sCount = 0;
			require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';

			foreach ($patronCheckouts->fields->circRecordList as $checkoutRecord) {
				$checkOutKey = $checkoutRecord->key;
				$lookupCheckOutResponse = $this->getWebServiceResponse($webServiceURL . '/v1/circulation/circRecord/key/' . $checkOutKey, null, $sessionToken);
				if (isset($lookupCheckOutResponse->fields)) {
					$checkout = $lookupCheckOutResponse->fields;

					$itemId = $checkout->item->key;
					$bibId = $this->getBibId($itemId, $patron);
					if (!empty($bibId)) {
						//TODO: volumes?
						//TODO: Barcode?
						//TODO: fine amount

						$curTitle                   = array();
						$curTitle['checkoutSource'] = 'ILS';
						$curTitle['recordId']       = $bibId;
						$curTitle['shortId']        = $bibId;
						$curTitle['id']             = $bibId;
						$curTitle['itemid']         = $itemId;
						$curTitle['dueDate']        = strtotime($checkout->dueDate);
						$curTitle['checkoutdate']   = strtotime($checkout->checkOutDate);
						// Note: there is an overdue flag
						$curTitle['renewCount']     = $checkout->renewalCount;
						$curTitle['canrenew']       = true; //TODO: check for any rules
						$curTitle['renewIndicator'] = $checkOutKey;
						$curTitle['format']         = 'Unknown';
						$curTitle['overdue']        = $checkout->overdue; //TODO:

						$recordDriver = new MarcRecord($bibId);
						if ($recordDriver->isValid()) {
							$curTitle['coverUrl']      = $recordDriver->getBookcoverUrl('medium');
							$curTitle['groupedWorkId'] = $recordDriver->getGroupedWorkId();
							$curTitle['format']        = $recordDriver->getPrimaryFormat();
							$curTitle['title']         = $recordDriver->getTitle();
							$curTitle['title_sort']    = $recordDriver->getSortableTitle();
							$curTitle['author']        = $recordDriver->getPrimaryAuthor();
							$curTitle['link']          = $recordDriver->getLinkUrl();
							$curTitle['ratingData']    = $recordDriver->getRatingData();
						} else {
							// Presumably ILL Items
							$bibInfo = $this->getWebServiceResponse($webServiceURL . '/v1/catalog/bib/key/' . $bibId, null, $sessionToken);
							//TODO: include only title & author
							$simpleSortTitle        = preg_replace('/^The\s|^A\s/i', '', $bibInfo->fields->title); // remove beginning The or A
							$curTitle['title']      = $bibInfo->fields->title;
							$curTitle['title_sort'] = empty($simpleSortTitle) ? $bibInfo->fields->title : $simpleSortTitle;
							$curTitle['author']     = $bibInfo->fields->author;
						}

						$checkedOutTitles[] = $curTitle;

						//TODO: should sorting be set here
						//					$sCount++;
						//					$sortTitle = isset($curTitle['title_sort']) ? $curTitle['title_sort'] : $curTitle['title'];
						//					$sortKey   = $sortTitle;
						//					if ($sortOption == 'title') {
						//						$sortKey = $sortTitle;
						//					} elseif ($sortOption == 'author') {
						//						$sortKey = (isset($curTitle['author']) ? $curTitle['author'] : "Unknown") . '-' . $sortTitle;
						//					} elseif ($sortOption == 'dueDate') {
						//						if (isset($curTitle['dueDate'])) {
						//							if (preg_match('/.*?(\\d{1,2})[-\/](\\d{1,2})[-\/](\\d{2,4}).*/', $curTitle['dueDate'], $matches)) {
						//								$sortKey = $matches[3] . '-' . $matches[1] . '-' . $matches[2] . '-' . $sortTitle;
						//							} else {
						//								$sortKey = $curTitle['dueDate'] . '-' . $sortTitle;
						//							}
						//						}
						//					} elseif ($sortOption == 'format') {
						//						$sortKey = (isset($curTitle['format']) ? $curTitle['format'] : "Unknown") . '-' . $sortTitle;
						//					} elseif ($sortOption == 'renewed') {
						//						$sortKey = (isset($curTitle['renewCount']) ? $curTitle['renewCount'] : 0) . '-' . $sortTitle;
						//					} elseif ($sortOption == 'holdQueueLength') {
						//						$sortKey = (isset($curTitle['holdQueueLength']) ? $curTitle['holdQueueLength'] : 0) . '-' . $sortTitle;
						//					}
						//					$sortKey                    .= "_$sCount";
						//					$checkedOutTitles[$sortKey] = $curTitle;

					}
				}
			}
		}
		return $checkedOutTitles;
	}

	/**
	 * @return boolean true if the driver can renew all titles in a single pass
	 */
	public function hasFastRenewAll()
	{
		// TODO: Implement hasFastRenewAll() method.
	}

	/**
	 * Renew all titles currently checked out to the user
	 *
	 * @param $patron  User
	 * @return mixed
	 */
	public function renewAll($patron)
	{
		// TODO: Implement renewAll() method.
	}

	/**
	 * Renew a single title currently checked out to the user
	 *
	 * @param $patron     User
	 * @param $recordId   string
	 * @param $itemId     string
	 * @param $itemIndex  string
	 * @return mixed
	 */
	public function renewItem($patron, $recordId, $itemId, $itemIndex)
	{
		// TODO: Implement renewItem() method.
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
	public function getMyHolds($patron)
	{
		$availableHolds   = array();
		$unavailableHolds = array();
		$holds            = array(
			'available'   => $availableHolds,
			'unavailable' => $unavailableHolds
		);

		//Get the session token for the user
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken) {
			return $holds;
		}

		//Now that we have the session token, get holds information
		$webServiceURL = $this->getWebServiceURL();

//		$holdRecordDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/describe", null, $sessionToken);
//		$itemDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/item/describe", null, $sessionToken);
//		$callDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/call/describe", null, $sessionToken);
//		$copyDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/copy/describe", null, $sessionToken);

		//Get a list of holds for the user
		// (Call now includes Item information for when the hold is an item level hold.)
//		$patronHolds = $this->getWebServiceResponse($webServiceURL . '/v1/user/patron/key/' . $patron->username . '?includeFields=holdRecordList{*,item{itemType,barcode,call{callNumber}}}', null, $sessionToken);
		$patronHolds = $this->getWebServiceResponse($webServiceURL . '/v1/user/patron/key/' . $patron->username . '?includeFields=holdRecordList', null, $sessionToken);
		if ($patronHolds && isset($patronHolds->fields)) {
			require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
			foreach ($patronHolds->fields->holdRecordList as $holdRecord) {
				$holdKey                = $holdRecord->key;
				$lookupHoldResponse = $this->getWebServiceResponse($webServiceURL . '/v1/circulation/holdRecord/key/' . $holdKey, null, $sessionToken);
				if (isset($lookupHoldResponse->fields)) {
					$hold = $lookupHoldResponse->fields;

					//TODO: Volume for title?
					//TODO: AvailableTime (availableTime only referenced in ilsHolds template and Holds Excel function)

					$curHold               = array();
					$bibId                 = $hold->bib->key;
					$expireDate            = empty($hold->expirationDate) ? null : $hold->expirationDate;
					$reactivateDate        = empty($hold->suspendEndDate) ? null : $hold->suspendEndDate;
					$createDate            = empty($hold->placedDate) ? null : $hold->placedDate;
					$fillByDate            = empty($hold->fillByDate) ? null : $hold->fillByDate;
					$curHold['id']                    = $bibId; // Template uses record Id for the ID instead of the hold ID
					$curHold['recordId']              = $bibId;
					$curHold['shortId']               = $bibId;
					$curHold['holdSource']            = 'ILS';
					$curHold['itemId']                = empty($hold->item->key) ? '' : $hold->item->key; //TODO: test
					$curHold['cancelId']              = $holdKey;
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
					$curPickupBranch       = new Location();
					$curPickupBranch->code = $hold->pickupLibrary->key;
					if ($curPickupBranch->find(true)) {
						$curPickupBranch->fetch();
						$curHold['currentPickupId']   = $curPickupBranch->locationId;
						$curHold['currentPickupName'] = $curPickupBranch->displayName;
						$curHold['location']          = $curPickupBranch->displayName;
					}

					$recordDriver = new MarcRecord($bibId);
					if ($recordDriver->isValid()) {
						$curHold['title']           = $recordDriver->getTitle();
						$curHold['author']          = $recordDriver->getPrimaryAuthor();
						$curHold['sortTitle']       = $recordDriver->getSortableTitle();
						$curHold['format']          = $recordDriver->getFormat();
						$curHold['isbn']            = $recordDriver->getCleanISBN();
						$curHold['upc']             = $recordDriver->getCleanUPC();
						$curHold['format_category'] = $recordDriver->getFormatCategory();
						$curHold['coverUrl']        = $recordDriver->getBookcoverUrl('medium');
						$curHold['link']            = $recordDriver->getRecordUrl();

						//Load rating information
						$curHold['ratingData']      = $recordDriver->getRatingData();

						if ($hold->fields->holdType == 'COPY') {

							$curHold['title2'] = $hold->fields->item->fields->itemType->key . ' - ' . $hold->fields->item->fields->call->fields->callNumber;


//						$itemInfo = $this->getWebServiceResponse($webServiceURL . '/v1' . $hold->fields->selectedItem->resource . '/key/' . $hold->fields->selectedItem->key. '?includeFields=barcode,call{*}', null, $sessionToken);
//						$curHold['title2'] = $itemInfo->fields->itemType->key . ' - ' . $itemInfo->fields->call->fields->callNumber;
							//TODO: Verify that this matches the title2 built below
//						if (isset($itemInfo->fields)){
//							$barcode = $itemInfo->fields->barcode;
//							$copies = $recordDriver->getCopies();
//							foreach ($copies as $copy){
//								if ($copy['itemId'] == $barcode){
//									$curHold['title2'] = $copy['shelfLocation'] . ' - ' . $copy['callNumber'];
//									break;
//								}
//							}
//						}
						}

					} else {
						// If we don't have good marc record, ask the ILS for title info
						//TODO: has format?
						$bibInfo              = $this->getWebServiceResponse($webServiceURL . '/v1/catalog/bib/key/' . $bibId, null, $sessionToken);
						$curHold['title']     = $bibInfo->fields->title;
						$simpleSortTitle      = preg_replace('/^The\s|^A\s/i', '', $bibInfo->fields->title); // remove begining The or A
						$curHold['sortTitle'] = empty($simpleSortTitle) ? $bibInfo->fields->title : $simpleSortTitle;
						$curHold['author']    = $bibInfo->fields->author;

//// TODO: ILL Holds are item level holds as well; but I doubt we need the title2 in that case.
//					if ($hold->fields->holdType == 'COPY'){
//						$curHold['title2'] = $hold->fields->item->fields->itemType->key . ' - ' . $hold->fields->item->fields->call->fields->callNumber;
//					}

					}

					if (!isset($curHold['status']) || strcasecmp($curHold['status'], "being_held") != 0) {
						//TODO: need the right available status
						$holds['unavailable'][] = $curHold;
					} else {
						$holds['available'][] = $curHold;
					}
				}
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
		$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch, 'request', $cancelDate);
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
	 * @param   string $type                          Whether to place a hold or recall
	 * @param   null|string $cancelIfNotFilledByDate  The date to cancel the Hold if it isn't filled
	 * @return  array                                 Array of (success and message) to be used for an AJAX response
	 * @access  public
	 */
	function placeItemHold($patron, $recordId, $itemId, $pickUpLocation = null, $type = 'request', $cancelIfNotFilledByDate = null)
	{

		//Get the session token for the user
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken) {
			return array(
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please login and try again');
		}

		if (empty($pickUpLocation)) {
			$pickUpLocation = $patron->homeLocationCode;
		}
		//create the hold using the web service
		$webServiceURL = $this->getWebServiceURL();

		$holdData = array(
			'patronBarcode' => $patron->getBarcode(),
			'pickupLibrary' => array(
				'resource' => '/policy/library',
				'key'      => strtoupper($pickUpLocation)
			),
		);

		if (!empty($itemId)) {
			//TODO: item-level holds haven't been tested yet.
			$holdData['itemBarcode'] = $itemId;
			$holdData['holdType']    = 'COPY';
		} else {
			$holdData['holdType']   = 'TITLE';
			$holdData['bib']         = array(
				'resource' => '/catalog/bib',
				'key'      => $recordId
			);
		}

		if (!empty($cancelIfNotFilledByDate)) {
			$timestamp = strtotime($cancelIfNotFilledByDate);
			if ($timestamp) {
				$holdData['fillByDate'] = date('Y-m-d', $timestamp);
			}
		}
//				$holdRecordDescribe = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/describe", null, $sessionToken);
//				$placeHoldDescribe  = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/placeHold/describe", null, $sessionToken);
		$createHoldResponse = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/placeHold", $holdData, $sessionToken);

		$hold_result = array(
			'success' => false,
			'message' => 'Your hold could not be placed. '
		);
		if (isset($createHoldResponse->messageList)) {
			$errorMessage = '';
			foreach ($createHoldResponse->messageList as $error){
				$errorMessage .= $error->message.'; ';
			}
			$hold_result['message'] .= $errorMessage;

			global $logger;
			$logger->log('Horizon ROA Place Hold Error: ' . $errorMessage, PEAR_LOG_ERR);

		} elseif (!empty($createHoldResponse->holdRecord)) {
			$hold_result['success'] = true;
			$hold_result['message'] = 'Your hold was placed successfully.';
		}

		// Retrieve Full Marc Record
		require_once ROOT_DIR . '/RecordDrivers/Factory.php';
		$record = RecordDriverFactory::initRecordDriverById('ils:' . $recordId);
		if (!$record) {
			$title = null;
		} else {
			$title = $record->getTitle();
		}

		$hold_result['title'] = $title;
		$hold_result['bid']   = $recordId;

		global $analytics;
		if ($analytics) {
			if ($hold_result['success'] == true) {
				$analytics->addEvent('ILS Integration', 'Successful Hold', $title);
			} else {
				$analytics->addEvent('ILS Integration', 'Failed Hold', $hold_result['message'] . ' - ' . $title);
			}
		}
		return $hold_result;
	}


	function cancelHold($patron, $recordId, $cancelId)
	{
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken) {
			return array(
				'success' => false,
				'message' => 'Sorry, we could not connect to the circulation system.');
		}

		//create the hold using the web service
		$webServiceURL = $this->getWebServiceURL();

		$cancelHoldResponse = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/key/$cancelId", null, $sessionToken, 'DELETE');

		if (empty($cancelHoldResponse)) {
			return array(
				'success' => true,
				'message' => 'The hold was successfully canceled'
			);
		} else {
			global $logger;
			$errorMessage = 'Horizon ROA Cancel Hold Error: ';
			foreach ($cancelHoldResponse->messageList as $error){
				$errorMessage .= $error->message.'; ';
			}
			$logger->log($errorMessage, PEAR_LOG_ERR);

			return array(
				'success' => false,
				'message' => 'Sorry, the hold was not canceled');
		}

	}

	function freezeHold($patron, $recordId, $holdToFreezeId, $dateToReactivate)
	{
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken) {
			return array(
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please login and try again');
		}

		$formattedDateToReactivate = $dateToReactivate ? date('Y-m-d', strtotime($dateToReactivate)) : null;

		$params = array(
			'suspendEndDate' => $formattedDateToReactivate,
			'holdRecord'     => array(
				'key'      => $holdToFreezeId,
				'resource' => '/circulation/holdRecord',
			)
		);

		$webServiceURL = $this->getWebServiceURL();
//		$describe  = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/unsuspendHold/describe", null, $sessionToken);
		$updateHoldResponse = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/suspendHold", $params, $sessionToken, 'POST');

		if (!empty($updateHoldResponse->holdRecord)) {
			$frozen = translate('frozen');
			return array(
				'success' => true,
				'message' => "The hold has been $frozen."
			);
		} else {
			$messages = array();
			if (isset($updateHoldResponse->messageList)) {
				foreach ($updateHoldResponse->messageList as $message) {
					$messages[] = $message->message;
				}
			}
			$freeze = translate('freeze');

			global $logger;
			$errorMessage = 'Horizon ROA Freeze Hold Error: '. ($messages ? implode('; ', $messages) : '');
			$logger->log($errorMessage, PEAR_LOG_ERR);

			return array(
				'success' => false,
				'message' => "Failed to $freeze hold : ". implode('; ', $messages)
			);
		}
	}

	function thawHold($patron, $recordId, $holdToThawId)
	{
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken) {
			return array(
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please login and try again');
		}

		$params = array(
			'holdRecord'     => array(
				'key'      => $holdToThawId,
				'resource' => '/circulation/holdRecord',
			)
		);

		$webServiceURL = $this->getWebServiceURL();
//		$describe  = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/unsuspendHold/describe", null, $sessionToken);
		$describe  = $this->getWebServiceResponse($webServiceURL . "/circulation/holdRecord/changePickupLibrary/describe", null, $sessionToken);
		$updateHoldResponse = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/unsuspendHold", $params, $sessionToken, 'POST');

		if (!empty($updateHoldResponse->holdRecord)) {
			$thawed = translate('thawed');
			return array(
				'success' => true,
				'message' => "The hold has been $thawed."
			);
		} else {
			$messages = array();
			if (isset($updateHoldResponse->messageList)) {
				foreach ($updateHoldResponse->messageList as $message) {
					$messages[] = $message->message;
				}
			}
			$thaw = translate('thaw');

			global $logger;
			$errorMessage = 'Horizon ROA Thaw Hold Error: '. ($messages ? implode('; ', $messages) : '');
			$logger->log($errorMessage, PEAR_LOG_ERR);

			return array(
				'success' => false,
				'message' => "Failed to $thaw hold : ". implode('; ', $messages)
			);
		}
	}


	function changeHoldPickupLocation($patron, $recordId, $holdToUpdateId, $newPickupLocation)
	{
		$sessionToken = $this->getSessionToken($patron);
		if (!$sessionToken) {
			return array(
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please login and try again');
		}

		$params = array(
			'pickupLibrary' => array(
				'key'      => $newPickupLocation,
				'resource' => '/policy/library',
			),
			'holdRecord'    => array(
				'key'      => $holdToUpdateId,
				'resource' => '/circulation/holdRecord',
			)
		);

		$webServiceURL      = $this->getWebServiceURL();
		$describe           = $this->getWebServiceResponse($webServiceURL . "/circulation/holdRecord/changePickupLibrary/describe", null, $sessionToken);
		$updateHoldResponse = $this->getWebServiceResponse($webServiceURL . "/v1/circulation/holdRecord/changePickupLibrary", $params, $sessionToken, 'POST');

		if (!empty($updateHoldResponse->holdRecord)) {
			return array(
				'success' => true,
				'message' => 'The pickup location has been updated.'
			);
		} else {
			$messages = array();
			if (isset($updateHoldResponse->messageList)) {
				foreach ($updateHoldResponse->messageList as $message) {
					$messages[] = $message->message;
				}
			}
			global $logger;
			$errorMessage = 'Horizon ROA Change Hold Pickup Location Error: ' . ($messages ? implode('; ', $messages) : '');
			$logger->log($errorMessage, PEAR_LOG_ERR);

			return array(
				'success' => false,
				'message' => 'Failed to update the pickup location : ' . implode('; ', $messages)
			);
		}
	}

	function getBibId($itemId, $patron) {
		$bibId = null;
		//TODO cache these
		$webServiceURL = $this->getWebServiceURL();
		$sessionToken = $this->getSessionToken($patron);

//		$bibLookupResponse  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/item/key/" . $itemId, null, $sessionToken);
		$bibLookupResponse  = $this->getWebServiceResponse($webServiceURL . "/v1/catalog/item/key/" . $itemId .'?includeFields=bib', null, $sessionToken);
		if (!empty($bibLookupResponse->fields)) {
			$bibId = $bibLookupResponse->fields->bib->key;
		}

		return $bibId;

	}
}