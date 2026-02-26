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

require_once ROOT_DIR . '/RecordDrivers/Islandora2Driver.php';
require_once ROOT_DIR . '/sys/Islandora2/I2Object.php';

use Islandora2\I2Object;

function getObjRelativeUrl(I2Object $obj): string
{
    
    if ($obj->getNodeId() <= 0) {
        return '#';
    }
    $displayModel = strtolower($obj->getDisplayModel());
    if (array_key_exists($displayModel, Islandora2Driver::DISPLAY_MODEL_URL_MAP)) {
        $displayModel = Islandora2Driver::DISPLAY_MODEL_URL_MAP[$displayModel];
    }

    return '/Archive2/' . $displayModel . '/' . urlencode((string)$obj->nid);
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

