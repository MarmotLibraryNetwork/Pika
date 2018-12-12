<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/19/2018
 *
 */


require_once ROOT_DIR . '/sys/KohaSIP.php';
require_once ROOT_DIR . '/Drivers/SIP2Driver.php';
abstract class ByWaterKoha extends SIP2Driver {

	/** @var  AccountProfile $accountProfile */
	public $accountProfile;

	/**
	 * @param AccountProfile $accountProfile
	 */
	public function __construct($accountProfile){
		global $configArray;
		$this->accountProfile = $accountProfile;
		$this->sipHost        = $configArray['SIP2']['host'];
	}

	public function patronLogin($username, $password, $validatedViaSSO)
	{
		$useSip = 1;
		if ($useSip) {
			$result = $this->patronLoginViaSip($username, $password);
		}
		//TODO: use database login as preference: look as Aspencat.php
	}


	protected function patronLoginViaSip($username, $password) {
		//TODO:
		// Just Verify credentials against SIP
//		$this->initSipConnection();
	}

	public function hasNativeReadingHistory()
	{
		return false;
	}

	/**
	 * Return the number of holds that are on a record
	 * @param $id
	 * @return int
	 */
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
		// TODO: Implement getMyCheckouts() method.
		$checkedOutTitles = array();

		if ($this->initSipConnection()) {
			$this->sipConnection->patron    = $patron->cat_username;
			$this->sipConnection->patronpwd = $patron->cat_password;
			$sip_result = $this->getPatronInfo('charged');
			if ($sip_result) {
				//Field AU doesn't contain any information

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
		$renew_result = array(
			'success' => false,
			'message' => array(),
			'Renewed' => 0,
			'Unrenewed' => $patron->numCheckedOutIls,
			'Total' => $patron->numCheckedOutIls
		);

		if ($this->initSipConnection()) {
			$this->sipConnection->patron    = $patron->cat_username;
			$this->sipConnection->patronpwd = $patron->cat_password;

			$in         = $this->sipConnection->msgRenewAll();
			$msg_result = $this->sipConnection->get_message($in);
			if (preg_match("/^66/", $msg_result)) {
				$result = $this->sipConnection->parseRenewAllResponse($msg_result);

				$renew_result['success'] = ($result['fixed']['Ok'] == 1);
				$renew_result['Renewed'] = ltrim($result['fixed']['Renewed'], '0');
				if (strlen($renew_result['Renewed']) == 0){
					$renew_result['Renewed'] = 0;
				}

				$renew_result['Unrenewed'] = ltrim($result['fixed']['Unrenewed'], '0');
				if (strlen($renew_result['Unrenewed']) == 0){
					$renew_result['Unrenewed'] = 0;
				}
				if (isset($result['variable']['AF'])){
					$renew_result['message'][] = $result['variable']['AF'][0];
				}

				if ($renew_result['Unrenewed'] > 0){
					$renew_result['message'] = array_merge($renew_result['message'], $result['variable']['BN']);
				}
			}
		}
		return $renew_result;
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
		$success = false;
		$message = "Could not connect to circulation server, please try again later.";

		if ($this->initSipConnection()) {
			$this->sipConnection->patron    = $patron->cat_username;
			$this->sipConnection->patronpwd = $patron->cat_password;

			$in         = $this->sipConnection->msgRenew($itemId, $recordId);
			$msg_result = $this->sipConnection->get_message($in);
			if (preg_match("/^30/", $msg_result)) {
				$result  = $this->sipConnection->parseRenewResponse($msg_result);
				$success = ($result['fixed']['Ok'] == 1);
				$message = $result['variable']['AF'][0];

			}

		}
		return array(
			'itemId'  => $itemId,
			'success' => $success,
			'message' => $message
		);

	}

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $user The user to load transactions for
	 *
	 * @return array        Array of the patron's holds
	 * @access public
	 */
	public function getMyHolds($user)
	{
		// TODO: Implement getMyHolds() method.
		$availableHolds   = array();
		$unavailableHolds = array();
		$holds            = array(
			'available'   => $availableHolds,
			'unavailable' => $unavailableHolds
		);


		return $holds;
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
	public function placeHold($patron, $recordId, $pickupBranch, $cancelIfNotFilledByDate = null)
	{
		$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch, $cancelIfNotFilledByDate);
		return $result;
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param   User $patron The User to place a hold for
	 * @param   string $recordId The id of the bib record
	 * @param   string $itemId The id of the item to hold
	 * @param   string $pickupBranch The branch where the user wants to pickup the item when available
	 * @param   null|string $cancelIfNotFilledByDate The date to cancel the Hold if it isn't filled
	 * @return  array                 An array with the following keys
	 *                                success - true/false
	 *                                message - the message to display
	 *                                title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	function placeItemHold($patron, $recordId, $itemId, $pickupBranch, $cancelIfNotFilledByDate = null)
	{
		$hold_result = array(
			'success' => false,
			'message' => 'Your hold could not be placed. '
		);
		if ($this->initSipConnection()) {
			$title   = null;
			$success = false;
			$message = 'Unkown error occurred communicating with the circulation system';

			$this->sipConnection->patron    = $patron->cat_username;
			$this->sipConnection->patronpwd = $patron->cat_password;

			// Determine a pickup location
			if (empty($pickupBranch)){
				//Get the code for the location
				$locationLookup = new Location();
				$locationLookup->get('locationId', $patron->homeLocationId);
				if ($locationLookup->get('locationId', $patron->homeLocationId)){
					$pickupBranch = strtoupper($locationLookup->code);
				}
			}else{
				$pickupBranch = strtoupper($pickupBranch);
			}

			// Determine hold expiration time
			if (!empty($cancelIfNotFilledByDate)) {
//				$timestamp = strtotime($cancelIfNotFilledByDate);
				$dateObject = date_create_from_format('m/d/Y', $cancelIfNotFilledByDate);
				$expirationTime = $dateObject->getTimestamp();

			} else {
				//TODO: Set default here? Can we do SIP call with out cancel time (yes, by SIP2 doc)
				$expirationTime = ''; // has to be empty strin to be handled well by the SIP2 class
			}

			//TODO: for item level holds, do we have to change the hold type? (probably to 3)

			$in         = $this->sipConnection->msgHold('+', $expirationTime, '2', $itemId, $recordId, null, $pickupBranch);
			$msg_result = $this->sipConnection->get_message($in);
			if (preg_match("/^16/", $msg_result)) {
				$result  = $this->sipConnection->parseHoldResponse($msg_result);
				$success = ($result['fixed']['Ok'] == 1);
				$message = $result['variable']['AF'][0];
				if (!empty($result['variable']['AJ'][0])) {
					$title = $result['variable']['AJ'][0];
				}
			}
			$hold_result = array(
				'title'   => $title,
				'bib'     => $recordId,
				'success' => $success,
				'message' => $message
			);
		}
		return $hold_result;
	}

	/**
	 * Cancels a hold for a patron
	 *
	 * @param   User $patron The User to cancel the hold for
	 * @param   string $recordId The id of the bib record
	 * @param   string $cancelId Information about the hold to be cancelled
	 * @return  array
	 */
	function cancelHold($patron, $recordId, $cancelId)
	{
		// TODO: Implement cancelHold() method.
	}

	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate)
	{
		// TODO: Implement freezeHold() method.
	}

	function thawHold($patron, $recordId, $itemToThawId)
	{
		// TODO: Implement thawHold() method.
	}

	function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation)
	{
		// TODO: Implement changeHoldPickupLocation() method.
	}

