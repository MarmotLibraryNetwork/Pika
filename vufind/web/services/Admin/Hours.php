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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LocationHours.php';

class Hours extends ObjectEditor
{
	
	function getObjectType(){
		return 'LocationHours';
	}
	function getToolName(){
		return 'Hours';
	}
	function getPageTitle(){
		return 'Hours';
	}
	function getAllObjects(){
		$hours = new LocationHours();
		$hours->orderBy('locationId, day');
		$hours->find();
		$list = array();
		while ($hours->fetch()){
			$list[$hours->id] = clone $hours;
		}
		return $list;
	}
	function getObjectStructure(){
		return LocationHours::getObjectStructure();
	}
	function getPrimaryKeyColumn(){
		return 'id';
	}
	function getIdKeyColumn(){
		return 'id';
	}
	function getAllowableRoles(){
		return array('opacAdmin');
	}

}
