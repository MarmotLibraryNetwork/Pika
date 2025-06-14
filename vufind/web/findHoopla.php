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

/**
 *  Matching a file of rbDigital title itemNumbers against works in the marmot search index that also have
 *  hoopla/(overdrive) titles)
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/13/2020
 *
 */
exit; // prevent unintentional execution
define('ROOT_DIR', __DIR__);

// Composer autoloader
set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/composer");
require_once "vendor/autoload.php";

# Removed require for legacy logger
require_once ROOT_DIR . '/sys/PEAR_Singleton.php';
PEAR_Singleton::init();
// logger required for config

$_SERVER['SERVER_NAME'] = 'marmot.localhost';

require_once ROOT_DIR . '/sys/ConfigArray.php';
global $configArray;
$configArray = readConfig();
// config required for solr

require_once ROOT_DIR . '/sys/Search/Solr.php';
$solr = new Solr('http:opac.marmot.org:8080/solr', 'grouped');


$isbns = file('rbdigital.txt', FILE_IGNORE_NEW_LINES);

$fieldsToReturn = 'item_details';

$results       = [];
$noHooplaMatch = $hooplaMatch = 0;
foreach ($isbns as $isbn){
	$response = $solr->getRecordByIsbn([$isbn], $fieldsToReturn);
	if (empty($response)){
		$results[] = $isbn . ',0,not found in index'. "\r\n";
		$noHooplaMatch++;

	}else{
		$itemMatches = [];
		foreach ($response['item_details'] as $itemDetail){
//			$hooplaItem = stristr($itemDetail, 'hoopla');
			$hooplaItem = stristr($itemDetail, 'overdrive');
			if ($hooplaItem !== false /*&& !empty($hooplaItem)*/){
				$itemMatches[] = $hooplaItem;
			}
//			elseif (empty($hooplaItem)){
//				$hooplaItem;
//			}
		}
		if (!empty($itemMatches)){
			$results[] = $isbn . ',1,' . implode(' or ', $itemMatches). "\r\n";
			$hooplaMatch++;
		}else{
			$results[] = $isbn . ',0,found in index but not for hoopla'. "\r\n";
			$noHooplaMatch++;
		}
	}

}
file_put_contents('rbdigitalIsbnsForHooplaMatches.csv', $results);
file_put_contents('rbdigitalIsbnsForOverdriveMatches.csv', $results);

echo "Hoopla matches : $hooplaMatch\n";
echo "No Hoopla matches : $noHooplaMatch\n";

