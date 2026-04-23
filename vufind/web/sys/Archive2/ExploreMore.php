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

namespace Archive2;

use Islandora2\I2Object;
use Pika\Logger;

require_once ROOT_DIR . '/RecordDrivers/Islandora2Driver.php';
require_once ROOT_DIR . '/sys/SearchObject/Islandora2.php';

/**
 * Builds the Explore More sidebar data for an Islandora2 object page.
 *
 * Each public section method returns a section array suitable for the
 * explore-more-sidebar.tpl template:
 *   ['format' => 'list'|'textOnlyList'|'scroller', 'values' => [...]]
 *
 * Call loadSidebar() to get the full $exploreMoreSections map, then assign
 * it to Smarty alongside SECTIONS (display names) and buildSettings() for the
 * section ordering/open-by-default config.
 */
class ExploreMore {

	/** Section keys and their default display names. Order determines sidebar order. */
	const SECTIONS = [
		// TODO: 'parentBook'           => 'Entire Book',        // For page objects that belong to a book/paged-content parent
		// TODO: 'tableOfContents'      => 'Table of Contents',  // For book/paged-content objects; requires child-page enumeration
		'relatedCollections'   => 'Related Archive Collections',
		'linkedCatalogRecords' => 'Librarian Picks',    // Catalog items manually linked to this archive object, or parent collection
		// TODO: 'exactEntityMatches'   => 'Related People, Places &amp; Events', // FAST/entity-linked matches (Islandora1 feature)
		'relatedPeople'        => 'Associated People',
		'relatedOrganizations' => 'Associated Organizations',
		'relatedPlaces'        => 'Associated Places',
		'relatedEvents'        => 'Associated Events',
		'relatedArchiveData'    => 'From the Archive',
		'relatedCatalog'        => 'More From the Catalog', // Catalog Solr search by subject terms
		'relatedSubjects'       => 'Related Subjects',
		// TODO: 'dpla'                 => 'Digital Public Library of America', // DPLA API results // Only shown for Marmot Entities
		// TODO: 'acknowledgements'     => 'Acknowledgements',   // Donor/funder branding
	];

	private Logger $logger;

	public function __construct() {
		$this->logger = new Logger(__CLASS__);
	}

	/**
	 * Build all sidebar sections for the given Islandora2 object.
	 *
	 * Returns the $exploreMoreSections array keyed by section ID.
	 * Only sections that have at least one item are included.
	 *
	 * @param I2Object $obj
	 * @return array
	 */
	public function loadExploreMoreSidebar(I2Object $obj): array {
		global $timer;

		$sections = [];

		$section = $this->getRelatedCollections($obj);
		if ($section) $sections['relatedCollections'] = $section;
		$timer->logTime('ExploreMore: relatedCollections');

		[$people, $orgs] = $this->getLinkedAgents($obj);
		if ($people)  $sections['relatedPeople']        = $people;
		if ($orgs)    $sections['relatedOrganizations'] = $orgs;
		$timer->logTime('ExploreMore: linked agents');

		$section = $this->getRelatedPlaces($obj);
		if ($section) $sections['relatedPlaces'] = $section;

		$section = $this->getRelatedEvents($obj);
		if ($section) $sections['relatedEvents'] = $section;
		$timer->logTime('ExploreMore: places and events');

		$section = $this->getRelatedArchiveData($obj);
		if ($section) $sections['relatedArchiveData'] = $section;
		$timer->logTime('ExploreMore: relatedArchiveData (Solr)');

		// Build a shared driver so getLinkedCatalogRecords and getRelatedCatalog
		// reuse the same memoized getRelatedPikaWorks() result.
		$i2Driver = null;
		$nid      = $obj->getNodeId();
		if ($nid) {
			$i2Driver = new \Islandora2Driver($nid);
		}

		$section = $this->getLinkedCatalogRecords($obj, $i2Driver);
		if ($section) $sections['linkedCatalogRecords'] = $section;
		$timer->logTime('ExploreMore: linkedCatalogRecords (Pika works)');

		$section = $this->getRelatedCatalog($obj, $i2Driver);
		if ($section) $sections['relatedCatalog'] = $section;
		$timer->logTime('ExploreMore: relatedCatalog (catalog Solr)');

		$section = $this->getRelatedSubjects($obj);
		if ($section) $sections['relatedSubjects'] = $section;

		return $sections;
	}

