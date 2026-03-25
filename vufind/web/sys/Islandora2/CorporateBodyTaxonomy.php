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
 * Taxonomy term object for the "corporate_body" vocabulary.
 */
class CorporateBodyTaxonomy extends I2Taxonomy
{
    /** @inheritDoc */
    public static function supports(array $term): bool
    {
        return ($term['vid'] ?? null) === 'corporate_body';
    }

    /** Alternate or variant name for the organization (field_alternate_name). */
    public function getAlternateName(): ?string
    {
        return $this->termWithoutFieldPrefix['alternate_name'] ?? null;
    }

    /**
     * Founded year string (field_cat_date_begin).
     */
    public function getFoundedYear(): ?string
    {
        return $this->termWithoutFieldPrefix['cat_date_begin'] ?? null;
    }

    /**
     * Dissolved year string (field_cat_date_end).
     */
    public function getDissolvedYear(): ?string
    {
        return $this->termWithoutFieldPrefix['cat_date_end'] ?? null;
    }

    /**
     * Notes/description (shared field_person_notes field).
     */
    public function getNotes(): ?string
    {
        $notes = $this->termWithoutFieldPrefix['person_notes'] ?? null;
        return (is_string($notes) && $notes !== '') ? $notes : null;
    }

    /**
     * Organization type (field_type).
     */
    public function getOrganizationType(): ?string
    {
        return $this->termWithoutFieldPrefix['type'] ?? null;
    }

    /**
     * Organization website link (field_organization_url).
     *
     * Returns a Drupal link field array with 'uri', 'title', and 'options' keys,
     * or null when not set.
     *
     * @return array{uri: string, title: string, options: array}|null
     */
    public function getOrganizationUrl(): ?array
    {
        $link = $this->termWithoutFieldPrefix['organization_url'] ?? null;
        if (is_array($link) && !empty($link['uri'])) {
            return $link;
        }
        return null;
    }

    /**
     * Raw related place reference (field_related_place).
     *
     * @return mixed Term reference array or null.
     */
    public function getRelatedPlace(): mixed
    {
        return $this->termWithoutFieldPrefix['related_place'] ?? null;
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
}
