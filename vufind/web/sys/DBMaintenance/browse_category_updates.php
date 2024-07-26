<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
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

function getBrowseCategoryUpdates(){

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
		'2024.03.0_RemoveBrowseCategoriesColumns' => [
			'release'         => '2024.03.0',
			'title'           => 'Remove Browse Category columns',
			'description'     => 'Remove unused columns for browse categories',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE `browse_category` DROP COLUMN `catalogScoping`," .
				"CHANGE COLUMN `description` `description` TEXT CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL ; ",
			],
		],


	];
}

// Functions definitions that get executed by any of the updates above