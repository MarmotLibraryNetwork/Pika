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

require_once ROOT_DIR . '/sys/Islandora2/I2Taxonomy.php';

/**
 * Taxonomy term object for the "person" vocabulary.
 */
class PersonTaxonomy extends I2Taxonomy
{
    protected $default_thumbnail = [
        'url'=>'/interface/themes/responsive/images/people.png',
        'mime'=>'image/png',
        'filename'=>'people.png'];

    /** @inheritDoc */
    public static function supports(array $term): bool
    {
        return ($term['vid'] ?? null) === 'person';
    }

    /** Family name / surname (field_family_name). */
    public function getFamilyName(): ?string
    {
        return $this->termWithoutFieldPrefix['family_name'] ?? null;
    }

    /** Given / first name (field_given_name). */
    public function getGivenName(): ?string
    {
        return $this->termWithoutFieldPrefix['given_name'] ?? null;
    }

    /** Middle name or initial (field_middle_name). */
    public function getMiddleName(): ?string
    {
        return $this->termWithoutFieldPrefix['middle_name'] ?? null;
    }

    /** Maiden / birth name (field_maiden_name). */
    public function getMaidenName(): ?string
    {
        return $this->termWithoutFieldPrefix['maiden_name'] ?? null;
    }

    /** Alternate or variant name (field_alternate_name). */
    public function getAlternateName(): ?string
    {
        return $this->termWithoutFieldPrefix['alternate_name'] ?? null;
    }

    /**
     * Birth year string (field_cat_date_begin).
     */
    public function getBirthYear(): ?string
    {
        return $this->termWithoutFieldPrefix['cat_date_begin'] ?? null;
    }

    /**
     * Death year string (field_cat_date_end).
     */
    public function getDeathYear(): ?string
    {
        return $this->termWithoutFieldPrefix['cat_date_end'] ?? null;
    }

    /** Notes text for this person (field_person_notes). */
    public function getNotes(): ?string
    {
        $notes = $this->termWithoutFieldPrefix['person_notes'] ?? null;
        return (is_string($notes) && $notes !== '') ? $notes : null;
    }

    /** Race / ethnicity descriptor (field_race_ethnicity). */
    public function getRaceEthnicity(): ?string
    {
        return $this->termWithoutFieldPrefix['race_ethnicity'] ?? null;
    }

    /** Military branch of service name (field_military_branch). */
    public function getMilitaryBranch(): ?string
    {
        $raw = $this->termWithoutFieldPrefix['military_branch'] ?? null;
        if (is_array($raw)) {
            return $raw['name'] ?? null;
        }
        return is_string($raw) ? $raw : null;
    }

    /** Military conflict or war name (field_military_conflict). */
    public function getMilitaryConflict(): ?string
    {
        $raw = $this->termWithoutFieldPrefix['military_conflict'] ?? null;
        if (is_array($raw)) {
            return $raw['name'] ?? null;
        }
        return is_string($raw) ? $raw : null;
    }

    /** Military rank (field_military_rank). */
    public function getMilitaryRank(): ?string
    {
        return $this->termWithoutFieldPrefix['military_rank'] ?? null;
    }

    /** Whether the person was a prisoner of war (field_military_pow === 'yes'). */
    public function getMilitaryIsPow(): bool
    {
        return ($this->termWithoutFieldPrefix['military_pow'] ?? null) === 'yes';
    }

    /** Military service start date (field_military_svc_date_start). */
    public function getMilitarySvcStartDate(): ?string
    {
        return $this->termWithoutFieldPrefix['military_svc_date_start'] ?? null;
    }

    /** Military service end date (field_military_svc_date_end). */
    public function getMilitarySvcEndDate(): ?string
    {
        return $this->termWithoutFieldPrefix['military_svc_date_end'] ?? null;
    }

    /**
     * Return true if any military field is populated.
     */
    public function hasMilitaryData(): bool
    {
        return $this->getMilitaryBranch() !== null
            || $this->getMilitaryConflict() !== null
            || $this->getMilitaryRank() !== null
            || $this->getMilitaryIsPow()
            || $this->getMilitarySvcStartDate() !== null
            || $this->getMilitarySvcEndDate() !== null;
    }

