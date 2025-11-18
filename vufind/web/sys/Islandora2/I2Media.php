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

use Pika\Logger;

/**
 * Base class for concrete Islandora 2 media objects.
 *
 * The class provides shared helpers for subclasses and keeps a reference
 * to the raw node payload returned by the Islandora 2 Pika-JSON endpoint.
 */
class I2Media
{
    protected Logger $logger;
    protected array $rawMedia;
    public string $bundle;
    public string $type;
    public string $mime;
    public string $fileUrl;
    public string $thumbnailUrl;
    public string $thumbnailMime;

    /**
      * @param array       $media   Islandora media extracted from JSON.
      */
    final public function __construct(array $media)
    {
        $this->logger = new Logger(__CLASS__);
        $this->rawMedia = $media;
        $this->bundle = isset($media['bundle']) ? $media['bundle'] : '';
        $this->type = isset($media['media_use']['name']) ? $media['media_use']['name'] : '';
        $this->mime = isset($media['mime_type']) ? $media['mime_type'] : '';
        $this->fileUrl = isset($media['mime_type']) ? $media['mime_type'] : '';
        $this->thumbnailUrl = isset($media['mime_type']) ? $media['mime_type'] : '';
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->rawMedia)) {
            return $this->rawMedia[$name];
        }
        return null;
    }

    private function extractFileUrl() {
        $file = null;
        switch ($this->bundle) {
            case 'video':
                $file = isset($this->rawMedia['media_video_file']['url']) ? $this->rawMedia['media_video_file']['url'] : null;
                break;
            case 'audio':
                $file = isset($this->rawMedia['media_audio_file']['url']) ? $this->rawMedia['media_audio_file']['url'] : null;
                break;
            case 'image':
                $file = isset($this->rawMedia['media_image']['url']) ? $this->rawMedia['media_image']['url'] : null;
                break;
            case 'file':
                $file = isset($this->rawMedia['media_file']['url']) ? $this->rawMedia['media_file']['url'] : null;
                break;
        }
        return $file;
    }

}
