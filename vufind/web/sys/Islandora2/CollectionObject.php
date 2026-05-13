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
        return $this->nodeWithoutFieldPrefix['pika_coll_display'] ?? null;
    }

    /**
     * Returns the display options configured for this collection, or null if not set.
     *
     * @return array|null Value of the `pika_coll_options` field.
     */
    public function getCollectionOptions(): ?array
    {
        return $this->nodeWithoutFieldPrefix['pika_coll_options'] ?? null;
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
     * @return array Combined related-person entries from all children.
     */
    public function getCollectionRelatedPeople(): array
    {
        $collection_related_people = [];
        foreach ($this->childrenObjects as $child) {
            if ($child->related_person_paragraph === null) {
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
     * Aggregates related place entries across all child objects in this collection,
     * sorted by name.
     *
     * @return array Combined related-place entries from all children.
     */
    public function getCollectionRelatedPlaces(): array
    {
        $collection_related_places = [];
        foreach ($this->childrenObjects as $child) {
            if ($child->related_place === null) {
                continue;
            }

            $child_places = $child->getRelatedPlace();
            if ($child_places !== null) {
                $collection_related_places = array_merge($collection_related_places, $child_places);
            }
        }

        return $this->sortByName($collection_related_places);
    }

    /**
     * Aggregates related event entries across all child objects in this collection,
     * sorted by name.
     *
     * @return array Combined related-event entries from all children.
     */
    public function getCollectionRelatedEvents(): array
    {
        $collection_related_events = [];
        foreach ($this->childrenObjects as $child) {
            if ($child->related_event === null) {
                continue;
            }

            $child_events = $child->getRelatedEvent();
            if ($child_events !== null) {
                $collection_related_events = array_merge($collection_related_events, $child_events);
            }
        }

        return $this->sortByName($collection_related_events);
    }

    /**
     * Aggregates related organization entries across all child objects in this collection,
     * sorted by name.
     *
     * @return array Combined related-organization entries from all children.
     */
    public function getCollectionRelatedOrganizations(): array
    {
        $collection_related_orgs = [];
        foreach ($this->childrenObjects as $child) {
            if ($child->related_org === null) {
                continue;
            }

            $child_orgs = $child->getRelatedOrganization();
            if ($child_orgs !== null) {
                $collection_related_orgs = array_merge($collection_related_orgs, $child_orgs);
            }
        }

        return $this->sortByName($collection_related_orgs);
    }

    /**
     * Get the collection thumbnail link
     *
     * @return string|null Uri or null if empty
     */
    public function getCollectionThumbnailLink(): ?string
    {
        if ($this->nodeWithoutFieldPrefix['pika_thumb_url'] === null) {
            return null;
        }
        return $this->nodeWithoutFieldPrefix['pika_thumb_url']['uri'];
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
