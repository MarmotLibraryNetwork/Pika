<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/13/2020
 *
 */

require_once ROOT_DIR . '/RecordDrivers/SideLoadedRecord.php';
class RBdigitalMagazineRecordDriver extends SideLoadedRecord {
	function getRecordType(){
		return 'RBdigitalMagazine';
		return parent::getRecordType(); // TODO: Change the autogenerated stub
	}

}