	/**
	 * Get Patron Fines
	 *
	 * This is responsible for retrieving all fines by a specific patron.
	 *
	 * @param User $patron
	 * @param bool $includeMessages
	 * @return mixed        Array of the patron's fines on success, PEAR_Error
	 * otherwise.
	 * @access public
	 */
	public function getMyFines($patron, $includeMessages = false)
	{
		// TODO: Implement getMyFines() method.
		$fines = array();
		if ($this->initSipConnection()) {
			$this->sipConnection->patron    = $patron->cat_username;
			$this->sipConnection->patronpwd = $patron->cat_password;
			$sip_result = $this->getPatronInfo('fine');
			if ($sip_result) {
				if (!empty($sip_result['variable']['AV'])) {
					foreach ($sip_result['variable']['AV'] as $sip_fine) {
						$fineAmount = trim(strstr($sip_fine, ' '));
						$fines[] = array(
							'reason'            => null,
							'amount'            => $fineAmount,
							'message'           => null,
//							'amountOutstanding' => $fineAmount,
							'date'              => null,
						);

					}
				}

			}
		}
		return $fines;
	}


	private $dbConnection = null;

//	function initDatabaseConnection(){
//		if ($this->dbConnection == null){
//			global $configArray;
//
//			try {
//				$databaseSourceName = $configArray['Catalog']['db_host'] . ';dbname=' . $configArray['Catalog']['db_name'];
//				$this->dbConnection = new PDO($databaseSourceName, $configArray['Catalog']['db_user'], $configArray['Catalog']['db_pwd']);
//			} catch (PDOException $e) {
//				$this->dbConnection = null;
//				global $logger;
//				$logger->log("Error connecting to Bywater Koha database " . $e->getMessage(), PEAR_LOG_ERR);
//			}
//			global $timer;
//			$timer->logTime("Initialized connection to Koha");
//		}
//	}

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
	}

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds by a specific patron.
	 *
	 * @param array|User $patron      The patron array from patronLogin
	 * @param integer $page           The current page of holds
	 * @param integer $recordsPerPage The number of records to show per page
	 * @param string $sortOption      How the records should be sorted
	 *
	 * @return mixed        Array of the patron's holds on success, PEAR_Error
	 * otherwise.
	 * @access public
	 */
	public function getMyHoldsFromDB($patron, $page = 1, $recordsPerPage = -1, $sortOption = 'title'){
		$availableHolds   = array();
		$unavailableHolds = array();
		$holds = array(
			'available'   => $availableHolds,
			'unavailable' => $unavailableHolds
		);

		$this->initDatabaseConnection();

		$sql    = "SELECT *, title, author FROM reserves inner join biblio on biblio.biblionumber = reserves.biblionumber where borrowernumber = {$patron->username}";
		$results = mysqli_query($this->dbConnection, $sql);
		while ($curRow = $results->fetch_assoc()){
			//Each row in the table represents a hold
			$bibId          = $curRow['biblionumber'];
			$expireDate     = $curRow['expirationdate'];
			$createDate     = $curRow['reservedate'];
			$fillByDate     = $curRow['cancellationdate']; //TODO: Is this the cancellation date or is 'waitingdate'
			$reactivateDate = $curRow['suspend_until'];
			$branchcode     = $curRow['branchcode'];

			$curHold                          = array();
			$curHold['id']                    = $bibId;
			$curHold['recordId']              = $bibId;
			$curHold['shortId']               = $bibId;
			$curHold['holdSource']            = 'ILS';
			$curHold['itemId']                = $curRow['itemnumber']; //TODO: verify this is really an item id (by db documentation, I'm pretty sure)
			$curHold['title']                 = $curRow['title'];
			$curHold['author']                = $curRow['author'];
			$curHold['create']                = strtotime($createDate);
			$curHold['expire']                = strtotime($expireDate);
			$curHold['automaticCancellation'] = strtotime($fillByDate);
			$curHold['reactivate']            = $reactivateDate;
			$curHold['reactivateTime']        = strtotime($reactivateDate);

			$curHold['location']           = $branchcode;
			$curHold['currentPickupName']  = $branchcode;
			$curHold['position']           = $curRow['priority'];
			$curHold['cancelId']           = $curRow['reservenumber'];
			$curHold['cancelable']         = true;
			$curHold['locationUpdateable'] = false;
			$curHold['frozen']             = false;
			$curHold['freezeable']         = false;

			switch ($curRow['found']) {
				case 'S':
					$curHold['status']      = "Suspended";
					$curHold['frozen']      = true;
					$curHold['cancelable']  = false;
					break;
				case 'W':
					$curHold['status']     = "Ready to Pickup";
					break;
				case 'T':
					$curHold['status']      = "In Transit";
					break;
				default:
					$curHold['status']     = "Pending";
					$curHold['freezeable'] = true;
					break;
			}

			if ($bibId){
				require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
				$recordDriver = new MarcRecord($bibId);
				if ($recordDriver->isValid()){
					$curHold['title']           = $recordDriver->getTitle();
					$curHold['author']          = $recordDriver->getPrimaryAuthor();
					$curHold['sortTitle']       = $recordDriver->getSortableTitle();
					$curHold['format']          = $recordDriver->getFormat();
					$curHold['isbn']            = $recordDriver->getCleanISBN();
					$curHold['upc']             = $recordDriver->getCleanUPC();
					$curHold['format_category'] = $recordDriver->getFormatCategory();
					$curHold['coverUrl']        = $recordDriver->getBookcoverUrl('medium');
					$curHold['link']            = $recordDriver->getRecordUrl();
					$curHold['ratingData']      = $recordDriver->getRatingData(); //Load rating information
				}
			}

			$curPickupBranch       = new Location();
			$curPickupBranch->code = $branchcode;
			if ($curPickupBranch->find(true)) {
				$curPickupBranch->fetch();
				$curHold['currentPickupId']   = $curPickupBranch->locationId;
				$curHold['currentPickupName'] = $curPickupBranch->displayName;
				$curHold['location']          = $curPickupBranch->displayName;
			}

			if (preg_match('/^Ready to Pickup.*/i', $curHold['status'])){
				$holds['available'][]   = $curHold;
			}else{
				$holds['unavailable'][] = $curHold;
			}
		}

		return $holds;
	}


