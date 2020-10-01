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
 * Record Driver Interface
 *
 * This interface class is the definition of the required methods for
 * interacting with a particular metadata record format.
 */
abstract class RecordInterface {

	/**
	 * Constructor.  We build the object using all the data retrieved
	 * from the (Solr) index.  Since we have to
	 * make a search call to find out which record driver to construct,
	 * we will already have this data available, so we might as well
	 * just pass it into the constructor.
	 *
	 * @param SourceAndId|array|File_MARC_Record|string   $recordData     Data to construct the driver from
	 * @access                       public
	 */
	public abstract function __construct($recordData);

	public abstract function getBookcoverUrl($size = 'small');

	/**
	 * Get text that can be displayed to represent this title in
	 * breadcrumbs.
	 *
	 * @access  public
	 * @return  string              Breadcrumb text to represent this title.
	 */
	public abstract function getBreadcrumb();

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
	public abstract function getCitation($format);

	/**
	 * Get an array of strings representing citation formats supported
	 * by this record's data (empty if none).  Legal values: "APA", "MLA".
	 *
	 * @access  public
	 * @return  array               Strings representing citation formats.
	 */
	public abstract function getCitationFormats();

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
	public abstract function getExport($format);

	/**
	 * Get an array of strings representing formats in which this record's
	 * data may be exported (empty if none).  Legal values: "RefWorks",
	 * "EndNote", "MARC", "RDF".
	 *
	 * @access  public
	 * @return  array               Strings representing export formats.
	 */
	public abstract function getExportFormats();

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * user's favorites list.
	 *
	 * @access  public
	 * @param object $user          User object owning tag/note metadata.
	 * @param int    $listId        ID of list containing desired tags/notes (or
	 *                              null to show tags/notes from all user's lists).
	 * @param bool   $allowEdit     Should we display edit controls?
	 * @return  string              Name of Smarty template file to display.
	 */
	public abstract function getListEntry($user, $listId = null, $allowEdit = true);

	/**
	 * A relative URL that is a link to the Full Record View AND additional search parameters
	 * to the recent search the user has navigated from
	 *
	 * @return string
	 */
	public function getLinkUrl() {
		global $interface;
		$linkUrl = $this->getRecordUrl();
		$extraParams = array();
		if (!empty($interface->get_template_vars('searchId'))){
			$extraParams[] = 'searchId=' . $interface->get_template_vars('searchId');
			$extraParams[] = 'recordIndex=' . $interface->get_template_vars('recordIndex');
			$extraParams[] = 'page='  . $interface->get_template_vars('page');
			$extraParams[] = 'searchSource=' . $interface->get_template_vars('searchSource');
		}

		if (count($extraParams) > 0){
			$linkUrl .= '?' . implode('&', $extraParams);
		}
		return $linkUrl;
	}

	/**
	 * A relative URL that is a link to the Full Record View only; no search parameters
	 *
	 * @return string
	 */
	public abstract function getRecordUrl();

	/**
	 * A full URL that is a link to the Full Record View
	 *
	 * @return string
	 */
	public abstract function getAbsoluteUrl();

	public abstract function getModule();

	/**
	 * Get an XML RDF representation of the data in this record.
	 *
	 * @access  public
	 * @return  mixed               XML RDF data (false if unsupported or error).
	 */
	public abstract function getRDFXML();

	/**
	 * Get Reviews for this title using an ISBN associated with this record
	 * @return string[]
	 */
//	public function getReviews(){
//		require_once ROOT_DIR . '/sys/Reviews.php';
//		$rev = new ExternalReviews($this->getCleanISBN());
//		return $rev->fetch();
//	}

	/**
	 * Get structured linked data related to the record.
	 *
	 * @see https://schema.org/
	 * @see http://linkeddata.org/
	 * @see https://json-ld.org/
	 * @return array
	 */
	public abstract function getSemanticData();

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public abstract function getSearchResult();

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display the full record information on the Staff
	 * View tab of the record view page.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public abstract function getStaffView();

	/**
	 * Get the full title of the record.
	 *
	 * @return  string
	 */
	public abstract function getTitle();

	/**
	 * load in order to display the Table of Contents for the title.
	 *  Returns null if no Table of Contents is available.
	 *
	 * @access  public
	 * @return  string[]|null              contents to display.
	 */
	public abstract function getTOC();

	/**
	 * Return the unique identifier of this record within the Solr index;
	 * useful for retrieving additional information (like tags and user
	 * comments) from the external MySQL database.
	 *
	 * @access  public
	 * @return  string              Unique identifier.
	 */
	public abstract function getUniqueID();

	//TODO: getId() & getUniqueID seem to be equivalent; I think I like getRecordId() best; got to make this work with class SourceAndId
	//public abstract function getId();

	/**
	 * Does this record have searchable full text in the index?
	 *
	 * Note: As of this writing, searchable full text is not a VuFind feature,
	 *       but this method will be useful if/when it is eventually added.
	 *
	 * @access  public
	 * @return  bool
	 */
	public abstract function hasFullText();

	/**
	 * Does this record support an RDF representation?
	 *
	 * @access  public
	 * @return  bool
	 */
	public abstract function hasRDF();

	public abstract function getDescription();

	public abstract function getMoreDetailsOptions();

	public function getBaseMoreDetailsOptions($isbn){
		global $interface;
		global $configArray;
		global $timer;
		$moreDetailsOptions = [];
		$description        = $this->getDescription();
		if (strlen($description) == 0){
			$description = 'Description not provided';
		}
		$description = strip_tags($description, '<a><b><p><i><em><strong><ul><li><ol>');
		$interface->assign('description', $description);
		$moreDetailsOptions['description'] = [
			'label'         => 'Description',
			'body'          => $description,
			'hideByDefault' => false,
			'openByDefault' => true,
		];
		$timer->logTime('Loaded Description');
		$moreDetailsOptions['series'] = [
			'label'         => 'Also in This Series',
			'body'          => $interface->fetch('GroupedWork/series.tpl'),
			'hideByDefault' => false,
			'openByDefault' => true,
		];
		$timer->logTime('Loaded Series Data');
		if (!$configArray['Catalog']['showExploreMoreForFullRecords']){
			$moreDetailsOptions['moreLikeThis'] = [
				'label'         => 'More Like This',
				'body'          => $interface->fetch('GroupedWork/moreLikeThis.tpl'),
				'hideByDefault' => false,
				'openByDefault' => true,
			];
		}
		$timer->logTime('Loaded More Like This');
		if ($interface->getVariable('enableProspectorIntegration')){
			// enableProspectorIntegration may be set by  $configArray['Content']['Prospector'] or by library setting $library->enableProspectorIntegration
			$innReachEncoreName               = $configArray['InterLibraryLoan']['innReachEncoreName'];
			$moreDetailsOptions['prospector'] = [
				'label'         => 'More Copies In ' . $innReachEncoreName,
				'body'          => '<div id="inProspectorPlaceholder">Loading ' . $innReachEncoreName . ' Copies...</div>',
				'hideByDefault' => false,
			];
		}
		$moreDetailsOptions['tableOfContents'] = [
			'label'         => 'Table of Contents',
			'body'          => $interface->fetch('GroupedWork/tableOfContents.tpl'),
			'hideByDefault' => true,
		];
		$timer->logTime('Loaded Table of Contents');
		$moreDetailsOptions['excerpt']     = [
			'label'         => 'Excerpt',
			'body'          => '<div id="excerptPlaceholder">Loading Excerpt...</div>',
			'hideByDefault' => true,
		];
		$moreDetailsOptions['authornotes'] = [
			'label'         => 'Author Notes',
			'body'          => '<div id="authornotesPlaceholder">Loading Author Notes...</div>',
			'hideByDefault' => true,
		];
		if ($interface->getVariable('showComments')){
			$moreDetailsOptions['borrowerReviews'] = [
				'label' => 'Borrower Reviews',
				'body'  => "<div id='customerReviewPlaceholder'></div>",
			];
		}
		$moreDetailsOptions['librarianReviews'] = [
			'label' => 'Librarian Reviews',
			'body'  => "<div id='librarianReviewPlaceholder'></div>",
		];
		if ($interface->getVariable('showTagging')){
			$moreDetailsOptions['tags'] = [
				'label' => 'Tagging',
				'body'  => $interface->fetch('GroupedWork/view-tags.tpl'),
			];
		}
		if ($isbn){
			$moreDetailsOptions['syndicatedReviews'] = [
				'label' => 'Published Reviews',
				'body'  => "<div id='syndicatedReviewPlaceholder'></div>",
			];
			if ($interface->getVariable('showGoodReadsReviews')){
				$moreDetailsOptions['goodreadsReviews'] = [
					'label'  => 'Reviews from GoodReads',
					'onShow' => "Pika.GroupedWork.getGoodReadsComments('$isbn');",
					'body'   => '<div id="goodReadsPlaceHolder">Loading GoodReads Reviews.</div>',
				];
			}
			if (!$configArray['Catalog']['showExploreMoreForFullRecords']){
				if ($interface->getVariable('showSimilarTitles')){
					$moreDetailsOptions['similarTitles'] = [
						'label'         => 'Similar Titles From NoveList',
						'body'          => '<div id="novelisttitlesPlaceholder"></div>',
						'hideByDefault' => true,
					];
				}
				if ($interface->getVariable('showSimilarAuthors')){
					$moreDetailsOptions['similarAuthors'] = [
						'label'         => 'Similar Authors From NoveList',
						'body'          => '<div id="novelistauthorsPlaceholder"></div>',
						'hideByDefault' => true,
					];
				}
				if ($interface->getVariable('showSimilarTitles')){
					$moreDetailsOptions['similarSeries'] = [
						'label'         => 'Similar Series From NoveList',
						'body'          => '<div id="novelistseriesPlaceholder"></div>',
						'hideByDefault' => true,
					];
				}
			}
		}
		//Do the filtering and sorting here so subclasses can use this directly
		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	public function filterAndSortMoreDetailsOptions($allOptions){
		global $library;
		global $locationSingleton;
		$activeLocation = $locationSingleton->getActiveLocation();

		$useDefault = true;
		if ($library && count($library->moreDetailsOptions) > 0){
			$moreDetailsFilters = [];
			$useDefault         = false;
			/** @var LibraryMoreDetails $option */
			foreach ($library->moreDetailsOptions as $option){
				$moreDetailsFilters[$option->source] = $option->collapseByDefault ? 'closed' : 'open';
			}
		}
		if ($activeLocation && count($activeLocation->moreDetailsOptions) > 0){
			$moreDetailsFilters = [];
			$useDefault         = false;
			/** @var LocationMoreDetails $option */
			foreach ($activeLocation->moreDetailsOptions as $option){
				$moreDetailsFilters[$option->source] = $option->collapseByDefault ? 'closed' : 'open';
			}
		}

		if ($useDefault){
			$moreDetailsFilters = RecordInterface::getDefaultMoreDetailsOptions();

		}

		$filteredMoreDetailsOptions = [];
		foreach ($moreDetailsFilters as $option => $initialState){
			if (array_key_exists($option, $allOptions)){
				$detailOptions                       = $allOptions[$option];
				$detailOptions['openByDefault']      = $initialState == 'open';
				$filteredMoreDetailsOptions[$option] = $detailOptions;
			}
		}
		return $filteredMoreDetailsOptions;
	}

	public static function getValidMoreDetailsSources(){
		return array(
			'description'       => 'Description',
			'series'            => 'Also in This Series',
			'formats'           => 'Formats',
			'copies'            => 'Copies',
			'links'             => 'Links',
			'moreLikeThis'      => 'More Like This',
			'otherEditions'     => 'Other Editions and Formats',
			'prospector'        => 'Prospector',
			'tableOfContents'   => 'Table of Contents  (MARC/Syndetics/ContentCafe)',
			'excerpt'           => 'Excerpt (Syndetics/ContentCafe)',
			'authornotes'       => 'Author Notes (Syndetics/ContentCafe)',
			'subjects'          => 'Subjects',
			'moreDetails'       => 'More Details',
			'similarSeries'     => 'Similar Series From NoveList',
			'similarTitles'     => 'Similar Titles From NoveList',
			'similarAuthors'    => 'Similar Authors From NoveList',
			'borrowerReviews'   => 'Borrower Reviews',
			'librarianReviews'  => 'Librarian Reviews',
			'syndicatedReviews' => 'Syndicated Reviews (Syndetics/ContentCafe)',
			'goodreadsReviews'  => 'GoodReads Reviews',
			'tags'              => 'Tags',
			'citations'         => 'Citations',
			'copyDetails'       => 'Copy Details (OverDrive)',
			'staff'             => 'Staff View',
		);
	}

	public static function getDefaultMoreDetailsOptions(){
		return array(
			'description'       => 'open',
			'series'            => 'open',
			'formats'           => 'open',
			'copies'            => 'open',
			'moreLikeThis'      => 'open',
			'otherEditions'     => 'closed',
			'prospector'        => 'closed',
			'links'             => 'closed',
			'tableOfContents'   => 'closed',
			'excerpt'           => 'closed',
			'authornotes'       => 'closed',
			'subjects'          => 'closed',
			'moreDetails'       => 'closed',
			'similarSeries'     => 'closed',
			'similarTitles'     => 'closed',
			'similarAuthors'    => 'closed',
			'borrowerReviews'   => 'closed',
			'librarianReviews'  => 'closed',
			'syndicatedReviews' => 'closed',
			'goodreadsReviews'  => 'closed',
			'tags'              => 'closed',
			'citations'         => 'closed',
			'copyDetails'       => 'closed',
			'staff'             => 'closed',
		);
	}

	public abstract function getItemActions($itemInfo);

	public abstract function getRecordActions($isAvailable, $isHoldable, $isBookable, $relatedUrls = null);

	public function hasOpacFieldMessage(){
		return false;
	}
}
