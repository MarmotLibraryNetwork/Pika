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
require_once ROOT_DIR . '/sys/OverDrive/OverDriveAPIProduct.php';

class OverDriveExtractLog extends Log_Admin {

	public $pageTitle = 'OverDrive Export Log';
	public $logTemplate = 'overdriveExtractLog.tpl';
	public $columnToFilterBy = 'numProducts';
	public $filterLabel = 'Min Products Processed';


	function launch(){
		global $interface;

		//Get the number of changes that are outstanding
		$overdriveProduct              = new OverDriveAPIProduct();
		$overdriveProduct->needsUpdate = 1;
		$overdriveProduct->deleted     = 0;
		$numOutstandingChanges         = $overdriveProduct->count();
		if (!empty($numOutstandingChanges)){
			$note       = "There are {$numOutstandingChanges} titles with updates to be processed from the OverDrive API.";
			$alertLevel = $numOutstandingChanges > 1000 ? 'alert-danger' : 'alert-warning';
			$alert      = "<div class='alert $alertLevel'>$note</div>";
			$interface->assign('alert', $alert);
		}

		parent::launch();
	}

}
