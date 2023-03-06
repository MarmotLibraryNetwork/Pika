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

class Genealogy_Results extends Union_Results {

	function launch(){
		global $interface;
		global $configArray;
		global $timer;
		$user = UserAccount::getLoggedInUser();

		//Check to see if a user is logged in with admin permissions
		if (UserAccount::isLoggedIn() && UserAccount::userHasRole('genealogyContributor')){
			$interface->assign('userIsAdmin', true);
		}else{
			$interface->assign('userIsAdmin', false);
		}

//		$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';

		// Initialise from the current search globals
		/** @var SearchObject_Genealogy $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject($configArray['Genealogy']['searchObject']);
		$searchObject->init();
		$searchObject->setPrimarySearch(true);

		$this->processAlternateOutputs($searchObject);

		// Range filters need special processing in order to be used
		$searchObject->processAllRangeFilters();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
//		$interface->setPageTitle('Search Results');  //TODO: may be for setting the title if there was error raised or exception thrown??
		$interface->assign('sortList', $searchObject->getSortList());
		$interface->assign('rssLink', $searchObject->getRSSUrl());
		$interface->assign('excelLink', $searchObject->getExcelUrl());


		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result->getMessage());
		}
		$timer->logTime('Process Search');

		// Some more variables
		//   Those we can construct AFTER the search is executed, but we need
		//   no matter whether there were any results
		$displayQuery = $searchObject->displayQuery();
		$interface->assign('qtime', round($searchObject->getQuerySpeed(), 2));
		$interface->assign('spellingSuggestions', $searchObject->getSpellingSuggestions());
		$interface->assign('lookfor', $displayQuery);
		$interface->assign('searchType', $searchObject->getSearchType());
		// Will assign null for an advanced search
		$interface->assign('searchIndex', $searchObject->getSearchIndex());

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
		$currentPage = $_REQUEST['page'] ?? 1;
		$interface->assign('page', $currentPage);

		if ($searchObject->getResultTotal() < 1){
			// No record found
			$interface->assign('subpage', 'Genealogy/list-none.tpl');
			$interface->setTemplate('Genealogy/list.tpl');
			$interface->assign('recordCount', 0);

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false){
				$this->displaySolrError($error);
			}

			$timer->logTime('no hits processing');

		}else{
			$timer->logTime('save search');

			// Assign interface variables
			$summary = $searchObject->getResultSummary();
			$interface->assign('recordCount', $summary['resultTotal']);
			$interface->assign('recordStart', $summary['startRecord']);
			$interface->assign('recordEnd', $summary['endRecord']);

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false) {
				$this->displaySolrError($error);
			}

			$facetSet = $searchObject->getFacetList();
			$interface->assign('facetSet', $facetSet);

			// Big one - our results
			$recordSet = $searchObject->getResultRecordHTML();
			$interface->assign('recordSet', $recordSet);
			$timer->logTime('load result records');

			// Setup Display
			$interface->assign('subpage', 'Genealogy/list-list.tpl');
			$interface->setTemplate('Genealogy/list.tpl');

			// Process Paging
			$link    = $searchObject->renderLinkPageTemplate();
			$options = [
				'totalItems' => $summary['resultTotal'],
				'fileName'   => $link,
				'perPage'    => $summary['perPage']
			];
			$pager   = new VuFindPager($options);
			$interface->assign('pageLinks', $pager->getLinks());
			$timer->logTime('finish hits processing');
		}

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		// Done, display the page
		$interface->assign('sectionLabel', 'Genealogy Database');
		$this->display($searchObject->getResultTotal() ? 'list.tpl' : 'list-none.tpl', $this->setPageTitle($displayQuery), 'Search/results-sidebar.tpl');
	} // End launch()
}
