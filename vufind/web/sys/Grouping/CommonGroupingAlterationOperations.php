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
//		if ($success){ //May not have resulted in any changes, so $sucess may be 0
			$this->followUpActions();
//		}
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
//		if ($success){
			$this->followUpActions();
//		}
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
			} elseif ($indexingProfile->name == 'ils'){
				//TODO: the above should check against the accountProfiles
				$extractInfo->insert();
			}
		}
		return false;
	}
}
