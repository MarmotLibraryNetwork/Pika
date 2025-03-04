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
 * Allows staff to return titles and checkout titles while the ILS is offline
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/26/13
 * Time: 10:27 AM
 */

require_once ROOT_DIR . '/sys/Circa/OfflineCirculationEntry.php';

class Circa_OfflineCirculation extends Action{
	function launch(){
		global $interface, $configArray;
		$error = '';

		if (isset($_POST['submit'])){
			$login     = trim($_REQUEST['login']);
			//$password1 = trim($_REQUEST['password1']);
			$interface->assign('lastLogin', $login);
			//$interface->assign('lastPassword1', $password1);

			$loginInfoValid = true;
			if (empty($login)){
				$error          .= "Please enter your login.<br>";
				$loginInfoValid = false;
			}
//			if (empty($password1)){
//				$error          .= "Please enter your login password.<br>";
//				$loginInfoValid = false;
//			}

			if ($loginInfoValid){
				//$barcodesToCheckIn = trim($_REQUEST['barcodesToCheckIn']);
				$patronBarcode      = trim($_REQUEST['patronBarcode']);
				$barcodesToCheckOut = trim($_REQUEST['barcodesToCheckOut']);

				//First store any titles that are being checked in
				/*if (!empty($barcodesToCheckIn)){
					$barcodesToCheckIn = preg_split('/[\\s\\r\\n]+/', $barcodesToCheckIn);
					foreach ($barcodesToCheckIn as $barcode){
						$offlineCirculationEntry = new OfflineCirculationEntry();
						$offlineCirculationEntry->timeEntered = time();
						$offlineCirculationEntry->itemBarcode = $barcode;
						$offlineCirculationEntry->login = $login;
						$offlineCirculationEntry->loginPassword = $password1;
						$offlineCirculationEntry->type = 'Check In';
						$offlineCirculationEntry->status = 'Not Processed';
						$offlineCirculationEntry->insert();
					}
				}*/
				$numItemsCheckedOut = 0;
				if (!empty($barcodesToCheckOut) && !empty($patronBarcode)){
					$patronId              = null;
					$userObj               = new User();
					$userObj->barcode      = $patronBarcode;
					if ($userObj->find(true)){
						$patronId = $userObj->id;
					}
					$barcodesToCheckOut = preg_split('/[\\s\\r\\n]+/', $barcodesToCheckOut);
					if (!is_array($barcodesToCheckOut)){
						$barcodesToCheckOut = [$barcodesToCheckOut];
					}
					foreach ($barcodesToCheckOut as $barcode){
						$barcode = trim($barcode);
						if (!empty($barcode)){
							$offlineCirculationEntry                = new OfflineCirculationEntry();
							$offlineCirculationEntry->timeEntered   = time();
							$offlineCirculationEntry->itemBarcode   = $barcode;
							$offlineCirculationEntry->login         = $login;
							//$offlineCirculationEntry->loginPassword = $password1;
							$offlineCirculationEntry->patronBarcode = $patronBarcode;
							$offlineCirculationEntry->patronId      = $patronId;
							$offlineCirculationEntry->type          = 'Check Out';
							$offlineCirculationEntry->status        = 'Not Processed';
							if ($offlineCirculationEntry->insert()){
								$numItemsCheckedOut++;
							}else{
								$error .= "Could not check out item $barcode to patron {$patronBarcode}.<br>";
							}
						}
					}
				}
				$results = "Successfully added <strong>{$numItemsCheckedOut}</strong> items to offline circulation transactions for patron <strong>{$patronBarcode}</strong>.<br>";
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
		$this->display('offlineCirculation.tpl', 'Offline Circulation');
	}
}
