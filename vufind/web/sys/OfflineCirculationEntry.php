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
 * Stores information related to a hold that has been placed when the system is offline.
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 7/29/13
 * Time: 9:49 AM
 */
require_once 'DB/DataObject.php';

class OfflineCirculationEntry extends DB_DataObject {
	public $__table = 'offline_circulation';
	public $id;
	public $timeEntered;
	public $timeProcessed;
	public $itemBarcode;
	public $patronBarcode;
	public $patronId;
	public $login;
	public $loginPassword;
	public $initials;
	public $initialsPassword;
	public $type; //valid values - 'Check In', 'Check Out'
	public $status; //valid values - 'Not Processed', 'Hold Placed', 'Hold Failed'
	public $notes;
}
