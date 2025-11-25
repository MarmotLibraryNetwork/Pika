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

/**
 * Shared helpers for retrieving caption and transcript media from objects.
 */
trait CaptionsandTranscriptTraits {
    
    /**
     * Return caption media entries (VTT files) from the object's media list.
     *
     * @return array Caption media objects.
     */
    public function getCaptions(): array {
        if(!method_exists(__CLASS__, 'getMedia')) {
            return [];
        }
        $media = $this->getMedia();

        $captions = [];
        foreach ($media as $m) {
            if ($m->use === 'Caption' && $m->mime === 'text/vtt') {
                $captions[] = $m;
            }
        }
        return $captions;
    }

    /**
     * Return transcript media entries (plain text or PDF) from the media list.
     *
     * @return array Transcript media objects.
     */
    public function getTranscripts(): array {
        if(!method_exists(__CLASS__, 'getMedia')) {
            return [];
        }
        $media = $this->getMedia();
        $transcripts = [];
        foreach ($media as $m) {
            if($m->use === 'Transcript' && ($m->mime === 'text/plain' || $m->mime === 'application/pdf')) {
                $trascript[] = $m;
            }
        }
        return $transcripts;
    }
}
