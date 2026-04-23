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

namespace Archive2;
use DB_DataObject;

/**
 * Data object class for handling Archive Requests
 *
 * @category Pika
 * @author C.J. Ohara <pika@marmot.org>
 */
class ArchiveRequest extends DB_DataObject
{
    public $__table = 'archive_requests';
    public $id;
    public $name;
    public $address;
    public $address2;
    public $city;
    public $state;
    public $zip;
    public $phone;
    public $email;
    public $format;
    public $purpose;
    public $pid;
		public $nid;
		public $libraryTid;
    public $dateRequested;

    public static function getObjectStructure()
    {
        $structure = [
	        'nid' => ['property' =>'nid', 'type' => 'archive2node', 'label' => 'Node ID', 'description' => 'Object Node ID', 'maxLength' => 50, 'required' => true],
	        ['property' => 'name', 'type' => 'text', 'label' => 'Name', 'description' => 'Name', 'maxLength' => 100, 'required' => true],
	        ['property' => 'address', 'type' => 'text', 'label' => 'Address', 'description' => 'Address', 'maxLength' => 200, 'required' => false],
	        ['property' => 'address2', 'type' => 'text', 'label' => 'Address 2', 'description' => 'Address 2', 'maxLength' => 200, 'required' => false],
	        ['property' => 'city', 'type' => 'text', 'label' => 'City', 'description' => 'City', 'maxLength' => 200, 'required' => false],
	        ['property' => 'state', 'type' => 'text', 'label' => 'State', 'description' => 'State', 'maxLength' => 200, 'required' => false],
	        ['property' => 'zip', 'type' => 'text', 'label' => 'Zip/Postal Code', 'description' => 'Address', 'maxLength' => 12, 'required' => false],
	        ['property' => 'country', 'type' => 'text', 'label' => 'Country', 'description' => 'Country', 'maxLength' => 50, 'required' => false, 'default' => 'United States'],
	        ['property' => 'phone', 'type' => 'text', 'label' => 'Phone', 'description' => 'Phone', 'maxLength' => 20, 'required' => true],
	        ['property' => 'alternatePhone', 'type' => 'text', 'label' => 'Alternate Phone', 'description' => 'Alternate Phone', 'maxLength' => 20, 'required' => false],
	        ['property' => 'email', 'type' => 'email', 'label' => 'E-mail Address', 'description' => 'E-mail Address', 'maxLength' => 100, 'required' => true],
	        ['property' => 'format', 'type' => 'text', 'label' => 'Format Required (Black and White/Color, Print/Digital, etc)', 'description' => 'Additional information about how you want the materials delivered', 'maxLength' => 255, 'required' => false],
	        ['property' => 'purpose', 'type' => 'textarea', 'label' => 'Purpose: Provide information about how this image will be used: Description, title of publication, author, publisher, date of publication, number of copies produced, etc', 'description' => 'Additional information about what you will use the copy(copies) for', 'required' => true, 'hideInLists' => true],
	        'pid' => ['property' => 'pid', 'type' => 'hidden', 'label' => 'legacy PID of the object', 'maxLength' => 50, 'hideInLists' => true],
	        'libraryTid' => ['property' => 'libraryTid', 'type'=> 'hidden', 'label'=>'Taxonomy ID of owning library', 'required' => true, 'maxLength' =>50, 'required' => true, 'hideInLists' => true ],
	        'dateRequested' => ['property' => 'dateRequested', 'type' => 'dateReadOnly', 'label' => 'Request Date', 'description' => 'Date the request was made'],
        ];
        return $structure;
    }

    public function insert()
    {
        $this->dateRequested = time();
        return parent::insert();
    }

}
