<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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
use Pika\Cache;
use Pika\Logger;
//Include code we need to use Tuque without Drupal
require_once(ROOT_DIR . '/sys/tuque/Cache.php');
require_once(ROOT_DIR . '/sys/tuque/FedoraApi.php');
require_once(ROOT_DIR . '/sys/tuque/FedoraApiSerializer.php');
require_once(ROOT_DIR . '/sys/tuque/Object.php');
require_once(ROOT_DIR . '/sys/tuque/HttpConnection.php');
require_once(ROOT_DIR . '/sys/tuque/Repository.php');
require_once(ROOT_DIR . '/sys/tuque/RepositoryConnection.php');
class FedoraUtils {
	/** @var FedoraRepository */
	private $repository;
	/** @var FedoraApi */
	private $api;
	/** @var  FedoraUtils */
	private static $singleton;

	private Pika\Logger $logger;
	private Pika\Cache $cache;

	private function __construct(){
		global $configArray;
		$this->logger = new Pika\Logger("FedoraUtils");
		$this->cache  = new Pika\Cache();
		try {
			$serializer             = new FedoraApiSerializer();
			$cache                  = new SimpleCache();
			$fedoraUrl              = $configArray['Islandora']['fedoraUrl'];
			$fedoraPassword         = $configArray['Islandora']['fedoraPassword'];
			$fedoraUser             = $configArray['Islandora']['fedoraUsername'];
			$connection             = new RepositoryConnection($fedoraUrl, $fedoraUser, $fedoraPassword);
			$connection->verifyPeer = false;
			$this->api              = new FedoraApi($connection, $serializer);
			$this->repository       = new FedoraRepository($this->api, $cache);
		} catch (Exception $e){
			$this->logger->error("Error connecting to repository", ['stack_trace' => $e->getTraceAsString()]);
		}
	}

	/**
	 * @return FedoraUtils
	 */
	public static function getInstance(){
		if (FedoraUtils::$singleton == null){
			FedoraUtils::$singleton = new FedoraUtils();
			global $timer;
			$timer->logTime('Setup Fedora Utils');
		}
		return FedoraUtils::$singleton;
	}

	/** AbstractObject */
	public function getObject($pid) {
		//Clean up the pid in case we get extra data
		$pid = str_replace('info:fedora/', '', $pid);
		$object = null;
		try{
			$object = $this->repository->getObject($pid);
		}catch (Exception $e){
			$this->logger->notice("Failed to fetch object from islandora fedora, $pid" . $e->getMessage());
			$object = null;
		}
		return $object;
	}

	/** AbstractObject */
	public function getObjectLabel($pid) {
		try{
			$object = $this->repository->getObject($pid);
		}catch (Exception $e){
			//global $logger;
			//$logger->log("Could not find object $pid due to exception $e", PEAR_LOG_WARNING);
			$object = null;
		}

		if ($object == null){
			return 'Invalid Object';
		}else{
			if (empty($object->label)){
				return $pid;
			}else{
				return $object->label;
			}
		}
	}

