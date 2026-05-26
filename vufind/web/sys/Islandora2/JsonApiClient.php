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

}
