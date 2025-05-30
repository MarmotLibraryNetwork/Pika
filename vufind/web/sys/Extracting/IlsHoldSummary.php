<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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
 * Table Definition for loading number of holds by ils id
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 10/15/14
 * Time: 9:09 AM
 */
require_once 'DB/DataObject.php';

class IlsHoldSummary extends DB_DataObject{
	public $__table = 'ils_hold_summary';    // table name
	public $id;
	public $ilsId;
	public $numHolds;

	function keys() {
		return ['id'];
	}
}
