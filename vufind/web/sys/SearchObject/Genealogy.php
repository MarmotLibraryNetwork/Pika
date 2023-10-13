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
require_once ROOT_DIR . '/sys/Search/Solr.php';
require_once ROOT_DIR . '/sys/SearchObject/Base.php';
require_once ROOT_DIR . '/RecordDrivers/Factory.php';
require_once ROOT_DIR . '/sys/Location/Location.php';

/**
 * Search Object class
 *
 * This is the default implementation of the SearchObjectBase class, providing the
 * Solr-driven functionality used by VuFind's standard Search module.
 */
class SearchObject_Genealogy extends SearchObject_Base {
	// Publicly viewable version
	private $publicQuery = null;
	// Facets
	private $facetLimit = 30;
	private $facetOffset = null;
	private $facetPrefix = null;
	private $facetSort = null;

	// Index
	private $index = null;
	// Field List
	private $fields = '*,score';
	// HTTP Method
	private $method = 'GET';
//	private $method = 'POST';
	// Result
	private $indexResult;

	// OTHER VARIABLES
	// Index
	/** @var Solr */
	private $indexEngine = null;
	// Facets information
	private $allFacetSettings = [];    // loaded from facets.ini

	// Spelling
	private $spellingLimit = 3;
	private $spellQuery = [];
	private $dictionary = 'default';
	private $spellSimple = false;
	private $spellSkipNumeric = true;

	// In each class, set the specific range filters based on the Search Object
	protected $rangeFilters = [];
	protected $dateFilters = ['birthYear', 'deathYear'];

	/**
	 * Constructor. Initialise some details about the server
	 *
	 * @access  public
	 */
	public function __construct(){
		// Call base class constructor
		parent::__construct();

		global $configArray;
		global $timer;

		$this->searchType      = 'genealogy';
		$this->basicSearchType = 'genealogy';
		$this->searchSource    = 'genealogy'; // This is required so that saved genealogy searches can be restored with the correct source

		// Initialise the index
		// Include our solr index
		$class = $configArray['Genealogy']['engine'];
		require_once ROOT_DIR . "/sys/Search/$class.php";
		$this->indexEngine = new $class($configArray['Genealogy']['url'], $configArray['Genealogy']['default_core']);
		$timer->logTime('Created Index Engine for Genealogy');

		// Get default facet settings
		$this->allFacetSettings = getExtraConfigArray('genealogyFacets');
		$this->facetConfig      = [];
		$facetLimit             = $this->getFacetSetting('Results_Settings', 'facet_limit');
		if (is_numeric($facetLimit)){
			$this->facetLimit = $facetLimit;
		}
		$translatedFacets = $this->getFacetSetting('Advanced_Settings', 'translated_facets');
		if (is_array($translatedFacets)){
			$this->translatedFacets = $translatedFacets;
		}

		// Load search preferences:
		$searchSettings     = getExtraConfigArray('genealogySearches');
		$this->defaultIndex = 'GenealogyKeyword';
		if (isset($searchSettings['General']['default_sort'])){
			$this->defaultSort = $searchSettings['General']['default_sort'];
		}
		if (isset($searchSettings['DefaultSortingByType']) &&
			is_array($searchSettings['DefaultSortingByType'])){
			$this->defaultSortByType = $searchSettings['DefaultSortingByType'];
		}
		if (isset($searchSettings['Basic_Searches'])){
			$this->basicTypes = $searchSettings['Basic_Searches'];
		}
		if (isset($searchSettings['Advanced_Searches'])){
			$this->advancedSearchTypes = $searchSettings['Advanced_Searches'];
		}

		// Load sort preferences (or defaults if none in .ini file):
		if (isset($searchSettings['Sorting'])){
			$this->sortOptions = $searchSettings['Sorting'];
		}else{
			$this->sortOptions = [
				'relevance' => 'sort_relevance',
				'year'      => 'sort_year',
				'year asc'  => 'sort_year asc',
				'title'     => 'sort_title'
			];
		}

		// Load Spelling preferences
		$this->spellcheck       = $configArray['Spelling']['enabled'];
		$this->spellingLimit    = $configArray['Spelling']['limit'];
		$this->spellSimple      = $configArray['Spelling']['simple'];
		$this->spellSkipNumeric = isset($configArray['Spelling']['skip_numeric']) ?
			$configArray['Spelling']['skip_numeric'] : true;

		// Debugging
		$this->indexEngine->debug          = $this->debug;
		$this->indexEngine->debugSolrQuery = $this->debugSolrQuery;

		$this->recommendIni = 'genealogySearches';


		$timer->logTime('Setup Solr Search Object');
	}

