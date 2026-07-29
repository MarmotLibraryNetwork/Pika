<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

namespace BookClubKit;
use DB_DataObject;

/**
 * Data object class for handling Colorado State Book Club Kit requests
 *
 * @category Pika
 */
class BookClubKitRequest extends DB_DataObject
{
	public $__table = 'book_club_kit_requests';
	public $id;
	public $libraryId;
	public $userId;
	public $barcode;
	public $name;
	public $email;
	public $phone;
	public $recordId;
	public $title;
	public $status;
	public $dateCreated;

	public static $statusOptions = [
		'Open'      => 'Open',
		'Requested' => 'Requested',
		'Closed'    => 'Closed',
	];

	public static function getObjectStructure()
	{
		$structure = [
			'id'          => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id within the database'],
			'status'      => ['property' => 'status', 'type' => 'enum', 'values' => self::$statusOptions, 'label' => 'Status', 'description' => 'The status of the request', 'default' => 'Open', 'required' => true],
			'name'        => ['property' => 'name', 'type' => 'text', 'label' => 'Name', 'description' => 'Name of the patron making the request', 'maxLength' => 120, 'required' => true],
			'email'       => ['property' => 'email', 'type' => 'email', 'label' => 'E-mail Address', 'description' => 'E-mail Address of the patron making the request', 'maxLength' => 120, 'required' => true],
			'phone'       => ['property' => 'phone', 'type' => 'text', 'label' => 'Contact Number', 'description' => 'Contact phone number of the patron making the request', 'maxLength' => 30, 'required' => false],
			'barcode'     => ['property' => 'barcode', 'type' => 'text', 'label' => 'Library Card Number', 'description' => 'Library card number of the patron making the request', 'maxLength' => 20, 'required' => true],
			'title'       => ['property' => 'title', 'type' => 'text', 'label' => 'Title', 'description' => 'The Book Club Kit title being requested', 'maxLength' => 120, 'required' => true],
			'recordId'    => ['property' => 'recordId', 'type' => 'hidden', 'label' => 'Record Id', 'description' => 'The id of the record being requested', 'hideInLists' => true],
			'userId'      => ['property' => 'userId', 'type' => 'hidden', 'label' => 'Patron Id', 'description' => 'The id of the patron making the request', 'hideInLists' => true],
			'libraryId'   => ['property' => 'libraryId', 'type' => 'hidden', 'label' => 'Library Id', 'description' => 'The id of the patron\'s home library', 'hideInLists' => true],
			'dateCreated' => ['property' => 'dateCreated', 'type' => 'dateReadOnly', 'label' => 'Request Date', 'description' => 'Date the request was made'],
		];
		return $structure;
	}

	public function insert()
	{
		$this->dateCreated = time();
		return parent::insert();
	}

}
