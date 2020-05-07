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
 * Allows configuration of More Details for full record display
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/12/14
 * Time: 8:34 AM
 */

class LocationMoreDetails extends DB_DataObject{
	public $__table = 'location_more_details';
	public $id;
	public $locationId;
	public $source;
	public $collapseByDefault;
	public $weight;

	static function getObjectStructure(){
		//Load Libraries for lookup values
		require_once ROOT_DIR . '/RecordDrivers/Interface.php';
		$validSources = RecordInterface::getValidMoreDetailsSources();
		$structure = array(
				'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of the hours within the database'),
				'source' => array('property'=>'source', 'type'=>'enum', 'label'=>'Source', 'values' => $validSources, 'description'=>'The source of the data to display'),
				'collapseByDefault' => array('property'=>'collapseByDefault', 'type'=>'checkbox', 'label'=>'Collapse By Default', 'description'=>'Whether or not the section should be collapsed by default', 'default' => true),
//				'weight' => array('property' => 'weight', 'type' => 'integer', 'label' => 'Weight', 'weight' => 'Defines how lists are sorted within the widget.  Lower weights are displayed to the left of the screen.', 'required'=> true),
				// Weight isn't needed in the object structure for display of oneToMany sections
		);
		return $structure;
	}

	function getEditLink(){
		return '';
	}
}
