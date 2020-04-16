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

/**
 * Service to show an Advanced Popup form to streamline the advanced search.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 6/26/13
 * Time: 9:50 AM
 */

require_once ROOT_DIR . '/services/Search/AdvancedBase.php';
class AdvancedPopup extends Search_AdvancedBase {
	function launch() {
		global $interface;

		// Create our search object
		/** @var SearchObject_Solr|SearchObject_Base $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->initAdvancedFacets();
		// We don't want this search in the search history
		$searchObject->disableLogging();
		// Go get the facets
		$searchObject->processSearch();
		$facetList = $searchObject->getFacetList();
		//print_r($facetList);
		// Shutdown the search object
		$searchObject->close();

		// Load a saved search, if any:
		$savedSearch = $this->loadSavedSearch();
		if ($savedSearch){
			$searchTerms = $savedSearch->getSearchTerms();

			$searchGroups = array();
			$numGroups = 0;
			foreach ($searchTerms as $search){
				$groupStart = true;
				$numItemsInGroup = count($search['group']);
				$curItem = 0;
				foreach ($search['group'] as $group) {
					$searchGroups[$numGroups] = array(
						'groupStart' => $groupStart ? 1 : 0,
						'searchType' => $group['field'],
						'lookfor' => $group['lookfor'],
						'join' => $group['bool'],
						'groupEnd' => ++$curItem === $numItemsInGroup ? 1 : 0
					);

					$groupStart = false;
					$numGroups++;
				}
			}
			$interface->assign('searchGroups', $searchGroups);
		}

		//Get basic search types
		$basicSearchTypes = $searchObject->getBasicTypes();
		$interface->assign('basicSearchTypes', $basicSearchTypes);
		// Send search type settings to the template
		$advSearchTypes = $searchObject->getAdvancedTypes();
		//Remove any basic search types
		foreach ($basicSearchTypes as $basicTypeKey => $basicType){
			unset($advSearchTypes[$basicTypeKey]);
		}
		foreach ($advSearchTypes as $advSearchKey => $label){
			$advSearchTypes[$advSearchKey] = translate($label);
		}
		natcasesort($advSearchTypes);
		$interface->assign('advSearchTypes', $advSearchTypes);

		foreach ($facetList as $facetKey => $facetData){
			$facetList[$facetKey] = translate($facetData['label']);
		}
		natcasesort($facetList);
		$interface->assign('facetList', $facetList);

		header('Content-type: application/json');
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

		$results = array(
				'title' => 'Advanced Search',
				'modalBody' => $interface->fetch("Search/advancedPopup.tpl"),
				'modalButtons' => "<span class='tool btn btn-primary' onclick='Pika.Searches.submitAdvancedSearch(); return false;'>Find</span>"
		);
		echo json_encode($results);
	}
}
