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
 * Table Definition for session
 */
require_once 'DB/DataObject.php';

class Session extends DB_DataObject
{
	###START_AUTOCODE
	/* the code below is auto generated do not remove the above tag */

	public $__table = 'session';                        // table name
	public $id;                              // int(11)  not_null primary_key auto_increment
	public $session_id;                      // string(128)  unique_key
	public $data;                            // blob(65535)  blob
	public $last_used;                       // int(12)  not_null
	public $created;                         // datetime(19)  not_null binary
	public $remember_me;                     // tinyint

	/* the code above is auto generated do not remove the tag below */
	###END_AUTOCODE

	function update($dataObject = false)
	{
		$r = parent::update($dataObject);
		global $interface;
		if (isset($interface)){
			$interface->assign('session', $this->session_id . ', remember me ' . $this->remember_me);
		}
		return $r;
	}

}
