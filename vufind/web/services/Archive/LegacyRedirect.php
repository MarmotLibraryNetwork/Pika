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
 * the redirect target. The PID namespace decides which endpoint to query: entity PIDs
 * are prefixed by type ("person:", "place:", "event:", "organization:") and resolve to
 * a taxonomy term, redirected to /Archive2/{Person|Organization|Place|Event}/{tid};
 * every other namespace is a digital object that resolves to a node, redirected to
 * /Archive2/{model segment}/{nid}.
 *
 * To redirect all actions in a legacy Archive service class, replace its launch()
 * body with a single delegation call:
 *   (new Archive_LegacyRedirect())->launch();
 *
 * Or let the class extend Archive_LegacyRedirect directly instead of Archive_Object.
 */
class Archive_LegacyRedirect extends Action
{
    /**
     * Legacy entity PIDs are namespaced by their type (e.g. "place:2455"), and objects never
     * use these namespaces. This maps each entity namespace to its Islandora 2 taxonomy
     * vocabulary machine name, so the PID prefix alone routes an entity to the right lookup.
     */
    private const ENTITY_PID_NAMESPACE_VOCAB_MAP = [
        'person'       => 'person',
        'place'        => 'geo_location',
        'event'        => 'event',
        'organization' => 'corporate_body',
    ];

    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger(__CLASS__);
    }

    public function launch(): void
    {
        global $interface;

        $pid = urldecode($_REQUEST['id'] ?? '');

        if (empty($pid)) {
            $this->logger->warning('LegacyRedirect called without a PID.');
            $interface->assign('pid', '');
            $interface->assign('newUrl', null);
            $this->display('legacyRedirect.tpl', 'Page Permanently Moved', false);
            return;
        }

        $target = $this->resolveTarget($pid);

        if ($target === null) {
            $this->logger->warning('Could not resolve legacy PID to Archive2 URL.', ['pid' => $pid]);
            $interface->assign('pid', $pid);
            $interface->assign('newUrl', null);
            $this->display('legacyRedirect.tpl', 'Page Permanently Moved', false);
            return;
        }

        [$segment, $id] = $target;
        $newUrl = '/Archive2/' . $segment . '/' . urlencode((string)$id);

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $newUrl, true, 301);

        $interface->assign('pid', $pid);
        $interface->assign('newUrl', $newUrl);
        $this->display('legacyRedirect.tpl', 'Page Permanently Moved', false);
    }

    /**
     * Resolve a legacy PID to an Archive2 [segment, id] redirect target.
     *
     * The PID namespace decides the lookup: entity PIDs are prefixed by their type
     * ("person:", "place:", "event:", "organization:") and migrated to taxonomy terms,
     * displayed under /Archive2/{Person|Organization|Place|Event}/{tid}; every other
     * namespace is a digital object migrated to a node, displayed under
     * /Archive2/{model segment}/{nid}. Both keep their original PID in field_pid.
     *
     * @param  string     $pid Legacy Islandora 1 PID (e.g. "fortlewis:10526").
     * @return array|null      [string $segment, int $id], or null when unresolved.
     */
    private function resolveTarget(string $pid): ?array
    {
        $namespace = strtolower(explode(':', $pid, 2)[0]);
        $vocab     = self::ENTITY_PID_NAMESPACE_VOCAB_MAP[$namespace] ?? null;

        // Entities: taxonomy terms keyed by field_pid.
        if ($vocab !== null) {
            $tid = $this->fetchTermTidByPid($pid, $vocab);
            if ($tid === null) {
							$this->logger->warning('Could not resolve legacy entity PID to taxonomy term. Might be missing from migration.', ['pid' => $pid]);
                return null;
            }
            // Every vocabulary in the namespace map is present in ISLANDORA2_VOCAB_URL_MAP.
            $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab];
            return [$segment, $tid];
        }

        // Digital objects: nodes keyed by field_pid.
        $node = $this->resolveByPid($pid);
        if ($node === null) {
					$this->logger->warning('Could not resolve legacy PID to Islandora2 node. Might be missing from migration.', ['pid' => $pid]);
          return null;
        }
        [$nid, $displayModel] = $node;
        $segment = ISLANDORA2_DISPLAY_MODEL_URL_MAP[strtolower($displayModel)] ?? null;
        if ($segment === null) {
            $this->logger->warning('No Archive2 URL segment found for display model; using ArchiveObject fallback.', [
                'pid'          => $pid,
                'displayModel' => $displayModel,
            ]);
            $segment = 'ArchiveObject';
        }
        return [$segment, (int)$nid];
    }

    /**
     * Resolve a legacy PID to a node ID and display model via the JSON:API node endpoint.
     *
     * Queries /jsonapi/node/islandora_object filtering by field_pid, and includes
     * field_model and field_legacy_resource_type so the display model name can be read
     * from the included resources without a second request.
     *
     * @param  string     $pid  Legacy Islandora 1 PID (e.g. "fortlewis:10526").
     * @return array|null       [int $nid, string $displayModel], or null when not a node.
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

        $data = $this->fetchJsonApi($url, ['pid' => $pid]);
        if ($data === null) {
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
    }

    /**
     * Look up a single taxonomy term's ID by legacy PID within one vocabulary.
     *
     * @param  string   $pid   Legacy Islandora 1 PID.
     * @param  string   $vocab Vocabulary machine name (e.g. "person").
     * @return int|null        The term's drupal_internal__tid, or null when none matches.
     */
    private function fetchTermTidByPid(string $pid, string $vocab): ?int
    {
        global $configArray;

        $baseUrl = rtrim($configArray['Islandora2']['url'] ?? '', '/');
        if (empty($baseUrl)) {
            $this->logger->error('Islandora2 [url] is not configured; cannot resolve legacy entity PID.');
            return null;
        }

        $resourceType = 'taxonomy_term--' . $vocab;
        $url = $baseUrl . '/jsonapi/taxonomy_term/' . $vocab
             . '?filter[field_pid][value]=' . urlencode($pid)
             . '&fields[' . $resourceType . ']=drupal_internal__tid';

        $data = $this->fetchJsonApi($url, ['pid' => $pid, 'vocab' => $vocab]);
        if ($data === null) {
            return null;
        }

        $terms = $data['data'] ?? [];
        if (empty($terms)) {
            return null;
        }

        $tid = $terms[0]['attributes']['drupal_internal__tid'] ?? null;
        return empty($tid) ? null : (int)$tid;
    }

    /**
     * GET a Drupal JSON:API URL and return the decoded document (with 'data'/'included'
     * keys), or null on any transport, HTTP, or JSON error. Shared by the node and
     * taxonomy-term lookups.
     *
     * @param  string     $url        Fully-built JSON:API URL.
     * @param  array      $logContext Extra context merged into any log entries.
     * @return array|null
     */
    private function fetchJsonApi(string $url, array $logContext = []): ?array
    {
        global $configArray;

        $userAgent = $configArray['Islandora2']['userAgent'] ?? '';
        $curl = new \Curl\Curl();
        if (!empty($userAgent)) {
            $curl->setUserAgent($userAgent);
        }

        try {
            $body = $curl->get($url);

            if ($curl->isCurlError()) {
                $this->logger->error('Curl error while querying Islandora2 JSON:API.', $logContext + [
                    'code'  => $curl->getCurlErrorCode(),
                    'error' => $curl->getCurlErrorMessage(),
                ]);
                return null;
            }

            if ($curl->isError()) {
                $this->logger->warning('HTTP error from Islandora2 JSON:API.', $logContext + [
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
                $this->logger->warning('Invalid JSON response from Islandora2 JSON:API.', $logContext + [
                    'error' => json_last_error_msg(),
                ]);
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Exception while querying Islandora2 JSON:API.', $logContext + [
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
