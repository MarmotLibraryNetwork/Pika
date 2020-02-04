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
 * Rules about which records to include in a scope
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/18/2015
 * Time: 10:31 AM
 */
require_once ROOT_DIR . '/sys/Indexing/RecordToInclude.php';
class LocationRecordToInclude extends RecordToInclude{
	public $__table = 'location_records_to_include';    // table name
	public $locationId;

	static function getObjectStructure(){
		$location = new Location();
		$location->orderBy('displayName');
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('libraryAdmin')){
			$homeLibrary = UserAccount::getUserHomeLibrary();
			$location->libraryId = $homeLibrary->libraryId;
		}
		$location->find();
		while ($location->fetch()){
			$locationList[$location->locationId] = $location->displayName;
		}

		$structure = parent::getObjectStructure();
		$structure['locationId'] = array('property'=>'locationId', 'type'=>'enum', 'values'=>$locationList, 'label'=>'Location', 'description'=>'The id of a location');

		return $structure;
	}
}
