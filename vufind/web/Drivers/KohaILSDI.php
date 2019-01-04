<?php
/**
 *   Implementa Patron Interactions that would go through Koha's ILS-DI (ILS Discovery Interface) interface
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 12/18/2018
 *
 */

//require_once ROOT_DIR . '/Drivers/SIP2Driver.php';
////  I can imagine a version that fully implements the Driver Interface with out using the SIP2Driver. Pascal 12/18/2018
//abstract class KohaILSDI extends SIP2Driver {

abstract class KohaILSDI extends ScreenScrapingDriver {
	/**
	 * @var $dbConnection null
	 */
	protected $dbConnection = null;

	private $ilsdiscript = '/ilsdi.pl';

	private $webServiceURL = null;

	public function getWebServiceURL(){
		if (empty($this->webServiceURL)){
			$webServiceURL = null;
			if (!empty($this->accountProfile->patronApiUrl)){
				$webServiceURL = trim($this->accountProfile->patronApiUrl);
			}elseif (!empty($configArray['Catalog']['webServiceUrl'])){
				$webServiceURL = $configArray['Catalog']['webServiceUrl'];
			}else{
				global $logger;
				$logger->log('No Web Service URL defined in Horizon ROA API Driver', PEAR_LOG_CRIT);
			}
			$this->webServiceURL = rtrim($webServiceURL, '/'); // remove any trailing slash because other functions will add it.
		}
		return $this->webServiceURL;
	}

