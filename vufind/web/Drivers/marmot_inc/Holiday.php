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
 * Table Definition for Location Hours.
 */
require_once 'DB/DataObject.php';

class Holiday extends DB_DataObject
{
	public $__table = 'holiday';   // table name
	public $id;                    // int(11)  not_null primary_key auto_increment
	public $libraryId;             // int(11)
	public $date;                  // date
	public $name;                  // varchar(100)
	
	function keys() {
		return array('id');
	}

	static function getObjectStructure(){
		$library = new Library();
		$library->orderBy('displayName');
		$library->find();
		$libraryList = array();
		while ($library->fetch()){
			$libraryList[$library->libraryId] = $library->displayName;
		}
		
		$structure = array(
			'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of the holiday within the database'),
			'libraryId' => array('property'=>'libraryId', 'type'=>'enum', 'values'=>$libraryList, 'label'=>'Library', 'description'=>'A link to the library'),
			'date' => array('property'=>'date', 'type'=>'date', 'label'=>'Date', 'description'=>'The date of a holiday.', 'required'=>true),
			'name' => array('property'=>'name', 'type'=>'text', 'label'=>'Holiday Name', 'description'=>'The name of a holiday')
		);
		return $structure;
	}
}
