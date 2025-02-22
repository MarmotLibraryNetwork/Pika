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

require_once ROOT_DIR . '/AJAXHandler.php';
require_once ROOT_DIR . '/sys/Pager.php';
require_once ROOT_DIR . '/sys/ISBN/ISBN.php';
require_once ROOT_DIR . '/CatalogConnection.php';
require_once ROOT_DIR . '/services/AJAX/MARC_AJAX_Basic.php';

class ItemAPI extends AJAXHandler {

	use MARC_AJAX_Basic;

	/** @var  Sierra|DriverInterface */
	protected $catalog;

	public $id;

	/**
	 * @var MarcRecord|IndexRecord
	 * marc record in File_Marc object
	 */
	protected $recordDriver;
	public $marcRecord;

	public $record;

	public $isbn;
	public $issn;
	public $upc;

	/** @var  Solr $db */
	public $db;

	protected $methodsThatRespondWithJSONUnstructured = [
		'getDescriptionByRecordId',
		'getDescriptionByTitleAndAuthor',
	];

	protected $methodsThatRespondWithJSONResultWrapper = [
		'getItem',
		'getBasicItemInfo',
		'getItemAvailability',
		'loadSolrRecord',
		'clearBookCoverCacheById',
		'getCopyAndHoldCounts',
	];

	protected $methodsThatRespondThemselves = [
		'getBookcoverById',
		'getBookCover',
		'getMarcRecord',
	];

	function getDescriptionByTitleAndAuthor(){
		global $configArray;

		//Load the title and author from the data passed in
		$title  = trim($_REQUEST['title']);
		$author = trim($_REQUEST['author']);

		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		/** @var SearchObject_Solr db */
		$this->db = new $class($url);

		//Setup the results to return from the API method
		$results = [];

		//Search the database by title and author
		if ($title && $author){
			$searchResults = $this->db->search("$title $author");
		}elseif ($title){
			$searchResults = $this->db->search("title:$title");
		}elseif ($author){
			$searchResults = $this->db->search("author:$author");
		}else{
			$results = [
				'result'  => false,
				'message' => 'Please enter a title and/or author',
			];
			return $results;
		}

		if ($searchResults['response']['numFound'] == 0){
			$results = [
				'result'  => false,
				'message' => 'Sorry, we could not find a description for that title and author',
			];
		}else{
			$firstRecord = $searchResults['response']['docs'][0];
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			$groupedWork = new GroupedWorkDriver($firstRecord);

			$results = [
				'result'       => true,
				'message'      => 'Found a summary for record ' . $firstRecord['title_display'] . ' by ' . $firstRecord['author_display'],
				'recordsFound' => $searchResults['response']['numFound'],
				'description'  => $groupedWork->getDescription(),
			];
		}
		return $results;
	}

	function getDescriptionByRecordId(){
		global $configArray;

		//Load the record id that the user wants to search for
		$recordId = trim($_REQUEST['recordId']);

		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		/** @var SearchObject_Solr db */
		$this->db = new $class($url);

		//Search the database by title and author
		if ($recordId){
			if (preg_match('/^b\d{7}[\dx]$/', $recordId)){
				$recordId = '.' . $recordId;
			}
			$searchResults = $this->db->search("$recordId", 'Id');
		}else{
			$results = [
				'result'  => false,
				'message' => 'Please enter the record Id to look for',
			];
			return $results;
		}

		if ($searchResults['response']['numFound'] == 0){
			$results = [
				'result'  => false,
				'message' => 'Sorry, we could not find a description for that record id',
			];
		}else{
			$firstRecord = $searchResults['response']['docs'][0];
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			$groupedWork = new GroupedWorkDriver($firstRecord);

			$results = [
				'result'       => true,
				'message'      => 'Found a summary for record ' . $firstRecord['title_display'] . ' by ' . $firstRecord['author_display'],
				'recordsFound' => $searchResults['response']['numFound'],
				'description'  => $groupedWork->getDescription(),
			];
		}
		return $results;
	}

	/**
	 * Load a marc record for a particular id from the server
	 * @deprecated Use Record AJAX DownloadMarc instead
	 */
	function getMarcRecord(){
		$this->downloadMarc();
	}

