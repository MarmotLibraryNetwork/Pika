<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
use Curl\Curl;
use \Pika\Cache;
use \Pika\Logger;

require_once ROOT_DIR . '/sys/Search/IndexEngine.php';
require_once ROOT_DIR . '/sys/ConfigArray.php';

/**
 * Solr HTTP Interface
 *
 * @version     $Revision: 1.13 $
 * @author      Andrew S. Nagy <andrew.nagy@villanova.edu>
 * @access      public
 */
class Solr implements IndexEngine {
	/**
	 * A boolean value determining whether to include debug information in the query
	 * @var bool
	 */
	public $debug = false;

	/**
	 * A boolean value determining whether to print debug information for the query
	 * @var bool
	 */
	public $debugSolrQuery = false;

	public $isPrimarySearch = false;

	/**
	 * Whether to Serialize to a PHP Array or not.
	 * @var bool
	 */
//	public $raw = false;

	/**
	 * The Curl handler object used for REST transactions
	 * @var Curl $client
	 */
	public Curl $client;

	/**
	 * The host to connect to
	 * @var string
	 */
	public $host;

	private $index;

	/**
	 * The status of the connection to Solr
	 * @var string
	 */
	public $status = false;

	/**
	 * An array of characters that are illegal in search strings
	 */
	private $illegal = ['!', ':', ';', '[', ']', '{', '}'];

	/**
	 * The path to the JSON file specifying available search types:
	 */
	protected $searchSpecsFile = '../../conf/searchspecs.json';

	/**
	 * An array of search specs pulled from $searchSpecsFile (above)
	 *
	 * @var array
	 */
	private $_searchSpecs = false;

	/**
	 * Should boolean operators in the search string be treated as
	 * case-insensitive (false), or must they be ALL UPPERCASE (true)?
	 */
	private $caseSensitiveBooleans = true;

	/**
	 * Should range operators (i.e. [a TO b]) in the search string be treated as
	 * case-insensitive (false), or must they be ALL UPPERCASE (true)?  Note that
	 * making this setting case insensitive not only changes the word "TO" to
	 * uppercase but also inserts OR clauses to check for case insensitive matches
	 * against the edges of the range...  i.e. ([a TO b] OR [A TO B]).
	 */
	private $_caseSensitiveRanges = true;

	/**
	 * Selected shard settings.
	 */
	private $_solrShards = [];
	private $_solrShardsFieldsToStrip = [];

	/**
	 * Should we collect highlighting data?
	 */
	private $_highlight = false;

	/**
	 * Flag to disable default scoping to show ILL book titles, etc.
	 */
	private $scopingDisabled = false;

	/** @var string */
	private $searchSource = null;

	private static $serversPinged = [];

	private Pika\Cache $cache;
	private Pika\Logger $logger;

	/**
	 * Constructor
	 *
	 * @param string $host  The URL for the local Solr Server
	 * @param string $index The name of the index
	 * @access  public
	 */
	function __construct($host, $index = null){
		global $configArray;
		global $timer;
		$this->cache  = new Cache();
		$this->logger = new Logger(__CLASS__);
		$this->index  = $index ?? $configArray['Index']['default_core'] ?? 'grouped';
		$this->host   = $host . '/' . $this->index;
		$this->client = new Curl($this->host);

		//Check for a more specific search specs file
		global $serverName;
		// Return the file path (note that all ini files are in the conf/ directory)
		$siteSearchSpecsFile    = ROOT_DIR . "/../../sites/$serverName/conf/searchspecs.json";
		$defaultSearchSpecsFile = ROOT_DIR . "/../../sites/default/conf/searchspecs.json";
		if (file_exists($siteSearchSpecsFile)){
			$this->searchSpecsFile = $siteSearchSpecsFile;
		}elseif (file_exists($defaultSearchSpecsFile)){
			$this->searchSpecsFile = $defaultSearchSpecsFile;
		}

		// Read in preferred boolean behavior:
		$searchSettings = getExtraConfigArray('searches');
		if (isset($searchSettings['General']['case_sensitive_bools'])){
			$this->caseSensitiveBooleans = $searchSettings['General']['case_sensitive_bools'];
		}
		if (isset($searchSettings['General']['case_sensitive_ranges'])){
			$this->_caseSensitiveRanges = $searchSettings['General']['case_sensitive_ranges'];
		}

		// Turn on highlighting if the user has requested highlighting or snippet
		// functionality:
		$highlight = $configArray['Index']['enableHighlighting'];
		$snippet   = $configArray['Index']['enableSnippets'];
		if ($highlight || $snippet){
			$this->_highlight = true;
		}

		// Deal with field-stripping shard settings:
		if (!empty($searchSettings['StripFields'])){
			$this->_solrShardsFieldsToStrip = $searchSettings['StripFields'];
		}

		if (isset($_SESSION['shards'])){
			$this->_loadShards($_SESSION['shards']);
		}

		$timer->logTime('Finish Solr Initialization');
	}

	public function __destruct(){
		//$this->client = null;
	}

	public function pingServer($failOnError = true){
		global $configArray;
		if (array_key_exists($this->host, Solr::$serversPinged)) {
			return Solr::$serversPinged[$this->host];
		}

		$hostEscaped      = preg_replace('[\W]', '_', $this->host);
		$memCacheKey      = 'solr_ping_' . $hostEscaped;
		$cachedPingResult = $this->cache->get($memCacheKey);
		if ($cachedPingResult != null) {
			Solr::$serversPinged[$this->host] = $cachedPingResult == 'true';
			return Solr::$serversPinged[$this->host];
		}

		if ($cachedPingResult == false) {
			// Test to see solr is online
			$curl = new Curl();
			$curl->setTimeout(2);
			$pingUrl = $this->host . "/admin/ping";
			$result  = $curl->get($pingUrl);
			if ($curl->isError()) {
				$pingResult                       = 'false';
				Solr::$serversPinged[$this->host] = false;
				if ($failOnError) {
					PEAR_Singleton::raiseError($curl->getErrorMessage(), $curl->getErrorCode());
				} else {
					$this->logger->debug("Ping of {$this->host} failed");
					return false;
				}
			} elseif ($curl->getHttpStatusCode() != 200) {
				// Even if we get a response, make sure it's a 'good' one.
				$pingResult                       = 'false';
				Solr::$serversPinged[$this->host] = false;
				if ($failOnError) {
					PEAR_Singleton::raiseError('Solr index is offline.');
				} else {
					$this->logger->debug("Ping of {$this->host} failed");
					return false;
				}
			} else {
				$pingResult = 'true';
			}

			$this->cache->set($memCacheKey, $pingResult, $configArray['Caching']['solr_ping']);

			Solr::$serversPinged[$this->host] = $pingResult == 'true';
			global $timer;
			$timer->logTime('Ping Solr instance ' . $this->host);
		}
		return Solr::$serversPinged[$this->host];
	}

	public function setDebugging($enableDebug, $enableSolrQueryDebugging){
		$this->debug          = $enableDebug;
		$this->debugSolrQuery = $enableDebug && $enableSolrQueryDebugging;
	}

	private function _loadShards($newShards){
		// Deal with session-based shard settings:
		$shards = [];
		global $configArray;
		foreach ($newShards as $current) {
			if (isset($configArray['IndexShards'][$current])) {
				$shards[$current] = $configArray['IndexShards'][$current];
			}
		}
		$this->setShards($shards);
	}

	/**
	 * Is this object configured with case-sensitive boolean operators?
	 *
	 * @access  public
	 * @return  boolean
	 */
	public function hasCaseSensitiveBooleans(){
		return $this->caseSensitiveBooleans;
	}

	/**
	 * Is this object configured with case-sensitive range operators?
	 *
	 * @return boolean
	 * @access public
	 */
	public function hasCaseSensitiveRanges(){
		return $this->_caseSensitiveRanges;
	}

	/**
	 * Support method for _getSearchSpecs() -- load the specs from cache or disk.
	 *
	 * @return void
	 * @access private
	 */
	private function _loadSearchSpecs(){
		global $configArray;
		$results = $this->cache->get('searchSpecs');
		if (empty($results)) {
			$searchSpecs = file_get_contents($this->searchSpecsFile);
			$results     = json_decode($searchSpecs, true);
			$this->cache->set('searchSpecs', $results, $configArray['Caching']['searchSpecs']);
		}
		$this->_searchSpecs = $results;
	}

	/**
	 * Get the search specifications loaded from the specified YAML file.
	 *
	 * @param string $handler The named search to provide information about (set
	 *                        to null to get all search specifications)
	 *
	 * @return mixed Search specifications array if available, false if an invalid
	 * search is specified.
	 * @access  private
	 */
	private function _getSearchSpecs($handler = null){
		// Only load specs once:
		if ($this->_searchSpecs === false){
			$this->_loadSearchSpecs();
		}

		// Special case -- null $handler means we want all search specs.
		if (is_null($handler)){
			return $this->_searchSpecs;
		}

		// Return specs on the named search if found (easiest, most common case).
		if (isset($this->_searchSpecs[$handler])){
			return $this->_searchSpecs[$handler];
		}

		// Check for a case-insensitive match -- this provides backward
		// compatibility with different cases used in early VuFind versions
		// and allows greater tolerance of minor typos in config files.
		foreach ($this->_searchSpecs as $name => $specs){
			if (strcasecmp($name, $handler) == 0){
				return $specs;
			}
		}

		// If we made it this far, no search specs exist -- return false.
		return false;
	}

