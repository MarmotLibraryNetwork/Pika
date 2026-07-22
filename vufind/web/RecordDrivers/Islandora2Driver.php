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
require_once ROOT_DIR . '/sys/Islandora2/I2ObjectFactory.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';

use Islandora2\I2Object;
use Islandora2\I2ObjectFactory;
use Islandora2\TaxonomyFactory;
use Pika\Logger;

/**
 * Record driver for Islandora 2 nodes that are exposed through the JSON endpoint.
 *
 * The driver mirrors the behavior of the legacy Islandora driver where possible
 * while sourcing its data from the Islandora 2 pika-api interface.
 *
 * @category Pika
 */
class Islandora2Driver extends RecordInterface
{
    /* TODO: Do we need a place holder image? */
    private const PLACEHOLDER_IMAGE = '/interface/themes/responsive/images/History.png';

	private Logger $logger;
	private int $nodeId = 0;
	private ?I2Object $i2Object = null;
	private bool $i2ObjectLoaded = false;
	protected ?float $solrScore = null;
	protected ?string $solrExplanation = null;
	private ?string $title = null;
	private ?string $description = null;
	private ?string $format = null;
	private ?string $model = null;
	private ?string $contributingLibrary = null;

	/**
	 * Per-request cache of resolved contributing-library info, keyed by library taxonomy tid.
	 * Static so it's shared across the many Islandora2Driver instances created while paging
	 * through a large result set (e.g. the DPLA feed export), avoiding a repeat
	 * DB + taxonomy lookup for every record from the same library.
	 *
	 * @var array<int, array{libraryName:string, baseUrl:?string, orgTid:?int}|null>
	 */
	private static array $contributingLibraryInfoCache = [];

    /**
     * @param int|string|array $recordData
	 * 
	 * Most likely the $recordData will be a nodeID
     */
    public function __construct($recordData)
    {
        $this->logger = new Logger(__CLASS__);
        $this->initialiseFromRecordData($recordData);
    }

    /**
     * Accept mixed construction input to match RecordInterface expectations.
     *
     * @param mixed $recordData
     */
		private function initialiseFromRecordData($recordData): void
		{
				if (is_array($recordData)) {
					if (isset($recordData['ss_type'])){ // Populate fields from Solr Document values
						$this->nodeId          = !empty($this->getSolrFieldValue($recordData, 'id')) ? (int)$this->getSolrFieldValue($recordData, 'id') : null;
						$solrTitleValue        = $this->getSolrFirstFieldValue($recordData, 'title');
						$this->title           = !empty($solrTitleValue) ? $solrTitleValue : null;
						$solrDescriptionValue  = $this->getSolrFirstFieldValue($recordData, 'description');
						$this->description     = !empty($solrDescriptionValue) ? $solrDescriptionValue : null;
						$solrFormatValue       = $this->getSolrFirstFieldValue($recordData, 'format');
						$this->format          = !empty($solrFormatValue) ? $solrFormatValue : null;
						$this->model           = !empty($this->getSolrFieldValue($recordData, 'model')) ? $this->getSolrFieldValue($recordData, 'model') : null;
						$this->contributingLibrary = !empty($this->getSolrFieldValue($recordData, 'library')) ? $this->getSolrFieldValue($recordData, 'library') : null;
						$this->solrScore       = isset($recordData['score']) ? (float)$recordData['score'] : null;
						$this->solrExplanation = isset($recordData['explain']) ? (string)$recordData['explain'] : null;
						// Do NOT eagerly fetch the I2Object here. ensureI2Object() lazy-loads it on
						// the first method call that actually needs it, avoiding an HTTP API round-trip
						// for every object in list/carousel contexts where only Solr fields are used.
					} else{
						$this->nodeId = $this->extractNodeId($recordData);

						$nodeData = $recordData['node'] ?? ($recordData['json'] ?? null);
						if (is_array($nodeData)){
							$factory              = new I2ObjectFactory();
							$obj                  = $factory->fromNode($nodeData);
							$this->i2Object       = ($obj instanceof I2Object) ? $obj : null;
							$this->i2ObjectLoaded = true;
						}
					}
				} elseif (is_numeric($recordData)) {
						$this->nodeId = (int)$recordData;
				// if an int is passed as a string
				 } elseif (is_string($recordData) && ctype_digit($recordData)) {
						 $this->nodeId = (int)$recordData;
				}

				if ($this->nodeId <= 0) {
						$this->logger->warning('Islandora2Driver initialized without a valid node id.', ['recordData' => $recordData]);
				}
		}

