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
 * Prefix that marks a user list entry as an Islandora 2 taxonomy term.
 *
 * A term is stored in user_list_entry.groupedWorkPermanentId as
 * tax_{vocabulary_machine_name}:{tid} — for example tax_person:4212.
 *
 * The prefix is not decoration. Legacy Islandora 1 "Marmot Entity" PIDs are namespaced by
 * entity type rather than by library (person:12345, place:2455, event:…, organization:…; see
 * ISLANDORA2_LEGACY_ENTITY_NAMESPACE_VOCAB_MAP below), so without it
 * a person or event term id would be indistinguishable from an unconverted legacy PID and
 * the only thing telling them apart would be the entry's hidden flag. With it, the kind of
 * an entry is decidable from the string alone.
 *
 * Carrying the vocabulary in the id also means routing and labelling a term costs no
 * Islandora round trip. Treat it as a hint rather than the truth: the tid identifies the
 * term, and where a Solr document is at hand its ss_vid should win, so a term that is moved
 * between vocabularies still resolves.
 */
const ISLANDORA2_TAXONOMY_ENTRY_PREFIX = 'tax_';

/** Entry kinds returned by parseUserListEntryId(). */
const USER_LIST_ENTRY_CATALOG        = 'catalog';
const USER_LIST_ENTRY_ARCHIVE_OBJECT = 'archiveObject';
const USER_LIST_ENTRY_TAXONOMY_TERM  = 'taxonomyTerm';
const USER_LIST_ENTRY_LEGACY         = 'legacy';

/**
 * Maps a legacy Islandora 1 entity PID namespace to the Islandora 2 taxonomy vocabulary the
 * entity became.
 *
 * Legacy entity PIDs are namespaced by their type ("place:2455"), and archive objects never use
 * these namespaces — an object's namespace is its contributing library ("fortlewis:10526"). So
 * the prefix alone says whether a legacy PID is an entity, and which vocabulary to look in.
 *
 * Every value here is a key of ISLANDORA2_VOCAB_URL_MAP.
 */
const ISLANDORA2_LEGACY_ENTITY_NAMESPACE_VOCAB_MAP = [
    'person'       => 'person',
    'place'        => 'geo_location',
    'event'        => 'event',
    'organization' => 'corporate_body',
];

/**
 * Return the Islandora 2 vocabulary machine name for a legacy entity PID, or null when the PID
 * is not in one of the entity namespaces (so: an archive object, or not a PID at all).
 *
 * @param string $pid  A legacy Islandora 1 PID, e.g. 'place:2455'.
 * @return string|null
 */
function getVocabularyForLegacyEntityPid(string $pid): ?string
{
    if (!str_contains($pid, ':')){
        return null;
    }
    $namespace = strtolower(explode(':', $pid, 2)[0]);

    return ISLANDORA2_LEGACY_ENTITY_NAMESPACE_VOCAB_MAP[$namespace] ?? null;
}

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
 * Classify a user list entry id.
 *
 * user_list_entry.groupedWorkPermanentId holds four different kinds of value, and every
 * caller that used to tell them apart did so with its own str_contains()/ctype_digit()
 * test. They are gathered here so the rules stay in one place:
 *
 *   tax_person:4212                        taxonomy term  — 'id' is the tid
 *   18432                                  archive object — 'id' is the Islandora 2 node id
 *   1a2b3c4d-....                          catalog        — 'id' is the grouped work id
 *   person:12345                           legacy         — an Islandora 1 PID that the
 *                                                           D-5399 migration could not
 *                                                           resolve; always hidden
 *
 * An unknown vocabulary still classifies as a term: getTaxonomyRelativeUrlFromParts()
 * falls back to the generic /Archive2/Term controller, so a vocabulary added in Islandora
 * before Pika knows about it degrades to a working link rather than to 'legacy'.
 *
 * @param string $id  The stored entry id.
 * @return array{type: string, id: string, vocabulary: ?string}
 */
