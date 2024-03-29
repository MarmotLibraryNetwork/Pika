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
require_once ROOT_DIR . '/sys/Recommend/RecommendationFactory.php';

/**
 * Search Object abstract base class.
 *
 * Generic base class for abstracting search URL generation and other standard
 * functionality.  This should be extended to implement functionality for specific
 * VuFind modules (i.e. standard Solr search vs. Summon, etc.).
 */
abstract class SearchObject_Base {
	// Parsed query
	protected $query = null;

	// SEARCH PARAMETERS
	// RSS feed?
	protected $view = null;
	protected $defaultView = 'list';
	// Search terms
	protected $searchTerms = [];
	// Sorting
	protected $sort = null;
	protected $defaultSort = 'relevance';
	protected $defaultSortByType = [];
	/** @var string */
	protected $searchSource = 'local';

	// Filters
	protected $filterList = [];
	// Page number
	protected $page = 1;
	// Result limit
	protected $limit = 20;

	// Used to pass hidden filter queries to Solr
	protected $hiddenFilters = [];

	// STATS
	protected $resultsTotal = 0;

	// OTHER VARIABLES
	// Server URL
	protected $serverUrl = '';
	// Module and Action for building search results URLs
	protected $resultsModule = 'Search';
	protected $resultsAction = 'Results';
	// Facets information
	protected $facetConfig = [];    // Array of valid facet fields=>labels
	protected $facetOptions = [];
	protected $checkboxFacets = [];    // Boolean facets represented as checkboxes
	protected $translatedFacets = [];  // Facets that need to be translated
	protected $pidFacets = [];
	// Default Search Handler
	protected $defaultIndex = null;
	// Available sort options
	protected $sortOptions = [];
	// An ID number for saving/retrieving search
	protected $searchId = null;
	protected $savedSearch = false;
	protected $searchType = 'basic';
	// Possible values of $searchType:
	protected $isAdvanced = false;
	protected $basicSearchType = 'basic';
	protected $advancedSearchType = 'advanced';
	// Flag for logging/search history
	protected $disableLogging = false;
	// Debugging flag
	protected $debug = false;
	protected $debugSolrQuery = false;
	protected $isPrimarySearch = false;
	// Search options for the user
	protected $advancedSearchTypes = [];
	protected $basicTypes = [];
	protected $browseTypes = [];
	// Spelling
	protected $spellcheck = true;
	protected $suggestions = [];
	// Recommendation modules associated with the search:
	/** @var bool|array $recommend */
	protected $recommend = false;
	// The INI file to load recommendations settings from:
	protected $recommendIni = 'searches';

	// STATS
	protected $initTime = null;
	protected $endTime = null;
	protected $totalTime = null;

	protected $queryStartTime = null;
	protected $queryEndTime = null;
	protected $queryTime = null;

	/**
	 * Constructor. Initialise some details about the server
	 *
	 * @access  public
	 */
	public function __construct(){
		global $configArray;
		global $timer;

		// Get the start of the server URL and store
//		$this->serverUrl = '';

		// Set appropriate debug mode:
		// Debugging
		if ($configArray['System']['debugSolr']){
			//Verify that the ip is ok
			if (!empty($configArray['MaintenanceMode']['maintenanceIps'])){
				global $locationSingleton;
				$activeIp     = $locationSingleton->getActiveIp();
				$allowableIps = explode(',', $configArray['MaintenanceMode']['maintenanceIps']);
				if (in_array($activeIp, $allowableIps)){
					$this->debug = true;
					if ($configArray['System']['debugSolrQuery'] == true){
						$this->debugSolrQuery = true;
					}
				}
			}
		}
		$timer->logTime('Setup Base Search Object');
	}

	public function setDebugging($enableDebug, $enableSolrQueryDebugging){
		$this->debug          = $enableDebug;
		$this->debugSolrQuery = $enableDebug && $enableSolrQueryDebugging;
		$this->getIndexEngine()->setDebugging($enableDebug, $enableSolrQueryDebugging);
	}

	/* Parse apart the field and value from a URL filter string.
	 *
	 * @access  protected
	 * @param   string  $filter     A filter string from url : "field:value"
	 * @return  []               Array with elements 0 = field, 1 = value.
	 */
	protected function parseFilter($filter){
		if ((strpos($filter, ' AND ') !== false) || (strpos($filter, ' OR ') !== false)){
			// This is a complex filter that does not need parsing
			return ['', $filter];
		}

		$temp  = explode(':', $filter); // Split the string
		$field = array_shift($temp); // $field is the first value
		$value = implode(':', $temp); // join them in case the value contained colons as well.
		$value = trim($value, '"'); // Remove quotes from the value if there are any
		$value = trim($value); // One last little clean on whitespace

		return [$field, $value];
	}

	/**
	 * @return string
	 */
	public function getSearchSource(){
		return $this->searchSource;
	}

	/**
	 * @return array
	 */
	public function getHiddenFilters(){
		return $this->hiddenFilters;
	}

//	/**
//	 * @return array
//	 */
//	public function getFacetConfig()
//	{
//		return $this->facetConfig;
//	}

	/**
	 * Does the object already contain the specified filter?
	 *
	 * @access  public
	 * @param   string  $filter     A filter string from url : "field:value"
	 * @return  bool
	 */
	public function hasFilter($filter){
		// Extract field and value from URL string:
		[$field, $value] = $this->parseFilter($filter);

		if (isset($this->filterList[$field]) && in_array($value, $this->filterList[$field])){
			return true;
		}
		return false;
	}

	public function clearFilters(){
		$this->filterList = [];
	}


	/**
	 * Take a filter string and add it into the protected
	 *   array checking for duplicates.
	 *
	 * @access  public
	 * @param   string  $newFilter   A filter string from url : "field:value"
	 */
	public function addFilter($newFilter){
		if (empty($newFilter)){
			return;
		}
		// Extract field and value from URL string:
		[$field, $value] = $this->parseFilter($newFilter);
		if ($field == ''){
			$field = count($this->filterList) + 1;
		}

		// Check for duplicates -- if it's not in the array, we can add it
		if (!$this->hasFilter($field)) {
			$this->filterList[$field][] = $value;
		}
	}

	/**
	 * Remove a filter from the list.
	 *
	 * @access  public
	 * @param   string  $oldFilter   A filter string from url : "field:value"
	 */
	public function removeFilter($oldFilter){
		// Extract field and value from URL string:
		[$field, $value] = $this->parseFilter($oldFilter);

		// Make sure the field exists
		if (isset($this->filterList[$field])){
			// Loop through all filters on the field
			for ($i = 0;$i < count($this->filterList[$field]);$i++){
				// Does it contain the value we don't want?
				if ($this->filterList[$field][$i] == $value){
					// If so remove it.
					unset($this->filterList[$field][$i]);
				}
			}
		}
	}

	public function clearHiddenFilters() {
		$this->hiddenFilters = [];
	}

	/**
	 * Add a hidden (i.e. not visible in facet controls) filter query to the object.
	 *
	 * @access  public
	 * @param $field
	 * @param $value
	 */
	public function addHiddenFilter($field, $value){
		$this->hiddenFilters[] = $field . ':' . $value;
	}

	// In each class, set the specific range filters based on the Search Object
	protected $rangeFilters = [];
	protected $dateFilters = [];

	/**
	 * Range filters are small input forms that must be processed differently that other facets.
	 * And must be converted to usable filter url parameter.
	 *
	 */
	public function processAllRangeFilters(){
		$yearFilters  = $this->processYearFilters();
		$rangeFilters = $this->processRangeFilters();
		$filters      = array_merge($yearFilters, $rangeFilters);
		if (!empty($filters)){
			foreach ($filters as $filter){
				$this->addFilter($filter);
			}
			$searchUrl = $this->renderSearchUrl();
			header("Location: $searchUrl");
			exit();
		}
	}

	/**
	 * Process general numeric range filters
	 *
	 * @return array
	 */
	private function processRangeFilters(){
		//Check to see if the year has been set and if so, convert to a filter and resend.
		$filters = [];
		foreach ($this->rangeFilters as $rangeFilter){
			if (!empty($_REQUEST[$rangeFilter . 'from']) || !empty($_REQUEST[$rangeFilter . 'to'])){
				$from = preg_match('/^\d+(\.\d*)?$/', $_REQUEST[$rangeFilter . 'from']) ? $_REQUEST[$rangeFilter . 'from'] : '*';
				$to   = preg_match('/^\d+(\.\d*)?$/', $_REQUEST[$rangeFilter . 'to']) ? $_REQUEST[$rangeFilter . 'to'] : '*';
				if ($from != '*' || $to != '*'){
					$filterStr  = "$rangeFilter:[$from TO $to]";
					$filterList = $this->getFilterList();
					foreach ($filterList as $facets){
						foreach ($facets as $facet){
							if ($facet['field'] == $rangeFilter){
								$this->removeFilter($facet['field'] . ':' . $facet['value']);
								break;
							}
						}
					}
					$filters[] = $filterStr;
				}
			}
		}
		return $filters;
	}


