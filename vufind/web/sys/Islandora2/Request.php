<?php

/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 *
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
use Pika\Logger;

class Request
{
    private $api_url;
    private $logger;
    protected ?int $nodeId;

    public function __construct($nodeId = null)
    {
        if ($nodeId) {
            $this->nodeId = $nodeId;
        }
        global $configArray;
        $this->logger = new Logger(__CLASS__);
        $baseUrl = $configArray['Islandora2']['url'] ?? '';
        $this->api_url = $baseUrl ? rtrim($baseUrl, '/') . '/pika-json/node/' : '';
    }

    /**
     * Fetch a node from the Islandora2 JSON endpoint.
     *
     * @param int $nodeId Identifier of the node to retrieve.
     * @return array|null Decoded node payload or null when the request fails.
     */
    public function fetch(?int $nodeId = null): ?array
    {
        if (!$nodeId) {
            $nodeId = $this->nodeId;
        }
        if ($nodeId !== $this->nodeId) {
            $this->nodeId = $nodeId;
        }
        if ($nodeId <= 0) {
            $this->logger->warning('Attempted to fetch Islandora node with invalid id.', ['nodeId' => $nodeId]);
            return null;
        }

        if (empty($this->api_url)) {
            $this->logger->error('Islandora2 URL is not configured.');
            return null;
        }

        $url       = $this->api_url . $nodeId;
        $curl      = new Curl();
        $response  = null;
        $curl->setUserAgent('pikaArchive');
        
        try {
            $response = $curl->get($url);
            /* Error checks */
            if ($curl->isCurlError()) {
                $this->logger->error('Curl error while fetching Islandora node.', [
                    'nodeId' => $nodeId,
                    'code'   => $curl->getCurlErrorCode(),
                    'error'  => $curl->getCurlErrorMessage(),
                ]);
                return null;
            }

            if ($curl->isError()) {
                $this->logger->warning('HTTP error returned by Islandora2 API.', [
                    'nodeId' => $nodeId,
                    'code'   => $curl->getHttpStatusCode(),
                ]);
                return null;
            }


            $statusCode = $curl->getHttpStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning('Unexpected HTTP status when fetching Islandora node.', [
                    'nodeId' => $nodeId,
                    'code'   => $statusCode,
                ]);
                return null;
            }
            
            /* Load the JSON payload */
            $body = null;
            
            $body = $curl->response;

            if ($body === null) {
                $body = $curl->getResponse();
            }

            if ($body === null) {
                $body = $curl->getRawResponse();
            }

            if ($body === null && $response !== null) {
                $body = $response;
            }

            $rawStringBody = is_string($body) ? $body : $curl->getRawResponse();
            if (!$this->validateContentLength($curl, $rawStringBody, (int)$nodeId)) {
                return null;
            }

            if (is_array($body)) {
                return $body;
            }

            if (is_object($body)) {
                $body = json_decode(json_encode($body), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('Failed to normalize Islandora node response object.', [
                        'nodeId' => $nodeId,
                        'error'  => json_last_error_msg(),
                    ]);
                    return null;
                }
                return $body;
            }

            if (!is_string($body) || trim($body) === '') {
                $this->logger->warning('Islandora2 API returned an empty response.', ['nodeId' => $nodeId]);
                return null;
            }

            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to decode Islandora node JSON response.', [
                    'nodeId' => $nodeId,
                    'error'  => json_last_error_msg(),
                    'body'   => substr($body, 0, 250),
                ]);
                return null;
            }

            return $decoded;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to query Islandora2 API.', [
                'nodeId'  => $nodeId,
                'message' => $exception->getMessage(),
            ]);
            return null;
        } finally {
            $curl->close();
        }
    }

    /**
     * Validate that the Content-Length header, when set, matches the body we received.
     */
    private function validateContentLength(Curl $curl, ?string $body, int $nodeId): bool
    {
        $expected = $this->getContentLengthHeader($curl);
        if ($expected === null || $expected < 0) {
            return true;
        }

        if ($body === null) {
            $this->logger->error('Islandora2 API declared response length but body is missing.', [
                'nodeId' => $nodeId,
                'expectedLength' => $expected,
            ]);
            return false;
        }

        $actual = strlen($body);
        if ($actual !== $expected) {
            $this->logger->error('Islandora2 API response length mismatch.', [
                'nodeId' => $nodeId,
                'expectedLength' => $expected,
                'actualLength' => $actual,
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

        // if (!is_array($headers)) {
        //     return null;
        // }

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
