<?php
/*
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
 * Availability data associated with an OverDrive Title
 * Shared Collections have a negative libraryId and Advantage collections will have the corresponding Pika libraryId
 */

namespace Pika\BibliographicDrivers\OverDrive;

class OverDriveAPIProductAvailability extends \DB_DataObject{
	public $__table = 'overdrive_api_product_availability';   // table name

	public $id;
	public $productId;
	public $libraryId;
	public $available;
	public $copiesOwned;
	public $copiesAvailable;
	public $numberOfHolds;

	function getLibraryName(){
		if ($this->libraryId > 0){
			//TODO: fetch shared collection name
			return 'Shared Digital Collection';
		}else{
			$library = new \Library();
			$library->libraryId = $this->libraryId;
			$library->find(true);
			return $library->displayName;
		}
	}
} 
