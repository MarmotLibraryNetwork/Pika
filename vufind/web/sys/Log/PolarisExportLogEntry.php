<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
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


require_once ROOT_DIR . '/sys/Log/LogEntry.php';

class PolarisExportLogEntry extends LogEntry {
	public $__table = 'polaris_export_log';   // table name
	public $numRecordsToProcess;
	public $numRecordsProcessed;
	public $numRecordsAdded;
	public $numRecordsUpdated;
	public $numRecordsDeleted;

	public $numErrors;
//	public $numRemainingRecords;


}