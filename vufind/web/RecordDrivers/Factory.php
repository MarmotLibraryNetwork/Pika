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
 * RecordDriverFactory Class
 *
 * This is a factory class to build record drivers for accessing metadata.
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
use Pika\Logger;

class RecordDriverFactory {
	/**
	 * initSearchObject
	 *
	 * This constructs a search object for the specified engine.
	 *
	 * @access  public
	 * @param   array|AbstractFedoraObject   $record     The fields retrieved from the Solr index.
	 * @return PEAR_Error|RecordInterface
	 */
	static function initRecordDriver($record){
		global $configArray;
		global $timer;

		$timer->logTime('Starting to load record driver');

		// Determine driver path based on record type:
		if (is_object($record) && $record instanceof AbstractFedoraObject){
			return self::initIslandoraDriverFromObject($record);

		}elseif (is_array($record) && !array_key_exists('recordtype', $record)){
			require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
			$islandoraObjectCache      = new IslandoraObjectCache();
			$islandoraObjectCache->pid = $record['PID'];
			$hasExistingCache          = false;
			$driver                    = '';
			if ($islandoraObjectCache->find(true) && !isset($_REQUEST['reload'])){
				$driver           = $islandoraObjectCache->driverName;
				$path             = $islandoraObjectCache->driverPath;
				$hasExistingCache = true;
			}
			if (empty($driver)){
				if (!isset($record['RELS_EXT_hasModel_uri_s'])){
					//print_r($record);
					PEAR_Singleton::raiseError('Unable to load Driver for ' . $record['PID'] . ' ; model did not exist');
				}
				$recordType = $record['RELS_EXT_hasModel_uri_s'];
				//Get rid of islandora namespace information
				$recordType = str_replace([
					'info:fedora/islandora:', 'sp_', 'sp-', '_cmodel', 'CModel',
				], '', $recordType);

				$driverNameParts      = explode('_', $recordType);
				$normalizedRecordType = '';
				foreach ($driverNameParts as $driverPart){
					$normalizedRecordType .= (ucfirst($driverPart));
				}

				if ($normalizedRecordType == 'Compound'){
					$genre = isset($record['mods_genre_s']) ? $record['mods_genre_s'] : null;
					if ($genre != null){
						$normalizedRecordType = ucfirst($genre);
						$normalizedRecordType = str_replace(' ', '', $normalizedRecordType);

						$driver = $normalizedRecordType . 'Driver';
						$path   = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";
						if (!is_readable($path)){
							//print_r($record);
							$normalizedRecordType = 'Compound';
						}
					}
				}

				$driver = $normalizedRecordType . 'Driver';
				$path   = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";

				// If we can't load the driver, fall back to the default, index-based one:
				if (!is_readable($path)){
					PEAR_Singleton::raiseError('Unable to load Driver for ' . $recordType . " ($normalizedRecordType)");
				}else{
					if (!$hasExistingCache){
						$islandoraObjectCache      = new IslandoraObjectCache();
						$islandoraObjectCache->pid = $record['PID'];
					}
					$islandoraObjectCache->driverName = $driver;
					$islandoraObjectCache->driverPath = $path;
					$islandoraObjectCache->title      = $record['fgs_label_s'];
					if (!$hasExistingCache){
						$islandoraObjectCache->insert();
					}else{
						$islandoraObjectCache->update();
					}
				}
			}
			$timer->logTime("Found Driver for archive object from solr doc {$record['PID']} " . $driver);
		}elseif (is_array($record) && array_key_exists('recordtype', $record)){
			// for example, Load Person records (at least from buildRSS)
			// Also SearchObject_Solr  getBrowseRecordHTML()
			// Also load groupedwork in SuggestedTitles->launch()
			$driver = ucwords($record['recordtype']) . 'Record';
			$path   = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";
			// If we can't load the driver, fall back to the default, index-based one:
			if (!is_readable($path)){
				//Try without appending Record
				// e.g. GroupedWorkDriver
				$recordType      = $record['recordtype'];
				$driverNameParts = explode('_', $recordType);
				$recordType      = '';
				foreach ($driverNameParts as $driverPart){
					$recordType .= (ucfirst($driverPart));
				}

				$driver = $recordType . 'Driver';
				$path   = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";

				// If we can't load the driver, fall back to the default, index-based one:
				if (!is_readable($path)){

					$driver = 'IndexRecord';
					$path   = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";
				}
			}
		} else {
			return new PEAR_Error("Problem loading record: ". $record);
		}

		return self::initAndReturnDriver($record, $driver, $path);
	}

