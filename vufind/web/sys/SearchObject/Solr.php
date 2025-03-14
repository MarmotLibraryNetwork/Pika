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

/**
 * Search Object class
 *
 * This is the default implementation of the SearchObjectBase class, providing the
 * Solr-driven functionality used by VuFind's standard Search module.
 */
class SearchObject_Solr extends SearchObject_Base {
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
	private static $fields = 'accelerated_reader_interest_level,accelerated_reader_point_value,accelerated_reader_reading_level,auth_author2,author,author2-role,author_display,display_description,display_description,fountas_pinnell,id,isbn,issn,item_details,last_indexed,lexile_code,lexile_score,literary_form,literary_form_full,mpaaRating,num_titles,primary_isbn,primary_upc,publishDate,publisher,record_details,recordtype,series,series_with_volume,subject_facet,title_display,title_full,title_short,title_sub,topic_facet,upc';
	private $fieldsFull = '*,score';
	// HTTP Method
	private $method = 'GET';
//	private $method = 'POST';
	// Result
	private $indexResult;

	// OTHER VARIABLES
	// Index
	/** @var Solr $indexEngine */
	private $indexEngine = null;
	// Facets information
	private $allFacetSettings;    // loaded from facets.ini
	// Search types of author have two subtypes: home and search
	private $authorSearchType  = '';

	// Spelling
	private $spellingLimit = 3;
	private $spellQuery    = [];
	private $dictionary    = 'default';
	private $spellSimple   = false;
	private $spellSkipNumeric = true;

	// Display Modes //
	public $viewOptions = ['list', 'covers'];

	/**
	 * Flag to disable default scoping to show ILL book titles, etc.
	 */
	private $scopingDisabled = false;


	// In each class, set the specific range filters based on the Search Object
	protected $rangeFilters = ['lexile_score', 'accelerated_reader_reading_level', 'accelerated_reader_point_value'];
	protected $dateFilters = ['publishDate'];


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
		global $library;
		global $solrScope;
		// Include our solr index
		$class              = $configArray['Index']['engine'];
		$classWithExtension = $class . '.php';
//		require_once ROOT_DIR . "/sys/" . $classWithExtension;
		// Initialise the index
		$this->indexEngine = new $class($configArray['Index']['url']);
		$timer->logTime('Created Index Engine');

		// Get default facet settings
		$this->allFacetSettings = getExtraConfigArray('facets');
		$this->facetConfig      = [];
		$facetLimit             = $this->getFacetSetting('Results_Settings', 'facet_limit');
		if (is_numeric($facetLimit)){
			$this->facetLimit = $facetLimit;
		}
		$translatedFacets = $this->getFacetSetting('Advanced_Settings', 'translated_facets');
		if (is_array($translatedFacets)){
			$this->translatedFacets = $translatedFacets;
			foreach ($translatedFacets as $translatedFacet){
				$this->translatedFacets[] = $translatedFacet . '_' . $solrScope;
			}
		}

