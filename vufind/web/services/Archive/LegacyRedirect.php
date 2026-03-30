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

require_once ROOT_DIR . '/sys/Islandora2/Functions.php';

use Pika\Logger;

/**
 * Issues a 301 Moved Permanently redirect from a legacy Islandora 1 Archive URL
 * to its Islandora 2 Archive2 equivalent.
 *
 * The legacy URL contains the PID in the 'id' request parameter, e.g.:
 *   /Archive/fortlewis%3A10526/Postcard
 *
 * This class queries the Islandora 2 JSON:API filtering by field_pid to resolve
 * the node ID and display model, then redirects to /Archive2/{segment}/{nid}.
 *
 * To redirect all actions in a legacy Archive service class, replace its launch()
 * body with a single delegation call:
 *   (new Archive_LegacyRedirect())->launch();
 *
 * Or let the class extend Archive_LegacyRedirect directly instead of Archive_Object.
 */
class Archive_LegacyRedirect extends Action
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger(__CLASS__);
    }

    public function launch(): void
    {
        global $configArray;
        global $interface;

        $pid = urldecode($_REQUEST['id'] ?? '');

        if (empty($pid)) {
            $this->logger->warning('LegacyRedirect called without a PID.');
            $interface->assign('pid', '');
            $interface->assign('newUrl', null);
            $this->display('legacyRedirect.tpl', 'Page Permanently Moved', false);
            return;
        }

        $result = $this->resolveByPid($pid);

        if ($result === null) {
            $this->logger->warning('Could not resolve legacy PID to Archive2 URL.', ['pid' => $pid]);
            $interface->assign('pid', $pid);
            $interface->assign('newUrl', null);
            $this->display('legacyRedirect.tpl', 'Page Permanently Moved', false);
            return;
        }

        [$nid, $displayModel] = $result;
        $segment = ISLANDORA2_DISPLAY_MODEL_URL_MAP[strtolower($displayModel)] ?? null;

        if ($segment === null) {
            $this->logger->warning('No Archive2 URL segment found for display model; using ArchiveObject fallback.', [
                'pid'          => $pid,
                'displayModel' => $displayModel,
            ]);
            $segment = 'ArchiveObject';
        }

        $newUrl = '/Archive2/' . $segment . '/' . urlencode((string)$nid);

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $newUrl, true, 301);

        $interface->assign('pid', $pid);
        $interface->assign('newUrl', $newUrl);
        $this->display('legacyRedirect.tpl', 'Page Permanently Moved', false);
    }

    /**
     * Query the Islandora 2 JSON:API to resolve a legacy PID to a node ID and display model.
     *
     * Queries /jsonapi/node/islandora_object filtering by field_pid, and includes
     * field_model and field_legacy_resource_type so the display model name can be read
     * from the included resources without a second request.
     *
     * @param  string     $pid  Legacy Islandora 1 PID (e.g. "fortlewis:10526").
     * @return array|null       [int $nid, string $displayModel], or null on failure.
     */
    private function resolveByPid(string $pid): ?array
    {
        global $configArray;

        $baseUrl = rtrim($configArray['Islandora2']['url'] ?? '', '/');
        if (empty($baseUrl)) {
            $this->logger->error('Islandora2 [url] is not configured; cannot resolve legacy PID.');
            return null;
        }

        $url = $baseUrl . '/jsonapi/node/islandora_object'
             . '?filter[field_pid][value]=' . urlencode($pid)
             . '&include=field_model,field_legacy_resource_type'
             . '&fields[node--islandora_object]=drupal_internal__nid,field_model,field_legacy_resource_type';

        $userAgent = $configArray['Islandora2']['userAgent'] ?? '';
        $curl = new \Curl\Curl();
        if (!empty($userAgent)) {
            $curl->setUserAgent($userAgent);
        }

        try {
            $body = $curl->get($url);

            if ($curl->isCurlError()) {
                $this->logger->error('Curl error while resolving legacy PID via JSON:API.', [
                    'pid'   => $pid,
                    'code'  => $curl->getCurlErrorCode(),
                    'error' => $curl->getCurlErrorMessage(),
                ]);
                return null;
            }

            if ($curl->isError()) {
                $this->logger->warning('HTTP error from Islandora2 JSON:API while resolving legacy PID.', [
                    'pid'  => $pid,
                    'code' => $curl->getHttpStatusCode(),
                ]);
                return null;
            }

            if (is_array($body)) {
                $data = $body;
            } elseif (is_object($body)) {
                $data = json_decode(json_encode($body), true);
            } else {
                $data = json_decode((string)$body, true);
            }

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $this->logger->warning('Invalid JSON response from Islandora2 JSON:API.', [
                    'pid'   => $pid,
                    'error' => json_last_error_msg(),
                ]);
                return null;
            }

            $nodes = $data['data'] ?? [];
            if (empty($nodes)) {
                $this->logger->warning('No node found in Islandora2 JSON:API for PID.', ['pid' => $pid]);
                return null;
            }

            $node = $nodes[0];
            $nid  = $node['attributes']['drupal_internal__nid'] ?? null;
            if (empty($nid)) {
                $this->logger->warning('Node found but drupal_internal__nid is missing.', ['pid' => $pid]);
                return null;
            }

            $included     = $data['included'] ?? [];
            $displayModel = $this->resolveDisplayModel($node, $included);

            if ($displayModel === null) {
                $this->logger->warning('Could not determine display model for PID.', [
                    'pid' => $pid,
                    'nid' => $nid,
                ]);
                return null;
            }

            return [(int)$nid, $displayModel];

        } catch (\Throwable $e) {
            $this->logger->error('Exception while resolving legacy PID.', [
                'pid'     => $pid,
                'message' => $e->getMessage(),
            ]);
            return null;
        } finally {
            $curl->close();
        }
    }

    /**
     * Resolve the display model name from a JSON:API node resource and its included terms.
     *
     * Prefers field_legacy_resource_type (the Islandora 1 content model name) so that
     * postcard, magazine, and other legacy types map correctly. Falls back to field_model
     * (the Islandora 2 media type) when the legacy field is absent.
     *
     * @param  array       $node     JSON:API node resource object (single item from data[]).
     * @param  array       $included JSON:API top-level included array.
     * @return string|null           Lower-cased display model name, or null.
     */
    private function resolveDisplayModel(array $node, array $included): ?string
    {
        $name = $this->resolveRelationshipName($node, 'field_legacy_resource_type', $included);
        if ($name !== null) {
            return strtolower($name);
        }

        $name = $this->resolveRelationshipName($node, 'field_model', $included);
        if ($name !== null) {
            return strtolower($name);
        }

        return null;
    }

    /**
     * Look up the name attribute of a to-one relationship term from the included resources.
     *
     * @param  array       $node         JSON:API node resource object.
     * @param  string      $relationship Relationship key (e.g. 'field_model').
     * @param  array       $included     JSON:API top-level included resources.
     * @return string|null               The term's 'name' attribute, or null.
     */
    private function resolveRelationshipName(array $node, string $relationship, array $included): ?string
    {
        $relData = $node['relationships'][$relationship]['data'] ?? null;
        if ($relData === null) {
            return null;
        }

        // Relationship data may be a single resource linkage object or an array of them.
        $termUuid = isset($relData['id']) ? $relData['id'] : ($relData[0]['id'] ?? null);
        if ($termUuid === null) {
            return null;
        }

        foreach ($included as $item) {
            if (($item['id'] ?? null) === $termUuid) {
                return $item['attributes']['name'] ?? null;
            }
        }

        return null;
    }
}
