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
 * Shared infrastructure for Islandora 2 API clients.
 *
 * Holds the common configuration (base URL, caching, logging, user agent) and
 * low-level helpers used by both the pika-json client (Request) and the Drupal
 * JSON:API client (JsonApiClient).
 */
abstract class AbstractApiClient
{
	protected string $baseUrl;
	protected Cache  $cache;
	protected Logger $logger;
	protected string $userAgent;
	protected int    $resourceTtl;
	protected int    $vocabularyTtl;

	public function __construct()
	{
		global $configArray;
		$this->logger        = new Logger(static::class);
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
	 * Normalise a raw Curl response body to an associative array.
	 */
	protected function decodeBody(mixed $body, string $type, int $id): ?array
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
	protected function validateContentLength(Curl $curl, ?string $body, string $type, int $id): bool
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
