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

require_once ROOT_DIR . '/services/Search/AdvancedBase.php';

class Search_Advanced extends Search_AdvancedBase {
		public $rangeFilters = ['publishDate', 'lexile_score', 'accelerated_reader_reading_level', 'accelerated_reader_point_value'];

	function launch(){
		global $interface;

		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->initAdvancedFacets();
		// We don't want this search in the search history
		$searchObject->disableLogging();
		// Go get the facets
		$searchObject->processSearch();
		$facetList = $searchObject->getFacetList();
		// Shutdown the search object
		$searchObject->close();

		// Load a saved search, if any:
		$savedSearch = $this->loadSavedSearch();

		// Process the facets for appropriate display on the Advanced Search screen:
		$facets = $this->processFacets($facetList, $savedSearch);
		//check to see if we have a facet for format category since we want to show those
		//as icons
		if (array_key_exists('format_category', $facetList)){
			$label = $facetList['format_category']['label'];
			foreach ($facets[$label]['values'] as $key => $optionInfo){
				$optionInfo['imageName']        = str_replace(" ", "", strtolower($key)) . '.png';
				$facets[$label]['values'][$key] = $optionInfo;
			}
			$interface->assign('formatCategoryLimit', $facets[$label]['values']);
			unset($facets[$label]);
		}
		$interface->assign('facetList', $facets);

		// Send search type settings to the template
		$interface->assign('advancedSearchTypes', $searchObject->getAdvancedSearchTypes());

		// If we found a saved search, let's assign some details to the interface:
		if ($savedSearch){
			$interface->assign('searchDetails', $savedSearch->getSearchTerms());
			$interface->assign('searchFilters', $savedSearch->getFilterList());
		}

		$this->display('advanced.tpl', 'Advanced Search', 'Search/results-sidebar.tpl');
	}

}

