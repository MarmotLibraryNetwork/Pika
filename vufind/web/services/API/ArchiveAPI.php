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

/**
 * APIs related to Digital Archive functionality
 *
 * Migrated from the Islandora 1 (Fedora/MODS) stack to Islandora 2
 * (SearchObject_Islandora2 + Islandora2Driver). See
 * documentation/dpla-feed-islandora2-migration.md for the migration plan.
 *
 */

require_once ROOT_DIR . '/AJAXHandler.php';

class API_ArchiveAPI extends AJAXHandler {

	protected $methodsThatRespondWithJSONResultWrapper = [
		'getDPLAFeed',
		'getDPLACounts',
	];

	/**
	 * I2 stores relator roles as machine values on the 'relation' key, in the form
	 * "local:<3-letter code>" (e.g. 'local:pbl' for Publisher) — see
	 * Islandora2Driver::relatedTermLabels(). These are the same "local:" relator
	 * codes used elsewhere for relation comparisons (ArchiveObject.php 'local:sup',
	 * correspondenceSection.tpl 'local:pml'); 'ack' also matches the Acknowledgement
	 * code in ArchiveObject::PRODUCTION_TEAM_ROLES_RELATOR_CODES.
	 */
	private const RELATOR_CODE_PUBLISHER = 'local:pbl';

	private $organizationRolesToIncludeInDPLA = [
		'local:own', // Owner
		'local:dnr', // Donor
		'local:ack', // Acknowledgement
	];