	/**
	 * @param AbstractObject $archiveObject
	 * @param string $size
	 * @param string $defaultType
	 * @return string
	 */
	function getObjectImageUrl($archiveObject, $size = 'small', $defaultType = null){
		global $configArray;
		$objectUrl = $configArray['Islandora']['objectUrl'];

		if ($size == 'thumbnail'){
			if ($archiveObject && $archiveObject->getDatastream('TN') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else if ($archiveObject && $archiveObject->getDatastream('SC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/SC/view';
			}else {
				//return a placeholder
				return $this->getPlaceholderImage($defaultType);
			}
		}elseif ($size == 'small'){
			if ($archiveObject && $archiveObject->getDatastream('SC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/SC/view';
			}elseif ($archiveObject && $archiveObject->getDatastream('TN') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else{
				//return a placeholder
				return $this->getPlaceholderImage($defaultType);
			}
		}elseif ($size == 'medium'){
			if ($archiveObject && $archiveObject->getDatastream('MC') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/MC/view';
			}elseif ($archiveObject && $archiveObject->getDatastream('MEDIUM_SIZE') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/MEDIUM_SIZE/view';
			}elseif ($archiveObject && $archiveObject->getDatastream('PREVIEW') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/PREVIEW/view';
			}elseif ($archiveObject && $archiveObject->getDatastream('TN') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else{
				return $this->getObjectImageUrl($archiveObject, 'small', $defaultType);
			}
		}if ($size == 'large'){
			if ($archiveObject && $archiveObject->getDatastream('JPG') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/JPG/view';
			}elseif ($archiveObject && $archiveObject->getDatastream('LC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/LC/view';
			}elseif ($archiveObject && $archiveObject->getDatastream('PREVIEW') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/PREVIEW/view';
			}else{
				return $this->getObjectImageUrl($archiveObject, 'medium', $defaultType);
			}
		}
	}

	public function getPlaceholderImage($defaultType) {
		global $configArray;
		if ($defaultType == 'personCModel' || $defaultType == 'person') {
			return '/interface/themes/responsive/images/people.png';
		}elseif ($defaultType == 'placeCModel' || $defaultType == 'place'){
			return '/interface/themes/responsive/images/places.png';
		}elseif ($defaultType == 'eventCModel' || $defaultType == 'event'){
			return '/interface/themes/responsive/images/events.png';
		}else{
			return '/interface/themes/responsive/images/History.png';
		}
	}

	/**
	 * Retrieves MODS data for the specified object
	 *
	 * @param FedoraObject $archiveObject
	 *
	 * @return SimpleXMLElement
	 */
	public function getModsData($archiveObject){
		global $timer;
		if (array_key_exists($archiveObject->id, $this->modsCache)) {
			$modsData = $this->modsCache[$archiveObject->id];
		}else{
			$modsStream = $archiveObject->getDatastream('MODS');
			if ($modsStream){
				$timer->logTime('Retrieved mods stream from fedora ' . $archiveObject->id);
				try{
					$modsData = $modsStream->content;
				}catch (Exception $e){
					echo("Unable to load MODS data for " . $archiveObject->id);
				}
				$timer->logTime('Retrieved mods stream content from fedora ' . $archiveObject->id);
				$this->modsCache[$archiveObject->id] = $modsData;
			}else{
				return null;
			}
		}
		return $modsData;
	}

	private $modsCache = array();

	public function doSparqlQuery($query){
		$results = $this->repository->ri->sparqlQuery($query);
		return $results;
	}

	/**
	 * @param FedoraObject $archiveObject
	 * @return bool
	 */
	public function isObjectValidForPika($archiveObject){
		global $timer;
		global $configArray;
		$isValid = $this->cache->get('islandora_object_valid_in_pika_' . $archiveObject->id);
		if (!is_null($isValid) && !isset($_REQUEST['reload'])){
			return $isValid == 1;
		}else{
			$mods = FedoraUtils::getInstance()->getModsData($archiveObject);
			if (strlen($mods) > 0) {
				$includeInPika = $this->getModsValue('includeInPika', 'marmot', $mods);
				$okToAdd = $includeInPika != 'no';

				if ($configArray['Site']['isProduction']) {
					$okToAdd = ($includeInPika != 'no' && $includeInPika != 'testOnly');
				}else{
					$okToAdd = $includeInPika != 'no';
				}
			} else {
				//If we don't get mods, exclude from the display
				$okToAdd = false;
			}
			$timer->logTime("Checked if {$archiveObject->id} is valid to include");
			global $configArray;
			$this->cache->set('islandora_object_valid_in_pika_' . $archiveObject->id, $okToAdd ? 1 : 0, $configArray['Caching']['islandora_object_valid']);
			return $okToAdd;
		}
	}

	/**
	 * @param string $pid
	 * @return bool
	 */
	public function isPidValidForPika($pid){

		$isValid = $this->cache->get('islandora_object_valid_in_pika_' . $pid);
		if (!is_null($isValid) && !isset($_REQUEST['reload'])){
			return $isValid == 1;
		}else{
			$archiveObject = $this->getObject($pid);
			if ($archiveObject != null) {
				return $this->isObjectValidForPika($archiveObject);
			}else{
				global $configArray;
				$this->cache->set('islandora_object_valid_in_pika_' . $pid, 0, $configArray['Caching']['islandora_object_valid']);
				return false;
			}

		}
	}

	public function getModsAttribute($attribute, $snippet){
		if (preg_match("/$attribute\\s*=\\s*[\"'](.*?)[\"']/s", $snippet, $matches)){
			return $matches[1];
		}
	}

	/**
	 * Gets a single valued field from the MODS data using regular expressions
	 *
	 * @param $tag
	 * @param $namespace
	 * @param $snippet - The snippet of XML to load from
	 * @param $includeTag - whether or not the surrounding tag should be included
	 *
	 * @return string
	 */
	public function getModsValue($tag, $namespace = null, $snippet, $includeTag = false){
		if ($namespace == null){
			if (preg_match("/<{$tag}(?=[\\s>]).*?>(.*?)<\\/$tag>/s", $snippet, $matches)){
				return $includeTag ? $matches[0] : $matches[1];
			}
		}else{
			if (preg_match("/<(?:$namespace:)?{$tag}(?=[\\s>]).*?>(.*?)<\\/(?:$namespace:)?$tag>/s", $snippet, $matches)){
				return $includeTag ? $matches[0] : $matches[1];
			}
		}
		return '';
	}

	/**
	 * Gets a multi valued field from the MODS data using regular expressions
	 *
	 * @param $tag
	 * @param $namespace
	 * @param $snippet - The snippet of XML to load from
	 * @param $includeTag - whether or not the surrounding tag should be included
	 *
	 * @return string[]
	 */
	public function getModsValues($tag, $namespace = null, $snippet, $includeTag = false){
		if ($namespace == null){
			if (preg_match_all("/<{$tag}(?=[\\s>]).*?>(.*?)<\\/$tag>/s", $snippet, $matches, PREG_PATTERN_ORDER)){
				if ($includeTag) {
					$match = self::decodeXmlCharacterReferences($matches[0]);
				} else {
					$match = self::decodeXmlCharacterReferences($matches[1]);
				}
				return $match;
			}
		}else{
			if (preg_match_all("/<(?:$namespace:)?{$tag}(?=[\\s>]).*?>(.*?)<\\/(?:$namespace:)?{$tag}>/s", $snippet, $matches, PREG_PATTERN_ORDER)){
				if ($includeTag) {
					$match = self::decodeXmlCharacterReferences($matches[0]);
				} else {
					$match = self::decodeXmlCharacterReferences($matches[1]);
				}
				return $match;
			}
		}
		return array();
	}

	static public function decodeXmlCharacterReferences($strings) {
		$newStrings = array();
		foreach ($strings as $string) {
			$newStrings[] = mb_convert_encoding($string, 'UTF-8', 'HTML-ENTITIES');
		}
		return $newStrings;
	}

	public static function cleanValues($values){
		$newValues = array();
		foreach ($values as $value){
			$newValue = FedoraUtils::cleanValue($value);
			if (strlen($newValue) > 0){
				$newValues[] = $value;
			}
		}
		return $newValues;
	}

	public static function cleanValue($value){
		return trim(strip_tags($value));
	}

	/**
	 * Return an array of pids that are part of a compound object.
	 */
	function getCompoundObjectParts($pid, $ret_title = FALSE) {
		$rels_predicate = 'isConstituentOf';
		$objects = array();

		$escaped_pid = str_replace(':', '_', $pid);
		$query = <<<EOQ
PREFIX islandora-rels-ext: <http://islandora.ca/ontology/relsext#>
SELECT ?object ?title ?seq
FROM <#ri>
WHERE {
?object <fedora-model:label> ?title ;
        <fedora-rels-ext:$rels_predicate> <info:fedora/$pid> .
OPTIONAL {
  ?object islandora-rels-ext:isSequenceNumberOf$escaped_pid ?seq
}
}
EOQ;
		$results = $this->doSparqlQuery($query);

		// Sort the objects into their proper order.
		$sort = function($a, $b) {
			$a = $a['seq']['value'];
			$b = $b['seq']['value'];
			if ($a === $b) {
				return 0;
			}
			if (empty($a)) {
				return 1;
			}
			if (empty($b)) {
				return -1;
			}
			return $a - $b;
		};
		uasort($results, $sort);

		foreach ($results as $result) {
			//TODO: Make sure the user can see this object
			$objects[$result['seq']['value']] = array(
					'pid' => $result['object']['value'],
					'title' => $result['title']['value'],
					'seq' => $result['seq']['value'],
			);
		}

		return $objects;
	}
}
