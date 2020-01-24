<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/24/2020
 *
 */

require_once 'DB/DataObject.php';

abstract class LogEntry extends DB_DataObject {

	public $__table;

	public $id;
	public $startTime;
	public $lastUpdate;
	public $endTime;

	function keys(){
		return array('id');
	}

	function getElapsedTime(){
		if (empty($this->endTime)){
			return '';
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