	/**
	 * Build the $exploreMoreSettings array expected by explore-more-sidebar.tpl.
	 *
	 * Returns an ordered array of stdClass objects with section, openByDefault,
	 * and displayName properties matching the ArchiveExploreMoreBar DB model shape.
	 *
	 * @return stdClass[]
	 */
	public static function buildSettings(): array {
		$settings = [];
		foreach (self::SECTIONS as $key => $label) {
			$s                = new \stdClass();
			$s->section       = $key;
			$s->openByDefault = true;
			$s->displayName   = '';   // empty → template uses default from $archiveSections
			$settings[]       = $s;
		}
		return $settings;
	}

	// -------------------------------------------------------------------------
	// Explore More Bar
	// -------------------------------------------------------------------------

	/**
	 * Build explore-more-bar tiles from the Islandora2 archive for a keyword search.
	 *
	 * Searches Islandora2 Solr for $searchTerm, facets by genre (sm_name_2), and
	 * appends one tile per content type to $exploreMoreOptions.
	 *
	 * @param array  $exploreMoreOptions  Existing bar tiles to append to
	 * @param string $searchTerm
	 * @return array
	 */
	public function loadArchiveOptions(array $exploreMoreOptions, string $searchTerm): array {
		if (empty($searchTerm)) {
			return $exploreMoreOptions;
		}

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchTerms = [
			'lookfor' => $searchTerm,
			'index'   => 'Islandora2Keyword',
		];
		$searchObject->setSearchTerms($searchTerms);

		$formatFacetField = 'sm_format'; // Hasn't been reverted yet.
		$collectionFacetField = 'sm_title_2';
		$searchObject->addFacet($formatFacetField, 'Format'); // Related Formats
		$searchObject->addFacet($collectionFacetField, 'Collection'); // Related Collections
		$searchObject->setLimit(1);
		$searchObject->setDebugging(false, false);

		$response = $searchObject->processSearch(true, false);
		if (empty($response['response']['numFound'])) {
			return $exploreMoreOptions;
		}

		$formatFacetResults = $response['facet_counts']['facet_fields'][$formatFacetField] ?? [];
		foreach ($formatFacetResults as [$format, $count]) {
			if ($count == 0) {
				continue;
			}

			/** @var \SearchObject_Islandora2 $formatSearch */
			$formatSearch = \SearchObjectFactory::initSearchObject('Islandora2');
			$formatSearch->init();
			$formatSearch->setSearchTerms($searchTerms);
			$formatSearch->addFilter("$formatFacetField:\"$format\"");
			$formatSearch->setLimit(1);

			$formatResponse = $formatSearch->processSearch(true, false);
			if (empty($formatResponse['response']['docs'])) {
				continue;
			}

			$firstDriver = \RecordDriverFactory::initRecordDriver(reset($formatResponse['response']['docs']));
			$label       = ucwords($format);

			if ($count == 1) {
				$exploreMoreOptions[] = [
					'label'       => $label,
					'description' => "$label related to $searchTerm",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $firstDriver->getRecordUrl(),
				];
			} else {
				$exploreMoreOptions[] = [
					'label'       => "$label ($count)",
					'description' => "$label related to $searchTerm",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $formatSearch->renderSearchUrl(),
					'usageCount'  => $count,
				];
			}
		}

		// Related collections
		$collectionFacetResults = $response['facet_counts']['facet_fields'][$collectionFacetField] ?? [];
		foreach ($collectionFacetResults as [$collection, $count]) {
			if ($count == 0) {
				continue;
			}

			/** @var \SearchObject_Islandora2 $collectionSearch */
			$collectionSearch = \SearchObjectFactory::initSearchObject('Islandora2');
			$collectionSearch->init();
			$collectionSearch->setSearchTerms($searchTerms);
			$collectionSearch->addFilter("$collectionFacetField:\"$collection\"");
			$collectionSearch->setLimit(1);

			$collectionResponse = $collectionSearch->processSearch(true, false);
			if (empty($collectionResponse['response']['docs'])) {
				continue;
			}

			$firstDriver = \RecordDriverFactory::initRecordDriver(reset($collectionResponse['response']['docs']));

			if ($count == 1) {
				$exploreMoreOptions[] = [
					'label'       => $collection,
					'description' => "$collection related to $searchTerm",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $firstDriver->getRecordUrl(),
				];
			} else {
				$exploreMoreOptions[] = [
					'label'       => "$collection ($count)",
					'description' => "$collection related to $searchTerm",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $collectionSearch->renderSearchUrl(),
					'usageCount'  => $count,
				];
			}
		}

		//TODO: Related People
		//TODO: Related Places
		//TODO: Related Events

		return $exploreMoreOptions;
	}

