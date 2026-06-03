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

namespace Islandora2;

require_once ROOT_DIR . '/sys/Islandora2/AbstractApiClient.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';

use Curl\Curl;

/**
 * Fetches resources from Drupal's JSON:API endpoint.
 *
 * Handles filtered queries, sparse fieldsets, and included entity resolution
 * for efficient server-side filtering of Islandora content.
 *
 * JSON:API base: /jsonapi/
 */
class JsonApiClient extends AbstractApiClient
{
	private int $jsonApiTtl = 2592000; // 30 days

	/** Media use term name whose image represents a node's display thumbnail. */
	private string $thumbnailMediaUse = 'Thumbnail Image';

	/**
	 * Fetch all taxonomy terms for a given vocabulary from the Drupal JSON:API.
	 *
	 * Returns an array of ['tid' => int, 'name' => string] entries sorted by name,
	 * or null on failure. Follows JSON:API pagination via links.next.
	 *
	 * @param string $vocabId  Vocabulary machine name (e.g. 'corporate_body').
	 * @return array[]|null
	 */
	public function fetchVocabulary(string $vocabId): ?array
	{
		if (empty($this->baseUrl)) {
			$this->logger->error('Islandora2 URL is not configured.');
			return null;
		}

		$cacheKey = 'islandora2_vocabulary_' . $vocabId;
		if (!isset($_REQUEST['reload'])) {
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return $cached;
			}
		}

		$url = $this->baseUrl . '/jsonapi/taxonomy_term/' . urlencode($vocabId)
			. '?fields[taxonomy_term--' . urlencode($vocabId) . ']=name,drupal_internal__tid'
			. '&sort=name'
			. '&page[limit]=100';

		$terms = [];

		do {
			$curl = new Curl();
			$curl->setUserAgent($this->userAgent);

			try {
				$body = $curl->get($url);

				if ($curl->isCurlError()) {
					$this->logger->error('Curl error fetching vocabulary from JSON:API.', [
						'vocabId' => $vocabId,
						'url'     => $url,
						'code'    => $curl->getCurlErrorCode(),
						'error'   => $curl->getCurlErrorMessage(),
					]);
					return null;
				}

				if ($curl->isError()) {
					$this->logger->warning('HTTP error from JSON:API while fetching vocabulary.', [
						'vocab' => $vocabId,
						'code'  => $curl->getHttpStatusCode(),
					]);
					return null;
				}

				$decoded = $this->decodeBody($body, 'vocabulary', 0);
				if ($decoded === null) {
					return null;
				}

				foreach ($decoded['data'] ?? [] as $item) {
					$tid  = $item['attributes']['drupal_internal__tid'] ?? null;
					$name = $item['attributes']['name'] ?? null;
					if (is_int($tid) && is_string($name) && $name !== '') {
						$terms[] = ['tid' => $tid, 'name' => $name];
					}
				}

				$url = $decoded['links']['next']['href'] ?? null;
			} catch (\Throwable $e) {
				$this->logger->error('Exception while fetching vocabulary from JSON:API.', [
					'vocab'   => $vocabId,
					'message' => $e->getMessage(),
				]);
				return null;
			} finally {
				$curl->close();
			}
		} while ($url !== null);