	/**
	 * Retrieves Solr Document with the document Id
	 * @param string     $id             The groupedWork Id/personId of the Solr document to retrieve
	 * @param null|string $fieldsToReturn An optional list of fields to return separated by commas
	 * @return array The Solr document of the grouped Work
	 */
	function getRecord($id, $fieldsToReturn = null){
		/** @var Memcache $memCache */
		global $memCache;
		global $solrScope;
		if (!$fieldsToReturn) {
			$validFields    = $this->_loadValidFields();
			$fieldsToReturn = implode(',', $validFields);
		}
		$memCacheKey  = "solr_record_{$id}_{$this->index}_{$solrScope}_{$fieldsToReturn}";
		$solrDocArray = $memCache->get($memCacheKey);

		if ($solrDocArray == false || isset($_REQUEST['reload'])) {
			$this->pingServer();
			// Query String Parameters
			$options = [
				'ids' => "$id",
				'fl'  => $fieldsToReturn,
			];

			global $timer;
			$timer->logTime("Prepare to send get (ids) request to solr returning fields $fieldsToReturn");

			$this->client->setDefaultJsonDecoder(true); // return an associative array instead of a json object
			$result = $this->client->get($this->host . '/get', $options);

			if ($this->client->isError()) {
				PEAR_Singleton::raiseError($this->client->getErrorMessage());
			} elseif (!empty($result['response']['docs'][0])) {
				$solrDocArray = $result['response']['docs'][0];
				global $configArray;
				$memCache->set($memCacheKey, $solrDocArray, 0, $configArray['Caching']['solr_record']);
			} else {
				$solrDocArray = [];
			}
		}
		return $solrDocArray;
	}

