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

require_once 'DriverInterface.php';
require_once ROOT_DIR . '/Drivers/Horizon.php';

abstract class HorizonAPI extends Horizon{

	private $webServiceURL = null;
	public function getWebServiceURL()
	{
		if (empty($this->webServiceURL)) {
			$webServiceURL = null;
			if (!empty($this->accountProfile->patronApiUrl)) {
				$webServiceURL = trim($this->accountProfile->patronApiUrl);
			} elseif (!empty($configArray['Catalog']['webServiceUrl'])) {
				$webServiceURL = $configArray['Catalog']['webServiceUrl'];
			} else {
				global $logger;
				$logger->log('No Web Service URL defined in Horizon ROA API Driver', PEAR_LOG_CRIT);
			}
			$this->webServiceURL = rtrim($webServiceURL, '/'); // remove any trailing slash because other functions will add it.
		}
		return $this->webServiceURL;
	}

	//TODO: Additional caching of sessionIds by patron
	private static $sessionIdsForUsers = array();

	/** login the user via web services API *
	 * @param $username
	 * @param $password
	 * @param $validatedViaSSO
	 * @return User|null
	 */
	public function patronLogin($username, $password, $validatedViaSSO){
		global $timer;
		global $configArray;

		//Remove any spaces from the barcode
		$username = preg_replace('/[\s]/', '', $username); // remove all space characters
		$password = trim($password);

		//Authenticate the user via WebService
		//First call loginUser
		list($userValid, $sessionToken, $userID) = $this->initialLoginViaWebService($username, $password);
		if ($validatedViaSSO){
			$userValid = true;
		}
		if ($userValid){
			$webServiceURL               = $this->getWebServiceURL();
			$lookupMyAccountInfoResponse = $this->getWebServiceResponse($webServiceURL . '/standard/lookupMyAccountInfo?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&includeAddressInfo=true&includeHoldInfo=true&includeBlockInfo=true&includeItemsOutInfo=true');
			if ($lookupMyAccountInfoResponse){
				$fullName = (string)$lookupMyAccountInfoResponse->name;
				list($fullName, $lastName, $firstName) = $this->splitFullName($fullName);

				$email = '';
				if (isset($lookupMyAccountInfoResponse->AddressInfo)){
					if (isset($lookupMyAccountInfoResponse->AddressInfo->email)){
						$email = (string)$lookupMyAccountInfoResponse->AddressInfo->email;
					}
				}

				$userExistsInDB  = false;
				$user            = new User();
//				$user->source    = $this->accountProfile->name;
				$user->ilsUserId = $userID;
				if ($user->find(true)){
					$userExistsInDB = true;
				}

				$forceDisplayNameUpdate = false;
				$firstName = isset($firstName) ? $firstName : '';
				if ($user->firstname != $firstName) {
					$user->firstname = $firstName;
					$forceDisplayNameUpdate = true;
				}
				$lastName = isset($lastName) ? $lastName : '';
				if ($user->lastname != $lastName){
					$user->lastname = isset($lastName) ? $lastName : '';
					$forceDisplayNameUpdate = true;
				}
				if ($forceDisplayNameUpdate){
					$user->displayName = '';
				}
				$user->fullname     = isset($fullName) ? $fullName : '';
				$user->cat_username = $username;
				$user->cat_password = $password;
				$user->email        = $email;

				if (isset($lookupMyAccountInfoResponse->AddressInfo)){
					$Address1 = (string)$lookupMyAccountInfoResponse->AddressInfo->line1;
					if (isset($lookupMyAccountInfoResponse->AddressInfo->cityState)){
						$cityState = (string)$lookupMyAccountInfoResponse->AddressInfo->cityState;
						list($City, $State) = explode(', ', $cityState);
					}else{
						$City = "";
						$State = "";
					}
					$Zip = (string)$lookupMyAccountInfoResponse->AddressInfo->postalCode;

				}else{
					$Address1 = "";
					$City = "";
					$State = "";
					$Zip = "";
				}

				//Get additional information about the patron's home branch for display.
				if (isset($lookupMyAccountInfoResponse->locationID)){
					$user->setUserHomeLocations(trim((string)$lookupMyAccountInfoResponse->locationID));
				} else {
					global $logger;
					$logger->log('HorizonAPI Driver: No Home Library Location or Hold location found in account look-up. User : '.$user->id, PEAR_LOG_ERR);
				}

				$finesVal = 0;
				if (isset($lookupMyAccountInfoResponse->BlockInfo)){
					foreach ($lookupMyAccountInfoResponse->BlockInfo as $block){
						// $block is a simplexml object with attribute info about currency, type casting as below seems to work for adding up. plb 3-27-2015
						$fineAmount = (float) $block->balance;
						$finesVal += $fineAmount;
					}
				}

				$numHoldsAvailable = 0;
				$numHoldsRequested = 0;
				if (isset($lookupMyAccountInfoResponse->HoldInfo)){
					foreach ($lookupMyAccountInfoResponse->HoldInfo as $hold){
						if ($hold->status == 'FILLED'){
							$numHoldsAvailable++;
						}else{
							$numHoldsRequested++;
						}
					}
				}

				$user->address1              = $Address1;
				$user->address2              = $City . ', ' . $State;
				$user->city                  = $City;
				$user->state                 = $State;
				$user->zip                   = $Zip;
				$user->phone                 = isset($lookupMyAccountInfoResponse->phone) ? (string)$lookupMyAccountInfoResponse->phone : '';
				$user->fines                 = sprintf('$%01.2f', $finesVal);
				$user->finesVal              = $finesVal;
				$user->expires               = ''; //TODO: Determine if we can get this
				$user->expireClose           = 0;
				$user->numCheckedOutIls      = isset($lookupMyAccountInfoResponse->ItemsOutInfo) ? count($lookupMyAccountInfoResponse->ItemsOutInfo) : 0;
				$user->numHoldsIls           = $numHoldsAvailable + $numHoldsRequested;
				$user->numHoldsAvailableIls  = $numHoldsAvailable;
				$user->numHoldsRequestedIls  = $numHoldsRequested;
				$user->patronType            = 0;
				$user->notices               = '-';
				$user->noticePreferenceLabel = 'E-mail';
				$user->web_note              = '';

				if ($userExistsInDB){
					$user->update();
				}else{
					$user->created = date('Y-m-d');
					$user->insert();
				}

				$timer->logTime("patron logged in successfully");
				return $user;
			} else {
				$timer->logTime("lookupMyAccountInfo failed");
				global $logger;
				$logger->log('Horizon API call lookupMyAccountInfo failed.', PEAR_LOG_ERR);
//				$logger->log($configArray['Catalog']['webServiceUrl'] . '/standard/lookupMyAccountInfo?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&includeAddressInfo=true&includeHoldInfo=true&includeBlockInfo=true&includeItemsOutInfo=true', PEAR_LOG_ERR);
				return null;
			}
		}
	}

