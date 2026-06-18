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

require_once ROOT_DIR . '/sys/Islandora2/I2Object.php';
require_once ROOT_DIR . '/sys/Islandora2/Request.php';
require_once ROOT_DIR . '/sys/Islandora2/JsonApiClient.php';

class CollectionObject extends I2Object
{
    /**
     * Returns true if the given node represents a collection media type.
     *
     * @param array $node Raw node data from the Islandora 2 API.
     * @return bool
     */
    public static function supports(array $node): bool
    {
        if (self::mediaTypeIn($node, ['collection'])) {
            return true;
        }

        return false;
    }

    /**
     * Returns the object type identifier for this class.
     *
     * @return string Always 'collection'.
     */
    public function getObjectType(): string
    {
        return 'collection';
    }

    /**
     * Returns the display mode configured for this collection, or null if not set.
     *
     * @return string|null Value of the `pika_coll_display` field.
     */
    public function getCollectionDisplay(): ?string
    {
        return $this->rawNode['field_pika_coll_display'] ?? null;
    }

    /**
     * Returns the display options configured for this collection.
     *
     * The underlying `field_pika_coll_options` field comes back as either a
     * single string (one option) or an array (several options); callers iterate
     * the result, so always hand back an array of option strings.
     *
     * @return array List of option strings, empty when none are configured.
     */
    public function getCollectionOptions(): array
    {
        return self::normalizeFieldValues($this->rawNode['field_pika_coll_options'] ?? null);
    }

    /**
     * Returns the curator-defined child order for this collection as node ids.
     *
     * `field_pika_coll_order` arrives as a list of nid strings (pika-json) but
     * legacy data may store a newline-separated string; both are normalized to
     * an ordered list of ints.
     *
     * @return int[] Ordered child node ids, empty when no order is configured.
     */
    public function getCollectionOrder(): array
    {
        return self::normalizeCollectionOrder($this->rawNode['field_pika_coll_order'] ?? null);
    }

    /**
     * Normalize a raw field value (string, array of scalars, or array of
     * `['value'|'target_id' => ...]` entries) into a flat list of non-empty
     * strings.
     *
     * @param mixed $raw
     * @return string[]
     */
    private static function normalizeFieldValues($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            return [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $entry) {
            if (is_array($entry)) {
                $entry = $entry['value'] ?? $entry['target_id'] ?? '';
            }
            if (is_scalar($entry) && (string)$entry !== '') {
                $out[] = (string)$entry;
            }
        }
        return $out;
    }

