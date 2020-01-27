<?php
/**
 * Table Definition for pin_reset
 * @see D-3218
 */
require_once 'DB/DataObject.php';

class PinReset extends DB_DataObject {
	public $__table = 'pin_reset';
	public $id;
	public $userId;
	public $selector;
	public $token;
	public $expires;

	public function keys(){
		return array('id');
	}

	public function insert() {
		$now = new DateTime('NOW');
		$now->add(new DateInterval('PT01H'));

		$this->expires  = $now->format('U');
		$this->selector = bin2hex(random_bytes(8));
		$this->token    = random_int(1000000000000000, 9999999999999999);

		parent::insert();
	}

}
