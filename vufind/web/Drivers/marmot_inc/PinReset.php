<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
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
		return parent::insert();
	}


	/**
	 * @return bool|string Returns full reset token
	 */
	public function insertReset() {
		$now = new DateTime('NOW');
		$now->add(new DateInterval('PT01H'));

		$this->expires  = $now->format('U');
		try {
			$this->selector = bin2hex(random_bytes(8));
		} catch(Exception $e) {
			$this->selector = bin2hex(mt_rand(10000000, 900000000));
		}
		try {
			$this->token = random_int(1000000000000000, 9999999999999999);
		} catch(Exception $e) {
			$this->token = mt_rand(1000000000000000, 9999999999999999);
		}

		if(!$this->insert()) {
			return false;
		}

		return $this->selector.$this->token;
	}

}
