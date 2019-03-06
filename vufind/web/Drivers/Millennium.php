<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */
require_once ROOT_DIR . '/sys/Proxy_Request.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LoanRule.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LoanRuleDeterminer.php';
require_once ROOT_DIR . '/Drivers/ScreenScrapingDriver.php';

/**
 * Pika Connector for Marmot's Innovative catalog (millennium)
 *
 * This class uses screen scraping techniques to gather record holdings written
 * by Adam Bryn of the Tri-College consortium.
 *
 * @author Adam Brin <abrin@brynmawr.com>
 *
 * Extended by Mark Noble and CJ O'Hara based on specific requirements for
 * Marmot Library Network.
 *
 * @author Mark Noble <mnoble@turningleaftech.com>
 * @author CJ O'Hara <cj@marmot.org>
 */
class Millennium extends ScreenScrapingDriver
{
//	var $statusTranslations = null;
//	var $holdableStatiRegex = null;
//	var $availableStatiRegex = null;
//	/** @var  Solr */
//	public $db;

	/** @var LoanRule[] $loanRules  */
	var $loanRules = null;
	/** @var LoanRuleDeterminer[] $loanRuleDeterminers */
	var $loanRuleDeterminers = null;

	protected function loadLoanRules(){
		if (is_null($this->loanRules)){
			/** @var Memcache $memCache */
			global $memCache;
			global $configArray;
			global $instanceName;
			$this->loanRules = $memCache->get($instanceName . '_loan_rules');
			if (!$this->loanRules || isset($_REQUEST['reload'])){
				$this->loanRules = array();
				$loanRule = new LoanRule();
				$loanRule->find();
				while ($loanRule->fetch()){
					$this->loanRules[$loanRule->loanRuleId] = clone($loanRule);
				}
			}
			$memCache->set($instanceName . '_loan_rules', $this->loanRules, 0, $configArray['Caching']['loan_rules']);

			$this->loanRuleDeterminers = $memCache->get($instanceName . '_loan_rule_determiners');
			if (!$this->loanRuleDeterminers || isset($_REQUEST['reload'])){
				$this->loanRuleDeterminers = array();
				$loanRuleDeterminer = new LoanRuleDeterminer();
				$loanRuleDeterminer->active = 1;
				$loanRuleDeterminer->orderBy('rowNumber DESC');
				$loanRuleDeterminer->find();
				while ($loanRuleDeterminer->fetch()){
					$this->loanRuleDeterminers[$loanRuleDeterminer->rowNumber] = clone($loanRuleDeterminer);
				}
			}
			$memCache->set($instanceName . '_loan_rule_determiners', $this->loanRuleDeterminers, 0, $configArray['Caching']['loan_rules']);
		}
	}

	public function getMillenniumScope(){
		if (isset($_REQUEST['useUnscopedHoldingsSummary'])){
			return $this->getDefaultScope();
		}
		$searchLocation = Location::getSearchLocation();

		$branchScope = '';
		//Load the holding label for the branch where the user is physically.
		if (!is_null($searchLocation)){
			if ($searchLocation->useScope && $searchLocation->restrictSearchByLocation){
				$branchScope = $searchLocation->scope;
			}
		}
		$searchLibrary = Library::getSearchLibrary();
		if (strlen($branchScope)){
			return $branchScope;
		}else if (isset($searchLibrary) && $searchLibrary->useScope && $searchLibrary->restrictSearchByLibrary) {
			//TODO: these condition checks are the main difference between this function and getLibraryScope. We should document the importance of this difference here.
			// I can only guess at the importance at this time. Should evaluate it. Pascal 10-17-2018
			return $searchLibrary->scope;
		}else{
      return $this->getDefaultScope();
		}
	}

	public function getLibraryScope(){
		if (isset($_REQUEST['useUnscopedHoldingsSummary'])){
			return $this->getDefaultScope();
		}

		//Load the holding label for the branch where the user is physically.
		$searchLocation = Location::getSearchLocation();
		if (!empty($searchLocation->scope)){
			return $searchLocation->scope;
		}

		$searchLibrary  = Library::getSearchLibrary();
		if (!empty($searchLibrary->scope)) {
			return $searchLibrary->scope;
		}
		return $this->getDefaultScope();
	}

	public function getDefaultScope(){
		global $configArray;
		return isset($configArray['OPAC']['defaultScope']) ? $configArray['OPAC']['defaultScope'] : '93';
	}

	public function getMillenniumRecordInfo($id){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCache.php';
		$scope = $this->getMillenniumScope();
		//Load the pages for holdings, order information, and items
		$millenniumCache           = new MillenniumCache();
		$millenniumCache->recordId = $id;
		$millenniumCache->scope    = $scope;

		//If we get an identifier type, strip that
		if (strpos($id, ':') > 0){
			$id = substr($id, strpos($id, ':') + 1);
		}
		// Strip ID
		$id_ = substr(str_replace('.b', '', $id), 0, -1);

		$host = $this->getVendorOpacUrl();
		$url =  $host . "/search~S{$scope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/holdings~" . $id_;
		$millenniumCache->holdingsInfo = $this->_curlGetPage($url);
		//$logger->log("Loaded holdings from url $url", PEAR_LOG_DEBUG);
		global $timer;
		$timer->logTime('got holdings from millennium');

		$url =  $host . "/search~S{$scope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/frameset~" . $id_;
		$millenniumCache->framesetInfo = $this->_curlGetPage($url);
		$timer->logTime('got frameset info from millennium');

		$millenniumCache->cacheDate = time();

		return $millenniumCache;

	}

