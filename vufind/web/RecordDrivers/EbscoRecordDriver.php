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
 * Record Driver for EBSCO titles
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/16/2016
 * Time: 8:31 PM
 */
require_once ROOT_DIR . '/RecordDrivers/Interface.php';

class EbscoRecordDriver extends RecordInterface {
	private $recordData;

	/**
	 * Constructor.  We build the object using all the data retrieved
	 * from the (Solr) index.  Since we have to
	 * make a search call to find out which record driver to construct,
	 * we will already have this data available, so we might as well
	 * just pass it into the constructor.
	 *
	 * @param   array|File_MARC_Record||string   $recordData     Data to construct the driver from
	 * @access  public
	 */
	public function __construct($recordData) {
		if (is_string($recordData)){
			require_once ROOT_DIR . '/sys/Ebsco/EDS_API.php';
			$edsApi = EDS_API::getInstance();
			list($dbId, $an) = explode(':', $recordData);
			$this->recordData = $edsApi->retrieveRecord($dbId, $an);
		}else{
			$this->recordData = $recordData;
		}
	}

	public function isValid(){
		return true;
	}

	public function getBookcoverUrl($size = 'small') {
		if ($this->recordData->ImageInfo){
			return (string)$this->recordData->ImageInfo->CoverArt->Target;
		}else{
			return null;
		}

	}