	/**
	 * Get information about a particular item and return it as JSON
	 */
	function getItem(){
		global $timer;
		global $configArray;
		$itemData = [];

		//Load basic information
		$this->id       = $_GET['id'];
		$itemData['id'] = $this->id;

		// Setup Search Engine Connection
		$class    = $configArray['Index']['engine'];
		$url      = $configArray['Index']['url'];
		$this->db = new $class($url);

		// Retrieve Full Marc Record
		if (!($record = $this->db->getRecord($this->id))){
			return ['error', 'Record does not exist'];
		}
		$this->record = $record;

		$this->recordDriver = RecordDriverFactory::initRecordDriver($record);
		$timer->logTime('Initialized the Record Driver');

		//Generate basic information from the marc file to make display easier.
		if (isset($record['isbn'])){
			$itemData['isbn'] = $record['isbn'][0];
		}
		if (isset($record['upc'])){
			$itemData['upc'] = $record['upc'][0];
		}
		if (isset($record['issn'])){
			$itemData['issn'] = $record['issn'][0];
		}
		$itemData['title']          = $record['title'];
		$itemData['author']         = $record['author'];
		$itemData['publisher']      = $record['publisher'];
		$itemData['allIsbn']        = $record['isbn'];
		$itemData['allUpc']         = $record['upc'] ?? null;
		$itemData['allIssn']        = $record['issn'] ?? null;
		$itemData['edition']        = $record['edition'] ?? null;
		$itemData['callnumber']     = $record['callnumber'] ?? null;
		$itemData['genre']          = $record['genre'] ?? null;
		$itemData['series']         = $record['series'] ?? null;
		$itemData['physical']       = $record['physical'];
		$itemData['lccn']           = $record['lccn'] ?? null;
		$itemData['contents']       = $record['contents'] ?? null;
		$itemData['format']         = $record['format'] ?? null;
		$itemData['formatCategory'] = $record['format_category'][0] ?? null;
		$itemData['language']       = $record['language'];

		//Retrieve description from MARC file
		$itemData['description'] = $this->recordDriver->getDescriptionFast();

		//setup 5 star ratings
		$ratingData             = $this->recordDriver->getRatingData();
		$itemData['ratingData'] = $ratingData;

		return $itemData;
	}

	function getBasicItemInfo(){
		global $timer;
		global $configArray;
		$itemData = [];

		//Load basic information
		$this->id       = $_GET['id'];
		$itemData['id'] = $this->id;

		// Setup Search Engine Connection
		$class    = $configArray['Index']['engine'];
		$url      = $configArray['Index']['url'];
		$this->db = new $class($url);

		// Retrieve Full Marc Record
		if (!($record = $this->db->getRecord($this->id))){
			PEAR_Singleton::raiseError(new PEAR_Error('Record Does Not Exist'));
		}
		$this->record       = $record;
		$this->recordDriver = RecordDriverFactory::initRecordDriver($record);
		$timer->logTime('Initialized the Record Driver');

		// Process MARC Data
		require_once ROOT_DIR . '/sys/MarcLoader.php';
		$marcRecord = MarcLoader::loadMarcRecordFromRecord($record);
		if ($marcRecord){
			$this->marcRecord = $marcRecord;
		}else{
			$itemData['error'] = 'Cannot Process MARC Record';
		}
		$timer->logTime('Processed the marc record');

		// Get ISBN for cover and review use
		if ($isbnFields = $this->marcRecord->getFields('020')){
			//Use the first good ISBN we find.
			/** @var File_MARC_Data_Field $isbnField */
			foreach ($isbnFields as $isbnField){
				if ($isbnSubfield = $isbnField->getSubfield('a')){
					$this->isbn = trim($isbnSubfield->getData());
					if ($pos = strpos($this->isbn, ' ')){
						$this->isbn = substr($this->isbn, 0, $pos);
					}
					if (strlen($this->isbn) < 10){
						$this->isbn = str_pad($this->isbn, 10, "0", STR_PAD_LEFT);
					}
					$itemData['isbn'] = $this->isbn;
					break;
				}
			}
		}
		/** @var File_MARC_Data_Field $upcField */
		if ($upcField = $this->marcRecord->getField('024')){
			if ($upcSubField = $upcField->getSubfield('a')){
				$this->upc       = trim($upcSubField->getData());
				$itemData['upc'] = $this->upc;
			}
		}
		/** @var File_MARC_Data_Field $issnField */
		if ($issnField = $this->marcRecord->getField('022')){
			if ($issnSubfield = $issnField->getSubfield('a')){
				$this->issn = trim($issnSubfield->getData());
				if ($pos = strpos($this->issn, ' ')){
					$this->issn = substr($this->issn, 0, $pos);
				}
				$itemData['issn'] = $this->issn;
			}
		}
		$timer->logTime('Got UPC, ISBN, and ISSN');

		//Generate basic information from the marc file to make display easier.
		$itemData['title']          = $record['title'];
		$itemData['author']         = isset($record['author']) ? $record['author'] : (isset($record['author2']) ? $record['author2'][0] : '');
		$itemData['publisher']      = $record['publisher'];
		$itemData['allIsbn']        = $record['isbn'];
		$itemData['allUpc']         = $record['upc'];
		$itemData['allIssn']        = $record['issn'];
		$itemData['issn']           = $record['issn'];
		$itemData['format']         = isset($record['format']) ? $record['format'][0] : '';
		$itemData['formatCategory'] = $record['format_category'][0];
		$itemData['language']       = $record['language'];
		$itemData['cover']          = "/bookcover.php?id={$itemData['id']}&issn={$itemData['issn']}&isbn={$itemData['isbn']}&upc={$itemData['upc']}&category={$itemData['formatCategory']}&format={$itemData['format'][0]}";

		//Retrieve description from MARC file
		$description = '';
		/** @var File_MARC_Data_Field $descriptionField */
		if ($descriptionField = $this->marcRecord->getField('520')){
			if ($descriptionSubfield = $descriptionField->getSubfield('a')){
				$description = trim($descriptionSubfield->getData());
			}
		}
		$itemData['description'] = $description;

		//setup 5 star ratings
		$itemData['ratingData'] = $this->recordDriver->getRatingData();
		$timer->logTime('Got 5 star data');

		return $itemData;
	}

