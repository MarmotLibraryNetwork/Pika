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
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 1/28/2016
 * Time: 7:17 PM
 */
class TimeToReshelve extends DB_DataObject {
	public $__table = 'time_to_reshelve';    // table name

	public $id;
	public $weight;
	public $indexingProfileId;
	public $locations;
	public $statusCodeToOverride;
	public $numHoursToOverride;
	public $status;
	public $groupedStatus;

	static function getObjectStructure(){
		require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
		$indexingProfiles = IndexingProfile::getAllIndexingProfileNames();
		return [
			'id'                   => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id within the database'],
			'indexingProfileId'    => ['property' => 'indexingProfileId', 'type' => 'enum', 'values' => $indexingProfiles, 'label' => 'Indexing Profile Id', 'description' => 'The Indexing Profile this override is applied to', 'required' => true],
			'locations'            => ['property' => 'locations', 'type' => 'text', 'label' => 'Locations', 'description' => 'The locations to apply this rule to', 'maxLength' => '100', 'required' => true],
			'statusCodeToOverride' => ['property' => 'statusCodeToOverride', 'type' => 'text', 'label' => 'Status Code To Override', 'description' => 'The particular status to override at check-in', 'maxLength' => '1', 'required' => true, 'default' => '-'],
			'numHoursToOverride'   => ['property' => 'numHoursToOverride', 'type' => 'integer', 'label' => 'Num. Hours to Override', 'description' => 'The number of hours that this override should be applied', 'required' => true],
			'status'               => ['property' => 'status', 'type' => 'text', 'label' => 'Status', 'description' => 'The Status to display to the user in full record/copies', 'hideInLists' => false, 'default' => false],
			'groupedStatus'        => ['property' => 'groupedStatus', 'type' => 'enum', 'label' => 'Grouped Status', 'description' => 'The Status to display to the when grouping multiple copies', 'hideInLists' => false, 'default' => false,
			                           'values'   => [
				                           'Currently Unavailable' => 'Currently Unavailable',
				                           'On Order'              => 'On Order',
				                           'Coming Soon'           => 'Coming Soon',
				                           'In Processing'         => 'In Processing',
				                           'Checked Out'           => 'Checked Out',
				                           'Library Use Only'      => 'Library Use Only',
				                           'Available Online'      => 'Available Online',
				                           'In Transit'            => 'In Transit',
				                           'On Shelf'              => 'On Shelf'
			                           ]],
		];
	}
}