	/**
	 * Get text that can be displayed to represent this record in
	 * breadcrumbs.
	 *
	 * @access  public
	 * @return  string              Breadcrumb text to represent this record.
	 */
	public function getBreadcrumb() {
		// TODO: Implement getBreadcrumb() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name
	 * to load in order to display the requested citation format.
	 * For legal values, see getCitationFormats().  Returns null if
	 * format is not supported.
	 *
	 * @param   string $format Citation format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getCitation($format) {
		// TODO: Implement getCitation() method.
	}

	/**
	 * Get an array of strings representing citation formats supported
	 * by this record's data (empty if none).  Legal values: "APA", "MLA".
	 *
	 * @access  public
	 * @return  array               Strings representing citation formats.
	 */
	public function getCitationFormats() {
		// TODO: Implement getCitationFormats() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to export the record in the requested format.  For
	 * legal values, see getExportFormats().  Returns null if format is
	 * not supported.
	 *
	 * @param   string $format Export format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getExport($format) {
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
	public function getExportFormats() {
		// TODO: Implement getExportFormats() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * user's favorites list.
	 *
	 * @access  public
	 * @param   int $listId ID of list containing desired tags/notes (or
	 *                              null to show tags/notes from all user's lists).
	 * @param   bool $allowEdit Should we display edit controls?
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getListEntry($listId = null, $allowEdit = true) {
		// TODO: Implement getListEntry() method.
	}

	public function getLinkUrl($unscoped = false) {
		return $this->getRecordUrl();
	}

	public function getAbsoluteUrl(){
		return $this->getEbscoUrl();
	}

	public function getRecordUrl() {
		//TODO: Switch back to an internal link once we do a full EBSCO implementation
		//return  '/EBSCO/Home?id=' . urlencode($this->getUniqueID());
		return $this->getEbscoUrl();
	}

	private function getEbscoUrl(){
		return $this->recordData->PLink;
	}

	public function getModule() {
		return 'EBSCO';
	}

	/**
	 * Get an XML RDF representation of the data in this record.
	 *
	 * @access  public
	 * @return  mixed               XML RDF data (false if unsupported or error).
	 */
	public function getRDFXML() {
		// TODO: Implement getRDFXML() method.
	}

	public function getSemanticData()
	{
		// TODO: Implement getSemanticData() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @param string $view          The view style for this search entry. (Only the 'list' view is applicable for EBSCO searching)
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getSearchResult($view = 'list') {
		global $interface;

		$id = $this->getUniqueID();
		$interface->assign('summId', $id);
		$interface->assign('summShortId', $id);
		$interface->assign('module', $this->getModule());

		$formats = $this->getFormats();
		$interface->assign('summFormats', $formats);

		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summAuthor', $this->getAuthor());
		$interface->assign('summSourceDatabase', $this->getSourceDatabase());
		$interface->assign('summHasFullText', $this->hasFullText());

		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		$interface->assign('summURLs', $this->getURLs());

		return 'RecordDrivers/EBSCO/result.tpl';
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getCombinedResult() {
		global $interface;

		$id = $this->getUniqueID();
		$interface->assign('summId', $id);
		$interface->assign('summShortId', $id);
		$interface->assign('module', $this->getModule());

		$formats = $this->getFormats();
		$interface->assign('summFormats', $formats);

		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summAuthor', $this->getAuthor());
		$interface->assign('summSourceDatabase', $this->getSourceDatabase());
		$interface->assign('summHasFullText', $this->hasFullText());

		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		$interface->assign('summURLs', $this->getURLs());

		return 'RecordDrivers/EBSCO/combinedResult.tpl';
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display the full record information on the Staff
	 * View tab of the record view page.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getStaffView() {
		return null;
	}

	/**
	 * Get the full title of the record.
	 *
	 * @return  string
	 */
	public function getTitle() {
		if (isset($this->recordData->RecordInfo->BibRecord->BibEntity)){
			return (string)$this->recordData->RecordInfo->BibRecord->BibEntity->Titles->Title->TitleFull;
		}else{
			return 'Unknown';
		}
	}

	/**
	 * load in order to display the Table of Contents for the title.
	 *  Returns null if no Table of Contents is available.
	 *
	 * @access  public
	 * @return  string[]|null              contents to display.
	 */
	public function getTOC() {
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
	public function getUniqueID() {
		return  (string)$this->recordData->Header->DbId . ':'. (string)$this->recordData->Header->An;
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
	public function hasFullText() {
		return $this->recordData->FullText->Text->Availability == 1;
	}

	public function getFullText() {
		$fullText = (string)$this->recordData->FullText->Text->Value;
		$fullText = html_entity_decode($fullText);
		$fullText = preg_replace('/<anid>.*?<\/anid>/', '', $fullText);
		return $fullText;
	}

	/**
	 * Does this record support an RDF representation?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasRDF() {
		return false;
	}

	public function getDescription() {
		return '';
	}

	public function getMoreDetailsOptions() {
		// TODO: Implement getMoreDetailsOptions() method.
	}

	public function getItemActions($itemInfo) {
		// TODO: Implement getItemActions() method.
	}

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $isHomePickupRecord, $relatedUrls = null) {
		// TODO: Implement getRecordActions() method.
	}

	public function getFormats() {
		return (string)$this->recordData->Header->PubType;
	}

	public function getCleanISSN() {
		return '';
	}

	public function getURLs() {
		return array();
	}

	public function getSourceDatabase() {
		return $this->recordData->Header->DbLabel;
	}

	public function getAuthor() {
		if (count($this->recordData->Items)){
			foreach ($this->recordData->Items->Item as $item){
				if ($item->Name == 'Author'){
					return strip_tags((string)$item->Data);
				}
			}
		}
	}

	public function getExploreMoreInfo(){
		global $configArray;
		$exploreMoreOptions = array();
		if ($configArray['Catalog']['showExploreMoreForFullRecords']) {
			require_once ROOT_DIR . '/sys/ExploreMore.php';
			$exploreMore = new ExploreMore();
			$exploreMore->loadExploreMoreSidebar('ebsco', $this);
		}
		return $exploreMoreOptions;
	}

	public function getAllSubjectHeadings(){
		$subjectHeadings = array();
		if (count(@$this->recordData->RecordInfo->BibRecord->BibEntity->Subjects) != 0){
			foreach ($this->recordData->RecordInfo->BibRecord->BibEntity->Subjects->Subject as $subject){
				$subjectHeadings[] = (string)$subject->SubjectFull;
			}
		}
		return $subjectHeadings;
	}

	public function getPermanentId(){
		return $this->getUniqueID();
	}
}