	/**
	 * Initialise the object from the global
	 *  search parameters in $_REQUEST.
	 *
	 * @access  public
	 * @return  boolean
	 */
	public function init($searchSource = 'genealogy'){
		// Call the standard initialization routine in the parent:
		parent::init('genealogy');

		//********************
		// Check if we have a saved search to restore -- if restored successfully,
		// our work here is done; if there is an error, we should report failure;
		// if restoreSavedSearch returns false, we should proceed as normal.
		$restored = $this->restoreSavedSearch();
		if ($restored === true){
			return true;
		}elseif (PEAR_Singleton::isError($restored)){
			return false;
		}

		//********************
		// Initialize standard search parameters
		$this->initView();
		$this->initPage();
		$this->initSort();
		$this->initFilters();

		//********************
		// Basic Search logic
		if (!$this->initBasicSearch()){
			$this->initAdvancedSearch();
		}

		// If a query override has been specified, log it here
		if (isset($_REQUEST['q'])){
			$this->query = $_REQUEST['q'];
		}

		return true;
	} // End init()

	/**
	 * Initialise the object for retrieving advanced
	 *   search screen facet data from inside solr.
	 *
	 * @access  public
	 * @return  boolean
	 */
	public function initAdvancedFacets(){
		// Call the standard initialization routine in the parent:
		parent::init();

		//********************
		// Adjust facet options to use advanced settings
		$this->facetConfig = isset($this->allFacetSettings['Advanced']) ? $this->allFacetSettings['Advanced'] : [];
		$facetLimit        = $this->getFacetSetting('Advanced_Settings', 'facet_limit');
		if (is_numeric($facetLimit)){
			$this->facetLimit = $facetLimit;
		}

		// Spellcheck is not needed for facet data!
		$this->spellcheck = false;

		//********************
		// Basic Search logic
		$this->searchTerms[] = [
			'index'   => $this->defaultIndex,
			'lookfor' => ""
		];

		return true;
	}

	/**
	 * Return the specified setting from the facets.ini file.
	 *
	 * @access  public
	 * @param string $section The section of the facets.ini file to look at.
	 * @param string $setting The setting within the specified file to return.
	 * @return  string    The value of the setting (blank if none).
	 */
	public function getFacetSetting($section, $setting){
		return isset($this->allFacetSettings[$section][$setting]) ?
			$this->allFacetSettings[$section][$setting] : '';
	}

	public function getDebugTiming() {
		if ($this->debug && isset($this->indexResult['debug'])){
			return json_encode($this->indexResult['debug']['timing'], JSON_PRETTY_PRINT);
		}
		return null;
	}

	/**
	 * Used during repeated deminification (such as search history).
	 *   To scrub fields populated above.
	 *
	 * @access  private
	 */
	protected function purge(){
		// Call standard purge:
		parent::purge();

		// Make some Solr-specific adjustments:
		$this->query       = null;
		$this->publicQuery = null;
	}

	/**
	 * Switch the spelling dictionary to basic
	 *
	 * @access  public
	 */
	public function useBasicDictionary(){
		$this->dictionary = 'basicSpell';
	}

	public function getQuery(){
		return $this->query;
	}

	public function getIndexEngine(){
		return $this->indexEngine;
	}

	/**
	 * Return the field (index) searched by a basic search
	 *
	 * @access  public
	 * @return  string   The searched index
	 */
	public function getSearchIndex(){
		// Use normal parent method for non-advanced searches.
		return $this->searchType == $this->basicSearchType ? parent::getSearchIndex() : null;
	}

	/**
	 * Return the record set from the search results.
	 *
	 * @access  public
	 * @return  array   recordSet
	 */
	public function getResultRecordSet(){
		//Marmot add shortIds without dot for use in display.
		$recordSet = $this->indexResult['response']['docs'];
		foreach ($recordSet as $key => $record){
			$recordSet[$key] = $record;
		}
		return $recordSet;
	}

	/**
	 * Use the record driver to build an array of HTML displays from the search
	 * results.
	 *
	 * @access  public
	 * @return  array   Array of HTML chunks for individual records.
	 */
	public function getResultRecordHTML(){
		global $interface;

		$html = [];
		for ($x = 0;$x < count($this->indexResult['response']['docs']);$x++){
			$current = &$this->indexResult['response']['docs'][$x];
			$interface->assign('recordIndex', $x + 1);
			$record = RecordDriverFactory::initRecordDriver($current);
			if (!PEAR_Singleton::isError($record)){
				$interface->assign('recordDriver', $record);
				$html[] = $interface->fetch($record->getSearchResult($this->view));
			}else{
				$html[] = 'Unable to find record';
			}
		}
		return $html;
	}

	/**
	 * Set an overriding array of record IDs.
	 *
	 * @access  public
	 * @param array $ids Record IDs to load
	 */
	public function setQueryIDs($ids){
		$this->query = 'id:(' . implode(' OR ', $ids) . ')';
	}

