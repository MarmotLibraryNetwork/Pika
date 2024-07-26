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

require_once ROOT_DIR . '/services/Admin/DBMaintenance.php';

/**
 * Provides a method of running SQL updates to the eContent database.
 * Shows a list of updates that are available with a description of the updates
 */
class DBMaintenanceOverDrive extends DBMaintenance {
	const TITLE = 'Database Maintenance - OverDrive';

	public function __construct(){
		parent::__construct();
		$temp     = new Pika\BibliographicDrivers\OverDrive\OverDriveAPIProduct();
		$this->db = $temp->getDatabaseConnection();
		if (PEAR::isError($this->db)){
			die($this->db->getMessage());
		}
	}

	protected function getSQLUpdates(){
		// Array Entry Template
//		'[release-number]_[update-order-#-if-needed]_[unique-update-key-name]' => [
//			'release'         => '[release-number/git-branch]',
//			'title'           => 'Title of Update',
//			'description'     => 'Description of what the updates are.',
//			'continueOnError' => false,
//			'sql'             => [
//				'[SQL]',
//				'[nameOfFunctionToRun]'
//			]
//		],

		return [

		];
	}

}
