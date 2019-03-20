<?php
/**
 * Handles Millennium Integration related to Reading History
 *
 * @category VuFind-Plus 
 * @author Mark Noble <mark@marmot.org>
 * Date: 5/20/13
 * Time: 11:51 AM
 */

class MillenniumReadingHistory {
	/**
	 * @var Millennium $driver;
	 */
	private $driver;
	public function __construct($driver){
		$this->driver = $driver;
	}

	/**
	 * Method to extract a patron's existing reading history in the ILS.
	 * This method is meant to be used by the Pika cron process to do the initial load
	 * of a patron's reading history.
	 *
	 * @param User $patron
	 * @param null|int $loadAdditional
	 * @return array
	 */
	public function loadReadingHistoryFromIls($patron, $loadAdditional = null) {
	global $timer;
	$additionalLoadsRequired = false;
	$pagesToLoadAtATime = 4;
	$initialStartPage   = 1;
	$ReadHistoryPage    = 'readinghistory';
	if (!empty($loadAdditional)){
		$initialStartPage = $loadAdditional * $pagesToLoadAtATime + 1;
		$ReadHistoryPage .= '&page=' . $initialStartPage;
	}
	$pageContents       = $this->driver->_fetchPatronInfoPage($patron, $ReadHistoryPage, false);

	//Check to see if there are multiple pages of reading history
	$hasPagination = preg_match('/<td[^>]*class="browsePager"/', $pageContents);
	if ($hasPagination){
		//Load a list of extra pages to load.  The pagination links display multiple times, so load into an associative array to make them unique
		preg_match_all('/<a href="readinghistory&page=(\\d+)">/', $pageContents, $additionalPageMatches);
		$maxPageNum        = max($additionalPageMatches[1]);
		$lastPageThisRound = $initialStartPage + ($pagesToLoadAtATime - 1);
		if ($maxPageNum > $lastPageThisRound){
			$additionalLoadsRequired = true;
			$nextRound               = empty($loadAdditional) ? 1 : $loadAdditional + 1;
		} else {
			$lastPageThisRound = $maxPageNum;
		}
	}

	$readingHistoryTitles = $this->parseReadingHistoryPage($pageContents, $patron);
	if (isset($maxPageNum)){
		$nextPageToStartWith = empty($loadAdditional) ? 2 : $initialStartPage + 1;
		for ($pageNum = $nextPageToStartWith; $pageNum <= $lastPageThisRound; $pageNum++){
			$pageContents         = $this->driver->_fetchPatronInfoPage($patron, 'readinghistory&page=' . $pageNum, false);
			$additionalTitles     = $this->parseReadingHistoryPage($pageContents, $patron);
			$readingHistoryTitles = array_merge($readingHistoryTitles, $additionalTitles);
		}
	}
	$timer->logTime("Loaded Reading history from ILS for patron");
	require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
	foreach ($readingHistoryTitles as &$readingHistoryEntry){
		// Add Grouped work ID and Format if we have information
		if (!empty($readingHistoryEntry['recordId'])){
			$recordDriver                       = new MarcRecord($this->driver->accountProfile->recordSource. ':' . $readingHistoryEntry['recordId']);
			$readingHistoryEntry['permanentId'] = $recordDriver->getPermanentId();
			$readingHistoryEntry['format']      = $recordDriver->getFormats();
		}

		// Unset fields not needed for loading history cron task
		unset(
			$readingHistoryEntry['itemindex'],
			$readingHistoryEntry['id'],
			$readingHistoryEntry['shortId'],
			$readingHistoryEntry['title_sort'],
			$readingHistoryEntry['details']
		);
	}

	if ($additionalLoadsRequired){
		return array(
			'nextRound' => $nextRound,
			'titles'    => $readingHistoryTitles,
		);
	}
	return array('titles' => $readingHistoryTitles);
}


	/**
	 * @param User $patron
	 * @param int $page
	 * @param int $recordsPerPage
	 * @param string $sortOption
	 * @return array
	 */
	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut") {
		global $timer;
		//Load the information from millennium using CURL
		//$this->driver->_close_curl();

		//$this->driver->_curl_login($patron);
		$pageContents = $this->driver->_fetchPatronInfoPage($patron, 'readinghistory');

		//Check to see if there are multiple pages of reading history
		$hasPagination = preg_match('/<td[^>]*class="browsePager"/', $pageContents);
		if ($hasPagination){
			//Load a list of extra pages to load.  The pagination links display multiple times, so load into an associative array to make them unique
			preg_match_all('/<a href="readinghistory&page=(\\d+)">/', $pageContents, $additionalPageMatches);
			$maxPageNum = max($additionalPageMatches[1]);
		}