		$this->cache->set($cacheKey, $terms, $this->jsonApiTtl);
		return $terms;
	}

	/**
	 * Fetch taxonomy terms referenced by a specific field across all children of a node.
	 *
	 * Uses JSON:API server-side filtering to return only children where the field is
	 * populated, with sparse fieldsets to minimise payload size. Results are deduplicated
	 * by tid across all matching children and all pages. Each child node contributes at
	 * most 1 to a term's count, even if it references the same term multiple times.
	 *
	 * Each returned entry has:
	 *   'tid'        → int    taxonomy term ID
	 *   'name'       → string term display name
	 *   'vocabulary' → string vocabulary machine name (e.g. 'geo_location', 'event')
	 *   'count'      → int    number of children referencing this term
	 *
	 * @param int    $nid         Parent node ID.
	 * @param string $filterField Drupal field machine name (e.g. 'field_related_place').
	 * @param int    $pageLimit   JSON:API page size (default 100).
	 * @return array Flat deduplicated array of term entries, empty on failure.
	 */
	public function fetchChildrenFiltered(int $nid, string $filterField, int $pageLimit = 100): array
	{
		if ($nid <= 0 || empty($this->baseUrl)) {
			return [];
		}

		$cacheKey = 'islandora2_children_filtered_' . $nid . '_' . md5($filterField);
		if (!isset($_REQUEST['reload'])) {
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return $cached;
			}
		}

		$url = $this->baseUrl . '/jsonapi/node/islandora_object'
			. '?filter[parent][condition][path]=field_member_of.meta.drupal_internal__target_id'
			. '&filter[parent][condition][operator]=' . rawurlencode('=')
			. '&filter[parent][condition][value]=' . $nid
			. '&filter[field][condition][path]=' . rawurlencode($filterField)
			. '&filter[field][condition][operator]=' . rawurlencode('IS NOT NULL')
			. '&fields[node--islandora_object]=drupal_internal__nid,' . rawurlencode($filterField)
			. '&include=' . rawurlencode($filterField)
			. '&page[limit]=' . $pageLimit;

		// Keyed by tid so we can deduplicate and increment counts in one pass.
		$terms = [];

		do {
			$curl = new Curl();
			$curl->setUserAgent($this->userAgent);

			try {
				$body = $curl->get($url);

				if ($curl->isCurlError()) {
					$this->logger->error('Curl error fetching filtered children from JSON:API.', [
						'nid'         => $nid,
						'filterField' => $filterField,
						'code'        => $curl->getCurlErrorCode(),
						'error'       => $curl->getCurlErrorMessage(),
					]);
					return array_values($terms);
				}

				if ($curl->isError()) {
					$this->logger->warning('HTTP error fetching filtered children from JSON:API.', [
						'nid'  => $nid,
						'code' => $curl->getHttpStatusCode(),
					]);
					return array_values($terms);
				}

				$decoded = $this->decodeBody($body, 'json-api-filtered', $nid);
				if ($decoded === null) {
					return array_values($terms);
				}

				// Build a UUID → term entry index from the included taxonomy term entities.
				// The vocabulary name is extracted from the JSON:API type string
				// (e.g. "taxonomy_term--geo_location" → "geo_location").
				$included = [];
				foreach ($decoded['included'] ?? [] as $entity) {
					$uuid = $entity['id'] ?? null;
					if ($uuid === null) {
						continue;
					}
					$tid  = $entity['attributes']['drupal_internal__tid'] ?? null;
					$name = $entity['attributes']['name'] ?? null;
					$type = $entity['type'] ?? '';
					$vocabulary = str_starts_with($type, 'taxonomy_term--')
						? substr($type, strlen('taxonomy_term--'))
						: '';
					if ($tid !== null && $name !== null) {
						$included[$uuid] = [
							'tid'        => (int)$tid,
							'name'       => (string)$name,
							'vocabulary' => $vocabulary,
						];
					}
				}

				// Resolve each node's field references against the included index.
				// $nodeTids tracks which terms this node has already contributed to,
				// ensuring each child counts at most once per term.
				foreach ($decoded['data'] ?? [] as $node) {
					$refs = $node['relationships'][$filterField]['data'] ?? [];
					// JSON:API returns a single object for to-one, array for to-many
					if (isset($refs['id'])) {
						$refs = [$refs];
					}
					$nodeTids = [];
					foreach ($refs as $ref) {
						$uuid = $ref['id'] ?? null;
						if ($uuid === null || !isset($included[$uuid])) {
							continue;
						}
						$tid = $included[$uuid]['tid'];
						if (!isset($nodeTids[$tid])) {
							$nodeTids[$tid] = true;
							if (!isset($terms[$tid])) {
								$terms[$tid] = array_merge($included[$uuid], ['count' => 0]);
							}
							$terms[$tid]['count']++;
						}
					}
				}

				$url = $decoded['links']['next']['href'] ?? null;
			} catch (\Throwable $e) {
				$this->logger->error('Exception fetching filtered children from JSON:API.', [
					'nid'     => $nid,
					'message' => $e->getMessage(),
				]);
				return array_values($terms);
			} finally {
				$curl->close();
			}
		} while ($url !== null);

		$result = array_values($terms);
		$this->cache->set($cacheKey, $result, $this->jsonApiTtl);
		return $result;
	}

	/**
	 * Fetch lightweight map markers for every child of a node that has geographic
	 * coordinates set via field_coordinates.
	 *
	 * Uses JSON:API server-side filtering (children of $nid where field_coordinates
	 * is populated) plus sparse fieldsets so only the geocoded children — and only
	 * the fields a marker needs — cross the wire. Thumbnails, which live on related
	 * media entities (a reverse reference that cannot be included from the node
	 * side), are resolved in a single batched media query, not per child.
	 *
	 * Each returned entry has:
	 *   'nid'       → int    child node ID
	 *   'title'     → string display title
	 *   'url'       → string relative Archive2 detail URL
	 *   'latitude'  → float
	 *   'longitude' → float
	 *   'thumbnail' → string absolute thumbnail URL, or '' when none exists
	 *
	 * @param int $nid       Parent node ID.
	 * @param int $pageLimit JSON:API page size (default 100).
	 * @return array Flat array of marker entries, empty on failure or when none have coordinates.
	 */
	public function fetchChildMarkers(int $nid, int $pageLimit = 100): array
	{
		if ($nid <= 0 || empty($this->baseUrl)) {
			return [];
		}

		$cacheKey = 'islandora2_child_markers_' . $nid;
		if (!isset($_REQUEST['reload'])) {
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return $cached;
			}
		}

		$markers = $this->fetchMarkerNodes($nid, $pageLimit);
		if (!empty($markers)) {
			$this->attachThumbnails($markers);
		}

		$result = array_values($markers);
		$this->cache->set($cacheKey, $result, $this->jsonApiTtl);
		return $result;
	}

	/**
	 * Fetch the geocoded children of a node as marker entries keyed by child nid.
	 *
	 * field_coordinates is a geofield exposed as an array of
	 * {lat, lng, data, value}; the first entry's lat/lng are used. The detail URL
	 * is derived from the legacy-resource-type name (falling back to the model
	 * name), mirroring I2Object::getDisplayModel() / getObjRelativeUrl().
	 *
	 * @param int $nid       Parent node ID.
	 * @param int $pageLimit JSON:API page size.
	 * @return array Markers keyed by nid, each with an empty 'thumbnail' placeholder.
	 */
	private function fetchMarkerNodes(int $nid, int $pageLimit): array
	{
		$url = $this->baseUrl . '/jsonapi/node/islandora_object'
			. '?filter[parent][condition][path]=field_member_of.meta.drupal_internal__target_id'
			. '&filter[parent][condition][value]=' . $nid
			. '&filter[coords][condition][path]=' . rawurlencode('field_coordinates.lat')
			. '&filter[coords][condition][operator]=' . rawurlencode('IS NOT NULL')
			. '&fields[node--islandora_object]=' . rawurlencode('drupal_internal__nid,title,field_coordinates,field_model,field_legacy_resource_type')
			. '&include=' . rawurlencode('field_model,field_legacy_resource_type')
			. '&fields[taxonomy_term--islandora_models]=name'
			. '&fields[taxonomy_term--legacy_resource_type]=name'
			. '&page[limit]=' . $pageLimit;

		$markers = [];

		do {
			$decoded = $this->requestJson($url, 'child-markers', $nid);
			if ($decoded === null) {
				break;
			}

			// Index included taxonomy terms (model + legacy resource type) by UUID → name.
			$termNames = [];
			foreach ($decoded['included'] ?? [] as $inc) {
				if (str_starts_with($inc['type'] ?? '', 'taxonomy_term--')) {
					$termNames[$inc['id'] ?? ''] = $inc['attributes']['name'] ?? null;
				}
			}

			foreach ($decoded['data'] ?? [] as $node) {
				$attr     = $node['attributes'] ?? [];
				$childNid = $attr['drupal_internal__nid'] ?? null;
				$coords   = $attr['field_coordinates'][0] ?? null;
				if ($childNid === null || !is_array($coords)) {
					continue;
				}
				$lat = $coords['lat'] ?? null;
				$lng = $coords['lng'] ?? null;
				if ($lat === null || $lng === null) {
					continue;
				}
				$markers[(int)$childNid] = [
					'nid'       => (int)$childNid,
					'title'     => (string)($attr['title'] ?? ''),
					'url'       => $this->buildDetailUrl((int)$childNid, $node['relationships'] ?? [], $termNames),
					'latitude'  => (float)$lat,
					'longitude' => (float)$lng,
					'thumbnail' => '',
				];
			}

			$url = $decoded['links']['next']['href'] ?? null;
		} while ($url !== null);

		return $markers;
	}

	/**
	 * Build the Archive2 detail URL for a node, mirroring getObjRelativeUrl():
	 * prefer the legacy-resource-type term name, fall back to the model term name,
	 * then map it through ISLANDORA2_DISPLAY_MODEL_URL_MAP.
	 *
	 * @param int   $nid           Child node ID.
	 * @param array $relationships JSON:API relationships block for the node.
	 * @param array $termNames     UUID → term name index from the included terms.
	 * @return string Relative URL, or '#' when the node ID is invalid.
	 */
	private function buildDetailUrl(int $nid, array $relationships, array $termNames): string
	{
		if ($nid <= 0) {
			return '#';
		}

		$legacy = $relationships['field_legacy_resource_type']['data'] ?? null;
		$model  = $relationships['field_model']['data'] ?? null;
		// Either relationship may be a to-one object or a to-many list.
		$legacyUuid = is_array($legacy) ? ($legacy['id'] ?? ($legacy[0]['id'] ?? null)) : null;
		$modelUuid  = is_array($model)  ? ($model['id']  ?? ($model[0]['id']  ?? null)) : null;

		$displayModel = strtolower((string)($termNames[$legacyUuid] ?? $termNames[$modelUuid] ?? ''));
		$segment      = ISLANDORA2_DISPLAY_MODEL_URL_MAP[$displayModel] ?? $displayModel;

		return '/Archive2/' . $segment . '/' . urlencode((string)$nid);
	}

	/**
	 * Resolve display thumbnails for a set of marker nodes via batched media queries.
	 *
	 * Thumbnails are media that reference the node through field_media_of, so they
	 * are fetched from /jsonapi/media/image filtered by the marker nids (chunked to
	 * keep URLs bounded) and the "Thumbnail Image" media use, with the thumbnail
	 * file included. Markers without a thumbnail keep their empty placeholder.
	 *
	 * @param array $markers Markers keyed by nid; modified in place to set 'thumbnail'.
	 */
	private function attachThumbnails(array &$markers): void
	{
		foreach (array_chunk(array_keys($markers), 50) as $chunk) {
			$url = $this->baseUrl . '/jsonapi/media/image'
				. '?filter[node][condition][path]=field_media_of.meta.drupal_internal__target_id'
				. '&filter[node][condition][operator]=IN'
				. $this->buildInValues('node', $chunk)
				. '&filter[use][condition][path]=field_media_use.name'
				. '&filter[use][condition][value]=' . rawurlencode($this->thumbnailMediaUse)
				. '&include=thumbnail'
				. '&fields[media--image]=' . rawurlencode('thumbnail,field_media_of')
				. '&fields[file--file]=uri'
				. '&page[limit]=50';

			do {
				$decoded = $this->requestJson($url, 'child-marker-thumbnails', 0);
				if ($decoded === null) {
					break;
				}

				// Index the included thumbnail file entities by UUID → relative URL.
				$fileUrls = [];
				foreach ($decoded['included'] ?? [] as $inc) {
					if (($inc['type'] ?? '') === 'file--file') {
						$fileUrls[$inc['id'] ?? ''] = $inc['attributes']['uri']['url'] ?? null;
					}
				}

				foreach ($decoded['data'] ?? [] as $media) {
					$rels      = $media['relationships'] ?? [];
					$targetNid = $rels['field_media_of']['data'][0]['meta']['drupal_internal__target_id'] ?? null;
					if ($targetNid === null || empty($markers[(int)$targetNid])) {
						continue;
					}
					if ($markers[(int)$targetNid]['thumbnail'] !== '') {
						continue; // first thumbnail wins
					}
					$fileUuid = $rels['thumbnail']['data']['id'] ?? null;
					$relUrl   = $fileUuid !== null ? ($fileUrls[$fileUuid] ?? null) : null;
					if (is_string($relUrl) && $relUrl !== '') {
						$markers[(int)$targetNid]['thumbnail'] = $this->baseUrl . $relUrl;
					}
				}

				$url = $decoded['links']['next']['href'] ?? null;
			} while ($url !== null);
		}
	}

	/**
	 * Build the repeated &filter[name][condition][value][]=... query segment for an IN filter.
	 *
	 * @param string $filterName Filter identifier used in the query string.
	 * @param array  $values     Scalar values to include.
	 * @return string Query-string fragment beginning with '&', empty when no values.
	 */
	private function buildInValues(string $filterName, array $values): string
	{
		$fragment = '';
		foreach ($values as $value) {
			$fragment .= '&filter[' . $filterName . '][condition][value][]=' . rawurlencode((string)$value);
		}
		return $fragment;
	}

	/**
	 * Perform a GET against the JSON:API and return the decoded body, or null on any error.
	 *
	 * Centralises the curl lifecycle (user agent, error checks, decode, close) shared
	 * by the marker and thumbnail queries.
	 *
	 * @param string $url     Fully-qualified JSON:API URL.
	 * @param string $context Short label used in log messages.
	 * @param int    $id      Identifier for log context (e.g. parent nid), 0 when not applicable.
	 * @return array|null Decoded response, or null on failure.
	 */
	private function requestJson(string $url, string $context, int $id): ?array
	{
		$curl = new Curl();
		$curl->setUserAgent($this->userAgent);

		try {
			$body = $curl->get($url);

			if ($curl->isCurlError()) {
				$this->logger->error('Curl error fetching ' . $context . ' from JSON:API.', [
					'id'    => $id,
					'code'  => $curl->getCurlErrorCode(),
					'error' => $curl->getCurlErrorMessage(),
				]);
				return null;
			}

			if ($curl->isError()) {
				$this->logger->warning('HTTP error fetching ' . $context . ' from JSON:API.', [
					'id'   => $id,
					'code' => $curl->getHttpStatusCode(),
				]);
				return null;
			}

			return $this->decodeBody($body, $context, $id);
		} catch (\Throwable $e) {
			$this->logger->error('Exception fetching ' . $context . ' from JSON:API.', [
				'id'      => $id,
				'message' => $e->getMessage(),
			]);
			return null;
		} finally {
			$curl->close();
		}
	}

}
