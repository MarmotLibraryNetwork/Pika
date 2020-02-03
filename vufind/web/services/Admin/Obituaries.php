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

class Obituaries extends ObjectEditor {
	function getObjectType(){
		return 'Obituary';
	}

	function getToolName(){
		return 'Obituaries';
	}

	function getPageTitle(){
		return 'Obituaries';
	}

	function getAllObjects(){
		$object = new Obituary();
		$object->orderBy('date');
		$object->find();
		$objectList = array();
		while ($object->fetch()){
			$objectList[$object->obituaryId] = clone $object;
		}
		return $objectList;
	}

	function getObjectStructure(){
		return Obituary::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return array('personId', 'source', 'date');
	}

	function getIdKeyColumn(){
		return 'obituaryId';
	}

	function getAllowableRoles(){
		return array('genealogyContributor');
	}

	function getRedirectLocation($objectAction, $curObject){
		return '/Person/' . $curObject->personId;
	}

	function showReturnToList(){
		return false;
	}
}
