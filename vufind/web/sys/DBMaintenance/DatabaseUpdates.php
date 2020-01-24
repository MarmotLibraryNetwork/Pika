<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/23/2020
 *
 */

require_once 'DB/DataObject.php';

class DatabaseUpdates extends DB_DataObject {
	public $__table = 'db_update';
	public $update_key;
	public $date_run;
}