    /**
     * Normalize a raw `field_pika_coll_order` value into an ordered list of
     * integer node ids.
     *
     * @param mixed $raw Array of nid strings/ints, or a newline-separated string.
     * @return int[]
     */
    public static function normalizeCollectionOrder($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n]+/', trim($raw)) ?: [];
        }
        $out = [];
        foreach (self::normalizeFieldValues($raw) as $value) {
            if (ctype_digit($value)) {
                $out[] = (int)$value;
            }
        }
        return $out;
    }

    /**
     * Sorts an array of items by their 'name' key in ascending alphabetical order.
     *
     * @param array $items Array of associative arrays, each containing a 'name' key.
     * @return array The sorted array.
     */
    private function sortByName(array $items): array
    {
        usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $items;
    }

    /**
     * Aggregates related people entries across all child objects in this collection,
     * sorted by name.
     *
     * Uses full child iteration because related_person_paragraph is a paragraph entity,
     * not a direct taxonomy term reference, so JSON:API server-side filtering requires
     * a double-include traversal that is not yet implemented in fetchChildrenFiltered().
     *
     * @return array Combined related-person entries from all children.
     */
    public function getCollectionRelatedPeople(): array
    {
        $nid = $this->getNodeId();
        if ($nid === null) {
            return [];
        }

        // Stream children one at a time and discard each after extracting its
        // related people, so memory stays bounded regardless of collection size.
        $factory                   = new I2ObjectFactory();
        $collection_related_people = [];
        foreach ((new Request())->streamChildren($nid) as $childNode) {
            $child = $factory->fromNode($childNode);
            if ($child === null || $child->related_person_paragraph === null) {
                continue;
            }

            $child_people = $child->getRelatedPerson();
            if ($child_people !== null) {
                $collection_related_people = array_merge($collection_related_people, $child_people);
            }
        }

        return $this->sortByName($collection_related_people);
    }

    /**
     * Returns taxonomy terms referenced by field_related_place across all children,
     * deduplicated and sorted by name.
     *
     * Uses a single server-side filtered JSON:API query instead of loading every child.
     *
     * @return array Entries of ['tid' => int, 'name' => string, 'url' => string].
     */
    public function getCollectionRelatedPlaces(): array
    {
        return $this->aggregateTermsViaApi('field_related_place');
    }

    /**
     * Returns taxonomy terms referenced by field_related_event across all children,
     * deduplicated and sorted by name.
     *
     * @return array Entries of ['tid' => int, 'name' => string, 'url' => string].
     */
    public function getCollectionRelatedEvents(): array
    {
        return $this->aggregateTermsViaApi('field_related_event');
    }

    /**
     * Returns taxonomy terms referenced by field_related_org across all children,
     * deduplicated and sorted by name.
     *
     * @return array Entries of ['tid' => int, 'name' => string, 'url' => string].
     */
    public function getCollectionRelatedOrganizations(): array
    {
        return $this->aggregateTermsViaApi('field_related_org');
    }

    /**
     * Returns the subjects (field_subject) found across the collection's children
     * AND on the collection node itself, deduplicated by tid and sorted by name.
     *
     * Children contribute a per-subject object count via the JSON:API aggregation.
     * A subject declared only on the collection node (not on any child) is included
     * with a count of 0.
     *
     * @return array Entries of ['tid' => int, 'name' => string, 'count' => int].
     */
    public function getCollectionSubjects(): array
    {
        $byTid = [];
        foreach ($this->aggregateTermsViaApi('field_subject') as $s) {
            $byTid[$s['tid']] = ['tid' => $s['tid'], 'name' => $s['name'], 'count' => $s['count']];
        }
        // Merge in the collection node's own subjects not already contributed by a child.
        foreach ($this->getSubjects() ?? [] as $own) {
            $tid = (int)($own['tid'] ?? 0);
            if ($tid > 0 && !isset($byTid[$tid])) {
                $byTid[$tid] = ['tid' => $tid, 'name' => $own['name'], 'count' => 0];
            }
        }

        return $this->sortByName(array_values($byTid));
    }

    /**
     * Fetches deduplicated taxonomy terms for a given field across all children via
     * a single JSON:API server-side filtered query, then builds Archive2 URLs.
     *
     * @param string $filterField Drupal field machine name (e.g. 'field_related_place').
     * @return array Entries of ['tid' => int, 'name' => string, 'url' => string].
     */
    private function aggregateTermsViaApi(string $filterField): array
    {
        $nid = $this->getNodeId();
        if ($nid === null) {
            return [];
        }

        $terms  = (new JsonApiClient())->fetchChildrenFiltered($nid, $filterField);
        $result = [];
        foreach ($terms as $term) {
            $segment  = ISLANDORA2_VOCAB_URL_MAP[$term['vocabulary']] ?? 'TaxonomyTerm';
            $result[] = [
                'tid'   => $term['tid'],
                'name'  => $term['name'],
                'url'   => '/Archive2/' . $segment . '/' . urlencode((string)$term['tid']),
                'count' => $term['count'] ?? 0,
            ];
        }

        return $this->sortByName($result);
    }

    /**
     * Get the collection thumbnail link
     *
     * @return string|null Uri or null if empty
     */
    public function getCollectionThumbnailLink(): ?string
    {
        $thumb = $this->nwfp()['pika_thumb_url'] ?? null;
        if ($thumb === null) {
            return null;
        }
        return $thumb['uri'];
    }

    /**
     * Returns lightweight map markers for child objects that have geographic
     * coordinates set via field_coordinates.
     *
     * Uses a single server-side filtered JSON:API query (plus one batched media
     * query for thumbnails) instead of loading every child as a full I2Object,
     * so memory scales with the number of geocoded children rather than the size
     * of the whole collection.
     *
     * @return array Marker entries of
     *               ['nid' => int, 'title' => string, 'latitude' => float,
     *                'longitude' => float, 'thumbnail' => string].
     */
    public function getChildMarkers(): array
    {
        $nid = $this->getNodeId();
        if ($nid === null) {
            return [];
        }
        return (new JsonApiClient())->fetchChildMarkers($nid);
    }

    /**
     * Returns the total number of direct children in this collection.
     */
    public function getTotalChildCount(): int
    {
        $nid = $this->getNodeId();
        if ($nid === null) {
            return 0;
        }
        $response = (new Request())->fetchChildren($nid, page: 1, number: 1);
        return (int)($response['total'] ?? 0);
    }

    /**
     * Returns a paginated page of children as I2Object instances.
     *
     * @return array Array of I2Object instances for the requested page.
     */
    public function getChildObjectsPaginated(int $page = 1, int $limit = 24): array
    {
        $nid = $this->getNodeId();
        if ($nid === null) {
            return [];
        }
        $response = (new Request())->fetchChildren($nid, $page, $limit);
        if ($response === null) {
            return [];
        }
        $objects = [];
        foreach ($response['children'] ?? [] as $childNode) {
            $obj = (new I2ObjectFactory())->fromNode($childNode);
            if ($obj !== null) {
                $objects[] = $obj;
            }
        }
        return $objects;
    }
}
