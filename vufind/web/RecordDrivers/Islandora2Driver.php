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
						$this->solrScore       = isset($recordData['score']) ? (float)$recordData['score'] : null;
						$this->solrExplanation = isset($recordData['explain']) ? (string)$recordData['explain'] : null;
						$factory               = new I2ObjectFactory();
						$obj                   = $factory->fromNodeId($this->nodeId);
						if ($obj instanceof I2Object){
							$this->i2Object       = $obj;
							$this->i2ObjectLoaded = true;
						}
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
		'description' => 'tm_X3b_en_field_description_long',
		'memberOf'    => 'itm_field_member_of', //node ids
		'legacyPID'   => 'tm_X3b_en_field_pid',
		//'genre'       => 'sm_name_2',
		'genre'       => 'sm_genre',
		//'model'       => 'ss_name_1',
		'model'       => 'ss_model',
		//'legacyResourceType' => 'sm_name_22',
		'legacyResourceType' => 'sm_legacy_resource_type',
		//'format'      => 'sm_name_43',
		'format'      => 'sm_format',
		//'library'     => 'ss_name_23',
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
		$interface->assign('jquerySafeId', str_replace(':', '_', $this->getUniqueID()));
		//TODO: str_replace likely not needed now
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summFormat', $this->getFormat());
//		$interface->assign('summShortId', null);
//		$interface->assign('summTitleStatement', null);
//		$interface->assign('summAuthor', null);
//		$interface->assign('summPublisher', null);
//		$interface->assign('summPubDate', null);
//		$interface->assign('$summSnippets', null);
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));
		$interface->assign('summAjaxStatus', false);
		$interface->assign('recordDriver', $this);

		if ($listId){
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry                         = new UserListEntry();
			$listEntry->groupedWorkPermanentId = $this->getUniqueID();
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
    public function getBrowseResult()
    {
        global $interface;
        $interface->assign('summId', $this->getUniqueID());
        $interface->assign('summTitle', $this->getTitle());
        $interface->assign('summUrl', $this->getLinkUrl());
        $interface->assign('bookCoverUrl', $this->getBookcoverUrl('medium'));
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

    public function getUniqueID(): string
    {
        return $this->nodeId > 0 ? 'islandora2-' . $this->nodeId : 'islandora2-unknown';
				// using dash to avoid html DOM problems when using a : colon character
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
			return null; //TODO Implement
	}

}
