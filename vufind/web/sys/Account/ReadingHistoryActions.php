<?php

namespace Account;

class ReadingHistoryActions extends \DB_DataObject
{
	public $__table = 'user_reading_history_action';        //table name
	public $id;                                             //int(11)
	public $userId;                                         //int(11)
	public $action;                                         //varchar(45)
	public $date;                                           //int(11)

	public function __construct(){
	}
}