	/**
	 * Process range filters that are specifically year ranges
	 */
	private function processYearFilters(){
		//Check to see if the year has been set and if so, convert to a filter and resend.
		$filters = [];
		foreach ($this->dateFilters as $dateFilter){
			if (!empty($_REQUEST[$dateFilter . 'yearfrom']) || !empty($_REQUEST[$dateFilter . 'yearto'])){
				$yearFrom = $this->processYearField($dateFilter . 'yearfrom');
				$yearTo   = $this->processYearField($dateFilter . 'yearto');
				if ($yearFrom != '*' || $yearTo != '*'){
					$filterStr  = "$dateFilter:[$yearFrom TO $yearTo]";
					$filterList = $this->getFilterList();
					foreach ($filterList as $facets){
						foreach ($facets as $facet){
							if ($facet['field'] == $dateFilter){
								$this->removeFilter($facet['field'] . ':' . $facet['value']);
								break;
							}
						}
					}
					$filters[] = $filterStr;
				}
			}
		}
		return $filters;
	}

	private function processYearField($yearField){
		$year = preg_match('/^\d{2,4}$/', $_REQUEST[$yearField]) ? $_REQUEST[$yearField] : '*';
		if (strlen($year) == 2){
			$year = '19' . $year;
		}elseif (strlen($year) == 3){
			$year = '0' . $year;
		}
		return $year;
	}


	/**
	 * Get a user-friendly string to describe the provided facet field.
	 *
	 * @access  protected
	 * @param   string  $field                  Facet field name.
	 * @return  string                          Human-readable description of field.
	 */
	protected function getFacetLabel($field){
		return $this->facetConfig[$field] ?? ucwords(str_replace('_', ' ', translate($field)));
	}

	/**
	 * Clear all facets which will speed up searching if we won't be using the facets.
	 */
	public function clearFacets(){
		$this->facetConfig = [];
	}

	public function hasAppliedFacets(){
		return count($this->filterList) > 0;
	}

	/**
	 * Return an array structure containing all current filters
	 *    and urls to remove them.
	 *
	 * @access  public
	 * @param   bool   $excludeCheckboxFilters  Should we exclude checkbox filters
	 *                                          from the list (to be used as a
	 *                                          complement to getCheckboxFacets()).
	 * @return  array    Field, values and removal urls
	 */
	public function getFilterList($excludeCheckboxFilters = false){
		// Get a list of checkbox filters to skip if necessary:
		$skipList = $excludeCheckboxFilters ? array_keys($this->checkboxFacets) : [];

		$list = [];
		// Loop through all the current filter fields
		foreach ($this->filterList as $field => $values){
			// and each value currently used for that field
			$translate = in_array($field, $this->translatedFacets);
			foreach ($values as $value){
				// Add to the list unless it's in the list of fields to skip:
				if (!in_array($field, $skipList)){
					$facetLabel = $this->getFacetLabel($field);
					if ($field == 'veteranOf' && $value == '[* TO *]'){
						$display = 'Any War';
					}elseif ($field == 'available_at' && $value == '*'){
						$anyLocationLabel = $this->getFacetSetting('Availability', 'anyLocationLabel');
						$display          = empty($anyLocationLabel) ? 'Any Location' : $anyLocationLabel;
					}else{
						$display = $translate ? translate($value) : $value;
					}

					$list[$facetLabel][] = [
						'value'      => $value,     // raw value for use with Solr
						'display'    => $display,   // version to display to user
						'field'      => $field,
						'removalUrl' => $this->renderLinkWithoutFilter("$field:$value")
					];
				}
			}
		}
		return $list;
	}


	/**
	 * Return a url for the current search with an additional filter
	 *
	 * @access  public
	 * @param   string   $newFilter   A filter to add to the search url
	 * @return  string   URL of a new search
	 */
	public function renderLinkWithFilter($newFilter){
		// Stash our old data for a minute
		$oldFilterList = $this->filterList;
		$oldPage       = $this->page;
		// Availability facet can have only one item selected at a time
		if (strpos($newFilter, 'availability_toggle') === 0){
			foreach ($this->filterList as $name => $value){
				if (strpos($name, 'availability_toggle') === 0){
					unset ($this->filterList[$name]);
				}
			}
		}
		// Add the new filter
		$this->addFilter($newFilter);
		// Remove page number
		$this->page = 1;
		// Get the new url
		$url = $this->renderSearchUrl();
		// Restore the old data
		$this->filterList = $oldFilterList;
		$this->page       = $oldPage;
		// Return the URL
		return $url;
	}

	/**
	 * Return a url for the current search without one of the current filters
	 *
	 * @access  public
	 * @param   string   $oldFilter   A filter to remove from the search url
	 * @return  string   URL of a new search
	 */
	public function renderLinkWithoutFilter($oldFilter){
		return $this->renderLinkWithoutFilters([$oldFilter]);
	}

	/**
	 * Return a url for the current search without several of the current filters
	 *
	 * @access  public
	 * @param   array    $filters      The filters to remove from the search url
	 * @return  string   URL of a new search
	 */
	public function renderLinkWithoutFilters($filters){
		// Stash our old data for a minute
		$oldFilterList = $this->filterList;
		$oldPage       = $this->page;
		// Remove the old filter
		foreach($filters as $oldFilter) {
			$this->removeFilter($oldFilter);
		}
		// Remove page number
		$this->page = 1;
		// Get the new url
		$url = $this->renderSearchUrl();
		// Restore the old data
		$this->filterList = $oldFilterList;
		$this->page       = $oldPage;
		// Return the URL
		return $url;
	}

	/**
	 * Get the base URL for search results (including ? parameter prefix).
	 *
	 * @access  protected
	 * @return  string   Base URL
	 */
	protected function getBaseUrl(){
		return $this->serverUrl . "/{$this->resultsModule}/{$this->resultsAction}?";
	}

	/**
	 * Get the URL to load a saved search from the current module.
	 *
	 * @access  public
	 * @param string $id The Id of the saved search
	 * @return  string   Saved search URL.
	 */
	public function getSavedUrl($id){
		return $this->getBaseUrl() . 'saved=' . urlencode($id);
	}

	/**
	 * Get an array of strings to attach to a base URL in order to reproduce the
	 * current search.
	 *
	 * @access  protected
	 * @return  array    Array of URL parameters (key=url_encoded_value format)
	 */
	protected function getSearchParams(){
		$params = [];
		switch ($this->searchType){
			// Advanced search
			case $this->advancedSearchType:
				// Advanced Search Page
				//structure lookfor0[], lookfor1[],
				$params[] = 'join=' . urlencode($this->searchTerms[0]['join']);
				for ($i = 0;$i < count($this->searchTerms);$i++){
					$params[] = 'bool'. $i . '[]=' . urlencode($this->searchTerms[$i]['group'][0]['bool']);
					for ($j = 0;$j < count($this->searchTerms[$i]['group']);$j++){
						$params[] = 'lookfor' . $i . '[]=' . urlencode($this->searchTerms[$i]['group'][$j]['lookfor']);
						$params[] = 'type' . $i . '[]=' . urlencode($this->searchTerms[$i]['group'][$j]['field']);
					}
				}
				break;
			// Basic search
			default:
				if (isset($this->searchTerms[0]['lookfor'])){
					$params[] = 'lookfor=' . urlencode($this->searchTerms[0]['lookfor']);
				}
				if (isset($this->searchTerms[0]['index'])){
					if ($this->searchType == 'basic'){
						$params[] = 'basicType=' . urlencode($this->searchTerms[0]['index']);
					}else{
						$params[] = 'type=' . urlencode($this->searchTerms[0]['index']);
					}

				}
				break;
		}
		return $params;
	}

	/**
	 * Initialize the object's search settings for a basic search found in the
	 * $_REQUEST superglobal.
	 *
	 * @access  public
	 * @param String|String[] $searchTerm
	 * @return  boolean  True if search settings were found, false if not.
	 */
	public function initBasicSearch($searchTerm = null){
		$searchTerm = $searchTerm ?? $_REQUEST['lookfor'] ?? false;
		if ($searchTerm === false) return false; // If no lookfor parameter was found, we have no search terms to add to our array!

		// If lookfor is an array, we may be dealing with a legacy Advanced
		// Search URL.  If there's only one parameter, we can flatten it,
		// but otherwise we should treat it as an error -- no point in going
		// to great lengths for compatibility.
		//TODO: document how this would come into play; or remove if it does
		if (is_array($searchTerm)) {
			if (count($searchTerm) == 1) {
				$searchTerm = strip_tags(reset($searchTerm));
				if (isset($_REQUEST['searchType'])){
					$_REQUEST['type'] = strip_tags(reset($_REQUEST['searchType']));
				}
			} else {
				return false;
			}
		}

		// If no type defined use default
		if (!empty($_REQUEST['type'])) {
			$type = $_REQUEST['type'];
			$type = strip_tags(is_array($type) ? $type[0]: $type); // Flatten type arrays for backward compatibility
		} else {
			$type = $this->defaultIndex;
		}

		if (strpos($searchTerm, ':') > 0 && $this->isAdvancedSearchFormDisplayQuery($searchTerm)){
			$this->isAdvanced = true;
			$this->searchTerms = $this->buildAdvancedSearchTermsFromAdvancedDisplayQuery($searchTerm);

		}else{
			$this->searchTerms[] = [
				'index'   => $type,
				'lookfor' => $searchTerm
			];
		}
		return true;
	}

	public function setSearchTerms($searchTerms){
		$this->searchTerms   = [];
		$this->searchTerms[] = $searchTerms;
	}

	public function isAdvanced(){
		return $this->isAdvanced;
	}

