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
 * Updates related to indexing for cleanliness
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/29/14
 * Time: 2:25 PM
 */

function getIndexingUpdates(){
	return array(
		'ils_hold_summary' => array(
			'title'       => 'ILS Hold Summary',
			'description' => 'Create ils hold summary table to store summary information about the available holds',
			'sql'         => array(
				"CREATE TABLE ils_hold_summary (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							ilsId VARCHAR (20) NOT NULL,
							numHolds INT(11) DEFAULT 0,
							UNIQUE(ilsId)
						) ENGINE = INNODB"
			),
		),

		'indexing_profile' => array(
			'title'       => 'Indexing profile setup',
			'description' => 'Setup indexing information table to store information about how to index ',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS `indexing_profiles` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `name` varchar(50) NOT NULL,
							  `marcPath` varchar(100) NOT NULL,
							  `marcEncoding` enum('MARC8','UTF','UNIMARC','ISO8859_1','BESTGUESS') NOT NULL DEFAULT 'MARC8',
							  `individualMarcPath` varchar(100) NOT NULL,
							  `groupingClass` varchar(100) NOT NULL DEFAULT 'MarcRecordGrouper',
							  `indexingClass` varchar(50) NOT NULL,
							  `recordDriver` varchar(100) NOT NULL DEFAULT 'MarcRecord',
							  `recordUrlComponent` varchar(25) NOT NULL DEFAULT 'Record',
							  `formatSource` enum('bib','item') NOT NULL DEFAULT 'bib',
							  `recordNumberTag` char(3) NOT NULL,
							  `recordNumberPrefix` varchar(10) NOT NULL,
							  `suppressItemlessBibs` tinyint(1) NOT NULL DEFAULT '1',
							  `itemTag` char(3) NOT NULL,
							  `itemRecordNumber` char(1) DEFAULT NULL,
							  `useItemBasedCallNumbers` tinyint(1) NOT NULL DEFAULT '1',
							  `callNumberPrestamp` char(1) DEFAULT NULL,
							  `callNumber` char(1) DEFAULT NULL,
							  `callNumberCutter` char(1) DEFAULT NULL,
							  `callNumberPoststamp` varchar(1) DEFAULT NULL,
							  `location` char(1) DEFAULT NULL,
							  `locationsToSuppress` varchar(100) DEFAULT NULL,
							  `shelvingLocation` char(1) DEFAULT NULL,
							  `volume` varchar(1) DEFAULT NULL,
							  `itemUrl` char(1) DEFAULT NULL,
							  `barcode` char(1) DEFAULT NULL,
							  `status` char(1) DEFAULT NULL,
							  `statusesToSuppress` varchar(100) DEFAULT NULL,
							  `totalCheckouts` char(1) DEFAULT NULL,
							  `lastYearCheckouts` char(1) DEFAULT NULL,
							  `yearToDateCheckouts` char(1) DEFAULT NULL,
							  `totalRenewals` char(1) DEFAULT NULL,
							  `iType` char(1) DEFAULT NULL,
							  `dueDate` char(1) DEFAULT NULL,
							  `dateCreated` char(1) DEFAULT NULL,
							  `dateCreatedFormat` varchar(20) DEFAULT NULL,
							  `iCode2` char(1) DEFAULT NULL,
							  `useICode2Suppression` tinyint(1) NOT NULL DEFAULT '1',
							  `format` char(1) DEFAULT NULL,
							  `eContentDescriptor` char(1) DEFAULT NULL,
							  `orderTag` char(3) DEFAULT NULL,
							  `orderStatus` char(1) DEFAULT NULL,
							  `orderLocation` char(1) DEFAULT NULL,
							  `orderCopies` char(1) DEFAULT NULL,
							  `orderCode3` char(1) DEFAULT NULL,
							  PRIMARY KEY (`id`),
							  UNIQUE KEY `name` (`name`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
				"CREATE TABLE IF NOT EXISTS `translation_maps` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `indexingProfileId` int(11) NOT NULL,
							  `name` varchar(50) NOT NULL,
							  PRIMARY KEY (`id`),
							  UNIQUE KEY `profileName` (`indexingProfileId`,`name`)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8",
				"CREATE TABLE IF NOT EXISTS `translation_map_values` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `translationMapId` int(11) NOT NULL,
							  `value` varchar(50) NOT NULL,
							  `translation` varchar(255) NOT NULL,
							  PRIMARY KEY (`id`),
							  UNIQUE KEY (`translationMapId`,`value`)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8",
				"CREATE TABLE IF NOT EXISTS `library_records_owned` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `libraryId` int(11) NOT NULL,
							  `indexingProfileId` int(11) NOT NULL,
							  `location` varchar(100) NOT NULL,
							  PRIMARY KEY (`id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
				"CREATE TABLE IF NOT EXISTS `library_records_to_include` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `libraryId` int(11) NOT NULL,
							  `indexingProfileId` int(11) NOT NULL,
							  `location` varchar(100) NOT NULL,
							  `includeHoldableOnly` tinyint(4) NOT NULL DEFAULT '1',
							  `includeItemsOnOrder` tinyint(1) NOT NULL DEFAULT '0',
							  `includeEContent` tinyint(1) NOT NULL DEFAULT '0',
							  `weight` int(11) NOT NULL,
							  PRIMARY KEY (`id`),
							  KEY `libraryId` (`libraryId`,`indexingProfileId`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
				"CREATE TABLE IF NOT EXISTS `location_records_owned` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `locationId` int(11) NOT NULL,
							  `indexingProfileId` int(11) NOT NULL,
							  `location` varchar(100) NOT NULL,
							  PRIMARY KEY (`id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
				"CREATE TABLE IF NOT EXISTS `location_records_to_include` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `locationId` int(11) NOT NULL,
							  `indexingProfileId` int(11) NOT NULL,
							  `location` varchar(100) NOT NULL,
							  `includeHoldableOnly` tinyint(4) NOT NULL DEFAULT '1',
							  `includeItemsOnOrder` tinyint(1) NOT NULL DEFAULT '0',
							  `includeEContent` tinyint(1) NOT NULL DEFAULT '0',
							  `weight` int(11) NOT NULL,
							  PRIMARY KEY (`id`),
							  KEY `locationId` (`locationId`,`indexingProfileId`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
				"ALTER TABLE indexing_profiles ADD COLUMN `collection` char(1) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `catalogDriver` varchar(50) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `nonHoldableITypes` varchar(255) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `nonHoldableStatuses` varchar(255) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `nonHoldableLocations` varchar(512) DEFAULT NULL",
				"ALTER TABLE indexing_profiles CHANGE marcEncoding `marcEncoding` enum('MARC8','UTF8','UNIMARC','ISO8859_1','BESTGUESS') NOT NULL DEFAULT 'MARC8'",
				"ALTER TABLE indexing_profiles ADD COLUMN `lastCheckinFormat` varchar(20) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `lastCheckinDate` char(1) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `orderLocationSingle` char(1) DEFAULT NULL",
				"ALTER TABLE indexing_profiles CHANGE formatSource `formatSource` enum('bib','item', 'specified') NOT NULL DEFAULT 'bib'",
				"ALTER TABLE indexing_profiles ADD COLUMN `specifiedFormat` varchar(50) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `specifiedFormatCategory` varchar(50) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `specifiedFormatBoost` int DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `filenamesToInclude` varchar(250) DEFAULT '.*\\\\.ma?rc'",
				"ALTER TABLE indexing_profiles ADD COLUMN `collectionsToSuppress` varchar(100) DEFAULT ''",
				"ALTER TABLE indexing_profiles ADD COLUMN `iTypesToSuppress`  varchar(100) DEFAULT null ",
				"ALTER TABLE indexing_profiles ADD COLUMN `iCode2sToSuppress` varchar(100) DEFAULT null ",
				"ALTER TABLE indexing_profiles ADD COLUMN `bCode3sToSuppress` varchar(100) DEFAULT null ",
				"ALTER TABLE indexing_profiles ADD COLUMN `sierraRecordFixedFieldsTag` char(3) DEFAULT null ",
				"ALTER TABLE indexing_profiles ADD COLUMN `bCode3` char(1) DEFAULT NULL ",
				"ALTER TABLE indexing_profiles ADD COLUMN `numCharsToCreateFolderFrom` int(11) DEFAULT 4",
				"ALTER TABLE indexing_profiles ADD COLUMN `createFolderFromLeadingCharacters` tinyint(1) DEFAULT 1",
				"UPDATE indexing_profiles SET `numCharsToCreateFolderFrom` = 7 WHERE name = 'hoopla'",
				"ALTER TABLE indexing_profiles ADD COLUMN `dueDateFormat` varchar(20) DEFAULT 'yyMMdd'",
				"updateDueDateFormat",
				"ALTER TABLE indexing_profiles CHANGE `locationsToSuppress` `locationsToSuppress` varchar(255)",
				"ALTER TABLE indexing_profiles ADD COLUMN `doAutomaticEcontentSuppression` tinyint(1) DEFAULT 1",
				"ALTER TABLE indexing_profiles ADD COLUMN `groupUnchangedFiles` tinyint(1) DEFAULT 0",
				"ALTER TABLE indexing_profiles ADD COLUMN `recordNumberField` char(1) DEFAULT 'a' ",
				"ALTER TABLE `indexing_profiles` ADD COLUMN `formatDeterminationMethod` varchar(20) DEFAULT 'bib' ",
				"ALTER TABLE `indexing_profiles` ADD COLUMN `materialTypesToIgnore` varchar(50) ",
				"ALTER TABLE indexing_profiles ADD COLUMN materialTypeField VARCHAR(3)",
				"UPDATE indexing_profiles  SET `recordDriver`='HooplaRecordDriver' WHERE name = 'hoopla'",
			),
		),

		'translation_map_regex' => array(
			'title'       => 'Translation Maps Regex',
			'description' => 'Setup Translation Maps to use regular expressions',
			'sql'         => array(
				"ALTER TABLE translation_maps ADD COLUMN `usesRegularExpressions` tinyint(1) DEFAULT 0",
			)
		),

		'setup_default_indexing_profiles' => array(
			'title'       => 'Setup Default Indexing Profiles',
			'description' => 'Setup indexing profiles based off historic information',
			'sql'         => array(
				'setupIndexingProfiles'
			)
		),

		'volume_information' => array(
			'title'       => 'Volume Information',
			'description' => 'Store information about volumes for use within display.  These do not need to be indexed independently.',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS `ils_volume_info` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `recordId` varchar(50) NOT NULL COMMENT 'Full Record ID including the source',
							  `displayLabel` varchar(255) NOT NULL,
							  `relatedItems` varchar(512) NOT NULL,
							  `volumeId` VARCHAR( 30 ) NOT NULL ,
							  PRIMARY KEY (`id`),
							  KEY `recordId` (`recordId`),
							  UNIQUE `volumeId` (`volumeId`)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8",
			)
		),

		'last_check_in_status_adjustments' => [
			'title'       => 'Last Check In Time Status Adjustments',
			'description' => 'Add additional fields to adjust status based on last check-in time.',
			'sql'         => [
				"CREATE TABLE IF NOT EXISTS `time_to_reshelve` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `indexingProfileId` int(11) NOT NULL,
							  `locations` varchar(100) NOT NULL,
							  `numHoursToOverride` int(11) NOT NULL,
							  `status` varchar(50) NOT NULL,
							  `groupedStatus` varchar(50) NOT NULL,
							  `weight` int(11) NOT NULL,
							  PRIMARY KEY (`id`),
							  KEY (indexingProfileId)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8",
			]
		],

		'2022.04.0_timeToReshelveStatusToOveride' => [
			'title'           => 'Time To Reshelve status code to override',
			'description'     => 'Add column for the status code to override on checkin',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE `time_to_reshelve` ADD COLUMN `statusCodeToOverride` CHAR(1) NOT NULL DEFAULT '-' AFTER `numHoursToOverride`; ",
			],
		],


		'2022.04.0_minMarcFileSize' => [
			'title'           => 'Optional minMarcFileSize indexing profile setting',
			'description'     => 'When set, the full export file must be larger than this level to be grouped. ',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE `indexing_profiles` ADD COLUMN `minMarcFileSize` INT UNSIGNED NULL AFTER `marcEncoding`; ",
			],
		],

		'records_to_include_2017-06' => array(
			'title'       => 'Records To Include Updates 2017.06',
			'description' => 'Additional control over what is included, URL rewriting.',
			'sql'         => array(
				"ALTER TABLE library_records_to_include ADD COLUMN iType VARCHAR(100)",
				"ALTER TABLE library_records_to_include ADD COLUMN audience VARCHAR(100)",
				"ALTER TABLE library_records_to_include ADD COLUMN format VARCHAR(100)",
				"ALTER TABLE library_records_to_include ADD COLUMN marcTagToMatch VARCHAR(100)",
				"ALTER TABLE library_records_to_include ADD COLUMN marcValueToMatch VARCHAR(100)",
				"ALTER TABLE library_records_to_include ADD COLUMN includeExcludeMatches TINYINT default 1",
				"ALTER TABLE library_records_to_include ADD COLUMN urlToMatch VARCHAR(100)",
				"ALTER TABLE library_records_to_include ADD COLUMN urlReplacement VARCHAR(100)",

				"ALTER TABLE location_records_to_include ADD COLUMN iType VARCHAR(100)",
				"ALTER TABLE location_records_to_include ADD COLUMN audience VARCHAR(100)",
				"ALTER TABLE location_records_to_include ADD COLUMN format VARCHAR(100)",
				"ALTER TABLE location_records_to_include ADD COLUMN marcTagToMatch VARCHAR(100)",
				"ALTER TABLE location_records_to_include ADD COLUMN marcValueToMatch VARCHAR(100)",
				"ALTER TABLE location_records_to_include ADD COLUMN includeExcludeMatches TINYINT default 1",
				"ALTER TABLE location_records_to_include ADD COLUMN urlToMatch VARCHAR(100)",
				"ALTER TABLE location_records_to_include ADD COLUMN urlReplacement VARCHAR(100)",

				"ALTER TABLE `library_records_to_include` CHANGE COLUMN `urlReplacement` `urlReplacement` VARCHAR(255) NULL DEFAULT NULL",
				"ALTER TABLE `location_records_to_include` CHANGE COLUMN `urlReplacement` `urlReplacement` VARCHAR(255) NULL DEFAULT NULL",
			)
		),

		//2019.07.0
		'create_extract_info_table' => array(
			'title'       => 'Create ILS Extract Info table',
			'description' => 'Create a table to track when an ils record was last extracted and if it was deleted.',
			'sql'         => array(
				'CREATE TABLE `ils_extract_info` (
					  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					  `indexingProfileId` INT NOT NULL,
					  `ilsId` VARCHAR(50) NOT NULL,
					  `lastExtracted` INT NULL,
					  `deleted` DATE NULL,
					  PRIMARY KEY (`id`),
					  UNIQUE INDEX `ilsId_UNIQUE` (`indexingProfileId` ASC, `ilsId` ASC)
					)
					ENGINE = InnoDB
					DEFAULT CHARACTER SET = utf8;',
			),
		),

		'indexing_profile_2020.01' => array(
			'title'       => 'Indexing Profiles updates',
			'description' => 'Better integrate for eContent, Sierra fixed field language extracting',
			'sql'         => array(
				'ALTER TABLE `indexing_profiles` CHANGE COLUMN `catalogDriver` `patronDriver` VARCHAR(50) NULL DEFAULT NULL AFTER `recordDriver`, '
					. 'CHANGE COLUMN `marcEncoding` `marcEncoding` ENUM(\'MARC8\', \'UTF8\', \'UNIMARC\', \'ISO8859_1\', \'BESTGUESS\') NOT NULL DEFAULT \'UTF8\' , '
					. 'ADD COLUMN `sourceName` VARCHAR(45) NULL AFTER `name`, '
					. 'ADD UNIQUE INDEX `sourceName_UNIQUE` (`sourceName` ASC); ',
				'UPDATE `indexing_profiles` SET `sourceName` = `name`',
				'ALTER TABLE `indexing_profiles` ADD COLUMN `sierraLanguageFixedField` VARCHAR(3) NULL',
			)
		),

		'indexing_profile_specified_grouping_category_2020.04' => array(
			'title'       => 'Step 0 : Add Specified Grouping Category',
			'description' => 'Add a Specified Grouping Category for sideloads to indexing profiles',
			'sql'         => array(
				'ALTER TABLE indexing_profiles ADD COLUMN `specifiedGroupingCategory` varchar(5) DEFAULT NULL AFTER `specifiedFormatBoost`',
			)
		),

		'indexing_profile_update_hoopla_name_2020.06' => [
			'title'       => 'Update Hoopla indexing profile name',
			'description' => 'Update Hoopla indexing profile name to Hoopla',
			'sql'         => [
				'UPDATE `indexing_profiles` SET `name`="Hoopla" WHERE `sourceName`="hoopla";',
			]
		],

		'indexing_profile_item_status_settings_2020.06' => [
			'title'       => 'Add standard item status settings to indexing profile',
			'description' => 'Add standard item status settings availablie, checked out, and library use only to indexing profile',
			'sql'         => [
				"ALTER TABLE indexing_profiles ROW_FORMAT=DYNAMIC;",
				"ALTER TABLE indexing_profiles ADD COLUMN `availableStatuses` varchar(255) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `checkedOutStatuses` varchar(255) DEFAULT NULL",
				"ALTER TABLE indexing_profiles ADD COLUMN `libraryUseOnlyStatuses` varchar(255) DEFAULT NULL",
			]
		],

		'indexing_profile_cover_source_settings_2020.07' => [
			'title'       => 'Add cover source settings to indexing profile',
			'description' => 'Set how the indexing profile should handle book cover images',
			'sql'         => [
				"ALTER TABLE indexing_profiles ADD COLUMN `coverSource` varchar(55) DEFAULT NULL",
				'setCoverSource',
			]
		],

		'add_new_format_to_maps_2020.07' => [
			'title'       => 'Add new formats to translation maps',
			'description' => 'Add playstation 5 and xbox series x to translation maps',
			'sql'         => [
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
'xboxseriesx', 'book')
,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
'playstation5', 'book')
,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
'xboxseriesx', 'Xbox Series X')
,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
'playstation5', 'PlayStation 5')
,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
'xboxseriesx', '')
,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
'playstation5', '')
,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
'xboxseriesx', '4')
,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
'playstation5', '4')"
				,
			]
		],
		'2022.01.0_add_young_readers_format_grouping' => [
			'title'       => 'Add new Young Reader format and Grouping Category',
			'description' => 'Add Young Reader format and Grouping Category to translation maps',
			'sql'         => [
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
						((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
						'Young Reader', 'young')",
			]
		],
		'add_opac_message_2021.02' => [
			'title'       => 'Add opac message subfield setting to indexing profile',
			'description' => 'Include opac message subfield in order for Sierra Extract to get the field for items.',
			'sql'         => [
				"ALTER TABLE `indexing_profiles` ADD COLUMN `opacMessage` CHAR(1) NULL DEFAULT NULL AFTER `iCode2`;",
			]
		],

		'create_table_indexing_profile_marc_validation_2021.03' => [
			'title'       => 'Create the table indexing_profile_marc_validation',
			'description' => 'For tracking Validated MARC export files',
			'sql'         => [
				'CREATE TABLE `pika`.`indexing_profile_marc_validation` (
				  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				  `source` VARCHAR(45) NOT NULL,
				  `fileName` VARCHAR(45) NOT NULL,
				  `fileLastModifiedTime` INT UNSIGNED NOT NULL,
				  `validationTime` INT UNSIGNED NOT NULL,
				  `validated` TINYINT(1) UNSIGNED NULL,
				  `totalRecords` INT UNSIGNED NULL,
				  `recordSuppressed` INT UNSIGNED NULL,
				  `errors` INT UNSIGNED NULL,
				  PRIMARY KEY (`id`),
				  UNIQUE INDEX `uniqueIndex` (`source` ASC, `fileName` ASC)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
			]
		],
		'2021.04.0_remove_sublocation_recordsOwned_recordToInclude' => [
			'title'           => 'Remove unused Koha setting sublocation from recordsOwned and recordToInclude',
			'description'     => '',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `indexing_profiles` DROP COLUMN `subLocation`; ',
				'ALTER TABLE `library_records_owned` DROP COLUMN `subLocation`; ',
				'ALTER TABLE `library_records_to_include` DROP COLUMN `subLocation`;',
				'ALTER TABLE `location_records_owned` DROP COLUMN `subLocation`; ',
				'ALTER TABLE `location_records_to_include` DROP COLUMN `subLocation`; '
			],
		],

		'2023.01.1_expand_loan_rule_determiners' => [
			'title'           => 'Expand Loan Rule Determiner columns for Patron and Item Types',
			'description'     => 'Expand Loan Rule Determiner columns for Patron and Item Types',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `loan_rule_determiners` CHANGE COLUMN `patronType` `patronType` VARCHAR(400) NOT NULL COMMENT \'The patron types that this rule applies to\';',
				'ALTER TABLE `loan_rule_determiners` CHANGE COLUMN `itemType` `itemType` VARCHAR(400) DEFAULT \'0\' NOT NULL COMMENT \'The item types that this rule applies to\';',
			],
		],

		'2022.02.0_add_changeRequiresReindexing_to_profiles' => [
			'title'           => 'Add changeRequiresReindexing to indexing profile',
			'description'     => 'Timestamp for when a setting has changed which requires indexing',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `indexing_profiles` ADD `changeRequiresReindexing` INT UNSIGNED NULL; ',
			],
		],

		'2021.04.0_add_grouping_time_for_sideloads' => [
			'title'           => 'Add a last grouped time to each indexing profile',
			'description'     => 'Enable grouping time tracking for sideloads.',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `indexing_profiles` ADD `lastGroupedTime` INT UNSIGNED NULL; ',
			],
		],
		'2022.01.0_add_mixedMaterials_format'    => [
			'title'           => 'Add Mixed Materials format',
			'description'     => 'Add Mixed Materials to translation maps',
			'continueOnError' => true,
			'sql'             =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'MixedMaterials', 'book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'MixedMaterials', 'Mixed Materials')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'MixedMaterials', '')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'MixedMaterials', '1')"
			],
		],
		'2022.01.0_add_audioWithCDROM_format'    => [
			'title'           => 'Add Audio CD with CD Rom format',
			'description'     => 'Add Audio CD with CD Rom to translation maps',
			'continueOnError' => true,
			'sql'             =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'SoundDiscWithCDROM', 'book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'SoundDiscWithCDROM', 'Audio CD with CD-ROM')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'SoundDiscWithCDROM', 'Audio Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'SoundDiscWithCDROM', '3')"
			],
		],
		'2022.01.0_add_AudioWithVideoExtra_formats'    => [
			'title'           => 'Add Audio CD with DVD/Blu-Ray',
			'description'     => 'Add Audio CD with DVD/Blu-Ray extras',
			'continueOnError' => true,
			'sql'             =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'MusicCDWithDVD', 'music')
        	,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'MusicCDWithBluRay', 'music')                                                                              
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'MusicCDWithDVD', 'Music CD With DVD')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'MusicCDWithBluRay', 'Music CD With Blu-Ray')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'MusicCDWithDVD', 'Music')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'MusicCDWithBluRay', 'Music')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'MusicCDWithDVD', '6')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'MusicCDWithBluRay', '6')"
			],
		],
		'2022.01.0_add_mp3Disc_format'    => [
			'title'           => 'Add MP3 Audio CD format',
			'description'     => 'Add MP3 Audio CD to translation maps',
			'continueOnError' => true,
			'sql'             =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'MP3Disc', 'book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'MP3Disc', 'MP3 Audio Disc')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'MP3Disc', 'Audio Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'MP3Disc', '8')"
			],
		],
		'2022.02.0_add_comboPack_format' => [
			'title'           => 'Add DVD Blu-Ray Combo Pack Format',
			'description'     => 'Add DVD Blu-Ray Combo to translation maps',
			'continueOnError' => true,
			'sql'             =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'DVDBlu-rayCombo', 'movie')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'DVDBlu-rayCombo', 'DVD Blu-ray Combo Pack')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'DVDBlu-rayCombo', 'Movies')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'DVDBlu-rayCombo', '12')"
			],
		],
		'2022.02.0_add_book_with_accompanying_formats'    => [
			'title'           => 'Add Books with Accompanying formats',
			'description'     => 'Add Books with Accompanying formats to translation maps',
			'continueOnError' => true,
			'sql'             =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'BookWithCDROM', 'book')
        	,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'BookWithDVD', 'book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'BookWithVideoDisc', 'book')                                                                              
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'BookWithCDROM', 'Book with CD-ROM')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'BookWithDVD', 'Book with DVD')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'BookWithVideoDisc', 'Book with Video')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'BookWithCDROM', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'BookWithDVD', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'BookWithVideoDisc', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'BookWithCDROM', '10')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'BookWithDVD', '10')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'BookWithVideoDisc', '10')"
			],
		],
		'2022.02.0_add_book_with_accompanying_Audio_CD'     => [
			'title'             => 'Add Books with Accompanying Audio CD',
			'description'       => 'Add Books with Accompanying Audio CD',
			'continueOnError'   => true,
			'sql'               =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'BookWithAudioCD', 'book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'BookWithAudioCD', 'Book with Audio CD')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'BookWithAudioCD', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'BookWithAudioCD', '10')"
			],
		],
		'2022.02.0_add_book_with_accompanying_DVD_ROM'     => [
			'title'             => 'Add Books with Accompanying DVD',
			'description'       => 'Add Books with Accompanying DVD',
			'continueOnError'   => true,
			'sql'               =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'BookWithDVDROM', 'book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'BookWithDVDROM', 'Book with DVD-ROM')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'BookWithDVDROM', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'BookWithDVDROM', '10')"
			],
		],
		'2022.02.0_add_illustrated_edition'     => [
			'title'             => 'Add Illustrated Edition Format',
			'description'       => 'Add Illustrated Edition Formats to translation maps',
			'continueOnError'   => true,
			'sql'               =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
					'IllustratedEdition', 'book')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'IllustratedEdition', 'Illustrated Edition')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'IllustratedEdition', 'Books')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'IllustratedEdition', '10')"
			],
		],
		'2023.04.0_add_playaway_launchpad'     => [
			'title'             => 'Add Playaway Launchpad Format',
			'description'       => 'Add Playaway Launchpad Formats to translation maps',
			'continueOnError'   => true,
			'sql'               =>[
				"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
					((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
					'PlayawayLaunchpad', 'Playaway Launchpad')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
					'PlayawayLaunchpad', '')
					,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
					'PlayawayLaunchpad', '3')"
			],
		],
		'2024.01.0_fix_cdrom_translation' => [
			'title'           => 'Fix CD-ROM translation',
			'description'     => 'Translate CDROM to CD-ROM instead of Software',
			'continueOnError' => true,
			'sql' => [
				'UPDATE pika.translation_map_values SET `translation` = "CD-ROM" WHERE value = "CDROM" and translation = "Software" ' .
				' AND translationMapId = (SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = "ils") AND name = "format") ' .
				' LIMIT 1;',
			]
		]
	);
}

