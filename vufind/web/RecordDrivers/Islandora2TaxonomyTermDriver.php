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

require_once ROOT_DIR . '/RecordDrivers/Interface.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';
require_once ROOT_DIR . '/sys/Islandora2/TaxonomyFactory.php';

use Islandora2\TaxonomyFactory;
use Islandora2\TaxonomyObjectInterface;
use Pika\Logger;

/**
 * Record driver for Islandora 2 taxonomy terms.
 *
 * Taxonomy terms live in the same Solr index as the archive objects (nodes) and are
 * now returned alongside them by SearchObject_Islandora2, so search results are a mix
 * of two entity types. They carry a different set of Solr fields (its_tid /
 * tm_X3b_en_name / tm_X3b_en_description rather than its_node_id / title / description)
 * and have no media, model, or contributing library, so they get their own driver and
 * their own result template instead of being squeezed into Islandora2Driver.
 *
 * Everything this driver displays comes from the Solr document except the cover image:
 * Solr carries no thumbnail field for taxonomy terms, so that one value costs an API
 * fetch. See getBookcoverUrl() for how that call is kept off the hot path.
 *
 * @category Pika
 */
class Islandora2TaxonomyTermDriver extends RecordInterface {

	/** Shown when a term has no thumbnail of its own; matches Islandora2Driver. */
	private const PLACEHOLDER_IMAGE = '/interface/themes/responsive/images/History.png';

	private Logger $logger;
	private int $tid = 0;
	private ?string $title = null;
	private ?string $description = null;
	private ?string $vocabulary = null;
	protected ?float $solrScore = null;
	protected ?string $solrExplanation = null;

	/** Populated on demand by ensureTaxonomyObject(); only the cover image needs it. */
	private ?TaxonomyObjectInterface $taxonomyObject = null;
	private bool $taxonomyObjectLoaded = false;

	/**
	 * Map of friendly names to the Solr fields on a taxonomy term document.
	 * Mirrors the taxonomy entries in SearchObject_Islandora2::$fields.
	 */
	private array $solrFields = [
		'id'          => 'its_tid',
		'title'       => 'tm_X3b_en_name',
		'description' => 'tm_X3b_en_description',
		'vocabulary'  => 'ss_vid',
	];

	/**
	 * @param array|int|string $recordData A taxonomy term Solr document, a bare term id, or a
	 *                                     stored user list entry id (tax_{vocabulary}:{tid})
	 */
	public function __construct($recordData){
		$this->logger = new Logger(__CLASS__);

		if (is_string($recordData) && !is_numeric($recordData)){
			// A user list entry id. It carries the vocabulary, so a term reached from a list
			// can build its own URL without an Islandora lookup.
			$parsed = parseUserListEntryId($recordData);
			if ($parsed['type'] === USER_LIST_ENTRY_TAXONOMY_TERM){
				$this->tid        = (int)$parsed['id'];
				$this->vocabulary = $parsed['vocabulary'];
			}
			$recordData = $this->tid;
		}

		if (is_array($recordData)){
			$this->tid             = (int)($this->getSolrFirstFieldValue($recordData, 'id') ?? 0);
			$title                 = $this->getSolrFirstFieldValue($recordData, 'title');
			$this->title           = !empty($title) ? $title : null;
			$description           = $this->getSolrFirstFieldValue($recordData, 'description');
			$this->description     = !empty($description) ? $description : null;
			$vocabulary            = $this->getSolrFirstFieldValue($recordData, 'vocabulary');
			$this->vocabulary      = !empty($vocabulary) ? strtolower((string)$vocabulary) : null;
			$this->solrScore       = isset($recordData['score']) ? (float)$recordData['score'] : null;
			$this->solrExplanation = isset($recordData['explain']) ? (string)$recordData['explain'] : null;
		}elseif (is_numeric($recordData)){
			// No Solr document to read from; the term id is all we have. getTitle() and
			// getDescription() fall back to a TaxonomyFactory lookup when something asks for
			// them, so nothing is fetched unless it is needed.
			$this->tid = (int)$recordData;
		}

		if ($this->tid <= 0){
			$this->logger->warning('Islandora2TaxonomyTermDriver initialized without a valid term id.', ['recordData' => $recordData]);
		}
	}

	/**
	 * Extract a Solr field value by friendly name, returning the first element when the
	 * field is multi-valued (the tm_* taxonomy fields always are).
	 *
	 * @param array  $solrDoc
	 * @param string $field
	 * @return mixed
	 */
	private function getSolrFirstFieldValue(array $solrDoc, string $field){
		$value = $solrDoc[$this->solrFields[$field]] ?? null;
		return is_array($value) ? ($value[0] ?? null) : $value;
	}

	public function getTid(): int{
		return $this->tid;
	}

