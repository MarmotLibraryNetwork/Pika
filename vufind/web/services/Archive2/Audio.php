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
class Audio extends ArchiveObject
{

    public function launch()
    {
        global $interface;

        $audio = $this->mediaObject->getAudio();
        if ($audio === null) {
            $this->logger->warning('Audio media not found for node.', ['nid' => $this->mediaObject->getNodeId()]);
            $interface->assign('audioUrl', null);
            $interface->assign('audioMime', null);
        } else {
            $interface->assign('audioUrl', $audio->fileUrl);
            $interface->assign('audioMime', $audio->mime);
        }

        $thumb = $this->mediaObject->getThumbnail();
        if ($thumb === null) {
            $this->logger->warning('Thumbnail not found for audio node.', ['nid' => $this->mediaObject->getNodeId()]);
            $interface->assign('videoThumbnailUrl', null);
        } else {
            $interface->assign('videoThumbnailUrl', $thumb->fileUrl);
        }

        $captions = $this->mediaObject->getCaptions();
        $captionsArray = $captions !== null ? json_decode(json_encode($captions), true) : [];
        $interface->assign('captions', $captionsArray);

        $transcripts = $this->mediaObject->getTranscripts();
        $interface->assign('transcripts', $transcripts ?? []);

        parent::launch();

        $interface->assign('viewer', 'audio');

        $title = $this->mediaObject->getTitle();
        return parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}