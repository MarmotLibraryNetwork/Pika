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

require_once ROOT_DIR . '/sys/Search/Solr.php';
require_once 'DB/DataObject.php';

abstract class SolrDataObject extends DB_DataObject{

	private Pika\Logger $logger;

	public function __construct(){
		global $pikaLogger;
		$this->logger = $pikaLogger->withName(__CLASS__);
	}

	/**
	 * Return an array describing the structure of the object fields, etc.
	 */
	abstract function getObjectStructure();

	function update($dataObject = false){
		return $this->updateDetailed(true);
	}
	private $updateStarted = false;

	function updateDetailed($insertInSolr = true){
		if ($this->updateStarted){
			return true;
		}
		$this->updateStarted = true;

		$result = parent::update();
		if (!$insertInSolr){
			$this->logger->debug('UpdateDetailed, not inserting in solr because insertInSolr was false');
			$this->updateStarted = false;
			return $result == 1;
		}elseif ($result !== false){
			$this->logger->debug('Updating Solr');
			if ($this->saveToSolr()){
				$this->updateStarted = false;
				return true;
			}else{
				$this->logger->error('Could not update Solr');
				//Could not save to solr
			}
		}else{
			$this->logger->error('Saving to database failed, not updating solr');
		}
		$this->updateStarted = false;
		return false;
	}

	function insert(){
		return $this->insertDetailed();
	}

	function insertDetailed($insertInSolr = true){
		$result = parent::insert();
		if (!$insertInSolr){
			return $result;
		}elseif ($result !== 0 && $this->saveToSolr()){
			return true;
		}
		return false;
	}

	function delete($useWhere = false){
		$result = parent::delete();
		if ($result != FALSE){
			$this->removeFromSolr();
		}
		return $result;
	}
	/**
	 * The configuration section to use when getting the url to use for Solr.
	 */
	function getConfigSection(){
		return 'Index';
	}

	/**
	 * Return an array with the name or names of the cores that contain this object
	 */
	abstract function cores();

	/**
	 * Return a unique id for the object
	 */
	abstract function solrId();

	function removeFromSolr(){
		require_once ROOT_DIR . '/sys/Search/Solr.php';
		global $configArray;
		$host = $configArray[$this->getConfigSection()]['url'];

		$this->logger->info("Deleting Record {$this->solrId()}");

		$this->initializeCores();
		/** @var Solr $index */
		foreach ($this->index as $coreName => $index){
			if ($index->deleteRecord($this->solrId())) {
				$index->commit();
			} else {
				return new PEAR_Error("Could not remove $this->solrId() from $coreName index");
			}
		}
		return true;
	}

	private $saveStarted = false;
	function saveToSolr($quick = false){
		if ($this->saveStarted){
			return true;
		}
		$this->saveStarted = true;

		$this->logger->debug("Updating {$this->solrId()} in solr");
		$objectStructure     = $this->getObjectStructure();
		$doc                 = [];
		foreach ($objectStructure as $property){
			if (!empty($property['storeSolr']) || !empty($property['properties'])){
				$doc = $this->updateSolrDocumentForProperty($doc, $property);
			}
		}

		$jsonString = $this->jsonUTF8EncodeResponse([$doc]);
		if ($jsonString){
			$this->initializeCores();
			/** @var Solr $index */
			foreach ($this->index as $coreName => $index){
				if ($index->saveRecord($jsonString)){
					if ($quick){
						$result = $index->commit();
					}
				}else{
					$this->saveStarted = false;
					return new PEAR_Error("Could not save to $coreName");
				}
			}
			$this->saveStarted = false;
			return true;
		}
		$this->saveStarted = false;
		return false;
	}

	function jsonUTF8EncodeResponse($response){
		try {
			require_once ROOT_DIR . '/sys/Utils/ArrayUtils.php';
			$utf8EncodedValue = ArrayUtils::utf8EncodeArray($response);
			$json             = json_encode($utf8EncodedValue);
			$error            = json_last_error();
			if ($error != JSON_ERROR_NONE){
				$json = false;
			}
		} catch (Exception $e){
			$json = false;
			$this->logger->error("Error encoding json data",['stack_trace'=>$e->getTraceAsString()]);
		}
		return $json;
	}

	private $index = [];
	private function initializeCores(){
		if (empty($this->index)){
			global $configArray;
			$host = $configArray[$this->getConfigSection()]['url'];
			foreach ($this->cores() as $coreName){
				$this->index[$coreName] = new Solr($host, $coreName);
			}
		}
	}

	function updateSolrDocumentForProperty($doc, $property){
		if (!empty($property['storeSolr'])){
			$propertyName = $property['property'];
			switch ($property['type']){
				case 'method':
					$methodName = $property['methodName'] ?? $property['property'];
					$results    = $this->$methodName();
					if (is_array($results)){
						array_walk($results, 'trim');
						$doc[$propertyName] = $results;
					} else{
						$value = trim($results);
						if (strlen($value)) $doc[$propertyName] = $value;
					}
					break;
				case 'crSeparated':
					if (strlen($this->$propertyName)){
						$propertyValues = explode("\r\n", $this->$propertyName);
						$values         = [];
						array_walk($propertyValues, 'trim');
						foreach ($propertyValues as $value){
							if (strlen($value)){
								$values[] = $value;
							}
						}
						$doc[$propertyName] = $values;
					}
					break;
				case 'date':
				case 'partialDate':
					if (!empty($this->$propertyName)){
						//get the date array and reformat for solr
						$dateParts = date_parse($this->$propertyName);
						if ($dateParts['year'] != false && $dateParts['month'] != false && $dateParts['day'] != false){
							$time               = trim($dateParts['year'] . '-' . $dateParts['month'] . '-' . $dateParts['day'] . 'T00:00:00Z');
							if (strlen($time)) $doc[$propertyName] = $time;
						}
					}
					break;
				default:
					if (isset($this->$propertyName)){
						$value = trim($this->$propertyName);
						if (strlen($value)) $doc[$propertyName] = $value;
					}
					break;
			}
		}elseif (!empty($property['properties'])){
			$properties = $property['properties'];
			foreach ($properties as $subProperty){
				$doc = $this->updateSolrDocumentForProperty($doc, $subProperty);
			}
		}
		return $doc;
	}

	function optimize(){
		require_once ROOT_DIR . '/sys/Search/Solr.php';
		global $configArray;
		$host = $configArray[$this->getConfigSection()]['url'];

		$this->initializeCores();
		/** @var Solr $index */
		foreach ($this->index as $coreName => $index){
			$this->logger->debug("Optimizing Solr Core! $coreName");
			$result = $index->optimize();
			if (PEAR_Singleton::isError($result)){
				PEAR_Singleton::raiseError($result);
			}
		}
		return true;
	}
}
