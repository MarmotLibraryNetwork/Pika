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
 * Admin interface for creating indexing profiles
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 6/30/2015
 * Time: 1:23 PM
 */

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';

class Admin_IndexingProfiles extends ObjectEditor {
	function launch(){
		global $interface;
		$objectAction =  $_REQUEST['objectAction'] ?? null;
		if ($objectAction == 'viewMarcFiles'){
			$id = $_REQUEST['id'];
			$interface->assign('id', $id);
			$files        = [];
			$indexProfile = new IndexingProfile();
			if ($indexProfile->get($id) && !empty($indexProfile->marcPath)){

				$marcPath = $indexProfile->marcPath;
				if ($handle = @opendir($marcPath)){
					while (false !== ($entry = readdir($handle))){
						if ($entry != "." && $entry != ".."){
							$files[$entry] = filemtime($marcPath . DIR_SEP . $entry);
						}
					}
					closedir($handle);
					$interface->assign('files', $files);
					$interface->assign('IndexProfileName', $indexProfile->name);
					$this->display('marcFiles.tpl', 'Marc Files');
				} else {
					echo "Failed to open file path: {$indexProfile->marcPath}";
					die;
				}
			} else {
				echo "Invalid indexing profile or marc path is not set.";
				die;
			}
		} else {
			parent::launch();
		}
	}


	function getObjectType(){
		return 'IndexingProfile';
	}

	function getToolName(){
		return 'IndexingProfiles';
	}

	function getPageTitle(){
		return 'Indexing Profiles';
	}

	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'name');
	}

	function getObjectStructure(){
		return IndexingProfile::getObjectStructure();
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function canAddNew(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	function canDelete(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	function getInstructions(){
		return 'For more information about indexing profiles, see the <a href="https://docs.google.com/document/d/1OA_HKMmgf4nm2l3ckojHiTHnlbo4dKMNnec3wCtGsLk">online documentation</a>.';
	}

	function getAdditionalObjectActions($existingObject){
		$actions = [];
		if ($existingObject && $existingObject->id != ''){
			$actions[] = [
				'text' => 'View MARC files',
				'url'  => '/Admin/IndexingProfiles?objectAction=viewMarcFiles&id=' . $existingObject->id,
			];
			$actions[] = [
				'text' => 'View MARC Validations',
				'url'  => '/Admin/MarcValidations?source=' . $existingObject->sourceName,
			];
		}else{
			$actions[] = [
				'text'    => 'Populate as a Sideload',
				'onclick' => "$('#indexingClass').val('SideLoadedEContent');$('#groupingClass').val('SideLoadedRecordGrouper');$('#recordDriver').val('SideLoadedRecord');$('#catalogDriver').val('na');$('#recordUrlComponent').val('');$(this).parent().after($('<div>Remember to update the relevant library and location Records to Include/Own settings.</div>').addClass('alert alert-warning')); return false",
			];
		}

		return $actions;
	}

}
