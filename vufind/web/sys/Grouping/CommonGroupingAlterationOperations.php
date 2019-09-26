<?php
/**
 * Methods shared between NonGroupedRecord & MergedGroupedWork
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/26/2019
 *
 */


abstract class CommonGroupingAlterationOperations extends DB_DataObject {


	function update($dataObject = false){
		$success = parent::update($dataObject);
		if ($success){
			$this->followUpActions();
		}
		return $success;
	}

	abstract protected function followUpActions();

	function insert(){
		$success = parent::insert();
		if ($success){
			$this->followUpActions();
		}
		return $success;
	}

	function delete($useWhere = false){
		$success = parent::delete($useWhere);
		if ($success){
			$this->followUpActions();
		}
		return $success;
	}

	/**
	 * Systems using the Sierra Extract process need to mark records for re-extraction that are merged or un-merged.
	 * (Traditional process of marking for regrouping won't have the desired effect.)
	 *
	 * @param SourceAndId $sourceAndId The record to mark for Re-extraction
	 */
	protected function markRecordForReExtraction($sourceAndId){
		$indexingProfile = $sourceAndId->getIndexingProfile();
		if (!empty($indexingProfile)){
			require_once ROOT_DIR . '/sys/Extracting/IlsExtractInfo.php';
			$extractInfo                    = new IlsExtractInfo();
			$extractInfo->indexingProfileId = $indexingProfile->id;
			$extractInfo->ilsId             = $sourceAndId->getRecordId();
			if ($extractInfo->find(true)){
				return $extractInfo->markForReExtraction();
			}
		}
		return false;
	}
}