	/**
	 * Initialize the object's search settings for an advanced search found in the
	 * $_REQUEST superglobal.  Advanced searches have numeric subscripts on the
	 * lookfor and type parameters -- this is how they are distinguished from basic
	 * searches.
	 *
	 * @access  protected
	 */
	protected function initAdvancedSearch(){
		$this->isAdvanced = true;
//		if (isset($_REQUEST['lookfor'])){
//			if (is_array($_REQUEST['lookfor'])){
//				//Advanced search from popup form
//				$this->searchType = $this->advancedSearchType;
//				$group            = [];
//				foreach ($_REQUEST['lookfor'] as $index => $lookfor){
//					$group[] = [
//						'field'   => $_REQUEST['searchType'][$index],
//						'lookfor' => $lookfor,
//						'bool'    => $_REQUEST['join'][$index]
//					];
//
//					if (isset($_REQUEST['groupEnd'])){
//						if (isset($_REQUEST['groupEnd'][$index]) && $_REQUEST['groupEnd'][$index] == 1){
//							// Add the completed group to the list
//							$this->searchTerms[] = [
//								'group' => $group,
//								'join'  => $_REQUEST['join'][$index]
//							];
//							$group               = [];
//						}
//					}
//				}
//				if (count($group) > 0){
//					// Add the completed group to the list
//					$this->searchTerms[] = [
//						'group' => $group,
//						'join'  => $_REQUEST['join'][$index]
//					];
//				}
//			}
//		}else{
			//********************
			// Advanced Search logic
			//  'lookfor0[]' 'type0[]'
			//  'lookfor1[]' 'type1[]' ...
			$this->searchType = $this->advancedSearchType;
			$groupCount       = 0;
			// Loop through each search group
			while (isset($_REQUEST['lookfor' . $groupCount])){
				$group           = [];
				$lookForGroupKey = 'lookfor' . $groupCount;
				// Loop through each term inside the group
				for ($i = 0, $l = count($_REQUEST[$lookForGroupKey]);$i < $l;$i++){
					// Ignore advanced search fields with no lookup
					if ($_REQUEST[$lookForGroupKey][$i] != ''){
						// Use default fields if not set
						if (!empty($_REQUEST['type' . $groupCount][$i])){
							$type = strip_tags($_REQUEST['type' . $groupCount][$i]);
						}else{
							$type = $this->defaultIndex;
						}

						//Marmot - search both ISBN-10 and ISBN-13
						//Check to see if the search term looks like an ISBN10 or ISBN13
						$lookfor = strip_tags($_REQUEST[$lookForGroupKey][$i]);

						// Add term to this group
						$group[] = [
							'field'   => $type,
							'lookfor' => $lookfor,
							'bool'    => isset($_REQUEST['bool' . $groupCount]) ? strip_tags($_REQUEST['bool' . $groupCount][0]) : 'AND'
						];
					}
				}

				// Make sure we aren't adding groups that had no terms
				if (count($group) > 0){
					// Add the completed group to the list
					$this->searchTerms[] = [
						'group' => $group,
						'join'  => isset($_REQUEST['join']) ? strip_tags(is_array($_REQUEST['join']) ? reset($_REQUEST['join']) : $_REQUEST['join']) : 'AND'
					];
				}

				// Increment
				$groupCount++;
			}

			// Finally, if every advanced row was empty
			if (count($this->searchTerms) == 0){
				// Treat it as an empty basic search
				$this->searchType    = $this->basicSearchType;
				$this->searchTerms[] = [
					'index'   => $this->defaultIndex,
					'lookfor' => ''
				];
			}
//		}
	}

	/**
	 * Add view mode to the object based on the $_REQUEST superglobal.
	 *
	 * @access  protected
	 */
	protected function initView(){
		if (!empty($this->view)){ //return view if it has already been set.
			return $this->view;
		}
		// Check for a view parameter in the url.
		if (isset($_REQUEST['view'])) {
			if ($_REQUEST['view'] == 'excel' || $_REQUEST['view'] == 'rss') {
				// we don't want to store excel or rss in the Session variable
				$this->view = $_REQUEST['view'];
			} else {
				// store other views in $_SESSION for persistence
				$validViews = $this->getViewOptions();
				// make sure the url parameter is a valid view
				if (in_array($_REQUEST['view'], $validViews)) {
					$this->view = $_REQUEST['view'];
					$_SESSION['lastView'] = $this->view;
				} else {
					$this->view = $this->defaultView;
				}
			}
		} elseif (!empty($_SESSION['lastView'])) {
			// if there is nothing in the URL, check the Session variable
			$this->view = $_SESSION['lastView'];
		} else {
			// otherwise load the default
			$this->view = $this->defaultView;
		}
	}

	/**
	 * Add page number to the object based on the $_REQUEST superglobal.
	 *
	 * @access  protected
	 */
	protected function initPage(){
		if (isset($_REQUEST['page'])){
			$page = $_REQUEST['page'];
			if (is_array($page)){
				$page = array_pop($page);
			}
			$this->page = strip_tags($page);
		}
		$this->page = intval($this->page);
		if ($this->page < 1){
			$this->page = 1;
		}
	}

	/**
	 * Navigate to a specific page.
	 *
	 * @access  protected
	 */
	function setPage($page){
		$this->page = intval($page);
		if ($this->page < 1){
			$this->page = 1;
		}
	}

	/**
	 * Add sort value to the object based on the $_REQUEST superglobal.
	 *
	 * @access  protected
	 */
	protected function initSort(){
		$defaultSort = '';
		if (is_object($this->searchSource)){
			$defaultSort = $this->searchSource->defaultSort;
			if ($defaultSort == 'newest_to_oldest'){
				$defaultSort = 'year';
			}elseif ($defaultSort == 'oldest_to_newest'){
				$defaultSort = 'year asc';
			}elseif ($defaultSort == 'user_rating'){
				$defaultSort = 'rating desc';
			}elseif ($defaultSort == 'popularity'){
				$defaultSort = 'popularity desc';
			}
		}
		if (isset($_REQUEST['sort'])){
			if (is_array($_REQUEST['sort'])){
				$sort = array_pop($_REQUEST['sort']);
			}else{
				$sort = $_REQUEST['sort'];
			}
			$this->sort = $sort;
		}elseif ($defaultSort != ''){
			$this->sort = $defaultSort;
		}else{
			// Is there a search-specific sort type set?
			$type = $_REQUEST['type'] ?? false;
			if ($type && isset($this->defaultSortByType[$type])){
				$this->sort = $this->defaultSortByType[$type];
				// If no search-specific sort type was found, use the overall default:
			}else{
				$this->sort = $this->defaultSort;
			}
		}
		//Validate the sort to make sure it is correct.
		if (!array_key_exists($this->sort, $this->sortOptions)){
			$this->sort = $this->defaultSort;
		}
	}

	public function setSort($sort){
		$this->sort = $sort;
	}

	/**
	 * Add filters to the object based on values found in the $_REQUEST superglobal.
	 *
	 * @access  protected
	 */
	protected function initFilters(){
		if (!empty($_REQUEST['filter'])){
			if (is_array($_REQUEST['filter'])){
				foreach ($_REQUEST['filter'] as $filter){
					if (!is_array($filter)){
						$this->addFilter(strip_tags($filter));
					}
				}
			}else{
				$this->addFilter(strip_tags($_REQUEST['filter']));
			}
		}
	}

	/**
	 * Build a url for the current search
	 *
	 * @access  public
	 * @return  string   URL of a search
	 */
	public function renderSearchUrl(){
		// Get the base URL and initialize the parameters attached to it:
		$url    = $this->getBaseUrl();
		$params = $this->getSearchParams();

		// Add any filters
		if (count($this->filterList) > 0){
			$encodedFilterArrayString = urlencode('filter[]'); // un-encoded braces technically not url allowed  (Good parsing for Accessibility 4.1.1)
			foreach ($this->filterList as $field => $filter){
				foreach ($filter as $value){
					if (preg_match('/\\[.*?\\sTO\\s.*?\\]/', $value)){
						$params[] = "$encodedFilterArrayString=$field:$value";
					}elseif (preg_match('/^\\(.*?\\)$/', $value)){
						$params[] = "$encodedFilterArrayString=$field:$value";
					}else{
						if (is_numeric($field)){
							$params[] = $encodedFilterArrayString . '=' . urlencode($value);
						}else{
							$params[] = $encodedFilterArrayString . '=' . urlencode("$field:\"$value\"");
						}
					}
				}
			}
		}

		// Sorting
		if ($this->sort != null){
			$params[] = 'sort=' . urlencode($this->sort);
		}

		// Page number
		if ($this->page != 1){
			// Don't url encode if it's the paging template
			if ($this->page == '%d'){
				$params[] = 'page=' . $this->page;
				// Otherwise... encode to prevent XSS.
			}else{
				$params[] = 'page=' . urlencode($this->page);
			}
		}

		// View
		if ($this->view != null){
			$params[] = 'view=' . urlencode($this->view);
		}elseif (isset($_REQUEST['view'])){
			$view = $_REQUEST['view'];
			if (is_array($view)){
				$view = array_pop($view);
			}
			$params[] = 'view=' . urlencode($view);
		}

		if ($this->searchSource){
			$params[] = 'searchSource=' . $this->searchSource;
		}

		// Join all parameters with an escaped ampersand,
		//   add to the base url and return
		return $url . implode('&', $params);
	}

