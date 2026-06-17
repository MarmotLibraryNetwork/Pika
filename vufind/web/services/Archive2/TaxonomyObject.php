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

namespace Archive2;

require_once ROOT_DIR . '/sys/Islandora2/TaxonomyFactory.php';
require_once ROOT_DIR . '/sys/Islandora2/Request.php';
require_once ROOT_DIR . '/sys/Islandora2/TaxonomyObjectInterface.php';
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';

use Islandora2\TaxonomyFactory;
use Islandora2\TaxonomyObjectInterface;
use Islandora2\Request;
use Pika\Logger;

/**
 * Base controller for all Archive2 taxonomy term display pages.
 *
 * Mirrors the role of ArchiveObject but for taxonomy terms fetched from
 * /pika-json/taxonomy/{tid} rather than /pika-json/node/{nid}.
 */
class TaxonomyObject extends \Action
{
    protected ?TaxonomyObjectInterface $taxonomyObject = null;
    protected int $tid;
    protected Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger(__CLASS__);
        $this->tid    = (int)($_GET['id'] ?? 0);

        if ($this->tid <= 0) {
            $this->logger->warning('Invalid or missing tid in request.', ['tid' => $_GET['tid'] ?? null]);
            return;
        }

        $factory = new TaxonomyFactory();
        $this->taxonomyObject = $factory->fromTid($this->tid);

        if ($this->taxonomyObject === null) {
            $this->logger->error('Failed to create taxonomy object for tid.', ['tid' => $this->tid]);
        }
    }

    public function display($mainContentTemplate, $pageTitle = null, $sidebarTemplate = 'Search/home-sidebar.tpl')
    {
        if ($this->taxonomyObject === null) {
            return;
        }

        $pageTitle = $pageTitle ?? $this->taxonomyObject->getTitle() ?? 'Archive Term';

        parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate);
    }

    public function launch()
    {
        global $interface;
        global $configArray;

        if ($this->taxonomyObject === null) {
            $this->logger->error('Attempted to launch taxonomy page with null taxonomyObject.');
            return;
        }

        // Display hints
         // Display hints
        $interface->assign('is_object_display', false);
        $interface->assign('is_taxonomy_display', true);

        // Maps key
        $mapsKey = $configArray['Maps']['apiKey'] ?? '';

        // Expose all term fields (with field_ prefix stripped) to templates.
        $termData = $this->taxonomyObject->getTermWithoutFieldPrefix();
        foreach ($termData as $field => $value) {
            $interface->assign($field, $value);
        }

        // Named variables for templates.
        $interface->assign('tid',                    $this->taxonomyObject->getTid());
        $interface->assign('term_title',             $this->taxonomyObject->getTitle());
        $interface->assign('term_description',       $this->taxonomyObject->getDescription());
        $interface->assign('vocabulary_name',        $this->taxonomyObject->getVocabularyName());
        $vocabularyMachineName = $this->taxonomyObject->getVocabularyMachineName();
        $interface->assign('vocabulary_machine_name', $vocabularyMachineName);
        $vocabularyLabels = [
            'corporate_body' => 'Organization',
            'person'         => 'Person',
            'place'          => 'Place',
            'event'          => 'Event',
        ];
        $interface->assign('vocabulary_label', $vocabularyLabels[$vocabularyMachineName] ?? ucwords(str_replace('_', ' ', $vocabularyMachineName)));
        $interface->assign('is_shown_in_search',     $this->taxonomyObject->isShownInSearch());
        $interface->assign('pika_usage',             $this->taxonomyObject->getPikaUsage());
        $interface->assign('pid',                    $this->taxonomyObject->getPid());
        $interface->assign('thumbnail',              $this->taxonomyObject->getThumbnail());
        $interface->assign('breadcrumbText',         $this->taxonomyObject->getTitle());
        $interface->assign('lastsearch',             $_SESSION['lastArchive2SearchURL'] ?? false);
        $interface->assign('archivePage',            true);
        $interface->assign('showExploreMore',        true);
        $interface->assign('maps_key',               $mapsKey);
        // Shared fields
        $interface->assign('geolocation',            $this->taxonomyObject->getGeolocation());
        // person, corperate_body, event related fileds
        if($this->taxonomyObject->termWithoutFieldPrefix['vocabulary'] != "geo_location") {
            $interface->assign('related_place',          $this->taxonomyObject->getRelatedPlace());
            $interface->assign('related_organization',   $this->taxonomyObject->getRelatedOrganization());
            $interface->assign('related_person',         $this->taxonomyObject->getRelatedPerson());
        }
        // subjects
        if($this->taxonomyObject->termWithoutFieldPrefix['vocabulary'] != "person") {
            $interface->assign('subjects',               $this->taxonomyObject->getSubjects());
        }

        // Staff view
        $isStaffUser = \UserAccount::userHasRole('archives')
            || \UserAccount::userHasRole('opacAdmin')
            || \UserAccount::userHasRole('libraryAdmin');
        $interface->assign('isStaffUser', $isStaffUser);

        $islandoraBaseUrl = rtrim($configArray['Islandora2']['url'] ?? '', '/');
        $interface->assign('islandora_taxonomy_url',          $islandoraBaseUrl . '/taxonomy/term/' . $this->tid);
        $interface->assign('islandora_taxonomy_pika_json_url', $islandoraBaseUrl . '/pika-json/taxonomy/' . $this->tid);

    }

    /**
     * Fetch nodes that reference this taxonomy term and return them as a
     * simple array of display-ready maps.
     *
     * @return array  Each entry has 'nid', 'title', 'url', 'thumbnailUrl'.
     */
    protected function loadRelatedObjects(): array
    {
        $request = new Request();
        $raw     = $request->fetchRelatedNodes($this->tid);

        if (empty($raw)) {
            return [];
        }

        $objects = [];
        foreach ($raw as $node) {
            if (empty($node['nid'])) {
                continue;
            }

            $nid          = (int)$node['nid'];
            $title        = $node['title'] ?? '';
            $displayModel = strtolower($node['display_model'] ?? $node['field_model']['name'] ?? '');
            $urlSegment   = ISLANDORA2_DISPLAY_MODEL_URL_MAP[$displayModel] ?? null;
            $url          = $urlSegment ? '/Archive2/' . $urlSegment . '/' . $nid : '#';
            $thumbnailUrl = $node['thumbnail_url'] ?? null;

            $objects[] = [
                'nid'          => $nid,
                'title'        => $title,
                'url'          => $url,
                'thumbnailUrl' => $thumbnailUrl,
            ];
        }

        return $objects;
    }
}
