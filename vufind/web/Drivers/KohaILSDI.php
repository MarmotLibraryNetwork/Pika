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
use \Pika\Logger;
abstract class KohaILSDI extends ScreenScrapingDriver {
	/**
	 * @var $dbConnection null
	 */
	protected $dbConnection = null;
	private $logger;
	private $ilsdiscript = '/ilsdi.pl';

	private $webServiceURL = null;

	public function __construct($accountProfile){
		parent::__construct($accountProfile);
	}

	public function getWebServiceURL(){
		if (empty($this->webServiceURL)){
			$webServiceURL = null;
			if (!empty($this->accountProfile->patronApiUrl)){
				$webServiceURL = trim($this->accountProfile->patronApiUrl);
			}elseif (!empty($configArray['Catalog']['webServiceUrl'])){
				$webServiceURL = $configArray['Catalog']['webServiceUrl'];
			}else{

				$this->logger->critical('No Web Service URL defined in Horizon ROA API Driver');
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

					$this->logger->error("Error parsing xml");
					$this->logger->debug($xml);
					foreach (libxml_get_errors() as $error){
						$this->logger->error("\t {$error->message}");
					}
					return false;
				}else{
					return $parsedXml;
				}
			}else{
				return $xml;
			}
		}else{

			$this->logger->warning('Curl problem in getWebServiceResponse');
			return false;
		}
	}


	function initDatabaseConnection(){
		global $configArray;
		if ($this->dbConnection == null){
			$this->dbConnection = mysqli_connect($configArray['Catalog']['db_host'], $configArray['Catalog']['db_user'], $configArray['Catalog']['db_pwd'], $configArray['Catalog']['db_name'], $configArray['Catalog']['db_port']);

			if (!$this->dbConnection || mysqli_errno($this->dbConnection) != 0){

				$this->logger->error("Error connecting to Koha database " . mysqli_error($this->dbConnection));
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
		$holdResult = array(
			'success' => false,
			'message' => 'Your hold could not be placed. '
		);

		$patronKohaId = $this->getKohaPatronId($patron);

		if (empty($pickupBranch)){
			$pickupBranch = strtoupper($patron->homeLocationCode);
		} else {
			$pickupBranch = strtoupper($pickupBranch);
		}

		$urlParameters = [
			'service' => 'HoldTitle',
			'patron_id' => $patronKohaId,
			'bib_id' => $recordId,
			'pickup_location' => $pickupBranch,
			'request_location' => $_SERVER['REMOTE_ADDR']
		];

		if (!empty($cancelIfNotFilledByDate)){
			$urlParameters['needed_before_date'] = $cancelIfNotFilledByDate;
		}

		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$title = null;
		$errorMessage = "Sorry, none of these items can be placed on hold. Either the items are not currently available for loan or the library that owns the materials does not allow them to be loaned outside of that library or service area. Please contact your library with any questions.";
		$holdResponse = $this->getWebServiceResponse($webServiceURL);
		if (!empty($holdResponse)){
			if (!empty($holdResponse->title)){
				$holdResult = array(
					'title'   => $holdResponse->title,
					'bib'     => $recordId,
					'success' => true,
					'message' => "Your hold has been placed."
				);
			}elseif(isset($holdResponse->message)){
				$holdResult['message'] = $errorMessage;
			}elseif(isset($holdResponse->code)){
				$holdResult['message'] = $errorMessage;
			}
		}

		return $holdResult;
	}

	function placeItemHold($patron, $recordId, $itemId, $pickupBranch = null, $cancelIfNotFilledByDate = null){

		return $this->placeHold($patron, $recordId, $pickupBranch, $cancelIfNotFilledByDate);

	}

	/**
	 * Cancels a hold for a patron
	 *
	 * @param   User    $patron     The User to cancel the hold for
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $cancelId   Information about the hold to be cancelled
	 * @return  array
	 */
	public function cancelHold($patron, $recordId, $cancelId) {
		$cancelResults = [
			'itemId' => 0,
			'success' => false,
			'message' => 'Failed to cancel hold.'
		];

		$patronKohaId = $this->getKohaPatronId($patron);

		$urlParameters = array(
			'service'          => 'CancelHold',
			'patron_id'        => $patronKohaId,
			'item_id'          => $cancelId,
		);

		//create the hold using the web service call
		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$cancelResponse = $this->getWebServiceResponse($webServiceURL);

		if ($cancelResponse) {
			if($cancelResponse->code && $cancelResponse->code == "Canceled") {
				// success
				$cancelResults['success'] = true;
				$cancelResults['message'] = 'Hold canceled.';
			} elseif($cancelResponse->message) {
				// fail
				if($cancelResponse->message) {
					$cancelResults['message'] .=  ' Reason '.$cancelResponse->code;
				}
			}
		}

		return $cancelResults;
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

		$renewResults = [
			'itemId' => 0,
			'success' => false,
			'message' => 'Failed to renew item.'
		];

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


	protected static $kohaPatronIdsForUsers = array();
	protected function getKohaPatronId(User $patron){
		// first check the $kohaPatronIdsForUsers array for koha id
		if (array_key_exists($patron->id, self::$kohaPatronIdsForUsers)) {
			// found it in the array
			return self::$kohaPatronIdsForUsers;
		}

		// next check memcache for the koha id
		global $memCache;
		$memcacheKey  = 'kohaPatronId_' . $patron->id;
		$kohaPatronId = $memCache->get($memcacheKey);

		if ($kohaPatronId && !isset($_REQUEST['reload'])) {
			// found the koha id and we don't need to reload
			return $kohaPatronId;
		}

		// not in memcache either. grab it from the database
		if( !$this->initDatabaseConnection()) {
			// oops. can't connect to database.
			return false;
		}
		// ok. lets proceed.
		$sql     = 'SELECT borrowernumber FROM borrowers WHERE cardnumber = "' . $patron->getBarcode() . '"';
		$results = mysqli_query($this->dbConnection, $sql);
		if($results) {
			// got a response. let's proceed.
			$row = $results->fetch_assoc();
			if(empty($row['borrowernumber'])) {
				// hmmm. what's up with this?
				return false;
			}
			// ok, we have something to work with.
			$kohaPatronId = $row['borrowernumber'];
		} else {
			// oops. something went wrong
			return false;
		}
		// load it into the $kohaPatronIdsForUsers array
		self::$kohaPatronIdsForUsers[$patron->id] = $kohaPatronId;
		// cool. let's load up memcache. we still have $memcacheKey and global $memCache from above. no need to do work twice.
		global $configArray;
		$memCache->set($memcacheKey, $kohaPatronId, 0, $configArray['Caching']['koha_patron_id']);
		// ok, memcache set. lets give back the koha id
		return $kohaPatronId;
		// and ... done!
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
				$patron = $this->getPatronInformation($kohaUserID, $password);
				break;
			}
		}
		if (!$patron && PEAR_Singleton::isError($kohaUserID)){
			return $kohaUserID;
		}
		return $patron;
	}

	private function authenticatePatron($barcode, $password){
		$urlParameters = array(
			'service' => 'AuthenticatePatron',
			'username' => $barcode,
			'password' => $password,
		);

		$webServiceURL        = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL        .= '?' . http_build_query($urlParameters);
		$authenticateResponse = $this->getWebServiceResponse($webServiceURL);
		if (!empty($authenticateResponse)){
			if (!empty($authenticateResponse->id)){
				return (integer)$authenticateResponse->id;
			}else{
				//Standard access denied response is $authenticateResponse->code == "PatronNotFound"
				if ($authenticateResponse->code != "PatronNotFound"){
					global $pikaLogger;
					$pikaLogger->debug('Unexpected authentication denied code : ' . $authenticateResponse->code);
					$pikaLogger->debug('Unexpected authentication denied code : ' . $authenticateResponse->message);
				}
				return new PEAR_Error('authentication_error_denied');
			}
		}
		return new PEAR_Error('authentication_error_technical');
	}

	private function getPatronInformation($kohaUserId, $password = ''){
		$patron = false;

		$urlParameters = [
			'service'         => 'GetPatronInfo',
			'patron_id'       => $kohaUserId,
			'show_contact'    => 1,
//			'show_fines'      => 1,
//			'show_holds'      => 1,
			'show_attributes' => 1,
		];

		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$patronInfoResponse = $this->getWebServiceResponse($webServiceURL);
		if (!empty($patronInfoResponse->cardnumber)){
			$userExistsInDB  = false;
			$patron          = new User();
			$patron->source  = $this->accountProfile->name;
			$patron->barcode = (string)$patronInfoResponse->cardnumber;
			if ($patron->find(true)){
				$userExistsInDB = true;
			}

			$forceDisplayNameUpdate = false;
			$firstName              = (string)$patronInfoResponse->firstname;
			if ($patron->firstname != $firstName){
				$patron->firstname      = $firstName;
				$forceDisplayNameUpdate = true;
			}
			$lastName = (string)$patronInfoResponse->surname;
			if ($patron->lastname != $lastName){
				$patron->lastname       = isset($lastName) ? $lastName : '';
				$forceDisplayNameUpdate = true;
			}
			if ($forceDisplayNameUpdate){
				$patron->displayName = '';
			}
			$patron->fullname  = $firstName . ' ' . $lastName;
			$patron->ilsUserId = $kohaUserId;
			$patron->barcode   = (string)$patronInfoResponse->cardnumber;
			if (!empty($password)) {
				$patron->setPassword($password);
			}
			$patron->email      = (string)$patronInfoResponse->email;
			$patron->patronType = (string)$patronInfoResponse->categorycode;
			$patron->webNote    = (string)$patronInfoResponse->opacnote; //TODO: double check that the point of the opac note is the same as our webnote
			$patron->address1   = (string)$patronInfoResponse->address;
			$patron->city       = (string)$patronInfoResponse->city;
			$patron->state      = (string)$patronInfoResponse->state;
			$patron->zip        = (string)$patronInfoResponse->zipcode;

			$outstandingFines = (string)$patronInfoResponse->charges;
			$patron->fines    = sprintf('$%0.2f', $outstandingFines);
			$patron->finesVal = floatval($outstandingFines);

			// Get Num Holds
			$patron->numHoldsAvailableIls = $this->getNumOfAvailableHoldsFromDB($kohaUserId);
			$patron->numHoldsRequestedIls = $this->getNumOfUnAvailableHoldsFromDB($kohaUserId);
			$patron->numHoldsIls          = $patron->numHoldsAvailableIls + $patron->numHoldsRequestedIls;

			// Get Num Checkouts
			$patron->numCheckedOutIls = $this->getNumOfCheckoutsFromDB($kohaUserId);


			$patron->setUserHomeLocations((string)$patronInfoResponse->branchcode);

			$dateString     = (string)$patronInfoResponse->dateexpiry;
			if (!empty($patron->expires)){
				[$yearExp, $monthExp, $dayExp] = explode('-', $patron->expires);
				$dateString = $monthExp . '/' . $dayExp . '/' . $yearExp;
			}
			$patron->setUserExpirationSettings($dateString);

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
