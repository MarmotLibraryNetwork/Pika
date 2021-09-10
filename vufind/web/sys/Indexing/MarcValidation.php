<?php
/*
 * Copyright (C) 2021  Marmot Library Network
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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 8/9/2021
 *
 */


class MarcValidation extends DB_DataObject {

	public $__table = 'indexing_profile_marc_validation';    // table name

	public $id;
	public $source;
	public $fileName;
	public $fileLastModifiedTime;
	public $validationTime;
	public $validated;
	public $totalRecords;
	public $recordSuppressed;
	public $errors;
	static function getObjectStructure(){
		$structure = [
			'source'               => ['property' => 'source', 'type' => 'readOnly', 'label' => 'source', 'description' => ''],
			'fileName'             => ['property' => 'fileName', 'type' => 'readOnly', 'label' => 'fileName', 'description' => ''],
			'fileLastModifiedTime' => ['property' => 'fileLastModifiedTime', 'type' => 'dateReadOnly', 'label' => 'fileLastModifiedTime', 'description' => ''],
			'validationTime'       => ['property' => 'validationTime', 'type' => 'dateReadOnly', 'label' => 'validationTime', 'description' => ''],
			'validated'            => ['property' => 'validated', 'type' => 'readOnly', 'label' => 'validated', 'description' => ''],
			'totalRecords'         => ['property' => 'totalRecords', 'type' => 'readOnly', 'label' => 'totalRecords', 'description' => ''],
			'recordSuppressed'     => ['property' => 'recordSuppressed', 'type' => 'readOnly', 'label' => 'recordSuppressed', 'description' => ''],
			'errors'               => ['property' => 'errors', 'type' => 'readOnly', 'label' => 'errors', 'description' => ''],
//'canEdit' => false
		];
		return $structure;
	}
}