	/**
	 * The vocabulary machine name (person, geo_location, corporate_body, event), which
	 * determines which Archive2 display page the term links to.
	 *
	 * @return string|null
	 */
	public function getVocabulary(): ?string{
		return $this->vocabulary;
	}

	/**
	 * The singular display label for the term's vocabulary — Person, Place, Organization
	 * or Event — as shown in the Taxonomy row of a search result.
	 *
	 * @return string
	 */
	public function getVocabularyLabel(): string{
		return getTaxonomyVocabularyLabel($this->vocabulary);
	}

	/**
	 * A term has no format, so its vocabulary label stands in for one.
	 *
	 * RecordInterface does not declare getFormat(), but SearchObject_Islandora2::getResultRecordSet()
	 * calls it on every document it returns, so a term reaching the Excel export or the email a
	 * list action fataled without it. This is the same value MyAccount_MyList::exportToExcel()
	 * puts in the Material Type column and the archive search spreadsheet puts in its format
	 * column, so a term reads the same wherever it is exported.
	 *
	 * @return string  Person, Place, Organization or Event.
	 */
	public function getFormat(): string{
		return $this->getVocabularyLabel();
	}

	public function getTitle(){
		// When constructed from a Solr document the name is already available without an API
		// call — use it directly to avoid triggering ensureTaxonomyObject(). A term reached
		// from a user list entry has only its id and vocabulary, so it has to ask Islandora,
		// exactly as Islandora2Driver::getTitle() does for an object.
		if (!empty($this->title)){
			return $this->title;
		}

		$term = $this->ensureTaxonomyObject();
		if ($term !== null){
			$title = $term->getTitle();
			if (!empty($title)){
				$this->title = $title; // resolved once per request
				return $this->title;
			}
		}

		return $this->tid > 0 ? 'Islandora Term ' . $this->tid : '';
	}

	public function getDescription(){
		if (!empty($this->description)){
			return $this->description;
		}

		$term = $this->ensureTaxonomyObject();
		if ($term !== null){
			$description = $term->getDescription();
			if (!empty($description)){
				$this->description = $description;
				return $this->description;
			}
		}

		return '';
	}

	public function getUniqueID(){
		// Dashes rather than colons so the id is safe to use in the HTML DOM and in jQuery
		// selectors. The vocabulary is carried along because this id comes back from the list
		// edit, delete and reorder requests and has to be turned back into the stored
		// tax_{vocabulary}:{tid} form; the tid alone would not be enough to rebuild it.
		// Vocabulary machine names use underscores, never dashes, so the final dash always
		// separates the vocabulary from the term id. See userListEntryIdFromDomId().
		if ($this->tid <= 0){
			return 'islandora2-term-unknown';
		}
		return 'islandora2-term-' . ($this->vocabulary ?? 'term') . '-' . $this->tid;
	}

	/**
	 * The id this term is stored under in user_list_entry.groupedWorkPermanentId.
	 *
	 * @return string
	 */
	public function getUserListEntryId(): string{
		return buildTaxonomyUserListEntryId($this->tid, $this->vocabulary);
	}

	/**
	 * Assign the Smarty variables for a taxonomy term search result and return its template.
	 *
	 * Terms get their own template rather than sharing RecordDrivers/Islandora/result.tpl:
	 * they have no format, model, or contributing library to show.  In covers view they do
	 * share the object tile, so a page of results reads as one grid - see getBrowseResult().
	 *
	 * @param string $view The view style for this search entry
	 * @return string
	 */
	public function getSearchResult(string $view = 'list'){
		if ($view === 'covers'){
			return $this->getBrowseResult();
		}

		global $interface;

		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('jquerySafeId', $this->getUniqueID());
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summDescription', $this->getDescription());
		// getLinkUrl() rather than getRecordUrl(): it carries the searchId / recordIndex / page
		// parameters the term page needs to show the previous & next result navigation.
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summVocabularyLabel', $this->getVocabularyLabel());

		// Resolving the cover costs an API call (see getBookcoverUrl), so ask for it only
		// when the template will actually paint it. Both flags are page-wide template vars
		// - showCovers is the patron's results toggle (Action.php), disableCoverArt their
		// account setting (index.php) - and the template's own {if $showCovers} block mirrors
		// this test, so leaving the URL unassigned when covers are off renders the same page
		// it would have anyway, just without the round trips.
		$showCovers = $interface->get_template_vars('showCovers') && $interface->get_template_vars('disableCoverArt') != 1;
		$interface->assign('bookCoverUrlMedium', $showCovers ? $this->getBookcoverUrl('medium') : '');

		global $configArray;
		if (!empty($configArray['System']['debugSolr'])){
			$interface->assign('summScore', $this->solrScore);
			$interface->assign('summExplain', $this->getExplain());
		}

		return 'RecordDrivers/Islandora/taxonomyTermResult.tpl';
	}

