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

		$allowableLocationCodes = '';
		if (UserAccount::userHasRole('opacAdmin') && UserAccount::userHasRole('locationReports')){
			$allowableLocationCodes = '.*';
		}elseif (UserAccount::userHasRole('libraryAdmin') && UserAccount::userHasRole('locationReports')){
			$homeLibrary            = UserAccount::getUserHomeLibrary();
			$allowableLocationCodes = trim($homeLibrary->ilsCode) . '.*';
		}elseif (UserAccount::userHasRole('locationReports')){
			$homeLocation           = Location::getUserHomeLocation();
			$allowableLocationCodes = trim($homeLocation->code) . '.*';
		}
		$availableReports = [];
		$dh               = opendir($reportDir);
		while (false !== ($filename = readdir($dh))){
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
		$showOverdueOnly = !isset($_REQUEST['showOverdueOnly']) || $_REQUEST['showOverdueOnly'] == 'overdue';
		$interface->assign('showOverdueOnly', $showOverdueOnly);
		$now      = time();
		$fileData = [];
		if ($selectedReport){
			$filemtime = date('Y-m-d H:i:s', filemtime($reportDir . '/' . $selectedReport));
			$interface->assign('reportDateTime', $filemtime);
			$fhnd = fopen($reportDir . '/' . $selectedReport, 'r');
			if ($fhnd){
				//Read as RFC 4180; the default escape of "\" makes fgetcsv treat a title
				//ending in a backslash as an escaped quote and swallow the next field
				while (($data = fgetcsv($fhnd, 0, ',', '"', '')) !== false){
					//fgetcsv returns array(null) for a blank line
					if ($data === [null]){
						continue;
					}
					$okToInclude = true;
					if ($showOverdueOnly){
						//A patron who owes a fine but has nothing checked out still gets a row, 
						//so the amount owed is reported; see the "No items are checked out"
						//branch of SierraReports.java. Every item column on those rows is blank,
						//including the due date, so there is nothing to test, and they are always
						//kept. Around a sixth of all rows are these, and for some schools all of
						//them are, so do not fold this into the comparison below
						$dueDate = $data[12];
						if ($dueDate !== ''){
							$dueTime = strtotime($dueDate);
							if ($dueTime >= $now){
								$okToInclude = false;
							}
						}
					}
					if ($okToInclude || count($fileData) == 0){
						if (end($data) == null){
							array_pop($data);
						}
						$fileData[] = $data;
					}
				}
				$interface->assign('reportData', $fileData);
			}
		}

		if (isset($_REQUEST['download'])){
			//Build the file from the filtered rows rather than streaming it from disk,
			//so the length has to be measured from what is actually sent
			$csvHnd = fopen('php://temp', 'r+');
			foreach ($fileData as $row){
				//An empty escape keeps this RFC 4180, so quotes are doubled rather than
				//backslash escaped and a value ending in a backslash still reads back
				fputcsv($csvHnd, $row, ',', '"', '', "\r\n");
			}
			rewind($csvHnd);
			$csvContents = stream_get_contents($csvHnd);
			fclose($csvHnd);

			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename=' . $selectedReport);
			header('Content-Length:' . strlen($csvContents));
			echo $csvContents;
			exit;
		}

		$this->display('studentReport.tpl', 'Student Report');
	}

	function getAllowableRoles(){
		return ['locationReports', 'opacAdmin', 'libraryAdmin'];
	}
}