    /** Academic or professional position title (field_academic_position_title). */
    public function getAcademicPositionTitle(): ?string
    {
        return $this->termWithoutFieldPrefix['academic_position_title'] ?? null;
    }

    /** Degree name (e.g. "B.A.") (field_degree_name). */
    public function getDegreeName(): ?string
    {
        return $this->termWithoutFieldPrefix['degree_name'] ?? null;
    }

    /** Degree discipline or field of study (field_degree_discipline). */
    public function getDegreeDiscipline(): ?string
    {
        return $this->termWithoutFieldPrefix['degree_discipline'] ?? null;
    }

    /** Graduation date string (field_graduation_date). */
    public function getGraduationDate(): ?string
    {
        return $this->termWithoutFieldPrefix['graduation_date'] ?? null;
    }

    /**
     * Return true if any academic field is populated.
     */
    public function hasAcademicData(): bool
    {
        return $this->getAcademicPositionTitle() !== null
            || $this->getDegreeName() !== null
            || $this->getDegreeDiscipline() !== null
            || $this->getGraduationDate() !== null;
    }

    /**
     * Return the related place as an array with tid, name, url, relation, relation_label,
     * or null when not set.
     */
    public function getRelatedPlace(): ?array
    {
        $raw = $this->termWithoutFieldPrefix['related_place'] ?? null;
        return $this->normalizeRelatedTerm($raw);
    }

    /**
     * Raw related organization reference (field_related_organization).
     *
     * @return mixed Term reference array or null.
     */
    public function getRelatedOrganization(): mixed
    {
        return $this->termWithoutFieldPrefix['related_organization'] ?? null;
    }

    /**
     * Extract the Genealogy person ID from field_genealogy_link.
     *
     * The field value is expected to be a URL matching the pattern used by
     * the old Islandora 1 marmotGenealogy linked-data type, e.g.:
     *   https://pika.example.org/Person/Home?personId=42
     * or the archive format:
     *   https://pika.example.org/Person/42
     *
     * Returns the integer personId, or null when the field is absent or
     * the URL cannot be parsed.
     *
     * @return int|null
     */
    public function getGenealogyPersonId(): ?int
    {
        $raw = $this->rawTerm['field_genealogy_link'] ?? null;
        if (empty($raw)) {
            return null;
        }
        $url = is_array($raw) ? ($raw['uri'] ?? $raw['url'] ?? '') : (string)$raw;
        // Match /Person/<digits> or personId=<digits>
        if (preg_match('/[\/=](\d+)\/?$/', $url, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Normalize a related taxonomy term reference to a consistent array shape.
     *
     * @param mixed $raw Raw value from $termWithoutFieldPrefix.
     * @return array{tid: int, name: string, url: string, relation: string|null, relation_label: string|null}|null
     */
    private function normalizeRelatedTerm(mixed $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }
        if (is_array($raw) && isset($raw['tid'])) {
            return [
                'tid'           => (int)$raw['tid'],
                'name'          => $raw['name'] ?? '',
                'url'           => $this->buildRelatedTermUrl($raw),
                'relation'      => $raw['relation'] ?? null,
                'relation_label' => $raw['relation_label'] ?? null,
            ];
        }
        return null;
    }

    /**
     * Build the Archive2 URL for a related taxonomy term reference.
     *
     * @param array $term Normalized term reference containing at least 'vocabulary' and 'tid'.
     * @return string Relative URL, or '#' when vocabulary or tid is missing.
     */
    private function buildRelatedTermUrl(array $term): string
    {
        $vocabMap = [
            'person'         => 'Person',
            'corporate_body' => 'CorporateBody',
            'geo_location'   => 'GeographicLocation',
            'event'          => 'Event',
        ];
        $vocab   = strtolower($term['vocabulary'] ?? '');
        $segment = $vocabMap[$vocab] ?? null;
        if (!$segment || empty($term['tid'])) {
            return '#';
        }
        return '/Archive2/' . $segment . '?tid=' . urlencode((string)$term['tid']);
    }
}