	static $libraryLocationInformationLoaded = false;
	static $libraryLocations = null;
	static $libraryLocationLabels = null;
	static $homeLocationCode = null;
	static $homeLocationLabel = null;
	static $scopingLocationCode = null;

	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 * Interface defined in CatalogConnection.php
	 *
	 * @param   string  $username         The patron username
	 * @param   string  $password         The patron password
	 * @param   boolean $validatedViaSSO  True if the patron has already been validated via SSO.  If so we don't need to validation, just retrieve information
	 *
	 * @return  User|null           A string of the user's ID number
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	public function patronLogin($username, $password, $validatedViaSSO) {
		global $timer;
		global $configArray;

		//Get the barcode property
		if ($this->accountProfile->loginConfiguration == 'barcode_pin'){
			$barcode = $username;
		}else{
			$barcode = $password;
		}

		//Strip any non digit characters from the password
		//Can't do this any longer since some libraries do have characters in their barcode:
		//$password = preg_replace('/[a-or-zA-OR-Z\W]/', '', $password);
		//Remove any spaces from the barcode
		//ARL-153 remove spaces from the barcode
		$barcode = preg_replace('/[^a-zA-Z\d]/', '', trim($barcode));

		//Load the raw information about the patron
		$patronDump = $this->_getPatronDump($barcode);
		//$logger->log("Retrieved patron dump for $barcode\r\n" . print_r($patronDump, true), PEAR_LOG_DEBUG);

		//Create a variety of possible name combinations for testing purposes.
		$userValid = false;
		//Break up the patron name into first name, last name and middle name based on the
		if ($validatedViaSSO) {
			$userValid = true;
		}else{
			if ($this->accountProfile->loginConfiguration == 'barcode_pin'){
				//TODO: check if a pin is set in patron dump (should always be unless the user doesn't have a pin yet
				$userValid = $this->_doPinTest($barcode, $password);
			}else{
				if (isset($patronDump['PATRN_NAME'])){
					$patronName = $patronDump['PATRN_NAME'];
					list($fullName, $lastName, $firstName, $userValid) = $this->validatePatronName($username, $patronName);
				}
			}
		}

		if ($userValid) {
			if (!isset($patronName) || $patronName == null) {
				if (isset($patronDump['PATRN_NAME'])) {
					$patronName = $patronDump['PATRN_NAME'];
					list($fullName, $lastName, $firstName) = $this->validatePatronName($username, $patronName);
				}
			}
			$userExistsInDB = false;
			$user = new User();
			//Get the unique user id from Millennium
			$user->source   = $this->accountProfile->name;
			$user->username = $patronDump['RECORD_#'];
			if ($user->find(true)) {
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
			$user->fullname = isset($fullName) ? $fullName : '';
			if ($forceDisplayNameUpdate){
				$user->displayName = '';
			}

			if ($this->accountProfile->loginConfiguration == 'barcode_pin'){
				if (isset($patronDump['P_BARCODE'])){
					$user->cat_username = $patronDump['P_BARCODE']; //Make sure to get the barcode so if we are using usernames we can still get the barcode for use with overdrive, etc.
				}else{
					$user->cat_username = $patronDump['CARD_#']; //Make sure to get the barcode so if we are using usernames we can still get the barcode for use with overdrive, etc.
				}
				$user->cat_password = $password;
			}else{
				$user->cat_username = $patronDump['PATRN_NAME'];
				//When we get the patron dump, we may override the barcode so make sure that we update it here.
				//For self registered cards, the P_BARCODE is not set so we need to use the RECORD_# field
				if (strlen($patronDump['P_BARCODE']) > 0){
					$user->cat_password = $patronDump['P_BARCODE'];
				}else{
					$user->cat_password = $patronDump['RECORD_#'];
				}

			}

			$user->phone = isset($patronDump['TELEPHONE']) ? $patronDump['TELEPHONE'] : (isset($patronDump['HOME_PHONE']) ? $patronDump['HOME_PHONE'] : '');
			$user->email = isset($patronDump['EMAIL_ADDR']) ? $patronDump['EMAIL_ADDR'] : '';
			$user->patronType = $patronDump['P_TYPE'];
			if (isset($configArray['OPAC']['webNoteField'])){
				$user->web_note = isset($patronDump[$configArray['OPAC']['webNoteField']]) ? $patronDump[$configArray['OPAC']['webNoteField']] : '';
			}else{
				$user->web_note = isset($patronDump['WEB_NOTE']) ? $patronDump['WEB_NOTE'] : '';
			}

			//Setup home location
			$location = null;
			if (isset($patronDump['HOME_LIBR']) || isset($patronDump['HOLD_LIBR'])){
				$homeBranchCode = isset($patronDump['HOME_LIBR']) ? $patronDump['HOME_LIBR'] : $patronDump['HOLD_LIBR'];
				$homeBranchCode = str_replace('+', '', $homeBranchCode); //Translate home branch to plain text
				$location = new Location();
				$location->code = $homeBranchCode;
				if (!$location->find(true)){
					unset($location);
				}
			} else {
				global $logger;
				$logger->log('Millennium Driver: No Home Library Location or Hold location found in patron dump. User : '.$user->id, PEAR_LOG_ERR);
				// The code below will attempt to find a location for the library anyway if the homeLocation is already set
			}

			if (empty($user->homeLocationId) || (isset($location) && $user->homeLocationId != $location->locationId)) { // When homeLocation isn't set or has changed
				if (empty($user->homeLocationId) && !isset($location)) {
						// homeBranch Code not found in location table and the user doesn't have an assigned homelocation,
						// try to find the main branch to assign to user
						// or the first location for the library
						global $library;

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

			if (isset($location)){
				//Get display names that aren't stored
				$user->homeLocationCode = $location->code;
				$user->homeLocation     = $location->displayName;
			}

			$user->expired     = 0; // default setting
			$user->expireClose = 0;
			//See if expiration date is close
			if (trim($patronDump['EXP_DATE']) != '-  -'){
				$user->expires = $patronDump['EXP_DATE'];
				list ($monthExp, $dayExp, $yearExp) = explode("-",$patronDump['EXP_DATE']);
				$timeExpire = strtotime($monthExp . "/" . $dayExp . "/" . $yearExp);
				$timeNow = time();
				$timeToExpire = $timeExpire - $timeNow;
				if ($timeToExpire <= 30 * 24 * 60 * 60){
					if ($timeToExpire <= 0){
						$user->expired = 1;
					}
					$user->expireClose = 1;
				}
			}

			//Get additional information that doesn't necessarily get stored in the User Table
			if (isset($patronDump['ADDRESS'])){
				$fullAddress = $patronDump['ADDRESS'];
				$addressParts = explode('$',$fullAddress);
				if (count($addressParts) == 3) {
					// Special handling for juvenile Sacramento Patrons with an initial C/O line
					// $addressParts[0] will have the C/O line
					//TODO: If
					if (strpos($addressParts[0], 'C/O ') === 0) {
						$user->careOf = $addressParts[0];
					}
					$user->address1 = $addressParts[1];
					$user->city     = isset($addressParts[2]) ? $addressParts[2] : '';
				} else {
					$user->address1 = $addressParts[0];
					$user->city     = isset($addressParts[1]) ? $addressParts[1] : '';
					$user->state    = isset($addressParts[2]) ? $addressParts[2] : '';
					$user->zip      = isset($addressParts[3]) ? $addressParts[3] : '';
				}

				if (preg_match('/(.*?),\\s+(.*)\\s+(\\d*(?:-\\d*)?)/', $user->city, $matches)) {
					$user->city  = $matches[1];
					$user->state = $matches[2];
					$user->zip   = $matches[3];
				}else if (preg_match('/(.*?)\\s+(\\w{2})\\s+(\\d*(?:-\\d*)?)/', $user->city, $matches)) {
					$user->city  = $matches[1];
					$user->state = $matches[2];
					$user->zip   = $matches[3];
				}
			}else{
				$user->address1 = "";
				$user->city     = "";
				$user->state    = "";
				$user->zip      = "";
			}

			$user->address2  = $user->city . ', ' . $user->state;
			$user->workPhone = !empty($patronDump['G/WK_PHONE']) ? $patronDump['G/WK_PHONE'] : '';
			if (!empty($patronDump['MOBILE_NO'])){
				$user->mobileNumber = $patronDump['MOBILE_NO'];
			}elseif (!empty($patronDump['MOBILE_PH'])){
				$user->mobileNumber = $patronDump['MOBILE_PH'];
			}else{
				$user->mobileNumber = '';
			}

			$user->finesVal = floatval(preg_replace('/[^\\d.]/', '', $patronDump['MONEY_OWED']));
			$user->fines    = $patronDump['MONEY_OWED'];

			if (isset($patronDump['USERNAME'])){
				$user->alt_username = $patronDump['USERNAME'];
			} elseif (isset($patronDump['ALT_ID'])) {
				// Apparently ALT_ID can also be used for the UserName in Sierra as well.
				// Hopefully this field doesn't server any other purpose
				$user->alt_username = $patronDump['ALT_ID'];
			}

			$numHoldsAvailable = 0;
			$numHoldsRequested = 0;
			$availableStatusRegex = isset($configArray['Catalog']['patronApiAvailableHoldsRegex']) ? $configArray['Catalog']['patronApiAvailableHoldsRegex'] : "/ST=(105|98|106),/";
			if (!empty($patronDump['HOLD']) && count($patronDump['HOLD']) > 0){
				foreach ($patronDump['HOLD'] as $hold){
					if (preg_match("$availableStatusRegex", $hold)){
						$numHoldsAvailable++;
					}else{
						$numHoldsRequested++;
					}
				}
			}
			$user->numCheckedOutIls     = $patronDump['CUR_CHKOUT'];
			$user->numHoldsIls          = isset($patronDump) ? (isset($patronDump['HOLD']) ? count($patronDump['HOLD']) : 0) : '?';
			$user->numHoldsAvailableIls = $numHoldsAvailable;
			$user->numHoldsRequestedIls = $numHoldsRequested;
			$user->numBookings          = isset($patronDump) ? (isset($patronDump['BOOKING']) ? count($patronDump['BOOKING']) : 0) : '?';

			$noticeLabels = array(
				//'-' => 'Mail',  // officially None in Sierra, as in No Preference Selected.
				'-' => '',  // notification will generally be based on what information is available so can't determine here. plb 12-02-2014
				'a' => 'Mail', // officially Print in Sierra
				'p' => 'Telephone',
				'z' => 'E-mail',
			);
			$user->notices = isset($patronDump) ? $patronDump['NOTICE_PREF'] : '-';
			if (array_key_exists($user->notices, $noticeLabels)){
				$user->noticePreferenceLabel = $noticeLabels[$user->notices];
			}else{
				$user->noticePreferenceLabel = 'Unknown';
			}

			if ($userExistsInDB){
				$user->update();
			}else{
				$user->created = date('Y-m-d');
				$user->insert();
			}

			$timer->logTime("Patron logged in successfully");
			return $user;

		} else {
			$timer->logTime("Patron login failed");
			return null;
		}
	}

	/**
	 * Get a dump of information from Millennium that can be used in other
	 * routines.
	 *
	 * @param string  $barcode the patron's barcode
	 * @param boolean $forceReload whether or not cached data can be used.
	 * @return array
	 */
	public function _getPatronDump(&$barcode, $forceReload = false)
	{
		global $configArray;
		/** @var Memcache $memCache */
		global $memCache;
		global $library;
		global $timer;

		$patronDump = $memCache->get("patron_dump_$barcode");
		if (!$patronDump || $forceReload){
			$host = isset($this->accountProfile->patronApiUrl) ? $this->accountProfile->patronApiUrl : null; // avoid warning notices
			if ($host == null){
				$host = $configArray['OPAC']['patron_host'];
			}
			$barcodesToTest   = array();
			$barcodesToTest[] = $barcode;

			//Special processing to allow users to login with short barcodes
			if ($library){
				if ($library->barcodePrefix){
					if (strpos($barcode, $library->barcodePrefix) !== 0){
						//Add the barcode prefix to the barcode
						$barcodesToTest[] = $library->barcodePrefix . $barcode;
					}
				}
			}

			//Special processing to allow MCVSD Students to login
			//with their student id.
			if (strlen($barcode)== 5){
				$barcodesToTest[] = "41000000" . $barcode;
				$barcodesToTest[] = "mv" . $barcode;
			}elseif (strlen($barcode)== 6){
				$barcodesToTest[] = "4100000" . $barcode;
				$barcodesToTest[] = "mv" . $barcode;
			}

			foreach ($barcodesToTest as $i=> $barcode){
				$patronDump = $this->_parsePatronApiPage($host, $barcode);

				if (empty($patronDump)){
					continue; // try any other barcodes
				}else if ((isset($patronDump['ERRNUM']) || count($patronDump) == 0) && $i != count($barcodesToTest) - 1){
					//check the next barcode
				}else{

					$timer->logTime('Finished loading patron dump from ILS.');
					$memCache->set("patron_dump_$barcode", $patronDump, 0, $configArray['Caching']['patron_dump']);
					//Need to wait a little bit since getting the patron api locks the record in the DB
					usleep(250);
					break;
				}
			}

		} else {
			$timer->logTime('Loaded Patron Dump from memcache');
		}
		return $patronDump;
	}

	private function _parsePatronApiPage($host, $barcode){
		global $timer;
		// Load Record Page.  This page has a dump of all patron information
		//as a simple name value pair list within the body of the webpage.
		//Sample format of a row is as follows:
		//P TYPE[p47]=100<BR>
		$patronApiUrl =  $host . "/PATRONAPI/" . $barcode ."/dump" ;
		$result       = $this->_curlGetPage($patronApiUrl);

		//Strip the actual contents out of the body of the page.
		//Periodically we get HTML like characters within the notes so strip tags breaks the page.
		//We really just need to remove the following tags:
		// <html>
		// <body>
		// <br>
		$cleanPatronData = preg_replace('/<(html|body|br)\s*\/?>/i', '', $result);
		//$cleanPatronData = strip_tags($result);

		//Add the key and value from each row into an associative array.
		$patronDump = array();
		preg_match_all('/(.*?)\\[.*?\\]=(.*)/', $cleanPatronData, $patronData, PREG_SET_ORDER);
		for ($curRow = 0; $curRow < count($patronData); $curRow++) {
			$patronDumpKey = str_replace(" ", "_", trim($patronData[$curRow][1]));
			switch ($patronDumpKey) {
				// multiple entries
				case 'HOLD' :
				case 'BOOKING' :
					$patronDump[$patronDumpKey][] = isset($patronData[$curRow][2]) ? $patronData[$curRow][2] : '';
					break;
				// single entries
				default :
					if (!array_key_exists($patronDumpKey, $patronDump)) {
						$patronDump[$patronDumpKey] = isset($patronData[$curRow][2]) ? $patronData[$curRow][2] : '';
					}
			}
		}

		$timer->logTime("Got patron information from Patron API for $barcode");
		return $patronDump;
	}

	/**
	 * @param $patron
	 * @return bool
	 */
	public function _curl_login($patron) {
		global $logger;
		$loginResult = false;

		$curlUrl   = $this->getVendorOpacUrl() . "/patroninfo/";
		$post_data = $this->_getLoginFormValues($patron);

		$logger->log('Loading page ' . $curlUrl, PEAR_LOG_INFO);

		$loginResponse = $this->_curlPostPage($curlUrl, $post_data);

		//When a library uses IPSSO, the initial login does a redirect and requires additional parameters.
		if (preg_match('/<input type="hidden" name="lt" value="(.*?)" \/>/si', $loginResponse, $loginMatches)) {
			$lt = $loginMatches[1]; //Get the lt value
			//Login again
			$post_data['lt']       = $lt;
			$post_data['_eventId'] = 'submit';

//			//Don't issue a post, just call the same page (with redirects as needed)
//			$post_string = http_build_query($post_data);
//			curl_setopt($this->curl_connection, CURLOPT_POSTFIELDS, $post_string);
//
//			$loginResponse = curl_exec($this->curl_connection);
			$loginResponse = $this->_curlPostPage($curlUrl, $post_data);
		}

		if ($loginResponse) {
			$loginResult = true;

			// Check for Login Error Responses
			$numMatches = preg_match('/<span.\s?class="errormessage">(?P<error>.+?)<\/span>/is', $loginResponse, $matches);
			if ($numMatches > 0) {
				$logger->log('Millennium Curl Login Attempt received an Error response : ' . $matches['error'], PEAR_LOG_DEBUG);
				$loginResult = false;
			} else {

				// Pause briefly after logging in as some follow-up millennium operations (done via curl) will fail if done too quickly
				usleep(50000);
			}
		}

		return $loginResult;
	}

	/**
	 * Get Patron Transactions
	 *
	 * This is responsible for retrieving all transactions (i.e. checked out items)
	 * by a specific patron.
	 *
	 * @param User $user           The user to load transactions for
	 * @param bool $linkedAccount  When using linked accounts for Sierra Encore, the curl connection for linked accounts has to be reset
	 * @return mixed               Array of the patron's transactions on success,
	 * PEAR_Error otherwise.
	 * @access public
	 */
	public function getMyCheckouts($user, $linkedAccount = false) {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCheckouts.php';
		$millenniumCheckouts = new MillenniumCheckouts($this);
		return $millenniumCheckouts->getMyCheckouts($user, $linkedAccount);
	}

	/**
	 * Return a page from classic with comments stripped
	 *
	 * @param User   $patron         User The unique identifier for the patron
	 * @param string $page           The page to be loaded
	 * @param bool   $linkedAccount  When using linked accounts for Sierra Encore, the curl connection for linked accounts has to be reset
	 * @return string                The page from classic
	 */
	public function _fetchPatronInfoPage($patron, $page, $linkedAccount = false){
		//First we have to login to classic
		if ($this->_curl_login($patron, $linkedAccount)) {
			$scope = $this->getDefaultScope();

			//Now we can get the page
			$curlUrl      = $this->getVendorOpacUrl() . "/patroninfo~S{$scope}/" . $patron->username . "/$page";
			$curlResponse = $this->_curlGetPage($curlUrl);

			//Strip HTML comments
			$curlResponse = preg_replace("/<!--([^(-->)]*)-->/", " ", $curlResponse);
			return $curlResponse;
		}
		return false;
	}

	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut") {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumReadingHistory.php';
		$millenniumReadingHistory = new MillenniumReadingHistory($this);
		return $millenniumReadingHistory->getReadingHistory($patron, $page, $recordsPerPage, $sortOption);
	}

	/**
	 * Do an update or edit of reading history information.  Current actions are:
	 * deleteMarked
	 * deleteAll
	 * exportList
	 * optOut
	 *
	 * @param   User    $patron
	 * @param   string  $action         The action to perform
	 * @param   array   $selectedTitles The titles to do the action on if applicable
	 */
	function doReadingHistoryAction($patron, $action, $selectedTitles){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumReadingHistory.php';
		$millenniumReadingHistory = new MillenniumReadingHistory($this);
		$millenniumReadingHistory->doReadingHistoryAction($patron, $action, $selectedTitles);
	}


	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron         The user to load transactions for
	 * @param bool $linkedAccount  When using linked accounts for Sierra Encore, the curl connection for linked accounts has to be reset
	 * @return array               Array of the patron's holds
	 * @access public
	 */
	public function getMyHolds($patron, $linkedAccount = false){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->getMyHolds($patron, $linkedAccount);
	}

	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param   User    $patron          The User to place a hold for
	 * @param   string  $recordId        The id of the bib record
	 * @param   string  $pickupBranch    The branch where the user wants to pickup the item when available
	 * @param   null|string $cancelDate  The date to cancel the hold if it isn't fulfilled
	 * @return  mixed                    True if successful, false if unsuccessful
	 *                                   If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null) {
		$result = $this->placeItemHold($patron, $recordId, '', $pickupBranch, $cancelDate);
		return $result;
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param   User    $patron          The User to place a hold for
	 * @param   string  $recordId        The id of the bib record
	 * @param   string  $itemId          The id of the item to hold
	 * @param   string  $pickupBranch    The branch where the user wants to pickup the item when available
	 * @param   null|string $cancelDate  The date to cancel the hold if it isn't fulfilled
	 * @return  mixed                    True if successful, false if unsuccessful
	 *                                   If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function placeItemHold($patron, $recordId, $itemId, $pickupBranch, $cancelDate = null) {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->placeItemHold($patron, $recordId, $itemId, $pickupBranch, $cancelDate);
	}

	/**
	 * Place Volume Hold
	 *
	 * This is responsible for both placing volume level holds.
	 *
	 * @param   User    $patron         The User to place a hold for
	 * @param   string  $recordId       The id of the bib record
	 * @param   string  $volumeId       The id of the volume to hold
	 * @param   string  $pickupBranch   The branch where the user wants to pickup the item when available
	 * @return  mixed                   True if successful, false if unsuccessful
	 *                                  If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch, $cancelDate = null) {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch, $cancelDate);
	}

	public function updateHold($patron, $requestId, $type){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->updateHold($patron, $requestId, $type);
	}

	public function updateHoldDetailed($patron, $type, $xNum, $cancelId, $locationId, $freezeValue='off'){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->updateHoldDetailed($patron, $type, $xNum, $cancelId, $locationId, $freezeValue);
	}

	public function cancelHold($patron, $recordId, $cancelId){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->updateHoldDetailed($patron, 'cancel', null, $cancelId, '', '');
	}

	function allowFreezingPendingHolds(){
		return false;
	}

	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->updateHoldDetailed($patron, 'update', null, $itemToFreezeId, '', 'on');
	}

	function thawHold($patron, $recordId, $itemToThawId){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->updateHoldDetailed($patron, 'update', null, $itemToThawId, '', 'off');
	}

	function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumHolds.php';
		$millenniumHolds = new MillenniumHolds($this);
		return $millenniumHolds->updateHoldDetailed($patron, 'update', null, $itemToUpdateId, $newPickupLocation, null); // freeze value of null gets us to change  pickup location
	}

	public function hasFastRenewAll(){
		return true;
	}

	public function renewAll($patron){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCheckouts.php';
		$millenniumCheckouts = new MillenniumCheckouts($this);
		return $millenniumCheckouts->renewAll($patron);
	}

	public function renewItem($patron, $recordId, $itemId, $itemIndex){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumCheckouts.php';
		$millenniumCheckouts = new MillenniumCheckouts($this);
		$numTries = 0;
		do {
			$result  = $millenniumCheckouts->renewItem($patron, $itemId, $itemIndex);
			$failure = !$result['success'] && stripos($result['message'], 'n use by system.');
			// If we get an account busy error let's try again a few times after a delay
			usleep(400000);
			$numTries++;
			if ($failure) {
				global $logger;
				$logger->log("System still busy after $numTries attempts at renewal", PEAR_LOG_ERR);
			}
		} while ($failure && $numTries < 4);
		return $result;
	}

	public function bookMaterial($patron, $recordId, $startDate, $startTime = null, $endDate = null, $endTime = null) {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumBooking.php';
		$millenniumBooking = new MillenniumBooking($this);
		return $millenniumBooking->bookMaterial($patron, $recordId, $startDate, $startTime, $endDate, $endTime);
	}

	/**
	 * @param User $user  User to cancel for
	 * @param $cancelIds  array uses a specific id for canceling a booking, rather than a record Id.
	 * @return array data for client-side AJAX responses
	 */
	public function cancelBookedMaterial($user, $cancelIds) {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumBooking.php';
		$millenniumBooking = new MillenniumBooking($this);
		return $millenniumBooking->cancelBookedMaterial($user, $cancelIds);
	}

	/**
	 * @param  User $patron
	 * @return array      data for client-side AJAX responses
	 */
	public function cancelAllBookedMaterial($patron) {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumBooking.php';
		$millenniumBooking = new MillenniumBooking($this);
		return $millenniumBooking->cancelAllBookedMaterial($patron);
	}

	public function getBookingCalendar($recordId) {
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumBooking.php';
		$millenniumBooking = new MillenniumBooking($this);
		return $millenniumBooking->getBookingCalendar($recordId);
	}

	/**
	 * @param User $user                     The User Object to make updates to
	 * @param boolean $canUpdateContactInfo  Permission check that updating is allowed
	 * @return array                         Array of error messages for errors that occurred
	 */
	public function updatePatronInfo($user, $canUpdateContactInfo){
		$updateErrors = array();

		if ($canUpdateContactInfo){
			//Setup the call to Millennium
			$barcode = $this->_getBarcode($user);
			$patronDump = $this->_getPatronDump($barcode);

			//Update profile information
			$extraPostInfo = array();
			if (isset($_REQUEST['address1'])){
				$extraPostInfo['addr1a'] = $_REQUEST['address1'];
				$extraPostInfo['addr1b'] = $_REQUEST['city'] . ', ' . $_REQUEST['state'] . ' ' . $_REQUEST['zip'];
				$extraPostInfo['addr1c'] = '';
				$extraPostInfo['addr1d'] = '';
			}
			$extraPostInfo['tele1'] = $_REQUEST['phone'];
			if (isset($_REQUEST['workPhone'])){
				$extraPostInfo['tele2'] = $_REQUEST['workPhone'];
			}
			$extraPostInfo['email'] = $_REQUEST['email'];

			if (!empty($_REQUEST['pickupLocation'])){
				$pickupLocation = $_REQUEST['pickupLocation'];
				if (strlen($pickupLocation) < 5){
					$pickupLocation = $pickupLocation . str_repeat(' ', 5 - strlen($pickupLocation));
				}
				$extraPostInfo['locx00'] = $pickupLocation;
			}

			if (isset($_REQUEST['notices'])){
				$extraPostInfo['notices'] = $_REQUEST['notices'];
			}

			if (isset($_REQUEST['alternate_username']) && $_REQUEST['alternate_username'] != $user->alt_username){
				// Only Update username if it has changed
				$extraPostInfo['user_name'] = $_REQUEST['alternate_username'];
			}

			if (!empty($_REQUEST['mobileNumber'])){
				$extraPostInfo['mobile'] = preg_replace('/\D/', '', $_REQUEST['mobileNumber']);
				if (strlen($_REQUEST['mobileNumber']) > 0 && $_REQUEST['smsNotices'] == 'on'){
					$extraPostInfo['optin'] = 'on';
					global $library;
					if ($library->addSMSIndicatorToPhone){
						//If the user is using SMS notices append TEXT ONLY to the primary phone number
						if (strpos($extraPostInfo['tele1'], '### TEXT ONLY') !== 0) {
							if (strpos($extraPostInfo['tele1'], 'TEXT ONLY') !== 0){
								$extraPostInfo['tele1'] = str_replace('TEXT ONLY ', '', $extraPostInfo['tele1']);
							}
							$extraPostInfo['tele1'] = '### TEXT ONLY ' . $extraPostInfo['tele1'];
						}

					}
				}else{
					$extraPostInfo['optin'] = 'off';
					$extraPostInfo['mobile'] = "";
					global $library;
					if ($library->addSMSIndicatorToPhone){
						if (strpos($extraPostInfo['tele1'], '### TEXT ONLY') === 0){
							$extraPostInfo['tele1'] = str_replace('### TEXT ONLY ', '', $extraPostInfo['tele1']);
						}else if (strpos($extraPostInfo['tele1'], 'TEXT ONLY') === 0){
							$extraPostInfo['tele1'] = str_replace('TEXT ONLY ', '', $extraPostInfo['tele1']);
						}
					}
				}
			}

			//Validate we have required info for notices
			if (isset($extraPostInfo['notices'])){
				if ($extraPostInfo['notices'] == 'z' && strlen($extraPostInfo['email']) == 0){
					$updateErrors[] = 'To receive notices by e-mail you must set an e-mail address.';
				}elseif ($extraPostInfo['notices'] == 'p' && strlen($extraPostInfo['tele1']) == 0){
					$updateErrors[] = 'To receive notices by phone you must provide a telephone number.';
				}elseif (strlen($extraPostInfo['addr1a']) == 0 || strlen($extraPostInfo['addr1b']) == 0){
					$updateErrors[] = 'To receive notices by mail you must provide a complete mailing address.';
				}
				if (count($updateErrors) > 0){
					return $updateErrors;
				}
			}

			//Login to the patron's account
			$this->_curl_login($user);

			//Issue a post request to update the patron information
			$scope = $this->getMillenniumScope();
			$curl_url = $this->getVendorOpacUrl() . "/patroninfo~S{$scope}/" . $patronDump['RECORD_#'] ."/modpinfo";
			$sresult = $this->_curlPostPage($curl_url, $extraPostInfo);

		// Update Patron Information on success
			global $analytics;
			if (isset($sresult) && strpos($sresult, 'Patron information updated') !== false){
				$user->phone = $_REQUEST['phone'];
				$user->email = $_REQUEST['email'];
				$user->alt_username = $_REQUEST['username'];
				$user->update();
				/* @var Memcache $memCache */
				global $memCache;
				$memCache->delete("patron_dump_$barcode"); // because the update will affect the patron dump information also clear that cache as well

				if ($analytics){
					$analytics->addEvent('ILS Integration', 'Profile updated successfully');
				}
			}else{
				// Doesn't look like the millennium (actually sierra) server ever provides error messages. plb 4-29-2015
				if (preg_match('/<h2 class="errormessage">(.*?)<\/h2>/i', $sresult, $errorMatches)){
					$errorMsg = $errorMatches[1]; // generic error message
				}else{
					$errorMsg = 'There were errors updating your information.'; // generic error message
				}

				$updateErrors[] = $errorMsg;
				if ($analytics){
					$analytics->addEvent('ILS Integration', 'Profile update failed');
				}
			}
		} else {
			$updateErrors[] = 'You can not update your information.';
		}
		return $updateErrors;
	}

	/** @var  int[] */
	var $pTypes;
	/**
	 * returns the patron type identifier if a patron is logged in or if the patron
	 * is not logged in, it will return the default PType for the library domain.
	 * If a domain is not in use it will return -1.
	 *
	 * @return int[]
	 */
	public function getPTypes(){
		if ($this->pTypes == null){
			$this->pTypes = array();
			/** @var $user User */
			$user = UserAccount::getLoggedInUser();
			/** @var $locationSingleton Location */
			global $locationSingleton;
			$searchLocation = $locationSingleton->getSearchLocation();
			$searchLibrary = Library::getSearchLibrary();
			if (isset($user) && $user != false){
				if (is_numeric($user->patronType)){
					$this->pTypes[] = $user->patronType;
				}else{
					$this->pTypes[] = -1;
				}
				//Add PTypes for any linked accounts
				foreach ($user->getLinkedUsers() as $tmpUser){
					if (is_numeric($tmpUser->patronType)){
						$this->pTypes[] = $tmpUser->patronType;
					}else{
						$this->pTypes[] = -1;
					}
				}
			}else if (isset($searchLocation) && $searchLocation->defaultPType >= 0){
				$this->pTypes[] = $searchLocation->defaultPType;
			}else if (isset($searchLibrary) && $searchLibrary->defaultPType >= 0){
				$this->pTypes[] = $searchLibrary->defaultPType;
			}else{
				$this->pTypes[] = -1;
			}
		}
		return $this->pTypes;
	}

	/**
	 * @param null|User $patron
	 * @return mixed
	 */
	public function _getBarcode($patron = null){
		if ($patron == null){
			$patron = UserAccount::getLoggedInUser();
		}
		if ($patron){
			return $patron->getBarcode();
		}else{
			return '';
		}
	}

	/**
	 * Checks millennium to determine if there are issue summaries available.
	 * If there are issue summaries available, it will return them in an array.
	 * With holdings below them.
	 *
	 * If there are no issue summaries, null will be returned from the summary.
	 *
	 * @param string $id
	 * @return mixed - array or null
	 */
	public function getIssueSummaries($id){
		$millenniumInfo = $this->getMillenniumRecordInfo($id);
		//Issue summaries are loaded from the main record page.

		if (preg_match('/class\\s*=\\s*\\"bibHoldings\\"/s', $millenniumInfo->framesetInfo)){
			//There are issue summaries available
			//Extract the table with the holdings
			$issueSummaries = array();
			$matches = array();
			if (preg_match('/<table\\s.*?class=\\"bibHoldings\\">(.*?)<\/table>/s', $millenniumInfo->framesetInfo, $matches)) {
				$issueSummaryTable = trim($matches[1]);
				//Each holdingSummary begins with a holdingsDivider statement
				$summaryMatches = explode('<tr><td colspan="2"><hr  class="holdingsDivider" /></td></tr>', $issueSummaryTable);
				if (count($summaryMatches) > 1){
					//Process each match independently
					foreach ($summaryMatches as $summaryData){
						$summaryData = trim($summaryData);
						if (strlen($summaryData) > 0){
							//Get each line within the summary
							$issueSummary = array();
							$issueSummary['type'] = 'issueSummary';
							$summaryLines = array();
							preg_match_all('/<tr\\s*>(.*?)<\/tr>/s', $summaryData, $summaryLines, PREG_SET_ORDER);
							for ($matchi = 0; $matchi < count($summaryLines); $matchi++) {
								$summaryLine = trim(str_replace('&nbsp;', ' ', $summaryLines[$matchi][1]));
								$summaryCols = array();
								if (preg_match('/<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>/s', $summaryLine, $summaryCols)) {
									$label = trim($summaryCols[1]);
									$value = trim(strip_tags($summaryCols[2]));
									//Check to see if this has a link to a check-in grid.
									if (preg_match('/.*?<a href="(.*?)">.*/s', $label, $linkData)) {
										//Parse the check-in id
										$checkInLink = $linkData[1];
										if (preg_match('/\/search~S\\d+\\?\/.*?\/.*?\/.*?\/(.*?)&.*/', $checkInLink, $checkInGridInfo)) {
											$issueSummary['checkInGridId'] = $checkInGridInfo[1];
										}
										$issueSummary['checkInGridLink'] = 'http://www.millenium.marmot.org' . $checkInLink;
									}
									//Convert to camel case
									$label = (preg_replace('/[^\\w]/', '', strip_tags($label)));
									$label = strtolower(substr($label, 0, 1)) . substr($label, 1);
									if ($label == 'location'){
										//Try to trim the courier code if any
										if (preg_match('/(.*?)\\sC\\d{3}\\w{0,2}$/', $value, $locationParts)){
											$value = $locationParts[1];
										}
									}elseif ($label == 'holdings'){
										//Change the lable to avoid conflicts with actual holdings
										$label = 'holdingStatement';
									}
									$issueSummary[$label] = $value;
								}
							}
							$issueSummaries[$issueSummary['location'] . count($issueSummaries)] = $issueSummary;
						}
					}
				}
			}

			return $issueSummaries;
		}else{
			return null;
		}
	}

	/**
	 * @param File_MARC_Record $marcRecord
	 * @return bool
	 */
	// Not used anywhere. pascal 20-18-2018
//	function isRecordHoldable($marcRecord){
//		$pTypes = $this->getPTypes();
//		global $configArray;
//		global $indexingProfiles;
//		if (array_key_exists($this->accountProfile->recordSource, $indexingProfiles)) {
//			/** @var IndexingProfile $indexingProfile */
//			$indexingProfile = $indexingProfiles[$this->accountProfile->recordSource];
//			$marcItemField = $indexingProfile->itemTag;
//			$iTypeSubfield = $indexingProfile->iType;
//			$locationSubfield = $indexingProfile->location;
//		}else{
//			$marcItemField = isset($configArray['Reindex']['itemTag']) ? $configArray['Reindex']['itemTag'] : '989';
//			$iTypeSubfield = isset($configArray['Reindex']['iTypeSubfield']) ? $configArray['Reindex']['iTypeSubfield'] : 'j';
//			$locationSubfield = isset($configArray['Reindex']['locationSubfield']) ? $configArray['Reindex']['locationSubfield'] : 'j';
//		}
//
//		/** @var File_MARC_Data_Field[] $items */
//		$items = $marcRecord->getFields($marcItemField);
//		$holdable = false;
//		$itemNumber = 0;
//		foreach ($items as $item){
//			$itemNumber++;
//			$subfield_j = $item->getSubfield($iTypeSubfield);
//			if (is_object($subfield_j) && !$subfield_j->isEmpty()){
//				$iType = $subfield_j->getData();
//			}else{
//				$iType = '0';
//			}
//			$subfield_d = $item->getSubfield($locationSubfield);
//			if (is_object($subfield_d) && !$subfield_d->isEmpty()){
//				$locationCode = $subfield_d->getData();
//			}else{
//				$locationCode = '?????';
//			}
//			//$logger->log("$itemNumber) iType = $iType, locationCode = $locationCode", PEAR_LOG_DEBUG);
//
//			//Check the determiner table to see if this matches
//			$holdable = $this->isItemHoldableToPatron($locationCode, $iType, $pTypes);
//
//			if ($holdable){
//				break;
//			}
//		}
//		return $holdable;
//	}

//	const SIERRA_ITYPE_WILDCARDS = array('999', '9999');
//	const SIERRA_PTYPE_WILDCARDS = array('999', '9999');
	//TODO: switch to const when php version is >= 5.6

	static $SIERRA_ITYPE_WILDCARDS = array('999', '9999');
	static $SIERRA_PTYPE_WILDCARDS = array('999', '9999');

	function isItemHoldableToPatron($locationCode, $iType, $pTypes){
		/** @var Memcache $memCache*/
		global $memCache;
		global $configArray;
		global $timer;
		global $serverName;
		$pTypeString = implode(',', $pTypes);
		$memcacheKey = "loan_rule_result_{$serverName}_{$locationCode}_{$iType}_{$pTypeString}";
		$cachedValue = $memCache->get($memcacheKey);
		if ($cachedValue !== false && !isset($_REQUEST['reload'])){
			return $cachedValue == 'true';
		}else{
			$timer->logTime("Start checking if item is holdable $locationCode, $iType, $pTypeString");
			$this->loadLoanRules();
			if (count($this->loanRuleDeterminers) == 0){
				//If we don't have any loan rules determiners, assume that the item is holdable.
				return true;
			}
			$holdable = false;
			//global $logger;
			//$logger->log("Checking loan rules for $locationCode, $iType, $pType", PEAR_LOG_DEBUG);
			foreach ($this->loanRuleDeterminers as $loanRuleDeterminer){
				//$logger->log("Determiner {$loanRuleDeterminer->rowNumber}", PEAR_LOG_DEBUG);
				//Check the location to be sure the determiner applies to this item
				if ($loanRuleDeterminer->matchesLocation($locationCode) ){
					//$logger->log("{$loanRuleDeterminer->rowNumber}) Location correct $locationCode, {$loanRuleDeterminer->location} ({$loanRuleDeterminer->trimmedLocation()})", PEAR_LOG_DEBUG);
					//Check that the iType is correct
					if (in_array($loanRuleDeterminer->itemType, self::$SIERRA_ITYPE_WILDCARDS) || in_array($iType, $loanRuleDeterminer->iTypeArray())){
						//$logger->log("{$loanRuleDeterminer->rowNumber}) iType correct $iType, {$loanRuleDeterminer->itemType}", PEAR_LOG_DEBUG);
						foreach ($pTypes as $pType){
							if ($pType == -1 || in_array($loanRuleDeterminer->patronType, self::$SIERRA_PTYPE_WILDCARDS) || in_array($pType, $loanRuleDeterminer->pTypeArray())){
								//$logger->log("{$loanRuleDeterminer->rowNumber}) pType correct $pType, {$loanRuleDeterminer->patronType}", PEAR_LOG_DEBUG);
								$loanRule = $this->loanRules[$loanRuleDeterminer->loanRuleId];
								//$logger->log("Determiner {$loanRuleDeterminer->rowNumber} indicates Loan Rule {$loanRule->loanRuleId} applies, holdable {$loanRule->holdable}", PEAR_LOG_DEBUG);
								$holdable = ($loanRule->holdable == 1);
								if ($holdable || $pType != -1){
									break;
								}
							}else{
								//$logger->log("PType incorrect", PEAR_LOG_DEBUG);
							}
						}

					}else{
						//$logger->log("IType incorrect", PEAR_LOG_DEBUG);
					}
				}else{
					//$logger->log("Location incorrect {$loanRuleDeterminer->location} != {$locationCode}", PEAR_LOG_DEBUG);
				}
				if ($holdable) break;
			}
			$memCache->set($memcacheKey, ($holdable ? 'true' : 'false'), 0 , $configArray['Caching']['loan_rule_result']);
			$timer->logTime("Finished checking if item is holdable $locationCode, $iType, $pTypeString");
		}

		return $holdable;
	}

	/**
	 * @param File_MARC_Record $marcRecord
	 * @return bool
	 */
	function isRecordBookable($marcRecord){
		//TODO: finish this, template from Holds
		global $configArray;
		$pTypes = $this->getPTypes();

		global $indexingProfiles;
		if (array_key_exists($this->accountProfile->recordSource, $indexingProfiles)) {
			/** @var IndexingProfile $indexingProfile */
			$indexingProfile = $indexingProfiles[$this->accountProfile->recordSource];
			$marcItemField = $indexingProfile->itemTag;
			$iTypeSubfield = $indexingProfile->iType;
			$locationSubfield = $indexingProfile->location;
		}else{
			//TODO: use indexing profile
			$marcItemField = isset($configArray['Reindex']['itemTag']) ? $configArray['Reindex']['itemTag'] : '989';
			$iTypeSubfield = isset($configArray['Reindex']['iTypeSubfield']) ? $configArray['Reindex']['iTypeSubfield'] : 'j';
			$locationSubfield = isset($configArray['Reindex']['locationSubfield']) ? $configArray['Reindex']['locationSubfield'] : 'j';
		}

		/** @var File_MARC_Data_Field[] $items */
		$items = $marcRecord->getFields($marcItemField);
		$bookable = false;
		$itemNumber = 0;
		foreach ($items as $item){
			$itemNumber++;
			$subfield_j = $item->getSubfield($iTypeSubfield);
			if (is_object($subfield_j) && !$subfield_j->isEmpty()){
				$iType = $subfield_j->getData();
			}else{
				$iType = '0';
			}
			$subfield_d = $item->getSubfield($locationSubfield);
			if (is_object($subfield_d) && !$subfield_d->isEmpty()){
				$locationCode = $subfield_d->getData();
			}else{
				$locationCode = '?????';
			}
			//$logger->log("$itemNumber) iType = $iType, locationCode = $locationCode", PEAR_LOG_DEBUG);

			//Check the determiner table to see if this matches
			$bookable = $this->isItemBookableToPatron($locationCode, $iType, $pTypes);

			if ($bookable){
				break;
			}
		}
		return $bookable;
	}

	public function isItemBookableToPatron($locationCode, $iType, $pTypes){
		/** @var Memcache $memCache*/
		global $memCache;
		global $configArray;
		global $timer;
		$pTypeString = implode(',', $pTypes);
		$memcacheKey = "loan_rule_material_booking_result_{$locationCode}_{$iType}_{$pTypeString}";
		$cachedValue = $memCache->get($memcacheKey);
		$pType = '';
		if ($cachedValue !== false && !isset($_REQUEST['reload'])){
			return $cachedValue == 'true';
		}else {
			$timer->logTime("Start checking if item is bookable $locationCode, $iType, $pTypeString");
			$this->loadLoanRules();
			if (count($this->loanRuleDeterminers) == 0){
				//If we don't have any loan rules determiners, assume that the item isn't bookable.
				return false;
			}
			$bookable = false;
			//global $logger;
			//$logger->log("Checking loan rules for $locationCode, $iType, $pType", PEAR_LOG_DEBUG);
			foreach ($this->loanRuleDeterminers as $loanRuleDeterminer){
				//$logger->log("Determiner {$loanRuleDeterminer->rowNumber}", PEAR_LOG_DEBUG);
				//Check the location to be sure the determiner applies to this item
				if ($loanRuleDeterminer->matchesLocation($locationCode) ){
					//$logger->log("{$loanRuleDeterminer->rowNumber}) Location correct $locationCode, {$loanRuleDeterminer->location} ({$loanRuleDeterminer->trimmedLocation()})", PEAR_LOG_DEBUG);
					//Check that the iType is correct
					if (in_array($loanRuleDeterminer->itemType, self::$SIERRA_ITYPE_WILDCARDS) || in_array($iType, $loanRuleDeterminer->iTypeArray())){
						//$logger->log("{$loanRuleDeterminer->rowNumber}) iType correct $iType, {$loanRuleDeterminer->itemType}", PEAR_LOG_DEBUG);
						foreach ($pTypes as $pType){
							if ($pType == -1 || in_array($loanRuleDeterminer->patronType, self::$SIERRA_PTYPE_WILDCARDS) || in_array($pType, $loanRuleDeterminer->pTypeArray())){
								//$logger->log("{$loanRuleDeterminer->rowNumber}) pType correct $pType, {$loanRuleDeterminer->patronType}", PEAR_LOG_DEBUG);
								$loanRule = $this->loanRules[$loanRuleDeterminer->loanRuleId];
								//$logger->log("Determiner {$loanRuleDeterminer->rowNumber} indicates Loan Rule {$loanRule->loanRuleId} applies, bookable {$loanRule->bookable}", PEAR_LOG_DEBUG);
								$bookable = ($loanRule->bookable == 1);
								if ($bookable || $pType != -1){
									break;
								}
							}
//						else{
//							//$logger->log("PType incorrect", PEAR_LOG_DEBUG);
//						}
						}
					}
//					else{
//						//$logger->log("IType incorrect", PEAR_LOG_DEBUG);
//					}
				}
//				else{
//					//$logger->log("Location incorrect {$loanRuleDeterminer->location} != {$locationCode}", PEAR_LOG_DEBUG);
//				}
			}
			$memCache->set($memcacheKey, ($bookable ? 'true' : 'false'), 0 , $configArray['Caching']['loan_rule_result']); // TODO: set a different config option for booking results?
			$timer->logTime("Finished checking if item is bookable $locationCode, $iType, $pType");
		}

		return $bookable;

	}

	public function getMyBookings($patron){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumBooking.php';
		$millenniumBookings = new MillenniumBooking($this);
		return $millenniumBookings->getMyBookings($patron);
	}

	function getCheckInGrid($id, $checkInGridId){
		//Issue summaries are loaded from the main record page.
//		global $configArray;

		// Strip ID
		$id_ = substr(str_replace('.b', '', $id), 0, -1);

		// Load Record Page

		$host        = $this->getVendorOpacUrl();
		$branchScope = $this->getMillenniumScope();
		$url         =  $host . "/search~S{$branchScope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/$checkInGridId&FF=1,0,";
		$result      = $this->_curlGetPage($url);

		//Extract the actual table
		$checkInData = array();
		if (preg_match('/<table  class="checkinCardTable">(.*?)<\/table>/s', $result, $matches)) {
			$checkInTable = trim($matches[1]);

			//Extract each item from the grid.
			preg_match_all('/.*?<td valign="top" class="(.*?)">(.*?)<\/td>/s', $checkInTable, $checkInCellMatch, PREG_SET_ORDER);
			for ($matchi = 0; $matchi < count($checkInCellMatch); $matchi++) {
				$cellData = trim($checkInCellMatch[$matchi][2]);
				$checkInCell                 = array();
				$checkInCell['class']        = $checkInCellMatch[$matchi][1];
				//Load issue date, status, date received, issue number, copies received
				if (preg_match('/(.*?)<br\\s*\/?>.*?<span class="(?:.*?)">(.*?)<\/span>.*?on (\\d{1,2}-\\d{1,2}-\\d{1,2})<br\\s*\/?>(.*?)(?:<!-- copies --> \\((\\d+) copy\\))?<br\\s*\/?>/s', $cellData, $matches)) {
					$checkInCell['issueDate']   = trim($matches[1]);
					$checkInCell['status']      = trim($matches[2]);
					$checkInCell['statusDate']  = trim($matches[3]);
					$checkInCell['issueNumber'] = trim($matches[4]);
					if (isset($matches[5])){
						$checkInCell['copies']    = trim($matches[5]);
					}
				}
				$checkInData[] = $checkInCell;
			}
		}
		return $checkInData;
	}

	function _getItemDetails($id, $holdings){
		global $logger;
		global $configArray;
		$scope = $this->getDefaultScope();

		$shortId = substr(str_replace('.b', 'b', $id), 0, -1);

		//Login to the site using vufind login.
		$cookie = tempnam ("/tmp", "CURLCOOKIE");
		$curl_url = $this->getVendorOpacUrl() . "/patroninfo";
		$logger->log('Loading page ' . $curl_url, PEAR_LOG_INFO);
		//echo "$curl_url";
		$curl_connection = curl_init($curl_url);
		curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
		curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl_connection, CURLOPT_UNRESTRICTED_AUTH, true);
		$post_data['name'] = $configArray['Catalog']['ils_admin_user'];
		$post_data['code'] = $configArray['Catalog']['ils_admin_pwd'];
//		$post_items = array();
//		foreach ($post_data as $key => $value) {
//			$post_items[] = $key . '=' . urlencode($value);
//		}
//		$post_string = implode ('&', $post_items);
		$post_string = http_build_query($post_data);
		curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);
		curl_exec($curl_connection);

		foreach ($holdings as $itemNumber => $holding){
			//Get the staff page for the record
			//$curl_url = "https://sierra.marmot.org/search~S93?/Ypig&searchscope=93&SORT=D/Ypig&searchscope=93&SORT=D&SUBKEY=pig/1,383,383,B/staffi1~$shortId&FF=Ypig&2,2,";
			$curl_url = $this->getVendorOpacUrl() . "/search~S{$scope}?/Ypig&searchscope={$scope}&SORT=D/Ypig&searchscope={$scope}&SORT=D&SUBKEY=pig/1,383,383,B/staffi$itemNumber~$shortId&FF=Ypig&2,2,";
			$logger->log('Loading page ' . $curl_url, PEAR_LOG_INFO);
			//echo "$curl_url";
			curl_setopt($curl_connection, CURLOPT_URL, $curl_url);
			curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($curl_connection, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($curl_connection, CURLOPT_UNRESTRICTED_AUTH, true);
			curl_setopt($curl_connection, CURLOPT_COOKIEJAR, $cookie );
			curl_setopt($curl_connection, CURLOPT_COOKIESESSION, false);
			$sResult = curl_exec($curl_connection);

			//Extract Item information
			if (preg_match('/<!-- Fixfields -->.*?<table.*?>(.*?)<\/table>.*?<!-- Varfields -->.*?<table.*?>(.*?)<\/table>.*?<!-- Lnkfields -->.*?<table.*?>(.*?)<\/table>/s', $sResult, $matches)) {
				$fixFieldString = $matches[1];
				$varFieldString = $matches[2];
			}

			//Extract the fixFields into an array of name value pairs
			$fixFields = array();
			if (isset($fixFieldString)){
				preg_match_all('/<td><font size="-1"><em>(.*?)<\/em><\/font>&nbsp;<strong>(.*?)<\/strong><\/td>/s', $fixFieldString, $fieldData, PREG_PATTERN_ORDER);
				for ($i = 0; $i < count($fieldData[0]); $i++) {
					$fixFields[$fieldData[1][$i]] = $fieldData[2][$i];
				}
			}

			//Extract the fixFields into an array of name value pairs
			$varFields = array();
			if (isset($varFieldString)){
				preg_match_all('/<td.*?><font size="-1"><em>(.*?)<\/em><\/font><\/td><td width="80%">(.*?)<\/td>/s', $varFieldString, $fieldData, PREG_PATTERN_ORDER);
				for ($i = 0; $i < count($fieldData[0]); $i++) {
					$varFields[$fieldData[1][$i]] = $fieldData[2][$i];
				}
			}

			//Add on the item information
			$holdings[$itemNumber] = array_merge($fixFields, $varFields, $holding);
		}
		curl_close($curl_connection);
	}

	function combineCityStateZipInSelfRegistration(){
		return true;
	}

	/**
	 * Override this function in the Site specific driver when the middle name
	 * is a seperate field to submit in the classic OPAC registration form.
	 * @return bool
	 */
	function isMiddleNameASeparateFieldInSelfRegistration(){
		return false;
	}

	function selfRegister(){
		global $logger;
		global $library;

		$firstName       = trim($_REQUEST['firstName']);
		$middleName      = trim($_REQUEST['middleName']);
		$lastName        = trim($_REQUEST['lastName']);
		$address         = trim($_REQUEST['address']);
		$city            = trim($_REQUEST['city']);
		$state           = trim($_REQUEST['state']);
		$zip             = trim($_REQUEST['zip']);
		$email           = trim($_REQUEST['email']);

		$SelfRegistrationURL = $this->getVendorOpacUrl() . "/selfreg~S" . $this->getLibraryScope();
		$logger->log('Loading page ' . $SelfRegistrationURL, PEAR_LOG_INFO);

		if ($this->isMiddleNameASeparateFieldInSelfRegistration()) {
			$post_data['nfirst']         =  $firstName;
			if (!empty($middleName)){
				$post_data['nmiddle']      =  $middleName;
			}
		} else {
			$post_data['nfirst']         = $middleName ? $firstName.' '.$middleName : $firstName; // add middle name onto first name;
		}
		$post_data['nlast']            = $lastName;
		$post_data['stre_aaddress']    = $address;
		if (!empty($_REQUEST['physicalAddress'])) {
			$physicalAddress             = trim($_REQUEST['physicalAddress']);
			$post_data['stre_haddress2'] = $physicalAddress;
		}
		if (!empty($_REQUEST['countyAddress'])) {
			$post_data['coun_aaddress'] = trim($_REQUEST['countyAddress']);
		}
		if ($this->combineCityStateZipInSelfRegistration()){
			$post_data['city_aaddress']  = "$city, $state $zip";
		}else{
			$post_data['city_aaddress']  = "$city";
			$post_data['stat_aaddress']  = "$state";
			$post_data['post_aaddress']  = "$zip";
		}

		$post_data['zemailaddr'] = $email;
		if (!empty($_REQUEST['phone'])){
			$phone = trim($_REQUEST['phone']);
			$post_data['tphone1'] = $phone;
		}
		if (!empty($_REQUEST['birthDate'])){
			$post_data['F051birthdate'] = trim($_REQUEST['birthDate']);
		}
		if (!empty($_REQUEST['universityID'])){
//			$post_data['universityID'] = trim($_REQUEST['universityID']);
			$post_data['uuniversityID'] = trim($_REQUEST['universityID']); // I think the initial double u is the correct entry. No one is currently using this so I can't confirm. Pascal. 10-11-2018
		}

		if ( isset($_REQUEST['ddepartment']) && !empty($_REQUEST['ddepartment'])) {
			$post_data['ddepartment'] = $_REQUEST['ddepartment'];
		}

		if (!empty($_REQUEST['signature'])){
			// Bemis self-registration form requires signature
			$post_data['signature'] = trim($_REQUEST['signature']);
		}

		if (!empty($library->selfRegistrationTemplate) && $library->selfRegistrationTemplate != 'default'){
			$post_data['TemplateName'] = $library->selfRegistrationTemplate;
		}

		$selfRegistrationResult = $this->_curlPostPage($SelfRegistrationURL, $post_data);

		//Parse the library card number from the response
		if (preg_match('/Your barcode is:.*?(\\d+)<\/(b|strong)>/s', $selfRegistrationResult, $matches)) {
			$barcode = $matches[1];
			return array('success' => true, 'barcode' => $barcode);
		} elseif (preg_match('/msg_confirm_note.*?(\\d+).*msg_confirm_note/s', $selfRegistrationResult, $matches)) {
			// Success for Sacramento
				$barcode = $matches[1];
				return array('success' => true, 'barcode' => $barcode);

		} else {
			return array('success' => false, 'barcode' => '');
		}

	}

	public function _getLoginFormValues($patron){
		$loginData = array();
		$loginData['name'] = $patron->cat_username;
		$loginData['code'] = $patron->cat_password;

		return $loginData;
	}

	/**
	 * Process inventory for a particular item in the catalog
	 *
	 * @param string $login     Login for the user doing the inventory
	 * @param string $password1 Password for the user doing the inventory
	 * @param string $initials
	 * @param string $password2
	 * @param string[] $barcodes
	 * @param boolean $updateIncorrectStatuses
	 *
	 * @return array
	 */
	function doInventory($login, $password1, $initials, $password2, $barcodes, $updateIncorrectStatuses){
		require_once ROOT_DIR . '/Drivers/marmot_inc/MillenniumInventory.php';
		$millenniumInventory = new MillenniumInventory($this);
		return $millenniumInventory->doInventory($login, $password1, $initials, $password2, $barcodes, $updateIncorrectStatuses);
	}

	/**
	 * @param $username
	 * @param $patronName
	 * @return array
	 */
	public function validatePatronName($username, $patronName) {
		$nameParts = explode(',', $patronName);
		$lastName = ucwords(strtolower(trim($nameParts[0])));

		if (isset($nameParts[1])){
			$firstName = ucwords(strtolower(trim($nameParts[1])));
		}else{
			$firstName = null;
		}

		$fullName         = str_replace(",", " ", $patronName);
		$fullName         = str_replace(";", " ", $fullName);
		$fullName         = preg_replace("/\\s{2,}/", " ", $fullName);
		$allNameComponents = preg_split('/[\s-]/', strtolower($fullName));
		foreach ($allNameComponents as $name){
			$newName = str_replace('-', '', $name);
			if ($newName != $name){
				$allNameComponents[] = $newName;
			}
			$newName = str_replace("'", '', $name);
			if ($newName != $name){
				$allNameComponents[] = $newName;
			}
		}
		$fullName = ucwords(strtolower($patronName));

		//Get the first name that the user supplies.
		//This expects the user to enter one or two names and only
		//Validates the first name that was entered.
		$username     = str_replace(",", " ", $username);
		$username     = str_replace(";", " ", $username);
		$username     = preg_replace("/\\s{2,}/", " ", $username);
		$enteredNames = preg_split('/[\s-]/', strtolower($username));
		$userValid = false;
		foreach ($enteredNames as $name) {
			if (in_array($name, $allNameComponents, false)) {
				$userValid = true;
				break;
			}
		}
		return array($fullName, $lastName, $firstName, $userValid);
	}

	/**
	 * @param User $patron           The user to load transactions for
	 * @param bool $includeMessages
	 * @param bool $linkedAccount    When using linked accounts for Sierra Encore, the curl connection for linked accounts has to be reset
	 * @return array                 Array of fines data
	 */
	public function getMyFines($patron = null, $includeMessages = false, $linkedAccount = false){
		//Load the information from millennium using CURL
		$pageContents = $this->_fetchPatronInfoPage($patron, 'overdues', $linkedAccount);

		//Get the fines table data
		$messages = array();
		if (preg_match('/<table border="0" class="patFunc">(.*?)<\/table>/si', $pageContents, $regs)) {
			$finesTable = $regs[1];
			//Get the title and, type, and fine detail from the page
			preg_match_all('/<tr class="(patFuncFinesEntryTitle|patFuncFinesEntryDetail|patFuncFinesDetailDate)">(.*?)<\/tr>/si', $finesTable, $rowDetails, PREG_SET_ORDER);
			$curFine = array();
			for ($match1 = 0; $match1 < count($rowDetails); $match1++) {
				$rowType = $rowDetails[$match1][1];
				$rowContents = $rowDetails[$match1][2];
				if ($rowType == 'patFuncFinesEntryTitle'){
					if ($curFine != null) $messages[] = $curFine;
					$curFine = array();
					if (preg_match('/<td.*?>(.*?)<\/td>/si', $rowContents, $colDetails)){
						$curFine['message'] = trim(strip_tags($colDetails[1]));
					}
				}else if ($rowType == 'patFuncFinesEntryDetail'){
					if (preg_match_all('/<td.*?>(.*?)<\/td>/si', $rowContents, $colDetails, PREG_SET_ORDER) > 0){
						$curFine['reason'] = trim(strip_tags($colDetails[1][1]));
						$curFine['amount'] = trim($colDetails[2][1]);
					}
				}else if ($rowType == 'patFuncFinesDetailDate'){
					if (preg_match_all('/<td.*?>(.*?)<\/td>/si', $rowContents, $colDetails, PREG_SET_ORDER) > 0){
						if (!array_key_exists('details', $curFine)) $curFine['details'] = array();
						$curFine['details'][] = array(
							'label' => trim(strip_tags($colDetails[1][1])),
							'value' => trim(strip_tags($colDetails[2][1])),
						);
					}
				}
			}
			if ($curFine != null) $messages[] = $curFine;
		}

		return $messages;
	}


	//This function is to match other drivers
	//TODO: refactor driver
	public function emailResetPin($barcode) {
		$requestPinResetResult = $this->requestPinReset($barcode);
		if ($requestPinResetResult['error']) {
			// Re-arrange result to match template's expected input
			$requestPinResetResult['error'] = $requestPinResetResult['message'];
		}
		return $requestPinResetResult;
	}

	public function requestPinReset($barcode){
		$pinResetUrl     = $this->getVendorOpacUrl() . '/pinreset';
		$this->_curlGetPage($pinResetUrl); 		//Go to the pin reset page first

		//Now submit the request
		$post_data['code']       = $barcode;
		$post_data['pat_submit'] = 'xxx';
		$pinResetResultPageHtml = $this->_curlPostPage($pinResetUrl, $post_data);

		//Parse the response
		$result = array(
			'success' => false,
			'error'   => true,
			'message' => 'Unknown error resetting pin'
		);

		if (preg_match('/<div class="errormessage">(.*?)<\/div>/is', $pinResetResultPageHtml, $matches)){
			$result['error']   = false;
			$result['message'] = trim($matches[1]);
		}elseif (preg_match('/<div class="pageContent">.*?<strong>(.*?)<\/strong>/si', $pinResetResultPageHtml, $matches)){
			$result['error']   = false;
			$result['success'] = true;
			$result['message'] = trim($matches[1]);
		}elseif (preg_match('/<div id="content">.*?<strong>(.*?)<\/strong>/si', $pinResetResultPageHtml, $matches)){
			//Sacramento result (Possible Encore result)
			$result['error']   = false;
			$result['success'] = true;
			$result['message'] = trim($matches[1]);
		}
		return $result;
	}

	/**
	 * Import Lists from the ILS
	 *
	 * @param  User $patron
	 * @return array - an array of results including the names of the lists that were imported as well as number of titles.
	 */
	function importListsFromIls($patron){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		$user = UserAccount::getLoggedInUser();
		$results = array(
			'totalTitles' => 0,
			'totalLists' => 0
		);

		//Get the page which contains a table with all lists in them.
		$listsPage = $this->_fetchPatronInfoPage($patron, 'mylists');
		//Get the actual table
		if (preg_match('/<table[^>]*?class="patFunc"[^>]*?>(.*?)<\/table>/si', $listsPage, $listsPageMatches)) {
			$allListTable = $listsPageMatches[1];
			//Now that we have the table, get the actual list names and ids
			preg_match_all('/<tr[^>]*?class="patFuncEntry"[^>]*?>.*?<input type="checkbox" id ="(\\d+)".*?<a.*?>(.*?)<\/a>.*?<td[^>]*class="patFuncDetails">(.*?)<\/td>.*?<\/tr>/si', $allListTable, $listDetails, PREG_SET_ORDER);
			for ($listIndex = 0; $listIndex < count($listDetails); $listIndex++ ){
				$listId = $listDetails[$listIndex][1];
				$title = $listDetails[$listIndex][2];
				$description = str_replace('&nbsp;', '', $listDetails[$listIndex][3]);

				//Create the list (or find one that already exists)
				$newList = new UserList();
				$newList->user_id = $user->id;
				$newList->title = $title;
				if (!$newList->find(true)){
					$newList->description = strip_tags($description);
					$newList->insert();
				}

				$currentListTitles = $newList->getListTitles();

				//Get a list of all titles within the list to be imported
				$listDetailsPage = $this->_fetchPatronInfoPage($patron, 'mylists?listNum='. $listId);
				//Get the table for the details
				if (preg_match('/<table[^>]*?class="patFunc"[^>]*?>(.*?)<\/table>/si', $listDetailsPage, $listsDetailsMatches)) {
					$listTitlesTable = $listsDetailsMatches[1];
					//Get the bib numbers for the title
					preg_match_all('/<input type="checkbox" name="(b\\d{1,7})".*?<span[^>]*class="patFuncTitle(?:Main)?">(.*?)<\/span>/si', $listTitlesTable, $bibNumberMatches, PREG_SET_ORDER);
					for ($bibCtr = 0; $bibCtr < count($bibNumberMatches); $bibCtr++){
						$bibNumber = $bibNumberMatches[$bibCtr][1];
						$bibTitle = strip_tags($bibNumberMatches[$bibCtr][2]);

						//Get the grouped work for the resource
						require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
						require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
						$primaryIdentifier = new GroupedWorkPrimaryIdentifier();
						$groupedWork = new GroupedWork();
						$primaryIdentifier->identifier = '.' . $bibNumber . $this->getCheckDigit($bibNumber);
						$primaryIdentifier->type = 'ils';
						$primaryIdentifier->joinAdd($groupedWork);
						if ($primaryIdentifier->find(true)){
							//Check to see if this title is already on the list.
							$resourceOnList = false;
							foreach ($currentListTitles as $currentTitle){
								if ($currentTitle->groupedWorkPermanentId == $primaryIdentifier->permanent_id){
									$resourceOnList = true;
									break;
								}
							}

							if (!$resourceOnList){
								$listEntry = new UserListEntry();
								$listEntry->groupedWorkPermanentId = $primaryIdentifier->permanent_id;
								$listEntry->listId = $newList->id;
								$listEntry->notes = '';
								$listEntry->dateAdded = time();
								$listEntry->insert();
							}
						}else{
							//The title is not in the resources, add an error to the results
							if (!isset($results['errors'])){
								$results['errors'] = array();
							}
							$results['errors'][] = "\"$bibTitle\" on list $title could not be found in the catalog and was not imported.";
						}

						$results['totalTitles']++;
					}
				}

				$results['totalLists'] += 1;
			}
		}

		return $results;
	}

	/**
	 * Calculates a check digit for a III identifier
	 * @param basedId String the base id without checksum
	 * @return String the check digit
	 */
	function getCheckDigit($baseId){
		$baseId = preg_replace('/\.?[bij]/', '', $baseId);
		$sumOfDigits = 0;
		for ($i = 0; $i < strlen($baseId); $i++){
			$curDigit = substr($baseId, $i, 1);
			$sumOfDigits += ((strlen($baseId) + 1) - $i) * $curDigit;
		}
		$modValue = $sumOfDigits % 11;
		if ($modValue == 10){
			return "x";
		}else{
			return $modValue;
		}
	}

	public function getSelfRegistrationFields(){
		global $library;
		$fields = array();
		$fields[] = array('property'=>'firstName', 'type'=>'text', 'label'=>'First Name', 'description'=>'Your first name', 'maxLength' => 40, 'required' => true);
		$fields[] = array('property'=>'middleName', 'type'=>'text', 'label'=>'Middle Name', 'description'=>'Your middle name', 'maxLength' => 40, 'required' => false);
		// gets added to the first name separated by a space
		$fields[] = array('property'=>'lastName', 'type'=>'text', 'label'=>'Last Name', 'description'=>'Your last name', 'maxLength' => 40, 'required' => true);
		if ($library && $library->promptForBirthDateInSelfReg){
			$fields[] = array('property'=>'birthDate', 'type'=>'date', 'label'=>'Date of Birth (MM-DD-YYYY)', 'description'=>'Date of birth', 'maxLength' => 10, 'required' => true);
		}
		$fields[] = array('property'=>'address', 'type'=>'text', 'label'=>'Mailing Address', 'description'=>'Mailing Address', 'maxLength' => 128, 'required' => true);
		$fields[] = array('property'=>'city', 'type'=>'text', 'label'=>'City', 'description'=>'City', 'maxLength' => 48, 'required' => true);
		$fields[] = array('property'=>'state', 'type'=>'text', 'label'=>'State', 'description'=>'State', 'maxLength' => 32, 'required' => true);
		$fields[] = array('property'=>'zip', 'type'=>'text', 'label'=>'Zip Code', 'description'=>'Zip Code', 'maxLength' => 32, 'required' => true);
		$fields[] = array('property'=>'email', 'type'=>'email', 'label'=>'E-Mail', 'description'=>'E-Mail', 'maxLength' => 128, 'required' => false);
		$fields[] = array('property'=>'phone', 'type'=>'text', 'label'=>'Phone (xxx-xxx-xxxx)', 'description'=>'Phone', 'maxLength' => 128, 'required' => false);

		return $fields;
	}

	public function hasNativeReadingHistory() {
		return true;
	}

	public function getNumHolds($id) {
		return 0;
	}

	protected function _doPinTest($barcode, $pin) {
		$pin              = urlencode(trim($pin));
		$barcode          = trim($barcode);
		$pinTestUrl       = $this->accountProfile->patronApiUrl . "/PATRONAPI/$barcode/$pin/pintest";
		$pinTestResultRaw = $this->_curlGetPage($pinTestUrl);
		//$logger->log('PATRONAPI pintest response : ' . $api_contents, PEAR_LOG_DEBUG);
		if ($pinTestResultRaw){
			$pinTestResult = strip_tags($pinTestResultRaw);

			//Parse the page
			$pinTestData = array();
			preg_match_all('/(.*?)=(.*)/', $pinTestResult, $patronData, PREG_SET_ORDER);
			for ($curRow = 0; $curRow < count($patronData); $curRow++) {
				$patronDumpKey = str_replace(" ", "_", trim($patronData[$curRow][1]));
				$pinTestData[$patronDumpKey] = isset($patronData[$curRow][2]) ? $patronData[$curRow][2] : '';
			}
			if (!isset($pinTestData['RETCOD'])){
				$userValid = false;
			}else if ($pinTestData['RETCOD'] == 0){
				$userValid = true;
			}else{
				$userValid = false;
			}
		}else{
			$userValid = false;
		}

		return $userValid;
	}

	public function showLinksForRecordsWithItems() {
		return false;
	}
}
