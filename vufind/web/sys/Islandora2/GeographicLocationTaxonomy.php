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
 * Taxonomy term object for the "geo_location" vocabulary.
 */
class GeographicLocationTaxonomy extends I2Taxonomy
{
    /** @inheritDoc */
    public static function supports(array $term): bool
    {
        return ($term['vid'] ?? null) === 'geo_location';
    }

    /**
     * Alternate geographic name (field_geo_alt_name — different key from other vocabulary types).
     */
    public function getAlternateName(): ?string
    {
        return $this->termWithoutFieldPrefix['geo_alt_name'] ?? null;
    }

    /**
     * Broader geographic term reference.
     */
    public function getBroaderLocation(): mixed
    {
        return $this->termWithoutFieldPrefix['geo_broader'] ?? null;
    }

    /**
     * Geographic location start date (field_geo_start_date).
     */
    public function getStartDate(): ?string
    {
        return $this->termWithoutFieldPrefix['geo_start_date'] ?? null;
    }

    /**
     * Geographic location end date (field_geo_end_date).
     */
    public function getEndDate(): ?string
    {
        return $this->termWithoutFieldPrefix['geo_end_date'] ?? null;
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
     * Geolocation coordinates field.
     *
     * Returns the raw geolocation value when populated (structure depends on
     * Drupal geofield module ['lat' => float, 'lng' => float]).
     */
    public function getGeolocation(): ?array
    {
        return $this->termWithoutFieldPrefix['geo_geolocation'] ?? null;
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
     * Raw related address reference (field_location_taxonomy).
     *
     * @return mixed Term reference array or null.
     */
    public function getAddress(): ?array
    {
        return $this->termWithoutFieldPrefix['location_taxonomy'] ?? null;
    } 

}