	/**
	 * Return a url for use by pagination template
	 *
	 * @access  public
	 * @return  string   URL of a new search
	 */
	public function renderLinkPageTemplate(){
		$oldPage    = $this->page;              // Stash our old data for a minute
		$this->page = '%d';                     // Add the page template
		$url        = $this->renderSearchUrl(); // Get the new url
		$this->page = $oldPage;                 // Restore the old data
		return $url;                            // Return the URL
	}

	/**
	 * Return a url for the current search with a new sort
	 *
	 * @access  public
	 * @param   string   $newSort   A field to sort by
	 * @return  string   URL of a new search
	 */
	public function renderLinkWithSort($newSort){
		$oldSort    = $this->sort;              // Stash our old data for a minute
		$this->sort = $newSort;                 // Add the new sort
		$url        = $this->renderSearchUrl(); // Get the new url
		$this->sort = $oldSort;                 // Restore the old data
		return $url;                            // Return the URL
	}

	/**
	 * Return a list of urls for sorting, along with which option
	 *    should be currently selected.
	 *
	 * @access  public
	 * @return  array    Sort urls, descriptions and selected flags
	 */
	public function getSortList(){
		// Loop through all the current filter fields
		$valid = $this->getSortOptions();
		$list  = [];
		foreach ($valid as $sort => $desc){
			$list[$sort] = [
				'sortUrl'  => $this->renderLinkWithSort($sort),
				'desc'     => $desc,
				'selected' => ($sort == $this->sort)
			];
		}
		return $list;
	}

	/**
	 * Return a url for the current search with a new view
	 *
	 * @param string $newView The new view
	 *
	 * @return string         URL of a new search
	 * @access public
	 */
	public function renderLinkWithView($newView){
		$oldView    = $this->view;              // Stash our old data for a minute
		$this->view = $newView;                 // Add the new view
		$url        = $this->renderSearchUrl(); // Get the new url
		$this->view = $oldView;                 // Restore the old data
		return $url;                            // Return the URL
	}

	/**
	 * Return a list of urls for possible views, along with which option
	 *    should be currently selected.
	 *
	 * @return array View urls, descriptions and selected flags
	 * @access public
	 */
	public function getViewList(){
		// Loop through all the current views
		$valid = $this->getViewOptions();
		$list  = [];
		foreach ($valid as $view => $desc){
			$list[$view] = [
				'viewType' => $view,
				'viewUrl'  => $this->renderLinkWithView($view),
				'desc'     => $desc,
				'selected' => ($view == $this->view)
			];
		}
		return $list;
	}

	/**
	 * Return a url for the current search with a new limit
	 *
	 * @param string $newLimit The new limit
	 *
	 * @return string         URL of a new search
	 * @access public
	 */
	public function renderLinkWithLimit($newLimit){
		$oldLimit    = $this->limit;             // Stash our old data for a minute
		$oldPage     = $this->page;              // Stash our old data for a minute
		$this->limit = $newLimit;                // Add the new limit
		$this->page  = 1;                        // Remove page number
		$url         = $this->renderSearchUrl(); // Get the new url
		$this->limit = $oldLimit;                // Restore the old data
		$this->page  = $oldPage;                 // Restore the old data
		return $url;                             // Return the URL
	}

	/**
	 * Return a list of urls for possible limits, along with which option
	 *    should be currently selected.
	 *
	 * @return array Limit urls, descriptions and selected flags
	 * @access public
	 */
	public function getLimitList(){
		// Loop through all the current limits
		$valid = $this->getLimitOptions();
		$list  = [];
		if (is_array($valid) && count($valid) > 0){
			foreach ($valid as $limit){
				$list[$limit] = [
					'limitUrl' => $this->renderLinkWithLimit($limit),
					'desc'     => $limit,
					'selected' => ($limit == $this->limit)
				];
			}
		}
		return $list;
	}

	/**
	 * Basic 'getters'
	 *
	 * @access  public
	 * @return  mixed    various internal variables
	 */

	public function getAdvancedSearchTypes()  {return $this->advancedSearchTypes;}
	public function getBasicTypes()     {return $this->basicTypes;}
	public function getFilters()        {return $this->filterList;}
	public function getPage()           {return $this->page;}
	public function getLimit()          {return $this->limit;}
	public function getQuerySpeed()     {return $this->queryTime;}
	public function getRawSuggestions() {return $this->suggestions;}
	public function getResultTotal()    {return $this->resultsTotal;}
	public function getSearchId()       {return $this->searchId;}
	public function getQuery()          {return $this->query;}
	public function getSearchTerms()    {return $this->searchTerms;}
	public function getSearchType()     {return $this->searchType;}
	public function getSort()           {return $this->sort;}
	public function getStartTime()      {return $this->initTime;}
	public function getTotalSpeed()     {return $this->totalTime;}
	public function getView()           {return $this->view;}
	public function isSavedSearch()     {return $this->savedSearch;}

	/**
	 * Protected 'getters' for values not intended for use outside the class.
	 *
	 * @access  protected
	 * @return  mixed    various internal variables
	 */
	public function getSortOptions() { return $this->sortOptions; }

	/**
	 * Get an array of view options; protected since this should not be used
	 * outside of the class.
	 *
	 * @access protected
	 * @return array
	 */
	protected function getViewOptions(){
		return isset($this->viewOptions) && is_array($this->viewOptions) ? $this->viewOptions : [];
	}

	/**
	 * Get an array of limit options; protected since this should not be used
	 * outside of the class.
	 *
	 * @access protected
	 * @return array
	 */
	protected function getLimitOptions(){
		return $this->limitOptions ?? [];
	}

	/**
	 * Reset a simple query against the default index.
	 *
	 * @access  public
	 * @param   string  $query   Query string
	 * @param   string  $index   Index to search (exclude to use default)
	 */
	public function setBasicQuery($query, $index = null){
		$index             = $index ?? $this->defaultIndex;
		$this->searchTerms = [['index' => $index, 'lookfor' => $query]];
	}

	/**
	 * Set the number of search results returned per page.
	 *
	 * @access  public
	 * @param   int     $limit      New page limit value
	 */
	public function setLimit($limit){
		$this->limit = $limit;
	}

	public function setSearchSource($searchSource){
		$this->searchSource = $searchSource;
	}

	/**
	 * Add a field to facet on.
	 *
	 * @access  public
	 * @param   string  $newField   Field name
	 * @param   string  $newAlias   Optional on-screen display label
	 */
	public function addFacet($newField, $newAlias = null){
		$newAlias                     = $newAlias ?? $newField;
		$this->facetConfig[$newField] = $newAlias;
	}

	public function addFacetOptions($options){
		$this->facetOptions = $options;
	}

	/**
	 * Add a checkbox facet.  When the checkbox is checked, the specified filter
	 * will be applied to the search.  When the checkbox is not checked, no filter
	 * will be applied.
	 *
	 * @access  public
	 * @param   string  $filter     [field]:[value] pair to associate with checkbox
	 * @param   string  $desc       Description to associate with the checkbox
	 */
	public function addCheckboxFacet($filter, $desc){
		// Extract the facet field name from the filter, then add the
		// relevant information to the array.
		[$fieldName] = explode(':', $filter);
		$this->checkboxFacets[$fieldName] = ['desc' => $desc, 'filter' => $filter];
	}

	/**
	 * Get information on the current state of the boolean checkbox facets.
	 *
	 * @access  public
	 * @return  array
	 */
	public function getCheckboxFacets(){
		// Create a lookup array of filter removal URLs -- this will tell us
		// if any of the boxes are checked, and help us uncheck them if they are.
		//
		// Note that this assumes that each boolean filter's field name will only
		// show up once anywhere in the filter list -- this is why you can't use
		// the same field both in the checkbox facet list and the regular facet
		// list.
		$filters  = $this->getFilterList();
		$deselect = [];
		foreach ($filters as $currentSet){
			foreach ($currentSet as $current){
				$deselect[$current['field']] = $current['removalUrl'];
			}
		}

		// Now build up an array of checkbox facets with status booleans and
		// toggle URLs.
		$facets = [];
		foreach ($this->checkboxFacets as $field => $details){
			$facets[$field] = $details;
			if (isset($deselect[$field])){
				$facets[$field]['selected']  = true;
				$facets[$field]['toggleUrl'] = $deselect[$field];
			}else{
				$facets[$field]['selected']  = false;
				$facets[$field]['toggleUrl'] =
					$this->renderLinkWithFilter($details['filter']);
			}
		}
		return $facets;
	}

	/**
	 * Return an array of data summarising the results of a search.
	 *
	 * @access  public
	 * @return  array   summary of results
	 */
	public function getResultSummary(){
		$summary                = array();
		$summary['page']        = $this->page;
		$summary['perPage']     = $this->limit;
		$summary['resultTotal'] = $this->resultsTotal;
		$summary['startRecord'] = (($this->page - 1) * $this->limit) + 1; // 1st record is easy, work out the start of this page
		// Last record needs more care
		if ($this->resultsTotal < $this->limit) {
			// There are less records returned than one page, then use total results
			$summary['endRecord'] = $this->resultsTotal;
		} elseif (($this->page * $this->limit) > $this->resultsTotal) {
			// The end of the current page runs past the last record, use total results
			$summary['endRecord'] = $this->resultsTotal;
		} else {
			// Otherwise use the last record on this page
			$summary['endRecord'] = $this->page * $this->limit;
		}

		return $summary;
	}

