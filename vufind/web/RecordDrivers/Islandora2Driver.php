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
require_once ROOT_DIR . '/sys/Islandora2/Request.php';

use Islandora2\Request;
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
	private const PLACEHOLDER_IMAGE = '/interface/themes/responsive/images/History.png';

	private Logger $logger;
	private ?Request $request = null;
	private int $nodeId = 0;
	private ?array $nodeData = null;
	private bool $nodeDataLoaded = false;
	//private ?float $solrScore = null;
	//private ?string $solrExplanation = null;
	private ?string $title = null;
	private ?string $description = null;
	private ?string $format = null;

	/**
	 * @param int|string|array $recordData
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
			$this->nodeId          = $this->extractNodeIdFromArray($recordData);
			//$this->solrScore       = isset($recordData['score']) ? (float)$recordData['score'] : null;
			//$this->solrExplanation = isset($recordData['explain']) ? (string)$recordData['explain'] : null;

			if (isset($recordData['node']) && is_array($recordData['node'])) {
				$this->nodeData       = $recordData['node'];
				$this->nodeDataLoaded = true;
			} elseif (isset($recordData['json']) && is_array($recordData['json'])) {
				$this->nodeData       = $recordData['json'];
				$this->nodeDataLoaded = true;
			}
		} elseif (is_numeric($recordData)) {
			$this->nodeId = (int)$recordData;
		} elseif (is_string($recordData) && ctype_digit($recordData)) {
			$this->nodeId = (int)$recordData;
		}

		if ($this->nodeId > 0) {
			$this->request = new Request($this->nodeId);
		} else {
			$this->logger->warning('Islandora2Driver initialised without a valid node id.', ['recordData' => $recordData]);
		}
	}

	/**
	 * Attempt to detect a node id in an array payload.
	 *
	 * @param array $recordData
	 * @return int
	 */
	private function extractNodeIdFromArray(array $recordData): int
	{
		$candidates = [
			'nodeId',
			'node_id',
			'nid',
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

	private function ensureRequest(): ?Request
	{
		if ($this->request === null && $this->nodeId > 0) {
			$this->request = new Request($this->nodeId);
		}
		return $this->request;
	}

	private function ensureNodeData(): bool
	{
		if ($this->nodeDataLoaded) {
			return $this->nodeData !== null;
		}
		$this->nodeDataLoaded = true;

		if ($this->nodeId <= 0) {
			$this->logger->warning('Cannot fetch Islandora2 node without a valid id.');
			return false;
		}

		$request = $this->ensureRequest();
		if ($request === null) {
			$this->logger->error('Islandora2 request helper is unavailable.');
			return false;
		}

		$data = $request->fetch($this->nodeId);
		if ($data === null) {
			$this->logger->warning('Failed to retrieve Islandora2 node.', ['nodeId' => $this->nodeId]);
			return false;
		}

		$this->nodeData = $data;
		return true;
	}

	/**
	 * @return array|null
	 */
	public function getNodeData(): ?array
	{
		$this->ensureNodeData();
		return $this->nodeData;
	}

	public function getNodeId(): int
	{
		return $this->nodeId;
	}

	/**
	 * Resolve a human-readable title for the node.
	 */
	private function resolveTitle(): string
	{
		if ($this->title !== null) {
			return $this->title;
		}

		$data = $this->getNodeData();
		if (!$data) {
			$this->title = $this->nodeId > 0 ? 'Islandora Node ' . $this->nodeId : '';
			return $this->title;
		}

		$title = $this->extractFirstString($data, [
			['title'],
			['label'],
			['data', 'attributes', 'title'],
			['data', 'attributes', 'label'],
			['attributes', 'title'],
			['attributes', 'field_title', 'value'],
			['attributes', 'name'],
			['attributes', 'field_display_title', 'value'],
		]);

		if ($title === null && isset($data['data']) && is_array($data['data'])) {
			$title = $this->extractFirstString($data['data'], [
				['attributes', 'title'],
				['attributes', 'label'],
			]);
		}

		if ($title === null && $this->nodeId > 0) {
			$title = 'Islandora Node ' . $this->nodeId;
		}

		$this->title = $title ?? '';
		return $this->title;
	}

	/**
	 * Resolve a textual description for summaries.
	 */
	private function resolveDescription(): string
	{
		if ($this->description !== null) {
			return $this->description;
		}

		$data = $this->getNodeData();
		if (!$data) {
			$this->description = '';
			return $this->description;
		}

		$description = $this->extractFirstString($data, [
			['attributes', 'description'],
			['attributes', 'field_description', 'processed'],
			['attributes', 'field_description', 'value'],
			['attributes', 'body', 'processed'],
			['attributes', 'body', 'value'],
			['data', 'attributes', 'description'],
		]);

		$this->description = $description ?? '';
		return $this->description;
	}

	/**
	 * Resolve a display format (e.g. photograph, image, document).
	 */
	private function resolveFormat(): string
	{
		if ($this->format !== null) {
			return $this->format;
		}

		$data = $this->getNodeData();
		if (!$data) {
			$this->format = 'Digital Resource';
			return $this->format;
		}

		$format = $this->extractFirstString($data, [
			['attributes', 'format'],
			['attributes', 'type'],
			['data', 'attributes', 'type'],
			['data', 'type'],
			['meta', 'bundle'],
		]);

		$this->format = $format ? ucfirst($format) : 'Digital Resource';
		return $this->format;
	}

	/**
	 * Determine a cover/thumbnail URL for the node.
	 *
	 * @param string $size
	 * @return string
	 */
	public function getBookcoverUrl($size = 'small')
	{
		$data = $this->getNodeData();

		if (!$data) {
			return self::PLACEHOLDER_IMAGE;
		}

		$candidates = [
			['attributes', 'thumbnail'],
			['attributes', 'field_thumbnail', 'url'],
			['attributes', 'field_media_image', 'url'],
			['attributes', 'field_image', 'url'],
			['data', 'attributes', 'thumbnail'],
			['data', 'attributes', 'image', 'url'],
			['meta', 'thumbnail'],
		];

		$coverUrl = $this->extractFirstString($data, $candidates);
		if ($coverUrl === null && isset($data['relationships']) && is_array($data['relationships'])) {
			$coverUrl = $this->resolveImageFromRelationships($data['relationships'], $size);
		}

		return $coverUrl ?? self::PLACEHOLDER_IMAGE;
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

	public function getListEntry($listId = null, $allowEdit = true)
	{
		global $interface;

		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('jquerySafeId', str_replace(':', '_', $this->getUniqueID()));
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summFormat', $this->getFormat());
		$interface->assign('summShortId', null);
		$interface->assign('summTitleStatement', null);
		$interface->assign('summAuthor', null);
		$interface->assign('summPublisher', null);
		$interface->assign('summPubDate', null);
		$interface->assign('$summSnippets', null);
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));
		$interface->assign('summAjaxStatus', false);
		$interface->assign('recordDriver', $this);

		if ($listId) {
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry                         = new UserListEntry();
			$listEntry->groupedWorkPermanentId = $this->getUniqueID();
			$listEntry->listId                 = $listId;
			if ($listEntry->find(true)) {
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
		if ($this->nodeId <= 0) {
			return '#';
		}
		return '/Archive2/' . urlencode((string)$this->nodeId);
	}

	public function getAbsoluteUrl()
	{
		global $configArray;
		global $library;

		$baseUrl = $configArray['Site']['url'] ?? '';
		if (!empty($library->catalogUrl ?? '')) {
			$scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
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
		$data = $this->getNodeData();
		if (!$data) {
			return [];
		}

		return [
			'@context' => 'https://schema.org',
			'@type'    => 'CreativeWork',
			'@id'      => $this->getAbsoluteUrl(),
			'name'     => $this->getTitle(),
			'description' => $this->getDescription(),
			'image'    => $this->getBookcoverUrl('large'),
		];
	}

	public function getSearchResult($view = 'list')
	{
		if ($view === 'covers') {
			return $this->getBrowseResult();
		}

		global $interface;
		$interface->assign('summId', $this->getUniqueID());
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('jquerySafeId', str_replace(':', '_', $this->getUniqueID()));
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summFormat', $this->getFormat());
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		global $configArray;
		if (!empty($configArray['System']['debugSolr'])) {
			// $interface->assign('summScore', $this->getScore());
			// $interface->assign('summExplain', $this->getExplain());
		}

		return 'RecordDrivers/Islandora/result.tpl';
	}

	public function getStaffView()
	{
		return null;
	}

	public function getTitle()
	{
		return $this->resolveTitle();
	}

	public function getDescription(): string
	{
		return $this->resolveDescription();
	}

	public function getFormat(): string
	{
		return $this->resolveFormat();
	}

	public function getUniqueID(): string
	{
		return $this->nodeId > 0 ? 'islandora2:' . $this->nodeId : 'islandora2:unknown';
	}

	// public function getScore(): ?float
	// {
	// 	return $this->solrScore;
	// }

	// public function getExplain(): ?string
	// {
	// 	return $this->solrExplanation;
	// }

	public function hasFullText(): bool
	{
		return false;
	}

	/**
	 * Extract the first scalar string value from the provided candidate paths.
	 *
	 * @param array $data
	 * @param array<int, array<int|string>> $paths
	 * @return string|null
	 */
	private function extractFirstString(array $data, array $paths): ?string
	{
		foreach ($paths as $path) {
			$value = $this->extractPathValue($data, $path);
			$value = $this->normaliseScalar($value);
			if ($value !== null) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Walk an array by key path.
	 *
	 * @param array $data
	 * @param array<int|string> $path
	 * @return mixed
	 */
	private function extractPathValue(array $data, array $path)
	{
		$current = $data;
		foreach ($path as $segment) {
			if (is_array($current) && array_key_exists($segment, $current)) {
				$current = $current[$segment];
			} elseif (is_array($current) && is_numeric($segment) && isset($current[(int)$segment])) {
				$current = $current[(int)$segment];
			} else {
				return null;
			}
		}

		return $current;
	}

	/**
	 * Normalise mixed values into a string when appropriate.
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	private function normaliseScalar($value): ?string
	{
		if (is_string($value) || is_numeric($value)) {
			$value = trim((string)$value);
			return $value === '' ? null : $value;
		}

		if (is_array($value)) {
			if (array_key_exists('value', $value)) {
				return $this->normaliseScalar($value['value']);
			}
			if (array_key_exists('processed', $value)) {
				return $this->normaliseScalar($value['processed']);
			}
			if (!empty($value)) {
				return $this->normaliseScalar(reset($value));
			}
		}

		return null;
	}

	/**
	 * Attempt to resolve a derivative image from relationship data.
	 *
	 * @param array $relationships
	 * @param string $size
	 * @return string|null
	 */
	private function resolveImageFromRelationships(array $relationships, string $size): ?string
	{
		foreach ($relationships as $relationship) {
			if (!is_array($relationship)) {
				continue;
			}
			$derived = $this->extractPathValue($relationship, ['data', 'attributes', 'uri']);
			$normalised = $this->normaliseScalar($derived);
			if ($normalised !== null) {
				return $normalised;
			}

			$derivatives = $this->extractPathValue($relationship, ['data', 'meta', 'derivatives']);
			if (is_array($derivatives)) {
				$sizeKey = $size === 'large' ? 'large' : ($size === 'medium' ? 'medium' : 'thumbnail');
				if (isset($derivatives[$sizeKey])) {
					$url = $this->normaliseScalar($derivatives[$sizeKey]);
					if ($url !== null) {
						return $url;
					}
				}
				foreach ($derivatives as $derivative) {
					$url = $this->normaliseScalar($derivative);
					if ($url !== null) {
						return $url;
					}
				}
			}
		}

		return null;
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

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $isHomePickupRecord, $relatedUrls = null)
	{
		return [];
	}
}
