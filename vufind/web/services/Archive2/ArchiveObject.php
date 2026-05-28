<?php

/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

namespace Archive2;

require_once ROOT_DIR . '/sys/Islandora2/Functions.php';
require_once ROOT_DIR . '/sys/Islandora2/I2ObjectFactory.php';
require_once ROOT_DIR . '/sys/Islandora2/MediaObjectInterface.php';
require_once ROOT_DIR . '/sys/Islandora2/TaxonomyFactory.php';
require_once ROOT_DIR . '/sys/Library/Library.php';
require_once ROOT_DIR . '/sys/Library/LibraryArchiveMoreDetails.php';

use Islandora2\I2ObjectFactory;
use Islandora2\MediaObjectInterface;
use Islandora2\TaxonomyFactory;
use Pika\Logger;

/* responsible for displaying template */
class ArchiveObject extends \Action
{
    protected ?MediaObjectInterface $mediaObject = null;
    /** node ID */
    protected int $nid;
    protected Logger $logger;

	protected const MODEL_VIEWER_MAP = [
		'audio'            => 'audio',
		'book'             => 'mirador',
		'compound object'  => 'compound',
		'digital document' => 'pdfjs',
		'image'            => 'open_seadragon',
		'paged content'    => 'mirador',
		'postcard'         => 'open_seadragon_multi',
		'video'            => 'video',
	];

	/** Roles that identify subjects/participants rather than production staff. */
	private const NON_PRODUCTION_TEAM_ROLES = [
		'attendee', 'artist', 'child', 'correspondence recipient', 'employee',
		'interviewee', 'member', 'parade marshal', 'parent', 'participant',
		'president', 'rodeo royalty', 'described', 'author', 'sibling',
		'spouse', 'pictured', 'student',
	]; //TODO replace use with one the arrays below

	private const PRODUCTION_TEAM_ROLES_RELATOR_CODES = [
		'pda', // Production Assistant
		'edt', // Editor
		'ivr', // Interviewer
		'trc', // Transcriber
		'prf', // Performer
		'cmp', // Composer
		'lyr', // Lyricist
		'dpr', // Digital Production team

		//TODO: populate with all codes
	];
	/** MARC three-letter relator codes for non-production roles — populate to switch filter from role names. */
	private const NON_PRODUCTION_RELATOR_CODES = [
		
	];

    /** Loads the media object from the `id` query parameter. */
    public function __construct()
    {
        $this->logger = new Logger(__CLASS__);
        $nid = (int)($_GET['id'] ?? 0);
        if ($nid <= 0) {
            $this->logger->warning('Invalid or missing nid in request.', ['nid' => $_GET['id'] ?? null]);
            // TODO: redirect error;
            return;
        }
        $factory = new I2ObjectFactory();
        $this->mediaObject = $factory->fromNodeId($nid);
        if ($this->mediaObject === null) {
            $this->logger->error('Failed to create media object for nid.', ['nid' => $nid]);
        }
				$this->logger->debug("Constructed Archive Object $nid");
    }

