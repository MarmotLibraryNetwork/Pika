<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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

require_once 'bootstrap.php';
use chillerlan\QRCode\{QRCode, QROptions};
//Create the QR Code if it doesn't exit or we have a reload url parameter
// todo: the $_REQUEST['id'] is always the grouped work id. If this changes use type to point to related record.
//$type     = $_REQUEST['type'];
$type     = 'GroupedWork';
$id       = $_REQUEST['id'];
//$filename = $configArray['Site']['qrcodePath'] . "/{$type}_{$id}.png";
$filename = $configArray['Site']['qrcodePath'] . '/'
	. str_replace(['.', 'http://', 'https://', '/'], '', $configArray['Site']['url'])
	."_$id.png";
// Store images by site urls because they should be different qrcodes depending on which url the
// generated qrcode is from.
// Note: $configArray['Site']['url'] is set to  $_SERVER['SERVER_NAME'] in readConfig()
if (isset($_REQUEST['reload']) || !file_exists($filename)){
	$options = new QROptions([
	 'version'          => 5,
	 'outputType'       => QRCode::OUTPUT_IMAGE_PNG,
	 'eccLevel'         => QRCode::ECC_L,
	 'imageBase64'      => true,
	 'imageTransparent' => false
	]);
// Note: $configArray['Site']['url'] is set to  $_SERVER['SERVER_NAME'] in readConfig()
	$data = $configArray['Site']['url'] . "/{$type}/{$id}/Home";
	$im = (new QRCode($options))->render($data, $filename);
}
header('Content-type: image/png');
readfile($filename);
//$timer->writeTimings(); // The $timer destruct() will write out timing messages