	static $recordDrivers = array();
	/**
	 * @param string|SourceAndId $fullId
	 * @param  GroupedWork       $groupedWork;
	 *
	 * @return ExternalEContentDriver|MarcRecord|OverDriveRecordDriver|null
	 */
	static function initRecordDriverById($fullId, $groupedWork = null){
		$logger = new Logger('RecordDriverFactory');
		require_once ROOT_DIR . '/services/SourceAndId.php';
		if ($fullId instanceof SourceAndId){
			$sourceAndId = $fullId;
		} else {
			$sourceAndId = new SourceAndId($fullId);
		}
		$fullId = $sourceAndId->getSourceAndId(); // Make sure the full Id is handled uniformly
		if (isset(RecordDriverFactory::$recordDrivers[$fullId])){
			return RecordDriverFactory::$recordDrivers[$fullId];
		}
		$recordType  = $sourceAndId->getSource();
		$recordId    = $sourceAndId->getRecordId();

		disableErrorHandler();
		if ($recordType == 'overdrive'){
			require_once ROOT_DIR . '/RecordDrivers/OverDriveRecordDriver.php';
			$recordDriver = new OverDriveRecordDriver($recordId, $groupedWork);
		}elseif ($sourceAndId->isIlsEContent){
			require_once ROOT_DIR . '/RecordDrivers/ExternalEContentDriver.php';
			$recordDriver = new ExternalEContentDriver($sourceAndId, $groupedWork);
		}else{
			$indexingProfile = $sourceAndId->getIndexingProfile();
			if (!empty($indexingProfile)){
				$driverName      = $indexingProfile->recordDriver;
				require_once ROOT_DIR . "/RecordDrivers/{$driverName}.php";
				$recordDriver = new $driverName($sourceAndId, $groupedWork);
			}else{
				//Check to see if this is an object from the archive
				$driverNameParts = explode('_', $recordType);
				$normalizedRecordType = '';
				foreach ($driverNameParts as $driverPart){
					$normalizedRecordType .= (ucfirst($driverPart));
				}
				global $configArray;
				$driver = $normalizedRecordType . 'Driver';
				$path   = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";

				// If we can't load the driver, fall back to the default, index-based one:
				if (!is_readable($path)) {
					$logger->error("Unknown record type " . $recordType);
					$recordDriver = null;
				}else{
					require_once $path;
					if (class_exists($driver)) {
						disableErrorHandler();
						$obj = new $driver($fullId);
						if (PEAR_Singleton::isError($obj)){
							$logger->warn("Error loading record driver");
						}
						enableErrorHandler();
						return $obj;
					}
				}

			}
		}
		enableErrorHandler();
		if (count(RecordDriverFactory::$recordDrivers) > 300){
			array_shift(RecordDriverFactory::$recordDrivers);
		}
		RecordDriverFactory::$recordDrivers[$fullId] = $recordDriver;
		return $recordDriver;
	}

