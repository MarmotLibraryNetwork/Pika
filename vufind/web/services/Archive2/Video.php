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


class Video extends ArchiveObject
{

    public function launch()
    {
        global $interface;
        
        $video = $this->mediaObject->getVideo();
        $interface->assign('videoUrl', $video->fileUrl);

        $videoMime = $video->mime;
        $interface->assign('videoMime', $videoMime);

        $poster = $this->mediaObject->getVideoPoster();
        $interface->assign('posterUrl', $poster->fileUrl);

        $captions = $this->mediaObject->getCaptions();
        // cast to an array
        $captionsArray = json_decode(json_encode($captions), true);
        $interface->assign('captions', $captionsArray);
       
        $transcripts = $this->mediaObject->getTranscripts();
        $interface->assign('transcripts', $transcripts);
        
        parent::launch();

        $title = $this->mediaObject->getTitle();
        return parent::display('video.tpl', $title, 'Search/home-sidebar.tpl');
    }
   
}