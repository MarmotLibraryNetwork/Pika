<?php
/**
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

/**
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 6/14/14
 * Time: 10:10 AM
 */
date_default_timezone_set('America/Denver');
$mergeListFile = fopen("./javascript_files.txt", 'r');
$mergedFile = fopen("pika.min.js", 'w');
while (($fileToMerge = fgets($mergeListFile)) !== false){
	$fileToMerge = trim($fileToMerge);
	if (strpos($fileToMerge, '#') !== 0){
		if (file_exists($fileToMerge)){
			fwrite($mergedFile, file_get_contents($fileToMerge, true));
			fwrite($mergedFile, "\r\n");
		}else{
			echo("$fileToMerge does not exist\r\n");
		}
	}
}
fclose($mergedFile);
fclose($mergeListFile);
