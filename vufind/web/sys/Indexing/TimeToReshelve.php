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
 * Description goes here
 *
 * @category VuFind-Plus-2014
 * @author Mark Noble <mark@marmot.org>
 * Date: 1/28/2016
 * Time: 7:17 PM
 */
class TimeToReshelve  extends DB_DataObject{
	public $__table = 'time_to_reshelve';    // table name

	public $id;
	public $weight;
	public $indexingProfileId;
	public $locations;
	public $numHoursToOverride;
	public $status;
	public $groupedStatus;

	function getObjectStructure(){
		$indexingProfiles = array();
		require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
		$indexingProfile = new IndexingProfile();
		$indexingProfile->orderBy('name');
		$indexingProfile->find();
		while ($indexingProfile->fetch()){
			$indexingProfiles[$indexingProfile->id] = $indexingProfile->name;
		}
		$structure = array(
			'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id within the database'),
			'indexingProfileId' => array('property' => 'indexingProfileId', 'type' => 'enum', 'values' => $indexingProfiles, 'label' => 'Indexing Profile Id', 'description' => 'The Indexing Profile this map is associated with'),
			'locations' => array('property'=>'locations', 'type'=>'text', 'label'=>'Locations', 'description'=>'The locations to apply this rule to', 'maxLength' => '100', 'required' => true),
			'numHoursToOverride' => array('property'=>'numHoursToOverride', 'type'=>'integer', 'label'=>'Num. Hours to Override', 'description'=>'The number of hours that this override should be applied', 'required' => true),
			'status' => array('property'=>'status', 'type'=>'text', 'label'=>'Status', 'description'=>'The Status to display to the user in full record/copies', 'hideInLists' => false, 'default'=>false),
			'groupedStatus' => array('property'=>'groupedStatus', 'type'=>'enum', 'values' => array(
					'Currently Unavailable' => 'Currently Unavailable',
					'On Order' => 'On Order',
					'Coming Soon' => 'Coming Soon',
					'In Processing' => 'In Processing',
					'Checked Out' => 'Checked Out',
					'Library Use Only' => 'Library Use Only',
					'Available Online' => 'Available Online',
					'In Transit' => 'In Transit',
					'On Shelf' => 'On Shelf'
			), 'label'=>'Grouped Status', 'description'=>'The Status to display to the when grouping multiple copies', 'hideInLists' => false, 'default'=>false),
		);
		return $structure;
	}
}
