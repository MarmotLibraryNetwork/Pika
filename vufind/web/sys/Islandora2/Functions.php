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

require_once ROOT_DIR . '/sys/Islandora2/I2Object.php';
require_once ROOT_DIR . '/sys/Islandora2/TaxonomyObjectInterface.php';

use Islandora2\I2Object;
use Islandora2\TaxonomyObjectInterface;

/**
 * Maps Islandora 2 display model names (lower-cased) to their Archive2 URL action segments.
 * Used by getObjRelativeUrl() and any controller that builds object links.
 * 
 * Items that are commented out will fall back to the Isalndora model for display
 */
const ISLANDORA2_DISPLAY_MODEL_URL_MAP = [
    'audio'            => 'Audio',
    'voice recordings' => 'Audio',
    'voice recording'  => 'Audio',
    'mp4'              => 'Audio',
    'collection'       => 'Collection',
    'compound object'  => 'Compound',
    'compound'         => 'Compound',
    'digital document' => 'DigitalDocument',
    'academic paper'   => 'DigitalDocument',
    'document'         => 'DigitalDocument',
    //'book'             => 'DigitalDocument',
    'image'            => 'Image',
    //'art'              => 'Image',
    //'article'          => 'Image',
    'page'             => 'DigitalDocument',
    'paged content'    => 'PagedContent',
    //'magazine'         => 'PagedContent',
    'postcard'         => 'Postcard',
    'video'            => 'Video',
];

/**
 * Maps taxonomy vocabulary machine names to their Archive2 URL action segments.
 * Used by getTaxonomyRelativeUrl() and any controller that builds taxonomy links.
 */
const ISLANDORA2_VOCAB_URL_MAP = [
    'person'         => 'Person',
    'corporate_body' => 'Organization',
    'geo_location'   => 'Place',
    'event'          => 'Event',
];

/**
 * Maps taxonomy vocabulary machine names to the singular label shown to patrons.
 *
 * Kept separate from ISLANDORA2_VOCAB_URL_MAP even though the two currently agree: one is
 * routing, the other is display text, and they should be free to diverge. The plural forms
 * in lang/en.ini (People, Places, ...) label the ss_vid facet, not an individual term.
 */
const ISLANDORA2_VOCAB_LABEL_MAP = [
    'person'         => 'Person',
    'corporate_body' => 'Organization',
    'geo_location'   => 'Place',
    'event'          => 'Event',
];

/**
 * Return the singular display label for a taxonomy vocabulary machine name.
 *
 * Unmapped vocabularies fall back to their machine name in title case, so a vocabulary
 * added in Islandora before Pika knows about it still reads sensibly.
 *
 * @param string|null $vocabularyMachineName
 * @return string  Label, or '' when no vocabulary is known.
 */
function getTaxonomyVocabularyLabel(?string $vocabularyMachineName): string
{
    $vocab = strtolower($vocabularyMachineName ?? '');
    if ($vocab === '') {
        return '';
    }

    return ISLANDORA2_VOCAB_LABEL_MAP[$vocab] ?? ucwords(str_replace('_', ' ', $vocab));
}

/**
 * Return the relative display URL for an Islandora 2 object.
 *
 * Maps display model names to their Archive2 action segment using
 * ISLANDORA2_DISPLAY_MODEL_URL_MAP. Unmapped models are used as-is.
 *
 * @param I2Object $obj
 * @return string  Relative URL, or '#' when the object has no valid node ID.
 */
function getObjRelativeUrl(I2Object $obj): string
{
    if ($obj->getNodeId() <= 0) {
        return '#';
    }

    $displayModel = strtolower($obj->getDisplayModel() ?? '');
    $displayModel = ISLANDORA2_DISPLAY_MODEL_URL_MAP[$displayModel] ?? $displayModel;

    return '/Archive2/' . $displayModel . '/' . urlencode((string)$obj->getNodeId());
}

/**
 * Return the absolute display URL for an Islandora 2 object.
 *
 * Prepends the site base URL (or the library's catalogUrl when set) to the
 * relative URL returned by getObjRelativeUrl().
 *
 * @param I2Object $obj
 * @return string  Absolute URL, or the base URL followed by '#' when invalid.
 */
