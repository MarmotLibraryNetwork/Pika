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

require_once ROOT_DIR . '/sys/Islandora2/TaxonomyObjectInterface.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';

use Pika\Logger;

/**
 * Abstract base class for Islandora 2 taxonomy term objects.
 *
 * Mirrors the structure of I2Object but is purpose-built for taxonomy terms
 * fetched from the /pika-json/taxonomy/{tid} endpoint.
 */
abstract class I2Taxonomy implements TaxonomyObjectInterface
{
    protected Logger $logger;
    protected array $rawTerm;
    protected array $termWithoutFieldPrefix;

    /**
     * Determine if the subclass can represent the supplied taxonomy term.
     *
     * @param array $term
     * @return bool
     */
    abstract public static function supports(array $term): bool;

    /**
     * @param array       $term   Raw taxonomy term payload from the API.
     * @param Logger|null $logger Optional logger override for testing.
     */
    final public function __construct(array $term, ?Logger $logger = null)
    {
        $this->rawTerm = $term;
        $this->termWithoutFieldPrefix = $this->removeFieldPrefix($term);
        $this->logger = $logger ?? new Logger(static::class);
    }

    /**
     * Magic property accessor that proxies to the term with or without the "field_" prefix.
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->termWithoutFieldPrefix)) {
            return $this->termWithoutFieldPrefix[$name];
        } elseif (array_key_exists($name, $this->rawTerm)) {
            return $this->rawTerm[$name];
        }

        return null;
    }

    /**
     * Return the taxonomy term ID.
     *
     * @return int|null
     */
    public function getTid(): ?int
    {
        if (isset($this->rawTerm['tid']) && is_numeric($this->rawTerm['tid'])) {
            return (int)$this->rawTerm['tid'];
        }

        return null;
    }

    /**
     * Return the unmodified term payload.
     *
     * @return array
     */
    public function getRawTerm(): array
    {
        return $this->rawTerm;
    }

    /**
     * Return the term payload with "field_" stripped from all keys.
     *
     * @return array
     */
    public function getTermWithoutFieldPrefix(): array
    {
        return $this->termWithoutFieldPrefix;
    }

    /**
     * Return the display title (taxonomy term name).
     *
     * Taxonomy terms use "name" rather than "title".
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        if (isset($this->rawTerm['name']) && $this->rawTerm['name'] !== '') {
            return htmlentities($this->rawTerm['name']);
        }

        return null;
    }

    /**
     * Return the primary description or notes for this term.
     *
     * Checks in priority order:
     *   1. field_person_notes  — the universal notes field used across all vocabulary types
     *   2. field_description   — type-specific long description (Event, Corporate Body)
     *   3. description         — base Drupal taxonomy description field
     *
     * Accesses $rawTerm directly to avoid the key collision that occurs when
     * removeFieldPrefix() strips "field_" from "field_description", which would
     * overwrite the base "description" key in $termWithoutFieldPrefix.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        // For now, only look at field_description since that's where migrated descriptions seem
        // to be.
        $candidates = [
            //$this->rawTerm['field_person_notes'] ?? null,
            $this->rawTerm['field_description'] ?? null,
            $this->rawTerm['description'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
            if (is_array($candidate) && isset($candidate['value']) && $candidate['value'] !== '') {
                return $candidate['value'];
            }
        }

        return null;
    }

    /**
     * Return the human-readable vocabulary name.
     *
     * @return string|null
     */
    public function getVocabularyName(): ?string
    {
        return $this->rawTerm['vocabulary'] ?? $this->rawTerm['vid'] ?? null;
    }

    /**
     * Return the vocabulary machine name (vid).
     *
     * @return string|null
     */
    public function getVocabularyMachineName(): ?string
    {
        return $this->rawTerm['vid'] ?? null;
    }

    /**
     * Return related person references, or null when none exist.
     *
     * @return array[]|null|false Term reference array, null if key exists but empty, false if key doesn't exist.
     */
    public function getRelatedPerson(): mixed
    {
        if(!array_key_exists('related_place', $this->termWithoutFieldPrefix)) {
            return false;
        }
        $person = $this->termWithoutFieldPrefix['related_person_paragraph'] ?? null;
        
        if($person === null)
            return null;

        // Only a single related person
        if(array_key_exists('related_person', $person)) {
            return [$person['related_person']];
        }

        // Multipule related people 
        // Only return the related person part.
        $people = [];
         
        foreach($person as $p) {
            $people[] = $p['related_person'];
        }

        return $people;
    }

