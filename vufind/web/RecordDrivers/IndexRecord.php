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
require_once ROOT_DIR . '/RecordDrivers/Interface.php';

/**
 * Index Record Driver
 *
 * This class is designed to handle records in a generic fashion, using
 * fields from the index.  It is invoked when a record-format-specific
 * driver cannot be found.
 */
class IndexRecord extends RecordInterface {
	protected $fields;
	protected $index = false;

	/**
	 * These Solr fields should NEVER be used for snippets.  (We exclude author
	 * and title because they are already covered by displayed fields; we exclude
	 * spelling because it contains lots of fields jammed together and may cause
	 * glitchy output; we exclude ID because random numbers are not helpful).
	 *
	 * @var    array
	 * @access protected
	 */
	protected $forbiddenSnippetFields = [
		'author', 'auth_author2', 'title', 'title_short', 'title_full',
		'title_auth', 'title_sub', 'title_display', 'spelling', 'id',
		'fulltext_unstemmed', //TODO: fulltext_unstemmed probably obsolete
		'spellingShingle', 'collection', 'title_proper',
		'display_description'
	];

	/**
	 * These are captions corresponding with Solr fields for use when displaying
	 * snippets.
	 *
	 * @var    array
	 * @access protected
	 */
	protected $snippetCaptions = [
		'display_description' => 'Description'
	];

	/**
	 * Should we highlight fields in search results?
	 *
	 * @var    bool
	 * @access protected
	 */
	protected $highlight = false;

	/**
	 * Should we include snippets in search results?
	 *
	 * @var    bool
	 * @access protected
	 */
	protected $snippet = false;

	/**
	 * The Grouped Work that this record is connected to
	 * @var  GroupedWork */
	protected $groupedWork;
	protected $groupedWorkDriver = null;

	/**
	 * Constructor.  We build the object using all the data retrieved
	 * from the (Solr) index.  Since we have to
	 * make a search call to find out which record driver to construct,
	 * we will already have this data available, so we might as well
	 * just pass it into the constructor.
	 *
	 * @param   array|File_MARC_Record||string   $recordData     Data to construct the driver from
	 * @param  GroupedWork $groupedWork;
	 * @access  public
	 */
	public function __construct($recordData, $groupedWork = null){
		$this->fields = $recordData;

		global $configArray;
		// Load highlighting/snippet preferences:
		$searchSettings        = getExtraConfigArray('searches');
		$this->highlight       = $configArray['Index']['enableHighlighting'];
		$this->snippet         = $configArray['Index']['enableSnippets'];
		$this->snippetCaptions = empty($searchSettings['Snippet_Captions']) ? [] : $searchSettings['Snippet_Captions'];

		if ($groupedWork == null){
			$this->loadGroupedWork();
		}else{
			$this->groupedWork = $groupedWork;
		}
	}

	/**
	 * Get text that can be displayed to represent this record in
	 * breadcrumbs.
	 *
	 * @access  public
	 * @return  string              Breadcrumb text to represent this record.
	 */
	public function getBreadcrumb(){
		return $this->getShortTitle();
	}

	/**
	 * Assign necessary Smarty variables and return a template name
	 * to load in order to display the requested citation format.
	 * For legal values, see getCitationFormats().  Returns null if
	 * format is not supported.
	 *
	 * @param   string  $format     Citation format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getCitation($format){
		return '';
	}

	/**
	 * Get an array of strings representing citation formats supported
	 * by this record's data (empty if none).  Legal values: "APA", "MLA".
	 *
	 * @access  public
	 * @return  array               Strings representing citation formats.
	 */
	public function getCitationFormats(){
		return [];
	}

	/**
	 * Get an array of search results for other editions of the title
	 * represented by this record (empty if unavailable).  In most cases,
	 * this will use the XISSN/XISBN logic to find matches.
	 *
	 * @access  public
	 * @return  mixed               Editions in index engine result format.
	 *                              (or null if no hits, or PEAR_Error object).
	 */
//	public function getEditions()
//	{
//		require_once ROOT_DIR . '/sys/WorldCatUtils.php';
//		$wc = new WorldCatUtils();
//
//		// Try to build an array of ISBN or ISSN-based sub-queries:
//		$parts = array();
//		$isbn = $this->getCleanISBN();
//		if (!empty($isbn)) {
//			$isbnList = $wc->getXISBN($isbn);
//			foreach($isbnList as $current) {
//				$parts[] = 'isbn:' . $current;
//			}
//		} else {
//			$issn = $this->getCleanISSN();
//			if (!empty($issn)) {
//				$issnList = $wc->getXISSN($issn);
//				foreach($issnList as $current) {
//					$parts[] = 'issn:' . $current;
//				}
//			}
//		}
//
//		// If we have query parts, we should try to find related records:
//		if (!empty($parts)) {
//			// Assemble the query parts and filter out current record:
//			$query = '(' . implode(' OR ', $parts) . ') NOT id:' .
//			$this->getUniqueID();
//
//			// Perform the search and return either results or an error:
//			$index = $this->getIndexEngine();
//			$result = $index->search($query, null, null, 0, 5);
//			if (PEAR_Singleton::isError($result)) {
//				return $result;
//			}
//			if (isset($result['response']['docs']) &&
//			!empty($result['response']['docs'])) {
//				return $result['response']['docs'];
//			}
//		}
//
//		// If we got this far, we were unable to find any results:
//		return null;
//	}


	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to export the record in the requested format.  For
	 * legal values, see getExportFormats().  Returns null if format is
	 * not supported.
	 *
	 * @param   string  $format     Export format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getExport($format){
		// Not currently supported for index-based records:
		return null;
	}

