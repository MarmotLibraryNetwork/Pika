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
require_once ROOT_DIR . '/Drivers/KohaILSDI.php';

abstract class ByWaterKoha extends KohaILSDI {

	/** @var  AccountProfile $accountProfile */
	public $accountProfile;

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
	public function getNumHolds($id) {

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

		$kohaPatronID = $this->getKohaPatronId($patron);

		$this->initDatabaseConnection();

		$sql = <<<EOD
SELECT i.*, items.*, i.renewals as times_renewed, bib.title, bib.author
FROM borrowers as b, issues as i, items, biblio as bib
where b.borrowernumber = '{$kohaPatronID}'
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

			if($curRow['auto_renew'] == 1) {
				$transaction['canrenew'] = false;
			}

			$transaction['dueDate'] = $dueTime;
			$transaction['itemid'] = $curRow['itemnumber'];
			$transaction['renewIndicator'] = $curRow['itemnumber'];
			$transaction['renewCount'] = $curRow['times_renewed'];

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
		return false;
	}

	/**
	 * Renew all titles currently checked out to the user
	 *
	 * @param $patron  User
	 * @return mixed
	 */
	public function renewAll($patron) {
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

		$kohaPatronID = $this->getKohaPatronId($patron);

		if($this->initDatabaseConnection() == null) {
			return array('historyActive' => false, 'titles' => array(), 'numTitles' => 0);
		}

		//Figure out if the user has opted out of reading history in koha.
		$sql = "select privacy from borrowers where borrowernumber = {$kohaPatronID}";
		$res = mysqli_query($this->dbConnection, $sql);
		$row = $res->fetch_assoc();
		// privacy in koha: 1 = default (keep as long as allowed by law), 0 = forever, 2 = never
		$privacy = $row['privacy'];
		// history enabled from koha
		$historyEnabled = false;
		if ($privacy != 2) {
			$historyEnabled = true;
		}
		// Update patron's setting in Pika only if reading history disabled in koha
		// otherwise keep setting as is
		if ($historyEnabled == false) {
			if ($historyEnabled != $patron->trackReadingHistory) {
				$patron->trackReadingHistory = (boolean)$historyEnabled;
				$patron->update();
			}
		}

		if (!$historyEnabled) {
			return array('historyActive' => false, 'titles' => array(), 'numTitles' => 0);
		}

		$historyActive = true;

		$readinHistorySql = <<<EOD
SELECT *, issues.timestamp as issuestimestamp, issues.renewals AS renewals,items.renewals AS totalrenewals,items.timestamp AS itemstimestamp
  FROM issues
  LEFT JOIN items on items.itemnumber=issues.itemnumber
  LEFT JOIN biblio ON items.biblionumber=biblio.biblionumber
  LEFT JOIN biblioitems ON items.biblioitemnumber=biblioitems.biblioitemnumber
  WHERE borrowernumber={$kohaPatronID}
  UNION ALL
  SELECT *, old_issues.timestamp as issuestimestamp, old_issues.renewals AS renewals,items.renewals AS totalrenewals,items.timestamp AS itemstimestamp
  FROM old_issues
  LEFT JOIN items on items.itemnumber=old_issues.itemnumber
  LEFT JOIN biblio ON items.biblionumber=biblio.biblionumber
  LEFT JOIN biblioitems ON items.biblioitemnumber=biblioitems.biblioitemnumber
  WHERE borrowernumber={$kohaPatronID} AND old_issues.itemnumber IS NOT NULL
EOD;

		$readingHistoryRes = mysqli_query($this->dbConnection, $readinHistorySql);

		if ($readingHistoryRes){
			$readingHistoryTitles = array();
			while ($readingHistoryTitleRow = $readingHistoryRes->fetch_assoc()){
				$checkOutDate = new DateTime($readingHistoryTitleRow['issuetimestamp']);
				$curTitle = array();
				$curTitle['id']       = $readingHistoryTitleRow['biblionumber'];
				$curTitle['shortId']  = $readingHistoryTitleRow['biblionumber'];
				$curTitle['recordId'] = $readingHistoryTitleRow['biblionumber'];
				$curTitle['title']    = $readingHistoryTitleRow['title'];
				$curTitle['checkout'] = $checkOutDate->format('m-d-Y'); // this format is expected by Pika's java cron program.

				$readingHistoryTitles[] = $curTitle;
			}

		$numTitles = count($readingHistoryTitles);

		//process pagination
		if ($recordsPerPage != -1){
			$startRecord = ($page - 1) * $recordsPerPage;
			$readingHistoryTitles = array_slice($readingHistoryTitles, $startRecord, $recordsPerPage);
		}

		set_time_limit(20 * count($readingHistoryTitles));
		foreach ($readingHistoryTitles as $key => $historyEntry){
			//Get additional information from resources table
			$historyEntry['ratingData']  = null;
			$historyEntry['permanentId'] = null;
			$historyEntry['linkUrl']     = null;
			$historyEntry['coverUrl']    = null;
			$historyEntry['format']      = "Unknown";

			if (!empty($historyEntry['recordId'])){
//					if (is_int($historyEntry['recordId'])) $historyEntry['recordId'] = (string) $historyEntry['recordId']; // Marc Record Contructor expects the recordId as a string.
				require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
				$recordDriver = new MarcRecord($this->accountProfile->recordSource.':'.$historyEntry['recordId']);
				if ($recordDriver->isValid()){
					$historyEntry['ratingData']  = $recordDriver->getRatingData();
					$historyEntry['permanentId'] = $recordDriver->getPermanentId();
					$historyEntry['linkUrl']     = $recordDriver->getGroupedWorkDriver()->getLinkUrl();
					$historyEntry['coverUrl']    = $recordDriver->getBookcoverUrl('medium');
					$historyEntry['format']      = $recordDriver->getFormats();
					$historyEntry['author']      = $recordDriver->getPrimaryAuthor();
				}
				$recordDriver = null;
			}
			$readingHistoryTitles[$key] = $historyEntry;
		}

			return array('historyActive'=>$historyActive, 'titles'=>$readingHistoryTitles, 'numTitles'=> $numTitles);
		}
		return array('historyActive'=>false, 'titles'=>array(), 'numTitles'=> 0);
	}


