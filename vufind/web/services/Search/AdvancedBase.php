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
 * Base class for Advanced Searches
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 6/27/13
 * Time: 10:10 AM
 */

require_once ROOT_DIR . '/Action.php';

abstract class Search_AdvancedBase extends Action{
	/**
	 * Load a saved search, if appropriate and legal; assign an error to the
	 * interface if necessary.
	 *
	 * @access  protected
	 * @return  SearchObject_Base|boolean mixed           Search Object on successful load, false otherwise
	 */
	protected function loadSavedSearch()
	{
		global $interface;

		// Are we editing an existing search?
		if (isset($_REQUEST['edit']) || isset($_SESSION['lastSearchId'])) {
			// Go find it
			require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
			$search = new SearchEntry();
			$search->id = isset($_REQUEST['edit']) ? $_REQUEST['edit'] : $_SESSION['lastSearchId'];
			if ($search->find(true)) {
				// Check permissions
				if ($search->session_id == session_id() || $search->user_id == UserAccount::getActiveUserId()) {
					// Retrieve the search details
					$minSO = unserialize($search->search_object);
					$savedSearch = SearchObjectFactory::deminify($minSO);
					// Make sure it's an advanced search or convert it to advanced
					if ($savedSearch->getSearchType() == 'basic') {
						$savedSearch->convertBasicToAdvancedSearch();
					}
					// Activate facets so we get appropriate descriptions
					// in the filter list:
					$savedSearch->activateAllFacets('Advanced');
					return $savedSearch;

				} else {
					// No permissions
					$interface->assign('editErr', 'noRights');
				}
				// Not found
			} else {
				$interface->assign('editErr', 'notFound');
			}
		}

		return false;
	}

	/**
	 * Process the facets to be used as limits on the Advanced Search screen.
	 *
	 * @access  protected
	 * @param   array   $facetList      The advanced facet values
	 * @param   object|boolean  $searchObject   Saved search object (false if none)
	 * @return  array                   Sorted facets, with selected values flagged.
	 */
	protected function processFacets($facetList, $searchObject = false)
	{
		// Process the facets, assuming they came back
		$processedFacets = [];
		foreach ($facetList as $facetName => $list) {
			$listOfValuesForCurrentFacet = [];
			$isAnyValueSelected          = false;
			foreach ($list['list'] as $value) {
				// Build the filter string for the URL:
				$fullFilter = $facetName.':"'.$value['value'].'"';

				// If we haven't already found a selected facet and the current
				// facet has been applied to the search, we should store it as
				// the selected facet for the current control.
				if ($searchObject && $searchObject->hasFilter($fullFilter)) {
					$selected = true;
					$isAnyValueSelected = true;
					// Remove the filter from the search object -- we don't want
					// it to show up in the "applied filters" sidebar since it
					// will already be accounted for by being selected in the
					// filter select list!
					$searchObject->removeFilter($fullFilter);
				} else {
					$selected = false;
				}
				$listOfValuesForCurrentFacet[$value['value']] = ['filter' => $fullFilter, 'selected' => $selected];
			}

			$keys = array_keys($listOfValuesForCurrentFacet);

			//Add a value for not selected which will be the first item
			if (strpos($facetName, 'availability_toggle') !== false){
				//Don't sort Available Now facet and make sure the Entire Collection is selected if no value is selected
				if (!$isAnyValueSelected && array_key_exists('Entire Collection', $listOfValuesForCurrentFacet)){
					$listOfValuesForCurrentFacet['Entire Collection']['selected'] = true;
				}
			}else{
				// Perform a natural case sort on the array of facet values:
				natcasesort($keys);

				$processedFacets[$list['label']]['values']['Any ' . $list['label']] = ['filter' => '', 'selected' => !$isAnyValueSelected];
			}

			$processedFacets[$list['label']]['facetName'] = $facetName;
			foreach($keys as $key) {
				$processedFacets[$list['label']]['values'][$key] = $listOfValuesForCurrentFacet[$key];
			}
		}
		return $processedFacets;
	}
}
