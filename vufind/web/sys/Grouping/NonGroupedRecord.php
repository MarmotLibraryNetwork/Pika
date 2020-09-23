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
 * Records that should not contribute to their normally determined Grouped Work
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 3/29/2016
 * Time: 12:05 PM
 */

require_once ROOT_DIR . '/sys/Grouping/CommonGroupingAlterationOperations.php';

class NonGroupedRecord extends CommonGroupingAlterationOperations {
	public $__table = 'nongrouped_records';
	public $id;
	public $source;
	public $recordId;
	public $notes;
	private $groupedWork;

	static function getObjectStructure(){
		global $indexingProfiles;
		$availableSources = array();
		foreach ($indexingProfiles as $profile){
			$availableSources[$profile->name] = $profile->name;
		}
		$availableSources['overdrive'] = 'overdrive';

		$structure = array(
			array(
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the merged grouped work in the database',
				'storeDb'     => true,
				'primaryKey'  => true,
			),
			array(
				'property'    => 'source',
				'type'        => 'enum',
				'values'      => $availableSources,
				'label'       => 'Source of the Record Id',
				'description' => 'The source of the record to avoid merging.',
				'default'     => 'ils',
				'storeDb'     => true,
				'required'    => true,
			),
			array(
				'property'         => 'recordId',
				'type'             => 'text',
				'size'             => 36,
				'maxLength'        => 36,
				'label'            => 'Record Id',
				'description'      => 'The id of the record that should not be merged.',
				'storeDb'          => true,
				'serverValidation' => 'validateRecordId',
				'required'         => true,
			),
			array(
				'property'    => 'notes',
				'type'        => 'textarea',
				//				'size'        => 255,
				//				'maxLength'   => 255,
				'label'       => 'Notes',
				'description' => 'Notes related to the record.',
				'storeDb'     => true,
				'required'    => true,
			),
		);
		return $structure;
	}

	public function validateRecordId(){
		//Setup validation return array
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];
		if (!empty($this->source) && !empty($this->recordId)){
			if (!$this->getGroupedWork()){
				$validationResults['validatedOk'] = false;
				$validationResults['errors'][]    = 'Did not find a grouped work for record Id ' . $this->recordId . ' in source ' . $this->source;
			}
			if (empty($this->id)){
				// for new entries, check for duplication
				$checkDuplicate           = new NonGroupedRecord();
				$checkDuplicate->source   = $this->source;
				$checkDuplicate->recordId = $this->recordId;
				if ($checkDuplicate->find()){
					$validationResults['validatedOk'] = false;
					$validationResults['errors'][]    = 'A Record To Not Merge entry for record Id ' . $this->recordId . ' in source ' . $this->source . ' already exists.';
				}
			}
		}else{
			$validationResults = [
				'validatedOk' => false,
				'errors'      => ['No Record Id or source provided.'],
			];
		}

		return $validationResults;
	}

	private function getGroupedWork(){
		if (isset($this->groupedWork)){
			return $this->groupedWork;
		}else{
			if (!empty($this->source) && !empty($this->recordId)){
				require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
				$primaryIdentifierEntry             = new GroupedWorkPrimaryIdentifier();
				$primaryIdentifierEntry->type       = $this->source;
				$primaryIdentifierEntry->identifier = $this->recordId;
				if ($primaryIdentifierEntry->find(true)){
					require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
					$groupedWork = new GroupedWork();
					if ($groupedWork->get($primaryIdentifierEntry->grouped_work_id)){
						$this->groupedWork = $groupedWork;
						return $this->groupedWork;
					}
				}
			}
			return false;
		}
	}

	private function markForForcedRegrouping(){
		$groupedWork = $this->getGroupedWork();
		if ($groupedWork){
			if (!$groupedWork->forceRegrouping()){
				global $logger;
				$logger->log('Error occurred marking grouped work ' . $groupedWork->permanent_id . ' for forced regrouping', PEAR_LOG_ERR);
			};
		}
	}

	/**
	 * Steps to take after saving data to database
	 *
	 */
	protected function followUpActions(){
		global $configArray;
		if ($configArray['Catalog']['ils'] == 'Sierra'){
			require_once ROOT_DIR . '/services/SourceAndId.php';
			$sourceAndId = new SourceAndId($this->source . ':' . $this->recordId);
			$this->markRecordForReExtraction($sourceAndId);
		}
		$this->markForForcedRegrouping();
		}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		require_once ROOT_DIR . '/services/SourceAndId.php';
		$sourceAndId  = new SourceAndId($this->source . ':' . $this->id);
		$recordDriver = new MarcRecord($sourceAndId);
		if ($recordDriver->isValid()){
			return $recordDriver->getShortTitle();
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