	protected function initialLoginViaWebService($username, $password) {
		global $configArray;
		$webServiceURL     = $this->getWebServiceURL();
		$loginUserUrl      = $webServiceURL . '/standard/loginUser?clientID=' . $configArray['Catalog']['clientId'] . '&login=' . urlencode($username) . '&password=' . urlencode($password);
		$loginUserResponse = $this->getWebServiceResponse($loginUserUrl);
		if (!$loginUserResponse){
			return array(false, false, false);
		}else if (isset($loginUserResponse->Fault)){
			return array(false, false, false);
		}else{
			//We got at valid user, next call lookupMyAccountInfo
			if (isset($loginUserResponse->sessionToken)){
				$userID       = (string)$loginUserResponse->userID;
				$sessionToken = (string)$loginUserResponse->sessionToken;
				HorizonAPI::$sessionIdsForUsers[$userID] = $sessionToken;
				return array(true, $sessionToken, $userID);
			}else{
				return array(false, false, false);
			}
		}
	}
	/**
	 * @param User $patron
	 * @return array
	 */
	protected function loginViaWebService($patron, $password = ''){
		$userID = $patron->ilsUserId;
		if (isset(HorizonAPI::$sessionIdsForUsers[$userID])){
			$sessionToken = HorizonAPI::$sessionIdsForUsers[$userID];
			return array(true, $sessionToken, $userID);
		}else{
			$username = $patron->cat_username;
			$password = $patron->cat_password;
			return $this->initialLoginViaWebService($username, $password);
		}
	}

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron    The user to load transactions for
	 *
	 * @return array          Array of the patron's holds
	 * @access public
	 */
	public function getMyHolds($patron){
		global $configArray;

		$availableHolds = array();
		$unavailableHolds = array();
		$holds = array(
			'available'   => $availableHolds,
			'unavailable' => $unavailableHolds
		);

		//Get the session token for the user
		if (isset(HorizonAPI::$sessionIdsForUsers[$patron->id])){
			$sessionToken = HorizonAPI::$sessionIdsForUsers[$patron->id];
		}else{
			//Log the user in
			list($userValid, $sessionToken) = $this->loginViaWebService($patron);
			if (!$userValid){
				return $holds;
			}
		}

		//Now that we have the session token, get holds information
		$lookupMyAccountInfoResponse = $this->getWebServiceResponse($this->getWebServiceURL() . '/standard/lookupMyAccountInfo?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&includeHoldInfo=true');
		if (isset($lookupMyAccountInfoResponse->HoldInfo)){
			require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
			foreach ($lookupMyAccountInfoResponse->HoldInfo as $hold){
				$curHold = array();
				$bibId          = (string) $hold->titleKey;
				$expireDate     = (string) $hold->expireDate;
				$reactivateDate = (string) $hold->reactivateDate;
				$curHold['user']               = $patron->getNameAndLibraryLabel(); //TODO: Likely not needed, because Done in Catalog Connection
				$curHold['id']                 = $bibId;
				$curHold['holdSource']         = 'ILS';
				$curHold['itemId']             = (string)$hold->itemKey;
				$curHold['cancelId']           = (string)$hold->holdKey;
				$curHold['position']           = (string)$hold->queuePosition;
				$curHold['recordId']           = $bibId;
				$curHold['shortId']            = $bibId;
				$curHold['title']              = (string)$hold->title;
				$curHold['sortTitle']          = (string)$hold->title;
				$curHold['author']             = (string)$hold->author;
				$curHold['location']           = (string)$hold->pickupLocDescription;
				$curHold['locationUpdateable'] = true;
				$curHold['currentPickupName']  = $curHold['location'];
				$curHold['status']             = ucfirst(strtolower((string)$hold->status));
				$curHold['expire']             = strtotime($expireDate);
				$curHold['reactivate']         = $reactivateDate;
				$curHold['reactivateTime']     = strtotime($reactivateDate);
				$curHold['cancelable']         = strcasecmp($curHold['status'], 'Suspended') != 0;
				$curHold['frozen']             = strcasecmp($curHold['status'], 'Suspended') == 0;
				$curHold['freezeable'] = true;
				if (strcasecmp($curHold['status'], 'Transit') == 0) {
					$curHold['freezeable'] = false;
				}

				$recordDriver = new MarcRecord($bibId);
				if ($recordDriver->isValid()){
					$curHold['sortTitle']       = $recordDriver->getSortableTitle();
					$curHold['format']          = $recordDriver->getFormat();
					$curHold['isbn']            = $recordDriver->getCleanISBN();
					$curHold['upc']             = $recordDriver->getCleanUPC();
					$curHold['format_category'] = $recordDriver->getFormatCategory();
					$curHold['coverUrl']        = $recordDriver->getBookcoverUrl('medium');
					$curHold['link']            = $recordDriver->getRecordUrl();

					//Load rating information
					$curHold['ratingData']      = $recordDriver->getRatingData();

					if (empty($curHold['title'])){
						$curHold['title'] = $recordDriver->getTitle();
					}
					if (empty($curHold['author'])){
						$curHold['author'] = $recordDriver->getPrimaryAuthor();
					}
				}

				if (!isset($curHold['status']) || strcasecmp($curHold['status'], "filled") != 0){
					$holds['unavailable'][] = $curHold;
				}else{
					$holds['available'][]   = $curHold;
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
	 * @param   User    $patron       The User to place a hold for
	 * @param   string  $recordId     The id of the bib record
	 * @param   string  $pickupBranch The branch where the user wants to pickup the item when available
	 * @return  mixed                 True if successful, false if unsuccessful
	 *                                If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null) {
		$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch);
		return $result;
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param   User    $patron     The User to place a hold for
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $itemId     The id of the item to hold
	 * @param   string  $pickupBranch
	 * @param   string  $type       Whether to place a hold or recall
	 * @return  mixed               True if successful, false if unsuccessful
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function placeItemHold($patron, $recordId, $itemId, $pickupBranch, $type = 'request') {
		global $configArray;

		$userId = $patron->id;

		//Get the session token for the user
		if (isset(HorizonAPI::$sessionIdsForUsers[$userId])){
			$sessionToken = HorizonAPI::$sessionIdsForUsers[$userId];
		}else{
			//Log the user in
			list($userValid, $sessionToken) = $this->loginViaWebService($patron);
			if (!$userValid){
				return array(
					'success' => false,
					'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again');
			}
		}

		// Retrieve Full Marc Record
		require_once ROOT_DIR . '/RecordDrivers/Factory.php';
		$record = RecordDriverFactory::initRecordDriverById('ils:' . $recordId);
		if (!$record) {
			$title = null;
		}else{
			$title = $record->getTitle();
		}

			if ($type == 'cancel' || $type == 'recall' || $type == 'update') {
				$result = $this->updateHold($patron, $recordId, $type/*, $title*/);
				$result['title'] = $title;
				$result['bid']   = $recordId;
				return $result;

			} else {
				//create the hold using the web service
				$createHoldUrl = $this->getWebServiceURL() . '/standard/createMyHold?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&pickupLocation=' . $pickupBranch . '&titleKey=' . $recordId ;
				if (!empty($itemId)){
					$createHoldUrl .= '&itemKey=' . $itemId;
				}

				$createHoldResponse = $this->getWebServiceResponse($createHoldUrl);

				$hold_result = array();
				if ($createHoldResponse == "true"){ //successful hold responses return a string "true"
					$hold_result['success'] = true;
					$hold_result['message'] = 'Your hold was placed successfully.';
				}else{
					$hold_result['success'] = false;
					$hold_result['message'] = 'Your hold could not be placed. ';
					if (isset($createHoldResponse->message)){
						$hold_result['message'] .= (string)$createHoldResponse->message;
					}else if (isset($createHoldResponse->string)){
						$hold_result['message'] .= (string)$createHoldResponse->string;
					}

				}

				$hold_result['title']  = $title;
				$hold_result['bid']    = $recordId;
				}
				//Clear the patron profile
				return $hold_result;

	}

	function cancelHold($patron, $recordId, $cancelId) {
		return $this->updateHoldDetailed($patron, 'cancel', null, $cancelId, '', '');
	}

	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate) {
		return $this->updateHoldDetailed($patron, 'update', null, $itemToFreezeId, '', 'on');
	}

	function thawHold($patron, $recordId, $itemToThawId) {
		return $this->updateHoldDetailed($patron, 'update', null, $itemToThawId, '', 'off');
	}

	function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation) {
		return $this->updateHoldDetailed($patron, 'update', null, $itemToUpdateId, $newPickupLocation, 'off');
	}

	public function updateHold($requestId, $patronId, $type){
		$xnum = "x" . $_REQUEST['x'];
		//Strip the . off the front of the bib and the last char from the bib
		if (isset($_REQUEST['cancelId'])){
			$cancelId = $_REQUEST['cancelId'];
		}else{
			$cancelId = substr($requestId, 1, -1);
		}
		$locationId = $_REQUEST['location'];
		$freezeValue = isset($_REQUEST['freeze']) ? 'on' : 'off';
		return $this->updateHoldDetailed($patronId, $type, /*$title,*/ $xnum, $cancelId, $locationId, $freezeValue);
	}

	/**
	 * Update a hold that was previously placed in the system.
	 * Can cancel the hold or update pickup locations.
	 */
	public function updateHoldDetailed($patron, $type, /*$titles,*/ $xNum, $cancelId, $locationId, $freezeValue='off'){
		global $configArray;

		//Get the session token for the user
			//Log the user in
		list($userValid, $sessionToken) = $this->loginViaWebService($patron);
		if (!$userValid){
			return array(
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again');
		}

		if (!isset($xNum)){ //AJAX function passes IDs through $cancelID below shouldn't be needed anymore. plb 2-4-2015
			if (isset($_REQUEST['waitingholdselected']) || isset($_REQUEST['availableholdselected'])){
				$waitingHolds   = isset($_REQUEST['waitingholdselected'])   ? $_REQUEST['waitingholdselected'] : array();
				$availableHolds = isset($_REQUEST['availableholdselected']) ? $_REQUEST['availableholdselected'] : array();
				$holdKeys       = array_merge($waitingHolds, $availableHolds);
			}else{
				$holdKeys = is_array($cancelId) ? $cancelId : array($cancelId);
			}
		}

//		$loadTitles = empty($titles);
//		if ($loadTitles) {
			$holds          = $this->getMyHolds($patron);
			$combined_holds = array_merge($holds['unavailable'], $holds['available']);
//		}
//		$logger->log("Load titles = $loadTitles", PEAR_LOG_DEBUG); // move out of foreach loop


		$titles = array();
		if ($type == 'cancel'){
			$allCancelsSucceed = true;
			$failure_messages  = array();

			foreach ($holdKeys as $holdKey){
				$title = 'an item';  // default in case title name isn't found.

				foreach ($combined_holds as $hold){
					if ($hold['cancelId'] == $holdKey) {
						$title = $hold['title'];
						break;
					}
				}
				$titles[] = $title; // build array of all titles


				//create the hold using the web service
				$cancelHoldUrl     = $this->getWebServiceURL() . '/standard/cancelMyHold?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&holdKey=' . $holdKey;
				$cancelHoldResponse = $this->getWebServiceResponse($cancelHoldUrl);

				if (!$cancelHoldResponse){
					$allCancelsSucceed          = false;
					$failure_messages[$holdKey] = "The hold for $title could not be cancelled.  Please try again later or see your librarian.";
				}
			}
			if ($allCancelsSucceed){
				$plural = count($holdKeys) > 1;

				return array(
					'title'   => $titles,
					'success' => true,
					'message' => 'Your hold'.($plural ? 's were' : ' was' ).' cancelled successfully.');
			}else{
				return array(
					'title'   => $titles,
					'success' => false,
					'message' => $failure_messages
				);
			}

		}else{
			if ($locationId){
				$allLocationChangesSucceed = true;

				foreach ($holdKeys as $holdKey){

					foreach ($combined_holds as $hold){
						if ($hold['cancelId'] == $holdKey) {
							$title = $hold['title'];
							break;
						}
					}
					$titles[] = $title; // build array of all titles

					//create the hold using the web service
					$changePickupLocationUrl      = $this->getWebServiceURL() . '/standard/changePickupLocation?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&holdKey=' . $holdKey . '&newLocation=' . $locationId;
					$changePickupLocationResponse = $this->getWebServiceResponse($changePickupLocationUrl);

					if (!$changePickupLocationResponse){
						$allLocationChangesSucceed = false;
					}
				}
				if ($allLocationChangesSucceed){
					return array(
						'title'   => $titles,
						'success' => true,
						'message' => 'Pickup location for your hold(s) was updated successfully.');
				}else{
					return array(
						'title'   => $titles,
						'success' => false,
						'message' => 'Pickup location for your hold(s) was could not be updated.  Please try again later or see your librarian.');
				}
			}else{
				//Freeze/Thaw the hold
				if ($freezeValue == 'on'){
					//Suspend the hold
					$reactivationDate = strtotime($_REQUEST['reactivationDate']);
					$reactivationDate = date('Y-m-d', $reactivationDate);
					$allLocationChangesSucceed = true;

					foreach ($holdKeys as $holdKey){
						foreach ($combined_holds as $hold){
							if ($hold['cancelId'] == $holdKey) {
								$title = $hold['title'];
								break;
							}
						}
						$titles[] = $title; // build array of all titles

						//create the hold using the web service
						$changePickupLocationUrl      = $this->getWebServiceURL() . '/standard/suspendMyHold?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&holdKey=' . $holdKey . '&suspendEndDate=' . $reactivationDate;
						$changePickupLocationResponse = $this->getWebServiceResponse($changePickupLocationUrl);

						if (!$changePickupLocationResponse){
							$allLocationChangesSucceed = false;
						}
					}

					$frozen = translate('frozen');
					if ($allLocationChangesSucceed){
						return array(
							'title'   => $titles,
							'success' => true,
							'message' => "Your hold(s) were $frozen successfully.");
					}else{
						return array(
							'title'   => $titles,
							'success' => false,
							'message' => "Some holds could not be $frozen.  Please try again later or see your librarian.");
					}
				}else{
					//Reactivate the hold
					$allUnsuspendsSucceed = true;

					foreach ($holdKeys as $holdKey){
						foreach ($combined_holds as $hold){
							if ($hold['cancelId'] == $holdKey) {
								$title = $hold['title'];
								break;
							}
						}
						//create the hold using the web service
						$changePickupLocationUrl      = $this->getWebServiceURL() . '/standard/unsuspendMyHold?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&holdKey=' . $holdKey;
						$changePickupLocationResponse = $this->getWebServiceResponse($changePickupLocationUrl);

						if (!$changePickupLocationResponse){
							$allUnsuspendsSucceed = false;
						}
					}

					$thawed = translate('thawed');
					if ($allUnsuspendsSucceed){
						return array(
							'title'   => $titles,
							'success' => true,
							'message' => "Your hold(s) were $thawed successfully.");
					}else{
						return array(
							'title'   => $titles,
							'success' => false,
							'message' => "Some holds could not be $thawed.  Please try again later or see your librarian.");
					}
				}
			}
		}
	}

	public function getMyCheckouts($patron, $page = 1, $recordsPerPage = -1, $sortOption = 'dueDate') {
		global $configArray;

		$userId = $patron->id;

		$checkedOutTitles = array();

		//Get the session token for the user
		//Log the user in
		list($userValid, $sessionToken) = $this->loginViaWebService($patron);
		if (!$userValid){
			return $checkedOutTitles;
		}

		//Now that we have the session token, get checkouts information
		$lookupMyAccountInfoResponse = $this->getWebServiceResponse($this->getWebServiceURL() . '/standard/lookupMyAccountInfo?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&includeItemsOutInfo=true');
		if (isset($lookupMyAccountInfoResponse->ItemsOutInfo)){
			$sCount = 0;
			foreach ($lookupMyAccountInfoResponse->ItemsOutInfo as $itemOut){
				$sCount++;
				$bibId = (string)$itemOut->titleKey;
				$curTitle['checkoutSource']  = 'ils';
				$curTitle['recordId']        = $bibId;
				$curTitle['shortId']         = $bibId;
				$curTitle['id']              = $bibId;
				$curTitle['title']           = (string)$itemOut->title;
				$curTitle['author']          = (string)$itemOut->author;
				$curTitle['dueDate']         = strtotime((string)$itemOut->dueDate);
				$curTitle['checkoutdate']    = (string)$itemOut->ckoDate;
				$curTitle['renewCount']      = (string)$itemOut->renewals;
				$curTitle['canrenew']        = true; //TODO: Figure out if the user can renew the title or not
				$curTitle['renewIndicator']  = (string)$itemOut->itemBarcode;
				$curTitle['barcode']         = (string)$itemOut->itemBarcode;
				$curTitle['holdQueueLength'] = $this->getNumHoldsOnRecord($bibId);
				$curTitle['format']          = 'Unknown';
				if (!empty($curTitle['id'])){
					require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
					$recordDriver = new MarcRecord($curTitle['id']);
					if ($recordDriver->isValid()){
						$curTitle['coverUrl']      = $recordDriver->getBookcoverUrl('medium');
						$curTitle['groupedWorkId'] = $recordDriver->getGroupedWorkId();
						$curTitle['ratingData']    = $recordDriver->getRatingData();
						$curTitle['format']        = $recordDriver->getPrimaryFormat();
						$curTitle['author']        = $recordDriver->getPrimaryAuthor();
						$curTitle['title']         = $recordDriver->getTitle();
						$curTitle['title_sort']    = $recordDriver->getSortableTitle();
						$curTitle['link']          = $recordDriver->getLinkUrl();
					}else{
						$curTitle['coverUrl'] = "";
					}
				}
				//TODO: Sort Keys Created in CheckedOut.php. Needed here?
				$sortTitle = isset($curTitle['title_sort']) ? $curTitle['title_sort'] : $curTitle['title'];
				$sortKey   = $sortTitle;
				if ($sortOption == 'title'){
					$sortKey = $sortTitle;
				}elseif ($sortOption == 'author'){
					$sortKey = (isset($curTitle['author']) ? $curTitle['author'] : "Unknown") . '-' . $sortTitle;
				}elseif ($sortOption == 'dueDate'){
					if (isset($curTitle['dueDate'])){
						if (preg_match('/.*?(\\d{1,2})[-\/](\\d{1,2})[-\/](\\d{2,4}).*/', $curTitle['dueDate'], $matches)) {
							$sortKey = $matches[3] . '-' . $matches[1] . '-' . $matches[2] . '-' . $sortTitle;
						} else {
							$sortKey = $curTitle['dueDate'] . '-' . $sortTitle;
						}
					}
				}elseif ($sortOption == 'format'){
					$sortKey = (isset($curTitle['format']) ? $curTitle['format'] : "Unknown") . '-' . $sortTitle;
				}elseif ($sortOption == 'renewed'){
					$sortKey = (isset($curTitle['renewCount']) ? $curTitle['renewCount'] : 0) . '-' . $sortTitle;
				}elseif ($sortOption == 'holdQueueLength'){
					$sortKey = (isset($curTitle['holdQueueLength']) ? $curTitle['holdQueueLength'] : 0) . '-' . $sortTitle;
				}
				$sortKey .= "_$sCount";
				$checkedOutTitles[$sortKey] = $curTitle;
			}
		}

		return $checkedOutTitles;
	}

	public function hasFastRenewAll(){
		return false;
	}

	public function renewAll($patron){
		return array(
			'success' => false,
			'message' => 'Renew All not supported directly, call through Catalog Connection',
		);
	}

	// TODO: Test with linked accounts (9-3-2015)
	public function renewItem($patron, $recordId, $itemId, $itemIndex){
		global $configArray;

		//Get the session token for the user
		//Log the user in
		list($userValid, $sessionToken) = $this->loginViaWebService($patron);
		if (!$userValid){
			return array(
				'success' => false,
				'message' => 'Sorry, it does not look like you are logged in currently.  Please log in and try again');
		}


		//create the hold using the web service
		$renewItemUrl      = $this->getWebServiceURL() . '/standard/renewMyCheckout?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&itemID=' . $itemId;
		$renewItemResponse = $this->getWebServiceResponse($renewItemUrl);

		if ($renewItemResponse && !isset($renewItemResponse->string)){
			$success = true;
			$message = 'Your item was successfully renewed.  The title is now due on ' . $renewItemResponse->dueDate;
			//Clear the patron profile

		}else{
			//TODO: check that title is included in the message
			$success = false;
			$message = $renewItemResponse->string;

		}
		return array(
			'itemId'  => $itemId,
			'success' => $success,
			'message' => $message
		);
	}

	/**
	 * Return the number of holds that are on a record
	 * @param  string|int $bibId
	 * @return bool|int
	 */
	public function getNumHoldsOnRecord($bibId) {
		global $offlineMode;
		if (!$offlineMode){
			global $configArray;
			$lookupTitleInfoUrl      = $this->getWebServiceURL() . '/standard/lookupTitleInfo?clientID=' . $configArray['Catalog']['clientId'] . '&titleKey=' . $bibId . '&includeItemInfo=false&includeHoldCount=true' ;
			$lookupTitleInfoResponse = $this->getWebServiceResponse($lookupTitleInfoUrl);
			if (!empty($lookupTitleInfoResponse->titleInfo)){
				return is_array($lookupTitleInfoResponse->titleInfo) ? (int)$lookupTitleInfoResponse->titleInfo[0]->holdCount : (int)$lookupTitleInfoResponse->titleInfo->holdCount;
			}
		}
		return false;
	}

	function resetPin($user, $newPin, $resetToken = null) {

	}

	/**
	 * @param User   $patron         The user to update PIN for
	 * @param string $oldPin         The current PIN
	 * @param string $newPin         The PIN to update to
	 * @param string $confirmNewPin  A second entry to confirm the new PIN number (checked in User now)
	 * @return string
	 */
	function updatePin($patron, $oldPin, $newPin, $confirmNewPin){
		global $configArray;

		//Get the session token for the user
		//Log the user in
		list($userValid, $sessionToken) = $this->loginViaWebService($patron);
		if (!$userValid){
			return 'Sorry, it does not look like you are logged in currently.  Please log in and try again';
		}

		//create the hold using the web service
		$updatePinUrl      = $this->getWebServiceURL() . '/standard/changeMyPin?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&currentPin=' . $oldPin . '&newPin=' . $newPin;
		$updatePinResponse = $this->getWebServiceResponse($updatePinUrl);

		if ($updatePinResponse){
			$patron->cat_password = $newPin;
			$patron->update();
			return "Your pin number was updated successfully.";
		}else{
			return "Sorry, we could not update your pin number. Please try again later.";
		}
	}

	public function emailPin($barcode){
		global $configArray;
		if (empty($barcode)) {
			$barcode = $_REQUEST['barcode'];
		}

		//email the pin to the user
		$updatePinUrl      = $this->getWebServiceURL() . '/standard/emailMyPin?clientID=' . $configArray['Catalog']['clientId'] . '&secret=' . $configArray['Catalog']['clientSecret'] . '&login=' . $barcode . '&profile=' . $this->hipProfile;
		$updatePinResponse = $this->getWebServiceResponse($updatePinUrl);
		//$updatePinResponse is an XML object, at least when there is an error with the API call
		// otherwise, it is true for the pin sent, or false for pin not sent.

		if ($updatePinResponse && !isset($updatePinResponse->code)){
			return array(
				'success' => true,
			);
		}else{
			$result = array(
				'error' => "Sorry, we could not e-mail your pin to you.  Please visit the library to reset your pin."
			);
			if (isset($updatePinResponse->code)){
				$result['error'] .= '  ' . $updatePinResponse->string;
			}
			return $result;
		}
	}

	public function getSelfRegistrationFields() {
		global $configArray;
		$lookupSelfRegistrationFieldsUrl      = $this->getWebServiceURL() . '/standard/lookupSelfRegistrationFields?clientID=' . $configArray['Catalog']['clientId'];
		$lookupSelfRegistrationFieldsResponse = $this->getWebServiceResponse($lookupSelfRegistrationFieldsUrl);
		$fields = array();
		if ($lookupSelfRegistrationFieldsResponse){
			foreach($lookupSelfRegistrationFieldsResponse->registrationField as $registrationField){
				$newField = array(
					'property'  => (string)$registrationField->column,
					'label'     => (string)$registrationField->label,
					'maxLength' => (int)$registrationField->length,
					'type'      => 'text',
					'required'  => (string)$registrationField->required == 'true',
				);
				if ($newField['property'] == 'pin#'){
					$newField['property'] = 'pin';
					$newField['type']     = 'pin';
				}elseif ($newField['property'] == 'confirmpin#'){
					$newField['property'] = 'pin1';
					$newField['type']     = 'pin';
				}
				if ((string)$registrationField->masked == 'true'){
					$newField['type'] = 'password';
				}
				if (isset($registrationField->values)){
					$newField['type'] = 'enum';
					$values = array();
					foreach($registrationField->values->value as $value){
						$values[(string)$value->code] = (string)$value->description;
					}
					$newField['values'] = $values;
				}
				$fields[] = $newField;
			}
		}
		return $fields;
	}

	//This function does not currently work due to posting of the self registration data.  Using HIP for now in individual drivers.
	/*function selfRegister(){
		global $configArray;
		$fields = $this->getSelfRegistrationFields();

		$createSelfRegisteredPatronUrl = $this->getWebServiceURL() . '/standard/createSelfRegisteredPatron?clientID=' . $configArray['Catalog']['clientId'] . '&secret=' . $configArray['Catalog']['clientSecret'];
		foreach ($fields as $field){
			if (isset($_REQUEST[$field['property']])){
				$createSelfRegisteredPatronUrl .= '&' . $field['property'] . '=' . urlencode($_REQUEST[$field['property']]);
			}
		}
		$createSelfRegisteredPatronResponse = $this->getWebServiceResponse($createSelfRegisteredPatronUrl);
		if ($createSelfRegisteredPatronResponse){
			return array('success' => true, 'barcode' => (string)$createSelfRegisteredPatronResponse);
		}else{
			return array('success' => false, 'barcode' => '');
		}
	}*/

	/**
	 * Split a name into firstName, lastName, middleName.
	 *a
	 * Assumes the name is entered as LastName, FirstName MiddleName
	 * @param $fullName
	 * @return array
	 */
	public function splitFullName($fullName) {
		$fullName   = str_replace(",", " ", $fullName);
		$fullName   = str_replace(";", " ", $fullName);
		$fullName   = str_replace(";", "'", $fullName);
		$fullName   = preg_replace("/\\s{2,}/", " ", $fullName);
		$nameParts  = explode(' ', $fullName);
		$lastName   = strtolower($nameParts[0]);
		$middleName = isset($nameParts[2]) ? strtolower($nameParts[2]) : '';
		$firstName  = isset($nameParts[1]) ? strtolower($nameParts[1]) : $middleName;
		return array($fullName, $lastName, $firstName);
	}

	public function getWebServiceResponse($url){
		$xml = $this->_curlGetPage($url);
		if ($xml !== false && $xml !== 'false'){
			if (strpos($xml, '<') !== FALSE){
				//Strip any non-UTF-8 characters
				$xml = preg_replace('/[^(\x20-\x7F)]*/','', $xml);

				libxml_use_internal_errors(true);
				$parsedXml = simplexml_load_string($xml);
				if ($parsedXml === false){
					//Failed to load xml
					global $logger;
					$logger->log("Error parsing xml", PEAR_LOG_ERR);
					$logger->log($xml, PEAR_LOG_DEBUG);
					foreach(libxml_get_errors() as $error) {
						$logger->log("\t {$error->message}", PEAR_LOG_ERR);
					}
					return false;
				}else{
					return $parsedXml;
				}
			}else{
				return $xml;
			}
		}else{
			global $logger;
			$logger->log('Curl problem in getWebServiceResponse', PEAR_LOG_WARNING);
			return false;
		}
	}

	public function hasNativeReadingHistory() {
		return false;
	}

}
