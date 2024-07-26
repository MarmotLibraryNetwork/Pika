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
 * Updates related to hoopla for cleanliness
 *
 */

function getHooplaUpdates(){

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
		'2024.03.0_expand_hoopla_rating_col' => [
			'release'     => '2024.03.0',
			'title'       => 'Expand Hoopla rating Column',
			'description' => 'Expand Hoopla rating column to hold value "Unrestricted"',
			'continueOnError' => true,
			'sql'         => [
				"ALTER TABLE `hoopla_export` CHANGE COLUMN `rating` `rating` VARCHAR(13);",
			],
		],

		'2024.03.0_expand_hoopla_additional_cols' => [
			'release'     => '2024.03.0',
			'title'       => 'Add Hoopla Columns',
			'description' => 'Add several columns to hoopla table.',
			'continueOnError' => false,
			'sql'         => [
				'ALTER TABLE `hoopla_export` '.
					'CHANGE COLUMN `hooplaId` `hooplaId` INT(11) UNSIGNED NOT NULL ,'.
					//'CHANGE COLUMN `active` `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 ,'. // this causes a strange error: "[nativecode=1054 ** Unknown column 'active' in 'hoopla_export']"
					'CHANGE COLUMN `kind` `kind` VARCHAR(15) CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_unicode_ci\' NULL DEFAULT NULL ,'.
					'CHANGE COLUMN `active` `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 ,'.
					'CHANGE COLUMN `pa` `pa` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `demo` `demo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `profanity` `profanity` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `abridged` `abridged` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `children` `children` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `price` `price` DOUBLE UNSIGNED NOT NULL DEFAULT 0 ,'.
					'ADD COLUMN `language` VARCHAR(25) NULL DEFAULT NULL AFTER `title`,'. // to hold "NO LINGUISTIC CONTENT"
					'ADD COLUMN `duration` VARCHAR(15) NULL DEFAULT NULL AFTER `kind`,'.
					'ADD COLUMN `series` VARCHAR(100) NULL DEFAULT NULL AFTER `duration`,'.
					'ADD COLUMN `season` VARCHAR(45) NULL DEFAULT NULL AFTER `series`,'.
					'ADD COLUMN `publisher` VARCHAR(100) NULL AFTER `season`,'.
					'ADD COLUMN `fiction` TINYINT(1) UNSIGNED NULL DEFAULT 0 AFTER `abridged`,'.
					'ADD COLUMN `purchaseModel` ENUM(\'INSTANT\', \'FLEX\') NULL DEFAULT \'INSTANT\' AFTER `price`;', // ENUM requires single quotes ('), double quotes (") don't work
			],
		],
	];
}

// Functions definitions that get executed by any of the updates above