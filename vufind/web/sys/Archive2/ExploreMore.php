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
use Islandora2\I2Taxonomy;
use Islandora2\TaxonomyObjectInterface;
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
		'dpla'                 => 'Digital Public Library of America', // For Taxonomy Terms Only
		'acknowledgements'     => 'Acknowledgements', // For Archive Objects Only
	];

	/** Maps vocabulary machine name to the Solr field used to filter archive objects by this term. */
	private const VOCAB_FILTER_FIELD = [
		'person'         => 'sm_related_person',
		'corporate_body' => 'sm_related_organization',
		'geo_location'   => 'sm_related_place',
		'event'          => 'sm_related_event',
	];

	/** Linked-agent relationship roles rendered as acknowledgements (not in people/orgs sections). */
	private const BRANDING_ROLES = [
		'owner'           => 'Owned by',
		'donor'           => 'Donated by',
		'funder'          => 'Funded by',
		'sponsor'         => 'Sponsored by',
		'acknowledgement' => '',   // shown without prefix
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

		//TODO: Set up Parent Book section if applicable and if possible

		//TODO: Set up Table of Contents for books; and Compound Objects (?)

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

		$section = $this->getAcknowledgements($obj);
		if ($section) $sections['acknowledgements'] = $section;
		$timer->logTime('ExploreMore: acknowledgements');

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

		/** @var \Library $library */
		global $library;
		if (!$library->archiveOnlyInterface){
			$section = $this->getLinkedCatalogRecords($obj, $i2Driver);
			if ($section){
				$sections['linkedCatalogRecords'] = $section;
			}
			$timer->logTime('ExploreMore: linkedCatalogRecords (Pika works)');

			$section = $this->getRelatedCatalog($obj, $i2Driver);
			if ($section){
				$sections['relatedCatalog'] = $section;
			}
			$timer->logTime('ExploreMore: relatedCatalog (catalog Solr)');
		}

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

	/**
	 * Build all sidebar sections for the given taxonomy term page.
	 *
	 * Covers the same section set as loadExploreMoreSidebar() minus acknowledgements
	 * (which is node-specific), plus dpla (taxonomy term pages only).
	 *
	 * @param TaxonomyObjectInterface $term
	 * @return array
	 */
	public function loadTaxonomyExploreMoreSidebar(TaxonomyObjectInterface $term): array {
		global $timer, $library;
		$sections = [];

		$s = $this->buildTaxonomySection($term->getRelatedPerson(),       'sm_related_person');
		if ($s) $sections['relatedPeople'] = $s;

		$s = $this->buildTaxonomySection($term->getRelatedOrganization(), 'sm_related_organization');
		if ($s) $sections['relatedOrganizations'] = $s;

		$s = $this->buildTaxonomySection($term->getRelatedPlace(),        'sm_related_place');
		if ($s) $sections['relatedPlaces'] = $s;

		$s = $this->buildTaxonomySection($term->getRelatedEvent(),        'sm_related_event');
		if ($s) $sections['relatedEvents'] = $s;
		$timer->logTime('ExploreMore taxonomy: related entities');

		[$archiveSection, $collectionsSection] = $this->getTaxonomyArchiveDataAndCollections($term);
		if ($collectionsSection) $sections['relatedCollections'] = $collectionsSection;
		if ($archiveSection)     $sections['relatedArchiveData'] = $archiveSection;
		$timer->logTime('ExploreMore taxonomy: archive data and collections');

		if (!$library->archiveOnlyInterface) {
			$s = $this->getTaxonomyRelatedCatalog($term);
			if ($s) $sections['relatedCatalog'] = $s;
			$timer->logTime('ExploreMore taxonomy: relatedCatalog');
		}

		$s = $this->getTaxonomyRelatedSubjects($term);
		if ($s) $sections['relatedSubjects'] = $s;

		$s = $this->getDplaSection($term->getTitle() ?? '');
		if ($s) $sections['dpla'] = $s;
		$timer->logTime('ExploreMore taxonomy: dpla');

		return $sections;
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
	 * @return array Array of Archive Tiles to display in the Explore More Bar
	 */
	public function loadArchiveExploreMoreBarOptions(array $exploreMoreOptions, string $searchTerm): array {
		if (empty($searchTerm)) {
			global /** @var \Library $library */ $library;
			if (!empty($library->libraryTid) && $library->libraryTid > 0) {
				return $this->loadLibraryArchiveOptions($exploreMoreOptions, $library);
			}
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

		$formatFacetField     = 'sm_format'; // Hasn't been reverted yet.
		$collectionFacetField = 'sm_title_2'; //TODO: change when field is renamed
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
			$formatSearch->setSort('ds_created desc'); // Show newly created items
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
		$i = 0;
		$collectionFacetResults = $response['facet_counts']['facet_fields'][$collectionFacetField] ?? [];
		foreach ($collectionFacetResults as [$collection, $count]) {
			if (++$i > 5) break; // Only Add 5 related collections
			if ($count == 0) {
				continue;
			}

			/** @var \SearchObject_Islandora2 $collectionSearch */
			$collectionSearch = \SearchObjectFactory::initSearchObject('Islandora2');
			$collectionSearch->init();
			$collectionSearch->setSearchTerms($searchTerms);
			$collectionSearch->addFilter("$collectionFacetField:\"$collection\"");
			$collectionSearch->setSort('ds_created desc'); // Show newly created collections
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

	/**
	 * Populate explore-more-bar tiles for a blank catalog search using the
	 * current library's Islandora2 taxonomy term ID.
	 *
	 * Shows up to 5 newest collections first, then one tile per format type.
	 *
	 * @param array    $exploreMoreOptions  Existing bar tiles to append to
	 * @param \Library $library
	 * @return array
	 */
	private function loadLibraryArchiveOptions(array $exploreMoreOptions, \Library $library): array {
		$tid                           = (int)$library->libraryTid;
		$libraryFilter                 = "itm_field_library:$tid";
		$formatFacetField              = 'sm_format';
		$collectionFacetField          = 'sm_title_2'; // 'sm_collection'
		$contributingLibraryFacetField = 'ss_name_23'; // 'ss_library'
		$emptySearchTerms              = ['lookfor' => '', 'index' => 'Islandora2Keyword'];

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->setSearchTerms($emptySearchTerms);
		$searchObject->addFilter($libraryFilter);
		$searchObject->addFacet($formatFacetField, 'Format');
		$searchObject->addFacet($collectionFacetField, 'Collection');
		$searchObject->setLimit(1);
		$searchObject->setDebugging(false, false);

		$response = $searchObject->processSearch(true, false);
		if (empty($response['response']['numFound'])) {
			return $exploreMoreOptions;
		}

		// Get Library Name for Facet Filter
		$contributingLibraryLabel = $response['response']['docs'][0][$contributingLibraryFacetField];

		// Collections first (up to 5)
		$i = 0;
		$collectionFacetResults = $response['facet_counts']['facet_fields'][$collectionFacetField] ?? [];
		foreach ($collectionFacetResults as [$collection, $count]) {
			if (++$i > 5) break;
			if ($count == 0) {
				continue;
			}

			/** @var \SearchObject_Islandora2 $collectionSearch */
			$collectionSearch = \SearchObjectFactory::initSearchObject('Islandora2');
			$collectionSearch->init();
			$collectionSearch->setSearchTerms($emptySearchTerms);
			//$collectionSearch->addFilter("$contributingLibraryFacetField:\"$contributingLibraryLabel\""); // Collection facet filter will make this redundant (unless libraries start sharing collection objects);
			$collectionSearch->addFilter("$collectionFacetField:\"$collection\"");
			$collectionSearch->setSort('ds_created desc');
			$collectionSearch->setLimit(1);

			$collectionResponse = $collectionSearch->processSearch(true, false);
			if (empty($collectionResponse['response']['docs'])) {
				continue;
			}

			$firstDriver = \RecordDriverFactory::initRecordDriver(reset($collectionResponse['response']['docs']));

			if ($count == 1) {
				$exploreMoreOptions[] = [
					'label'       => $collection,
					'description' => "$collection in the archive",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $firstDriver->getRecordUrl(),
				];
			} else {
				$exploreMoreOptions[] = [
					'label'       => "$collection ($count)",
					'description' => "$collection in the archive",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $collectionSearch->renderSearchUrl(),
					'usageCount'  => $count,
				];
			}
		}

		// Formats second
		$formatFacetResults = $response['facet_counts']['facet_fields'][$formatFacetField] ?? [];
		foreach ($formatFacetResults as [$format, $count]) {
			if ($count == 0) {
				continue;
			}

			/** @var \SearchObject_Islandora2 $formatSearch */
			$formatSearch = \SearchObjectFactory::initSearchObject('Islandora2');
			$formatSearch->init();
			$formatSearch->setSearchTerms($emptySearchTerms);
			$formatSearch->addFilter("$contributingLibraryFacetField:\"$contributingLibraryLabel\"");
			$formatSearch->addFilter("$formatFacetField:\"$format\"");
			$formatSearch->setSort('ds_created desc');
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
					'description' => "$label in the archive",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $firstDriver->getRecordUrl(),
				];
			} else {
				$exploreMoreOptions[] = [
					'label'       => "$label ($count)",
					'description' => "$label in the archive",
					'image'       => $firstDriver->getBookcoverUrl('medium'),
					'link'        => $formatSearch->renderSearchUrl(),
					'usageCount'  => $count,
				];
			}
		}

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

		$displayType = count($values) > 3 ? 'textOnlyList' : 'list';
		//$values[]    = [
		//	'label' => 'Archive Homepage',
		//	'link'  => '/Archive2/Home'
		//];
		return ['format' => $displayType, 'values' => $values, 'showTitles' => true,];
		// showTitle is needed because Not all collection images contain the title
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

			// Branding agents are handled by getAcknowledgements()
			if (array_key_exists($rel, self::BRANDING_ROLES)) {
				continue;
			}

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
	 * Donor, funder, owner, and contributing-library acknowledgements for this object.
	 *
	 * Reads branding-role linked_agent entries and the object's contributing library
	 * field. Each entry is shown as an image tile (Corporate Body taxonomy thumbnail)
	 * linking to its /Archive2/Organization?tid=N page.
	 *
	 * @param I2Object $obj
	 * @return array|null
	 */
	private function getAcknowledgements(I2Object $obj): ?array {
		//TODO: Get Acknowledgements for Parent Collection
		require_once ROOT_DIR . '/sys/Islandora2/TaxonomyFactory.php';
		$factory = new \Islandora2\TaxonomyFactory();
		$values  = [];

		// Branding agents from linked_agent
		$linkedAgents = $obj->linked_agent;
		if (!empty($linkedAgents) && is_array($linkedAgents)) {
			if (isset($linkedAgents['name'])) {
				$linkedAgents = [$linkedAgents];
			}
			foreach ($linkedAgents as $agent) {
				$rel = strtolower($agent['rel'] ?? ($agent['role'] ?? ''));
				if (!array_key_exists($rel, self::BRANDING_ROLES)) {
					continue;
				}
				$tid = isset($agent['tid']) ? (int)$agent['tid'] : 0;
				if ($tid <= 0) {
					continue;
				}
				/** @var I2Taxonomy $term */
				$term = $factory->fromTid($tid);
				if ($term === null) {
					continue;
				}
				$prefix    = self::BRANDING_ROLES[$rel];
				$name      = $agent['name'] ?? $term->getName();
				$label     = $prefix ? "$prefix $name" : $name;
				$thumbnail = $term->getThumbnail();
				$values[]  = [
					'label' => $label,
					'image' => $thumbnail['url'] ?? null,
					'link'  => $term->getUrl(),
				];
			}
		}

		// Contributing Library — look up the object's contributing library and use its corporateBodyTid
		$raw           = $obj->library;
		$objLibraryTid = is_array($raw) ? (int)($raw['tid'] ?? 0) : (int)($raw ?? 0);
		if ($objLibraryTid > 0) {
			$contributingLibrary             = new \Library();
			$contributingLibrary->libraryTid = $objLibraryTid;
			if ($contributingLibrary->find(true) && !empty($contributingLibrary->corporateBodyTid) && $contributingLibrary->corporateBodyTid > 0) {
				/** @var \Islandora2\Organization $term */
				$term = $factory->fromTid((int)$contributingLibrary->corporateBodyTid);
				if ($term !== null) {
					$thumbnail = $term->getThumbnail();
					$values[]  = [
						'label' => 'Contributed by ' . $term->name,
						'image' => $thumbnail['url'] ?? null,
						'link'  => $term->getUrl(),
					];
				}
			}
		}
		// TODO: Fallback for contributing libraries not in the Pika Library table.
		// Lafayette on MLN1 Pika server; MLN1 libraries on MLN2 Pika server
		//   Some objects are contributed by organizations that have a Corporate Body
		//   taxonomy term in Islandora2 but no corresponding row in the library table
		//   (e.g. partner institutions, historical collections donors).
		//   Fallback approach: read $obj->library (field_library on the node), which
		//   carries the library vocabulary tid. From that tid, find the matching
		//   archivePid via the library Solr index or a DB lookup, then resolve to a
		//   Corporate Body tid via getLegacyEntitiesTIDs() — or alternatively, query
		//   Solr directly for Corporate Body terms whose ss_contributing_library field
		//   matches the library tid, if such a field is indexed.

		if (empty($values)) {
			return null;
		}

		return ['format' => 'list', 'values' => $values, 'showTitles' => true];
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
		// Normalize a single subject
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
	// Taxonomy section builders
	// -------------------------------------------------------------------------

	/**
	 * Run one Islandora2 Solr search for archive objects referencing this taxonomy
	 * term and return both the archive-data scroller section and a collections list
	 * section (up to 3 collections).
	 *
	 * Two facets are requested in one search:
	 *   sm_title_2  — collection display labels
	 *   its_nid_1   — corresponding collection node IDs (same order as sm_title_2)
	 * The nid facet allows direct linking to /Archive2/Collection/{nid} without
	 * sub-searches. Facet counts are compared as a sanity check that title and nid
	 * entries are aligned before pairing them.
	 *
	 * @param TaxonomyObjectInterface $term
	 * @return array  Two-element array [archiveDataSection|null, collectionsSection|null]
	 */
	private function getTaxonomyArchiveDataAndCollections(TaxonomyObjectInterface $term): array {
		$filterField = self::VOCAB_FILTER_FIELD[$term->getVocabularyMachineName()] ?? null;
		$name        = $term->getTitle();
		if (!$filterField || empty($name)) {
			return [null, null];
		}

		$collectionTitleField = 'sm_title_2';
		$collectionNidField   = 'its_nid_1';

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->addFilter($filterField . ':"' . $name . '"');
		$searchObject->addFacet($collectionTitleField, 'Collection');
		$searchObject->addFacet($collectionNidField,   'CollectionNid');
		$searchObject->setSort('sm_field_edtf_date_created desc');
		$searchObject->setLimit(12);
		$response = $searchObject->processSearch(true, false);

		if (empty($response['response']['docs'])) {
			return [null, null];
		}

		// Archive-data scroller
		$archiveValues = [];
		foreach ($response['response']['docs'] as $doc) {
			$driver = \RecordDriverFactory::initRecordDriver($doc);
			$title  = $driver->getTitle();
			if (empty($title)) {
				continue;
			}
			$archiveValues[] = [
				'label' => $title,
				'link'  => $driver->getRecordUrl(),
				'image' => $driver->getBookcoverUrl('small'),
			];
		}
		$archiveSection = empty($archiveValues) ? null : ['format' => 'scroller', 'values' => $archiveValues];

		// Collections list — pair sm_title_2 and its_nid_1 facets by position (same order),
		// verifying counts match before pairing. Up to 3 collections; each links directly
		// to the collection page via Islandora2Driver::getRecordUrl().
		$titleFacets = $response['facet_counts']['facet_fields'][$collectionTitleField] ?? [];
		$nidFacets   = $response['facet_counts']['facet_fields'][$collectionNidField]   ?? [];

		$collectionValues = [];
		$limit            = 3;
		foreach ($titleFacets as $pos => [$title, $titleCount]) {
			if (count($collectionValues) >= $limit) {
				break;
			}
			if ($titleCount == 0) {
				continue;
			}
			[$nid, $nidCount] = $nidFacets[$pos] ?? [null, null];
			if (empty($nid) || $nidCount !== $titleCount) {
				$this->logger->warning('getTaxonomyArchiveDataAndCollections: facet count mismatch, skipping.', [
					'title' => $title, 'titleCount' => $titleCount, 'nid' => $nid, 'nidCount' => $nidCount,
				]);
				continue;
			}
			$collectionDriver   = new \Islandora2Driver((int)$nid);
			$collectionValues[] = [
				'label' => $title,
				'link'  => $collectionDriver->getRecordUrl(),
				'image' => $collectionDriver->getBookcoverUrl('small'),
			];
		}
		$collectionsSection = empty($collectionValues) ? null : ['format' => 'list', 'values' => $collectionValues];

		return [$archiveSection, $collectionsSection];
	}

	/**
	 * Catalog keyword search using the taxonomy term's name.
	 *
	 * @param TaxonomyObjectInterface $term
	 * @return array|null
	 */
	private function getTaxonomyRelatedCatalog(TaxonomyObjectInterface $term): ?array {
		$name = $term->getTitle();
		if (empty($name)) {
			return null;
		}
		$searchTerm   = '"' . $name . '"';
		/** @var \SearchObject_Solr $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject();
		$searchObject->init('local', $searchTerm);
		$searchObject->setSearchTerms(['lookfor' => $searchTerm, 'index' => 'Keyword']);
		$searchObject->setPage(1);
		$searchObject->setLimit(5);
		$results = $searchObject->processSearch(true, false);
		if (empty($results['response']['docs'])) {
			return null;
		}
		$values = [];
		foreach ($results['response']['docs'] as $doc) {
			$driver = \RecordDriverFactory::initRecordDriver($doc);
			$title  = $driver->getTitle();
			if (empty($title)) {
				continue;
			}
			$values[] = [
				'label' => $title,
				'link'  => $driver->getLinkUrl(),
				'image' => $driver->getBookcoverUrl('medium'),
			];
		}
		return empty($values) ? null : ['format' => 'scroller', 'values' => $values];
	}

	/**
	 * Subject links from the taxonomy term's own subject field.
	 *
	 * @param TaxonomyObjectInterface $term
	 * @return array|null
	 */
	private function getTaxonomyRelatedSubjects(TaxonomyObjectInterface $term): ?array {
		$subjects = $term->getSubjects();
		if (empty($subjects)) {
			return null;
		}
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
	 * DPLA API results for this taxonomy term (taxonomy pages only).
	 *
	 * @param string $termTitle
	 * @return array|null
	 */
	private function getDplaSection(string $termTitle): ?array {
		if (empty($termTitle)) {
			return null;
		}
		require_once ROOT_DIR . '/sys/SearchObject/DPLA.php';
		$dpla        = new \DPLA();
		$dplaResults = $dpla->getDPLAResults('"' . $termTitle . '"');
		if (empty($dplaResults['records'])) {
			return null;
		}
		return [
			'format'          => 'scrollerWithLink',
			'values'          => $dplaResults['records'],
			'link'            => 'http://dp.la/search?q=' . urlencode('"' . $termTitle . '"'),
			'openInNewWindow' => true,
			'numFound'        => $dplaResults['resultTotal'],
		];
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
