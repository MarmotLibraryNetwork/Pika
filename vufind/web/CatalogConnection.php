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
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category Pika
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
use Pika\PatronDrivers\RBdigital;

class CatalogConnection
{
	/**
	 * A boolean value that defines whether a connection has been successfully
	 * made.
	 *
	 * @access public
	 * @var    bool
	 */
	public $status = false;

	public $accountProfile;

	/**
	 * The object of the appropriate driver.
	 *
	 * @access private
	 * @var    \Pika\PatronDrivers\Sierra|HorizonROA|SirsiDynixROA|DriverInterface
	 */
	public $driver;

	private Pika\Cache $cache;
	private Pika\Logger $logger;

	/**
	 * Constructor
	 *
	 * This is responsible for instantiating the driver that has been specified.
	 *
	 * @param string         $driver         The name of the driver to load.
	 * @param AccountProfile $accountProfile
	 * @throws Exception error if we cannot connect to the driver.
	 *
	 * @access public
	 */
	public function __construct($driver, $accountProfile){
		$this->cache  = new Pika\Cache();
		$this->logger = new Pika\Logger("CatalogConnection");
		if ($driver != 'DriverInterface'){
			$path = ROOT_DIR . "/Drivers/{$driver}.php";
			if (file_exists($path)){
				require_once $path;
			}

				try {
					$this->driver = new $driver($accountProfile);
				} catch (Exception $e){
					$this->logger->error(
					 "Unable to create driver $driver for account profile {$accountProfile->name}",
					 ["stack_trace" => $e->getTraceAsString()]
					);
					throw $e;
				}
				$this->accountProfile = $accountProfile;
				$this->status         = true;
		}
	}

	/**
	 * Check Function
	 *
	 * This is responsible for checking the driver configuration to determine
	 * if the system supports a particular function.
	 *
	 * @param string $function The name of the function to check.
	 *
	 * @return mixed On success, an associative array with specific function keys
	 * and values; on failure, false.
	 * @access public
	 * @deprecated Just use method_exists(); The additional functionality doesn't come into effect
	 */
	public function checkFunction($function)
	{
		// Extract the configuration from the driver if available:
		$functionConfig = method_exists($this->driver, 'getConfig') ? $this->driver->getConfig($function) : false;

		// See if we have a corresponding check method to analyze the response:
		$checkMethod = "_checkMethod".$function;
		if (!method_exists($this, $checkMethod)) {
			//Just see if the method exists on the driver
			return method_exists($this->driver, $function);
		}

		// Send back the settings:
		return $this->$checkMethod($functionConfig);
	}

	/**
	 * Get Holding
	 *
	 * This is responsible for retrieving the holding information of a certain
	 * record.
	 *
	 * @param string $recordId The record id to retrieve the holdings for
	 * @param array  $patron   Optional Patron details to determine if a user can
	 * place a hold or recall on an item
	 *
	 * @return mixed     On success, an associative array with the following keys:
	 * id, availability (boolean), status, location, reserve, callnumber, dueDate,
	 * number, barcode; on failure, a PEAR_Error.
	 * @access public
	 */
//	public function getHolding($recordId, $patron = false)
//	{
//		$holding = $this->driver->getHolding($recordId, $patron);
//
//		// Validate return from driver's getHolding method -- should be an array or
//		// an error.  Anything else is unexpected and should become an error.
//		if (!is_array($holding) && !PEAR_Singleton::isError($holding)) {
//			return new PEAR_Error('Unexpected return from getHolding: ' . $holding);
//		}
//
//		return $holding;
//	}

	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 *
	 * @param string  $username        The patron username
	 * @param string  $password        The patron password
	 * @param User    $parentAccount   A parent account that we are linking from if any
	 * @param boolean $validatedViaSSO True if the patron has already been validated via SSO.  If so we don't need to validation, just retrieve information
	 *
	 * @return User|null     User object or null if the user cannot be logged in
	 * @access public
	 */
	public function patronLogin($username, $password, $parentAccount = null, $validatedViaSSO = false) {
		global $timer;
		global $offlineMode;

		//Get the barcode property
		$barcode = $this->accountProfile->loginConfiguration == 'barcode_pin' ? $username : $password;
		//TODO: some libraries may have barcodes that the space character is valid. So far Aspencat appears to be one. pascal 9/27/2018
		$barcode = trim($barcode);

		if ($offlineMode){
			//The catalog is offline, check the database to see if the user is valid
			$user = new User();
			if ($this->driver->accountProfile->loginConfiguration == 'barcode_pin') {
				$user->cat_username = $barcode;
			}else{
				$user->cat_password = $barcode;
			}
			if ($user->find(true)){
				if ($this->driver->accountProfile->loginConfiguration == 'barcode_pin') {
					//We load the account based on the barcode make sure the pin matches
					$userValid = $user->cat_password == $password;
				}else{
					//We still load based on barcode, make sure the username is similar
					$userValid = $this->areNamesSimilar($username, $user->cat_username);
				}
				if (!$userValid){
					$timer->logTime("offline patron login failed due to invalid name");
					$this->logger->info("offline patron login failed due to invalid name", PEAR_LOG_INFO);
					return null;
				}
			} else {
				$timer->logTime("offline patron login failed because we haven't seen this user before");
				$this->logger->info("offline patron login failed because we haven't seen this user before");
				return null;
			}
		}else {
			if ($this->driver->accountProfile->loginConfiguration == 'barcode_pin') {
				$username = $barcode;
			}else{
				$password = $barcode;
			}
			// TODO: this is called several times after the patron is logged in.
			$user = $this->driver->patronLogin($username, $password, $validatedViaSSO);
		}

		if ($user && !PEAR_Singleton::isError($user)){
			if ($user->displayName == '') {
				if ($user->firstname == ''){
					$user->displayName = $user->lastname;
				}else{
					// #PK-979 Make display name configurable firstname, last initial, vs first initial last name
					/** @var Library $homeLibrary */
					$homeLibrary = $user->getHomeLibrary();
					if ($homeLibrary == null || ($homeLibrary->patronNameDisplayStyle == 'firstinitial_lastname')){
						// #PK-979 Make display name configurable firstname, last initial, vs first initial last name
						$user->displayName = substr($user->firstname, 0, 1) . '. ' . $user->lastname;
					}elseif ($homeLibrary->patronNameDisplayStyle == 'lastinitial_firstname'){
						$user->displayName = $user->firstname . ' ' . substr($user->lastname, 0, 1) . '.';
					}
				}
				$user->update();
			}
			if ($parentAccount) $user->setParentUser($parentAccount); // only set when the parent account is passed.
		}

		return $user;
	}

