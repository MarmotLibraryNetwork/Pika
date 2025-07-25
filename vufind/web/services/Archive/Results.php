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

require_once ROOT_DIR . '/services/Union/Results.php';
require_once ROOT_DIR . '/sys/Pager.php';

class Archive_Results extends Union_Results {

	function launch(){
		global $interface;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Search/Solr.php';
		$timer->logTime('Include search engine');

		// Initialise from the current search globals
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setPrimarySearch(true);
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");

		$this->processAlternateOutputs($searchObject);

		$displayMode = $searchObject->getView();
		if ($displayMode == 'covers') {
			$searchObject->setLimit(24); // a set of 24 covers looks better in display
		}

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
//		$interface->setPageTitle('Archive Search Results');
		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());
		$interface->assign('excelLink',  $searchObject->getExcelUrl());

		// Hide Covers when the user has set that setting on the Search Results Page
		$this->setShowCovers();

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}
		$timer->logTime('Process Search');

		// Some more variables
		//   Those we can construct AFTER the search is executed, but we need
		//   no matter whether there were any results
		$interface->assign('qtime',               round($searchObject->getQuerySpeed(), 2));
		$interface->assign('spellingSuggestions', $searchObject->getSpellingSuggestions());
		$interface->assign('lookfor',             $searchObject->displayQuery());
		$interface->assign('searchType',          $searchObject->getSearchType());
		// Will assign null for an advanced search
		$interface->assign('searchIndex',         $searchObject->getSearchIndex());

		// We'll need recommendations no matter how many results we found:
		$interface->assign('topRecommendations',
		$searchObject->getRecommendationsTemplates('top'));
		$interface->assign('sideRecommendations',
		$searchObject->getRecommendationsTemplates('side'));

		// 'Finish' the search... complete timers and log search history.
		$searchObject->close();
		$interface->assign('time', round($searchObject->getTotalSpeed(), 2));
		// Show the save/unsave code on screen
		// The ID won't exist until after the search has been put in the search history
		//    so this needs to occur after the close() on the searchObject
		$interface->assign('showSaved',   true);
		$interface->assign('savedSearch', $searchObject->isSavedSearch());
		$interface->assign('searchId',    $searchObject->getSearchId());
		$currentPage = $_REQUEST['page'] ?? 1;
		$interface->assign('page', $currentPage);

		if ($searchObject->getResultTotal() < 1) {
			// No record found
			$interface->assign('subpage', 'Archive/list-none.tpl');
			$interface->setTemplate('list.tpl');
			$interface->assign('recordCount', 0);

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false) {
				// If it's a parse error or the user specified an invalid field, we
				// should display an appropriate message:
				if (is_array($error)){
					$errorMessage = reset($error);
				}else{
					$errorMessage = $error;
				}
				if (stristr($errorMessage, 'org.apache.lucene.queryParser.ParseException') ||
				preg_match('/^undefined field/', $errorMessage)) {
					$interface->assign('parseError', true);

					// Unexpected error -- let's treat this as a fatal condition.
				} else {
					PEAR_Singleton::raiseError(new PEAR_Error('Unable to process query<br>' .
                        'Solr Returned: ' . $errorMessage));
				}
			}

			$timer->logTime('no hits processing');

		} else {
			$timer->logTime('save search');

			// Assign interface variables
			$summary = $searchObject->getResultSummary();
			$interface->assign('recordCount', $summary['resultTotal']);
			$interface->assign('recordStart', $summary['startRecord']);
			$interface->assign('recordEnd',   $summary['endRecord']);

			$facetSet = $searchObject->getFacetList();
			$interface->assign('facetSet',       $facetSet);

			// Big one - our results
			$recordSet = $searchObject->getResultRecordHTML();
			$interface->assign('recordSet', $recordSet);
			$timer->logTime('load result records');

			// Setup Display
			if ($displayMode == 'covers') {
				$displayTemplate = 'Archive/covers-list.tpl'; // structure for bookcover tiles
			}else{
				$displayTemplate = 'Archive/list-list.tpl'; // structure for regular results
				$displayMode = 'list'; // In case the view is not explicitly set, do so now for display & clients-side functions
				// Process Paging
				$link = $searchObject->renderLinkPageTemplate();
				$options = [
					'totalItems' => $summary['resultTotal'],
					'fileName'   => $link,
					'perPage'    => $summary['perPage']
				];
				$pager = new VuFindPager($options);
				$interface->assign('pageLinks', $pager->getLinks());
			}

			$timer->logTime('finish hits processing');
			$interface->assign('subpage', $displayTemplate);
		}

		$interface->assign('displayMode', $displayMode); // For user toggle switches

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		// clear Exhibit Navigation
		if (!empty($_SESSION['ExhibitContext'])) {
			unset($_SESSION['ExhibitContext'], $_SESSION['placePid'], $_SESSION['placeLabel']);
		}

		//Setup explore more
		$showExploreMoreBar = true;
		if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1){
			$showExploreMoreBar = false;
		}
		$exploreMore = new ExploreMore();
		$exploreMoreSearchTerm = $exploreMore->getExploreMoreQuery();
		$interface->assign('exploreMoreSection', 'archive');
		$interface->assign('showExploreMoreBar', $showExploreMoreBar);
		$interface->assign('exploreMoreSearchTerm', $exploreMoreSearchTerm);

		// Done, display the page
		$interface->assign('sectionLabel', 'Local Digital Archive Results');
		$this->display($searchObject->getResultTotal() ? 'list.tpl' : 'list-none.tpl','Archive Search Results', 'Search/results-sidebar.tpl');
	} // End launch()
}
