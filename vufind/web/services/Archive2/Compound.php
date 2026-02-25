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
class Compound extends ArchiveObject
{
    public function launch()
    {
        global $interface;

        $childrenData = [];
        $allAudio = true;
        $allVideo = true;
        $firstObjectModel = null;

        // get child objects
        if (method_exists($this->mediaObject, 'getChildren')) {
            $childObjects = $this->mediaObject->getChildren();

            // First pass: check if all children are the same type
            foreach ($childObjects as $childObject) {
                $objectModel = $childObject->getObjectModel();

                if ($firstObjectModel === null) {
                    $firstObjectModel = $objectModel;
                }

                if ($objectModel !== 'audio') {
                    $allAudio = false;
                }

                if ($objectModel !== 'video') {
                    $allVideo = false;
                }
            }

            // If all children are audio and there's more than one, use compound audio viewer
            if ($allAudio && count($childObjects) > 1) {

                $audioChildren = [];
                foreach ($childObjects as $childObject) {
                    // Extract audio data
                    $audio = $childObject->getAudio();
                    if ($audio === null) {
                        $this->logger->warning('Audio media not found for compound child.', ['nid' => $childObject->getNodeId()]);
                    }
                    $thumb = $childObject->getThumbnail();
                    $captions = $childObject->getCaptions();

                    // Cast captions to array
                    $captionsArray = $captions !== null ? json_decode(json_encode($captions), true) : [];

                    $audioChildren[] = [
                        'audioUrl' => $audio->fileUrl ?? '',
                        'audioMime' => $audio->mime ?? 'audio/mpeg',
                        'title' => $childObject->getTitle() ?? 'Untitled',
                        'thumbnailUrl' => $thumb->fileUrl ?? null,
                        'captions' => $captionsArray,
                    ];
                }

                parent::launch();

                $interface->assign('audioChildren', $audioChildren);
                $interface->assign('useCompoundAudio', true);
                $interface->assign('viewer', 'compound');

                $title = $this->mediaObject->getTitle();
                return parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
            }

            // If all children are video and there's more than one, use compound video viewer
            if ($allVideo && count($childObjects) > 1) {

                $videoChildren = [];
                foreach ($childObjects as $childObject) {
                    // Extract video data
                    $video = $childObject->getVideo();
                    if ($video === null) {
                        $this->logger->warning('Video media not found for compound child.', ['nid' => $childObject->getNodeId()]);
                    }
                    $poster = $childObject->getVideoPoster();
                    $captions = $childObject->getCaptions();

                    // Cast captions to array
                    $captionsArray = $captions !== null ? json_decode(json_encode($captions), true) : [];

                    $videoChildren[] = [
                        'videoUrl' => $video->fileUrl ?? '',
                        'videoMime' => $video->mime ?? 'video/mp4',
                        'title' => $childObject->getTitle() ?? 'Untitled',
                        'posterUrl' => $poster->fileUrl ?? null,
                        'captions' => $captionsArray,
                    ];
                }

                parent::launch();

                $interface->assign('videoChildren', $videoChildren);
                $interface->assign('useCompoundVideo', true);
                $interface->assign('viewer', 'compound');

                $title = $this->mediaObject->getTitle();
                return parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
            }

            // Otherwise, use individual viewers for each child
            foreach ($childObjects as $childObject) {
                $objectModel = $childObject->getObjectModel();
                $viewer = $this->getViewerForModel($objectModel);
                $title = $childObject->getTitle();

                $childrenData[] = [
                    'mediaObject' => $childObject,
                    'viewer' => $viewer,
                    'objectModel' => $objectModel,
                    'title' => $title,
                ];
            }
        } else {
            $this->logger->error('mediaObject does not have getChildren method.', ['nid' => $this->mediaObject->getNodeId()]);
        }

        parent::launch();

        $interface->assign('children', $childrenData);
        $interface->assign('viewer', 'compound');

        $title = $this->mediaObject->getTitle();
        return parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}
