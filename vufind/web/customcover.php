<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 2/9/2020
 *
 */

require_once 'bootstrap.php';

switch ($_GET['size']){
	default :
	case 'small' :
		$sizeFolder = 'thumbnail';
		break;
	case 'medium' :
		$sizeFolder = 'medium';
		break;
	case 'large' :
		$sizeFolder = 'original';
		break;
}

global $configArray;
$fileName = $configArray['Site']['coverPath'] .DIR_SEP. $sizeFolder . DIR_SEP . $_GET['image'];
if (file_exists($fileName)){
	[,,$imageType] = getimagesize($fileName);
	header("Content-type: " . image_type_to_mime_type($imageType));
	readfile($fileName);
}
else {return false;}