	private $solrFields = [
		'id'          => 'its_node_id',
		//'title'       => 'tm_X3b_en_title', //test
		'title'       => 'twm_X3b_en_title_ws_token', // production TODO: use this field, it has punctuation, whereas the field above does not
		'description' => 'twm_X3b_en_field_description_long_ws_token',
		//'description' => 'tm_X3b_en_field_description_long',
		'memberOf'    => 'itm_field_member_of', //node ids
		'legacyPID'   => 'ss_legacy_pid',
		//'legacyPID'   => 'tm_X3b_en_field_pid',
		//'genre'       => 'sm_name_2',
		'genre'       => 'sm_genre',
		//'model'       => 'ss_name_1', //TODO remove
		'model'       => 'ss_model',
		//'legacyResourceType' => 'sm_name_22',
		'legacyResourceType' => 'sm_legacy_resource_type',
		//'format'      => 'sm_name_43',
		'format'      => 'sm_format',
		//'library'     => 'ss_name_23', // TODO remove
		'library'     => 'ss_library',
		//'rightsCreator' => 'tm_X3b_en_name_41',
		'rightsCreator' => 'sm_rights_creator',
	];

	/**
	 * Extract solr field value using an easier to understand key
	 * @param array $solrDoc
	 * @param string $field
	 * @return mixed
	 */
	private function getSolrFieldValue(array $solrDoc, string $field){
		return $solrDoc[$this->solrFields[$field]];
	}

	/**
	 * Extract a solr field value and return the first element if the value is an array.
	 * @param array $solrDoc
	 * @param string $field
	 * @return mixed
	 */
	private function getSolrFirstFieldValue(array $solrDoc, string $field){
		$value = $this->getSolrFieldValue($solrDoc, $field);
		return is_array($value) ? $value[0] : $value;
	}

    /**
     * Attempt to detect a node id in an array payload.
     *
     * @param array $recordData
     * @return int
     */
    private function extractNodeId(array $recordData): int
    {
        $candidates = [
            'nodeId',
            'node_id',
            'nid',
			'its_node_id', // Solr Field
            'id',
            'record_id',
            'identifier',
            'sourceId',
            'pid',
        ];

        foreach ($candidates as $candidate) {
            if (isset($recordData[$candidate]) && is_numeric($recordData[$candidate])) {
                $nodeId = (int)$recordData[$candidate];
                if ($nodeId > 0) {
                    return $nodeId;
                }
            }
        }

        return 0;
    }

    /**
     * Lazy-load the I2Object, fetch from the API when needed.
     *
     * @return I2Object|null
     */
    private function ensureI2Object(): ?I2Object
    {
        if ($this->i2ObjectLoaded) {
            return $this->i2Object;
        }

        if ($this->nodeId <= 0) {
            $this->logger->warning('Cannot load Islandora2 object without a valid node id.');
            return null;
        }

        $factory = new I2ObjectFactory();
        $obj     = $factory->fromNodeId($this->nodeId);
        $this->i2Object = ($obj instanceof I2Object) ? $obj : null;

        if ($this->i2Object === null) {
            $this->logger->warning('Failed to load Islandora2 object.', ['nodeId' => $this->nodeId]);
        }

		$this->i2ObjectLoaded = true;

        return $this->i2Object;
    }

