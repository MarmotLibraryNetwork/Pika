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

require_once ROOT_DIR . '/services/Archive2/ArchiveObject.php';

/* Responsible for displaying video from Islandora2 */
class Image extends ArchiveObject
{

    public function launch()
    {
        global $interface;
        global $configArray;

        // parent::launch() checks for a null mediaObject (e.g. missing/invalid id in the
        // URL) and shows the unavailable page before this method touches $this->mediaObject.
        parent::launch();

        $serviceFileUrl = null;

        // The Cantaloupe image server has no access control of its own: anyone who has
        // this URL can fetch the full image directly, forever, regardless of Pika's own
        // viewing-restriction check (and regardless of whether wrapper.tpl later decides
        // not to render the viewer markup that would have embedded it). So only build and
        // expose the URL when the current patron is actually allowed to view this object;
        // otherwise leave service_file_url null so nothing restricted ever reaches the page.
        if (!$this->canCurrentUserView()) {
            $this->logger->info('Skipping service_file_url construction; a viewing restriction is in effect.', ['nid' => $this->mediaObject->getNodeId()]);
        } else {
            $serviceFile = $this->mediaObject->getServiceFile();

            # if CORS becomes an issue, see vufind/web/services/Archive2/AJAX.php fetchCantaloupeManifest()
            if ($serviceFile && !empty($serviceFile->fileUrl)) {
                $baseUrl = $configArray['Islandora2']['url'] ?? '';
                if (empty($baseUrl)) {
                    $this->logger->error('Islandora2 URL not configured; cannot build image viewer URL.', ['nid' => $this->mediaObject->getNodeId()]);
                } else {
                    $baseUrl = rtrim($baseUrl, '/');
                    $serviceFileUrl = $baseUrl . "/cantaloupe/iiif/2/" . urlencode($serviceFile->fileUrl) . "/info.json";
                }
            } else {
                $this->logger->warning('Service file not found for image node.', ['nid' => $this->mediaObject->getNodeId()]);
            }
        }

        $interface->assign('service_file_url', $serviceFileUrl);
        $interface->assign('viewer', 'open_seadragon');

        $title = $this->mediaObject->getTitle();
        parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}