	// -------------------------------------------------------------------------
	// Section builders
	// -------------------------------------------------------------------------

	/**
	 * Collections this object belongs to (from field_member_of node IDs).
	 *
	 * @param I2Object $obj
	 * @return array|null
	 */
	private function getRelatedCollections(I2Object $obj): ?array {
		//TODO: children of compound objects report parent node id, rather than parent collection node Id
		// Solr field itm_field_member_of does return the chain of members
		$memberOf = $obj->member_of;
		if (empty($memberOf)) {
			return null;
		}

		// Normalize: member_of may be a single int or an array of ints/arrays
		if (!is_array($memberOf)) {
			$memberOf = [$memberOf];
		}

		$values = [];
		foreach ($memberOf as $entry) {
			// The field value may be a bare node ID or an array with an 'id' key
			$nid = is_array($entry) ? ($entry['id'] ?? ($entry['nid'] ?? null)) : $entry;
			if (!is_numeric($nid)) {
				continue;
			}
			$nid    = (int)$nid;
			$driver = new \Islandora2Driver($nid);
			$title  = $driver->getTitle();
			if (empty($title)) {
				continue;
			}
			$values[] = [
				'label' => $title,
				'link'  => $driver->getRecordUrl(),
				'image' => $driver->getBookcoverUrl('small'),
			];
		}

		if (empty($values)) {
			return null;
		}

		return ['format' => 'list', 'values' => $values];
	}

	/**
	 * Split linked_agent into people and organizations.
	 *
	 * Returns [peopleSection|null, orgsSection|null].
	 *
	 * @param I2Object $obj
	 * @return array  Two-element array [people, orgs]
	 */
	private function getLinkedAgents(I2Object $obj): array {
		$linkedAgents = $obj->linked_agent;
		if (empty($linkedAgents)) {
			return [null, null];
		}

		if (!is_array($linkedAgents)) {
			return [null, null];
		}

		// Normalize single-agent (non-indexed) arrays to a list
		if (isset($linkedAgents['name'])) {
			$linkedAgents = [$linkedAgents];
		}

		$people = [];
		$orgs   = [];

		foreach ($linkedAgents as $agent) {
			$name = $agent['name'] ?? null;
			if (empty($name)) {
				continue;
			}
			// 'rel' or 'role' field indicates agent type
			$rel  = strtolower($agent['rel'] ?? ($agent['role'] ?? ''));
			$encodedName = urlencode('"' . $name . '"');

			// Heuristic: corporate/organization roles contain 'corporate' or 'org'
			if (str_contains($rel, 'corporate') || str_contains($rel, 'org')) {
				$orgs[] = [
					'label' => $name,
					'link'  => "/Archive2/Results?filter[]=sm_related_organization:$encodedName",
				];
			} else {
				$people[] = [
					'label' => $name,
					'link'  => "/Archive2/Results?filter[]=sm_related_person:$encodedName",
				];
			}
		}

		$peopleSection = empty($people) ? null : ['format' => 'textOnlyList', 'values' => $people];
		$orgsSection   = empty($orgs)   ? null : ['format' => 'textOnlyList', 'values' => $orgs];

		return [$peopleSection, $orgsSection];
	}

