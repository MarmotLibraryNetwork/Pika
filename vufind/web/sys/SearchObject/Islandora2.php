<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

//namespace SearchObject;

use ArchivePrivateCollection;
use minSO;
use PEAR_Singleton;
use Pika\Logger;
use RecordDriverFactory;
use Solr;

require_once ROOT_DIR . '/sys/SearchObject/Base.php';

class SearchObject_Islandora2 extends \SearchObject_Base {
	protected string $searchIni = 'Islandora2Searches';
	protected string $searchSource = 'islandora2';
	protected ?string $defaultIndex = 'Islandora2Keyword';

	// Publicly viewable version of the search query used
	private string|null $publicQuery = null;
	private $facetOffset = null;
	private $facetSort = null;
	protected $nidFacets = [];
	// Index
	private $index = null;
	// Field List
	private $fields = '*,score';
	//private $fields = 'its_node_id,twm_X3b_en_title_ws_token,twm_X3b_en_field_description_long_ws_token,sm_name_2,ss_name_1,ss_name_23,sm_field_edtf_date_created,ds_changed,score';
	//TODO: modified date field
	//TODO: which created date field should we use?

	//its_edtf_year,sm_field_display_title

	//TODO: construct good default list
	//private $fields = 'PID,fgs_label_s,dc.title,mods_abstract_s,mods_genre_s,RELS_EXT_hasModel_uri_s,dateCreated,score,fgs_createdDate_dt,fgs_lastModifiedDate_dt';

	// Solr field Map
	private $solrFields = [
		'id'          => 'its_node_id',
		'title'       => 'tm_X3b_en_title', //test
//		'title'       => 'twm_X3b_en_title_ws_token', // production
		'description' => 'tm_X3b_en_field_description_long',
		'memberOf'    => 'itm_field_member_of', //node ids
		'legacyPID'   => 'tm_X3b_en_field_pid',
		'genre'       => 'sm_name_2',
		'model'       => 'ss_name_1',
		'legacyResourceType' => 'sm_name_22',
		'format'      => 'sm_name_43',
		'library'     => 'ss_name_23',
//		'rightsCreator' => 'tm_X3b_en_name_41',
	];

	// HTTP Method
	private $method = 'GET';

	//Whether default archive filters should be applied
	private $applyStandardFilters = true;

	// OTHER VARIABLES
	const string IDFIELD = 'its_node_id';

	// Display Modes //
	public $viewOptions = ['list'/*, 'covers'*/];
	private Logger $logger;

	public function __construct(){
		// Call base class constructor
		parent::__construct();

		global $configArray;
		//global $timer;
		// Include our solr index
		$this->searchType      = 'islandora2';
		$this->basicSearchType = 'islandora2';
		$this->indexEngine     = new Solr($configArray['Islandora2']['solrUrl'], $configArray['Islandora2']['solrCore'] ?? 'ISLANDORA');
		//$timer->logTime('Created Index Engine for Islandora2');

		// Get default facet settings
		$this->allFacetSettings = getExtraConfigArray('islandora2Facets');
		$this->facetConfig      = [];

		$this->initFacetLimit();

		$translatedFacets = $this->getFacetSetting('Advanced_Settings', 'translated_facets');
		if (is_array($translatedFacets)){
			$this->translatedFacets = $translatedFacets;
		}
		$nidFacets = $this->getFacetSetting('Advanced_Settings', 'nid_facets');
		if (is_array($nidFacets)){
			$this->nidFacets = $nidFacets;
		}

		// Load search preferences
		$searchSettings = getExtraConfigArray($this->searchIni);
		$this->defaultIndex = 'Islandora2Keyword'; //TODO: isn't needed now (set in definition above)
		if (isset($searchSettings['General']['default_sort'])){
			$this->defaultSort = $searchSettings['General']['default_sort'];
		}
		if (isset($searchSettings['DefaultSortingByType']) &&
			is_array($searchSettings['DefaultSortingByType'])){
			$this->defaultSortByType = $searchSettings['DefaultSortingByType'];
		}
		//TODO:  Implement a basic keyword and title search type
		if (isset($searchSettings['Basic_Searches'])){
			$this->basicTypes = $searchSettings['Basic_Searches'];
		}
//		if (isset($searchSettings['Advanced_Searches'])){
//			$this->advancedSearchTypes = $searchSettings['Advanced_Searches'];
//		}

		// Load sort preferences (or defaults if none in .ini file):
		if (isset($searchSettings['Sorting'])){
			$this->sortOptions = $searchSettings['Sorting'];
		}else{
			//TODO: genuine defaults?
			$this->sortOptions = [
				'relevance' => 'sort_relevance',
//				'year'      => 'sort_year',
//				'year asc'  => 'sort_year asc',
				'title'     => 'sort_title'
			];
		}

		// Load Spelling preferences
		$this->spellcheck = false; //There is no dictionary on the index
//		$this->spellcheck       = $configArray['Spelling']['enabled'];
//		$this->spellingLimit    = $configArray['Spelling']['limit'];
//		$this->spellSimple      = $configArray['Spelling']['simple'];
//		$this->spellSkipNumeric = $configArray['Spelling']['skip_numeric'] ?? true;

		// Alternate Recommendations added to a search page
		// (Only current example is Similar Authors on catalog Author search)
		$this->recommendIni = $this->searchIni;

		// Debugging
		$this->indexEngine->debug           = $this->debug;
		$this->indexEngine->debugSolrQuery  = $this->debugSolrQuery;

		$this->indexEngine->isPrimarySearch = $this->isPrimarySearch;

		$this->resultsModule = 'Archive2';
		$this->resultsAction = 'Results';
		$this->searchSource  = 'islandora2'; //TODO: shouldn't be needed

		//$timer->logTime('Setup Islandora2 Solr Search Object');
	}

