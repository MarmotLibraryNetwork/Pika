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

require_once ROOT_DIR . '/services/Union/Results.php';
require_once ROOT_DIR . '/sys/Pager.php';

class Search_Results extends Union_Results {

	protected $viewOptions = ['list', 'covers'];
	// define the valid view modes checked in Base.php

	function launch() {
		global $interface;
		global $configArray;
		global $timer;
		global $memoryWatcher;
		global $library;

		/** @var string $searchSource */
		$searchSource = empty($_REQUEST['searchSource']) ? 'local' : $_REQUEST['searchSource'];

		if (isset($_REQUEST['replacementTerm'])){
			$replacementTerm     = $_REQUEST['replacementTerm'];
			$oldTerm             = $_REQUEST['lookfor'];
			$oldSearchUrl        = $_SERVER['REQUEST_URI'];
			$oldSearchUrl        = str_replace('replacementTerm=' . urlencode($replacementTerm), 'disallowReplacements', $oldSearchUrl);
			$_GET['lookfor']     = $replacementTerm;
			$_REQUEST['lookfor'] = $replacementTerm;
			$interface->assign('replacementTerm', $replacementTerm);
			$interface->assign('oldTerm', $oldTerm);
			$interface->assign('oldSearchUrl', $oldSearchUrl);
		}

		$interface->assign('showDplaLink', false);
		if ($configArray['DPLA']['enabled']){
			if ($library->includeDplaResults){
				$interface->assign('showDplaLink', true);
			}
		}

		// Set Show in Search Results Main Details Section options for template
		// (needs to be set before moreDetailsOptions)
		global $library;
		foreach ($library->showInSearchResultsMainDetails as $detailoption) {
			$interface->assign($detailoption, true);
		}


		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Search/Solr.php';
		$timer->logTime('Include search engine');
		$memoryWatcher->logMemory('Include search engine');

		// Cannot use the current search globals since we may change the search term above
		// Display of query is not right when reusing the global search object
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init($searchSource);
		$searchObject->setPrimarySearch(true);
		$timer->logTime('Init Search Object');
		$memoryWatcher->logMemory('Init Search Object');
//		$searchObject->viewOptions = $this->viewOptions; // set valid view options for the search object

		// Build RSS Feed for Results (if requested)
		$this->processAlternateOutputs($searchObject);

		$displayMode = $searchObject->getView();
		if ($displayMode == 'covers') {
			$searchObject->setLimit(24); // a set of 24 covers looks better in display
		}

		$this->processAllRangeFilters($searchObject);

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed

		// Hide Covers when the user has set that setting on the Search Results Page
		$this->setShowCovers();

		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());
		$interface->assign('excelLink',  $searchObject->getExcelUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}
		$timer->logTime('Process Search');
		$memoryWatcher->logMemory('Process Search');

		// Some more variables
		//   Those we can construct AFTER the search is executed, but we need
		//   no matter whether there were any results
		$displayQuery = $searchObject->displayQuery();
		$interface->assign('qtime',               round($searchObject->getQuerySpeed(), 2));
		$interface->assign('lookfor',             $displayQuery);
		$interface->assign('searchType',          $searchObject->getSearchType());
		// Will assign null for an advanced search
		$searchIndex = $searchObject->getSearchIndex();
		$interface->assign('searchIndex', $searchIndex);

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

		//Enable and disable functionality based on library settings
		//This must be done before we process each result
		$interface->assign('showNotInterested', false);

		$showRatings = 1;
		$enableProspectorIntegration = $configArray['Content']['Prospector'] ?? false;
		if (isset($library)){
			$enableProspectorIntegration = ($library->enableProspectorIntegration == 1);
			$showRatings                 = $library->showRatings;
		}
		if ($enableProspectorIntegration){
			$interface->assign('showProspectorLink', true);
			$interface->assign('prospectorSavedSearchId', $searchObject->getSearchId());
		}else{
			$interface->assign('showProspectorLink', false);
		}
		$interface->assign('showRatings', $showRatings);

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		// No Results Actions //
		if ($searchObject->getResultTotal() < 1) {
			require_once ROOT_DIR . '/sys/Search/SearchSuggestions.php';
			$searchSuggestions = new SearchSuggestions();
			$commonSearches    = $searchSuggestions->getSpellingSearches($displayQuery);
			$suggestions       = [];
			foreach ($commonSearches as $commonSearch){
				$suggestions[$commonSearch['phrase']] = '/Search/Results?lookfor=' . urlencode($commonSearch['phrase']);
			}
			$interface->assign('spellingSuggestions', $suggestions);

			//We didn't find anything.  Look for search Suggestions
			//Don't try to find suggestions if facets were applied
			$autoSwitchSearch     = false;
			$disallowReplacements = isset($_REQUEST['disallowReplacements']) || isset($_REQUEST['replacementTerm']);
			if (!$disallowReplacements && (!isset($facetSet) || count($facetSet) == 0)){
				//We can try to find a suggestion, but only if we are not doing a phrase search.
				if (strpos($displayQuery, '"') === false){
					$searchSuggestions = new SearchSuggestions();
					$commonSearches    = $searchSuggestions->getCommonSearchesMySql($displayQuery, $searchIndex);

					//assign here before we start popping stuff off
					$interface->assign('searchSuggestions', $commonSearches);

					//If the first search in the list is used 10 times more than the next, just show results for that
					$allSuggestions = $searchSuggestions->getAllSuggestions($displayQuery, $searchIndex);
					$numSuggestions = count($allSuggestions);
					if ($numSuggestions == 1){
						$firstSearch      = array_pop($allSuggestions);
						$autoSwitchSearch = true;
					}elseif ($numSuggestions >= 2){
						$firstSearch         = array_shift($allSuggestions);
						$secondSearch        = array_shift($allSuggestions);
						$firstTimesSearched  = $firstSearch['numSearches'];
						$secondTimesSearched = $secondSearch['numSearches'];
						if ($secondTimesSearched > 0 && $firstTimesSearched / $secondTimesSearched > 10){ // avoids division by zero
							$autoSwitchSearch = true;
						}
					}

					//Check to see if the library does not want automatic search replacements
					if (!$library->allowAutomaticSearchReplacements){
						$autoSwitchSearch = false;
					}

					// Switch to search with a better search term //
					if ($autoSwitchSearch){
						//Get search results for the new search
						// The above assignments probably do nothing when there is a redirect below
						$thisUrl = $_SERVER['REQUEST_URI'] . "&replacementTerm=" . urlencode($firstSearch['phrase']);
						header("Location: " . $thisUrl);
						exit();
					}
				}
			}

			// No record found
			$interface->assign('recordCount', 0);

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false) {
				$this->displaySolrError($error);
			}

