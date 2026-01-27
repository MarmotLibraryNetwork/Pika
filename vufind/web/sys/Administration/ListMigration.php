<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

namespace Administration;

use Pika\Logger;
use UserList;
use User;

class ListMigration extends \DB_DataObject
{
	public $__table = 'list_migration'; // table name
	public $id; // int(11)
	public $listId; // List ID int(11)
	public $userId; // User ID int(11)
	public $previousListId; // Previous List ID int(11)
	public $previousUserId;
	public $barcode;
	public $migrationDate;

	protected $logger;

	function keys(){
		return ['id'];
	}

	function getObjectStructure(){
		return null;
	}

	public function migrateList($barcode, $previousListId, $previousUserId, $title, $description=null, $public = false, $dateUpdated=null, $deleted = false, $created=null, $defaultSort=null){
		$this->logger  = new Logger(__CLASS__);
		$user          = new User();
		$user->barcode = $barcode;
		$user->find(true); // you can use find in your if statement, it will return false on error, 0 on no results; or number of rows for the result set
		if (!empty($user->id)){
			$migration                 = new ListMigration();
			$migration->previousListId = $previousListId;
			if(!$migration->find()){
				$userList = new UserList();
				$userList->user_id = $user->id;
				$userList->title = $title;
				$userList->description = $description;
				$userList->public      = $public;
				$userList->dateUpdated = $dateUpdated;
				$userList->deleted     = $deleted;
				$userList->created     = $created;
				$userList->defaultSort = $defaultSort;
				if($insertedList = $userList->insert()){
					$migration->listId = $insertedList;
					$migration->barcode = $barcode;
					$migration->migrationDate = time();
					$migration->userId = $user->id;
					$migration->previousUserId = $previousUserId;
					$migration->insert();
					return $insertedList;
				}else{
					echo "Error on list id " . $previousListId . ": List could not be added by Pika <br>";
					$this->logger->notice('ID ' . $previousListId . ' could not be added');
					return false;
				}
			}else{
				echo "Error on list id " . $previousListId . ": List was previously migrated <br>";
				$this->logger->notice('ID ' . $previousListId . ' was previously migrated');
				return false;
			}
		}else{
			echo "Error on barcode " . $barcode . ": User was not found in ILS<br>";
			$this->logger->notice('User with barcode ' . $barcode . ' was not found in ILS');
			return false;
		}
	}

	/**
	 *
	 * @param $migrationFile
	 * @return int|false
	 */
	public function migrateLists($migrationFile){
		$this->logger = new Logger(__CLASS__);
		$migrationCSV = fopen($migrationFile, 'r');
		$listLines     = explode(PHP_EOL, fread($migrationCSV, filesize($migrationFile)));
		$lists = array();
		foreach($listLines as $line){
			$lists[] = explode(',', $line);
		}
		
		$n            = 0;
		$migratedLists  = array();
		foreach ($lists as $list){
			if ($this->migrateList(trim($list[1]), trim($list[0]), trim($list[2]), trim($list[3]), trim($list[4]), trim($list[5]), trim($list[6]), trim($list[7]), trim($list[8]), trim($list[9]))){
				$n = $n+1;
				$migratedLists['success'][] = $list->id;
			}else{
				$migratedLists['error'][] = $list->id;
			}
		}
		fclose($migrationCSV);
		if ($n > 0){
			$migratedLists['migratedLists'] = $n;
			return $migratedLists;
		}else{
			$this->logger->warn('No lists were migrated');
			return false;
		}
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}
}