<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2025  Marmot Library Network
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

exit; // prevent unintentional execution

set_time_limit(600);

define('ROOT_DIR', __DIR__);

// Composer autoloader
set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/composer');
require_once 'vendor/autoload.php';

# Removed require for legacy logger
require_once ROOT_DIR . '/sys/PEAR_Singleton.php';
require_once ROOT_DIR . '/sys/Pika/Cache/Cache.php'; // required by Solr Search Object
require_once ROOT_DIR . '/sys/Pika/Logger.php'; // required by Solr Search Object
require_once ROOT_DIR . '/sys/Timer.php';
require_once 'C:\usr\share\composer\Memcached.php';
PEAR_Singleton::init();
// logger required for config

$_SERVER['SERVER_NAME'] = 'marmot.localhost'; // Gets used in readConfig()

require_once ROOT_DIR . '/sys/ConfigArray.php';
global $configArray;
$configArray = readConfig();
// config required for solr

initCache();

$timer = new Timer();
require_once ROOT_DIR . '/sys/Search/Solr.php';
$solr = new Solr('[SOLR_URL_HERE]', 'grouped');

//echo dirname();
$itemNumbers       = file('C:\usr\local\pika\vufind\web\ItemNumbers', FILE_IGNORE_NEW_LINES);
$fieldsToReturn    = 'item_details';
$results           = [];
$noItemNumberMatch = $itemNumberMatch = 0;
foreach ($itemNumbers as $itemId){
//	$response = $solr->getRecordByIsbn([$itemId], $fieldsToReturn);
//	$response = $solr->getRecord($itemId, $fieldsToReturn);
	$itemId   = str_replace('i', '.i', $itemId);
	$response = $solr->getRecordByAlternateIds($itemId, $fieldsToReturn);
	if (empty($response)){
		$results[] = $itemId . ',' . "\r\n";
		$noItemNumberMatch++;
	}else{
		foreach ($response['item_details'] as $itemDetail){
			$itemDetailRow = stristr($itemDetail, $itemId);
			if ($itemDetailRow !== false /*&& !empty($itemDetailRow)*/){
				$itemRows      = explode('|', $itemDetailRow);
				$callNumber = $itemRows[2];
				if (!empty($callNumber)){
					$results[] = $itemId . ',"' . $callNumber . "\"\r\n";
					$itemNumberMatch++;
				} else {
					$results[] = $itemId . ',' . "\r\n";
				}
			}
		}
	}

}
file_put_contents('itemIdThenCallNumber.csv', $results);

echo "Item matches : $itemNumberMatch\n";
echo "No Item matches : $noItemNumberMatch\n";

function initCache(){
	global $configArray;
	// Set defaults if nothing set in config file.
	$host = isset($configArray['Caching']['memcache_host']) ? $configArray['Caching']['memcache_host'] : '127.0.0.1';
	$port = isset($configArray['Caching']['memcache_port']) ? $configArray['Caching']['memcache_port'] : 11211;
	$timeout = isset($configArray['Caching']['memcache_connection_timeout']) ? $configArray['Caching']['memcache_connection_timeout'] : 1;
	// Connect to Memcached with persistent
	$memCached = new Memcached('pika');
	// Caution! Since this is a persistent connection adding server adds on every page load
	if (!count($memCached->getServerList())) {
		$memCached->setOption(Memcached::OPT_NO_BLOCK, true);
		$memCached->setOption(Memcached::OPT_TCP_NODELAY, true);
		$memCached->addServer($host, $port);
	}
	return $memCached;
}



/**
 * Added to Search\Solr.php to access the solr document by solr itemId
 *
 *
 *
 * Retrieves Solr Document by an alternate Id
 * @param string     $id              An alternate Id of the Solr document to retrieve
 * @param null|string $fieldsToReturn An optional list of fields to return separated by commas
 * @return array An array of the Solr document fields of the grouped Work
 */
function getRecordByAlternateIds($id, $fieldsToReturn = null){
	/** @var Memcache $memCache */
	global $memCache;
	global $solrScope;
	if (!$fieldsToReturn) {
		$validFields    = $this->_loadValidFields();
		$fieldsToReturn = implode(',', $validFields);
	}
	//$memCacheKey  = "solr_record_{$id}_{$this->index}_{$solrScope}_{$fieldsToReturn}";
	//$solrDocArray = $memCache->get($memCacheKey);
	$solrDocArray = false;

	if ($solrDocArray == false || isset($_REQUEST['reload'])) {
		$this->pingServer();
		// Query String Parameters
		$options = [
			'q' => "alternate_ids:$id",
			'fl'            => $fieldsToReturn,
		];

		//global $timer;
		//$timer->logTime("Prepare to send get (ids) request to solr returning fields $fieldsToReturn");

		$this->client->setDefaultJsonDecoder(true); // return an associative array instead of a json object
		$this->client->setHeaders(['User-Agent' => 'SteamboatPikaCovers']); // Needed to get through
		$result = $this->client->get($this->host . '/select', $options);

		if ($this->client->isError()) {
			PEAR_Singleton::raiseError($this->client->getErrorMessage());
		} elseif (!empty($result['response']['docs'][0])) {
			$solrDocArray = $result['response']['docs'][0];
			//global $configArray;
			//$memCache->set($memCacheKey, $solrDocArray, 0, $configArray['Caching']['solr_record']);
		} else {
			$solrDocArray = [];
		}
	}
	return $solrDocArray;
}
