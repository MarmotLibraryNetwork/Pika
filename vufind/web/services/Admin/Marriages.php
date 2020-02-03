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
require_once ROOT_DIR . '/sys/Genealogy/Marriage.php';

class Marriages extends ObjectEditor {
	function getObjectType(){
		return 'Marriage';
	}

	function getToolName(){
		return 'Marriages';
	}

	function getPageTitle(){
		return 'Marriages';
	}

	function getAllObjects(){
		$object = new Marriage();
		$object->orderBy('marriageDate');
		$object->find();
		$objectList = array();
		while ($object->fetch()){
			$objectList[$object->marriageId] = clone $object;
		}
		return $objectList;
	}

	function getObjectStructure(){
		return Marriage::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return array('personId', 'spouseName', 'date');
	}

	function getIdKeyColumn(){
		return 'marriageId';
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
