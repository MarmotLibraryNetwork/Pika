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
 * This driver is deliberately Solr-only: unlike Islandora2Driver it never falls back to
 * an API fetch, because everything it currently displays (name and description) is in
 * the Solr document already.
 *
 * @category Pika
 */
class Islandora2TaxonomyTermDriver extends RecordInterface {

	private Logger $logger;
	private int $tid = 0;
	private ?string $title = null;
	private ?string $description = null;
	protected ?float $solrScore = null;
	protected ?string $solrExplanation = null;

	/**
	 * Map of friendly names to the Solr fields on a taxonomy term document.
	 * Mirrors the taxonomy entries in SearchObject_Islandora2::$fields.
	 */
	private array $solrFields = [
		'id'          => 'its_tid',
		'title'       => 'tm_X3b_en_name',
		'description' => 'tm_X3b_en_description',
	];

	/**
	 * @param array|int|string $recordData A taxonomy term Solr document, or a bare term id
	 */
	public function __construct($recordData){
		$this->logger = new Logger(__CLASS__);

		if (is_array($recordData)){
			$this->tid             = (int)($this->getSolrFirstFieldValue($recordData, 'id') ?? 0);
			$title                 = $this->getSolrFirstFieldValue($recordData, 'title');
			$this->title           = !empty($title) ? $title : null;
			$description           = $this->getSolrFirstFieldValue($recordData, 'description');
			$this->description     = !empty($description) ? $description : null;
			$this->solrScore       = isset($recordData['score']) ? (float)$recordData['score'] : null;
			$this->solrExplanation = isset($recordData['explain']) ? (string)$recordData['explain'] : null;
		}elseif (is_numeric($recordData)){
			// No Solr document to read from; the term id is all we have. Name and description
			// stay empty until this driver grows a TaxonomyFactory lookup.
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

	public function getTitle(){
		return $this->title ?? ($this->tid > 0 ? 'Islandora Term ' . $this->tid : '');
	}

	public function getDescription(){
		return $this->description ?? '';
	}

	public function getUniqueID(){
		// Dash rather than colon so the id is safe to use in the HTML DOM and in jQuery selectors
		return $this->tid > 0 ? 'islandora2-term-' . $this->tid : 'islandora2-term-unknown';
	}

	/**
	 * Assign the Smarty variables for a taxonomy term search result and return its template.
	 *
	 * Terms get their own template rather than sharing RecordDrivers/Islandora/result.tpl:
	 * they have no cover, format, model, or contributing library to show.
	 *
	 * @param string $view The view style for this search entry
	 * @return string
	 */
	public function getSearchResult(string $view = 'list'){
		global $interface;

		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('jquerySafeId', $this->getUniqueID());
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summDescription', $this->getDescription());

		global $configArray;
		if (!empty($configArray['System']['debugSolr'])){
			$interface->assign('summScore', $this->solrScore);
			$interface->assign('summExplain', $this->getExplain());
		}

		return 'RecordDrivers/Islandora/taxonomyTermResult.tpl';
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

	//TODO: taxonomy terms have no landing page in Pika yet. Once one exists, return its
	// path here and turn the title in taxonomyTermResult.tpl back into a link.
	public function getRecordUrl(){
		return '#';
	}

	public function getAbsoluteUrl(){
		return '#';
	}

	//TODO: no thumbnail for terms yet; the result template does not display a cover.
	public function getBookcoverUrl($size = 'small'){
		return '';
	}

	public function getListEntry($listId = null, $allowEdit = true){
		return null;
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