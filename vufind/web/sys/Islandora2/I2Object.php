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
 * to the raw node payload returned by the Islandora 2 JSON endpoint.
 */
abstract class I2Object implements MediaObjectInterface
{
    protected Logger $logger;
    protected array $node;

    abstract public static function supports(array $node): bool;

    final public function __construct(array $node, ?Logger $logger = null)
    {
        $this->node = $this->normaliseNode($node);
        $this->logger = $logger ?? new Logger(static::class);
    }

    /**
     * Allow subclasses to perform light-weight normalisation before storage.
     *
     * @param array $node
     * @return array
     */
    protected function normaliseNode(array $node): array
    {
        return $node;
    }

    public function getNode(): array
    {
        return $this->node;
    }

    /**
     * Some subclasses may want a richer label; the default keeps things simple.
     */
    public function getMediaTypeLabel(): string
    {
        return ucfirst($this->getMediaType());
    }

    public function getPrimaryFile(): ?array
    {
        $field = $this->getMediaFileField();
        if ($field === null) {
            return null;
        }

        if (isset($field['uri']) || isset($field['target_id'])) {
            return $field;
        }

        if (isset($field[0]) && is_array($field[0])) {
            return $field[0];
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
        if (isset($this->node['id']) && is_numeric($this->node['id'])) {
            return (int)$this->node['id'];
        }

        if (isset($this->node['data']['id']) && is_numeric($this->node['data']['id'])) {
            return (int)$this->node['data']['id'];
        }

        return null;
    }

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
            $value = $this->resolvePath($this->node, $path);
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
     * @return array|null
     */
    protected function getMediaFileField(): ?array
    {
        $candidates = [
            $this->node['attributes']['field_media_file'] ?? null,
            $this->node['data']['attributes']['field_media_file'] ?? null,
            $this->node['attributes']['file'] ?? null,
            $this->node['data']['attributes']['file'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
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
     * Attempt to normalise the bundle name used by Islandora.
     *
     * @param array $node
     * @return string|null
     */
    protected static function extractBundle(array $node): ?string
    {
        $candidates = [
            ['data', 'type'],
            ['type'],
            ['attributes', 'type'],
            ['data', 'attributes', 'type'],
            ['data', 'attributes', 'bundle'],
            ['attributes', 'bundle'],
        ];

        foreach ($candidates as $candidate) {
            $value = self::readString($node, $candidate);
            if ($value !== null) {
                return strtolower((string)$value);
            }
        }

        return null;
    }

    /**
     * Attempt to gather mime types referenced by the node.
     *
     * @param array $node
     * @return array<int, string>
     */
    protected static function extractMimeTypes(array $node): array
    {
        $paths = [
            ['data', 'attributes', 'mime_type'],
            ['data', 'attributes', 'field_mime_type'],
            ['attributes', 'field_mime_type'],
            ['attributes', 'mime_type'],
            ['relationships', 'field_media_file', 'data', 'meta', 'mime_type'],
        ];

        $result = [];
        foreach ($paths as $path) {
            $value = self::readString($node, $path);
            if ($value !== null) {
                $result[] = strtolower($value);
            }
        }

        // Inline files array (when multiple derivatives are present).
        $mediaField = $node['attributes']['field_media_file'] ?? $node['data']['attributes']['field_media_file'] ?? null;
        if (is_array($mediaField)) {
            foreach ($mediaField as $item) {
                if (isset($item['mime_type']) && is_string($item['mime_type'])) {
                    $result[] = strtolower($item['mime_type']);
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Check if the node bundle matches any candidate.
     *
     * @param array $node
     * @param array<int, string> $candidates
     * @return bool
     */
    protected static function bundleMatches(array $node, array $candidates): bool
    {
        $bundle = self::extractBundle($node);
        if ($bundle === null) {
            return false;
        }

        foreach ($candidates as $candidate) {
            $needle = strtolower($candidate);
            if ($bundle === $needle || strpos($bundle, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if any mime type starts with the given prefix.
     *
     * @param array $node
     * @param string $prefix
     * @return bool
     */
    protected static function mimeStartsWith(array $node, string $prefix): bool
    {
        $prefix = strtolower($prefix);
        $length = strlen($prefix);
        foreach (self::extractMimeTypes($node) as $mime) {
            if (strncmp($mime, $prefix, $length) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if any mime type matches one of the provided values.
     *
     * @param array $node
     * @param array<int, string> $candidates
     * @return bool
     */
    protected static function mimeMatches(array $node, array $candidates): bool
    {
        $types = self::extractMimeTypes($node);
        if ($types === []) {
            return false;
        }
        $normalised = array_map('strtolower', $candidates);
        foreach ($types as $mime) {
            if (in_array($mime, $normalised, true)) {
                return true;
            }
        }
        return false;
    }
}