		$recordsRead          = 0;
		$readingHistoryTitles = $this->parseReadingHistoryPage($pageContents, $patron, $sortOption, $recordsRead);
		$recordsRead          += count($readingHistoryTitles);
		if (isset($maxPageNum)){
			for ($pageNum = 2; $pageNum <= $maxPageNum; $pageNum++){
				$pageContents         = $this->driver->_fetchPatronInfoPage($patron, 'readinghistory&page=' . $pageNum);
				$additionalTitles     = $this->parseReadingHistoryPage($pageContents, $patron, $sortOption, $recordsRead);
				$recordsRead          += count($additionalTitles);
				$readingHistoryTitles = array_merge($readingHistoryTitles, $additionalTitles);
			}
		}

		if ($sortOption == "checkedOut" || $sortOption == "returned"){
			krsort($readingHistoryTitles);
		}else{
			ksort($readingHistoryTitles);
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
			$historyEntry['format']      = array();
			if (!empty($historyEntry['recordId'])){
//				$historyEntry['recordId'] = "." . $historyEntry['shortId'] . $this->driver->getCheckDigit($historyEntry['shortId']);
				require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
				$recordDriver = new MarcRecord($this->driver->accountProfile->recordSource . ':' . $historyEntry['recordId']);
				if ($recordDriver->isValid()){
					$historyEntry['ratingData']  = $recordDriver->getRatingData();
					$historyEntry['permanentId'] = $recordDriver->getPermanentId();
					$historyEntry['linkUrl']     = $recordDriver->getGroupedWorkDriver()->getLinkUrl();
					$historyEntry['coverUrl']    = $recordDriver->getBookcoverUrl('medium');
					$historyEntry['format']      = $recordDriver->getFormats();
				}
				$recordDriver = null;
			}
			$readingHistoryTitles[$key] = $historyEntry;
		}

		//The history is active if there is an opt out link.
		$historyActive = (strpos($pageContents, 'OptOut') > 0);
		$timer->logTime("Loaded Reading history for patron");
		if ($historyActive && !$patron->trackReadingHistory){
			//The user does have reading history even though we hadn't detected it before.
			$patron->trackReadingHistory = true;
			$patron->update();
		}
		elseif (!$historyActive && $patron->trackReadingHistory){
			//The user does have reading history even though we hadn't detected it before.
			$patron->trackReadingHistory = false;
			$patron->update();
		}

		return array('historyActive' => $historyActive, 'titles' => $readingHistoryTitles, 'numTitles' => $numTitles);
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
		global $analytics;
		//Load the reading history page
		$scope                 = $this->driver->getDefaultScope();
		$baseReadingHistoryURL = $this->driver->getVendorOpacUrl() . "/patroninfo~S{$scope}/" . $patron->username . "/readinghistory";


		$this->driver->_curl_connect($baseReadingHistoryURL);
		$this->driver->_curl_login($patron);