	/**
	 * Provide a browse tile result, the covers view of a search result.
	 *
	 * Deliberately the same tile Islandora2Driver::getBrowseResult() builds, down to the
	 * template: covers view mixes terms and archive objects in one grid of thumbnails, and a
	 * term rendered any other way breaks the grid it sits in.  The tile captions itself with
	 * the term name, since an archive thumbnail rarely carries its own title and is often the
	 * placeholder image.
	 *
	 * Unlike getSearchResult(), this asks for the cover without first checking the patron's
	 * show-covers setting - the thumbnail is the substance of a tile, and the object tiles
	 * beside it make the same call.
	 *
	 * @return string
	 */
	public function getBrowseResult(){
		global $interface;

		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('summTitle', $this->getTitle());
		// getLinkUrl() rather than getRecordUrl(): it carries the searchId / recordIndex / page
		// parameters the term page needs to show the previous & next result navigation.
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('medium'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		return 'RecordDrivers/Islandora/browse_result_archive2.tpl';
	}

	/**
	 * Taxonomy terms render the same way in the combined (Union) results as they do in a
	 * plain archive search, so both views share one template.
	 *
	 * @param string $view The view style for this search entry
	 * @return string
	 */
	public function getCombinedResult($view = 'list'){
		return $this->getSearchResult($view);
	}

	/**
	 * Format the raw Solr score explanation for display.
	 * Same treatment Islandora2Driver gives object explanations.
	 *
	 * @return string
	 */
	public function getExplain(){
		if (!empty($this->solrExplanation)){
			$explain = explode(', result of:', $this->solrExplanation, 2);
			if (count($explain) > 1){
				$explain[1] = preg_replace('/weight\((.*):(.*)( in \d+\))/i', 'weight(<code>$1</code>:<strong>$2</strong>$3)', $explain[1]);
				$explain[1] = preg_replace('/computed as (.*) from:/i', 'computed as <var>$1</var> from:', $explain[1]);
				$explain[0] = preg_replace('/weight\((.*):(.*)( in \d+\))/i', 'weight(<code>$1</code>:<strong>$2</strong>$3)', $explain[0]);

				return $explain[0] . '<br> result of : <p>' . nl2br(str_replace(' ', '&nbsp;', $explain[1])) . '</p>';
			}
		}
		return '';
	}

	public function getBreadcrumb(){
		return $this->getTitle();
	}

	public function getModule(){
		return 'Archive2';
	}

	/**
	 * The term's display page, e.g. /Archive2/Place/12345.
	 *
	 * The vocabulary (ss_vid) picks the typed Archive2 action; terms indexed without one
	 * fall back to /Archive2/Term, which looks the term up and redirects.
	 *
	 * @return string
	 */
	public function getRecordUrl(){
		return getTaxonomyRelativeUrlFromParts($this->tid, $this->vocabulary);
	}

	public function getAbsoluteUrl(){
		return getTaxonomyAbsoluteUrlFromParts($this->tid, $this->vocabulary);
	}

	/**
	 * SearchObject_Islandora2::getNextPrevLinks() locates a record by its position in the
	 * whole result set, so the link out of the search results has to carry resultIndex; the
	 * default recordIndex is only the position within the current page of results, which
	 * would make every record on page two navigate as though it were on page one.
	 *
	 * @return string
	 */
	protected function getSearchPositionVariable(){
		return 'resultIndex';
	}

	/**
	 * Lazily fetch the full taxonomy term from the Islandora API.
	 *
	 * Nothing on a search result needs this except the cover image, so it stays behind a
	 * flag rather than being loaded in the constructor: a term driver built for its title,
	 * description or URL never makes an HTTP call. Mirrors Islandora2Driver::ensureI2Object().
	 *
	 * The null result is cached in $taxonomyObjectLoaded as well, so a term the API cannot
	 * resolve is not retried once per accessor within a single request.
	 *
	 * @return TaxonomyObjectInterface|null
	 */
	private function ensureTaxonomyObject(): ?TaxonomyObjectInterface{
		if ($this->taxonomyObjectLoaded){
			return $this->taxonomyObject;
		}
		$this->taxonomyObjectLoaded = true;

		if ($this->tid <= 0){
			$this->logger->warning('Cannot load Islandora2 taxonomy term without a valid term id.');
			return null;
		}

		$factory              = new TaxonomyFactory();
		$this->taxonomyObject = $factory->fromTid($this->tid);

		if ($this->taxonomyObject === null){
			$this->logger->warning('Failed to load Islandora2 taxonomy term.', ['tid' => $this->tid]);
		}

		return $this->taxonomyObject;
	}

	/**
	 * The term's thumbnail, or the placeholder image when it has none.
	 *
	 * This is the one value on a term result that the search index cannot supply, and it
	 * is the expensive one. Solr has no thumbnail field for taxonomy terms, so the URL can
	 * only come from /pika-json/taxonomy/{tid} - one HTTP round trip per term shown. Three
	 * things keep that off the hot path:
	 *
	 *   1. The fetch is lazy (ensureTaxonomyObject), so it only happens when something
	 *      actually asks for a cover.
	 *   2. getSearchResult() asks only when the cover will really be rendered - it checks
	 *      the patron's show-covers toggle and the disableCoverArt account setting first,
	 *      so a covers-off results page costs zero API calls.
	 *   3. Request::fetch() stores the response in memcache under islandora2_taxonomy_{tid},
	 *      so a term costs at most one round trip per cache lifetime however many results
	 *      pages it turns up on. Warm cache is the normal case; a cold cache on a page of
	 *      24 term results is the worst case worth watching.
	 *
	 * $size is accepted for interface compatibility but ignored: the API exposes a single
	 * thumbnail derivative per term, with no small/medium/large variants.
	 *
	 * @param string $size Ignored; terms have only one thumbnail size.
	 * @return string
	 */
	public function getBookcoverUrl($size = 'small'){
		$term = $this->ensureTaxonomyObject();
		if ($term === null){
			return self::PLACEHOLDER_IMAGE;
		}

		$thumbnail = $term->getThumbnail();
		return !empty($thumbnail['url']) ? $thumbnail['url'] : self::PLACEHOLDER_IMAGE;
	}

	/**
	 * Assign the Smarty variables for this term as an entry in a user list.
	 *
	 * Shares RecordDrivers/Islandora/listentry.tpl with the archive objects rather than
	 * taking a template of its own: a list row is mostly the cover, title, description and
	 * the edit/delete tools, all of which a term has. The fields a term has no answer for
	 * (format, author, publisher) are assigned empty and the template skips their rows; a term
	 * shows its vocabulary where an object shows its format.
	 *
	 * @param int|null $listId    The list being displayed, when notes should be shown.
	 * @param bool     $allowEdit Should the edit & delete controls be rendered?
	 * @return string             Template to render.
	 */
	public function getListEntry($listId = null, $allowEdit = true){
		global $interface;

		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('jquerySafeId', $this->getUniqueID());
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summVocabularyLabel', $this->getVocabularyLabel());
		// A term has no format, author or publisher; listentry.tpl skips each row when the
		// variable is empty, so these are assigned null rather than left over from the
		// previously rendered entry in the list.
		$interface->assign('summFormat', null);
		$interface->assign('summAuthor', null);
		$interface->assign('summPublisher', null);
		$interface->assign('summPubDate', null);
		// Resolving a term's cover costs an Islandora call (see getBookcoverUrl), and a page of
		// list entries would make one per row. listentry.tpl paints the thumbnail only when both
		// of these are set, so when they are not there is nothing to pay for. getSearchResult()
		// guards the same way.
		$showCovers = $interface->get_template_vars('showCovers') && $interface->get_template_vars('disableCoverArt') != 1;
		$interface->assign('bookCoverUrl', $showCovers ? $this->getBookcoverUrl('small') : '');
		$interface->assign('bookCoverUrlMedium', $showCovers ? $this->getBookcoverUrl('medium') : '');
		$interface->assign('summAjaxStatus', false);
		$interface->assign('recordDriver', $this);

		if ($listId){
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry                         = new UserListEntry();
			$listEntry->groupedWorkPermanentId = $this->getUserListEntryId();
			$listEntry->listId                 = $listId;
			// Always assign, even when there is no entry: Smarty variables persist between the
			// rows of a list, so an unassigned value would show the previous row's notes.
			$interface->assign('listEntryNotes', $listEntry->find(true) ? $listEntry->notes : '');
			$interface->assign('listEditAllowed', $allowEdit);
		}

		return 'RecordDrivers/Islandora/listentry.tpl';
	}

	public function getStaffView(){
		return null;
	}

	public function getSemanticData(){
		return [];
	}

	public function getCitation($format){
		return null;
	}

	public function getCitationFormats(){
		return [];
	}

	public function getExport($format){
		return null;
	}

	public function getExportFormats(){
		return [];
	}

	public function getRDFXML(){
		return null;
	}

	public function hasRDF(){
		return false;
	}

	public function hasFullText(){
		return false;
	}

	public function getTOC(){
		return [];
	}

	public function getMoreDetailsOptions(){
		return [];
	}

	public function getItemActions($itemInfo){
		return [];
	}

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $isHomePickupRecord, $isExternalReservationItem = false, $relatedUrls = null){
		return [];
	}

}