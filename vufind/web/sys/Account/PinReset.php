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

		$expires  = $now->format('U');
		try {
			$selector = (string)bin2hex(random_bytes(8));
		} catch(Exception $e) {
			$selector = bin2hex(mt_rand(10000000, 900000000));
		}
		try {
			$token = random_int(1000000000000000, 9999999999999999);
		} catch(Exception $e) {
			$token = mt_rand(1000000000000000, 9999999999999999);
		}
		$userId = $this->userId;
		// the pear DataObject class isn't inserting the selector for some reason. WTF??? Do a "query" to insert.
		// this is why unmaintained packages SUCK!
		// this comes with no way to check for errors.
		$sql = "insert into pin_reset (userId, selector, token, expires) values ({$userId}, '{$selector}', '{$token}', {$expires})";
		$r = $this->query($sql);
		return $selector.$token;
	}

}