function getObjAbsoluteUrl(I2Object $obj)
{
    return getArchiveBaseUrl() . getObjRelativeUrl($obj);
}

/**
 * Return the base URL that absolute Archive2 links are built on, without a trailing slash.
 *
 * Pika is multi-tenant, so the current library's catalogUrl wins over the configured site
 * URL to keep links on the host the patron is actually browsing.
 *
 * @return string
 */
function getArchiveBaseUrl(): string
{
    global $configArray;
    global $library;

    $baseUrl = $configArray['Site']['url'] ?? '';
    if (!empty($library->catalogUrl ?? '')) {
        $scheme  = $_SERVER['REQUEST_SCHEME'] ?? 'https';
        $baseUrl = $scheme . '://' . $library->catalogUrl;
    }

    return rtrim($baseUrl, '/');
}

/**
 * Return the relative display URL for a taxonomy term given its id and vocabulary.
 *
 * Maps vocabulary machine names to their Archive2 action segment:
 *   person           → /Archive2/Person
 *   corporate_body   → /Archive2/Organization
 *   geo_location     → /Archive2/Place
 *   event            → /Archive2/Event
 *
 * A missing or unmapped vocabulary falls back to /Archive2/Term, the generic controller
 * that resolves the term and redirects to its typed URL (or returns 410 for vocabularies
 * Pika does not display).
 *
 * This variant exists for callers that only have the Solr fields of a term (its_tid and
 * ss_vid) rather than a full taxonomy object, such as the search result record drivers.
 *
 * @param int         $tid
 * @param string|null $vocabularyMachineName
 * @return string  Relative URL, or '#' when the term ID is not valid.
 */
function getTaxonomyRelativeUrlFromParts(int $tid, ?string $vocabularyMachineName): string
{
    if ($tid <= 0) {
        return '#';
    }

    $vocab   = strtolower($vocabularyMachineName ?? '');
    $segment = ISLANDORA2_VOCAB_URL_MAP[$vocab] ?? 'Term';

    return '/Archive2/' . $segment . '/' . urlencode((string)$tid);
}

/**
 * Return the absolute display URL for a taxonomy term given its id and vocabulary.
 *
 * @param int         $tid
 * @param string|null $vocabularyMachineName
 * @return string  Absolute URL, or the base URL followed by '#' when invalid.
 */
function getTaxonomyAbsoluteUrlFromParts(int $tid, ?string $vocabularyMachineName): string
{
    return getArchiveBaseUrl() . getTaxonomyRelativeUrlFromParts($tid, $vocabularyMachineName);
}

/**
 * Return the relative display URL for an Islandora 2 taxonomy term.
 *
 * @param TaxonomyObjectInterface $term
 * @return string  Relative URL, or '#' when the term has no valid ID.
 */
function getTaxonomyRelativeUrl(TaxonomyObjectInterface $term): string
{
    return getTaxonomyRelativeUrlFromParts((int)$term->getTid(), $term->getVocabularyMachineName());
}

/**
 * Return the absolute display URL for an Islandora 2 taxonomy term.
 *
 * Prepends the site base URL (or the library's catalogUrl when set) to the
 * relative URL returned by getTaxonomyRelativeUrl().
 *
 * @param TaxonomyObjectInterface $term
 * @return string  Absolute URL, or the base URL followed by '#' when invalid.
 */
function getTaxonomyAbsoluteUrl(TaxonomyObjectInterface $term)
{
    return getArchiveBaseUrl() . getTaxonomyRelativeUrl($term);
}

/**
 * Assign the previous & next search result links for an Archive2 display page.
 *
 * Shared by the object (ArchiveObject) and taxonomy term (TaxonomyObject) controllers,
 * which have no common parent of their own.  Does nothing unless the request carries the
 * saved search parameters a link out of the archive search results adds, so a page reached
 * any other way simply renders without the navigation.
 *
 * @return void
 */
function assignArchive2SearchResultsNavigation(): void
{
    /** @var SearchObject_Islandora2|false $searchObject */
    $searchObject = SearchObjectFactory::initSearchObject('Islandora2');
    if ($searchObject === false) {
        return;
    }
    $searchObject->getNextPrevLinks();
}