    /**
     * @return array|null
     */
    public function getNodeData(): ?array
    {
        $obj = $this->ensureI2Object();
        return $obj ? $obj->getNode() : null;
    }

    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    /**
     * Determine a cover/thumbnail URL for the node.
     *
     * @param string $size
     * @return string
     */
    public function getBookcoverUrl($size = 'small')
    {
        $obj = $this->ensureI2Object();
        if (!$obj) {
            return self::PLACEHOLDER_IMAGE;
        }

        if ($size === 'large') {
            /** @var \Islandora2\I2Media|null $original */
            $original = $obj->getOriginalMedia();
            if ($original && $original->bundle === 'image' && $original->fileUrl !== '') {
                return $original->fileUrl;
            }
            /** @var \Islandora2\I2Media|null $serviceFile */
            $serviceFile = $obj->getServiceFile();
            if ($serviceFile && $serviceFile->fileUrl !== '') {
                return $serviceFile->fileUrl;
            }
        }

        $thumbnail = $obj->getThumbnail();
        if ($thumbnail && $thumbnail->fileUrl !== '') {
            return $thumbnail->fileUrl;
        }

        return self::PLACEHOLDER_IMAGE;
    }

    public function getBreadcrumb()
    {
        return $this->getTitle();
    }

    public function getCitation($format)
    {
        return null;
    }

    public function getCitationFormats()
    {
        return [];
    }

    public function getExport($format)
    {
        return null;
    }

    public function getExportFormats()
    {
        return [];
    }

