<?php
/**
 * ByWaterKoha ILS-DI + SIP driver.
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/19/2018
 *
 */

require_once ROOT_DIR . '/sys/KohaSIP.php';
#require_once ROOT_DIR . '/Drivers/SIP2Driver.php';
require_once ROOT_DIR . '/Drivers/KohaILSDI.php';

abstract class ByWaterKoha extends KohaILSDI {

	/** @var  AccountProfile $accountProfile */
	public $accountProfile;
//	/**
//	 * @var $dbConnection null
//	 */
//	protected $dbConnection = null;

	/**
	 * @var KohaSIP $sipConnection
	 */
	protected $sipConnection = null;

	/**
	 * @param AccountProfile $accountProfile
	 */
	public function __construct($accountProfile){
		global $configArray;
		$this->accountProfile = $accountProfile;
		$this->sipHost        = $configArray['SIP2']['host'];
		$this->sipPort        = $configArray['SIP2']['port'];
		$this->debug          = isset($configArray['System']['debug'])        ? $configArray['System']['debug'] : false;
	}

	protected function initSipConnection($host = null, $port = null){
		$this->sipConnection           = new KohaSIP();
		$this->sipConnection->hostname = $this->sipHost;
		$this->sipConnection->port     = $this->sipPort;
		$this->sipConnection->debug    = $this->debug;
		if ($this->sipConnection->connect()){
			$in         = $this->sipConnection->msgSCStatus();
			$msg_result = $this->sipConnection->get_message($in);

			// Make sure the response is 98 as expected
			if (preg_match("/^98/", $msg_result)){
				$result = $this->sipConnection->parseACSStatusResponse($msg_result);

				//  Use result to populate SIP2 setings
				$this->sipConnection->AO = $result['variable']['AO'][0]; /* set AO to value returned */
				if (!empty($result['variable']['AN'])){
					$this->sipConnection->AN = $result['variable']['AN'][0]; /* set AN to value returned */
				}
				return true;
			}
		}
		$this->sipConnection->disconnect();
		return false;
	}


	/**
	 * @param string $username
	 * @param string $password
	 * @param $validatedViaSSO
	 * @return array|void|null
	 */
//	public function patronLogin($username, $password, $validatedViaSSO)
//	{
//		$useSip = 1;
//		if ($useSip) {
//			$result = $this->patronLoginViaSip($username, $password);
//			return $result;
//		} else {
//			//TODO: use database login as preference: look as Aspencat.php
//		}
//
//	}


	/**
	 * @param $username
	 * @param $password
	 */
	protected function patronLoginViaSip($username, $password) {
		$this->initSipConnection();
	}


	public function hasNativeReadingHistory()
	{
		return false;
	}

	/**
	 * Return the number of holds that are on a record
	 * @param int $id biblionumber of title
	 * @return int
	 */
	public function getNumHolds($id)
	{
		// TODO: Implement getNumHolds() method.

	}

	/**
	 * Return the number of holds that are on a record
	 * @param int $id biblionumber of title
	 * @return int
	 */
	public function getNumHoldsFromDB($id) {
		if (isset($this->holdsByBib[$id])){
			return $this->holdsByBib[$id];
		}

		$numHolds = 0;
		if ($this->initDatabaseConnection()){
			$sql     = "SELECT count(*) from reserves where biblionumber = $id";
			$results = mysqli_query($this->dbConnection, $sql);
			if (!$results){
				global $logger;
				$logger->log("Unable to load hold count from Koha (" . mysqli_errno($this->dbConnection) . ") " . mysqli_error($this->dbConnection), PEAR_LOG_ERR);
			}else{
				$curRow   = $results->fetch_row();
				$numHolds = $curRow[0];
				$results->close();
			}
			$this->holdsByBib[$id] = $numHolds;
		}
		global $timer;
		$timer->logTime("Finished loading num holds for record ");

		return $numHolds;
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

		return $this->getMyCheckoutsFromDB($patron);

	}