		if ($action == 'deleteMarked'){
			//Load patron page readinghistory/rsh with selected titles marked
			if (!isset($selectedTitles) || count($selectedTitles) == 0){
				return;
			}
			$titles = array();
			foreach ($selectedTitles as $titleId){
				$titles[] = $titleId . '=1';
			}
			$title_string = implode ('&', $titles);
			//Issue a get request to delete the item from the reading history.
			//Note: Millennium really does issue a malformed url, and it is required
			//to make the history delete properly.
			$curl_url = $baseReadingHistoryURL ."/rsh&" . $title_string;

			$this->driver->_curlGetPage($curl_url);
			if ($analytics){
				$analytics->addEvent('ILS Integration', 'Delete Marked Reading History Titles');
			}
		}elseif ($action == 'deleteAll'){
			//load patron page readinghistory/rah
			$curl_url = $baseReadingHistoryURL ."/rah";

			$this->driver->_curlGetPage($curl_url);
			if ($analytics){
				$analytics->addEvent('ILS Integration', 'Delete All Reading History Titles');
			}
		}elseif ($action == 'exportList'){
			//Leave this unimplemented for now.
		}elseif ($action == 'optOut'){
			//load patron page readinghistory/OptOut
			$curl_url = $baseReadingHistoryURL ."/OptOut";
			$this->driver->_curlGetPage($curl_url);
			if ($analytics){
				$analytics->addEvent('ILS Integration', 'Opt Out of Reading History');
			}
			$patron->trackReadingHistory = false;
			$patron->update();
		}elseif ($action == 'optIn'){
			//load patron page readinghistory/OptIn
			$curl_url = $baseReadingHistoryURL ."/OptIn";
			$this->driver->_curlGetPage($curl_url);

			if ($analytics){
				$analytics->addEvent('ILS Integration', 'Opt in to Reading History');
			}
			$patron->trackReadingHistory = true;
			$patron->update();
		}
		$this->driver->_close_curl();
	}

	private function parseReadingHistoryPage($pageContents, $patron, $sortOption = null, $recordsRead = null) {

		//Get the headers from the table
		preg_match_all('/<th\\s+class="patFuncHeaders">\\s*(.*?)\\s*<\/th>/si', $pageContents, $result, PREG_SET_ORDER);
		$sKeys = array();
		for ($matchi = 0; $matchi < count($result); $matchi++) {
			$sKeys[] = strip_tags($result[$matchi][1]);
		}

		//Get the rows for the table
		preg_match_all('/<tr\\s+class="patFuncEntry">(.*?)<\/tr>/si', $pageContents, $result, PREG_SET_ORDER);
		$sRows = array();
		for ($matchi = 0; $matchi < count($result); $matchi++) {
			$sRows[] = $result[$matchi][1];
		}

		$sCount = 1;
		$readingHistoryTitles = array();
		foreach ($sRows as $sRow) {
			preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $sRow, $result, PREG_SET_ORDER);
			$sCols = array();
			for ($matchi = 0; $matchi < count($result); $matchi++) {
				$sCols[] = $result[$matchi][1];
			}
			$historyEntry = array();
//			for ($i=0; $i < count($sCols); $i++) {
			foreach ($sCols as $i => $currentColumn){
				$currentColumnKey = $sKeys[$i];
				$currentColumn    = str_replace('&nbsp;', ' ', $currentColumn);
				$currentColumn    = preg_replace('/<br+?>/', ' ', $currentColumn);
				$currentColumn    = trim(html_entity_decode($currentColumn, ENT_COMPAT | ENT_HTML401, 'UTF-8'));

				if (stripos($currentColumnKey,'Mark') > -1) {
					if (preg_match('/id="rsh(\\d+)"/', $currentColumn, $matches)){
						$itemIndex                 = $matches[1];
						$historyEntry['itemindex'] = $itemIndex;
					}
				}

				elseif (stripos($currentColumnKey,"Title") > -1) {
					//echo("Title value is <br/>$currentColumn<br/>");
					if (preg_match('/.*?<a href=\\"\/record=(.*?)(?:~S\\d{1,2})\\">(.*?)<\/a>.*/', $currentColumn, $matches)) {
						$shortId                  = $matches[1];
						$bibId                    = '.' . $matches[1] . $this->driver->getCheckDigit($shortId);
						$historyEntry['id']       = $bibId;
						$historyEntry['shortId']  = $shortId;
						$historyEntry['recordId'] = $bibId;
					}elseif (preg_match('/.*<a href=".*?\/record\/C__R(.*?)\\?.*?">(.*?)<\/a>.*/si', $currentColumn, $matches)){
						$shortId                  = $matches[1];
						$bibId                    = '.' . $matches[1] . $this->driver->getCheckDigit($shortId);
						$historyEntry['id']       = $bibId;
						$historyEntry['shortId']  = $shortId;
						$historyEntry['recordId'] = $bibId;
					}

					$title                 = preg_replace('/\shttp:\/\/.+?<\/span>/', '', $currentColumn); // Remove authorities links
					$title                 = trim(strip_tags($title));
					$historyEntry['title'] = $title;
				}

				elseif (stripos($currentColumnKey,"Author") > -1) {
					$author                 = preg_replace('/\shttp:\/\/.+?$/', '', $currentColumn); // Remove authorities links
					$historyEntry['author'] = trim(rtrim(strip_tags($author), ','));
				}

				elseif (stripos($currentColumnKey,"Checked Out") > -1) {
					$historyEntry['checkout'] = trim(strip_tags($currentColumn));
				}
				elseif (stripos($currentColumnKey,"Details") > -1) {
					$historyEntry['details'] = trim(strip_tags($currentColumn));
				}

			} //Done processing the current row's columns

			$historyEntry['borrower_num'] = $patron->id;
			$historyEntry['title_sort']   = preg_replace('/[^a-z\s]/', '', strtolower($historyEntry['title']));

//			if ($sortOption == "title"){
//				$titleKey = $historyEntry['title_sort'];
//			}elseif ($sortOption == "author"){
//				$titleKey = $historyEntry['author'] . "_" . $historyEntry['title_sort'];
//			}elseif ($sortOption == "checkedOut" || $sortOption == "returned"){
//				$checkoutTime = DateTime::createFromFormat('m-d-Y', $historyEntry['checkout']) ;
//				if ($checkoutTime){
//					$titleKey = $checkoutTime->getTimestamp() . "_" . $historyEntry['title_sort'];
//				}else{
//					//print_r($historyEntry);
//					$titleKey = $historyEntry['title_sort'];
//				}
//			}elseif ($sortOption == "format"){
//				$titleKey = $historyEntry['format'] . "_" . $historyEntry['title_sort'];
//			}else{
//				$titleKey = $historyEntry['title_sort'];
//			}
//			$titleKey .= '_' . ($sCount + $recordsRead);
//			$readingHistoryTitles[$titleKey] = $historyEntry;


			//Catalog Connector Does the sort key work now
			$readingHistoryTitles[] = $historyEntry;

			$sCount++;
		}//processed all rows in the table
		return $readingHistoryTitles;
	}
}