	function getRecordByBarcode($barcode, $fieldsToReturn = null){
		if ($this->debug) {
			$this->logger->debug("Get Record by Barcode: $barcode");
		}
		if (empty($fieldsToReturn)) {
			$validFields    = $this->_loadValidFields();
			$fieldsToReturn = implode(',', $validFields);
		}

		// Query String Parameters
		$options = [
			'q'  => "barcode:\"$barcode\"",
			'fl' => $fieldsToReturn
		];
		$result  = $this->_select('GET', $options);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result);
		}

		return $result['response']['docs'][0] ?? null;
	}

	/**
	 * @param string[] $ISBNs
	 * @param string|null $fieldsToReturn
	 * @return array|null
	 */
	function getRecordByIsbn($ISBNs, $fieldsToReturn = null){
		// Query String Parameters
		if (empty($fieldsToReturn)) {
			$validFields    = $this->_loadValidFields();
			$fieldsToReturn = implode(',', $validFields);
		}
		$options        = [
			'q'  => 'isbn:' . implode(' OR ', $ISBNs),
			'fl' => $fieldsToReturn
		];
		$result         = $this->_select('GET', $options);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result);
		}

		return $result['response']['docs'][0] ?? null;
	}

	/**
	 * Retrieves Solr Documents for an array of grouped Work Ids
	 * @param string[] $ids The groupedWork Id of the Solr document to retrieve
	 * @param null|array $fieldsToReturn An optional list of fields to return separated by commas
	 * @param null $batchSize
	 * @return array The Solr document of the grouped Work
	 */
	function getRecords($ids, $fieldsToReturn = null, $batchSize = 50){
		$solrDocArray = [];
		$numIds       = count($ids);
		if ($numIds) {

			if (!$fieldsToReturn) {
				$validFields    = $this->_loadValidFields();
				$fieldsToReturn = implode(',', $validFields);
			}

			$this->pingServer();
			$this->client->setDefaultJsonDecoder(true); // return an associative array instead of a json object

			//TODO: this comment doesn't appear to accurate any longer
			//Solr does not seem to be able to return more than 50 records at a time,
			//If we have more than 50 ids, we will ned to make multiple calls and
			//concatenate the results.
			$startIndex = 0;
			$batchSize  ??= $numIds;
			$lastBatch  = false;
			do {
				$endIndex = $startIndex + $batchSize;
				if ($endIndex >= $numIds) {
					$lastBatch = true;
					$endIndex  = $numIds;
					$batchSize = $numIds - $startIndex;
				}
				$tmpIds = array_slice($ids, $startIndex, $batchSize);

				// Query String Parameters
				$idString = implode(',', $tmpIds);
				$options  = [
					'ids' => $idString,
					'fl'  => $fieldsToReturn,
				];

				global $timer;
				$timer->logTime('Prepare to send get (ids) request to solr');

				// Send Request
				$result = $this->client->get($this->host . '/get', $options);

				$timer->logTime('Send data to solr for getRecords');

				if ($this->client->isError()) {
					PEAR_Singleton::raiseError($this->client->getErrorMessage());
				} else {
					$result = $this->_process($result);
					foreach ($result['response']['docs'] as $solrDoc) {
						$solrDocArray[$solrDoc['id']] = $solrDoc;
					}
				}

				if (!$lastBatch) {
					$startIndex = $endIndex;
				}
			} while (!$lastBatch);
		}
		return $solrDocArray;
	}

	/**
	 * Get records similar to one record
	 * Uses MoreLikeThis Request Handler
	 *
	 * Uses SOLR MLT Query Handler
	 *
	 * @access  public
	 * @return  array              An array of query results
	 *
	 * @throws  object            PEAR Error
	 * @var     string $id The id to retrieve similar titles for
	 */
	function getMoreLikeThis($id){
		// Query String Parameters
		$options = [
			'q'  => "id:$id",
			'qt' => 'morelikethis',
			'fl' => SearchObject_Solr::$fields
		];
		$result  = $this->_select('GET', $options);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Get records similar to one record
	 * Uses MoreLikeThis Request Handler
	 *
	 * Uses SOLR MLT Query Handler
	 *
	 * @access  public
	 * @return  array              An array of query results
	 *
	 * @throws  object            PEAR Error
	 * @var     string $id The id to retrieve similar titles for
	 */
	function getMoreLikeThis2($id){
		require_once ROOT_DIR . '/sys/ISBN/ISBN.php';
		global $configArray;
		global $solrScope;
		$originalResult = $this->getRecord($id,
			'target_audience_full,target_audience_full,literary_form,isbn,upc,language_' . $solrScope);

		// Query String Parameters
		$options = [
			'q'                    => "id:$id",
			'qt'                   => 'morelikethis2',
			'mlt.interestingTerms' => 'details',
			'rows'                 => 25,
			'fl'                   => SearchObject_Solr::$fields
		];
		if ($originalResult){
			$options['fq'] = [];
			if (isset($originalResult['target_audience_full'])){
				if (is_array($originalResult['target_audience_full'])){
					$filter = '';
					foreach ($originalResult['target_audience_full'] as $targetAudience){
						if ($targetAudience != 'Unknown'){
							if (strlen($filter) > 0){
								$filter .= ' OR ';
							}
							$filter .= 'target_audience_full:"' . $targetAudience . '"';
						}
					}
					if (strlen($filter) > 0){
						$options['fq'][] = "($filter)";
					}
				}else{
					$options['fq'][] = 'target_audience_full:"' . $originalResult['target_audience_full'] . '"';
				}
			}
			if (isset($originalResult['literary_form'])){
				if (is_array($originalResult['literary_form'])){
					$filter = '';
					foreach ($originalResult['literary_form'] as $literaryForm){
						if ($literaryForm != 'Not Coded'){
							if (strlen($filter) > 0){
								$filter .= ' OR ';
							}
							$filter .= 'literary_form:"' . $literaryForm . '"';
						}
					}
					if (strlen($filter) > 0){
						$options['fq'][] = "($filter)";
					}
				}else{
					$options['fq'][] = 'literary_form:"' . $originalResult['literary_form'] . '"';
				}
			}
			if (isset($originalResult['language_' . $solrScope])){
				$options['fq'][] = "language_$solrScope:\"" . $originalResult["language_$solrScope"][0] . '"';
			}
			//Don't want to get other editions of the same work (that's a different query)
			if ($this->index != 'grouped'){
				if (isset($originalResult['isbn'])){
					if (is_array($originalResult['isbn'])){
						foreach ($originalResult['isbn'] as $isbn){
							$options['fq'][] = '-isbn:' . ISBN::normalizeISBN($isbn);
						}
					}else{
						$options['fq'][] = '-isbn:' . ISBN::normalizeISBN($originalResult['isbn']);
					}
				}
				if (isset($originalResult['upc'])){
					if (is_array($originalResult['upc'])){
						foreach ($originalResult['upc'] as $upc){
							$options['fq'][] = '-upc:' . ISBN::normalizeISBN($upc);
						}
					}else{
						$options['fq'][] = '-upc:' . ISBN::normalizeISBN($originalResult['upc']);
					}
				}
			}
		}

		$searchLibrary  = Library::getSearchLibrary();
		$searchLocation = Location::getSearchLocation();
		if ($searchLibrary && $searchLocation){
			if ($searchLibrary->ilsCode == $searchLocation->code){
				$searchLocation = null;
			}
		}

		$scopingFilters = $this->getScopingFilters($searchLibrary, $searchLocation);
		foreach ($scopingFilters as $filter){
			$options['fq'][] = $filter;
		}
		$boostFactors = $this->getBoostFactors($searchLibrary, $searchLocation);
		if ($configArray['Index']['enableBoosting']){
			$options['bf'] = $boostFactors;
		}

		$result = $this->_select('GET', $options);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Get records similar to one record
	 * Uses MoreLikeThis Request Handler
	 *
	 * Uses SOLR MLT Query Handler
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
			'qt'                   => 'morelikethese',
			'mlt.interestingTerms' => 'details',
			'rows'                 => 25
		];

		$searchLibrary  = Library::getSearchLibrary();
		$searchLocation = Location::getSearchLocation();
		$scopingFilters = $this->getScopingFilters($searchLibrary, $searchLocation);

		$notInterestedString = implode(' OR ', $notInterestedIds);
		if (strlen($notInterestedString) > 0){
			$idString .= ' OR ' . $notInterestedString;
		}
		$options['fq'][] = "-id:($idString)";
		foreach ($scopingFilters as $filter){
			$options['fq'][] = $filter;
		}
		if ($configArray['Index']['enableBoosting']){
			$boostFactors  = $this->getBoostFactors($searchLibrary, $searchLocation);
			$options['bf'] = $boostFactors;
		}

		$options['rows'] = 30;

		// TODO: Limit Fields
//		if ($this->debug && isset($fields)) {
//			$options['fl'] = $fields;
//		} else {
		// This should be an explicit list
		$options['fl'] = '*,score';
//		}
		$result = $this->_select('GET', $options);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Get record data based on the provided field and phrase.
	 * Used for AJAX suggestions.
	 *
	 * @access  public
	 * @param string $phrase The input phrase
	 * @param string $field  The field to search on
	 * @param int    $limit  The number of results to return
	 * @return  array   An array of query results
	 */
	function getSuggestion($phrase, $field, $limit){
		if (!strlen($phrase)){
			return null;
		}

		// Ignore illegal characters
		$phrase = str_replace($this->illegal, '', $phrase);

		// Process Search
		$query  = "$field:($phrase*)";
		$result = $this->search($query, null, null, 0, $limit, array('field' => $field, 'limit' => $limit));
		return $result['facet_counts']['facet_fields'][$field];
	}

	/**
	 * Get spelling suggestions based on input phrase.
	 *
	 * @access  public
	 * @param string $phrase The input phrase
	 * @return  array   An array of spelling suggestions
	 */
	function checkSpelling($phrase){
		if ($this->debugSolrQuery){
			$this->logger->debug("Spell Check: $phrase");
		}

		// Query String Parameters
		$options = [
			'q'          => $phrase,
			'rows'       => 0,
			'start'      => 1,
			'indent'     => 'yes',
			'spellcheck' => 'true'
		];

		$result = $this->_select('GET', $options);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * applySearchSpecs -- internal method to build query string from search parameters
	 *
	 * @access  private
	 * @param array  $structure the SearchSpecs-derived structure or substructure defining the search, derived from the
	 *                          json file
	 * @param array  $values    the various values in an array with keys 'onephrase', 'and', 'or' (and perhaps others)
	 * @param string $joiner
	 * @return  string              A search string suitable for adding to a query URL
	 * @throws  object              PEAR Error
	 * @static
	 */
	private function _applySearchSpecs($structure, $values, $joiner = "OR"){
		global $solrScope;
		$clauses = array();
		foreach ($structure as $field => $clauseArray) {
			if (is_numeric($field)){
				$sw           = array_shift($clauseArray); // shift off the join string and weight
				$internalJoin = ' ' . $sw[0] . ' ';
				$searchString = '(' . $this->_applySearchSpecs($clauseArray, $values, $internalJoin) . ')'; // Build it up recursively
				$weight       = $sw[1]; // ...and add a weight if we have one
				if (!empty($weight)){
					$searchString .= '^' . $weight;
				}
				// push it onto the stack of clauses
				$clauses[] = $searchString;
			} else {
				if ($solrScope && ($field == 'local_callnumber' || $field == 'local_callnumber_left' || $field == 'local_callnumber_exact')) {
					$field .= '_' . $solrScope;
				}

				// Otherwise, we've got a (list of) [munge, weight] pairs to deal with
				foreach ($clauseArray as $spec) {
					$fieldValue = $values[$spec[0]];

					switch ($field){
						case 'isbn':
							if (!preg_match('/^((?:\sOR\s)?["(]?\d{9,13}X?[\s")]*)+$/', $fieldValue)){
								continue 2;
							}else{
								require_once ROOT_DIR . '/sys/ISBN/ISBN.php';
								$isbn = new ISBN($fieldValue);
								if ($isbn->isValid()){
									$isbn10 = $isbn->get10();
									$isbn13 = $isbn->get13();
									if ($isbn10 && $isbn13){
										$fieldValue = '(' . $isbn->get10() . ' OR ' . $isbn->get13() . ')';
									}
								}
							}
							break;
						case 'id': //todo : double check that this includes all valid id schemes
							if (!preg_match('/^"?(\d+|.[boi]\d+x?|[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})"?$/i',
								$fieldValue)){
								continue 2;
							}
							break;
						case 'alternate_ids': //todo: this doesn't have all Id schemes included
							if (!preg_match('/^"?(\d+|.?[boi]\d+x?|[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}|MWT\d+|CARL\d+)"?$/i',
								$fieldValue)){
								continue 2;
							}
							break;
						case 'issn':
							if (!preg_match('/^"?[\dXx-]+"?$/', $fieldValue)){
								continue 2;
							}
							break;
						case 'upc':
							if (!preg_match('/^"?\d+"?$/', $fieldValue)){
								continue 2;
							}
							break;
					}

					// build a string like title:("one two")
					$searchString = $field . ':(' . $fieldValue . ')';
					//Check to make sure we don't already have this clause.  We will get the same clause if we have a single word and are doing different munges
					foreach ($clauses as $clause) {
						if (strpos($clause, $searchString) === 0) {
							continue 2;
						}
					}

					// Add the weight it we have one. Yes, I know, it's redundant code.
					$weight = $spec[1];
					if (!empty($weight)) {
						$searchString .= '^' . $weight;
					}

					// ..and push it on the stack of clauses
					$clauses[] = $searchString;
				}
			}
		}

		// Join it all together
		return implode(' ' . $joiner . ' ', $clauses);
	}

	/**
	 * Load Boost factors for a query
	 *
	 * @param Library  $searchLibrary
	 * @param Location $searchLocation
	 * @return array
	 */
	public function getBoostFactors($searchLibrary, $searchLocation){
		$boostFactors = array();

		global $solrScope;
		global $language;
		if ($language == 'es') {
			$boostFactors[] = "language_boost_es_{$solrScope}";
		} else {
			$boostFactors[] = "language_boost_{$solrScope}";
		}

		$boostFactors[] = (!empty($searchLibrary->applyNumberOfHoldingsBoost)) ? 'product(sum(popularity,1),format_boost)' : 'format_boost';

		// Add rating as part of the ranking, normalize so ratings of less that 2.5 are below unrated entries.
		$boostFactors[] = 'sum(rating,1)';

		if (!empty($searchLibrary->boostByLibrary)) {
			$boostFactors[] = ($searchLibrary->additionalLocalBoostFactor > 1) ? "sum(product(lib_boost_{$solrScope},{$searchLibrary->additionalLocalBoostFactor}),1)" : "sum(lib_boost_{$solrScope},1)";
		} else {
			// Handle boosting even if we are in a global scope
			global $library;
			if (!empty($library->boostByLibrary)) {
				$boostFactors[] = ($library->additionalLocalBoostFactor > 1) ? "sum(product(lib_boost_{$solrScope},{$library->additionalLocalBoostFactor}),1)" : "sum(lib_boost_{$solrScope},1)";
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
			}
		}
		return $boostFactors;
	}

	/**
	 * Given a field name and search string, return an array containing munged
	 * versions of the search string for use in _applySearchSpecs().
	 *
	 * @access  private
	 * @param string $lookfor The string to search for in the field
	 * @param array  $custom  Custom munge settings from YAML search specs
	 * @param bool   $basic   Is $lookfor a basic (true) or advanced (false) query?
	 * @return  array         Array for use as _applySearchSpecs() values param
	 */
	private function _buildMungeValues($lookfor, $custom = null, $basic = true){
		if ($basic) {
			$cleanedQuery = str_replace(':', ' ', $lookfor);

			// Tokenize Input
			$tokenized = $this->tokenizeInput($cleanedQuery);

			// Create AND'd and OR'd queries
			$andQuery = implode(' AND ', $tokenized);
			$orQuery  = implode(' OR ', $tokenized);

			// Build possible inputs for searching:
			$values              = array();
			$values['onephrase'] = '"' . str_replace('"', '', implode(' ', $tokenized)) . '"';
			if (count($tokenized) > 1) {
				$values['proximal'] = $values['onephrase'] . '~10';
			} elseif (!array_key_exists(0, $tokenized)) {
				$values['proximal'] = '';
			} else {
				$values['proximal'] = $tokenized[0];
			}

			$values['exact']        = str_replace(':', '\\:', $lookfor);
			$values['exact_quoted'] = '"' . $lookfor . '"';
			$values['and']          = $andQuery;
			$values['or']           = $orQuery;
			$singleWordRemoval      = '';
			if (count($tokenized) <= 4) {
				$singleWordRemoval = '"' . str_replace('"', '', implode(' ', $tokenized)) . '"';
			} else {
				for ($i = 0; $i < count($tokenized); $i++) {
					$newTerm = '"';
					for ($j = 0; $j < count($tokenized); $j++) {
						if ($j != $i) {
							$newTerm .= $tokenized[$j] . ' ';
						}
					}
					$newTerm = trim($newTerm) . '"';
					if (strlen($singleWordRemoval) > 0) {
						$singleWordRemoval .= ' OR ';
					}
					$singleWordRemoval .= $newTerm;
				}
			}
			$values['single_word_removal'] = $singleWordRemoval;
		} else {
			// If we're skipping tokenization, we just want to pass $lookfor through
			// unmodified (it's probably an advanced search that won't benefit from
			// tokenization).	We'll just set all possible values to the same thing,
			// except that we'll try to do the "one phrase" in quotes if possible.
			$onephrase = strstr($lookfor, '"') ? $lookfor : '"' . $lookfor . '"';
			$values    = array(
				'exact'               => $onephrase,
				'onephrase'           => $onephrase,
				'and'                 => $lookfor,
				'or'                  => $lookfor,
				'proximal'            => $lookfor,
				'single_word_removal' => $onephrase,
				'exact_quoted'        => '"' . $lookfor . '"',
			);
		}

		//Create localized call number
		$noWildCardLookFor = str_replace('*', '', $lookfor);
		if (strpos($lookfor, '*') !== false) {
			$noWildCardLookFor = str_replace('*', '', $lookfor);
		}
		$values['localized_callnumber'] = '"' . str_replace(array('"', ':', '/'), ' ', $noWildCardLookFor) . '"';

		// Apply custom munge operations if necessary
		if (is_array($custom) && $basic) {
			foreach ($custom as $mungeName => $mungeOps) {
				$values[$mungeName] = $lookfor;

				// Skip munging if tokenization is disabled.
				foreach ($mungeOps as $operation) {
					switch ($operation[0]) {
						case 'exact':
							$values[$mungeName] = '"' . $values[$mungeName] . '"';
							break;
						case 'append':
							$values[$mungeName] .= $operation[1];
							break;
						case 'lowercase':
							$values[$mungeName] = strtolower($values[$mungeName]);
							break;
						case 'preg_replace':
							$values[$mungeName] = preg_replace($operation[1],
							 $operation[2], $values[$mungeName]);
							break;
						case 'uppercase':
							$values[$mungeName] = strtoupper($values[$mungeName]);
							break;
					}
				}
			}
		}
		return $values;
	}

	/**
	 * Given a field name and search string, expand this into the necessary Lucene
	 * query to perform the specified search on the specified field(s).
	 *
	 * @access  public            Has to be public since it can be called as part of a preg replace statement
	 * @param string $field    The YAML search spec field name to search
	 * @param string $lookfor  The string to search for in the field
	 * @param bool   $tokenize Should we tokenize $lookfor or pass it through?
	 * @return  string              The query
	 */
	public function _buildQueryComponent($field, $lookfor, $tokenize = true)
	{
		// Load the YAML search specifications:
		$ss = $this->_getSearchSpecs($field);

		if ($field == 'AllFields') {
			$field = 'Keyword';
		}

		// If we received a field spec that wasn't defined in the YAML file,
		// let's try simply passing it along to Solr.
		if ($ss === false) {
			$allFields = $this->_loadValidFields();
			if (in_array($field, $allFields)) {
				return $field . ':(' . $lookfor . ')';
			}
			$dynamicFields = $this->_loadDynamicFields();
			global $solrScope;
			foreach ($dynamicFields as $dynamicField) {
				if ($dynamicField . $solrScope == $field) {
					return $field . ':(' . $lookfor . ')';
				}
			}
			//Not a search by field
			return '"' . $field . ':' . $lookfor . '"';
		}

		// Munge the user query in a few different ways:
		$customMunge = $ss['CustomMunge'] ?? null;
		$values      = $this->_buildMungeValues($lookfor, $customMunge, $tokenize);

		// Apply the $searchSpecs property to the data:
		$baseQuery = $this->_applySearchSpecs($ss['QueryFields'], $values);

		// Apply filter query if applicable:
		if (isset($ss['FilterQuery'])) {
			return "({$baseQuery}) AND ({$ss['FilterQuery']})";
		}

		return "($baseQuery)";
	}

	/**
	 * Given a field name and search string known to contain advanced features
	 * (as identified by isAdvanced()), expand this into the necessary Lucene
	 * query to perform the specified search on the specified field(s).
	 *
	 * @access  private
	 * @param string $handler The handler for the search
	 * @param string $query   The string to search for in the field
	 * @return  string              The query
	 */
	private function _buildAdvancedQuery($handler, $query)
	{
		// Special case -- if the user wants all records but the current handler
		// has a filter query, apply the filter query:
		if (trim($query) == '*:*') {
			$ss = $this->_getSearchSpecs($handler);
			if (isset($ss['FilterQuery'])) {
				return $ss['FilterQuery'];
			}
		}

		// Strip out any colons that are NOT part of a field specification:
		$query = preg_replace('/(\:\s+|\s+:)/', ' ', $query);

		// If the query already includes field specifications, we can't easily
		// apply it to other fields through our defined handlers, so we'll leave
		// it as-is:
		if (strstr($query, ':')) {
			return $query;
		}

		// Convert empty queries to return all values in a field:
		if (empty($query)) {
			$query = '[* TO *]';
		}

		// If the query ends in a question mark, the user may not really intend to
		// use the question mark as a wildcard -- let's account for that possibility
		if (substr($query, -1) == '?') {
			$query = "({$query}) OR (" . substr($query, 0, strlen($query) - 1) . ")";
		}

		// We're now ready to use the regular YAML query handler but with the
		// $tokenize parameter set to false so that we leave the advanced query
		// features unmolested.
		return $this->_buildQueryComponent($handler, $query, false);
	}

	/* Build Query string from search parameters
	 *
	 * @access	public
	 * @param	 array	 $search		  An array of search parameters
	 * @param	 boolean $forDisplay  Whether or not the query is being built for display purposes
	 * @throws	object							PEAR Error
	 * @static
	 * @return	string							The query
	 */
	function buildQuery($search, $forDisplay = false){
		$groups   = [];
		$excludes = [];
		$query    = '';
		if (is_array($search)) {

			foreach ($search as $params) {
				//Check to see if need to break up a basic search into an advanced search
				$modifiedQuery = false;
				$that          = $this;
				if (isset($params['lookfor']) && !$forDisplay) {
					$lookfor       = preg_replace_callback(
					 '/([\\w-]+):([\\w\\d\\s"-]+?)\\s?(?<=\b)(AND|OR|AND NOT|OR NOT|\\)|$)(?=\b)/',
					 function ($matches) use ($that) {
						 $field    = $matches[1];
						 $lookfor  = $matches[2];
						 $newQuery = $that->_buildQueryComponent($field, $lookfor);
						 return $newQuery . $matches[3];
					 },
					 $params['lookfor']
					);
					$modifiedQuery = $lookfor != $params['lookfor'];
				}
				if ($modifiedQuery) {
					//This is an advanced search
					$query = $lookfor;
				} else {
					// Advanced Search
					if (isset($params['group'])) {
						$thisGroup = [];
						// Process each search group
						foreach ($params['group'] as $group) {
							// Build this group individually as a basic search
							if (strpos($group['lookfor'], ' ') > 0) {
								$group['lookfor'] = '(' . $group['lookfor'] . ')';
							}
							if ($group['field'] == 'AllFields') {
								$group['field'] = 'Keyword';
							}
							$thisGroup[] = $this->buildQuery([$group]);
						}
						// Is this an exclusion (NOT) group or a normal group?
						if ($params['group'][0]['bool'] == 'NOT') {
							$excludes[] = join(" OR ", $thisGroup);
						} else {
							$groups[] = join(" " . $params['group'][0]['bool'] . " ", $thisGroup);
						}
					}

					// Basic Search
					if (isset($params['lookfor']) && $params['lookfor'] != '') {
						// Clean and validate input
						$lookfor = $this->validateInput($params['lookfor']);

						// Force boolean operators to uppercase if we are in a case-insensitive
						// mode:
						if (!$this->caseSensitiveBooleans) {
							$lookfor = self::capitalizeBooleans($lookfor);
						}

						if (isset($params['field']) && ($params['field'] != '')) {
							if ($this->isAdvanced($lookfor)) {
								$query .= $this->_buildAdvancedQuery($params['field'], $lookfor);
							} else {
								$query .= $this->_buildQueryComponent($params['field'], $lookfor);
							}
						} else {
							/*if ($forDisplay &&
									isset($params['index']) &&
									$params['index'] != 'Keyword' &&
									!strpos($lookfor, $params['index']) === 0) {

								$query = $params['index'] . ':' . $lookfor;
							} else {*/
							$query .= $lookfor;
							//}
						}
					}
				}
			}
		}

		// Put our advanced search together
		if (count($groups) > 0) {
			$query = "(" . join(") " . $search[0]['join'] . " (", $groups) . ")";
		}
		// and concatenate exclusion after that
		if (count($excludes) > 0) {
			$query .= " NOT ((" . join(") OR (", $excludes) . "))";
		}

		// Ensure we have a valid query to this point
		if (empty($query)) {
			$query = '*:*';
		}

		return $query;
	}

	/**
	 * Normalize a sort option.
	 *
	 * @param string $sort The sort option.
	 *
	 * @return string      The normalized sort value.
	 * @access private
	 */
	private function _normalizeSort($sort){
		// Break apart sort into field name and sort direction
		$sort = trim($sort);
		@list($sortField, $sortDirection) = explode(' ', $sort);
		// (note : error suppression with @to prevent notice when direction is left blank)
		// this notice suppression doesn't work when replacing list() with []

		// Default sort order (may be overridden by switch below):
		$defaultSortDirection = 'asc';

		// Translate special sort values into appropriate Solr fields:
		switch ($sortField){
			case 'year':
			case 'publishDate':
				$sortField            = 'publishDateSort';
				$defaultSortDirection = 'desc';
				break;
			case 'author':
				$sortField = 'authorStr asc, title_sort';
				break;
			case 'title':
				$sortField = 'title_sort asc, authorStr';
				break;
			case 'callnumber_sort':
				$searchLibrary = Library::getSearchLibrary($this->searchSource);
				if ($searchLibrary != null){
					$sortField = 'callnumber_sort_' . $searchLibrary->subdomain;
				}
				break;
		}

		// Normalize sort direction to either "asc" or "desc":
		$sortDirection = strtolower(trim($sortDirection));
		if ($sortDirection != 'asc' && $sortDirection != 'desc'){
			$sortDirection = $defaultSortDirection;
		}

		return $sortField . ' ' . $sortDirection;
	}

	function disableScoping(){
		$this->scopingDisabled = true;
		global $configArray;
		if (isset($configArray['ShardPreferences']['defaultChecked']) && !empty($configArray['ShardPreferences']['defaultChecked'])){
			$checkedShards = $configArray['ShardPreferences']['defaultChecked'];
			$shards        = is_array($checkedShards) ? $checkedShards : [$checkedShards];
		}else{
			// If no default is configured, use all shards...
			if (isset($configArray['IndexShards'])){
				$shards = array_keys($configArray['IndexShards']);
			}
		}
		if (isset($shards)){
			$this->_loadShards($shards);
		}
	}

	function enableScoping()
	{
		$this->scopingDisabled = false;
		global $configArray;
		if (isset($configArray['ShardPreferences']['defaultChecked']) && !empty($configArray['ShardPreferences']['defaultChecked'])) {
			$checkedShards = $configArray['ShardPreferences']['defaultChecked'];
			$shards        = is_array($checkedShards) ? $checkedShards : array($checkedShards);
		} else {
			// If no default is configured, use all shards...
			if (isset($configArray['IndexShards'])) {
				$shards = array_keys($configArray['IndexShards']);
			}
		}
		if (isset($shards)) {
			$this->_loadShards($shards);
		}
	}

	/**
	 * Execute a search.
	 *
	 * @param string $query                 The XQuery script in binary encoding.
	 * @param string $handler               The Query Handler to use (null for default)
	 * @param array  $filter                The fields and values to filter results on
	 * @param int    $start                 The record to start with
	 * @param int    $limit                 The amount of records to return
	 * @param array  $facet                 An array of faceting options
	 * @param string $spell                 Phrase to spell check
	 * @param string $dictionary            Spell check dictionary to use
	 * @param string $sort                  Field name to use for sorting
	 * @param string $fields                A list of fields to be returned
	 * @param string $method                Method to use for sending request (GET/POST)
	 * @param bool   $returnSolrError       If Solr reports a syntax error,
	 *                                      should we fail outright (false) or
	 *                                      treat it as an empty result set with
	 *                                      an error key set (true)?
	 * @access  public
	 * @return  array               An array of query results
	 * @throws  object              PEAR Error
	 */
	function search(
		$query,
		$handler = null,
		$filter = null,
		$start = 0,
		$limit = 20,
		$facet = null,
		$spell = '',
		$dictionary = null,
		$sort = null,
		$fields = null,
		$method = 'GET',
		$returnSolrError = false
	) {
		global $timer;
		global $configArray;
		// Query String Parameters
		$options = ['q' => $query, 'rows' => $limit, 'start' => $start, 'indent' => 'yes'];

		// Add Sorting
		if (!empty($sort)) {
			// There may be multiple sort options (ranked, with tie-breakers);
			// process each individually, then assemble them back together again:
			$sortParts = explode(',', $sort);
			foreach ($sortParts as &$sortPart){
				$sortPart = $this->_normalizeSort($sortPart);
			}
			$options['sort'] = implode(',', $sortParts);
		}

		//Check to see if we need to automatically convert to a proper case only (no stemming search)
		//We will do this whenever all or part of a string is surrounded by quotes.
		if (is_array($query)) {
			echo("Invalid query " . print_r($query, true));
		}
		if (preg_match('/\\".+?\\"/', $query)) {
			switch ($handler){
				case 'AllFields':
				case 'Keyword':
					$handler = 'KeywordProper';
					break;
				case 'Author':
					$handler = 'AuthorProper';
					break;
				case 'Subject':
					$handler = 'SubjectProper';
					break;
				case 'Title':
					$handler = 'TitleProper';
					break;
				case 'IslandoraKeyword':
					$handler = 'IslandoraKeywordProper';
					break;
				case 'IslandoraSubject':
					$handler = 'IslandoraSubjectProper';
					break;

			}
		}

		// Determine which handler to use
		if (!$this->isAdvanced($query)) {
			//Remove extraneous colons to make sure that the query isn't treated as a field spec.
			$ss = is_null($handler) ? null : $this->_getSearchSpecs($handler);
			// Is this a Dismax search?
			if (isset($ss['DismaxFields'])) {
				// Specify the fields to do a Dismax search on:
				$options['qf'] = implode(' ', $ss['DismaxFields']);

				// Specify the default dismax search handler so we can use any
				// global settings defined by the user:
				$options['defType'] = 'dismax';

				// Load any custom Dismax parameters from the YAML search spec file:
				if (isset($ss['DismaxParams']) &&
				 is_array($ss['DismaxParams'])) {
					foreach ($ss['DismaxParams'] as $current) {
						$options[$current[0]] = $current[1];
					}
				}

				// Apply search-specific filters if necessary:
				if (isset($ss['FilterQuery'])) {
					if (is_array($filter)) {
						$filter[] = $ss['FilterQuery'];
					} else {
						$filter = [$ss['FilterQuery']];
					}
				}
			} else {
				// Not DisMax... but do we need to format the query based on
				// a setting in the YAML search specs?	If $ss is an array
				// at this point, it indicates that we found YAML details.
				if (is_array($ss)) {
					$options['q'] = $this->_buildQueryComponent($handler, $query);
				}elseif (!empty($handler)){
					$options['q'] = "({$handler}:{$query})";
				}
			}
		} else {
			// Force boolean operators to uppercase if we are in a case-insensitive
			// mode:
			if (!$this->caseSensitiveBooleans) {
				$query = self::capitalizeBooleans($query);
			}

			// Process advanced search -- if a handler was specified, let's see
			// if we can adapt the search to work with the appropriate fields.
			if (!empty($handler)) {
				$options['q'] = $this->_buildAdvancedQuery($handler, $query);
			}
		}
		$timer->logTime('build query');

		// Limit Fields
		if ($fields) {
			$options['fl'] = $fields;
		} else {
			// This should be an explicit list
			$options['fl'] = '*,score';
		}
		if ($this->debug) {
			$options['fl'] .= ',explain';
		}

		if (is_object($this->searchSource)) {
			$defaultFilters = preg_split('/\r\n/', $this->searchSource->defaultFilter);
			foreach ($defaultFilters as $tmpFilter) {
				$filter[] = $tmpFilter;
			}
		}

		//Apply automatic boosting (only to biblio and econtent queries)
		$isPikaGroupedWorkIndex = preg_match('/.*(grouped).*/i', $this->host);
		if ($isPikaGroupedWorkIndex) {
			$searchLibrary  = Library::getSearchLibrary($this->searchSource);
			$searchLocation = Location::getSearchLocation($this->searchSource);

			if (!empty($configArray['Index']['enableBoosting'])){
				$boostFactors = $this->getBoostFactors($searchLibrary, $searchLocation);
				if (!empty($boostFactors)){
					$boost = 'sum(' . implode(',', $boostFactors) . ')';
					if (!empty($options['defType']) && $options['defType'] == 'dismax'){
						$options['bf'] = $boost;
					} else{
						$options['q'] = "{!boost b=$boost} " . $options['q'];
					}
				}
				$timer->logTime('apply boosting');
			}

			$scopingFilters = $this->getScopingFilters($searchLibrary, $searchLocation);

			$timer->logTime('apply filters based on location');
		} else {
			//Non book search (genealogy)
			$scopingFilters = [];
		}
		if ($filter != null && $scopingFilters != null) {
			if (!is_array($filter)) {
				$filter = [$filter];
			}
			//Check the filters to make sure they are for the correct scope
			global $solrScope;
			$validFields  = $this->_loadValidFields();
			$validFilters = [];
			foreach ($filter as $id => $filterTerm) {
				[$fieldName, $term] = explode(':', $filterTerm, 2);
				if (!in_array($fieldName, $validFields)) {
					//Special handling for availability_by_format
					if (preg_match("/^availability_by_format_([^_]+)_[\\w_]+$/", $fieldName)) {
						//This is a valid field
						$validFilters[$id] = $filterTerm;
					} elseif (preg_match("/^available_at_by_format_([^_]+)_[\\w_]+$/", $fieldName)) {
						//This is a valid field
						$validFilters[$id] = $filterTerm;
					} else {
						//Field doesn't exist, check to see if it is a dynamic field
						//Where we can replace the scope with the current scope
						if (!isset($dynamicField)) {
							$dynamicFields = $this->_loadDynamicFields();
						}
						foreach ($dynamicFields as $dynamicField) {
							if (preg_match("/^{$dynamicField}[^_]+$/", $fieldName)) {
								//This is a dynamic field with the wrong scope
								$validFilters[$id] = $dynamicField . $solrScope . ':' . $term;
								break;
							} elseif ($fieldName == rtrim($dynamicField, '_')) {
								//This is a regular field that is now a dynamic field so needs the scope applied
								$validFilters[$id] = $dynamicField . $solrScope . ':' . $term;
								break;
							}
						}
					}
				} else {
					$validFilters[$id] = $filterTerm;
				}
			}
			$filters = array_merge($validFilters, $scopingFilters);
		} elseif ($filter == null) {
			$filters = $scopingFilters;
		} else {
			$filters = $filter;
		}


		// Build Facet Options
		if (!empty($facet['field']) && $configArray['Index']['enableFacets']) {
			$options['facet']          = 'true';
			$options['facet.mincount'] = 1;
			$options['facet.method']   = 'fcs';
			$options['facet.threads']  = 25;
			$options['facet.limit']    =  $facet['limit'] ?? null;

			//Determine which fields should be treated as enums
			global $solrScope;
			if ($isPikaGroupedWorkIndex) {
				$options["f.target_audience_full.facet.method"]                = 'enum';
				$options["f.target_audience.facet.method"]                     = 'enum';
				$options["f.literary_form_full.facet.method"]                  = 'enum';
				$options["f.literary_form.facet.method"]                       = 'enum';
				$options["f.literary_form.econtent_device"]                    = 'enum';
				$options["f.literary_form.lexile_code"]                        = 'enum';
				$options["f.literary_form.mpaa_rating"]                        = 'enum';
				$options["f.literary_form.rating_facet"]                       = 'enum';
				$options["f.format_category_{$solrScope}.rating_facet"]        = 'enum';
				$options["f.format_{$solrScope}.rating_facet"]                 = 'enum';
				$options["f.availability_toggle_{$solrScope}.rating_facet"]    = 'enum';
				$options["f.local_time_since_added_{$solrScope}.rating_facet"] = 'enum';
				$options["f.owning_library_{$solrScope}.rating_facet"]         = 'enum';
				$options["f.owning_location_{$solrScope}.rating_facet"]        = 'enum';
			}

			unset($facet['limit']);
			if (isset($facet['field']) && is_array($facet['field']) && in_array('date_added', $facet['field'])) {
				$options['facet.date']       = 'date_added';
				$options['facet.date.end']   = 'NOW';
				$options['facet.date.start'] = 'NOW-1YEAR';
				$options['facet.date.gap']   = '+1WEEK';
				foreach ($facet['field'] as $key => $value) {
					if ($value == 'date_added') {
						unset($facet['field'][$key]);
						break;
					}
				}
			}


			if (isset($facet['field'])) {
				$options['facet.field'] = $facet['field'];
				if ($isPikaGroupedWorkIndex && $options['facet.field'] && is_array($options['facet.field'])) {
					foreach ($options['facet.field'] as $key => $facetName) {
						if (strpos($facetName, 'availability_toggle') === 0 || strpos($facetName, 'availability_by_format') === 0) {
							$options['facet.field'][$key]            = '{!ex=avail}' . $facetName;
							$options["f.{$facetName}.facet.missing"] = 'true';
						}
						//Update facets for grouped core
					}
				}
			} else {
				$options['facet.field'] = null;
			}

			unset($facet['field']);

			if (!empty($facet['prefix'])){
				$options['facet.prefix'] = $facet['prefix'] ?? null;
			}
			unset($facet['prefix']);
			$options['facet.sort'] =  $facet['sort'] ?? 'count';

			unset($facet['sort']);
			if (isset($facet['offset'])) {
				$options['facet.offset'] = $facet['offset'];
				unset($facet['offset']);
			}
			if (isset($facet['limit'])) {
				$options['facet.limit'] = $facet['limit'];
				unset($facet['limit']);
			}
			if ($isPikaGroupedWorkIndex) {
				if (isset($searchLibrary) && $searchLibrary->showAvailableAtAnyLocation) {
					$options['f.available_at.facet.missing'] = 'true';
				}
			}

			foreach ($facet as $param => $value) {
				if ($param != 'additionalOptions') {
					$options[$param] = $value;
				}
			}
		}

		if (isset($facet['additionalOptions'])) {
			$options = array_merge($options, $facet['additionalOptions']);
		}

		$timer->logTime("build facet options");

		//Check to see if there are filters we want to show all values for
		if ($isPikaGroupedWorkIndex && isset($filters) && is_array($filters)) {
			foreach ($filters as $key => $value) {
				if (strpos($value, 'availability_toggle') === 0 || strpos($value, 'availability_by_format') === 0) {
					$filters[$key] = '{!tag=avail}' . $value;
				}
			}
		}

		// Build Filter Query
		if (is_array($filters) && count($filters)) {
			$options['fq'] = $filters;
		}

		// Enable Spell Checking
		if ($spell != '') {
			$options['spellcheck']   = 'true';
			$options['spellcheck.q'] = $spell;
			if ($dictionary != null) {
				$options['spellcheck.dictionary'] = $dictionary;
			}
		}

		// Enable highlighting
		if ($this->_highlight) {
			global $solrScope;
			$highlightFields = $fields . ",table_of_contents";

			// Exclude format & format category from highlighting
			$highlightFields = str_replace(",format_$solrScope", '', $highlightFields);
			$highlightFields = str_replace(",format_category_$solrScope", '', $highlightFields);

			$options['hl']                                = 'true';
			$options['hl.fl']                             = $highlightFields;
			$options['hl.simple.pre']                     = '{{{{START_HILITE}}}}';
			$options['hl.simple.post']                    = '{{{{END_HILITE}}}}';
			$options['f.display_description.hl.fragsize'] = 50000;
			$options['f.title_display.hl.fragsize']       = 1000;
			$options['f.title_full.hl.fragsize']          = 1000;
		}

		if ($this->debugSolrQuery) {
//			$solrSearchDebug = print_r($options, true) . "\n";
			$solrSearchDebug = json_encode($options, JSON_PRETTY_PRINT) . "\n";

			if ($filters) {
				$solrSearchDebug .= "\nFilterQuery: ";
				foreach ($filters as $filterItem) {
					$solrSearchDebug .= " $filterItem";
				}
			}

			if ($sort) {
				$solrSearchDebug .= "\nSort: " . $options['sort'];
			}

			if ($this->isPrimarySearch) {
				global $interface;
				$interface->assign('solrSearchDebug', $solrSearchDebug);
			}
		}
		if ($this->debugSolrQuery || $this->debug) {
			$options['debugQuery'] = 'on';
		}

		$timer->logTime('end solr setup');
		$result = $this->_select($method, $options, $returnSolrError);
		$timer->logTime('run select');
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}


	/**
	 * Get filters based on scoping for the search
	 * @param Library  $searchLibrary
	 * @param Location $searchLocation
	 * @return array
	 */
	public function getScopingFilters($searchLibrary, $searchLocation){
		global $solrScope;

		$filter = [];

		//Simplify detecting which works are relevant to our scope
		if ($solrScope){
			$filter[] = "scope_has_related_records:$solrScope";
		}elseif (isset($searchLocation)){
			// A solr scope should be defined usually. It is probably an anomalous situation to fall back to this, and should be fixed; (or noted here explicitly.)
			$this->logger->notice('Global solr scope not set when setting scoping filters');
			$filter[] = "scope_has_related_records:{$searchLocation->code}";
		}elseif (isset($searchLibrary)){
			$filter[] = "scope_has_related_records:{$searchLibrary->subdomain}";
		}

		$blacklistRecords = '';
		if (!empty($searchLocation->recordsToBlackList)) {
			$blacklistRecords = $searchLocation->recordsToBlackList;
		}
		if (!empty($searchLibrary->recordsToBlackList)) {
				$blacklistRecords .= "\n" . $searchLibrary->recordsToBlackList;
		}
		if (!empty($blacklistRecords)){
			$recordsToBlacklist = preg_split('/\s|\r\n|\r|\n/s', $blacklistRecords);
			$blacklist          = '-id:(' . implode(' OR ', $recordsToBlacklist) . ')';
			$filter[]           = $blacklist;
		}

		return $filter;
	}

	/**
	 * Save Record to Database
	 *
	 * @param string $json JSON object to post to Solr
	 * @return  bool|PEAR_Error  Boolean true on success or PEAR_Error
	 * @access  public
	 */
	function saveRecord($json){
		// Note the document to be added needs to be within a JSON array
		// [{doc object}]
		if ($this->debugSolrQuery){
			$this->logger->debug('Adding Record to Solr');
		}

		$result = $this->_update($json);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Delete Record from Database
	 *
	 * @param string $id ID for record to delete
	 * @return  bool|PEAR_Error  Boolean true on success or PEAR_Error
	 * @access  public
	 */
	function deleteRecord($id){
		if ($this->debugSolrQuery){
			$this->logger->debug("Deleting Record $id from Solr");
		}

		$json = '{"delete":"' .$id . '"}';

		$result = $this->_update($json);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Delete Record from Database
	 *
	 * @param string[] $idList Array of IDs for record to delete
	 * @return  bool|PEAR_Error  Boolean true on success or PEAR_Error
	 * @access  public
	 */
	function deleteRecords($idList){
		if ($this->debugSolrQuery){
			$this->logger->debug("Deleting Record list {$idList} from Solr");
		}

		// Delete
		$body = '{"delete":' . json_encode($idList). '}';

		$result = $this->_update($body);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Tell Solr to Commit any received updates
	 *
	 * @return  bool|PEAR_Error  Boolean true on success or PEAR_Error
	 * @access  public
	 */
	function commit(){
		if ($this->debugSolrQuery){
			$this->logger->debug('Sending commit command to Solr');
		}

		$json = '{"commit": {}}';

		$result = $this->_update($json);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Optimize
	 *
	 * @return  bool|PEAR_Error  Boolean true on success or PEAR_Error
	 * @access  public
	 */
	function optimize(){
		if ($this->debugSolrQuery){
			$this->logger->debug('Sending optimize command to Solr');
		}

		$json = '{"optimize": {}}';
//		$json = '{"optimize": {"waitSearcher":false}}';

		$result = $this->_update($json);
		if (PEAR_Singleton::isError($result)){
			PEAR_Singleton::raiseError($result);
		}

		return $result;
	}

	/**
	 * Set the shards for distributed search
	 *
	 * @param array $shards Name => URL array of shards
	 *
	 * @return void
	 * @access public
	 */
	public function setShards($shards){
		$this->_solrShards = $shards;
	}

	/**
	 * Submit REST Request to write data (protected wrapper to allow child classes
	 * to use this mechanism -- we should eventually phase out private _update).
	 *
	 * @param string $xml The command to execute
	 *
	 * @return  bool|PEAR_Error  Boolean true on success or PEAR_Error
	 * @access protected
	 */
//	protected function update($xml){
//		return $this->_update($xml);
//	}

	/**
	 * Strip facet settings that are illegal due to shard settings.
	 *
	 * @param array $value Current facet.field setting
	 * @return array       Filtered facet.field setting
	 * @access private
	 */
	private function _stripUnwantedFacets($value){
		if (!empty($this->_solrShards) && is_array($this->_solrShards)){
			// Load the configuration of facets to strip and build a list of the ones that currently apply
			$facetConfig = getExtraConfigArray('facets');
			if (isset($facetConfig['StripFacets']) && is_array($facetConfig['StripFacets'])){
				$shardNames = array_keys($this->_solrShards);
				$badFacets  = [];
				foreach ($facetConfig['StripFacets'] as $indexName => $facets){
					if (in_array($indexName, $shardNames) === true){
						$badFacets = array_merge($badFacets, explode(',', $facets));
					}
				}

				// No bad facets means no filtering necessary:
				if (empty($badFacets)){
					return $value;
				}

				// Ensure that $value is an array:
				if (!is_array($value)){
					$value = [$value];
				}

				// Rebuild the $value array, excluding all unwanted facets:
				$newValue = [];
				foreach ($value as $current){
					if (!in_array($current, $badFacets)){
						$newValue[] = $current;
					}
				}

				return $newValue;
			}
		}else{
			return $value;
		}
	}

	/**
	 * Submit REST Request to read data
	 *
	 * @param string $method          HTTP Method to use: GET, POST,
	 * @param array  $params          Array of parameters for the request
	 * @param bool   $returnSolrError If Solr reports a syntax error,
	 *                                should we fail outright (false) or
	 *                                treat it as an empty result set with
	 *                                an error key set (true)?
	 * @return  array|PEAR_Error    The Solr response (or a PEAR error)
	 * @access  private
	 */
	private function _select($method = 'GET', $params = array(), $returnSolrError = false){
		global $timer;
		global $memoryWatcher;

		$memoryWatcher->logMemory('Start Solr Select');

		$this->pingServer();

		$params['wt']      = 'json'; // this is the default for modern Solr; We have to keep till Islandora is upgraded.
		$params['json.nl'] = 'arrarr'; // Needed to process faceting; arrarr breaks ordered pairs into a series of arrays

		// Build query string for use with GET or POST, with special handling for repeated parameters
		$query = [];
		if ($params){
			foreach ($params as $function => $value){
				if ($function != ''){
					// Strip custom FacetFields when sharding makes it necessary:
					if ($function === 'facet.field'){
						$value = $this->_stripUnwantedFacets($value);

						// If we stripped all values, skip the parameter:
						if (empty($value)){
							continue;
						}
					}
					if (is_array($value)){
						foreach ($value as $additional){
							//Islandora Solr takes repeated url parameters with out the typical array style. eg. &fq=firstOne&fq=secondOne
							$additional = urlencode($additional);
							$query[]    = "$function=$additional";
						}
					}else{
						$value   = urlencode($value);
						$query[] = "$function=$value";
					}
				}
			}
		}

		// pass the shard parameter along to Solr if necessary:
		if (!empty($this->_solrShards) && is_array($this->_solrShards)){
			$query[] = 'shards=' . urlencode(implode(',', $this->_solrShards));
		}
		$queryString = implode('&', $query);

		if (strlen($queryString) > 8000){
			// For extremely long queries, like lists we will get an error: "URI Too Long"
			// Official limit on JETTY is 8192 bytes
			$method = 'POST';
		}

		$url                 = $this->host . '/select/';
		$this->fullSearchUrl = $url . '?' . $queryString;
		if ($this->debug && $this->debugSolrQuery && $this->isPrimarySearch){
			global $interface;
			if ($interface){
				//Add debug parameter so we can see the explain section at the bottom.
				$debugSearchUrl = $this->host . "/select/?debugQuery=on&" . $queryString;
				$solrQueryDebug = "$method: <a href='" . $debugSearchUrl . "' target='_blank'>$this->fullSearchUrl</a>";
				$interface->assign('solrLinkDebug', $solrQueryDebug);
			}
		}

		// Send Request
		$timer->logTime("Prepare to send request to solr");
		$memoryWatcher->logMemory('Prepare to send request to solr');
		$this->client->setDefaultJsonDecoder(true); // return an associative array instead of a json object
		switch ($method){
			case 'POST':
				$result = $this->client->post($url, $queryString);
				break;
			case 'GET':
			default :
				$result = $this->client->get($url, $queryString);
		}

		$timer->logTime("Send data to solr for select $queryString");
		$memoryWatcher->logMemory("Send data to solr for select $queryString");

		if ($this->client->isError()){
			//TODO: additional handling for curl errors
			return $this->_process($result, $returnSolrError, $queryString);
		}else{
			return $this->_process($result, $returnSolrError, $queryString);
		}
	}

	/**
	 * Submit REST Request to write data
	 *
	 *
	 * @param string $json The command to execute
	 * @return  bool|PEAR_Error    Boolean true on success or PEAR_Error
	 * @access  private
	 * @link https://solr.apache.org/guide/8_7/uploading-data-with-index-handlers.html#uploading-data-with-index-handlers
	 */
	private function _update($json){
		global $timer;

		$this->pingServer();

		$url = $this->host . '/update/';

		if ($this->debugSolrQuery){
			$this->logger->debug('Solr->update: ' . $url, ['json' => $json]);
		}

		// Set up JSON
		$this->client->setHeader('Content-Type', 'application/json;charset=utf-8');

		// Send Request
		$result       = $this->client->post($url, $json);
		$responseCode = $this->client->getHttpStatusCode();

		if ($responseCode == 500 || $responseCode == 400){
			$detail = $this->client->getRawResponse();
			$timer->logTime('Send the update request');

			// Attempt to extract the most useful error message from the response:
			if (preg_match('/<title>(.*)<\/title>/msi', $detail, $matches)){
				//TODO: rewrite this handling
				$errorMsg = $matches[1];
			}else{
				$errorMsg = $detail;
			}
			$this->logger->error('Error updating document', ['json' => $json]);
			return new PEAR_Error('Unexpected response -- ' . $errorMsg);
		}

		if ($this->client->isError()){
			return $result; //TODO: new error process
		}else{
			return true;
		}
	}

	/**
	 * Perform normalization and analysis of Solr return value.
	 *
	 * @param array  $result                    The raw response from Solr
	 * @param bool   $returnSolrError           If Solr reports a syntax error,
	 *                                          should we fail outright (false) or
	 *                                          treat it as an empty result set with
	 *                                          an error key set (true)?
	 * @param string $queryString               The raw query that was sent
	 * @return  array                           The processed response from Solr
	 * @access  private
	 */
	private function _process($result, $returnSolrError = false, $queryString = null)
	{
		//TODO: below parsing for error probably obsolete
		if (is_string($result)) {
			// Catch errors from SOLR
			if (substr(trim($result), 0, 2) == '<h') {
				$errorMsg = substr($result, strpos($result, '<pre>'));
				$errorMsg = substr($errorMsg, strlen('<pre>'), strpos($result, "</pre>"));
				if ($returnSolrError) {
					return ['response' => ['numfound' => 0, 'docs' => []], 'error' => $errorMsg];
				} else {
					$errorMessage = 'Unable to process query ' . ($this->debug ? urldecode($queryString) : '');
					PEAR_Singleton::raiseError(new PEAR_Error($errorMessage . '<br>' .
					 'Solr Returned: ' . $errorMsg));
				}
			} else {
				$result = json_decode($result, true);
				// Curl will give us good array of json data only when response headers indicate that it is json.
				// Curl default decode
			}
		}

		global $timer;
		global $memoryWatcher;
		$memoryWatcher->logMemory('received result from solr ');
		$timer->logTime('received result from solr');

		// Inject highlighting details into results if necessary:
		if (!empty($result['highlighting'])) {
			foreach ($result['response']['docs'] as $key => $current) {
				if (isset($result['highlighting'][$current['id']])) {
					$result['response']['docs'][$key]['_highlighting'] = $result['highlighting'][$current['id']];
				}
			}
			// Remove highlighting section now that we have copied its contents:
			unset($result['highlighting']);
			$timer->logTime("process highlighting");
			$memoryWatcher->logMemory('process highlighting');
		}

		return $result;
	}

	/**
	 * Input Tokenizer
	 *
	 * Tokenizes the user input based on spaces and quotes.  Then joins phrases
	 * together that have an AND, OR, NOT present.
	 *
	 * @param string $input User's input string
	 * @return  array               Tokenized array
	 * @access  public
	 */
	public function tokenizeInput($input){
		// Tokenize on spaces and quotes
		//preg_match_all('/"[^"]*"|[^ ]+/', $input, $words);
		preg_match_all('/"[^"]*"[~[0-9]+]*|"[^"]*"|[^ ]+/', $input, $words);
		$words = $words[0];

		$newWords = array();
		for ($i = 0; $i < count($words); $i++) {
			if (in_array($words[$i], ['OR', 'AND', 'NOT'])) {
				// Join words with AND, OR, NOT
				if (count($newWords)) {
					$newWords[count($newWords) - 1] .= ' ' . trim($words[$i]) . ' ' . trim($words[$i + 1]);
					$i++;
				}
			} else {
				//If we are tokenizing, remove any punctuation
				$tmpWord = trim(preg_replace('/[^\s\-\w.\'aàáâãåäæeèéêëiìíîïoòóôõöøuùúûü&]/', '', $words[$i]));
				if (strlen($tmpWord) > 0) {
					$newWords[] = $tmpWord;
				}
			}
		}

		return $newWords;
	}

	/**
	 * Input Validater
	 *
	 * Cleans the input based on the Lucene Syntax rules.
	 *
	 * @param string $input User's input string
	 * @return  bool                Fixed input
	 * @access  public
	 */
	public function validateInput($input)
	{
		//Get rid of any spaces at the end
		$input = trim($input);

		// Normalize fancy quotes:
		$quotes = array(
		 "\xC2\xAB" => '"', // Â« (U+00AB) in UTF-8
		 "\xC2\xBB" => '"', // Â» (U+00BB) in UTF-8
		 "\xE2\x80\x98" => "'", // â€˜ (U+2018) in UTF-8
		 "\xE2\x80\x99" => "'", // â€™ (U+2019) in UTF-8
		 "\xE2\x80\x9A" => "'", // â€š (U+201A) in UTF-8
		 "\xE2\x80\x9B" => "'", // â€› (U+201B) in UTF-8
		 "\xE2\x80\x9C" => '"', // â€œ (U+201C) in UTF-8
		 "\xE2\x80\x9D" => '"', // â€ (U+201D) in UTF-8
		 "\xE2\x80\x9E" => '"', // â€ž (U+201E) in UTF-8
		 "\xE2\x80\x9F" => '"', // â€Ÿ (U+201F) in UTF-8
		 "\xE2\x80\xB9" => "'", // â€¹ (U+2039) in UTF-8
		 "\xE2\x80\xBA" => "'", // â€º (U+203A) in UTF-8
		);
		$input  = strtr($input, $quotes);

		// If the user has entered a lone BOOLEAN operator, convert it to lowercase
		// so it is treated as a word (otherwise it will trigger a fatal error):
		switch (trim($input)) {
			case 'OR':
				return 'or';
			case 'AND':
				return 'and';
			case 'NOT':
				return 'not';
		}

		// If the string consists only of control characters and/or BOOLEANs with no
		// other input, wipe it out entirely to prevent weird errors:
		$operators = array('AND', 'OR', 'NOT', '+', '-', '"', '&', '|');
		if (trim(str_replace($operators, '', $input)) == '') {
			return '';
		}

		// Translate "all records" search into a blank string
		if (trim($input) == '*:*') {
			return '';
		}

		// Ensure wildcards are not at beginning of input
		if ((substr($input, 0, 1) == '*') ||
		 (substr($input, 0, 1) == '?')) {
			$input = substr($input, 1);
		}

		// Ensure all parens match
		$start = preg_match_all('/\(/', $input, $tmp);
		$end   = preg_match_all('/\)/', $input, $tmp);
		if ($start != $end) {
			$input = str_replace(array('(', ')'), '', $input);
		}

		// Check to make sure we have an even number of quotes
		$numQuotes = preg_match_all('/"/', $input, $tmp);
		if ($numQuotes % 2 != 0) {
			//We have an uneven number of quotes, delete the last one
			$input = substr_replace($input, '', strrpos($input, '"'), 1);
		}

		// Ensure ^ is used properly
		$cnt     = preg_match_all('/\^/', $input, $tmp);
		$matches = preg_match_all('/.+\^[0-9]/', $input, $tmp);

		if (($cnt) && ($cnt !== $matches)) {
			$input = str_replace('^', '', $input);
		}

		// Remove unwanted brackets/braces that are not part of range queries.
		// This is a bit of a shell game -- first we replace valid brackets and
		// braces with tokens that cannot possibly already be in the query (due
		// to ^ normalization in the step above).	Next, we remove all remaining
		// invalid brackets/braces, and transform our tokens back into valid ones.
		// Obviously, the order of the patterns/merges array is critically
		// important to get this right!!
		$patterns = array(
			// STEP 1 -- escape valid brackets/braces
			'/\[([^\[\]\s]+\s+TO\s+[^\[\]\s]+)\]/',
			'/\{([^\{\}\s]+\s+TO\s+[^\{\}\s]+)\}/',
			// STEP 2 -- destroy remaining brackets/braces
			'/[\[\]\{\}]/',
			// STEP 3 -- unescape valid brackets/braces
			'/\^\^lbrack\^\^/',
			'/\^\^rbrack\^\^/',
			'/\^\^lbrace\^\^/',
			'/\^\^rbrace\^\^/'
		);
		$matches  = array(
			// STEP 1 -- escape valid brackets/braces
			'^^lbrack^^$1^^rbrack^^',
			'^^lbrace^^$1^^rbrace^^',
			// STEP 2 -- destroy remaining brackets/braces
			'',
			// STEP 3 -- unescape valid brackets/braces
			'[',
			']',
			'{',
			'}'
		);
		$input    = preg_replace($patterns, $matches, $input);

		//Remove any exclamation marks that Solr will handle incorrectly.
		$input = str_replace('!', ' ', $input);

		//Remove any semi-colons that Solr will handle incorrectly.
		$input = str_replace(';', ' ', $input);

		//Remove any slashes that Solr will handle incorrectly.
		$input = str_replace('\\', ' ', $input);
		$input = str_replace('/', ' ', $input);
		//$input = preg_replace('/\\\\(?![&:])/', ' ', $input);

		//Look for any colons that are not identifying fields


		return $input;
	}

	public function isAdvanced($query)
	{
		// Check for various conditions that flag an advanced Lucene query:
		if ($query == '*:*') {
			return true;
		}

		// The following conditions do not apply to text inside quoted strings,
		// so let's just strip all quoted strings out of the query to simplify
		// detection.	We'll replace quoted phrases with a dummy keyword so quote
		// removal doesn't interfere with the field specifier check below.
		$query = preg_replace('/"[^"]*"/', 'quoted', $query);

		// Check for field specifiers:
		if (preg_match("/([^\(\s\:]+)\s?\:[^\s]/", $query, $matches)) {
			//Make sure the field is actually one of our fields
			$fieldName = $matches[1];
			$fields    = $this->_loadValidFields();
			if (in_array($fieldName, $fields)) {
				return true;
			}
			/*$searchSpecs = $this->_getSearchSpecs();
			if (array_key_exists($fieldName, $searchSpecs)){
				return true;
			}*/
		}

		// Check for parentheses and range operators:
		if (strstr($query, '(') && strstr($query, ')')) {
			return true;
		}
		$rangeReg = '/(\[.+\s+TO\s+.+\])|(\{.+\s+TO\s+.+\})/';
		if (preg_match($rangeReg, $query)) {
			return true;
		}

		// Build a regular expression to detect booleans -- AND/OR/NOT surrounded
		// by whitespace, or NOT leading the query and followed by whitespace.
		$boolReg = '/((\s+(AND|OR|NOT)\s+)|^NOT\s+)/';
		if (!$this->caseSensitiveBooleans) {
			$boolReg .= "i";
		}
		if (preg_match($boolReg, $query)) {
			return true;
		}

		// Check for wildcards and fuzzy matches:
		if (strstr($query, '*') || strstr($query, '?') || strstr($query, '~')) {
			return true;
		}

		// Check for boosts:
		if (preg_match('/[\^][0-9]+/', $query)) {
			return true;
		}

		return false;
	}

	/**
	 * Obtain information from an alphabetic browse index.
	 *
	 * @param string $source          Name of index to search
	 * @param string $from            Starting point for browse results
	 * @param int    $page            Result page to return (starts at 0)
	 * @param int    $page_size       Number of results to return on each page
	 * @param bool   $returnSolrError Should we fail outright on syntax error
	 *                                (false) or treat it as an empty result set with an error key set (true)?
	 *
	 * @return array
	 * @access public
	 */
	public function alphabeticBrowse($source, $from, $page, $page_size = 20, $returnSolrError = false){
		$this->pingServer();

		$url        = $this->host . "/browse";
		$offset     = $page * $page_size;
		$parameters = [
			'from'    => $from,
			'json.nl' => 'arrarr',
			'offset'  => $offset,
			'rows'    => $page_size,
			'source'  => $source,
			'wt'      => 'json',
		];

		$result = $this->client->get($url, $parameters);

		if ($this->client->isError()){
			return $result;
		}else{
			return $this->_process($result, $returnSolrError);
		}
	}

	/**
	 * Convert a terms array (where every even entry is a term and every odd entry
	 * is a count) into an associate array of terms => counts.
	 *
	 * @param array $in Input array
	 *
	 * @return array    Processed array
	 * @access private
	 */
	private function _processTerms($in){
		$out = array();

		for ($i = 0;$i < count($in);$i += 2){
			$out[$in[$i]] = $in[$i + 1];
		}

		return $out;
	}

	/**
	 * Extract terms from the Solr index.
	 *
	 * @param string $field           Field to extract terms from
	 * @param string $start           Starting term to extract (blank for beginning
	 *                                of list)
	 * @param int    $limit           Maximum number of terms to return (-1 for no
	 *                                limit)
	 * @param bool   $returnSolrError Should we fail outright on syntax error
	 *                                (false) or treat it as an empty result set with an error key set (true)?
	 *
	 * @return array                  Associative array parsed from Solr JSON
	 * response; meat of the response is in the ['terms'] element, which contains
	 * an index named for the requested term, which in turn contains an associative
	 * array of term => count in index.
	 * @access public
	 */
	public function getTerms($field, $start, $limit, $returnSolrError = false){
		$this->pingServer();
		$url = $this->host . '/terms';

		$parameters = [
			'terms'            => 'true',
			'terms.fl'         => $field,
			'terms.lower.incl' => 'false',
			'terms.lower'      => $start,
			'terms.limit'      => $limit,
			'terms.sort'       => 'index',
			'wt'               => 'json'
		];

		$result = $this->client->get($url, $parameters);

		if ($this->client->isError()){
			return $result;
		}else{
			// Process the JSON response:
			$data = $this->_process($result, $returnSolrError);

			// Tidy the data into a more usable format:
			if (isset($data['terms'])){
				$data['terms'] = array(
					$data['terms'][0] => $this->_processTerms($data['terms'][1])
				);
			}
			return $data;
		}
	}

	public function setSearchSource($searchSource){
		$this->searchSource = $searchSource;
	}

	private function _loadDynamicFields(){
		global $solrScope;
		$fields = $this->cache->get("schema_dynamic_fields_$solrScope");
		if (empty($fields) || isset($_REQUEST['reload'])){
			global $configArray;
			$schemaUrl = $configArray['Index']['url'] . '/grouped/admin/file?file=schema.xml&contentType=text/xml;charset=utf-8';
			$schema    = simplexml_load_file($schemaUrl);
			$fields    = [];
			/** @var SimpleXMLElement $field */
			foreach ($schema->fields->dynamicField as $field){
				$fields[] = substr((string)$field['name'], 0, -1);
			}
			$this->cache->set("schema_dynamic_fields_$solrScope", $fields, 86400);
		}
		return $fields;
	}

	protected function _loadValidFields(){
		global $solrScope;
		if (isset($_REQUEST['allFields'])) {
			return ['*'];
		}
		$schemaCacheKey = "schema_fields_{$this->index}";
		if ($this->index == 'grouped'){
			$schemaCacheKey .= "_$solrScope";
		}
		$fields = $this->cache->get($schemaCacheKey);
		if (!$fields || isset($_REQUEST['reload'])) {
			global $configArray;
			$schemaUrl =  $this->host . '/admin/file?file=schema.xml&contentType=text/xml;charset=utf-8';
			$schema    = simplexml_load_file($schemaUrl);
			$fields    = [];
			/** @var SimpleXMLElement $field */
			foreach ($schema->fields->field as $field){
				//print_r($field);
				$fields[] = (string)$field['name'];
			}
			if ($this->index == 'grouped' && !empty($solrScope)) {
				// Only process for grouped work index where dymanic fields have the wildcard at the end
				// islandora dynamic fields start with the wildcard eg *_s
				foreach ($schema->fields->dynamicField as $field) {
					$fields[] = substr((string)$field['name'], 0, -1) . $solrScope;
				}
			}
			$this->cache->set($schemaCacheKey, $fields, 86400);
		}
		return $fields;
	}

	/**
	 * Capitalize boolean operators in a query string to allow case-insensitivity.
	 *
	 * @access  public
	 * @param string $query The query to capitalize.
	 * @return  string                  The capitalized query.
	 */
	private static function capitalizeBooleans($query){
		// This lookAhead detects whether or not we are inside quotes; it
		// is used to prevent switching case of Boolean reserved words
		// inside quotes, since that can cause problems in case-sensitive
		// fields when the reserved words are actually used as search terms.
		$lookAhead = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';
		$regs      = [
			"/\\s+AND\\s+{$lookAhead}/i",
			"/\\s+OR\\s+{$lookAhead}/i",
			"/(\\s+NOT\\s+|^NOT\\s+){$lookAhead}/i",
			"/\\(NOT\\s+{$lookAhead}/i"];
		$replace   = [' AND ', ' OR ', ' NOT ', '(NOT '];
		return trim(preg_replace($regs, $replace, $query));
	}

}