	/**
	 * Set an overriding string.
	 *
	 * @access  public
	 * @param string $newQuery Query string
	 */
	public function setQueryString($newQuery){
		$this->query = $newQuery;
	}

	/**
	 * Set an overriding facet sort order.
	 *
	 * @access  public
	 * @param string $newSort Sort string
	 */
	public function setFacetSortOrder($newSort){
		// As of Solr 1.4 valid values are:
		// 'count' = relevancy ranked
		// 'index' = index order, most likely alphabetical
		// more info : http://wiki.apache.org/solr/SimpleFacetParameters#facet.sort
		if ($newSort == 'count' || $newSort == 'index'){
			$this->facetSort = $newSort;
		}
	}

	/**
	 * Add a prefix to facet requirements. Serves to
	 *    limits facet sets to smaller subsets.
	 *
	 *  eg. all facet data starting with 'R'
	 *
	 * @access  public
	 * @param string $prefix Data for prefix
	 */
	public function addFacetPrefix($prefix){
		$this->facetPrefix = $prefix;
	}

	/**
	 * Turn the list of spelling suggestions into an array of urls
	 *   for on-screen use to implement the suggestions.
	 *
	 * @access  public
	 * @return  array     Spelling suggestion data arrays
	 */
	public function getSpellingSuggestions(){
		global $configArray;

		$returnArray = [];
		if (count($this->suggestions) == 0){
			return $returnArray;
		}
		$tokens = $this->spellingTokens($this->buildSpellingQuery());

		foreach ($this->suggestions as $term => $details){
			// Find out if our suggestion is part of a token
			$inToken    = false;
			$targetTerm = '';
			foreach ($tokens as $token){
				// TODO - Do we need stricter matching here?
				//   Similar to that in replaceSearchTerm()?
				if (stripos($token, $term) !== false){
					$inToken = true;
					// We need to replace the whole token
					$targetTerm = $token;
					// Go and replace this token
					$returnArray = $this->doSpellingReplace($term,
						$targetTerm, $inToken, $details, $returnArray);
				}
			}
			// If no tokens we found, just look
			//    for the suggestion 'as is'
			if ($targetTerm == ""){
				$targetTerm  = $term;
				$returnArray = $this->doSpellingReplace($term,
					$targetTerm, $inToken, $details, $returnArray);
			}
		}
		return $returnArray;
	}

	/**
	 * Build a string for onscreen display showing the
	 *   query used in the search (not the filters).
	 *
	 * @access  public
	 * @return  string   user friendly version of 'query'
	 */
	public function displayQuery(){
		// Maybe this is a restored object...
		if ($this->query == null){
			$this->query = $this->indexEngine->buildQuery($this->searchTerms);
		}

		// Do we need the complex answer? Advanced searches
		if ($this->searchType == $this->advancedSearchType){
			$output = $this->buildAdvancedDisplayQuery();
			// If there is a hardcoded public query (like tags) return that
		}elseif ($this->publicQuery != null){
			$output = $this->publicQuery;
			// If we don't already have a public query, and this is a basic search
			// with case-insensitive booleans, we need to do some extra work to ensure
			// that we display the user's query back to them unmodified (i.e. without
			// capitalized Boolean operators)!
		}elseif (!$this->indexEngine->hasCaseSensitiveBooleans()){
			$output = $this->publicQuery =
				$this->indexEngine->buildQuery($this->searchTerms, true);
			// Simple answer
		}else{
			$output = $this->query;
		}

		// Empty searches will look odd to users
		if ($output == '*:*'){
			$output = '';
		}

		return $output;
	}

	/**
	 * Get the base URL for search results (including ? parameter prefix).
	 *
	 * @access  protected
	 * @return  string   Base URL
	 */
	protected function getBaseUrl(){
		// Base URL is different for author searches:
//		return $this->serverUrl . '/Genealogy/Results?';
		return $this->serverUrl . '/Union/Search?';
	}

	/**
	 * Get error message from index response, if any.  This will only work if
	 * processSearch was called with $returnIndexErrors set to true!
	 *
	 * @access  public
	 * @return  mixed       false if no error, error string otherwise.
	 */
	public function getIndexError(){
		return $this->indexResult['error'] ?? false;
	}