	public function getListEntry($listId = null, $allowEdit = true){
		global $interface;

		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('jquerySafeId',$this->getUniqueID());
		//TODO: str_replace likely not needed now
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summFormat', $this->getFormat());
//		$interface->assign('summShortId', null);
//		$interface->assign('summTitleStatement', null);
		$interface->assign('summAuthor', null);
		$interface->assign('summPublisher', null);
		$interface->assign('summPubDate', null);
//		$interface->assign('$summSnippets', null);
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));
		$interface->assign('summAjaxStatus', false);
		$interface->assign('recordDriver', $this);

		if ($listId){
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry                         = new UserListEntry();
			$listEntry->groupedWorkPermanentId = $this->nodeId;
			$listEntry->listId                 = $listId;
			if ($listEntry->find(true)){
				$interface->assign('listEntryNotes', $listEntry->notes);
			}
			$interface->assign('listEditAllowed', $allowEdit);
		}

		return 'RecordDrivers/Islandora/listentry.tpl';
	}

    /**
     * Provide a browse tile result.
     *
     * @return string
     */
	public function getBrowseResult(){
		global $interface;
		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('medium'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		return 'RecordDrivers/Islandora/browse_result.tpl';
	}

    public function getRecordUrl()
    {
		$obj = $this->ensureI2Object();
        if ($this->nodeId <= 0 || is_null($obj)) {
            return '#';
        }
		$displayModel = strtolower($obj->getDisplayModel());
		$displayModel = ISLANDORA2_DISPLAY_MODEL_URL_MAP[$displayModel] ?? $displayModel;
		//TODO: $displayModel should be checked against an array of known valid values
		//TODO: ensure conforming capitalization of $displayModel

        return '/Archive2/' . $displayModel . '/' . urlencode((string)$this->nodeId);
    }

    public function getAbsoluteUrl()
    {
        global $configArray;
        global $library;

        $baseUrl = $configArray['Site']['url'] ?? '';
        if (!empty($library->catalogUrl ?? '')) {
            $scheme  = $_SERVER['REQUEST_SCHEME'] ?? 'https';
            $baseUrl = $scheme . '://' . $library->catalogUrl;
        }

        return rtrim($baseUrl, '/') . $this->getRecordUrl();
    }



    public function getModule()
    {
        return 'Archive2';
    }

    public function getRDFXML()
    {
        return null;
    }

    public function getSemanticData()
    {
        $obj = $this->ensureI2Object();
        if (!$obj) {
            return [];
        }

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'CreativeWork',
            '@id'         => $this->getAbsoluteUrl(),
            'name'        => $this->getTitle(),
            'description' => $this->getDescription(),
            'image'       => $this->getBookcoverUrl('large'),
        ];
    }

	public function getSearchResult($view = 'list'){
		if ($view === 'covers'){
			return $this->getBrowseResult();
		}

		global $interface;
		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('summTitle', $this->getTitle());
		//$interface->assign('jquerySafeId', str_replace(':', '_', $this->getUniqueID()));
		$interface->assign('jquerySafeId', $this->getUniqueID());
		// colon replacement no longer necessary
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summFormat', $this->format);
		$interface->assign('summModel', $this->model);
		$interface->assign('summLibrary', $this->getContributingLibrary());
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		global $configArray;
		if (!empty($configArray['System']['debugSolr'])){
			$interface->assign('summScore', $this->solrScore);
			$interface->assign('summExplain', $this->getExplain());
		}

		return 'RecordDrivers/Islandora/result.tpl';
	}

	public function getExplain(){
		if (!empty($this->solrExplanation)){
			$explain = explode(', result of:', $this->solrExplanation, 2);
			// Break query from score explanation
			//TODO: this explode may not may sense with this version of solr
			if (count($explain) > 1){
				$explain[1] = preg_replace('/weight\((.*):(.*)( in \d+\))/i', 'weight(<code>$1</code>:<strong>$2</strong>$3)', $explain[1]);
				// highlight the solr fields and the search term of interest
				$explain[1] = preg_replace('/computed as (.*) from:/i', 'computed as <var>$1</var> from:', $explain[1]);
				// italicize the formula fragments

				$explain[0] = preg_replace('/weight\((.*):(.*)( in \d+\))/i', 'weight(<code>$1</code>:<strong>$2</strong>$3)', $explain[0]);
				// highlight the solr fields and the search term of interest in the query

				return $explain[0] . '<br> result of : <p>' . nl2br(str_replace(' ', '&nbsp;', $explain[1])) . '</p>';
				// Put text back together, replace spaces with non-breaking space character, so the indentation of explanation lines displays
			}
		}
		return '';
	}

	public function getStaffView()
    {
        return null; //TODO: Implement
    }

    public function getTitle()
    {
        // When constructed from a Solr document, the title is already available without
        // an API call — use it directly to avoid triggering ensureI2Object().
        if (!empty($this->title)) {
            return $this->title;
        }

        $obj = $this->ensureI2Object();
        if (!$obj) {
            return $this->nodeId > 0 ? 'Islandora Node ' . $this->nodeId : '';
        }

        $title = $obj->getTitle();
        if ($title === null || $title === '') {
            // Fallback when the I2Object subclass cannot resolve a title (e.g. DefaultMediaObject).
            $raw   = $obj->getRawNode();
            $title = $raw['field_display_title'] ?? ($raw['title'] ?? '');
        }

        return $title !== '' ? $title : ($this->nodeId > 0 ? 'Islandora Node ' . $this->nodeId : '');
    }

    public function getDateCreated($format='d/m/Y')
    {
        $obj = $this->ensureI2Object();
        if (!$obj) {
            return $this->nodeId > 0 ? 'Islandora Node ' . $this->nodeId : '';
        }
        return $obj->getDateCreated($format);
    }



    public function getDescription(): string
    {
        $obj = $this->ensureI2Object();
        if (!$obj) {
            return '';
        }
        return $obj->getDescription() ?? '';
    }

    public function getFormat(): string
    {
        $obj = $this->ensureI2Object();
        if (!$obj) {
            return 'Digital Resource';
        }
        $label = $obj->getObjectModelLabel();
        return $label !== '' ? $label : 'Digital Resource';
    }

    public function getUniqueID(bool $includePrefix = true): string
    {
			//TODO: the use-cases that require the prefix are likely limited. Most uses probably just need the node Id.
			if ($includePrefix){
				return $this->nodeId > 0 ? 'islandora2-' . $this->nodeId : 'islandora2-unknown';
				// using dash to avoid html DOM problems when using a colon (:) character
			} else {
				return $this->nodeId > 0 ? $this->nodeId : 'unknown';
			}
    }

    public function hasFullText(): bool
    {
        return false;
    }

    public function getTOC()
    {
        return [];
    }

    public function hasRDF()
    {
        return false;
    }

    public function getMoreDetailsOptions()
    {
        return [];
    }

    public function getItemActions($itemInfo)
    {
        return [];
    }

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $isHomePickupRecord, $isExternalReservationItem = false, $relatedUrls = null){
		return [];
	}

	private function getContributingLibrary(){
			return $this->contributingLibrary ?? null;
	}

	// -------------------------------------------------------------------------
	// Related Pika Works
	// -------------------------------------------------------------------------

	private ?array $relatedPikaWorks = null;

	/**
	 * Return catalog GroupedWork records linked to this object via field_pika_related_link,
	 * including any links inherited from parent collection(s).
	 *
	 * Each entry: ['label' => string, 'link' => string, 'image' => string, 'id' => string]
	 *
	 * @return array[]
	 */
	public function getRelatedPikaWorks(): array {
		if ($this->relatedPikaWorks !== null) {
			return $this->relatedPikaWorks;
		}
		$this->relatedPikaWorks = $this->collectRelatedPikaWorks([$this->nodeId]);
		return $this->relatedPikaWorks;
	}

	/**
	 * Recursive helper: collect related Pika works from this object and its parents.
	 *
	 * $visitedNids prevents infinite loops if the member_of hierarchy contains cycles.
	 *
	 * @param int[] $visitedNids Node IDs already processed in this branch
	 * @return array[]
	 */
	private function collectRelatedPikaWorks(array $visitedNids): array {
		$obj = $this->ensureI2Object();
		if (!$obj) {
			return [];
		}

		$works = $this->fetchGroupedWorks(
			$this->extractGroupedWorkIds($obj->pika_related_link)
		);

		// Inherit links from parent collection(s)
		$memberOf = $obj->member_of;
		if (!empty($memberOf)) {
			if (!is_array($memberOf)) {
				$memberOf = [$memberOf];
			}
			foreach ($memberOf as $entry) {
				$nid = is_array($entry) ? ($entry['id'] ?? ($entry['nid'] ?? null)) : $entry;
				if (!is_numeric($nid)) {
					continue;
				}
				$nid = (int)$nid;
				if ($nid <= 0 || in_array($nid, $visitedNids, true)) {
					continue; // cycle guard
				}
				$visitedNids[] = $nid;
				$parentDriver  = new self($nid);
				$works         = array_merge($works, $parentDriver->collectRelatedPikaWorks($visitedNids));
			}
		}

		return $works;
	}

	/**
	 * Extract GroupedWork UUIDs from a field_pika_related_link value.
	 *
	 * Drupal link fields arrive as a bare URL string, a single ['uri'|'url' => ...]
	 * array, or an indexed array of such items.
	 *
	 * @param mixed $fieldValue
	 * @return string[]
	 */
	private function extractGroupedWorkIds(mixed $fieldValue): array {
		if (empty($fieldValue)) {
			return [];
		}

		// Normalize to a flat list of URL strings
		$urls = [];
		if (is_string($fieldValue)) {
			$urls = [$fieldValue];
		} elseif (is_array($fieldValue)) {
			if (isset($fieldValue['uri']) || isset($fieldValue['url'])) {
				// Single link entry
				$urls = [$fieldValue['uri'] ?? $fieldValue['url']];
			} else {
				// Indexed array of link entries
				foreach ($fieldValue as $item) {
					if (is_string($item)) {
						$urls[] = $item;
					} elseif (is_array($item)) {
						$url = $item['uri'] ?? ($item['url'] ?? null);
						if ($url) {
							$urls[] = $url;
						}
					}
				}
			}
		}

		$ids = [];
		foreach ($urls as $url) {
			if (preg_match('/\/GroupedWork\/([a-f0-9\-]{36})/i', $url, $m)) {
				$ids[] = $m[1];
			}
		}
		return array_unique($ids);
	}

	/**
	 * Fetch GroupedWork display data from the catalog Solr index.
	 *
	 * @param string[] $workIds Grouped work UUIDs
	 * @return array[]
	 */
	private function fetchGroupedWorks(array $workIds): array {
		if (empty($workIds)) {
			return [];
		}
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';

		/** @var \SearchObject_Solr $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject();
		$searchObject->init();
		$records      = $searchObject->getRecords($workIds);
		$searchObject = null;

		$works = [];
		foreach ($records as $workData) {
			$driver = new \GroupedWorkDriver($workData);
			if ($driver->isValid) {
				$works[] = [
					'label' => $driver->getTitle(),
					'link'  => $driver->getLinkUrl(),
					'image' => $driver->getBookcoverUrl('medium'),
					'id'    => $driver->getPermanentId(),
				];
			}
		}
		return $works;
	}

	// -------------------------------------------------------------------------
	// DPLA feed support
	//
	// Thin adapters that expose I2Object data in the flat shape the DPLA export
	// (services/API/ArchiveAPI.php) consumes. Kept on the driver — rather than in
	// the API class — so ArchiveAPI stays driver-facing, mirroring how the legacy
	// IslandoraDriver exposed these same methods.
	// -------------------------------------------------------------------------

	/**
	 * Alternative title for the object, or '' when none is set.
	 * @return string
	 */
	public function getAlternativeTitle(): string{
			$obj = $this->ensureI2Object();
			if (!$obj) {
				return '';
			}
			$alternativeTitle = $this->firstNonEmptyString($obj->alternative_title); // magic __get → field_alternative_title
			return $alternativeTitle;
	}

	/**
	 * Subtitle for the object, or '' when none is set.
	 */
	public function getSubTitle(): string {
		$obj = $this->ensureI2Object();
		if (!$obj) {
			return '';
		}
		$subtitle = $obj->subtitle; // magic __get → field_subtitle
		return (is_string($subtitle) && $subtitle !== '') ? $subtitle : '';
	}

	/**
	 * Normalize a raw node field value that may be a plain string or a list of
	 * strings (Drupal multi-value field) down to its first non-empty string.
	 *
	 * @param mixed $raw
	 * @return string
	 */
	private function firstNonEmptyString($raw): string {
		if (is_string($raw)){
			return $raw;
		}
		if (is_array($raw)){
			foreach ($raw as $item){
				if (is_string($item) && $item !== ''){
					return $item;
				}
			}
		}
		return '';
	}

	/**
	 * Human-readable language name, or '' when unavailable.
	 */
	public function getLanguage(): string {
		$obj = $this->ensureI2Object();
		return $obj ? ($obj->getLanguage() ?? '') : '';
	}

	/**
	 * Subject heading labels as a plain list of strings.
	 *
	 * Replaces the legacy getAllSubjectHeadings() associative shape; the DPLA feed
	 * only ever consumed the subject strings (via array_keys) anyway.
	 *
	 * @return string[]
	 */
	public function getSubjectLabels(): array {
		$obj = $this->ensureI2Object();
		if (!$obj) {
			return [];
		}
		$labels = [];
		foreach ($obj->getSubjects() ?? [] as $subject) {
			$name = $subject['name'] ?? '';
			if ($name !== '') {
				$labels[] = $name;
			}
		}
		return $labels;
	}

	/**
	 * Related places as [['label' => name, 'tid' => tid], ...].
	 * @return array[]
	 */
	public function getRelatedPlaces(): array {
		return $this->relatedTermLabels($this->ensureI2Object()?->getRelatedPlace());
	}

	/**
	 * Related events as [['label' => name, 'tid' => tid], ...].
	 * @return array[]
	 */
	public function getRelatedEvents(): array {
		return $this->relatedTermLabels($this->ensureI2Object()?->getRelatedEvent());
	}

	/**
	 * Related people as [['label' => name, 'tid' => tid, 'role' => relation], ...].
	 * @return array[]
	 */
	public function getRelatedPeople(): array {
		return $this->relatedTermLabels($this->ensureI2Object()?->getRelatedPerson(), true);
	}

	/**
	 * Related organizations as [['label' => name, 'tid' => tid, 'role' => relation], ...].
	 * @return array[]
	 */
	public function getRelatedOrganizations(): array {
		return $this->relatedTermLabels($this->ensureI2Object()?->getRelatedOrganization(), true);
	}

	/**
	 * Normalize an I2Object related-term list (name/relation/…) into the flat
	 * label/role shape the DPLA feed consumes.
	 *
	 * @param array|null $entries  Raw related-term entries from I2Object
	 * @param bool       $withRole Include the 'role' key (relation machine value)
	 * @return array[]
	 */
	private function relatedTermLabels(?array $entries, bool $withRole = false): array {
		if (empty($entries)) {
			return [];
		}
		$out = [];
		foreach ($entries as $entry) {
			$label = $entry['name'] ?? '';
			if ($label === '') {
				continue;
			}
			$item = [
				'label' => $label,
				'tid'   => $entry['tid'] ?? null,
			];
			if ($withRole) {
				// I2 stores the relator as the machine value in 'relation', e.g. 'local:pbl'.
				// API_ArchiveAPI compares this directly against its 'local:<code>' relator
				// constants (RELATOR_CODE_PUBLISHER / organizationRolesToIncludeInDPLA) —
				// still worth spot-checking against production data since these codes were
				// inferred from the 'local:sup' / 'local:pml' comparisons used elsewhere
				// (ArchiveObject.php, correspondenceSection.tpl) rather than confirmed live.
				$item['role'] = $entry['relation'] ?? ($entry['relation_label'] ?? null);
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Parent collections as [['label' => title, 'nid' => nid], ...], walking the
	 * full member_of ancestor chain (nearest parent first).
	 *
	 * @return array[]
	 */
	public function getRelatedCollections(): array {
		$obj = $this->ensureI2Object();
		if (!$obj) {
			return [];
		}
		$collections = [];
		$visited     = [];
		$parent      = $obj->getParentCollection();
		while ($parent !== null) {
			$nid = $parent->getNodeId();
			if ($nid !== null && in_array($nid, $visited, true)) {
				break; // cycle guard
			}
			if ($nid !== null) {
				$visited[] = $nid;
			}
			$title = $parent->getTitle();
			if (!empty($title)) {
				$collections[] = ['label' => $title, 'nid' => $nid];
			}
			$parent = $parent->getParentCollection();
		}
		return $collections;
	}

	/**
	 * rightsstatements.org URI from field_rights_org_statement, or the Pika default
	 * when unset.
	 *
	 * The '?language=en' query param is left intact here; the DPLA feed strips it
	 * (the DPLA hub requested its removal).
	 */
	public function getRightsStatement(): string {
		$default = 'http://rightsstatements.org/page/CNE/1.0/?language=en';
		$obj     = $this->ensureI2Object();
		if (!$obj) {
			return $default;
		}
		$rights = $obj->rights_org_statement; // magic __get → field_rights_org_statement
		if (is_array($rights) && !empty($rights['uri'])) {
			return $rights['uri'];
		}
		return $default;
	}

	/**
	 * Contributing-library info for the DPLA feed: display name, catalog base URL,
	 * and the Corporate Body term id (used to de-dupe against partner organizations).
	 *
	 * Mirrors the legacy IslandoraDriver::getContributingLibrary() shape, but sources
	 * the name/tid from the library's Corporate Body taxonomy term and the base URL
	 * from the matching Pika Library row (keyed by libraryTid rather than namespace).
	 *
	 * Not every contributing library has a matching Pika Library row (or a populated
	 * corporateBodyTid on that row) — e.g. partner institutions hosted on a different
	 * Pika server. When that lookup comes up empty we fall back to the library's own
	 * Islandora taxonomy term, which carries a field_related_organization pointing at
	 * the equivalent Corporate Body term (same fallback used by
	 * Archive2\ExploreMore::getExploreMoreData() for the "Contributed by" tile).
	 *
	 * Results are cached per libraryTid for the lifetime of the request: this driver
	 * is re-instantiated for every record in a search result, but a DPLA feed page
	 * covers many records from the same handful of libraries.
	 *
	 * @return array{libraryName:string, baseUrl:?string, orgTid:?int}|null
	 */
	public function getContributingLibraryInfo(): ?array {
		$obj = $this->ensureI2Object();
		if (!$obj) {
			return null;
		}

		$libraryTid = isset($obj->library['tid']) ? (int)$obj->library['tid'] : 0;
		if ($libraryTid <= 0) {
			return null;
		}

		if (array_key_exists($libraryTid, self::$contributingLibraryInfoCache)) {
			return self::$contributingLibraryInfoCache[$libraryTid];
		}

		return self::$contributingLibraryInfoCache[$libraryTid] = $this->resolveContributingLibraryInfo($libraryTid);
	}

	/**
	 * Resolve (uncached) the contributing-library info for a given library taxonomy tid.
	 * See getContributingLibraryInfo() for the primary/fallback strategy.
	 *
	 * @param int $libraryTid
	 * @return array{libraryName:string, baseUrl:?string, orgTid:?int}|null
	 */
	private function resolveContributingLibraryInfo(int $libraryTid): ?array {
		$pikaLibrary             = new Library();
		$pikaLibrary->libraryTid = $libraryTid;
		$libraryRowFound         = $pikaLibrary->find(true);

		$baseUrl = null;
		if ($libraryRowFound && !empty($pikaLibrary->catalogUrl)) {
			$scheme  = $_SERVER['REQUEST_SCHEME'] ?? 'https';
			$baseUrl = $scheme . '://' . $pikaLibrary->catalogUrl;
		}

		$taxonomy    = new TaxonomyFactory();
		$libraryName = null;
		$orgTid      = null;

		// Primary: resolve via the Pika Library DB row → corporateBodyTid
		if ($libraryRowFound && !empty($pikaLibrary->corporateBodyTid)) {
			$orgTerm = $taxonomy->fromTid((int)$pikaLibrary->corporateBodyTid);
			if ($orgTerm !== null) {
				$libraryName = $orgTerm->getTitle();
				$orgTid      = $orgTerm->getTid();
			}
		} elseif ($libraryRowFound) {
			$this->logger->warn("Contributing library $pikaLibrary->subdomain does not have the Corporate Body TID set");
		}

		// Fallback for libraries with no matching Pika Library row or corporateBodyTid
		// (e.g., partner institutions on a different Pika server): the library
		// vocabulary term in Islandora carries a field_related_organization pointing
		// to the equivalent Corporate Body term; use the first entry.
		if ($libraryName === null) {
			$libraryTerm = $taxonomy->fromTid($libraryTid);
			if ($libraryTerm !== null) {
				$relatedOrgs = $libraryTerm->getRelatedOrganization();
				if (!empty($relatedOrgs)) {
					$org         = $relatedOrgs[0];
					$libraryName = $org['name'] ?? null;
					$orgTid      = isset($org['tid']) ? (int)$org['tid'] : null;
				} else {
					$this->logger->warn("Islandora2Driver: library term $libraryTid has no related organization; cannot resolve contributing library name.");
				}
			} else {
				$this->logger->warn("Islandora2Driver: library term $libraryTid not found in Islandora; cannot resolve contributing library name.");
			}
		}

		if (empty($libraryName) && $baseUrl === null) {
			return null; // nothing useful to report
		}
		return [
			'libraryName' => $libraryName ?? '',
			'baseUrl'     => $baseUrl,
			'orgTid'      => $orgTid,
		];
	}

}