	/**
	 * Returns a feed of content to be sent by DPLA after being processed by the state library.  May not return
	 * a full number of results due to filtering at the collection level.
	 *
	 * Future libraries may require different information.
	 */
	function getDPLAFeed(){
		$curPage      = $_REQUEST['page'] ?? 1;
		$pageSize     = $_REQUEST['pageSize'] ?? 100;
		$changesSince = $_REQUEST['changesSince'] ?? null;
		$namespace    = $_REQUEST['namespace'] ?? null;
		list($searchObject, $collectionsToInclude, $searchResult) = $this->getDPLASearchResults($namespace, $changesSince, $curPage, $pageSize);

		$dplaDocs = [];

		foreach ($searchResult['response']['docs'] as $doc){
			$dplaDoc = [];
			/** @var Islandora2Driver $record */
			$record   = RecordDriverFactory::initRecordDriver($doc);
			$nodeData = $record->getNodeData();
			if (null == $nodeData){
				// Skip objects whose Islandora2 node can't be fetched (e.g. unauthorized / missing)
				$this->logger->error('DPLA Feed: Failed to fetch Islandora2 node for node id ' . ($doc['its_node_id'] ?? 'unknown'));
				continue;
			}

			// Identifier: the archive migration to Islandora 2 breaks PID continuity, so the DPLA
			// identifier is now node-based. The legacy PID is retained separately for traceability.
			$dplaDoc['identifier'] = $record->getUniqueID(false);
			$legacyPid             = $doc['ss_legacy_pid'] ?? null;
			if (is_array($legacyPid)){
				$legacyPid = reset($legacyPid);
			}
			if (!empty($legacyPid)){
				$dplaDoc['legacyIdentifier'] = $legacyPid;
			}

			$dplaDoc['title']         = $record->getTitle();
			$dplaDoc['description']   = $record->getDescription();
			$dplaDoc['type']          = $record->getFormat();
			$dplaDoc['format']        = $this->mapFormat($record->getFormat());
			$dplaDoc['preview']       = $record->getBookcoverUrl('small');
			$dplaDoc['includeInDPLA'] = $doc['ss_field_pika_dpla'] ?? 'default';

			$dateCreated            = $record->getDateCreated('Y-m-d'); // Reformat back to YYYY-MM-DD
			$dplaDoc['dateCreated'] = !empty($dateCreated) ? $dateCreated : null;

			$language = $record->getLanguage();
			if (strlen($language)){
				$dplaDoc['language'] = $language;
			}

			$subTitle = $record->getSubTitle();
			if (strlen($subTitle) > 0){
				$dplaDoc['alternativeTitle'] = $subTitle;
			}

			// Extent (physical description of the digital object)
			// Drupal escapes commas as "\," in some text fields; restore plain commas.
			$extent = $nodeData['extent'] ?? null;
			if (!empty($extent)){
				$dplaDoc['extent'] = is_string($extent) ? str_replace('\\,', ',', $extent) : $extent;
			}

			// Creator
			$creators = $this->extractTermNames($nodeData['rights_creator'] ?? null);
			if (!empty($creators)){
				$dplaDoc['creator'] = $creators;
			}

			// Marmot Contributor
			$contributingLibrary = $record->getContributingLibraryInfo();
			global $configArray;
			if ($contributingLibrary == null || empty($contributingLibrary['baseUrl'])){
				// When the contributing Library (or its base URL) isn't available, we don't have an
				// ideal base URL — fall back to the site URL and the best available provider name.
				$dplaDoc['dataProvider'] = $contributingLibrary['libraryName'] ?? ($namespace ?: 'Marmot');
				$dplaDoc['isShownAt']    = $configArray['Site']['url'] . $record->getLinkUrl();
			}else{
				$dplaDoc['dataProvider'] = $contributingLibrary['libraryName'];
				$dplaDoc['isShownAt']    = $contributingLibrary['baseUrl'] . $record->getLinkUrl();
			}
			$contributingLibraryOrgTid = $contributingLibrary['orgTid'] ?? null;

			// Partner Contributors — related organizations with specific roles that aren't the library itself
			$institutionalContributors = [];
			foreach ($record->getRelatedOrganizations() as $organization){
				$role = $organization['role'] ?? '';
				if (empty($role) || !in_array($role, $this->organizationRolesToIncludeInDPLA)){
					continue;
				}
				// Exclude the contributing library's own organization
				if ($contributingLibraryOrgTid !== null && ($organization['tid'] ?? null) == $contributingLibraryOrgTid){
					continue;
				}
				if (!empty($organization['label'])){
					$institutionalContributors[] = $organization['label'];
				}
			}
			if (!empty($institutionalContributors)){
				// Institutional Contributors becomes the data Provider & the Marmot Contributor becomes the intermediate data provider
				$intermediateProvider            = $dplaDoc['dataProvider'];
				$dplaDoc['intermediateProvider'] = $intermediateProvider;
				$dplaDoc['dataProvider']         = count($institutionalContributors) == 1 ? $institutionalContributors[0] : $institutionalContributors;
			}

			// Related Collections
			$relatedCollections = $record->getRelatedCollections();
			$dplaRelations      = [];
			foreach ($relatedCollections as $relatedCollection){
				$dplaRelations[] = $relatedCollection['label'];
			}
			$dplaDoc['relation'] = $dplaRelations;

			// Rights.org statement (object field, with a Pika default when unset)
			$rightsStatement   = $record->getRightsStatement();
			$rightsStatement   = str_replace('?language=en', '', $rightsStatement); // Our DPLA hub requested removal of language parameter
			$dplaDoc['rights'] = $rightsStatement;

			// Rights holder
			$rightsHolders = $this->extractTermNames($nodeData['rights_holder'] ?? null);
			if (!empty($rightsHolders)){
				$dplaDoc['rightsHolder'] = $rightsHolders;
			}

			// Places
			$relatedPlaces     = $record->getRelatedPlaces();
			$dplaRelatedPlaces = [];
			foreach ($relatedPlaces as $relatedPlace){
				$dplaRelatedPlaces[] = $relatedPlace['label'];
			}
			if (count($dplaRelatedPlaces)){
				$dplaDoc['place'] = $dplaRelatedPlaces;
			}

			// Primary Subjects
			$subjects = $record->getSubjectLabels(); // DPLA does not want the title included as a subject
			// Marmot wants related Collections included in the subjects
			if (empty($subjects)){
				$subjects = $dplaRelations;
			}else{
				$subjects = array_merge($subjects, $dplaRelations);
			}

			// Add Persons that are Publishers & Related People as DPLA Subjects
			$publishers    = [];
			$relatedPeople = $record->getRelatedPeople();
			foreach ($relatedPeople as $relatedPerson){
				if (($relatedPerson['role'] ?? '') === self::RELATOR_CODE_PUBLISHER){
					$publishers[] = $relatedPerson['label'];
				} else {
					// Include related Entities as Subjects
					$subjects[] = $relatedPerson['label'];
				}
			}

			// Add organizations that are Publishers & related organizations as DPLA Subjects
			$relatedOrganizations = $record->getRelatedOrganizations();
			foreach ($relatedOrganizations as $relatedOrganization){
				if (($relatedOrganization['role'] ?? '') === self::RELATOR_CODE_PUBLISHER){
					$publishers[] = $relatedOrganization['label'];
				} else {
					// Include related Entities as Subjects
					$subjects[]  = $relatedOrganization['label'];
				}
			}
			if (count($publishers) > 0){
				$dplaDoc['publisher'] = $publishers;
			}

			// Events as DPLA subjects
			$relatedEvents = $record->getRelatedEvents();
			foreach ($relatedEvents as $relatedEvent){
				$subjects[] = $relatedEvent['label'];
			}

			$dplaDoc['subject'] = $subjects;

			$dplaDocs[] = $dplaDoc;
		}

		$recordsByLibrary = $this->extractRecordsByLibrary($searchResult);

		$summary = $searchObject->getResultSummary();
		$results = [
			'numResults'          => $summary['resultTotal'],
			'numPages'            => ceil($summary['resultTotal'] / $pageSize),
			'recordsByLibrary'    => $recordsByLibrary,
			'includedCollections' => $collectionsToInclude,
			'docs'                => $dplaDocs,
		];

		return $results;
	}