	function getItemAvailability(){
		$itemData = [];

		//Load basic information
		$this->id       = $_GET['id'];
		$itemData['id'] = $this->id;

		$fullId = 'ils:' . $this->id;

		//Rather than calling the catalog, update to load information from the index
		//Need to match historical data so we don't break EBSCO
		$recordDriver = RecordDriverFactory::initRecordDriverById($fullId);
		if ($recordDriver->isValid()){
			$copies   = $recordDriver->getCopies();
			$holdings = [];
			$i        = 0;
			foreach ($copies as $copy){
				$key              = $copy['shelfLocation'];
				$key              = preg_replace('~\W~', '_', $key);
				$holdings[$key][] = [
					'location'           => $copy['shelfLocation'],
					'callnumber'         => $copy['callNumber'],
					'status'             => $copy['status'],
					'dueDate'            => '',
					'statusFull'         => $copy['status'],
					'statusfull'         => $copy['status'], // EBSCO EDS uses this field for availability See PK-1421
					'id'                 => $fullId,
					'number'             => $i++,
					'type'               => 'holding',
					'availability'       => $copy['available'],
					'holdable'           => $copy['holdable'] ? 1 : 0,
					'bookable'           => $copy['bookable'] ? 1 : 0,
					'libraryDisplayName' => $copy['shelfLocation'],
					'section'            => $copy['section'],
					'sectionId'          => $copy['sectionId'],
					'lastCheckinDate'    => $copy['lastCheckinDate'],
				];
			}
			$itemData['holdings'] = $holdings;
		}

		return $itemData;
	}

	function getBookcoverById(){
		$record         = $this->loadSolrRecord($_GET['id']);
		$isbn           = isset($record['isbn']) ? ISBN::normalizeISBN($record['isbn'][0]) : null;
		$upc            = isset($record['upc']) ? $record['upc'][0] : null;
		$id             = isset($record['id']) ? $record['id'][0] : null;
		$issn           = isset($record['issn']) ? $record['issn'][0] : null;
		$formatCategory = isset($record['format_category']) ? $record['format_category'][0] : null;
		$this->getBookCover($isbn, $upc, $formatCategory, $id, $issn);
	}

	function getBookCover($isbn = null, $upc = null, $formatCategory = null, $size = null, $id = null, $issn = null){
		if (is_null($isbn)){
			$isbn = $_GET['isbn'];
		}
		$_GET['isn'] = ISBN::normalizeISBN($isbn);
		if (is_null($issn)){
			$issn = $_GET['issn'];
		}
		$_GET['iss'] = $issn;
		if (is_null($upc)){
			$upc = $_GET['upc'];
		}
		$_GET['upc'] = $upc;
		if (is_null($formatCategory)){
			$formatCategory = $_GET['formatCategory'];
		}
		$_GET['category'] = $formatCategory;
		if (is_null($size)){
			$size = isset($_GET['size']) ? $_GET['size'] : 'small';
		}
		$_GET['size'] = $size;
		if (is_null($id)){
			$id = $_GET['id'];
		}
		$_GET['id'] = $id;
		include_once(ROOT_DIR . '/bookcover.php');
	}

