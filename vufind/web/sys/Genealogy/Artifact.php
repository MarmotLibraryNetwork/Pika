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
 * Table Definition for an artifact (physical document, picture, man-made object, etc)
 */
require_once 'DB/DataObject.php';

class Artifact extends DB_DataObject {
	public $__table = 'artifact';    // table name
	public $artifactId;
	public $catalogId;
	public $objectName;
	public $catalogType;
	public $title;
	public $description;
	public $dateOfCreation;
	public $dateOfAcquisition;
	public $physicalDescription;
	public $place;
	public $copyright;
	public $collection;
	public $lexiconCategory;
	public $lexiconSubCategory;
	public $subjects;

	function keys(){
		return array('artifactId');
	}
}