	/**
	 * getMyCheckoutsFromDB
	 *
	 * Get a list of checkouts for a patron.
	 *
	 * @param User $patron
	 * @return array Array of patrons checkouts
	 * @access public
	 */
	public function getMyCheckoutsFromDB($patron) {
		if (isset($this->transactions[$patron->id])){
			return $this->transactions[$patron->id];
		}

		$transactions = array();

		$this->initDatabaseConnection();

		$sql = <<<EOD
SELECT i.*, items.*, bib.title, bib.author
FROM borrowers as b, issues as i, items, biblio as bib
where b.cardnumber ="{$patron->username}"
AND b.borrowernumber = i.borrowernumber
AND items.itemnumber = i.itemnumber
AND items.biblionumber = bib.biblionumber
EOD;


		$results = mysqli_query($this->dbConnection, $sql);
		while ($curRow = $results->fetch_assoc()){
			$transaction = array();
			$transaction['checkoutSource'] = 'ILS';

			$transaction['id'] = $curRow['biblionumber'];
			$transaction['recordId'] = $curRow['biblionumber'];
			$transaction['shortId'] = $curRow['biblionumber'];
			$transaction['title'] = $curRow['title'];
			$transaction['author'] = $curRow['author'];

			$dateDue = DateTime::createFromFormat('Y-m-d G:i:s', $curRow['date_due']);
			if ($dateDue){
				$dueTime = $dateDue->getTimestamp();
			}else{
				$dueTime = null;
			}
			$transaction['dueDate'] = $dueTime;
			$transaction['itemid'] = $curRow['itemnumber'];
			$transaction['renewIndicator'] = $curRow['itemnumber'];
			$transaction['renewCount'] = $curRow['renewals'];

			if ($transaction['id'] && strlen($transaction['id']) > 0){
				$transaction['recordId'] = $transaction['id'];
				require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
				$recordDriver = new MarcRecord($transaction['recordId']);
				if ($recordDriver->isValid()){
					$transaction['coverUrl']      = $recordDriver->getBookcoverUrl('medium');
					$transaction['groupedWorkId'] = $recordDriver->getGroupedWorkId();
					$transaction['ratingData']    = $recordDriver->getRatingData();
					$transaction['format']        = $recordDriver->getPrimaryFormat();
					$transaction['author']        = $recordDriver->getPrimaryAuthor();
					$transaction['title']         = $recordDriver->getTitle();
					$curTitle['title_sort']       = $recordDriver->getSortableTitle();
					$transaction['link']          = $recordDriver->getLinkUrl();
				}else{
					$transaction['coverUrl'] = "";
					$transaction['groupedWorkId'] = "";
					$transaction['format'] = "Unknown";
				}
			}

			$transaction['user'] = $patron->getNameAndLibraryLabel();

			$transactions[] = $transaction;
		}

		$this->transactions[$patron->id] = $transactions;

		return $transactions;
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
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds for a specific patron.
	 *
	 * @param User $patron The user to load transactions for
	 *
	 * @return array        Array of the patron's holds
	 * @access public
	 */
	public function getMyHolds($patron){
		$holds = $this->getMyHoldsFromDB($patron);
		return $holds;
	}

	/**
	 * Get Reading History
	 *
	 * This is responsible for retrieving a history of checked out items for the patron.
	 *
	 * @param   User   $patron     The patron account
	 * @param   int    $page
	 * @param   int    $recordsPerPage
	 * @param   string $sortOption
	 *
	 * @return  array               Array of the patron's reading list
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut") {

		$this->initDatabaseConnection();

		//Figure out if the user is opted in to reading history
		$sql = "select privacy from borrowers where borrowernumber = {$patron->username}";
		$res = mysqli_query($this->dbConnection, $sql);
		$row = $res->fetch_assoc();
		// privacy in koha db: 1 = default (keep as long as allowed by law), 0 = forever, 2 = never
		$privacy = $row['privacy'];

		if ($privacy != 2) {

		}
		// Update patron's setting in Pika if the setting has changed in Koha
		if ($historyEnabled != $patron->trackReadingHistory) {
			$patron->trackReadingHistory = (boolean) $historyEnabled;
			$patron->update();
		}
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
//	public function placeHold($patron, $recordId, $pickupBranch, $cancelIfNotFilledByDate = null)
//	{
//		$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch, $cancelIfNotFilledByDate);
//		return $result;
//	}

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
//	function placeItemHold($patron, $recordId, $itemId, $pickupBranch, $cancelIfNotFilledByDate = null){
//		$holdResult = array(
//			'success' => false,
//			'message' => 'Your hold could not be placed. '
//		);
//		if ($this->initSipConnection()) {
//			$title   = null;
//			$success = false;
//			$message = 'Unknown error occurred communicating with the circulation system';
//
////			$this->sipConnection->patron    = $patron->cat_username; //TODO: appears barcode is needed to place hold but user id is needed to look up patron status
//			$this->sipConnection->patron    = $patron->username;
//			$this->sipConnection->patronpwd = $patron->cat_password;
//
//			// Determine a pickup location
//			if (empty($pickupBranch)){
//				//Get the code for the location
//				$locationLookup = new Location();
//				$locationLookup->get('locationId', $patron->homeLocationId);
//				if ($locationLookup->get('locationId', $patron->homeLocationId)){
//					$pickupBranch = strtoupper($locationLookup->code);
//				}
//			}else{
//				$pickupBranch = strtoupper($pickupBranch);
//			}
//
//			// Determine hold expiration time
//			if (!empty($cancelIfNotFilledByDate)) {
////				$timestamp = strtotime($cancelIfNotFilledByDate);
//				$dateObject     = date_create_from_format('m/d/Y', $cancelIfNotFilledByDate);
//				$expirationTime = $dateObject->getTimestamp();
//
//			} else {
//				//TODO: Set default here? Can we do SIP call with out cancel time (yes, by SIP2 doc)
//				$expirationTime = ''; // has to be empty strin to be handled well by the SIP2 class
//			}
//
//			//TODO: for item level holds, do we have to change the hold type? (probably to 3)
//
//			$in         = $this->sipConnection->msgHold('+', $expirationTime, '2', $itemId, $recordId, null, $pickupBranch);
////			$in         = $this->sipConnection->msgHold('+', $expirationTime, '2', $recordId, null, 'N', $pickupBranch);
//			$msg_result = $this->sipConnection->get_message($in);
//			if (preg_match("/^16/", $msg_result)) {
//				$result  = $this->sipConnection->parseHoldResponse($msg_result);
//				$success = ($result['fixed']['Ok'] == 1);
//				$message = $result['variable']['AF'][0];
//				if (!empty($result['variable']['AJ'][0])) {
//					$title = $result['variable']['AJ'][0];
//				}
//			}
//			$holdResult = array(
//				'title'   => $title,
//				'bib'     => $recordId,
//				'success' => $success,
//				'message' => $message
//			);
//		}
//		return $holdResult;
//	}

	/**
	 * Cancels a hold for a patron
	 *
	 * @param   User $patron The User to cancel the hold for
	 * @param   string $recordId The id of the bib record
	 * @param   string $cancelId Information about the hold to be cancelled
	 * @return  array
	 */

	/*
	function cancelHold($patron, $recordId, $cancelId){
	#TODO: What hold type?

		# msgHold($mode, $expDate = '', $holdtype = '', $item = '', $title = '', $fee='N', $pkupLocation = '')
		if ($this->initSipConnection()) {
			$this->sipConnection->patron    = $patron->cat_username;
			$this->sipConnection->patronpwd = $patron->cat_password;
			$msgHold = $this->sipConnection->msgHold('-', '', '', $recordId, $cancelId,'N' ,'');
			$rspHold = $this->sipConnection->parseHoldResponse($msgHold);
			var_dump($rspHold); exit();
		}


	}
*/


	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate){
		$result = $this->freezeThawHoldViaSIP($patron, $recordId, null, $dateToReactivate);
		return $result;
	}