	/**
	 * The method to call to get the DBObject set up so that the code isn't repeated in multiple place
	 * and doesn't diverge in those additional places
	 * @param User $patron
	 * @return ReadingHistoryEntry
	 */
	private function getReadingHistoryDBObject($patron){
	require_once ROOT_DIR . '/sys/Account/ReadingHistoryEntry.php';
	// Reading History entries with groupedWorkIds

	$readingHistoryDB          = new ReadingHistoryEntry();
	$readingHistoryDB->userId  = $patron->id;
	$readingHistoryDB->deleted = 0; //Only show titles that have not been deleted
	$readingHistoryDB->whereAdd('groupedWorkPermanentId != ""'); // Exclude entries with out a grouped work (typically ILL items)

	$readingHistoryDB->selectAdd();
	$readingHistoryDB->selectAdd('id,groupedWorkPermanentId,source,sourceId,title,author,checkInDate');
	$readingHistoryDB->selectAdd('MAX(checkOutDate) as checkOutDate');
	$readingHistoryDB->selectAdd('GROUP_CONCAT(DISTINCT(format)) as format');
	$readingHistoryDB->groupBy('groupedWorkPermanentId');


	// InterLibrary Loan Reading History entries
	$readingHistoryILL          = new ReadingHistoryEntry();
//	$readingHistoryILL->userId  = $patron->id;
//	$readingHistoryILL->deleted = 0; //Only show titles that have not been deleted

	$readingHistoryILL->selectAdd();
	$readingHistoryILL->selectAdd('id,groupedWorkPermanentId,source,sourceId,title,author,checkInDate');
	$readingHistoryILL->selectAdd('MAX(checkOutDate) as checkOutDate');
	$readingHistoryILL->selectAdd('GROUP_CONCAT(DISTINCT(format)) as format');
	$readingHistoryILL->groupBy('title');
	// Have to add the conditions manually for the dbobject to be UNIONed
	$readingHistoryILL->whereAdd('userId = ' . $patron->id);
	$readingHistoryILL->whereAdd('deleted = 0');
	$readingHistoryILL->whereAdd('groupedWorkPermanentId = ""'); // Include only entries with out a grouped work (typically ILL items

	// Add both entries together with an SQL union
	$readingHistoryDB->unionAdd($readingHistoryILL);
	return $readingHistoryDB;

}

	/**
	 * @param User $user
	 */
	public function updateUserWithAdditionalRuntimeInformation($user){
		global $timer;
		require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
		$overDriveDriver = OverDriveDriverFactory::getDriver();
		if ($user->isValidForOverDrive() && $overDriveDriver->isUserValidForOverDrive($user)){
			$overDriveSummary = $overDriveDriver->getOverDriveSummary($user);
			$user->setNumCheckedOutOverDrive($overDriveSummary['numCheckedOut']);
			$user->setNumHoldsAvailableOverDrive($overDriveSummary['numAvailableHolds']);
			$user->setNumHoldsRequestedOverDrive($overDriveSummary['numUnavailableHolds']);
			$timer->logTime("Updated runtime information from OverDrive");
		}

		if ($user->isValidForHoopla()){
			require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
			$driver          = new HooplaDriver();
			$hooplaSummary   = $driver->getHooplaPatronStatus($user);
			$hooplaCheckOuts = isset($hooplaSummary->currentlyBorrowed) ? $hooplaSummary->currentlyBorrowed : 0;
			$user->setNumCheckedOutHoopla($hooplaCheckOuts);
		}

		if($user->isValidForRBDigital()) {
			$rbDigital = new RBdigital();
			$count = $rbDigital->getCheckoutCount($user);
			$user->setNumCheckedOutRBdigital($count);
		}

		require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequest.php';
		$materialsRequest            = new MaterialsRequest();
		$materialsRequest->createdBy = $user->id;
//		$homeLibrary                 = Library::getLibraryForLocation($user->homeLocationId);
		$homeLibrary                 = $user->getHomeLibrary();
		if ($homeLibrary && $homeLibrary->enableMaterialsRequest){
			require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestStatus.php';
			$statusQuery            = new MaterialsRequestStatus();
			$statusQuery->isOpen    = 1;
			$statusQuery->libraryId = $homeLibrary->libraryId;
			$materialsRequest->joinAdd($statusQuery);
			$materialsRequest->find();
			$user->setNumMaterialsRequests($materialsRequest->N);
			$timer->logTime("Updated number of active materials requests");
		}


		if ($user->trackReadingHistory && $user->initialReadingHistoryLoaded){
			//TODO: we should cache this for a short time, 30 secs, because it shouldn't change between page loads of a session.
			$readingHistoryDB           = $this->getReadingHistoryDBObject($user);
			$readingHistoryDB->find();
			$user->setReadingHistorySize($readingHistoryDB->N);
			$timer->logTime("Updated reading history size");
		}
	}

