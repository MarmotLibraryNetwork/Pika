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
 * @author Mark Noble <pika@marmot.org>
 * Date: 6/30/2015
 * Time: 1:23 PM
 */

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Indexing/TranslationMap.php';

class Admin_TranslationMaps extends ObjectEditor {
	function launch(){
		global $interface;
		$objectAction = isset($_REQUEST['objectAction']) ? $_REQUEST['objectAction'] : null;
		if ($objectAction == 'loadFromFile'){
			$id             = $_REQUEST['id'];
			$translationMap = new TranslationMap();
			if ($translationMap->get($id)){
				$interface->assign('mapName', $translationMap->name);
				$interface->assign('additionalObjectActions', $this->getAdditionalObjectActions($translationMap));
			}
			$interface->assign('id', $id);
			$shortPageTitle = "Import Translation Map Data";
//			$interface->assign('shortPageTitle', $shortPageTitle);
			$this->display('../Admin/importTranslationMapData.tpl', $shortPageTitle);
			exit();
		}elseif ($objectAction == 'doAppend' || $objectAction == 'doReload'){
			$id = $_REQUEST['id'];

			$translationMapData = $_REQUEST['translationMapData'];
			//Truncate the current data
			$translationMap     = new TranslationMap();
			$translationMap->id = $id;
			if ($translationMap->find(true)){
				$newValues = array();
				if ($objectAction == 'doReload'){
					/** @var TranslationMapValue $value */
					foreach ($translationMap->translationMapValues as $value){
						$value->delete();
					}
					$translationMap->translationMapValues = array();
					$translationMap->update();
				}else{
					foreach ($translationMap->translationMapValues as $value){
						$newValues[$value->value] = $value;
					}
				}

				//Parse the new data
				$data = preg_split('/\\r\\n|\\r|\\n/', $translationMapData);

				foreach ($data as $dataRow){
					if (strlen(trim($dataRow)) != 0 && $dataRow[0] != '#'){
						$dataFields = preg_split('/[,=]/', $dataRow, 2);
						$value      = trim(str_replace('"', '', $dataFields[0]));
						if (array_key_exists($value, $newValues)){
							$translationMapValue = $newValues[$value];
						}else{
							$translationMapValue = new TranslationMapValue();
						}
						$translationMapValue->value            = $value;
						$translationMapValue->translation      = trim(str_replace('"', '', $dataFields[1]));
						$translationMapValue->translationMapId = $id;

						$newValues[$translationMapValue->value] = $translationMapValue;
					}
				}
				$translationMap->translationMapValues = $newValues;
				$translationMap->update();
			}else{
				$interface->assign('error', "Sorry we could not find a translation map with that id");
			}


			//Show the results
			$_REQUEST['objectAction'] = 'edit';
		}elseif ($objectAction == 'viewAsINI'){
			$id                 = $_REQUEST['id'];
			$translationMap     = new TranslationMap();
			$translationMap->id = $id;
			if ($translationMap->find(true)){
				$interface->assign('id', $id);
				$interface->assign('additionalObjectActions', $this->getAdditionalObjectActions($translationMap));
				$interface->assign('translationMapValues', $translationMap->translationMapValues);
				$interface->assign('objectName', $translationMap->label());
				$this->display('../Admin/viewTranslationMapAsIni.tpl', 'View Translation Map Data');
				exit();
			}else{
				$interface->assign('error', "Sorry we could not find a translation map with that id");
			}

		}
		parent::launch();
	}

	function getObjectType(){
		return 'TranslationMap';
	}

	function getToolName(){
		return 'TranslationMaps';
	}

	function getPageTitle(){
		return 'Translation Maps';
	}

	function getAllObjects(){
		$list = array();

		$object = new TranslationMap();
		$object->orderBy('name');
		$object->find();
		while ($object->fetch()){
			$list[$object->id] = clone $object;
		}

		return $list;
	}

	function getObjectStructure(){
		return TranslationMap::getObjectStructure();
	}

	function getAllowableRoles(){
		return array('opacAdmin');
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

	function getAdditionalObjectActions($existingObject){
		$actions = array();
		if ($existingObject && $existingObject->id != ''){
			$actions[] = array(
				'text' => 'Load From CSV/INI',
				'url' => '/Admin/TranslationMaps?objectAction=loadFromFile&id=' . $existingObject->id,
			);
			$actions[] = array(
				'text' => 'View as INI',
				'url' => '/Admin/TranslationMaps?objectAction=viewAsINI&id=' . $existingObject->id,
			);
		}

		return $actions;
	}

}
