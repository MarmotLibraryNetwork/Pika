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

require_once ROOT_DIR . '/AJAXHandler.php';

class SearchAPI extends AJAXHandler {

	protected $methodsThatRespondWithHTML = [
		'getSearchBar',
		'getListWidget',
	];

	protected $methodsThatRespondWithJSONResultWrapper = [
		'getTopSearches',
		'getRecordIdForTitle',
		'getRecordIdForItemBarcode',
		'getTitleInfoForISBN',
		'search',
	];

	protected $methodsThatRespondWithJSONUnstructured = [
		'getIndexStatus',
	];

	// The time intervals in seconds beyond which we consider the status as not current
	const FULL_INDEX_INTERVAL_WARN            = 86400;  // 24 Hours (in seconds)
	const FULL_INDEX_INTERVAL_CRITICAL        = 129600; // 36 Hours (in seconds)
	const PARTIAL_INDEX_INTERVAL_WARN         = 1500;   // 25 Minutes (in seconds)
	const PARTIAL_INDEX_INTERVAL_CRITICAL     = 3600;   // 1 Hour (in seconds)
	const SIERRA_EXTRACT_INTERVAL_WARN        = 900;    // 15 Minutes (in seconds)
	const SIERRA_EXTRACT_INTERVAL_CRITICAL    = 3600;   // 1 Hour (in seconds)
	const LAST_EXTRACT_INTERVAL_WARN          = 7200;    // 2 Hours (in seconds)
	const LAST_EXTRACT_INTERVAL_CRITICAL      = 14400;  // 4 Hours (in seconds)
	const OVERDRIVE_EXTRACT_INTERVAL_WARN     = 14400;  // 4 Hours (in seconds)
	const OVERDRIVE_EXTRACT_INTERVAL_CRITICAL = 18000;  // 5 Hours (in seconds)
	const SOLR_RESTART_INTERVAL_WARN          = 86400;  // 24 Hours (in seconds)
	const SOLR_RESTART_INTERVAL_CRITICAL      = 129600; // 36 Hours (in seconds)
	const OVERDRIVE_DELETED_ITEMS_WARN        = 300;
	const OVERDRIVE_DELETED_ITEMS_CRITICAL    = 1000;
	const OVERDRIVE_UNPROCESSED_ITEMS_WARN    = 5000;
	const OVERDRIVE_UNPROCESSED_ITEMS_CRITICAL= 10000;
	const SIERRA_MAX_REMAINING_ITEMS_WARN     = 5000;
	const SIERRA_MAX_REMAINING_ITEMS_CRITICAL = 20000;

	const STATUS_OK       = 'okay';
	const STATUS_WARN     = 'warning';
	const STATUS_CRITICAL = 'critical';