	function freezeThawHoldViaSIP($patron, $recordId, $itemToFreezeId = null, $dateToReactivate = null, $type = 'freeze'){
		$holdResult = array(
			'success' => false,
			'message' => 'Your hold could not be placed. '
		);
		if ($this->initSipConnection()) {

		}
		return $holdResult;

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
		return $this->getMyFinesFromDB($patron);

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

	/**
	 * Get a list of fines for the user.
	 * Code take from C4::Account getcharges method
	 *
	 * @param null $patron
	 * @param bool $includeMessages
	 * @return array
	 */
	public function getMyFinesFromDB($patron, $includeMessages = false){

		$this->initDatabaseConnection();
		// set early to give quick return if no fines
		$fines = array();

		$sum_query = <<<EOD
select sum(accountlines.amountoutstanding) as sum 
from accountlines, borrowers
where borrowers.cardnumber = "{$patron->username}"
and borrowers.borrowernumber = accountlines.borrowernumber
EOD;
		// quick query to see if there are any outstanding fines.
		$sumResp = mysqli_query($this->dbConnection, $sum_query);
		// only a single row returned 'sum'
		$sum_row  = $sumResp->fetch_assoc();
		if ($sum_row['sum'] <= 0) {
			// no fines
			return $fines;
		}

		$sumResp->close();
/*
 *
 * SELECT distinct accountno FROM accountlines where borrowernumber = '416127' order by accountno desc;
 *
 * Next loop over each account #
 *
 *
 */
		// has fines
		// First get account #'s
		/*
		$accountNosSql = 'select distinct accountno from accountlines where borrowernumber = "{$patron->username}" order by accountno desc';
		$accountNosRsp = mysqli_query($this->dbConnection, $accountNosSql);

		$accountLineSql = 'select accountlines.amountoutstanding, '
		foreach ($accountNosRsp->fetch_assoc() as $accountNo) {

		}
*/
		$fines_query = <<<EOD
select accountlines.amount as ac_amount, accountlines.*, account_offsets.*
from accountlines, account_offsets, borrowers 
where borrowers.cardnumber = "{$patron->username}"
and borrowers.borrowernumber = accountlines.borrowernumber
and (accountlines.accountlines_id = account_offsets.credit_id 
or accountlines.accountlines_id = account_offsets.debit_id)
order by accountlines.date ASC
EOD;


		$allFeesRS = mysqli_query($this->dbConnection, $fines_query);

		while ($allFeesRow = $allFeesRS->fetch_assoc()){
			// do some rounding to get rid of extra zeros.
			$amount = number_format($allFeesRow['amount'], 2, '.', '');
			$amountOutstanding = number_format($allFeesRow['amountoutstanding'], 2, '.', '');
			// if amountoutstanding is 0.000000 set it to nothing so it doesn't mess with Fine.php class calculation
			if ($amountOutstanding == 0) {
				$amountOutstanding = '';
			}
			// TODO: select title if accountlines.issueid != null
			$curFine = [
					'date' => $allFeesRow['date'],
					'reason' => $allFeesRow['accounttype'],
					'message' => $allFeesRow['type'].": ".$allFeesRow['description'],
					'amount' => $amount,
					'amountOutstanding' => $amountOutstanding
			];
				$fines[] = $curFine;
		}
		$allFeesRS->close();

		return $fines;
	}

	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds by a specific patron.
	 *
	 * @param object|User $patron     The patron object from patronLogin
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

		require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';

		$this->initDatabaseConnection();
		if ($this->dbConnection){

			$sql = 'SELECT reserves.*, biblio.title, biblio.author, borrowers.cardnumber, borrowers.borrowernumber FROM reserves inner join biblio on biblio.biblionumber = reserves.biblionumber left join borrowers using (borrowernumber) ';
//			$sql .= 'where userid = "'. $patron->getBarcode() . '"'; //TODO: temp
			$sql .= 'where cardnumber = "'. $patron->getBarcode() . '"';

			$results = mysqli_query($this->dbConnection, $sql);
			if ($results){
				while ($curRow = $results->fetch_assoc()){
					//Each row in the table represents a hold
					$bibId          = $curRow['biblionumber'];
					$expireDate     = $curRow['expirationdate'];
					$createDate     = $curRow['reservedate'];
					$fillByDate     = $curRow['cancellationdate']; //TODO: Is this the cancellation date or is 'waitingdate'
					$reactivateDate = $curRow['suspend_until'];
					$branchCode     = $curRow['branchcode'];

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
					$curHold['location']              = $branchCode;
					$curHold['currentPickupName']     = $branchCode;
					$curHold['position']              = $curRow['priority'];
					$curHold['cancelId']              = $curRow['reserve_id'];//$curRow['reservenumber'];
					$curHold['cancelable']            = true;
					$curHold['locationUpdateable']    = false; //TODO: can update after the SIP call is built (will need additional logic for this depending on status)
					$curHold['frozen']                = false;
					$curHold['freezeable']            = false; //TODO: can update after the SIP call is built (will need additional logic for this depending on status)

					switch ($curRow['found']){
						case 'S':
							$curHold['status']     = "Suspended";
							$curHold['frozen']     = true;
							$curHold['cancelable'] = false;
							break;
						case 'W':
							$curHold['status'] = "Ready to Pickup";
							break;
						case 'T':
							$curHold['status'] = "In Transit";
							break;
						default:
							$curHold['status']     = "Pending";
							$curHold['freezeable'] = true;
							break;
					}

					$curPickupBranch       = new Location();
					$curPickupBranch->code = $branchCode;
					if ($curPickupBranch->find(true)){
						$curPickupBranch->fetch();
						$curHold['currentPickupId']   = $curPickupBranch->locationId;
						$curHold['currentPickupName'] = $curPickupBranch->displayName;
						$curHold['location']          = $curPickupBranch->displayName;
					}

					if (!empty($bibId)){
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
						}else{
							$simpleSortTitle      = preg_replace('/^The\s|^A\s/i', '', $curRow['title']); // remove beginning The or A
							$curHold['sortTitle'] = empty($simpleSortTitle) ? $curRow['title'] : $simpleSortTitle;
						}
					}

					if (preg_match('/^Ready to Pickup.*/i', $curHold['status'])){
						$holds['available'][] = $curHold;
					}else{
						$holds['unavailable'][] = $curHold;
					}
				}
			} else {
				global $logger;
				$logger->log("Error querying for holds in Bywater Koha database " . mysqli_error($this->dbConnection), PEAR_LOG_ERR);
			}
		}
		return $holds;
	}

	//Moved to KohaILSDI Driver
//	function initDatabaseConnection(){
//		global $configArray;
//		if ($this->dbConnection == null){
//			$this->dbConnection = mysqli_connect($configArray['Catalog']['db_host'], $configArray['Catalog']['db_user'], $configArray['Catalog']['db_pwd'], $configArray['Catalog']['db_name'], $configArray['Catalog']['db_port']);
//
//			if (!$this->dbConnection || mysqli_errno($this->dbConnection) != 0){
//				global $logger;
//				$logger->log("Error connecting to Koha database " . mysqli_error($this->dbConnection), PEAR_LOG_ERR);
//				$this->dbConnection = null;
//			}
//			global $timer;
//			$timer->logTime("Initialized connection to Koha");
//		}
//	}
//
	function __destruct(){
		//Cleanup any connections we have to other systems
		if ($this->sipConnection != null){
			$this->sipConnection->disconnect();
			$this->sipConnection = null;
		}

		if ($this->dbConnection != null){
//			if ($this->getNumHoldsStmt != null){
//				$this->getNumHoldsStmt->close();
//			}
			mysqli_close($this->dbConnection);
		}

	}


}