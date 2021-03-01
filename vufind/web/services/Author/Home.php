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
require_once ROOT_DIR . '/sys/NovelistFactory.php';

class Author_Home extends Union_Results {

	function launch(){
		global $configArray;
		global $interface;
		global $library;

		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
//		$searchObject->viewOptions = $this->viewOptions; // set valid view options for the search object
		$searchObject->init();

		$this->processAlternateOutputs($searchObject);

		$this->processAllRangeFilters($searchObject);

		$displayMode = $searchObject->getView();
		if ($displayMode == 'covers') {
			$searchObject->setLimit(24); // a set of 24 covers looks better in display
		}


		$interface->caching = false;

		if (!isset($_GET['author'])){
			PEAR_Singleton::raiseError(new PEAR_Error('Unknown Author'));
		}


		// Retrieve User Search History -- note that we only want to offer a
		// "back to search" link if the saved URL is not for the current action;
		// when users first reach this page from search results, the "last URL"
		// will be their original search, which we want to link to.  However,
		// since this module will later set the "last URL" value in order to
		// allow the user to return from a record view to this page, after they
		// return here, we will no longer have access to the last non-author
		// search, and it is better to display nothing than to provide an infinite
		// loop of links.  Perhaps this can be solved more elegantly with a stack
		// or with multiple session variables, but for now this seems okay.
		$interface->assign('lastsearch', (isset($_SESSION['lastSearchURL']) && !strstr($_SESSION['lastSearchURL'], 'Author/Home')) ? $_SESSION['lastSearchURL'] : false);

		$interface->assign('lookfor', $_GET['author']);
		$interface->assign('basicSearchIndex', 'Author');
		$interface->assign('searchIndex', 'Author');

		// Clean up author string
		$author = strip_tags($_GET['author']);
		if (is_array($author)){
			$author = array_pop($author);
		}

		$author = trim(str_replace('"', '', $author));
		if (substr($author, strlen($author) - 1, 1) == ','){
			$author = substr($author, 0, strlen($author) - 1);
		}
		$authorRaw           = $author;
		$wikipediaAuthorName = $author;
		$author              = explode(',', $author);
		$interface->assign('author', $author);

		// Create First Name
		$firstName = '';
		if (isset($author[1])){
			$firstName = $author[1];

			if (isset($author[2])){
				// Remove punctuation
				if ((strlen($author[2]) > 2) && (substr($author[2], -1) == '.')){
					$author[2] = substr($author[2], 0, -1);
				}
			}
		}

		// Remove dates
		$firstName = preg_replace('/[0-9]+-[0-9]*/', '', $firstName);

		// Build Author name to display.
		if (substr($firstName, -3, 1) == ' '){
			// Keep period after initial
			$authorName = $firstName . ' ';
		}elseif ((substr(trim($firstName), -1) == ',') ||
			(substr(trim($firstName), -1) == '.')){
			// No initial so strip any punctuation from the end
			$authorName = substr(trim($firstName), 0, -1) . ' ';
		}else{
			$authorName = $firstName . ' ';
		}
		$authorName .= $author[0];
		$interface->assign('authorName', trim($authorName));

		// Pull External Author Content
		$interface->assign('showWikipedia', false);
		if ($searchObject->getPage() == 1){
			// Only load Wikipedia info if turned on in config file:
			if (!empty($configArray['Content']['authors'])
				&& stristr($configArray['Content']['authors'], 'wikipedia')
				&& (!$library || $library->showWikipediaContent == 1)
			){

				$interface->assign('showWikipedia', true);

				//Strip anything in parenthesis
				if (strpos($wikipediaAuthorName, '(') > 0){
					$wikipediaAuthorName = substr($wikipediaAuthorName, 0, strpos($wikipediaAuthorName, '('));
				}
				$wikipediaAuthorName = trim($wikipediaAuthorName);
				$interface->assign('wikipediaAuthorName', $wikipediaAuthorName);
			}
		}

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->assign('sortList', $searchObject->getSortList());
		$interface->assign('limitList', $searchObject->getLimitList());
		$interface->assign('viewList', $searchObject->getViewList());
		$interface->assign('rssLink', $searchObject->getRSSUrl());
		$interface->assign('excelLink',  $searchObject->getExcelUrl());
		$interface->assign('filterList', $searchObject->getFilterList());

		$this->setShowCovers();

		// Process Search
		/** @var PEAR_Error|null $result */
		$result = $searchObject->processSearch(false, true);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result->getMessage());
		}

		// Some more variables
		//   Those we can construct AFTER the search is executed, but we need
		//   no matter whether there were any results
		$interface->assign('qtime', round($searchObject->getQuerySpeed(), 2));

		// Assign interface variables
		$summary = $searchObject->getResultSummary();
		$interface->assign('recordCount', $summary['resultTotal']);
		$interface->assign('recordStart', $summary['startRecord']);
		$interface->assign('recordEnd', $summary['endRecord']);

		$interface->assign('sideRecommendations', $searchObject->getRecommendationsTemplates('side'));
		$interface->assign('topRecommendations', $searchObject->getRecommendationsTemplates('top'));

		// Big one - our results
		$authorTitles = $searchObject->getResultRecordHTML();
		$interface->assign('recordSet', $authorTitles);
		$template = $searchObject->getDisplayTemplate();
		$interface->assign('resultsTemplate', $template);

		// 'Finish' the search... complete timers and log search history.
		$searchObject->close();
		$interface->assign('time', round($searchObject->getTotalSpeed(), 2));
		// Show the save/unsave code on screen
		// The ID won't exist until after the search has been put in the search history
		//    so this needs to occur after the close() on the searchObject
		$interface->assign('showSaved',   true);
		$interface->assign('savedSearch', $searchObject->isSavedSearch());
		$interface->assign('searchId',    $searchObject->getSearchId());

		//Load similar author information.
		$groupedWorkId = null;
		$workIsbns     = [];
		foreach ($searchObject->getResultRecordSet() as $title){
			$groupedWorkId = $title['id'];
			if (isset($title['isbn'])){
				if (is_array($title['isbn'])){
					$workIsbns = $title['isbn'];
				}else{
					$workIsbns[] = $title['isbn'];
				}

				if (count($workIsbns) > 0){
					break;
				}
			}
		}

		if (count($workIsbns) > 0){
			//Make sure to trim off any format information from the ISBN
			$novelist               = NovelistFactory::getNovelist();
			$enrichment['novelist'] = $novelist->getSimilarAuthors($groupedWorkId, $workIsbns);
			if ($enrichment){
				$interface->assign('enrichment', $enrichment);
			}
		}

		// Process Paging
		$link    = $searchObject->renderLinkPageTemplate();
		$options = [
			'totalItems' => $summary['resultTotal'],
			'fileName'   => $link,
			'perPage'    => $summary['perPage']
		];
		$pager   = new VuFindPager($options);
		$interface->assign('pageLinks', $pager->getLinks());

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();
		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();
		//Get view & load template
		$interface->assign('displayMode', $displayMode);
		$interface->assign('subpage', 'Search/list-' . $displayMode . '.tpl');

		$this->display('home.tpl', 'Author ' . $authorRaw, 'Author/sidebar.tpl');
	}
}
