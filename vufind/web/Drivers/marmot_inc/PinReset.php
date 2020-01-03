<?php
/**
 * Table Definition for pin_reset
 * @see D-3218
 */
require_once 'DB/DataObject.php';


class PinReset extends DB_DataObject
{
	public $__table = 'pin_reset';
	public $id;
	public $userId;
	public $selector;
	public $token;
	public $expires;

	public function __construct() {
		$hello = 'hello';
	}

	public function keys() {
		return array('id');
	}

}