	/**
	 * @param $nameFromUser  string
	 * @param $nameFromIls   string
	 * @return boolean
	 */
	private function areNamesSimilar($nameFromUser, $nameFromIls) {
		$fullName = str_replace(",", " ", $nameFromIls);
		$fullName = str_replace(";", " ", $fullName);
		$fullName = str_replace(";", "'", $fullName);
		$fullName = preg_replace("/\\s{2,}/", " ", $fullName);
		$allNameComponents = preg_split('^[\s-]^', strtolower($fullName));

		//Get the first name that the user supplies.
		//This expects the user to enter one or two names and only
		//Validates the first name that was entered.
		$enteredNames = preg_split('^[\s-]^', strtolower($nameFromUser));
		$userValid = false;
		foreach ($enteredNames as $name) {
			if (in_array($name, $allNameComponents, false)) {
				$userValid = true;
				break;
			}
		}
		return $userValid;
	}

	/**
	 * Get Patron Transactions
	 *
	 * This is responsible for retrieving all transactions (i.e. checked out items)
	 * by a specific patron.
	 *
	 * @param User $user          The user to load transactions for
	 * @param bool $linkedAccount When using linked accounts for Sierra Encore, the curl connection for linked accounts
	 *                            has to be reset
	 * @return mixed        Array of the patron's transactions on success,
	 *                            PEAR_Error otherwise.
	 * @access public
	 * @throws ErrorException
	 */
	public function getMyCheckouts($user, $linkedAccount = false){
		$transactions = $this->driver->getMyCheckouts($user, $linkedAccount);
		foreach ($transactions as $key => $curTitle){
			$curTitle['user']   = $user->getNameAndLibraryLabel();
			$curTitle['userId'] = $user->id;
			$curTitle['fullId'] = $this->accountProfile->recordSource . ':' . $curTitle['recordId'];

			if ($curTitle['dueDate']){
				// use the same time of day to calculate days until due, in order to avoid errors with rounding
				$dueDate                  = strtotime('midnight', $curTitle['dueDate']);
				$today                    = strtotime('midnight');
				$daysUntilDue             = ceil(($dueDate - $today) / (24 * 60 * 60));
				$overdue                  = $daysUntilDue < 0;
				$curTitle['overdue']      = $overdue;
				$curTitle['daysUntilDue'] = $daysUntilDue;
			}
			//Determine if the record
			$transactions[$key] = $curTitle;
		}
		return $transactions;
	}

	/**
	 * Get Patron Fines
	 *
	 * This is responsible for retrieving all fines by a specific patron.
	 *
	 * @param array $patron            The patron array from patronLogin
	 * @param bool  $includeMessages
	 * @param bool $linkedAccount When using linked accounts for Sierra Encore, the curl connection for
	 *                            linked accounts has to be reset
	 * @return mixed        Array of the patron's fines on success, PEAR_Error otherwise.
	 * @access public
	 */
	public function getMyFines($patron, $includeMessages = false, $linkedAccount = false)
	{
		return $this->driver->getMyFines($patron, $includeMessages, $linkedAccount);
	}

	/**
	 * This method is called by the cron process that populates Pika reading history data.
	 * An implementation of this method should return only the data needed to store a reading
	 * history entry, and not all the info needed to display reading history on a page.
	 * Also the pagination should be implemented with respect to what the ILS allows so that
	 * the cron process can work through as many entries as can be reliably delivered
	 *
	 * @param User $patron
	 * @param boolean|null $loadAdditional
	 * @return array|bool|false
	 * @throws ErrorException
	 */
	function loadReadingHistoryFromIls($patron, $loadAdditional = null){
		if (!empty($patron) && $patron->trackReadingHistory){
				if ($this->driver->hasNativeReadingHistory()){
					if (method_exists($this->driver, 'loadReadingHistoryFromIls')){
						$result = $this->driver->loadReadingHistoryFromIls($patron, $loadAdditional);
						return $result;
					}
					// Fall back
					//TODO: all drivers that fallback to this, need to implement the above method
					// so that Pika can reliably maintain a patron's reading history
					return $this->getReadingHistory($patron);
				}
		}
		return false;
	}