	function clearBookCoverCacheById(){
		$id                 = strip_tags($_REQUEST['id']);
		$sizes              = ['small', 'medium', 'large'];
		$extensions         = ['jpg', 'gif', 'png'];
		$record             = $this->loadSolrRecord($id);
		$filenamesToCheck   = [];
		$filenamesToCheck[] = $id;
		if (isset($record['isbn'])){
			$isbns = $record['isbn'];
			foreach ($isbns as $isbn){
				$filenamesToCheck[] = preg_replace('/[^0-9xX]/', '', $isbn);
			}
		}
		if (isset($record['upc'])){
			$upcs = $record['upc'];
			if (isset($upcs)){
				$filenamesToCheck = array_merge($filenamesToCheck, $upcs);
			}
		}
		$deletedFiles = [];
		global $configArray;
		$coverPath = $configArray['Site']['coverPath'];
		foreach ($filenamesToCheck as $filename){
			foreach ($extensions as $extension){
				foreach ($sizes as $size){
					$tmpFilename = "$coverPath/$size/$filename.$extension";
					if (file_exists($tmpFilename)){
						$deletedFiles[] = $tmpFilename;
						unlink($tmpFilename);
					}
				}
			}
		}

		return ['deletedFiles' => $deletedFiles];
	}

	public function getCopyAndHoldCounts(){
		if (empty($_REQUEST['recordId'])){
			return ['error' => 'Please provide a record to load data for'];
		}
		$recordId = $_REQUEST['recordId'];
		/** @var GroupedWorkDriver|MarcRecord|OverDriveRecordDriver|ExternalEContentDriver $driver */
		$driver = RecordDriverFactory::initRecordDriverById($recordId);
		if ($driver == null || !$driver->isValid()){
			return ['error' => 'Sorry we could not find a record with that ID'];
		}else{
			if ($driver instanceof GroupedWorkDriver){
				/** @var GroupedWorkDriver $driver */
				$manifestations = $driver->getRelatedManifestations();
				$returnData     = [];
				foreach ($manifestations as $manifestation){
					$manifestationSummary = [
						'format'            => $manifestation['format'],
						'copies'            => $manifestation['copies'],
						'availableCopies'   => $manifestation['availableCopies'],
						'numHolds'          => $manifestation['numHolds'],
						'available'         => $manifestation['available'],
						'isEContent'        => $manifestation['isEContent'],
						'groupedStatus'     => $manifestation['groupedStatus'],
						'numRelatedRecords' => $manifestation['numRelatedRecords'],
					];
					foreach ($manifestation['relatedRecords'] as $relatedRecord){
						$manifestationSummary['relatedRecords'][] = $relatedRecord['id'];
					}
					$returnData[] = $manifestationSummary;
				}
				return $returnData;
			}elseif ($driver instanceof OverDriveRecordDriver){
				/** @var OverDriveRecordDriver $driver */
				$copies = count($driver->getItems());
				$holds  = $driver->getNumHolds();
				return [
					'copies' => $copies,
					'holds'  => $holds,
				];
			}elseif ($driver instanceof ExternalEContentDriver || $driver instanceof HooplaRecordDriver){
				/** @var ExternalEContentDriver $driver */
				return [
					'copies' => 1,
					'holds'  => 0,
				];
			}else{
				/** @var MarcRecord| $driver */
				$copies = count($driver->getCopies());
				$holds  = $driver->getNumHolds();
				return [
					'copies' => $copies,
					'holds'  => $holds,
				];
			}
		}
	}

	public function loadSolrRecord($id = null){
		global $configArray;
		//Load basic information
		if (!empty($id)){
			$this->id = $id;
		}else{
			$this->id = $_GET['id'];
		}

		$itemData['id'] = $this->id;

		// Setup Search Engine Connection
		$class    = $configArray['Index']['engine'];
		$url      = $configArray['Index']['url'];
		$this->db = new $class($url);

		// Retrieve Full Marc Record
		if (!($record = $this->db->getRecord($this->id))){
			PEAR_Singleton::raiseError(new PEAR_Error('Record Does Not Exist'));
		}
		return $record;
	}
}
