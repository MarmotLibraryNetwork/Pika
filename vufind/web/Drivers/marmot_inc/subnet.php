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

/**
 * Table Definition for IP subnet
 */
require_once 'DB/DataObject.php';

class subnet extends DB_DataObject {
	public $__table = 'ip_lookup';   // table name
	public $id;                      //int(25)
	public $locationid;              //int(5)
	public $location;                //varchar(255)
	public $ip;                      //varchar(255)
	public $isOpac;                  //tinyint(1)
	public $startIpVal;
	public $endIpVal;

	function keys(){
		return array('id', 'locationid', 'ip');
	}

	function label(){
		return $this->location;
	}

	function insert(){
		$this->calcIpRange();
		return parent::insert();
	}

	function update($dataObject = false){
		$this->calcIpRange();
		return parent::update($dataObject);
	}

	private function calcIpRange(){
		$ipAddress       = $this->ip;
		$subnet_and_mask = explode('/', $ipAddress);
		if (count($subnet_and_mask) == 2){
			require_once ROOT_DIR . '/Drivers/marmot_inc/ipcalc.php';
			$ipRange = getIpRange($ipAddress);
			$startIp = $ipRange[0];
			$endIp   = $ipRange[1];
		}else{
			$startIp = ip2long($ipAddress);
			$endIp   = $startIp;
		}
		$this->startIpVal = $startIp;
		$this->endIpVal   = $endIp;
	}
}
