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

use DB_DataObject;
use Pika\Logger;
use UserAccount;

class UserMigration extends DB_DataObject {
	public $__table = 'user_migration';    // table name
	public $id;                            //int(11)
	public $mlnId;   // ILS ID               //int(11)
	public $userId;  // Pika UserId
	public $barcode; //varchar(45)
	public $migrationDate; //int(11)

	protected $logger;

	function keys(){
		return ['id'];
	}

	function getObjectStructure(){
		return null;
	}

	public function migrateUser($barcode){
		$this->logger = new Logger(__CLASS__);
		$account      = new UserAccount();
		$sierraUser   = $account->findNewUser($barcode);
		if ($sierraUser){
			$migration          = new UserMigration();
			$migration->barcode = $barcode;


			if ($migration->find() == 0 || $migration->find() === false){
				$migration->mlnId         = $sierraUser->ilsUserId;
				$migration->userId        = $sierraUser->id;
				$migration->migrationDate = time();
				$migration->insert();
				return $sierraUser->ilsUserId;
			}else{
				echo "Error on barcode " . $barcode . ": User was previously migrated <br>";
				$this->logger->notice('barcode ' . $barcode . ' was previously migrated');
				return false;
			}
		}else{
			echo "Error on barcode " . $barcode . ": User was not found in ILS<br>";
			return false;
		}
	}

	/**
	 *
	 * @param $migrationFile
	 * @return int|false
	 */
	public function migrateUsers($migrationFile){
		$this->logger = new Logger(__CLASS__);
		$migrationCSV = fopen($migrationFile, 'r');
		$barcodes     = explode(PHP_EOL, fread($migrationCSV, filesize($migrationFile)));
		$n            = 0;
		$migrationAccounts  = array();
		foreach ($barcodes as $barcode){
			if ($this->migrateUser(trim($barcode))){
				$n = $n+1;
				$migrationAccounts['success'][] = $barcode;
			}else{
				$migrationAccounts['error'][] = $barcode;
			}
		}
		fclose($migrationCSV);
		if ($n > 0){
			$migrationAccounts['migratedUsers'] = $n;
			return $migrationAccounts;
		}else{
			$this->logger->warn('No users were migrated');
			return false;
		}
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}
}