function setupIndexingProfiles($update){
	global $configArray;
	$profileExists = false;
	//Create a default indexing profile
	$ilsIndexingProfile       = new IndexingProfile();
	$ilsIndexingProfile->name = 'ils';
	if ($ilsIndexingProfile->find(true)){
		$profileExists = true;
	}
	$ilsIndexingProfile->marcPath                = $configArray['Reindex']['marcPath'];
	$ilsIndexingProfile->marcEncoding            = $configArray['Reindex']['marcEncoding'];
	$ilsIndexingProfile->individualMarcPath      = $configArray['Reindex']['individualMarcPath'];
	$ilsIndexingProfile->groupingClass           = 'MarcRecordGrouper';
	$ilsIndexingProfile->indexingClass           = 'IlsRecordProcessor';
	$ilsIndexingProfile->patronDriver            = $configArray['Catalog']['driver'];
	$ilsIndexingProfile->recordDriver            = 'MarcRecord';
	$ilsIndexingProfile->recordUrlComponent      = 'Record';
	$ilsIndexingProfile->formatSource            = $configArray['Reindex']['useItemBasedCallNumbers'] == true ? 'item' : 'bib';
	$ilsIndexingProfile->recordNumberTag         = $configArray['Reindex']['recordNumberTag'];
	$ilsIndexingProfile->recordNumberPrefix      = $configArray['Reindex']['recordNumberPrefix'];
	$ilsIndexingProfile->suppressItemlessBibs    = $configArray['Reindex']['suppressItemlessBibs'] == true ? 1 : 0;
	$ilsIndexingProfile->itemTag                 = $configArray['Reindex']['itemTag'];
	$ilsIndexingProfile->itemRecordNumber        = $configArray['Reindex']['itemRecordNumberSubfield'];
	$ilsIndexingProfile->useItemBasedCallNumbers = $configArray['Reindex']['useItemBasedCallNumbers'] == true ? 1 : 0;
	$ilsIndexingProfile->callNumberPrestamp      = $configArray['Reindex']['callNumberPrestampSubfield'];
	$ilsIndexingProfile->callNumber              = $configArray['Reindex']['callNumberSubfield'];
	$ilsIndexingProfile->callNumberCutter        = $configArray['Reindex']['callNumberCutterSubfield'];
	$ilsIndexingProfile->callNumberPoststamp     = $configArray['Reindex']['callNumberPoststampSubfield'];
	$ilsIndexingProfile->location                = $configArray['Reindex']['locationSubfield'];
	$ilsIndexingProfile->locationsToSuppress     = isset($configArray['Reindex']['locationsToSuppress']) ? $configArray['Reindex']['locationsToSuppress'] : '';
	$ilsIndexingProfile->shelvingLocation        = $configArray['Reindex']['locationSubfield'];
	$ilsIndexingProfile->collection              = $configArray['Reindex']['collectionSubfield'];
	$ilsIndexingProfile->volume                  = $configArray['Reindex']['volumeSubfield'];
	$ilsIndexingProfile->itemUrl                 = $configArray['Reindex']['itemUrlSubfield'];
	$ilsIndexingProfile->barcode                 = $configArray['Reindex']['barcodeSubfield'];
	$ilsIndexingProfile->status                  = $configArray['Reindex']['statusSubfield'];
	$ilsIndexingProfile->statusesToSuppress      = '';
	$ilsIndexingProfile->totalCheckouts          = $configArray['Reindex']['totalCheckoutSubfield'];
	$ilsIndexingProfile->lastYearCheckouts       = $configArray['Reindex']['lastYearCheckoutSubfield'];
	$ilsIndexingProfile->yearToDateCheckouts     = $configArray['Reindex']['ytdCheckoutSubfield'];
	$ilsIndexingProfile->totalRenewals           = $configArray['Reindex']['totalRenewalSubfield'];
	$ilsIndexingProfile->iType                   = $configArray['Reindex']['iTypeSubfield'];
	$ilsIndexingProfile->dueDate                 = $configArray['Reindex']['dueDateSubfield'];
	$ilsIndexingProfile->dateCreated             = $configArray['Reindex']['dateCreatedSubfield'];
	$ilsIndexingProfile->dateCreatedFormat       = $configArray['Reindex']['dateAddedFormat'];
	$ilsIndexingProfile->iCode2                  = $configArray['Reindex']['iCode2Subfield'];
	$ilsIndexingProfile->useICode2Suppression    = $configArray['Reindex']['useICode2Suppression'];
	$ilsIndexingProfile->format                  = isset($configArray['Reindex']['formatSubfield']) ? $configArray['Reindex']['formatSubfield'] : '';
	$ilsIndexingProfile->eContentDescriptor      = $configArray['Reindex']['eContentSubfield'];
//	$ilsIndexingProfile->orderTag                = isset($configArray['Reindex']['orderTag']) ? $configArray['Reindex']['orderTag'] : '';
//	$ilsIndexingProfile->orderStatus             = isset($configArray['Reindex']['orderStatusSubfield']) ? $configArray['Reindex']['orderStatusSubfield'] : '';
//	$ilsIndexingProfile->orderLocation           = isset($configArray['Reindex']['orderLocationsSubfield']) ? $configArray['Reindex']['orderLocationsSubfield'] : '';
//	$ilsIndexingProfile->orderCopies             = isset($configArray['Reindex']['orderCopiesSubfield']) ? $configArray['Reindex']['orderCopiesSubfield'] : '';
//	$ilsIndexingProfile->orderCode3              = isset($configArray['Reindex']['orderCode3Subfield']) ? $configArray['Reindex']['orderCode3Subfield'] : '';

	if ($profileExists){
		$ilsIndexingProfile->update();
	}else{
		$ilsIndexingProfile->insert();
	}

	//Create a profile for hoopla
	$profileExists               = false;
	$hooplaIndexingProfile       = new IndexingProfile();
	$hooplaIndexingProfile->name = 'hoopla';
	if ($hooplaIndexingProfile->find(true)){
		$profileExists = true;
	}
	$hooplaIndexingProfile->marcPath           = $configArray['Hoopla']['marcPath'];
	$hooplaIndexingProfile->marcEncoding       = $configArray['Hoopla']['marcEncoding'];
	$hooplaIndexingProfile->individualMarcPath = $configArray['Hoopla']['individualMarcPath'];
	$hooplaIndexingProfile->groupingClass      = 'HooplaRecordGrouper';
	$hooplaIndexingProfile->indexingClass      = 'Hoopla';
	$hooplaIndexingProfile->recordDriver       = 'HooplaRecordDriver';
	$hooplaIndexingProfile->recordUrlComponent = 'Hoopla';
	$hooplaIndexingProfile->formatSource       = 'bib';
	$hooplaIndexingProfile->recordNumberTag    = '001';
	$hooplaIndexingProfile->recordNumberPrefix = '';
	$hooplaIndexingProfile->itemTag            = '';
	if ($profileExists){
		$hooplaIndexingProfile->update();
	}else{
		$hooplaIndexingProfile->insert();
	}

	//Setup ownership rules and inclusion rules for libraries
	$allLibraries = new Library();
	$allLibraries->find();
	while ($allLibraries->fetch()){
		$ownershipRule                    = new LibraryRecordOwned();
		$ownershipRule->indexingProfileId = $ilsIndexingProfile->id;
		$ownershipRule->libraryId         = $allLibraries->libraryId;
		$ownershipRule->location          = $allLibraries->ilsCode;
		$ownershipRule->insert();

		//Other print titles
		if (!$allLibraries->restrictSearchByLibrary){
			$inclusionRule                      = new LibraryRecordToInclude();
			$inclusionRule->indexingProfileId   = $ilsIndexingProfile->id;
			$inclusionRule->libraryId           = $allLibraries->libraryId;
			$inclusionRule->location            = ".*";
			$inclusionRule->includeHoldableOnly = 1;
			$inclusionRule->includeEContent     = 0;
			$inclusionRule->includeItemsOnOrder = 0;
			$inclusionRule->weight              = 1;
			$inclusionRule->insert();
		}

		//eContent titles
		/*if ($allLibraries->econtentLocationsToInclude){
			$inclusionRule                      = new LibraryRecordToInclude();
			$inclusionRule->indexingProfileId   = $ilsIndexingProfile->id;
			$inclusionRule->libraryId           = $allLibraries->libraryId;
			$inclusionRule->location            = str_replace(',', '|', $allLibraries->econtentLocationsToInclude);
			$inclusionRule->includeHoldableOnly = 0;
			$inclusionRule->includeEContent     = 1;
			$inclusionRule->includeItemsOnOrder = 0;
			$inclusionRule->weight              = 1;
			$inclusionRule->insert();
		}*/

		//Hoopla titles
		/*if ($allLibraries->includeHoopla){
			$inclusionRule = new LibraryRecordToInclude();
			$inclusionRule->indexingProfileId = $hooplaIndexingProfile->id;
			$inclusionRule->libraryId = $allLibraries->libraryId;
			$inclusionRule->location = '.*';
			$inclusionRule->includeHoldableOnly = 0;
			$inclusionRule->includeEContent = 1;
			$inclusionRule->includeItemsOnOrder = 0;
			$inclusionRule->weight = 1;
			$inclusionRule->insert();
		}*/
	}

	//Setup ownership rules and inclusion rules for locations
	$allLocations = new Location();
	$allLocations->find();
	while ($allLocations->fetch()){
		$ownershipRule                    = new LocationRecordOwned();
		$ownershipRule->indexingProfileId = $ilsIndexingProfile->id;
		$ownershipRule->locationId        = $allLocations->locationId;
		$ownershipRule->location          = $allLocations->code;
		$ownershipRule->insert();

		//Other print titles
		if ($allLocations->restrictSearchByLocation){
			$inclusionRule                      = new LocationRecordToInclude();
			$inclusionRule->indexingProfileId   = $ilsIndexingProfile->id;
			$inclusionRule->locationId          = $allLocations->locationId;
			$inclusionRule->location            = ".*";
			$inclusionRule->includeHoldableOnly = 1;
			$inclusionRule->includeEContent     = 0;
			$inclusionRule->includeItemsOnOrder = 0;
			$inclusionRule->weight              = 1;
			$inclusionRule->insert();
		}

		//eContent titles
		/*if ($allLocations->econtentLocationsToInclude){
			$inclusionRule                      = new LocationRecordToInclude();
			$inclusionRule->indexingProfileId   = $ilsIndexingProfile->id;
			$inclusionRule->locationId          = $allLocations->locationId;
			$inclusionRule->location            = str_replace(',', '|', $allLibraries->econtentLocationsToInclude);
			$inclusionRule->includeHoldableOnly = 0;
			$inclusionRule->includeEContent     = 1;
			$inclusionRule->includeItemsOnOrder = 0;
			$inclusionRule->weight              = 1;
			$inclusionRule->insert();
		}*/

		//Hoopla titles
		$relatedLibrary            = new Library();
		$relatedLibrary->libraryId = $allLocations->libraryId;
		if ($relatedLibrary->find(true) && $relatedLibrary->includeHoopla){
			$inclusionRule                      = new LocationRecordToInclude();
			$inclusionRule->indexingProfileId   = $hooplaIndexingProfile->id;
			$inclusionRule->locationId          = $allLocations->locationId;
			$inclusionRule->location            = '.*';
			$inclusionRule->includeHoldableOnly = 0;
			$inclusionRule->includeEContent     = 1;
			$inclusionRule->includeItemsOnOrder = 0;
			$inclusionRule->weight              = 1;
			$inclusionRule->insert();
		}
	}

}
