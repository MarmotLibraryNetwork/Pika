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

    public function getDateCreated($format = 'm/d/Y')
    {
        $created = $this->created;
        if (empty($created)) {
            return null;
        }
        return date($format, $created);
    }

    /**
     * Return the description for this node, preferring the long form when available.
     *
     * @return string|null The description string, or null when neither form is set.
     */
    public function getDescription(): ?string
    {
        if (isset($this->nodeWithoutFieldPrefix['description_long']) && $this->nodeWithoutFieldPrefix['description_long'] !== '') {
            return htmlentities($this->nodeWithoutFieldPrefix['description_long']);
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
        usort($subjects, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
        return $subjects;
    }

    /**
     * Return the media associacted with this item as objects.
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
     * Return the children associacted with this item.
     *
     * @return array|null Returns null if no children
     */
    public function getRawChildren(): ?array
    {
        return $this->rawNode['children'] ?? null;
    }

    /**
     * Return all childern as I2Objects.
     *
     * @return array|null|false Array of thumbnail media objects, null when none exist, false if field doesn't exist.
     */
    public function getChildObjects(): mixed
    {

        if (!array_key_exists('children', $this->nodeWithoutFieldPrefix)) {
            return false;
        }

        // Return cached children if already loaded
        if (!empty($this->childrenObjects)) {
            return $this->childrenObjects;
        }

        // Build children from raw node data
        $children = [];
        $rawChildren = $this->children;
        if (!is_array($rawChildren)) {
            return null;
        }
        foreach ($rawChildren as $child) {
            $nid = $child['nid'] ?? null;
            if (!is_numeric($nid) || $nid <= 0) {
                continue;
            }
            $mediaObject = (new I2ObjectFactory())->fromNodeId($nid);
            if ($mediaObject) {
                $children[] = $mediaObject;
            }
        }

        $this->childrenObjects = $children;
        return $children;

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

    public function getUrl(): string
    {
        return getObjRelativeUrl($this);
    }

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

    public function getRelatedPlace(): ?array
    {
        $related_place = $this->nodeWithoutFieldPrefix['related_place'] ?? null;
        if (is_array($related_place) && array_key_exists('tid', $related_place)) {
            $related_place = [$related_place];
        }
        return $related_place;
    }

    public function getRelatedEvent(): ?array
    {
        $related_event = $this->nodeWithoutFieldPrefix['related_event'] ?? null;
        if (is_array($related_event) && array_key_exists('tid', $related_event)) {
            $related_event = [$related_event];
        }
        return $related_event;
    }

    public function getRelatedOrganization(): ?array
    {
        $related_org = $this->nodeWithoutFieldPrefix['related_org'] ?? null;
        if (is_array($related_org) && array_key_exists('tid', $related_org)) {
            $related_org = [$related_org];
        }
        return $related_org;
    }

    public function getRelatedPerson(): ?array
    {
        $related_person = $this->nodeWithoutFieldPrefix['related_person_paragraph'] ?? null;
        // if it's a single entry
        if (is_array($related_person) && array_key_exists('id', $related_person)) {
            $related_person = [$related_person['related_person']];
            // multipule persons
        } elseif ($related_person !== null) {
            $temp_person = [];
            foreach ($related_person as $person) {
                $temp_person[] = $person['related_person'];
            }
            $related_person = $temp_person;
        }
        return $related_person;
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
