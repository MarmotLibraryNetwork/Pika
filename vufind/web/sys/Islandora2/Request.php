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

use Curl\Curl;
use Pika\Cache;
use Pika\Logger;

/**
 * Fetches resources from the Islandora 2 pika-json API.
 *
 * Supports two resource types:
 *   'node'     → /pika-json/node/{id}
 *   'taxonomy' → /pika-json/taxonomy/{id}
 */
class Request
{
	private string $baseUrl;
	private Cache  $cache;
	private Logger $logger;
	private string $userAgent;
	private int    $resourceTtl;
	private int    $vocabularyTtl;

	public function __construct()
	{
		global $configArray;
		$this->logger        = new Logger(__CLASS__);
		$this->cache         = new Cache();
		$this->userAgent     = $configArray['Islandora2']['userAgent'] ?? '';
		$this->resourceTtl   = (int)($configArray['Caching']['islandora2_resource']   ?? 600);
		$this->vocabularyTtl = (int)($configArray['Caching']['islandora2_vocabulary'] ?? 3600);

		$url = $configArray['Islandora2']['url'] ?? '';
		if (empty($url)) {
			$this->logger->error('Islandora2 [url] is not configured in config.ini. All API requests will fail.');
		}
		$this->baseUrl = rtrim($url, '/');
	}

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
						'vocabId'   => $vocabId,
						'url'   => $url,
						'code'  => $curl->getCurlErrorCode(),
						'error' => $curl->getCurlErrorMessage(),
					]);
					return null;
				}

				if ($curl->isError()) {
					$this->logger->warning('HTTP error from JSON:API while fetching vocabulary.', [
						'vocab'  => $vocabId,
						'code' => $curl->getHttpStatusCode(),
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
					'vocab'     => $vocabId,
					'message' => $e->getMessage(),
				]);
				return null;
			} finally {
				$curl->close();
			}
		} while ($url !== null);

		$this->cache->set($cacheKey, $terms, $this->vocabularyTtl);
		return $terms;
	}
	

	/**
	 * Normalise a raw Curl response body to an associative array.
	 */
	private function decodeBody(mixed $body, string $type, int $id): ?array
	{
		if (is_array($body)) {
			return $body;
		}

		if (is_object($body)) {
			$body = json_decode(json_encode($body), true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$this->logger->error('Failed to normalize Islandora response object.', [
					'type'  => $type,
					'id'    => $id,
					'error' => json_last_error_msg(),
				]);
				return null;
			}
			return $body;
		}

		if (!is_string($body) || trim($body) === '') {
			$this->logger->warning('Islandora2 API returned an empty response.', [
				'type' => $type,
				'id'   => $id,
			]);
			return null;
		}

		$decoded = json_decode($body, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->logger->error('Failed to decode Islandora JSON response.', [
				'type'  => $type,
				'id'    => $id,
				'error' => json_last_error_msg(),
				'body'  => substr($body, 0, 250),
			]);
			return null;
		}

		return $decoded;
	}

	/**
	 * Validate that the Content-Length header, when set, matches the body we received.
	 */
	private function validateContentLength(Curl $curl, ?string $body, string $type, int $id): bool
	{
		$expected = $this->getContentLengthHeader($curl);
		if ($expected === null || $expected < 0) {
			return true;
		}

		if ($body === null) {
			$this->logger->error('Islandora2 API declared response length but body is missing.', [
				'type'           => $type,
				'id'             => $id,
				'expectedLength' => $expected,
			]);
			return false;
		}

		$actual = strlen($body);
		if ($actual !== $expected) {
			$this->logger->error('Islandora2 API response length mismatch.', [
				'type'           => $type,
				'id'             => $id,
				'expectedLength' => $expected,
				'actualLength'   => $actual,
			]);
			return false;
		}

		return true;
	}

	/**
	 * Extract the Content-Length header from the Curl response headers.
	 */
	private function getContentLengthHeader(Curl $curl): ?int
	{
		if (!method_exists($curl, 'getResponseHeaders')) {
			return null;
		}

		$headers = $curl->getResponseHeaders();

		if (!is_array($headers)) {
			return null;
		}

		foreach ($headers as $name => $value) {
			if (strcasecmp((string)$name, 'Content-Length') !== 0) {
				continue;
			}

			if (is_array($value)) {
				$value = end($value);
			}

			if (is_numeric($value)) {
				return (int)$value;
			}
		}

		return null;
	}
}
