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

require_once ROOT_DIR . '/sys/Language/Language.php';

use Language;
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
    public string $title = '';
    public string $bundle = '';
    public string $use = '';
    public string $mime = '';
    public string $fileUrl = '';
    public string $filePath = '';
    public string $thumbnailUrl = '';
    public string $thumbnailMime = '';
    public string $langName = '';
    public string $langCode = '';
    public int $created;

    /**
      * @param array       $media   Islandora media extracted from JSON.
      */
    public function __construct(array $media)
    {
        $this->logger = new Logger(__CLASS__); 
        $this->rawMedia = $media;
        $this->title = isset($media['title']) ? $media['title'] : '';
        $this->bundle = isset($media['bundle']) ? $media['bundle'] : '';
        $this->use = isset($media['media_use']['name']) ? $media['media_use']['name'] : '';
        $this->mime = isset($media['mime_type']) ? $media['mime_type'] : '';
        $this->thumbnailUrl = isset($media['thumbnail']['url']) ? $media['thumbnail']['url'] : '';
        $this->created = isset($media['created']) ? (int)$media['created'] : 0;
        $fileUrl = $this->extractFileUrl();
        $this->fileUrl = ($fileUrl !== null) ? $fileUrl : '';
        $this->langCode = isset($media['langcode']) ? $media['langcode'] : '';
        if($this->langCode !== '') {
            $langName = Language::getLanguage($this->langCode);
            if($langName && $langName !== '') {
                $this->langName = $langName;
            }
        }  
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->rawMedia)) {
            return $this->rawMedia[$name];
        }
        return null;
    }

    public function useIs($use) {
        $use = strtolower($use);
        $thisUse = strtolower($this->use);
        return $use === $thisUse;
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