	function getIndexStatus(){
		$notes  = [];
		$status = [];

		$currentTime = time();

		// Last Export Valid //
		$lastExportValidVariable = new Variable('last_export_valid');
		if (!empty($lastExportValidVariable->N)){
			//Check to see if the last export was valid
			if ($lastExportValidVariable->value == false){
				$status[] = self::STATUS_WARN;
				$notes[]  = 'MARC file(s) failed validation';
			}
		}else{
			$status[] = self::STATUS_WARN;
			$notes[]  = 'MARC file validation has never been run';
		}

		// Full Index //
		$lastFullIndexVariable = new Variable('lastFullReindexFinish');
		if (!empty($lastFullIndexVariable->N)){
			$fullIndexWarningInterval          = self::FULL_INDEX_INTERVAL_WARN;

			// Look up override value
			$fullIndexWarningIntervalVar = new Variable('fullReindexIntervalWarning');
			if (!empty($fullIndexWarningIntervalVar->N)){
				$fullIndexWarningInterval = $fullIndexWarningIntervalVar->value;
			}

			//Check to see if the last full index finished more than $fullIndexWarningInterval seconds ago
			if ($lastFullIndexVariable->value < ($currentTime - $fullIndexWarningInterval)){
				$fullIndexCriticalInterval    = self::FULL_INDEX_INTERVAL_CRITICAL;
				$fullIndexCriticalIntervalVar = new Variable('fullReindexIntervalCritical');
				if (!empty($fullIndexCriticalIntervalVar->N)){
					$fullIndexCriticalInterval = $fullIndexCriticalIntervalVar->value;
				}
				$status[] = ($lastFullIndexVariable->value < ($currentTime - $fullIndexCriticalInterval)) ? self::STATUS_CRITICAL : self::STATUS_WARN;
				$notes[]  = 'Full Index last finished ' . date('m-d-Y H:i:s', $lastFullIndexVariable->value) . ' - ' . round(($currentTime - $lastFullIndexVariable->value) / 3600, 2) . ' hours ago';
			}
		}else{
			$status[]              = self::STATUS_WARN;
			$notes[]               = 'Full index has never been run';
			$lastFullIndexVariable = null;
		}

		// Check if a fullReindex is running now
		$fullIndexRunning         = false;
		$fullIndexRunningVariable = new Variable('full_reindex_running');
		if (!empty($fullIndexRunningVariable->N)){
			$fullIndexRunning = $fullIndexRunningVariable->value == 'true' || $fullIndexRunningVariable->value == '1';
		}

		//Check to see if a regrouping is running since that will also delay partial indexing
		$recordGroupingRunning         = false;
		$recordGroupingRunningVariable = new Variable('record_grouping_running');
		if (!empty($recordGroupingRunningVariable->N)){
			$recordGroupingRunning = $recordGroupingRunningVariable->value == 'true' || $recordGroupingRunning == '1';
		}

		//Do not check partial index or overdrive extract if there is a full index running since they pause during that period
		//Also do not check these from 9pm to 7am since between these hours, we're running full indexing and these issues wind up being ok.
		$curHour = date('H');
		if (!$fullIndexRunning && !$recordGroupingRunning && ($curHour >= 7 && $curHour <= 21)){
			$isPartialIndexPaused       = false;
			$partialIndexPauseIntervals = new Variable('partial_index_pause_intervals');
			if (!empty($partialIndexPauseIntervals->N)){

				if (!empty($partialIndexPauseIntervals->value)){
					// Format should be hh:mm-hh:mm;hh:mm-hh:mm (some spacing tolerated) (24 hour format; Intervals can't cross 24:00/00:00)
					$intervals = explode(';', trim($partialIndexPauseIntervals->value));
					foreach ($intervals as $interval){
						[$start, $stop] = explode('-', trim($interval));
						[$startHour, $startMin] = explode(':', trim($start));
						[$stopHour, $stopMin] = explode(':', trim($stop));

						if (is_numeric($startHour) && is_numeric($startMin) && is_numeric($stopHour) && is_numeric($startMin)){
							$startTimeStamp = mktime($startHour, $startMin, 0);
							$stopTimeStamp  = mktime($stopHour, $stopMin, 0);
							if ($currentTime >= $startTimeStamp && $currentTime <= $stopTimeStamp){
								$isPartialIndexPaused = true;
								$status[]             = self::STATUS_OK;
								$notes[]              = 'Partial Index monitoring is currently paused';
								break;
							}
						}
					}
				}
			}

			if (!$isPartialIndexPaused){
				// Partial Index //
				$lastPartialIndexVariable = new Variable('lastPartialReindexFinish');
				if (!empty($lastPartialIndexVariable->N)){
					//Get the last time either a full or partial index finished
					$lastIndexFinishedWasFull = false;
					$lastIndexTime            = $lastPartialIndexVariable->value;
					if ($lastFullIndexVariable && $lastFullIndexVariable->value > $lastIndexTime){
						$lastIndexTime            = $lastFullIndexVariable->value;
						$lastIndexFinishedWasFull = true;
					}

					//Check to see if the last partial index finished more than PARTIAL_INDEX_INTERVAL_WARN seconds ago
					if ($lastIndexTime < ($currentTime - self::PARTIAL_INDEX_INTERVAL_WARN)){
						$status[] = ($lastIndexTime < ($currentTime - self::PARTIAL_INDEX_INTERVAL_CRITICAL)) ? self::STATUS_CRITICAL : self::STATUS_WARN;

						if ($lastIndexFinishedWasFull){
							$notes[] = 'Full Index last finished ' . date('m-d-Y H:i:s', $lastFullIndexVariable->value) . ' - ' . round(($currentTime - $lastPartialIndexVariable->value) / 60, 2) . ' minutes ago, and a new partial index hasn\'t completed since.';
						}else{
							$notes[] = 'Partial Index last finished ' . date('m-d-Y H:i:s', $lastPartialIndexVariable->value) . ' - ' . round(($currentTime - $lastPartialIndexVariable->value) / 60, 2) . ' minutes ago';
						}
					}
				}else{
					$status[] = self::STATUS_WARN;
					$notes[]  = 'Partial index has never been run';
				}
			}

			// Verify Actual Extraction of ILS records is happening.
			// That ils MARC records are in fact being updated.
			// This is for the case when all of the processes are running but new data isn't actually being delivered
			// so nothing in fact is being updated.
			require_once ROOT_DIR . '/sys/Extracting/IlsExtractInfo.php';
			$ilsExtractInfo = new IlsExtractInfo();
			$ilsExtractInfo->orderBy('lastExtracted DESC');
			$ilsExtractInfo->limit(1);
			// SELECT * FROM pika.ils_extract_info ORDER BY lastExtracted DESC LIMIT 1;
			//TODO: handling for multiple ILSes
			if ($ilsExtractInfo->find(true)){
				// Fetch the last updated MARC Record
				$lastExtractTime = $ilsExtractInfo->lastExtracted;
				if ($lastExtractTime < ($currentTime - self::LAST_EXTRACT_INTERVAL_WARN)){
					$status[] = ($lastExtractTime < ($currentTime - self::LAST_EXTRACT_INTERVAL_CRITICAL)) ? self::STATUS_CRITICAL : self::STATUS_WARN;
					$notes[]  = 'The last ILS record was extracted ' . round(($currentTime - ($lastExtractTime)) / 60, 2) . ' minutes ago';
				}
			}

			// Sierra Extract //
			global $configArray;
			if ($configArray['Catalog']['ils'] == 'Sierra'){
				$lastSierraExtractVariable = new Variable('last_sierra_extract_time');
				if (!empty($lastSierraExtractVariable->N)){
					//Check to see if the last sierra extract finished more than SIERRA_EXTRACT_INTERVAL_WARN seconds ago
					$lastSierraExtractTime = $lastSierraExtractVariable->value;
					if ($lastSierraExtractTime < ($currentTime - self::SIERRA_EXTRACT_INTERVAL_WARN)){
						$status[] = ($lastSierraExtractVariable->value < ($currentTime - self::SIERRA_EXTRACT_INTERVAL_CRITICAL)) ? self::STATUS_CRITICAL : self::STATUS_WARN;
						$notes[]  = 'Sierra Last Extract time  ' . date('m-d-Y H:i:s', $lastSierraExtractTime) . ' - ' . round(($currentTime - ($lastExtractTime)) / 60, 2) . ' minutes ago';
					}
				}else{
					$status[] = self::STATUS_WARN;
					$notes[]  = 'Sierra Extract has never been run';
				}

				//Sierra Export Remaining items
				$remainingSierraRecords = new Variable('remaining_sierra_records');
				if (!empty($remainingSierraRecords->N)){
					if ($remainingSierraRecords->value >= self::SIERRA_MAX_REMAINING_ITEMS_WARN){
						$notes[]  = "{$remainingSierraRecords->value} changed items remain to be processed from Sierra";
						$status[] = $remainingSierraRecords->value >= self::SIERRA_MAX_REMAINING_ITEMS_CRITICAL ? self::STATUS_CRITICAL : self::STATUS_WARN;
					}
				}
			}

			// Solr Restart //
			if ($configArray['Index']['engine'] == 'Solr'){
				$json = @file_get_contents($configArray['Index']['url'] . '/admin/cores');
				if (!empty($json)){
					$data = json_decode($json, true);

					$uptime        = $data['status']['grouped']['uptime'] / 1000;  // Grouped Index, puts uptime into seconds.
					$solrStartTime = strtotime($data['status']['grouped']['startTime']);
					if ($uptime >= self::SOLR_RESTART_INTERVAL_WARN){ // Grouped Index
						$status[] = ($uptime >= self::SOLR_RESTART_INTERVAL_CRITICAL) ? self::STATUS_CRITICAL : self::STATUS_WARN;
						$notes[]  = 'Solr Index (Grouped) last restarted ' . date('m-d-Y H:i:s', $solrStartTime) . ' - ' . round($uptime / 3600, 2) . ' hours ago';
					}

					$numRecords = $data['status']['grouped']['index']['numDocs'];

					$minNumRecordVariable = new Variable('solr_grouped_minimum_number_records');
					if (!empty($minNumRecordVariable->N)){
						$minNumRecords = $minNumRecordVariable->value;
						if (!empty($minNumRecords) && $numRecords < $minNumRecords){
							// Warn till more that 500 works below the limit
							$status[] = $numRecords < ($minNumRecords - 500) ? self::STATUS_CRITICAL : self::STATUS_WARN;
							$notes[]  = "Solr Index (Grouped) Record Count ($numRecords) in below the minimum ($minNumRecords)";
						}elseif ($numRecords > $minNumRecords + 10000){
							$status[] = self::STATUS_WARN;
							$notes[]  = "Solr Index (Grouped) Record Count ($numRecords) is more than 10,000 above the minimum ($minNumRecords)";
						}

					}else{
						$status[] = self::STATUS_WARN;
						$notes[]  = 'The minimum number of records for Solr Index (Grouped) has not been set.';
					}

				}else{
					$status[] = self::STATUS_CRITICAL;
					$notes[]  = 'Could not get status from Solr searcher core. Solr is down or unresponsive';
				}

				// Check that the indexing core is up
				$masterIndexUrl = str_replace('8080', $configArray['Reindex']['solrPort'], $configArray['Index']['url']) . '/admin/cores';
				$masterJson     = @file_get_contents($masterIndexUrl);
				if (!$masterJson){
					$status[] = self::STATUS_CRITICAL;
					$notes[]  = 'Could not get status from Solr indexer core. Solr is down or unresponsive';
				}


				// Count Number of Back-up Index Folders
				$solrSearcherPath = rtrim($configArray['Index']['local'], '/');
				$solrSearcherPath = str_replace('solr', 'solr_searcher/grouped/', $solrSearcherPath); // modify path to solr search grouped core path
				if (strpos($solrSearcherPath, 'grouped')){ // If we didn't make a good path, skip the rest of these checks
					$indexBackupDirectories    = glob($solrSearcherPath . 'index.*', GLOB_ONLYDIR);
					$numIndexBackupDirectories = count($indexBackupDirectories);
					if ($numIndexBackupDirectories >= 7){
						$status[] = self::STATUS_CRITICAL;
						$notes[]  = "There are $numIndexBackupDirectories Solr Searcher Grouped Index directories";
					}elseif ($numIndexBackupDirectories >= 4){
						$status[] = self::STATUS_WARN;
						$notes[]  = "There are $numIndexBackupDirectories Solr Searcher Grouped Index directories";
					}

				}
			}

			if (!empty($configArray['OverDrive']['url'])){
				// Checking that the url is set as a proxy for Overdrive being enabled

				// OverDrive Extract //
				$lastOverDriveExtractVariable = new Variable('last_overdrive_extract_time');
				if (!empty($lastOverDriveExtractVariable->N)){
					//Check to see if the last overdrive extract finished more than OVERDRIVE_EXTRACT_INTERVAL_WARN seconds ago
					$lastOverDriveExtractTime = $lastOverDriveExtractVariable->value;
					if ($lastOverDriveExtractTime < ($currentTime - self::OVERDRIVE_EXTRACT_INTERVAL_WARN)){
						$status[] = ($lastOverDriveExtractTime < ($currentTime - self::OVERDRIVE_EXTRACT_INTERVAL_CRITICAL)) ? self::STATUS_CRITICAL : self::STATUS_WARN;
						$notes[]  = 'OverDrive Extract last finished ' . date('m-d-Y H:i:s', $lastOverDriveExtractTime) . ' - ' . round(($currentTime - ($lastOverDriveExtractTime)) / 3600, 2) . ' hours ago';
					}
				}else{
					$status[] = self::STATUS_WARN;
					$notes[]  = 'OverDrive Extract has never been run';
				}

				// Overdrive extract errors
				require_once ROOT_DIR . '/sys/Log/OverDriveExtractLogEntry.php';
				$logEntry = new OverDriveExtractLogEntry();
				$logEntry->orderBy('id DESC');
				$logEntry->limit(1);
				if ($logEntry->find(true)){
					if ($logEntry->numErrors > 0){
						$status[] = self::STATUS_WARN;
						$notes[]  = "Last OverDrive Extract round had {$logEntry->numErrors} errors";
					}
				}

				// Check How Many Overdrive Items have been deleted in the last 24 hours
				$overdriveItems          = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
				$overdriveItems->deleted = true;
				$overdriveItems->whereAdd('dateDeleted > unix_timestamp(DATE_SUB(CURDATE(),INTERVAL 1 DAY) )');
				// where deleted = 1 and dateDeleted > unix_timestamp(DATE_SUB(CURDATE(),INTERVAL 1 DAY) )
				$deletedOverdriveItems = $overdriveItems->count();
				if ($deletedOverdriveItems !== false && $deletedOverdriveItems >= self::OVERDRIVE_DELETED_ITEMS_WARN){
					$notes[]  = "$deletedOverdriveItems Overdrive Items have been marked as deleted in the last 24 hours";
					$status[] = $deletedOverdriveItems >= self::OVERDRIVE_DELETED_ITEMS_CRITICAL ? self::STATUS_CRITICAL : self::STATUS_WARN;
				}

				// Check How Many Overdrive Products need to be extracted and haven't been processed yet.
				$overdriveProduct              = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
				$overdriveProduct->needsUpdate = 1;
				$overdriveProduct->deleted     = 0;
				$numOutstandingChanges         = $overdriveProduct->count();
				if (!empty($numOutstandingChanges) && $numOutstandingChanges >= self::OVERDRIVE_UNPROCESSED_ITEMS_WARN){
					$notes[]  = "$numOutstandingChanges Overdrive Items needing to be processed";
					$status[] = $numOutstandingChanges >= self::OVERDRIVE_UNPROCESSED_ITEMS_CRITICAL ? self::STATUS_CRITICAL : self::STATUS_WARN;
				}
			}

		}

		// Unprocessed Offline Circs //
		require_once ROOT_DIR . '/sys/Circa/OfflineCirculationEntry.php';
		$offlineCirculationEntry         = new OfflineCirculationEntry();
		$offlineCirculationEntry->status = 'Not Processed';
		$offlineCircs                    = $offlineCirculationEntry->count('id');
		if (!empty($offlineCircs)){
			$status[] = self::STATUS_CRITICAL;
			$notes[]  = "There are $offlineCircs un-processed offline circulation transactions";
		}

		// Unprocessed Offline Holds //
		require_once ROOT_DIR . '/sys/Circa/OfflineHold.php';
		$offlineHoldEntry         = new OfflineHold();
		$offlineHoldEntry->status = 'Not Processed';
		$offlineHolds             = $offlineHoldEntry->count('id');
		if (!empty($offlineHolds)){
			$status[] = self::STATUS_CRITICAL;
			$notes[]  = "There are $offlineHolds un-processed offline holds";
		}

		// Now that we have checked everything we are monitoring, consolidate the message and set the status to the most critical
		if (count($notes) > 0){
			$result = [
				'status'  => in_array(self::STATUS_CRITICAL, $status) ? self::STATUS_CRITICAL : self::STATUS_WARN, // Criticals trump Warnings;
				'message' => implode('; ', $notes),
			];
		}elseif ($fullIndexRunning){
			$result = [
				'status'  => self::STATUS_OK,
				'message' => "Monitoring Paused during full reindex.",
			];

		}else{
			$result = [
				'status'  => self::STATUS_OK,
				'message' => "Everything is current",
			];
		}

		if (isset($_REQUEST['prtg'])){
			// Reformat $result to the structure expected by PRTG

			$prtgStatusValues = [
				self::STATUS_OK       => 0,
				self::STATUS_WARN     => 1,
				self::STATUS_CRITICAL => 2,
			];

			$result = [
				'prtg' => [
					'result' => [
						0 => [
							'channel'         => 'Pika Status',
							'value'           => $prtgStatusValues[$result['status']],
							'limitmode'       => 1,
							'limitmaxwarning' => $prtgStatusValues[self::STATUS_OK],
							'limitmaxerror'   => $prtgStatusValues[self::STATUS_WARN],
						],
					],
					'text'   => $result['message'],
				],
			];
		}

		return $result;
	}

