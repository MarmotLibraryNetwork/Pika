<?php
/**
 * Grouped Work table Database Object
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
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
	public $date_updated;

	public static function validGroupedWorkId($id){
		return preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $id) === 1;
	}

	function forceRegrouping() {
//		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		if (!empty($this->id)) {
			$numRecordsMarked = 0;
			require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
			$groupedWorkPrimaryIdentifier                  = new GroupedWorkPrimaryIdentifier();
			$groupedWorkPrimaryIdentifier->grouped_work_id = $this->id;
			$groupedWorkPrimaryIdentifier->find();
			//Get a list of all primary identifiers and mark the checksum as null.
			while ($groupedWorkPrimaryIdentifier->fetch()) {
				if ($groupedWorkPrimaryIdentifier->type == 'overdrive') {
					//For OverDrive titles, just need to set dateUpdated to now.
					require_once ROOT_DIR . '/sys/OverDrive/OverDriveAPIProduct.php';
					$overDriveProduct              = new OverDriveAPIProduct();
					$overDriveProduct->overdriveId = $groupedWorkPrimaryIdentifier->identifier;
					if ($overDriveProduct->find(true)) {
						$overDriveProduct->dateUpdated = time();
						 if ($overDriveProduct->update()) {
						 	$numRecordsMarked++;
						 }
					}
				} else {
					//Mark the checksum as 0.
					require_once ROOT_DIR . '/sys/Extracting/IlsMarcChecksum.php';
					$ilsMarcChecksum         = new IlsMarcChecksum();
					$ilsMarcChecksum->ilsId  = $groupedWorkPrimaryIdentifier->identifier;
					$ilsMarcChecksum->source = $groupedWorkPrimaryIdentifier->type;
					if ($ilsMarcChecksum->find(true)) {
						$ilsMarcChecksum->checksum = 0;
						if($ilsMarcChecksum->update()) {
							$numRecordsMarked++;
						}
					}
				}
			}
			return $numRecordsMarked;
		}
		return false;
	}

	public function forceReindex(){
		$this->date_updated = null;
		$numRows            = $this->update();
		return $numRows == 1;
	}

}