<?php

/**
 * Records that should not contribute to their normally determined Grouped Work
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
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
		$validationResults = array(
			'validatedOk' => true,
			'errors'      => array(),
		);
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
			$validationResults = array(
				'validatedOk' => false,
				'errors'      => array('No Record Id or source provided.'),
			);
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

}