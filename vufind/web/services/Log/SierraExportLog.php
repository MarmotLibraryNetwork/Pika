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

require_once ROOT_DIR . '/services/Log/LogAdmin.php';

class SierraExportLog extends Log_Admin {

	public $pageTitle = 'Sierra Export Log';
	public $logTemplate = 'sierraExportLog.tpl';
	public $columnToFilterBy = 'numRecordsToProcess';

	function launch(){
		$remainingSierraRecords = new Variable('remaining_sierra_records');
		if (!empty($remainingSierraRecords->value)){
			global $interface;
			$note       = "There are {$remainingSierraRecords->value} changes to be processed from the Sierra API.";
			$alertLevel = $remainingSierraRecords->value > 500 ? 'alert-danger' : 'alert-warning';
			$alert      = "<div class='alert $alertLevel'>$note</div>";
			$interface->assign('alert', $alert);
		}

		parent::launch();
	}


	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'cataloging');
	}
}
