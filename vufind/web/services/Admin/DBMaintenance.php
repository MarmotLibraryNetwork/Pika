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

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/DBMaintenance/DatabaseUpdates.php';

use \Pika\Logger;
/**
 * Provides a method of running SQL updates to the database.
 * Shows a list of updates that are available with a description of the updates
 */
class DBMaintenance extends Admin_Admin {

	/** @var DB $db */
	protected $db;

	const TITLE = 'Database Maintenance - Pika';

	public function __construct(){
		parent::__construct();
		$temp     = new DatabaseUpdates();
		$this->db = $temp->getDatabaseConnection();
		if (PEAR::isError($this->db)){
			die($this->db->getMessage());
		}
	}

	function launch(){
		global $interface;

		//Create updates table if one doesn't exist already
//		$this->createUpdatesTable();  //Generally the table exists

		$availableUpdates = $this->getSQLUpdates();

		if (isset($_REQUEST['submit'])){
			$interface->assign('showStatus', true);

			//Process the updates
			foreach ($availableUpdates as $key => $update){
				if (isset($_REQUEST['selected'][$key])){
					$sqlStatements = $update['sql'];
					$updateOk      = true;
					$successAll    = true;
					foreach ($sqlStatements as $sql){
						if (method_exists($this, $sql)){
							$updateOk           = $this->$sql($update);
							$update['status'][] = $updateOk ? 'Update succeeded' : 'Update failed';
							if (empty($update['continueOnError']) && !$updateOk){
								break;
							}
						}elseif (function_exists($sql)){
							$updateOk           = $sql($update);
							$update['status'][] = $updateOk ? 'Update succeeded' : 'Update failed';
							if (empty($update['continueOnError']) && !$updateOk){
								break;
							}
						}else{
							if (!$this->runSQLStatement($update, $sql)){
								$successAll = false;
								break;
							}
						}
						if ($successAll){
							$successAll = $updateOk; // Keep updating successAll til it is false
						}
					}
					if ($successAll){
						$this->markUpdateAsRun($key);
					}
					$update['success']      = $successAll;
					$availableUpdates[$key] = $update;
					if (!$successAll && empty($update['continueOnError'])){
						break; // Stop additional updates on Error
					}
				}
			}
		}

		//Check to see which updates have already been performed.
		$this->checkWhichUpdatesHaveRun($availableUpdates);
		$interface->assign('sqlUpdates', $availableUpdates);

		$this->display('dbMaintenance.tpl', self::TITLE);
	}


	protected function checkWhichUpdatesHaveRun(&$availableUpdates){
		foreach ($availableUpdates as $key => &$update){
			$update['alreadyRun'] = false;
			$dbUpdate             = new DatabaseUpdates();
			$dbUpdate->update_key = $key;
			if ($dbUpdate->find()){
				$update['alreadyRun'] = true;
			}
		}
	}

	protected function markUpdateAsRun($update_key){
		$dbUpdate = new DatabaseUpdates();
		if ($dbUpdate->get($update_key)){
			$dbUpdate->date_run = time();
			$dbUpdate->update();
		}else{
			$dbUpdate->update_key = $update_key;
//			$dbUpdate->date_run = time(); // table should auto-set this column
			$dbUpdate->insert();
		}
	}

	function getAllowableRoles(){
		return ['userAdmin', 'opacAdmin'];
	}

	protected function runSQLStatement(&$update, $sql){
		set_time_limit(500);
		$result   = $this->db->query($sql);
		$updateOk = true;

		// Since $sql comes from array, we can have multiple update statuses to report.
		if (empty($result)){ // got an error
			$updateOk           = false;
			if (empty($update['continueOnError'])){
				$update['status'][] = 'Update failed: ' . $this->db->error(); //TODO: does this method exist?
			}else{
				$update['status'][] = 'Warning: ' . $this->db->error();
			}
		}elseif (is_a($result, 'DB_Error')){
			$updateOk           = false;
			global $pikaLogger;
			$pikaLogger->error('Error with database update', ['message' => $result->getMessage(), 'info' => $result->userinfo]);
			if (empty($update['continueOnError'])){
				$update['status'][] = 'Update failed: ' . $result->getMessage();
			}else{
				$update['status'][] = 'Warning: ' . $result->getMessage();
			}
		}else{
			$update['status'][] = 'Update succeeded';
		}
		return $updateOk;
	}

	protected function createUpdatesTable(){
		$tableFound = false;
		//Check to see if the updates table exists
		/** @var DB_result $result */
		$result =& $this->db->query('SHOW TABLES');
		if ($result){
			while ($row =& $result->fetchRow()){
				if ($row[0] == 'db_update'){
					$tableFound = true;
					break;
				}
			}
		}
		if (!$tableFound){
			//Create the table to mark which updates have been run.
			$this->db->query("CREATE TABLE db_update (" .
				"update_key VARCHAR( 100 ) NOT NULL PRIMARY KEY ," .
				"date_run TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP" .
				") ENGINE = InnoDB");
		}
	}

	protected function getSQLUpdates(){
		global $configArray;

		require_once ROOT_DIR . '/sys/DBMaintenance/library_location_updates.php';
		require_once ROOT_DIR . '/sys/DBMaintenance/user_updates.php';
		require_once ROOT_DIR . '/sys/DBMaintenance/admin_updates.php';
		require_once ROOT_DIR . '/sys/DBMaintenance/indexing_updates.php';
		require_once ROOT_DIR . '/sys/DBMaintenance/grouped_work_updates.php';
		require_once ROOT_DIR . '/sys/DBMaintenance/hoopla_updates.php';
		require_once ROOT_DIR . '/sys/DBMaintenance/browse_category_updates.php';
		require_once ROOT_DIR . '/sys/DBMaintenance/list_widget_updates.php';
		//require_once ROOT_DIR . '/sys/DBMaintenance/islandora_updates.php';

		$updates = array_merge(
			getLibraryLocationUpdates(),
			getUserUpdates(),
			getAdminUpdates(),
			getIndexingUpdates(),
			getGroupedWorkUpdates(),
			getHooplaUpdates(),
			getBrowseCategoryUpdates(),
			getListWidgetUpdates(),
			//getIslandoraUpdates(),

			// Uncategorized updates
			[

			] // End of main array
		);

		// Sort updates by the Release Number
		$release_column = array_column($updates, 'release');
		array_multisort($release_column, SORT_ASC, $updates);
		return $updates;
	}


	// end class
}
