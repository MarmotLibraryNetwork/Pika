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

require_once ROOT_DIR . '/sys/Recommend/Interface.php';

/**
 * SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 */
class SideFacets implements RecommendationInterface {
	/** @var  SearchObject_Islandora|SearchObject_Solr|SearchObject_Genealogy $searchObject */
	private $searchObject;
	private $facetSettings;
	private $mainFacets;
	private $checkboxFacets;

	/* Constructor
	 *
	 * Establishes base settings for making recommendations.
	 *
	 * @access  public
	 * @param   SearchObject_Islandora|SearchObject_Solr|SearchObject_Genealogy  $searchObject   The SearchObject requesting recommendations.
	 * @param   string  $params         Additional settings from the searches.ini.
	 */
	public function __construct($searchObject, $params){
		// Save the passed-in SearchObject:
		$this->searchObject = $searchObject;

		// Parse the additional settings:
		$params          = explode(':', $params);
		$mainSection     = empty($params[0]) ? 'Results' : $params[0];
		$checkboxSection = $params[1] ?? false;
		$iniName         = $params[2] ?? 'facets';

		if ($searchObject->getSearchType() == 'genealogy'){
			$config           = getExtraConfigArray($iniName);
			$this->mainFacets = $config[$mainSection] ?? [];
		}elseif ($searchObject->getSearchType() == 'islandora'){
			$searchLibrary                 = Library::getActiveLibrary();
			$hasArchiveSearchLibraryFacets = ($searchLibrary != null && (count($searchLibrary->archiveSearchFacets) > 0));
			if ($hasArchiveSearchLibraryFacets){
				$facets = $searchLibrary->archiveSearchFacets;
			}else{
				$facets = Library::getDefaultArchiveSearchFacets();
			}

			$this->facetSettings = [];
			$this->mainFacets    = [];

			foreach ($facets as $facet){
				$facetName = $facet->facetName;

				//Figure out if the facet should be included
				if ($mainSection == 'Results'){
					if ($facet->showInResults == 1 && $facet->showAboveResults == 0){
						$this->facetSettings[$facetName] = $facet;
						$this->mainFacets[$facetName]    = $facet->displayName;
					}elseif ($facet->showInAdvancedSearch == 1 && $facet->showAboveResults == 0){
						$this->facetSettings[$facetName] = $facet->displayName;
					}
				}
			}


		}else{
			// Processing for the various types of catalog searches
			/** @var Location $locationSingleton */
			global 	$locationSingleton;
			$searchLibrary           = Library::getActiveLibrary();
			$searchLocation          = $locationSingleton->getActiveLocation();
			$libraryScopeName        = $searchLibrary->subdomain;
			$isSearchLocationScope   = $searchLocation != null;
			$hasSearchLibraryFacets  = ($searchLibrary != null && (count($searchLibrary->facets) > 0));
			$hasSearchLocationFacets = ($isSearchLocationScope && (count($searchLocation->facets) > 0));
			if ($hasSearchLocationFacets){
				$facets = $searchLocation->facets;
			}elseif ($hasSearchLibraryFacets){
				$facets = $searchLibrary->facets;
			}else{
				$facets = Library::getDefaultFacets();
			}
			$this->facetSettings = [];
			$this->mainFacets    = [];

			// The below block of code is common with SearchObject_Solr method initAdvancedFacets()
			global $solrScope;
			if ($isSearchLocationScope){
				$locationScopeName = $searchLocation->code;
			}
			foreach ($facets as $facet){
				$facetName = $facet->facetName;

				//Adjust facet name for local scoping
				if ($solrScope){
					if (in_array($facetName, [
						'availability_toggle',
						'available_at',
						'collection',
						'detailed_location',
						'econtent_source',
						'format',
						'format_category',
						'itype',
						'language',
						'owning_library',
						'owning_location',
						'translation',
					])){
						if ($isSearchLocationScope){
							switch ($searchLocation->scopeType){
								case 'regularBranchScope' :
									if (!in_array($facetName, [
										// these should be the same for all branches, so for these we will skip location facet and use library scope
										'collection',
										'econtent_source',
										'format',
										'format_category',
										'itype',
										'language',
										'translation',
									])){
										$facetName .= '_' . $locationScopeName;
										break;
									}
								default :
									$facetName .= '_' . $libraryScopeName;
							}
						} else{
							$facetName .= '_' . $libraryScopeName;
						}
					}
				}

				// Below logic is broken out to determine search scope for special locations (i.e. Juvenile collections)
				// TODO: does it need seperated from location scoping above??
				if ($facet->facetName == 'time_since_added'){
					if (isset($searchLibrary)){
						$facetName = 'local_time_since_added_' . $libraryScopeName;
					}
					if (isset($searchLocation) && $searchLocation->restrictSearchByLocation){
						$facetName = 'local_time_since_added_' . $locationScopeName;
					}
				}

				//Figure out if the facet should be included
				if ($mainSection == 'Results'){
					if ($facet->showInResults == 1 && $facet->showAboveResults == 0){
						$this->facetSettings[$facetName] = $facet;
						$this->mainFacets[$facetName]    = $facet->displayName;
					}elseif ($facet->showInAdvancedSearch == 1 && $facet->showAboveResults == 0){
						$this->facetSettings[$facetName] = $facet->displayName;
					}
				}elseif ($mainSection == 'Author'){
					if ($facet->showInAuthorResults == 1 && $facet->showAboveResults == 0){
						$this->facetSettings[$facetName] = $facet;
						$this->mainFacets[$facetName]    = $facet->displayName;
					}
				}
			}
		}

		$this->checkboxFacets = ($checkboxSection && isset($config[$checkboxSection])) ? $config[$checkboxSection] : [];
	}