	/**
	 * Actually process and submit the search
	 *
	 * @access  public
	 * @param bool $returnIndexErrors Should we die inside the index code if
	 *                                     we encounter an error (false) or return
	 *                                     it for access via the getIndexError()
	 *                                     method (true)?
	 * @param bool $recommendations Should we process recommendations along
	 *                                     with the search itself?
	 * @param bool $preventQueryModification Should we allow the search engine
	 *                                             to modify the query or is it already
	 *                                             a well formatted query
	 * @return  object solr result structure (for now)
	 */
	public function processSearch($returnIndexErrors = false, $recommendations = false, $preventQueryModification = false){
		// Our search has already been processed in init()
		$search = $this->searchTerms;

		// Build a recommendations module appropriate to the current search:
		if ($recommendations){
			$this->initRecommendations();
		}

		// Build Query
		$query = $preventQueryModification ? $search : $this->indexEngine->buildQuery($search, false);
		if (PEAR_Singleton::isError($query)){
			return $query;
		}

		// Only use the query we just built if there isn't an override in place.
		if ($this->query == null){
			$this->query = $query;
		}

		// Define Filter Query
		$filterQuery = $this->hiddenFilters;
		foreach ($this->filterList as $field => $filter){
			if (empty($field)){
				//Remove any empty filters if we get them
				//(typically happens when a subdomain has a function disabled that is enabled in the main scope)
				unset($this->filterList[$field]);
			}else{
				foreach ($filter as $value){
					// Special case -- allow trailing wildcards:
					if (substr($value, -1) == '*'){
						$filterQuery[] = "$field:$value";
					}elseif (preg_match('/\\A\\[.*?\\sTO\\s.*?]\\z/', $value)){
						$filterQuery[] = "$field:$value";
					}elseif (!empty($value)){
						$filterQuery[] = "$field:\"$value\"";
					}
				}
			}
		}

		// If we are only searching one field use the DisMax handler
		//    for that field. If left at null let solr take care of it
		if (count($search) == 1 && isset($search[0]['index'])){
			$this->index = $search[0]['index'];
		}

		// Build a list of facets we want from the index
		$facetSet = [];
		if (!empty($this->facetConfig)){
			$facetSet['limit'] = $this->facetLimit;
			foreach ($this->facetConfig as $facetField => $facetName){
				$facetSet['field'][] = $facetField;
			}
			if ($this->facetOffset != null){
				$facetSet['offset'] = $this->facetOffset;
			}
			if ($this->facetPrefix != null){
				$facetSet['prefix'] = $this->facetPrefix;
			}
			if ($this->facetSort != null){
				$facetSet['sort'] = $this->facetSort;
			}
		}

		if (!empty($this->facetOptions)){
			$facetSet['additionalOptions'] = $this->facetOptions;
		}

		// Build our spellcheck query
		if ($this->spellcheck){
			if ($this->spellSimple){
				$this->useBasicDictionary();
			}
			$spellcheck = $this->buildSpellingQuery();

			// If the spellcheck query is purely numeric, skip it if
			// the appropriate setting is turned on.
			if ($this->spellSkipNumeric && is_numeric($spellcheck)){
				$spellcheck = '';
			}
		}else{
			$spellcheck = '';
		}

		// Get time before the query
		$this->startQueryTimer();

		// The "relevance" sort option is a VuFind reserved word; we need to make
		// this null in order to achieve the desired effect with Solr:
		$finalSort = ($this->sort == 'relevance') ? null : $this->sort;

		// The first record to retrieve:
		//  (page - 1) * limit = start
		$recordStart       = ($this->page - 1) * $this->limit;
		$this->indexResult = $this->indexEngine->search(
			$this->query,      // Query string
			$this->index,      // DisMax Handler
			$filterQuery,      // Filter query
			$recordStart,      // Starting record
			$this->limit,      // Records per page
			$facetSet,         // Fields to facet on
			$spellcheck,       // Spellcheck query
			$this->dictionary, // Spellcheck dictionary
			$finalSort,        // Field to sort on
			$this->fields,     // Fields to return
			$this->method,     // HTTP Request method
			$returnIndexErrors // Include errors in response?
		);

		// Get time after the query
		$this->stopQueryTimer();

		// How many results were there?
		if (isset($this->indexResult['response']['numFound'])){
			$this->resultsTotal = $this->indexResult['response']['numFound'];
		}else{
			$this->resultsTotal = 0;
		}

		// Process spelling suggestions if no index error resulted from the query
		if ($this->spellcheck && !isset($this->indexResult['error'])){
			// Shingle dictionary
			$this->processSpelling();
			// Make sure we don't endlessly loop
			if ($this->dictionary == 'default'){
				// Expand against the basic dictionary
				$this->basicSpelling();
			}
		}

		// If extra processing is needed for recommendations, do it now:
		if ($recommendations && is_array($this->recommend)){
			foreach ($this->recommend as $currentSet){
				foreach ($currentSet as $current){
					$current->process();
				}
			}
		}

		//Add debug information to the results if available
		if ($this->debug){
			if (!empty($this->indexResult['debug']['explain'])){
				$explainInfo = $this->indexResult['debug']['explain'];
				foreach ($this->indexResult['response']['docs'] as &$result){
					if (array_key_exists($result['id'], $explainInfo)){
						$result['explain'] = $explainInfo[$result['id']];
					}
				}
			}

			global $interface;
			$interface->assign('debugSolrOutput', $this->debugOutput());
			$interface->assign('debugTiming', $this->getDebugTiming());
		}

		// Return the result set
		return $this->indexResult;
	}

