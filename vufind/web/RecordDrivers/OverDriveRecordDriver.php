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
 * Record Driver to handle loading data for OverDrive Records
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 12/2/13
 * Time: 8:37 AM
 */

require_once ROOT_DIR . '/RecordDrivers/Interface.php';

class OverDriveRecordDriver extends RecordInterface {

	private $id;
	/** @var OverDriveAPIProduct */
	private $overDriveProduct;
	/** @var  OverDriveAPIProductMetaData */
	private $overDriveMetaData;
	private $valid;
	private $isbns = null;
	private $upcs = null;
	private $asins = null;
	private $items;
	private $issues;
	/**
	 * The Grouped Work that this record is connected to
	 * @var  GroupedWork
	 */
	protected $groupedWork;
	protected $groupedWorkDriver = null;


	/**
	 * Constructor.  We build the object using all the data retrieved
	 * from the (Solr) index.  Since we have to
	 * make a search call to find out which record driver to construct,
	 * we will already have this data available, so we might as well
	 * just pass it into the constructor.
	 *
	 * @param string $recordId The id of the record within OverDrive.
	 * @param GroupedWork $groupedWork ;
	 * @access  public
	 */
	public function __construct($recordId, $groupedWork = null){
		if (is_string($recordId)){
			//The record is the identifier for the overdrive title
			$this->id                            = $recordId;

			$this->overDriveProduct              = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
			$this->overDriveProduct->overdriveId = $recordId;
			if ($this->overDriveProduct->find(true)){
				$this->valid = true;
			}else{
				$this->valid = false;
			}
			if ($groupedWork == null){
				$this->loadGroupedWork();
			}else{
				$this->groupedWork = $groupedWork;
			}
		}
	}

	public function getModule(){
		return 'OverDrive';
	}

	/**
	 * Load the grouped work that this record is connected to.
	 */
	public function loadGroupedWork(){
		require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		$groupedWork = new GroupedWork();
		$query       = "SELECT grouped_work.* FROM grouped_work INNER JOIN grouped_work_primary_identifiers ON grouped_work.id = grouped_work_id WHERE type='overdrive' AND identifier = '" . $this->getUniqueID() . "'";
		$groupedWork->query($query);

		if ($groupedWork->N == 1){
			$groupedWork->fetch();
			$this->groupedWork = clone $groupedWork;
		}
	}

	public function getPermanentId(){
		return $this->getGroupedWorkId();
	}

	public function getGroupedWorkId(){
		if (!isset($this->groupedWork)){
			$this->loadGroupedWork();
		}
		if ($this->groupedWork){
			return $this->groupedWork->permanent_id;
		}else{
			return null;
		}

	}

	public function isValid(){
		return $this->valid;
	}

	/**
	 * Get text that can be displayed to represent this record in
	 * breadcrumbs.
	 *
	 * @access  public
	 * @return  string              Breadcrumb text to represent this title.
	 */
	public function getBreadcrumb(){
		return $this->getTitle();
	}

	/**
	 * Assign necessary Smarty variables and return a template name
	 * to load in order to display the requested citation format.
	 * For legal values, see getCitationFormats().  Returns null if
	 * format is not supported.
	 *
	 * @param string $format Citation format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getCitation($format){
		require_once ROOT_DIR . '/sys/LocalEnrichment/CitationBuilder.php';

		// Build author list:
		$authors = [];
		$primary = $this->getAuthor();
		if (!empty($primary)){
			$authors[] = $primary;
		}
		$authors = array_unique(array_merge($authors, $this->getContributors()));

		// Collect all details for citation builder:
		$publishers = $this->getPublishers();
		$pubDates   = $this->getPublicationDates();
		$pubPlaces  = $this->getPlacesOfPublication();
		$details    = [
			'authors'  => $authors,
			'title'    => $this->getTitle(),
			'subtitle' => $this->getSubTitle(),
			'pubPlace' => count($pubPlaces) > 0 ? $pubPlaces[0] : null,
			'pubName'  => count($publishers) > 0 ? $publishers[0] : null,
			'pubDate'  => count($pubDates) > 0 ? $pubDates[0] : null,
			'edition'  => $this->getEdition(),
			'format'   => $this->getFormats()
		];

		// Build the citation:
		$citation = new CitationBuilder($details);
		switch ($format){
			case 'APA':
				return $citation->getAPA();
			case 'AMA':
				return $citation->getAMA();
			case 'ChicagoAuthDate':
				return $citation->getChicagoAuthDate();
			case 'ChicagoHumanities':
				return $citation->getChicagoHumanities();
			case 'MLA':
				return $citation->getMLA();
		}
		return '';
	}

	private array $detailedContributors = array();

	/**
	 * Get an array of creators and roles
	 *
	 * @access  public
	 * @return  array               Strings representing citation formats.
	 */
	public function getDetailedContributors(){

			$overDriveAPIProductCreators     = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductCreators();
			$overDriveAPIProductCreators->productId = $this->overDriveProduct->id;
			$overDriveAPIProductCreators->orderBy("role");
			if ($overDriveAPIProductCreators->find()){
				while($overDriveAPIProductCreators->fetch()){
					$curContributor               = [
						'name' => $overDriveAPIProductCreators->fileAs,
						'role' => $overDriveAPIProductCreators->role
					];
					$this->detailedContributors[] = $curContributor;
				}
			}


		return $this->detailedContributors;
	}

