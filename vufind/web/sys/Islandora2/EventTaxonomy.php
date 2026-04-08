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
 * Taxonomy term object for the "event" vocabulary.
 */
class EventTaxonomy extends I2Taxonomy
{
    protected $default_thumbnail = [
        'url'=>'/interface/themes/responsive/images/events.png',
        'mime'=>'image/png',
        'filename'=>'events.png'];

    /** @inheritDoc */
    public static function supports(array $term): bool
    {
        return ($term['vid'] ?? null) === 'event';
    }

    /** Alternate or variant name for the event (field_alternate_name). */
    public function getAlternateName(): ?string
    {
        return $this->termWithoutFieldPrefix['alternate_name'] ?? null;
    }

    /**
     * Start year string (field_cat_date_begin).
     */
    public function getStartYear(): ?string
    {
        return $this->termWithoutFieldPrefix['cat_date_begin'] ?? null;
    }

    /**
     * End year string (field_cat_date_end).
     */
    public function getEndYear(): ?string
    {
        return $this->termWithoutFieldPrefix['cat_date_end'] ?? null;
    }

    /** Notes text for this event (field_person_notes). */
    public function getNotes(): ?string
    {
        $notes = $this->termWithoutFieldPrefix['person_notes'] ?? null;
        return (is_string($notes) && $notes !== '') ? $notes : null;
    }

    /** City where the event occurred (field_event_city). */
    public function getEventCity(): ?string
    {
        return $this->termWithoutFieldPrefix['event_city'] ?? null;
    }

    /** County where the event occurred (field_event_county). */
    public function getEventCounty(): ?string
    {
        return $this->termWithoutFieldPrefix['event_county'] ?? null;
    }

    /** State where the event occurred (field_event_state). */
    public function getEventState(): ?string
    {
        return $this->termWithoutFieldPrefix['event_state'] ?? null;
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
     * Raw related event reference (field_related_event).
     *
     * @return mixed Term reference array or null.
     */
    public function getRelatedEvent(): mixed
    {
        return $this->termWithoutFieldPrefix['related_event'] ?? null;
    }
}
