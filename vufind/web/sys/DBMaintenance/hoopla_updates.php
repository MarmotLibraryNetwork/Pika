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
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 7/29/14
 * Time: 2:25 PM
 */

function getHooplaUpdates(){
	return [
		'variables_lastHooplaExport' => [
			'title'       => 'Variables Last Hoopla Export Time',
			'description' => 'Add a variable for when hoopla data was extracted from the API last.',
			'sql'         => [
				"INSERT INTO variables (name, value) VALUES ('lastHooplaExport', 'false')",
			],
		],

		'variables_overdriveMaxProductsToUpdatea_2020.06' => [
			'title'       => 'Variables Overdrive Max Products To Update',
			'description' => 'Add a variable to adjust the amount of production process in a round of extraction.',
			'sql'         => [
				"INSERT INTO variables (name, value) VALUES ('overdriveMaxProductsToUpdate', '2500')",
			],
		],

		'hoopla_exportTables' => [
			'title'       => 'Hoopla export tables',
			'description' => 'Create tables to store data exported from hoopla.',
			'sql'         => [
				"CREATE TABLE hoopla_export ( 
									id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
									hooplaId INT NOT NULL,
									active TINYINT NOT NULL DEFAULT 1,
									title VARCHAR(255),
									kind VARCHAR(50),
									pa TINYINT NOT NULL DEFAULT 0,
									demo TINYINT NOT NULL DEFAULT 0,
									profanity TINYINT NOT NULL DEFAULT 0,
									rating VARCHAR(10),
									abridged TINYINT NOT NULL DEFAULT 0,
									children TINYINT NOT NULL DEFAULT 0,
									price DOUBLE NOT NULL DEFAULT 0,
									UNIQUE(hooplaId)
								) ENGINE = INNODB",
			],
		],

		'hoopla_exportLog' => [
			'title'       => 'Hoopla export log',
			'description' => 'Create log for hoopla export.',
			'sql'         => [
				"CREATE TABLE IF NOT EXISTS hoopla_export_log(
									`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of log', 
									`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the run started', 
									`endTime` INT(11) NULL COMMENT 'The timestamp when the run ended', 
									`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the run last updated (to check for stuck processes)', 
									`notes` TEXT COMMENT 'Additional information about the run', 
									PRIMARY KEY ( `id` )
									) ENGINE = INNODB;",
			],
		],

		'hoopla_export_date_cols' => [
			'title'       => 'Add date updated column to Hoopla Extract',
			'description' => 'Add date updated column to Hoopla Extract table.',
			'sql'         => [
				"ALTER TABLE `hoopla_export` 
									ADD COLUMN `dateLastUpdated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;",
			],
		],

		'2024.03.0_expand_ hoopla_rating_col' => [
			'title'       => '2024.03.0 Expand Hoopla rating Column',
			'description' => 'Expand Hoopla rating column to hold value "Unrestricted"',
			'sql'         => [
				"ALTER TABLE `hoopla_export` CHANGE COLUMN `rating` `rating` VARCHAR(13);",
			],
		],

		'2024.03.0_expand_ hoopla_additional_cols' => [
			'title'       => '2024.03.0 Add Hoopla  Columns',
			'description' => 'Add several columns to hoopla table.',
			'sql'         => [
				'ALTER TABLE `hoopla_export` '.
					'CHANGE COLUMN `hooplaId` `hooplaId` INT(11) UNSIGNED NOT NULL ,'.
					'CHANGE COLUMN `active` `active` TINYINT(4) UNSIGNED NOT NULL DEFAULT 1 ,'.
					'CHANGE COLUMN `kind` `kind` VARCHAR(15) CHARACTER SET \'utf8mb4\' COLLATE \'utf8mb4_unicode_ci\' NULL DEFAULT NULL ,'.
					'CHANGE COLUMN `active` `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 ,'.
					'CHANGE COLUMN `pa` `pa` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `demo` `demo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `profanity` `profanity` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `abridged` `abridged` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `children` `children` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 ,'.
					'CHANGE COLUMN `price` `price` DOUBLE UNSIGNED NOT NULL DEFAULT 0 ,'.
					'ADD COLUMN `language` VARCHAR(20) NULL DEFAULT NULL AFTER `title`,'.
					'ADD COLUMN `duration` VARCHAR(15) NULL DEFAULT NULL AFTER `kind`,'.
					'ADD COLUMN `series` VARCHAR(45) NULL DEFAULT NULL AFTER `duration`,'.
					'ADD COLUMN `season` VARCHAR(45) NULL DEFAULT NULL AFTER `series`,'.
					'ADD COLUMN `publisher` VARCHAR(75) NULL AFTER `season`,'.
					'ADD COLUMN `fiction` TINYINT(1) UNSIGNED NULL DEFAULT 0 AFTER `abridged`,'.
					'ADD COLUMN `purchaseModel` ENUM(\'INSTANT\') NULL DEFAULT "INSTANT" AFTER `price`;',
			],
		],

	];
}
