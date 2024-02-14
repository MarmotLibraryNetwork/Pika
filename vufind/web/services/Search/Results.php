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

class Search_Results extends Union_Results {

	protected $viewOptions = ['list', 'covers'];
	// define the valid view modes checked in Base.php

	function launch(){
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
		foreach ($library->showInSearchResultsMainDetails as $detailOption){
			$interface->assign($detailOption, true);
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
		if ($displayMode == 'covers'){
			$searchObject->setLimit(24); // a set of 24 covers looks better in display
		}

		// Range filters need special processing in order to be used
		$searchObject->processAllRangeFilters();

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
		if (PEAR_Singleton::isError($result)){
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
		$interface->assign('topRecommendations', $searchObject->getRecommendationsTemplates('top'));
		$interface->assign('sideRecommendations', $searchObject->getRecommendationsTemplates('side'));

		// 'Finish' the search... complete timers and log search history.
		$searchObject->close();
		$interface->assign('time', round($searchObject->getTotalSpeed(), 2));
		// Show the save/unsave code on screen
		// The ID won't exist until after the search has been put in the search history
		//    so this needs to occur after the close() on the searchObject
		$interface->assign('showSaved', true);
		$interface->assign('savedSearch', $searchObject->isSavedSearch());
		$interface->assign('searchId', $searchObject->getSearchId());
		$currentPage = isset($_REQUEST['page']) && ctype_digit($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$interface->assign('page', $currentPage);

		//Enable and disable functionality based on library settings
		//This must be done before we process each result
		$interface->assign('showNotInterested', false);

		$showRatings                 = $library->showRatings ?? 1;
		$enableProspectorIntegration = $library->enableProspectorIntegration ?? $configArray['Content']['Prospector'] ?? false;
		if ($enableProspectorIntegration){
			$interface->assign('showProspectorLink', true);
			$interface->assign('prospectorSavedSearchId', $searchObject->getSearchId());
		}else{
			$interface->assign('showProspectorLink', false);
		}
		$interface->assign('showRatings', $showRatings);

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session, so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		// No Results Actions //
		if ($searchObject->getResultTotal() < 1){

			if (in_array($searchIndex, $searchObject->getTextLeftSearchIndexes()) && strlen($displayQuery) > 35){
				// Searches using solr fields of type text-left will only return matches for search phrases less than 37 characters long
				$searchTypeLabel = $searchObject->getBasicTypes()[$searchIndex];
				$interface->assign('leftTextSearchWarning', $searchTypeLabel . ' search phrases can only be a maximum of 35 characters long.  Please shorten the search phrase.');
			}else{

				if (isset($_REQUEST['replacementTerm'])){
					// The automatic search phrase had no results, so was a bad suggestion, go right back to the original.
					global $pikaLogger;
					$pikaLogger->notice("Replacement search term also had no results.", ['original' => $oldTerm, 'replacement' => $replacementTerm]);

					header("Location: " . $oldSearchUrl);
					exit();
				}

				if (empty($_REQUEST['filter']) && strpos($displayQuery, '"') === false){
				// Only show search and spelling suggestions if no facets have been applied to a search and there isn't a quoted phrase search.

					require_once ROOT_DIR . '/sys/Search/SearchSuggestions.php';
					$spellingSuggestions    = SearchSuggestions::getSpellingSearches($displayQuery, false);
					$hasSpellingSuggestions = !empty($spellingSuggestions);
					if ($hasSpellingSuggestions){
						$spellingWordSearchURLs = [];
						foreach ($spellingSuggestions as $spellingSuggestion){
							$spellingWordSearchURLs[$spellingSuggestion['phrase']] = $searchObject->renderLinkWithReplacedTerm($_REQUEST['lookfor'], $spellingSuggestion['phrase']);
//							$spellingWordSearchURLs[$spellingSuggestion['phrase']] = '/Search/Results?lookfor=' . urlencode($spellingSuggestion['phrase']);
						}
						$interface->assign('spellingSuggestions', $spellingWordSearchURLs);
					}

					$commonSearches = SearchSuggestions::getCommonSearchesMySql($displayQuery, !$hasSpellingSuggestions);
					// don't use sort key when there are spelling suggestions
					$interface->assign('searchSuggestions', $commonSearches);
					//assign here for the template before doing the automatic term replacement determination

					if ($library->allowAutomaticSearchReplacements && !in_array($searchIndex, SearchSuggestions::$disallowedSearchTypesForTermReplacement)){
						$disallowReplacements = isset($_REQUEST['disallowReplacements']) || isset($_REQUEST['replacementTerm']);
						if (!$disallowReplacements){
							if ($hasSpellingSuggestions){
								//Add spelling suggestions to the search suggestions array
								// (Both arrays should have cleaned search terms as the array index)
								foreach ($spellingSuggestions as $key => $suggestion){
									if (!array_key_exists($key, $commonSearches)){
										$commonSearches[$key] = $suggestion;
									}
								}
							}

							$numSuggestions = count($commonSearches);
							if ($numSuggestions){
								$autoSwitchSearch = false;
								if ($numSuggestions == 1){
									$firstSearch      = reset($commonSearches);
									$autoSwitchSearch = true;
								}elseif ($numSuggestions >= 2){
									// If the first search in the list is used 10 times more than the second term, just show results for the first term

									if ($hasSpellingSuggestions){
										// Now that we are here, sort by the array by the number of searches (which is sortKey)
										$array = [];
										foreach ($commonSearches as $suggestion){
											$array[$suggestion['sortKey']] = $suggestion;
										}
										krsort($array);
										$commonSearches = $array;
									}

									$firstSearch         = reset($commonSearches);
									$secondSearch        = next($commonSearches);
									$firstTimesSearched  = $firstSearch['numSearches'];
									$secondTimesSearched = $secondSearch['numSearches'];
									if ($secondTimesSearched > 0 && $firstTimesSearched / $secondTimesSearched > 10){ // avoids division by zero
										$autoSwitchSearch = true;
									}
								}

								// Switch to search with a better search term //
								if ($autoSwitchSearch){
									//Get search results for the new search
									// The above assignments probably do nothing when there is a redirect below
									$thisUrl = $_SERVER['REQUEST_URI'] . '&replacementTerm=' . urlencode($firstSearch['phrase']);
									header('Location: ' . $thisUrl);
									exit();
								}
							}
						}
					}
				}

				// No record found
				$interface->assign('recordCount', 0);  // Was the empty result set due to an error?
				$error = $searchObject->getIndexError();
				if ($error !== false){
					$this->displaySolrError($error);
				}
				$timer->logTime('no hits processing');
			}

		}else{
			$timer->logTime('save search');

			// Assign interface variables
			$summary = $searchObject->getResultSummary();
			$interface->assign('recordCount', $summary['resultTotal']);
			$interface->assign('recordStart', $summary['startRecord']);
			$interface->assign('recordEnd', $summary['endRecord']);
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
					if ($categorySelected){
						break;
					}
				}
			}
			$interface->assign('categorySelected', $categorySelected);
			$timer->logTime('load selected category');
		}

		if ($displayMode != 'covers'){
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

		$interface->assign('subpage', $searchObject->getDisplayTemplate());
		$interface->assign('displayMode', $displayMode); // For user toggle switches

		// Big one - our results //
		$recordSet = $searchObject->getResultRecordHTML();
		$interface->assign('recordSet', $recordSet);
		$timer->logTime('load result records');
		$memoryWatcher->logMemory('load result records');

		//Setup explore more
		$showExploreMoreBar    = ($currentPage == 1);
		$exploreMore           = new ExploreMore();
		$exploreMoreSearchTerm = $exploreMore->getExploreMoreQuery();
		$interface->assign('exploreMoreSection', 'catalog');
		$interface->assign('showExploreMoreBar', $showExploreMoreBar);
		$interface->assign('exploreMoreSearchTerm', $exploreMoreSearchTerm);

		if ($configArray['Statistics']['enabled'] && isset($_GET['lookfor']) && !is_array($_GET['lookfor'])){
			require_once ROOT_DIR . '/sys/Search/SearchStatNew.php';
			$searchStat = new SearchStatNew();
			$searchStat->saveSearch(strip_tags($_GET['lookfor']), $searchObject->getResultTotal());
		}

		$interface->assign('sectionLabel', 'Library Catalog');
		// Done, display the page
		$this->display($searchObject->getResultTotal() ? 'list.tpl' : 'list-none.tpl', $this->setPageTitle($displayQuery), 'Search/results-sidebar.tpl');
	} // End launch()

}