	/* init
	 *
	 * Called before the SearchObject performs its main search.  This may be used
	 * to set SearchObject parameters in order to generate recommendations as part
	 * of the search.
	 *
	 * @access  public
	 */
	public function init(){
		// Turn on side facets in the search results:
		foreach ($this->mainFacets as $name => $displayName){
			$this->searchObject->addFacet($name, $displayName);
		}
		foreach ($this->checkboxFacets as $name => $displayName){
			$this->searchObject->addCheckboxFacet($name, $displayName);
		}
	}

	/* process
	 *
	 * Called after the SearchObject has performed its main search.  This may be
	 * used to extract necessary information from the SearchObject or to perform
	 * completely unrelated processing.
	 *
	 * @access  public
	 */
	public function process(){
		global $interface;

		//Get Facet settings for processing display
		$interface->assign('checkboxFilters', $this->searchObject->getCheckboxFacets());
		//Get applied facets
		$filterList = $this->searchObject->getFilterList(true);
		foreach ($filterList as $facetKey => $facet){
			//Remove any top facets since the removal links are displayed above results
			if (strpos($facet[0]['field'], 'availability_toggle') === 0){
				unset($filterList[$facetKey]);
			}
		}
		$interface->assign('filterList', $filterList);
		//Process the side facet set to handle the Added In Last facet which we only want to be
		//visible if there is not a value selected for the facet (makes it single select
		$sideFacets    = $this->searchObject->getFacetList($this->mainFacets);
		$searchLibrary = Library::getSearchLibrary();

		//Do additional processing of facets for non-genealogy searches
		if ($this->searchObject->getSearchType() != 'genealogy'/* && $this->searchObject->getSearchType() != 'islandora'*/){
			foreach ($sideFacets as $facetKey => $facet){

				$facetSetting = $this->facetSettings[$facetKey];

				//Do special processing of facets
				if (preg_match('/time_since_added/i', $facetKey)){
					$timeSinceAddedFacet   = $this->updateTimeSinceAddedFacet($facet);
					$sideFacets[$facetKey] = $timeSinceAddedFacet;
				}elseif ($facetKey == 'rating_facet'){
					$userRatingFacet       = $this->updateUserRatingsFacet($facet);
					$sideFacets[$facetKey] = $userRatingFacet;
				}else{
					//Do other handling of the display
					if ($facetSetting->sortMode == 'alphabetically'){
						asort($sideFacets[$facetKey]['list']);
					}
					if ($facetSetting->numEntriesToShowByDefault > 0){
						$sideFacets[$facetKey]['valuesToShow'] = $facetSetting->numEntriesToShowByDefault;
					}
					if ($facetSetting->showAsDropDown){
						$sideFacets[$facetKey]['showAsDropDown'] = $facetSetting->showAsDropDown;
					}
					if ($facetSetting->useMoreFacetPopup && count($sideFacets[$facetKey]['list']) > 12){
						$sideFacets[$facetKey]['showMoreFacetPopup'] = true;
						$sideFacets[$facetKey]['sortedList']         = $sideFacets[$facetKey]['list'];
						$sideFacets[$facetKey]['list']               = array_slice($sideFacets[$facetKey]['list'], 0, 5); // shorten main list to the first 5 entries
						ksort($sideFacets[$facetKey]['sortedList'], SORT_NATURAL | SORT_FLAG_CASE);                       // use case-insensitive natural ordering to sort the full list of options
					}else{
						$sideFacets[$facetKey]['showMoreFacetPopup'] = false;
					}
				}
				$sideFacets[$facetKey]['collapseByDefault'] = $facetSetting->collapseByDefault;
			}
		}else{
			//Process genealogy to add more facet popup
			foreach ($sideFacets as $facetKey => $facet){
				if (count($sideFacets[$facetKey]['list']) > 12){
					$sideFacets[$facetKey]['showMoreFacetPopup'] = true;
					$facetsList                                  = $sideFacets[$facetKey]['list'];
					$sideFacets[$facetKey]['list']               = array_slice($facetsList, 0, 5);
					$sortedList                                  = [];
					foreach ($facetsList as $key => $value){
						$sortedList[$value['display']] = $value;
					}
					ksort($sortedList, SORT_NATURAL | SORT_FLAG_CASE); // use case-insensitive natural ordering
					$sideFacets[$facetKey]['sortedList'] = $sortedList;
				}
			}
		}

		$interface->assign('sideFacetSet', $sideFacets);
	}

