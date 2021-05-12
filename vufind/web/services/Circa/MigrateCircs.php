<?php
/*
 * Copyright (C) 2021  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 5/12/2021
 *
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Circa/OfflineCirculationEntry.php';

class MigrateCircs extends Admin_Admin {

	function launch(){
		global $interface, $configArray;
		$error = '';

		if (isset($_POST['submit'])){
			//Store information into the database
			$login     = $_REQUEST['login'];
			$password1 = $_REQUEST['password1'];
			$interface->assign('lastLogin', $login);
			$interface->assign('lastPassword1', $password1);

			$loginInfoValid = true;
			if (strlen($login) == 0){
				$error          .= "Please enter your login.<br>";
				$loginInfoValid = false;
			}
			if (strlen($password1) == 0){
				$error          .= "Please enter your login password.<br>";
				$loginInfoValid = false;
			}

			if ($loginInfoValid){
				$barcodesToCheckOut = trim($_REQUEST['barcodesToCheckOut']);

				if (!empty($barcodesToCheckOut)){
					$numItemsCheckedOut = 0;
					$data               = preg_split('/\\r\\n|\\r|\\n/', $barcodesToCheckOut); //Parse the new data
					$now                = time();

					foreach ($data as $dataRow){
						if (strlen(trim($dataRow)) != 0 && $dataRow[0] != '#'){
							$dataFields    = preg_split('/[,=]/', $dataRow, 2);
							$patronBarcode = trim(str_replace('"', '', $dataFields[0]));
							$itemBarcode   = trim(str_replace('"', '', $dataFields[1]));
							if (!empty($patronBarcode) && !empty($itemBarcode)){
								$offlineCirculationEntry                = new OfflineCirculationEntry();
								$offlineCirculationEntry->timeEntered   = $now;
								$offlineCirculationEntry->itemBarcode   = $itemBarcode;
								$offlineCirculationEntry->login         = $login;
								$offlineCirculationEntry->loginPassword = $password1;
								$offlineCirculationEntry->patronBarcode = $patronBarcode;
								$offlineCirculationEntry->type          = 'Check Out';
								$offlineCirculationEntry->status        = 'Not Processed';
								if ($offlineCirculationEntry->insert()){
									$numItemsCheckedOut++;
								}else{
									$error .= "Could not input check out item $itemBarcode to patron {$patronBarcode}.<br>";
								}
							}
						}
					}
				}
				$results = "Successfully added <strong>{$numItemsCheckedOut}</strong> items to offline circulation transactions.<br>";
			}
			if (isset($results)){
				$interface->assign('results', $results);
			}else{
				$error .= 'No Items were checked out.<br>';
			}
		}

		$interface->assign('error', $error);

		$ils_name = $configArray['Catalog']['ils'] ?? 'ILS';
		$interface->assign('ILSname', $ils_name);

		//Get view & load template
		$this->display('migrateCircs.tpl', 'Migrate Circs');
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}
}