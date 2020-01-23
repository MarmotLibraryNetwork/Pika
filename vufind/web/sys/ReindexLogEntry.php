<?php
/**
 * Table Definition for ReindexLogEntry
 */
require_once 'DB/DataObject.php';

class ReindexLogEntry extends DB_DataObject {
	public $__table = 'reindex_log';   // table name
	public $id;
	public $startTime;
	public $lastUpdate;
	public $endTime;
	public $notes;
	public $numWorksProcessed;
	public $numListsProcessed;

	function keys(){
		return array('id');
	}

	function getElapsedTime(){
		if (!isset($this->endTime) || is_null($this->endTime)){
			return "";
		}else{
			$elapsedTimeMin = ceil(($this->endTime - $this->startTime) / 60);
			if ($elapsedTimeMin < 60){
				return $elapsedTimeMin . " min";
			}else{
				$hours   = floor($elapsedTimeMin / 60);
				$minutes = $elapsedTimeMin - (60 * $hours);
				return "$hours hours, $minutes min";
			}
		}
	}

}