		// Load search preferences:
		$searchSettings = getExtraConfigArray('searches');
		if (isset($library)){
			if ($library->showTagging == 0){
				unset($searchSettings['Basic_Searches']['tag']);
			}
		}
		if (isset($searchSettings['General']['default_handler'])){
			$this->defaultIndex = $searchSettings['General']['default_handler'];
		}
		if (isset($searchSettings['General']['default_sort'])){
			$this->defaultSort = $searchSettings['General']['default_sort'];
		}
		if (isset($searchSettings['General']['default_view'])){
			$this->defaultView = $searchSettings['General']['default_view'];
		}
		if (isset($searchSettings['General']['default_limit'])){
			$this->defaultLimit = $searchSettings['General']['default_limit'];
		}
		if (isset($searchSettings['General']['retain_filters_by_default'])){
			$this->retainFiltersByDefault
				= $searchSettings['General']['retain_filters_by_default'];
		}
		if (isset($searchSettings['DefaultSortingByType']) && is_array($searchSettings['DefaultSortingByType'])){
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
				'relevance'  => 'sort_relevance',
				'popularity' => 'sort_popularity',
				'year'       => 'sort_year',
				'year asc'   => 'sort_year asc',
				'callnumber' => 'sort_callnumber',
				'author'     => 'sort_author',
				'title'      => 'sort_title'
			];
		}

		// Load Spelling preferences
		$this->spellcheck       = $configArray['Spelling']['enabled'];
		$this->spellingLimit    = $configArray['Spelling']['limit'];
		$this->spellSimple      = $configArray['Spelling']['simple'];
		$this->spellSkipNumeric = $configArray['Spelling']['skip_numeric'] ?? true;

		$this->indexEngine->debug          = $this->debug;
		$this->indexEngine->debugSolrQuery = $this->debugSolrQuery;

		$timer->logTime('Setup Solr Search Object');
	}

	/**
	 * Turn off filter search of field to scope_has_related records
	 * so that results will be returned even if grouped work is outside
	 * the current search scope.
	 *
	 * @return void
	 */
	public function disableScoping(){
		$this->scopingDisabled = true;
	}

	public function disableSpelling(){
		$this->spellcheck = false;
	}

	public function enableSpelling(){
		$this->spellcheck = true;
	}

	public function getBasicTypes(){
		$basicSearchTypes = $this->basicTypes;
		if ($this->searchType != $this->advancedSearchType){
			$searchIndex  = $this->getSearchIndex();
			$searchSource = $_REQUEST['searchSource'] ?? 'local';
			if ($this->searchType != 'genealogy' && $searchSource != 'genealogy' &&
				$this->searchType != 'islandora' && $searchSource != 'islandora'
			){
				if (!array_key_exists($searchIndex, $basicSearchTypes)){
					$basicSearchTypes[$searchIndex] = $searchIndex;
				}
			}
		}
		return $basicSearchTypes;
	}

	/**
	 * TODO: move to solr
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
			if (!is_numeric($field)){

				if (strcmp($field, 'literary-form') === 0){
					$field = 'literary_form';
				}elseif (strcmp($field, 'literary-form-full') == 0){
					$field = 'literary_form_full';
				}elseif (strcmp($field, 'target-audience') == 0){
					$field = 'target_audience';
				}elseif (strcmp($field, 'target-audience-full') == 0){
					$field = 'target_audience_full';
				}

				global $solrScope;
				global $locationSingleton;
				$searchLibrary  = Library::getActiveLibrary();
				$searchLocation = $locationSingleton->getActiveLocation();
				$userLocation   = Location::getUserHomeLocation();

				//See if the filter should be localized
				if (isset($searchLibrary)){
					if (strcmp($field, 'time_since_added') === 0){
						$field = 'local_time_since_added_' . $searchLibrary->subdomain;
					}elseif (strcmp($field, 'itype') === 0){
						$field = 'itype_' . $searchLibrary->subdomain;
					}elseif (strcmp($field, 'detailed_location') === 0){
						$field = 'detailed_location_' . $searchLibrary->subdomain;
					}
				}

				// Correct any scoped fields to the current search scope
				if ($solrScope){
					if (strcmp($field, 'availability_by_format') == 0){
						$field = 'availability_by_format_' . $solrScope;
					}elseif (strcmp($field, 'availability_toggle') == 0){
						$field = 'availability_toggle_' . $solrScope;
					}elseif ((strcmp($field, 'available_at') == 0)){
						$field = 'available_at_' . $solrScope;
					}elseif (strcmp($field, 'format') == 0){
						$field = 'format_' . $solrScope;
					}elseif (strcmp($field, 'format_category') == 0){
						$field = 'format_category_' . $solrScope;
					}elseif (strcmp($field, 'econtent_source') == 0){
						$field = 'econtent_source_' . $solrScope;
					}elseif ((strcmp($field, 'collection') == 0) || (strcmp($field, 'collection_group') == 0)){
						$field = 'collection_' . $solrScope;
					}elseif ((strcmp($field, 'language') == 0)){
						$field = 'language_' . $solrScope;
					}elseif ((strcmp($field, 'translation') == 0)){
						$field = 'translation_' . $solrScope;
					}elseif ((strcmp($field, 'owning_library') == 0)){
						$field = 'owning_library_' . $solrScope;
					}elseif ((strcmp($field, 'owning_location') == 0)){
						$field = 'owning_location_' . $solrScope;
					}elseif ((strcmp($field, 'detailed_location') == 0)){
						$field = 'detailed_location_' . $solrScope;
					}elseif ((strcmp($field, 'local_callnumber') == 0)){
						$field = 'local_callnumber_' . $solrScope;
					}
				}

				// TODO: Explain why this should override the solr scope above.
				if (isset($userLocation)){
					if (strcmp($field, 'availability_toggle') == 0){
						$field = 'availability_toggle_' . $userLocation->code;
					}
				}
				if (isset($searchLocation)){
					if ((strcmp($field, 'time_since_added') == 0) && $searchLocation->restrictSearchByLocation){
						$field = 'local_time_since_added_' . $searchLocation->code;
					}elseif (strcmp($field, 'availability_toggle') == 0){
						$field = 'availability_toggle_' . $searchLocation->code;
					}
				}
			}

			$this->filterList[$field][] = $value;
		}
	}

	/**
	 * Initialise the object from the global
	 *  search parameters in $_REQUEST.
	 *
	 * @access  public
	 * @var string $searchSource
	 * @return  boolean
	 */
	public function init($searchSource = null, $searchTerm = null){
		// Call the standard initialization routine in the parent:
		parent::init($searchSource);

		$this->indexEngine->setSearchSource($searchSource);

		//********************
		// Check if we have a saved search to restore -- if restored successfully,
		// our work here is done; if there is an error, we should report failure;
		// if restoreSavedSearch returns false, we should proceed as normal.
		$restored = $this->restoreSavedSearch(null, true, true);
		if ($restored === true) {
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

		if ($searchTerm == null){
			$searchTerm = $_REQUEST['lookfor'] ?? null;
		}

		global $module;
		global $action;

		//********************
		// Basic Search logic
		if ($this->initBasicSearch($searchTerm)) {
			// If we found a basic search, we don't need to do anything further.
		} elseif (isset($_REQUEST['tag']) && $module != 'MyAccount') {
			// Tags, just treat them as normal searches for now.
			// The search processor knows what to do with them.
			if (!empty($_REQUEST['tag'])) {
				$this->searchTerms[] = [
					'index'   => 'tag',
					'lookfor' => strip_tags($_REQUEST['tag'])
				];
			}
		} else {
			$this->initAdvancedSearch();
		}

		//********************
		// Author screens - handled slightly differently
		$author_ajax_call = (isset($_REQUEST['author']) && $action == 'AJAX' && $module == 'Search');
		if ($module == 'Author' || $author_ajax_call) {
			// Author module or ajax call from author results page
			// *** Things in common to both screens
			// Log a special type of search
			$this->searchType = 'author';
			// We don't spellcheck this screen
			//   it's not for free user input anyway
			$this->spellcheck  = false;

			// *** Author/Home
			if ($action == 'Home' || $author_ajax_call) {
				$this->authorSearchType = 'home';
				// Remove our empty basic search (default)
				$this->searchTerms = [];
				// Prepare the search as a normal author search
				$author = $_REQUEST['author'];
				if (is_array($author)){
					$author = array_pop($author);
				}
				$this->searchTerms[] = [
					'index'   => 'Author',
					'lookfor' => trim(strip_tags($author))
				];
			}

			// *** Author/Search
			if ($action == 'Search') {
				$this->authorSearchType = 'search';
				// We already have the 'lookfor', just set the index
				$this->searchTerms[0]['index'] = 'Author';
				// We really want author facet data
				$this->facetConfig = [];
				$this->addFacet('authorStr');
				// Offset the facet list by the current page of results, and
				// allow up to ten total pages of results -- since we can't
				// get a total facet count, this at least allows the paging
				// mechanism to automatically add more pages to the end of the
				// list so that users can browse deeper and deeper as they go.
				// TODO: Make this better in the future if Solr offers a way
				//       to get a total facet count (currently not possible).
				$this->facetOffset = ($this->page - 1) * $this->limit;
				$this->facetLimit = $this->limit * 10;
				// Sorting - defaults to off with unlimited facets, so let's
				//           be explicit here for simplicity.
				if (isset($_REQUEST['sort']) && ($_REQUEST['sort'] == 'author')) {
					$this->setFacetSortOrder('index');
				} else {
					$this->setFacetSortOrder('count');
				}
			}
		} else if ($module == 'MyAccount') {
			// Users Lists
			$this->spellcheck = false;
			$this->searchType = ($action == 'Home') ? 'favorites' : 'list';
		}

		// If a query override has been specified, log it here
		if (isset($_REQUEST['q'])) {
			$this->query = strip_tags($_REQUEST['q']);
		}

		return true;
	} // End init()

	// This sets up Browse Categories based on search phrases
	public function setSearchTermForBrowseCategory($searchTerm){
		if (strpos($searchTerm, ':') > 0 && substr_count($searchTerm, ':') == 1){
			// Browse Category Search Term of the for SearchType:search phrase
			[$tmpType, $tempSearchTerm] = explode(':', $searchTerm, 2);
			if (in_array($tmpType, array_keys($this->basicTypes))){
				$this->searchTerms[] = [
					'index'   => $tmpType,
					'lookfor' => $tempSearchTerm
				];
			}
		}else{
			$this->searchTerms[] = [
				'index'   => $this->defaultIndex,
				'lookfor' => $searchTerm
			];
		}
	}

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

		global $locationSingleton;
		$searchLibrary           = Library::getActiveLibrary();
		$searchLocation          = $locationSingleton->getActiveLocation();
		$hasSearchLibraryFacets  = ($searchLibrary != null && (count($searchLibrary->facets) > 0));
		$hasSearchLocationFacets = ($searchLocation != null && (count($searchLocation->facets) > 0));
		if ($hasSearchLocationFacets){
			$facets = $searchLocation->facets;
		}elseif ($hasSearchLibraryFacets){
			$facets = $searchLibrary->facets;
		}else{
			$facets = Library::getDefaultFacets();
		}

		$this->facetConfig = [];

		// The below block of code is common with SideFacets method _construct()
		global $solrScope;
		foreach ($facets as $facet){
			$facetName = $facet->facetName;

			//Adjust facet name for local scoping
			if ($solrScope){
				if (in_array($facetName, [
					'availability_toggle',
					'format',
					'format_category',
					'econtent_source',
					'language',
					'translation',
					'detailed_location',
					'owning_location',
					'owning_library',
					'available_at',
					'collection',
				])){
					$facetName .= '_' . $solrScope;
				}

				// Handle obsolete facet name
				if ($facet->facetName == 'collection_group'){
					$facetName = 'collection_' . $solrScope;
				}
			}
			if (isset($searchLibrary)){
				if ($facet->facetName == 'time_since_added'){
					$facetName = 'local_time_since_added_' . $searchLibrary->subdomain;
				}elseif ($facet->facetName == 'itype'){
					$facetName = 'itype_' . $searchLibrary->subdomain;
				}
			}
			//TODO: check if needed anymore
//			if (isset($userLocation)){
//				if ($facet->facetName == 'availability_toggle'){
//					$facetName = 'availability_toggle_' . $userLocation->code;
//				}
//			}
			if (isset($searchLocation)){
				if ($facet->facetName == 'time_since_added' && $searchLocation->restrictSearchByLocation){
					$facetName = 'local_time_since_added_' . $searchLocation->code;
				}
			}

			if ($facet->showInAdvancedSearch){
				$this->facetConfig[$facetName] = $facet->displayName;
			}
		}

		//********************

		$facetLimit = $this->getFacetSetting('Advanced_Settings', 'facet_limit');
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
	 * Initialise the object for retrieving dynamic data
	 *    for the browse screen to function.
	 *
	 * We don't know much at this stage, the browse AJAX
	 *   calls need to supply the queries and facets.
	 *
	 * @access  public
	 * @return  boolean
	 */
	public function initBrowseScreen()
	{
		global $configArray;

		// Call the standard initialization routine in the parent:
		parent::init();

		$this->facetConfig = array();
		// Use the facet limit specified in config.ini (or default to 100):
		$this->facetLimit = isset($configArray['Browse']['result_limit']) ?
		$configArray['Browse']['result_limit'] : 100;
		// Sorting defaults to off with unlimited facets
		$this->setFacetSortOrder('count');

		// We don't need spell checking
		$this->spellcheck = false;

		//********************
		// Basic Search logic
		$this->searchTerms[] = array(
            'index'   => $this->defaultIndex,
            'lookfor' => ""
            );

            return true;
	}

	/**
	 * Return the specified setting from the facets.ini file.
	 *
	 * @access  public
	 * @param   string $section   The section of the facets.ini file to look at.
	 * @param   string $setting   The setting within the specified file to return.
	 * @return  string    The value of the setting (blank if none).
	 */
	public function getFacetSetting($section, $setting){
		return $this->allFacetSettings[$section][$setting] ?? '';
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
	public function useBasicDictionary() {
		$this->dictionary = 'basicSpell';
	}

	public function getQuery()          {return $this->query;}
	public function getIndexEngine()    {return $this->indexEngine;}

	/**
	 * Return the field (index) searched by a basic search
	 *
	 * @access  public
	 * @return  string   The searched index
	 */
	public function getSearchIndex(){
		// Use normal parent method for non-advanced searches.
		if ($this->searchType == $this->basicSearchType || $this->searchType == 'author'){
			return parent::getSearchIndex();
		}else{
			return null;
		}
	}

	/**
	 * Any search types that use a field of type text-left will have an upper limit for search phrases that will return matching results.
	 *  Any search phrases longer than the upper limit will always have no results.
	 *
	 * @return array  Array of search types/search indexes that text-left fields
	 */
	public function getTextLeftSearchIndexes(){
		return ['StartOfTitle'];
	}


	/**
	 * Use the record driver to build an array of HTML displays from the search
	 * results suitable for use while displaying lists
	 *
	 * @access  public
	 * @param   int $listId ID of list containing desired tags/notes (or
	 *                              null to show tags/notes from all user's lists).
	 * @param   bool $allowEdit Should we display edit controls?
	 * @param   array $IDList optional list of IDs to re-order the records by (ie User List sorts)
	 * @param    bool $isMixedUserList Used to correctly number items in a list of mixed content (eg catalog & archive content)
	 * @return array Array of HTML chunks for individual records.
	 */
	public function getResultListHTML($listId = null, $allowEdit = true, $IDList = null, $isMixedUserList = false){
		global $interface;
		$html = [];
		if ($IDList){
			//Reorder the documents based on the list of id's
			$x = 0;
			$nullHolder = null;
			foreach ($IDList as $listPosition => $currentId){
				// use $IDList as the order guide for the html
				$current = &$nullHolder; // empty out in case we don't find the matching record
				reset($this->indexResult['response']['docs']);
				foreach ($this->indexResult['response']['docs'] as $index => $doc) {
					if ($doc['id'] == $currentId) {
						$current = & $this->indexResult['response']['docs'][$index];
						break;
					}
				}
				if (empty($current)) {
					continue; // In the case the record wasn't found, move on to the next record
				}else {
					if ($isMixedUserList) {
						$interface->assign('recordIndex', $listPosition + 1);
						$interface->assign('resultIndex', $listPosition + 1 + (($this->page - 1) * $this->limit));
					} else {
						$interface->assign('recordIndex', $x + 1);
						$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
					}
					if (!$this->debug){
						unset($current['explain']);
						unset($current['score']);
					}
					/** @var GroupedWorkDriver $record */
					$record = RecordDriverFactory::initRecordDriver($current);
					if ($isMixedUserList) {
						$html[$listPosition] = $interface->fetch($record->getListEntry($listId, $allowEdit));
					} else {
						$html[] = $interface->fetch($record->getListEntry($listId, $allowEdit));
						$x++;
					}
				}
			}
		}else{
			//The order we get from solr is just fine
			for ($x = 0; $x < count($this->indexResult['response']['docs']); $x++) {
				$current = & $this->indexResult['response']['docs'][$x];
				$interface->assign('recordIndex', $x + 1);
				$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
				if (!$this->debug){
					unset($current['explain']);
					unset($current['score']);
				}
				/** @var GroupedWorkDriver $record */
				$interface->assign('recordIndex', $x + 1);
				$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
				$record = RecordDriverFactory::initRecordDriver($current);
				$html[] = $interface->fetch($record->getListEntry($listId, $allowEdit));
			}
		}
		return $html;
	}

	/**
	 * TODO: Currently not used by anything in Pika
	 *
	 * Use the record driver to build an array of HTML displays from the search
	 * results suitable for use on a user's "favorites" page.
	 *
	 * @access  public
	 * @return  array   Array of HTML chunks for individual records.
	 */
	public function getSuggestionListHTML()
	{
		global $interface;

		$html = array();
		if (isset($this->indexResult['response']) && isset($this->indexResult['response']['docs'])){
			for ($x = 0; $x < count($this->indexResult['response']['docs']); $x++) {
				$current = & $this->indexResult['response']['docs'][$x];
				if (!$this->debug){
					unset($current['explain']);
					unset($current['score']);
				}
				$record = RecordDriverFactory::initRecordDriver($current);
				$html[] = $interface->fetch($record->getSuggestionEntry());
			}
		}
		return $html;
	}
	/**
	 * Use the record driver to build an array of HTML displays from the search
	 * results suitable for use on a user's "favorites" page.
	 *
	 * @access  public
	 * @return  array   Array of HTML chunks for individual records.
	 */
	public function getBrowseRecordHTML(){
		global $interface;
		$html = [];
		for ($x = 0;$x < count($this->indexResult['response']['docs']);$x++){
			$current = &$this->indexResult['response']['docs'][$x];
			$interface->assign('recordIndex', $x + 1);
			$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
			$record = RecordDriverFactory::initRecordDriver($current);
			if (!PEAR_Singleton::isError($record)){
				if (method_exists($record, 'getBrowseResult')){
					$html[] = $interface->fetch($record->getBrowseResult());
				}else{
					$html[] = 'Browse Result not available';
				}

			}else{
				$html[] = 'Unable to find record';
			}
		}
		return $html;
	}

	/**
	 * Return the record set from the search results.
	 *
	 * @access  public
	 * @return  array   recordSet
	 */
	public function getResultRecordSet()
	{
		//Marmot add shortIds without dot for use in display.
		if (isset($this->indexResult['response'])){
			$recordSet = $this->indexResult['response']['docs'];
			if (is_array($recordSet)){
				foreach ($recordSet as $key => $record){
					//Trim off the dot from the start
					$record['shortId'] = ltrim($record['id'], '.'); // TODO: does this shortID even get used anymore. (record['id'] Should be always be a grouped work instead of a bib ID)
					if (!$this->debug){
						unset($record['explain']);
						unset($record['score']);
					}
					$recordSet[$key] = $record;
				}
			}
		}else{
			return array();
		}

		return $recordSet;
	}

	/**
	 * @param array $orderedListOfIDs  Use the index of the matched ID as the index of the resulting array of ListWidget data (for later merging)
	 * @return array
	 */
	public function getListWidgetTitles($orderedListOfIDs = array()){
		$widgetTitles = array();
		for ($x = 0; $x < count($this->indexResult['response']['docs']); $x++) {
			$current = & $this->indexResult['response']['docs'][$x];
			$record = RecordDriverFactory::initRecordDriver($current);
			if (!PEAR_Singleton::isError($record)){
				if (method_exists($record, 'getListWidgetTitle')){
					if (!empty($orderedListOfIDs)){
						$position = array_search($current['id'], $orderedListOfIDs);
						if ($position !== false){
							$widgetTitles[$position] = $record->getListWidgetTitle();
						}
					} else {
						$widgetTitles[] = $record->getListWidgetTitle();
					}
				}else{
					$widgetTitles[] = 'List Widget Title not available';
				}
			}else{
				$widgetTitles[] = "Unable to find record";
			}
		}
		return $widgetTitles;
	}

	/*
	 * Get an array of citations for the records within the search results
	 */
	public function getCitations($citationFormat){
		global $interface;
		$html = array();
		for ($x = 0; $x < count($this->indexResult['response']['docs']); $x++) {
			$current = & $this->indexResult['response']['docs'][$x];
			$interface->assign('recordIndex', $x + 1);
			$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
			$record = RecordDriverFactory::initRecordDriver($current);
			$html[] = $interface->fetch($record->getCitation($citationFormat));
		}
		return $html;
	}
/*
 *  Get the template to use to display the results returned from getRecordHTML()
 *  based on the view mode
 *
 * @return string  Template file name
 */
	public function getDisplayTemplate() {
		if ($this->view == 'covers'){
			$displayTemplate = 'Search/covers-list.tpl'; // structure for bookcover tiles
		} else { // default
			$displayTemplate = 'Search/list-list.tpl'; // structure for regular results
		}
		return $displayTemplate;
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
		global $memoryWatcher;
		$html = [];
		if (isset($this->indexResult['response'])){
			$allWorkIds = [];
			foreach($this->indexResult['response']['docs'] as &$doc){
				$allWorkIds[] = $doc['id'];
			}
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			GroupedWorkDriver::loadArchiveLinksForWorks($allWorkIds);
			foreach($this->indexResult['response']['docs'] as $x => &$current){
				$memoryWatcher->logMemory("Started loading index document information for result $x");
				if (!$this->debug){
					unset($current['explain']);
					unset($current['score']);
				}
				$interface->assign('recordIndex', $x + 1);
				$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
				$record = RecordDriverFactory::initRecordDriver($current);
				if (!PEAR_Singleton::isError($record)){
					$interface->assign('recordDriver', $record);
					$html[] = $interface->fetch($record->getSearchResult($this->view));
				}else{
					$html[] = 'Unable to find record';
				}
				//Free some memory
				$record = 0;
				unset($record);
				$memoryWatcher->logMemory("Finished loading record information for index $x");
			}
		}
		return $html;
	}

	/**
	 * Use the record driver to build an array of HTML displays from the search
	 * results.
	 *
	 * @access  public
	 * @return  array   Array of HTML chunks for individual records.
	 */
	public function getCombinedResultsHTML()
	{
		global $interface;
		global $memoryWatcher;
		$html = array();
		if (isset($this->indexResult['response'])) {
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			for ($x = 0; $x < count($this->indexResult['response']['docs']); $x++) {
				$memoryWatcher->logMemory("Started loading record information for index $x");
				$current = &$this->indexResult['response']['docs'][$x];
				if (!$this->debug) {
					unset($current['explain']);
					unset($current['score']);
				}
				$interface->assign('recordIndex', $x + 1);
				$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
				/** @var GroupedWorkDriver|ListRecord $record */
				$record = RecordDriverFactory::initRecordDriver($current);
				if (!PEAR_Singleton::isError($record)) {
					$interface->assign('recordDriver', $record);
					$html[] = $interface->fetch($record->getCombinedResult($this->view));
				} else {
					$html[] = "Unable to find record";
				}
				//Free some memory
				$record = 0;
				unset($record);
				$memoryWatcher->logMemory("Finished loading record information for index $x");
			}
		}
		return $html;
	}

	/**
	 * Set an overriding array of record IDs.
	 *
	 * @access  public
	 * @param   array   $ids        Record IDs to load
	 */
	public function setQueryIDs($ids){
		$this->query = 'id:(' . implode(' ', $ids) . ')';
		// separating by a single space appears to be the equivalent of ORing as below
//		$this->query = 'id:(' . implode(' OR ', $ids) . ')';
	}

	/**
	 * Set an overriding string.
	 *
	 * @access  public
	 * @param   string  $newQuery   Query string
	 */
	public function setQueryString($newQuery){
		$this->query = $newQuery;
	}

	/**
	 * Set an overriding facet sort order.
	 *
	 * @access  public
	 * @param   string  $newSort   Sort string
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
	 * @param   string  $prefix   Data for prefix
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
		$returnArray = array();
		if (count($this->suggestions) == 0){
			return $returnArray;
		}
		$tokens = $this->spellingTokens($this->buildSpellingQuery());

		foreach ($this->suggestions as $term => $details){
			// Find out if our suggestion is part of a token
			$inToken    = false;
			$targetTerm = "";
			foreach ($tokens as $token){
				// TODO - Do we need stricter matching here?
				//   Similar to that in replaceSearchTerm()?
				if (stripos($token, $term) !== false){
					$inToken = true;
					// We need to replace the whole token
					$targetTerm = $token;
					// Go and replace this token
					$returnArray = $this->doSpellingReplace($term, $targetTerm, $inToken, $details, $returnArray);
				}
			}
			// If no tokens we found, just look
			//    for the suggestion 'as is'
			if ($targetTerm == ""){
				$targetTerm  = $term;
				$returnArray = $this->doSpellingReplace($term, $targetTerm, $inToken, $details, $returnArray);
			}
		}
		return $returnArray;
	}


	/**
	 * Return a list of valid sort options -- overrides the base class with
	 * custom behavior for Author/Search screen.
	 *
	 * @access  public
	 * @return  array    Sort value => description array.
	 */
	public function getSortOptions(){
		// Author/Search screen
		if ($this->searchType == 'author' && $this->authorSearchType == 'search'){
			// It's important to remember here we are talking about on-screen
			//   sort values, not what is sent to Solr, since this screen
			//   is really using facet sorting.
			return [
				'relevance' => 'sort_author_relevance',
				'author'    => 'sort_author_author'
			];
		}

		// Everywhere else -- use normal default behavior
		$sortOptions   = parent::getSortOptions();
		$searchLibrary = Library::getSearchLibrary($this->searchSource);
		if ($searchLibrary == null){
			unset($sortOptions['callnumber_sort']);
		}
		return $sortOptions;
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
			$fullQuery    = $this->indexEngine->buildQuery($this->searchTerms, false);
			$displayQuery = $this->indexEngine->buildQuery($this->searchTerms, true);
			$this->query  = $fullQuery;
			if ($fullQuery != $displayQuery){
				$this->publicQuery = $displayQuery;
			}
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
			$output = $this->publicQuery = $this->indexEngine->buildQuery($this->searchTerms, true);
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
		//todo: some of these cases are obsolete
		switch ($this->searchType){
			case 'favorites' :
				return $this->serverUrl . '/MyAccount/Home?';
			case 'list' :
				return $this->serverUrl . '/MyAccount/MyList/' . urlencode($_GET['id']) . '?';
			case 'author' :
				// Base URL is different for author searches:
				if ($this->authorSearchType == 'home'){
					return $this->serverUrl . '/Author/Home?';
				}
				if ($this->authorSearchType == 'search'){
					return $this->serverUrl . "/Author/Search?";
				}
			// Restored saved author searches will not have a authorSearchType set so need to fall back to the default Base URL (so no break statement)
			default :
				// If none of the special cases were met, use the default from the parent:
				return parent::getBaseUrl();
		}
	}

	protected $params;
	/**
	 * Get an array of strings to attach to a base URL in order to reproduce the
	 * current search.
	 *
	 * @access  protected
	 * @return  array    Array of URL parameters (key=url_encoded_value format)
	 */
	protected function getSearchParams(){
		//if (is_null($this->params)){ // caching the params locally breaks the base class function renderLinkWithReplacedTerm() changing search terms
			$params = [];
			switch ($this->searchType){
				// Author Home screen
				case 'author':
					//restored saved author searches
					$params[] = ($this->authorSearchType == 'home' ? 'author=' : 'lookfor=') . urlencode($this->searchTerms[0]['lookfor']);
					$params[] = 'basicSearchType=Author';
					break;
				case 'favorites':
				case 'list':
					$preserveParams = [
						// for favorites/list:
						'tag', 'pagesize'
					];
					foreach ($preserveParams as $current){
						if (isset($_GET[$current])){
							if (is_array($_GET[$current])){
								foreach ($_GET[$current] as $value){
									$params[] = $current . '[]=' . urlencode($value);
								}
							}else{
								$params[] = $current . '=' . urlencode($_GET[$current]);
							}
						}
					}
					break;
				// Basic search -- use default from parent class.
				default:
					$params = parent::getSearchParams();
					break;
			}

			if (isset($_REQUEST['basicType'])){
				if ($_REQUEST['basicType'] == 'AllFields'){
					$_REQUEST['basicType'] = 'Keyword';
				}
				if (is_array($_REQUEST['basicType'])){
					$_REQUEST['basicType'] = reset($_REQUEST['basicType']);
				}
				$params[] = 'basicType=' . $_REQUEST['basicType'];
			}elseif (isset($_REQUEST['type'])){
				if ($_REQUEST['type'] == 'AllFields'){
					$_REQUEST['type'] = 'Keyword';
				}
				$params[] = 'type=' . $_REQUEST['type'];
			}
			$this->params = $params;
		//}
		return $this->params;
	}

	/**
	 * Process a search for a particular tag.
	 *
	 * @access  private
	 * @param   string  $lookfor    The tag to search for
	 * @return  boolean   A revised searchTerms array to get matching Solr records
	 *                  (empty if no tag matches found).
	 */
	private function processTagSearch($lookfor){
		// Include the app database objects
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';

		// Find our tag in the database
		$tag      = new UserTag();
		$tag->tag = $lookfor;
		$tag->selectAdd(null);
		$tag->selectAdd('DISTINCT(groupedWorkPermanentId) as groupedWorkPermanentId');
		if ($tag->find()){
			$groupedWorkIds = [];
			while ($tag->fetch()){
				// Grab the list of records tagged with this tag
				$groupedWorkIds[] = $tag->groupedWorkPermanentId;
			}
			$this->setQueryIDs($groupedWorkIds);
			return true;
		}
		return false;
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
	 * @param   bool   $returnIndexErrors  Should we die inside the index code if
	 *                                     we encounter an error (false) or return
	 *                                     it for access via the getIndexError()
	 *                                     method (true)?
	 * @param   bool   $recommendations    Should we process recommendations along
	 *                                     with the search itself?
	 * @param   bool   $preventQueryModification   Should we allow the search engine
	 *                                             to modify the query or is it already
	 *                                             a well formatted query
	 * @return array|object
	 */
	public function processSearch($returnIndexErrors = false, $recommendations = false, $preventQueryModification = false) {
		global $timer;
		global $solrScope;

		if ($this->searchSource == 'econtent'){
			$this->addHiddenFilter("econtent_source_$solrScope", '*');
		}

		// Our search has already been processed in init()
		$search = $this->searchTerms;

		// Build a recommendations module appropriate to the current search:
		if ($recommendations) {
			$this->initRecommendations();
			$timer->logTime('initRecommendations');
		}

		// Tag searches need to be handled differently
		if (!empty($search[0]['index']) && $search[0]['index'] == 'tag'){
			// If we managed to find some tag matches, the query will be a list of Ids.
			// If we didn't find any tag matches, we should return an empty record set.
			$this->publicQuery = $search[0]['lookfor'];
			if (!$this->processTagSearch($search[0]['lookfor'])){
				// Save search so it displays correctly on the "no hits" page:
				return ['response' => ['numFound' => 0, 'docs' => []]];
			}
			$timer->logTime('process Tag search');
		}else{

			// Build Query
			if ($preventQueryModification){
				$query = $search;
			}else{
				$query = $this->indexEngine->buildQuery($search, false);
			}
			$timer->logTime('build query');
			if (PEAR_Singleton::isError($query)){
				return $query;
			}
		}
		// Only use the query we just built if there isn't an override in place.
		if ($this->query == null) {
			$this->query = $query;
		}

		// Define Filter Query
		[$filterQuery, $availabilityByFormatFieldName, $availableAtByFormatFieldName] = $this->setFinalFilterQuery();

		// If we are only searching one field use the DisMax handler
		//    for that field. If left at null let solr take care of it
		if (count($search) == 1 && isset($search[0]['index'])) {
			$this->index = $search[0]['index'];
		}

		// Build a list of facets we want from the index
		$facetSet = [];
		if (!empty($this->facetConfig)) {
			$facetSet['limit'] = $this->facetLimit;
			foreach ($this->facetConfig as $facetField => $facetName) {
				if (strpos($facetField, 'availability_toggle') === 0){
					if ($availabilityByFormatFieldName){
						$facetSet['field'][] = $availabilityByFormatFieldName;
					}else{
						$facetSet['field'][] = $facetField;
					}
				}elseif (strpos($facetField, 'available_at') === 0){
					if ($availableAtByFormatFieldName){
						$facetSet['field'][] = $availableAtByFormatFieldName;
					}else{
						$facetSet['field'][] = $facetField;
					}
				}else{
					$facetSet['field'][] = $facetField;
				}
			}
			if ($this->facetOffset != null) {
				$facetSet['offset'] = $this->facetOffset;
			}
			if ($this->facetLimit != null) {
				$facetSet['limit'] = $this->facetLimit;
			}
			if ($this->facetPrefix != null) {
				$facetSet['prefix'] = $this->facetPrefix;
			}
			if ($this->facetSort != null) {
				$facetSet['sort'] = $this->facetSort;
			}
		}
		if (!empty($this->facetOptions)){
			$facetSet['additionalOptions'] = $this->facetOptions;
		}
		$timer->logTime('create facets');

		// Build our spellcheck query
		if ($this->spellcheck) {
			if ($this->spellSimple) {
				$this->useBasicDictionary();
			}
			$spellcheck = $this->buildSpellingQuery();

			// If the spellcheck query is purely numeric, skip it if
			// the appropriate setting is turned on.
			if ($this->spellSkipNumeric && is_numeric($spellcheck)) {
				$spellcheck = '';
			}
			$timer->logTime('create spell check');
		} else {
			$spellcheck = '';
		}

		// The "relevance" sort option is a Pika reserved word; we need to make
		// this null in order to achieve the desired effect with Solr:
		$finalSort = ($this->sort == 'relevance') ? null : $this->sort;

		// The first record to retrieve:
		//  (page - 1) * limit = start
		$recordStart = ($this->page - 1) * $this->limit;
		//Remove irrelevant fields based on scoping
		$fieldsToReturn = $this->getFieldsToReturn();

		global $configArray;
		$boost = null;
		if (!empty($configArray['Index']['enableBoosting'])){
			$boost = $this->getBoostingFormula();
			$timer->logTime('apply boosting');
		}

		// Get time before the query
		$this->startQueryTimer();

		$this->indexResult = $this->indexEngine->search(
			$this->query,      // Query string
			$this->index,      // The search Specification to Use
			$filterQuery,      // Filter query
			$recordStart,      // Starting record
			$this->limit,      // Records per page
			$facetSet,         // Fields to facet on
			$spellcheck,       // Spellcheck query
			$this->dictionary, // Spellcheck dictionary
			$finalSort,        // Field to sort on
			$fieldsToReturn,   // Fields to return
			$this->method,     // HTTP Request method
			$returnIndexErrors,// Include errors in response?
			$boost             // Results boosting formula
		);
		$timer->logTime('run solr search');

		// Get time after the query
		$this->stopQueryTimer();

		// How many results were there?
		if (!isset($this->indexResult['response']['numFound'])){
			//An error occurred
			$this->resultsTotal = 0;
		}else{
			$this->resultsTotal = $this->indexResult['response']['numFound'];
		}

		// Process spelling suggestions if no index error resulted from the query
		if ($this->spellcheck && !isset($this->indexResult['error'])) {
			// Shingle dictionary
			$this->processSpelling();
			// Make sure we don't endlessly loop
			if ($this->dictionary == 'default') {
				// Expand against the basic dictionary
				$this->basicSpelling();
			}
		}

		// If extra processing is needed for recommendations, do it now:
		if ($recommendations && is_array($this->recommend)) {
			foreach($this->recommend as $currentSet) {
				foreach($currentSet as $current) {
					/** @var SideFacets|TopFacets $current */
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

	private function setFinalFilterQuery(){
		global $solrScope;

		$filterQuery             = $this->hiddenFilters;
		$availabilityToggleValue = null;
		$availabilityAtValue     = null;
		$formatValue             = null;
		$formatCategoryValue     = null;
		$validFields             = $this->indexEngine->getValidFields();
		foreach ($this->filterList as $field => $filter){
			if ($field === ''){
				// Remove any empty filters if we get them
				// (typically happens when a subdomain has a function disabled that is enabled in the main scope)
				unset($this->filterList[$field]);
				continue;
			}

			//Check the filters to make sure they are for the correct scope
			$isValidField = false;
			if (in_array($field, $validFields)){
				$isValidField = true;
			}else{
				//Field doesn't exist, check to see if it is a dynamic field
				//Where we can replace the scope with the current scope
				if (!isset($dynamicField)){
					$dynamicFields = $this->indexEngine->getDynamicFields();
				}
				foreach ($dynamicFields as $dynamicField){
					if (preg_match("/^{$dynamicField}[^_]+$/", $field)){
						//This is a dynamic field with the wrong scope  eg. format_wrongScopeName
						$field        = $dynamicField . $solrScope;
						$isValidField = true;
						break;
					}elseif ($field == rtrim($dynamicField, '_')){
						//This is a regular field that is now a dynamic field so needs the scope applied  eg. format
						$field        = $dynamicField . $solrScope;
						$isValidField = true;
						break;
					}
				}
			}

			if ($isValidField){
				foreach ($filter as $value){
					$isAvailabilityToggle = false;
					$isAvailableAt        = false;
					if (strpos($field,'availability_toggle') === 0){
						$availabilityToggleValue = $value;
						$isAvailabilityToggle    = true;
					}elseif (strpos($field,'available_at') === 0){
						$availabilityAtValue = $value;
						$isAvailableAt       = true;
					}elseif (strpos($field,'format_category') === 0){
						$formatCategoryValue = $value;
					}elseif (strpos($field,'format') === 0){
						$formatValue = $value;
					}

					// Special case -- allow trailing wildcards:
					if (substr($value, -1) == '*'){
						$filterQuery[] = "$field:$value";
					}elseif (preg_match('/\\A\\[.*?\\sTO\\s.*?]\\z/', $value)){
						$filterQuery[] = "$field:$value";
					}elseif (preg_match('/^\\(.*?\\)$/', $value)){
						$filterQuery[] = "$field:$value";
					}else{
						if (!empty($value)){
							if ($isAvailabilityToggle){
								$filterQuery['availability_toggle'] = "$field:\"$value\"";
							}elseif ($isAvailableAt){
								$filterQuery['available_at'] = "$field:\"$value\"";
							}else{
								if (is_numeric($field)){
									$filterQuery[] = $value;
								}else{
									$filterQuery[] = "$field:\"$value\"";
								}
							}
						}
					}
				}
			}
		}

		//Check to see if we have format facets applied.
		$availabilityByFormatFieldName = $availableAtByFormatFieldName= null;
		if ($formatCategoryValue != null || $formatValue != null){
			// When a format or format category facet is applied, switch to using the availability plus format facets.
			// Both for filtering and importantly, for facet fetching (so that incompatible eContent availability isn't displayed in facets)

			//Make sure to process the more specific format first
			if ($formatValue != null){
				$escapedFormatValue            = strtolower(preg_replace('/\W/', '_', $formatValue));
				$availabilityByFormatFieldName = 'availability_by_format_' . $solrScope . '_' . $escapedFormatValue;
				$availableAtByFormatFieldName  = 'available_at_by_format_' . $solrScope . '_' . $escapedFormatValue;
			}else{
				$escapedFormatCategoryValue     = strtolower(preg_replace('/\W/', '_', $formatCategoryValue));
				$availabilityByFormatFieldName = 'availability_by_format_' . $solrScope . '_' . $escapedFormatCategoryValue;
				$availableAtByFormatFieldName  = 'available_at_by_format_' . $solrScope . '_' . $escapedFormatCategoryValue;
			}
			if (!empty($availabilityToggleValue)){
				$filterQuery['availability_toggle'] = $availabilityByFormatFieldName . ':"' . $availabilityToggleValue . '"';
			}
			if (!empty($availabilityAtValue)){
				$filterQuery['available_at'] = $availableAtByFormatFieldName . ':"' . $availabilityAtValue . '"';
			}
		}

		$scopingFilters = $this->getScopingFilters();
		$filterQuery    = array_merge($filterQuery, $scopingFilters);

		//		$timer->logTime('apply scoping filters');
		return [$filterQuery, $availabilityByFormatFieldName, $availableAtByFormatFieldName];
	}

	/**
	 * Get filters based on scoping for the search
	 * @return array
	 */
	public function getScopingFilters(){
		global $solrScope;

		$filter         = [];
		$searchLibrary  = Library::getSearchLibrary();
		$searchLocation = Location::getSearchLocation();

		//Simplify detecting which works are relevant to our scope
		if (!$this->scopingDisabled){
			if ($solrScope){
				$filter[] = "scope_has_related_records:$solrScope";
			}elseif (isset($searchLocation)){
				// A solr scope should be defined usually. It is probably an anomalous situation to fall back to this, and should be fixed; (or noted here explicitly.)
				// kids/juvenille opac interfaces??
				global $pikaLogger;
				$pikaLogger->notice('Global solr scope not set when setting scoping filters');
				$filter[] = "scope_has_related_records:{$searchLocation->code}";
			}elseif (isset($searchLibrary)){
				// A solr scope should be defined usually. It is probably an anomalous situation to fall back to this, and should be fixed; (or noted here explicitly.)
				// kids/juvenille opac interfaces??
				global $pikaLogger;
				$pikaLogger->notice('Global solr scope not set when setting scoping filters');
				$filter[] = "scope_has_related_records:{$searchLibrary->subdomain}";
			}
		}

		$blacklistRecords = '';
		if (!empty($searchLocation->recordsToBlackList)) {
			$blacklistRecords = $searchLocation->recordsToBlackList;
		}
		if (!empty($searchLibrary->recordsToBlackList)) {
			$blacklistRecords .= "\n" . $searchLibrary->recordsToBlackList;
		}
		if (!empty($blacklistRecords)){
			$recordsToBlacklist = preg_split('/\s|\r\n|\r|\n/', $blacklistRecords, -1, PREG_SPLIT_NO_EMPTY);
			$blacklist          = '-id:(' . implode(' OR ', $recordsToBlacklist) . ')';
			$filter[]           = $blacklist;
		}

		return $filter;
	}

	/**
	 * Load Boost factors for a search query and return the boosting formula
	 *
	 * @return string
	 */
	public function getBoostingFormula(){
		$boostFactors = [];
		$boostFormula = null;

		global $solrScope;

//		$boostFactors[] = (!empty($searchLibrary->applyNumberOfHoldingsBoost)) ? 'product(sum(popularity,1),format_boost)' : 'format_boost';

		$searchLibrary  = Library::getSearchLibrary();
		$searchLocation = Location::getSearchLocation();
		if ((!empty($searchLibrary->applyNumberOfHoldingsBoost))){
			$boostFactors[] = 'sum(popularity,1)';
		}
		$boostFactors[] = 'format_boost_' . $solrScope;
		//$boostFactors[] = 'if(exists(format_boost_' . $solrScope . '),format_boost_' . $solrScope .',format_boost)';
		// This is needed for the time before the format_boost has been indexed as a scoped field

		// popularity is indexed as zero or greater, but to apply to boosting we want it to be a value of 1 or greater
		// hence sum(popularity,1)

		//For physical records, popularity is determined by the checkouts for each item with this formula :
		//  year-to-date checkouts
		//  plus half of last year's checkouts
		//  plus a tenth of total checkouts minus last year's checkouts and minus the year-to-date checkouts
		// Or a value of one, if the calculation above is zero
		// So a record's popularity is the sum of this calculation for every item
		//  plus twice the number of holds there are on the record

		// For order records, the popularity is based on the number of copies for each order record

		// For Overdrive, the popularity is the overdrive metadata measure popularity divided by 500; or 1 if it is less than 500

		// For Sideloads and Hoopla, the popularity is the number of bibs

		// Add rating as part of the ranking
		$boostFactors[] = 'sum(rating,1)';

		// Library Holdings Boost factors:
		// when the usual default values from config.ini are :
		// availableAtLocationBoostValue = 50
		// ownedByLocationBoostValue = 10
		// the lib_boost_[scope] field will be 50 when any item is available at the library,
		// or it will be 10 when an item is owned,
		// or it will be missing (effectively zero) when it is neither "available at" nor owned by the library

		if (!empty($searchLibrary->boostByLibrary)) {
			$boostFactors[] = ($searchLibrary->additionalLocalBoostFactor > 1) ? "sum(product(lib_boost_{$solrScope},{$searchLibrary->additionalLocalBoostFactor}),1)" : "sum(lib_boost_{$solrScope},1)";
		} else {
			// Handle boosting even if we are in a global scope
			global $library;
			if (!empty($library->boostByLibrary)) {
				$boostFactors[] = ($library->additionalLocalBoostFactor > 1) ? "sum(product(lib_boost_{$solrScope},{$library->additionalLocalBoostFactor}),1)" : "sum(lib_boost_{$solrScope},1)";
				global $pikaLogger;
				$pikaLogger->notice('Case of missing library search scope', [$_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']]);
				//TODO: document when this situation occurs.  It ought to be the case that there is always a search scope defined
			}
		}

		if (!empty($searchLocation->boostByLocation)) {
			$boostFactors[] = ($searchLocation->boostByLocation > 1) ? "sum(product(lib_boost_{$solrScope},{$searchLocation->additionalLocalBoostFactor}),1)" : "sum(lib_boost_{$solrScope},1)";

		} else {
			// Handle boosting even if we are in a global scope
			global $locationSingleton;
			$physicalLocation = $locationSingleton->getActiveLocation();
			if (!empty($physicalLocation->boostByLocation)) {
				$boostFactors[] = ($physicalLocation->additionalLocalBoostFactor > 1) ? "sum(product(lib_boost_{$solrScope},{$physicalLocation->additionalLocalBoostFactor}),1)" : "sum(lib_boost_{$solrScope},1)";
				global $pikaLogger;
				$pikaLogger->notice('Case of missing location search scope (and using physical location)', [$_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']]);
				//TODO: document when this situation occurs.  It ought to be the case that there is always a search scope defined
			}
		}
		if (!empty($boostFactors)){
			$boostFormula = 'sum(' . implode(',', $boostFactors) . ')';
		}
		return $boostFormula;
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
			// Just the search query is fine
			return $this->query;

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
		if (count($suggestions) == 0){
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
	 * @param   array    $termList List of suggestions
	 * @return  array    Filtered list
	 */
	private function filterSpellingTerms($termList){
		if (empty($termList)){
			return [];
		}

		$newList = [];
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
		// TODO: There might be a way to run the
		//   search against both dictionaries from
		//   inside solr. Investigate. Currently
		//   submitting a second search for this.

		// Create a new search object
		$newSearch = SearchObjectFactory::initSearchObject('Solr');
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
		if (count($this->suggestions) == 0) {
			// Just use the basic ones as provided
			$this->suggestions = $newList;

			// Otherwise
		} else {
			// For all the new suggestions
			foreach ($newList as $word => $data) {
				// Check the old suggestions
				$found = false;
				foreach ($this->suggestions as $k => $v) {
					// Make sure it wasn't part of a shingle
					//   which has been suggested at a higher
					//   level.
					$found = preg_match("/\b$word\b/", $k) ? true : $found;
				}
				if (!$found) {
					$this->suggestions[$word] = $data;
				}
			}
		}
	}

	/**
	 * Process facets from the results object
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
		global $solrScope;
		// If there is no filter, we'll use all facets as the filter:
		if (is_null($filter)){
			$filter = $this->facetConfig;
		}

		// Start building the facet list:
		$list = [];

		// If we have no facets to process, give up now
		if (!isset($this->indexResult['facet_counts'])){
			return $list;
		}elseif (!is_array($this->indexResult['facet_counts']['facet_fields']) && !is_array($this->indexResult['facet_counts']['facet_dates'])) {
			return $list;
		}

		// Loop through every field returned by the result set
		$validFields = array_keys($filter);

		global $locationSingleton;
		/** @var Library $currentLibrary */
		$currentLibrary      = Library::getActiveLibrary();
		$activeLocationFacet = null;
		$activeLocation      = $locationSingleton->getActiveLocation();
		if (!empty($activeLocation)){
			$activeLocationFacet = $activeLocation->facetLabel;
		}
		$relatedLocationFacets          = null;
		$relatedHomeLocationFacets      = null;
		$additionalAvailableAtLocations = null;
		if (!empty($currentLibrary)){
			if (!empty($currentLibrary->facetLabel)){
				$currentLibrary->facetLabel = $currentLibrary->displayName;
			}
			$relatedLocationFacets = $locationSingleton->getLocationsFacetsForLibrary($currentLibrary->libraryId);
			if (strlen($currentLibrary->additionalLocationsToShowAvailabilityFor) > 0){
				$locationsToLookfor = explode('|', $currentLibrary->additionalLocationsToShowAvailabilityFor);
				$location = new Location();
				$location->whereAddIn('code', $locationsToLookfor, 'string');
				$location->find();
				$additionalAvailableAtLocations = [];
				while ($location->fetch()){
					$additionalAvailableAtLocations[] = $location->facetLabel;
				}
			}
		}
		$homeLibrary = UserAccount::getUserHomeLibrary();
		if (!empty($homeLibrary)){
			$relatedHomeLocationFacets = $locationSingleton->getLocationsFacetsForLibrary($homeLibrary->libraryId);
		}

		$allFacets = array_merge($this->indexResult['facet_counts']['facet_fields'], $this->indexResult['facet_counts']['facet_ranges']);
		foreach ($allFacets as $field => $data) {
			// Skip filtered fields and empty arrays:
			if (!in_array($field, $validFields) || count($data) < 1) {
				$isValid = false;
				//Check to see if we are overriding availability toggle
				if (strpos($field, 'availability_by_format') === 0){
					foreach ($validFields as $validFieldName){
						if (strpos($validFieldName, 'availability_toggle') === 0){
							$field   = $validFieldName;
							$isValid = true;
							break;
						}
					}
				}
				elseif (strpos($field, 'available_at_by_format') === 0){
					foreach ($validFields as $validFieldName){
						if (strpos($validFieldName, 'available_at') === 0){
							$field   = $validFieldName;
							$isValid = true;
							break;
						}
					}
				}
				if (!$isValid){
					continue;
				}
			}
			// Initialize the settings for the current field
			$list[$field] = [];
			// Add the on-screen label
			$list[$field]['label'] = $filter[$field];
			// Build our array of values for this field
			$list[$field]['list']    = [];
			$foundInstitution        = false;
			$doInstitutionProcessing = false;
			$foundBranch             = false;
			$doBranchProcessing      = false;

			//Marmot specific processing to do custom resorting of facets.
			if (strpos($field, 'owning_library') === 0 && !empty($currentLibrary)){
				$doInstitutionProcessing = true;
			}
			if (strpos($field, 'owning_location') === 0 && (!is_null($relatedLocationFacets) || !is_null($activeLocationFacet))){
				$doBranchProcessing = true;
			}elseif(strpos($field, 'available_at') === 0){
				$doBranchProcessing = true;
			}
			// Should we translate values for the current facet?
			$translate                = in_array($field, $this->translatedFacets);
			$numValidRelatedLocations = 0;
			$numValidLibraries        = 0;

			if (in_array($field, array_merge($this->rangeFilters, $this->dateFilters))){
				$list[$field]['list']['url'] = $this->renderSearchUrl();
			}else{
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

					//Set up the key to allow sorting alphabetically if needed.
					$valueKey = $facet[0];
					$okToAdd  = true;
					if ($doInstitutionProcessing){
						//Special processing for Marmot digital library
						if ($facet[0] == $currentLibrary->facetLabel){
							$valueKey         = '1' . $valueKey;
							$foundInstitution = true;
							$numValidLibraries++;
						}elseif ($facet[0] == $currentLibrary->facetLabel . ' Online'){
							$valueKey         = '1' . $valueKey;
							$foundInstitution = true;
							$numValidLibraries++;
						}elseif ($facet[0] == $currentLibrary->facetLabel . ' On Order' || $facet[0] == $currentLibrary->facetLabel . ' Under Consideration'){
							$valueKey         = '1' . $valueKey;
							$foundInstitution = true;
							$numValidLibraries++;
						}elseif ($facet[0] == 'Digital Collection' || $facet[0] == 'Marmot Digital Library'){
							$valueKey         = '2' . $valueKey;
							$foundInstitution = true;
							$numValidLibraries++;
						}elseif (!is_null($currentLibrary) && $currentLibrary->restrictOwningBranchesAndSystems == 1){
							//$okToAdd = false;
						}
					}elseif ($doBranchProcessing){
						if (strlen($facet[0]) > 0){
							if ($activeLocationFacet != null && $facet[0] == $activeLocationFacet){
								$valueKey    = '1' . $valueKey;
								$foundBranch = true;
								$numValidRelatedLocations++;
							}elseif (isset($currentLibrary) && $facet[0] == $currentLibrary->facetLabel . ' Online'){
								$valueKey = '1' . $valueKey;
								$numValidRelatedLocations++;
							}elseif (isset($currentLibrary) && ($facet[0] == $currentLibrary->facetLabel . ' On Order' || $facet[0] == $currentLibrary->facetLabel . ' Under Consideration')){
								$valueKey = '1' . $valueKey;
								$numValidRelatedLocations++;
							}elseif (!is_null($relatedLocationFacets) && in_array($facet[0], $relatedLocationFacets)){
								$valueKey = '2' . $valueKey;
								$numValidRelatedLocations++;
							}elseif (!is_null($relatedLocationFacets) && in_array($facet[0], $relatedLocationFacets)){
								$valueKey = '2' . $valueKey;
								$numValidRelatedLocations++;
							}elseif (!is_null($relatedHomeLocationFacets) && in_array($facet[0], $relatedHomeLocationFacets)){
								$valueKey = '2' . $valueKey;
								$numValidRelatedLocations++;
							}elseif (!is_null($currentLibrary) && $facet[0] == $currentLibrary->facetLabel . ' Online'){
								$valueKey = '3' . $valueKey;
								$numValidRelatedLocations++;
							}elseif ($field == 'available_at' && !is_null($additionalAvailableAtLocations) && in_array($facet[0], $additionalAvailableAtLocations)){
								$valueKey = '4' . $valueKey;
								$numValidRelatedLocations++;
							}elseif ($facet[0] == 'Marmot Digital Library' || $facet[0] == 'Digital Collection' || $facet[0] == 'OverDrive' || $facet[0] == 'Online'){
								$valueKey = '5' . $valueKey;
								$numValidRelatedLocations++;
							}elseif (!is_null($currentLibrary) && $currentLibrary->restrictOwningBranchesAndSystems == 1){
								//$okToAdd = false;
							}
						}
					}


					// Store the collected values:
					if ($okToAdd){
						$list[$field]['list'][$valueKey] = $currentSettings;
					}
				}
			}

			if (!$foundInstitution && $doInstitutionProcessing){
				$list[$field]['list']['1' . $currentLibrary->facetLabel] =
					[
						'value'     => $currentLibrary->facetLabel,
						'display'   => $currentLibrary->facetLabel,
						'count'     => 0,
						'isApplied' => false,
						'url'       => null,
						'expandUrl' => null,
					];
			}
			if (!$foundBranch && $doBranchProcessing && !is_null($activeLocationFacet)){
				$list[$field]['list']['1' . $activeLocationFacet] =
					[
						'value'     => $activeLocationFacet,
						'display'   => $activeLocationFacet,
						'count'     => 0,
						'isApplied' => false,
						'url'       => null,
						'expandUrl' => null,
					];
				$numValidRelatedLocations++;
			}

			//How many facets should be shown by default
			//Only show one system unless we are in the global scope
			if ($field == 'owning_library_' . $solrScope && isset($currentLibrary)){
				$list[$field]['valuesToShow'] = $numValidLibraries;
			}elseif ($field == 'owning_location_' . $solrScope && isset($relatedLocationFacets) && $numValidRelatedLocations > 0){
				$list[$field]['valuesToShow'] = $numValidRelatedLocations;
			}elseif ($field == 'available_at_' . $solrScope){
				$list[$field]['valuesToShow'] = count($list[$field]['list']);
			}else{
				$list[$field]['valuesToShow'] = 5;
			}

			//Sort the facet alphabetically?
			//Sort the system and location alphabetically unless we are in the global scope
			global $solrScope;
			if (in_array($field, ['owning_library_' . $solrScope, 'owning_location_' . $solrScope, 'available_at_' . $solrScope])  && isset($currentLibrary) ){
				$list[$field]['showAlphabetically'] = true;
			}else{
				$list[$field]['showAlphabetically'] = false;
			}
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
	 * @param   string      $preferredSection       Section to favor when loading
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
	 * @return  string           XML document
	 */
	public function buildRSS(){
		// XML HTTP header
		header('Content-type: text/xml', true);

		$this->limit  = 50;
		self::$fields = 'id,recordtype,title_display,author_display,display_description,date_added';  // format_category_' . $solrScope is added automatically
		$result       = $this->processSearch(false, false);

		global $library;
		global $configArray;
		$baseUrl  = empty($library->catalogUrl) ? $configArray['Site']['url'] : $_SERVER['REQUEST_SCHEME'] . '://' . $library->catalogUrl;


		foreach ($result['response']['docs'] as &$currentDoc){
			//Since the base URL can be different depending on the record type, add the url to the response
			$recordType = strtolower($currentDoc['recordtype']);
			switch ($recordType){
				case 'list' :
					$id                      = str_replace('list', '', $currentDoc['id']);
					$currentDoc['recordUrl'] = $baseUrl . '/MyAccount/MyList/' . $id;
					break;
				case 'grouped_work' :
				default :
					$id = $currentDoc['id'];
					require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
					$groupedWorkDriver = new GroupedWorkDriver($currentDoc);
					if ($groupedWorkDriver->isValid){
						$image                         = $groupedWorkDriver->getBookcoverUrl('medium', true);
						$description                   = "<img src='$image'/> " . $groupedWorkDriver->getDescriptionFast();
						$currentDoc['rss_description'] = $description;
						$currentDoc['rss_date']        = date('r', strtotime($currentDoc['date_added']));
						$currentDoc['recordUrl']       = $groupedWorkDriver->getAbsoluteUrl();
					}else{
						$currentDoc['recordUrl'] = $baseUrl . '/GroupedWork/' . $id;
					}
			}

		}

		global $interface;
		$lookFor = $this->displayQuery();
		if (count($this->filterList) > 0){
			// TODO : better display of filters
			$interface->assign('lookfor', $lookFor . " (" . translate('with filters') . ")");
		}else{
			$interface->assign('lookfor', $lookFor);
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
	 * @return  string                  Excel document
	 * @var  array $result Existing result set (null to do new search)
	 */
	public function buildExcel($result = null){
		// First, get the search results if none were provided
		// (we'll go for 1000 at a time)
		if (is_null($result)){
			$this->limit = 1000;
			$result      = $this->processSearch(false, false);
		}

		// Prepare the spreadsheet
		$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$objPHPExcel->getProperties()->setTitle("Search Results");

		$objPHPExcel->setActiveSheetIndex(0);
		$objPHPExcel->getActiveSheet()->setTitle('Results');

		//Add headers to the table
		$sheet  = $objPHPExcel->getActiveSheet();
		$curRow = 1;
		$curCol = 1;
		$sheet->setCellValue([$curCol++, $curRow], 'Record #');
		$sheet->setCellValue([$curCol++, $curRow], 'Title');
		$sheet->setCellValue([$curCol++, $curRow], 'Author');
		$sheet->setCellValue([$curCol++, $curRow], 'Publisher');
		$sheet->setCellValue([$curCol++, $curRow], 'Published');
		$sheet->setCellValue([$curCol++, $curRow], 'Call Number');
		$sheet->setCellValue([$curCol++, $curRow], 'Item Type');
		$sheet->setCellValue([$curCol++, $curRow], 'Location');

		$maxColumn = $curCol - 1;

		global $solrScope;
		foreach ($result['response']['docs'] as $curDoc){
			//Output the row to excel
			$curRow++;
			$curCol = 1;
			//Output the row to excel
			$sheet->setCellValue([$curCol++, $curRow], isset($curDoc['id']) ? $curDoc['id'] : '');
			$sheet->setCellValue([$curCol++, $curRow], isset($curDoc['title_display']) ? $curDoc['title_display'] : '');
			$sheet->setCellValue([$curCol++, $curRow], isset($curDoc['author']) ? $curDoc['author'] : '');
			$sheet->setCellValue([$curCol++, $curRow], isset($curDoc['publisher']) ? implode(', ', $curDoc['publisher']) : '');
			$sheet->setCellValue([$curCol++, $curRow], isset($curDoc['publishDate']) ? implode(', ', $curDoc['publishDate']) : '');
			$callNumber = '';
			if (isset($curDoc['local_callnumber_' . $solrScope])){
				$callNumber = is_array($curDoc['local_callnumber_' . $solrScope]) ? $curDoc['local_callnumber_' . $solrScope][0] : $curDoc['local_callnumber_' . $solrScope];
			}
			$sheet->setCellValue([$curCol++, $curRow], $callNumber);
			$iType = '';
			if (isset($curDoc['itype_' . $solrScope])){
				$iType = is_array($curDoc['itype_' . $solrScope]) ? $curDoc['itype_' . $solrScope][0] : $curDoc['itype_' . $solrScope];
			}
			$sheet->setCellValue([$curCol++, $curRow], $iType);
			$location = '';
			if (isset($curDoc['detailed_location_' . $solrScope])){
				$location = is_array($curDoc['detailed_location_' . $solrScope]) ? $curDoc['detailed_location_' . $solrScope][0] : $curDoc['detailed_location_' . $solrScope];
			}
			$sheet->setCellValue([$curCol++, $curRow], $location);
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

		$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPHPExcel, 'Xlsx');
		$objWriter->save('php://output'); //THIS DOES NOT WORK WHY?
		$objPHPExcel->disconnectWorksheets();
		unset($objPHPExcel);
	}

	/**
	 * Get records similar to one record
	 * Uses Custom Solr MoreLikeThis2 Request Handler
	 *
	 * @access  public
	 * @return  array              An array of query results
	 *
	 * @throws  object            PEAR Error
	 * @var     string $id The id to retrieve similar titles for
	 */
	function getMoreLikeThis2($id){
		global $configArray;
		global $solrScope;
		$originalResult = $this->getRecord($id,
			'target_audience_full,target_audience_full,literary_form,language_' . $solrScope);

		// Query String Parameters
		$options = [
			'q'                    => "id:$id",
			'rows'                 => 25,
			'fl'                   => 'id,title_display,title_full,author,author_display', // These appear to be the only fields used for displaying
			'fq'                   => [],
//			'mlt.interestingTerms' => 'details', // This returns the interesting terms for this 'more like this' search but isn't used any where
		];
		if ($originalResult){
			if (!empty($originalResult['target_audience_full'])){
				if (is_array($originalResult['target_audience_full'])){
					$filter = [];
					foreach ($originalResult['target_audience_full'] as $targetAudience){
						if ($targetAudience != 'Unknown'){
							$filter[] = 'target_audience_full:"' . $targetAudience . '"';
						}
					}
					if (count($filter) > 0){
						$options['fq'][] = '(' . implode(' OR ', $filter) . ')';
					}
				}else{
					$options['fq'][] = 'target_audience_full:"' . $originalResult['target_audience_full'] . '"';
				}
			}
			if (!empty($originalResult['literary_form'])){
				if (is_array($originalResult['literary_form'])){
					$filter = [];
					foreach ($originalResult['literary_form'] as $literaryForm){
						if ($literaryForm != 'Not Coded'){
							$filter[] = 'literary_form:"' . $literaryForm . '"';
						}
					}
					if (count($filter) > 0){
						$options['fq'][] = '(' . implode(' OR ', $filter) . ')';
					}
				}else{
					$options['fq'][] = 'literary_form:"' . $originalResult['literary_form'] . '"';
				}
			}
			if (!empty($originalResult['language_' . $solrScope])){
				$options['fq'][] = "language_$solrScope:\"" . $originalResult["language_$solrScope"][0] . '"';
			}
		}

		$scopingFilters = $this->getScopingFilters();
		foreach ($scopingFilters as $filter){
			$options['fq'][] = $filter;
		}
		if ($configArray['Index']['enableBoosting']){
			$options['bf'] = $this->getBoostingFormula();
		}

		return $this->indexEngine->callRequestHandler('morelikethis2', $options);
	}

	/**
	 * Get records similar to an array of records
	 * Uses Custom Solr MoreLikeThese Request Handler
	 *
	 * @access  public
	 * @return  array                      An array of query results
	 * @throws  object                     PEAR Error
	 * @var     string[] $ids              A list of ids to return data for
	 * @var     string[] $notInterestedIds A list of ids the user is not interested in
	 */
	function getMoreLikeThese($ids, $notInterestedIds){
		global $configArray;
		// Query String Parameters
		$idString = implode(' OR ', $ids);
		$options  = [
			'q'                    => "id:($idString)",
//			'qt'                   => 'morelikethese',
//			'mlt.interestingTerms' => 'details',
			'rows'                 => 30
		];

		if (!empty($notInterestedIds)){
			$notInterestedString = implode(' OR ', $notInterestedIds);
			$options['fq'][]     = "-id:($notInterestedString)";
		}

		$scopingFilters = $this->getScopingFilters();
		foreach ($scopingFilters as $filter){
			$options['fq'][] = $filter;
		}
		if ($configArray['Index']['enableBoosting']){
			$options['bf'] = $this->getBoostingFormula();
		}


		// TODO: Limit Fields
//		if ($this->debug && isset($fields)) {
//			$options['fl'] = $fields;
//		} else {
		// This should be an explicit list
		$options['fl'] = '*,score';
//		$options['fl'] = 'id,rating,title,author';
//		}
		return $this->indexEngine->callRequestHandler('morelikethese', $options);
	}


	/**
	 * Retrieves Solr Document for grouped Work Id
	 * @param string $id The groupedWork Id of the Solr document to retrieve
	 * @param null $fieldsToReturn The fields of the Solr document to incude
	 * @return array The Solr document of the grouped Work
	 */
	function getRecord($id, $fieldsToReturn = null){
		return $this->indexEngine->getRecord($id, $fieldsToReturn ?? $this->getFieldsToReturn());
	}

	/**
	 * Retrieves Solr Documents for an array of grouped Work Ids
	 * @param string[] $ids  The groupedWork Id of the Solr document to retrieve
	 * @return array The Solr document of the grouped Work
	 */
	function getRecords($ids){
		return $this->indexEngine->getRecords($ids, $this->getFieldsToReturn());
	}

	/**
	 * Retrieves Solr Documents for an array of grouped Work Ids
	 * @param string[] $ids  The groupedWork Id of the Solr document to retrieve
	 * @return array The Solr document of the grouped Work
	 */
	function getFilteredIds($ids){
		[$filterQuery] = $this->setFinalFilterQuery();
		return $this->indexEngine->getFilteredIds($ids, $filterQuery);
	}

	/**
	 * Retrieves a document specified by the item barcode.
	 *
	 * @param   string  $barcode    A barcode of an item in the document to retrieve from Solr
	 * @access  public
	 * @throws  object              PEAR Error
	 * @return  string              The requested resource
	 */
	function getRecordByBarcode($barcode){
		return $this->indexEngine->getRecordByBarcode($barcode, $this->getFieldsToReturn());
	}

	/**
	 * Retrieves a document specified by an isbn.
	 *
	 * @param   string[]  $isbn     An array of isbns to check
	 * @access  public
	 * @throws  object              PEAR Error
	 * @return  array|null              The requested resource
	 */
	function getRecordByIsbn($isbn, $fieldsToReturn = null){
		return $this->indexEngine->getRecordByIsbn($isbn, $fieldsToReturn ?? $this->getFieldsToReturn());
	}

	private function getFieldsToReturn(){
		if (isset($_REQUEST['allFields'])){
			$fieldsToReturn = $this->fieldsFull;
		}else{
			$fieldsToReturn = SearchObject_Solr::$fields;
			global $solrScope;
			if ($solrScope != false){
				$fieldsToReturn .= ',format_' . $solrScope;
				$fieldsToReturn .= ',format_category_' . $solrScope;
				$fieldsToReturn .= ',collection_' . $solrScope;
				$fieldsToReturn .= ',local_time_since_added_' . $solrScope;
				$fieldsToReturn .= ',local_callnumber_' . $solrScope;
				$fieldsToReturn .= ',detailed_location_' . $solrScope;
				$fieldsToReturn .= ',scoping_details_' . $solrScope;
				$fieldsToReturn .= ',owning_location_' . $solrScope;
				$fieldsToReturn .= ',owning_library_' . $solrScope;
				$fieldsToReturn .= ',available_at_' . $solrScope;
				$fieldsToReturn .= ',itype_' . $solrScope;

			}else{
				//TODO: this block is obsolete, since all these facets are scoped.  Likely would cause empty document returns
				// if no scope is set
				// Gets called by getRelatedPikaContent()
				global $pikaLogger;
				$pikaLogger->warning('Solr scope not set when fetching scoped fields.', [$_SERVER['REQUEST_URI'], $_REQUEST]);
				$fieldsToReturn .= ',format';
				$fieldsToReturn .= ',format_category';
				$fieldsToReturn .= ',days_since_added';
				$fieldsToReturn .= ',local_callnumber';
				$fieldsToReturn .= ',detailed_location';
				$fieldsToReturn .= ',owning_location';
				$fieldsToReturn .= ',owning_library';
				$fieldsToReturn .= ',available_at';
				$fieldsToReturn .= ',itype';
			}
			if ($this->debug){
				$fieldsToReturn .= ',score';
			}
		}
		return $fieldsToReturn;
	}

	public function setPrimarySearch($flag){
		parent::setPrimarySearch($flag);
		$this->indexEngine->isPrimarySearch = $flag;
	}

	public function __destruct(){
		if (isset($this->indexEngine)){
			$this->indexEngine = null;
			unset($this->indexEngine);
		}
	}

	public function pingServer($failOnError = true){
		return $this->indexEngine->pingServer($failOnError);
	}

	public function getNextPrevLinks(){
		global $interface;
		global $timer;
		//Setup next and previous links based on the search results.
		if (isset($_REQUEST['searchId']) && isset($_REQUEST['recordIndex']) && ctype_digit($_REQUEST['searchId']) && ctype_digit($_REQUEST['recordIndex'])){
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
					$previousSearchObject->processSearch(true);
					$previousResults = $previousSearchObject->getResultRecordSet();
				}elseif (($currentResultIndex + 1) % $recordsPerPage == 0 && ($currentResultIndex + 1) < $searchObject->getResultTotal()){
					//Need to run a search for the next page
					$nextSearchObject = clone $searchObject;
					$interface->assign('nextPage', $currentPage + 1);
					$nextSearchObject->setPage($currentPage + 1);
					$nextSearchObject->processSearch(true);
					$nextResults = $nextSearchObject->getResultRecordSet();
				}

				if (!PEAR_Singleton::isError($result) && $searchObject->getResultTotal() > 0){
					$recordSet = $searchObject->getResultRecordSet();
					//Record set is 0 based, but we are passed a 1 based index
					if ($currentResultIndex > 0){
						if (isset($previousResults)){
							$previousRecord = $previousResults[count($previousResults) -1];
						}else{
							$previousId = $adjustedResultIndex - 1;
							if (isset($recordSet[$previousId])){
								$previousRecord = $recordSet[$previousId];
							}
						}

						//Convert back to 1 based index
						if (isset($previousRecord)) {
							$interface->assign('previousIndex', $currentResultIndex - 1 + 1);
							if (!empty($previousRecord['title_display'])){
								$interface->assign('previousTitle', $previousRecord['title_display']);
							}
							if ($previousRecord['recordtype'] == 'grouped_work'){
								require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
								$groupedWork    = new GroupedWorkDriver($previousRecord);
								$relatedRecords = $groupedWork->getRelatedRecords(true);
								$timer->logTime('Loaded related records for previous result');
								if (count($relatedRecords) == 1){
									$previousRecord = reset($relatedRecords);
									[$previousType, $previousId] = explode('/', trim($previousRecord['url'], '/'));
									$interface->assign('previousId', $previousId);
									$interface->assign('previousType', $previousType);
								}else{
									$interface->assign('previousType', 'GroupedWork');
									$interface->assign('previousId', $previousRecord['id']);
								}
							}elseif ($previousRecord['recordtype'] == 'list'){
								$interface->assign('previousType', 'MyAccount/MyList');
								$interface->assign('previousId', str_replace('list', '', $previousRecord['id']));
							}
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
							if (!empty($nextRecord['title_display'])){
								$interface->assign('nextTitle', $nextRecord['title_display']);
							}
							if ($nextRecord['recordtype'] == 'grouped_work'){
								require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
								$groupedWork    = new GroupedWorkDriver($nextRecord);
								$relatedRecords = $groupedWork->getRelatedRecords(true);
								$timer->logTime('Loaded related records for next result');
								if (count($relatedRecords) == 1) {
									$nextRecord = reset($relatedRecords);
									[$nextType, $nextId] = explode('/', trim($nextRecord['url'], '/'));
									$interface->assign('nextId', $nextId);
									$interface->assign('nextType', $nextType);
								} else {
									$interface->assign('nextType', 'GroupedWork');
									$interface->assign('nextId', $nextRecord['id']);
								}
							}elseif ($nextRecord['recordtype'] == 'list'){
								$interface->assign('nextType', 'MyAccount/MyList');
								$interface->assign('nextId', str_replace('list', '', $nextRecord['id']));
							}
						}
					}
				}
			}
			$timer->logTime('Got next/previous links');
		}
	}
}
