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

require_once ROOT_DIR . '/services/Log/LogAdmin.php';
class PolarisExportLog extends Log_Admin {

	public $pageTitle = 'Polaris Export Log';
	public $logTemplate = 'polarisExportLog.tpl';
	public $columnToFilterBy = null;

	function launch(){
//		$remainingPolarisRecords = new Variable('remaining_polaris_records');
//		if (!empty($remainingPolarisRecords->value)){
//			global $interface;
//			$note       = "There are {$remainingPolarisRecords->value} changes to be processed from the Polaris API.";
//			$alertLevel = $remainingPolarisRecords->value > 500 ? 'alert-danger' : 'alert-warning';
//			$alert      = "<div class='alert $alertLevel'>$note</div>";
//			$interface->assign('alert', $alert);
//		}

		parent::launch();
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'cataloging'];
	}
}