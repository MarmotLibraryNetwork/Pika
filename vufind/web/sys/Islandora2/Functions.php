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

require_once ROOT_DIR . '/sys/Islandora2/I2Object.php';
require_once ROOT_DIR . '/sys/Islandora2/TaxonomyObjectInterface.php';

use Islandora2\I2Object;
use Islandora2\TaxonomyObjectInterface;

/**
 * Maps Islandora 2 display model names (lower-cased) to their Archive2 URL action segments.
 * Used by getObjRelativeUrl() and any controller that builds object links.
 */
const ISLANDORA2_DISPLAY_MODEL_URL_MAP = [
    'audio'            => 'Audio',
    'book'             => 'Book',
    'compound object'  => 'Compound',
    'digital document' => 'DigitalDocument',
    'image'            => 'Image',
    'paged content'    => 'PagedContent',
    'postcard'         => 'Postcard',
    'video'            => 'Video',
];

/**
 * Maps taxonomy vocabulary machine names to their Archive2 URL action segments.
 * Used by getTaxonomyRelativeUrl() and any controller that builds taxonomy links.
 */
const ISLANDORA2_VOCAB_URL_MAP = [
    'person'         => 'Person',
    'corporate_body' => 'Organization',
    'geo_location'   => 'Place',
    'event'          => 'Event',
];

function getObjRelativeUrl(I2Object $obj): string
{
    if ($obj->getNodeId() <= 0) {
        return '#';
    }

    $displayModel = strtolower($obj->getDisplayModel() ?? '');
    $displayModel = ISLANDORA2_DISPLAY_MODEL_URL_MAP[$displayModel] ?? $displayModel;

    return '/Archive2/' . $displayModel . '/' . urlencode((string)$obj->getNodeId());
}

function getObjAbsoluteUrl(I2Object $obj)
{
    global $configArray;
    global $library;

    $baseUrl = $configArray['Site']['url'] ?? '';
    if (!empty($library->catalogUrl ?? '')) {
        $scheme  = $_SERVER['REQUEST_SCHEME'] ?? 'https';
        $baseUrl = $scheme . '://' . $library->catalogUrl;
    }

    return rtrim($baseUrl, '/') . getObjRelativeUrl($obj);
}