	/**
	 * Places related to this object (field_related_place).
	 *
	 * @param I2Object $obj
	 * @return array|null
	 */
	private function getRelatedPlaces(I2Object $obj): ?array {
		return $this->buildTaxonomySection($obj->related_place, 'sm_related_place');
	}

	/**
	 * Events related to this object (field_related_event).
	 *
	 * @param I2Object $obj
	 * @return array|null
	 */
	private function getRelatedEvents(I2Object $obj): ?array {
		return $this->buildTaxonomySection($obj->related_event, 'sm_related_event');
	}

	/**
	 * Subject terms linking to Archive2 subject searches.
	 *
	 * @param I2Object $obj
	 * @return array|null
	 */
	private function getRelatedSubjects(I2Object $obj): ?array {
		$subjects = $obj->getSubjects();
		if (empty($subjects)) {
			return null;
		}
		// Normalize single subject
		if (isset($subjects['name'])) {
			$subjects = [$subjects];
		}
		$values = [];
		foreach ($subjects as $subject) {
			$name = $subject['name'] ?? null;
			if (empty($name)) {
				continue;
			}
			$values[] = [
				'label' => $name,
				'link'  => '/Archive2/Results?filter[]=' . urlencode('sm_field_subject:"' . $name . '"'),
			];
		}
		return empty($values) ? null : ['format' => 'textOnlyList', 'values' => $values];
	}

	/**
	 * Solr search for archive objects sharing subjects or genre with this object.
	 * Returns up to 12 results as a scroller.
	 *
	 * @param I2Object $obj
	 * @return array|null
	 */
	private function getRelatedArchiveData(I2Object $obj): ?array {
		$subjects = $obj->getSubjects();
		$nid      = $obj->getNodeId();

		// Build query terms from up to 3 subject names
		$terms = [];
		if (!empty($subjects)) {
			$list = isset($subjects['name']) ? [$subjects] : $subjects;
			foreach (array_slice($list, 0, 3) as $subject) {
				if (!empty($subject['name'])) {
					$terms[] = '"' . addslashes($subject['name']) . '"';
				}
			}
		}

		// Fall back to genre if no subjects available
		if (empty($terms)) {
			$genre = $obj->genre;
			if (!empty($genre['name'])) {
				$terms[] = '"' . addslashes($genre['name']) . '"';
			}
		}

		if (empty($terms)) {
			return null;
		}

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		//$searchObject->setDebugging(false, false);
		//TODO: turn off debugging later
		$searchObject->setSearchTerms(['lookfor' => implode(' OR ', $terms)]);
		$searchObject->setLimit(12);

		// Exclude the current object
		if ($nid) {
			$searchObject->addFilter("!its_node_id:$nid");
		}

		$response = $searchObject->processSearch(true, false);
		if (empty($response['response']['docs'])) {
			return null;
		}

		$values = [];
		foreach ($response['response']['docs'] as $doc) {
			$driver = \RecordDriverFactory::initRecordDriver($doc);
			$title  = $driver->getTitle();
			if (empty($title)) {
				continue;
			}
			$values[] = [
				'label' => $title,
				'link'  => $driver->getRecordUrl(),
				'image' => $driver->getBookcoverUrl('small'),
			];
		}

		return empty($values) ? null : ['format' => 'scroller', 'values' => $values];
	}