	/**
	 * Get a link to a blank search restricted by the specified facet value.
	 *
	 * @access  protected
	 * @param   string      $field      The facet field to limit on
	 * @param   string      $value      The facet value to limit with
	 * @return  string                  The URL to the desired search
	 */
	protected function getExpandingFacetLink($field, $value){
		// Stash our old search
		$temp_data = $this->searchTerms;
		$temp_type = $this->searchType;

		// Add an empty search
		$this->searchType = $this->basicSearchType;
		$this->setBasicQuery('');

		// Get the link:
		$url = $this->renderLinkWithFilter("{$field}:{$value}");

		// Restore our old search
		$this->searchTerms = $temp_data;
		$this->searchType  = $temp_type;

		// Send back the requested link>
		return $url;
	}

	/**
	 * Returns the stored list of facets for the last search
	 *
	 * @access  public
	 * @param   array   $filter         Array of field => on-screen description
	 *                                  listing all of the desired facet fields;
	 *                                  set to null to get all configured values.
	 * @param   bool    $expandingLinks If true, we will include expanding URLs
	 *                                  (i.e. get all matches for a facet, not
	 *                                  just a limit to the current search) in
	 *                                  the return array.
	 * @return  array   Facets data arrays
	 */
	public function getFacetList($filter = null, $expandingLinks = false){
		// Assume no facets by default -- child classes can override this to extract
		// the necessary details from the results saved by processSearch().
		return [];
	}

	/**
	 * Disable logging. Used to stop administrative searches
	 *    appearing in search histories
	 *
	 * @access  public
	 */
	public function disableLogging() {
		$this->disableLogging = true;
	}

	/**
	 * Used during repeated deminification (such as search history).
	 *   To scrub fields populated above.
	 *
	 * @access  protected
	 */
	protected function purge(){
		$this->searchType   = $this->basicSearchType;
		$this->searchId     = null;
		$this->resultsTotal = null;
		$this->filterList   = null;
		$this->initTime     = null;
		$this->queryTime    = null;
		// An array so we don't have to initialise
		//   the empty array during population.
		$this->searchTerms = [];
	}

	/**
	 * Create a minified copy of this object for storage in the database.
	 *
	 * @access  protected
	 * @return  object     A SearchObject instance
	 */
	protected function minify(){
		// Clone ourself as a minified object
		$newObject = new minSO($this);
		// Return the new object
		return $newObject;
	}

	/**
	 * Initialise the object from a minified one stored in a cookie or database.
	 *  Needs to be kept up-to-date with the minSO object at the end of this file.
	 *
	 * @access  public
	 * @param   object  $minified     A minSO object
	 */
	public function deminify($minified){
		// Clean the object
		$this->purge();

		// Most values will transfer without changes
		if (isset($minified->q)){
			$this->query = $minified->q;
		}
		$this->searchId     = $minified->id;
		$this->initTime     = $minified->i;
		$this->queryTime    = $minified->s;
		$this->resultsTotal = $minified->r;
		$this->filterList   = $minified->f;
		$this->searchType   = $minified->ty;
		$this->sort         = $minified->sr;

		// Search terms, we need to expand keys
		$tempTerms = $minified->t;
		foreach ($tempTerms as $term){
			$newTerm = [];
			foreach ($term as $k => $v){
				switch ($k){
					case 'j' :
						$newTerm['join'] = $v;
						break;
					case 'i' :
						$newTerm['index'] = $v;
						break;
					case 'l' :
						$newTerm['lookfor'] = $v;
						break;
					case 'g' :
						$newTerm['group'] = [];
						foreach ($v as $line){
							$search = [];
							foreach ($line as $k2 => $v2){
								switch ($k2){
									case 'b' :
										$search['bool'] = $v2;
										break;
									case 'f' :
										$search['field'] = $v2;
										break;
									case 'l' :
										$search['lookfor'] = $v2;
										break;
								}
							}
							$newTerm['group'][] = $search;
						}
						break;
				}
			}
			$this->searchTerms[] = $newTerm;
		}
	}

	/**
	 * Add into the search table (history)
	 *
	 * @access  protected
	 */
	protected function addToHistory(){
		// Get the list of all old searches for this session and/or user
		require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
		$s = new SearchEntry();
		/** @var SearchEntry[] $searchHistory */
		$searchHistory = $s->getSearches(session_id(), UserAccount::isLoggedIn() ? UserAccount::getActiveUserId() : null);

		// Duplicate elimination
		$dupSaved  = false;
		foreach ($searchHistory as $oldSearch) {
			// Deminify the old search
			$minSO     = unserialize($oldSearch->search_object);
			$dupSearch = SearchObjectFactory::deminify($minSO);
			// See if the classes and urls match
			if (get_class($dupSearch) && get_class($this) &&
			$dupSearch->renderSearchUrl() == $this->renderSearchUrl()) {
				// Is the older search saved?
				if ($oldSearch->saved) {
					// Flag for later
					$dupSaved = true;
					// Record the details
					$this->searchId    = $oldSearch->id;
					$this->savedSearch = true;
				} else {
					// Delete this search
					$oldSearch->delete();
				}
			}
		}

		// Save this search unless we found a 'saved' duplicate
		if (!$dupSaved) {
			require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
			$search                = new SearchEntry();
			$search->session_id    = session_id();
			$search->created       = date('Y-m-d');
			$search->searchSource  = $this->searchSource;
//			$search->search_object = serialize($this->minify()); // Only minify once after we have the saved search id below

			$search->insert();
			// Record the details
			$this->searchId    = $search->id;
			$this->savedSearch = false;

			// Chicken and egg... We didn't know the id before insert
			$search->search_object = serialize($this->minify());
			$search->update();
		}
	}

	public function loadLastSearch(){
		if (isset($_SESSION['lastSearchId']) && is_numeric($_SESSION['lastSearchId'])){
			$lastSearchId = $_SESSION['lastSearchId'];
		}else{
			//No search to load, get out
			return null;
		}
		// Yes, retrieve it
		require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
		$search     = new SearchEntry();
		$search->id = $lastSearchId;
		if ($search->find(true)) {
			// Found, make sure the user has the
			//   rights to view this search
			$currentSessionId = session_id();
			if ($search->session_id == $currentSessionId || $search->user_id == UserAccount::getActiveUserId()) {
				// They do, deminify it to a new object.
				$minSO = unserialize($search->search_object);
				$savedSearch = SearchObjectFactory::deminify($minSO);
				return $savedSearch;
			} else {
				// Just get out, we don't need to show an error
				return null;
			}
		}
		return null;
	}

	/**
	 * If there is a saved search being loaded through $_REQUEST, redirect to the
	 * URL for that search.  If no saved search was requested, return false.  If
	 * unable to load a requested saved search, return a PEAR_Error object.
	 *
	 * @access  protected
	 * @var     string    $searchId
	 * @var     boolean   $redirect
	 * @var     boolean   $forceReload
	 * @return  mixed               Does not return on successful load, returns
	 *                              false if no search to restore, returns
	 *                              PEAR_Error object in case of trouble.
	 */
	public function restoreSavedSearch($searchId = null, $redirect = true, $forceReload = false){
		// Is this is a saved search?
		if (isset($_REQUEST['saved']) || $searchId != null){
			// Yes, retrieve it
			require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
			$search     = new SearchEntry();
			$search->id = strip_tags($_REQUEST['saved'] ?? $searchId);
			if (ctype_digit($search->id) /* Ensure is valid parameter */
				&& $search->find(true)){
				// Found, make sure the user has the
				//   rights to view this search
				if ($forceReload || $search->session_id == session_id() || (UserAccount::isLoggedIn() && $search->user_id == UserAccount::getActiveUserId())){
					// They do, deminify it to a new object.
					$minSO       = unserialize($search->search_object);
					$restoredSearch = SearchObjectFactory::deminify($minSO);

					// Now redirect to the URL associated with the saved search;
					// this simplifies problems caused by mixing different classes
					// of search object, and it also prevents the user from ever
					// landing on a "?saved=xxxx" URL, which may not persist beyond
					// the current session.  (We want all searches to be
					// persistent and bookmarkable).
					if ($redirect){
						header('Location: ' . $restoredSearch->renderSearchUrl());
						die();
					}else{
						return $restoredSearch;
					}
				}else{
					// They don't
					// TODO : Error handling -
					//    User is trying to view a saved search from
					//    another session (deliberate or expired) or
					//    associated with another user.
					return new PEAR_Error('Attempt to access invalid search ID');
				}
			}
		}

		// Report no saved search to restore.
		return false;
	}

	/**
	 * Initialise the object from the global
	 *  search parameters in $_REQUEST.
	 *
	 * @access  public
	 * @var string $searchSource
	 * @return  boolean
	 */
	public function init(string $searchSource = null){
		// Start the timer
		$mtime          = explode(' ', microtime());
		$this->initTime = $mtime[1] + $mtime[0];

		$this->searchSource = $searchSource;
		return true;
	}