    /**
     * Return related place references as a list of normalized arrays, or null when none exist.
     *
     * Merges field_related_place with field_related_place_addl_info by index so that
     * extra relation metadata from addl_info is folded into each place entry.
     *
     * Each entry shape: ['tid' => int|null, 'name' => string, 'url' => string,
     *                    'relation' => string|null, 'relation_label' => string|null]
     *
     * @return array[]|null|false Term reference array, null if key exists but empty, false if key doesn't exist.
     */
    public function getRelatedPlace(): mixed
    {
        if(!array_key_exists('related_place', $this->termWithoutFieldPrefix)) {
            return false;
        }

        $places   = $this->termWithoutFieldPrefix['related_place'] ?? null;
        $addlInfo = $this->termWithoutFieldPrefix['related_place_addl_info'] ?? null;

        if (empty($places) && empty($addlInfo)) {
            return null;
        }

        // A single place arrives as an associative array with a 'tid' key; wrap it.
        if (is_array($places) && isset($places['tid'])) {
            $places = [$places];
        }
        if (is_array($addlInfo) && isset($addlInfo['id'])) {
            $addlInfo = [$addlInfo];
        }
        $places   = is_array($places)   ? array_values($places)   : [];
        $addlInfo = is_array($addlInfo) ? array_values($addlInfo) : [];

        $count  = max(count($places), count($addlInfo));
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $raw     = array_merge($places[$i] ?? [], $addlInfo[$i] ?? []);
            $tid     = $raw['tid'] ?? null;
            $vocab   = strtolower($raw['vocabulary'] ?? '');
            $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab] ?? null;
            $url     = ($segment && !empty($tid))
                ? '/Archive2/' . $segment . '/' . urlencode((string)$tid)
                : '#';
            $result[] = [
                'tid'            => isset($tid) ? (int)$tid : null,
                'name'           => $raw['name'] ?? '',
                'url'            => $url,
                'relation'       => $raw['relation'] ?? null,
                'relation_label' => $raw['relation_label'] ?? null,
            ];
        }

        if(array_key_exists('tid', $result)) {
            $result = [$result];
        }

        return $result ?: null;
    }

    /**
     * Raw related organization reference (field_related_organization).
     *
     * @return array[]|null|false Term reference array, null if key exists but empty, false if key doesn't exist.
     */
    public function getRelatedOrganization(): mixed
    {
        if(!array_key_exists('related_organization', $this->termWithoutFieldPrefix)) {
            return false;
        }
        $related_orgs = $this->termWithoutFieldPrefix['related_organization'] ?? null;

        if($related_orgs === null)
            return null;

        if(array_key_exists('tid', $related_orgs)) {
            $related_orgs = [$related_orgs];
        }

        return $related_orgs;
    }

    /**
     * Raw related event reference (field_related_event).
     *
     * @return array[]|null|false Term reference array, null if key exists but empty, false if key doesn't exist.
     */
    public function getRelatedEvent(): mixed
    {
        if(!array_key_exists('related_event', $this->termWithoutFieldPrefix)) {
            return false;
        }
        $related_event = $this->termWithoutFieldPrefix['related_event'] ?? null;

        if($related_event === null)
            return null;

        if(array_key_exists('tid', $related_event)) {
            $related_event = [$related_event];
        }

        return $related_event;
    }

    /**
     * Return the relative URL for this taxonomy term's display page.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return getTaxonomyRelativeUrl($this);
    }

    /**
     * Return the legacy Fedora PID for this term.
     *
     * @return string|null
     */
    public function getPid(): ?string
    {
        return $this->rawTerm['field_pid'] ?? null;
    }

    /**
     * Return the owner ID for this term.
     *
     * @return string|null
     */
    public function getOwnerId(): ?string
    {
        return $this->rawTerm['field_owner_id'] ?? null;
    }

    /**
     * Whether this term should appear in search results.
     *
     * @return bool
     */
    public function isShownInSearch(): bool
    {
        return ($this->rawTerm['field_geo_pika_show_in_search'] ?? '0') === '1';
    }

    /**
     * Return the Pika usage value for this term.
     *
     * @return string|null
     */
    public function getPikaUsage(): ?string
    {
        return $this->rawTerm['field_taxo_pika_usage'] ?? null;
    }

    /**
     * Return the geolocation field value (present on all vocabulary types).
     *
     * @return mixed|null
     */
    public function getGeolocation(): mixed
    {
        return $this->rawTerm['field_geo_geolocation'] ?? null;
    }

    /**
     * Return the thumbnail image data for this term, or null when none is set.
     *
     * Shape: ['url' => string, 'mime' => string, 'filename' => string]
     *
     * @return array{url: string, mime: string, filename: string}|null
     */
    public function getThumbnail(): ?array
    {
        $thumbnail = $this->termWithoutFieldPrefix['thumbnail'] ?? null;
        if (is_array($thumbnail) && !empty($thumbnail['url'])) {
            return $thumbnail;
        } elseif (isset($this->default_thumbnail)) {
            return $this->default_thumbnail;
        }
        return null;
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
}
