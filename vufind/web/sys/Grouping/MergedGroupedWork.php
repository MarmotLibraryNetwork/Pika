<?php

/**
 * A Grouped Work that has been manually merged
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 7/29/14
 * Time: 3:14 PM
 */

require_once ROOT_DIR . '/sys/Grouping/CommonGroupingAlterationOperations.php';

class MergedGroupedWork extends CommonGroupingAlterationOperations {
	public $__table = 'merged_grouped_works';
	public $id;
	public $sourceGroupedWorkId;
	public $destinationGroupedWorkId;
	public $notes;

	static function getObjectStructure(){
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
				'property'         => 'destinationGroupedWorkId',
				'type'             => 'text',
				'size'             => 36,
				'maxLength'        => 40,
				'label'            => 'Destination Grouped Work Id',
				'description'      => 'The id of the grouped work to merge the work into.',
				'serverValidation' => 'validateDestination',
				'storeDb'          => true,
				'required'         => true,
			),
			array(
				'property'         => 'sourceGroupedWorkId',
				'type'             => 'text',
				'size'             => 36,
				'maxLength'        => 40,
				'label'            => 'Source Grouped Work Id',
				'description'      => 'The id of the grouped work to be merged.',
				'serverValidation' => 'validateSource',
				'storeDb'          => true,
				'required'         => true,
			),
			array(
				'property'    => 'notes',
				'type'        => 'textarea',
				'size'        => 250,
				'maxLength'   => 250,
				'label'       => 'Notes',
				'description' => 'Notes related to the merged work.',
				'storeDb'     => true,
				'required'    => true,
			),
			//			// For display only
			//			array(
			//				'property'         => 'full_title',
			//				'type'             => 'text',
			//				'label'            => 'Grouping Title',
			//				'storeDb'          => false,
			//			),
		);
		return $structure;
	}

	function validateSource(){
		//Setup validation return array
		$validationResults = array(
			'validatedOk' => true,
			'errors'      => array(),
		);

		$this->sourceGroupedWorkId = trim($this->sourceGroupedWorkId);
		if (!preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $this->sourceGroupedWorkId)){
			$validationResults = array(
				'validatedOk' => false,
				'errors'      => array("The format of the source {$this->sourceGroupedWorkId} is not a valid work id"),
			);
		}else{
			$destination_check                           = new MergedGroupedWork();
			$destination_check->destinationGroupedWorkId = $this->sourceGroupedWorkId;
			if ($destination_check->find()){
				$validationResults = array(
					'validatedOk' => false,
					'errors'      => array("The source {$this->sourceGroupedWorkId} is a destination work for another manual merging entry. A Destination work can not be the source work in another manual merging."),
				);
			}
		}
		return $validationResults;
	}

	function validateDestination(){
		//Setup validation return array
		$validationResults              = array(
			'validatedOk' => true,
			'errors'      => array(),
		);
		$this->destinationGroupedWorkId = trim($this->destinationGroupedWorkId);

		if ($this->destinationGroupedWorkId == $this->sourceGroupedWorkId){
			$validationResults = array(
				'validatedOk' => false,
				'errors'      => array('The source work id cannot match the destination work id'),
			);
		}elseif (!preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $this->destinationGroupedWorkId)){
			$validationResults = array(
				'validatedOk' => false,
				'errors'      => array('The format of the destination is not a valid work id'),
			);
		}else{
			//Make sure the destination actually exists (not a big deal if the source doesn't since invalid ones will just be skipped)
			require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
			$groupedWork               = new GroupedWork();
			$groupedWork->permanent_id = $this->destinationGroupedWorkId;
			if (!$groupedWork->find(true)){
				$validationResults = array(
					'validatedOk' => false,
					'errors'      => array('The destination work id does not exist'),
				);
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
				global $logger;
				$logger->log('Error occurred marking destination grouped work ' . $this->destinationGroupedWorkId . ' for forced regrouping', PEAR_LOG_ERR);
			};
		}
		$groupedWork               = new GroupedWork();
		$groupedWork->permanent_id = $this->sourceGroupedWorkId;
		if ($groupedWork->find(true)){
			if (!$groupedWork->forceRegrouping()){
				global $logger;
				$logger->log('Error occurred marking destination grouped work ' . $this->sourceGroupedWorkId . ' for forced regrouping', PEAR_LOG_ERR);
			};
		}
	}

	/**
	 * Steps to take after saving data to database
	 */
	protected function followUpActions(){
		global $configArray;
		if ($configArray['Catalog']['ils'] == 'Sierra'){
			// Merge Works require re-extraction in the Sierra API Extract process
			// The full regrouping process will ignore forced-regrouping markings for Sierra records
			require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
			$groupedWork               = new GroupedWork();
			$groupedWork->permanent_id = $this->sourceGroupedWorkId;
			if ($groupedWork->find(true)){
				require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
				$groupedWorkPrimaryIdentifier                  = new GroupedWorkPrimaryIdentifier();
				$groupedWorkPrimaryIdentifier->grouped_work_id = $groupedWork->id;
				$groupedWorkPrimaryIdentifier->find();
				while ($groupedWorkPrimaryIdentifier->fetch()){
					$sourceAndId = $groupedWorkPrimaryIdentifier->getSourceAndId();
					$this->markRecordForReExtraction($sourceAndId);
				}
			}
		}
		$this->markForForcedRegrouping();
	}

}