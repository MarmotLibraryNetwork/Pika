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
 * Grouped Work table Database Object
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 12/6/13
 * Time: 9:50 AM
 */

class GroupedWork extends DB_DataObject {
	public $__table = 'grouped_work';    // table name
	public $id;
	public $permanent_id;
	public $full_title;
	public $author;
	public $grouping_category;
	public $grouping_language;
	public $date_updated;

	public static function validGroupedWorkId($id){
		return preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $id) === 1;
	}

//	function forceRegrouping() {
////		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
//		if (!empty($this->id)) {
//			$numRecordsMarked = 0;
//			require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
//			$groupedWorkPrimaryIdentifier                  = new GroupedWorkPrimaryIdentifier();
//			$groupedWorkPrimaryIdentifier->grouped_work_id = $this->id;
//			$groupedWorkPrimaryIdentifier->find();
//			//Get a list of all primary identifiers and mark the checksum as null.
//			while ($groupedWorkPrimaryIdentifier->fetch()) {
//				if ($groupedWorkPrimaryIdentifier->type == 'overdrive') {
//					//For OverDrive titles, just need to set dateUpdated to now.
//					$overDriveProduct              = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
//					$overDriveProduct->overdriveId = $groupedWorkPrimaryIdentifier->identifier;
//					if ($overDriveProduct->find(true)) {
//						$overDriveProduct->dateUpdated = time();
//						 if ($overDriveProduct->update()) {
//						 	$numRecordsMarked++;
//						 }
//					}
//				} else {
//					//Mark the checksum as 0.
//					require_once ROOT_DIR . '/sys/Extracting/IlsMarcChecksum.php';
//					$ilsMarcChecksum         = new IlsMarcChecksum();
//					$ilsMarcChecksum->ilsId  = $groupedWorkPrimaryIdentifier->identifier;
//					$ilsMarcChecksum->source = $groupedWorkPrimaryIdentifier->type;
//					if ($ilsMarcChecksum->find(true)) {
//						$ilsMarcChecksum->checksum = 0;
//						if($ilsMarcChecksum->update()) {
//							$numRecordsMarked++;
//						}
//					}
//				}
//			}
//			return $numRecordsMarked;
//		}
//		return false;
//	}

	function forceRegrouping(){
//		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		if (!empty($this->permanent_id)){

			// Get site name from covers directory
			global $configArray;
			$partParts = explode("/", $configArray['Site']['coverPath']);
			$siteName  = $partParts[count($partParts) - 2];

			$localPath          = $configArray['Site']['local'];
			$recordGroupingPath = realpath("$localPath/../record_grouping/");
			$commandToRun       = "java -jar $recordGroupingPath/record_grouping.jar $siteName singleWork {$this->permanent_id}";
			$result             = shell_exec($commandToRun);
			$result             = json_decode($result);
			if (!empty($result->success)){
				return true;
			}
		}

		return false;
	}

	public function forceReindex(){
		$this->date_updated = "null";  // DB Object has special processing to set an column value to null (note: the vufind.ini value is important in this)
		$numRows            = $this->update();
		return $numRows == 1;
	}

}
