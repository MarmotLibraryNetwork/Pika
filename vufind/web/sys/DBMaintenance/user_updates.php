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
				'CREATE TABLE `user_reading_history_action`(`id` INT(11) NOT NULL AUTO_INCREMENT, `userId` INT(11) NOT NULL, `action` VARCHAR(45) NOT NULL, `date` INT(11) NOT NULL, PRIMARY KEY(`id`))'
			]
		]
	];
}

// Functions definitions that get executed by any of the updates above