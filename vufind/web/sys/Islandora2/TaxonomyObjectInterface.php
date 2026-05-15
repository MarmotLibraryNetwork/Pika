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

/**
 * Contract for all Islandora 2 taxonomy term objects.
 */
interface TaxonomyObjectInterface
{
    /**
     * Return the Islandora taxonomy term ID.
     *
     * @return int|null
     */
    public function getTid(): ?int;

    /**
     * Return the unmodified term payload as returned by the API.
     *
     * @return array
     */
    public function getRawTerm(): array;

    /**
     * Return the human-readable vocabulary name (e.g. "person", "event").
     *
     * @return string|null
     */
    public function getVocabularyName(): ?string;

    /**
     * Return the vocabulary machine name / vid (e.g. "corporate_body", "geo_location").
     *
     * @return string|null
     */
    public function getVocabularyMachineName(): ?string;

    /**
     * Return the display title (taxonomy term name).
     *
     * @return string|null
     */
    public function getTitle(): ?string;

    /**
     * Return the primary description or notes for this term.
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Return the thumbnail image data for this term, or null when none is set.
     *
     * Shape: ['url' => string, 'mime' => string, 'filename' => string]
     *
     * @return array{url: string, mime: string, filename: string}|null
     */
    public function getThumbnail(): ?array;

    /**
     * Determine if this class can represent the supplied taxonomy term.
     *
     * @param array $term
     * @return bool
     */
    public static function supports(array $term): bool;
}