	private $formatMap = [
		'Academic Paper'  => 'Text',
		'Art'             => 'Image',
		'Article'         => 'Text',
		'Audio'           => 'Sound',
		'Book'            => 'Text',
		//'Collection' => '', //TODO: determine format
		//'Compound Object => '', //TODO: determine format
		'Document'        => 'Text',
		'Digital document' => 'Text',
		'Digital Document' => 'Text',
		'Image'           => 'Still Image',
		'Magazine'        => 'Text',
		'Music Recording' => 'Sound',
		'Newspaper'       => 'Text',
		'Page'            => 'Text',
		'Paged content'   => 'Text',
		'Paged Content'   => 'Text',
		'Postcard'        => 'Still Image',
		'Video'           => 'Moving Image',
		'Voice Recording' => 'Sound',
	];

	private function mapFormat($format){
		if (array_key_exists($format, $this->formatMap)){
			return $this->formatMap[$format];
		}else{
			$this->logger->error("Unknown format: $format");
			return 'Unknown';
		}
	}

	/**
	 * Extract taxonomy term name(s) from a node field that may hold a single term
	 * (associative array with a 'name'/'tid') or a list of such terms.
	 *
	 * @param mixed $raw
	 * @return string[]
	 */
	private function extractTermNames($raw): array{
		if (empty($raw)){
			return [];
		}
		// A single taxonomy term arrives as an associative array; multiple terms as a list.
		$items = (isset($raw['name']) || isset($raw['tid'])) ? [$raw] : (array)$raw;
		$names = [];
		foreach ($items as $item){
			if (is_array($item) && !empty($item['name'])){
				$names[] = $item['name'];
			}elseif (is_string($item) && $item !== ''){
				$names[] = $item;
			}
		}
		return $names;
	}