	/**
	 * Adapt the search query to a spelling query
	 *
	 * @access  private
	 * @return  string    Spelling query
	 */
	private function buildSpellingQuery(){
		$this->spellQuery = [];

		// Basic search
		if ($this->searchType == $this->basicSearchType){
			return $this->query; // Just the search query is fine

			// Advanced search
		}else{
			foreach ($this->searchTerms as $search){
				foreach ($search['group'] as $field){
					// Add just the search terms to the list
					$this->spellQuery[] = $field['lookfor'];
				}
			}
			// Return the list put together as a string
			return implode(' ', $this->spellQuery);
		}
	}

	/**
	 * Process spelling suggestions from the results object
	 *
	 * @access  private
	 */
	private function processSpelling(){
		global $configArray;

		// Do nothing if spelling is disabled
		if (!$configArray['Spelling']['enabled']){
			return;
		}

		// Do nothing if there are no suggestions
		$suggestions = $this->indexResult['spellcheck']['suggestions'] ?? [];
		if (empty($suggestions)){
			return;
		}

		// Loop through the array of search terms we have suggestions for
		$suggestionList = [];
		foreach ($suggestions as $suggestion){
			$ourTerm = $suggestion[0];

			// Skip numeric terms if numeric suggestions are disabled
			if ($this->spellSkipNumeric && is_numeric($ourTerm)){
				continue;
			}

			$ourHit  = $suggestion[1]['origFreq'];
			$count   = $suggestion[1]['numFound'];
			$newList = $suggestion[1]['suggestion'];

			$validTerm = true;

			// Make sure the suggestion is for a valid search term.
			// Sometimes shingling will have bridged two search fields (in
			// an advanced search) or skipped over a stopword.
			if (!$this->findSearchTerm($ourTerm)){
				$validTerm = false;
			}

			// Unless this term had no hits
			if ($ourHit != 0){
				// Filter out suggestions we are already using
				$newList = $this->filterSpellingTerms($newList);
			}

			// Make sure it has suggestions and is valid
			if (count($newList) > 0 && $validTerm){
				// Did we get more suggestions then our limit?
				if ($count > $this->spellingLimit){
					// Cut the list at the limit
					array_splice($newList, $this->spellingLimit);
				}
				$suggestionList[$ourTerm]['freq'] = $ourHit;
				// Format the list nicely
				foreach ($newList as $item){
					if (is_array($item)){
						$suggestionList[$ourTerm]['suggestions'][$item['word']] = $item['freq'];
					}else{
						$suggestionList[$ourTerm]['suggestions'][$item] = 0;
					}
				}
			}
		}
		$this->suggestions = $suggestionList;
	}

	/**
	 * Filter a list of spelling suggestions to remove suggestions
	 *   we are already searching for
	 *
	 * @access  private
	 * @param array $termList List of suggestions
	 * @return  array    Filtered list
	 */
	private function filterSpellingTerms($termList){
		$newList = [];
		if (empty($termList)){
			return $newList;
		}

		foreach ($termList as $term){
			if (!$this->findSearchTerm($term['word'])){
				$newList[] = $term;
			}
		}
		return $newList;
	}

	/**
	 * Try running spelling against the basic dictionary.
	 *   This function should ensure it doesn't return
	 *   single word suggestions that have been accounted
	 *   for in the shingle suggestions above.
	 *
	 * @access  private
	 * @return  array     Suggestions array
	 */
	private function basicSpelling(){
		// TODO: There might be a way to run the search against both dictionaries from
		//   inside solr. Investigate. Currently submitting a second search for this.

		// Create a new search object
		/** @var SearchObject_Genealogy $newSearch */
		$newSearch = SearchObjectFactory::initSearchObject('Genealogy');
		$newSearch->deminify($this->minify());

		// Activate the basic dictionary
		$newSearch->useBasicDictionary();
		// We don't want it in the search history
		$newSearch->disableLogging();

		// Run the search
		$newSearch->processSearch();
		// Get the spelling results
		$newList = $newSearch->getRawSuggestions();

		// If there were no shingle suggestions
		if (count($this->suggestions) == 0){
			// Just use the basic ones as provided
			$this->suggestions = $newList;

			// Otherwise
		}else{
			// For all the new suggestions
			foreach ($newList as $word => $data){
				// Check the old suggestions
				$found = false;
				foreach ($this->suggestions as $k => $v){
					// Make sure it wasn't part of a shingle which has been suggested at a higher level.
					$found = preg_match("/\b$word\b/", $k) ? true : $found;
				}
				if (!$found){
					$this->suggestions[$word] = $data;
				}
			}
		}
	}

