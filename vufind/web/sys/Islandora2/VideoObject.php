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

namespace Islandora2;

use CaptionsandTranscriptTraits;

require_once ROOT_DIR . '/sys/Islandora2/I2Object.php';
require_once ROOT_DIR . '/sys/Islandora2/CaptionAndTranscriptTraits.php';

class VideoObject extends I2Object
{
    use CaptionsandTranscriptTraits;

    public static function supports(array $node): bool
    {
        if (self::mediaTypeIn($node, ['video'])) {
            return true;
        }

        return false;
    }

    public function getObjectType(): string
    {
        return 'video';
    }

    /**
     * Get the primary video media
     * 
     */
    public function getVideo() {
        $media = $this->getMedia();
        foreach($media as $m) {
            if($m->bundle === 'video' && $m->use === 'Original File') {
                return $m;
            }
        }
        return null;
    }

    public function getVideoPoster() {
        $media = $this->getMedia();
        // Possible to have more than one "poster" image attached
        $images = [];
        foreach($media as $m) {
            if($m->bundle === 'image' && $m->use === 'Thumbnail Image') {
                $images[] = $m;
            }
        }
        if(empty($images)) {
            return null;
        }
        if(count($images) === 1) {
            return $images[0];
        }
        // If more than 1 thumbnail, get the newest
        $sorted = $this->sortMediaByCreatedDate($images);
        // Return the newest image
        return $sorted[0];
    }
    
}