			$timer->logTime('no hits processing');

		}
		// Exactly One Result for an id search //
		elseif ($searchObject->getResultTotal() == 1 && (strpos($displayQuery, 'id:') === 0 || $searchObject->getSearchType() == 'id')){
			//Redirect to the home page for the record
			$recordSet = $searchObject->getResultRecordSet();
			$record = reset($recordSet);
			$_SESSION['searchId'] = $searchObject->getSearchId();
			if ($record['recordtype'] == 'list'){
				$listId = substr($record['id'], 4);
				header("Location: /MyAccount/MyList/{$listId}");
				exit();
			}else{
				header("Location: /GroupedWork/{$record['id']}/Home");
				exit();
			}

		}
		else {
			$timer->logTime('save search');

			// Assign interface variables
			$summary = $searchObject->getResultSummary();
			$interface->assign('recordCount', $summary['resultTotal']);
			$interface->assign('recordStart', $summary['startRecord']);
			$interface->assign('recordEnd',   $summary['endRecord']);
			$memoryWatcher->logMemory('Get Result Summary');

			$facetSet = $searchObject->getFacetList();
			$interface->assign('facetSet', $facetSet);
			$memoryWatcher->logMemory('Get Facet List');

			//Check to see if a format category is already set
			$categorySelected = false;
			if (isset($facetSet['top'])){
				foreach ($facetSet['top'] as $cluster){
					if ($cluster['label'] == 'Category'){
						foreach ($cluster['list'] as $thisFacet){
							if ($thisFacet['isApplied']){
								$categorySelected = true;
								break;
							}
						}
					}
					if ($categorySelected) break;
				}
			}
			$interface->assign('categorySelected', $categorySelected);
			$timer->logTime('load selected category');
		}

		// What Mode will search results be Displayed In //
		if ($displayMode == 'covers'){
			$displayTemplate = 'Search/covers-list.tpl'; // structure for bookcover tiles
		} else{ // default
			$displayTemplate = 'Search/list-list.tpl'; // structure for regular results
			$displayMode     = 'list'; // In case the view is not explicitly set, do so now for display & clients-side functions

			// Process Paging (only in list mode)
			if ($searchObject->getResultTotal() > 1){
				$link    = $searchObject->renderLinkPageTemplate();
				$options = [
					'totalItems' => $summary['resultTotal'],
					'fileName'   => $link,
					'perPage'    => $summary['perPage']
				];
				$pager   = new VuFindPager($options);
				$interface->assign('pageLinks', $pager->getLinks());
			}
		}
		$timer->logTime('finish hits processing');

		$interface->assign('subpage', $displayTemplate);
		$interface->assign('displayMode', $displayMode); // For user toggle switches

		// Big one - our results //
		$recordSet = $searchObject->getResultRecordHTML();
		$interface->assign('recordSet', $recordSet);
		$timer->logTime('load result records');
		$memoryWatcher->logMemory('load result records');

		//Setup explore more
		$showExploreMoreBar = $currentPage > 1 ? false : true;
		$exploreMore        = new ExploreMore();
		$exploreMoreSearchTerm = $exploreMore->getExploreMoreQuery();
		$interface->assign('exploreMoreSection', 'catalog');
		$interface->assign('showExploreMoreBar', $showExploreMoreBar);
		$interface->assign('exploreMoreSearchTerm', $exploreMoreSearchTerm);

		if ($configArray['Statistics']['enabled'] && isset( $_GET['lookfor']) && !is_array($_GET['lookfor'])) {
			require_once ROOT_DIR . '/sys/Search/SearchStatNew.php';
			$searchStat = new SearchStatNew();
			$searchStat->saveSearch(strip_tags($_GET['lookfor']), $searchObject->getResultTotal());
		}

		$interface->assign('sectionLabel', 'Library Catalog');
		// Done, display the page
		$this->display($searchObject->getResultTotal() ? 'list.tpl' : 'list-none.tpl', $this->setPageTitle($displayQuery), 'Search/results-sidebar.tpl');
	} // End launch()

}