	/**
	 * Get Reading History
	 *
	 * This is responsible for retrieving a history of checked out items for the patron.
	 *
	 * This method is used for display of reading history on the MyAccount/ReadingHistory page,
	 * so each entry returned should contain all the information needed for display (covers, links, ratings
	 * etc.)
	 *
	 * @param   User   $patron     The patron array
	 * @param   int     $page
	 * @param   int     $recordsPerPage
	 * @param   string  $sortOption
	 *
	 * @return  array               Array of the patron's reading list
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut"){
		//Get reading history from the database unless we specifically want to load from the driver.
		if (($patron->trackReadingHistory && $patron->initialReadingHistoryLoaded) || !$this->driver->hasNativeReadingHistory()){
			if ($patron->trackReadingHistory){
				//Make sure initial reading history loaded is set to true if we are here since
				//The only way it wouldn't be here is if the user has elected to start tracking reading history
				//And they don't have reading history currently specified.  We get what is checked out below though
				//So that takes care of the initial load
				if (!$patron->initialReadingHistoryLoaded){
					//Load the initial reading history
					$patron->initialReadingHistoryLoaded = 1;
					$patron->update();
				}
				set_time_limit(180);

				$this->updateReadingHistoryBasedOnCurrentCheckouts($patron);

				$readingHistoryDB = $this->getReadingHistoryDBObject($patron);

				// Get the Total number of reading history entries
				$totalReadingHistoryEntries = $readingHistoryDB->find();

				// Set the order for the query of all entries
				if ($sortOption == "checkedOut"){
					$readingHistoryDB->orderBy('checkOutDate DESC, title ASC');
//				}elseif ($sortOption == "returned"){
//					$readingHistoryDB->orderBy('checkInDate DESC, title ASC');
				}elseif ($sortOption == "title"){
					$readingHistoryDB->orderBy('title ASC, checkOutDate DESC');
				}elseif ($sortOption == "author"){
					$readingHistoryDB->orderBy('author ASC, title ASC, checkOutDate DESC');
				}elseif ($sortOption == "format"){
					$readingHistoryDB->orderBy('format ASC, title ASC, checkOutDate DESC');
				}

				if ($recordsPerPage != -1){
					$startAt = ($page - 1) * $recordsPerPage;
					if ($startAt >= $totalReadingHistoryEntries ){
						//Reset to Last Page
						$startAt = floor($totalReadingHistoryEntries / $recordsPerPage) * $recordsPerPage;
					}
					$readingHistoryDB->limit($startAt, $recordsPerPage);
				}

				$readingHistoryDB->find();

				$readingHistoryTitles = array();
				while ($readingHistoryDB->fetch()){
					$historyEntry           = $this->getHistoryEntryForDatabaseEntry($readingHistoryDB);
					$readingHistoryTitles[] = $historyEntry;
				}

				return array('historyActive' => $patron->trackReadingHistory, 'titles' => $readingHistoryTitles, 'numTitles' => $totalReadingHistoryEntries);
			}else{
				//Reading history disabled
				return array('historyActive' => $patron->trackReadingHistory, 'titles' => array(), 'numTitles' => 0);
			}

		}elseif ($this->driver->hasNativeReadingHistory() && method_exists($this->driver, 'getReadingHistory')){
			//Since ILSes rarely implement fetching of reading history with the pagination *plus* the sorting we would like,
			// this section should be avoided.  Previous implementations would fetch a patron's entire reading history and
			// then resort based on our desired criteria, and then paginate to the desired section.  This method is incredibly
			// slow for a single page load.

			// Get complete reading history from ILS; We can't assume it was sorted as we want by the ILS
			$result = $this->driver->getReadingHistory($patron, $page, -1, $sortOption);

			// keep the rest of this method from throwing errors and warnings.
			if($result['numTitles'] == 0 || $result['historyActive'] == false) {
				return $result;
			}
			//Do not try to mark that the initial load has been done since we only load a subset of the reading history above.

			//Sort the records
			$count = 0;
			foreach ($result['titles'] as $key => $historyEntry){
				$count++;
				if (!isset($historyEntry['title_sort']) && !empty($historyEntry['title'])){
					$historyEntry['title_sort'] = preg_replace('/[^a-z\s]/', '', strtolower($historyEntry['title']));
				}
				switch ($sortOption){
					case "author":
						$titleKey = $historyEntry['author'] . "_" . $historyEntry['title_sort'];
						break;
					case "checkedOut":
						$titleKey = $historyEntry['title_sort']; // Default if there is no checkout time to use
						if (!empty($historyEntry['checkout'])){
							if (is_int($historyEntry['checkout'])){
								// already a timestamp
								$titleKey = $historyEntry['checkout'] . '_' .  $historyEntry['title_sort'];
							}else{
								//Simple date sting, convert to timestamp
								$checkoutTime = DateTime::createFromFormat('m-d-Y', $historyEntry['checkout']);
								if ($checkoutTime){
									$titleKey = $checkoutTime->getTimestamp() . "_" . $historyEntry['title_sort'];
								}
							}
						}
						break;
					case "format":
						$titleKey = $historyEntry['format'] . "_" . $historyEntry['title_sort'];
						break;
					case "title":
					default:
						$titleKey = $historyEntry['title_sort'];
						break;
				}
				$titleKey .= '_' . ($count);
				$result['titles'][$titleKey] = $historyEntry;
				unset($result['titles'][$key]);
			}
			if ($sortOption == "checkedOut" || $sortOption == "returned"){
				krsort($result['titles']);
			}else{
				ksort($result['titles']);
			}

			if ($recordsPerPage != -1 && $count > $recordsPerPage){
				$historyPages = array_chunk($result['titles'], $recordsPerPage, true);
				$pageIndex         = $page - 1;
				$result['titles'] = $historyPages[$pageIndex];
			}

			return $result;
		}
	}

	/**
	 * Do an update or edit of reading history information.  Current actions are:
	 * deleteMarked
	 * deleteAll
	 * exportList
	 * optOut
	 *
	 * @param   User    $patron         The user to do the reading history action on
	 * @param   string  $action         The action to perform
	 * @param   array   $selectedTitles The titles to do the action on if applicable
	 */
//	function doReadingHistoryAction($patron, $action, $selectedTitles = array()){
//		if (($patron->trackReadingHistory && $patron->initialReadingHistoryLoaded) || ! $this->driver->hasNativeReadingHistory()){
//			if ($action == 'deleteMarked'){
//			}elseif ($action == 'deleteAll'){
//
//			}elseif ($action == 'optOut'){
//			}
//		}else{
//			return $this->driver->doReadingHistoryAction($patron, $action, $selectedTitles);
//		}
//		return $result;
//	}

