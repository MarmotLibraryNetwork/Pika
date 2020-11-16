<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 7/13/2020
 *
 */
require_once ROOT_DIR . '/sys/Grouping/GroupedWorkVersionMap.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_GroupedWorkVersionMaps extends ObjectEditor {

	function getPrimaryKeyColumn(){
		return 'groupedWorkPermanentIdVersion4';
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	function getObjectType(){
		return 'GroupedWorkVersionMap';
	}

	function getToolName(){
		return 'GroupedWorkVersionMaps';
	}

	function getPageTitle(){
		return 'Grouped Work Version Map';
	}

	function getObjectStructure(){
		return $this->getObjectType()::getObjectStructure();
	}

	function getIdKeyColumn(){
		return 'groupedWorkPermanentIdVersion4';
	}

	function getAllObjects($orderBy = null){
		/** @var GroupedWorkVersionMap $object */
		$objectList  = [];
		$objectClass = $this->getObjectType();
		$objectIdCol = $this->getIdKeyColumn();
		$object      = new $objectClass;
		$object->whereAdd("groupedWorkPermanentIdVersion5 IS NULL");
		if ($orderBy){
			$object->orderBy($orderBy);
		}
		if ($object->find()){
			while ($object->fetch()){
				$objectList[$object->$objectIdCol] = clone $object;
			}
		}
		return $objectList;
	}


}