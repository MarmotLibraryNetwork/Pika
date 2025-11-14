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

use Pika\Logger;

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
     * Magic property accessor that proxies to the node without the "field_" prefix.
     *
     * Example: accessing `$object->title` will read from `field_title` within the
     * Islandora node payload if it exists.
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name)
    {
        $nodeWithoutFieldPrefix = $this->getNodeWithoutFieldPrefix();
        if (array_key_exists($name, $nodeWithoutFieldPrefix)) {
            return $nodeWithoutFieldPrefix[$name];
        }

        return null;
    }

    /**
     * Allow subclasses to perform light-weight normalisation before storage.
     *
     * @param array $node
     * @return array
     */
    protected function normalizeNode(array $node): array
    {
        return $node;
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
     * Attempt to return the primary media file/derivative metadata.
     *
     * @return array|null
     */
    public function getPrimaryFile(): ?array
    {
        $primary_media = [];
        $media = $this->getMedia();
        foreach($media as $m) {
            $media_use = strtolower($m['media_use']['name']);
            if($media_use === 'origninal file') {
                $primary_media[] = $m;
            }
        }
        if(!empty($primary_media)) {
            return $primary_media;
        }
        return null;
    }

    /**
     * Return the media associacted with media object.
     * 
     * @param $withoutFieldPrefix bool Remove field_ prefix from array keys.
     * @return array|null
     */
    public function getMedia(bool $withoutFieldPrefix = true): ?array
    {
        $media = null;
        if($withoutFieldPrefix) {
            $media = $this->nodeWithoutFieldPrefix['media'];
        } else {
            $media = $this->nodeWithoutFieldPrefix['media'];
        }

        if(is_array($media) && !empty($media)) {
            return $media;
        }
        return null;
    }

    /**
     * Convenience accessor for the Islandora node id.
     *
     * @return int|null
     */
    public function getNodeId(): ?int
    {
        if (isset($this->rawNode['nid']) && is_numeric($this->rawNode['nid'])) {
            return (int)$this->rawNode['id'];
        }

        return null;
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
     * Helper for subclasses that need to fish strings out of a nested array.
     *
     * @param array $paths
     * @return string|null
     */
    protected function extractFirstString(array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $this->resolvePath($this->rawNode, $path);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Resolve a dotted path within an array payload.
     *
     * @param array $source
     * @param array $path
     * @return mixed|null
     */
    protected function resolvePath(array $source, array $path)
    {
        $cursor = $source;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        if (is_string($cursor)) {
            return $cursor;
        }
        if (is_scalar($cursor)) {
            return (string)$cursor;
        }

        return null;
    }

    /**
     * Locate the media file field regardless of how Islandora structured the response.
     *
     * @return array|null
     */
    protected function getMediaFileField(): ?array
    {
        $candidates = [
            $this->rawNode['attributes']['field_media_file'] ?? null,
            $this->rawNode['data']['attributes']['field_media_file'] ?? null,
            $this->rawNode['attributes']['file'] ?? null,
            $this->rawNode['data']['attributes']['file'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Remove the "field_" prefix from every string key within the payload.
     *
     * @param array $payload
     * @return array
     */
    protected function removeFieldPrefix(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
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
     * Static helper for subclasses to read nested strings during type resolution.
     *
     * @param array $source
     * @param array $path
     * @return string|null
     */
    protected static function readString(array $source, array $path): ?string
    {
        $cursor = $source;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        if (is_string($cursor)) {
            return $cursor;
        }
        if (is_scalar($cursor)) {
            return (string)$cursor;
        }
        return null;
    }

    /**
     * Resolve the Islandora media type from the raw node.
     *
     * @param array $node
     * @return string|null Lower-cased model value or null when unavailable.
     */
    protected static function getObjectModelFromNode(array $node): ?string
    {
        return isset($node['field_model']['name']) ? strtolower($node['field_model']['name']) : null;
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
}
