<?php

/*
 * Pika Discovery Layer
 * Copyright (C) 2021  Marmot Library Network
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
		$this->configArray = $configArray;
		$this->connectionString = $configArray['Catalog']['sierra_conn_php'];
		$this->logger = new Logger('CirculationSystemDrives/SierraDNA');
	}

	public function loadPtypes() {
		$ptypes = $this->fetchPtypes();
		$this->savePtypes($ptypes);
		return true;
	}

	protected function savePtypes($pTypes) {
//		$insertPtypeSql = <<<EOT
//INSERT INTO ptype
//	(pType, maxHolds,label)
//VALUES ()
//EOT;
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
		$sql = "TRUNCATE ptype";
}

	private function _connect() {
		return \pg_connect($this->connectionString);
	}

}