	/**
	 * An optional de-initialise function.
	 *
	 *   At this stage it's used for finish of timing calculations
	 *   and logged search history.
	 *
	 *   Possible future uses will including closing child resources if
	 *     required (database connections) or finish database writes
	 *     (audit tables). Remember the destructor() can also be used
	 *     for mandatory stuff.
	 *   Might need parameters for logging level and whether to keep
	 *     the search in history etc.
	 *
	 * @access  public
	 */
	public function close(){
		// Finish timing
		$mtime           = explode(" ", microtime());
		$this->endTime   = $mtime[1] + $mtime[0];
		$this->totalTime = $this->endTime - $this->initTime;

		if (!$this->disableLogging){
			// Add to search history
			$this->addToHistory();
		}
	}

	/**
	 * DEBUGGING. Support function for debugOutput().
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function debugOutputSearchTerms(){
		// Advanced search
		if (isset($this->searchTerms[0]['group'])){
			$output = 'GROUP JOIN : ' . $this->searchTerms[0]['join'] . "<br>\n";
			for ($i = 0;$i < count($this->searchTerms);$i++){
				$output .= "BOOL ($i) : " . $this->searchTerms[$i]['group'][0]['bool'] . "<br>\n";
				for ($j = 0;$j < count($this->searchTerms[$i]['group']);$j++){
					$output .= "TERMS ($i)($j) : " . $this->searchTerms[$i]['group'][$j]['lookfor'] . "<br>\n";
					$output .= "SEARCH SPEC HANDLER ($i)($j) : " . $this->searchTerms[$i]['group'][$j]['field'] . "<br>\n";
				}
			}
			// Basic search
		}else{
			$output = 'TERMS : ' . ($this->searchTerms[0]['lookfor'] ?? '') . "<br>\n";
			$output .= 'SEARCH SPEC HANDLER : ' . ($this->searchTerms[0]['index'] ?? '') . "<br>\n";
		}

		return $output;
	}

	/**
	 * DEBUGGING. Use this to print out your search.
	 *
	 * @access  public
	 * @return  string
	 */
	public function debugOutput(){
		$output = 'VIEW : ' . $this->view . "<br>\n";
		$output .= $this->debugOutputSearchTerms();

		foreach ($this->filterList as $field => $filter){
			foreach ($filter as $value){
				$output .= "FILTER : $field => $value<br>\n";
			}
		}
		$output .= 'PAGE : ' . $this->page . "<br>\n";
		$output .= 'SORT : ' . $this->sort . "<br>\n";
		$output .= 'TIMING : START : ' . $this->initTime . "<br>\n";
		$output .= 'TIMING : QUERY.S : ' . $this->queryStartTime . "<br>\n";
		$output .= 'TIMING : QUERY.E : ' . $this->queryEndTime . "<br>\n";
		$output .= 'TIMING : FINISH : ' . $this->endTime . "<br>\n";

		return $output;
	}

	/**
	 * Start the timer to figure out how long a query takes.  Complements
	 * stopQueryTimer().
	 *
	 * @access protected
	 */
	protected function startQueryTimer(){
		// Get time before the query
		$time                 = explode(" ", microtime());
		$this->queryStartTime = $time[1] + $time[0];
	}

	/**
	 * End the timer to figure out how long a query takes.  Complements
	 * startQueryTimer().
	 *
	 * @access protected
	 */
	protected function stopQueryTimer(){
		$time               = explode(' ', microtime());
		$this->queryEndTime = $time[1] + $time[0];
		$this->queryTime    = $this->queryEndTime - $this->queryStartTime;
	}

	/**
	 * Return the field (index) searched by a basic search
	 *
	 * @access  public
	 * @return  string   The searched index
	 */
	public function getSearchIndex(){
		// Single search index does not apply to advanced search:
		if ($this->searchType == $this->advancedSearchType){
			return null;
		}elseif (isset($this->searchTerms[0]['index'])){
			return $this->searchTerms[0]['index'];
		}else{
			return 'Keyword';
		}
	}

	/**
	 * Any search types that use a field of type text-left will have an upper limit for search phrases that will return matching results.
	 *  Any search phrases longer than the upper limit will always have no results.
	 *
	 * @return array  Array of search types/search indexes that text-left fields
	 */
	public function getTextLeftSearchIndexes(){
		return [];
	}

