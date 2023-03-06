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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

/*
 *  Simple abstract class extension to hold the methods common to all of the Genealogy Objects
 *
 * */
abstract class GenealogyObjectEditor extends ObjectEditor {

	function getAllowableRoles(){
		return ['genealogyContributor'];
	}

	function getRedirectLocation($curObject, $objectAction = null){
		return '/Person/' . $curObject->personId;
	}

	function getAdditionalObjectActions($existingObject){
		return empty($existingObject->personId) ? [] : [
			[
				'text' => 'Return to Person',
				'url' => '/Person/' . $existingObject->personId
			]
		];
	}

	function showReturnToList(){
		return false;
	}

	function insertObject($structure){
		$objectType = $this->getObjectType();
		/** @var DB_DataObject $newObject */
		$newObject = new $objectType;
		//Check to see if we are getting default values from the
		$validationResults = $this->updateFromUI($newObject, $structure);
		if ($validationResults['validatedOk']){
			$ret = $newObject->insert();
			if (!$ret){

				if ($newObject->_lastError){
					$errorDescription = $newObject->_lastError->getUserInfo();
				}else{
					$errorDescription = 'Unknown error';
				}
				$this->logger->debug('Could not insert new object ' . $ret . ' ' . $errorDescription);
				@session_start();
				$_SESSION['lastError'] = "An error occurred inserting {$this->getObjectType()} <br>{$errorDescription}";

				return false;
			}
		}else{
			global $pikaLogger;
			$pikaLogger->debug('Could not validate new object ' . $objectType, $validationResults['errors']);

			// Redisplay the form with the User's input prepopulated
			global $interface;
			$interface->assign('lastError', 'The information entered was not valid. <br>' . implode('<br>', $validationResults['errors']));
			$interface->assign('object', $newObject);
			$interface->assign('additionalObjectActions', $this->getAdditionalObjectActions($newObject));
			$interface->setTemplate('../Admin/objectEditor.tpl');
			$this->display();
			die;
		}
		return $newObject;
	}

}