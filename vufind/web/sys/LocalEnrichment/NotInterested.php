<?php
/**
 * Store grouped works that a user is not interested in seeing
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 5/1/13
 * Time: 9:51 AM
 */

class NotInterested extends DB_DataObject{
	public $id;
	public $userId;
	public $groupedRecordPermanentId;
	public $dateMarked;

	public $__table = 'user_not_interested';
}