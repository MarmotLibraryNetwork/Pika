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
use Curl\MultiCurl;

/**
 * Fetches resources from the Islandora 2 pika-json API.
 *
 * Supports the following endpoints:
 *   /pika-json/node/{id}
 *   /pika-json/taxonomy/{id}
 *   /pika-json/node/{nid}/children
 *   /pika-json/taxonomy/{tid}/nodes
 */
class Request extends AbstractApiClient
{
	/**
	 * Fetch a resource from the Islandora 2 JSON API.
	 *
	 * @param string $type Either 'node' or 'taxonomy'.
	 * @param int    $id   Node ID or taxonomy term ID.
	 * @return array|null  Decoded payload, or null on failure.
	 */
	public function fetch(string $type, int $id): ?array
	{
		if ($id <= 0) {
			$this->logger->warning('Attempted to fetch Islandora resource with invalid id.', [
				'type' => $type,
				'id'   => $id,
			]);
			return null;
		}

		if (empty($this->baseUrl)) {
			$this->logger->error('Islandora2 URL is not configured.');
			return null;
		}

		$cacheKey = 'islandora2_' . $type . '_' . $id;
		if (!isset($_REQUEST['reload'])) {
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return $cached;
			}
		}

		$url  = $this->baseUrl . '/pika-json/' . $type . '/' . $id;
		$curl = new Curl();
		$curl->setUserAgent($this->userAgent);