	/**
	 * @param User $patron
	 * @return bool
	 */
	public function optInReadingHistory($patron){
		$driverHasReadingHistory = $this->driver->hasNativeReadingHistory();
		//Opt in within the ILS if possible
		if ($driverHasReadingHistory){
			if (method_exists($this->driver, 'optInReadingHistory')){
				$result = $this->driver->optInReadingHistory($patron);
				if (!$result){
					return false;
				}
			} elseif (method_exists($this->driver, 'doReadingHistoryAction')){
				//Deprecated process
				$this->driver->doReadingHistoryAction($patron, 'optIn');
			}
		}

		//Opt in within Pika since the ILS does not seem to implement this functionality
		$patron->trackReadingHistory = true;
		$result = $patron->update();
		// Other parts may not use strict checking // set to true to pass back up the chain.
		if($result !== false) {
			$result = true;
		}
		return $result;
		//return $result !== false;  // The update can return 0 for no rows affected
	}

	/**
	 * @param User $patron
	 * @return bool
	 */
	public function optOutReadingHistory($patron){
		$success                 = true;
		$driverHasReadingHistory = $this->driver->hasNativeReadingHistory();

		//Opt out within the ILS if possible
		if ($driverHasReadingHistory){

			//First run delete all
			if (method_exists($this->driver, 'deleteAllReadingHistory')){
				$result = $this->driver->deleteAllReadingHistory($patron);
				if (!$result){
					$success = false;
					$this->logger->warn('Failed to delete all reading history in ILS for an opt out for user ' . $patron->id);
				}
			}elseif (method_exists($this->driver, 'doReadingHistoryAction')){
				//Deprecated process
				$this->driver->doReadingHistoryAction($patron, 'deleteAll');
			}

			// Now opt of reading history in the ILS
			if (method_exists($this->driver, 'optOutReadingHistory')){
				$result = $this->driver->optOutReadingHistory($patron);
				if (!$result){
					$success = false;
					$this->logger->warn('Failed to opt out of reading history in ILS for user ' . $patron->id);
				}
			} elseif (method_exists($this->driver, 'doReadingHistoryAction')){
				//Deprecated process
				$this->driver->doReadingHistoryAction($patron, 'optOut');
			}
		}

			//Delete the reading history (permanently this time since we are opting out)
			$readingHistoryDB         = new ReadingHistoryEntry();
			$readingHistoryDB->userId = $patron->id;
			$result                   = $readingHistoryDB->delete();
			if ($result !== false){  // The delete can return 0 for no rows affected
				$success = false;
				$this->logger->warn('Failed to delete all reading history entries in Pika for user ' . $patron->id);
			}

			//Opt out within Pika since the ILS does not seem to implement this functionality
			$patron->trackReadingHistory         = false;
			$patron->initialReadingHistoryLoaded = false;
			$result                              = $patron->update();
			if ($result !== false){  // The update can return 0 for no rows affected
				$success = false;
			}

			return $success;
	}

	/**
	 * @param $patron
	 * @return boolean
	 */
	public function deleteAllReadingHistory($patron){
		if (is_a($patron, 'User') && !empty($patron->id)){
			//Remove all titles from database
			$success                  = true;
			$readingHistoryDB         = new ReadingHistoryEntry();
			$readingHistoryDB->userId = $patron->id;
			$readingHistoryDB->find();
			while ($readingHistoryDB->fetch()){
				// Mark as deleted instead of deleting
				$readingHistoryDB->deleted = 1;
				$result                    = $readingHistoryDB->update();
				if ($success){
					// Set to false if any updates fail; stop checking after the first failure
					if (!$success = $result != false){
						$this->logger->warn('Failed to delete all reading history entries for user id ' . $patron->id . ', starting with history entry ' . $readingHistoryDB->id);
					}
				}
			}
			//TODO: discuss. delete all reading history in ILS only on opt out (Apparent previous behavior)
//			if ($success){
//				$driverHasReadingHistory = $this->driver->hasNativeReadingHistory();
//				if ($driverHasReadingHistory){
//					if (method_exists($this->driver, 'deleteAllReadingHistory')){
//						return $this->driver->deleteAllReadingHistory($patron);
//					}elseif (method_exists($this->driver, 'doReadingHistoryAction')){
//						//Deprecated process
//						$this->driver->doReadingHistoryAction($patron, 'deleteAll');
//					}
//				}
//			}
			return $success;
		}
		return false;
	}

