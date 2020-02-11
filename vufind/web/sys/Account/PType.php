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
 * Table Definition for library
 */
require_once 'DB/DataObject.php';

class PType extends DB_DataObject {
	public $__table = 'ptype';   // table name
	public $id;
	public $label;        // varchar(60)
	public $pType;        //varchar(45)
	public $maxHolds;      //int(11)
	public $masquerade;   //varchar(45)

	static $masqueradeLevels = array(
		'none'     => 'No Masquerade',
		'location' => 'Masquerade as Patrons of home branch',
		'library'  => 'Masquerade as Patrons of home library',
		'any'      => 'Masquerade as any user'
	);

	function keys(){
		return array('id');
	}

	function getObjectStructure(){
		$structure = array(
			'id'         => array('property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the p-type within the database', 'hideInLists' => false),
			'label'      => array('property' => 'label', 'type' => 'text', 'label' => 'Label', 'description' => 'The label of the p-type.'),
			'pType'      => array('property' => 'pType', 'type' => 'text', 'label' => 'P-Type', 'description' => 'The P-Type for the patron'),
			'maxHolds'   => array('property' => 'maxHolds', 'type' => 'integer', 'label' => 'Max Holds', 'description' => 'The maximum holds that a patron can have.', 'default' => 300),
			'masquerade' => array('property' => 'masquerade', 'type' => 'enum', 'values' => self::$masqueradeLevels, 'label' => 'Masquerade Level', 'description' => 'The level at which this ptype can masquerade at', 'default' => 'none')
		);
		return $structure;
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->label)){
			return $this->label;
		}
	}
}

