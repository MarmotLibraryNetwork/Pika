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

use Account\UserMigration;

/**
 * Updates related to user tables
 *
 */

function getUserUpdates(): array{

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
		'2025.01.0_add_reading_history_action_log' => [
			'release'         => '2025.01.0',
			'title'           => 'Add reading history action log',
			'description'     => 'Add a table to log when reading history is cleared or deleted',
			'continueOnError' => false,
			'sql'             => [
				'CREATE TABLE `user_reading_history_action` (`id` INT(11) NOT NULL AUTO_INCREMENT, `userId` INT(11) NOT NULL, `action` VARCHAR(45) NOT NULL, `date` INT(11) NOT NULL, PRIMARY KEY(`id`), KEY `index2` (`date`))',
				'setReadingHistoryActionStart'
			]
		],
		'2026.01.0_add_user_migration_table' =>[
			'release'         => '2026.01.0',
			'title'           => 'Add User Migration Table',
			'description'   =>'Add a table to link previous user system accounts to mln accounts',
			'continueOnError' => false,
			'sql'             => [
				'CREATE TABLE `user_migration` (`id` INT(11) NOT NULL AUTO_INCREMENT, `mlnId` INT(11) NOT NULL, `userId` INT(11) NOT NULL,  `barcode` VARCHAR(45), `migrationDate` INT(11) NOT NULL, PRIMARY KEY(`id`), KEY (`userId`))'
			]
		]
	];
}

// Functions definitions that get executed by any of the updates above

function setReadingHistoryActionStart(){
	$variable = new Variable('reading_history_action_log_start');
	return $variable->setWithTimeStampValue();
}
function userMigrationActionStart(){
require_once \Account\UserMigration::class;
 $migration = new UserMigration();
 $migration->migrateUsers('/data/pika/migration/userAccounts.csv');
}