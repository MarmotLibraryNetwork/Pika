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
	 *  An array of protected word phrases that do not get altered by solr and should not be mundged in this driver
	 * for search queries
	 *
	 * @var bool|array
	 */
	private $_protectedWords = false;
	private $_protectedWordsFile = '../../data_dir_setup/solr_master/grouped/conf/protwords.txt';

	/**
	 * Should boolean operators in the search string be treated as
	 * case-insensitive (false), or must they be ALL UPPERCASE (true)?
	 */
	private $caseSensitiveBooleans = true;

	/**
	 * Should range operators (i.e. [a TO b]) in the search string be treated as
	 * case-insensitive (false), or must they be ALL UPPERCASE (true)?  Note that
	 * making this setting case-insensitive not only changes the word "TO" to
	 * uppercase but also inserts OR clauses to check for case-insensitive matches
	 * against the edges of the range...  i.e. ([a TO b] OR [A TO B]).
	 */
//	private $_caseSensitiveRanges = true;

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
//		if (isset($searchSettings['General']['case_sensitive_ranges'])){
//			$this->_caseSensitiveRanges = $searchSettings['General']['case_sensitive_ranges'];
//		}

		// Turn on highlighting if the user has requested highlighting or snippet
		// functionality:
		$highlight = $configArray['Index']['enableHighlighting'];
		$snippet   = $configArray['Index']['enableSnippets'];
		if ($highlight || $snippet){
			$this->_highlight = true;
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
//	public function hasCaseSensitiveRanges(){
//		return $this->_caseSensitiveRanges;
//	}

	/**
	 * Support method for _getSearchSpecs() -- load the specs from cache or disk.
	 *
	 * @return void
	 * @access private
	 */
	private function _loadSearchSpecs(){
		global $configArray;
		$results = $this->debugSolrQuery ? null : $this->cache->get('searchSpecs');
		if (empty($results)) {
			$searchSpecs = file_get_contents($this->searchSpecsFile);
			$searchSpecs = preg_replace('/\s*(?!<\")\/\*[^\*]+\*\/(?!\")\s*/', '', $searchSpecs); // Remove any text within /**/ as comments to ignore
			$results     = json_decode($searchSpecs, true);
			if (is_array($results)){
				$this->cache->set('searchSpecs', $results, $configArray['Caching']['searchSpecs']);
			} else {
				$this->logger->error('Failed to parse search specification json file.', [json_last_error_msg()]);
				$results = false;
			}
		}
		$this->_searchSpecs = $results;

		// Populate the protectedWords array as we load the searchSpecs since both will be used to build solr queries
		if ($this->_protectedWords === false){
			$protectedWords = $this->debugSolrQuery ? null : $this->cache->get('searchProtectedWords');
			if (empty($protectedWords) && file_exists($this->_protectedWordsFile)){
				$protectedWords = [];
				$temp         = file_get_contents($this->_protectedWordsFile);
				if ($temp){
					foreach (explode("\n", $temp) as $line){
						if (strpos($line, '#') !== 0){ // ignore commments
							$line = trim($line);
							if (!empty($line)){
								$protectedWords[] = $line;
							}
						}
					}
					$this->cache->set('searchProtectedWords', $protectedWords, $configArray['Caching']['searchSpecs']);
				}
			}
			$this->_protectedWords = $protectedWords;
		}
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
				//TODO: because the default solr operator is OR now, the separator here can be a simple space character
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
			//If we have more than 50 ids, we will need to make multiple calls and
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
	 * Retrieves Solr Documents for an array of grouped Work Ids
	 * @param string[] $ids The groupedWork Id of the Solr document to retrieve
	 * @param string[]|null $filters Any search filters to apply to query
	 * @param int $batchSize
	 * @param string $idFieldToReturn
	 * @return array of the filtered ids
	 */
	function getFilteredIds(array $ids, array $filters = null, int $batchSize = 100, string $idFieldToReturn = 'id'){
		$solrDocArray = [];
		$numIds       = count($ids);
		if ($numIds) {

			$this->pingServer();
			$this->client->setDefaultJsonDecoder(true); // return an associative array instead of a json object

			//TODO: this comment doesn't appear to accurate any longer
			//Solr does not seem to be able to return more than 50 records at a time,
			//If we have more than 50 ids, we will need to make multiple calls and
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
				$idString = implode(',', $tmpIds);
				$options  = [
					'ids' => $idString,
					'fl'  => $idFieldToReturn,
					'wt'  => 'json',
				];

				if (!empty($filters)){
					$options['fq'] = $filters;
				}
				// Build query string for use with GET or POST, with special handling for repeated parameters
				$queryString = $this->buildSolrQueryString($options);

				// Send Request
				if (strlen($queryString) > 8000){
					// For extremely long queries, like lists we will get an error: "URI Too Long"
					$result = $this->client->post($this->host . '/get', $queryString);
				}else{
					$result = $this->client->get($this->host . '/get', $queryString);
				}

				if ($this->client->isError()) {
					PEAR_Singleton::raiseError($this->client->getErrorMessage());
				} else {
					$result       = $this->_process($result);
					$tempArray    = array_column($result['response']['docs'], $idFieldToReturn);
					$solrDocArray = array_merge($solrDocArray, $tempArray);
				}

				if (!$lastBatch) {
					$startIndex = $endIndex;
				}
			} while (!$lastBatch);
		}
		return $solrDocArray;
	}

	/**
	 * Retrieves Solr Documents for an array of Islandora PIDs
	 *
	 * The getFilteredIds() doesn't filter with the special /get request and ids field for
	 * the current version of Islandora so we have to do a regular /select request with a query against
	 * PID field
	 *
	 * @param string[] $ids The Id of the Solr document to retrieve
	 * @param null $filters Any search filters to apply to query
	 * @param int $batchSize
	 * @param string $idFieldToReturn
	 * @return array of the filtered PIDs
	 */
	function getFilteredPIDs(array $ids, array $filters = null, int $batchSize = 100, string $idFieldToReturn = 'PID'){
		$solrDocArray = [];
		$numIds       = count($ids);
		if ($numIds) {

			$this->pingServer();
			$this->client->setDefaultJsonDecoder(true); // return an associative array instead of a json object

			//TODO: this comment doesn't appear to accurate any longer
			//Solr does not seem to be able to return more than 50 records at a time,
			//If we have more than 50 ids, we will need to make multiple calls and
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
				$idString = implode(' ', $tmpIds);
				$idString = str_replace(':', '\:', $idString); // escape colon characters in PIDs
				$options = [
					'q'    => "$idFieldToReturn:($idString)",
					'q.op' => 'OR',
					'fl'   => $idFieldToReturn,
					'rows' => $batchSize, // overrides the default value of 10 (for islandora solr) 20 (for catalog solr)
					'wt'   => 'json'
				];

				if (!empty($filters)){
					$options['fq'] = $filters;
				}
				// Build query string for use with GET or POST, with special handling for repeated parameters
				$queryString = $this->buildSolrQueryString($options);

				// Send Request
				if (strlen($queryString) > 8000){
					// For extremely long queries, like lists we will get an error: "URI Too Long"
					$result = $this->client->post($this->host . '/select', $queryString);
				}else{
					$result = $this->client->get($this->host . '/select', $queryString);
				}

				if ($this->client->isError()) {
					PEAR_Singleton::raiseError($this->client->getErrorMessage());
				} else {
					$result       = $this->_process($result);
					$tempArray    = array_column($result['response']['docs'], $idFieldToReturn);
					$solrDocArray = array_merge($solrDocArray, $tempArray);
				}

				if (!$lastBatch) {
					$startIndex = $endIndex;
				}
			} while (!$lastBatch);
		}
		return $solrDocArray;
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
		$result = $this->search($query, null, null, 0, $limit, ['field' => $field, 'limit' => $limit]);
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
	 * @param array $mungeRules            The SearchSpecs-derived structure or substructure defining the search,
	 *                                     derived from the json file
	 * @param array $mungedValues          The values for the various munge types
	 * @param bool $isKeyWordSearchSpec    Is this the main search type keyword (with the most elaborate search spec)
	 * @param string $joiner               Joiner of sub-queries  eg AND OR
	 *
	 * @return  string            A search string suitable for query Solr with
	 */
	private function _applySearchSpecs($mungeRules, $mungedValues, bool $isKeyWordSearchSpec = false, $joiner = 'OR'){
		$clauses = [];
		foreach ($mungeRules as $field => $clauseArray) {
			if (is_numeric($field)){
				$sw           = array_shift($clauseArray); // shift off the join string and weight
				$internalJoin = ' ' . $sw[0] . ' ';
				$searchString = '(' . $this->_applySearchSpecs($clauseArray, $mungedValues, $isKeyWordSearchSpec, $internalJoin) . ')'; // Build it up recursively
				$weight       = $sw[1];                                                                           // ...and add a weight if we have one
				if (!empty($weight)){
					$searchString .= '^' . $weight;
				}
				// push it onto the stack of clauses
				$clauses[] = $searchString;
			} else {
				global $solrScope;
				if ($solrScope){
					// $dynamicFields = $this->_loadDynamicFields();
					// if (in_array($field.'_', $dynamicFields)){
					// if we ever expand the search spec to any other dynamic fields, use above lines
					if ($field == 'local_callnumber' || $field == 'local_callnumber_left' || $field == 'local_callnumber_exact'){
						$field .= '_' . $solrScope;
					}
				}

				// Otherwise, we've got a (list of) [munge, weight] pairs to deal with
				foreach ($clauseArray as $spec) {
					$fieldValue = $mungedValues[$spec[0]];

					switch ($field){
						case 'isbn':
						case 'canceled_isbn':
						case 'primary_isbn':
							if (!preg_match('/^((?:\sOR\s)?["(]?\d{9,13}X?[\s")]*)+$/', $fieldValue)){
								//Match 9-13 digits with optional X at the end eg isbn structures
								// Or phrases like ' OR [isbn]'  or ' OR ([isbn])' or ' OR "[isbn]"' where [isbn] is an actual isbn number
								// Note the preceding space in front of the OR is needed
								// TODO: note when these OR checks are needed
								continue 2;
							}else{
								// Now only 13 digit ISBN numbers are in the search index so just search for those
//									require_once ROOT_DIR . '/sys/ISBN/ISBN.php';
//									$isbn   = new ISBN($fieldValue);
//									$isbn13 = $isbn->get13();
//									if ($isbn13){
//										$fieldValue = $isbn13;
//									}else {
								if (strlen($fieldValue) == 10){
									require_once ROOT_DIR . '/sys/ISBN/ISBNConverter.php';
									$temp = ISBNConverter::convertISBN10to13($fieldValue);
									if (!empty($temp)){
										$fieldValue = $temp;
									}
								}

//									$isbn10 = $isbn->get10();
//									$isbn13 = $isbn->get13();
//									if ($isbn10 && $isbn13){
//										$fieldValue = $isbn10 . ' OR ' . $isbn13;
//									}

							}
							break;
						case 'shortId':
							// Genealogy Id number field
							if (!ctype_digit($fieldValue)){
								// If the search phrase is'nt all numbers, don't add this clause to the query
								continue 2;
							}
					}

					if ($isKeyWordSearchSpec){
						// Only do these field skips when working with the Keyword search spec
						switch ($field){
							case 'id':
								if (!preg_match('/^"?(list\d+|[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})"?$/i',
									// Match list Id number OR grouped work permanent id
									$fieldValue)){
									continue 2;
								}
								break;
							case 'alternate_ids': //todo: this doesn't have all Id schemes included
								if (!preg_match('/^"?(\d+|.?[boi]\d+x?|[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}|MWT\d+|CARL\d+)"?$/i',
									// Match all digits OR Sierra-style bib, item or order numbers OR grouped work or overdrive Ids OR hoopla record numbers OR CARLX record Ids
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
					}

					// build a string like title:("one two")
					$searchString = $field . ':(' . $fieldValue . ')';
					//Check to make sure we don't already have this clause.
					//  We will get the same clause if we have a single word and are doing different munges
					if (in_array($searchString, $clauses)){
						continue 2;
					}
					// The below might match against longer clauses that begin with the current one.
					// That might be a good idea or a bad one. Replaced with the above check instead
//					foreach ($clauses as $clause) {
//						if (strpos($clause, $searchString) === 0) {
//							continue 2;
//						}
//					}

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

	private $_munges = [];
	/**
	 * Given a field name and search string, return an array containing munged
	 * versions of the search string for use in _applySearchSpecs().
	 *
	 * @access  private
	 * @param string $lookfor              The string to search for in the field
	 * @param bool   $isBasicSearchQuery   Is $lookfor a basic (true) or advanced (false) query?
	 * @return  array                      Array for use as _applySearchSpecs() values param
	 */
	private function _buildMungeValues($lookfor, $isBasicSearchQuery = true){
		$key = $lookfor . ($isBasicSearchQuery ? '1' : '0');
		if (!isset($this->_munges[$key])){
			if ($isBasicSearchQuery){
				$cleanedQuery = str_replace(':', ' ', $lookfor);

				// Tokenize Input
				$tokenized = $this->tokenizeInput($cleanedQuery);

				// Create AND'd and OR'd queries
				$andQuery = implode(' AND ', $tokenized);
				$orQuery  = implode(' OR ', $tokenized);

				// Build possible inputs for searching:
				$mungedValues              = [];
				$mungedValues['onePhrase'] = '"' . str_replace('"', '', implode(' ', $tokenized)) . '"';
				$numTokens                 = count($tokenized);
				if ($numTokens > 1){
					$mungedValues['proximal'] = $mungedValues['onePhrase'] . '~10';
				}elseif ($numTokens == 1){
					$mungedValues['proximal'] = $tokenized[0];
				}else{
					$mungedValues['proximal'] = '';
				}

				$mungedValues['exact']       = str_replace(':', '\\:', $lookfor);
				$mungedValues['exactQuoted'] = '"' . $lookfor . '"';
				$mungedValues['and']         = $andQuery;
				$mungedValues['or']          = $orQuery;

				// The singleWordRemoval munge is only used in the Keyword search spec against the title_proper and title_full fields.  pascal 10/15/2021
				if ($numTokens < 5){
					$mungedValues['singleWordRemoval'] = $mungedValues['onePhrase'];
				}else{
					$singleWordRemoval = [];
					for ($i = 0;$i < $numTokens;$i++){
						$newTerm = [];
						for ($j = 0;$j < $numTokens;$j++){
							if ($j != $i){
								$newTerm[] = $tokenized[$j];
							}
						}
						$singleWordRemoval[] = '"' . implode(' ', $newTerm) . '"';
					}
					$mungedValues['singleWordRemoval'] = implode(' OR ', $singleWordRemoval);
				}

			}else{
				//TODO: this block is never used or called  Should it?  Did it?

				// If we're skipping tokenization, we just want to pass $lookfor through
				// unmodified (it's probably an advanced search that won't benefit from
				// tokenization).	We'll just set all possible values to the same thing,
				// except that we'll try to do the "one phrase" in quotes if possible.
				$onePhrase    = strstr($lookfor, '"') ? $lookfor : '"' . $lookfor . '"';
				$mungedValues = [
					'exact'             => $onePhrase,
					'onePhrase'         => $onePhrase,
					'and'               => $lookfor,
					'or'                => $lookfor,
					'proximal'          => $lookfor,
					'singleWordRemoval' => $onePhrase,
					'exactQuoted'       => '"' . $lookfor . '"',
				];
			}

			// This sets up search phrases to be used against the text-exact and text-left types of solr fields.
			// Remove special characters and then replace any doubled space characters with a single one
			// (also convert other space characters to the literal space character.)
			$mungedValues['anchoredSearchFieldMunge'] = '"' . preg_replace('/\s+/', ' ', str_replace(['*', '"', ':', '/'], '', $lookfor)) . '"'; // same as above but replaces wildcard character with space as well

			$this->_munges[$key] = $mungedValues;
		}
		return $this->_munges[$key];
	}

	/**
	 * Given a field name and search string, expand this into the necessary Lucene
	 * query to perform the specified search on the specified field(s).
	 *
	 * @access  public            Has to be public since it can be called as part of a preg replace statement
	 * @param string $searchSpecToUse    The YAML search spec field name to search
	 * @param string $lookfor  The string to search for in the field
	 * @param bool   $tokenize Should we tokenize $lookfor or pass it through?
	 * @return  string              The query
	 */
	public function _buildQueryComponent($searchSpecToUse, $lookfor, $tokenize = true){
		// Load the YAML search specifications:
		$ss = $this->_getSearchSpecs($searchSpecToUse);

		if ($searchSpecToUse == 'AllFields'){
			$searchSpecToUse = 'Keyword';
		}

		// If we received a field spec that wasn't defined in the YAML file,
		// let's try simply passing it along to Solr.
		if ($ss === false){
			$allFields = $this->_loadValidFields();
			if (in_array($searchSpecToUse, $allFields)){
				return $searchSpecToUse . ':(' . $lookfor . ')';
			}
			$dynamicFields = $this->_loadDynamicFields();
			global $solrScope;
			foreach ($dynamicFields as $dynamicField){
				if ($dynamicField . $solrScope == $searchSpecToUse){
					return $searchSpecToUse . ':(' . $lookfor . ')';
				}
			}
			//Not a search by field
			//TODO: note kinds of situations that fall here
			return '"' . $searchSpecToUse . ':' . $lookfor . '"';
		}

		$mungedValues = $this->_buildMungeValues($lookfor, $tokenize);

		// Apply the $searchSpecs property to the data:
		$baseQuery = $this->_applySearchSpecs($ss['QueryFields'], $mungedValues, $searchSpecToUse == 'Keyword');

		// Apply filter query if applicable:
		if (isset($ss['FilterQuery'])){
			return "({$baseQuery}) AND ({$ss['FilterQuery']})";
//			return  empty($baseQuery) ? "({$ss['FilterQuery']})" : "($baseQuery) AND ({$ss['FilterQuery']})";
		}

//		return "($baseQuery)";
		return empty($baseQuery) ? '' : "($baseQuery)";
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
	private function _buildAdvancedQuery($handler, $query){
		// Special case -- if the user wants all records but the current handler
		// has a filter query, apply the filter query:
		if (trim($query) == '*:*'){
			$ss = $this->_getSearchSpecs($handler);
			if (isset($ss['FilterQuery'])){
				return $ss['FilterQuery'];
			}
		}

		// Strip out any colons that are NOT part of a field specification:
		$queryBefore = $query;
		$query = preg_replace('/(:\s+|\s+:)/', ' ', $query);

		// If the query already includes field specifications, we can't easily
		// apply it to other fields through our defined handlers, so we'll leave
		// it as-is:
		if (strstr($query, ':')){
			return $query;
		}

		// Convert empty queries to return all values in a field:
		if (empty($query)){
			$query = '[* TO *]';
		}

		// If the query ends in a question mark, the user may not really intend to
		// use the question mark as a wildcard -- let's account for that possibility
		if (substr($query, -1) == '?'){
			$query = "({$query}) OR (" . substr($query, 0, strlen($query) - 1) . ")";
		}

		// We're now ready to use the regular YAML query handler but with the
		// $tokenize parameter set to false so that we leave the advanced query
		// features unmolested.
		return $this->_buildQueryComponent($handler, $query, false);
	}

	private array $builtQueries = [];

	/** Build Query string from search parameters
	 *
	 * @access  public
	 * @param string[] $search An array of search parameters
	 * @param boolean $forDisplay Whether or not the query is being built for display purposes
	 * @return  string              The query
	 * @throws  object              PEAR Error
	 */
	function buildQuery($search, $forDisplay = false){
		$key = serialize([$search, $forDisplay]);
		if (isset($this->builtQueries[$key])){
			return $this->builtQueries[$key];
		}
		$memcacheKey = 'solrQuery' . $this->index . $key;
		$query       = $this->debugSolrQuery ? '' : $this->cache->get($memcacheKey); // skip memcache when debugging solr queries
		if (!empty($query)){
			return $query;
		}

		$groups   = [];
		$excludes = [];
		if (is_array($search)) {
			foreach ($search as $params) {
				//Check to see if need to break up a basic search into an advanced search
//				$modifiedQuery = false;
//				$that          = $this;
//				if (isset($params['lookfor']) && !$forDisplay) {
//					//TODO: note examples that are supposed to be converted
//					// Note: this seems to be meant as an Advanced Search Form public query detector and parser, but doesn't work
//					// and is not triggered by many Advanced Search Form public queries
//					$lookfor       = preg_replace_callback(
//						'/([\\w-]+):([\\w\\d\\s"-]+?)\\s?(?<=\b)(AND|OR|AND NOT|OR NOT|\\)|$)(?=\b)/',
//						//     '/(\\w+):([\\w\\d\\s]+?)(\\sAND|OR|AND NOT|OR NOT|\\))/
//						function ($matches) use ($that) {
//							$field    = $matches[1];
//							$lookfor  = $matches[2];
//							$newQuery = $that->_buildQueryComponent($field, $lookfor);
//							return $newQuery . $matches[3];
//						},
//						$params['lookfor']
//					);
//					$modifiedQuery = $lookfor != $params['lookfor'];
//				}
//				if ($modifiedQuery) {
//					//This is an advanced search
//					$query = $lookfor;
//				} else {

					// Advanced Search
					if (isset($params['group'])) {
						$thisGroup = [];
						// Process each search group
						foreach ($params['group'] as $group) {
							// Build this group individually as a basic search
//							if (strpos($group['lookfor'], ' ') > 0) {
//								$group['lookfor'] = '(' . $group['lookfor'] . ')';
//							}
							if ($group['field'] == 'AllFields') {
								$group['field'] = 'Keyword';
							}
							$thisGroup[] = $this->buildQuery([$group]);
						}
						// Is this an exclusion (NOT) group or a normal group?
						if ($params['group'][0]['bool'] == 'NOT') {
							$excludes[] = implode(' OR ', $thisGroup);
						} else {
							$groups[] = implode(' ' . $params['group'][0]['bool'] . ' ', $thisGroup);
						}
					}

					// Basic Search or a basic search phrase (sub-clause of an advanced search)
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

							// Examples of this :
							// when the search phrase contains a search field reference eg title_exact:test
							$query .= $lookfor;
							//}
						}
					}
//				}
			}
		}

		// Put our advanced search together
		if (count($groups) > 0) {
			// TODO: Only surround with parentheses when  count($groups) > 1   ???
			$searchPhrasesConnector = ') ' . $search[0]['join'] . ' (';
			$query     = '(' . implode($searchPhrasesConnector, $groups) . ')';
		}
		// and concatenate exclusion after that
		if (count($excludes) > 0) {
			$query .= ' NOT ((' . implode(') OR (', $excludes) . '))';
		}

		// Ensure we have a valid query to this point
		if (empty($query)) {
			$query = '*:*';
		}

		$this->cache->set($memcacheKey, $query, 60);
		$this->builtQueries[$key] = $query;
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

		$sortDirection = strtolower(trim($sortDirection));

		// Translate special sort values into appropriate Solr fields:
		switch ($sortField){
			case 'year':
			case 'publishDate':
				if ($sortDirection != 'asc' && $sortDirection != 'desc'){
					// Default sort direction for catalog date should be descending rather than ascending for other options
					$sortDirection = 'desc';
				}
				return "publishDateSort $sortDirection,title_sort asc,authorStr asc";
			case 'author':
				if ($sortDirection != 'asc' && $sortDirection != 'desc'){
					$sortDirection = 'asc';
				}
				return "authorStr $sortDirection,title_sort asc";
			case 'title':
				if ($sortDirection != 'asc' && $sortDirection != 'desc'){
					$sortDirection = 'asc';
				}
				return "title_sort $sortDirection,authorStr asc";
			case 'callnumber_sort':
				$searchLibrary = Library::getSearchLibrary($this->searchSource);
				if ($searchLibrary != null){
					$sortField = 'callnumber_sort_' . $searchLibrary->subdomain;
				}
				if ($sortDirection != 'asc' && $sortDirection != 'desc'){
					$sortDirection = 'asc';
				}
				return $sortField . ' ' . $sortDirection;
			default:
				if ($sortDirection != 'asc' && $sortDirection != 'desc'){
					$sortDirection = 'asc';
				}
				return $sortField . ' ' . $sortDirection;
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
		$returnSolrError = false,
		$boost = null
	) {
		global $timer;
		global $configArray;
		// Query String Parameters
		$options = [
			'q'      => $query,
			'q.op'   => 'AND',
			'rows'   => $limit,
			'start'  => $start,
		];

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
		if (preg_match('/\".+?\"/', $query)) {
			// If the search query contains quoted phrase, switch from the regular search handler
			// to a textProper version of the search handler
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
				case 'Series':
					$handler = 'SeriesProper';
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


		//Apply automatic boosting (only to biblio and econtent queries)
		$isPikaGroupedWorkIndex = preg_match('/.*(grouped).*/i', $this->host);
		if ($isPikaGroupedWorkIndex) {
			if (!empty($boost)){
					if (!empty($options['defType']) && $options['defType'] == 'dismax'){
						$options['bf'] = $boost;
					} else{
						// Set the boosting query for the standard query parser
						// https://solr.apache.org/guide/8_8/other-parsers.html#boost-query-parser

						$options['q'] = "{!boost b=$boost} " . $options['q'];
					}
				$timer->logTime('apply boosting');
			}
		}

		// Build Filter Query
		if (!empty($filter)) {
			if (!is_array($filter)) {
				//TODO: does this happen? how so?
				$filter = [$filter];
			}
			$options['fq'] = $filter;
		}

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

		// Build Facet Options
		if (!empty($facet['field']) && $configArray['Index']['enableFacets']) {
			$options['facet']          = 'true';
			$options['facet.mincount'] = 1;
			$options['facet.method']   = 'fcs';
			$options['facet.threads']  = 25;

			//Determine which fields should be treated as enums
			global $solrScope;
			if ($isPikaGroupedWorkIndex) {
				//TODO: have to change after location scopes are removed
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

			if (isset($facet['additionalOptions'])) {
				//Currently this is only used by the Archive Mapped Timeline Exhibits (Collections)
				$options = array_merge($options, $facet['additionalOptions']);
				unset($facet['additionalOptions']);
			}

			foreach ($facet as $param => $value){
				$options[$param] = $value;
			}

		}

		$timer->logTime('build facet options');

		//Check to see if there are filters we want to show all values for
		if ($isPikaGroupedWorkIndex && isset($filters) && is_array($filters)) {
			foreach ($filters as $key => $value) {
				if (strpos($value, 'availability_toggle') === 0 || strpos($value, 'availability_by_format') === 0) {
					$filters[$key] = '{!tag=avail}' . $value;
				}
			}
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
			$highlightFields = $fields . ',table_of_contents';

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

		if ($this->debugSolrQuery && $this->isPrimarySearch){
			$solrSearchDebug = 'Search Query: ' . $options['q'] . "\n";

			if ($filter){
				$solrSearchDebug .= "\nFilterQuery: ";
				foreach ($filter as $filterItem){
					$solrSearchDebug .= " $filterItem";
				}
			}

			if ($sort){
				$solrSearchDebug .= "\nSort: " . $options['sort'];
			}
			$solrSearchDebug .= "\n\nSearch Options JSON: \n" . json_encode($options, JSON_PRETTY_PRINT) . "\n";

			global $interface;
			$interface->assign('solrSearchDebug', $solrSearchDebug);
		}
		if ($this->debugSolrQuery || $this->debug) {
			$options['debugQuery'] = 'on';
			$options['indent']     = 'yes';
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
	private function _select($method = 'GET', $params = [], $returnSolrError = false){
		global $timer;
		global $memoryWatcher;

		$memoryWatcher->logMemory('Start Solr Select');

		$this->pingServer();

		$params['q.op']    ??= 'AND';    // This used to be set in the schema, but the parameter is obsolete.
		// All of our query creation, processing, and term munging seems to be built on this assumption that terms are ANDed together.
		// The Lucene (and therefore Solr) default is to "OR" terms together.
		$params['wt']      = 'json';   // this is the default for modern Solr; We have to keep till Islandora is upgraded.
		$params['json.nl'] = 'arrarr'; // Needed to process faceting; arrarr breaks ordered pairs into a series of arrays

		$queryString = $this->buildSolrQueryString($params);

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
		$timer->logTime('Prepare to send request to solr');
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

	public function callRequestHandler(string $requestHandler, $options = []){
		$url    = $this->host . '/' . $requestHandler;
		if (!empty($options['fq'])){
			// Since http_build_query() builds url parameters as array eg. fq[0]=foo&fq[1]=bla
			// Solr does not seem to be able to handle this; and so our filter queries don't get applied.
			// The below builds repeating url parameters that solr accepts eg. fq=foo&fq=bla  See also  _select()
			$url .= '?fq=' . urlencode(array_shift($options['fq']));
			foreach ($options['fq'] as $option){
				$url .= '&fq=' . urlencode($option);
			}
			unset($options['fq']);
		}
		$this->client->setDefaultJsonDecoder(true); // return an associative array instead of a json object
		$result = $this->client->get($url, $options);
		if ($this->client->isError()) {
			PEAR_Singleton::raiseError($this->client->getErrorMessage());
		}
		return $result;
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
	private function _process($result, $returnSolrError = false, $queryString = null){
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
		$memoryWatcher->logMemory('received result from solr');
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

		$newWords = [];
		for ($i = 0; $i < count($words); $i++) {
			if (in_array($words[$i], ['OR', 'AND', 'NOT'])) {
				// Join words with AND, OR, NOT
				if (count($newWords)) {
					$newWords[count($newWords) - 1] .= ' ' . trim($words[$i]) . ' ' . trim($words[$i + 1]);
					$i++;
				}
			} elseif (!in_array($words[$i], $this->_protectedWords)) {
				//If we are tokenizing, remove any punctuation
				$tmpWord = trim(preg_replace('/[^\s\-\w.\'&]/u', '', $words[$i]));
				// Removes any character that is NOT : whitespace, word characters, a period, a dash -, an apostrophe ', or an ampersand &
				// NOTE: the u modifier causes pattern and subject strings to be treated as UTF-8.
				// (this causes diacritical characters to be included in word character pattern also)
				// Keep the dash to preserve range searches
				//
				if (strlen($tmpWord) > 0) {
					$newWords[] = $tmpWord;
				}
			} else {
				$newWords[] = $words[$i];
			}
		}

		return $newWords;
	}

	/**
	 * Input Validator
	 *
	 * Cleans the input based on the Lucene Syntax rules.
	 *
	 * @param string $input User's input string
	 * @return  bool                Fixed input
	 * @access  public
	 */
	public function validateInput($input){
		//Get rid of any spaces at the end
		$input = trim($input);

		// Normalize fancy quotes:
		$quotes = [
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
		];
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
		$operators = ['AND', 'OR', 'NOT', '+', '-', '"', '&', '|'];
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
			$input = str_replace(['(', ')'], '', $input);
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
		$patterns = [
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
		];
		$matches  = [
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
		];
		$input = preg_replace($patterns, $matches, $input);

		//Remove any semi-colons or  any slashes that Solr will handle incorrectly.
		$input = str_replace([';', '\\', '/',],' ', $input);

		// Ensure $this->_protectedWords has already been loaded
		if ($this->_searchSpecs === false){
			$this->_loadSearchSpecs();
		}

		if (strpos($input, '!') !== false){
			//Remove any exclamation marks that Solr will handle incorrectly.
			// But not for any of our protected words. eg P!nk
			if ($this->_protectedWords){
				$tokens = explode(' ', $input);
				foreach ($tokens as &$token){
					if ($numQuotes > 0){
						$tmpToken = str_replace('"', '', $token);
						if (!in_array($tmpToken, $this->_protectedWords)){
							// Check unquoted words against protected word list
							$token = str_replace('!', ' ', $token);
						}
					} elseif (!in_array($token, $this->_protectedWords)){
						$token = str_replace('!', ' ', $token);
					}
				}
				$input = implode(' ', $tokens);
			}else{
				$input = str_replace('!', ' ', $input);
			}
		}

		return $input;
	}

	public function isAdvanced($query){
		//TODO: refactor isAdvancedSolrQueryPhrase
		// Check for various conditions that flag an advanced Lucene query:
		if ($query == '*:*'){
			return true;
		}

		// The following conditions do not apply to text inside quoted strings,
		// so let's just strip all quoted strings out of the query to simplify
		// detection.	We'll replace quoted phrases with a dummy keyword so quote
		// removal doesn't interfere with the field specifier check below.
		$query = preg_replace('/"[^"]*"/', 'quoted', $query);

		// Check for parentheses:
		if (strpos($query, '(') !== false && strpos($query, ')') !== false){
			return true;
		}

		// Check for wildcards and fuzzy matches:
		if (strpos($query, '*') !== false || strpos($query, '?') !== false || strpos($query, '~') !== false){
			return true;
		}


		// Build a regular expression to detect booleans -- AND/OR/NOT surrounded
		// by whitespace, or NOT leading the query and followed by whitespace.
		$boolReg = '/((\s+(AND|OR|NOT)\s+)|^NOT\s+)/';
		if (!$this->caseSensitiveBooleans){
			$boolReg .= 'i';
		}
		if (preg_match($boolReg, $query)){
			return true;
		}

		// Check for field specifiers:
		if (preg_match("/([^\(\s\:]+)\s?\:[^\s]/", $query, $matches)){
			//Make sure the field is actually one of our fields
			$fieldName = $matches[1];
			$fields    = $this->_loadValidFields();
			if (in_array($fieldName, $fields)){
				return true;
			}
		}

		// Check for range operators:
		$rangeReg = '/(\[.+\s+TO\s+.+\])|(\{.+\s+TO\s+.+\})/';
		if (preg_match($rangeReg, $query)){
			return true;
		}

		// Check for boosts:
		if (preg_match('/[\^][0-9]+/', $query)){
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
				$data['terms'] = [
					$data['terms'][0] => $this->_processTerms($data['terms'][1])
				];
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
			$schemaUrl = $this->host . '/admin/file?file=schema.xml&contentType=text/xml;charset=utf-8';
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

	function getDynamicFields(){
		return $this->_loadDynamicFields();
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
			$schemaUrl =  $this->host . '/admin/file?file=schema.xml&contentType=text/xml;charset=utf-8';
			$schema    = simplexml_load_file($schemaUrl);
			$fields    = [];
			/** @var SimpleXMLElement $field */
			foreach ($schema->fields->field as $field){
				//print_r($field);
				$fields[] = (string)$field['name'];
			}
			if ($this->index == 'grouped' && !empty($solrScope)) {
				// Only process for grouped work index where dynamic fields have the wildcard at the end
				// islandora dynamic fields start with the wildcard eg *_s
				foreach ($schema->fields->dynamicField as $field) {
					$fields[] = substr((string)$field['name'], 0, -1) . $solrScope;
				}
			}
			$this->cache->set($schemaCacheKey, $fields, 86400);
		}
		return $fields;
	}

	function getValidFields(){
		return $this->_loadValidFields();
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

	/**
	 * 	 Build query string for use with GET or POST, with special handling
	 * for repeated parameters
	 * Solr takes repeated url parameters without the typical array style.
	 * eg. &fq=firstOne&fq=secondOne
	 * and NOT &fq[0]=firstOne&fq[1]=secondOne
	 * @param array $parameters
	 * @return string
	 */
	private function buildSolrQueryString(array $parameters): string{
		$query = [];
		if ($parameters){
			foreach ($parameters as $function => $value){
				if ($function != ''){
					if (is_array($value)){
						foreach ($value as $additional){
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

		return implode('&', $query);
	}

}