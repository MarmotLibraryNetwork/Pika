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
    protected $default_thumbnail = [
        'url'=>'/interface/themes/responsive/images/organization.png',
        'mime'=>'image/png',
        'filename'=>'organization.png'];

    /** @inheritDoc */
    public static function supports(array $term): bool
    {
        return ($term['vid'] ?? null) === 'corporate_body';
    }

    /** Alternate or variant name for the organization (field_alternate_name). */
    public function getAlternateName(): mixed
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
     * Address(es) for this organization (field_related_place_addl_info).
     *
     * Entries where every meaningful field is empty and the country is "USA" (or
     * absent) are skipped — a country-only USA entry conveys no useful address.
     *
     * Each returned entry has keys: street, address2, city, state, zip, county,
     * otherRegion, country, startDate, endDate.
     *
     * @return array[]|null
     */
    public function getAddresses(): ?array
    {
        $addlInfo = $this->termWithoutFieldPrefix['related_place_addl_info'] ?? null;

        if (empty($addlInfo)) {
            return null;
        }

        // Normalize a single paragraph entry to a list.
        if (isset($addlInfo['id'])) {
            $addlInfo = [$addlInfo];
        }

        $result = [];
        foreach ($addlInfo as $raw) {
            $street      = $raw['street_number_and_name_rp'] ?? null;
            $address2    = $raw['address_2_rel_place']       ?? null;
            $city        = $raw['city_rel_place']            ?? null;
            $state       = $raw['state_rel_place']           ?? null;
            $zip         = $raw['zip_code_rel_place']        ?? null;
            $county      = $raw['county_rel_place']          ?? null;
            $otherRegion = $raw['other_region_rel_place']    ?? null;
            $country     = $raw['country_rel_place']         ?? null;
            $startDate   = $raw['start_date_rel_place']      ?? null;
            $endDate     = $raw['end_date_rel_place']        ?? null;

            // Skip entries that are empty except for country = "USA".
            $hasContent = array_filter([$street, $address2, $city, $state, $zip, $county, $otherRegion, $startDate, $endDate]);
            if (empty($hasContent) && (empty($country) || strtoupper(trim($country)) === 'USA')) {
                continue;
            }

            $result[] = [
                'street'      => $street,
                'address2'    => $address2,
                'city'        => $city,
                'state'       => $state,
                'zip'         => $zip,
                'county'      => $county,
                'otherRegion' => $otherRegion,
                'country'     => $country,
                'startDate'   => $startDate,
                'endDate'     => $endDate,
            ];
        }

        return $result ?: null;
    }

}
