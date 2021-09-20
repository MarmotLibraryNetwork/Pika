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
 * A Grouped Work that has been manually merged
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/29/14
 * Time: 3:14 PM
 */

require_once ROOT_DIR . '/sys/Grouping/CommonGroupingAlterationOperations.php';

class MergedGroupedWork extends CommonGroupingAlterationOperations {
	public $__table = 'grouped_work_merges';
	public $id;
	public $sourceGroupedWorkId;
	public $destinationGroupedWorkId;
	public $notes;
	public $userId;
	public $updated;

	static function getObjectStructure(){
		$structure = [
			[
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the merged grouped work in the database',
				'storeDb'     => true,
				'primaryKey'  => true,
			],
			[
				'property'         => 'destinationGroupedWorkId',
				'type'             => 'text',
				'size'             => 36,
				'maxLength'        => 40,
				'label'            => 'Destination Grouped Work Id',
				'description'      => 'The id of the grouped work to merge the work into.',
				'serverValidation' => 'validateDestination',
				'storeDb'          => true,
				'required'         => true,
			],
			[
				'property'         => 'sourceGroupedWorkId',
				'type'             => 'text',
				'size'             => 36,
				'maxLength'        => 40,
				'label'            => 'Source Grouped Work Id',
				'description'      => 'The id of the grouped work to be merged.',
				'serverValidation' => 'validateSource',
				'storeDb'          => true,
				'required'         => true,
			],
			[
				'property'    => 'notes',
				'type'        => 'textarea',
				'size'        => 250,
				'maxLength'   => 250,
				'label'       => 'Notes',
				'description' => 'Notes related to the merged work.',
				'storeDb'     => true,
				'required'    => true,
			],
			[
				'property'    => 'updated',
				'type'        => 'dateReadOnly',
				'label'       => 'Date Updated',
				'description' => 'The date the merged grouped work was last updated in the database',
			],
			//			// For display only
			//			array(
			//				'property'         => 'full_title',
			//				'type'             => 'text',
			//				'label'            => 'Grouping Title',
			//				'storeDb'          => false,
			//			),
		];
		return $structure;
	}

	function validateSource(){
		//Setup validation return array
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

		$this->sourceGroupedWorkId = trim($this->sourceGroupedWorkId);
		if (!preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $this->sourceGroupedWorkId)){
			$validationResults = [
				'validatedOk' => false,
				'errors'      => ["The format of the source {$this->sourceGroupedWorkId} is not a valid work id"],
			];
		}else{
			$destination_check                           = new MergedGroupedWork();
			$destination_check->destinationGroupedWorkId = $this->sourceGroupedWorkId;
			if ($destination_check->find()){
				$validationResults = [
					'validatedOk' => false,
					'errors'      => ["The source {$this->sourceGroupedWorkId} is a destination work for another manual merging entry. A Destination work can not be the source work in another manual merging."],
				];
			}
		}
		return $validationResults;
	}

	function validateDestination(){
		//Setup validation return array
		$validationResults              = [
			'validatedOk' => true,
			'errors'      => [],
		];
		$this->destinationGroupedWorkId = trim($this->destinationGroupedWorkId);

		if ($this->destinationGroupedWorkId == $this->sourceGroupedWorkId){
			$validationResults = [
				'validatedOk' => false,
				'errors'      => ['The source work id cannot match the destination work id'],
			];
		}elseif (!preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $this->destinationGroupedWorkId)){
			$validationResults = [
				'validatedOk' => false,
				'errors'      => ['The format of the destination is not a valid work id'],
			];
		}else{
			//Make sure the destination actually exists (not a big deal if the source doesn't since invalid ones will just be skipped)
			require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
			$groupedWork               = new GroupedWork();
			$groupedWork->permanent_id = $this->destinationGroupedWorkId;
			if (!$groupedWork->find(true)){
				$validationResults = [
					'validatedOk' => false,
					'errors'      => ['The destination work id does not exist'],
				];
			}
		}

		return $validationResults;
	}

	private function markForForcedRegrouping(){
		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
		$groupedWork               = new GroupedWork();
		$groupedWork->permanent_id = $this->destinationGroupedWorkId;
		if ($groupedWork->find(true)){
			if (!$groupedWork->forceRegrouping()){
				global $pikaLogger;
				$pikaLogger->error('Error occurred marking destination grouped work ' . $this->destinationGroupedWorkId . ' for forced regrouping');
			};
		}
		$groupedWork               = new GroupedWork();
		$groupedWork->permanent_id = $this->sourceGroupedWorkId;
		if ($groupedWork->find(true)){
			if (!$groupedWork->forceRegrouping()){
				global $pikaLogger;
				$pikaLogger->error('Error occurred marking destination grouped work ' . $this->sourceGroupedWorkId . ' for forced regrouping');
			};
		}
	}

	/**
	 * Steps to take after saving data to database
	 */
	protected function followUpActions(){
//		global $configArray;
//		if ($configArray['Catalog']['ils'] == 'Sierra'){
//			// Merge Works require re-extraction in the Sierra API Extract process
//			// The full regrouping process will ignore forced-regrouping markings for Sierra records
//			require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
//			$groupedWork               = new GroupedWork();
//			$groupedWork->permanent_id = $this->sourceGroupedWorkId;
//			if ($groupedWork->find(true)){
//				require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
//				$groupedWorkPrimaryIdentifier                  = new GroupedWorkPrimaryIdentifier();
//				$groupedWorkPrimaryIdentifier->grouped_work_id = $groupedWork->id;
//				$groupedWorkPrimaryIdentifier->find();
//				while ($groupedWorkPrimaryIdentifier->fetch()){
//					$sourceAndId = $groupedWorkPrimaryIdentifier->getSourceAndId();
//					$this->markRecordForReExtraction($sourceAndId);
//				}
//			}
//		}
		$this->markForForcedRegrouping();
	}


	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		if (!empty($this->destinationGroupedWorkId)){
			$groupedWorkDriver = new GroupedWorkDriver($this->destinationGroupedWorkId);
			if ($groupedWorkDriver->isValid()){
				return $groupedWorkDriver->getTitleShort();
			}
		}
	}

	function insert(){
		UserAccount::getLoggedInUser(); // ensure active User info is populated
		$this->userId = UserAccount::getActiveUserId();
		return parent::insert();
	}

	function update($dataObject = false){
		UserAccount::getLoggedInUser(); // ensure active User info is populated
		$this->userId = UserAccount::getActiveUserId();
		return parent::update($dataObject);
	}

}
