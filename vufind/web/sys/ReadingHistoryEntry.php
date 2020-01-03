<?php
/**
 * Table Definition for library
 */
require_once 'DB/DataObject.php';


class ReadingHistoryEntry extends DB_DataObject 
{
	public $__table = 'user_reading_history_work';   // table name
	public $id;
	public $userId;
	public $groupedWorkPermanentId;
	public $ilsHistoryId;
	public $source;
	public $sourceId;
	public $title;
	public $author;
	public $format;
	public $checkOutDate;
	public $checkInDate;
	public $deleted;

	function keys() {
		return array('id');
 	}
}