	public function getWebServiceResponse($url){
		$xml = $this->_curlGetPage($url);
		if ($xml !== false && $xml !== 'false'){
			if (strpos($xml, '<') !== false){
				//Strip any non-UTF-8 characters
				$xml = preg_replace('/[^(\x20-\x7F)]*/', '', $xml);

				libxml_use_internal_errors(true);
				$parsedXml = simplexml_load_string($xml);
				if ($parsedXml === false){
					//Failed to load xml
					global $logger;
					$logger->log("Error parsing xml", PEAR_LOG_ERR);
					$logger->log($xml, PEAR_LOG_DEBUG);
					foreach (libxml_get_errors() as $error){
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


	function initDatabaseConnection(){
		global $configArray;
		if ($this->dbConnection == null){
			$this->dbConnection = mysqli_connect($configArray['Catalog']['db_host'], $configArray['Catalog']['db_user'], $configArray['Catalog']['db_pwd'], $configArray['Catalog']['db_name'], $configArray['Catalog']['db_port']);

			if (!$this->dbConnection || mysqli_errno($this->dbConnection) != 0){
				global $logger;
				$logger->log("Error connecting to Koha database " . mysqli_error($this->dbConnection), PEAR_LOG_ERR);
				$this->dbConnection = null;
			}
			global $timer;
			$timer->logTime("Initialized connection to Koha");
		}
		return $this->dbConnection;
	}


	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param   User $patron The User to place a hold for
	 * @param   string $recordId The id of the bib record
	 * @param   string $pickupBranch The branch where the user wants to pickup the item when available
	 * @param   null|string $cancelIfNotFilledByDate The date to cancel the Hold if it isn't filled
	 * @return  array                 An array with the following keys
	 *                                success - true/false
	 *                                message - the message to display (if item holds are required, this is a form to select the item).
	 *                                needsItemLevelHold - An indicator that item level holds are required
	 *                                title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public function placeHold($patron, $recordId, $pickupBranch, $cancelIfNotFilledByDate = null){
		$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch, $cancelIfNotFilledByDate);
		return $result;
	}

	function placeItemHold($patron, $recordId, $itemId, $pickupBranch = null, $cancelIfNotFilledByDate = null){
		$holdResult = array(
			'success' => false,
			'message' => 'Your hold could not be placed. '
		);

		$patronKohaId = $this->getKohaPatronId($patron);
		if (empty($pickupBranch)){
			$pickupBranch = strtoupper($patron->homeLocationCode);
		}

		$urlParameters = array(
			'service' => empty($itemId) ? 'HoldTitle' : 'HoldItem',
			'patron_id' => $patronKohaId,
			'bib_id' => $recordId,
			'pickup_location' => $pickupBranch,
		);
		if (!empty($itemId)){
			$urlParameters['item_id'] = $itemId;
		}else{
			// Hold Title request requires the user's end IP address
			$urlParameters['request_location'] = $_SERVER['REMOTE_ADDR']; //TODO: End user's IP. (yike's! Koha wants this?)
		}
		if (!empty($cancelIfNotFilledByDate)){
			$urlParameters['needed_before_date'] = $cancelIfNotFilledByDate;//TODO determine date format needed
		}
		//create the hold using the web service call
		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$success      = false;
		$title        = null;
		$holdResponse = $this->getWebServiceResponse($webServiceURL);
		if (!empty($holdResponse)){
			if (empty($holdResponse->message) && empty($holdResponse->code)){

			}else{
				//TODO: error message
				$message = 'Failed to place the hold';
				if (isset($holdResponse->message)){
					$message .= ' : ' . $holdResponse->message;
				}else{
					$message .= '. Error Code : ' . $holdResponse->code;
				}
			}
			$holdResult = array(
				'title' => $title,
				'bib' => $recordId,
				'success' => $success,
				'message' => $message
			);
		}

		return $holdResult;
	}

	/**
	 * Cancel hold a single title
	 *
	 * @param $patron     User
	 * @param $recordId   string
	 * @param  $cancelId  string
	 * @param $itemIndex  string
	 * @return $cancelHoldResults array
	 */
	public function cancelHold($patron, $recordId, $cancelId){
		$cancelHoldResults = [
			'itemId' => 0,
			'success' => false,
			'message' => 'Failed to renew item.'
		];
		$patronKohaId = $this->getKohaPatronId($patron);

		$urlParameters = [
			'service'          => 'CancelHold',
			'patron_id'        => $patronKohaId,
			'item_id'          => $recordId
		];

		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$cancelHoldResponse = $this->getWebServiceResponse($webServiceURL);

		if ($cancelHoldResponse) {
			if($cancelHoldResponse->message ==  'Canceled') {
				// success
				$cancelHoldResults['success'] = true;
				$cancelHoldResults['itemId']  = $recordId;
				$cancelHoldResults['message'] = 'Hold successfully canceled.';
			} else {
				// fail
					$cancelHoldResultsResults['message'] = 'Unable able to cancel hold. Reason '.$cancelHoldResponse->code;
			}
		}

		return $cancelHoldResultsResults;
	}

	/**
	 * Renew a single title currently checked out to the user
	 *
	 * @param $patron     User
	 * @param $recordId   string
	 * @param $itemId     string
	 * @param $itemIndex  string
	 * @return $holdResults array
	 */
	public function renewItem($patron, $recordId, $itemId, $itemIndex){

		$renewResults = array(
			'itemId' => 0,
			'success' => false,
			'message' => 'Failed to renew item.'
		);

		$patronKohaId = $this->getKohaPatronId($patron);

		$urlParameters = array(
			'service'          => 'RenewLoan',
			'patron_id'        => $patronKohaId,
			'item_id'          => $itemId,
		);

		//create the hold using the web service call
		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$renewResponse = $this->getWebServiceResponse($webServiceURL);

		if ($renewResponse) {
			if($renewResponse->success ==  '1') {
				// success
				$renewResults['success'] = true;
				$renewResults['itemId']  = $itemId;
				$renewResults['message'] = 'Items successfully renewed.';
			} elseif($renewResponse->success == '0') {
				// fail
				if($renewResponse->error) {
					$renewResults['message'] = 'Unable able to renew item. Reason '.$renewResponse->error;
				}
			}
		}

		return $renewResults;

	}


	private function getKohaPatronId(User $patron){
		//TODO: memcache KohaPatronIds
		if ($this->initDatabaseConnection()){
			$sql     = 'SELECT borrowernumber FROM borrowers WHERE cardnumber = "' . $patron->getBarcode() . '"';
			$results = mysqli_query($this->dbConnection, $sql);
			if ($results){
				$row          = $results->fetch_assoc();
				$kohaPatronId = $row['borrowernumber'];
				if (!empty($kohaPatronId)){
					return $kohaPatronId;
				}
			}
		}
		return false;
	}

	public function patronLogin($username, $password, $validatedViaSSO){
		$username = trim($username);
		$password = trim($password);

		$barcodesToTest   = array();
		$barcodesToTest[] = $username;
		//Special processing to allow users to login with short barcodes
		global $library;
		if ($library){
			if ($library->barcodePrefix){
				if (strpos($username, $library->barcodePrefix) !== 0){
					//Add the barcode prefix to the barcode
					$barcodesToTest[] = $library->barcodePrefix . $username;
				}
			}
		}

		$patron = false;
		foreach ($barcodesToTest as $i => $barcode){

			$kohaUserID = $this->authenticatePatron($barcode, $password);
			if (PEAR_Singleton::isError($kohaUserID)){
				continue;
			}elseif (is_int($kohaUserID)){
				$patron = $this->getPatronInformation($kohaUserID);
//				if ($patron){
//					// Get Num Holds
//					$patron->numHoldsAvailableIls = $this->getNumOfAvailableHoldsFromDB($kohaUserID);
//					$patron->numHoldsRequestedIls = $this->getNumOfUnAvailableHoldsFromDB($kohaUserID);
//					$patron->numHoldsIls          = $patron->numHoldsAvailableIls + $patron->numHoldsRequestedIls;
//
//					// Get Num Checkouts
//					$patron->numCheckedOutIls = $this->getNumOfCheckoutsFromDB($kohaUserID);
//				}
				return $patron;
			}
		}
		if (!$patron && PEAR_Singleton::isError($kohaUserID)){
			return $kohaUserID;
		}
	}


	private function authenticatePatron($barcode, $password){
		$urlParameters = array(
			'service' => 'AuthenticatePatron',
			'username' => $barcode,
			'password' => $password,
		);

		$webServiceURL        = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL       .= '?' . http_build_query($urlParameters);
		$authenticateResponse = $this->getWebServiceResponse($webServiceURL);
		if (!empty($authenticateResponse)){
			if (!empty($authenticateResponse->id)){
				return (integer)$authenticateResponse->id;
			}else{
				//Standard access denied response is $authenticateResponse->code == "PatronNotFound"
				if ($authenticateResponse->code != "PatronNotFound"){
					global $logger;
					$logger->log('Unexpected authentication denied code : ' . $authenticateResponse->code, LOG_DEBUG);
				}
				return new PEAR_Error('authentication_error_denied');
			}
		}
		return new PEAR_Error('authentication_error_technical');
	}

	private function getPatronInformation($kohaUserId){
		$patron = false;

		$urlParameters = array(
			'service'         => 'GetPatronInfo',
			'patron_id'       => $kohaUserId,
			'show_contact'    => 1,
//			'show_fines'      => 1,
//			'show_holds'      => 1,
			'show_attributes' => 1,
		);

		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$patronInfoRepsonse = $this->getWebServiceResponse($webServiceURL);
		if (!empty($patronInfoRepsonse->cardnumber)){
			$userExistsInDB       = false;
			$patron               = new User();
			$patron->source       = $this->accountProfile->name;
			$patron->cat_username = (string)$patronInfoRepsonse->cardnumber;
			if ($patron->find(true)){
				$userExistsInDB = true;
			}

			$forceDisplayNameUpdate = false;
			$firstName              = (string)$patronInfoRepsonse->firstname;
			if ($patron->firstname != $firstName){
				$patron->firstname      = $firstName;
				$forceDisplayNameUpdate = true;
			}
			$lastName = (string)$patronInfoRepsonse->surname;
			if ($patron->lastname != $lastName){
				$patron->lastname       = isset($lastName) ? $lastName : '';
				$forceDisplayNameUpdate = true;
			}
			if ($forceDisplayNameUpdate){
				$patron->displayName = '';
			}
			$patron->fullname     = $firstName . ' ' . $lastName;
			$patron->username     = $kohaUserId;
			$patron->cat_username = (string)$patronInfoRepsonse->cardnumber;
//			$patron->cat_password = //TODO: this will have to be set somewhere else
			$patron->email      = (string)$patronInfoRepsonse->email;
			$patron->patronType = (string)$patronInfoRepsonse->categorycode;
			$patron->web_note   = (string)$patronInfoRepsonse->opacnote; //TODO: double check that the point of the opac note is the same as our webnote
			$patron->address1   = (string)$patronInfoRepsonse->address;
			$patron->city       = (string)$patronInfoRepsonse->city;
			$patron->state      = (string)$patronInfoRepsonse->state;
			$patron->zip        = (string)$patronInfoRepsonse->zipcode;

			$outstandingFines = (string)$patronInfoRepsonse->charges;
			$patron->fines    = sprintf('$%0.2f', $outstandingFines);
			$patron->finesVal = floatval($outstandingFines);

			// Get Num Holds
			$patron->numHoldsAvailableIls = $this->getNumOfAvailableHoldsFromDB($kohaUserId);
			$patron->numHoldsRequestedIls = $this->getNumOfUnAvailableHoldsFromDB($kohaUserId);
			$patron->numHoldsIls          = $patron->numHoldsAvailableIls + $patron->numHoldsRequestedIls;

			// Get Num Checkouts
			$patron->numCheckedOutIls = $this->getNumOfCheckoutsFromDB($kohaUserId);


			$homeBranchCode = strtolower((string)$patronInfoRepsonse->branchcode);
			$location       = new Location();
			$location->code = $homeBranchCode;
			if (!$location->find(1)){
				unset($location);
				$patron->homeLocationId = 0;
				// Logging for Diagnosing PK-1846
				global $logger;
				$logger->log('Aspencat Driver: No Location found, patron\'s homeLocationId being set to 0. User : ' . $patron->id, PEAR_LOG_WARNING);
			}

			if ((empty($patron->homeLocationId) || $patron->homeLocationId == -1) || (isset($location) && $patron->homeLocationId != $location->locationId)){ // When homeLocation isn't set or has changed
				if ((empty($patron->homeLocationId) || $patron->homeLocationId == -1) && !isset($location)){
					// homeBranch Code not found in location table and the patron doesn't have an assigned home location,
					// try to find the main branch to assign to patron
					// or the first location for the library
					global $library;

					$location            = new Location();
					$location->libraryId = $library->libraryId;
					$location->orderBy('isMainBranch desc'); // gets the main branch first or the first location
					if (!$location->find(true)){
						// Seriously no locations even?
						global $logger;
						$logger->log('Failed to find any location to assign to patron as home location', PEAR_LOG_ERR);
						unset($location);
					}
				}
				if (isset($location)){
					$patron->homeLocationId = $location->locationId;
					if (empty($patron->myLocation1Id)){
						$patron->myLocation1Id = ($location->nearbyLocation1 > 0) ? $location->nearbyLocation1 : $location->locationId;
						/** @var /Location $location */
						//Get display name for preferred location 1
						$myLocation1             = new Location();
						$myLocation1->locationId = $patron->myLocation1Id;
						if ($myLocation1->find(true)){
							$patron->myLocation1 = $myLocation1->displayName;
						}
					}

					if (empty($patron->myLocation2Id)){
						$patron->myLocation2Id = ($location->nearbyLocation2 > 0) ? $location->nearbyLocation2 : $location->locationId;
						//Get display name for preferred location 2
						$myLocation2             = new Location();
						$myLocation2->locationId = $patron->myLocation2Id;
						if ($myLocation2->find(true)){
							$patron->myLocation2 = $myLocation2->displayName;
						}
					}
				}
			}

			if (isset($location)){
				//Get display names that aren't stored
				$patron->homeLocationCode = $location->code;
				$patron->homeLocation     = $location->displayName;
			}

			$patron->expired     = 0; // default setting
			$patron->expireClose = 0;
			$patron->expires     = (string)$patronInfoRepsonse->dateexpiry;
			if (!empty($patron->expires)){
				list ($yearExp, $monthExp, $dayExp) = explode('-', $patron->expires);
				$timeExpire   = strtotime($monthExp . "/" . $dayExp . "/" . $yearExp);
				$timeNow      = time();
				$timeToExpire = $timeExpire - $timeNow;
				if ($timeToExpire <= 30 * 24 * 60 * 60){
					if ($timeToExpire <= 0){
						$patron->expired = 1;
					}
					$patron->expireClose = 1;
				}
			}
			if ($userExistsInDB){
				$patron->update();
			}else{
				$patron->created = date('Y-m-d');
				$patron->insert();
			}
		}
		return $patron;
	}

	private function getNumOfCheckoutsFromDB($kohaUserID){
		$numCheckouts      = 0;
		if ($this->initDatabaseConnection()){
			$checkedOutItemsRS = mysqli_query($this->dbConnection, 'SELECT count(*) as numCheckouts FROM issues WHERE borrowernumber = ' . $kohaUserID, MYSQLI_USE_RESULT);
			if ($checkedOutItemsRS){
				$checkedOutItems = $checkedOutItemsRS->fetch_assoc();
				$numCheckouts    = $checkedOutItems['numCheckouts'];
				$checkedOutItemsRS->close();
			}
		}
		return $numCheckouts;
	}

	private function getNumOfAvailableHoldsFromDB($kohaUserID){
		$numAvailableHolds = 0;
		if ($this->initDatabaseConnection()){
			$availableHoldsRS = mysqli_query($this->dbConnection, 'SELECT count(*) as numHolds FROM reserves WHERE found = "W" and borrowernumber = ' . $kohaUserID, MYSQLI_USE_RESULT);
			if ($availableHoldsRS){
				$availableHolds    = $availableHoldsRS->fetch_assoc();
				$numAvailableHolds = $availableHolds['numHolds'];
				$availableHoldsRS->close();
			}
		}
		return $numAvailableHolds;
	}

	private function getNumOfUnAvailableHoldsFromDB($kohaUserID){
		$numWaitingHolds = 0;
		if ($this->initDatabaseConnection()){
			$waitingHoldsRS = mysqli_query($this->dbConnection, 'SELECT count(*) as numHolds FROM reserves WHERE (found <> "W" or found is null) and borrowernumber = ' . $kohaUserID, MYSQLI_USE_RESULT);
			if ($waitingHoldsRS){
				$waitingHolds    = $waitingHoldsRS->fetch_assoc();
				$numWaitingHolds = $waitingHolds['numHolds'];
				$waitingHoldsRS->close();
			}
		}
		return $numWaitingHolds;
	}
}