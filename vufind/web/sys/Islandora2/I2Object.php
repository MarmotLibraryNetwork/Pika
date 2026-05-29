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

require_once ROOT_DIR . '/sys/Islandora2/I2Media.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';
require_once ROOT_DIR . '/sys/Islandora2/Request.php';

use Pika\Logger;
use Islandora2\I2Media;

/**
 * Base class for concrete Islandora 2 media objects.
 *
 * The class provides shared helpers for subclasses and keeps a reference
 * to the raw node payload returned by the Islandora 2 Pika-JSON endpoint.
 */
abstract class I2Object implements MediaObjectInterface
{
    protected Logger $logger;
    protected array $rawNode;
    protected array $nodeWithoutFieldPrefix;
    protected array $media = [];
    protected array $childrenObjects = [];

    /**
     * Determine if the subclass can represent the supplied Islandora node.
     *
     * @param array $node
     * @return bool
     */
    abstract public static function supports(array $node): bool;

    /**
     * @param array       $node   Raw Islandora node payload.
     * @param Logger|null $logger Optional logger override for testing.
     */
    final public function __construct(array $node, ?Logger $logger = null)
    {
        $this->rawNode = $node;
        $this->nodeWithoutFieldPrefix = $this->removeFieldPrefix($node);
        $this->logger = $logger ?? new Logger(static::class);
    }

    /**
     * Magic property accessor that proxies to the node with or without the "field_" prefix.
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name)
    {
        if ($name === 'childrenObjects') {
            return $this->getChildObjects();
        }
        if (array_key_exists($name, $this->nodeWithoutFieldPrefix)) {
            return $this->nodeWithoutFieldPrefix[$name];
        } elseif (array_key_exists($name, $this->rawNode)) {
            return $this->rawNode[$name];
        }

        return null;
    }

    /**
     * Retrieve the stored Islandora node payload.
     *
     * @param bool $withoutFieldPrefix When true (default) removes leading "field_" prefixes.
     * @return array
     */
    public function getNode(bool $withoutFieldPrefix = true): array
    {
        if ($withoutFieldPrefix) {
            return $this->getNodeWithoutFieldPrefix();
        }
        return $this->getRawNode();
    }

    /**
     * Return the untouched Islandora node as provided by the API.
     *
     * @return array
     */
    public function getRawNode(): array
    {
        return $this->rawNode;
    }

    /**
     * Return node payload with "field_" stripped from all keys.
     *
     * @return array
     */
    public function getNodeWithoutFieldPrefix(): array
    {
        return $this->nodeWithoutFieldPrefix;
    }

    /**
     * Resolve the Islandora object model string from the node.
     *
     * @return string
     */
    public function getObjectModel(): string
    {
        return static::getObjectModelFromNode($this->rawNode) ?? '';
    }

    /**
     * Some subclasses may want a richer label; the default keeps things simple.
     *
     * @return string
     */
    public function getObjectModelLabel(): string
    {
        return ucfirst($this->getObjectModel());
    }

    /**
     * Get the display model/type.
     *
     * @return string
     */
    public function getDisplayModel(): ?string
    {
        $displayModel = $this->legacy_resource_type['name'] ?? null;
        if ($displayModel === null) {
            $displayModel = $this->getObjectModel();
        }
        return $displayModel;
    }

