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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/14/2020
 *
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/services/Admin/Libraries.php';

class Admin_HooplaInfo extends Admin_Admin {


	function launch(){
		if (isset($_REQUEST['startDate'])){
			$startDate = new DateTime($_REQUEST['startDate']);
		}else{
			$startDate = new DateTime();
			date_sub($startDate, new DateInterval('P1M')); // 1 day ago
		}
		if (isset($_REQUEST['endDate'])){
			$endDate = new DateTime($_REQUEST['endDate']);
		}else{
			$endDate = new DateTime();
		}
		$endDate->setTime(23,59,59); //second before midnight

		require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
		$driver          = new HooplaDriver();
		$startTime       = $startDate->getTimestamp();
		$endTime         = $endDate->getTimestamp();
		$libraries       = new Admin_Libraries();
		$libraryList     = $libraries->getAllObjectsByPermission(); // accounts for permissions
		$hooplaLibraries = [];
		$hooplaLibraryId = null;
		/** @var Library[] $libraryList */
		foreach ($libraryList as $library){
			if (!empty($library->hooplaLibraryID)){
				$checkOutsResponse = $driver->getLibraryHooplaTotalCheckOuts($library->hooplaLibraryID, $startTime, $endTime);
				if (isset($checkOutsResponse->checkouts)){
					$hooplaLibraries[] = [
						'hooplaLibraryId' => $library->hooplaLibraryID,
						'libraryName'     => $library->displayName,
						'checkouts'       => $checkOutsResponse->checkouts,
					];
					if (!empty($_REQUEST['hooplaId']) && !is_null($hooplaLibraryId)){
						$hooplaLibraryId = $library->hooplaLibraryID;
					}
				}

			}
		}
		global $interface;

		if (!empty($_REQUEST['hooplaId'])){
			$_REQUEST['hooplaId'] = trim(str_replace(['MWT', 'mwt'], '', $_REQUEST['hooplaId']));
			$response             = $driver->getHooplaRecordMetaData($hooplaLibraryId, $_REQUEST['hooplaId']);
			$hooplaData           = json_encode($response, JSON_PRETTY_PRINT);
			$interface->assign('hooplaRecordData', $hooplaData);
		}



		$interface->assign('startDate', $startDate->getTimestamp());
		$interface->assign('endDate', $endDate->getTimestamp());

		$interface->assign('hooplaLibraryCheckouts', $hooplaLibraries);
		$this->display('hooplaInfo.tpl', 'Hoopla API Info');

	}

	function getAllowableRoles(){
		return ['opacAdmin', 'cataloging'/*, 'libraryAdmin', 'libraryManager'*/];
	}
}