	/**
	 * Get an array of strings representing citation formats supported
	 * by this record's data (empty if none).  Legal values: "APA", "MLA".
	 *
	 * @access  public
	 * @return  array               Strings representing citation formats.
	 */
	public function getCitationFormats(){
		return ['AMA', 'APA', 'ChicagoHumanities', 'ChicagoAuthDate', 'MLA'];
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to export the record in the requested format.  For
	 * legal values, see getExportFormats().  Returns null if format is
	 * not supported.
	 *
	 * @param string $format Export format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getExport($format){
		// TODO: Implement getExport() method.
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
		// TODO: Implement getExportFormats() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display holdings extracted from the base record
	 * (i.e. URLs in MARC 856 fields).  This is designed to supplement,
	 * not replace, holdings information extracted through the ILS driver
	 * and displayed in the Holdings tab of the record view page.  Returns
	 * null if no data is available.
	 *
	 * @return  Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductFormats[]  An Array of Formats with link information included
	 */
	public function getHoldings(){
		$items            = $this->getItems();
		$availability     = $this->getAvailability();
		$addCheckoutLink  = false;
		$addPlaceHoldLink = false;
		foreach ($availability as $availableFrom){
			if ($availableFrom->copiesAvailable > 0){
				$addCheckoutLink = true;
			}else{
				$addPlaceHoldLink = true;
			}
		}
		foreach ($items as &$item){
			//Add links as needed
			$item->links = [];
			if ($addCheckoutLink){
				$item->links[] = [
					'onclick' => "return Pika.OverDrive.checkOutOverDriveTitle('{$this->getUniqueID()}', '{$item->textId}');",
					'text'    => 'Check Out ' . $item->name,
				];
			}elseif ($addPlaceHoldLink){
				$item->links[] = [
					'onclick' => "return Pika.OverDrive.placeOverDriveHold('{$this->getUniqueID()}');",
					'text'    => 'Place Hold ' . $item->name,
				];
			}
		}

		return $items;
	}
	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display issues extracted from the base record
	 * Returns
	 * null if no data is available.
	 */
	public function getMagazineIssues(){
		$parentId = strtolower($this->id);

		if($this->issues == null){
			$overdriveIssues = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIMagazineIssues();
			$this->issues = [];
			if($this->valid){
				$overdriveIssues->parentId = $parentId;
				$overdriveIssues->orderBy("pubDate DESC");
				$overdriveIssues->find();
				$issuesList = $overdriveIssues->fetchAll();

				foreach($issuesList as $issue)
				{
					$this->issues[] = $issue;
				}

			}
			global $timer;
			$timer->logTime("Finished getIssues for OverDrive record {$parentId}");
		}
		return $this->issues;

	}

		/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * user's favorites list.
	 *
	 * @access  public
	 * @param int $listId ID of list containing desired tags/notes (or
	 *                              null to show tags/notes from all user's lists).
	 * @param bool $allowEdit Should we display edit controls?
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getListEntry($listId = null, $allowEdit = true){
		// TODO: Implement getListEntry() method.
	}

	/**
	 * Get an XML RDF representation of the data in this record.
	 *
	 * @access  public
	 * @return  mixed               XML RDF data (false if unsupported or error).
	 */
	public function getRDFXML(){
		// TODO: Implement getRDFXML() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @param string $view The view style for this search entry.
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getSearchResult($view = 'list'){
		// TODO: probably not needed.
	}

	public function getSeries(){
		$seriesData = $this->getGroupedWorkDriver()->getSeries();
		if ($seriesData == null){
			$seriesName = isset($this->getOverDriveMetaData()->getDecodedRawData()->series) ? $this->getOverDriveMetaData()->getDecodedRawData()->series : null;
			if ($seriesName != null){
				$seriesData = [
					'seriesTitle'  => $seriesName,
					'fromNovelist' => false,
				];
			}
		}
		return $seriesData;
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

		$overDriveAPIProduct              = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
		$overDriveAPIProduct->overdriveId = strtolower($this->id);
		$overDriveAPIProductMetaData      = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductMetaData();
		$overDriveAPIProduct->joinAdd($overDriveAPIProductMetaData, 'INNER');
		$overDriveAPIProduct->selectAdd("overdrive_api_products.rawData as productRaw");
		$overDriveAPIProduct->selectAdd("overdrive_api_product_metadata.rawData as metaDataRaw");
		if ($overDriveAPIProduct->find(true)){
			$interface->assign('overDriveProduct', $overDriveAPIProduct);

			$productRaw = json_decode($overDriveAPIProduct->productRaw);
			//Remove links to overdrive that could be used to get semi-sensitive data
			unset($productRaw->links);
			unset($productRaw->contentDetails->account);
			$interface->assign('overDriveProductRaw', $productRaw);
			$overDriveMetadata = $overDriveAPIProduct->metaDataRaw;
			//Replace http links to content reserve with https so we don't get mixed content warnings
			$overDriveMetadata = str_replace('http://images.contentreserve.com', 'https://images.contentreserve.com', $overDriveMetadata);
			$overDriveMetadata = json_decode($overDriveMetadata);
			$interface->assign('overDriveMetaDataRaw', $overDriveMetadata);
		}

		$lastGroupedWorkModificationTime = empty($this->groupedWork->date_updated) ? 'null' : $this->groupedWork->date_updated;
		// Mark with text 'null' so that the template handles the display properly
		$interface->assign('lastGroupedWorkModificationTime', $lastGroupedWorkModificationTime);

		return 'RecordDrivers/OverDrive/staff-view.tpl';
	}

	/**
	 * load in order to display the Table of Contents for the title.
	 *  Returns null if no Table of Contents is available.
	 *
	 * @access  public
	 * @return  string[]|null              contents to display.
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
		return $this->id;
	}

	public function getId(){
		return $this->id;
	}

	/**
	 * Generally, the indexing profile source name associated with this Record
	 * However, OverDrive is the exception in that it has no indexing profile
	 * and the sourceName is assumed to be overdrive.
	 *
	 * @return string
	 */
	function getRecordType(){
		return 'overdrive';
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
		// TODO: Implement hasFullText() method.
	}


	/**
	 * Does this record support an RDF representation?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasRDF(){
		// TODO: Implement hasRDF() method.
	}

	function getLanguage(){
		$metaData  = $this->getOverDriveMetaData()->getDecodedRawData();
		$languages = [];
		if (isset($metaData->languages)){
			foreach ($metaData->languages as $language){
				$languages[] = $language->name;
			}
		}
		return $languages;
	}

	function getLanguages(){
		return $this->getLanguage();
	}

	private $availability = null;

	/**
	 * Get Available copy information for this OverDrive title
	 * @return Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductAvailability[]
	 */
	function getAvailability(){
		if ($this->availability == null){
			$this->availability = [];
			if (!empty($this->overDriveProduct->id)){ // Don't do the below search when there isn't an ID to look for.
				$availability            = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductAvailability();
				$availability->productId = $this->overDriveProduct->id;//Only include shared collection if include digital collection is on
				$searchLibrary           = Library::getSearchLibrary();
				$searchLocation          = Location::getSearchLocation();
				$includeSharedTitles     = true;
				if ($searchLocation != null){
					$includeSharedTitles = $searchLocation->enableOverdriveCollection != 0;
				}elseif ($searchLibrary != null){
					$includeSharedTitles = $searchLibrary->enableOverdriveCollection != 0;
				}
				$pikaLibraryIdForOverDriveAdvantageAccount = $this->getLibraryIdForOverDriveAdvantageAccount();
				//TODO: provide shared account id; if there are ever multiple shared accounts with shared advantage accounts as well
				if ($includeSharedTitles){
					$sharedCollectionId = $searchLibrary->sharedOverdriveCollection;
					if ($pikaLibraryIdForOverDriveAdvantageAccount){
						// Shared collection and advantage collection
						$availability->whereAdd('libraryId = ' . $sharedCollectionId . ' OR libraryId = ' . $pikaLibraryIdForOverDriveAdvantageAccount);
					} else {
						// Just shared collection
						$availability->whereAdd('libraryId = ' . $sharedCollectionId);
					}
				}else{
					if ($pikaLibraryIdForOverDriveAdvantageAccount){
						$availability->whereAdd('libraryId = ' . $pikaLibraryIdForOverDriveAdvantageAccount);
					}else{
						//Not including shared titles and no advantage account, so return empty availability info
						return $this->availability;
					}
				}
				$availability->find();
				while ($availability->fetch()){
					$this->availability[] = clone $availability;
				}
			}
		}
		return $this->availability;
	}
	/**
	 * @return array
	 */
	public function getScopedAvailability(){
		$availability                          = [
			'mine'  => $this->getAvailability(),
			'other' => []
		];

		$libraryIdForOverDriveAdvantageAccount = $this->getLibraryIdForOverDriveAdvantageAccount();
		//TODO: provide shared account id; if there are ever multiple shared accounts with shared advantage accounts as well
		if ($libraryIdForOverDriveAdvantageAccount){
			foreach ($availability['mine'] as $key => $availabilityItem){
				if ($availabilityItem->libraryId > 0 && $availabilityItem->libraryId != $libraryIdForOverDriveAdvantageAccount){
					// Move items not in the shared collections and not in the library's advantage collection to the "other" category.
					//TODO: does this need reworked with multiple shared collections
					$availability['other'][$key] = $availability['mine'][$key];
					unset($availability['mine'][$key]);
				}
			}
		}
		return $availability;
	}

	/**
	 * Get the most appropriate library Id to use for looking up an Advantage Account's pika Library Id.
	 * At this point, used only for calculating available copies counts
	 *
	 * @param int|null $sharedCollectionId  The shared account associated with this Advantage Account
	 *                                      If none is provided, the first shared account is assumed.
	 * @return false|int libraryId for the appropriate OverDrive Advantage Account
	 */
	public function getLibraryIdForOverDriveAdvantageAccount(int $sharedCollectionId = null){
		//For eContent, we need to be more specific when restricting copies
		//since patrons can't use copies that are only available to other libraries.

		global $configArray;
		if (!empty($configArray['OverDrive']['sharedAdvantageName'])){
			// When using shared Advantage accounts;
			// the library id corresponds to the index of shared main account eg 1 for the first; 2 for the second
			return empty($sharedCollectionId) ? 1 : abs($sharedCollectionId);
			// if we don't know what the shared collection is, fallback to assuming that it would be the first one
		}

		$homeLibrary = UserAccount::getUserHomeLibrary();
		if (!empty($homeLibrary)){
			return $homeLibrary->libraryId;
		}
		$activeLocation = Location::getActiveLocation();
		if (!empty($activeLocation)){
			return $activeLocation->libraryId;
		}
		$activeLibrary = Library::getActiveLibrary();
		if (!empty($activeLibrary)){
			return $activeLibrary->libraryId;
		}
		$searchLocation = Location::getSearchLocation();
		if (!empty($searchLocation)){
			return $searchLocation->libraryId;
		}
		$searchLibrary = Library::getSearchLibrary();
		if (!empty($searchLibrary)){
			return $searchLibrary->libraryId;
		}
		return false;
	}

	public function getDescriptionFast(){
		$metaData = $this->getOverDriveMetaData();
		return $metaData->fullDescription;
	}

	public function getDescription(){
		$metaData = $this->getOverDriveMetaData();
		return $metaData->fullDescription;
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

	// TODO: document
	public function getCleanISBNs(){
		require_once ROOT_DIR . '/sys/ISBN/ISBN.php';

		// Get all the ISBNs and initialize the return value:
		$isbns      = $this->getISBNs();
		$cleanIsbns = [];
		// Loop through the ISBNs:
		foreach ($isbns as $isbn){
			// Strip off any unwanted notes:
			if ($pos = strpos($isbn, ' ')){
				$isbn = substr($isbn, 0, $pos);
			}

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
	 * Get an array of all ISBNs associated with the record (may be empty).
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getISBNs(){
		//Load ISBNs for the product
		if ($this->isbns == null){
			$overDriveIdentifiers            = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductIdentifiers();
			$overDriveIdentifiers->type      = 'ISBN';
			$overDriveIdentifiers->productId = $this->overDriveProduct->id;
			$this->isbns                     = [];
			$overDriveIdentifiers->find();
			while ($overDriveIdentifiers->fetch()){
				$this->isbns[] = $overDriveIdentifiers->value;
			}
		}
		return $this->isbns;
	}

	/**
	 * Get an array of all UPCs associated with the record (may be empty).
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getUPCs(){
		//Load UPCs for the product
		if ($this->upcs == null){
			$overDriveIdentifiers            = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductIdentifiers();
			$overDriveIdentifiers->type      = 'UPC';
			$overDriveIdentifiers->productId = $this->overDriveProduct->id;
			$this->upcs                      = [];
			$overDriveIdentifiers->find();
			while ($overDriveIdentifiers->fetch()){
				$this->upcs[] = $overDriveIdentifiers->value;
			}
		}
		return $this->upcs;
	}

	public function getAcceleratedReaderData(){
		return $this->getGroupedWorkDriver()->getAcceleratedReaderData();
	}

	public function getAcceleratedReaderDisplayString(){
		return $this->getGroupedWorkDriver()->getAcceleratedReaderDisplayString();
	}

	public function getLexileCode(){
		return $this->getGroupedWorkDriver()->getLexileCode();
	}

	public function getLexileScore(){
		return $this->getGroupedWorkDriver()->getLexileScore();
	}

	public function getLexileDisplayString(){
		return $this->getGroupedWorkDriver()->getLexileDisplayString();
	}

	public function getFountasPinnellLevel(){
		return $this->getGroupedWorkDriver()->getFountasPinnellLevel();
	}

	public function getSubjects(){
		return $this->getOverDriveMetaData()->getDecodedRawData()->subjects;
	}

	/**
	 * Get an array of all ASINs associated with the record (may be empty).
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getASINs(){
		//Load UPCs for the product
		if ($this->asins == null){
			$overDriveIdentifiers            = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductIdentifiers();
			$overDriveIdentifiers->type      = 'ASIN';
			$overDriveIdentifiers->productId = $this->overDriveProduct->id;
			$this->asins                     = [];
			$overDriveIdentifiers->find();
			while ($overDriveIdentifiers->fetch()){
				$this->asins[] = $overDriveIdentifiers->value;
			}
		}
		return $this->asins;
	}

	private $title;

	/**
	 * Get the full title of the record.
	 *
	 * @return  string
	 */
	public function getTitle(){
		if (!isset($this->title)){
			$shortTitle = $this->overDriveProduct->title;
			if (!empty($shortTitle)){
				$subTitle       = $this->getSubtitle();
				$subTitleLength = strlen($subTitle);
				if ($subTitleLength > 0 && strcasecmp($subTitle, $shortTitle) !== 0){ // If the short title and the subtitle are the same skip this check
					if (strcasecmp(substr($shortTitle, -$subTitleLength), $subTitle) === 0){ // TODO: do these tests work with multibyte characters? Diacritic characters?
						// If the subtitle is at the end of the short title, trim out the subtitle from the short title
						$shortTitle = trim(rtrim(trim(substr($shortTitle, 0, -$subTitleLength)), ':'));
						// remove ending white space and colon characters
					}
				}
			}
			$this->title = $shortTitle;
		}
		return $this->title;
	}

	/**
	 * Get the subtitle of the record.
	 *
	 * @return  string
	 */
	public function getSubTitle(){
		return $this->overDriveProduct->subtitle;
	}
	/**
	 * Is the record a magazine
	 *
	 * @return  bool
	 */
	public function isMagazine(){
		return $this->overDriveProduct->mediaType == 'Magazine';
//		if (in_array("OverDrive Magazine",$this->getFormats())){
//			return true;
//		}
//		return false;
	}

	/**
	 * Return the media type for this product.
	 * Potential Options are :  Audiobook, eBook, Video, Magazine
	 * @return string|null
	 */
	public function getOverDriveMediaType(){
		return $this->overDriveProduct->mediaType;
	}
	/**
	 * Get an array of all the formats associated with the record.
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getFormats(){
		$formats = [];
		foreach ($this->getItems() as $item){
			$formats[] = $item->name;
		}
		return $formats;
	}

	/**
	 * @return Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductFormats[]
	 */
	public function getItems(){
		if ($this->items == null){
			$overDriveFormats = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductFormats();
			$this->items      = [];
			if ($this->valid){
				$overDriveFormats->productId = $this->overDriveProduct->id;
				$overDriveFormats->find();
				while ($overDriveFormats->fetch()){
					$this->items[] = clone $overDriveFormats;
				}
			}

			global $timer;
			$timer->logTime("Finished getItems for OverDrive record {$this->overDriveProduct->id}");
		}
		return $this->items;
	}

	public function getAuthor(){
		return $this->overDriveProduct->primaryCreatorName;
	}

	public function getPrimaryAuthor(){
		return $this->overDriveProduct->primaryCreatorName;
	}

	public function getContributors(){
		return [];
	}

	public function getBookcoverUrl($size = 'small', $absolutePath = false){
		global $configArray;
		if ($absolutePath){
			$bookCoverUrl = $configArray['Site']['url'];
		}else{
			$bookCoverUrl = '';
		}
		$bookCoverUrl .= '/bookcover.php?size=' . $size;
		$bookCoverUrl .= '&id=' . $this->id;
		$bookCoverUrl .= '&type=overdrive';
		return $bookCoverUrl;
	}

	public function getCoverUrl($size = 'small'){
		return $this->getBookcoverUrl($size);
	}

	private function getOverDriveMetaData(){
		if ($this->overDriveMetaData == null){
			$this->overDriveMetaData            = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductMetaData();
			$this->overDriveMetaData->productId = $this->overDriveProduct->id;
			$this->overDriveMetaData->find(true);
		}
		return $this->overDriveMetaData;
	}

	public function getRatingData(){
		require_once ROOT_DIR . '/services/API/WorkAPI.php';
		$workAPI       = new WorkAPI();
		$groupedWorkId = $this->getGroupedWorkId();
		if ($groupedWorkId == null){
			return null;
		}else{
			return $workAPI->getRatingData($this->getGroupedWorkId());
		}
	}

	public function getMoreDetailsOptions(){
		global $interface;

		$isbn = $this->getCleanISBN();

		//Load overDrive Title Holdings information from the driver

		/** @var OverDriveAPIProductFormats[] $overDriveTitleHoldings */
		$overDriveTitleHoldings = $this->getHoldings();

		$scopedAvailability     = $this->getScopedAvailability();
		$interface->assign('overDriveTitleHoldings', $overDriveTitleHoldings);
		$interface->assign('availability', $scopedAvailability['mine']);
		$interface->assign('availabilityOther', $scopedAvailability['other']);
		$interface->assign('id', $this->id);
		$numberOfHolds = 0;
		foreach ($scopedAvailability['mine'] as $availability){
			if ($availability->numberOfHolds > 0){
				$numberOfHolds = $availability->numberOfHolds;
				break;
			}
		}
		$interface->assign('numberOfHolds', $numberOfHolds);
		$showAvailability      = true;
		$showAvailabilityOther = true;
		$interface->assign('showAvailability', $showAvailability);
		$interface->assign('showAvailabilityOther', $showAvailabilityOther);

		//Load more details options
		$moreDetailsOptions            = $this->getBaseMoreDetailsOptions($isbn);
		$moreDetailsOptions['formats'] = [
			'label'         => 'Formats',
			'body'          => $interface->fetch('OverDrive/view-formats.tpl'),
			'openByDefault' => true
		];
		//Other editions if applicable (only if we aren't the only record!)
		$relatedRecords = $this->getGroupedWorkDriver()->getRelatedRecords();
		if (count($relatedRecords) > 1){
			$interface->assign('relatedManifestations', $this->getGroupedWorkDriver()->getRelatedManifestations());
			$moreDetailsOptions['otherEditions'] = [
				'label'         => 'Other Editions and Formats',
				'body'          => $interface->fetch('GroupedWork/relatedManifestations.tpl'),
				'hideByDefault' => false
			];
		}
		if ($this->isMagazine()){
			$moreDetailsOptions['issues'] = [
				'label' => 'Magazine Issues',
				'body'  => $interface->fetch('OverDrive/view-issues.tpl'),
				'hideByDefault' => false
			];
		}
		$moreDetailsOptions['moreDetails'] = [
			'label' => 'More Details',
			'body'  => $interface->fetch('OverDrive/view-more-details.tpl'),
		];
		$moreDetailsOptions['subjects'] = [
			'label' => 'Subjects',
			'body' => $interface->fetch('OverDrive/view-subjects.tpl'),
		];
		$moreDetailsOptions['citations']   = [
			'label' => 'Citations',
			'body'  => $interface->fetch('Record/cite.tpl'),
		];
		$moreDetailsOptions['copyDetails'] = [
			'label' => 'Copy Details',
			'body'  => $interface->fetch('OverDrive/view-copies.tpl'),
		];

		if ($interface->getVariable('showStaffView')){
			$moreDetailsOptions['staff'] = [
				'label' => 'Staff View',
				'body'  => $interface->fetch($this->getStaffView()),
			];
		}

		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	public function getRecordUrl(){
		$id      = $this->getUniqueID();
		$linkUrl = '/OverDrive/' . $id . '/Home';
		return $linkUrl;
	}

	function getAbsoluteUrl(){
		global $configArray;
		$recordId = $this->getUniqueID();

		return $configArray['Site']['url'] . '/' . $this->getModule() . '/' . $recordId;
	}

	/**
	 * A relative URL that is a link to the Full Record View AND additional search parameters
	 * to the recent search the user has navigated from
	 *
	 * @return string
	 */
	public function getLinkUrl(){
		return parent::getLinkUrl();
	}

	function getQRCodeUrl(){
		global $configArray;
		return $configArray['Site']['url'] . '/qrcode.php?type=OverDrive&id=' . $this->getPermanentId();
	}

	private function getPublishers(){
		$publishers = [];
		if (isset($this->overDriveMetaData->publisher)){
			$publishers[] = $this->overDriveMetaData->publisher;
		}
		return $publishers;
	}

	private function getPublicationDates(){
		$publicationDates = [];
		if (isset($this->getOverDriveMetaData()->getDecodedRawData()->publishDateText)){
			$publishDate        = $this->getOverDriveMetaData()->getDecodedRawData()->publishDateText;
			$publishYear        = substr($publishDate, -4);
			$publicationDates[] = $publishYear;
		}
		return $publicationDates;
	}

	private function getPlacesOfPublication(){
		return [];
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
			$publicationInfo = (isset($places[$i]) ? $places[$i] . ' ' : '') .
				(isset($names[$i]) ? $names[$i] . ' ' : '') .
				(isset($dates[$i]) ? $dates[$i] : '');
			$returnVal[]     = trim(str_replace('  ', ' ', $publicationInfo));
			$i++;
		}

		return $returnVal;
	}

	public function getEdition($returnFirst = false){
		$edition = $this->overDriveMetaData->getDecodedRawData()->edition ?? null;
		if ($returnFirst || is_null($edition)){
			return $edition;
		}else{
			return [$edition];
		}
	}

	public function getStreetDate(){
		return isset($this->overDriveMetaData->getDecodedRawData()->publishDateText) ? $this->overDriveMetaData->getDecodedRawData()->publishDateText : null;
	}

	public function getGroupedWorkDriver(){
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		if ($this->groupedWorkDriver == null){
			$this->groupedWorkDriver = new GroupedWorkDriver($this->getPermanentId());
		}
		return $this->groupedWorkDriver;
	}

	public function getTags(){
		return $this->getGroupedWorkDriver()->getTags();
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
		}elseif (in_array('Journal', $formats)){
			$format = 'Journal';
		}else{
			$format = $formats[0];
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

	public function getItemActions($itemInfo){
		return [];
	}

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $relatedUrls = null, $volumeData = null){
		$actions = [];
		if ($isAvailable){
			$actions[] = [
				'title'        => 'Check Out OverDrive',
				'onclick'      => "return Pika.OverDrive.checkOutOverDriveTitle('{$this->id}');",
				'requireLogin' => false,
			];
		}else{
			$actions[] = [
				'title'        => 'Place Hold OverDrive',
				'onclick'      => "return Pika.OverDrive.placeOverDriveHold('{$this->id}');",
				'requireLogin' => false,
			];
		}
		return $actions;
	}

	function getNumHolds(){
		$totalHolds = 0;
		/** @var Pika\BibliographicDrivers\OverDrive\OverDriveAPIProductAvailability $availabilityInfo */
		foreach ($this->getAvailability() as $availabilityInfo){
			//Holds is set once for everyone so don't add them up.
			if ($availabilityInfo->numberOfHolds > $totalHolds){
				$totalHolds = $availabilityInfo->numberOfHolds;
			}
		}
		return $totalHolds;
	}

	function getVolumeHolds($volumeData){
		return 0;
	}


	public function getSemanticData(){
		// Schema.org
		// Get information about the record
		require_once ROOT_DIR . '/RecordDrivers/LDRecordOffer.php';
		$linkedDataRecord = new LDRecordOffer($this->getRelatedRecord());
		$semanticData []  = [
			'@context'            => 'http://schema.org',
			'@type'               => $linkedDataRecord->getWorkType(),
			'name'                => $this->getTitle(),                             //getTitleSection(),
			'creator'             => $this->getAuthor(),
			'bookEdition'         => $this->getEdition(),
			'isAccessibleForFree' => true,
			'image'               => $this->getBookcoverUrl('large', true),
			"offers"              => $linkedDataRecord->getOffers()
		];

		global $interface;
		$interface->assign('og_title', $this->getTitle());
		$interface->assign('og_type', $this->getGroupedWorkDriver()->getOGType());
		$interface->assign('og_image', $this->getBookcoverUrl('large', true));
		$interface->assign('og_url', $this->getAbsoluteUrl());
		return $semanticData;
	}

	function getRelatedRecord(){
		$id = 'overdrive:' . $this->id;
		return $this->getGroupedWorkDriver()->getRelatedRecord($id);
	}

	/**
	 * This is for retrieving Volume Records, which are a collection of item records of a Bib. (eg Part 1 of a DVD set would
	 * be a volume record, part 2 another volume record ) This is different from the volume on an item record.
	 * @return IlsVolumeInfo[]  An array of VolumeInfoObjects
	 */
	function getVolumeInfoForRecord(){
		return [];
	}

	/**
	 * An Array of basic template information. Essentially determines whether or not to show place hold or check out buttons.
	 * @return array
	 */
	public function getStatusSummary(){
		$statusSummary   = [];
		$availableCopies = 0;
		$totalCopies     = 0;
		$availabilities  = $this->getAvailability();
		$isCopies        = count($availabilities) > 0;
		if ($isCopies){
			foreach ($availabilities as $curAvailability){
				$availableCopies += $curAvailability->copiesAvailable;
				$totalCopies     += $curAvailability->copiesOwned;
			}
		}

		//Set status summary
		if ($availableCopies > 0){
			$statusSummary['status'] = 'Available from OverDrive';
			$statusSummary['class']  = 'available';
		}else{
			$statusSummary['status'] = 'Checked Out';
			$statusSummary['class']  = 'checkedOut';
		}

		//Determine which buttons to show
		$statusSummary['showPlaceHold'] = $isCopies && $availableCopies == 0;
		$statusSummary['showCheckout']  = $isCopies && $availableCopies > 0;

		return $statusSummary;
	}

}
