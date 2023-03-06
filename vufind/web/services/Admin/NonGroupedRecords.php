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

require_once ROOT_DIR . '/sys/Grouping/NonGroupedRecord.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_NonGroupedRecords extends ObjectEditor {
	function getObjectType(){
		return 'NonGroupedRecord';
	}

	function getToolName(){
		return 'NonGroupedRecords';
	}

	function getPageTitle(){
		return 'Records to Not Group';
	}

	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'updated desc, source, recordId');
	}

	function getObjectStructure(){
		return NonGroupedRecord::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'cataloging');
	}

	function getInstructions(){
		global $interface;
		return $interface->fetch('Admin/ungrouping_work_instructions.tpl');
	}

	function getListInstructions(){
		return 'For more information on how to ungroup works, see the <a href="https://marmot-support.atlassian.net/l/c/bcJcpX05">online documentation</a>.';
	}

//	function getAdditionalObjectActions($existingObject){
//		$objectActions = [];
//		$idCol         = $this->getIdKeyColumn();
//		if (empty($existingObject->$idCol)){
//			$objectActions[] = [
//				'text' => 'Ungroup and Merge',
//				'url'  => "/{$this->getModule()}/{$this->getToolName()}?objectAction=ungroupAndMerge",
//			];
//		}
//		return $objectActions;
//	}
//
//	function ungroupAndMerge(){
//		$structure = $this->getObjectStructure();
//		$newObject = $this->insertObject($structure);
//		$idCol     = $this->getIdKeyColumn();
//		if (!empty($newObject->$idCol)){
//			// Now that the new entry has been saved, which will have forced it to be regrouped,
//			// re-fetch the entry so that we can get the new grouped work id
//			$objectType = $this->getObjectType();
//			/** @var NonGroupedRecord $ungroupedObject */
//			$ungroupedObject         = new $objectType();
//			$ungroupedObject->$idCol = $newObject->$idCol;
//			if ($ungroupedObject->find(true)){
//				$groupedWork = $ungroupedObject->getGroupedWork();
//				if (!empty($groupedWork->permanent_id)){
//					header("Location:/Admin/MergedGroupedWorks?objectAction=addNew&sourceGroupedWorkId={$groupedWork->permanent_id}");
////					header("Location:/Admin/MergedGroupedWorks?objectAction=addNew&sourceGroupedWorkId={$recordDriver->getPermanentId()}&notes={$recordDriver->getTitle()|removeTrailingPunctuation|escape}%0A{$userDisplayName}, {$homeLibrary}, {$smarty.now|date_format}%0A");
//				}
//			}
//		}
//	}
}