	private function updateTimeSinceAddedFacet($timeSinceAddedFacet){
		//See if there is a value selected
		$valueSelected = false;
		foreach ($timeSinceAddedFacet['list'] as $facetValue){
			if (isset($facetValue['isApplied']) && $facetValue['isApplied'] == true){
				$valueSelected = true;
			}
		}
		if ($valueSelected){
			//Get rid of all values except the selected value which will allow the value to be removed
			//We remove the other values because it is confusing to have results both longer and shorter than the current value.
			foreach ($timeSinceAddedFacet['list'] as $facetKey => $facetValue){
				if (!isset($facetValue['isApplied']) || $facetValue['isApplied'] == false){
					unset($timeSinceAddedFacet['list'][$facetKey]);
				}
			}
		}else{
			//Make sure to show all values
			$timeSinceAddedFacet['valuesToShow'] = count($timeSinceAddedFacet['list']);
			//Reverse the display of the list so Day is first and year is last
			$timeSinceAddedFacet['list'] = array_reverse($timeSinceAddedFacet['list']);
		}
		return $timeSinceAddedFacet;
	}

	private function updateUserRatingsFacet($userRatingFacet){
		global $interface;
		$ratingApplied = false;
		$ratingLabels  = [];
		foreach ($userRatingFacet['list'] as $facetValue){
			if ($facetValue['isApplied']){
				$ratingApplied  = true;
				$ratingLabels[] = $facetValue['value'];
			}
		}
		if (!$ratingApplied){
			$ratingLabels = ['fiveStar', 'fourStar', 'threeStar', 'twoStar', 'oneStar', 'Unrated'];
		}
		$interface->assign('ratingLabels', $ratingLabels);
		return $userRatingFacet;
	}

	/* getTemplate
	 *
	 * This method provides a template name so that recommendations can be displayed
	 * to the end user.  It is the responsibility of the process() method to
	 * populate all necessary template variables.
	 *
	 * @access  public
	 * @return  string      The template to use to display the recommendations.
	 */
	public function getTemplate(){
		return 'Search/Recommend/SideFacets.tpl';
	}
}
