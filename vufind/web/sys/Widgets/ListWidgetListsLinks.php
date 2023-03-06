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

require_once 'DB/DataObject.php';

class ListWidgetListsLinks extends DB_DataObject {
	public $__table = 'list_widget_lists_links';    // table name
	public $id;                //int(11)
	public $listWidgetListsId; //int(11)
	public $name;              //varchar(255)
	public $link;              //text


	function keys(){
		return ['id'];
	}

	static function getObjectStructure(){
		return [
			'id'           => [
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the list widget file.',
				'primaryKey'  => true,
				'storeDb'     => true
			],
			'weight'       => [
				'property'    => 'weight',
				'type'        => 'text',
				'label'       => 'Weight',
				'description' => '',
				'required'    => true,
				'storeDb'     => true
			],
			'listWidgetId' => [
				'property'    => 'listWidgetListsId',
				'type'        => 'text',
				'label'       => 'List Widget List Id',
				'description' => 'The widget this list is associated with.',
				'required'    => true,
				'storeDb'     => true
			],
			'name'         => [
				'property'    => 'name',
				'type'        => 'text',
				'label'       => 'Name',
				'description' => 'The name of the list to display in the tab.',
				'required'    => true,
				'storeDb'     => true
			],
			'link'         => [
				'property'    => 'link',
				'type'        => 'text',
				'label'       => 'Link',
				'description' => 'The link of the list to display in the tab.',
				'required'    => true,
				'storeDb'     => true
			]

		];
	}

}