	/**
	 * Find a word amongst the current search terms
	 *
	 * @access  protected
	 * @param   string   $needle  Search term to find
	 * @return  bool     True/False if the word was found
	 */
	protected function findSearchTerm($needle) {
		// Advanced search
		if (isset($this->searchTerms[0]['group'])) {
			foreach ($this->searchTerms as $group) {
				foreach ($group['group'] as $search) {
					if (preg_match("/\b$needle\b/", $search['lookfor'])) {
						return true;
					}
				}
			}

			// Basic search
		} else {
			foreach ($this->searchTerms as $haystack) {
				if (preg_match("/\b$needle\b/", $haystack['lookfor'])) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Replace a search term in the query
	 *
	 * @access  protected
	 * @param   string   $from  Search term to find
	 * @param   string   $to    Search term to insert
	 */
	protected function replaceSearchTerm($from, $to) {
		// Escape $from so it is regular expression safe (just in case it
		// includes any weird punctuation -- unlikely but possible):
		$from = preg_quote($from, '/');

		// If we are looking for a quoted phrase
		// we can't use word boundaries
		if (strpos($from, '"') === false) {
			$pattern = "/\b$from\b/i";
		} else  {
			$pattern = "/$from/i";
		}

		// Advanced search
		if (isset($this->searchTerms[0]['group'])) {
			for ($i = 0; $i < count($this->searchTerms); $i++) {
				for ($j = 0; $j < count($this->searchTerms[$i]['group']); $j++) {
					$this->searchTerms[$i]['group'][$j]['lookfor'] =
					preg_replace($pattern, $to,
					$this->searchTerms[$i]['group'][$j]['lookfor']);
				}
			}
			// Basic search
		} else {
			for ($i = 0; $i < count($this->searchTerms); $i++) {
				// Perform the replacement:
				$this->searchTerms[$i]['lookfor'] = preg_replace($pattern,
				$to, $this->searchTerms[$i]['lookfor']);
			}
		}
	}

	/**
	 * Return a query string for the current search with
	 *   a search term replaced
	 *
	 * @access  public
	 * @param   string   $oldTerm   The old term to replace
	 * @param   string   $newTerm   The new term to search
	 * @return  string   query string
	 */
	public function getDisplayQueryWithReplacedTerm($oldTerm, $newTerm){
		// Stash our old data for a minute
		$oldTerms = $this->searchTerms;
		// Replace the search term
		$this->replaceSearchTerm($oldTerm, $newTerm);
		// Get the new query string
		$query = $this->displayQuery();
		// Restore the old data
		$this->searchTerms = $oldTerms;
		// Return the query string
		return $query;
	}

	/**
	 * Input Tokenizer - Specifically for spelling purposes
	 *
	 * Because of its focus on spelling, these tokens are unsuitable
	 * for actual searching. They are stripping important search data
	 * such as joins and groups, simply because they don't need to be
	 * spellchecked.
	 *
	 * @param string $input
	 * @return  array               Tokenized array
	 * @access  public
	 */
	public function spellingTokens($input){
		$joins = ["AND", "OR", "NOT"];
		$paren = ["(" => "", ")" => ""];

		// Base of this algorithm comes straight from
		// PHP doco examples & benighted at gmail dot com
		// http://php.net/manual/en/function.strtok.php
		$tokens = [];
		$token = strtok($input,' ');
		while ($token) {
			// find bracketed tokens
			if ($token[0] =='(') {$token .= ' '.strtok(')').')';}
			// find double quoted tokens
			if ($token[0] =='"') {$token .= ' '.strtok('"').'"';}
			// find single quoted tokens
			if ($token[0] =="'") {$token .= ' '.strtok("'")."'";}
			$tokens[] = $token;
			$token = strtok(' ');
		}
		// Some cleaning of tokens that are just boolean joins
		//  and removal of brackets
		$return = [];
		foreach ($tokens as $token) {
			// Ignore join
			if (!in_array($token, $joins)) {
				// And strip parentheses
				$final = trim(strtr($token, $paren));
				if ($final != "") $return[] = $final;
			}
		}
		return $return;
	}

	/**
	 * Return a url for the current search with a search term replaced
	 *
	 * @access  public
	 * @param   string   $oldTerm   The old term to replace
	 * @param   string   $newTerm   The new term to search
	 * @return  string   URL of a new search
	 */
	public function renderLinkWithReplacedTerm($oldTerm, $newTerm){
		// Stash our old data for a minute
		$oldTerms = $this->searchTerms;
		$oldPage  = $this->page;
		// Switch to page 1 -- it doesn't make sense to maintain the current page
		// when changing the contents of the search
		$this->page = 1;
		// Replace the term
		$this->replaceSearchTerm($oldTerm, $newTerm);
		// Get the new url
		$url = $this->renderSearchUrl();
		// Restore the old data
		$this->searchTerms = $oldTerms;
		$this->page        = $oldPage;
		// Return the URL
		return $url;
	}

	/**
	 * Get the templates used to display recommendations for the current search.
	 *
	 * @access  public
	 * @param   string      $location           'top' or 'side'
	 * @return  array       Array of templates to display at the specified location.
	 */
	public function getRecommendationsTemplates($location = 'top'){
		$returnValue = [];
		if (isset($this->recommend[$location]) && !empty($this->recommend[$location])){
			/** @var RecommendationInterface $current */
			foreach ($this->recommend[$location] as $current){
				$returnValue[] = $current->getTemplate();
			}
		}
		return $returnValue;
	}

	/**
	 * Load all recommendation settings from the relevant ini file.  Returns an
	 * associative array where the key is the location of the recommendations (top
	 * or side) and the value is the settings found in the file (which may be either
	 * a single string or an array of strings).
	 *
	 * @access  protected
	 * @return  array           associative: location (top/side) => search settings
	 */
	protected function getRecommendationSettings(){
		// Load the necessary settings to determine the appropriate recommendations
		// module:
		$search         = $this->searchTerms;
		$searchSettings = getExtraConfigArray($this->recommendIni);

		// If we have just one search type, save it so we can try to load a
		// type-specific recommendations module:
		$searchType = count($search) == 1 && isset($search[0]['index']) ? $search[0]['index'] : false;

		// Load a type-specific recommendations setting if possible, or the default
		// otherwise:
		$recommend = array();
		if ($searchType && isset($searchSettings['TopRecommendations'][$searchType])){
			$recommend['top'] = $searchSettings['TopRecommendations'][$searchType];
		}else{
			$recommend['top'] = $searchSettings['General']['default_top_recommend'] ?? false;
		}
		if ($searchType && isset($searchSettings['SideRecommendations'][$searchType])){
			$recommend['side'] = $searchSettings['SideRecommendations'][$searchType];
		}else{
			$recommend['side'] = $searchSettings['General']['default_side_recommend'] ?? false;
		}

		return $recommend;
	}

	/**
	 * Initialize the recommendations module based on current searchTerms.
	 *
	 * @access  protected
	 */
	protected function initRecommendations(){
		// If no settings were found, quit now:
		$settings = $this->getRecommendationSettings();
		if (empty($settings)){
			$this->recommend = false;
			return;
		}

		// Process recommendations for each location:
		$this->recommend = ['top' => [], 'side' => []];
		foreach ($settings as $location => $currentSet){
			// If the current location is disabled, skip processing!
			if (empty($currentSet)){
				continue;
			}
			// Make sure the current location's set of recommendations is an array;
			// if it's a single string, this normalization will simplify processing.
			if (!is_array($currentSet)){
				$currentSet = [$currentSet];
			}
			// Now loop through all recommendation settings for the location.
			foreach ($currentSet as $current){
				// Break apart the setting into module name and extra parameters:
				$current = explode(':', $current);
				$module  = array_shift($current);
				$params  = implode(':', $current);

				// Can we build a recommendation module with the provided settings?
				// If the factory throws an error, we'll assume for now it means we
				// tried to load a non-existent module, and we'll ignore it.
				$obj = RecommendationFactory::initRecommendation($module, $this, $params);
				if ($obj && !PEAR_Singleton::isError($obj)){
					$obj->init();
					$this->recommend[$location][] = $obj;
				}
			}
		}
	}

	/**
	 * Load all available facet settings.  This is mainly useful for showing
	 * appropriate labels when an existing search has multiple filters associated
	 * with it.
	 *
	 * @access  public
	 * @param   string      $preferredSection       Section to favor when loading
	 *                                              settings; if multiple sections
	 *                                              contain the same facet, this
	 *                                              section's description will be
	 *                                              favored.
	 */
	public function activateAllFacets($preferredSection = false){
		// By default, there is only set of facet settings, so this function isn't
		// really necessary.  However, in the Search History screen, we need to
		// use this for Solr-based Search Objects, so we need this dummy method to
		// allow other types of Search Objects to co-exist with Solr-based ones.
		// See the Solr Search Object for details of how this works if you need to
		// implement context-sensitive facet settings in another module.
	}

	/**
	 * Get a human-readable presentation version of the advanced search query
	 * stored in the object.  This will not work if $this->searchType is not
	 * 'advanced.'
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function buildAdvancedDisplayQuery(){
		// Groups and exclusions. This mirrors some logic in Solr.php
		$groups   = [];
		$excludes = [];

		foreach ($this->searchTerms as $search){
			$thisGroup = [];
			// Process each search group
			foreach ($search['group'] as $group){
				// Build this group individually as a basic search
				$thisGroup[] = $group['field'] . ':' . $group['lookfor'];
			}
			// Is this an exclusion (NOT) group or a normal group?
			if ($search['group'][0]['bool'] == 'NOT'){
				$excludes[] = implode(' OR ', $thisGroup);
			}else{
				$groups[] = implode(' ' . $search['group'][0]['bool'] . ' ', $thisGroup);
			}
		}

		// Base 'advanced' query
		if (count($groups) > 0) {
			// TODO: Only surround with parentheses when  count($groups) > 1   ???
			$searchPhrasesConnector = ') ' . $this->searchTerms[0]['join'] . ' (';
			$output     = '(' . implode($searchPhrasesConnector, $groups) . ')';
		} else {
			$output = ''; // Initialize string in case there are excludes below
		}

		// Concatenate exclusion after that
		if (count($excludes) > 0){
			$output .= ' NOT ((' . implode(') OR (', $excludes) . '))';
		}

		return $output;
	}

	/**
	 * Take a string that is presumably the Displayed query string from an Advanced Search
	 * and reconstruct the Advanced Search Form style search terms array
	 *
	 * @param string $advancedSearchDisplayQuery
	 * @return array
	 */
	public function buildAdvancedSearchTermsFromAdvancedDisplayQuery(string $advancedSearchDisplayQuery){
		require_once ROOT_DIR . '/sys/SearchObject/ParensParser.php';
		$parser                       = new ParensParser();
		$arrayStructuredByParentheses = $parser->parse($advancedSearchDisplayQuery);

		$searchTerms = [];
		$defaultJoin = (!empty($arrayStructuredByParentheses[1]) && is_string($arrayStructuredByParentheses[1]) && self::isBooleanKeyword($arrayStructuredByParentheses[1])) ? $arrayStructuredByParentheses[1] : 'AND';
		$groupJoin   = $defaultJoin;
		foreach ($arrayStructuredByParentheses as $value){
			if (is_string($value)){
				if (self::isBooleanKeyword($value)){
					$groupJoin = trim($value);
				}else{
					$searchTerms['group'] = $this->parseBooleanSearchClauses($value);
					$searchTerms['join']  = $groupJoin;
				}

			}elseif (is_array($value)){
				//TODO: use of $defaultJoin
				if (count($value) == 1 && is_array($value[0])){
					// Doubled parentheses
					$array = $this->handleSubArraysAdvancedSearchParsing($value[0], $groupJoin);
					if (!empty($array)){
						$searchTerms = array_merge($searchTerms, $array);
					}
				}else{
					$array = $this->handleSubArraysAdvancedSearchParsing($value, $groupJoin);
					if (!empty($array)){
						$searchTerms = array_merge($searchTerms, $array);
					}
				}
			}
		}
		return $searchTerms;
	}

	/**
	 * Handle an entry of the $arrayStructuredByParentheses from method above and parse into the array structure expected
	 * for the Advanced Search Form searches.
	 *
	 * @param array $value
	 * @param string $groupJoin
	 * @return array
	 */
	private function handleSubArraysAdvancedSearchParsing(array $value, string $groupJoin){
		$searchTerms = [];
		foreach ($value as $subValue){
			if (is_string($subValue)){
				if (self::isBooleanKeyword($subValue)){
					$groupJoin = $subValue;
				} else{
					$groupArray = $this->parseBooleanSearchClauses($subValue);
					$searchTerms[]    = [
						'group' => $groupArray,
						'join'  => $groupJoin,
					];
				}
			}
		}
		return $searchTerms;
	}

	/**
	 * Determine if a string is a boolena operator term.
	 * This is used when attempting to construct an Advanced Search Form search query structure
	 * from a presumed Advanced Search Form Display query.
	 *
	 * @param string $string
	 * @return bool
	 */
	private static function isBooleanKeyword(string $string){
		return in_array(trim($string), ['AND','OR','NOT']);
	}

	/**
	 * Parse a search phrase from a clause of a presumed Advanced Search Form Display query.
	 * It breaks up the phrases by capitalized boolean phrases and reconstructs the corresponding
	 * search term arrays used for the Advanced Search Form queries.
	 *
	 * @param string $searchTerm
	 * @param string|null $groupJoin
	 * @return array
	 */
	private function parseBooleanSearchClauses(string $searchTerm, string $groupJoin = null){
		$clauses       = [];
		$searchPhrases = preg_split('/(?:\s(AND NOT|OR NOT|AND|OR|NOT)\s)/', $searchTerm, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		// Split by boolean phrases so that we have search phrases on their own; as well as the boolean operator without surrounding spaces

		foreach ($searchPhrases as $i => $term){
			if (self::isBooleanKeyword($term)){
				// Our boolean operator
				// TODO: handling for AND NOT as well as OR NOT
				$bool = $term;
			}else{
				// A single search phrase
				$aClause = $this->parseAdvancedSearchFormSearchWord($term);
			}
			if ($i % 2 == 1){
//				$aClause['bool'] = $bool ?? ($groupJoin == 'NOT' ? 'NOT' : 'AND'); // I think we only need the groupJoin variable when it is NOT
				$aClause['bool'] = $bool ?? 'AND';
				$clauses[]       = $aClause;
				unset($bool, $aClause);  // undo for the next round of phrases
			}
		}
		if (isset($aClause)){
			// Catch final search phrases
			$aClause['bool'] = $bool ?? 'AND';
			$clauses[]       = $aClause;
		}
		return $clauses;
	}

	/**
	 * Parse a single search phrase that contains the Advanced Search Spec Handler and the phrase to search with (separated by a colon)
	 * ie. SearchHandler:phrase
	 * eg. Title:Othello
	 *
	 * @param string $searchTerm
	 * @return array|void
	 */
	private function parseAdvancedSearchFormSearchWord(string $searchTerm){
		if (strpos($searchTerm, ':')){
			[$searchSpecHandler, $lookfor] = explode(':', $searchTerm, 2);
			return [
				'field'   => $searchSpecHandler,
				'lookfor' => $lookfor,
			];
		}
//		else {
//			//TODO: error logging;
//		}
	}

	public function convertBasicToAdvancedSearch(){
		$searchTerms  = $this->searchTerms;
		$searchString = $searchTerms[0]['lookfor'];
		$searchIndex  = $searchTerms[0]['index'];

		$this->searchTerms = [
			[
				'group' => [
					0 => [
						'field'   => $searchIndex,
						'lookfor' => $searchString,
						'bool'    => 'AND'
					]
				],
				'join'  => 'AND'
			]
		];

		$this->searchType = 'advanced';
	}

	/**
	 * Determine whether a search string has a reference to an Advanced Search Handler (aka Advanced Search Types) and
	 * therefore should be treated as an Adcanced Search Form public query.
	 *
	 * @param string $searchPhrase
	 * @return bool
	 */
	public function isAdvancedSearchFormDisplayQuery(string $searchPhrase){
		foreach ($this->advancedSearchTypes as $advancedSearchHandler => $label_ignored){
			if (strpos($searchPhrase, $advancedSearchHandler . ':') !== false){
				return true;
			}
		}
		return false;
	}

	/**
	 * Build a string for onscreen display showing the
	 *   query used in the search (not the filters).
	 *
	 * @access  public
	 * @return  string   user friendly version of 'query'
	 */
	public function displayQuery(){
		// Advanced search?
		if ($this->searchType == $this->advancedSearchType){
			return $this->buildAdvancedDisplayQuery();
		}
		// Default -- Basic search:
		return $this->searchTerms[0]['lookfor'];
	}

	/**
	 * Turn the list of spelling suggestions into an array of urls
	 *   for on-screen use to implement the suggestions.
	 *
	 * @access  public
	 * @return  array     Spelling suggestion data arrays
	 */
	abstract public function getSpellingSuggestions();

	/**
	 * Process one instance of a spelling replacement and modify the return
	 *   data structure with the details of what was done.
	 *
	 * @access  public
	 * @param   string   $term        The actually term we're replacing
	 * @param   string   $targetTerm  The term above, or the token it is inside
	 * @param   boolean  $inToken     Flag for whether the token or term is used
	 * @param   array    $details     The spelling suggestions
	 * @param   array    $returnArray Return data structure so far
	 * @return  array    $returnArray modified
	 */
	protected function doSpellingReplace($term, $targetTerm, $inToken, $details, $returnArray){
		global $configArray;

		$returnArray[$targetTerm]['freq'] = $details['freq'];
		foreach ($details['suggestions'] as $word => $freq){
			// If the suggested word is part of a token
			$replacement = $inToken ? str_replace($term, $word, $targetTerm) : $word; // We need to make sure we replace the whole token
			//  Do we need to show the whole, modified query?
			$label = $configArray['Spelling']['phrase'] ? $this->getDisplayQueryWithReplacedTerm($targetTerm, $replacement) : $replacement;
			// Basic spelling suggestion data
			$returnArray[$targetTerm]['suggestions'][$label] = array(
				'freq'        => $freq,
				'replace_url' => $this->renderLinkWithReplacedTerm($targetTerm, $replacement)
			);
			// Only generate expansions if enabled in config
			if ($configArray['Spelling']['expand']){
				// Parentheses differ for shingles
				$replacement                                                   = strstr($targetTerm, " ") !== false ? "(($targetTerm) OR ($replacement))" : "($targetTerm OR $replacement)";
				$returnArray[$targetTerm]['suggestions'][$label]['expand_url'] = $this->renderLinkWithReplacedTerm($targetTerm, $replacement);
			}
		}

		return $returnArray;
	}

	/**
	 * Actually process and submit the search; in addition to returning results,
	 * this method is responsible for populating various class properties that
	 * are returned by other get methods (i.e. getFacetList).
	 *
	 * @access  public
	 * @param   bool   $returnIndexErrors  Should we die inside the index code if
	 *                                     we encounter an error (false) or return
	 *                                     it for access via the getIndexError()
	 *                                     method (true)?
	 * @param   bool   $recommendations    Should we process recommendations along
	 *                                     with the search itself?
	 * @return  object   Search results (format may vary from class to class).
	 */
	abstract public function processSearch($returnIndexErrors = false,
	$recommendations = false);

	/**
	 * Get error message from index response, if any.  This will only work if
	 * processSearch was called with $returnIndexErrors set to true!
	 *
	 * @access  public
	 * @return  mixed       false if no error, error string otherwise.
	 */
	abstract public function getIndexError();

	abstract public function getNextPrevLinks();

	/**
	 * Set weather or not this is a primary search.  If it is, we will show links to it in search result debugging
	 * @param boolean $flag
	 */
	public function setPrimarySearch($flag){
		$this->isPrimarySearch = $flag;
	}

	/**
	 * Return a url of the current search as an RSS feed.
	 *
	 * @access  public
	 * @return  string    URL
	 */
	public function getRSSUrl(){
		// Stash our old data for a minute
		$oldView = $this->view;
		$oldPage = $this->page;

		// Add the new view
		$this->view = 'rss';
		$this->page = 1; // Remove page number

		// Get the new url
		$url = $this->renderSearchUrl();

		// Restore the old data
		$this->view = $oldView;
		$this->page = $oldPage;

		// Return the URL
		return $url;
	}

	/**
	 * Return a url of the current search as an Excel Spreadsheet.
	 *
	 * @access  public
	 * @return  string    URL
	 */
	public function getExcelUrl(){
		// Stash our old data for a minute
		$oldView = $this->view;
		$oldPage = $this->page;
		// Add the new view
		$this->view = 'excel';
		// Remove page number
		$this->page = 1;
		// Get the new url
		$url = $this->renderSearchUrl();
		// Restore the old data
		$this->view = $oldView;
		$this->page = $oldPage;
		// Return the URL
		return $url;
	}

}//End of SearchObject_Base

/**
 * ****************************************************
 *
 * A minified search object used exclusively for trimming
 *  a search object down to it's barest minimum size
 *  before storage in a cookie or database.
 *
 * It's still contains enough data granularity to
 *  programmatically recreate search urls.
 *
 * This class isn't intended for general use, but simply
 *  a way of storing/retrieving data from a search object:
 *
 * eg. Store
 * $searchHistory[] = serialize($this->minify());
 *
 * eg. Retrieve
 * $searchObject  = SearchObjectFactory::initSearchObject();
 * $searchObject->deminify(unserialize($search));
 *
 */
class minSO {
	public $t = [];  // search terms
	public $f = [];  // search filters
	public $hf = []; // hidden search filters
	public $fc = []; // facet configurations
	public $id, $i, $s, $r, $ty, $sr;

	/**
	 * Constructor. Building minified object from the
	 *    searchObject passed in. Needs to be kept
	 *    up-to-date with the deminify() function on
	 *    searchObject.
	 *
	 * @access  public
	 */
	public function __construct($searchObject){
		// Most values will transfer without changes
		$this->id = $searchObject->getSearchId();
		$this->i  = $searchObject->getStartTime();
		$this->s  = $searchObject->getQuerySpeed();
		$this->r  = $searchObject->getResultTotal();
		$this->ty = $searchObject->getSearchType();
		$this->sr = $searchObject->getSort();
		$this->q  = $searchObject->getQuery();

		// Search terms, we'll shorten keys
		$tempTerms = $searchObject->getSearchTerms();
		foreach ($tempTerms as $term){
			$newTerm = [];
			foreach ($term as $k => $v){
				switch ($k){
					case 'join'    :
						$newTerm['j'] = $v;
						break;
					case 'index'   :
						$newTerm['i'] = $v;
						break;
					case 'lookfor' :
						$newTerm['l'] = $v;
						break;
					case 'group' :
						$newTerm['g'] = [];
						foreach ($v as $line){
							$search = [];
							foreach ($line as $k2 => $v2){
								switch ($k2){
									case 'bool'    :
										$search['b'] = $v2;
										break;
									case 'field'   :
										$search['f'] = $v2;
										break;
									case 'lookfor' :
										$search['l'] = $v2;
										break;
								}
							}
							$newTerm['g'][] = $search;
						}
						break;
				}
			}
			$this->t[] = $newTerm;
		}

		// It would be nice to shorten filter fields too, but
		//      it would be a nightmare to maintain.
		$this->f = $searchObject->getFilters();


		// Add Hidden Filters if Present
		if (method_exists($searchObject, 'getHiddenFilters')){
			$this->hf = $searchObject->getHiddenFilters();
		}

		// Add Facet Configurations if Present
		if (method_exists($searchObject, 'getFacetConfig')){
			$this->fc = $searchObject->getFacetConfig();
		}

		// TODO: Add any other data needed to restore Islandora searches
	}
} //End of minso object (not SearchObject_Base)