	/**
	 * Freeze Hold
	 *
	 * Freeze/suspend/pause a hold for an individual title.
	 *
	 * @param $patron             Patron
	 * @param $recordId
	 * @param $itemToFreezeId
	 * @param $dateToReactivate
	 * @return array
	 */
	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate){
		global $configArray;

		$result = [
			'title' => '',
			'success' => false,
			'message' => 'Unable to ' . translate('freeze') .' your hold.'
		];

		$apiUrl = $configArray['Catalog']['koha_api_url'];
		$apiUrl .= "/api/v1/contrib/pika/holds/{$itemToFreezeId}/suspend/";

		$response = $this->_curl_post_request($apiUrl);

		if(!$response) {
			return $result;
		}

		$hold_response = json_decode($response, false);
		if ($hold_response->suspended && $hold_response->suspended == true) {
			$result['message'] = 'Your hold was ' . translate('frozen') .' successfully.';
			$result['success'] = true;
			return $result;
		}

		return $result;
	}

	function thawHold($patron, $recordId, $itemToThawId) {
		global $configArray;

		$result = [
			'title' => '',
			'success' => false,
			'message' => 'Unable to ' . translate('thaw') . ' your hold.'
		];

		$apiUrl =  $configArray['Catalog']['koha_api_url'];
		$apiUrl .= "/api/v1/contrib/pika/holds/{$itemToThawId}/resume/";

		$response = $this->_curl_post_request($apiUrl);
		if(!$response) {
			return $result;
		}

		$hold_response = json_decode($response, false);
		if ($hold_response->resumeed && $hold_response->resumeed == true) {
			$result['message'] = 'Your hold was ' . translate('thawed') .' successfully.';
			$result['success'] = true;
			return $result;
		}

		return $result;
	}


	function changeHoldPickupLocation($patron, $recordId, $itemToUpdateId, $newPickupLocation) {
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
	public function getMyFines($patron, $includeMessages = false) {
		return $this->getMyFinesFromDB($patron);
	}

	/**
	 * Get a list of fines for the user.
	 *
	 * @param null $patron
	 * @param bool $includeMessages
	 * @return array
	 */
	public function getMyFinesFromDB($patron, $includeMessages = false){

		$kohaPatronID = $this->getKohaPatronId($patron);

		$this->initDatabaseConnection();
		// set early to give quick return if no fines
		$fines = array();

		$sum_query = <<<EOD
select sum(accountlines.amountoutstanding) as sum 
from accountlines
where accountlines.borrowernumber = "{$kohaPatronID}"
EOD;
		// quick query to see if there are any outstanding fines.
		$sumResp = mysqli_query($this->dbConnection, $sum_query);
		// only a single row returned 'sum'
		$sum_row  = $sumResp->fetch_assoc();
		if ($sum_row['sum'] <= 0) {
			// no fines
			return $fines;
		}

		$fines_query = <<<EOD
select *
from accountlines
where accountlines.borrowernumber = "{$kohaPatronID}"
and accountlines.amountoutstanding != 0.000000
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
					'message' => $allFeesRow['description'],
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

			//$sql = 'SELECT reserves.*, biblio.title, biblio.author, borrowers.cardnumber, borrowers.borrowernumber FROM reserves inner join biblio on biblio.biblionumber = reserves.biblionumber left join borrowers using (borrowernumber) ';
			//$sql .= 'where cardnumber = "'. $patron->getBarcode() . '"';
			$patron_barcode = $patron->getBarcode();
			$sql = <<<EOD
SELECT reserves.*, biblio.title, biblio.author, borrowers.cardnumber, borrowers.borrowernumber 
FROM reserves 
	inner join biblio on biblio.biblionumber = reserves.biblionumber 
	left join borrowers using (borrowernumber)
where cardnumber = "{$patron_barcode}"
EOD;



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
					$curHold['locationUpdateable']    = false;
					$curHold['frozen']                = false;
					$curHold['freezeable']            = false;



					switch ($curRow['found']){
						case 'S':
							$curHold['status']     = "Frozen";
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
							// frozen
							if($curRow['suspend'] == 1) {
								$curHold['frozen'] = true;
								$curHold['status'] = "Frozen";
							} else {
								$curHold['status']     = "Pending";
								$curHold['freezeable'] = true;
							}
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

	private function _curl_post_request($url) {

		$c = curl_init($url);
		$curl_options  = array(
			CURLOPT_POST              => true,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_SSL_VERIFYPEER    => false,
			CURLOPT_SSL_VERIFYHOST    => false,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_FORBID_REUSE      => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		);

		curl_setopt_array($c, $curl_options);
		// this stops the remote server from giving "Bad Request".
		curl_setopt($c, CURLOPT_POSTFIELDS, array());

		$return = curl_exec($c);

		if($errno = curl_errno($c)) {
			global $logger;
			$error_message = curl_strerror($errno);
			$curlError = "cURL error ({$errno}):\n {$error_message}";
			$logger->log("\n\nBywater API URL: " . $url, PEAR_LOG_ERR);
			$logger->log($curlError, PEAR_LOG_ERR);
			$logger->log('Response from bywater api: ' . $return . "\n\n", PEAR_LOG_ERR);
		}

		curl_close($c);
		return $return;
	}

	function __destruct(){
		//Cleanup any connections we have to other systems
		if ($this->sipConnection != null){
			$this->sipConnection->disconnect();
			$this->sipConnection = null;
		}

		if ($this->dbConnection != null){
			mysqli_close($this->dbConnection);
		}
	}


}