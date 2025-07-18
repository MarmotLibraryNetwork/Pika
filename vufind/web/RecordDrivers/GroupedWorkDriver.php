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

/**
 * GroupedWorkDriver Class
 *
 * This class handles the display of Grouped Works within Pika.
 *
 * @category Pika
 * @author <pika@marmot.org>
 * Date: 11/26/13
 * Time: 1:51 PM
 */
use \Pika\Logger;
require_once ROOT_DIR . '/RecordDrivers/Interface.php';


class GroupedWorkDriver extends RecordInterface {

	protected $fields;
	public $isValid = true;
	private $logger;
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
	 * Should we include snippets in search results?
	 *
	 * @var    bool
	 * @access protected
	 */
	protected $snippet = false;
	protected $highlight = false;
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
	 * GroupedWorkDriver constructor.
	 *
	 * @param array|string $indexFields  An array of the solr document fields, or grouped work Id as a string
	 */
	public function __construct($indexFields){

		$this->logger = new Logger(__CLASS__);

		if (is_string($indexFields)){
			$id = $indexFields;
			$id = str_replace('groupedWork:', '', $id);
			//Just got a record id, let's load the full record from Solr
			// Setup Search Engine Connection
			/** @var SearchObject_Solr $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject();
			if (function_exists('disableErrorHandler')){
				disableErrorHandler();
			}

			// Retrieve the record from Solr
			if (!($record = $searchObject->getRecord($id))){
				$this->isValid = false;
			}else{
				$this->fields = $record;
			}
			if (function_exists('enableErrorHandler')){
				enableErrorHandler();
			}

		}elseif (is_array($indexFields)){
			$this->fields = $indexFields;
			// Load highlighting/snippet preferences:
			global $configArray;
			$searchSettings        = getExtraConfigArray('searches');
			$this->highlight       = $configArray['Index']['enableHighlighting'];
			$this->snippet         = $configArray['Index']['enableSnippets'];
			$this->snippetCaptions = empty($searchSettings['Snippet_Captions']) ? [] : $searchSettings['Snippet_Captions'];
		} else {
			$this->isValid = false;
		}
	}

	public function isValid(){
		return $this->isValid;
	}

	public function getSolrField($fieldName){
		return $this->fields[$fieldName] ?? null;
	}

	private static function normalizeEdition($edition){
		$edition = strtolower($edition);
		$edition = str_replace('first', '1', $edition);
		$edition = str_replace('second', '2', $edition);
		$edition = str_replace('third', '3', $edition);
		$edition = str_replace('fourth', '4', $edition);
		$edition = str_replace('fifth', '5', $edition);
		$edition = str_replace('sixth', '6', $edition);
		$edition = str_replace('seventh', '7', $edition);
		$edition = str_replace('eighth', '8', $edition);
		$edition = str_replace('ninth', '9', $edition);
		$edition = str_replace('tenth', '10', $edition);
		$edition = str_replace('eleventh', '11', $edition);
		$edition = str_replace('twelfth', '12', $edition);
		$edition = str_replace('thirteenth', '13', $edition);
		$edition = str_replace('fourteenth', '14', $edition);
		$edition = str_replace('fifteenth', '15', $edition);
		$edition = preg_replace('/\D/', '', $edition);
		return $edition;
	}

	public function getContributors(){
		return $this->fields['author2-role']; //Include the role when displaying contributor
	}

	private $detailedContributors = null;

	public function getDetailedContributors(){
		if ($this->detailedContributors == null){
			$this->detailedContributors = array();
			if (isset($this->fields['author2-role'])){
				$contributorsInIndex = $this->fields['author2-role'];
				if (is_string($contributorsInIndex)){
					$contributorsInIndex = [$contributorsInIndex];
				}
				foreach ($contributorsInIndex as $contributor){
					if (strpos($contributor, '|')){
						$contributorInfo = explode('|', $contributor);
						$curContributor  = [
							'name' => $contributorInfo[0],
							'role' => $contributorInfo[1],
						];
					}else{
						$curContributor = [
							'name' => $contributor,
						];
					}
					$this->detailedContributors[] = $curContributor;
				}
			}
		}
		return $this->detailedContributors;
	}

	public function getPermanentId(){
		return $this->fields['id'];
	}

	public function getMpaaRating(){
		return $this->fields['mpaaRating'];
	}

	/**
	 * Get text that can be displayed to represent this record in
	 * breadcrumbs.
	 *
	 * @access  public
	 * @return  string              Breadcrumb text to represent this record.
	 */
	public function getBreadcrumb(){
		return $this->getTitleShort();
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
		$authors = array();
		$primary = $this->getPrimaryAuthor();
		if (!empty($primary)){
			$authors[] = $primary;
		}
		//$authors = array_unique(array_merge($authors, $this->getSecondaryAuthors()));

		// Collect all details for citation builder:
		$publishers = $this->getPublishers();
		$pubDates   = $this->getPublicationDates();
		//$pubPlaces = $this->getPlacesOfPublication();
		$details = array(
			'authors'  => $authors,
			'title'    => $this->getTitleShort(),
			'subtitle' => $this->getSubtitle(),
			//			'pubPlace' => count($pubPlaces) > 0 ? $pubPlaces[0] : null,
			'pubName'  => count($publishers) > 0 ? $publishers[0] : null,
			'pubDate'  => count($pubDates) > 0 ? $pubDates[0] : null,
			'edition'  => $this->getEdition(),
			'format'   => $this->getFormats()
		);

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
	 * Get an array of search results for other editions of the title
	 * represented by this record (empty if unavailable).  In most cases,
	 * this will use the XISSN/XISBN logic to find matches.
	 *
	 * @access  public
	 * @return  mixed               Editions in index engine result format.
	 *                              (or null if no hits, or PEAR_Error object).
	 */
//	public function getEditions(){
//		// TODO: Implement getEditions() method.
//	}

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
		global $configArray;
		global $interface;
		global $timer;

		$id = $this->getUniqueID();
		$timer->logTime("Starting to load search result for grouped work $id");
		$interface->assign('summId', $id);
		if (substr($id, 0, 1) == '.'){
			$interface->assign('summShortId', substr($id, 1));
		}else{
			$interface->assign('summShortId', $id);
		}

		$relatedManifestations = $this->getRelatedManifestations();
		$interface->assign('relatedManifestations', $relatedManifestations);

		//Build the link URL.
		//If there is only one record for the work we will link straight to that.
		$linkUrl = $this->getMoreInfoLinkUrl();
		$linkUrl .= '?searchId=' . $interface->get_template_vars('searchId') . '&amp;recordIndex=' . $interface->get_template_vars('recordIndex') . '&amp;page=' . $interface->get_template_vars('page');

		$interface->assign('summUrl', $linkUrl);
		$interface->assign('summTitle', $this->getTitleShort());
		$interface->assign('summSubTitle', $this->getSubtitle());
		$interface->assign('summAuthor', $this->getPrimaryAuthor());
		$isbn = $this->getCleanISBN();
		$interface->assign('summISBN', $isbn);
		$interface->assign('summFormats', $this->getFormats());

		$this->assignBasicTitleDetails();


		$interface->assign('numRelatedRecords', $this->getNumRelatedRecords());

		if ($configArray['System']['debugSolr']){
			$interface->assign('summScore', $this->getScore());
			$interface->assign('summExplain', $this->getExplain());
		}

		//Get Rating
		$interface->assign('summRating', $this->getRatingData());

		//Description
		$interface->assign('summDescription', $this->getDescriptionFast());
		$timer->logTime('Finished Loading Description');
		if ($this->hasCachedSeries()){
			$interface->assign('ajaxSeries', false);
			$interface->assign('summSeries', $this->getSeries());
		}else{
			$interface->assign('ajaxSeries', true);
			$interface->assign('summSeries', '');
		}

		$timer->logTime('Finished Loading Series');

		//Get information from list entry
		if ($listId){
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry                         = new UserListEntry();
			$listEntry->groupedWorkPermanentId = $this->getUniqueID();
			$listEntry->listId                 = $listId;
			if ($listEntry->find(true)){
				$interface->assign('listEntryNotes', $listEntry->notes);
			}else{
				$interface->assign('listEntryNotes', '');
			}
			$interface->assign('listEditAllowed', $allowEdit);
		}
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		// By default, do not display AJAX status; we won't assume that all
		// records exist in the ILS.  Child classes can override this setting
		// to turn on AJAX as needed:
		$interface->assign('summAjaxStatus', false);

		$interface->assign('recordDriver', $this);

		return 'RecordDrivers/GroupedWork/listentry.tpl';
	}

	public function getSuggestionEntry(){
		global $interface;
		global $timer;

		$id = $this->getUniqueID();
		$timer->logTime("Starting to load search result for grouped work $id");
		$interface->assign('summId', $id);
		if (substr($id, 0, 1) == '.'){
			$interface->assign('summShortId', substr($id, 1));
		}else{
			$interface->assign('summShortId', $id);
		}

		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summTitle', $this->getTitleShort());
		$interface->assign('summSubTitle', $this->getSubtitle());
		$interface->assign('summAuthor', $this->getPrimaryAuthor());
		$isbn = $this->getCleanISBN();
		$interface->assign('summISBN', $isbn);
		$interface->assign('summFormats', $this->getFormats());

		$interface->assign('numRelatedRecords', $this->getNumRelatedRecords());

		$relatedManifestations = $this->getRelatedManifestations();
		$interface->assign('relatedManifestations', $relatedManifestations);

		//Get Rating
		$interface->assign('summRating', $this->getRatingData());

		//Description
		$interface->assign('summDescription', $this->getDescriptionFast());
		$timer->logTime('Finished Loading Description');
		if ($this->hasCachedSeries()){
			$interface->assign('ajaxSeries', false);
			$interface->assign('summSeries', $this->getSeries());
		}else{
			$interface->assign('ajaxSeries', true);
		}
		$timer->logTime('Finished Loading Series');

		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		$interface->assign('recordDriver', $this);

		return 'RecordDrivers/GroupedWork/suggestionEntry.tpl';
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
	 * @param string $view The current view.
	 *
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getSearchResult($view = 'list'){
		if ($view == 'covers'){ // Displaying Results as bookcover tiles
			return $this->getBrowseResult();
		}

		// Displaying results as the default list
		global $configArray;
		global $interface;
		global $timer;
		global $memoryWatcher;

		$interface->assign('displayingSearchResults', true);

		$id = $this->getUniqueID();
		$timer->logTime("Starting to load search result for grouped work $id");
		$interface->assign('summId', $id);
		if (substr($id, 0, 1) == '.'){
			$interface->assign('summShortId', substr($id, 1));
		}else{
			$interface->assign('summShortId', $id);
		}
		$relatedManifestations = $this->getRelatedManifestations();
		$interface->assign('relatedManifestations', $relatedManifestations);
		$timer->logTime("Loaded related manifestations");
		$memoryWatcher->logMemory("Loaded related manifestations for {$this->getUniqueID()}");

		//Build the link URL.
		//If there is only one record for the work we will link straight to that.
		$relatedRecords = $this->getRelatedRecords();
		$timer->logTime('Loaded related records');
		$memoryWatcher->logMemory('Loaded related records');
		if (count($relatedRecords) == 1){
			$firstRecord = reset($relatedRecords);
			$linkUrl     = $firstRecord['url'];
			//We use resultIndex here instead of recordIndex to fix a previous/next button display issue
			$linkUrl     .= '?searchId=' . $interface->get_template_vars('searchId') . '&amp;recordIndex=' . $interface->get_template_vars('resultIndex') . '&amp;page=' . $interface->get_template_vars('page');
		}else{
			//We use resultIndex here instead of recordIndex to fix a previous/next button display issue
			$linkUrl = '/GroupedWork/' . $id . '/Home?searchId=' . $interface->get_template_vars('searchId') . '&amp;recordIndex=' . $interface->get_template_vars('resultIndex') . '&amp;page=' . $interface->get_template_vars('page');
			$linkUrl .= '&amp;searchSource=' . $interface->get_template_vars('searchSource');
		}

		$interface->assign('summUrl', $linkUrl);
		$interface->assign('summTitle', $this->getTitleShort(true));
		$interface->assign('summSubTitle', $this->getSubtitle(true));
		$interface->assign('summAuthor', rtrim($this->getPrimaryAuthor(true), ','));
		$isbn = $this->getCleanISBN();
		$interface->assign('summISBN', $isbn);
		$interface->assign('summFormats', $this->getFormats());
		$interface->assign('numRelatedRecords', count($relatedRecords));
		$acceleratedReaderInfo = $this->getAcceleratedReaderDisplayString();
		$interface->assign('summArInfo', $acceleratedReaderInfo);
		$lexileInfo = $this->getLexileDisplayString();
		$interface->assign('summLexileInfo', $lexileInfo);
		$interface->assign('summFountasPinnell', $this->getFountasPinnellLevel());
		$timer->logTime("Finished assignment of main data");
		$memoryWatcher->logMemory("Finished assignment of main data");

		// Obtain and assign snippet (highlighting) information:
		$snippets = $this->getHighlightedSnippets();
		$interface->assign('summSnippets', $snippets);

		//Generate COinS URL for Zotero support
		$interface->assign('summCOinS', $this->getOpenURL());

		$summPublisher    = null;
		$summPubDate      = null;
		$summPhysicalDesc = null;
		$summEdition      = null;
		$summLanguage     = null;
		$isFirst          = true;
		global $library;
		$alwaysShowMainDetails = $library ? $library->alwaysShowSearchResultsMainDetails : false;
		foreach ($relatedRecords as $relatedRecord){
			if ($isFirst){
				$summPublisher    = $relatedRecord['publisher'];
				$summPubDate      = $relatedRecord['publicationDate'];
				$summPhysicalDesc = $relatedRecord['physical'];
				$summEdition      = $relatedRecord['edition'];
				$summLanguage     = $relatedRecord['language'];
			}else{
				// Only display these details if it is the same for every related record, otherwise don't populate
				// (or show the Varies by edition statement)
				if ($summPublisher != $relatedRecord['publisher']){
					$summPublisher = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summPubDate != $relatedRecord['publicationDate']){
					$summPubDate = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summPhysicalDesc != $relatedRecord['physical']){
					$summPhysicalDesc = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summEdition != $relatedRecord['edition']){
					$summEdition = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summLanguage != $relatedRecord['language']){
					$summLanguage = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
			}
			$isFirst = false;
		}
		$showBookshelf = $library ? $library->showFavorites: false;
		$interface->assign('showBookshelf', $showBookshelf);
		$interface->assign('summPublisher', rtrim($summPublisher, ','));
		$interface->assign('summPubDate', $summPubDate);
		$interface->assign('summPhysicalDesc', $summPhysicalDesc);
		$interface->assign('summEdition', $summEdition);
		$interface->assign('summLanguage', $summLanguage);
		$timer->logTime("Finished assignment of data based on related records");

		if ($configArray['System']['debugSolr']){
			$interface->assign('summScore', $this->getScore());
			$interface->assign('summExplain', $this->getExplain());
		}
		$timer->logTime("Finished assignment of data based on solr debug info");

		//Get Rating
		$interface->assign('summRating', $this->getRatingData());
		$timer->logTime("Finished loading rating data");

		//Description
		$interface->assign('summDescription', $this->getDescriptionFast(true));
		$timer->logTime('Finished Loading Description');
		$memoryWatcher->logMemory("Finished Loading Description");
		if ($this->hasCachedSeries()){
			$interface->assign('ajaxSeries', false);
			$interface->assign('summSeries', $this->getSeries(false));
		}else{
			$interface->assign('ajaxSeries', true);
			$interface->assign('summSeries', null);
		}
		$timer->logTime('Finished Loading Series');
		$memoryWatcher->logMemory("Finished Loading Series");

		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		// By default, do not display AJAX status; we won't assume that all
		// records exist in the ILS.  Child classes can override this setting
		// to turn on AJAX as needed:
		$interface->assign('summAjaxStatus', false);

		$interface->assign('recordDriver', $this);

		return 'RecordDrivers/GroupedWork/result.tpl';
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @param string $view The current view.
	 *
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getCombinedResult($view = 'list'){
		if ($view == 'covers'){ // Displaying Results as bookcover tiles
			return $this->getBrowseResult();
		}

		// Displaying results as the default list
		global $configArray;
		global $interface;
		global $timer;
		global $memoryWatcher;

		$interface->assign('displayingSearchResults', true);

		$id = $this->getUniqueID();
		$timer->logTime("Starting to load search result for grouped work $id");
		$interface->assign('summId', $id);
		if (substr($id, 0, 1) == '.'){
			$interface->assign('summShortId', substr($id, 1));
		}else{
			$interface->assign('summShortId', $id);
		}
		$relatedManifestations = $this->getRelatedManifestations();
		$interface->assign('relatedManifestations', $relatedManifestations);
		$timer->logTime("Loaded related manifestations");
		$memoryWatcher->logMemory("Loaded related manifestations for {$this->getUniqueID()}");

		$interface->assign('summUrl', $this->getMoreInfoLinkUrl());
		$interface->assign('summTitle', $this->getTitleShort(true));
		$interface->assign('summSubTitle', $this->getSubtitle(true));
		$interface->assign('summAuthor', rtrim($this->getPrimaryAuthor(true), ','));
		$isbn = $this->getCleanISBN();
		$interface->assign('summISBN', $isbn);
		$interface->assign('summFormats', $this->getFormats());
		$interface->assign('numRelatedRecords', $this->getNumRelatedRecords());
		$acceleratedReaderInfo = $this->getAcceleratedReaderDisplayString();
		$interface->assign('summArInfo', $acceleratedReaderInfo);
		$lexileInfo = $this->getLexileDisplayString();
		$interface->assign('summLexileInfo', $lexileInfo);
		$interface->assign('summFountasPinnell', $this->getFountasPinnellLevel());
		$timer->logTime("Finished assignment of main data");
		$memoryWatcher->logMemory("Finished assignment of main data");

		//Generate COinS URL for Zotero support
		$interface->assign('summCOinS', $this->getOpenURL());

		$summPublisher    = null;
		$summPubDate      = null;
		$summPhysicalDesc = null;
		$summEdition      = null;
		$summLanguage     = null;
		$isFirst          = true;
		global $library;
		$alwaysShowMainDetails = $library ? $library->alwaysShowSearchResultsMainDetails : false;
		$relatedRecords = $this->getRelatedRecords();
		foreach ($relatedRecords as $relatedRecord){
			if ($isFirst){
				$summPublisher    = $relatedRecord['publisher'];
				$summPubDate      = $relatedRecord['publicationDate'];
				$summPhysicalDesc = $relatedRecord['physical'];
				$summEdition      = $relatedRecord['edition'];
				$summLanguage     = $relatedRecord['language'];
			}else{
				// Only display these details if it is the same for every related record, otherwise don't populate
				// (or show the Varies by edition statement)
				if ($summPublisher != $relatedRecord['publisher']){
					$summPublisher = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summPubDate != $relatedRecord['publicationDate']){
					$summPubDate = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summPhysicalDesc != $relatedRecord['physical']){
					$summPhysicalDesc = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summEdition != $relatedRecord['edition']){
					$summEdition = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
				if ($summLanguage != $relatedRecord['language']){
					$summLanguage = $alwaysShowMainDetails ? translate('Varies, see individual formats and editions') : null;
				}
			}
			$isFirst = false;
		}
		$interface->assign('summPublisher', rtrim($summPublisher, ','));
		$interface->assign('summPubDate', $summPubDate);
		$interface->assign('summPhysicalDesc', $summPhysicalDesc);
		$interface->assign('summEdition', $summEdition);
		$interface->assign('summLanguage', $summLanguage);
		$timer->logTime("Finished assignment of data based on related records");

		if ($configArray['System']['debugSolr']){
			$interface->assign('summScore', $this->getScore());
			$interface->assign('summExplain', $this->getExplain());
		}
		$timer->logTime("Finished assignment of data based on solr debug info");

		//Get Rating
		$interface->assign('summRating', $this->getRatingData());
		$timer->logTime("Finished loading rating data");

		//Description
		$interface->assign('summDescription', $this->getDescriptionFast(true));
		$timer->logTime('Finished Loading Description');
		$memoryWatcher->logMemory("Finished Loading Description");
		if ($this->hasCachedSeries()){
			$interface->assign('ajaxSeries', false);
			$interface->assign('summSeries', $this->getSeries(false));
		}else{
			$interface->assign('ajaxSeries', true);
			$interface->assign('summSeries', null);
		}
		$timer->logTime('Finished Loading Series');
		$memoryWatcher->logMemory("Finished Loading Series");

		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		// By default, do not display AJAX status; we won't assume that all
		// records exist in the ILS.  Child classes can override this setting
		// to turn on AJAX as needed:
		$interface->assign('summAjaxStatus', false);

		$interface->assign('recordDriver', $this);

		return 'RecordDrivers/GroupedWork/combinedResult.tpl';
	}

	public function getBrowseResult(){
		global $interface;
		$id = $this->getUniqueID();
		$interface->assign('summId', $id);

		$url = $this->getMoreInfoLinkUrl();

		$interface->assign('summUrl', $url);
		$interface->assign('summTitle', $this->getTitleShort());
		$interface->assign('summSubTitle', $this->getSubtitle());
		$interface->assign('summAuthor', $this->getPrimaryAuthor());

		//Get Rating
		$interface->assign('ratingData', $this->getRatingData());
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));
		// Rating Settings
		global $library, $location; /** @var Library $library */
		$browseCategoryRatingsMode = null;
		if ($location){ // Try Location Setting
			$browseCategoryRatingsMode = $location->browseCategoryRatingsMode;
		}
		if (!$browseCategoryRatingsMode){ // Try Library Setting
			$browseCategoryRatingsMode = $library->browseCategoryRatingsMode;
		}
		if (!$browseCategoryRatingsMode){
			$browseCategoryRatingsMode = 'popup';
		} // default
		$interface->assign('browseCategoryRatingsMode', $browseCategoryRatingsMode);

		return 'RecordDrivers/GroupedWork/browse_result.tpl';
	}

	public function getListWidgetTitle(){
		$widgetTitleInfo = [
			'id'          => $this->getPermanentId(),
			'shortId'     => $this->getPermanentId(),
			'recordtype'  => 'grouped_work',
			'image'       => $this->getBookcoverUrl('medium'),
			'small_image' => $this->getBookcoverUrl('small'),
			'title'       => $this->getTitle(),
			'titleURL'    => $this->getAbsoluteUrl(),
			'author'      => $this->getPrimaryAuthor(),
			'description' => $this->getDescriptionFast(),
			'length'      => '',
			'publisher'   => '',
			'ratingData'  => $this->getRatingData(),
		];
		return $widgetTitleInfo;
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

		$user        = UserAccount::getLoggedInUser();
		$userIsStaff = $user && $user->isStaff();
		$interface->assign('userIsStaff', $userIsStaff);

		$fields = $this->fields;
		ksort($fields);
		$interface->assign('details', $fields);

		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		require_once ROOT_DIR . '/sys/Language/Language.php';
		$groupedWork               = new GroupedWork();
		$groupedWork->permanent_id = $this->getPermanentId();
		if ($groupedWork->find(true)){
			$groupedWorkDetails                         = [];
			$groupedWorkDetails['Grouping Title']       = $groupedWork->full_title;
			$groupedWorkDetails['Grouping Author']      = $groupedWork->author;
			$groupedWorkDetails['Grouping Category']    = $groupedWork->grouping_category;
			$groupedWorkDetails['Grouping Language']    = Language::getLanguage($groupedWork->grouping_language) . " ({$groupedWork->grouping_language})";
			$groupedWorkDetails['Last Grouping Update'] = empty($groupedWork->date_updated) ? 'Marked for re-index' : date('Y-m-d H:i:sA', $groupedWork->date_updated);
			if (array_key_exists('last_indexed', $fields)){
				$groupedWorkDetails['Last Indexed'] = date('Y-m-d H:i:sA', strtotime($fields['last_indexed']));
			}
			$interface->assign('groupedWorkDetails', $groupedWorkDetails);

			$novelistPrimaryISBN = $this->getNovelistPrimaryISBN();
			$interface->assign('novelistPrimaryISBN', empty($novelistPrimaryISBN) ? 'none' : $novelistPrimaryISBN);
		}

		return 'RecordDrivers/GroupedWork/staff-view.tpl';
	}

	/**
	 * load in order to display the Table of Contents for the title.
	 *  Returns null if no Table of Contents is available.
	 *
	 * @access  public
	 * @return  string[]|null              contents to display.
	 */
	public function getTOC(){
		$tableOfContents = array();
		foreach ($this->getRelatedRecords() as $record){
			if ($record['driver']){
				$recordTOC = $record['driver']->getTOC();
				if (is_array($recordTOC) && count($recordTOC) > 0){
					$editionDescription = "{$record['format']}";
					if ($record['edition']){
						$editionDescription .= " - {$record['edition']}";
					}
					$tableOfContents = array_merge($tableOfContents, array("<h4>From the $editionDescription</h4>"), $recordTOC);
				}
			}
		}
		return $tableOfContents;
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

	/**
	 * Get the full title of the record.
	 *
	 * @return  string
	 */
	public function getTitle($useHighlighting = false){
		// Don't check for highlighted values if highlighting is disabled:
		if ($this->highlight && $useHighlighting){
			if (isset($this->fields['_highlighting']['title_display'][0])){
				return $this->fields['_highlighting']['title_display'][0];
			}elseif (isset($this->fields['_highlighting']['title_full'][0])){
				return $this->fields['_highlighting']['title_full'][0];
			}
		}

		if (isset($this->fields['title_display'])){
			return $this->fields['title_display'];
		}elseif (isset($this->fields['title_full'])){
			return is_array($this->fields['title_full']) ? reset($this->fields['title_full']) : $this->fields['title_full'];
		}else{
			return '';
		}
	}

	public function getTitleShort($useHighlighting = false){
		// Don't check for highlighted values if highlighting is disabled:
		if ($this->highlight && $useHighlighting){
			if (isset($this->fields['_highlighting']['title_short'][0])){
				return $this->fields['_highlighting']['title_short'][0];
			}elseif (isset($this->fields['_highlighting']['title'][0])){
				return $this->fields['_highlighting']['title'][0];
			}
		}

		if (isset($this->fields['title_short'])){
			return is_array($this->fields['title_short']) ? reset($this->fields['title_short']) : $this->fields['title_short'];
		}elseif (isset($this->fields['title'])){
			return is_array($this->fields['title']) ? reset($this->fields['title']) : $this->fields['title'];
		}else{
			return '';
		}
	}

	/**
	 * Get the subtitle of the record.
	 *
	 * @access  protected
	 * @return  string
	 */
	public function getSubtitle($useHighlighting = false){
		// Don't check for highlighted values if highlighting is disabled:
		if ($useHighlighting){
			if (isset($this->fields['_highlighting']['title_sub'][0])){
				return $this->fields['_highlighting']['title_sub'][0];
			}
		}
		return $this->fields['title_sub'] ?? '';
	}

	/**
	 * Get the authors of the work.
	 *
	 * @access  protected
	 * @return  string
	 */
	public function getAuthors(){
		return $this->fields['author'] ?? null;
	}


	/**
	 * Get the main author of the record.
	 *
	 * @access  protected
	 *
	 * @return  string
	 */
	public function getPrimaryAuthor($useHighlighting = false){
		// Don't check for highlighted values if highlighting is disabled:
		// MDN: 1/26 - author actually contains more information than author display.
		//  It also includes dates lived so we will use that instead if possible
		if ($this->highlight && $useHighlighting){
			if (isset($this->fields['_highlighting']['author'][0])){
				return $this->fields['_highlighting']['author'][0];
			}elseif (isset($this->fields['_highlighting']['author_display'][0])){
				return $this->fields['_highlighting']['author_display'][0];
			}
		}
		return $this->fields['author'] ?? $this->fields['author_display'] ?? '';
	}

	public function getScore(){
			return $this->fields['score'] ?? null;
	}

	public function getExplain(){
		if (isset($this->fields['explain'])){
			$explain = explode(', result of:', $this->fields['explain'], 2);
			if (count($explain) == 2){                                                                                                                        // Break query from score explanation
				$explain[1] = preg_replace('/weight\((.*):(.*)( in \d+\))/i', 'weight(<code>$1</code>:<strong>$2</strong>$3)', $explain[1]);// highlight the solr fields and the search term of interest
				$explain[1] = preg_replace('/computed as (.*) from:/i', 'computed as <var>$1</var> from:', $explain[1]);                    // italicize the formula fragments
				return $explain[0] . '<br> result of : <p>' . nl2br(str_replace(' ', '&nbsp;', $explain[1])) . '</p>';                      // Put text back together, replace spaces with non-breaking space character, so the indentation of explaination lines display
			}else{
				return $this->fields['explain'];
			}
		}
		return '';
	}

	private $fastDescription = null;

	function getDescriptionFast($useHighlighting = false){

		// Don't check for highlighted values if highlighting is disabled:
		if ($this->highlight && $useHighlighting){
			if (isset($this->fields['_highlighting']['display_description'][0])){
				return $this->fields['_highlighting']['display_description'][0];
			}
		}
		if ($this->fastDescription != null){
			return $this->fastDescription;
		}
		$this->fastDescription = empty($this->fields['display_description']) ? '' : $this->fields['display_description'];
		return $this->fastDescription;
	}

	function getDescription(){
		$description = null;
		$cleanIsbn   = $this->getCleanISBN();
		if (!empty($cleanIsbn)){
			require_once ROOT_DIR . '/sys/ExternalEnrichment/GoDeeperData.php';
			$summaryInfo = GoDeeperData::getSummary($cleanIsbn, $this->getCleanUPC());
			if (isset($summaryInfo['summary'])){
				$description = $summaryInfo['summary'];
			}
		}
		if (empty($description)){
			$description = $this->getDescriptionFast();
		}
		if (empty($description)){
			$description = 'Description Not Provided';
		}
		return $description;
	}

	function getBookcoverUrl($size = 'small', $absolutePath = false){
		global $configArray;

		if ($absolutePath){
			$bookCoverUrl = empty($configArray['Site']['coverUrl']) ? $configArray['Site']['url'] : $configArray['Site']['coverUrl'];
		}else{
			$bookCoverUrl = '';
		}
		$bookCoverUrl .= "/bookcover.php?id={$this->getUniqueID()}&size={$size}&type=grouped_work";

		$formatCategory = $this->getFormatCategory();
		if (!empty($formatCategory)){
			$bookCoverUrl .= '&category=' . $formatCategory;
		}

		return $bookCoverUrl;
	}

	function getQRCodeUrl(){
		global $configArray;
		return $configArray['Site']['url'] . '/qrcode.php?type=GroupedWork&id=' . $this->getPermanentId();
	}

	private $archiveLink = 'unset';

	function getArchiveLink(){
		if ($this->archiveLink === 'unset'){
			$this->archiveLink = GroupedWorkDriver::getArchiveLinkForWork($this->getUniqueID());
		}
		return $this->archiveLink;
	}

	static $archiveLinksForWorkIds = [];

	/**
	 * @param string[] $groupedWorkIds
	 */
	static function loadArchiveLinksForWorks($groupedWorkIds){
		global $library;
		global $configArray;
		global $timer;
		$archiveLink = null;
		if (count($groupedWorkIds) > 0 && $library->enableArchive && !empty($configArray['Islandora']['enabled'])){
			require_once ROOT_DIR . '/sys/Islandora/IslandoraSamePikaCache.php';
			$groupedWorkIdsToSearch = [];
			foreach ($groupedWorkIds as $groupedWorkId){
				//Check for cached links
				$samePikaCache                         = new IslandoraSamePikaCache();
				$samePikaCache->groupedWorkPermanentId = $groupedWorkId;
				if ($samePikaCache->find(true)){
					GroupedWorkDriver::$archiveLinksForWorkIds[$groupedWorkId] = $samePikaCache->archiveLink;
				}else{
					GroupedWorkDriver::$archiveLinksForWorkIds[$groupedWorkId] = false;
					$samePikaCache->archiveLink                                = '';
					$samePikaCache->insert();
					$groupedWorkIdsToSearch[] = $groupedWorkId;
				}
			}

			if (isset($_REQUEST['reload'])){
				$groupedWorkIdsToSearch = $groupedWorkIds;
			}
			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			if ($searchObject->pingServer(false)){
				$searchObject->disableLogging();
				$searchObject->setDebugging(false, false);
				$query = 'mods_extension_marmotLocal_externalLink_samePika_link_s:*' . implode('* OR mods_extension_marmotLocal_externalLink_samePika_link_s:*', $groupedWorkIdsToSearch) . '*';
				$searchObject->setBasicQuery($query);
				//Clear existing filters so search filters don't apply to this query
				$searchObject->clearFilters();
				$searchObject->clearFacets();
				$searchObject->addFieldsToReturn(['mods_extension_marmotLocal_externalLink_samePika_link_s']);

				$searchObject->setLimit(count($groupedWorkIdsToSearch));

				$response = $searchObject->processSearch(true, false, true);

				if ($response && isset($response['response'])){
					//Get information about each project
					if ($searchObject->getResultTotal() > 0){
						foreach ($response['response']['docs'] as $doc){
							$firstObjectDriver = RecordDriverFactory::initRecordDriver($doc);

							$archiveLink = $firstObjectDriver->getRecordUrl();
							foreach ($groupedWorkIdsToSearch as $groupedWorkId){
								if (strpos($doc['mods_extension_marmotLocal_externalLink_samePika_link_s'], $groupedWorkId) !== false){
									$samePikaCache                         = new IslandoraSamePikaCache();
									$samePikaCache->groupedWorkPermanentId = $groupedWorkId;
									if ($samePikaCache->find(true) && $samePikaCache->archiveLink != $archiveLink){
										$samePikaCache->archiveLink = $archiveLink;
										$samePikaCache->pid         = $firstObjectDriver->getUniqueID();
										$numUpdates                 = $samePikaCache->update();
										if ($numUpdates == 0){
											global $pikaLogger;
											$pikaLogger->error('Did not update same pika cache ' . print_r($samePikaCache->_lastError, true));
										}
									}
									GroupedWorkDriver::$archiveLinksForWorkIds[$groupedWorkId] = $archiveLink;
									break;
								}
							}
						}
					}
				}
			}
			$timer->logTime('Loaded archive links for work ' . count($groupedWorkIds) . ' works');

			$searchObject = null;
			unset($searchObject);
		}
	}

	static function getArchiveLinkForWork($groupedWorkId){
		//Check to see if the record is available within the archive
		global $library;
		global $timer;
		global $configArray;
		$archiveLink = '';
		if ($library->enableArchive && !empty($configArray['Islandora']['enabled'])){
			if (array_key_exists($groupedWorkId, GroupedWorkDriver::$archiveLinksForWorkIds)){
				$archiveLink = GroupedWorkDriver::$archiveLinksForWorkIds[$groupedWorkId];
			}else{
				require_once ROOT_DIR . '/sys/Islandora/IslandoraSamePikaCache.php';
				//Check for cached links
				$samePikaCache                         = new IslandoraSamePikaCache();
				$samePikaCache->groupedWorkPermanentId = $groupedWorkId;
				$foundLink                             = false;
				if ($samePikaCache->find(true)){
					GroupedWorkDriver::$archiveLinksForWorkIds[$groupedWorkId] = $samePikaCache->archiveLink;
					$archiveLink                                               = $samePikaCache->archiveLink;
					$foundLink                                                 = true;
				}else{
					GroupedWorkDriver::$archiveLinksForWorkIds[$groupedWorkId] = false;
					$samePikaCache->archiveLink                                = '';
					$samePikaCache->insert();
				}

				if (!$foundLink || isset($_REQUEST['reload'])){
					/** @var SearchObject_Islandora $searchObject */
					$searchObject = SearchObjectFactory::initSearchObject('Islandora');
					$searchObject->init();
					$searchObject->disableLogging();
					$searchObject->setDebugging(false, false);
					$searchObject->setBasicQuery("mods_extension_marmotLocal_externalLink_samePika_link_s:*" . $groupedWorkId);
					//Clear existing filters so search filters don't apply to this query
					$searchObject->clearFilters();
					$searchObject->clearFacets();

					$searchObject->setLimit(1);

					$response = $searchObject->processSearch(true, false, true);

					if ($response && isset($response['response'])){
						//Get information about each project
						if ($searchObject->getResultTotal() > 0){
							$firstObjectDriver                     = RecordDriverFactory::initRecordDriver($response['response']['docs'][0]);
							$archiveLink                           = $firstObjectDriver->getRecordUrl();
							$samePikaCache                         = new IslandoraSamePikaCache();
							$samePikaCache->groupedWorkPermanentId = $groupedWorkId;
							if ($samePikaCache->find(true) && $samePikaCache->archiveLink != $archiveLink){
								$samePikaCache->archiveLink = $archiveLink;
								$samePikaCache->pid         = $firstObjectDriver->getUniqueID();
								$numUpdates                 = $samePikaCache->update();
								if ($numUpdates == 0){
									global $pikaLogger;
									$pikaLogger->error('Did not update same pika cache :' . print_r($samePikaCache->_lastError, true));
								}
							}
							GroupedWorkDriver::$archiveLinksForWorkIds[$groupedWorkId] = $archiveLink;
							$timer->logTime("Loaded archive link for work $groupedWorkId");
						}
					}

					$searchObject = null;
					unset($searchObject);
				}
			}
		}
		return $archiveLink;
	}

	/**
	 * Get an array of all ISBNs associated with the record (may be empty).
	 * The primary ISBN is the first entry
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getISBNs(){
		// If ISBN is in the index, it should automatically be an array... but if
		// it's not set at all, we should normalize the value to an empty array.
		$isbns       = [];
		$primaryIsbn = $this->getPrimaryIsbn();
		if ($primaryIsbn != null){
			$isbns[] = $primaryIsbn;
		}
		$additionalIsbns = isset($this->fields['isbn']) ? (is_array($this->fields['isbn']) ? $this->fields['isbn'] : [$this->fields['isbn']]) : [];
		$additionalIsbns = array_remove_by_value($additionalIsbns, $primaryIsbn);
		$isbns           = array_merge($isbns, $additionalIsbns);
		return $isbns;
	}

	public function getPrimaryIsbn(){
		return $this->fields['primary_isbn'] ?? null;
	}

	/**
	 * Return the first valid ISBN found in the record (favoring ISBN-10 over
	 * ISBN-13 when possible).
	 *
	 * @return  mixed
	 */
	public function getCleanISBN(){
		require_once ROOT_DIR . '/sys/ISBN/ISBN.php';

		$novelistISBN = $this->getNovelistPrimaryISBN();
		if (!isset($_REQUEST['reload']) && $novelistISBN){
			return $novelistISBN;
		}else{
			// Get all the ISBNs and initialize the return value:
			$isbns  = $this->getISBNs();
			$isbn10 = false;

			// Loop through the ISBNs:
			foreach ($isbns as $isbn){
				// If we find an ISBN-13, return it immediately; otherwise, if we find
				// an ISBN-10, save it if it is the first one encountered.
				$isbnObj = new ISBN($isbn);
				if ($isbnObj->isValid()){
					if ($isbn13 = $isbnObj->get13()){
						return $isbn13;
					}
					if (!$isbn10){
						$isbn10 = $isbnObj->get10();
					}
				}
			}
			return $isbn10;
		}
	}

	public function getNovelistPrimaryISBN(){
		if (!empty($this->getPermanentId())){
			require_once ROOT_DIR . '/sys/Novelist/NovelistData.php';
			$novelistData                         = new NovelistData();
			$novelistData->groupedWorkPermanentId = $this->getPermanentId();
			if ($novelistData->find(true) && !empty($novelistData->primaryISBN)){
				return $novelistData->primaryISBN;
			}
		}
		return false;
	}
	/**
	 * Get an array of all ISBNs associated with the record (may be empty).
	 *
	 * @access  protected
	 * @return  array
	 */
	public function getISSNs(){
		// If ISBN is in the index, it should automatically be an array... but if
		// it's not set at all, we should normalize the value to an empty array.
		return isset($this->fields['issn']) ? (is_array($this->fields['issn']) ? $this->fields['issn'] : array($this->fields['issn'])) : [];
	}

	/**
	 * Get the UPC associated with the record (may be empty).
	 *
	 * @return  array
	 */
	public function getUPCs(){
		// If UPCs is in the index, it should automatically be an array... but if
		// it's not set at all, we should normalize the value to an empty array.
		return isset($this->fields['upc']) ? (is_array($this->fields['upc']) ? $this->fields['upc'] : array($this->fields['upc'])) : [];
	}

	public function getCleanUPC(){
		$upcs = $this->getUPCs();
		if (empty($upcs)){
			return false;
		}
		$upc = $upcs[0];
		if ($pos = strpos($upc, ' ')){
			$upc = substr($upc, 0, $pos);
		}
		return $upc;
	}

	private $numRelatedRecords = -1;

	private function getNumRelatedRecords(){
		if ($this->numRelatedRecords == -1){
			if ($this->relatedRecords == null){
				$this->loadRelatedRecords(); // This will ensure the related Record count accounts for records excluded by scoping
			}
			$this->numRelatedRecords = count($this->relatedRecords);
		}
		return $this->numRelatedRecords;
	}

	private $relatedRecords = null;
	private $relatedItemsByRecordId = null;

	public function getRelatedRecords($forCovers = false){
		$this->loadRelatedRecords($forCovers);
		return $this->relatedRecords;
	}

	public function getRelatedRecord($recordIdentifier){
		$this->loadRelatedRecords();
		if (isset($this->relatedRecords[$recordIdentifier])){
			return $this->relatedRecords[$recordIdentifier];
		}else{
			return null;
		}
	}

	private function loadRelatedRecords($forCovers = false){
		global $timer;
		global $memoryWatcher;
		if ($this->relatedRecords == null || isset($_REQUEST['reload'])){
			$timer->logTime("Starting to load related records for {$this->getUniqueID()}");

			$this->relatedItemsByRecordId = [];

			global $solrScope;
			global $library;
			$user = UserAccount::getActiveUserObj();

			$searchLocation = Location::getSearchLocation();
			$activePTypes   = [];
			if ($user){
				$activePTypes = array_merge($activePTypes, $user->getRelatedPTypes());
			}
			if ($searchLocation){
				$activePTypes[$searchLocation->defaultPType] = $searchLocation->defaultPType;
			}
			if ($library){
				$activePTypes[$library->defaultPType] = $library->defaultPType;
			}
			[$scopingInfo, $validRecordIds, $validItemIds] = $this->loadScopingDetails($solrScope);
			$timer->logTime('Loaded Scoping Details from the index');
			$memoryWatcher->logMemory('Loaded scoping details from the index');

			$recordsFromIndex = $this->loadRecordDetailsFromIndex($validRecordIds);
			$timer->logTime('Loaded Record Details from the index');
			$memoryWatcher->logMemory('Loaded Record Details from the index');

			//Get a list of related items filtered according to scoping
			$this->loadItemDetailsFromIndex($validItemIds);
			$timer->logTime('Loaded Item Details from the index');
			$memoryWatcher->logMemory('Loaded Item Details from the index');

			//Load the work from the database so we can use it in each record diver
			require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
			$groupedWork               = new GroupedWork();
			$groupedWork->permanent_id = $this->getUniqueID();
			$relatedRecords            = [];
			//This will be false if the record is old
			if ($groupedWork->find(true)){
				//Generate record information based on the information we have in the index
				/** @var \Pika\BibliographicDrivers\GroupedWork\RecordDetails $recordDetails */
				foreach ($recordsFromIndex as $recordDetails){
					$relatedRecord                        = $this->setupRelatedRecordDetails($recordDetails, $groupedWork, $timer, $scopingInfo, $activePTypes, $searchLocation, $library, $forCovers);
					$relatedRecords[$relatedRecord['id']] = $relatedRecord;
					$memoryWatcher->logMemory('Setup related record details for ' . $relatedRecord['id']);
				}
			}

			//Sort the records based on format and then edition
			uasort($relatedRecords, [$this, "compareRelatedRecords"]);

			$this->relatedRecords = $relatedRecords;
			$timer->logTime("Finished loading related records {$this->getUniqueID()}");
		}
	}

	/**
	 * The vast majority of record information is stored within the index.
	 * This routine parses the information from the index and restructures it for use within the user interface.
	 *
	 * @return array|null
	 */
	public function getRelatedManifestations(){
		global $timer;
		global $memoryWatcher;
		$timer->logTime('Starting to load related records in getRelatedManifestations');
		$relatedRecords = $this->getRelatedRecords();
		$timer->logTime('Finished loading related records in getRelatedManifestations');
		$memoryWatcher->logMemory('Finished loading related records');

		// alter the status ranking array to use for comparison here
		$statusRankings = [];
		foreach (self::$statusRankings as $key => $value){
			$key                  = strtolower($key);
			$statusRankings[$key] = $value;
		}

		//Group the records based on format
		$relatedManifestations = [];
		foreach ($relatedRecords as $curRecord){
			$currentManifestation = $curRecord['format'];
			if (!array_key_exists($currentManifestation, $relatedManifestations)){
				// Create array structure for first occurrence of the format
				$relatedManifestations[$currentManifestation] = [
					'format'               => $currentManifestation,
					'formatCategory'       => $curRecord['formatCategory'],
					'copies'               => 0,
					'availableCopies'      => 0,
					'localCopies'          => 0,
					'localAvailableCopies' => 0,
					'onOrderCopies'        => 0,
					'numHolds'             => 0,
					'available'            => false,
					'hasLocalItem'         => false,
					'isEContent'           => false,
					'relatedRecords'       => [],
					'preferredEdition'     => null,
					//'statusMessage'        => '',
					//'itemLocations'        => [],
					'availableLocally'     => false,
					'availableOnline'      => false,
					'availableHere'        => false,
					'inLibraryUseOnly'     => false,
					'allLibraryUseOnly'    => true,
					'hideByDefault'        => false,
					'itemSummary'          => [],
					//'itemSummaryLocal'     => [],
					'groupedStatus'        => ''
				];
			}
			if (isset($curRecord['availableLocally']) && $curRecord['availableLocally'] == true){
				$relatedManifestations[$currentManifestation]['availableLocally'] = true;
			}
			if (isset($curRecord['availableHere']) && $curRecord['availableHere'] == true){
				$relatedManifestations[$currentManifestation]['availableHere'] = true;
			}
			// Location Label field seems to be obsolete. pascal 2/26/2025
//			if ($curRecord['available'] && $curRecord['locationLabel'] === 'Online'){
//				$relatedManifestations[$currentManifestation]['availableOnline'] = true;
//			}
			if (isset($curRecord['availableOnline']) && $curRecord['availableOnline']){
				$relatedManifestations[$currentManifestation]['availableOnline'] = true;
			}
			if (isset($curRecord['isEContent']) && $curRecord['isEContent']){
				$relatedManifestations[$currentManifestation]['isEContent'] = true;

				//Set Manifestation eContent Source
				if (empty($relatedManifestations[$currentManifestation]['eContentSource'])){
					$relatedManifestations[$currentManifestation]['eContentSource'] = $curRecord['eContentSource'];
				}elseif ($curRecord['eContentSource'] != $relatedManifestations[$currentManifestation]['eContentSource']){

					$this->logger->warning("Format Manifestation has multiple eContent sources containing record {$curRecord['id']}");
				}
			}
			if (!$relatedManifestations[$currentManifestation]['available'] && $curRecord['available']){
				$relatedManifestations[$currentManifestation]['available'] = true;
			}
			if ($curRecord['inLibraryUseOnly']){
				$relatedManifestations[$currentManifestation]['inLibraryUseOnly'] = true;
			}else{
				$relatedManifestations[$currentManifestation]['allLibraryUseOnly'] = false;
			}
			if (!$relatedManifestations[$currentManifestation]['hasLocalItem'] && $curRecord['hasLocalItem']){
//				$relatedManifestations[$currentManifestationFormat]['hasLocalItem'] = $curRecord['hasLocalItem'];
				$relatedManifestations[$currentManifestation]['hasLocalItem'] = true;
			}
			if ($curRecord['shelfLocation']){
				$relatedManifestations[$currentManifestation]['shelfLocation'][$curRecord['shelfLocation']] = $curRecord['shelfLocation'];
			}
			if ($curRecord['callNumber']){
				$relatedManifestations[$currentManifestation]['callNumber'][$curRecord['callNumber']] = $curRecord['callNumber'];
			}
			$relatedManifestations[$currentManifestation]['relatedRecords'][] = $curRecord;

			$relatedManifestations[$currentManifestation]['copies']          += $curRecord['copies'];
			$relatedManifestations[$currentManifestation]['availableCopies'] += $curRecord['availableCopies'];



			if ($curRecord['hasLocalItem']){
				$relatedManifestations[$currentManifestation]['localCopies']          += ($curRecord['localCopies'] ?? 0);
				$relatedManifestations[$currentManifestation]['localAvailableCopies'] += ($curRecord['localAvailableCopies'] ?? 0);
			}
			if (isset($curRecord['itemSummary'])){
				$relatedManifestations[$currentManifestation]['itemSummary'] = $this->mergeItemSummary($relatedManifestations[$currentManifestation]['itemSummary'], $curRecord['itemSummary']);
			}
			if (!empty($curRecord['numHolds'])){
				$relatedManifestations[$currentManifestation]['numHolds'] += $curRecord['numHolds'];
			}
			if (!empty($curRecord['onOrderCopies'])){
				$relatedManifestations[$currentManifestation]['onOrderCopies'] += $curRecord['onOrderCopies'];
			}
// For Reference
//			static $statusRankings = [
//				'Currently Unavailable' => 1,
//				//Unknown Grouped Status   2,
//				'Available to Order'    => 3,
//				'On Order'              => 4,
//				'Coming Soon'           => 5,
//				'In Processing'         => 6,
//				'Checked Out'           => 7,
//				'Shelving'              => 8,
//				'Library Use Only'      => 9,
//				'Available Online'      => 10,
//				'In Transit'            => 11,
//				'On Shelf'              => 12
//			];
			if (!empty($curRecord['groupedStatus'])){
				$manifestationCurrentGroupedStatus = $relatedManifestations[$currentManifestation]['groupedStatus'];

				//Check to see if we have a better status here
				if (array_key_exists(strtolower($curRecord['groupedStatus']), $statusRankings)){
					if (empty($manifestationCurrentGroupedStatus)){
						$manifestationCurrentGroupedStatus = $curRecord['groupedStatus']; // Use the first one we find if we haven't set a grouped status yet
					}elseif ($statusRankings[strtolower($curRecord['groupedStatus'])] > $statusRankings[strtolower($manifestationCurrentGroupedStatus)]){
						$manifestationCurrentGroupedStatus = $curRecord['groupedStatus']; // Update to the better ranked status if we find a better ranked one
					}
					//Update the manifestation's grouped status elements
					$relatedManifestations[$currentManifestation]['groupedStatus']      = $manifestationCurrentGroupedStatus;
					$relatedManifestations[$currentManifestation]['isAvailableToOrder'] = $manifestationCurrentGroupedStatus == 'Available to Order';
				}
			}
		}
		$timer->logTime('Finished initial processing of related records');
		$memoryWatcher->logMemory('Finished initial processing of related records');

		//Check to see if we have applied a format or format category facet
		$selectedFormats              = null;
		$selectedFormatCategories     = null;
		$selectedAvailability         = null;
		$selectedDetailedAvailability = null;
		$selectedEcontentSource       = null;
		if (isset($_REQUEST['filter'])){
			foreach ($_REQUEST['filter'] as $filter){
				if (preg_match('/^format_category(?:\w*):"?(.+?)"?$/', $filter, $matches)){
					$selectedFormatCategories[] = urldecode($matches[1]);
				}elseif (preg_match('/^format(?:\w*):"?(.+?)"?$/', $filter, $matches)){
					$selectedFormats[] = urldecode($matches[1]);
				}elseif (preg_match('/^econtent_source(?:\w*):"?(.+?)"?$/', $filter, $matches)){
					$selectedEcontentSource[] = urldecode($matches[1]);
				}elseif (preg_match('/^availability_toggle(?:\w*):"?(.+?)"?$/', $filter, $matches)){
					$selectedAvailability = urldecode($matches[1]);
				}elseif (preg_match('/^availability_by_format(?:[\w_]*):"?(.+?)"?$/', $filter, $matches)){
					$selectedAvailability = urldecode($matches[1]);
				}elseif (preg_match('/^available_at(?:[\w_]*):"?(.+?)"?$/', $filter, $matches)){
					$selectedDetailedAvailability = urldecode($matches[1]);
					$availableAtLocationsToMatch = [];
					if (!empty($selectedDetailedAvailability)){
						// Look up the location codes of the records owned for
						// the location matching the facet we are filtering by
						$availableAtLocationsToMatch = $this->getAvailableAtLocationsToMatch($selectedDetailedAvailability);
					}
				}
			}
		}



		//Check to see what we need to do for actions, and determine if the record should be hidden by default
		$searchLibrary  = Library::getSearchLibrary();
		$searchLocation = Location::getSearchLocation();
		$isSuperScope   = false;
		if ($searchLocation){
			$isSuperScope = !$searchLocation->restrictSearchByLocation;
		}elseif ($searchLibrary){
			$isSuperScope = !$searchLibrary->restrictSearchByLibrary;
		}
		foreach ($relatedManifestations as $key => $manifestation){
			$manifestation['numRelatedRecords'] = count($manifestation['relatedRecords']);
			if (count($manifestation['relatedRecords']) == 1){
				$firstRecord              = reset($manifestation['relatedRecords']);
				$manifestation['url']     = $firstRecord['url'];
				$manifestation['actions'] = $firstRecord['actions'];
			}else{
				//Figure out what the preferred record is to place a hold on.  Since sorting has been done properly, this should always be the first
				$bestRecord = reset($manifestation['relatedRecords']);

				if ($manifestation['numRelatedRecords'] > 1 && array_key_exists($bestRecord['groupedStatus'], self::$statusRankings) && self::$statusRankings[$bestRecord['groupedStatus']] <= self::$statusRankings['Library Use Only']){
					// Check to set prompt for Alternate Edition for any grouped status equal to or less than that of 'Library Use Only'
					$promptForAlternateEdition = false;
					foreach ($manifestation['relatedRecords'] as $relatedRecord){
						if ($relatedRecord['available'] == true && $relatedRecord['holdable'] == true){
							$promptForAlternateEdition = true;
							unset($relatedRecord);
							break;
						}
					}
					if ($promptForAlternateEdition){
						$alteredActions = [];
						foreach ($bestRecord['actions'] as $action){
							$action['onclick'] = str_replace('Record.showPlaceHold', 'Record.showPlaceHoldEditions', $action['onclick']);
							$alteredActions[]  = $action;
						}
						$manifestation['actions'] = $alteredActions;
						unset($action, $alteredActions);
					}else{
						$manifestation['actions'] = $bestRecord['actions'];
					}
				}else{
					$manifestation['actions'] = $bestRecord['actions'];
				}
			}

			// Set Up Manifestation Display when a format facet is set
			$hasSelectedFormatMatches = false;
			if (!empty($selectedFormats)){
				foreach ($selectedFormats as $selectedFormat){
					if (strcasecmp($selectedFormat, $manifestation['format']) == 0){
						$hasSelectedFormatMatches = true;
						if (!$manifestation['isEContent']){
							$manifestation['isSelectedNonEcontentFormat'] = true;
						}
						break;
					} elseif ($selectedFormat != 'Book' && $manifestation['format'] != 'Book') {
						// Only do this logic when not working with books; to avoid false match on book with ebook
						if (stripos($manifestation['format'], $selectedFormat) !== false){
							// For example: when selectedFormat = ebook, will match against "overdrive ebook"

							//TODO: this will cause the following additional matching
							// when selectedFormat = cd, will match against "audio cd" and "music cd" also
							// when selectedFormat = audio, will match against "audio cd" and "eaudiobook" also
							// when selectedFormat = kit, will match against "book club kit" also
							// when selectedFormat = video, will match against "evideo" also
							// when selectedFormat = playaway, will match against "playaway view" also

							$hasSelectedFormatMatches = true;
							if (!$manifestation['isEContent']){
								//(Should always be econtent, so this is probably unneeded; but just in case
								$manifestation['isSelectedNonEcontentFormat'] = true;
							}
							break;
						} else{
							if ($selectedFormat == 'PlayStation 5' && $manifestation['format'] == 'PlayStation 4'){
								// Since PS4 games work on PS5, show both manifestions when PS5 is selected
								$hasSelectedFormatMatches = true;
								break;
							} elseif ($selectedFormat == 'Xbox Series X' && $manifestation['format'] == 'Xbox One'){
								// Since Xbox One games work on Xbox Series X consoles, show both manifestations when Xbox series
								$hasSelectedFormatMatches = true;
								break;
							}

							//TODO: I strongly suspect this check is no longer needed for econtent;
							//It was used at one point to match Audio CD with CD, see PK-1729

							$currentManifestationFormatDetailedFormat = strtolower(mapValue('format_by_detailed_format', $manifestation['format']));
							if (strcasecmp($selectedFormat, $currentManifestationFormatDetailedFormat) == 0){
								//TODO: Log matches
								$hasSelectedFormatMatches = true;
								if (!$manifestation['isEContent']){
									$manifestation['isSelectedNonEcontentFormat'] = true;
								}
								break;
							}
						}
					}
				}
				if (!$hasSelectedFormatMatches){
					$manifestation['hideByDefault'] = true;
				}
			}

			// Previous logic just in case I am missing something essential in the above. pascal 10/28/21
//			if ($selectedFormat && stripos($manifestation['format'], $selectedFormat) === false){
//				//Do a secondary check to see if we have a more detailed format in the facet
//				// Map is at sites/default/translation_maps/format_by_detailed_format_map.properties
//				// This is for situations where the reported format is specific like EPUB_eBook when we care that it is an ebook instead
//				$selectedFormatDetailedFormat             = mapValue('format_by_detailed_format', $selectedFormat);
//				$currentManifestationFormatDetailedFormat = mapValue('format_by_detailed_format', $manifestation['format']);
//				if ($manifestation['format'] != $selectedFormatDetailedFormat && $currentManifestationFormatDetailedFormat != $selectedFormat){
//					$manifestation['hideByDefault'] = true;
//				}
//			}
//			if ($selectedFormat && $selectedFormat == "Book" && $manifestation['format'] != "Book"){
//				//Do a secondary check to see if we have a more detailed format in the facet
//				$selectedFormatDetailedFormat             = mapValue('format_by_detailed_format', $selectedFormat);
//				$currentManifestationFormatDetailedFormat = mapValue('format_by_detailed_format', $manifestation['format']);
//				if ($manifestation['format'] != $selectedFormatDetailedFormat && $currentManifestationFormatDetailedFormat != $selectedFormat){
//					$manifestation['hideByDefault'] = true;
//				}
//			}

			// Set Up Manifestation Display when an eContent source facet is set
			if ($selectedEcontentSource && (
				(!$manifestation['isEContent'] && empty($manifestation['isSelectedNonEcontentFormat']))
				// Hide non-eContent unless it is also a chosen format facet
					|| (!empty($manifestation['eContentSource']) && !in_array($manifestation['eContentSource'], $selectedEcontentSource))
				)
			){
				$manifestation['hideByDefault'] = true;
			}


			// Set Up Manifestation Display when a format category facet is set
			if ($selectedFormatCategories && !in_array($manifestation['formatCategory'], $selectedFormatCategories)){
				if (($manifestation['format'] == 'eAudiobook') && (in_array('eBook', $selectedFormatCategories) || in_array('Audio Books', $selectedFormatCategories))){
					//This is a special case where the format is in 2 categories
				}elseif (in_array($manifestation['format'], ['VOX Books', "WonderBook"]) && (in_array('Books', $selectedFormatCategories) || in_array('Audio Books', $selectedFormatCategories))){
					//This is another special case where the format is in 2 categories
				}else{
					$manifestation['hideByDefault'] = true;
				}
			}

			// Set Up Manifestation Display when an availability facet is set
			if ($selectedAvailability == 'Available Online' && !($manifestation['availableOnline'])){
				$manifestation['hideByDefault'] = true;
			}elseif ($selectedAvailability == 'Available Now'){
				if ($manifestation['availableOnline']){
					$addOnline = true;
					if ($searchLocation != null){
						$addOnline = $searchLocation->includeOnlineMaterialsInAvailableToggle;
					}elseif ($searchLibrary != null){
						$addOnline = $searchLibrary->includeOnlineMaterialsInAvailableToggle;
					}
					if (!$addOnline){
						$manifestation['hideByDefault'] = true;
					}
				}elseif (!$manifestation['availableLocally'] && !$isSuperScope){
					$manifestation['hideByDefault'] = true;
				}
			}elseif ($selectedAvailability == 'Entire Collection' && !$isSuperScope && (!$manifestation['hasLocalItem'] && !$manifestation['isEContent'])){
				$manifestation['hideByDefault'] = true;
			}
			if ($selectedDetailedAvailability){
				$manifestationIsAvailable = false;
				if ($manifestation['availableOnline']){
					$manifestationIsAvailable = true;
				}elseif ($manifestation['available']){
					foreach ($manifestation['itemSummary'] as $itemSummary){
						if ($itemSummary['available']){
							if (!empty($itemSummary['locationCode'])){
								foreach ($availableAtLocationsToMatch as $locationToMatch){
									if (stripos($itemSummary['locationCode'], $locationToMatch) === 0){
										// Checking that the shelf location code starts with the owning location codes associated with the
										// facet value we are filtering by for the available at facet
										$manifestationIsAvailable = true;
										break;
									}
								}
							}
						}
					}
				}
				if (!$manifestationIsAvailable){
					$manifestation['hideByDefault'] = true;
				}
			}
			global $searchSource;
			if ($searchSource == 'econtent'){
				if (!$manifestation['isEContent']){
					$manifestation['hideByDefault'] = true;
				}
			}

			$relatedManifestations[$key] = $manifestation;
		}
		$timer->logTime('Finished loading related manifestations');
		$memoryWatcher->logMemory('Finished loading related manifestations');

		return $relatedManifestations;
	}

	private function getAvailableAtLocationsToMatch(string $selectedDetailedAvailability) : array{
		// Look up the location codes of the records owned for the location matching the facet we are filtering by
		global $serverName;
		global $memCache;
		/** @var Memcache $memCache */
		$memCacheKey = "availableAtLocationsToMatch_{$selectedDetailedAvailability}_{$serverName}";
		$result = $memCache->get($memCacheKey);
		if (is_array($result)){
			$availableAtLocationsToMatch = $result;
		}else{
			$availableAtLocationsToMatch = [];
			$recordsOwned = new LocationRecordOwned();
			$recordsOwned->query('SELECT `location` FROM `location_records_owned` LEFT JOIN `location` USING (locationId) WHERE `facetLabel` = "' . $selectedDetailedAvailability . '"');
			if ($recordsOwned->N){
				while ($recordsOwned->fetch()){
					// strip out simple regex found in some of the codes
					$loc = str_replace('.*', '', $recordsOwned->location);
					if (strlen($loc)){
						if (strpos($loc, '|') !== false){
							$availableAtLocationsToMatch = array_merge($availableAtLocationsToMatch, explode('|', $loc));
						} else{
							$availableAtLocationsToMatch[] = $loc;
						}
					}
				}
			}

			// Look up the location codes of the records owned for the libary matching the facet we are filtering by
			$recordsOwned->query('SELECT `location` FROM `library_records_owned` LEFT JOIN `library` USING (libraryId) WHERE `facetLabel` = "' . $selectedDetailedAvailability . '"');
			if ($recordsOwned->N){
				while ($recordsOwned->fetch()){
					// strip out simple regex found in some of the codes
					$loc = str_replace('.*', '', $recordsOwned->location);
					if (strlen($loc)){
						if (strpos($loc, '|') !== false){
							$availableAtLocationsToMatch = array_merge($availableAtLocationsToMatch, explode('|', $loc));
						} else{
							$availableAtLocationsToMatch[] = $loc;
						}
					}
				}
			}
			global $configArray;
			$memCache->set($memCacheKey, $availableAtLocationsToMatch, 0, $configArray['Caching']['solr_record']);
		}
		return $availableAtLocationsToMatch;
	}
	/**
	 * Main sort function for ordering all the related records/editions for display in the related manifestations table
	 * of a Grouped Work
	 *
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	function compareRelatedRecords($a, $b){
		//0) First sort by format
		$formatComparison = GroupedWorkDriver::compareFormat($a, $b);
		if ($formatComparison == 0){
			//1) Put anything that is abridged *last*
			$abridgedComparison = GroupedWorkDriver::compareAbridged($a, $b);
			if ($abridgedComparison == 0){
				//2) Put anything that is holdable first
				$holdabilityComparison = GroupedWorkDriver::compareHoldability($a, $b);
				if ($holdabilityComparison == 0){
					// Sort Overdrive Magazines by publication date
					if ($a['source'] == 'overdrive' && $b['source'] == 'overdrive' && strcasecmp($a['format'], 'eMagazine') == 0 && strcasecmp($b['format'],  'eMagazine') == 0){
						return strtotime($b['publicationDate']) <=> strtotime($a['publicationDate']);
					}
					//3) Compare editions for non-fiction if available
					//Get literary form to determine if we should compare editions
					$literaryForm = '';
					if (isset($this->fields['literary_form'])){
						$literaryForm = is_array($this->fields['literary_form']) ? reset($this->fields['literary_form']) : $this->fields['literary_form'];
					}
					$editionComparisonResult = GroupedWorkDriver::compareEditionsForRecords($literaryForm, $a, $b);
					if ($editionComparisonResult == 0){
						//4) Put anything with locally available items first
						$localAvailableItemComparisonResult = GroupedWorkDriver::compareLocalAvailableItemsForRecords($a, $b);
						if ($localAvailableItemComparisonResult == 0){
							//5) Anything that is available elsewhere goes higher
							$availabilityComparisonResults = GroupedWorkDriver::compareAvailabilityForRecords($a, $b);
							if ($availabilityComparisonResults == 0){
								//6) Put anything with a local copy higher
								$localItemComparisonResult = GroupedWorkDriver::compareLocalItemsForRecords($a, $b);
								if ($localItemComparisonResult == 0){
									//7) All else being equal, sort by hold ratio
									$holdRatioComparison = self::compareHoldRatioForRecords($a, $b);
									if ($holdRatioComparison == 0){
										return $b['copies'] <=> $a['copies'];
									}else{
										return $holdRatioComparison;
									}
								}else{
									return $localItemComparisonResult;
								}
							}else{
								return $availabilityComparisonResults;
							}
						}else{
							return $localAvailableItemComparisonResult;
						}
					}else{
						return $editionComparisonResult;
					}
				}else{
					return $holdabilityComparison;
				}
			}else{
				return $abridgedComparison;
			}
		}else{
			return $formatComparison;
		}
	}

	/**
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function eContentComparison($a, $b){
		//TODO: build a static ranking of the sideload sources
		global $library;
		require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
		$indexingProfiles = IndexingProfile::getAllIndexingProfileNames();
		/** @var LibraryRecordToInclude[] $recordsToInclude */
		$recordsToInclude = $library->recordsToInclude;
		$sourceA          = $a['source'];
		$sourceB          = $b['source'];
		$rankSourceA      = 1;
		$rankSourceB      = 1;
		foreach ($recordsToInclude as $key => $includedSideLoad){
			$sideLoadName = $indexingProfiles[$includedSideLoad->indexingProfileId];
			if ($sourceA == $sideLoadName){
				$rankSourceA = -$key;
			}
			if ($sourceB == $sideLoadName){
				$rankSourceB = -$key;
			}
			if ($rankSourceA < 1 && $rankSourceB < 1){
				break;
			}
		}
	}

	/**
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function compareFormat($a, $b){
		$format1          = $a['format'];
		$format2          = $b['format'];
		$formatComparison = strcasecmp($format1, $format2);
		//Make sure that book is the very first format always
		if ($formatComparison == 0){
			return 0;
		}elseif ($format1 == 'Book'){
			return -1;
		}elseif ($format2 == 'Book'){
			return 1;
		} else {
			return $formatComparison;
		}
	}

	/**
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function compareHoldability($a, $b){
		return $b['holdable'] <=> $a['holdable'];
	}

	/**
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function compareAbridged($a, $b){
		return $a['abridged'] <=> $b['abridged'];
	}

//	/**
//	 * @param $a
//	 * @param $b
//	 * @return int
//	 */
//	static function compareLanguagesForRecords($a, $b){
//		$aHasEnglish = false;
//		if (is_array($a['language'])){
//			$languageA = strtolower(reset($a['language']));
//			foreach ($a['language'] as $language){
//				if (strcasecmp('english', $language) == 0){
//					$aHasEnglish = true;
//					break;
//				}
//			}
//		}else{
//			$languageA = strtolower($a['language']);
//			if (strcasecmp('english', $languageA) == 0){
//				$aHasEnglish = true;
//			}
//		}
//		$bHasEnglish = false;
//		if (is_array($b['language'])){
//			$languageB = strtolower(reset($b['language']));
//			foreach ($b['language'] as $language){
//				if (strcasecmp('english', $language) == 0){
//					$bHasEnglish = true;
//					break;
//				}
//			}
//		}else{
//			$languageB = strtolower($b['language']);
//			if (strcasecmp('english', $languageB) == 0){
//				$bHasEnglish = true;
//			}
//		}
//		if ($aHasEnglish && $bHasEnglish){
//			return 0;
//		}else{
//			if ($aHasEnglish){
//				return -1;
//			}elseif ($bHasEnglish){
//				return 1;
//			}else{
//				return -strcmp($languageA, $languageB);
//			}
//		}
//	}

	/**
	 * @param string $literaryForm
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function compareEditionsForRecords($literaryForm, $a, $b){
		//We only want to compare editions if the work is non-fiction
		if ($literaryForm == 'Non Fiction'){
			$editionA = GroupedWorkDriver::normalizeEdition($a['edition']);
			$editionB = GroupedWorkDriver::normalizeEdition($b['edition']);
			return $editionB <=> $editionA;
		}
		return 0;
	}

	/**
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function compareAvailabilityForRecords($a, $b){
		$availableLocallyA = isset($a['availableLocally']) && $a['availableLocally'];
		$availableLocallyB = isset($b['availableLocally']) && $b['availableLocally'];
		$compareAvailableLocally = $availableLocallyB <=> $availableLocallyA;
		if ($compareAvailableLocally == 0){
			$availableA = isset($a['available']) && $a['available'] && $a['holdable'];
			$availableB = isset($b['available']) && $b['available'] && $b['holdable'];
			return $availableB <=> $availableA;
		}else{
			return $compareAvailableLocally;
		}
	}

	/**
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function compareLocalAvailableItemsForRecords($a, $b){
		if (($a['availableHere'] || $a['availableOnline']) && ($b['availableHere'] || $b['availableOnline'])){
			if (($a['availableLocally'] || $a['availableOnline']) && ($b['availableLocally'] || $b['availableOnline'])){
				return 0;
			}elseif ($a['availableLocally'] || $a['availableOnline']){
				return -1;
			}elseif ($b['availableLocally'] || $b['availableOnline']){
				return 1;
			}else{
				return 0;
			}
		}elseif ($a['availableHere'] || $a['availableOnline']){
			return -1;
		}elseif ($b['availableHere'] || $b['availableOnline']){
			return 1;
		}else{
			return 0;
		}
	}

	/**
	 * @param array $a Record Details array of a related record to sort
	 * @param array $b Record Details array of a related record to sort against
	 * @return int sort value
	 */
	static function compareLocalItemsForRecords($a, $b){
		return $b['hasLocalItem'] <=> $a['hasLocalItem'];
	}

	static function compareHoldRatioForRecords($a, $b){
		// First calculate hold ratio as needed
		if (!isset($a['holdRatio'])){
			$a['holdRatio'] = self::calculateHoldRatioForRecord($a);
		}
		if (!isset($b['holdRatio'])){
			$b['holdRatio'] = self::calculateHoldRatioForRecord($b);
		}
		return $b['holdRatio'] <=> $a['holdRatio'];
	}

	static function calculateHoldRatioForRecord($relatedRecord){
		// Calculate Hold Ratio
		$totalCopies     = $relatedRecord['copies'];
		$availableCopies = $relatedRecord['availableCopies'];
		$numHolds        = $relatedRecord['numHolds'];
		$holdRatio       = $totalCopies > 0 ? ($availableCopies + ($totalCopies - $numHolds) / $totalCopies) : 0;
		// Hold Ratio formula found in previous commit (1-28-2014)  c8ae17fd66c41d3f6ada747ceb65b05685e1b614
		//TODO: revisit this formula. basic concept should be holds per copies, but lots of nuances to consider:
		// * need to define a concept of holdable copies. eg checked out items count, but lost shouldn't  (this isn't the same as available copies)
		// * holdable copies within the scope?
		// * on order items included?
		// Note: hold-ratio can be displayed in relatedRecords template for debugging
		return $holdRatio;
	}

	public function getIndexedSeries(){
		$seriesWithVolume = null;
		if (isset($this->fields['series_with_volume'])){
			$rawSeries = $this->fields['series_with_volume'];
			if (is_string($rawSeries)){
				$rawSeries = [$rawSeries];
			}
			foreach ($rawSeries as $seriesInfo){
				if (strpos($seriesInfo, '|') > 0){
					$seriesInfoSplit    = explode('|', $seriesInfo);
					$seriesWithVolume[] = [
						'seriesTitle' => $seriesInfoSplit[0],
						'volume'      => $seriesInfoSplit[1]
					];
				}else{
					$seriesWithVolume[] = [
						'seriesTitle' => $seriesInfo
					];
				}
			}
		}
		return $seriesWithVolume;
	}

	public function hasCachedSeries(){
		require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
		return NovelistData::doesGroupedWorkHaveCachedSeries($this->getPermanentId());
	}

	public function getSeries($allowReload = true){
		//Get a list of isbns from the record
		$relatedIsbns = $this->getISBNs();
		$novelist     = NovelistFactory::getNovelist();
		$novelistData = $novelist->loadBasicEnrichment($this->getPermanentId(), $relatedIsbns, $allowReload);
		if ($novelistData != null && isset($novelistData->seriesTitle)){
			return [
				'seriesTitle'  => $novelistData->seriesTitle,
				'volume'       => $novelistData->volume,
				'fromNovelist' => true,
			];
		}
		return null;
	}

	public function getFormats(){
		global $solrScope;
		if (isset($this->fields['format_' . $solrScope])){
			$formats = $this->fields['format_' . $solrScope];
			if (is_array($formats)){
				natcasesort($formats);
				return implode(', ', $formats);
			}else{
				return $formats;
			}
		}else{
			return 'Unknown';
		}
	}

	public function getFormatsArray(){
		global $solrScope;
		if (isset($this->fields['format_' . $solrScope])){
			$formats = $this->fields['format_' . $solrScope];
			if (is_array($formats)){
				return $formats;
			}else{
				return [$formats];
			}
		}else{
			return [];
		}
	}

	/**
	 * @return string
	 */
	public function getFormatCategory(){
		global $solrScope;
		$scopedFieldName = 'format_category_' . $solrScope;
		return isset($this->fields[$scopedFieldName]) ? (is_array($this->fields[$scopedFieldName]) ? reset($this->fields[$scopedFieldName]) : $this->fields[$scopedFieldName]) : '';
	}

	public function loadEnrichment(){
		$isbn       = $this->getCleanISBN(); // This prefers the Novelist primary ISBN
		$enrichment = [];
		if (!empty($isbn)){
			$novelist = NovelistFactory::getNovelist();
			global $memoryWatcher;
			$memoryWatcher->logMemory('Setup Novelist Connection');
			$enrichment['novelist'] = $novelist->loadEnrichment($this->getPermanentId(), $this->getISBNs());
		}
		return $enrichment;
	}

	public function getUserReviews(){
		$reviews = [];

		// Determine if we should censor bad words or hide the comment completely.
		$censorWords = true;
		global $library;
		if (isset($library)){
			$censorWords = !$library->hideCommentsWithBadWords;
		} // censor if not hiding
		require_once ROOT_DIR . '/sys/Language/BadWord.php';
		$badWords = new BadWord();

		// Get the Reviews
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
		$userReview                         = new UserWorkReview();
		$userReview->groupedWorkPermanentId = $this->getUniqueID();
		$joinUser                           = new User();
		$userReview->joinAdd($joinUser);
		$userReview->find();
		while ($userReview->fetch()){
			// Set the display Name for the review
			if (!$userReview->displayName){
				if (strlen(trim($userReview->firstname)) >= 1){
					$userReview->displayName = mb_substr($userReview->firstname, 0, 1) . '. ' . $userReview->lastname;
				}else{
					$userReview->displayName = $userReview->lastname;
				}
			}

			// Clean-up User Review Text
			if ($userReview->review){ // if the review has content to check
				if ($censorWords){ // replace bad words
					$userReview->review = $badWords->censorBadWords($userReview->review);
				}else{ // skip reviews with bad words
					if ($badWords->hasBadWords($userReview->review)){
						continue;
					}
				}
			}

			$reviews[] = clone $userReview;
		}
		return $reviews;
	}

	public function getRatingData(){
		require_once ROOT_DIR . '/services/API/WorkAPI.php';
		$workAPI = new WorkAPI();
		return $workAPI->getRatingData($this->getPermanentId());
	}

	public function getExploreMoreInfo(){
		global $interface;
		global $configArray;
		$exploreMoreOptions = [];
		if ($configArray['Catalog']['showExploreMoreForFullRecords']){
			$interface->assign('showMoreLikeThisInExplore', true);
			$interface->assign('showExploreMore', true);
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
		}
		return $exploreMoreOptions;
	}


	public function getMoreDetailsOptions(){
		global $interface;

		$isbn = $this->getCleanISBN();

		$tableOfContents = $this->getTOC();
		$interface->assign('tableOfContents', $tableOfContents);

		//Load more details options
		$moreDetailsOptions                = $this->getBaseMoreDetailsOptions($isbn);
		$moreDetailsOptions['moreDetails'] = [
			'label' => 'More Details',
			'body'  => $interface->fetch('GroupedWork/view-title-details.tpl'),
		];
		$moreDetailsOptions['subjects']    = [
			'label' => 'Subjects',
			'body'  => $interface->fetch('GroupedWork/view-subjects.tpl'),
		];
		if ($interface->getVariable('showStaffView')){
			$moreDetailsOptions['staff'] = [
				'label' => 'Staff View',
				'body'  => $interface->fetch($this->getStaffView()),
			];
		}

		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	private $userTagsForThisWork;

	public function getTags(){
		if (!isset($this->userTagsForThisWork)){
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';
			/** @var UserTag[] $tags */
			$tags                             = array();
			$userTags                         = new UserTag();
			$userTags->groupedWorkPermanentId = $this->getPermanentId();
			$userTags->find();
			while ($userTags->fetch()){
				if (!isset($tags[$userTags->tag])){
					$tags[$userTags->tag]                = clone $userTags;
					$tags[$userTags->tag]->userAddedThis = UserAccount::getActiveUserId() == $tags[$userTags->tag]->userId;
				}
				$tags[$userTags->tag]->cnt++;
			}
			$this->userTagsForThisWork = $tags;
		}
		return $this->userTagsForThisWork;
	}

	public function getAcceleratedReaderData(){
		$hasArData = false;
		$arData    = [];
		if (isset($this->fields['accelerated_reader_point_value'])){
			$arData['pointValue'] = $this->fields['accelerated_reader_point_value'];
			$hasArData            = true;
		}
		if (isset($this->fields['accelerated_reader_reading_level'])){
			$arData['readingLevel'] = $this->fields['accelerated_reader_reading_level'];
			$hasArData              = true;
		}
		if (isset($this->fields['accelerated_reader_interest_level'])){
			$arData['interestLevel'] = $this->fields['accelerated_reader_interest_level'];
			$hasArData               = true;
		}

		if ($hasArData){
			if ($arData['pointValue'] == 0 && $arData['readingLevel'] == 0){
				return null;
			}
			return $arData;
		}else{
			return null;
		}
	}

	public function getFountasPinnellLevel(){
		return $this->fields['fountas_pinnell'] ?? null;
	}

	public function getLexileCode(){
		return $this->fields['lexile_code'] ?? null;
	}

	public function getLexileScore(){
		if (isset($this->fields['lexile_score']) && $this->fields['lexile_score'] > 0){
			return $this->fields['lexile_score'];
		}
		return null;
	}

	public function getSubjects(){
		global $library,
		       $interface;

		$subjects         = [];
		$otherSubjects    = [];
		$lcSubjects       = [];
		$bisacSubjects    = [];
		$oclcFastSubjects = [];
		$localSubjects    = [];

		if (!empty($this->fields['lc_subject'])){
			$lcSubjects = $this->fields['lc_subject'];
			$subjects   = array_merge($subjects, $this->fields['lc_subject']);
		}

		if (!empty($this->fields['bisac_subject'])){
			$bisacSubjects = $this->fields['bisac_subject'];
			$subjects      = array_merge($subjects, $this->fields['bisac_subject']);
		}

		if (!empty($this->fields['topic_facet'])){
			$subjects = array_merge($subjects, $this->fields['topic_facet']);
		}

		if (!empty($this->fields['subject_facet'])){
			$subjects = array_merge($subjects, $this->fields['subject_facet']);
		}

		// TODO: get local Subjects
		// TODO: get oclc Fast Subjects
		// TODO: get other subjects

		$normalizedSubjects = array();
		foreach ($subjects as $subject){
			$subjectLower = strtolower($subject);
			if (!array_key_exists($subjectLower, $subjects)){
				$normalizedSubjects[$subjectLower] = $subject;
			}
		}
		$subjects = $normalizedSubjects;

		natcasesort($subjects);
		$interface->assign('subjects', $subjects);
		$interface->assign('showLCSubjects', $library->showLCSubjects);
		$interface->assign('showBisacSubjects', $library->showBisacSubjects);
		$interface->assign('showFastAddSubjects', $library->showFastAddSubjects);
		$interface->assign('showOtherSubjects', $library->showOtherSubjects);

		if ($library->showLCSubjects){
			natcasesort($lcSubjects);
			$interface->assign('lcSubjects', $lcSubjects);
		}
		if ($library->showBisacSubjects){
			natcasesort($bisacSubjects);
			$interface->assign('bisacSubjects', $bisacSubjects);
		}
		if ($library->showFastAddSubjects){
			natcasesort($oclcFastSubjects);
			$interface->assign('oclcFastSubjects', $oclcFastSubjects);
		}
		if ($library->showOtherSubjects){
			natcasesort($otherSubjects);
			$interface->assign('otherSubjects', $otherSubjects);
		}
		natcasesort($localSubjects);
		$interface->assign('localSubjects', $localSubjects);

	}

	private function mergeItemSummary($localCopies, $itemSummary){
		foreach ($itemSummary as $key => $item){
			if (isset($localCopies[$key])){
				$localCopies[$key]['totalCopies']     += $item['totalCopies'];
				$localCopies[$key]['availableCopies'] += $item['availableCopies'];
				if ($item['displayByDefault']){
					$localCopies[$key]['displayByDefault'] = true;
				}
				if ($item['available']){
					$localCopies[$key]['available'] = true;
				}
			}else{
				$localCopies[$key] = $item;
			}
		}
		ksort($localCopies);
		return $localCopies;
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
		$formats = $this->getFormatsArray();

		// If we have multiple formats, Book and Journal are most important...
		if (in_array('Book', $formats)){
			$format = 'Book';
		}elseif (in_array('Journal', $formats)){
			$format = 'Journal';
		}else{
			if (count($formats) > 0){
				$format = $formats[0];
			}else{
				$format = '';
			}
		}
		switch ($format){
			case 'Book':
				$params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:book';
				$params['rft.genre']   = 'book';
				$params['rft.btitle']  = $params['rft.title'];
				if ($this->hasCachedSeries()){
					$series = $this->getSeries(false);
					if ($series != null){
						// Handle both possible return formats of getSeries:
						$params['rft.series'] = $series['seriesTitle'];
					}
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
		$parts = array();
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

		//TODO: replace with simplified use of http_build_query()
		//return http_build_query($params);
	}

	private function getPublicationDates(){
		return $this->fields['publishDate'] ?? [];
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
	 * Get the edition of the current record.
	 *
	 * @access  protected
	 * @return  string
	 */
	protected function getEdition(){
		return $this->fields['edition'] ?? '';
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
	 * Ranking of the item grouped statuses based on relative available-ness to
	 * patrons.
	 *
	 * The highest ranked status of a format manifestation will be displayed in
	 * the header of each format manifestation on the grouped work display.
	 * @var int[]
	 */
	private static $statusRankings = [
		// Any change here also needs made in TimeToReshelve->getObjectStructure() groupedStatus values
		'Currently Unavailable' => 1,
		//Unknown Grouped Status   2,
		'Available to Order'    => 3,
		'On Order'              => 4,
		'Coming Soon'           => 5,
		'In Processing'         => 6,
		'Checked Out'           => 7,
		'Shelving'              => 8, // Shelving & Recently Returned are equivalent
		'Recently Returned'     => 8,
		'Library Use Only'      => 9,
		'Available Online'      => 10,
		'In Transit'            => 11,
		'On Shelf'              => 12,
	];

	/**
	 * Rank Grouped status according to their relative available-ness (the array above) to the patron.
	 * Unknown grouped statuses get a value of 2, higher than unavailable but lower than all other grouped statuses
	 *
	 * @param $groupedStatus1
	 * @param $groupedStatus2
	 * @return int
	 */
	public static function keepBestGroupedStatus($groupedStatus1, $groupedStatus2){
		$ranking1 = GroupedWorkDriver::$statusRankings[$groupedStatus1] ?? 2;
		$ranking2 = GroupedWorkDriver::$statusRankings[$groupedStatus2] ?? 2;
		return $ranking1 > $ranking2 ? $groupedStatus1 : $groupedStatus2;
	}

	public function getItemActions($itemInfo){
		return [];
	}

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $isHomePickupRecord, $relatedUrls = null){
		return [];
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
					'@type'      => 'bf:Work', /* TODO: This should change to a more specific type Book/Movie as applicable */
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
	 * @param $solrScope
	 * @return array
	 */
	protected function loadScopingDetails($solrScope){
//First load scoping information from the index.  This is stored as multiple values
		//within the scoping details field for the scope.
		//Each field is
		$scopingInfoFieldName = 'scoping_details_' . $solrScope;
		$scopingInfo          = [];
		$validRecordIds       = [];
		$validItemIds         = [];
		if (isset($this->fields[$scopingInfoFieldName])){
			$scopingInfoRaw = $this->fields[$scopingInfoFieldName];
			if (!is_array($scopingInfoRaw)){
				$scopingInfoRaw = [$scopingInfoRaw];
			}
			foreach ($scopingInfoRaw as $tmpItem){
				$scopingDetailsArray    = explode('|', $tmpItem);
				$scopeDetails           = new \Pika\BibliographicDrivers\GroupedWork\ScopeDetails($scopingDetailsArray);
				$scopeKey               = $scopeDetails->recordFullIdentifier . ':' . $scopeDetails->itemIdentifier;
				$scopingInfo[$scopeKey] = $scopeDetails;
				$validRecordIds[]       = $scopeDetails->recordFullIdentifier;
				$validItemIds[]         = $scopeKey;
			}
		}
		return [$scopingInfo, $validRecordIds, $validItemIds];
	}

	/**
	 * Get related records from the index filtered according to the current scope
	 *
	 * @param $validRecordIds
	 * @return array
	 */
	protected function loadRecordDetailsFromIndex($validRecordIds){
		$relatedRecordFieldName = 'record_details';
		$recordsFromIndex       = [];
		if (isset($this->fields[$relatedRecordFieldName])){
			$relatedRecordIdsRaw = $this->fields[$relatedRecordFieldName];
			if (!is_array($relatedRecordIdsRaw)){
				$relatedRecordIdsRaw = [$relatedRecordIdsRaw];
			}
			foreach ($relatedRecordIdsRaw as $tmpItem){
				$recordDetailsArray = explode('|', $tmpItem);
				$recordDetails      = new \Pika\BibliographicDrivers\GroupedWork\RecordDetails($recordDetailsArray);
				//Check to see if the record is valid
				if (in_array($recordDetails->recordFullIdentifier, $validRecordIds)){
					$recordsFromIndex[$recordDetails->recordFullIdentifier] = $recordDetails;
				}
			}
		}
		return $recordsFromIndex;
	}

	/**
	 * @param array $validItemIdsForScope Array of items filtered by scope
	 * @return void
	 */
	protected function loadItemDetailsFromIndex($validItemIdsForScope){
		$relatedItemsFieldName = 'item_details';
		if (isset($this->fields[$relatedItemsFieldName])){
			$itemsFromIndexRaw = $this->fields[$relatedItemsFieldName];
			if (!is_array($itemsFromIndexRaw)){
				$itemsFromIndexRaw = [$itemsFromIndexRaw];
			}

			foreach ($itemsFromIndexRaw as $tmpItem){
				$itemDetailsArray = explode('|', $tmpItem);
				$recordIdForItem  = $itemDetailsArray[0];
				$itemIdentifier   = $recordIdForItem . ':' . $itemDetailsArray[1];
				if (in_array($itemIdentifier, $validItemIdsForScope)){
					$itemDetails = new \Pika\BibliographicDrivers\GroupedWork\ItemDetails($itemDetailsArray);
					if (!array_key_exists($recordIdForItem, $this->relatedItemsByRecordId)){
						$this->relatedItemsByRecordId[$recordIdForItem] = [];
					}
					$this->relatedItemsByRecordId[$recordIdForItem][] = $itemDetails;
				}
			}
		}
	}

	const SIERRA_PTYPE_WILDCARDS = ['9999'];
	/**
	 * Determine holdable, bookable, or "is home pickup" by checking the Patron Type values
	 * @param array $activePTypes  Patron Types of the user, library and location interface
	 * @param string $actionPTypes  The Applicable Ptypes for the action (hold, book, home pick up)
	 * @param bool $isActionable  The boolean for the action from the solr item details table
	 * @return bool  The calculated boolean for the action
	 */
	private function calculateForActionByPtype(array $activePTypes, string $actionPTypes, bool $isActionable){
		if (strlen($actionPTypes) > 0 && !in_array($actionPTypes, self::SIERRA_PTYPE_WILDCARDS)){
			$actionPTypes   = explode(',', $actionPTypes);
			$matchingPTypes = array_intersect($actionPTypes, $activePTypes);
			if (count($matchingPTypes) == 0){
				$isActionable = false;
			}
		}
		return $isActionable;
	}

	/**
	 * @param \Pika\BibliographicDrivers\GroupedWork\RecordDetails $recordDetails
	 * @param GroupedWork $groupedWork
	 * @param Timer $timer
	 * @param array $scopingInfo
	 * @param array $activePTypes
	 * @param Location $searchLocation
	 * @param Library $library
	 * @param bool $forCovers
	 * @return array
	 */
	//TODO: this function should be optimized much more when loading for covers
	protected function setupRelatedRecordDetails($recordDetails, $groupedWork, $timer, $scopingInfo, $activePTypes, $searchLocation, $library, $forCovers = false){
		//Check to see if we have any volume data for the record
		global $memoryWatcher;

		//		list($source) = explode(':', $recordDetails->recordFullIdentifier, 1); // this does not work for 'overdrive:27770ba9-9e68-410c-902b-de2de8e2b7fe', returns 'overdrive:27770ba9-9e68-410c-902b-de2de8e2b7fe'
		// when loading book covers.
		[$source] = explode(':', $recordDetails->recordFullIdentifier, 2);
		require_once ROOT_DIR . '/RecordDrivers/Factory.php';
		$recordDriver = RecordDriverFactory::initRecordDriverById($recordDetails->recordFullIdentifier, $groupedWork);
		$timer->logTime("Loaded Record Driver for $recordDetails->recordFullIdentifier");
		$memoryWatcher->logMemory("Loaded Record Driver for $recordDetails->recordFullIdentifier");

//		$volumeData = $recordDriver->getVolumeInfoForRecord();

		//Set up the base record
		$relatedRecord = [
			'id'                     => $recordDetails->recordFullIdentifier,
			'driver'                 => $recordDriver,
			'url'                    => $recordDriver != null ? $recordDriver->getRecordUrl() : '',
			'format'                 => $recordDetails->primaryFormat,
			'formatCategory'         => $recordDetails->primaryFormatCategory,
			'edition'                => $recordDetails->edition,
			'language'               => $recordDetails->primaryLanguage,//TODO :might be obsolete now
			'publisher'              => $recordDetails->publisher,
			'publicationDate'        => $recordDetails->publicationDate,
			'physical'               => $recordDetails->physicalDescription,
			'callNumber'             => '',
			'available'              => false,
			'availableOnline'        => false,
			'availableLocally'       => false,
			'availableHere'          => false,
			'inLibraryUseOnly'       => true,
			'allLibraryUseOnly'      => true,
			'isEContent'             => false,
			'availableCopies'        => 0,
			'copies'                 => 0,
			'onOrderCopies'          => 0,
			'localAvailableCopies'   => 0,
			'localCopies'            => 0,
			'numHolds'               => !$forCovers && $recordDriver != null ? $recordDriver->getNumHolds() : 0,
//			'volumeHolds'            => !$forCovers && $recordDriver != null ? $recordDriver->getVolumeHolds($volumeData) : null,
			'hasLocalItem'           => false,
			'hasAHomePickupItem'     => false,
			'homePickupLocations'    => [],
//			'holdRatio'              => 0, // Only calculate as needed for sorting
			//'locationLabel'          => '', // Location Label field seems to be obsolete. pascal 2/26/2025
			'shelfLocation'          => '',
			'bookable'               => false,
			'holdable'               => false,
			'itemSummary'            => [],
			'groupedStatus'          => 'Currently Unavailable',
			'source'                 => $source,
			'actions'                => [],
			'schemaDotOrgType'       => $this->getSchemaOrgType($recordDetails->primaryFormat),
			'schemaDotOrgBookFormat' => $this->getSchemaOrgBookFormat($recordDetails->primaryFormat),
			'abridged'               => $recordDetails->abridged,
		];
		$timer->logTime('Setup base related record');
		$memoryWatcher->logMemory('Setup base related record');

		//Process the items for the record and add additional information as needed
		$localShelfLocation   = null;
		$libraryShelfLocation = null;
		$localCallNumber      = null;
		$libraryCallNumber    = null;
		$relatedUrls          = [];

		$recordHoldable     = false;
		$recordBookable     = false;
		$recordIsHomePickUp = false;

		$i                 = 0;
		$allLibraryUseOnly = true;
		/** @var \Pika\BibliographicDrivers\GroupedWork\ItemDetails $curItem */
		foreach ($this->relatedItemsByRecordId[$recordDetails->recordFullIdentifier] as $curItem){
			$itemId        = $curItem->itemIdentifier;
			$shelfLocation = $curItem->shelfLocation;

			if (!$forCovers){
				if (!empty($itemId) && $recordDriver != null && $recordDriver->hasOpacFieldMessage()){
					$opacMessage = $recordDriver->getOpacFieldMessage($itemId);
					if ($opacMessage && $opacMessage != '-' && $opacMessage != ' '){
						$opacMessageTranslation = translate('opacFieldMessageCode_' . $opacMessage);
						if ($opacMessageTranslation != 'opacFieldMessageCode_'){ // Only display if the code has a translation
							$shelfLocation = "$opacMessageTranslation $shelfLocation";
						}
					}
				}
			}

			$scopeKey     = $curItem->recordIdentifier . ':' . $itemId;
			$callNumber   = $curItem->callNumber;
			$numCopies    = $curItem->numCopies;
			$isOrderItem  = $curItem->isOrderItem;
			$isEcontent   = $curItem->isEContent;
			$locationCode = $curItem->locationCode;

			/** @var \Pika\BibliographicDrivers\GroupedWork\ScopeDetails $scopingDetails */
			$scopingDetails = $scopingInfo[$scopeKey];
			//Get Scoping information for this record
			$groupedStatus    = $scopingDetails->groupedStatus;
			$locallyOwned     = $scopingDetails->locallyOwned;
			$available        = $scopingDetails->available;
			$holdable         = $scopingDetails->holdable;
			$bookable         = $scopingDetails->bookable;
			$isHomePickUp     = $scopingDetails->isHomePickUpOnly;
			$inLibraryUseOnly = $scopingDetails->inLibraryUseOnly;
			$libraryOwned     = $scopingDetails->libraryOwned;
			$holdablePTypes   = $scopingDetails->holdablePTypes;
			$bookablePTypes   = $scopingDetails->bookablePTypes;
			$homePickUpPTypes = $scopingDetails->homePickUpPTypes;
			$status           = $curItem->detailedStatus;

			if (!$available && strtolower($status) == 'library use only'){
				$status = 'Checked Out (library use only)';
			}
			if (!$inLibraryUseOnly){
				$allLibraryUseOnly = false;
			}

			// If holdable pTypes were calculated for this scope, determine if the record is holdable to the scope's pTypes
			$holdable = $this->calculateForActionByPtype($activePTypes, $holdablePTypes, $holdable);
			if ($holdable){
				// If this item is holdable, then treat the record as holdable when building action buttons
				$recordHoldable = true;
			}

			// If bookable pTypes were calculated for this scope, determine if the record is bookable to the scope's pTypes
			$bookable = $this->calculateForActionByPtype($activePTypes, $bookablePTypes, $bookable);
			if ($bookable){
				// If this item is bookable, then treat the record as bookable when building action buttons
				$recordBookable = true;
			}


			global $configArray;
			if (!empty($configArray['Catalog']['displayHomePickupItems']) && $holdable && $isHomePickUp){
				if ($this->calculateForActionByPtype($activePTypes, $homePickUpPTypes, $isHomePickUp)){
					$recordIsHomePickUp                  = true;
					$relatedRecord['hasAHomePickupItem'] = true;
					// Any home pickup item for the record should turn on this flag
					//$relatedRecord['homePickupLocations'][] = $locationCode;
					$relatedRecord['homePickupLocations'][] = [
						'location'   => $shelfLocation,
						'callnumber' => $callNumber,
						'status'     => $status,
					];
				}
			}

			//Update the record with information from the item and from scoping.
			if ($isEcontent){
				// the scope local url should override the item url if it is set
				if (!empty($scopingDetails->localUrl)){
					$relatedUrls[] = [
						'source' => $curItem->eContentSource,
						'url'    => $scopingDetails->localUrl
					];
				}else{
					$relatedUrls[] = [
						'source' => $curItem->eContentSource,
						'url'    => $curItem->eContentUrl
					];
				}

				$relatedRecord['eContentSource'] = $curItem->eContentSource;
				$relatedRecord['isEContent']     = true;
				if (!$forCovers){
					$relatedRecord['format'] = $relatedRecord['eContentSource'] . ' ' . $recordDetails->primaryFormat; // Break out eContent manifestations by the source of the eContent
				}
			}elseif (!empty($curItem->eContentUrl)){
				// Special Physical Records, like KitKeeper
				$relatedUrls[] = [
					'url' => $curItem->eContentUrl
				];
			}

			$displayByDefault = false;
			if ($available){
				if ($isEcontent){
					$relatedRecord['availableOnline'] = true;
				}else{
					$relatedRecord['available'] = true;
				}
				$relatedRecord['availableCopies'] += $numCopies;
				if ($searchLocation){
					$displayByDefault = $locallyOwned || $isEcontent;
				}elseif ($library){
					$displayByDefault = $libraryOwned || $isEcontent;
				}
			}
			if ($isOrderItem){
				$relatedRecord['onOrderCopies'] += $numCopies;
			}else{
				$relatedRecord['copies'] += $numCopies;
			}
			if (!$inLibraryUseOnly){
				$relatedRecord['inLibraryUseOnly'] = false;
			}
			$relatedRecord['allLibraryUseOnly'] = $allLibraryUseOnly;
			if ($holdable){
				$relatedRecord['holdable'] = true;
			}
			if ($bookable){
				$relatedRecord['bookable'] = true;
			}
			$relatedRecord['groupedStatus']      = GroupedWorkDriver::keepBestGroupedStatus($relatedRecord['groupedStatus'], $groupedStatus);
			$relatedRecord['isAvailableToOrder'] = $relatedRecord['groupedStatus'] == 'Available to Order';

//			$volumeRecordLabel = null;
//			$volumeId          = null;
//			if (count($volumeData)){
//				/** @var IlsVolumeInfo $volumeDataPoint */
//				foreach ($volumeData as $volumeDataPoint){
//					if ((strlen($volumeDataPoint->relatedItems) == 0) || (strpos($volumeDataPoint->relatedItems, $curItem->itemIdentifier) !== false)){
//						if ($holdable){
//							$volumeDataPoint->holdable = true;
//						}
//						if (strlen($volumeDataPoint->relatedItems) > 0){
//							$volumeRecordLabel   = $volumeDataPoint->displayLabel;
//							$volumeId = $volumeDataPoint->volumeId;
//							break;
//						}
//					}
//				}
//			}
//			if ($volumeRecordLabel){
//				$description = $shelfLocation . ':' . $volumeRecordLabel . $callNumber;
//			}else{
				$description = $shelfLocation . ':' . $callNumber;
//			}

			if ($locallyOwned){
				if ($localShelfLocation == null){
					$localShelfLocation = $shelfLocation;
				}
				if ($localCallNumber == null){
					$localCallNumber = $callNumber;
				}
				if ($available && !$isEcontent){
					$relatedRecord['availableHere']    = true;
					$relatedRecord['availableLocally'] = true;
					$relatedRecord['class']            = 'here';
				}
				$relatedRecord['localCopies']  += $numCopies;
				$relatedRecord['hasLocalItem'] = true;
				$sectionId                     = 1;
				$section                       = 'In this library';
			}elseif ($libraryOwned){
				if ($libraryShelfLocation == null){
					$libraryShelfLocation = $shelfLocation;
				}
				if ($libraryCallNumber == null){
					$libraryCallNumber = $callNumber;
				}
				if ($available && !$isEcontent){
					$relatedRecord['availableLocally'] = true;
				}
				$relatedRecord['localCopies'] += $numCopies;
				if ($searchLocation == null || $isEcontent){
					$relatedRecord['hasLocalItem'] = true;
				}
				$sectionId = 5;
				$section   = $library->displayName;
			}elseif ($isOrderItem){
				$sectionId = 7;
				$section   = 'On Order';
			}else{
				$sectionId = 6;
				$section   = 'Other Locations';
			}
			$itemSummaryKey = $sectionId . ' ' . $description;

//			if ((strlen($volumeRecordLabel) > 0) && !substr($callNumber, -strlen($volumeRecordLabel)) == $volumeRecordLabel){
//				$callNumber = trim($callNumber . ' ' . $volumeRecordLabel);
//			}
			//Add the item to the item summary ($relatedRecord['itemSummary'])
			$itemSummaryInfo = [
				'description'        => $description,
				'shelfLocation'      => $shelfLocation,
				'callNumber'         => $callNumber,
				'totalCopies'        => 1,
				'availableCopies'    => ($available && !$isOrderItem) ? $numCopies : 0,
				'isLocalItem'        => $locallyOwned,
				'isLibraryItem'      => $libraryOwned,
				'inLibraryUseOnly'   => $inLibraryUseOnly,
				'allLibraryUseOnly'  => $inLibraryUseOnly,
				'displayByDefault'   => $displayByDefault,
				'onOrderCopies'      => $isOrderItem ? $numCopies : 0,
//				'isAvailableToOrder' => $groupedStatus == 'Available to Order', // special status for patron-driven acquisitions; The item is available for the patron to request it be Ordered.
				'status'             => $groupedStatus,
				'statusFull'         => $status,
				'available'          => $available,
				'holdable'           => $holdable,
				'bookable'           => $bookable,
				'sectionId'          => $sectionId,
				'section'            => $section,
				'relatedUrls'        => $relatedUrls,
				'lastCheckinDate'    => $curItem->lastCheckinDate,
//				'volume'             => $volumeRecordLabel,
//				'volumeId'           => $volumeId,
				'isEContent'         => $isEcontent,
				'locationCode'       => $locationCode,
				'itemId'             => $itemId
			];
			if (!$forCovers){
				$itemSummaryInfo['actions'] = $recordDriver != null ? $recordDriver->getItemActions($itemSummaryInfo) : [];
			}

			//Group the item based on location and call number for display in the summary
			if (isset($relatedRecord['itemSummary'][$itemSummaryKey])){
				//TODO: The if/else block is duplicative or similar to method mergeItemSummary()
				$relatedRecord['itemSummary'][$itemSummaryKey]['totalCopies']++;
				$relatedRecord['itemSummary'][$itemSummaryKey]['availableCopies'] += $itemSummaryInfo['availableCopies'];
				if ($itemSummaryInfo['displayByDefault']){
					$relatedRecord['itemSummary'][$itemSummaryKey]['displayByDefault'] = true;
				}
				if ($itemSummaryInfo['available']){
					// Needed for 'Available At' facet, especially if the first item that populated the itemSummary was unavailable
					$relatedRecord['itemSummary'][$itemSummaryKey]['available'] = true;
				}
				$relatedRecord['itemSummary'][$itemSummaryKey]['onOrderCopies'] += $itemSummaryInfo['onOrderCopies'];
				$lastStatus                                                     = $relatedRecord['itemSummary'][$itemSummaryKey]['status'];
				$relatedRecord['itemSummary'][$itemSummaryKey]['status']        = GroupedWorkDriver::keepBestGroupedStatus($lastStatus, $groupedStatus);
				if ($lastStatus != $relatedRecord['itemSummary'][$itemSummaryKey]['status']){
					$relatedRecord['itemSummary'][$itemSummaryKey]['statusFull'] = $itemSummaryInfo['statusFull'];
				}
			}else{
				$relatedRecord['itemSummary'][$itemSummaryKey] = $itemSummaryInfo;
			}
			//Also add to the details for display in the full list
			$relatedRecord['itemDetails'][$itemSummaryKey . $i++] = $itemSummaryInfo;
		}
		if ($localShelfLocation != null){
			$relatedRecord['shelfLocation'] = $localShelfLocation;
		}elseif ($libraryShelfLocation != null){
			$relatedRecord['shelfLocation'] = $libraryShelfLocation;
		}
		if ($localCallNumber != null){
			$relatedRecord['callNumber'] = $localCallNumber;
		}elseif ($libraryCallNumber != null){
			$relatedRecord['callNumber'] = $libraryCallNumber;
		}

		ksort($relatedRecord['itemDetails']); // ItemDetails is used in the MarcRecord driver to set up displaying the copies section. See MarcRecord->loadCopies()
		ksort($relatedRecord['itemSummary']);
		$timer->logTime('Setup record items');
		$memoryWatcher->logMemory('Setup record items');

		if (!$forCovers){
			$recordAvailable          = $relatedRecord['availableLocally'] || $relatedRecord['availableOnline'];
			$relatedRecord['actions'] = $recordDriver != null ? $recordDriver->getRecordActions($recordAvailable, $recordHoldable, $recordBookable, $recordIsHomePickUp, $relatedUrls/*, $volumeData*/) : [];
			$timer->logTime('Loaded actions');
			$memoryWatcher->logMemory('Loaded actions');
		}

		$recordDriver = null;
		return $relatedRecord;
	}

	public function getRecordUrl(){
		$recordId = $this->getUniqueID();
		return '/' . $this->getModule() . '/' . urlencode($recordId) . '/Home';
	}

	public function getAbsoluteUrl(){
		global $configArray;
		$recordId = $this->getUniqueID();
		return $configArray['Site']['url'] . '/' . $this->getModule() . '/' . urlencode($recordId) . '/Home';
	}

	/**
	 * A relative URL that is a link to the Full Record View AND additional search parameters
	 * to the recent search the user has navigated from
	 *
	 * @return string
	 */
	public function getLinkUrl() {
		return parent::getLinkUrl();
	}

	public function getModule(){
		return 'GroupedWork';
	}

	public function assignBasicTitleDetails(){
		global $interface;
		$relatedRecords = $this->getRelatedRecords();

		$summPublisher    = null;
		$summPubDate      = null;
		$summPhysicalDesc = null;
		$summEdition      = null;
		$summLanguage     = null;
		$isFirst          = true;
		foreach ($relatedRecords as $relatedRecord){
			if ($isFirst){
				$summPublisher    = $relatedRecord['publisher'];
				$summPubDate      = $relatedRecord['publicationDate'];
				$summPhysicalDesc = $relatedRecord['physical'];
				$summEdition      = $relatedRecord['edition'];
				$summLanguage     = $relatedRecord['language'];
			}else{
				// Only display these details if it is the same for every related record, otherwise don't populate
				if ($summPublisher != $relatedRecord['publisher']){
					$summPublisher = null;
				}
				if ($summPubDate != $relatedRecord['publicationDate']){
					$summPubDate = null;
				}
				if ($summPhysicalDesc != $relatedRecord['physical']){
					$summPhysicalDesc = null;
				}
				if ($summEdition != $relatedRecord['edition']){
					$summEdition = null;
				}
				if ($summLanguage != $relatedRecord['language']){
					$summLanguage = null;
				}
			}
			$isFirst = false;
		}
		$interface->assign('summPublisher', $summPublisher);
		$interface->assign('summPubDate', $summPubDate);
		$interface->assign('summPhysicalDesc', $summPhysicalDesc);
		$interface->assign('summEdition', $summEdition);
		$interface->assign('summLanguage', $summLanguage);
		$interface->assign('summArInfo', $this->getAcceleratedReaderDisplayString());
		$interface->assign('summLexileInfo', $this->getLexileDisplayString());
		$interface->assign('summFountasPinnell', $this->getFountasPinnellLevel());
	}

	public function getAcceleratedReaderDisplayString(){
		$acceleratedReaderInfo = $this->getAcceleratedReaderData();
		if ($acceleratedReaderInfo != null){
			$arDetails = '';
			if (isset($acceleratedReaderInfo['interestLevel'])){
				$arDetails .= '<abbr title="Interest Level">IL</abbr>: <strong>';
				switch ($acceleratedReaderInfo['interestLevel']){
					case 'LG':
						$arDetails .= '<abbr title="Lower Grades, K - 3">LG</abbr>';
						break;
					case 'MG':
						$arDetails .= '<abbr title="Middle Grades, 4 - 8">MG</abbr>';
						break;
					case 'MG+':
						$arDetails .= '<abbr title="Middle Grades Plus, 6 and up">MG+</abbr>';
						break;
					case 'UG':
						$arDetails .= '<abbr title="Upper Grades, 9 - 12">UG</abbr>';
						break;
					default:
						$arDetails .= $acceleratedReaderInfo['interestLevel'];
				}
				$arDetails .=  '</strong>';
			}
			if (isset($acceleratedReaderInfo['readingLevel'])){
				if (strlen($arDetails) > 0){
					$arDetails .= ' - ';
				}
				$arDetails .= '<abbr title="Book Level">BL</abbr>: <strong>' . $acceleratedReaderInfo['readingLevel'] . '</strong>';
			}
			if (isset($acceleratedReaderInfo['pointValue'])){
				if (strlen($arDetails) > 0){
					$arDetails .= ' - ';
				}
				$arDetails .= '<abbr title="Accelerated Reading">AR</abbr> Points: <strong>' . $acceleratedReaderInfo['pointValue'] . '</strong>';
			}
			return $arDetails;
		}
		return null;
	}

	public function getLexileDisplayString(){
		$lexileScore = $this->getLexileScore();
		if ($lexileScore != null){
			$lexileInfo = '';
			$lexileCode = $this->getLexileCode();
			if ($lexileCode != null){
				$lexileInfo .= $lexileCode . ' ';
			}
			$lexileInfo .= $lexileScore . 'L';
			return $lexileInfo;
		}
		return null;
	}

	private function getSemanticWorkExamples(){
		global $configArray;
		$relatedWorkExamples = [];
		$relatedRecords      = $this->getRelatedRecords();
		foreach ($relatedRecords as $record){
			$relatedWorkExample = [
				'@id'   => $configArray['Site']['url'] . $record['url'],
				'@type' => $record['schemaDotOrgType']
			];
			if ($record['schemaDotOrgBookFormat']){
				$relatedWorkExample['bookFormat'] = $record['schemaDotOrgBookFormat'];
			}
			$relatedWorkExamples[] = $relatedWorkExample;
		}
		return $relatedWorkExamples;
	}

	private function getSchemaOrgType($pikaFormat){
		switch ($pikaFormat){
			case 'Audio':
			case 'Audio Book':
			case 'Audio Cassette':
			case 'Audio CD':
			case 'Book':
			case 'Book Club Kit':
			case 'eAudiobook':
			case 'eBook':
			case 'eMagazine':
			case 'CD':
			case 'Journal':
			case 'Large Print':
			case 'Manuscript':
			case 'Musical Score':
			case 'Newspaper':
			case 'Playaway':
			case 'Serial':
				return 'Book';

			case 'eComic':
			case 'Graphic Novel':
				return 'ComicStory';

			case 'eMusic':
			case 'Music Recording':
			case 'Phonograph':
				return 'MusicRecording';

			case 'Blu-ray':
			case 'DVD':
			case 'eVideo':
			case 'VHS':
			case 'Video':
				return 'Movie';

			case 'Map':
				return 'Map';

			case 'Nintendo 3DS':
			case 'Nintendo Wii':
			case 'Nintendo Wii U':
			case 'PlayStation':
			case 'PlayStation 3':
			case 'PlayStation 4':
			case 'Windows Game':
			case 'Xbox 360':
			case 'Xbox 360 Kinect':
			case 'Xbox One':
				return 'Game';

			case 'Web Content':
				return 'WebPage';

			default:
				$this->logger->notice("No schema.org format set for $pikaFormat");
				return 'CreativeWork';
		}
	}

	private function getSchemaOrgBookFormat($pikaFormat){
		switch ($pikaFormat){
			case 'Book':
			case 'Large Print':
			case 'Manuscript':
				return 'Hardcover';

			case 'Audio':
			case 'Audio Cassette':
			case 'Audio CD':
			case 'CD':
			case 'eAudiobook':
			case 'Playaway':
				return 'AudiobookFormat';

			case 'eBook':
			case 'eComic':
			case 'eMagazine':
				return 'EBook';

			case 'Graphic Novel':
			case 'Journal':
				return 'Paperback';

			default:
				$this->logger->notice("No schema.org book format set for $pikaFormat");
				return '';
		}
	}

	function getOGType(){
		$pikaFormat = strtolower($this->getFormatCategory());
		switch ($pikaFormat){
			case 'books':
			case 'ebook':
			case 'audio books':
				return 'book';

			case 'music':
				return 'music.album';

			case 'movies':
				return 'video.movie';

			default:
				return 'website';
		}
	}

	function getMoreInfoLinkUrl(){
		// if the grouped work consists of only 1 related item, return the record url, otherwise return the grouped-work url
		//Rather than loading all related records which can be slow, just get the count
		$numRelatedRecords = $this->getNumRelatedRecords();

		if ($numRelatedRecords == 1){
			//Now that we know that we need more detailed information, load the related record.
			$relatedRecords = $this->getRelatedRecords(false);
			$onlyRecord     = reset($relatedRecords);
			$url            = !empty($onlyRecord['driver']) ? $onlyRecord['driver']->getlinkUrl() : $onlyRecord['url'];
		}else{
			$url = $this->getLinkUrl();
		}
		return $url;
	}

}
