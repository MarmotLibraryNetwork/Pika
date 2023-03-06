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
 * Stores MetaData for a product that has been loaded from OverDrive
 *
 * @category Pika
 * @author C.J. O'Hara <pika@marmot.org>
 * Date: 04/13/22
 * Time: 8:59 AM
 */

namespace Pika\BibliographicDrivers\OverDrive;

class OverDriveAPIProductCreators extends \DB_DataObject {
	public $__table = 'overdrive_api_product_creators';   // table name

	public $id;
	public $productId;
	public $role;
	public $name;
	public $fileAs;

	private $decodedRawData = null;
	public function getDecodedRawData(){
		if ($this->decodedRawData == null){
			$this->decodedRawData = json_decode($this->rawData);
		}
		return $this->decodedRawData;
	}
}