    /**
     * Attempt to return the orignial media file.
     *
     * @return I2Media|null
     */
    public function getOriginalMedia(): ?I2Media
    {
        $media = $this->getMedia();
        foreach ($media as $m) {
            if ($m->useIs('original file')) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Get the PDF file.
     *
     * @return I2Media|null
     */
    public function getPDFMedia(): ?I2Media
    {
        $media = $this->getMedia();
        foreach ($media as $m) {
            if ($m->useIs('PDF')) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Attempt to return the intermediate media file.
     *
     * @return I2Media|null
     */
    public function getIntermediateFile(): ?I2Media
    {
        $media = $this->getMedia();
        foreach ($media as $m) {
            if ($m->useIs('intermediate file')) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Return the most recently created thumbnail media object for this node.
     *
     * @return I2Media|null The newest thumbnail, or null when none exist.
     */
    public function getThumbnail()
    {
        $media = $this->getMedia();
        $thumbnails = [];
        foreach ($media as $m) {
            if ($m->useIs('thumbnail image')) {
                $thumbnails[] = $m;
            }
        }
        if (empty($thumbnails)) {
            return null;
        }
        $sorted = $this->sortMediaByCreatedDate($thumbnails);
        return $sorted[0];
    }

    /**
     * Return all thumbnail media objects associated with this node.
     *
     * @return I2Media[]|null Array of thumbnail media objects, or null when none exist.
     */
    public function getThumbnails()
    {
        $media = $this->getMedia();
        $thumbnails = [];
        foreach ($media as $m) {
            if ($m->useIs('thumbnail image')) {
                $thumbnails[] = $m;
            }
        }
        if (empty($thumbnails)) {
            return null;
        }
        return $thumbnails;
    }

    /**
     * Return the service file media object for this node, if one exists.
     *
     * @return I2Media|null The service file media object, or null when unavailable.
     */
    public function getServiceFile()
    {
        $media = $this->getMedia();
        foreach ($media as $m) {
            if ($m->useIs('service file')) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Return the created date formatted according to the given format string.
     *
     * @param string $format A date() format string.
     * @return string|null Formatted date string, or null when no created date is set.
     */
    public function getDateCreated($format = 'm/d/Y')
    {
        $created = $this->created;
        if (empty($created)) {
            return null;
        }
        return date($format, $created);
    }

    /**
     * Return the geographic coordinates for this node from field_coordinates.
     *
     * @return array|null Associative array with 'lat' and 'lng' floats, or null when not set.
     */
    public function getCoordinates(): ?array
    {
        $coords = $this->nodeWithoutFieldPrefix['coordinates'] ?? null;
        if (!is_array($coords)) {
            return null;
        }
        $lat = $coords['lat'] ?? null;
        $lng = $coords['lng'] ?? $coords['lon'] ?? null;
        if ($lat === null || $lng === null) {
            return null;
        }
        return ['lat' => (float)$lat, 'lng' => (float)$lng];
    }

    /**
     * Return the description for this node, preferring the long form when available.
     *
     * @return string|null The description string, or null when neither form is set.
     */
    public function getDescription(): ?string
    {
        if (isset($this->nodeWithoutFieldPrefix['description_long']) && $this->nodeWithoutFieldPrefix['description_long'] !== '') {
            return $this->nodeWithoutFieldPrefix['description_long'];
            //return htmlentities($this->nodeWithoutFieldPrefix['description_long']);
						//Displaying html should be okay
        } elseif (isset($this->nodeWithoutFieldPrefix['description']) && $this->nodeWithoutFieldPrefix['description'] !== '') {
            return $this->nodeWithoutFieldPrefix['description'];
        }
        return null;
    }

    /**
     * Return the display title for this node, falling back to the plain title.
     *
     * @return string|null The title string, or null when neither field is set.
     */
    public function getTitle(): ?string
    {
        if (isset($this->nodeWithoutFieldPrefix['display_title']) && $this->nodeWithoutFieldPrefix['display_title'] !== '') {
            return $this->nodeWithoutFieldPrefix['display_title'];
        } elseif (isset($this->nodeWithoutFieldPrefix['title']) && $this->nodeWithoutFieldPrefix['title'] !== '') {
            return htmlentities($this->nodeWithoutFieldPrefix['title']);
        }
        return null;
    }

    /**
     * Return the human-readable language name for this node.
     *
     * @return string|null The language name, or null when unavailable.
     */
    public function getLanguage(): ?string
    {
        if (isset($this->nodeWithoutFieldPrefix['language']['name']) && $this->nodeWithoutFieldPrefix['language']['name'] !== '') {
            return $this->nodeWithoutFieldPrefix['language']['name'];
        }
        return null;
    }

    /**
     * Return the subject terms associated with this node.
     *
     * @return array|null Array of subject values, or null when none are present.
     */
    public function getSubjects(): ?array
    {
        $subjects = (empty($this->nodeWithoutFieldPrefix['subject']) === false) ? $this->nodeWithoutFieldPrefix['subject'] : null;
        if ($subjects === null) {
            return null;
        }
        // if it's a single subject, wrap in an array
        if (is_array($subjects) && array_key_exists('tid', $subjects)) {
            $subjects = [$subjects];
        }
        usort($subjects, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
        // The null-coalescing ?? '' handles any subjects that might be missing a name key gracefully.
        return $subjects;
    }

    /**
     * Return the media associated with this item as objects.
     *
     * @return array Returns an empty array if no media is present
     */
    public function getMedia(): array
    {
        if (!empty($this->media)) {
            return $this->media;
        }

        return $this->loadMedia();
    }

    /**
     * Return the first page of raw child node data from the children API.
     *
     * @return array|null Array of raw child node payloads, or null on failure.
     */
    public function getRawChildren(): ?array
    {
        $nid = $this->getNodeId();
        if ($nid === null) {
            return null;
        }
        $response = (new Request())->fetchChildren($nid);
        return $response['children'] ?? null;
    }

    /**
     * Return all children as I2Objects.
     *
     * Page 1 is fetched synchronously; any remaining pages are fetched in
     * parallel via MultiCurl (max 250 children per request).
     *
     * @return array Array of I2Object instances; empty when the node has no children.
     */
    public function getChildObjects(): array
    {
        if (!empty($this->childrenObjects)) {
            return $this->childrenObjects;
        }

        $nid = $this->getNodeId();
        if ($nid === null) {
            return [];
        }

        $factory  = new I2ObjectFactory();
        $children = [];

        foreach ((new Request())->fetchAllChildren($nid) as $childNode) {
            $obj = $factory->fromNode($childNode);
            if ($obj !== null) {
                $children[] = $obj;
            }
        }

        $this->childrenObjects = $children;
        return $children;
    }

    public function getParentCollection() {
        $parent_nid = $this->nodeWithoutFieldPrefix['member_of'] ?? null;
        if ($parent_nid == null)
            return null;
        if(is_array($parent_nid))
            $parent_nid = $parent_nid[0]['target_id'];
        $factory = new I2ObjectFactory();
        return $factory->fromNodeId($parent_nid);
    }

    /**
     * Convenience accessor for the Islandora node id.
     *
     * @return int|null
     */
    public function getNodeId(): ?int
    {
        if (isset($this->rawNode['nid']) && is_numeric($this->rawNode['nid'])) {
            return (int)$this->rawNode['nid'];
        }

        return null;
    }

    /**
     * Return the relative URL for this Islandora object.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return getObjRelativeUrl($this);
    }

    /**
     * Return the absolute URL for this Islandora object.
     *
     * @return string
     */
    public function getAbsoluteUrl(): string
    {
        return getObjAbsoluteUrl($this);
    }

    /**
     * Retrieve the logger used by the media object.
     *
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Return the library organization taxonomy term associated with this node.
     *
     * @return mixed The taxonomy term object, or null when unavailable.
     */
    public function getLibraryOrganization()
    {
        $library_tid = $this->nodeWithoutFieldPrefix['library']['tid'] ?? null;
        if ($library_tid === null) {
            $this->logger->warn('Warning no library set for node ' . $this->getNodeId());
            return null;
        }
        $taxonomy = new TaxonomyFactory();
        $library = $taxonomy->fromTid($library_tid);
        if ($library === null) {
            $this->logger->warn('Warning no Corperate Body related to library tid ' . $library_tid);
            return null;
        }
        return $library;
    }

    /**
     * Return the related place taxonomy terms associated with this node.
     *
     * @return array|null Array of related place entries, or null when none are present.
     */
    public function getRelatedPlace(): ?array
    {
        $related_place = $this->nodeWithoutFieldPrefix['related_place'] ?? null;
        if (is_array($related_place) && array_key_exists('tid', $related_place)) {
            $related_place = [$related_place];
        }
        return $related_place;
    }

    /**
     * Return the related event taxonomy terms associated with this node.
     *
     * @return array|null Array of related event entries, or null when none are present.
     */
    public function getRelatedEvent(): ?array
    {
        $related_event = $this->nodeWithoutFieldPrefix['related_event'] ?? null;
        if (is_array($related_event) && array_key_exists('tid', $related_event)) {
            $related_event = [$related_event];
        }
        return $related_event;
    }

    /**
     * Return the related organization taxonomy terms associated with this node.
     *
     * @return array|null Array of related organization entries, or null when none are present.
     */
    public function getRelatedOrganization(): ?array
    {
        $related_org = $this->nodeWithoutFieldPrefix['related_org'] ?? null;
        if (is_array($related_org) && array_key_exists('tid', $related_org)) {
            $related_org = [$related_org];
        }
        return $related_org;
    }

    /**
     * Return the related persons associated with this node.
     *
     * Handles both single and multiple related-person paragraph entries, normalizing
     * the result to a flat array of person term arrays.
     *
     * @return array|null Array of related person entries, or null when none are present.
     */
    public function getRelatedPerson(): ?array
    {
        $related_person = $this->nodeWithoutFieldPrefix['related_person_paragraph'] ?? null;
        // if it's a single entry
        if (is_array($related_person) && array_key_exists('id', $related_person)) {
            // get notes
            $note = $this->getRelatedPersonNote($related_person);
            $related_person['note'] = $note;
            $related_person = [$related_person['related_person']];
            // multiple persons
        } elseif ($related_person !== null) {
            $tmp_people = [];
            foreach ($related_person as $person) {
                $this_person = $person['related_person'];
                // get notes
                $note = $this->getRelatedPersonNote($person);
                $this_person['note'] = $note;
                $tmp_people[] = $this_person;
            }
            $related_person = $tmp_people;
        }
        return $related_person;
    }

    private function getRelatedPersonNote(array $person)
    {
        $current_note = $person['related_person_note'];
        $note = null;

        if ($current_note !== null) {
            // if the field start with relators or local, ignore it, the string following it
            // isn't for display
            if (!str_starts_with($current_note, 'relators') && !str_starts_with($current_note, 'local')) {
                // find the seperator
                $seperator = '';
                if (stristr($current_note, 'relator')) {
                    $seperator = 'relator';
                } elseif (stristr($current_note, 'local')) {
                    $seperator = 'local';
                } else {
                    $this->logger->warn('Using raw Related Person Note for node ID: ' . $this->getNodeId());
                    return $current_note;
                }
                $parts = explode($seperator, $person['related_person_note']);
                if ($parts[0] && is_string($parts[0])) {
                    $note = $parts[0];
                }
            }
        }
        return $note;
    }

    /**
     * Remove the "field_" prefix from every string key within the array.
     *
     * @param array $ar
     * @return array
     */
    protected function removeFieldPrefix(array $ar): array
    {
        $result = [];

        foreach ($ar as $key => $value) {
            $normalisedKey = $key;
            if (is_string($key) && strncmp($key, 'field_', 6) === 0) {
                $normalisedKey = substr($key, 6);
            }

            if (is_array($value)) {
                $value = $this->removeFieldPrefix($value);
            }

            $result[$normalisedKey] = $value;
        }

        return $result;
    }

    /**
     * Convenience helper for subclasses checking media types.
     *
     * @param array $node
     * @param array<int, string> $candidates
     * @return bool
     */
    protected static function mediaTypeIn(array $node, array $candidates): bool
    {
        $mediaType = self::getObjectModelFromNode($node);
        if ($mediaType === null) {
            return false;
        }

        $mediaType = strtolower($mediaType);
        foreach ($candidates as $candidate) {
            if ($mediaType === strtolower($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort media objects by created date
     * @param I2Media[] $mediaItems
     * @return I2Media[]
     */
    protected function sortMediaByCreatedDate(array $mediaItems): array
    {
        usort($mediaItems, static function (I2Media $a, I2Media $b): int {
            return $b->created <=> $a->created;
        });

        return $mediaItems;
    }

    /**
     * Find media associated with node and return as array of objects
     *
     * @return array
     */
    private function loadMedia(): array
    {
        $rawMedia = $this->nodeWithoutFieldPrefix['media'] ?? [];
        $media = [];
        foreach ($rawMedia as $m) {
            $media[] = new I2Media($m);
        }
        $this->media = $media;
        return $media;
    }

    /**
     * Resolve the Islandora media type from the raw node.
     *
     * @param array $node
     * @return string|null Lower-cased model value or null when unavailable.
     */
    protected static function getObjectModelFromNode(array $node): ?string
    {
        $fieldModel = $node['field_model'] ?? null;
        if (!is_array($fieldModel)) {
            return null;
        }
        if (array_key_exists('tid', $fieldModel)) {
            return isset($fieldModel['name']) ? strtolower($fieldModel['name']) : null;
        } elseif (isset($fieldModel[0]) && is_array($fieldModel[0]) && array_key_exists('tid', $fieldModel[0])) {
            return isset($fieldModel[0]['name']) ? strtolower($fieldModel[0]['name']) : null;
        }
        return null;
    }

}