	/**
	 * Process facets from the results object
	 *
	 * @access  public
	 * @param array $filter Array of field => on-screen description
	 *                                  listing all of the desired facet fields;
	 *                                  set to null to get all configured values.
	 * @param bool $expandingLinks If true, we will include expanding URLs
	 *                                  (i.e. get all matches for a facet, not
	 *                                  just a limit to the current search) in
	 *                                  the return array.
	 * @return  array   Facets data arrays
	 */
	public function getFacetList($filter = null, $expandingLinks = false){
		// If there is no filter, we'll use all facets as the filter:
		if (is_null($filter)){
			$filter = $this->facetConfig;
		}

		// Start building the facet list:
		$list = [];

		// If we have no facets to process, give up now
		if (!isset($this->indexResult['facet_counts']) || (!is_array($this->indexResult['facet_counts']['facet_fields']) && !is_array($this->indexResult['facet_counts']['facet_ranges']))){
			return $list;
		}

		// Loop through every field returned by the result set
		$validFields = array_keys($filter);

		$allFacets = array_merge($this->indexResult['facet_counts']['facet_fields'], $this->indexResult['facet_counts']['facet_ranges']);
		foreach ($allFacets as $field => $data){
			// Skip filtered fields and empty arrays:
			if (!in_array($field, $validFields) || count($data) < 1){
				continue;
			}

			// Initialize the settings for the current field
			$list[$field]          = [];
			$list[$field]['label'] = $filter[$field]; // Add the on-screen label
			$list[$field]['list']  = [];              // Build our array of values for this field

			// Should we translate values for the current facet?
			$translate = in_array($field, $this->translatedFacets);

			// Loop through values:
			foreach ($data as $facet){
				// Initialize the array of data about the current facet:
				$currentSettings              = [];
				$currentSettings['value']     = $facet[0];
				$currentSettings['display']   = $translate ? translate($facet[0]) : $facet[0];
				$currentSettings['count']     = $facet[1];
				$currentSettings['isApplied'] = false;
				$currentSettings['url']       = $this->renderLinkWithFilter("$field:" . $facet[0]);
				// If we want to have expanding links (all values matching the facet)
				// in addition to limiting links (filter current search with facet),
				// do some extra work:
				if ($expandingLinks){
					$currentSettings['expandUrl'] = $this->getExpandingFacetLink($field, $facet[0]);
				}

				// Is this field a current filter?
				if (in_array($field, array_keys($this->filterList))){
					// and is this value a selected filter?
					if (in_array($facet[0], $this->filterList[$field])){
						$currentSettings['isApplied']  = true;
						$currentSettings['removalUrl'] = $this->renderLinkWithoutFilter("$field:{$facet[0]}");
					}
				}

				//Setup the key to allow sorting alphabetically if needed.
				$valueKey = $facet[0];

				// Store the collected values:
				$list[$field]['list'][$valueKey] = $currentSettings;
			}

			if ($field == 'veteranOf'){
				//Add a field for Any war
				$currentSettings              = [];
				$currentSettings['value']     = '[* TO *]';
				$currentSettings['display']   = $translate ? translate('Any War') : 'Any War';
				$currentSettings['count']     = '';
				$currentSettings['isApplied'] = false;
				if (in_array($field, array_keys($this->filterList))){
					// and is this value a selected filter?
					if (in_array($currentSettings['value'], $this->filterList[$field])){
						$currentSettings['isApplied']  = true;
						$currentSettings['removalUrl'] = $this->renderLinkWithoutFilter("$field:{$facet[0]}");
					}
				}
				$currentSettings['url']          = $this->renderLinkWithFilter("veteranOf:" . $currentSettings['value']);
				$list[$field]['list']['Any War'] = $currentSettings;
			}

			//How many facets should be shown by default
			$list[$field]['valuesToShow'] = 5;

			//Sort the facet alphabetically?
			//Sort the system and location alphabetically unless we are in the global scope
			$list[$field]['showAlphabetically'] = false;
			if ($list[$field]['showAlphabetically']){
				ksort($list[$field]['list']);
			}
		}
		return $list;
	}

	/**
	 * Load all available facet settings.  This is mainly useful for showing
	 * appropriate labels when an existing search has multiple filters associated
	 * with it.
	 *
	 * @access  public
	 * @param string $preferredSection Section to favor when loading
	 *                                              settings; if multiple sections
	 *                                              contain the same facet, this
	 *                                              section's description will be
	 *                                              favored.
	 */
	public function activateAllFacets($preferredSection = false){
		foreach ($this->allFacetSettings as $section => $values){
			foreach ($values as $key => $value){
				$this->addFacet($key, $value);
			}
		}

		if ($preferredSection && is_array($this->allFacetSettings[$preferredSection])){
			foreach ($this->allFacetSettings[$preferredSection] as $key => $value){
				$this->addFacet($key, $value);
			}
		}
	}

