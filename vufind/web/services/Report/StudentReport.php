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
 * Displays Student Reports Created by cron
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/19/14
 * Time: 2:28 PM
 */
require_once ROOT_DIR . '/services/Report/Report.php';

class Report_StudentReport extends Report_Report {
	function launch(){
		global $interface;
		global $configArray;
		$user = UserAccount::getLoggedInUser();

		//Get a list of all reports the user has access to
		$reportDir = $configArray['Site']['reportPath'];

		$allowableLocationCodes = "";
		if (UserAccount::userHasRole('opacAdmin')){
			$allowableLocationCodes = '.*';
		}elseif (UserAccount::userHasRole('libraryAdmin')){
			$homeLibrary = UserAccount::getUserHomeLibrary();
			$allowableLocationCodes = trim($homeLibrary->ilsCode) . '.*';
		}elseif (UserAccount::userHasRole('locationReports')){
			$homeLocation = Location::getUserHomeLocation();
			$allowableLocationCodes = trim($homeLocation->code) . '.*';
		}
		$availableReports = array();
		$dh  = opendir($reportDir);
		while (false !== ($filename = readdir($dh))) {
			if (is_file($reportDir . '/' . $filename)){
				if (preg_match('/(\w+)_school_report\.csv/i', $filename, $matches)){
					$locationCode = $matches[1];
					if (preg_match("/$allowableLocationCodes/", $locationCode)){
						$availableReports[$locationCode] = $filename;
					}
				}
			}
		}
		ksort($availableReports);
		$interface->assign('availableReports', $availableReports);

		$selectedReport = isset($_REQUEST['selectedReport']) ? $availableReports[$_REQUEST['selectedReport']] : reset($availableReports);
		$interface->assign('selectedReport', $selectedReport);
		$showOverdueOnly = isset($_REQUEST['showOverdueOnly']) ? $_REQUEST['showOverdueOnly'] == 'overdue': true;
		$interface->assign('showOverdueOnly', $showOverdueOnly);
		$now = time();
		$fileData = array();
		if ($selectedReport){
			$filemtime = date('Y-m-d H:i:s',filemtime($reportDir . '/' . $selectedReport));
			$interface->assign('reportDateTime', $filemtime);
			$fhnd = fopen($reportDir . '/' . $selectedReport, "r");
			if ($fhnd){
				while (($data = fgetcsv($fhnd)) !== FALSE){
					$okToInclude = true;
					if ($showOverdueOnly){
						$dueDate = $data[12];
						$dueTime = strtotime($dueDate);
						if ($dueTime >= $now){
							$okToInclude = false;
						}
					}
					if ($okToInclude || count($fileData) == 0){
						$fileData[] = $data;
					}
				}
				$interface->assign('reportData', $fileData);
			}
		}

		if (isset($_REQUEST['download'])){
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename=' . $selectedReport);
			header('Content-Length:' . filesize($reportDir . '/' . $selectedReport));
			foreach ($fileData as $row){
				foreach ($row as $index => $cell){
					if ($index != 0){
						echo(",");
					}
					if (strpos($cell, ',') != false){
						echo('"' . $cell . '"');
					}else{
						echo($cell);
					}

				}
				echo("\r\n");
			}
			exit;
		}

		$this->display('studentReport.tpl', 'Student Report');
	}

	function getAllowableRoles(){
		return array('locationReports');
	}
}
