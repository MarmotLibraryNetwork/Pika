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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

class People extends ObjectEditor {
	function getObjectType(){
		return 'Person';
	}

	function getToolName(){
		return 'People';
	}

	function getPageTitle(){
		return 'People';
	}

	function getAllObjects(){
		$object = new Person();
		$object->orderBy('lastName, firstName');
		$object->find();
		$objectList = array();
		while ($object->fetch()){
			$objectList[$object->personId] = clone $object;
		}
		return $objectList;
	}

	function getObjectStructure(){
		$person = new Person();
		return $person->getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return array('lastName', 'firstName', 'middleName', 'birthDate');
	}

	function getIdKeyColumn(){
		return 'personId';
	}

	function getAllowableRoles(){
		return array('genealogyContributor');
	}

	function getRedirectLocation($objectAction, $curObject){
		if ($objectAction == 'delete'){
			return '/Union/Search?searchSource=genealogy&lookfor=&genealogyType=GenealogyName';
		}else{
			return '/Person/' . $curObject->personId;
		}
	}

	function showReturnToList(){
		return false;
	}
}
