<?php
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