function parseUserListEntryId(string $id): array
{
    $id = trim($id);
    if ($id === ''){
        return ['type' => USER_LIST_ENTRY_LEGACY, 'id' => '', 'vocabulary' => null];
    }

    if (str_starts_with($id, ISLANDORA2_TAXONOMY_ENTRY_PREFIX) && str_contains($id, ':')){
        [$prefix, $tid] = explode(':', $id, 2);
        $vocabulary     = substr($prefix, strlen(ISLANDORA2_TAXONOMY_ENTRY_PREFIX));
        if ($vocabulary !== '' && ctype_digit($tid)){
            return [
                'type'       => USER_LIST_ENTRY_TAXONOMY_TERM,
                'id'         => $tid,
                'vocabulary' => strtolower($vocabulary),
            ];
        }
    }

    if (str_contains($id, ':')){
        // An Islandora 1 PID that was never converted. Kept whole so the migration can
        // still find it and so nothing silently reinterprets it as something else.
        return ['type' => USER_LIST_ENTRY_LEGACY, 'id' => $id, 'vocabulary' => null];
    }

    if (ctype_digit($id)){
        return ['type' => USER_LIST_ENTRY_ARCHIVE_OBJECT, 'id' => $id, 'vocabulary' => null];
    }

    return ['type' => USER_LIST_ENTRY_CATALOG, 'id' => $id, 'vocabulary' => null];
}

/**
 * Build the user list entry id for a taxonomy term.
 *
 * A term with no known vocabulary is stored as tax_term:{tid}, which routes through the
 * generic /Archive2/Term controller — the same fallback getTaxonomyRelativeUrlFromParts()
 * applies to an unmapped vocabulary.
 *
 * @param int         $tid
 * @param string|null $vocabularyMachineName
 * @return string  The id to store, or '' when the term id is not valid.
 */
function buildTaxonomyUserListEntryId(int $tid, ?string $vocabularyMachineName): string
{
    if ($tid <= 0){
        return '';
    }

    $vocabulary = strtolower(trim($vocabularyMachineName ?? ''));
    if ($vocabulary === ''){
        $vocabulary = 'term';
    }

    return ISLANDORA2_TAXONOMY_ENTRY_PREFIX . $vocabulary . ':' . $tid;
}

/**
 * Convert the id a record driver puts in the DOM back into the id stored in user_list_entry.
 *
 * Record drivers cannot use the stored id in markup directly: a colon is not usable in a
 * jQuery selector, so Islandora2Driver emits islandora2-{nid} and
 * Islandora2TaxonomyTermDriver emits islandora2-term-{vocabulary}-{tid}. Both come back
 * through the list edit, delete and reorder requests and have to be turned back into the
 * stored form before any database lookup.
 *
 * Vocabulary machine names use underscores rather than dashes (geo_location,
 * corporate_body), so splitting the term form on its final dash is unambiguous.
 *
 * Anything that is not one of those two shapes — a grouped work id above all — is returned
 * unchanged.
 *
 * @param string $domId
 * @return string
 */
function userListEntryIdFromDomId(string $domId): string
{
    $domId = trim($domId);
    if ($domId === ''){
        return '';
    }

    if (str_starts_with($domId, 'islandora2-term-')){
        $remainder    = substr($domId, strlen('islandora2-term-'));
        $lastDash     = strrpos($remainder, '-');
        if ($lastDash !== false){
            $vocabulary = substr($remainder, 0, $lastDash);
            $tid        = substr($remainder, $lastDash + 1);
            if (ctype_digit($tid)){
                return buildTaxonomyUserListEntryId((int)$tid, $vocabulary);
            }
        }
        // A term id with no vocabulary in it (islandora2-term-4212) predates this format.
        if (ctype_digit($remainder)){
            return buildTaxonomyUserListEntryId((int)$remainder, null);
        }
        return $domId;
    }

    if (str_starts_with($domId, 'islandora2-')){
        return substr($domId, strlen('islandora2-'));
    }

    return $domId;
}

/**
 * SQL fragment matching the taxonomy term entries in user_list_entry.
 *
 * UserList::getListEntries() and UserList::numValidListItems() both filter out ids holding
 * a colon, to keep unresolvable legacy PIDs out of patron lists. Taxonomy term ids contain
 * a colon too, so both need this exemption, and they need exactly the same one — they have
 * drifted apart once already.
 *
 * @param string $column  Qualified column name to test.
 * @return string
 */
function taxonomyUserListEntrySqlCondition(string $column = 'user_list_entry.groupedWorkPermanentId'): string
{
    return $column . " REGEXP '^" . ISLANDORA2_TAXONOMY_ENTRY_PREFIX . "[a-z_]+:[0-9]+$'";
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
