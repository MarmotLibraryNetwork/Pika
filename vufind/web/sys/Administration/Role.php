<?php
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