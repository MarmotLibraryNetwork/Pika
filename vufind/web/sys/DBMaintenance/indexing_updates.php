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

				'2024.03.0_add_adult_literacy_format'     => [
					'release'           => '2024.03.0',
					'title'             => 'Add Adult Literacy Book Format',
					'description'       => 'Add Adult Literacy Book format to translation maps',
					'continueOnError'   => true,
					'sql'               =>[
						"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'AdultLiteracyBook', 'Adult Literacy Book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'AdultLiteracyBook', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'AdultLiteracyBook', '10')"
					],
				],

	];
}

// Functions definitions that get executed by any of the updates above