		/**
		 * @param User $patron
		 * @param array $selectedTitles
		 * @return boolean
		 */
	public function deleteMarkedReadingHistory($patron, $selectedTitles){
	//Remove titles from database (do not remove from ILS)
		if (is_a($patron, 'User') && !empty($patron->id)){
			$success = true;
			foreach ($selectedTitles as $groupedWorkId => $titleId){
				if (!empty($groupedWorkId)){
					// Reading History entries tied to a grouped work
					$readingHistoryDB                         = new ReadingHistoryEntry();
					$readingHistoryDB->userId                 = $patron->id;
					$readingHistoryDB->groupedWorkPermanentId = strtolower($groupedWorkId);
					$readingHistoryDB->find();
					if ($readingHistoryDB->N > 0){
						while ($readingHistoryDB->fetch()){
							$readingHistoryDB->deleted = 1;
							$result                    = $readingHistoryDB->update();
							if ($success){
								// Set to false if any updates fail; stop checking after the first failure
								$success = $result != false;
								$this->logger->warn('Failed to delete selected reading history entry for user id ' . $patron->id);
							}
						}
					}
				}else{
					// Reading history entries that aren't tied to a grouped work, like inter-library loan titles
					$readingHistoryDB         = new ReadingHistoryEntry();
					$readingHistoryDB->userId = $patron->id;
					$readingHistoryDB->id     = str_replace('rsh', '', $titleId); // reading history ids are prefixes with rsh in the template
					if ($readingHistoryDB->find(true)){
						$readingHistoryDB->deleted = 1;
						$result                    = $readingHistoryDB->update();
						if ($success){
							// Set to false if any updates fail; stop checking after the first failure
							$success = $result != false;
							$this->logger->warn('Failed to delete selected reading history entry for user id ' . $patron->id);
						}
					}
				}
			}
			return $success;
		}
		return false;
	}

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $user           The user to load transactions for
	 * @param bool $linkedAccount  When using linked accounts for Sierra Encore, the curl connection for linked accounts has to be reset
	 * @return array               Array of the patron's holds
	 * @access public
	 */
	public function getMyHolds($user, $linkedAccount = false) {
		$holds = $this->driver->getMyHolds($user, $linkedAccount);
		foreach ($holds as $section => $holdsForSection){
			foreach ($holdsForSection as $key => $curTitle){
				$curTitle['user']             = $user->getNameAndLibraryLabel();
				$curTitle['userId']           = $user->id;
				$curTitle['allowFreezeHolds'] = $user->getHomeLibrary()->allowFreezeHolds;
				if (!isset($curTitle['sortTitle']) && !empty($curTitle['title'])){
					$curTitle['sortTitle'] = $curTitle['title'];
				}
				$holds[$section][$key] = $curTitle;
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
	 * @param   null|string $cancelDate  The date to cancel the hold if it isn't fulfilled
	 * @return  mixed                    True if successful, false if unsuccessful
	 *                                   If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null) {
		return $this->driver->placeHold($patron, $recordId, $pickupBranch, $cancelDate);
	}

	/**
	* Place Item Hold
	*
	* This is responsible for placing item level holds.
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
		return $this->driver->placeItemHold($patron, $recordId, $itemId, $pickupBranch, $cancelDate);
	}

	function updatePatronInfo($user, $canUpdateContactInfo){
		try {
			$errors = $this->driver->updatePatronInfo($user, $canUpdateContactInfo);
		} catch (ErrorException $e) {
			$this->logger->error($e->getMessage(), ['stack_trace' => $e->getTraceAsString()]);
		}
		return $errors;
	}

	// TODO Sierra only at this time, set other drivers to return false.
	function bookMaterial(User $patron, SourceAndId $recordId, $startDate, $startTime = null, $endDate = null, $endTime = null){
		return $this->driver->bookMaterial($patron, $recordId, $startDate, $startTime, $endDate, $endTime);
	}

	// TODO Sierra only at this time, set other drivers to return false.
	function cancelBookedMaterial($patron, $cancelIds){
		return $this->driver->cancelBookedMaterial($patron, $cancelIds);
	}

	// TODO Sierra only at this time, set other drivers to return false.
	function cancelAllBookedMaterial($patron){
		return $this->driver->cancelAllBookedMaterial($patron);
	}

	/**
	 * @param User $patron
	 * @return array
	 */
	function getMyBookings($patron){
		$bookings = $this->driver->getMyBookings($patron);
		return $bookings;
	}

	function selfRegister(){
		return $this->driver->selfRegister();
	}

	/**
	 * Default method -- pass along calls to the driver if available; return
	 * false otherwise.  This allows custom functions to be implemented in
	 * the driver without constant modification to the connection class.
	 *
	 * @param string $methodName The name of the called method.
	 * @param array  $params     Array of passed parameters.
	 *
	 * @return mixed             Varies by method (false if undefined method)
	 * @access public
	 */
	public function __call($methodName, $params)
	{
		$method = array($this->driver, $methodName);
		if (is_callable($method)) {
			return call_user_func_array($method, $params);
		}
		return false;
	}

	public function getSelfRegistrationFields() {
		return $this->driver->getSelfRegistrationFields();
	}

	/**
	 * @param ReadingHistoryEntry $readingHistoryDB
	 * @return mixed
	 */
	public function getHistoryEntryForDatabaseEntry(ReadingHistoryEntry $readingHistoryDB) {
		$historyEntry = array();

		$historyEntry['itemindex']   = $readingHistoryDB->id;
		$historyEntry['deletable']   = true;
		$historyEntry['source']      = $readingHistoryDB->source;
		$historyEntry['recordId']    = $readingHistoryDB->sourceId;
		$historyEntry['title']       = $readingHistoryDB->title;
		$historyEntry['author']      = $readingHistoryDB->author;
		$historyEntry['format']      = $readingHistoryDB->format;
		$historyEntry['checkout']    = $readingHistoryDB->checkOutDate;
		$historyEntry['checkin']     = $readingHistoryDB->checkInDate;
		$historyEntry['ratingData']  = null;
		$historyEntry['permanentId'] = null;
		$historyEntry['linkUrl']     = null;
		$historyEntry['coverUrl']    = null;
		$recordDriver                = null;
		if (!empty($readingHistoryDB->groupedWorkPermanentId)){
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			$recordDriver = new GroupedWorkDriver($readingHistoryDB->groupedWorkPermanentId);
			if (!empty($recordDriver) && $recordDriver->isValid()){
				$historyEntry['ratingData']  = $recordDriver->getRatingData();
				$historyEntry['permanentId'] = $recordDriver->getPermanentId();
				$historyEntry['coverUrl']    = $recordDriver->getBookcoverUrl('medium');
				$historyEntry['linkUrl']     = $recordDriver->getLinkUrl();
				if (empty($historyEntry['title'])){
					$historyEntry['title'] = $recordDriver->getTitle();
				}
			}
		}
		elseif (!empty($readingHistoryDB->source) && !empty($readingHistoryDB->sourceId)){
			require_once ROOT_DIR . '/services/SourceAndId.php';
			$sourceAndID = new sourceAndId($readingHistoryDB->source . ':' . $readingHistoryDB->sourceId);
			$recordDriver = RecordDriverFactory::initRecordDriverById($sourceAndID);
			if (!empty($recordDriver) && $recordDriver->isValid()){
				$historyEntry['ratingData']  = $recordDriver->getRatingData();
				$historyEntry['coverUrl']    = $recordDriver->getBookcoverUrl('medium');
				$historyEntry['linkUrl']     = $recordDriver->getLinkUrl();
				$historyEntry['permanentId'] = $recordDriver->getPermanentId();
				if (empty($historyEntry['title'])){
					$historyEntry['title'] = $recordDriver->getTitle();
				}
			}
			//TODO: update history db entry with any missing information?

		}
		$recordDriver = null;
		return $historyEntry;
	}

	/**
	 * @param User $patron
	 */
	private function updateReadingHistoryBasedOnCurrentCheckouts($patron) {
		require_once ROOT_DIR . '/sys/Account/ReadingHistoryEntry.php';
		//Note, include deleted titles here so they are not added multiple times.
		$readingHistoryDB         = new ReadingHistoryEntry();
		$readingHistoryDB->userId = $patron->id;
		$readingHistoryDB->whereAdd('checkInDate IS NULL');
		$readingHistoryDB->find();

		$activeHistoryTitles = array();
		while ($readingHistoryDB->fetch()){
			if ($readingHistoryDB->source == 'ILS'){
				$readingHistoryDB->source = 'ils';
			}
			$key                       = $readingHistoryDB->source. ':' .$readingHistoryDB->sourceId; //TODO: what about an ILL check out
//			$historyEntry              = $this->getHistoryEntryForDatabaseEntry($readingHistoryDB);
//			$activeHistoryTitles[$key] = $historyEntry;
			//The getHistoryEntryForDatabaseEntry() fetches additional information that we don't actually use.
			$activeHistoryTitles[$key] = [
				'source'   => $readingHistoryDB->source,
				'recordId' => $readingHistoryDB->sourceId,
			];
		}

		//Update reading history based on current checkouts.  That way it never looks out of date
		$checkouts = $patron->getMyCheckouts(false);
		foreach ($checkouts as $checkout){
//			$sourceId = '?';
			$source   = $checkout['checkoutSource'];
			switch ($source){
				case 'OverDrive':
				$sourceId = $checkout['overDriveId'];
					break;
				case 'Hoopla':
				$sourceId = $checkout['hooplaId'];
					break;
				case 'eContent':
					$source   = $checkout['recordType'];
					$sourceId = $checkout['id'];
					break;
				default:
				case 'ILS':
					$source = 'ils'; // make all ILS sources lower case too
				case 'ils':
					$sourceId = $checkout['recordId'];
					break;
			}

			$key = $source . ':' . $sourceId;

			//TODO: case where $key is ':' or 'ils:' for ILL checkouts (At this point more than one ILL checkout will end up as one entry)
			if (array_key_exists($key, $activeHistoryTitles)){
				$activeHistoryTitles[$key]['stillActiveCheckout'] = true;
				// can't merely unset the entry because it is possible for the user to have more than one item from the same bib
				// checked out (eg 2 copies of a title), and we don't want to duplicate entries in reading history when only
				// bib-level data is recorded
			}else{
				// A new checkout that *isn't* is the users reading history yet; so we will add it to the reading history
				$historyEntryDB         = new ReadingHistoryEntry();
				$historyEntryDB->userId = $patron->id;
				if (isset($checkout['groupedWorkId'])){
					$historyEntryDB->groupedWorkPermanentId = $checkout['groupedWorkId'] == null ? '' : $checkout['groupedWorkId'];
				}else{
					$historyEntryDB->groupedWorkPermanentId = "";
				}

				$historyEntryDB->source       = $source;
				$historyEntryDB->sourceId     = $sourceId;
				if (!empty($checkout['title'])){
					$historyEntryDB->title = substr($checkout['title'], 0, 150);
				}
				if (!empty($checkout['author'])){
					$historyEntryDB->author = substr($checkout['author'], 0, 75);
				}
				if (!empty($checkout['format'])){
					$historyEntryDB->format = substr($checkout['format'], 0, 50);
				}
				$historyEntryDB->checkOutDate = time();
				if (!$historyEntryDB->insert()){
					$this->logger->warn("Could not insert new reading history entry");
				}
			}
		}

		// Active reading histories that were checked out but aren't checked out anymore
		foreach ($activeHistoryTitles as $key => $historyEntry){
			if (empty($historyEntry['stillActiveCheckout'])){ //No longer an active checkout
				//Update even if deleted to make sure code is cleaned up correctly
				$historyEntryDB              = new ReadingHistoryEntry();
				$historyEntryDB->source      = $historyEntry['source'];
				$historyEntryDB->sourceId    = $historyEntry['recordId'];
				$historyEntryDB->checkInDate = null;
				if ($historyEntryDB->find(true)){
					$historyEntryDB->checkInDate = time();
					$numUpdates                  = $historyEntryDB->update();
					if ($numUpdates != 1){
						$this->logger->warn("Could not update reading history entry $key");
					}
				}
			}
		}
	}

	/**
	 * Return the number of holds that are on a record
	 * @param $id
	 * @return int
	 */
	public function getNumHoldsFromRecord($id) {
		// these all need to live with the owning object.
		/** @var Memcache $memCache */
		global $memCache, $configArray;
		$key = 'num_holds_' . $id ;
		$cachedValue = $this->cache->get($key);
		if ($cachedValue == false || isset($_REQUEST['reload'])){
			$cachedValue = $this->driver->getNumHoldsOnRecord($id);
			$this->cache->set($key, $cachedValue, $configArray['Caching']['item_data']);
		}

		return $cachedValue;
	}

	function cancelHold($patron, $recordId, $cancelId) {
		return $this->driver->cancelHold($patron, $recordId, $cancelId);
	}

	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate) {
		return $this->driver->freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate);
	}

