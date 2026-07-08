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

require_once ROOT_DIR . '/services/Archive2/ArchiveObject.php';

/* Responsible for displaying video from Islandora2 */
class Postcard extends ArchiveObject
{
    public function launch()
    {
        global $interface;
        global $configArray;

        parent::launch();

        // Get manifests for child objects
        $serviceFileUrls = [];

        // The Cantaloupe image server has no access control of its own: anyone who has
        // one of these URLs can fetch the full-resolution image directly, forever,
        // regardless of Pika's own viewing-restriction check (and regardless of whether
        // wrapper.tpl later decides not to render the viewer markup that would have
        // embedded them). So only build URLs for the postcard's child images when the
        // current patron is actually allowed to view the postcard itself; otherwise leave
        // service_file_url empty so nothing restricted ever reaches the page.
        if (!$this->canCurrentUserView()) {
            $this->logger->info('Skipping postcard service_file_url construction; a viewing restriction is in effect.', ['nid' => $this->mediaObject->getNodeId()]);
        } else {
            $childObjects = $this->mediaObject->getChildObjects();
            foreach ($childObjects as $childObject) {
                $modelName = $childObject->model['name'] ?? null;
                if ($modelName === null || strtolower($modelName) !== 'image') {
                    continue;
                }

                $serviceFile = $childObject->getServiceFile();
                $serviceFileUrl = null;

                if ($serviceFile && isset($serviceFile->fileUrl)) {
                    $baseUrl = $configArray['Islandora2']['url'] ?? '';
                    if (empty($baseUrl)) {
                        $this->logger->error('Islandora2 URL not configured; cannot build postcard image URL.', ['nid' => $childObject->getNodeId()]);
                    } else {
                        $baseUrl = rtrim($baseUrl, '/');
                        $serviceFileUrl = $baseUrl . "/cantaloupe/iiif/2/" . urlencode($serviceFile->fileUrl);
                    }
                } else {
                    $this->logger->warning('Service file not found for postcard child.', ['nid' => $childObject->getNodeId()]);
                }

                $serviceFileUrls[] = $serviceFileUrl;
            }
        }

        $interface->assign('service_file_url', $serviceFileUrls);
        $interface->assign('viewer', 'open_seadragon_multi');

        $title = $this->mediaObject->getTitle();
        parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}