		try {
			$body = $curl->get($url);

			if ($curl->isCurlError()) {
				$this->logger->error('Curl error while fetching Islandora resource.', [
					'type'  => $type,
					'id'    => $id,
					'code'  => $curl->getCurlErrorCode(),
					'error' => $curl->getCurlErrorMessage(),
				]);
				return null;
			}

			if ($curl->isError()) {
				$this->logger->warning('HTTP error returned by Islandora2 API.', [
					'type' => $type,
					'id'   => $id,
					'code' => $curl->getHttpStatusCode(),
				]);
				return null;
			}

			$statusCode = $curl->getHttpStatusCode();
			if ($statusCode !== 200) {
				$this->logger->warning('Unexpected HTTP status when fetching Islandora resource.', [
					'type' => $type,
					'id'   => $id,
					'code' => $statusCode,
				]);
				return null;
			}

			$rawStringBody = is_string($body) ? $body : $curl->getRawResponse();
			if (!$this->validateContentLength($curl, $rawStringBody, $type, $id)) {
				return null;
			}

			$result = $this->decodeBody($body, $type, $id);
			if ($result !== null) {
				$this->cache->set($cacheKey, $result, $this->resourceTtl);
			}
			return $result;
		} catch (\Throwable $exception) {
			$this->logger->error('Failed to query Islandora2 API.', [
				'type'    => $type,
				'id'      => $id,
				'message' => $exception->getMessage(),
			]);
			return null;
		} finally {
			$curl->close();
		}
	}

	/**
	 * Fetch all children of a node across as many pages as needed.
	 *
	 * Page 1 is fetched synchronously to learn the total; any remaining pages
	 * are requested in parallel via MultiCurl. Each page is individually cached
	 * so subsequent calls reuse cached data without making any network requests.
	 *
	 * @param int $nid      Parent node ID.
	 * @param int $pageSize Items per page (max 250 per API call).
	 * @return array        Flat array of raw child node payloads.
	 */
	public function fetchAllChildren(int $nid, int $pageSize = 250): array
	{
		$first = $this->fetchChildren($nid, 1, $pageSize);
		if ($first === null) {
			return [];
		}

		$total    = (int)($first['total'] ?? 0);
		$children = $first['children'] ?? [];

		if (count($children) >= $total) {
			return $children;
		}

		$totalPages  = (int)ceil($total / $pageSize);
		$multiCurl   = new MultiCurl();
		if (!empty($this->userAgent)) {
			$multiCurl->setUserAgent($this->userAgent);
		}
		$pageResults = [];

		for ($page = 2; $page <= $totalPages; $page++) {
			$cacheKey = 'islandora2_node_children_' . $nid . '_p' . $page . '_n' . $pageSize;
			if (!isset($_REQUEST['reload'])) {
				$cached = $this->cache->get($cacheKey);
				if ($cached !== null) {
					$pageResults[$page] = $cached['children'] ?? [];
					continue;
				}
			}

			$url  = $this->baseUrl . '/pika-json/node/' . $nid . '/children?page=' . $page . '&number=' . $pageSize;
			$curl = $multiCurl->addGet($url);
			$p    = $page;
			$ck   = $cacheKey;
			$curl->success(function ($instance) use (&$pageResults, $p, $nid, $ck) {
				$result = $this->decodeBody($instance->response, 'node-children', $nid);
				if ($result !== null) {
					$this->cache->set($ck, $result, $this->resourceTtl);
					$pageResults[$p] = $result['children'] ?? [];
				}
			});
			$curl->error(function ($instance) use ($p, $nid) {
				$this->logger->warning('MultiCurl error fetching children page.', [
					'nid'  => $nid,
					'page' => $p,
					'code' => $instance->getHttpStatusCode(),
				]);
			});
		}

		$multiCurl->start();
		$multiCurl->close();

		ksort($pageResults);
		foreach ($pageResults as $pageChildren) {
			$children = array_merge($children, $pageChildren);
		}

		return $children;
	}

	/**
	 * Yield every child of a node one at a time, fetching pages sequentially.
	 *
	 * Unlike fetchAllChildren(), which fetches remaining pages in parallel and
	 * returns them as a single array, this holds at most one page of raw payloads
	 * in memory at once. Use it when a caller can process and discard each child
	 * (e.g. aggregating a field across a large collection) so memory does not grow
	 * with the collection size. Each page is cached the same way as fetchChildren().
	 *
	 * @param int $nid      Parent node ID.
	 * @param int $pageSize Items per page (max 250 per API call).
	 * @return \Generator Yields raw child node payloads.
	 */
	public function streamChildren(int $nid, int $pageSize = 250): \Generator
	{
		if ($nid <= 0) {
			return;
		}

		$page    = 1;
		$yielded = 0;

		do {
			$response = $this->fetchChildren($nid, $page, $pageSize);
			if ($response === null) {
				return;
			}

			$children = $response['children'] ?? [];
			if (empty($children)) {
				return;
			}

			foreach ($children as $child) {
				yield $child;
				$yielded++;
			}

			$total = (int)($response['total'] ?? 0);
			$page++;
		} while ($yielded < $total);
	}

	/**
	 * Fetch nodes that reference a taxonomy term.
	 *
	 * Returns an empty array when no related nodes exist (404), null on other failures.
	 *
	 * @param int $tid Term ID.
	 * @return array|null Array of node records, empty array when none, null on error.
	 */
	public function fetchRelatedNodes(int $tid): ?array
	{
		if ($tid <= 0 || empty($this->baseUrl)) {
			return null;
		}

		$cacheKey = 'islandora2_related_nodes_' . $tid;
		if (!isset($_REQUEST['reload'])) {
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return $cached;
			}
		}

		$url  = $this->baseUrl . '/pika-json/taxonomy/' . $tid . '/nodes';
		$curl = new Curl();
		$curl->setUserAgent($this->userAgent);

		try {
			$body = $curl->get($url);

			if ($curl->isCurlError()) {
				$this->logger->warning('Curl error while fetching related nodes for taxonomy term.', [
					'tid'   => $tid,
					'code'  => $curl->getCurlErrorCode(),
					'error' => $curl->getCurlErrorMessage(),
				]);
				return null;
			}

			$statusCode = $curl->getHttpStatusCode();
			if ($statusCode === 404) {
				$this->cache->set($cacheKey, [], $this->resourceTtl);
				return [];
			}

			if ($curl->isError()) {
				$this->logger->warning('HTTP error returned when fetching related nodes.', [
					'tid'  => $tid,
					'code' => $statusCode,
				]);
				return null;
			}

			if ($statusCode !== 200) {
				$this->logger->warning('Unexpected HTTP status when fetching related nodes.', [
					'tid'  => $tid,
					'code' => $statusCode,
				]);
				return null;
			}

			if (!is_string($body) || trim($body) === '') {
				$this->cache->set($cacheKey, [], $this->resourceTtl);
				return [];
			}

			$result = $this->decodeBody($body, 'taxonomy-nodes', $tid);
			if ($result !== null) {
				$this->cache->set($cacheKey, $result, $this->resourceTtl);
			}
			return $result;
		} catch (\Throwable $exception) {
			$this->logger->error('Exception while fetching related nodes for taxonomy term.', [
				'tid'     => $tid,
				'message' => $exception->getMessage(),
			]);
			return null;
		} finally {
			$curl->close();
		}
	}

	/**
	 * Fetch a paginated list of child nodes for a parent node.
	 *
	 * @param int $nid    Parent node ID.
	 * @param int $page   1-indexed page number.
	 * @param int $number Items per page.
	 * @return array|null Response array with 'total', 'count', 'page', 'children' keys, or null on failure.
	 */
	public function fetchChildren(int $nid, int $page = 1, int $number = 10): ?array
	{
		if ($nid <= 0 || empty($this->baseUrl)) {
			return null;
		}

		$cacheKey = 'islandora2_node_children_' . $nid . '_p' . $page . '_n' . $number;
		if (!isset($_REQUEST['reload'])) {
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return $cached;
			}
		}

		$url  = $this->baseUrl . '/pika-json/node/' . $nid . '/children?page=' . $page . '&number=' . $number;
		$curl = new Curl();
		$curl->setUserAgent($this->userAgent);

		try {
			$body = $curl->get($url);

			if ($curl->isCurlError()) {
				$this->logger->error('Curl error while fetching children for node.', [
					'nid'   => $nid,
					'page'  => $page,
					'code'  => $curl->getCurlErrorCode(),
					'error' => $curl->getCurlErrorMessage(),
				]);
				return null;
			}

			$statusCode = $curl->getHttpStatusCode();
			if ($statusCode === 404) {
				return null;
			}

			if ($curl->isError()) {
				$this->logger->warning('HTTP error returned when fetching children.', [
					'nid'  => $nid,
					'code' => $statusCode,
				]);
				return null;
			}

			if ($statusCode !== 200) {
				$this->logger->warning('Unexpected HTTP status when fetching children.', [
					'nid'  => $nid,
					'code' => $statusCode,
				]);
				return null;
			}

			$result = $this->decodeBody($body, 'node-children', $nid);
			if ($result !== null) {
				$this->cache->set($cacheKey, $result, $this->resourceTtl);
			}
			return $result;
		} catch (\Throwable $exception) {
			$this->logger->error('Failed to fetch children for Islandora node.', [
				'nid'     => $nid,
				'message' => $exception->getMessage(),
			]);
			return null;
		} finally {
			$curl->close();
		}
	}
}