    /**
     * @param string      $mainContentTemplate
     * @param string|null $pageTitle           Defaults to the media object title.
     * @param string      $sidebarTemplate
     */
    public function display($mainContentTemplate, $pageTitle = null, $sidebarTemplate = 'Search/home-sidebar.tpl')
    {
        if ($this->mediaObject === null) {
            return;
        }

        $pageTitle = $pageTitle ?? $this->mediaObject->getTitle() ?? 'Archive Object';

        parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate);
    }

    /** Assigns all template variables for the archive object detail page. */
    public function launch()
    {
        global $interface;
        global $configArray;

        if ($this->mediaObject === null) {
            $this->logger->error('Attempted to launch with null mediaObject.');
            return;
        }

        $interface->assign('showExploreMore', true);
        $interface->assign('debugDetails', !empty($configArray['Islandora2']['debugDetails']));

        // Expose every field from the Islandora node (with "field_" removed) to the templates.
        $nodeData = $this->mediaObject->getNodeWithoutFieldPrefix();
        foreach ($nodeData as $field => $value) {
            $interface->assign($field, $value);
        }

        /*********
         * Overrides
         */

        // Dates
        $interface->assign('created', $this->formatDisplayDate($nodeData['created'] ?? null));
        $interface->assign('changed', $this->formatDisplayDate($nodeData['changed'] ?? null));

        // EDTF date fields: reformat ISO dates to human-readable form.
        $edtfDateFields = ['edtf_date_created', 'edtf_date_issued', 'edtf_date', 'date_captured', 'copyright_date', 'postmark', 'conference_date'];
        foreach ($edtfDateFields as $field) {
            if (isset($nodeData[$field]) && is_string($nodeData[$field])) {
                $interface->assign($field, $this->formatEdtfDate($nodeData[$field]));
            }
        }

        // Viewing permissions (true or false)
        $interface->assign('can_view', $this->canCurrentUserView());

        // Download & Request permissions
        // Can download master file
        $interface->assign('can_download_orginal', $this->canCurrentUserDownloadOrignial());
        // Can download intermediate file
        $interface->assign('can_download_intermediate', $this->canCurrentUserDownloadIntermediate());
        $interface->assign('can_request_copy', $this->canCurrentUserRequestCopy());
				$interface->assign('can_claim_authorship', $this->canCurrentUserClaimAuthorship());
        // Download files
        $orignal_media = $this->mediaObject->getOriginalMedia() ?? null;
        if ($orignal_media) {
            $orignal_media_file = $orignal_media->fileUrl;
            $interface->assign('orignal_media_file', $orignal_media_file);
        } else {
            $interface->assign('orignal_media_file', false);
        }

        $intermeidate_media = $this->mediaObject->getIntermediateFile() ?? null;
        if ($intermeidate_media) {
            $intermeidate_media_file = $intermeidate_media->fileUrl;
            $interface->assign('intermediate_media_file', $intermeidate_media_file);
        } else {
            $interface->assign('intermediate_media_file', false);
        }

        // Language
        $languageName = null;
        if ($this->mediaObject->language && $this->mediaObject->language != '') {
            $languageName = $this->mediaObject->language['name'];
        }
        $interface->assign('languageName', $languageName);

        // Rights Holder: normalize taxonomy term(s) for conditional linking.
        $rawRightsHolder = $nodeData['rights_holder'] ?? null;
        $rightsHolderData = [];
        if (!empty($rawRightsHolder)) {
            $items = isset($rawRightsHolder['tid']) ? [$rawRightsHolder] : (array)$rawRightsHolder;
            foreach ($items as $item) {
                if (is_array($item) && !empty($item['name'])) {
                    $rightsHolderData[] = [
                        'name'       => $item['name'],
                        'tid'        => $item['tid'] ?? null,
                        'vocabulary' => $item['vocabulary'] ?? $item['vid'] ?? null,
                        //TODO: is vid numeric? or is it a term? Does it occur here ever?
                    ];
                }
            }
        }
        $interface->assign('rights_holder', $rightsHolderData ?: null);

        // Rights Creator: normalize taxonomy term(s) for conditional linking.
        $rawRightsCreator  = $nodeData['rights_creator'] ?? null;
        $rightsCreatorData = [];
        if (!empty($rawRightsCreator)) {
            $items = isset($rawRightsCreator['tid']) ? [$rawRightsCreator] : (array)$rawRightsCreator;
            foreach ($items as $item) {
                if (is_array($item) && !empty($item['name'])) {
                    $rightsCreatorData[] = [
                        'name'       => $item['name'],
                        'tid'        => $item['tid'] ?? null,
                        'vocabulary' => $item['vocabulary'] ?? $item['vid'] ?? null,
                        //TODO: is vid numeric? or is it a term? Does it occur here ever?
                    ];
                }
            }
        }
        $interface->assign('rights_creator', $rightsCreatorData ?: null);

        // Titles
        $title = ($this->mediaObject->getTitle() !== null) ? $this->mediaObject->getTitle() : null;
        $interface->assign('title', $title);
        // breadcrumb
        $interface->assign('breadcrumbText', $title);
        $interface->assign('lastsearch', $_SESSION['lastArchive2SearchURL'] ?? false);
        $displayModel = $this->mediaObject->getDisplayModel();
        $interface->assign('display_model', $displayModel ? ucfirst($displayModel) : null);

        $subtitle = ($this->mediaObject->subtitle !== null) ? $this->mediaObject->subtitle : null;
        $interface->assign('subtitle', $subtitle);

        // Description
        $description = ($this->mediaObject->getDescription() !== null) ? $this->mediaObject->getDescription() : null;
        $interface->assign('description', $description);

        // Subjects
        $subjects = $this->mediaObject->getSubjects() ?? null;
        $interface->assign('subjects', $subjects);

        // Extent (physical description)
        $extent = ($this->mediaObject->extent !== null) ? $this->mediaObject->extent : null;
        $interface->assign('physical_description', $extent);

        // Library
        // Get the Corporate Body associated with the library
        $libraryTerm = $this->mediaObject->getLibraryOrganization();
        // If corporte body term isn't found use the Library vocab term
        if ($libraryTerm === null) {
            $interface->assign('library_name', $this->mediaObject->library['name'] ?? null);
            $interface->assign('library_tid', $this->mediaObject->library['tid'] ?? null);
            $interface->assign('library_url', null);
        } else {
            $interface->assign('library_name', $libraryTerm->name ?? null);
            $interface->assign('library_org_tid', $libraryTerm->tid ?? null);
            $libraryURL = getTaxonomyAbsoluteUrl($libraryTerm);
            $interface->assign('library_url', $libraryURL);
        }


        // Interview Location
        // NOTE: field_location is labeled as Interview Location in UI
        $rawInterviewLocations = ($this->mediaObject->location !== null) ? $this->mediaObject->location : [];
        $interviewLocations = [];
        // Determine if the location field has multipule locations
        // Single entry, put it into an array
        if (array_key_exists('id', $rawInterviewLocations)) {
            $tempLoc = $rawInterviewLocations;
            unset($rawInterviewLocations);
            $rawInterviewLocations = [];
            $rawInterviewLocations[] = $tempLoc;
        }

        foreach ($rawInterviewLocations as $rawInterviewLocation) {
            $interviewLocation = [
                'city'     => $rawInterviewLocation['city'] ?? null,
                'state'    => $rawInterviewLocation['state'] ?? '',
                'street'   => $rawInterviewLocation['street'] ?? '',
                'county'   => $rawInterviewLocation['county'] ?? '',
                'country'  => $rawInterviewLocation['country'] ?? '',
                'zip'      => $rawInterviewLocation['zip_code'] ?? '',
                'address2' => $rawInterviewLocation['address_2'] ?? '',
                'id'       => $rawInterviewLocation['id'],
            ];
            $interviewLocations[] = $interviewLocation;
        }
        $interface->assign('interview_locations', $interviewLocations);
        $interface->assign('linked_agents_display', $this->normalizeLinkedAgents());
		    //Linked Agents (Creator): normalize each entry for display with role label and conditional link.

        // Related Entity processing
        $relatedPeople = $this->getRelatedPeople();

        // Build production team first — removes matched entries from $relatedPeople
        // so they don't also appear in the Related People section.
        $productionTeam = $this->buildProductionTeam($relatedPeople);
        $interface->assign('production_team', $productionTeam ?: null);

        $interface->assign('related_place', $this->enrichRelatedPlacesWithThumbnails($this->mediaObject->getRelatedPlace()));
        $interface->assign('related_organization', $this->mediaObject->getRelatedOrganization());
        $interface->assign('related_event', $this->enrichRelatedEventsWithThumbnails($this->mediaObject->getRelatedEvent()));
        $interface->assign('related_person', $this->enrichRelatedPeopleWithThumbnails($relatedPeople ?: null));

        // Parent collection(s): resolve member_of nid(s) to title + Pika URL.
        $parentCollections = $this->resolveParentCollections();
        $interface->assign('parent_collection', $parentCollections ?: null);

        // Admin
        // Reload URL
        $cacheReloadUrl = $this->mediaObject->getAbsoluteUrl() . '?reload=true';
        $interface->assign('cache_reload_url', $cacheReloadUrl);
        // like to Islandora node
        $islandoraUrl = rtrim($configArray['Islandora2']['url'], "/") . "/node/" . $this->mediaObject->getNodeId();
        $interface->assign('islandora_url', $islandoraUrl);


        // Analytics
        $interface->assign('archivePage', true);

        // Process art materials taxonomy field into name+AAT-number pairs for artworkDetailsSection.tpl.
        // Each entry's name ends with the AAT number in parentheses, e.g. "bronze (metal) (300010957)".
        $rawMaterials = $nodeData['materials'] ?? null;
        $artMaterials = [];
        if ($rawMaterials !== null) {
            // Normalize: single item has a 'name' key directly; multiple items is a numeric array.
            $items = isset($rawMaterials['name']) ? [$rawMaterials] : (array)$rawMaterials;
            foreach ($items as $item) {
                $name = is_array($item) ? ($item['name'] ?? '') : (string)$item;
                $aatNumber = null;
                if (preg_match('/\((\d+)\)\s*$/', $name, $matches)) {
                    $aatNumber = $matches[1];
                }
                if ($name !== '') {
                    $artMaterials[] = ['name' => $name, 'aatNumber' => $aatNumber];
                }
            }
        }
        $interface->assign('artMaterials', $artMaterials ?: null);

        // Same processing for art technique taxonomy field.
        $rawArtTechnique = $nodeData['art_technique'] ?? null;
        $artTechniques = [];
        if ($rawArtTechnique !== null) {
            $items = isset($rawArtTechnique['name']) ? [$rawArtTechnique] : (array)$rawArtTechnique;
            foreach ($items as $item) {
                $name = is_array($item) ? ($item['name'] ?? '') : (string)$item;
                $aatNumber = null;
                if (preg_match('/\((\d+)\)\s*$/', $name, $matches)) {
                    $aatNumber = $matches[1];
                }
                if ($name !== '') {
                    $artTechniques[] = ['name' => $name, 'aatNumber' => $aatNumber];
                }
            }
        }
        $interface->assign('artTechniques', $artTechniques ?: null);

        // Same processing for style/period taxonomy field.
        $rawStylePeriod = $nodeData['style_period'] ?? null;
        $stylePeriods = [];
        if ($rawStylePeriod !== null) {
            $items = isset($rawStylePeriod['name']) ? [$rawStylePeriod] : (array)$rawStylePeriod;
            foreach ($items as $item) {
                $name = is_array($item) ? ($item['name'] ?? '') : (string)$item;
                $aatNumber = null;
                if (preg_match('/\((\d+)\)\s*$/', $name, $matches)) {
                    $aatNumber = $matches[1];
                }
                if ($name !== '') {
                    $stylePeriods[] = ['name' => $name, 'aatNumber' => $aatNumber];
                }
            }
        }
        $interface->assign('stylePeriods', $stylePeriods ?: null);

        // Transcription: collect text/plain Transcript media, fetch content, pair with location/language metadata.
        $transcriptMedia = array_values(array_filter(
            $this->mediaObject->getMedia(),
            fn($m) => $m->use === 'Transcript' && $m->mime === 'text/plain'
        ));
        usort($transcriptMedia, fn($a, $b) => $a->created <=> $b->created);

        $rawLoc  = $nodeData['transcription_loc'] ?? '';
        $rawLang = $nodeData['transcription_lang']['name'] ?? '';
        $locations = $rawLoc  !== '' ? array_map('trim', explode(',', $rawLoc))  : [];
        $languages = $rawLang !== '' ? array_map('trim', explode(',', $rawLang)) : [];

        $transcription = [];
        foreach ($transcriptMedia as $i => $tm) {
            $text = $this->fetchTranscriptText($tm->fileUrl);
            if ($text === null) {
                continue;
            }
            $transcription[] = [
                'location' => $locations[$i] ?? '',
                'language' => $languages[$i] ?? '',
                'text'     => $text,
            ];
        }
        $interface->assign('transcription', $transcription ?: null);

        // Normalize Drupal link fields for externalLinksSection.tpl.
        $interface->assign('externalLinks',   $this->normalizeLinkField($nodeData['external_link']     ?? null) ?: null);
        $interface->assign('furtherSiteLinks',$this->normalizeLinkField($nodeData['further_site_info'] ?? null) ?: null);
        $rawGenealogyLinks = $this->normalizeLinkField($nodeData['genealogy_link'] ?? null);
        foreach ($rawGenealogyLinks as &$link) {
            $link['uri'] = $this->rewriteGenealogyLinkUri($link['uri']);
        }
        unset($link);
        $interface->assign('genealogyLinks', $rawGenealogyLinks ?: null);

        // Catalog links: rewrite host to the current library's catalog when the stored
        // host matches a known library's catalogUrl, then fall back to a generic title.
        $rawCatalogLinks = $this->normalizeLinkField($nodeData['catalog_link'] ?? null, 'This title within the catalog.');
        foreach ($rawCatalogLinks as &$link) {
            $link['uri'] = $this->rewriteCatalogLinkUri($link['uri']);
        }
        unset($link);
        $interface->assign('catalogLinks', $rawCatalogLinks ?: null);

        // Staff role flag consumed by staffViewSection.tpl
        $isStaffUser = \UserAccount::userHasRole('archives')
            || \UserAccount::userHasRole('opacAdmin')
            || \UserAccount::userHasRole('libraryAdmin');
        $interface->assign('isStaffUser', $isStaffUser);

        $moreDetailsOptions = $this->filterAndSortMoreDetailsOptions($this->getBaseMoreDetailsOptions());
        $interface->assign('moreDetailsOptions', $moreDetailsOptions);

    }

    /**
     * Builds the full set of more-details accordion sections from Archive2 section templates.
     * Each section's body is fetched from `Archive2/sections/{key}Section.tpl`.
     * Sections whose template renders empty are excluded.
     */
    protected function getBaseMoreDetailsOptions(): array
    {
        global $interface;

        $sections = \LibraryArchiveMoreDetails::$moreDetailsOptions;
        $sections['moreDetails'] = 'Catalog Details';

        $allOptions = [];
        foreach ($sections as $key => $label) {
            $body = trim($interface->fetch("Archive2/sections/{$key}Section.tpl"));
            if ($body !== '') {
                $allOptions[$key] = [
                    'label'         => $label,
                    'body'          => $body,
                    'openByDefault' => false,
                ];
            }
        }

        return $allOptions;
    }

    /**
     * Filters and sorts $allOptions to only the sections configured for the current
     * library (or the LibraryArchiveMoreDetails defaults), setting openByDefault from
     * the collapseByDefault flag. Mirrors IslandoraDriver::filterAndSortMoreDetailsOptions().
     */
    protected function filterAndSortMoreDetailsOptions(array $allOptions): array
    {
        global $library;

        $useDefault         = true;
        $moreDetailsFilters = [];

        if ($library && count($library->archiveMoreDetailsOptions) > 0) {
            $useDefault = false;
            /** @var \LibraryArchiveMoreDetails $option */
            foreach ($library->archiveMoreDetailsOptions as $option) {
                $moreDetailsFilters[$option->section] = $option->collapseByDefault ? 'closed' : 'open';
            }
        }

        if ($useDefault) {
            $libraryId             = $library ? (int)$library->libraryId : -1;
            $defaultDetailsFilters = \LibraryArchiveMoreDetails::getDefaultOptions($libraryId);
            foreach ($defaultDetailsFilters as $filter) {
                $moreDetailsFilters[$filter->section] = $filter->collapseByDefault ? 'closed' : 'open';
            }
        }

        $filteredMoreDetailsOptions = [];
        foreach ($moreDetailsFilters as $option => $initialState) {
            if (array_key_exists($option, $allOptions)) {
                $detailOptions                       = $allOptions[$option];
                $detailOptions['openByDefault']      = ($initialState === 'open');
                $filteredMoreDetailsOptions[$option] = $detailOptions;
            }
        }

        return $filteredMoreDetailsOptions;
    }

    /**
     * Maps a content model name to its viewer identifier, or null if unmapped.
     *
     * @see MODEL_VIEWER_MAP
     */
    protected function getViewerForModel(?string $model): ?string
    {
        if ($model === null || $model === '') {
            return null;
        }

        return self::MODEL_VIEWER_MAP[$model] ?? null;
    }

    /**
     * Formats a Unix timestamp or date string as `m/d/Y h:i a` in the server's local timezone.
     *
     * @param int|string|null $value
     */
    private function formatDisplayDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $date = new \DateTimeImmutable('@' . (int)$value);
            } else {
                $date = new \DateTimeImmutable((string)$value);
            }
        } catch (\Exception $e) {
            return null;
        }

        $date = $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $date->format('m/d/Y h:i a');
    }

    /**
     * Adds a 'thumbnail' URL to each related-place entry by fetching the
     * taxonomy term. Falls back to the vocabulary default image when no
     * term-specific thumbnail is set.
     *
     * @param array|null $places Output of I2Object::getRelatedPlace()
     * @return array|null
     */
    private function enrichRelatedPlacesWithThumbnails(?array $places): ?array
    {
        if (empty($places)) {
            return $places;
        }
        $factory = new TaxonomyFactory();
        foreach ($places as &$place) {
            $tid                = $place['tid'] ?? null;
            $term               = ($tid !== null) ? $factory->fromTid($tid) : null;
            $thumb              = $term?->getThumbnail();
            $place['thumbnail'] = $thumb['url'] ?? null;
        }
        unset($place);
        return $places;
    }

    /**
     * Adds a 'thumbnail' URL to each related-event entry by fetching the
     * taxonomy term. Falls back to the vocabulary default image when no
     * term-specific thumbnail is set.
     *
     * @param array|null $events Output of I2Object::getRelatedEvent()
     * @return array|null
     */
    private function enrichRelatedEventsWithThumbnails(?array $events): ?array
    {
        if (empty($events)) {
            return $events;
        }
        $factory = new TaxonomyFactory();
        foreach ($events as &$event) {
            $tid               = $event['tid'] ?? null;
            $term              = ($tid !== null) ? $factory->fromTid($tid) : null;
            $thumb             = $term?->getThumbnail();
            $event['thumbnail'] = $thumb['url'] ?? null;
        }
        unset($event);
        return $events;
    }

    /**
     * Adds a 'thumbnail' URL to each related-person entry by fetching the
     * taxonomy term. Falls back to the vocabulary default image when no
     * term-specific thumbnail is set.
     *
     * @param array|null $people Output of I2Object::getRelatedPerson()
     * @return array|null
     */
    private function enrichRelatedPeopleWithThumbnails(?array $people): ?array
    {
        if (empty($people)) {
            return $people;
        }
        $factory = new TaxonomyFactory();
        foreach ($people as &$person) {
            $tid                 = $person['tid'] ?? null;
            $term                = ($tid !== null) ? $factory->fromTid($tid) : null;
            $thumb               = $term?->getThumbnail();
            $person['thumbnail'] = $thumb['url'] ?? null;
        }
        unset($person);
        return $people;
    }

    /**
     * Returns the related-person array for this object.
     * Subclasses may override to augment or replace the default list
     * (e.g. Compound merges people from child objects).
     *
     * @return array
     */
    protected function getRelatedPeople(): array
    {
        return $this->mediaObject->getRelatedPerson() ?? [];
    }

    /**
     * Resolves the member_of field into an array of ['title', 'url'] pairs
     * suitable for rendering as hyperlinks in the Catalog Details section.
     *
     * member_of may arrive as a bare nid (int), a single array with 'id'/'nid',
     * or an array of such entries. Any entry whose node cannot be fetched or has
     * no title is silently skipped.
     *
     * @return array<array{title: string, url: string}>
     */
    private function resolveParentCollections(): array
    {
        $raw = $this->mediaObject->member_of;
        if (empty($raw)) {
            return [];
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $factory = new I2ObjectFactory();
        $links   = [];
        foreach ($raw as $entry) {
            $nid = is_array($entry) ? ($entry['id'] ?? ($entry['nid'] ?? null)) : $entry;
            if (!is_numeric($nid)) {
                continue;
            }
            $obj = $factory->fromNodeId((int)$nid);
            if ($obj === null) {
                continue;
            }
            $title = $obj->getTitle();
            if (empty($title)) {
                continue;
            }
            $links[] = [
                'title' => $title,
                'url'   => $obj->getUrl(),
            ];
        }
        return $links;
    }

    /**
     * Converts a simple EDTF/ISO date string to a human-readable format.
     * Handles YYYY-MM-DD → "Month Day, Year" and YYYY-MM → "Month Year".
     * Returns the original string unchanged for year-only, ranges, or any
     * value that does not match those two patterns.
     */
    private function formatEdtfDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if ($date !== false) {
                return $date->format('F j, Y');
            }
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('Y-m', $value);
            if ($date !== false) {
                return $date->format('F Y');
            }
        }
        return $value;
    }

    /**
     * Normalizes the linked_agent field into display-ready entries, each with:
     *   'label'      — role label string, e.g. "Artist (art)"
     *   'name'       — agent display name
     *   'tid'        — taxonomy term ID, or null if not present in the payload
     *   'vocabulary' — 'corporate_body', 'person', or null (inferred from vid or rel heuristic)
     *
     * @return array<array{label: string, name: string, tid: int|null, vocabulary: string|null}>
     */
    private function normalizeLinkedAgents(): array
    {
        $raw = $this->mediaObject->linked_agent;
        if (empty($raw) || !is_array($raw)) {
            return [];
        }
        if (isset($raw['name'])) {
            $raw = [$raw];
        }

        $result = [];
        foreach ($raw as $agent) {
            $name = $agent['name'] ?? null;
            if (empty($name)) {
                continue;
            }

            [$roleLabel, $code] = $this->parseRoleLabel(
                $agent['relation_label'] ?? $agent['rel'] ?? ($agent['role'] ?? '')
            );
            $label = $roleLabel !== '' ? $roleLabel : 'Creator';
            if ($code !== null) {
                $label .= " ({$code})";
            }

            $tid = isset($agent['tid']) ? (int)$agent['tid'] : null;

            // Prefer an explicit vocabulary key; fall back to rel-field heuristic.
            $vocabulary = $agent['vid'] ?? null;
            if ($vocabulary === null) {
                $rel = strtolower($agent['rel'] ?? ($agent['role'] ?? ''));
                $vocabulary = (str_contains($rel, 'corporate') || str_contains($rel, 'org'))
                    ? 'corporate_body'
                    : 'person';
            }

            $result[] = [
                'label'      => $label,
                'name'       => $name,
                'tid'        => $tid,
                'vocabulary' => $vocabulary,
            ];
        }
        return $result;
    }

    /**
     * Builds the production team from two sources:
     *   1. linked_agent field — filtered by excluding NON_PRODUCTION_TEAM_ROLES role labels.
     *   2. related_person paragraphs — filtered by matching PRODUCTION_TEAM_ROLES_RELATOR_CODES.
     *
     * Entries from both sources are merged by name so a person appearing in both
     * lists gets a single entry with their roles concatenated (e.g. "Editor, Interviewer").
     *
     * Each entry: ['role' => string, 'code' => string|null, 'name' => string].
     *
     * @param array $relatedPeople Output of I2Object::getRelatedPerson(); matched entries are
     *                             removed so they are not duplicated in the Related People section.
     * @return array<array{role: string, code: string|null, name: string}>
     */
    private function buildProductionTeam(array &$relatedPeople): array
    {
				//$this->logger->debug("Building Production Team");
        $team   = [];
        $byName = []; // name → index in $team for dedup

        // --- Pass 1: linked_agent — role-label based filter ---
        $linkedAgents = $this->mediaObject->linked_agent;
        if (!empty($linkedAgents) && is_array($linkedAgents)) {
            if (isset($linkedAgents['name'])) {
                $linkedAgents = [$linkedAgents];
            }
            foreach ($linkedAgents as $agent) {
                $name = $agent['name'] ?? null;
                if (empty($name)) {
                    continue;
                }
                [$roleLabel, $code] = $this->parseRoleLabel(
                    $agent['relation_label'] ?? $agent['rel'] ?? ($agent['role'] ?? '')
                );
								if (isset($agent['rel']) || isset($agent['role'])){
									$this->logger->debug('$agent key "rel" or "role" present. Explain in code these cases', $agent);
									//TODO: if we never seen this triggered, removed unneeded logic above
								}
                $role = strtolower($roleLabel);
                if (empty($role) || in_array($role, self::NON_PRODUCTION_TEAM_ROLES)) {
                    continue;
                }
                $this->addToTeam($team, $byName, $name, ucfirst($roleLabel), $code);
            }
        }

        // --- Pass 2: related_person — relator-code based filter ---
	      // Entries added to the team are removed from $relatedPeople to prevent duplication.
	      foreach ($relatedPeople as $key => $person) {
	        $name = $person['name'] ?? null;
          if (empty($name)) {
              continue;
          }
          [$roleLabel, $code] = $this->parseRoleLabel($person['relation_label'] ?? '');
          if (empty($code) || !in_array($code, self::PRODUCTION_TEAM_ROLES_RELATOR_CODES)){
	          continue;
          }
          $this->addToTeam($team, $byName, $name, ucfirst($roleLabel), $code);
					unset($relatedPeople[$key]); // Don't Display Production Team in Related People Section also
        }
        return $team;
    }

    /**
     * Parses a role label string into [label, code], handling two formats:
     *   - "Performer (prf)"         (linked_agent form — no outer parens)
     *   - "(Photographer (pht))"    (relation_label form — outer parens present)
     *
     * Returns [label, null] when no three-letter code is found.
     *
     * @return array{string, string|null}
     */
    private function parseRoleLabel(string $raw): array
    {
        $raw = trim($raw);
        // Strip outer parentheses: "(Label (xyz))" → "Label (xyz)"
        $raw = preg_replace('/^\((.+)\)$/', '$1', $raw);
        if (preg_match('/^(.*?)\s*\(([a-zA-Z]{3})\)\s*$/', $raw, $m)) {
            return [trim($m[1]), $m[2]];
        }
        return [$raw, null];
    }

    /**
     * Adds or merges a production team member into $team, keyed by $byName.
     * When the name already exists, the role is appended with ", ".
     */
    private function addToTeam(array &$team, array &$byName, string $name, string $role, ?string $code): void
    {
        if (isset($byName[$name])) {
            $team[$byName[$name]]['role'] .= ', ' . $role;
        } else {
            $byName[$name] = count($team);
            $team[]        = ['role' => $role, 'code' => $code, 'name' => $name];
        }
    }

    /**
     * Returns the Library object associated with this archive object's library TID,
     * or null if the TID is missing or does not match a library record.
     *
     * @return \Library|null
     */
    public function getOwningLibrary(): ?\Library
    {
        $libraryTid = $this->mediaObject->library['tid'] ?? null;
        $library = new \Library();
        $library->libraryTid = $libraryTid;
        $library->find(true);

        if (!$library || empty($library->libraryId)) {
            return null;
        }
        return $library;
    }

    /**
     * Returns the libraryId of the library that owns this archive object,
     * or null if no owning library can be resolved.
     *
     * @return int|null
     */
    public function getOwningLibraryId(): ?int
    {
        $library = $this->getOwningLibrary();
        return (int)$library->libraryId ?? null;
    }


    /** Returns true if the current user may download the master (original) file. */
    protected function canCurrentUserDownloadOrignial(): bool
    {
        // anonymous download
        if ((int)$this->mediaObject->pika_anon_master_download === 1) {
            return true;
        }
        // logged in
        $user = \UserAccount::getLoggedInUser();
        if ($user && (int)$this->mediaObject->pika_master_download === 1) {
            return true;
        }
        return false;
    }

    /** Returns true if the current user may download the intermediate (low-resolution) file. */
    protected function canCurrentUserDownloadIntermediate(): bool
    {
        // anonymous download
        if ((int)$this->mediaObject->pika_anon_lc_download === 1) {
            return true;
        }
        // logged in
        $user = \UserAccount::getLoggedInUser();
        if ($user && ((int)$this->mediaObject->pika_lc_download === 1)) {
            return true;
        }
        return false;
    }

    protected function canCurrentUserRequestCopy(): bool
    {
        $owningLibrary = $this->getOwningLibrary();

        if (!$owningLibrary) {
            return false;
        }

        if ($owningLibrary->allowRequestsForArchiveMaterials) {
            return true;
        }

        return false;
    }

	protected function canCurrentUserClaimAuthorship(): bool
	{
		if ($this->mediaObject->__get('pika_claim_authorship')) {
			return true;
		}
		return false;
	}

    /**
     * Determine if the current patron can view the object.
     */
    protected function canCurrentUserView(): bool
    {
        return true;
        if ($this->mediaObject->pika_usage === 'no') {
            return false;
        }

        return true;

        $viewingRestrictions = $this->resolveViewingRestrictions();
        if (count($viewingRestrictions) === 0) {
            return true;
        }

        $canView            = false;
        $validHomeLibraries = [];
        $userPTypes         = [];

        $user = \UserAccount::getLoggedInUser();
        if ($user && $user->getHomeLibrary()) {
            $validHomeLibraries[] = $user->getHomeLibrary()->subdomain;
            $userPTypes           = $user->getRelatedPTypes();
            $linkedAccounts       = $user->getLinkedUsers();
            foreach ($linkedAccounts as $linkedAccount) {
                $validHomeLibraries[] = $linkedAccount->getHomeLibrary()->subdomain;
            }
        }

        global $locationSingleton;
        $physicalLocation         = $locationSingleton->getPhysicalLocation();
        $physicalLibrarySubdomain = null;
        if ($physicalLocation) {
            $physicalLibrary            = new \Library();
            $physicalLibrary->libraryId = $physicalLocation->libraryId;
            if ($physicalLibrary->find(true)) {
                $physicalLibrarySubdomain = $physicalLibrary->subdomain;
            }
        }

        foreach ($viewingRestrictions as $restriction) {
            $restrictionType = 'homeLibraryOrIP';
            if (strpos($restriction, ':') !== false) {
                [$restrictionType, $restriction] = explode(':', $restriction, 2);
            }
            $restrictionType  = strtolower(trim($restrictionType));
            $restrictionType  = str_replace(' ', '', $restrictionType);
            $restriction      = trim($restriction);
            $restrictionLower = strtolower($restriction);
            if ($restrictionLower === 'anonymousmasterdownload' || $restrictionLower === 'verifiedmasterdownload') {
                continue;
            }

            if ($restrictionType === 'homelibraryorip' || $restrictionType === 'patronsfrom') {
                $libraryDomain = trim($restriction);
                if ($restrictionLower === 'default' || array_search($libraryDomain, $validHomeLibraries, true) !== false) {
                    $canView = true;
                    break;
                }
            }

            if ($restrictionType === 'homelibraryorip' || $restrictionType === 'withinlibrary') {
                $libraryDomain = trim($restriction);
                if ($libraryDomain === $physicalLibrarySubdomain) {
                    $canView = true;
                    break;
                }
            }

            if ($restrictionType === 'ptypes' || $restrictionType === 'ptype') {
                $validPTypes = array_map('trim', explode(',', $restriction));
                foreach ($validPTypes as $pType) {
                    if (array_search($pType, $userPTypes, true) !== false) {
                        $canView = true;
                        break 2;
                    }
                }
            }
        }

        return $canView;
    }

    /** Parses the raw `pika_access_limits` field into an array of restriction strings. */
    protected function resolveViewingRestrictions(): array
    {
        $raw = $this->mediaObject->pika_access_limits ?? null;
        if ($raw === null) {
            return [];
        }

        if (is_string($raw)) {
            $rawArray = preg_split('/[\r\n;]+/', $raw);
        } elseif (is_array($raw)) {
            $rawArray = $raw;
        } else {
            $this->logger->warning('Unexpected type for pika_access_limits.', ['type' => gettype($raw)]);
            return [];
        }

        return array_values(array_filter($rawArray));
    }

    /**
     * Parses a single restriction string into a keyed array.
     *
     * Supports `key:value` and `key:val1,val2` forms; bare keys map to `1`.
     *
     * @param string $restriction
     * @return array<string, int|string[]>
     */
    protected function parseRestriction($restriction)
    {
        // has paramaters
        if (strstr($restriction, ':')) {
            $pieces = explode(':', $restriction);
            $k = trim($pieces[0]);
            // has multipule parameters
            if (strstr($pieces[1], ',')) {
                $subs = explode(',', $pieces[1]);
                foreach ($subs as $key => $val) {
                    $subs[$key] = trim($val);
                }
                // has single parameter
            } else {
                $v = trim($pieces[1]);
                $restrictions[$k] = [$v];
                return $restrictions;
            }
            $restrictions[$k] = $subs;
            return $restrictions;
        }
        $k = trim($restriction);
        $restrictions[$k] = 1;
        return $restrictions;
    }

    /**
     * Normalizes a Drupal link field (single item or array of items) into a
     * consistent array of ['uri' => string, 'title' => string] entries.
     *
     * @param mixed  $raw
     * @param string $titleFallback Text to use when the item has no title; defaults to the URI.
     * @return array<array{uri: string, title: string}>
     */
    private function normalizeLinkField($raw, string $titleFallback = ''): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $items = isset($raw['uri']) ? [$raw] : (array)$raw;
        $links = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $uri = trim($item['uri'] ?? '');
            if ($uri === '') {
                continue;
            }
            $links[] = [
                'uri'   => $uri,
                'title' => trim($item['title'] ?? '') ?: ($titleFallback ?: $uri),
            ];
        }
        return $links;
    }

    /**
     * Rewrites the host portion of a genealogy link URI to the current library's
     * catalog URL when genealogy is enabled for the current library.
     * Returns the original URI unchanged when genealogy is not enabled.
     */
    private function rewriteGenealogyLinkUri(string $uri): string
    {
        global $library, $configArray;

        if (!$library || !$library->enableGenealogy) {
            return $uri;
        }

        $parts = parse_url($uri);
        if (!isset($parts['host'])) {
            return $uri;
        }

        $currentBase = empty($library->catalogUrl)
            ? rtrim($configArray['Site']['url'], '/')
            : ($_SERVER['REQUEST_SCHEME'] . '://' . $library->catalogUrl);

        $newUri  = $currentBase;
        $newUri .= $parts['path'] ?? '';
        if (isset($parts['query'])) {
            $newUri .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $newUri .= '#' . $parts['fragment'];
        }
        return $newUri;
    }

    /**
     * Rewrites the host portion of a catalog link URI to the current library's
     * catalogUrl, but only when the original host (or a known non-production
     * variant of it) matches a library's catalogUrl in the database.
     * Preserves the original URI when no library match is found.
     *
     * Since MLN2 is unlikely to have the corresponding title for
     * an MLN1 archive object's catalog link, preserve the original link in those cases.
     *
     * On non-production servers two additional candidate hosts are tried:
     *   - Test-server form:  first-subdomain + '2' + rest  (opac.x.org → opac2.x.org)
     *   - Local-dev form:    replace TLD with '.local'      (opac.x.org → opac.x.local)
     */
    private function rewriteCatalogLinkUri(string $uri): string
    {
        global $library, $configArray;

        $parts    = parse_url($uri);
        $linkHost = $parts['host'] ?? null;
        if ($linkHost === null || !$library || empty($library->catalogUrl)) {
            return $uri;
        }

        // Always try the exact stored host first.
        $hostsToTry = [$linkHost];

        if (empty($configArray['Site']['isProduction'])) {
            // Test-server alternate: 'opac.marmot.org' → 'opac2.marmot.org'
            $dotPos = strpos($linkHost, '.');
            if ($dotPos !== false) {
                $hostsToTry[] = substr($linkHost, 0, $dotPos) . '2' . substr($linkHost, $dotPos);
            }

            // Local-dev alternate: 'opac.marmot.org' → 'opac.marmot.local'
            $lastDotPos = strrpos($linkHost, '.');
            if ($lastDotPos !== false) {
                $hostsToTry[] = substr($linkHost, 0, $lastDotPos) . '.local';
            }
        }

        foreach ($hostsToTry as $candidateHost) {
            $matchingLibrary             = new \Library();
            $matchingLibrary->catalogUrl = $candidateHost;
            if ($matchingLibrary->find(true)) {
                $newUri  = 'https://' . $library->catalogUrl;
                $newUri .= $parts['path'] ?? '';
                if (isset($parts['query'])) {
                    $newUri .= '?' . $parts['query'];
                }
                if (isset($parts['fragment'])) {
                    $newUri .= '#' . $parts['fragment'];
                }
                return $newUri;
            }
        }

        return $uri;
    }

    private function fetchTranscriptText(string $url): ?string {
        global $configArray;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => $configArray['Islandora2']['userAgent'] ?? '',
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $response   = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);
        if ($response === false || $statusCode !== 200) {
            $this->logger->error('fetchTranscriptText failed.', [
                'url'        => $url,
                'statusCode' => $statusCode,
                'curlError'  => $curlError,
            ]);
            return null;
        }
        return $response;
    }
}