	/**
	 * Get an array of strings representing formats in which this record's
	 * data may be exported (empty if none).  Legal values: "RefWorks",
	 * "EndNote", "MARC", "RDF".
	 *
	 * @access  public
	 * @return  array               Strings representing export formats.
	 */
	public function getExportFormats(){
		// No export formats currently supported for index-based records:
		return [];
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * user's favorites list.
	 *
	 * @access  public
	 * @param   int     $listId     ID of list containing desired tags/notes (or
	 *                              null to show tags/notes from all user's lists).
	 * @param   bool    $allowEdit  Should we display edit controls?
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getListEntry($listId = null, $allowEdit = true){
	// Obsolete method
		return 'RecordDrivers/Index/listentry.tpl';
	}

	/**
	 * Get an XML RDF representation of the data in this record.
	 *
	 * @access  public
	 * @return  mixed               XML RDF data (false if unsupported or error).
	 */
	public function getRDFXML(){
		// Not supported.
		return false;
	}

	public function getSemanticData(){
		//Schema.org
		$semanticData[] = [
			'@context'            => 'http://schema.org',
			'@type'               => 'CreativeWork',
			'name'                => $this->getTitle(),
			'author'              => $this->getPrimaryAuthor(),
			'isAccessibleForFree' => true,
			'image'               => $this->getBookcoverUrl('medium', true),
			'workExample'         => $this->getSemanticWorkExamples(),
		];

		//BibFrame
		$semanticData[] = [
			'@context' => [
				"bf"       => 'http://bibframe.org/vocab/',
				"bf2"      => 'http://bibframe.org/vocab2/',
				"madsrdf"  => 'http://www.loc.gov/mads/rdf/v1#',
				"rdf"      => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
				"rdfs"     => 'http://www.w3.org/2000/01/rdf-schema',
				"relators" => "http://id.loc.gov/vocabulary/relators/",
				"xsd"      => "http://www.w3.org/2001/XMLSchema#"
			],
			'@graph'   => [
				[
					'@type'      => $this->getOGType(),
					'bf:title'   => $this->getTitle(),
					'bf:creator' => $this->getPrimaryAuthor(),
				],
			]
		];

		//Open graph data (goes in meta tags)
		global $interface;
		$interface->assign('og_title', $this->getTitle());
		$interface->assign('og_type', $this->getOGType());
		$interface->assign('og_image', $this->getBookcoverUrl('large', true));
		$interface->assign('og_url', $this->getAbsoluteUrl());

		//TODO: add audience, award, content
		return $semanticData;
	}


	/**
	 * TODO: probably obsolete
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public

	 * @param string $view The current view.
	 *
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getSearchResult($view = 'list') {
		global $configArray;
		global $interface;


		$id = $this->getUniqueID();
		$interface->assign('summId', $id);
		if (substr($id, 0, 1) == '.'){
			$interface->assign('summShortId', substr($id, 1));
		}else{
			$interface->assign('summShortId', $id);
		}
		$interface->assign('module', $this->getModule());

		$interface->assign('summUrl', $this->getLinkUrl());
		$formats = $this->getFormats();
		$interface->assign('summFormats', $formats);
		$formatCategories = $this->getFormatCategory();
		$interface->assign('summFormatCategory', $formatCategories);
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summSubTitle', $this->getSubtitle());
		$interface->assign('summTitleStatement', $this->getTitleSection());
		$interface->assign('summAuthor', $this->getPrimaryAuthor());
		$publishers = $this->getPublishers();
		$pubDates = $this->getPublicationDates();
		$pubPlaces = $this->getPlacesOfPublication();
		$interface->assign('summPublicationDates', $pubDates);
		$interface->assign('summPublishers', $publishers);
		$interface->assign('summPublicationPlaces',$pubPlaces);
		$interface->assign('summDate', $this->getPublicationDates());
		$interface->assign('summISBN', $this->getCleanISBN());
		$issn = $this->getCleanISSN();
		$interface->assign('summISSN', $issn);
		$upc = $this->getCleanUPC();
		$interface->assign('summUPC', $upc);
		if ($configArray['System']['debugSolr'] == 1){
			$interface->assign('summScore', $this->getScore());
			$interface->assign('summExplain', $this->getExplain());
		}
		$interface->assign('summPhysical', $this->getPhysicalDescriptions());
		$interface->assign('summEditions', $this->getEdition());

		// Obtain and assign snippet information:
		$snippet = $this->getHighlightedSnippet();
		$interface->assign('summSnippetCaption', $snippet ? $snippet['caption'] : false);
		$interface->assign('summSnippet', $snippet ? $snippet['snippet'] : false);

		$interface->assign('summURLs', $this->getURLs());

		//Get Rating
		$interface->assign('summRating', $this->getRatingData());

		//Description
		$interface->assign('summDescription', $this->getDescription());

		//Determine the cover to use
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		// By default, do not display AJAX status; we won't assume that all
		// records exist in the ILS.  Child classes can override this setting
		// to turn on AJAX as needed:
		$interface->assign('summAjaxStatus', false);

		return 'RecordDrivers/Index/result.tpl';
	}

	/**
	 * @return string  A description of the title
	 */
	function getDescription(){
		return empty($this->fields['display_description']) ? '' : $this->fields['display_description'];
	}

	/**
	 * Some description fetching takes a while. This method is for getting an
	 * adequate description quickly.
	 *
	 * If the class hasn't made an explicit implementation of this method, this
	 * will fall back to the regular description fetching
	 *
	 * @param bool $useHighlighting Whether or not to use highlighting of searched phrases
	 * @return string  A description of the title
	 */
	function getDescriptionFast($useHighlighting = false){
		return $this->getDescription();
	}

	function getBookcoverUrl($size = 'small'){
		$id             = $this->getIdWithSource();
		$formats        = $this->getFormat();
		$format         = reset($formats);
		$parameters     = [
			'id'     => $id,
			'size'   => $size,
			'format' => $format,
		];
		$formatCategory = $this->getFormatCategory();
		if (!empty($formatCategory)){
			if (is_array($formatCategory)){
				$formatCategory = reset($formatCategory);
			}
			$parameters['category'] = $formatCategory;
		}

		$isbn = $this->getCleanISBN();
		if ($isbn){
			$parameters['isn'] = $isbn;
		}
		$upc = $this->getCleanUPC();
		if ($upc){
			$parameters['upc'] = $upc;
		}
		$issn = $this->getCleanISSN();
		if ($issn){
			$parameters['issn'] = $issn;
		}
		global $configArray;
		$bookCoverUrl = $configArray['Site']['coverUrl'] . '/bookcover.php?' . http_build_query($parameters);
		return $bookCoverUrl;
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display the full record information on the Staff
	 * View tab of the record view page.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getStaffView(){
		global $interface;
		$interface->assign('details', $this->fields);

		$lastGroupedWorkModificationTime = $this->groupedWork->date_updated;
		$interface->assign('lastGroupedWorkModificationTime', $lastGroupedWorkModificationTime);

		return 'RecordDrivers/Index/staff.tpl';
	}

	/**
	 * load in order to display the Table of Contents for the title.
	 *  Returns null if no Table of Contents is available.
	 *
	 * @access  public
	 * @return  string              contents to display.
	 */
	public function getTOC(){
		return null;
	}

	/**
	 * Return the unique identifier of this record within the Solr index;
	 * useful for retrieving additional information (like tags and user
	 * comments) from the external MySQL database.
	 *
	 * @access  public
	 * @return  string              Unique identifier.
	 */
	public function getUniqueID(){
		return $this->fields['id'];
	}

	/**
	 * Does this record have searchable full text in the index?
	 *
	 * Note: As of this writing, searchable full text is not a VuFind feature,
	 *       but this method will be useful if/when it is eventually added.
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasFullText(){
		/* Full text is not supported yet.
		 */
		return false;
	}

	/**
	 * Does this record support an RDF representation?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasRDF(){
		// No RDF for Solr-based entries yet.
		return false;
	}

	/**
	 * Get access restriction notes for the record.
	 *
	 * @access  protected
	 * @return  array
	 */
	protected function getAccessRestrictions(){
		// Not currently stored in the Solr index
		return [];
	}

	/**
	 * Get all subject headings associated with this record.  Each heading is
	 * returned as an array of chunks, increasing from least specific to most
	 * specific.
	 *
	 * @access  protected
	 * @return array
	 */
	public function getAllSubjectHeadings(){
		$topic = $this->fields['topic'] ?? [];
		$geo   = $this->fields['geographic'] ?? [];
		$genre = $this->fields['genre'] ?? [];

		// The Solr index doesn't currently store subject headings in a broken-down
		// format, so we'll just send each value as a single chunk.  Other record
		// drivers (i.e. MARC) can offer this data in a more granular format.
		$retval = [];
		foreach ($topic as $t){
			$retval[] = [$t];
		}
		foreach ($geo as $g){
			$retval[] = [$g];
		}
		foreach ($genre as $g){
			$retval[] = [$g];
		}

		return $retval;
	}

	/**
	 * Return the first valid ISBN found in the record (favoring ISBN-10 over
	 * ISBN-13 when possible).
	 *
	 * @return  mixed
	 */
	public function getCleanISBN(){
		require_once ROOT_DIR . '/sys/ISBN/ISBN.php';

		// Get all the ISBNs and initialize the return value:
		$isbns  = $this->getISBNs();
		$isbn13 = false;

		// Loop through the ISBNs:
		foreach ($isbns as $isbn){
			// Strip off any unwanted notes:
			if ($pos = strpos($isbn, ' ')){
				$isbn = substr($isbn, 0, $pos);
			}

			// If we find an ISBN-10, return it immediately; otherwise, if we find
			// an ISBN-13, save it if it is the first one encountered.
			$isbnObj = new ISBN($isbn);
			if ($isbn10 = $isbnObj->get10()){
				return $isbn10;
			}
			if (!$isbn13){
				$isbn13 = $isbnObj->get13();
			}
		}
		return $isbn13;
	}

	public function getCleanISBNs(){
		require_once ROOT_DIR . '/sys/ISBN/ISBN.php';

		$cleanIsbns = [];
		// Get all the ISBNs and initialize the return value:
		$isbns = $this->getISBNs();

		// Loop through the ISBNs:
		foreach ($isbns as $isbn){
			// Strip off any unwanted notes:
			if ($pos = strpos($isbn, ' ')){
				$isbn = substr($isbn, 0, $pos);
			}

			// If we find an ISBN-10, return it immediately; otherwise, if we find
			// an ISBN-13, save it if it is the first one encountered.
			$isbnObj = new ISBN($isbn);
			if ($isbn10 = $isbnObj->get10()){
				if (!array_key_exists($isbn10, $cleanIsbns)){
					$cleanIsbns[$isbn10] = $isbn10;
				}
			}
			if ($isbn13 = $isbnObj->get13()){
				if (!array_key_exists($isbn13, $cleanIsbns)){
					$cleanIsbns[$isbn13] = $isbn13;
				}
			}
		}
		return $cleanIsbns;
	}

	/**
	 * Get just the base portion of the first listed ISSN (or false if no ISSNs).
	 *
	 * @access  protected
	 * @return  mixed
	 */
	protected function getCleanISSN(){
		$issns = $this->getISSNs();
		if (empty($issns)){
			return false;
		}
		$issn = $issns[0];
		if ($pos = strpos($issn, ' ')){
			$issn = substr($issn, 0, $pos);
		}
		return $issn;
	}

	public function getCleanUPC(){
		$upcs = $this->getUPCs();
		if (empty($upcs)) {
			return false;
		}
		$upc = $upcs[0];
		if ($pos = strpos($upc, ' ')) {
			$upc = substr($upc, 0, $pos);
		}
		return $upc;
	}

	public function getCleanUPCs(){
		$cleanUPCs = array();
		$upcs = $this->getUPCs();
		if (empty($upcs)) {
			return $cleanUPCs;
		}
		foreach ($upcs as $upc){
			if ($pos = strpos($upc, ' ')) {
				$upc = substr($upc, 0, $pos);
			}
			if (!array_key_exists($upc, $cleanUPCs)){
				$cleanUPCs[$upc] = $upc;
			}
		}

		return $cleanUPCs;
	}

	/**
	 * Get the edition of the current record.
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function getEdition(){
		return 	$this->fields['edition'] ?? '';
	}

	/**
	 * Get an array of all the formats associated with the record.
	 *
	 * @access  public
	 * @return  array
	 */
	public function getFormats(){
		return $this->fields['format'] ?? [];
	}

	public function getPrimaryFormat(){
		$formats = $this->getFormats();
		return reset($formats);
	}

	/**
	 * Get an array of all the format categories associated with the record.
	 *
	 * @return  array
	 */
	public function getFormatCategory(){
		global $solrScope;
		return $this->fields['format_category_'.$solrScope] ?? [];
		//TODO: compare with GroupedWorkDriver getFormatCategory()
	}

	/**
	 * Load the grouped work that this record is connected to.
	 */
	public function loadGroupedWork(){
		if ($this->groupedWork == null){
			global $timer;
			require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
			require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
			$groupedWorkPrimaryIdentifier             = new GroupedWorkPrimaryIdentifier();
			$groupedWorkPrimaryIdentifier->type       = $this->getRecordType();
			$groupedWorkPrimaryIdentifier->identifier = $this->getUniqueID();
			if ($groupedWorkPrimaryIdentifier->find(true)){
				$groupedWork     = new GroupedWork();
				$groupedWork->id = $groupedWorkPrimaryIdentifier->grouped_work_id;
				if ($groupedWork->find(true)){
					$this->groupedWork = clone $groupedWork;
				}
			}

			if ($timer){
				$timer->logTime('Loaded Grouped Work for record');
			}
		}
	}

	public function getPermanentId(){
		return $this->getGroupedWorkId();
	}
	public function getGroupedWorkId(){
		if ($this->groupedWork == null){
			return null;
		}else{
			return $this->groupedWork->permanent_id;
		}
	}

	public function getGroupedWorkDriver(){
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		if ($this->groupedWorkDriver == null){
			$this->groupedWorkDriver = new GroupedWorkDriver($this->getPermanentId());
		}
		return $this->groupedWorkDriver;
	}


	/**
	 * Get a highlighted author string, if available.
	 *
	 * @return string
	 * @access protected
	 */
	protected function getHighlightedAuthor(){
		// Don't check for highlighted values if highlighting is disabled:
		if (!$this->highlight){
			return '';
		}
		return $this->fields['_highlighting']['author'][0] ?? '';
	}

	/**
	 * Given a Solr field name, return an appropriate caption.
	 *
	 * @param string $field Solr field name
	 *
	 * @return mixed        Caption if found, false if none available.
	 * @access protected
	 */
	protected function getSnippetCaption($field){
		if (isset($this->snippetCaptions[$field])){
			return $this->snippetCaptions[$field];
		}else{
			if (preg_match('/callnumber/', $field)){
				return 'Call Number';
			}else{
				return ucwords(str_replace('_', ' ', $field));
			}

		}
	}

	/**
	 * Pick one line from the highlighted text (if any) to use as a snippet.
	 *
	 * @return mixed False if no snippet found, otherwise associative array
	 * with 'snippet' and 'caption' keys.
	 * @access protected
	 */
	protected function getHighlightedSnippets(){
		$snippets = [];
		// Only process snippets if the setting is enabled:
		if ($this->snippet && isset($this->fields['_highlighting'])){
			if (is_array($this->fields['_highlighting'])){
				foreach ($this->fields['_highlighting'] as $key => $value){
					if (!in_array($key, $this->forbiddenSnippetFields)){
						$snippets[] = [
							'snippet' => $value[0],
							'caption' => $this->getSnippetCaption($key)
						];
					}
				}
			}
			return $snippets;
		}

		// If we got this far, no snippet was found:
		return false;
	}

	/**
	 * Get a highlighted title string, if available.
	 *
	 * @return string
	 * @access protected
	 */
	protected function getHighlightedTitle(){
		// Don't check for highlighted values if highlighting is disabled:
		if (!$this->highlight){
			return '';
		}
		return $this->fields['_highlighting']['title'][0] ?? '';
	}

	/**
	 * Get the index engine to do a follow-up query.
	 *
	 * @access  protected
	 * @return  object
	 */
	protected function getIndexEngine(){
		// Build the index engine if we don't already have one:
		if (!$this->index){
			$searchObject = SearchObjectFactory::initSearchObject();
			$this->index  = new $searchObject;
		}

		return $this->index;
	}

	/**
	 * Get an array of all ISBNs associated with the record (may be empty).
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getISBNs(){
		// If ISBN is in the index, it should automatically be an array... but if
		// it's not set at all, we should normalize the value to an empty array.
		if (isset($this->fields['isbn'])){
			return is_array($this->fields['isbn']) ? $this->fields['isbn'] : [$this->fields['isbn']];
		}else{
			return [];
		}
	}

	/**
	 * Get the UPC associated with the record (may be empty).
	 *
	 * @return  array
	 */
	public function getUPCs(){
		// If UPCs is in the index, it should automatically be an array... but if
		// it's not set at all, we should normalize the value to an empty array.
		if (isset($this->fields['upc'])){
			return is_array($this->fields['upc']) ? $this->fields['upc'] : [$this->fields['upc']];
		}else{
			return [];
		}
	}

	public function getUPC(){
		// If UPCs is in the index, it should automatically be an array... but if
		// it's not set at all, we should normalize the value to an empty array.
		return isset($this->fields['upc']) && is_array($this->fields['upc']) ? $this->fields['upc'][0] : '';
	}

	/**
	 * Get an array of all ISSNs associated with the record (may be empty).
	 *
	 * @access  public
	 * @return  array
	 */
	public function getISSNs(){
		// If ISSN is in the index, it should automatically be an array... but if
		// it's not set at all, we should normalize the value to an empty array.
		return isset($this->fields['issn']) && is_array($this->fields['issn']) ?
			$this->fields['issn'] : [];
	}

	/**
	 * Get an array of all the languages associated with the record.
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getLanguages(){
		return $this->fields['language'] ?? [];
	}

	/**
	 * Get the item's place of publication.
	 *
	 * @access  protected
	 * @return  array
	 */
	protected function getPlacesOfPublication(){
		// Not currently stored in the Solr index
		return [];
	}

	/**
	 * TODO: not called anywhere
	 * Get an array of newer titles for the record's series.
	 *
	 * @access  protected
	 * @return  array
	 */
//	protected function getNewerTitles(){
//		return $this->fields['title_new'] ?? [];
//	}

	/**
	 * TODO: not called anywhere
	 * Get an array of previous titles for the record's series.
	 *
	 * @access  protected
	 * @return  array
	 */
//	protected function getPreviousTitles(){
//		return $this->fields['title_old'] ?? [];
//	}

	/**
	 * Get the main author of the record.
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function getPrimaryAuthor(){
		return $this->fields['author'] ?? '';
	}

	/**
	 * Get the publication dates of the record.
	 *
	 * @access  public
	 * @return  array
	 */
	public function getPublicationDates(){
		return $this->fields['publishDate'] ?? [];
	}

	/**
	 * Get an array of publication detail lines combining information from
	 * getPublicationDates(), getPublishers() and getPlacesOfPublication().
	 *
	 * @access  public
	 * @return  array
	 */
	function getPublicationDetails(){
		$places = $this->getPlacesOfPublication();
		$names  = $this->getPublishers();
		$dates  = $this->getPublicationDates();

		$i         = 0;
		$returnVal = [];
		while (isset($places[$i]) || isset($names[$i]) || isset($dates[$i])){
			// Put all the pieces together, and do a little processing to clean up
			// unwanted whitespace.
			$publicationInfo = (!empty($places[$i]) ? $places[$i] . ' ' : '') .
				(!empty($names[$i]) ? $names[$i] . ' ' : '');
			if (!empty($dates[$i])){
				if (!empty($publicationInfo)){
					$publicationInfo .= ', ' . $dates[$i] . '.';
				}else{
					$publicationInfo = $dates[$i] . '.';
				}
			}
			$publicationInfo = trim(str_replace('  ', ' ', $publicationInfo));
			$publicationInfo = str_replace(' ,', ',', $publicationInfo);
			$publicationInfo = htmlentities($publicationInfo);
			$returnVal[]     = $publicationInfo;
			$i++;
		}

		return $returnVal;
	}

	/**
 * Get the publishers of the record.
 *
 * @access  protected
 * @return  array
 */
	protected function getPublishers(){
		return $this->fields['publisher'] ?? [];
	}

	/**
	 * Get an array of all secondary authors (complementing getPrimaryAuthor()).
	 *
	 * @access  protected
	 * @return  array
	 */
	protected function getSecondaryAuthors(){
		return $this->fields['author2'] ?? [];
	}

	/**
	 * Get an array of all series names containing the record.  Array entries may
	 * be either the name string, or an associative array with 'name' and 'number'
	 * keys.
	 *
	 * @access  protected
	 * @return  array
	 */
	protected function getSeries(){
		// Only use the contents of the series2 field if the series field is empty
		if (!empty($this->fields['series'])){
			return $this->fields['series'];
		}
		return $this->fields['series2'] ?? [];
	}

	/**
	 * Get the short (pre-subtitle) title of the record.
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function getShortTitle(){
		return $this->fields['title_short'] ?? '';
	}

	/**
	 * Get the subtitle of the record.
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function getSubtitle(){
		return $this->fields['title_sub'] ?? '';
	}

	/**
	 * Get an array of summary strings for the record.
	 *
	 * @access  protected
	 * @return  array
	 */
	protected function getSummary(){
		// Not currently stored in the Solr index
		return [];
	}

	/**
	 * Get the full title of the record.
	 *
	 * @return  string
	 */
	public function getTitle(){
		return $this->fields['title'] ?? $this->fields['title_display'] ?? '';
	}

	/**
	 * Get the text of the part/section portion of the title.
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function getTitleSection(){
		// Not currently stored in the Solr index
		return null;
	}

	/**
	 * Return an associative array of URLs associated with this record (key = URL,
	 * value = description).
	 *
	 * @access  protected
	 * @return  array
	 */
	protected function getURLs(){
		$urls = [];
		if (isset($this->fields['url']) && is_array($this->fields['url'])){
			foreach ($this->fields['url'] as $url){
				// The index doesn't contain descriptions for URLs, so we'll just
				// use the URL itself as the description.
				$urls[$url] = $url;
			}
		}
		return $urls;
	}

	public function getScore(){
		return $this->fields['score'] ?? null;
	}

	public function getExplain(){
		if (isset($this->fields['explain'])){
			$explain = explode(', result of:', $this->fields['explain'], 2);
			// Break query from score explanation
			$explain[1] = preg_replace('/weight\((.*):(.*)( in \d+\))/i', 'weight(<code>$1</code>:<strong>$2</strong>$3)', $explain[1]);
			// highlight the solr fields and the search term of interest
			$explain[1] = preg_replace('/computed as (.*) from:/i', 'computed as <var>$1</var> from:', $explain[1]);
			// italicize the formula fragments
			return $explain[0] . '<br> result of : <p>' . nl2br(str_replace(' ', '&nbsp;', $explain[1])) . '</p>';
			// Put text back together, replace spaces with non-breaking space character, so the indentation of explaination lines display
		}
		return '';
	}

	public function getId(){
		return $this->fields['id'] ?? null;
	}

	/**
	 * @return string[]
	 */
	public function getFormat(){
		if (isset($this->fields['format'])){
			if (is_array($this->fields['format'])){
				return $this->fields['format'];
			}else{
				return [$this->fields['format']];
			}
		}else{
			return ["Unknown"];
		}
	}

	public function getLanguage(){
		return $this->fields['language'] ?? 'Implement this when not backed by Solr data';
	}

	public function getRatingData() {
		require_once ROOT_DIR . '/services/API/WorkAPI.php';
		$workAPI = new WorkAPI();
		return $workAPI->getRatingData($this->getGroupedWorkId());
	}

	/**
	 * The indexing profile source name associated with this Record
	 *
	 * @return string
	 */
	public function getRecordType(){
		return 'unknown';
	}

	function getRecordUrl(){
		$recordId = $this->getUniqueID();

		//TODO: This should have the correct module set
		return '/' . $this->getModule() . '/' . $recordId;
	}

	function getAbsoluteUrl(){
		global $configArray;
		$recordId = $this->getUniqueID();

		return $configArray['Site']['url'] . '/' . $this->getModule() . '/' . $recordId;
	}

	function getQRCodeUrl(){
		global $configArray;
		return $configArray['Site']['url'] . '/qrcode.php?type=Record&id=' . $this->getPermanentId();
	}

	public function getTags(){
		return $this->getGroupedWorkDriver()->getTags();
	}

	public function getExploreMoreInfo(){
		global $interface;
		global $configArray;
		$exploreMoreOptions = [];
		if ($configArray['Catalog']['showExploreMoreForFullRecords']){
			$interface->assign('showMoreLikeThisInExplore', true);

			if ($this->getCleanISBN()){
				if ($interface->getVariable('showSimilarTitles')){
					$exploreMoreOptions['similarTitles'] = [
						'label'         => 'Similar Titles From NoveList',
						'body'          => '<div id="novelisttitlesPlaceholder"></div>',
						'hideByDefault' => true
					];
				}
				if ($interface->getVariable('showSimilarAuthors')){
					$exploreMoreOptions['similarAuthors'] = [
						'label'         => 'Similar Authors From NoveList',
						'body'          => '<div id="novelistauthorsPlaceholder"></div>',
						'hideByDefault' => true
					];
				}
				if ($interface->getVariable('showSimilarTitles')){
					$exploreMoreOptions['similarSeries'] = [
						'label'         => 'Similar Series From NoveList',
						'body'          => '<div id="novelistseriesPlaceholder"></div>',
						'hideByDefault' => true
					];
				}
			}

			require_once ROOT_DIR . '/sys/ExploreMore.php';
			$exploreMore = new ExploreMore();
			$exploreMore->loadExploreMoreSidebar('catalog', $this);
		}
		return $exploreMoreOptions;
	}

	public function getMoreDetailsOptions(){
		return $this->getBaseMoreDetailsOptions(false);
	}

	/**
	 * Get the OpenURL parameters to represent this record (useful for the
	 * title attribute of a COinS span tag).
	 *
	 * @access  public
	 * @return  string              OpenURL parameters.
	 */
	public function getOpenURL(){
		// Get the COinS ID -- it should be in the OpenURL section of config.ini,
		// but we'll also check the COinS section for compatibility with legacy
		// configurations (this moved between the RC2 and 1.0 releases).
		$coinsID = 'pika';

		// Start an array of OpenURL parameters:
		$params = [
			'ctx_ver'   => 'Z39.88-2004',
			'ctx_enc'   => 'info:ofi/enc:UTF-8',
			'rfr_id'    => "info:sid/{$coinsID}:generator",
			'rft.title' => $this->getTitle(),
		];

		// Get a representative publication date:
		$pubDate = $this->getPublicationDates();
		if (count($pubDate) == 1){
			$params['rft.date'] = $pubDate[0];
		}elseif (count($pubDate) > 1){
			$params['rft.date'] = $pubDate;
		}

		// Add additional parameters based on the format of the record:
		$formats = $this->getFormats();

		// If we have multiple formats, Book and Journal are most important...
		if (in_array('Book', $formats)){
			$format = 'Book';
		}else{
			if (in_array('Journal', $formats)){
				$format = 'Journal';
			}else{
				$format = $formats[0];
			}
		}
		switch ($format){
			case 'Book':
				$params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:book';
				$params['rft.genre']   = 'book';
				$params['rft.btitle']  = $params['rft.title'];

				$series = $this->getSeries(false);
				if ($series != null){
					// Handle both possible return formats of getSeries:
					$params['rft.series'] = $series['seriesTitle'];
				}

				$params['rft.au'] = $this->getPrimaryAuthor();
				$publishers       = $this->getPublishers();
				if (count($publishers) == 1){
					$params['rft.pub'] = $publishers[0];
				}elseif (count($publishers) > 1){
					$params['rft.pub'] = $publishers;
				}
				$params['rft.edition'] = $this->getEdition();
				$params['rft.isbn']    = $this->getCleanISBN();
				break;
			case 'Journal':
				/* This is probably the most technically correct way to represent
				 * a journal run as an OpenURL; however, it doesn't work well with
				 * Zotero, so it is currently commented out -- instead, we just add
				 * some extra fields and then drop through to the default case.
				 $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:journal';
				 $params['rft.genre'] = 'journal';
				 $params['rft.jtitle'] = $params['rft.title'];
				 $params['rft.issn'] = $this->getCleanISSN();
				 $params['rft.au'] = $this->getPrimaryAuthor();
				 break;
				 */
				$issns = $this->getISSNs();
				if (count($issns) > 0){
					$params['rft.issn'] = $issns[0];
				}

				// Including a date in a title-level Journal OpenURL may be too
				// limiting -- in some link resolvers, it may cause the exclusion
				// of databases if they do not cover the exact date provided!
				unset($params['rft.date']);
			default:
				$params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
				$params['rft.creator'] = $this->getPrimaryAuthor();
				$publishers            = $this->getPublishers();
				if (count($publishers) > 0){
					$params['rft.pub'] = $publishers[0];
				}
				$params['rft.format'] = $format;
				$langs                = $this->getLanguages();
				if (count($langs) > 0){
					$params['rft.language'] = $langs[0];
				}
				break;
		}

		// Assemble the URL:
		$parts = [];
		foreach ($params as $key => $value){
			if (is_array($value)){
				foreach ($value as $arrVal){
					$parts[] = $key . '[]=' . urlencode($arrVal);
				}
			}else{
				$parts[] = $key . '=' . urlencode($value);
			}
		}
		return implode('&', $parts);
	}

	/**
	 * Load Record actions when we don't have detailed information about the record yet
	 */
	public function getRecordActionsFromIndex(){
		$groupedWork = $this->getGroupedWorkDriver();
		if ($groupedWork != null){
			$relatedRecords = $groupedWork->getRelatedRecords();
			foreach ($relatedRecords as $relatedRecord){
				if ($relatedRecord['id'] == $this->getIdWithSource()){
					return $relatedRecord['actions'];
				}
			}
		}
		return [];
	}

	public function getItemActions($itemInfo){
		return [];
	}

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $relatedUrls = null){
		return [];
	}

	public function getModule() {
		return 'Record';
	}

	function getOGType(){
		$pikaFormat = strtolower($this->getFormatCategory()[0] ?? '');
		switch ($pikaFormat){
			case 'music':
				return 'music.album';
			case 'movies':
				return 'video.movie';
			default:
				return 'book';
		}
	}

}