	/**
	 * Initialize the object from the global
	 *  search parameters in $_REQUEST.
	 *
	 * @access  public
	 * @return  boolean
	 */
	public function init($searchSource = null){
		// Call the standard initialization routine in the parent:
		parent::init($searchSource);

		//********************
		// Check if we have a saved search to restore -- if restored successfully,
		// our work here is done; if there is an error, we should report failure;
		// if restoreSavedSearch returns false, we should proceed as normal.
		$restored = $this->restoreSavedSearch();
		if ($restored === true){
			return true;
		}else{
			if (PEAR_Singleton::isError($restored)){
				return false;
			}
		}

		//********************
		// Initialize standard search parameters
		$this->initView();
		$this->initPage();
		$this->initSort();
		$this->initFilters();

		//********************
		// Basic Search logic
		// Map islandoraType to type so initBasicSearch() picks up the correct search index
		if (!empty($_REQUEST['islandoraType']) && empty($_REQUEST['type'])){
			$_REQUEST['type'] = $_REQUEST['islandoraType'];
		}
		if (!$this->initBasicSearch()){
			$this->initAdvancedSearch();
		}

		// If a query override has been specified, log it here
		//TODO: explain override scenarios here
		if (isset($_REQUEST['q'])){
			$this->query = $_REQUEST['q'];
		}

		return true;
	} // End init()

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
	 * Creates hiddenFilters & facetConfig in addition to default object building
	 * TODO: Is there any way an Islandora2 saved search will be structured differently
	 * than Islandora1
	 *
	 * @param $minified minSO Saved Search Object
	 * @return void
	 */
	public function deminify(minSO $minified){
		// Clean the object
		$this->purge();

		// Most values will transfer without changes
		$this->searchId     = $minified->id;
		$this->initTime     = $minified->i;
		$this->queryTime    = $minified->s;
		$this->resultsTotal = $minified->r;
		$this->filterList   = $minified->f;
		$this->searchType   = $minified->ty;
		$this->sort         = $minified->sr;
		$this->hiddenFilters= $minified->hf;
		$this->facetConfig  = $minified->fc;

		// Search terms, we need to expand keys
		$tempTerms = $minified->t;
		foreach ($tempTerms as $term) {
			$newTerm = [];
			foreach ($term as $k => $v) {
				switch ($k) {
					case 'j' :  $newTerm['join']    = $v; break;
					case 'i' :  $newTerm['index']   = $v; break;
					case 'l' :  $newTerm['lookfor'] = $v; break;
					case 'g' :
						$newTerm['group'] = array();
						foreach ($v as $line) {
							$search = array();
							foreach ($line as $k2 => $v2) {
								switch ($k2) {
									case 'b' :  $search['bool']    = $v2; break;
									case 'f' :  $search['field']   = $v2; break;
									case 'l' :  $search['lookfor'] = $v2; break;
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
	 * Wrapper method to retrieve logger.
	 * This way the logger is only set if needed
	 * @return Logger
	 */
	function getLogger(){
		if (!isset($this->logger)){
			$this->logger = new Logger(__CLASS__);
		}
		return $this->logger;
	}

	/**
	 * @inheritDoc
	 */

	/**
	 * Turn the list of spelling suggestions into an array of urls
	 *   for on-screen use to implement the suggestions.
	 *
	 * @access  public
	 * @return  array     Spelling suggestion data arrays
	 */
	public function getSpellingSuggestions(): array{
		if (empty($this->suggestions)){
			return [];
		}
		$tokens      = $this->spellingTokens($this->buildSpellingQuery());
		$returnArray = [];
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
			if ($targetTerm == ''){
				$targetTerm  = $term;
				$returnArray = $this->doSpellingReplace($term,
					$targetTerm, $inToken, $details, $returnArray);
			}
		}
		return $returnArray;
	}

	/**
	 * Process spelling suggestions from the results object and populate
	 * $this->suggestions
	 *
	 * @access  private
	 * @return void
	 */
	private function processSpelling(): void{
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

			$ourHit  = $suggestion[1]['origFreq']; // int
			$count   = $suggestion[1]['numFound']; // int
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
				// Did we get more suggestions than our limit?
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
	private function filterSpellingTerms(array $termList) :array{
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
	 * Switch the spelling dictionary to basic
	 *
	 * @access  public
	 */
	public function useBasicDictionary() {
		$this->dictionary = 'basicSpell';
	}

	/**
	 * Get an error message from index response, if any.  This will only work if
	 * processSearch was called with $returnIndexErrors set to true!
	 *
	 * @access  public
	 * @return  mixed       false if no error, error string otherwise.
	 */
	public function getIndexError(){
		//TODO: further refine error return
		//probably return $this->indexResult['error']['msg']
		return $this->indexResult['error'] ?? false;
	}

	/**
	 * @inheritDoc
	 */
	public function processSearch($returnIndexErrors = false, $recommendations = false, $preventQueryModification = false){
		// Our search has already been processed in init()
		$search = $this->searchTerms;

		// Build a recommendations module appropriate to the current search:
		if ($recommendations){
			$this->initRecommendations();
		}

		// Build Query
		if ($preventQueryModification){
			$query = $search;
			//TODO Islandora1 version uses: $query = $search[0]['lookfor'];
		}else{
			$query = $this->indexEngine->buildQuery($search, false);
		}
		if (PEAR_Singleton::isError($query)){
			return $query;
		}

		// Only use the query we just built if there isn't an override in place.
		if ($this->query == null) {
			$this->query = $query;
		}

		// Filter Query
		$filterQuery = $this->setFinalFilterQuery();

		// Set the searchType for this search when we have a basic search
		if (count($search) == 1 && isset($search[0]['index'])) {
			$this->index = $search[0]['index'];

			//TODO: previous comments that don't seem to encompass all uses correctly :
			// If we are only searching one field, use the DisMax handler for that field.
			// If left with a null value, let solr take care of it
			// TODO: does solr actually take care of it? what use cases? does it apply to islandora2 searches?
		}

		// Build a list of facets we want from the index
		$facetSet = [];
		if (!empty($this->facetConfig)){
			foreach ($this->facetConfig as $facetField => $facetName){
				$facetSet['field'][] = $facetField;
			}
			if ($this->facetOffset != null){
				$facetSet['offset'] = $this->facetOffset;
			}
			if ($this->facetLimit != null) {
				$facetSet['limit'] = $this->facetLimit;
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
		} else {
			$spellcheck = '';
		}

		// The "relevance" sort option is a Pika reserved word; we need to make
		// this null in order to achieve the desired effect with Solr:
		$finalSort = ($this->sort == 'relevance') ? null : $this->sort;

		// The first record to retrieve:
		//  (page - 1) * limit = start
		$recordStart = ($this->page - 1) * $this->limit;
		$pingResult  = $this->indexEngine->pingServer(false);
		if (!$pingResult){
			PEAR_Singleton::raiseError('The archive server is currently unavailable.  Please try your search again in a few minutes.');
		}

		// Get time before the query
		$this->startQueryTimer();

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
		$this->resultsTotal = $this->indexResult['response']['numFound'] ?? 0;

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
						// the solr document Id for islandora2 is different from what we use
						// but use it here to move the Solr explaination
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
	 * Taken from the Islandora1 class. It contains Collection/Exhibit handling that
	 * we ultimately abandoned.  So may be able to simplify to the traditional handling
	 * found in the other versions of the Search Object.
	 *
	 * TODO: Disable initially
	 * Current Archive search doesn't appear to use this.
	 * @param $searchId
	 * @param $recordIndex
	 * @param $page
	 * @param $preventQueryModification
	 * @return void
	 */
	public function getNextPrevLinks($searchId=null, $recordIndex=null, $page=null, $preventQueryModification = false){
		global $interface;
		//global $timer;
		//Setup next and previous links based on the search results.
		if (is_null($searchId)) {
			if (!empty($_REQUEST['searchId']) && ctype_digit($_REQUEST['searchId'])) {
				$searchId = $_REQUEST['searchId'];
			}
		}
		if (is_null($recordIndex)) {
			if (!empty($_REQUEST['recordIndex']) && ctype_digit($_REQUEST['recordIndex'])) {
				$recordIndex = $_REQUEST['recordIndex'];
			} else {
				$recordIndex = 1;
			}
		}
		if ($searchId) {
			require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
			$s = new SearchEntry();
			if ($s->get($searchId)){
				//rerun the search
				$interface->assign('searchId', $searchId);
				if (is_null($page)) {
					$page = !empty($_REQUEST['page']) && ctype_digit($_REQUEST['page']) ? $_REQUEST['page'] : 1;
				}
				$interface->assign('page', $page);

				/** @var SearchObject_Islandora2 $searchObject */
				$minSO        = unserialize($s->search_object);
				$searchObject = SearchObjectFactory::deminify($minSO);
				$searchObject->setPage($page);
				$searchObject->setLimit(24); // Assume 24 for Archive Searches; or // TODO: Add pagelimit to saved search?
				//Run the search
				$result = $searchObject->processSearch(true, false, $preventQueryModification); // prevent query modification needed for Map Exhibits

				//Check to see if we need to run a search for the next or previous page
				$currentResultIndex  = $recordIndex - 1;
				$recordsPerPage      = $searchObject->getLimit();
				$adjustedResultIndex = $currentResultIndex - ($recordsPerPage * ($page - 1));

				if (($currentResultIndex) % $recordsPerPage == 0 && $currentResultIndex > 0){
					//Need to run a search for the previous page
					$interface->assign('previousPage', $page - 1);
					$previousSearchObject = clone $searchObject;
					$previousSearchObject->setPage($page - 1);
					$previousSearchObject->processSearch(true, false, $preventQueryModification);
					$previousResults = $previousSearchObject->getResultRecordSet();
				}else if (($currentResultIndex + 1) % $recordsPerPage == 0 && ($currentResultIndex + 1) < $searchObject->getResultTotal()){
					//Need to run a search for the next page
					$nextSearchObject = clone $searchObject;
					$interface->assign('nextPage', $page + 1);
					$nextSearchObject->setPage($page + 1);
					$nextSearchObject->processSearch(true, false, $preventQueryModification);
					$nextResults = $nextSearchObject->getResultRecordSet();
				}

				if (!PEAR_Singleton::isError($result)) {
					if ($searchObject->getResultTotal() > 0) {
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
								if (key_exists(self::IDFIELD, $previousRecord)) {
									$interface->assign('previousType', $this->resultsModule);
									$interface->assign('previousUrl', $previousRecord['url']);
									$interface->assign('previousTitle', $previousRecord['fgs_label_s']);
									//TODO:  update title solr field
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
							if (isset($nextRecord)) {
								if (key_exists('PID', $nextRecord)) {
									$interface->assign('nextType', 'Archive');
									$interface->assign('nextUrl', $nextRecord['url']);
									$interface->assign('nextTitle', $nextRecord['fgs_label_s']);
									//TODO:  update title solr field
								}
							}
						}

					}
				}
			}
			//$timer->logTime('Got next/previous links');
		}
	}

	/**
	 * Initialize the object for retrieving advanced
	 *   search screen facet data from inside solr.
	 *
	 * @access  public
	 * @return  boolean
	 */
	public function initAdvancedFacets(){
		return false;
	}

	/**
	 * Takes the full Solr URL from the search engine. Used for debugging info
	 * when we are displaying a Solr Error
	 * @return string
	 */
	public function getFullSearchUrl(){
		return $this->indexEngine->fullSearchUrl ?? 'Unknown';
	}

	/**
	 * Return the field (index) searched by a basic search
	 *
	 * @access  public
	 * @return  string   The searched index
	 */
	public function getSearchIndex(){
		// Use the normal parent method for non-advanced searches.
		return $this->searchType == $this->basicSearchType ? parent::getSearchIndex() : null;
	}

	/**
	 * USER LIST displays
	 *
	 * Use the record driver to build an array of HTML displays from the search
	 * results suitable for use on a user list/user's "favorites" page.
	 *
	 * @access  public
	 * @param   int   $listId           ID of list containing desired tags/notes (or
	 *                                   null to show tags/notes from all user's lists).
	 * @param   bool  $allowEdit        Should we display edit controls?
	 * @param   array $IDList           Optional list of IDs to re-order the archive Objects by (ie User List sorts)
	 * @param   bool  $isMixedUserList  Used to correctly number items in a list of mixed content (e.g. catalog & archive content)
	 * @return array Array of HTML chunks for individual records.
	 */
	public function getResultListHTML($listId = null, $allowEdit = true, $IDList = null, $isMixedUserList = false) : array{
		global $interface;
		$html = [];

		if ($IDList){
			//Reorder the documents based on the list of id's
			$i = 0;
			foreach ($IDList as $listPosition => $currentId){
				// use $IDList as the order guide for the HTML
				$current = null; // empty out in case we don't find the matching record
				foreach ($this->indexResult['response']['docs'] as $index => $doc) {
					if (!empty($doc[self::IDFIELD]) && $doc[self::IDFIELD] == $currentId) {
						$current = & $this->indexResult['response']['docs'][$index];
						break;
					}
				}
				if (empty($current)){
					continue; // In the case the record wasn't found, move on to the next record
				}else{
					if ($isMixedUserList){
						$interface->assign('recordIndex', $listPosition + 1);
						$interface->assign('resultIndex', $listPosition + 1 + (($this->page - 1) * $this->limit));
					}else{
						$interface->assign('recordIndex', $i + 1);
						$interface->assign('resultIndex', $i + 1 + (($this->page - 1) * $this->limit));
					}
					if (!$this->debug){
						unset($current['explain']);
						unset($current['score']);
					}
					/** @var Islandora2Driver $record */
					$record = RecordDriverFactory::initRecordDriver($current);
					//TODO:
					if ($isMixedUserList){
						$html[$listPosition] = $interface->fetch($record->getListEntry($listId, $allowEdit));
					}else{
						$html[] = $interface->fetch($record->getListEntry($listId, $allowEdit));
						$i++;
					}
				}
			}
		}else{
			for ($x = 0;$x < count($this->indexResult['response']['docs']);$x++){
				$interface->assign('recordIndex', $x + 1);
				$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
				$current = &$this->indexResult['response']['docs'][$x];
				$record  = RecordDriverFactory::initRecordDriver($current);
				$html[]  = $interface->fetch($record->getListEntry($listId, $allowEdit));
			}
		}
		return $html;
	}

	/*
 * Get an array of citations for the records within the search results
 */
	public function getCitations($citationFormat){
		global $interface;
		$html = [];
		for ($x = 0; $x < count($this->indexResult['response']['docs']); $x++) {
			$current = & $this->indexResult['response']['docs'][$x];
			$interface->assign('recordIndex', $x + 1);
			$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));
			$record = RecordDriverFactory::initRecordDriver($current);
			$html[] = $interface->fetch($record->getCitation($citationFormat));
		}
		return $html;
	}

	/**
	 * Get the solr document for use in User Lists and Previous/Next Links
	 *
	 * @access  public
	 * @return  array   Return the record set from the search results.
	 */
	public function getResultRecordSet(){
		$recordSet = $this->indexResult['response']['docs'];
		foreach ($recordSet as $key => $solrDocument){
			// Include Additional Information for Emailing a list of Archive (islandora1) Objects
			$recordDriver           = RecordDriverFactory::initRecordDriver($solrDocument);
			$solrDocument['url']    = $recordDriver->getLinkUrl();
			$solrDocument['format'] = $recordDriver->getFormat();
			$recordSet[$key]        = $solrDocument;
		}
		return $recordSet;
	}

	/**
	 * @param array $orderedListOfIDs  Use the index of the matched ID
	 *               as the index of the resulting array of ListWidget data
	 *               (for later merging)
	 * @return array
	 */
	public function getListWidgetTitles($orderedListOfIDs = []){
		$widgetTitles = [];
			foreach ($this->indexResult['response']['docs'] as $solrDoc){
			$archiveObjectDriver  = RecordDriverFactory::initRecordDriver($solrDoc);
			if (!PEAR_Singleton::isError($archiveObjectDriver)){
				if (method_exists($archiveObjectDriver, 'getListWidgetTitle')){
					if (!empty($orderedListOfIDs)){
						$position = array_search($solrDoc[self::IDFIELD], $orderedListOfIDs);
						if ($position !== false){
							$widgetTitles[$position] = $archiveObjectDriver->getListWidgetTitle();
						}
					}else{
						$widgetTitles[] = $archiveObjectDriver->getListWidgetTitle();
					}
				}else{
					$widgetTitles[] = 'List Widget Item not available';
				}
			}else{
				$widgetTitles[] = 'Unable to find record';
			}
		}
		return $widgetTitles;
	}

	/**
	 * Use the record driver to build an array of HTML displays from the search
	 * results for conventional Islandora2 archive search results.
	 *
	 * @access  public
	 * @return  array   Array of HTML chunks for individual records.
	 */
	public function getResultRecordHTML(): array{
		return $this->getResultHTML();
	}

	/**
	 * Use the record driver to build an array of HTML displays from the search
	 * results for use combined search results
	 *
	 * @access  public
	 * @return  array   Array of HTML chunks for individual records.
	 */
	public function getCombinedResultHTML(): array{
		return $this->getResultHTML(true);
	}

	/**
	 * Extract solr field value using an easier to understand key
	 * @param array $solrDoc
	 * @param string $field
	 * @return mixed
	 */
	private function getSolrFieldValue(array $solrDoc, string $field){
		return $solrDoc[$this->solrFields[$field]];
	}
	private function getResultHTML($getCombinedResult = false):array{
		global $interface;
		$html = [];
		foreach ($this->indexResult['response']['docs'] as $x => $doc){
			$interface->assign('recordIndex', $x + 1);
			$interface->assign('resultIndex', $x + 1 + (($this->page - 1) * $this->limit));

			//Temp process with most details
			global $interface;
			// Prelim Code using Solr Data only
//			$obj    = RecordDriverFactory::initRecordDriver($doc);
//			$nodeId = $this->getSolrFieldValue($doc, 'id');
//			$titles = $this->getSolrFieldValue($doc, 'title');
//			$title  = is_array($titles) ? $titles[0] : $titles;
//			$interface->assign('summId', $nodeId);
//			$interface->assign('summTitle', $title);
//			$interface->assign('jquerySafeId', $nodeId);
//			$interface->assign('summUrl', $obj->getLinkUrl());
//			$interface->assign('summDescription', $this->getSolrFieldValue($doc, 'description')[0]);
//			$interface->assign('summModel', $this->getSolrFieldValue($doc, 'model'));
//			$interface->assign('summFormat', $this->getSolrFieldValue($doc, 'format')[0]);
//			$interface->assign('bookCoverUrl', $obj->getBookcoverUrl('small'));
//			$interface->assign('bookCoverUrlMedium', $obj->getBookcoverUrl('medium'));
//			$interface->assign('summScore', $doc['score']);
//			$interface->assign('summExplain', $doc['explain']);
//			$html[]  = $interface->fetch('RecordDrivers/Islandora/result.tpl');

			$record = RecordDriverFactory::initRecordDriver($doc);
			if (!PEAR_Singleton::isError($record)){
				$interface->assign('recordDriver', $record);
				$html[] = $interface->fetch($getCombinedResult ? $record->getCombinedResult($this->view) : $record->getSearchResult($this->view));
			}else{
				$html[] = 'Unable to find record';
			}
		}
		return $html;
	}

	/**
	 * Set an overriding array of archive Node Ids.
	 *
	 * @access  public
	 * @param   array  $ids archive Node IDs to load
	 */
	public function setQueryIDs($ids){
		$this->query = self::IDFIELD . ':(' . implode(' ', $ids) . ')';
		// The default q.op for Islandora2 is OR so we can just use a space instead of adding an explicit OR
	}


	/**
	 * Set the facet sort order.  Determines the order for display of the facet values
	 * returned by a search.
	 *
	 * 'count' = Return the terms sorted by highest count first.
	 * 'index' = Return the terms sorted lexicographically.
	 * default is count if facet.limit > 0; otherwise default is index
	 * https://solr.apache.org/guide/solr/latest/query-guide/faceting.html
 * @param $facetSort
	 * @return void
	 */
	public function setFacetSortOrder($facetSort){
		if ($facetSort == 'count' || $facetSort == 'index'){
			$this->facetSort = $facetSort;
		}
	}

	/**
	 * Build a string for onscreen display showing the
	 *   query used in the search (not the filters).
	 *
	 * @access  public
	 * @return string|null user-friendly version of 'query'
	 */
	public function displayQuery(): ?string{
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

	public function getFilterList($excludeCheckboxFilters = false) :array{
		//TODO: special handling for archive facets (node id; translated facets)
		return parent::getFilterList($excludeCheckboxFilters);
	}

	/**
	 * Process facets from the results object
	 *
	 * @access  public
	 * @param array|null $filter         Array of field => on-screen description
	 *                                  listing all the desired facet fields;
	 *                                  set to null to get all configured values.
	 * @param   bool    $expandingLinks If true, we will include expanding URLs
	 *                                  (i.e. get all matches for a facet, not
	 *                                  just a limit to the doc search) in
	 *                                  the return array.
	 * @return  array   Facets data arrays
	 */
	public function getFacetList(array|null $filter = null, bool $expandingLinks = false) :array{
		$list = [];

		// If we have no facets to process, give up now
		if (!isset($this->indexResult['facet_counts']) || (!is_array($this->indexResult['facet_counts']['facet_fields']) && !is_array($this->indexResult['facet_counts']['facet_dates']))) {
			return $list;
		}

		if (is_array($filter) && empty($filter)){
			//TopFacets sends us an empty array; let's return an empty array back and avoid processing
			return $list;
		}
		$filter ??= $this->facetConfig;
		//TODO: explain when $filter is not passed
//		if (is_null($filter)) {
//			// If there is no filter, we'll use all facets as the filter:
//			$filter = $this->facetConfig;
//		}


		$validFacets = array_keys($filter);

		// merge date and regular facets
		if (is_array($this->indexResult['facet_counts']['facet_dates'])){
			$allFacets = array_merge($this->indexResult['facet_counts']['facet_fields'], $this->indexResult['facet_counts']['facet_dates']);
		} else {
			$allFacets = $this->indexResult['facet_counts']['facet_fields'];
		}

		// Loop through every facet returned by the results
		foreach ($allFacets as $facetName => $facetValues) {
			if (empty($facetValues) || !in_array($facetName, $validFacets)) {
				// Skip empty facet values or facets not in our list
				continue;
			}


			$list[$facetName]                 = [];                  // Initialize doc facet
			$list[$facetName]['label']        = $filter[$facetName]; // Set label
			$list[$facetName]['valuesToShow'] = parent::NUM_FACET_VALUES_TO_SHOW;
			$list[$facetName]['list']         = [];                  //Initialize facet values

			$isTranslationFacet = in_array($facetName, $this->translatedFacets);
			$isNIDFacet         = in_array($facetName, $this->nidFacets);

			foreach ($facetValues as $facetValue){
				$currentFacetValueSettings          = [];
				$value                              = $facetValue[0];
				$currentFacetValueSettings['value'] = $value;
				//TODO: is collection facet check
				if ($isNIDFacet){
					//TODO: NID facet handling

				} elseif ($isTranslationFacet){
					//TODO: Contributing Library Look up
					$currentFacetValueSettings['display'] = translate($value);
				} else {
					$currentFacetValueSettings['display'] = $value;
				}
				$currentFacetValueSettings['count']     = $facetValue[1];
				$currentFacetValueSettings['isApplied'] = false;
				$currentFacetValueSettings['url']       = $this->renderLinkWithFilter("$facetName:$value");
				if ($expandingLinks){
					// If we want to have expanding links (all values matching the facet)
					// in addition to limiting links (filter doc search with facet),
					// do some extra work:
					$currentFacetValueSettings['expandUrl'] = $this->getExpandingFacetLink($facetName, $value);
				}

				// Is this facet a currently applied filter?
				if (in_array($facetName, array_keys($this->filterList))) {
					// Is the doc facet value the selected value of the applied filter?
					if (in_array($value, $this->filterList[$facetName])) {
						$currentFacetValueSettings['isApplied']  = true;
						$currentFacetValueSettings['removalUrl'] = $this->renderLinkWithoutFilter("$facetName:$value");
					}
				}
				$list[$facetName]['list'][$value] = $currentFacetValueSettings;

				// Other version populate a 'ShowAlphabetically' field, that nearly never used
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
	 * @param string|false $preferredSection Section to favor when loading
	 *                                              settings; if multiple sections
	 *                                              contain the same facet, this
	 *                                              section's description will be
	 *                                              favored.
	 */
	public function activateAllFacets(string|false $preferredSection = false){
		foreach ($this->allFacetSettings as $section => $values){
			foreach ($values as $key => $value){
				$this->addFacet($key, $value);
			}
		}

		if ($preferredSection &&
			is_array($this->allFacetSettings[$preferredSection])){
			foreach ($this->allFacetSettings[$preferredSection] as $key => $value){
				$this->addFacet($key, $value);
			}
		}
	}

	/**
	 * Filter Collections in Facet lists
	 *
	 * @param $nid
	 * @return void
	 */
	private function showCollectionAsFacet($nid){
		$okToShow = true;
		global /** @var \Library $library */ $library;
		if ($library->hideAllCollectionsFromOtherLibraries && $library->archiveNamespace){
			//TODO: method to get namespace; then check nid namespace
			//$okToShow = ($namespace == $library->archiveNamespace);
		} elseif (!empty($library->collectionsToHide)){
			$collections = array_map('trim', explode("\n\r", $library->collectionsToHide));
			$okToShow = !in_array($nid, $collections);
		}
		if ($okToShow){
			//TODO: check if object for $nid is Valid for Pika based on "includeInPika" setting
		}
		return $okToShow;
	}


		/**
	 * Turn our results into an RSS feed
	 *
	 * @access  public
	 * @return  string                  XML document
	 */
	public function buildRSS():string{
		// XML HTTP header
		header('Content-type: text/xml', true);

		$this->limit = 50;
		$result      = $this->processSearch(false, false);
		foreach ($result['response']['docs'] as &$currentDoc){
			$record = RecordDriverFactory::initRecordDriver($currentDoc);
			if (!PEAR_Singleton::isError($record)){
				$currentDoc['recordUrl']       = $record->getAbsoluteUrl();
				$currentDoc['title_display']   = $record->getTitle();
				$image                         = $record->getBookcoverUrl('medium');
				$description                   = "<img src='$image'/> " . $record->getDescription();
				$currentDoc['rss_description'] = $description;
				$currentDoc['rss_date']        = date('r', strtotime($currentDoc['fgs_createdDate_dt']));
			}else{
				unset($currentDoc);
			}
		}

		global $interface;
		global $library;
		global $configArray;
		$baseUrl  = empty($library->catalogUrl) ? $configArray['Site']['url'] : $_SERVER['REQUEST_SCHEME'] . '://' . $library->catalogUrl;


		// On-screen display value for our search
		$lookfor = $this->displayQuery();
		if (!empty($this->filterList)){
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
	 * @return  void                  Excel document
	 */
	public function buildExcel($result = null){
		//TODO: this wouldn't work for archive searches

//		// First, get the search results if none were provided
//		// (we'll go for 50 at a time)
//		if (is_null($result)) {
//			$this->limit = 2000;
//			$result = $this->processSearch(false, false);
//		}
//
//		// Prepare the spreadsheet
//		$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
//		$objPHPExcel->getProperties()->setTitle("Search Results");
//
//		$objPHPExcel->setActiveSheetIndex(0);
//		$objPHPExcel->getActiveSheet()->setTitle('Results');
//
//		//Add headers to the table
//		$sheet = $objPHPExcel->getActiveSheet();
//		$curRow = 1;
//		$curCol = 1;
//		$sheet->setCellValue([$curCol++, $curRow], 'First Name');
//		$sheet->setCellValue([$curCol++, $curRow], 'Last Name');
//		$sheet->setCellValue([$curCol++, $curRow], 'Birth Date');
//		$sheet->setCellValue([$curCol++, $curRow], 'Death Date');
//		$sheet->setCellValue([$curCol++, $curRow], 'Veteran Of');
//		$sheet->setCellValue([$curCol++, $curRow], 'Cemetery');
//		$sheet->setCellValue([$curCol++, $curRow], 'Addition');
//		$sheet->setCellValue([$curCol++, $curRow], 'Block');
//		$sheet->setCellValue([$curCol++, $curRow], 'Lot');
//		$sheet->setCellValue([$curCol++, $curRow], 'Grave');
//		$maxColumn = $curCol -1;
//
//        $_count = count($result['response']['docs']);
//		for ($i = 0; $i < $_count; $i++) {
//			$curDoc = $result['response']['docs'][$i];
//			$curRow++;
//			$curCol = 1;
//			//TODO: Need to export information to Excel
//		}
//
//		for ($i = 0; $i < $maxColumn; $i++){
//			$sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
//		}
//
//		//Output to the browser
//		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
//		header("Cache-Control: no-store, no-cache, must-revalidate");
//		header("Cache-Control: post-check=0, pre-check=0", false);
//		header("Pragma: no-cache");
//		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
//		header('Content-Disposition: attachment;filename="Results.xlsx"');
//
//		$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPHPExcel, 'Xlsx');
//		$objWriter->save('php://output'); //THIS DOES NOT WORK WHY?
//		$objPHPExcel->disconnectWorksheets();
//		unset($objPHPExcel);
	}


	/**
	 * Retrieve Solr documents for an array of legacy Islandora 1 PIDs.
	 *
	 * Queries against the PID field (e.g. 'namespace:id' format) with the current
	 * standard filters applied. Use for lookups by legacy PID, not Islandora2 node IDs.
	 *
	 * @param string[] $pids  Legacy Islandora 1 PIDs to retrieve (e.g. 'fortlewis:12699')
	 * @return array          Array of matching Solr document arrays
	 */
	function getLegacyFilteredPIDs($pids):array{
		$filterQuery = $this->setFinalFilterQuery();
		return $this->indexEngine->getFilteredPIDs($pids, $filterQuery);
	}

	/**
	 * Retrieve full Solr documents for an array of Islandora2 node IDs, with standard filters applied.
	 *
	 * Delegates to {@see \Solr::getIslandora2NodeIds()} which queries the its_node_id
	 * field via /select. Standard search filters (library visibility, private collections, etc.)
	 * are applied via {@see setFinalFilterQuery()}.
	 *
	 * @param int[]|string[] $nids  Islandora2 node IDs to retrieve
	 * @return array                Array of matching Solr document arrays
	 */
	function getFilteredNodeIds($nids):array{
		$filterQuery = $this->setFinalFilterQuery();
		return $this->indexEngine->getIslandora2NodeIds($nids, $filterQuery);
	}

	/**
	 * Retrieve a single Solr document by Islandora2 node ID.
	 *
	 * Uses {@see \Solr::getIslandora2NodeIds()} which queries against the its_node_id
	 * field via /select — the /get endpoint cannot be used because Islandora2 node IDs
	 * are not stored in the Solr 'ids' index.
	 *
	 * @param int|string $nid  The Islandora2 node ID
	 * @return array           Array of matching Solr document arrays (normally one entry)
	 */
	function getRecord($nid):array{
		return $this->indexEngine->getIslandora2NodeIds([$nid]);
	}


	protected $params;
	/**
	 * TODO: do we need a different islandoraType?
	 *
	 * Get an array of strings to attach to a base URL in order to reproduce the
	 * doc search.
	 *
	 * @access  protected
	 * @return  array    Array of URL parameters (key=url_encoded_value format)
	 */
	protected function getSearchParams(){
		if (is_null($this->params)) {
			$params = [];
			switch ($this->searchType) {
				case 'islandora2':
				case 'islandora' :
				default :
					$params = parent::getSearchParams();
					if (isset($_REQUEST['islandoraType'])) {
						//TODO: validate the parameter
						$params[] = 'islandoraType=' . $_REQUEST['islandoraType'];
					} else {
						$params[] = 'islandoraType=' . $this->defaultIndex;
					}
					break;
				case 'list' :
				case 'favorites':
					$preserveParams = [
						// for favorites/list:
						'tag', 'pagesize'
					];
					foreach ($preserveParams as $current) {
						if (isset($_GET[$current])) {
							if (is_array($_GET[$current])) {
								foreach ($_GET[$current] as $value) {
									$params[] = $current . '[]=' . urlencode($value);
								}
							} else {
								$params[] = $current . '=' . urlencode($_GET[$current]);
							}
						}
					}

			}
			$this->params = $params;
		}
		return $this->params;
	}

	/**
	 * Add to the solr fields returned from the search to be done
	 * by adding to $this->fields
	 *
	 * @param array $fields
	 * @return void
	 */
	public function addFieldsToReturn(array $fields): void{
		$this->fields .= ',' . implode(',', $fields);
	}

	/**
	 * Used to turn off standard filters used for creating default archive searching
	 *
	 * @param bool $useStandardFilters
	 * @return void
	 */
	public function setApplyStandardFilters(bool $useStandardFilters): void{
		$this->applyStandardFilters = $useStandardFilters;
	}
	/**
	 * Get an array of filters that should always be applied based on the library
	 *
	 * @return array
	 */
	private function getStandardFilters(){
		global $configArray;
		$filters = [
			'ss_type:islandoraobject',   // ignore other drupal things
			'bs_pika_show_in_search:1',  // Pika Option: Show in Search Results
			'!ss_name_1:Page',           // Hide Page objects
			'!itm_field_library:29478', // Hide Boulder Objects (theoretically number-filtering is quicker)
			//'!ss_name_23:Boulder',      // Hide Boulder Objects
			'!itm_field_member_of:567', // Hide objects member of Boulder (top) Collection; catches some things without library
			'!itm_field_member_of:530', //BD test? //TODO: these might not exist; and just need a full reindex to remove from search
			'!itm_field_member_of:640', // BD test?
		];

		// Pika Search Options
		// Pika Usage
		$filters[] = $configArray['Site']['isProduction']
			? "!ss_pika_usage:(no OR testOnly)"
			: "!ss_pika_usage:no";

		// Collections Hidden by the Current Library's Interface
		global /** @var \Library $library */ $library;
		$filter = $this->nodeIdsToFilter($library->collectionsToHide, '!itm_field_member_of');
		if ($filter) $filters[] = $filter;

		// Objects Hidden by the Current Library's Interface
		$filter = $this->nodeIdsToFilter($library->objectsToHide, '!its_node_id');
		if ($filter) $filters[] = $filter;

		// Collections Hidden to All Libraries
		require_once ROOT_DIR . '/sys/Archive/ArchivePrivateCollection.php';
		$privateCollectionsObj = new ArchivePrivateCollection();
		$privateCollectionsObj->type = 'collection';
		if ($privateCollectionsObj->find(true)){
			$filter = $this->nodeIdsToFilter($privateCollectionsObj->privateCollections, '!itm_field_member_of');
			if ($filter) $filters[] = $filter;
		}

		// Objects Hidden to All Libraries
		$privateObjectsObj = new ArchivePrivateCollection();
		$privateObjectsObj->type = 'object';
		if ($privateObjectsObj->find(true)){
			$filter = $this->nodeIdsToFilter($privateObjectsObj->privateCollections, '!its_node_id');
			if ($filter) $filters[] = $filter;
		}

		return $filters;
	}



	/**
	 * Build a compound Solr filter from a newline-separated list of numeric node IDs.
	 *
	 * @param string|null $nodeIdField  Raw \r\n-separated node ID string (e.g. $library->objectsToHide)
	 * @param string      $solrFilter   Solr filter prefix, e.g. '!its_node_id' or '!itm_field_member_of'
	 * @return string|null              Combined filter string, or null if no valid IDs found
	 */
	private function nodeIdsToFilter(?string $nodeIdField, string $solrFilter): ?string {
		if (empty($nodeIdField)){
			return null;
		}
		$parts = [];
		foreach (explode("\r\n", $nodeIdField) as $nodeId){
			if (!empty($nodeId) && ctype_digit($nodeId)){
				$parts[] = "$solrFilter:$nodeId";
			}
		}
		return empty($parts) ? null : implode(' AND ', $parts);
	}

	/**
	 * Fetch Facet Configuration settings
	 * @return array
	 */
	public function getFacetConfig():array{
		return $this->facetConfig;
	}

	/**
	 *  Define Filter Query that will be sent to Islandora2 Solr search engine
	 *
	 * @return array
	 */
	private function setFinalFilterQuery(): array{
		$filterQuery = $this->hiddenFilters;

		if ($this->applyStandardFilters){
			$filterQuery = array_merge($filterQuery, $this->getStandardFilters());
		}

		//Remove any empty filters if we get them
		//(typically happens when a subdomain has a function disabled that is enabled in the main scope)
		foreach ($this->filterList as $field => $filter){
			if (empty($field)){
				unset($this->filterList[$field]);
			}
		}
		foreach ($this->filterList as $field => $filter){
			if (is_numeric($field)){
				//This is a complex filter with ANDs and/or ORs
				$filterQuery[] = $filter[0];
			}else{
				foreach ($filter as $value){
					// Special case -- allow trailing wildcards:
					if (str_ends_with($value, '*')){
						$filterQuery[] = "$field:$value";
					}elseif (preg_match('/\\A\\[.*?\\sTO\\s.*?]\\z/', $value)){
						$filterQuery[] = "$field:$value";
					}elseif (!empty($value)){
						$filterQuery[] = "$field:\"$value\"";
					}
				}
			}
		}
		return $filterQuery;
	}


}