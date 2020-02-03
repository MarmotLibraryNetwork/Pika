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
 * Table Definition for Roles of Administrators
 */
require_once 'DB/DataObject.php';

class Role extends DB_DataObject {
	public $__table = 'roles';  // table name
	public $roleId;            //int(11)
	public $name;              //varchar(50)
	public $description;       //varchar(100)

	function keys(){
		return array('roleId');
	}

	function getObjectStructure(){
		$structure = array(
			'roleId'      => array('property' => 'roleId', 'type' => 'label', 'label' => 'Role Id', 'description' => 'The unique id of the role within the database'),
			'name'        => array('property' => 'name', 'type' => 'text', 'label' => 'Name', 'maxLength' => 50, 'description' => 'The full name of the role.'),
			'description' => array('property' => 'name', 'type' => 'text', 'label' => 'Name', 'maxLength' => 100, 'description' => 'The full name of the role.'),
		);
		return $structure;
	}

	/**
	 * @param bool $includeRoleDescription
	 * @return array  An array of Roll names, optionally appended with the role's description. The index is the role's Id.
	 */
	static function fetchAllRoles($includeRoleDescription = true){
		$role = new Role();
		$role->orderBy('name');
		$role->find();
		$roleList = array();
		while ($role->fetch()){
			$roleList[$role->roleId] = $role->name . ($includeRoleDescription ? ' - ' . $role->description : '');
		}
		return $roleList;
	}
}