//	protected function initSipConnection() {
//		if ($this->sipConnection == null){
//			global $configArray;
//			$host = $configArray['SIP2']['host'];
//			$post = $configArray['SIP2']['port'];
//			require_once ROOT_DIR . '/sys/KohaSIP.php';
//			$this->sipConnection           = new KohaSIP();
//			$this->sipConnection->hostname = $host;
//			$this->sipConnection->port     = $post;
//			if ($this->sipConnection->connect()) {
//				//send self-check status message
//				$in         = $this->sipConnection->msgSCStatus();
//				$msg_result = $this->sipConnection->get_message($in);
//
//				// Make sure the response is 98 as expected
//				if (preg_match("/^98/", $msg_result)) {
//					$result = $this->sipConnection->parseACSStatusResponse($msg_result);
//
//					//  Use result to populate SIP2 settings
//					$this->sipConnection->AO = $result['variable']['AO'][0]; /* set AO to value returned */
//					if (isset($result['variable']['AN'])){
//						$this->sipConnection->AN = $result['variable']['AN'][0]; /* set AN to value returned */
//					}
//					return true;
//				}
//				$this->sipConnection->disconnect();
//			}
//			$this->sipConnection = null;
//			return false;
//		}else{
//			return true;
//		}
//	}

	function __destruct(){
		//Cleanup any connections we have to other systems
		if ($this->sipConnection != null){
			$this->sipConnection->disconnect();
			$this->sipConnection = null;
		}

		if ($this->dbConnection != null){
			if ($this->getNumHoldsStmt != null){
				$this->getNumHoldsStmt->close();
			}
			mysqli_close($this->dbConnection);
		}

	}


}