	/**
	 * Turn our results into an RSS feed
	 *
	 * @access  public
	 * @return  string                XML document
	 */
	public function buildRSS(){
		global $configArray;
		global $library;
		// XML HTTP header
		header('Content-type: text/xml', true);

		$baseUrl    = empty($library->catalogUrl) ? $configArray['Site']['url'] : $_SERVER['REQUEST_SCHEME'] . '://' . $library->catalogUrl;
		$this->limit = 50;
		$result      = $this->processSearch(false, false);
		foreach ($result['response']['docs'] as &$currentDoc){

			/** @var PersonRecord $record */
			$record = RecordDriverFactory::initRecordDriver($currentDoc);
			if (!PEAR_Singleton::isError($record)){
				$currentDoc['recordUrl']       = $record->getAbsoluteUrl();
				$currentDoc['title_display']   = $record->getName();
				$image                         = $baseUrl . $record->getBookcoverUrl('medium');
				$description                   = "<img src='$image'/> ";
				$currentDoc['rss_description'] = $description;
				$dateAdded                     = $record->getDateAdded();
				if (!empty($dateAdded)){
					$currentDoc['rss_date'] = date('r', $dateAdded);
				}
			}
		}

		global $interface;

		// On-screen display value for our search
		$lookfor = $this->displayQuery();

		if (count($this->filterList) > 0){
			// TODO : better display of filters
			$interface->assign('lookfor', $lookfor . " (" . translate('with filters') . ")");
		}else{
			$interface->assign('lookfor', $lookfor);
		}
		// The full url to recreate this search
		$interface->assign('searchUrl', $baseUrl . $this->renderSearchUrl());

		$interface->assign('result', $result);
		return $interface->fetch('Search/rss.tpl');
	}

