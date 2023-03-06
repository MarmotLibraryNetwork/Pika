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

/**
 * Indexing information for what records to are owned by a particular scope
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/18/2015
 * Time: 10:31 AM
 */

require_once 'DB/DataObject.php';

class RecordOwned extends DB_DataObject {
	public $id;
	public $indexingProfileId;
	public $location;

	static function getObjectStructure(){
		require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
		$indexingProfiles = IndexingProfile::getAllIndexingProfileNames();
		$structure        = [
			'id'                => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of this association'],
			'indexingProfileId' => ['property' => 'indexingProfileId', 'type' => 'enum', 'values' => $indexingProfiles, 'label' => 'Indexing Profile Id', 'description' => 'The Indexing Profile this map is associated with'],
			'location'          => ['property' => 'location', 'type' => 'text', 'label' => 'Location', 'description' => 'A regular expression for location codes to include', 'maxLength' => '100', 'required' => true],
		];
		return $structure;
	}
}
