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
 * A report of check-outs that have been placed offline with their status.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/26/13
 * Time: 10:39 AM
 */
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Circa/OfflineCirculationEntry.php';
class Circa_OfflineCirculationReport extends Admin_Admin{
	private $daysDueFromNow = 21;
	public function launch(){
		global $interface;

		if (isset($_REQUEST['startDate'])){
			$startDate = new DateTime(trim($_REQUEST['startDate']));
		}else{
			$startDate = new DateTime();
			date_sub($startDate, new DateInterval('P1D')); // 1 day ago
		}
		if (isset($_REQUEST['endDate'])){
			$endDate = new DateTime(trim($_REQUEST['endDate']));
		}else{
			$endDate = new DateTime();
		}
		$endDate->setTime(23,59,59); //second before midnight

		$exportToSierra   = !empty($_REQUEST['exportToSierra']);
		$typesToInclude   = $_REQUEST['typesToInclude'] ?? 'checkouts';
		$loginsToInclude  = $_REQUEST['loginsToInclude'] ?? '';
		$hideNotProcessed = isset($_REQUEST['hideNotProcessed']);
		$hideFailed       = isset($_REQUEST['hideFailed']);
		$hideSuccess      = isset($_REQUEST['hideSuccess']);

		$interface->assign('startDate', $startDate->getTimestamp());
		$interface->assign('endDate', $endDate->getTimestamp());
		$interface->assign('typesToInclude', $typesToInclude);
		$interface->assign('loginsToInclude', $loginsToInclude);
		$interface->assign('hideNotProcessed', $hideNotProcessed);
		$interface->assign('hideFailed', $hideFailed);
		$interface->assign('hideSuccess', $hideSuccess);


		$offlineCirculationEntries = [];
		$offlineCirculationEntryObj = new OfflineCirculationEntry();
		$offlineCirculationEntryObj->whereAdd("timeEntered >= " . $startDate->getTimestamp() . " AND timeEntered <= " . $endDate->getTimestamp());
		if ($typesToInclude == 'checkouts'){
			$offlineCirculationEntryObj->type = 'Check Out';
		}else if ($typesToInclude == 'checkins'){
			$offlineCirculationEntryObj->type = 'Check In';
		}
		if ($hideFailed){
			$offlineCirculationEntryObj->whereAdd("status != 'Processing Failed'", 'AND');
		}
		if ($hideSuccess){
			$offlineCirculationEntryObj->whereAdd("status != 'Processing Succeeded'", 'AND');
		}
		if ($hideNotProcessed){
			$offlineCirculationEntryObj->whereAdd("status != 'Not Processed'", 'AND');
		}
		if (strlen($loginsToInclude) > 0){
			$logins       = explode(',', $loginsToInclude);
			$loginsToFind = '';
			foreach ($logins as $login){
				$login = trim($login);
				if (strlen($loginsToFind) > 0){
					$loginsToFind .= ', ';
				}
				$loginsToFind .= "'$login'";
			}
			if (!empty($loginsToFind)){
				$offlineCirculationEntryObj->whereAdd("login IN ($loginsToFind)", 'AND');
			}
		}

		if ($exportToSierra){
			$sierraCircs = [];
			$dueDate     = date('YmdHi', strtotime("+$this->daysDueFromNow days"));
		}
		if (!empty($_REQUEST['fetchStatGroups'])){
			require_once ROOT_DIR . '/sys/Pika/CirculationSystemDrivers/SierraDNA.php';
			$sierraDna           = new Pika\CirculationSystemDrivers\SierraDNA();
			$loginsAndStatGroups = $sierraDna->fetchSierraLoginsAndStatGroupNumbers();
		}
		$offlineCirculationEntryObj->find();
		$totalRecords      = 0;
		$totalPassed       = 0;
		$totalFailed       = 0;
		$totalNotProcessed = 0;
		while ($offlineCirculationEntryObj->fetch()){

			// Special Actions
			if (!empty($_REQUEST['markExported'])){
				$offlineCirculationEntryObj->status = 'Processing Succeeded';
				$offlineCirculationEntryObj->notes  = 'Marked as processed for the export to Sierra Offline Circulation App';
				$offlineCirculationEntryObj->update();
			} elseif (!empty($_REQUEST['fetchStatGroups'])){
				if (!empty($offlineCirculationEntryObj->login) && array_key_exists($offlineCirculationEntryObj->login, $loginsAndStatGroups)){
					$offlineCirculationEntryObj->statGroup = $loginsAndStatGroups[$offlineCirculationEntryObj->login];
					$offlineCirculationEntryObj->update();
				}
			}

			// Regular Display
			$offlineCirculationEntries[] = clone $offlineCirculationEntryObj;
			$totalRecords++;
			if ($offlineCirculationEntryObj->status == 'Not Processed'){
				$totalNotProcessed++;
			}elseif ($offlineCirculationEntryObj->status == 'Processing Succeeded'){
				$totalPassed++;
			}else{
				$totalFailed++;
			}

			// Export for processing is Sierra Offline Circulation App
			if ($exportToSierra){
				// Type of offline circ to process
				$cirLine = ($typesToInclude == 'checkouts') ? 'o' : (($typesToInclude == 'checkins') ? 'i' : 'r' /* renewals */ );
				$cirLine .= ':';
				$cirLine .= date('YmdHi', $offlineCirculationEntryObj->timeEntered);
				$cirLine .= ':';
				$cirLine .= 'b' . $offlineCirculationEntryObj->itemBarcode;
				$cirLine .= ':';
				$cirLine .= 'b' . $offlineCirculationEntryObj->patronBarcode;
				$cirLine .= ':';
				$cirLine .= $dueDate;
				$cirLine .= ':';
				$cirLine .= $offlineCirculationEntryObj->statGroup ?? 0;  // Stat group
				$sierraCircs[] = $cirLine . PHP_EOL;
			}
		}

		// Summary stats
		$interface->assign('totalRecords', $totalRecords);
		$interface->assign('totalPassed', $totalPassed);
		$interface->assign('totalFailed', $totalFailed);
		$interface->assign('totalNotProcessed', $totalNotProcessed);
		$interface->assign('offlineCirculation', $offlineCirculationEntries);
		if ($exportToSierra){
			$interface->assign([
				'sierraCircs'   => $sierraCircs,
				'dueDateNotice' => "<strong>Due Date:</strong> $dueDate ($this->daysDueFromNow days from now)",
			]);
		}

		$this->display('offlineCirculationReport.tpl', 'Offline Circulation Report');
	}

	function getAllowableRoles() {
		return ['opacAdmin', 'libraryAdmin', 'circulationReports'];
	}
}