	function thawHold($patron, $recordId, $itemToThawId) {
		return $this->driver->thawHold($patron, $recordId, $itemToThawId);
	}

	function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation) {
		return $this->driver->changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation);
	}

	public function getBookingCalendar(User $patron, SourceAndId $sourceAndId) {
		// Graceful degradation -- return null if method not supported by driver.
		return method_exists($this->driver, 'getBookingCalendar') ?
			$this->driver->getBookingCalendar($patron, $sourceAndId) : null;
	}

	public function renewItem($patron, $recordId, $itemId, $itemIndex){
		return $this->driver->renewItem($patron, $recordId, $itemId, $itemIndex);
	}

	public function renewAll($patron){
		if ($this->driver->hasFastRenewAll()){
			return $this->driver->renewAll($patron);
		}else{
			//Get all list of all transactions
			$currentTransactions = $this->driver->getMyCheckouts($patron);
			$renewResult = array(
				'success' => true,
				'message' => array(),
				'Renewed' => 0,
				'Unrenewed' => 0
			);
			$renewResult['Total'] = count($currentTransactions);
			$numRenewals = 0;
			$failure_messages = array();
			foreach ($currentTransactions as $transaction){
				if ((isset($transaction['canrenew']) && $transaction['canrenew'] == true) || !isset($transaction['canrenew'])) {
					// If we are calculating canrew, make a renewall attempt.  If we are though, don't make an attempt if canrenew is false
					$curResult = $this->renewItem($patron, $transaction['recordId'], $transaction['renewIndicator'], null);
					if ($curResult['success']){
						$numRenewals++;
					} else {
						$failure_messages[] = $curResult['message'];
					}
				} else {
					$failure_messages[] = '"' . $transaction['title'] . '" can not be renewed';
				}
			}
			$renewResult['Renewed'] += $numRenewals;
			$renewResult['Unrenewed'] = $renewResult['Total'] - $renewResult['Renewed'];
			if ($renewResult['Unrenewed'] > 0) {
				$renewResult['success'] = false;
				$renewResult['message'] = $failure_messages;
			}else{
				$renewResult['message'][] = "All items were renewed successfully.";
			}
			return $renewResult;
		}
	}

	public function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch, $cancelDate = null) {
		if ($this->checkFunction('placeVolumeHold')){
			return $this->driver->placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch, $cancelDate);
		}else{
			return array(
					'success' => false,
					'message' => 'Volume level holds have not been implemented for this ILS.');
		}
	}

	public function importListsFromIls($patron){
		if ($this->checkFunction('importListsFromIls')){
			return $this->driver->importListsFromIls($patron);
		}else{
			return array(
					'success' => false,
					'errors' => array('Importing Lists has not been implemented for this ILS.')
			);
		}
	}

	public function getShowUsernameField() {
		if ($this->checkFunction('hasUsernameField')) {
			return $this->driver->hasUsernameField();
		}else{
			return false;
		}
	}
}
