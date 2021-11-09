<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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
 * Updates related to record grouping for cleanliness
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/29/14
 * Time: 2:25 PM
 */

function getGroupedWorkUpdates(){
	return array(
		'grouped_works' => array(
			'title'       => 'Setup Grouped Works',
			'description' => 'Sets up tables for grouped works so we can index and display them.',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS grouped_work (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					permanent_id CHAR(36) NOT NULL,
					title VARCHAR(100) NULL,
					author VARCHAR(50) NULL,
					subtitle VARCHAR(175) NULL,
					grouping_category VARCHAR(25) NOT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY permanent_id (permanent_id),
					KEY title (title,author,grouping_category)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8",
				"CREATE TABLE IF NOT EXISTS grouped_work_identifiers (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					grouped_work_id BIGINT(20) NOT NULL,
					`type` VARCHAR(15) NOT NULL,
					identifier VARCHAR(36) NOT NULL,
					linksToDifferentTitles TINYINT(4) NOT NULL DEFAULT '0',
					PRIMARY KEY (id),
					KEY `type` (`type`,identifier)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8",
			),

		),

		'grouped_works_1' => array(
			'title'       => 'Grouped Work update 1',
			'description' => 'Updates grouped works to normalize identifiers and add a reference table to link to .',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS grouped_work_identifiers_ref (
					grouped_work_id BIGINT(20) NOT NULL,
					identifier_id BIGINT(20) NOT NULL,
					PRIMARY KEY (grouped_work_id, identifier_id)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8",
				"TRUNCATE TABLE grouped_work_identifiers",
				"ALTER TABLE `grouped_work_identifiers` CHANGE `type` `type` ENUM( 'asin', 'ils', 'isbn', 'issn', 'oclc', 'upc', 'order', 'external_econtent', 'acs', 'free', 'overdrive' )",
				"ALTER TABLE grouped_work_identifiers DROP COLUMN grouped_work_id",
				"ALTER TABLE grouped_work_identifiers DROP COLUMN linksToDifferentTitles",
				"ALTER TABLE grouped_work_identifiers ADD UNIQUE (`type`, `identifier`)",
			),
		),

		'grouped_works_2' => array(
			'title'       => 'Grouped Work update 2',
			'description' => 'Updates grouped works to add a full title field.',
			'sql'         => array(
				"ALTER TABLE `grouped_work` ADD `full_title` VARCHAR( 276 ) NOT NULL",
				"ALTER TABLE `grouped_work` ADD INDEX(`full_title`)",
			),
		),

		'grouped_works_primary_identifiers' => array(
			'title'       => 'Grouped Work Primary Identifiers',
			'description' => 'Add primary identifiers table for works.',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS grouped_work_primary_identifiers (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					grouped_work_id BIGINT(20) NOT NULL,
					`type` ENUM('ils', 'external_econtent', 'acs', 'free', 'overdrive' ) NOT NULL,
					identifier VARCHAR(36) NOT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY (`type`,identifier),
					KEY grouped_record_id (grouped_work_id)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8",
			),
		),

		'grouped_works_primary_identifiers_1' => array(
			'title'       => 'Grouped Work Primary Identifiers Update 1',
			'description' => 'Add additional types of identifiers.',
			'sql'         => array(
				"ALTER TABLE grouped_work_primary_identifiers CHANGE `type` `type` ENUM('ils', 'external', 'drm', 'free', 'overdrive' ) NOT NULL",
			),
		),

		'grouped_work_identifiers_ref_indexing' => array(
			'title'       => 'Grouped Work Identifiers Ref Indexing',
			'description' => 'Add indexing to identifiers re.',
			'sql'         => array(
				"ALTER TABLE grouped_work_identifiers_ref ADD INDEX(identifier_id)",
				"ALTER TABLE grouped_work_identifiers_ref ADD INDEX(grouped_work_id)",
			),
		),

		'grouped_works_partial_updates' => array(
			'title'       => 'Grouped Work Partial Updates',
			'description' => 'Updates to allow only changed records to be regrouped.',
			'sql'         => array(
				"ALTER TABLE grouped_work ADD date_updated INT(11)",
				"CREATE TABLE grouped_work_primary_to_secondary_id_ref (
					primary_identifier_id BIGINT(20),
					secondary_identifier_id BIGINT(20),
					UNIQUE KEY (primary_identifier_id, secondary_identifier_id),
					KEY (primary_identifier_id),
					KEY (secondary_identifier_id)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8",
				"ALTER TABLE grouped_work_identifiers ADD valid_for_enrichment TINYINT(1) DEFAULT 1"
			),
		),

		'grouped_work_engine' => array(
			'title'       => 'Grouped Work Engine',
			'description' => 'Change storage engine to INNODB for grouped work tables',
			'sql'         => array(
				'ALTER TABLE `grouped_work` ENGINE = InnoDB',
				'ALTER TABLE `grouped_work_identifiers` ENGINE = InnoDB',
				'ALTER TABLE `grouped_work_identifiers_ref` ENGINE = InnoDB',
				'ALTER TABLE `grouped_work_primary_identifiers` ENGINE = InnoDB',
				'ALTER TABLE `grouped_work_primary_to_secondary_id_ref` ENGINE = InnoDB',
				'ALTER TABLE `ils_marc_checksums` ENGINE = InnoDB',
			)
		),

		'grouped_work_merging' => array(
			'title'       => 'Grouped Work Merging',
			'description' => 'Add a new table to allow manual merging of grouped works',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS merged_grouped_works(
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					sourceGroupedWorkId CHAR(36) NOT NULL,
					destinationGroupedWorkId CHAR(36) NOT NULL,
					notes VARCHAR(250) NOT NULL DEFAULT '',
					PRIMARY KEY (id),
					UNIQUE KEY (sourceGroupedWorkId,destinationGroupedWorkId)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8",
			)
		),

		'grouped_work_index_date_updated' => array(
			'title'       => 'Grouped Work Index Date Update',
			'description' => 'Index date updated to improve performance',
			'sql'         => array(
				"ALTER TABLE `grouped_work` ADD INDEX(`date_updated`)",
			)
		),

		'grouped_work_index_cleanup' => array(
			'title'           => 'Cleanup Grouped Work Indexes',
			'description'     => 'Cleanup Indexes for better performance',
			'continueOnError' => true,
			'sql'             => array(
				"DROP INDEX title on grouped_work",
				"DROP INDEX full_title on grouped_work",
				"DROP INDEX grouped_work_id on grouped_work_identifiers",
				"DROP INDEX type_2 on grouped_work_identifiers",
				"DROP INDEX type_3 on grouped_work_identifiers",
				"DROP INDEX identifier_id_2 on grouped_work_identifiers_ref",
				"DROP INDEX grouped_work_id on grouped_work_identifiers_ref",
				"DROP INDEX grouped_work_id_2 on grouped_work_identifiers_ref",
				"DROP INDEX primary_identifier_id_2 on grouped_work_primary_to_secondary_id_ref",
			),
		),

		'grouped_work_duplicate_identifiers' => array(
			'title'           => 'Cleanup Grouped Duplicate Identifiers within ',
			'description'     => 'Cleanup Duplicate Identifiers that were added mistakenly',
			'continueOnError' => true,
			'sql'             => array(
				"TRUNCATE table grouped_work_identifiers",
				"TRUNCATE table grouped_work_identifiers_ref",
				"TRUNCATE table grouped_work_primary_to_secondary_id_ref",
				"ALTER TABLE grouped_work_identifiers DROP INDEX type",
				"ALTER TABLE grouped_work_identifiers ADD UNIQUE (`type`, `identifier`)",
			),
		),

		'grouped_work_primary_identifier_types' => array(
			'title'           => 'Expand Primary Identifiers Types ',
			'description'     => 'Expand Primary Identifiers so they can be any type to make it easier to index different collections.',
			'continueOnError' => true,
			'sql'             => array(
				"ALTER TABLE grouped_work_primary_identifiers CHANGE `type` `type` VARCHAR(50) NOT NULL",
			),
		),

		'increase_ilsID_size_for_ils_marc_checksums' => array(
			'title'           => 'Expand ilsId Size',
			'description'     => 'Increase the column size of the ilsId in the ils_marc_checksums table to accomodate larger Sideload Ids.',
			'continueOnError' => true,
			'sql'             => array(
				"ALTER TABLE `ils_marc_checksums` CHANGE COLUMN `ilsId` `ilsId` VARCHAR(50) NOT NULL ;",
			),
		),

		'historical_grouped_works' => array(
			'title'       => 'Create a historical grouped work factors table',
			'description' => 'Table to track grouping factors that lead to the unique permanent id among grouping versions. Do not remove entries even if there are no longer contributing records in the catalog. ',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS `grouped_work_historical` (
				  `permanent_id` CHAR(36) NOT NULL,
				  `grouping_title` VARCHAR(250) NOT NULL,
				  `grouping_author` VARCHAR(50) NOT NULL,
				  `grouping_category` VARCHAR(25) NOT NULL,
				  `grouping_version` SMALLINT(1) UNSIGNED NOT NULL,
				  UNIQUE INDEX `index1` (`permanent_id` ASC, `grouping_title` ASC, `grouping_author` ASC, `grouping_category` ASC, `grouping_version` ASC))
				ENGINE = InnoDB
				DEFAULT CHARACTER SET = utf8
				COMMENT = 'Table to track grouping factors that lead to the unique permanent id among grouping versions. Do not remove entries even if there are no longer contributing records in the catalog. ';",
			),
		),

		'add_language_to_grouping_table-2020.02' => array(
			'title'           => 'Step 0 : Add Grouping Language',
			'description'     => 'Add language to the grouped work table',
			'continueOnError' => false,
			'sql'             => array(
				"ALTER TABLE `grouped_work` ADD COLUMN `grouping_language` CHAR(3) NULL AFTER `full_title`;",
				"ALTER TABLE `grouped_work_historical` ADD COLUMN `grouping_language` CHAR(3) NULL AFTER `grouping_category`;",
			),
		),

		'preferred_grouping_tables-2020.06' => [
			'title'           => 'Step 0 : Create Preferred Grouping Author & Title tables',
			'description'     => 'Tables for looking up an authoritative version of a grouping title or author',
			'continueOnError' => false,
			'sql'             => [
				"CREATE TABLE `grouping_titles_preferred` (
					`id` INT NOT NULL AUTO_INCREMENT,
					`sourceGroupingTitle` VARCHAR(400) NULL,
					`preferredGroupingTitle` VARCHAR(400) NULL,
					`notes` VARCHAR(250) NULL,
				  `userId` int(10) unsigned DEFAULT NULL,
				  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`))
					ENGINE = InnoDB
					DEFAULT CHARACTER SET = utf8;",
				"CREATE TABLE `grouping_authors_preferred` (
					`id` INT NOT NULL AUTO_INCREMENT,
					`sourceGroupingAuthor` VARCHAR(100) NULL,
					`preferredGroupingAuthor` VARCHAR(100) NULL,
					`notes` VARCHAR(250) NULL,
				  `userId` int(10) unsigned DEFAULT NULL,
				  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (`id`),
					UNIQUE INDEX `sourceGroupingAuthor_UNIQUE` (`sourceGroupingAuthor` ASC))
					ENGINE = InnoDB
					DEFAULT CHARACTER SET = utf8;",
			],
		],

		'grouping_table_sizing-2020.06' => [
			'title'           => 'Step 0 : Increase grouping title/author column sizes',
			'description'     => 'Increase grouping title/author column sizes',
			'continueOnError' => false,
			'sql'             => [
				"ALTER TABLE `grouped_work` 
					CHANGE COLUMN `author` `author` VARCHAR(100) NOT NULL ,
					CHANGE COLUMN `grouping_category` `grouping_category` VARCHAR(5) NOT NULL ,
					CHANGE COLUMN `full_title` `full_title` VARCHAR(400) NOT NULL ;",
			],
		],

		'grouping_migration-2020.06' => [
			'title'           => 'Step 1 : Prepare for grouping migration ',
			'description'     => 'Run sql updates to do grouping migration',
			'continueOnError' => false,
			'sql'             => [
				"DROP TABLE IF EXISTS `grouped_work_identifiers`;",
				"DROP TABLE IF EXISTS `grouped_work_identifiers_ref`;",
				"DROP TABLE IF EXISTS `grouped_work_primary_to_secondary_id_ref`;",
				"ALTER TABLE `user_work_review` CHANGE COLUMN `groupedRecordPermanentId` `groupedWorkPermanentId` VARCHAR(36) NULL DEFAULT NULL ;",
				"ALTER TABLE `user_not_interested` CHANGE COLUMN `groupedRecordPermanentId` `groupedWorkPermanentId` VARCHAR(36) NULL DEFAULT NULL ;",
				"ALTER TABLE `user_tags` CHANGE COLUMN `groupedRecordPermanentId` `groupedWorkPermanentId` VARCHAR(36) NULL DEFAULT NULL ;",
				"ALTER TABLE `novelist_data` CHANGE COLUMN `groupedRecordPermanentId` `groupedWorkPermanentId` VARCHAR(36) NULL DEFAULT NULL ;",
				"ALTER TABLE `islandora_samepika_cache` CHANGE COLUMN `groupedWorkId` `groupedWorkPermanentId` CHAR(36) NOT NULL ;",
				"ALTER TABLE `grouped_work` RENAME TO `grouped_work_old` ;",
				"ALTER TABLE `grouped_work_primary_identifiers` RENAME TO `grouped_work_primary_identifiers_old` ;",
				"ALTER TABLE `merged_grouped_works` RENAME TO `merged_grouped_works_old` ;",
				"ALTER TABLE `nongrouped_records` RENAME TO `nongrouped_records_old` ;",
				"ALTER TABLE `grouped_work_historical`
						DROP INDEX `index1`,
						ADD INDEX `index2` (`permanent_id` ASC),
						CHANGE COLUMN `grouping_title` `grouping_title` VARCHAR(400) NOT NULL ,
						CHANGE COLUMN `grouping_author` `grouping_author` VARCHAR(100) NOT NULL ;",
				"CREATE TABLE `grouped_work` (
					  `id` bigint(20) NOT NULL AUTO_INCREMENT,
					  `permanent_id` char(36) NOT NULL,
					  `author` varchar(100) NOT NULL,
					  `grouping_category` varchar(5) NOT NULL,
					  `full_title` varchar(400) NOT NULL,
					  `grouping_language` char(3) DEFAULT NULL,
					  `date_updated` int(11) DEFAULT NULL,
					  PRIMARY KEY (`id`),
					  UNIQUE KEY `permanent_id` (`permanent_id`),
					  KEY `date_updated` (`date_updated`)
					) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;",
				"CREATE TABLE `grouped_work_primary_identifiers` (
					  `id` bigint(20) NOT NULL AUTO_INCREMENT,
					  `grouped_work_id` bigint(20) NOT NULL,
					  `type` varchar(45) NOT NULL,
					  `identifier` varchar(36) NOT NULL,
					  PRIMARY KEY (`id`),
					  UNIQUE KEY `type` (`type`,`identifier`),
					  KEY `grouped_record_id` (`grouped_work_id`)
					) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;",
				"CREATE TABLE `grouped_work_merges` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `sourceGroupedWorkId` char(36) NOT NULL,
				  `destinationGroupedWorkId` char(36) NOT NULL,
				  `notes` varchar(250) DEFAULT NULL,
				  `userId` int(10) unsigned DEFAULT NULL,
				  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `sourceGroupedWorkId` (`sourceGroupedWorkId`,`destinationGroupedWorkId`)
				) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;",
				"CREATE TABLE `nongrouped_records` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `source` varchar(45) NOT NULL,
				  `recordId` varchar(36) NOT NULL,
				  `notes` varchar(255) NOT NULL,
				  `userId` int(10) unsigned DEFAULT NULL,
				  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `source` (`source`,`recordId`)
				) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;",
				"CREATE TABLE `grouped_work_versions_map` (
				  `groupedWorkPermanentIdVersion4` char(36) NOT NULL,
				  `groupedWorkPermanentIdVersion5` char(36) DEFAULT NULL,
				  `missingFromCatalog` tinyint(1) unsigned DEFAULT NULL,
				  PRIMARY KEY (`groupedWorkPermanentIdVersion4`),
				  UNIQUE KEY `version4_permanent_id_UNIQUE` (`groupedWorkPermanentIdVersion4`),
				  KEY `version5` (`groupedWorkPermanentIdVersion5`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
			],
		],

		'grouping_migration_implement_source_name-2020.06' => [
			'title'           => 'Step 1 : Set sourceName for old related records table',
			'description'     => 'Change the type for grouped_work_primary_identifiers_old from the indexing profile name to the source name.',
			'continueOnError' => false,
			'sql'             => [
				"UPDATE grouped_work_primary_identifiers_old
					LEFT JOIN indexing_profiles ON (type = name)
					SET grouped_work_primary_identifiers_old.type = indexing_profiles.sourceName
					WHERE type != sourceName",
			],
		],

		'grouping_migration_data_clean_up-2020.06' => [
			'title'           => 'Step 2 : Reading History Clean up',
			'description'     => 'Delete reading history that is marked as deleted, correct reading history sources',
			'continueOnError' => false,
			'sql'             => [
				"DELETE FROM user_reading_history_work WHERE deleted = 1;", // remove any entries that have been marked as deleted to make the migration cleaner
				"UPDATE user_reading_history_work SET source = lower(source);", // Set Reading history entries to lower case
				"DELETE FROM user_list_entry WHERE groupedWorkPermanentId ='' ", // Clean up user list entries with no data
			],
		],

		'grouping_migration_build_version_map-2020.06' => [
			'title'           => 'Step 3 : Populate Grouped Work Version Map with version 4 Ids',
			'description'     => 'Add version 4 ids found in user data tables into grouped work version map',
			'continueOnError' => false,
			'sql'             => [
				"TRUNCATE `novelist_data`;", // This table will repopulate on its own
				"TRUNCATE `pika`.`islandora_samepika_cache`;", // This table will repopulate on its own
				// populate user related entries into map
				"INSERT LOW_PRIORITY IGNORE INTO grouped_work_versions_map (groupedWorkPermanentIdVersion4) SELECT DISTINCT groupedWorkPermanentId FROM user_reading_history_work WHERE groupedWorkPermanentId IS NOT NULL;",
				"INSERT LOW_PRIORITY IGNORE INTO grouped_work_versions_map (groupedWorkPermanentIdVersion4) SELECT DISTINCT groupedWorkPermanentId FROM user_work_review WHERE groupedWorkPermanentId IS NOT NULL;",
				"INSERT LOW_PRIORITY IGNORE INTO grouped_work_versions_map (groupedWorkPermanentIdVersion4) SELECT DISTINCT groupedWorkPermanentId FROM user_list_entry WHERE groupedWorkPermanentId IS NOT NULL;",
				"INSERT LOW_PRIORITY IGNORE INTO grouped_work_versions_map (groupedWorkPermanentIdVersion4) SELECT DISTINCT groupedWorkPermanentId FROM user_tags WHERE groupedWorkPermanentId IS NOT NULL;",
				"INSERT LOW_PRIORITY IGNORE INTO grouped_work_versions_map (groupedWorkPermanentIdVersion4) SELECT DISTINCT groupedWorkPermanentId FROM user_not_interested WHERE groupedWorkPermanentId IS NOT NULL;",
				"INSERT LOW_PRIORITY IGNORE INTO grouped_work_versions_map (groupedWorkPermanentIdVersion4) SELECT DISTINCT groupedWorkPermanentId FROM librarian_reviews WHERE groupedWorkPermanentId IS NOT NULL;",
				"DELETE FROM grouped_work_versions_map WHERE groupedWorkPermanentIdVersion4 LIKE \"%:%\";", // Remove Archive PIDs
				"DELETE FROM `grouped_work_versions_map` WHERE `groupedWorkPermanentIdVersion4`='';", // remove the empty entry
			],
		],

		'2021.04.0_remove_old_grouping_tables' => [
			'title'           => 'Remove old grouping tables',
			'description'     => 'Remove old grouping tables from before grouping improvement',
			'continueOnError' => true,
			'sql'             => [
				'DROP TABLE IF EXISTS `grouped_work_old`',
				'DROP TABLE IF EXISTS `grouped_work_primary_identifiers_old`',
				'DROP TABLE IF EXISTS `merged_grouped_works_old`',
				'DROP TABLE IF EXISTS `nongrouped_records_old`',
			],
		],

	);
}
