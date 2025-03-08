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
 * Updates related to indexing
 *
 */

function getIndexingUpdates(): array{

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

		'2024.03.0_create-polaris-export-log' => [
			'release'         => '2024.03.0',
			'title'           => 'Create Polaris Export Log',
			'description'     => 'Export log to track record created, updated, deleted',
			'continueOnError' => false,
			'sql'             => [
				'CREATE TABLE `polaris_export_log` (
						  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
						  `startTime` INT(11) UNSIGNED NOT NULL,
						  `endTime` INT(11) UNSIGNED NULL,
						  `lastUpdate` INT(11) UNSIGNED NULL,
						  `numRecordsToProcess` SMALLINT UNSIGNED NULL,
						  `numRecordsProcessed` SMALLINT UNSIGNED NULL,
						  `numRecordsAdded` SMALLINT UNSIGNED NULL,
						  `numRecordsUpdated` SMALLINT UNSIGNED NULL,
						  `numRecordsDeleted` SMALLINT UNSIGNED NULL,
						  `numErrors` SMALLINT UNSIGNED NULL,
						  `numRemainingRecords` SMALLINT UNSIGNED NULL,
						  `notes` TEXT NULL,
						  PRIMARY KEY (`id`),
						  INDEX `index2` (`startTime` DESC))
						ENGINE = InnoDB;',
			]
		],

		'2024.03.0_polaris-extract-table_updates' => [
			'release'         => '2024.03.0',
			'title'           => 'Create item to record table',
			'description'     => 'Create item id to record id table; add suppressed date to ils_extract_info',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `ils_extract_info` ADD COLUMN `suppressed` DATE NULL DEFAULT NULL AFTER `lastExtracted`;',
				'CREATE TABLE `ils_itemid_to_ilsid` (
						  `itemId` INT UNSIGNED NOT NULL,
						  `ilsId` INT UNSIGNED NOT NULL,
						  PRIMARY KEY (`itemId`),
						  UNIQUE INDEX `itemId_UNIQUE` (`itemId` ASC));',
				'ALTER TABLE `polaris_export_log` 
							CHANGE COLUMN `numRemainingRecords` `numItemsUpdated` SMALLINT UNSIGNED NULL DEFAULT NULL ,
							ADD COLUMN `numItemsDeleted` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `numItemsUpdated`;',
			]
		],

		'2024.03.0_ils_hold_sumary_update_time' => [
			'release'         => '2024.03.0',
			'title'           => 'Add update time to hold summary table.',
			'description'     => 'Add update time column to ils_hold_sumary table.',
			'continueOnError' => false,
			'sql'             => [
				'ALTER TABLE `ils_hold_summary` 
									CHANGE COLUMN `numHolds` `numHolds` INT(11) UNSIGNED DEFAULT 0 ,
									ADD COLUMN `updateTime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() AFTER `numHolds`;',
			]
		],

		'2024.03.0_add_adult_literacy_format' => [
			'release'         => '2024.03.0',
			'title'           => 'Add Adult Literacy Book Format',
			'description'     => 'Add Adult Literacy Book format to translation maps',
			'continueOnError' => true,
			'sql'             => [
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'AdultLiteracyBook', 'Adult Literacy Book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'AdultLiteracyBook', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'AdultLiteracyBook', '10')"
			],
		],

		'2024.04.0_increase_pickup_loc_column' => [
			'release'         => '2024.04.0',
			'title'           => 'Increase Offline Hold pickup location column',
			'description'     => 'Increase Offline Hold pickup location column to accommodate Polaris location names',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `offline_hold` CHANGE COLUMN `pickupLocation` `pickupLocation` VARCHAR(20) CHARACTER SET "utf8mb4" COLLATE "utf8mb4_unicode_ci" NULL DEFAULT NULL ;',
			]
		],

		'2025.02.0_add_yoto_formats' => [
			'release'         => '2025.02.0',
			'title'           => 'Add Yoto Formats',
			'description'     => 'Add Yoto Story & Yoto Music Formats to translation maps',
			'continueOnError' => true,
			'sql'             => [
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'YotoStory', 'Yoto Story Card')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'YotoStory', 'Audio Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'YotoStory', '4')",
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'YotoMusic', 'Yoto Music Card')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'YotoMusic', 'Music')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'YotoMusic', '4')",
			],
		],

	];
}

// Functions definitions that get executed by any of the updates above