	/**
	 * Do a basic search and return results as a JSON array
	 */
	function search(){
		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Search/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		//setup the results array.
		$jsonResults = [];

		// Initialise from the current search globals
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList', $searchObject->getSortList());
		$interface->assign('rssLink', $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result->getMessage());
		}
		$timer->logTime('Process Search');

		// 'Finish' the search... complete timers and log search history.
		$searchObject->close();

		if ($searchObject->getResultTotal() < 1){
			// No record found
			$interface->setTemplate('list-none.tpl');
			$jsonResults['recordCount'] = 0;

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false){
				// If it's a parse error or the user specified an invalid field, we
				// should display an appropriate message:
				if (stristr($error, 'org.apache.lucene.queryParser.ParseException') ||
					preg_match('/^undefined field/', $error)){
					$jsonResults['parseError'] = true;

					// Unexpected error -- let's treat this as a fatal condition.
				}else{
					PEAR_Singleton::raiseError(new PEAR_Error('Unable to process query<br>' .
						'Solr Returned: ' . $error));
				}
			}

			$timer->logTime('no hits processing');

		}else{
			$timer->logTime('save search');

			// Assign interface variables
			$summary                    = $searchObject->getResultSummary();
			$jsonResults['recordCount'] = $summary['resultTotal'];
			$jsonResults['recordStart'] = $summary['startRecord'];
			$jsonResults['recordEnd']   = $summary['endRecord'];

			// Big one - our results
			$recordSet = $searchObject->getResultRecordSet();
			//Remove fields as needed to improve the display.
			foreach ($recordSet as $recordKey => $record){
				unset($record['auth_author']);
				unset($record['auth_authorStr']);
				unset($record['callnumber-first-code']);
				unset($record['spelling']);
				unset($record['callnumber-first']);
				unset($record['title_auth']);
				unset($record['callnumber-subject']);
				unset($record['author-letter']);
				unset($record['marc_error']);
				unset($record['title_fullStr']);
				unset($record['shortId']);
				$recordSet[$recordKey] = $record;
			}
			$jsonResults['recordSet'] = $recordSet;
			$timer->logTime('load result records');

			$facetSet                = $searchObject->getFacetList();
			$jsonResults['facetSet'] = $facetSet;

			//Check to see if a format category is already set
			$categorySelected = false;
			if (isset($facetSet['top'])){
				foreach ($facetSet['top'] as $title => $cluster){
					if ($cluster['label'] == 'Category'){
						foreach ($cluster['list'] as $thisFacet){
							if ($thisFacet['isApplied']){
								$categorySelected = true;
							}
						}
					}
				}
			}
			$jsonResults['categorySelected'] = $categorySelected;
			$timer->logTime('finish checking to see if a format category has been loaded already');

			// Process Paging
			$link                  = $searchObject->renderLinkPageTemplate();
			$options               = array(
				'totalItems' => $summary['resultTotal'],
				'fileName'   => $link,
				'perPage'    => $summary['perPage'],
			);
			$pager                 = new VuFindPager($options);
			$jsonResults['paging'] = array(
				'currentPage'  => $pager->pager->_currentPage,
				'totalPages'   => $pager->pager->_totalPages,
				'totalItems'   => $pager->pager->_totalItems,
				'itemsPerPage' => $pager->pager->_perPage,
			);
			$interface->assign('pageLinks', $pager->getLinks());
			$timer->logTime('finish hits processing');
		}

		// Report additional information after the results
		$jsonResults['query_time']          = round($searchObject->getQuerySpeed(), 2);
		$jsonResults['spellingSuggestions'] = $searchObject->getSpellingSuggestions();
		$jsonResults['lookfor']             = $searchObject->displayQuery();
		$jsonResults['searchType']          = $searchObject->getSearchType();
		// Will assign null for an advanced search
		$jsonResults['searchIndex'] = $searchObject->getSearchIndex();
		$jsonResults['time']        = round($searchObject->getTotalSpeed(), 2);
		// Show the save/unsave code on screen
		// The ID won't exist until after the search has been put in the search history
		//    so this needs to occur after the close() on the searchObject
		$jsonResults['showSaved']   = true;
		$jsonResults['savedSearch'] = $searchObject->isSavedSearch();
		$jsonResults['searchId']    = $searchObject->getSearchId();
		$currentPage                = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$jsonResults['page']        = $currentPage;


		if ($configArray['Statistics']['enabled'] && isset($_GET['lookfor']) && !is_array($_GET['lookfor'])){
			require_once ROOT_DIR . '/sys/Search/SearchStatNew.php';
			$searchStat = new SearchStatNew();
			$type       = isset($_GET['type']) ? strip_tags($_GET['type']) : 'Keyword';
			$searchStat->saveSearch(strip_tags($_GET['lookfor']), $type, $searchObject->getResultTotal());
		}

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		// Return the results for display to the user.
		return $jsonResults;
	}

	function getListWidget(){
		global $interface;
		if (isset($_REQUEST['username']) && isset($_REQUEST['password'])){
			$username = $_REQUEST['username'];
			$password = $_REQUEST['password'];
			$user     = UserAccount::validateAccount($username, $password);
			$interface->assign('user', $user);
		}else{
			$user = UserAccount::getLoggedInUser();
		}
		//Load the widget configuration
		require_once ROOT_DIR . '/sys/Widgets/ListWidget.php';
		require_once ROOT_DIR . '/sys/Widgets/ListWidgetList.php';
		require_once ROOT_DIR . '/sys/Widgets/ListWidgetListsLinks.php';
		$widget = new ListWidget();
		$id     = $_REQUEST['id'];

		if (isset($_REQUEST['reload'])){
			$interface->assign('reload', true);
		}else{
			$interface->assign('reload', false);
		}


		$widget->id = $id;
		if ($widget->find(true)){
			$interface->assign('widget', $widget);

			if (!empty($_REQUEST['resizeIframe']) || !empty($_REQUEST['resizeiframe'])){
				$interface->assign('resizeIframe', true);
			}
			//return the widget
			return $interface->fetch('ListWidget/listWidget.tpl');
		}
	}

	/**
	 * Retrieve the top 20 search terms by popularity from the search_stats table
	 * Enter description here ...
	 */
	function getTopSearches(){
		require_once ROOT_DIR . '/sys/Search/SearchStatNew.php';
		$numSearchesToReturn = isset($_REQUEST['numResults']) && ctype_digit($_REQUEST['numResults']) ? $_REQUEST['numResults'] : 20;
		$searchStats         = new SearchStatNew();
		$searchStats->query("SELECT phrase, numSearches AS numTotalSearches FROM `search_stats_new` WHERE phrase != '' ORDER BY numTotalSearches DESC LIMIT " . $numSearchesToReturn);
		$searches = [];
		while ($searchStats->fetch()){
			$searches[] = $searchStats->phrase;
		}
		return $searches;
	}

	function getRecordIdForTitle(){
		$title               = strip_tags($_REQUEST['title']);
		$_REQUEST['lookfor'] = $title;
		$_REQUEST['type']    = 'Keyword';

		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Search/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList', $searchObject->getSortList());
		$interface->assign('rssLink', $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result->getMessage());
		}

		if ($searchObject->getResultTotal() < 1){
			return "";
		}else{
			//Return the first result
			$recordSet = $searchObject->getResultRecordSet();
			foreach ($recordSet as $recordKey => $record){
				return $record['id'];
			}
		}
	}

	function getRecordIdForItemBarcode(){
		$barcode             = strip_tags($_REQUEST['barcode']);
		$_REQUEST['lookfor'] = $barcode;
		$_REQUEST['type']    = 'barcode';

		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Search/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList', $searchObject->getSortList());
		$interface->assign('rssLink', $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result->getMessage());
		}

		if ($searchObject->getResultTotal() < 1){
			return "";
		}else{
			//Return the first result
			$recordSet = $searchObject->getResultRecordSet();
			foreach ($recordSet as $recordKey => $record){
				return $record['id'];
			}
		}
	}

	function getTitleInfoForISBN(){
		$isbn                = str_replace('-', '', strip_tags($_REQUEST['isbn']));
		$_REQUEST['lookfor'] = $isbn;
		$_REQUEST['type']    = 'ISN';

		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Search/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		//setup the results array.
		$jsonResults = array();

		// Initialise from the current search globals
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList', $searchObject->getSortList());
		$interface->assign('rssLink', $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		/** @var SearchObject_Solr $searchObject */
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result->getMessage());
		}

		if ($searchObject->getResultTotal() >= 1){
			//Return the first result
			$recordSet = $searchObject->getResultRecordSet();
			foreach ($recordSet as $recordKey => $record){
				$jsonResults[] = array(
					'id'              => $record['id'],
					'title'           => isset($record['title']) ? $record['title'] : null,
					'author'          => isset($record['author']) ? $record['author'] : (isset($record['author2']) ? $record['author2'] : ''),
					'format'          => isset($record['format']) ? $record['format'] : '',
					'format_category' => isset($record['format_category']) ? $record['format_category'] : '',
				);
			}
		}
		return $jsonResults;
	}
}
