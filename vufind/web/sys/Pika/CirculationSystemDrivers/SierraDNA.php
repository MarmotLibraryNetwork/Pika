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
namespace Pika\CirculationSystemDrivers;

use Pika\Logger;

require_once ROOT_DIR . '/sys/Account/PType.php';
/**
 * SierraDNA.php
 *
 * Utility functions for
 *
 * @category Pika
 * @package  CirculationSystemDrivers
 * @author   Chris Froese
 *
 */
class SierraDNA {

	private $connectionString;
	private $configArray;
	private $logger;

	public function __construct() {
		global $configArray;
		$this->logger = new Logger('CirculationSystemDrives/SierraDNA');
		$this->configArray = $configArray;
		if (isset($configArray['Catalog']['sierra_conn_php'])){
			$this->connectionString = $configArray['Catalog']['sierra_conn_php'];
		} else {
			$this->connectionString = false;
			$this->logger->error("No Sierra DNA connection string set.");
		}

	}

	public function loadPtypes() {
		if(!$this->connectionString) {
			return false;
		}
		$ptypes = $this->fetchPtypes();
		if(!$ptypes) {
			return false;
		}
		$c = $this->countPtypes();
		if($c && (int)$c >= 1) {
			$this->emptyPtypeTable();
		}

		$this->savePtypes($ptypes);
		return true;
	}

	protected function savePtypes($pTypes) {
		$e = [];
		foreach ($pTypes as $pType) {
			$pt = new \PType();
			$pt->pType = $pType['ptype'];
			$pt->label = $pType['label'];
			$pt->maxHolds = $pType['maxholds'];
			$r = $pt->insert();
			if(!$r) {
				$e[] = $pt->_lastError;
			}
		}
		if(!empty($e)){
			$this->logger->error('Error(s) saving pType to database.', ['errors'=> $e]);
		}
	}

	/**
	 * Fetch sierra login names and stat group numbers from sierra DNA.
	 * Used to populate offline circs for export to be used in Sierra Offline Circulation App
	 * @return array|false Returns false on error.
	 */
	public function fetchSierraLoginsAndStatGroupNumbers(){
		$connection = $this->_connect();
		if (!$connection){
			return false;
		}
		$sql    = "SELECT name AS login, statistic_group_code_num AS statgroup FROM sierra_view.iii_user WHERE statistic_group_code_num IS NOT NULL";
		$result = pg_query($connection, $sql);
		if (!$result){
			$error = pg_result_error($result);
			$this->logger->error('Error querying SierraDNA', ['error' => $error]);
			return false;
		}
		$loginsAndStatGroups = [];
		while ($row = pg_fetch_assoc($result)) {
			$loginsAndStatGroups[$row['login']] = $row['statgroup'];
		}
		pg_close($connection);
		return $loginsAndStatGroups;
	}

	/**
	 * Fetch pTypes and maxHolds from sierra DNA.
	 * @return array|false Returns false on error.
	 */
	protected function fetchPtypes(){
		$sql            = <<<EOT
SELECT pt.value AS pType, 
       pt.name AS label, 
       pb.max_hold_num AS maxHolds
FROM sierra_view.ptype_property_myuser pt, 
     sierra_view.pblock pb
WHERE pb.ptype_code_num = pt.value
EOT;

		$con = $this->_connect();
		if(!$con) {
			return false;
		}
		$res = pg_query($con, $sql);
		if(!$res){
			$e = pg_result_error($res);
			$this->logger->error('Error querying SierraDNA', ['error' => $e]);
			return false;
		}
		$ptypes = pg_fetch_all($res);
		pg_close($con);
		return $ptypes;
	}

protected function emptyPtypeTable() {
	if (!$this->connectionString){
		return false;
	}
	$pt = new \PType();
	$sql = "TRUNCATE ptype";
	$pt->query($sql);
}

protected function countPtypes() {
	// TODO: is there a way to do count(*)
	$pt = new \PType();
	return count($pt->fetchAll());
}

	private function _connect() {
		if($this->connectionString){
			if($db = \pg_connect($this->connectionString)){
				return $db;
			}
		}
		return false;
	}

}