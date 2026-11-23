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
     * @return mixed|false Returns mixed if key is found, flase if key doesn't exist.
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
     * Local relation codes whose meaning flips when viewed from the other end of the link.
     * Codes not listed here (other than the symmetric ones) are worded as "<Label> of".
     */
    private const REVERSE_RELATION_FLIPS = [
        'chd' => 'Parent',
        'par' => 'Child',
        'emp' => 'Employer',
        'epr' => 'Employee',
    ];

    /** Local relation codes that read the same from either end of the link. */
    private const SYMMETRIC_RELATIONS = ['spo', 'sib', 'ptr', 'rel', 'aka'];

    /** Memoized output of getReverseRelations(). */
    private ?array $reverseRelations = null;

    /**
     * Normalize a Pika-JSON value that may be null, a single object, or a list into a list.
     *
     * @param mixed $value
     * @return array
     */
    protected static function asList(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }
        return array_is_list($value) ? $value : [$value];
    }

    /**
     * Return related person references, or null when none exist.
     *
     * Merges links entered on this term (field_related_person_paragraph) with links entered on
     * other person terms that point at this term (field_related_person_reverse). Forward links win
     * when the same person appears in both.
     *
     * @return array[]|null|false Term reference array, null if key exists but empty, false if key doesn't exist.
     */
    public function getRelatedPerson(): mixed
    {
        $reverse = $this->getReverseRelations()['person'];
        if (!array_key_exists('related_person_paragraph', $this->termWithoutFieldPrefix) && empty($reverse)) {
            return false;
        }

        $result = [];
        foreach (self::asList($this->termWithoutFieldPrefix['related_person_paragraph'] ?? null) as $p) {
            $note = $p['related_person_note'] ?? null;
            // A paragraph's related_person may hold one person or several.
            foreach (self::asList($p['related_person'] ?? null) as $raw) {
                $tid  = $raw['tid'] ?? null;
                $name = $raw['name'] ?? '';
                if (empty($tid) && empty($name)) {
                    continue;
                }
                $vocab   = strtolower($raw['vocabulary'] ?? 'person');
                $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab] ?? 'Person';
                $url     = (!empty($tid)) ? '/Archive2/' . $segment . '/' . urlencode((string)$tid) : '#';
                $entry   = [
                    'tid'            => isset($tid) ? (int)$tid : null,
                    'name'           => $name,
                    'url'            => $url,
                    'thumbnail'      => $raw['thumbnail']['url'] ?? null,
                    'relation'       => $raw['relation'] ?? null,
                    'relation_label' => $raw['relation_label'] ?? null,
                ];
                if (is_string($note) && $note !== '') {
                    $entry['note'] = $note;
                }
                $result[] = $entry;
            }
        }

        $result = $this->appendReverseEntries($result, $reverse);
        return $result ?: null;
    }

    /**
     * Collect reverse related-person links (field_related_person_reverse), grouped by the host
     * term's vocabulary: 'person', 'corporate_body', or 'event'.
     *
     * Each entry points at the host term (the record that owns the paragraph) and carries the
     * relation worded from this term's side. Hosts flagged linked_forward are skipped, as are
     * duplicate hosts and host paragraph entries that are about someone other than this term.
     *
     * @return array{person: array[], corporate_body: array[], event: array[]}
     */
    private function getReverseRelations(): array
    {
        if ($this->reverseRelations !== null) {
            return $this->reverseRelations;
        }

        $buckets   = ['person' => [], 'corporate_body' => [], 'event' => []];
        $thisTid   = (string)($this->getTid() ?? '');
        $seenHosts = [];

        foreach (self::asList($this->termWithoutFieldPrefix['related_person_reverse'] ?? null) as $r) {
            $host    = $r['host'] ?? null;
            $hostTid = (string)($host['tid'] ?? '');
            if ($hostTid === '' || !empty($r['linked_forward']) || isset($seenHosts[$hostTid])) {
                continue;
            }

            // The host's paragraph may list several people; use the entry about this term.
            $match = null;
            foreach (self::asList($r['related_person'] ?? null) as $person) {
                if ((string)($person['tid'] ?? '') === $thisTid) {
                    $match = $person;
                    break;
                }
            }
            if ($match === null) {
                continue;
            }
            $seenHosts[$hostTid] = true;

            $vocab   = strtolower($host['vocabulary'] ?? 'person');
            $bucket  = isset($buckets[$vocab]) ? $vocab : 'person';
            $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab] ?? 'Person';
            $entry   = [
                'tid'            => (int)$hostTid,
                'name'           => $host['name'] ?? '',
                'url'            => '/Archive2/' . $segment . '/' . urlencode($hostTid),
                'thumbnail'      => $host['thumbnail']['url'] ?? null,
                'relation'       => $match['relation'] ?? null,
                'relation_label' => self::reverseRelationLabel($match['relation'] ?? null, $match['relation_label'] ?? null),
                'direction'      => 'reverse',
            ];
            $note = $r['related_person_note'] ?? null;
            if (is_string($note) && $note !== '') {
                $entry['note'] = $note;
            }
            $buckets[$bucket][] = $entry;
        }

        return $this->reverseRelations = $buckets;
    }

    /**
     * Append reverse entries to a forward list, skipping any whose tid the forward list already has.
     *
     * @param array[] $forward
     * @param array[] $reverse
     * @return array[]
     */
    private function appendReverseEntries(array $forward, array $reverse): array
    {
        $tids = array_flip(array_filter(array_column($forward, 'tid')));
        foreach ($reverse as $entry) {
            if (!isset($tids[$entry['tid']])) {
                $forward[]            = $entry;
                $tids[$entry['tid']]  = true;
            }
        }
        return $forward;
    }

    /**
     * Word a reverse link's relation from the viewed term's side.
     *
     * The stored relation describes this term as seen from the host ("I am George's Child"),
     * so known opposites are flipped, symmetric relations are kept, and anything else is
     * phrased as "<Label> of" (e.g. "Committee member of").
     *
     * @param string|null $relation Relation code, e.g. "local:chd" or "relator:aut".
     * @param string|null $label    Relation label, e.g. "Child (chd)".
     * @return string
     */
    private static function reverseRelationLabel(?string $relation, ?string $label): string
    {
        require_once ROOT_DIR . '/interface/plugins/modifier.stripRelatorCode.php';
        $code = strtolower((string)$relation);
        if (($pos = strrpos($code, ':')) !== false) {
            $code = substr($code, $pos + 1);
        }
        $cleanLabel = smarty_modifier_stripRelatorCode($label ?? '');

        if (isset(self::REVERSE_RELATION_FLIPS[$code])) {
            return self::REVERSE_RELATION_FLIPS[$code];
        }
        if ($cleanLabel === '') {
            return 'Related to';
        }
        if (in_array($code, self::SYMMETRIC_RELATIONS, true)) {
            return $cleanLabel;
        }
        return $cleanLabel . ' of';
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
            $name    = $raw['name'] ?? '';
            if (empty($tid) && empty($name)) {
                continue; // Skip entries with no identifying data (API placeholder with all-empty fields)
            }
            $vocab   = strtolower($raw['vocabulary'] ?? '');
            $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab] ?? null;
            $url     = ($segment && !empty($tid))
                ? '/Archive2/' . $segment . '/' . urlencode((string)$tid)
                : '#';
            $result[] = [
                'tid'            => isset($tid) ? (int)$tid : null,
                'name'           => $name,
                'url'            => $url,
                'thumbnail'      => $raw['field_thumbnail']['url'] ?? null,
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
        $reverse = $this->getReverseRelations()['corporate_body'];
        if(!array_key_exists('related_organization', $this->termWithoutFieldPrefix) && empty($reverse)) {
            return false;
        }
        $related_orgs = self::asList($this->termWithoutFieldPrefix['related_organization'] ?? null);

        $result = [];
        foreach ($related_orgs as $raw) {
            $tid  = $raw['tid'] ?? null;
            $name = $raw['name'] ?? '';
            if (empty($tid) && empty($name)) {
                continue;
            }
            $vocab   = strtolower($raw['vocabulary'] ?? 'corporate_body');
            $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab] ?? 'Organization';
            $url     = (!empty($tid)) ? '/Archive2/' . $segment . '/' . urlencode((string)$tid) : '#';
            $result[] = [
                'tid'            => isset($tid) ? (int)$tid : null,
                'name'           => $name,
                'url'            => $url,
                'thumbnail'      => $raw['field_thumbnail']['url'] ?? $raw['thumbnail']['url'] ?? null, // $raw['thumbnail']['url'] is needed for related org attached to contributing library taxonomy term
                'relation'       => $raw['relation'] ?? null,
                'relation_label' => $raw['relation_label'] ?? null,
            ];
        }

        $result = $this->appendReverseEntries($result, $reverse);
        return $result ?: null;
    }

    /**
     * Raw related event reference (field_related_event).
     *
     * @return array[]|null|false Term reference array, null if key exists but empty, false if key doesn't exist.
     */
    public function getRelatedEvent(): mixed
    {
        $reverse = $this->getReverseRelations()['event'];
        if(!array_key_exists('related_event', $this->termWithoutFieldPrefix) && empty($reverse)) {
            return false;
        }
        $related_event = self::asList($this->termWithoutFieldPrefix['related_event'] ?? null);

        $result = [];
        foreach ($related_event as $raw) {
            $tid  = $raw['tid'] ?? null;
            $name = $raw['name'] ?? '';
            if (empty($tid) && empty($name)) {
                continue;
            }
            $vocab   = strtolower($raw['vocabulary'] ?? 'event');
            $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab] ?? 'Event';
            $url     = (!empty($tid)) ? '/Archive2/' . $segment . '/' . urlencode((string)$tid) : '#';
            $result[] = [
                'tid'            => isset($tid) ? (int)$tid : null,
                'name'           => $name,
                'url'            => $url,
                'thumbnail'      => $raw['field_thumbnail']['url'] ?? null,
                'relation'       => $raw['relation'] ?? null,
                'relation_label' => $raw['relation_label'] ?? null,
            ];
        }

        $result = $this->appendReverseEntries($result, $reverse);
        return $result ?: null;
    }

    /**
     * Get subjects (field_geo_subjects)
     * For vocabs that have subjects, field is named field_geo_subjects
     * 
     * @return array[]|null|false Term reference array, null if key exists but empty, false if key doesn't exist.
     */
    public function getSubjects(): mixed
    {
        // geo_subject is the field name for event, corperate body and geo location
        if(!array_key_exists('geo_subject', $this->termWithoutFieldPrefix)) {
            return false;
        }
        $subjects = $this->termWithoutFieldPrefix['geo_subject'] ?? null;
        if(is_array($subjects) && array_key_exists('tid', $subjects)) {
            $subjects = [$subjects];
        }
        return $subjects;
    }


    public function getExternalLink()
    {
        if(!array_key_exists('external_link', $this->termWithoutFieldPrefix)) {
            return false;
        }
        $links = $this->termWithoutFieldPrefix['external_link'] ?? null;
        if (!is_array($links)) {
            return null;
        }
        if (array_key_exists('uri', $links)) {
            $links = [$links];
        }
        return $links;
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
    //public function getPikaUsage(): ?string
    //{
    //    return $this->rawTerm['field_taxo_pika_usage'] ?? null;
    //}

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