	/**
	 * Turn our results into an Excel document
	 *
	 * @access  public
	 * @public  array      $result      Existing result set (null to do new search)
	 * @return  string                  Excel document
	 */
	public function buildExcel($result = null){
		// First, get the search results if none were provided
		// (we'll go for 50 at a time)
		if (is_null($result)){
			$this->limit = 2000;
			$result      = $this->processSearch(false, false);
		}

		// Prepare the spreadsheet
		ini_set('include_path', ini_get('include_path' . ';/PHPExcel/Classes'));
		include 'PHPExcel.php';
		include 'PHPExcel/Writer/Excel2007.php';
		$objPHPExcel = new PHPExcel();
		$objPHPExcel->getProperties()->setTitle("Search Results");

		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet()->setTitle('Results');

		//Add headers to the table
		$sheet  = $objPHPExcel->getActiveSheet();
		$curRow = 1;
		$curCol = 0;
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'First Name');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Last Name');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Birth Date');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Death Date');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Veteran Of');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Cemetery');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Addition');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Block');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Lot');
		$sheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Grave');
		$maxColumn = $curCol - 1;

		for ($i = 0;$i < count($result['response']['docs']);$i++){
			$curDoc = $result['response']['docs'][$i];
			$curRow++;
			$curCol = 0;
			//Get supplemental information from the database
			require_once ROOT_DIR . '/sys/Genealogy/Person.php';
			$person           = new Person();
			$id               = str_replace('person', '', $curDoc['id']);
			$person->personId = $id;
			if ($person->find(true)){
				//Output the row to excel
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, isset($curDoc['firstName']) ? $curDoc['firstName'] : '');
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, isset($curDoc['lastName']) ? $curDoc['lastName'] : '');
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, $person->formatPartialDate($person->birthDateDay, $person->birthDateMonth, $person->birthDateYear));
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, $person->formatPartialDate($person->deathDateDay, $person->deathDateMonth, $person->deathDateYear));
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, isset($curDoc['veteranOf']) ? implode(', ', $curDoc['veteranOf']) : '');
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, isset($curDoc['cemeteryName']) ? $curDoc['cemeteryName'] : '');
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, $person->addition);
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, $person->block);
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, $person->lot);
				$sheet->setCellValueByColumnAndRow($curCol++, $curRow, $person->grave);
			}
		}

		for ($i = 0;$i < $maxColumn;$i++){
			$sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
		}

		//Output to the browser
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="Results.xlsx"');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save('php://output'); //THIS DOES NOT WORK WHY?
		$objPHPExcel->disconnectWorksheets();
		unset($objPHPExcel);
	}

	/**
	 * Retrieves a document specified by the ID.
	 *
	 * @param string $id The document to retrieve from Solr
	 * @param null|string $fieldsToReturn An optional list of fields to return separated by commas
	 * @return  array              The requested document
	 * @throws Exception
	 * @access  public
	 */
	function getRecord($id, $fieldsToReturn = null){
		return $this->indexEngine->getRecord($id, $fieldsToReturn);
	}

	/**
	 * Retrieves Solr Documents for an array of grouped Work Ids
	 * @param string[] $ids The groupedWork Id of the Solr document to retrieve
	 * @param null|string $fieldsToReturn An optional list of fields to return separated by commas
	 * @return array The Solr document of the grouped Work
	 */
	function getRecords($ids){
		return $this->indexEngine->getRecords($ids, $fieldsToReturn = null, count($ids));
	}

	/**
	 * Get an array of strings to attach to a base URL in order to reproduce the
	 * current search.
	 *
	 * @access  protected
	 * @return  array    Array of URL parameters (key=url_encoded_value format)
	 */
	protected function getSearchParams(){
		$params = parent::getSearchParams();
		$params[] = 'genealogyType=' . ($_REQUEST['genealogyType'] ?? 'GenealogyKeyword'); //TODO: can this be replaced with general $_REQUEST['type']
		return $params;
	}

	public function setPrimarySearch($flag){
		parent::setPrimarySearch($flag);
		$this->indexEngine->isPrimarySearch = $flag;
	}

	public function getNextPrevLinks(){
		global $interface;
		//Setup next and previous links based on the search results.
		if (isset($_REQUEST['searchId']) && isset($_REQUEST['recordIndex']) && ctype_digit($_REQUEST['searchId']) && ctype_digit($_REQUEST['recordIndex'])){
			//rerun the search
			require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
			$s     = new SearchEntry();
			$s->id = $_REQUEST['searchId'];
			if ($s->find(true)){
				$currentPage = isset($_REQUEST['page']) && ctype_digit($_REQUEST['page']) ? $_REQUEST['page'] : 1;
				$interface->assign('searchId', $_REQUEST['searchId']);
				$interface->assign('page', $currentPage);

				$minSO = unserialize($s->search_object);
				/** @var SearchObject_Solr $searchObject */
				$searchObject = SearchObjectFactory::deminify($minSO);
				$searchObject->setPage($currentPage);
				//Run the search
				$result = $searchObject->processSearch(true);

				//Check to see if we need to run a search for the next or previous page
				$currentResultIndex  = $_REQUEST['recordIndex'] - 1;
				$recordsPerPage      = $searchObject->getLimit();
				$adjustedResultIndex = $currentResultIndex - ($recordsPerPage * ($currentPage - 1));

				if (($currentResultIndex) % $recordsPerPage == 0 && $currentResultIndex > 0){
					//Need to run a search for the previous page
					$interface->assign('previousPage', $currentPage - 1);
					$previousSearchObject = clone $searchObject;
					$previousSearchObject->setPage($currentPage - 1);
					$previousSearchObject->processSearch(true, false, false);
					$previousResults = $previousSearchObject->getResultRecordSet();
				}elseif (($currentResultIndex + 1) % $recordsPerPage == 0 && ($currentResultIndex + 1) < $searchObject->getResultTotal()){
					//Need to run a search for the next page
					$nextSearchObject = clone $searchObject;
					$interface->assign('nextPage', $currentPage + 1);
					$nextSearchObject->setPage($currentPage + 1);
					$nextSearchObject->processSearch(true, false, false);
					$nextResults = $nextSearchObject->getResultRecordSet();
				}

				if (!PEAR_Singleton::isError($result) && $searchObject->getResultTotal() > 0){
					$recordSet = $searchObject->getResultRecordSet();
					//Record set is 0 based, but we are passed a 1 based index
					if ($currentResultIndex > 0){
						if (isset($previousResults)){
							$previousRecord = $previousResults[count($previousResults) - 1];
						}else{
							$previousId = $adjustedResultIndex - 1;
							if (isset($recordSet[$previousId])){
								$previousRecord = $recordSet[$previousId];
							}
						}

						//Convert back to 1 based index
						if (isset($previousRecord)){
							$interface->assign('previousIndex', $currentResultIndex - 1 + 1);
							$interface->assign('previousTitle', $previousRecord['title']);
							$interface->assign('previousType', 'Person');
							$interface->assign('previousId', str_replace('person', '', $previousRecord['id']));
						}
					}
					if ($currentResultIndex + 1 < $searchObject->getResultTotal()){
						if (isset($nextResults)){
							$nextRecord = $nextResults[0];
						}else{
							$nextRecordIndex = $adjustedResultIndex + 1;
							if (isset($recordSet[$nextRecordIndex])){
								$nextRecord = $recordSet[$nextRecordIndex];
							}
						}
						//Convert back to 1 based index
						$interface->assign('nextIndex', $currentResultIndex + 1 + 1);
						if (isset($nextRecord)){
							$interface->assign('nextTitle', $nextRecord['title']);
							$interface->assign('nextType', 'Person');
							$interface->assign('nextId', str_replace('person', '', $nextRecord['id']));
						}
					}
				}
			}
		}
	}
}
