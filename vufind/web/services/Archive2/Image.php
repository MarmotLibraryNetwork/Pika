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
class Image extends ArchiveObject
{

    public function launch()
    {
        global $interface;
        
        $serviceFile = $this->mediaObject->getServiceFile();
        $serviceFileUrl = null;
        if($serviceFile && isset($serviceFile->fileUrl)) {
            $serviceFileUrl = urlencode($serviceFile->fileUrl);
        }
        
        $interface->assign('service_file_url', $serviceFileUrl);
        parent::launch();

        $interface->assign('viewer', 'open_seadragon');

        $title = $this->mediaObject->getTitle();
        return parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}