	/**
	 * Build the per-library record counts from the ss_library facet on a search result.
	 *
	 * @param array $searchResult
	 * @return array  [libraryName => count]
	 */
	private function extractRecordsByLibrary($searchResult): array{
		$recordsByLibrary = [];
		if (isset($searchResult['facet_counts'])){
			$libraryFacet = $searchResult['facet_counts']['facet_fields']['ss_library'] ?? [];
			foreach ($libraryFacet as $facetInfo){
				$recordsByLibrary[$facetInfo[0]] = $facetInfo[1];
			}
		}
		return $recordsByLibrary;
	}

	/**
	 * Run the two Solr queries backing the DPLA feed: first the collections that have
	 * opted into DPLA, then the objects eligible for export.
	 *
	 * DPLA eligibility is driven by the Islandora 2 field ss_field_pika_dpla
	 * (_none | no | yes | collection):
	 *   - yes        → always included
	 *   - collection → included when a parent collection is flagged yes
	 *   - no / _none → excluded
	 *
	 * itm_field_member_of holds the full ancestor node-ID chain, so a single
	 * membership filter covers nested collections.
	 *
	 * @param string|null $namespace     Contributing-library facet value (ss_library) to limit to
	 * @param string|null $changesSince   Only export records changed since this timestamp
	 * @param int         $curPage
	 * @param int         $pageSize
	 * @return array
	 */
	private function getDPLASearchResults($namespace, $changesSince, $curPage, $pageSize){
		// --- Query 1: collections that have opted into DPLA (ss_field_pika_dpla:yes) ---
		/** @var SearchObject_Islandora2 $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->setPrimarySearch(false);
		$searchObject->addHiddenFilter('ss_model', 'Collection');
		$searchObject->addHiddenFilter('ss_field_pika_dpla', 'yes');
		$searchObject->setPage(1);
		$searchObject->setLimit(1000);
		$searchCollectionsResult = $searchObject->processSearch(true, false);

		$collectionsToInclude = [];
		foreach ($searchCollectionsResult['response']['docs'] ?? [] as $doc){
			$nid = $doc['its_node_id'] ?? null;
			if ($nid !== null){
				$collectionsToInclude[] = $nid;
			}
		}

		// --- Query 2: objects to export ---
		/** @var SearchObject_Islandora2 $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->setPrimarySearch(true);

		if (!empty($collectionsToInclude)){
			$ancestors = 'itm_field_member_of:(' . implode(' OR ', $collectionsToInclude) . ')';
			$searchObject->addFilter("ss_field_pika_dpla:yes OR (ss_field_pika_dpla:collection AND ($ancestors))");
		}else{
			$searchObject->addFilter('ss_field_pika_dpla:yes');
		}

		if ($namespace != null){
			// Contributing-library facet value (replaces the Islandora 1 namespace_ms filter)
			$searchObject->addHiddenFilter('ss_library', $namespace);
		}

		// Filter to only records changed since the given timestamp
		if ($changesSince != null){
			$searchObject->addHiddenFilter('ds_changed', "[$changesSince TO *]");
		}

		$searchObject->addFieldsToReturn([
			'ss_field_pika_dpla',
			'ss_legacy_pid',
			'itm_field_member_of',
		]);
		$searchObject->setPage($curPage);
		$searchObject->setLimit($pageSize);
		$searchObject->clearFacets();
		$searchObject->addFacet('ss_library');
		$searchObject->setSort('ds_changed asc');

		$searchResult = $searchObject->processSearch(true, false);
		return [$searchObject, $collectionsToInclude, $searchResult];
	}

	public function getDPLACounts(){
		$curPage      = $_REQUEST['page'] ?? 1;
		$pageSize     = $_REQUEST['pageSize'] ?? 100;
		$changesSince = $_REQUEST['changesSince'] ?? null;
		$namespace    = $_REQUEST['namespace'] ?? null;
		list($searchObject, $collectionsToInclude, $searchResult) = $this->getDPLASearchResults($namespace, $changesSince, $curPage, $pageSize);

		return $this->extractRecordsByLibrary($searchResult);
	}
}
