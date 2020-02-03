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

class Union_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'getCombinedResults',
		'getResultsFromPika',
		'getResultsFromEDS',
		'getResultsFromArchive',
		'getResultsFromDPLA',
		'getResultsFromProspector',
	);

	function getCombinedResults(){
		$source          = $_REQUEST['source'];
		$numberOfResults = $_REQUEST['numberOfResults'];
		$sectionId       = $_REQUEST['id'];
		list($className, $id) = explode(':', $sectionId);
		$sectionObject = null;
		switch ($className){
			case 'LibraryCombinedResultSection':
				$sectionObject     = new LibraryCombinedResultSection();
				$sectionObject->id = $id;
				$sectionObject->find(true);
				break;
			case 'LocationCombinedResultSection':
				$sectionObject     = new LocationCombinedResultSection();
				$sectionObject->id = $id;
				$sectionObject->find(true);
				break;
			default:
				return array(
					'success' => false,
					'error'   => 'Invalid section id passed in',
				);
		}
		$searchTerm = $_REQUEST['searchTerm'];
		$searchType = $_REQUEST['searchType'];
		$showCovers = $_REQUEST['showCovers'];
		$this->setShowCovers();

		$fullResultsLink = $sectionObject->getResultsLink($searchTerm, $searchType);
		switch ($source){
			case 'eds':
				$results = $this->getResultsFromEDS($searchTerm, $numberOfResults, $fullResultsLink);
				break;
			case 'pika':
				$results = $this->getResultsFromPika($searchTerm, $numberOfResults, $searchType, $fullResultsLink);
				break;
			case 'archive':
				$results = $this->getResultsFromArchive($numberOfResults, $searchType, $searchTerm, $fullResultsLink);
				break;
			case 'dpla':
				$results = $this->getResultsFromDPLA($searchTerm, $numberOfResults, $fullResultsLink);
				break;
			case 'prospector':
				$results = $this->getResultsFromProspector($searchType, $searchTerm, $numberOfResults, $fullResultsLink);
				break;
			default:
				$results = "<div>Showing $numberOfResults for $source.  Show covers? $showCovers</div>";
				break;
		}
		$results .= "<div><a href='" . $fullResultsLink . "' target='_blank'>Full Results from {$sectionObject->displayName}</a></div>";


		return array(
			'success' => true,
			'results' => $results,
		);
	}

	/**
	 * @param $searchTerm
	 * @param $numberOfResults
	 * @param $searchType
	 * @return string
	 */
	private function getResultsFromPika($searchTerm, $numberOfResults, $searchType, $fullResultsLink){
		global $interface;
		$interface->assign('viewingCombinedResults', true);
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init('local', $searchTerm);
		$searchObject->setLimit($numberOfResults);
		$searchObject->setSearchTerms(array(
			'index'   => $searchType,
			'lookfor' => $searchTerm,
		));
		$result  = $searchObject->processSearch(true, false);
		$summary = $searchObject->getResultSummary();
		$records = $searchObject->getCombinedResultsHTML();
		if ($summary['resultTotal'] == 0){
			$results = '<div class="clearfix"></div><div>No results match your search.</div>';
		}else{
			$results = "<a href='{$fullResultsLink}' class='btn btn-info combined-results-button' target='_blank'>&gt; See all {$summary['resultTotal']} results</a><div class='clearfix'></div>";


			$interface->assign('recordSet', $records);
			$interface->assign('showExploreMoreBar', false);
			$results .= $interface->fetch('Search/list-list.tpl');
		}
		return $results;
	}

	/**
	 * @param $searchTerm
	 * @param $numberOfResults
	 * @return string
	 */
	private function getResultsFromEDS($searchTerm, $numberOfResults, $fullResultsLink){
		global $interface;
		$interface->assign('viewingCombinedResults', true);
		if ($searchTerm == ''){
			$results = '<div class="clearfix"></div><div>Enter search terms to see results.</div>';
		}else{
			require_once ROOT_DIR . '/sys/Ebsco/EDS_API.php';
			$edsApi        = EDS_API::getInstance();
			$searchResults = $edsApi->getSearchResults($searchTerm);
			$summary       = $edsApi->getResultSummary();
			$records       = $edsApi->getCombinedResultHTML();
			if ($summary['resultTotal'] == 0){
				$results = '<div class="clearfix"></div><div>No results match your search.</div>';
			}else{
				$results = "<a href='{$fullResultsLink}' class='btn btn-info combined-results-button' target='_blank'>&gt; See all {$summary['resultTotal']} results</a><div class='clearfix'></div>";

				$records = array_slice($records, 0, $numberOfResults);
				global $interface;
				$interface->assign('recordSet', $records);
				$interface->assign('showExploreMoreBar', false);
				$results .= $interface->fetch('Search/list-list.tpl');
			}
		}

		return $results;
	}

	/**
	 * @param $numberOfResults
	 * @param $searchType
	 * @param $searchTerm
	 * @return string
	 */
	private function getResultsFromArchive($numberOfResults, $searchType, $searchTerm, $fullResultsLink){
		global $interface;
		$interface->assign('viewingCombinedResults', true);
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
		$searchObject->setLimit($numberOfResults);
		if ($searchType == 'Title'){
			$searchType = 'IslandoraTitle';
		}elseif ($searchType == 'Subject'){
			$searchType = 'IslandoraSubject';
		}else{
			$searchType = 'IslandoraKeyword';
		}
		$searchObject->setSearchTerms(array(
			'index'   => $searchType,
			'lookfor' => $searchTerm,
		));
		$result  = $searchObject->processSearch(true, false);
		$summary = $searchObject->getResultSummary();
		$records = $searchObject->getCombinedResultHTML();
		if ($summary['resultTotal'] == 0){
			$results = '<div class="clearfix"></div><div>No results match your search.</div>';
		}else{
			$results = "<a href='{$fullResultsLink}' class='btn btn-info combined-results-button' target='_blank'>&gt; See all {$summary['resultTotal']} results</a><div class='clearfix'></div>";

			global $interface;
			$interface->assign('recordSet', $records);
			$interface->assign('showExploreMoreBar', false);
			$results .= $interface->fetch('Search/list-list.tpl');
		}
		return $results;
	}

	/**
	 * @param $searchTerm
	 * @param $numberOfResults
	 * @return string
	 */
	private function getResultsFromDPLA($searchTerm, $numberOfResults, $fullResultsLink){
		global $interface;
		$interface->assign('viewingCombinedResults', true);
		require_once ROOT_DIR . '/sys/SearchObject/DPLA.php';
		$dpla        = new DPLA();
		$dplaResults = $dpla->getDPLAResults($searchTerm, $numberOfResults);
		if ($dplaResults['resultTotal'] == 0){
			$results = '<div class="clearfix"></div><div>No results match your search.</div>';
		}else{
			$results = "<a href='{$fullResultsLink}' class='btn btn-info combined-results-button' target='_blank'>&gt; See all {$dplaResults['resultTotal']} results</a><div class='clearfix'></div>";
		}
		$results .= $dpla->formatCombinedResults($dplaResults['records'], false);
		return $results;
	}

	/**
	 * @param $searchType
	 * @param $searchTerm
	 * @param $numberOfResults
	 * @return string
	 */
	private function getResultsFromProspector($searchType, $searchTerm, $numberOfResults, $fullResultsLink){
		global $interface;
		$interface->assign('viewingCombinedResults', true);
		require_once ROOT_DIR . '/InterLibraryLoanDrivers/Prospector.php';
		if ($searchTerm == ''){
			$results = '<div class="clearfix"></div><div>Enter search terms to see results.</div>';
		}else{
			$prospector        = new Prospector();
			$searchTerms       = array(
				array(
					'index'   => $searchType,
					'lookfor' => $searchTerm,
				),
			);
			$prospectorResults = $prospector->getTopSearchResults($searchTerms, $numberOfResults);
			global $interface;
			if ($prospectorResults['resultTotal'] == 0){
				$results = '<div class="clearfix"></div><div>No results match your search.</div>';
			}else{
				$results = "<a href='{$fullResultsLink}' class='btn btn-info combined-results-button' target='_blank'>&gt; See all {$prospectorResults['resultTotal']} results</a><div class='clearfix'></div>";
				$interface->assign('prospectorResults', $prospectorResults['records']);
				$results .= $interface->fetch('Union/prospector.tpl');
			}
		}
		return $results;
	}
}