	/**
	 * @param AbstractFedoraObject $record
	 * @return PEAR_Error|RecordInterface
	 */
	public static function initIslandoraDriverFromObject($record){
		if ($record == null){
			return null;
		}

		global $configArray;
		global $timer;

		require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
		$islandoraObjectCache      = new IslandoraObjectCache();
		$islandoraObjectCache->pid = $record->id;
		if ($islandoraObjectCache->find(true) && !isset($_REQUEST['reload'])){
			$driver = $islandoraObjectCache->driverName;
			$path   = $islandoraObjectCache->driverPath;
		}else{
			$models = $record->models;
			$timer->logTime("Loaded models for object");
			foreach ($models as $model){
				$recordType = $model;
				//Get rid of islandora namespace information
				$recordType           = str_replace(['info:fedora/islandora:', 'sp_', 'sp-', '_cmodel', 'CModel', 'islandora:'], '', $recordType);
				$driverNameParts      = explode('_', $recordType);
				$normalizedRecordType = '';
				foreach ($driverNameParts as $driverPart){
					$normalizedRecordType .= (ucfirst($driverPart));
				}

				if ($normalizedRecordType == 'Compound'){
					$genre = $record['mods_genre_s'] ?? null;
					if ($genre != null){
						$normalizedRecordType = ucfirst($genre);
						$driver               = $normalizedRecordType . 'Driver';
						$path                 = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";
						if (!is_readable($path)){
							//print_r($record);
							$normalizedRecordType = 'Compound';
						}
					}
				}
				$driver = $normalizedRecordType . 'Driver';
				$path   = "{$configArray['Site']['local']}/RecordDrivers/{$driver}.php";

				// If we can't load the driver, fall back to the default, index-based one:
				if (!is_readable($path)){
					//print_r($record);
					PEAR_Singleton::raiseError('Unable to load Driver for ' . $recordType . " ($normalizedRecordType)");
				}else{
					$islandoraObjectCache             = new IslandoraObjectCache();
					$islandoraObjectCache->pid        = $record->id;
					$islandoraObjectCache->driverName = $driver;
					$islandoraObjectCache->driverPath = $path;
					$islandoraObjectCache->title      = $record->label;
					$islandoraObjectCache->insert();
					break;
				}
			}
			$timer->logTime('Found Driver for archive object ' . $driver);

		}
		return self::initAndReturnDriver($record, $driver, $path);
	}

	/**
	 * @param string $record
	 * @return PEAR_Error|RecordInterface
	 */
	public static function initIslandoraDriverFromPid($record){
		require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
		$islandoraObjectCache      = new IslandoraObjectCache();
		$islandoraObjectCache->pid = $record;
		if ($islandoraObjectCache->find(true) && !isset($_REQUEST['reload'])){
			$driver = $islandoraObjectCache->driverName;
			$path   = $islandoraObjectCache->driverPath;
			return self::initAndReturnDriver($record, $driver, $path);
		}else{
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils     = FedoraUtils::getInstance();
			$islandoraObject = $fedoraUtils->getObject($record);
			return self::initIslandoraDriverFromObject($islandoraObject);
		}
	}

	/**
	 * @param $record
	 * @param $path
	 * @param $driver
	 * @return PEAR_Error|RecordInterface
	 */
	public static function initAndReturnDriver($record, $driver, $path){
		global $timer;
		global $memoryWatcher;
		// Build the object:
		if ($path){
			require_once $path;
			if (class_exists($driver)){
				$timer->logTime('Start of loading record driver');
				disableErrorHandler();
				/** @var RecordInterface $obj */
				$obj = new $driver($record);
				$timer->logTime('Initialized Driver');
				if (PEAR_Singleton::isError($obj)){
					$logger = new Logger(__CLASS__);
					$logger->warn("Error loading record driver");
				}
				enableErrorHandler();
				$timer->logTime('Loaded record driver for ' . $obj->getUniqueID());

				$memoryWatcher->logMemory("Created record driver for {$obj->getUniqueID()}");
				return $obj;
			}
		}

		// If we got here, something went very wrong:
		$timer->logTime('No path for record driver found');
		return new PEAR_Error("Problem loading record driver: {$driver}");
	}


}