	/**
	 * Catalog GroupedWork records linked via field_pika_related_link on this object
	 * or its parent collection(s).
	 * Returns up to the full set as a scroller.
	 *
	 * @param I2Object          $obj
	 * @param \Islandora2Driver|null $driver  Shared driver instance (avoids duplicate API calls)
	 * @return array|null
	 */
	private function getLinkedCatalogRecords(I2Object $obj, ?\Islandora2Driver $driver = null): ?array {
		if ($driver === null) {
			$nid = $obj->getNodeId();
			if (!$nid) {
				return null;
			}
			$driver = new \Islandora2Driver($nid);
		}

		$works = $driver->getRelatedPikaWorks();
		if (empty($works)) {
			return null;
		}

		$values = [];
		foreach ($works as $work) {
			if (empty($work['label'])) {
				continue;
			}
			$values[] = [
				'label' => $work['label'],
				'link'  => $work['link'],
				'image' => $work['image'],
			];
		}

		return empty($values) ? null : ['format' => 'scroller', 'values' => $values];
	}

	/**
	 * Catalog GroupedWork records found by searching subject terms from this object.
	 * Excludes works already surfaced by getLinkedCatalogRecords() (field_pika_related_link).
	 * Returns up to 5 results as a scroller.
	 *
	 * @param I2Object               $obj
	 * @param \Islandora2Driver|null $driver  Shared driver instance to read already-linked IDs
	 * @return array|null
	 */
	private function getRelatedCatalog(I2Object $obj, ?\Islandora2Driver $driver = null): ?array {
		$subjects = $obj->getSubjects();
		if (empty($subjects)) {
			return null;
		}

		// Normalise and quote up to 5 subject names for the search term
		if (isset($subjects['name'])) {
			$subjects = [$subjects];
		}
		$terms = [];
		foreach (array_slice($subjects, 0, 5) as $subject) {
			$name = $subject['name'] ?? null;
			if (!empty($name)) {
				$terms[] = '"' . addslashes($name) . '"';
			}
		}
		if (empty($terms)) {
			return null;
		}
		$searchTerm = implode(' OR ', $terms);

		// Collect IDs of directly-linked works so we can exclude them
		$excludeIds = [];
		if ($driver !== null) {
			foreach ($driver->getRelatedPikaWorks() as $work) {
				if (!empty($work['id'])) {
					$excludeIds[] = $work['id'];
				}
			}
		}

		/** @var \SearchObject_Solr $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject();
		$searchObject->init('local', $searchTerm);
		$searchObject->setSearchTerms([
			'lookfor' => $searchTerm,
			'index'   => 'Keyword',
		]);

		if (!empty($excludeIds)) {
			$searchObject->addHiddenFilter('!'. $searchObject::IDFIELD, implode(' OR ', $excludeIds));
		}

		$searchObject->setPage(1);
		$searchObject->setLimit(5);
		$results = $searchObject->processSearch(true, false);

		if (empty($results['response']['docs'])) {
			return null;
		}

		$values = [];
		foreach ($results['response']['docs'] as $doc) {
			$workDriver = \RecordDriverFactory::initRecordDriver($doc);
			$title      = $workDriver->getTitle();
			if (empty($title)) {
				continue;
			}
			$values[] = [
				'label' => $title,
				'link'  => $workDriver->getLinkUrl(),
				'image' => $workDriver->getBookcoverUrl('medium'),
			];
		}

		return empty($values) ? null : ['format' => 'scroller', 'values' => $values];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a textOnlyList section from a taxonomy term field value.
	 *
	 * @param mixed  $fieldValue  Value of the taxonomy field from I2Object
	 * @param string $facetField  Solr facet field name for the search link
	 * @return array|null
	 */
	private function buildTaxonomySection(mixed $fieldValue, string $facetField): ?array {
		if (empty($fieldValue)) {
			return null;
		}

		// Normalize single-entry arrays (keyed with 'name') to a list
		if (isset($fieldValue['name'])) {
			$fieldValue = [$fieldValue];
		}

		$values = [];
		foreach ($fieldValue as $term) {
			$name = $term['name'] ?? null;
			if (empty($name)) {
				continue;
			}
			$values[] = [
				'label' => $name,
				'link'  => '/Archive2/Results?filter[]=' . urlencode($facetField . ':"' . $name . '"'),
			];
		}

		return empty($values) ? null : ['format' => 'textOnlyList', 'values' => $values];
	}
}
