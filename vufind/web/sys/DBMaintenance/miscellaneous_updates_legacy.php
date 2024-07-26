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

function getSQLUpdates(){
	global $configArray;

	require_once ROOT_DIR . '/sys/DBMaintenance/library_location_updates.php';
	require_once ROOT_DIR . '/sys/DBMaintenance/grouped_work_updates.php';
	require_once ROOT_DIR . '/sys/DBMaintenance/indexing_updates.php';
	require_once ROOT_DIR . '/sys/DBMaintenance/user_updates.php';
	require_once ROOT_DIR . '/sys/DBMaintenance/hoopla_updates.php';
	require_once ROOT_DIR . '/sys/DBMaintenance/list_widget_updates.php';
	//require_once ROOT_DIR . '/sys/DBMaintenance/islandora_updates.php';

	return array_merge(
		getLibraryLocationUpdates(),
		getGroupedWorkUpdates(),
		getIndexingUpdates(),
		getUserUpdates(),
		getHooplaUpdates(),
		getListWidgetUpdates(),
		//getIslandoraUpdates(),
		//getSierraAPIUpdates(),

		[
			'new_search_stats' => [
				'title'       => 'Create new search stats table with better performance',
				'description' => 'Create an optimized table for performing auto completes based on prior searches',
				'sql'         => [
					"CREATE TABLE `search_stats_new` (
						  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'The unique id of the search statistic',
						  `phrase` varchar(500) NOT NULL COMMENT 'The phrase being searched for',
						  `lastSearch` int(16) NOT NULL COMMENT 'The last time this search was done',
						  `numSearches` int(16) NOT NULL COMMENT 'The number of times this search has been done.',
						  PRIMARY KEY (`id`),
						  KEY `numSearches` (`numSearches`),
						  KEY `lastSearch` (`lastSearch`),
						  KEY `phrase` (`phrase`),
						  FULLTEXT `phrase_text` (`phrase`)
						) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='Statistical information about searches for use in reporting '",
					"INSERT INTO search_stats_new (phrase, lastSearch, numSearches) SELECT TRIM(REPLACE(phrase, char(9), '')) as phrase, MAX(lastSearch), sum(numSearches) FROM search_stats WHERE numResults > 0 GROUP BY TRIM(REPLACE(phrase,char(9), ''))",
					"DELETE FROM search_stats_new WHERE phrase LIKE '%(%'",
					"DELETE FROM search_stats_new WHERE phrase LIKE '%)%'",
				],
			],


			'editorial_review' => [
				'title'       => 'Create Editorial Review table',
				'description' => 'Create editorial review tables for external reviews, i.e. book-a-day blog',
				'sql'         => [
					"CREATE TABLE editorial_reviews (" .
					"editorialReviewId int NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
					"recordId VARCHAR(50) NOT NULL, " .
					"title VARCHAR(255) NOT NULL, " .
					"pubDate BIGINT NOT NULL, " .
					"review TEXT, " .
					"source VARCHAR(50) NOT NULL" .
					")",
				],
			],
			'editorial_review_update_2020_01' => [
				'title'       => 'Update Editorial Review table',
				'description' => 'use grouped workIds and use timestamp column',
				'sql'         => [
					"ALTER TABLE editorial_reviews CHANGE COLUMN `recordId` `groupedWorkPermanentId` CHAR(36) NOT NULL ;",
					"ALTER TABLE editorial_reviews DROP COLUMN `tabName` ;",
					"ALTER TABLE editorial_reviews DROP COLUMN `teaser` ;",
					"ALTER TABLE editorial_reviews DROP COLUMN `pubDate` ;",
					"ALTER TABLE editorial_reviews ADD COLUMN  `pubDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ;",
				],
			],

			'materialsRequest' => [
				'title'       => 'Materials Request Table Creation',
				'description' => 'Update reading History to include an id table',
				'sql'         => [
					'CREATE TABLE IF NOT EXISTS materials_request (' .
					'id int(11) NOT NULL AUTO_INCREMENT, ' .
					'title varchar(255), ' .
					'author varchar(255), ' .
					'format varchar(25), ' .
					'ageLevel varchar(25), ' .
					'isbn varchar(15), ' .
					'oclcNumber varchar(30), ' .
					'publisher varchar(255), ' .
					'publicationYear varchar(4), ' .
					'articleInfo varchar(255), ' .
					'abridged TINYINT, ' .
					'about TEXT, ' .
					'comments TEXT, ' .
					"status enum('pending', 'owned', 'purchased', 'referredToILL', 'ILLplaced', 'ILLreturned', 'notEnoughInfo', 'notAcquiredOutOfPrint', 'notAcquiredNotAvailable', 'notAcquiredFormatNotAvailable', 'notAcquiredPrice', 'notAcquiredPublicationDate', 'requestCancelled') DEFAULT 'pending', " .
					'dateCreated int(11), ' .
					'createdBy int(11), ' .
					'dateUpdated int(11), ' .
					'PRIMARY KEY (id) ' .
					') ENGINE=InnoDB',
				],
			],

			'materialsRequest_update1' => [
				'title'       => 'Materials Request Update 1',
				'description' => 'Material Request add fields for sending emails and creating holds',
				'sql'         => [
					'ALTER TABLE `materials_request` ADD `emailSent` TINYINT NOT NULL DEFAULT 0',
					'ALTER TABLE `materials_request` ADD `holdsCreated` TINYINT NOT NULL DEFAULT 0',
					'ALTER TABLE `materials_request` ADD `email` VARCHAR(80)',
					'ALTER TABLE `materials_request` ADD `phone` VARCHAR(15)',
					'ALTER TABLE `materials_request` ADD `season` VARCHAR(80)',
					'ALTER TABLE `materials_request` ADD `magazineTitle` VARCHAR(255)',
					//'ALTER TABLE `materials_request` CHANGE `isbn_upc` `isbn` VARCHAR( 15 )',
					'ALTER TABLE `materials_request` ADD `upc` VARCHAR(15)',
					'ALTER TABLE `materials_request` ADD `issn` VARCHAR(8)',
					'ALTER TABLE `materials_request` ADD `bookType` VARCHAR(20)',
					'ALTER TABLE `materials_request` ADD `subFormat` VARCHAR(20)',
					'ALTER TABLE `materials_request` ADD `magazineDate` VARCHAR(20)',
					'ALTER TABLE `materials_request` ADD `magazineVolume` VARCHAR(20)',
					'ALTER TABLE `materials_request` ADD `magazinePageNumbers` VARCHAR(20)',
					'ALTER TABLE `materials_request` ADD `placeHoldWhenAvailable` TINYINT',
					'ALTER TABLE `materials_request` ADD `holdPickupLocation` VARCHAR(10)',
					'ALTER TABLE `materials_request` ADD `bookmobileStop` VARCHAR(50)',
					'ALTER TABLE `materials_request` ADD `illItem` VARCHAR(80)',
					'ALTER TABLE `materials_request` ADD `magazineNumber` VARCHAR(80)',
					'ALTER TABLE `materials_request` ADD INDEX(createdBy)',
					'ALTER TABLE `materials_request` ADD INDEX(dateUpdated)',
					'ALTER TABLE `materials_request` ADD INDEX(dateCreated)',
					'ALTER TABLE `materials_request` ADD INDEX(emailSent)',
					'ALTER TABLE `materials_request` ADD INDEX(holdsCreated)',
					'ALTER TABLE `materials_request` ADD INDEX(format)',
					'ALTER TABLE `materials_request` ADD INDEX(subFormat)',
					'ALTER TABLE `materials_request` ADD COLUMN `assignedTo` INT NULL',
				],
			],

			'materialsRequestStatus' => [
				'title'       => 'Materials Request Status Table Creation',
				'description' => 'Update reading History to include an id table',
				'sql'         => [
					'CREATE TABLE IF NOT EXISTS materials_request_status (' .
					'id int(11) NOT NULL AUTO_INCREMENT, ' .
					'description varchar(80), ' .
					'isDefault TINYINT DEFAULT 0, ' .
					'sendEmailToPatron TINYINT, ' .
					'emailTemplate TEXT, ' .
					'isOpen TINYINT, ' .
					'isPatronCancel TINYINT, ' .
					'PRIMARY KEY (id) ' .
					') ENGINE=InnoDB',

					"INSERT INTO materials_request_status (description, isDefault, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Request Pending', 1, 0, '', 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Already owned/On order', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The Library already owns this item or it is already on order. Please access our catalog to place this item on hold.	Please check our online catalog periodically to put a hold for this item.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Item purchased', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. Outcome: The library is purchasing the item you requested. Please check our online catalog periodically to put yourself on hold for this item. We anticipate that this item will be available soon for you to place a hold.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Referred to Collection Development - Adult', 0, '', 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Referred to Collection Development - J/YA', 0, '', 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Referred to Collection Development - AV', 0, '', 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('ILL Under Review', 0, '', 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Request Referred to ILL', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The library\\'s Interlibrary loan department is reviewing your request. We will attempt to borrow this item from another system. This process generally takes about 2 - 6 weeks.', 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Request Filled by ILL', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. Our Interlibrary Loan Department is set to borrow this item from another library.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Ineligible ILL', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. Your library account is not eligible for interlibrary loan at this time.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Not enough info - please contact Collection Development to clarify', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We need more specific information in order to locate the exact item you need. Please re-submit your request with more details.', 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - out of print', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item is out of print.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - not available in the US', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item is not available in the US.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - not available from vendor', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item is not available from a preferred vendor.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - not published', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The item you requested has not yet been published. Please check our catalog when the publication date draws near.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - price', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item does not fit our collection guidelines.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - publication date', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item does not fit our collection guidelines.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unavailable', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The item you requested cannot be purchased at this time from any of our regular suppliers and is not available from any of our lending libraries.', 0)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen, isPatronCancel) VALUES ('Cancelled by Patron', 0, '', 0, 1)",
					"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Cancelled - Duplicate Request', 0, '', 0)",

					"UPDATE materials_request SET status = (SELECT id FROM materials_request_status WHERE isDefault =1)",

					"ALTER TABLE materials_request CHANGE `status` `status` INT(11)",
				],
			],

			'manageMaterialsRequestFieldsToDisplay' => [
				'title'       => 'Manage Material Requests Fields to Display Table Creation',
				'description' => 'New table to manage columns displayed in lists of materials requests on the manage page.',
				'sql'         => [
					"CREATE TABLE `materials_request_fields_to_display` ("
					. "  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,"
					. "  `libraryId` int(11) NOT NULL,"
					. "  `columnNameToDisplay` varchar(30) NOT NULL,"
					. "  `labelForColumnToDisplay` varchar(45) NOT NULL,"
					. "  `weight` smallint(2) unsigned NOT NULL DEFAULT '0',"
					. "  PRIMARY KEY (`id`),"
					. "  UNIQUE KEY `columnNameToDisplay` (`columnNameToDisplay`,`libraryId`),"
					. "  KEY `libraryId` (`libraryId`)"
					. ") ENGINE=InnoDB DEFAULT CHARSET=utf8;"
				],
			],

			'materialsRequestFormats' => [
				'title'       => 'Material Requests Formats Table Creation',
				'description' => 'New table to manage materials formats that can be requested.',
				'sql'         => [
					'CREATE TABLE `materials_request_formats` ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
					. '`libraryId` INT UNSIGNED NOT NULL,'
					. ' `format` VARCHAR(30) NOT NULL,'
					. '`formatLabel` VARCHAR(60) NOT NULL,'
					. '`authorLabel` VARCHAR(45) NOT NULL,'
					. '`weight` SMALLINT(2) UNSIGNED NOT NULL DEFAULT 0,'
					. "`specialFields` SET('Abridged/Unabridged', 'Article Field', 'Eaudio format', 'Ebook format', 'Season') NULL,"
					. 'PRIMARY KEY (`id`),'
					. 'INDEX `libraryId` (`libraryId` ASC));'
				],
			],

			'materialsRequestFormFields' => [
				'title'       => 'Material Requests Form Fields Table Creation',
				'description' => 'New table to manage materials request form fields.',
				'sql'         => [
					'CREATE TABLE `materials_request_form_fields` ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
					. '`libraryId` INT UNSIGNED NOT NULL,'
					. '`formCategory` VARCHAR(55) NOT NULL,'
					. '`fieldLabel` VARCHAR(255) NOT NULL,'
					. '`fieldType` VARCHAR(30) NULL,'
					. '`weight` SMALLINT(2) UNSIGNED NOT NULL,'
					. 'PRIMARY KEY (`id`),'
					. 'UNIQUE INDEX `id_UNIQUE` (`id` ASC),'
					. 'INDEX `libraryId` (`libraryId` ASC));'
				],
			],

			'staffSettingsTable' => [
				'title'       => 'Staff Settings Table Creation',
				'description' => 'New table to contain user settings for staff users.',
				'sql'         => [
					'CREATE TABLE `user_staff_settings` ('
					. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
					. '`userId` INT UNSIGNED NOT NULL,'
					. '`materialsRequestReplyToAddress` VARCHAR(70) NULL,'
					. '`materialsRequestEmailSignature` TINYTEXT NULL,'
					. 'PRIMARY KEY (`id`),'
					. 'UNIQUE INDEX `userId_UNIQUE` (`userId` ASC),'
					. 'INDEX `userId` (`userId` ASC));'
				],
			],

			'materialsRequestLibraryId' => [
				'title'       => 'Add LibraryId to Material Requests Table',
				'description' => 'Add LibraryId column to Materials Request table and populate column for existing requests.',
				'sql'         => [
					'ALTER TABLE `materials_request` '
					. 'ADD COLUMN `libraryId` INT UNSIGNED NULL AFTER `id`, '
					. 'ADD COLUMN `formatId` INT UNSIGNED NULL AFTER `format`; ',

					'ALTER TABLE `materials_request` '
					. 'CHANGE COLUMN `illItem` `illItem` TINYINT(4) NULL DEFAULT NULL ;',

					'UPDATE  `materials_request`'
					. 'LEFT JOIN `user` ON (user.id=materials_request.createdBy) '
					. 'LEFT JOIN `location` ON (location.locationId=user.homeLocationId) '
					. 'SET materials_request.libraryId = location.libraryId '
					. 'WHERE materials_request.libraryId IS null '
					. 'and user.id IS NOT null '
					. 'and location.libraryId IS not null;',

					'UPDATE `materials_request` '
					. 'LEFT JOIN `location` ON (location.locationId=materials_request.holdPickupLocation) '
					. 'SET materials_request.libraryId = location.libraryId '
					. ' WHERE materials_request.libraryId IS null and location.libraryId IS not null;'
				],
			],

			'materialsRequestStatus_update1' => [
				'title'       => 'Materials Request Status Update 1',
				'description' => 'Material Request Status add library id',
				'sql'         => [
					"ALTER TABLE `materials_request_status` ADD `libraryId` INT(11) DEFAULT '-1'",
					'ALTER TABLE `materials_request_status` ADD INDEX (`libraryId`)',
				],
			],

			'catalogingRole' => [
				'title'       => 'Create cataloging role',
				'description' => 'Create cataloging role to handle materials requests, econtent loading, etc.',
				'sql'         => [
					"INSERT INTO `roles` (`name`, `description`) VALUES ('cataloging', 'Allows user to perform cataloging activities.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('library_material_requests', 'Allows user to manage material requests for a specific library.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('libraryManager', 'Allows user to do basic configuration for their library.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('locationManager', 'Allows user to do basic configuration for their location.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('circulationReports', 'Allows user to view offline circulation reports.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('libraryAdmin', 'Allows user to update library configuration for their library system only for their home location.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('contentEditor', 'Allows entering of librarian reviews and creation of widgets.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('listPublisher', 'Optionally only include lists from people with this role in search results.')",
					"INSERT INTO `roles` (`name`, `description`) VALUES ('archives', 'Control overall archives integration.')",
					"INSERT INTO roles (name, description) VALUES ('locationReports', 'Allows the user to view reports for their location.')",
				],
			],

			'ip_lookup_1' => [
				'title'           => 'IP Lookup Update 1',
				'description'     => 'Add start and end ranges for IP Lookup table to improve performance.',
				'continueOnError' => true,
				'sql'             => [
					"ALTER TABLE ip_lookup ADD COLUMN startIpVal BIGINT",
					"ALTER TABLE ip_lookup ADD COLUMN endIpVal BIGINT",
					"ALTER TABLE `ip_lookup` ADD INDEX ( `startIpVal` )",
					"ALTER TABLE `ip_lookup` ADD INDEX ( `endIpVal` )",
					"ALTER TABLE `ip_lookup` CHANGE `startIpVal` `startIpVal` BIGINT NULL DEFAULT NULL ",
					"ALTER TABLE `ip_lookup` CHANGE `endIpVal` `endIpVal` BIGINT NULL DEFAULT NULL ",
					"ALTER TABLE `ip_lookup` ADD COLUMN `isOpac` TINYINT UNSIGNED NOT NULL DEFAULT 1",
					"createDefaultIpRanges"

				],
			],

			'author_enrichment' => [
				'title'       => 'Author Enrichment',
				'description' => 'Create table to store enrichment for authors',
				'sql'         => [
					"CREATE TABLE `author_enrichment` (
									id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
									`authorName` VARCHAR( 255 ) NOT NULL,
									`hideWikipedia` TINYINT( 1 ),
									`wikipediaUrl` VARCHAR( 255 ),
									INDEX(authorName)
								)",
				],
			],

			'variables_table' => [
				'title'       => 'Variables Table',
				'description' => 'Create Variables Table for storing basic variables for use in programs (system writable config)',
				'sql'         => [
					"CREATE TABLE `variables` (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							`name` VARCHAR( 128 ) NOT NULL,
							`value` VARCHAR( 255 ),
							INDEX(name)
						)",
					"ALTER TABLE variables ADD UNIQUE (name)",
					"INSERT INTO variables (name, value) VALUES ('validateChecksumsFromDisk', 'false')",
					"INSERT INTO variables (name, value) VALUES ('offline_mode_when_offline_login_allowed', 'false')",
					"INSERT INTO variables (name, value) VALUES ('fullReindexIntervalWarning', '86400')",
					"INSERT INTO variables (name, value) VALUES ('fullReindexIntervalCritical', '129600')",
				],
			],

			'utf8_update' => [
				'title'           => 'Update to UTF-8',
				'description'     => 'Update database to use UTF-8 encoding',
				'continueOnError' => true,
				'sql'             => [
					"ALTER DATABASE " . $configArray['Database']['database_vufind_dbname'] . " DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;",
					//"ALTER TABLE administrators CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE bad_words CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE comments CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE db_update CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE editorial_reviews CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE ip_lookup CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE library CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE list_widgets CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE list_widget_lists CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE location CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE resource CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE resource_tags CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE roles CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE search CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE search_stats CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE session CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE spelling_words CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE tags CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE user CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE user_list CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					"ALTER TABLE user_roles CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
				],
			],

			'reindexLog' => [
				'title'       => 'Reindex Log table',
				'description' => 'Create Reindex Log table to track reindexing.',
				'sql'         => [
					"CREATE TABLE IF NOT EXISTS reindex_log(" .
					"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of reindex log', " .
					"`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the reindex started', " .
					"`endTime` INT(11) NULL COMMENT 'The timestamp when the reindex process ended', " .
					"PRIMARY KEY ( `id` )" .
					") ENGINE = MYISAM;",
					"CREATE TABLE IF NOT EXISTS reindex_process_log(" .
					"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of reindex process', " .
					"`reindex_id` INT(11) NOT NULL COMMENT 'The id of the reindex log this process ran during', " .
					"`processName` VARCHAR(50) NOT NULL COMMENT 'The name of the process being run', " .
					"`recordsProcessed` INT(11) NOT NULL COMMENT 'The number of records processed from marc files', " .
					"`eContentRecordsProcessed` INT(11) NOT NULL COMMENT 'The number of econtent records processed from the database', " .
					"`resourcesProcessed` INT(11) NOT NULL COMMENT 'The number of resources processed from the database', " .
					"`numErrors` INT(11) NOT NULL COMMENT 'The number of errors that occurred during the process', " .
					"`numAdded` INT(11) NOT NULL COMMENT 'The number of additions that occurred during the process', " .
					"`numUpdated` INT(11) NOT NULL COMMENT 'The number of items updated during the process', " .
					"`numDeleted` INT(11) NOT NULL COMMENT 'The number of items deleted during the process', " .
					"`numSkipped` INT(11) NOT NULL COMMENT 'The number of items skipped during the process', " .
					"`notes` TEXT COMMENT 'Additional information about the process', " .
					"PRIMARY KEY ( `id` ), INDEX ( `reindex_id` ), INDEX ( `processName` )" .
					") ENGINE = MYISAM;",
					"ALTER TABLE reindex_log ADD COLUMN `notes` TEXT COMMENT 'Notes related to the overall process'",
					"ALTER TABLE reindex_log ADD `lastUpdate` INT(11) COMMENT 'The last time the log was updated'",
					"ALTER TABLE reindex_log ADD COLUMN numWorksProcessed INT(11) NOT NULL DEFAULT 0",
					"ALTER TABLE reindex_log ADD COLUMN numListsProcessed INT(11) NOT NULL DEFAULT 0"
				],
			],


			'cronLog' => [
				'title'       => 'Cron Log table',
				'description' => 'Create Cron Log table to track reindexing.',
				'sql'         => [
					"CREATE TABLE IF NOT EXISTS cron_log(" .
					"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of the cron log', " .
					"`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the cron run started', " .
					"`endTime` INT(11) NULL COMMENT 'The timestamp when the cron run ended', " .
					"`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the cron run last updated (to check for stuck processes)', " .
					"`notes` TEXT COMMENT 'Additional information about the cron run', " .
					"PRIMARY KEY ( `id` )" .
					") ENGINE = MYISAM;",
					"CREATE TABLE IF NOT EXISTS cron_process_log(" .
					"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of cron process', " .
					"`cronId` INT(11) NOT NULL COMMENT 'The id of the cron run this process ran during', " .
					"`processName` VARCHAR(50) NOT NULL COMMENT 'The name of the process being run', " .
					"`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the process started', " .
					"`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the process last updated (to check for stuck processes)', " .
					"`endTime` INT(11) NULL COMMENT 'The timestamp when the process ended', " .
					"`numErrors` INT(11) NOT NULL DEFAULT 0 COMMENT 'The number of errors that occurred during the process', " .
					"`numUpdates` INT(11) NOT NULL DEFAULT 0 COMMENT 'The number of updates, additions, etc. that occurred', " .
					"`notes` TEXT COMMENT 'Additional information about the process', " .
					"PRIMARY KEY ( `id` ), INDEX ( `cronId` ), INDEX ( `processName` )" .
					") ENGINE = MYISAM;",

				],
			],

			'add_indexes'  => [
				'title'           => 'Add indexes',
				'description'     => 'Add indexes to tables that were not defined originally',
				'continueOnError' => true,
				'sql'             => [
					'ALTER TABLE `editorial_reviews` ADD INDEX `RecordId` ( `recordId` ) ',
					'ALTER TABLE `list_widget_lists` ADD INDEX `ListWidgetId` ( `listWidgetId` ) ',
					'ALTER TABLE `location` ADD INDEX `ValidHoldPickupBranch` ( `validHoldPickupBranch` ) ',
				],
			],

			'add_indexes2' => [
				'title'           => 'Add indexes 2',
				'description'     => 'Add additional indexes to tables that were not defined originally',
				'continueOnError' => true,
				'sql'             => [
					'ALTER TABLE `materials_request_status` ADD INDEX ( `isDefault` )',
					'ALTER TABLE `materials_request_status` ADD INDEX ( `isOpen` )',
					'ALTER TABLE `materials_request_status` ADD INDEX ( `isPatronCancel` )',
					'ALTER TABLE `materials_request` ADD INDEX ( `status` )'
				],
			],

			'spelling_optimization' => [
				'title'       => 'Spelling Optimization',
				'description' => 'Optimizations to spelling to ensure indexes are used',
				'sql'         => [
					'ALTER TABLE `spelling_words` ADD `soundex` VARCHAR(20) ',
					'ALTER TABLE `spelling_words` ADD INDEX `Soundex` (`soundex`)',
					'UPDATE `spelling_words` SET soundex = SOUNDEX(word) '
				],
			],


			'loan_rule_determiners_1' => [
				'title'       => 'Loan Rule Determiners',
				'description' => 'Build tables to store loan rule determiners',
				'sql'         => [
					"CREATE TABLE IF NOT EXISTS loan_rules (" .
					"`id` INT NOT NULL AUTO_INCREMENT, " .
					"`loanRuleId` INT NOT NULL COMMENT 'The location id', " .
					"`name` varchar(50) NOT NULL COMMENT 'The location code the rule applies to', " .
					"`code` char(1) NOT NULL COMMENT '', " .
					"`normalLoanPeriod` INT(4) NOT NULL COMMENT 'Number of days the item checks out for', " .
					"`holdable` TINYINT NOT NULL DEFAULT '0', " .
					"`bookable` TINYINT NOT NULL DEFAULT '0', " .
					"`homePickup` TINYINT NOT NULL DEFAULT '0', " .
					"`shippable` TINYINT NOT NULL DEFAULT '0', " .
					"PRIMARY KEY ( `id` ), " .
					"INDEX ( `loanRuleId` ), " .
					"INDEX (`holdable`) " .
					") ENGINE=InnoDB",
					"CREATE TABLE IF NOT EXISTS loan_rule_determiners (" .
					"`id` INT NOT NULL AUTO_INCREMENT, " .
					"`rowNumber` INT NOT NULL COMMENT 'The row of the determiner.  Rules are processed in reverse order', " .
					"`location` varchar(10) NOT NULL COMMENT '', " .
					"`patronType` VARCHAR(50) NOT NULL COMMENT 'The patron types that this rule applies to', " .
					"`itemType` VARCHAR(255) NOT NULL DEFAULT '0' COMMENT 'The item types that this rule applies to', " .
					"`ageRange` varchar(10) NOT NULL COMMENT '', " .
					"`loanRuleId` varchar(10) NOT NULL COMMENT 'Close hour (24hr format) HH:MM', " .
					"`active` TINYINT NOT NULL DEFAULT '0', " .
					"PRIMARY KEY ( `id` ), " .
					"INDEX ( `rowNumber` ), " .
					"INDEX (`active`) " .
					") ENGINE=InnoDB",
					"ALTER TABLE loan_rule_determiners CHANGE COLUMN patronType `patronType` VARCHAR(255) NOT NULL COMMENT 'The patron types that this rule applies to'",
				],
			],

			'ptype' => [
				'title'       => 'P-Type',
				'description' => 'Build tables to store information related to P-Types.',
				'sql'         => [
					'CREATE TABLE IF NOT EXISTS ptype(
							id INT(11) NOT NULL AUTO_INCREMENT,
							pType INT(11) NOT NULL,
							maxHolds INT(11) NOT NULL DEFAULT 300,
							UNIQUE KEY (pType),
							PRIMARY KEY (id)
						)',
					'ALTER TABLE `ptype` ADD COLUMN `masquerade` VARCHAR(45) NOT NULL DEFAULT \'none\' AFTER `maxHolds`;',
					'ALTER TABLE `ptype`  CHANGE COLUMN `pType` `pType` VARCHAR(20) NOT NULL ;',
					"ALTER TABLE ptype ADD COLUMN label VARCHAR(60) NULL",
				]
			],

			'add_staff_ptype_2021.01.0' => [
				'title'       => 'Add Staff P-Type',
				'description' => 'Add isStaffPType column to P-Types table.',
				'sql'         => [
					'ALTER TABLE `ptype` ADD COLUMN `isStaffPType` BOOLEAN NOT NULL DEFAULT false AFTER `maxHolds`;',
					'setStaffPtypes'
				]
			],

			'session_update_1' => [
				'title'       => 'Session Update 1',
				'description' => 'Add a field for whether or not the session was started with remember me on.',
				'sql'         => [
					"ALTER TABLE session ADD COLUMN `remember_me` TINYINT NOT NULL DEFAULT 0 COMMENT 'Whether or not the session was started with remember me on.'",
				],
			],

			'offline_holds' => [
				'title'       => 'Offline Holds',
				'description' => 'Stores information about holds that have been placed while the circulation system is offline',
				'sql'         => [
					"CREATE TABLE offline_hold (
							`id` INT(11) NOT NULL AUTO_INCREMENT,
							`timeEntered` INT(11) NOT NULL,
							`timeProcessed` INT(11) NULL,
							`bibId` VARCHAR(10) NOT NULL,
							`patronId` INT(11) NOT NULL,
							`patronBarcode` VARCHAR(20),
							`status` ENUM('Not Processed', 'Hold Succeeded', 'Hold Failed'),
							`notes` VARCHAR(512),
							INDEX(`timeEntered`),
							INDEX(`timeProcessed`),
							INDEX(`patronBarcode`),
							INDEX(`patronId`),
							INDEX(`bibId`),
							INDEX(`status`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM",
					"ALTER TABLE `offline_hold` CHANGE `patronId` `patronId` INT( 11 ) NULL",
					"ALTER TABLE `offline_hold` ADD COLUMN `patronName` VARCHAR( 200 ) NULL",
					"ALTER TABLE `offline_hold` ADD COLUMN `itemId` VARCHAR( 20 ) NULL",
				]
			],

			'offline_circulation' => [
				'title'       => 'Offline Circulation',
				'description' => 'Stores information about circulation activities done while the circulation system was offline',
				'sql'         => [
					"CREATE TABLE offline_circulation (
							`id` INT(11) NOT NULL AUTO_INCREMENT,
							`timeEntered` INT(11) NOT NULL,
							`timeProcessed` INT(11) NULL,
							`itemBarcode` VARCHAR(20) NOT NULL,
							`patronBarcode` VARCHAR(20),
							`patronId` INT(11) NULL,
							`login` VARCHAR(50),
							`loginPassword` VARCHAR(50),
							`initials` VARCHAR(50),
							`initialsPassword` VARCHAR(50),
							`type` ENUM('Check In', 'Check Out'),
							`status` ENUM('Not Processed', 'Processing Succeeded', 'Processing Failed'),
							`notes` VARCHAR(512),
							INDEX(`timeEntered`),
							INDEX(`patronBarcode`),
							INDEX(`patronId`),
							INDEX(`itemBarcode`),
							INDEX(`login`),
							INDEX(`initials`),
							INDEX(`type`),
							INDEX(`status`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM"
				]
			],

			'novelist_data' => [
				'title'       => 'Novelist Data',
				'description' => 'Stores basic information from Novelist for efficiency purposes.  We can\'t cache everything due to contract.',
				'sql'         => [
					"CREATE TABLE novelist_data (
							id INT(11) NOT NULL AUTO_INCREMENT,
							groupedRecordPermanentId VARCHAR(36),
							lastUpdate INT(11),
							hasNovelistData TINYINT(1),
							groupedRecordHasISBN TINYINT(1),
							primaryISBN VARCHAR(13),
							seriesTitle VARCHAR(255),
							seriesNote VARCHAR(255),
							volume VARCHAR(32),
							INDEX(`groupedRecordPermanentId`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM",
				],
			],

			'ils_marc_checksums' => [
				'title'       => 'ILS MARC Checksums',
				'description' => 'Add a table to store checksums of MARC records stored in the ILS so we can determine if the record needs to be updated during grouping.',
				'sql'         => [
					"CREATE TABLE IF NOT EXISTS ils_marc_checksums (
							id INT(11) NOT NULL AUTO_INCREMENT,
							ilsId VARCHAR(20) NOT NULL,
							checksum BIGINT(20) UNSIGNED NOT NULL,
							PRIMARY KEY (id),
							UNIQUE (ilsId)
						) ENGINE=MyISAM  DEFAULT CHARSET=utf8",
					"ALTER TABLE ils_marc_checksums ADD dateFirstDetected BIGINT UNSIGNED NULL",
					"ALTER TABLE ils_marc_checksums CHANGE dateFirstDetected dateFirstDetected BIGINT SIGNED NULL",
					"ALTER TABLE ils_marc_checksums ADD source VARCHAR(50) NOT NULL DEFAULT 'ils'",
					"ALTER TABLE ils_marc_checksums ADD UNIQUE (`source`, `ilsId`)",
				],
			],

			'Fix_ils_marc_checksums_indexes-2021.03.1' => [
				'title'       => 'Fix ILS MARC Checksums indexes',
				'description' => 'ilsId unique key needs to accommodate for source as well',
				'sql'         => [
					"ALTER TABLE `ils_marc_checksums` 
							DROP INDEX `ilsId` ,
							ADD INDEX `ilsId` (`ilsId` ASC),
							DROP INDEX `source` ,
							ADD UNIQUE INDEX `sourceAndIlsId` (`source` ASC, `ilsId` ASC); ",
				],
			],

			'browse_categories' => [
				'title'       => 'Browse Categories',
				'description' => 'Setup Browse Category Table',
				'sql'         => [
					"CREATE TABLE browse_category (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							textId VARCHAR(60) NOT NULL DEFAULT -1,
							userId INT(11),
							sharing ENUM('private', 'location', 'library', 'everyone') DEFAULT 'everyone',
							label VARCHAR(50) NOT NULL,
							description MEDIUMTEXT,
							catalogScoping ENUM('unscoped', 'library', 'location'),
							defaultFilter TEXT,
							defaultSort ENUM('relevance', 'popularity', 'newest_to_oldest', 'oldest_to_newest', 'author', 'title', 'user_rating'),
							UNIQUE (textId)
						) ENGINE = MYISAM",
					"ALTER TABLE browse_category ADD searchTerm VARCHAR(100) NOT NULL DEFAULT ''",
					"ALTER TABLE browse_category ADD numTimesShown MEDIUMINT NOT NULL DEFAULT 0",
					"ALTER TABLE browse_category ADD numTitlesClickedOn MEDIUMINT NOT NULL DEFAULT 0",
					"ALTER TABLE browse_category CHANGE searchTerm searchTerm VARCHAR(300) NOT NULL DEFAULT ''",
					"ALTER TABLE browse_category CHANGE searchTerm searchTerm VARCHAR(500) NOT NULL DEFAULT ''",
					"ALTER TABLE browse_category ADD sourceListId MEDIUMINT NULL DEFAULT NULL",
				],
			],

			'sub-browse_categories' => [
				'title'       => 'Enable Browse Sub-Categories',
				'description' => 'Add a the ability to define a browse category from a list',
				'sql'         => [
					"CREATE TABLE `browse_category_subcategories` (
							  `id` int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
							  `browseCategoryId` int(11) NOT NULL,
							  `subCategoryId` int(11) NOT NULL,
							  `weight` SMALLINT(2) UNSIGNED NOT NULL DEFAULT '0',
							  UNIQUE (`subCategoryId`,`browseCategoryId`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8"
				],
			],

			'localized_browse_categories' => [
				'title'       => 'Localized Browse Categories',
				'description' => 'Setup Localized Browse Category Tables',
				'sql'         => [
					"CREATE TABLE browse_category_library (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							libraryId INT(11) NOT NULL,
							browseCategoryTextId VARCHAR(60) NOT NULL DEFAULT -1,
							weight INT NOT NULL DEFAULT '0',
							UNIQUE (libraryId, browseCategoryTextId)
						) ENGINE = MYISAM",
					"CREATE TABLE browse_category_location (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							locationId INT(11) NOT NULL,
							browseCategoryTextId VARCHAR(60) NOT NULL DEFAULT -1,
							weight INT NOT NULL DEFAULT '0',
							UNIQUE (locationId, browseCategoryTextId)
						) ENGINE = MYISAM",
				],
			],


			'2022.03.0_BrowseCategorySortOption' => [
				'title'       => 'Match Browse Category default Sort Options to the Solr Sort Options',
				'description' => 'Match Browse Category Search Sort Options to any valid Solr Sort Options',
				'sql'         => [
					'ALTER TABLE `browse_category` CHANGE COLUMN `defaultSort` `defaultSort` VARCHAR(50) NULL DEFAULT NULL;',
					"UPDATE `browse_category` SET `defaultSort`='popularity desc' WHERE `defaultSort`='popularity';",
					"UPDATE `browse_category` SET `defaultSort`='days_since_added' WHERE `defaultSort`='newest_to_oldest'",
					"UPDATE `browse_category` SET `defaultSort`='user_rating' WHERE `defaultSort`='rating desc,title'",
				],
			],

			'remove_old_resource_tables' => [
				'title'       => 'Remove old Resource Tables',
				'description' => 'Remove old tables that were used for storing information based on resource',
				'sql'         => [
					"DROP TABLE IF EXISTS comments",
					"DROP TABLE IF EXISTS resource_tags",
					"DROP TABLE IF EXISTS user_resource",
					"DROP TABLE IF EXISTS resource",
				],
			],

			'authentication_profiles' => [
				'title'       => 'Setup Authentication Profiles',
				'description' => 'Setup authentication profiles to store information about how to authenticate',
				'sql'         => [
					"CREATE TABLE IF NOT EXISTS `account_profiles` (
						  `id` int(11) NOT NULL AUTO_INCREMENT,
						  `name` varchar(50) NOT NULL DEFAULT 'ils',
						  `driver` varchar(50) NOT NULL,
						  `loginConfiguration` enum('barcode_pin','name_barcode') NOT NULL,
						  `authenticationMethod` enum('ils','sip2','db','ldap') NOT NULL DEFAULT 'ils',
						  `vendorOpacUrl` varchar(100) NOT NULL,
						  `patronApiUrl` varchar(100) NOT NULL,
						  `recordSource` varchar(50) NOT NULL,
						  PRIMARY KEY (`id`),
						  UNIQUE KEY `name` (`name`)
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
					"ALTER TABLE `account_profiles` ADD `vendorOpacUrl` varchar(100) NOT NULL",
					"ALTER TABLE `account_profiles` ADD `patronApiUrl` varchar(100) NOT NULL",
					"ALTER TABLE `account_profiles` ADD `recordSource` varchar(50) NOT NULL",
					"ALTER TABLE `account_profiles` ADD `weight` int(11) NOT NULL",
				]
			],

			'add_search_source_to_saved_searches' => [
				'title'           => 'Store the Search Source with saved searches',
				'description'     => 'Add column to store the source for a search in the search table',
				'continueOnError' => true,
				'sql'             => [
					"ALTER TABLE `search` 
									ADD COLUMN `searchSource` VARCHAR(30) NOT NULL DEFAULT 'local' AFTER `search_object`;",
				]
			],

			'2021.03.0_remove_obsolete_column_searches' => [
				'title'           => 'Remove obsolete column from searches table',
				'description'     => 'Remove unused column folder_id from searches table',
				'continueOnError' => true,
				'sql'             => [
					"ALTER TABLE `search` DROP COLUMN `folder_id`, DROP INDEX `folder_id` ; ",
				]
			],

			'record_grouping_log' => [
				'title'           => 'Record Grouping Log',
				'description'     => 'Create Log for record grouping',
				'continueOnError' => false,
				'sql'             => [
					"CREATE TABLE IF NOT EXISTS record_grouping_log(
									`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of log', 
									`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the run started', 
									`endTime` INT(11) NULL COMMENT 'The timestamp when the run ended', 
									`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the run last updated (to check for stuck processes)', 
									`notes` TEXT COMMENT 'Additional information about the run includes stats per source', 
									PRIMARY KEY ( `id` )
									) ENGINE = MYISAM;",
				]
			],

			'create_pin_reset_table' => [
				'title'           => 'Create table for secure pin reset.',
				'description'     => 'Creates table for pin reset',
				'continueOnError' => true,
				'sql'             => [
					"CREATE TABLE IF NOT EXISTS pin_reset (
				    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				    userId VARCHAR(255),
				    selector CHAR(16),
				    token CHAR(64),
				    expires BIGINT(20)
						)",
				]
			],

			'remove_obsolete_tables-2020.01' => [
				'title'           => 'Delete Unused tables',
				'description'     => 'Get rid of unused tables',
				'continueOnError' => true,
				'sql'             => [
					"DROP TABLE IF EXISTS `marc_import`;",
					"DROP TABLE IF EXISTS `analytics_city`;",
					"DROP TABLE IF EXISTS `analytics_country`;",
					"DROP TABLE IF EXISTS `analytics_device`;",
					"DROP TABLE IF EXISTS `analytics_event`;",
					"DROP TABLE IF EXISTS `analytics_page_view`;",
					"DROP TABLE IF EXISTS `analytics_patron_type`;",
					"DROP TABLE IF EXISTS `analytics_physical_location`;",
					"DROP TABLE IF EXISTS `analytics_search`;",
					"DROP TABLE IF EXISTS `analytics_session`;",
					"DROP TABLE IF EXISTS `analytics_session_old`;",
					"DROP TABLE IF EXISTS `analytics_state`;",
					"DROP TABLE IF EXISTS `analytics_theme`;",
					"DROP TABLE IF EXISTS `millennium_cache`;",
					"DROP TABLE IF EXISTS `tag`;",
					"DROP TABLE IF EXISTS `book_store`;",
					"DROP TABLE IF EXISTS `circulation_status`;",
					"DROP TABLE IF EXISTS `evoke_record`;",
					"DROP TABLE IF EXISTS `external_link_tracking`;",
					"DROP TABLE IF EXISTS `nearby_book_store`;",
					"DROP TABLE IF EXISTS `non_holdable_locations`;",
					"DROP TABLE IF EXISTS `ptype_restricted_locations`;",
					"DROP TABLE IF EXISTS `purchase_link_tracking`;",
					"DROP TABLE IF EXISTS `purchaselinktracking_old`;",
					"DROP TABLE IF EXISTS `resource_callnumber`;",
					"DROP TABLE IF EXISTS `resource_subject`;",
					"DROP TABLE IF EXISTS `user_suggestions`;",
				]
			],

			'rename_editorial_reviews-2020.02' => [
				'title'           => 'Refactor Editorial Reviews name',
				'description'     => 'Rename Editorial Reviews to Librarian Reviews',
				'continueOnError' => false,
				'sql'             => [
					"UPDATE `roles` SET `description`='Allows entering of librarian reviews and creation of widgets.' WHERE `name`='contentEditor';",
					"ALTER TABLE `editorial_reviews` CHANGE COLUMN `editorialReviewId` `id` INT(11) NOT NULL , RENAME TO  `pika`.`librarian_reviews` ;",
					"UPDATE `library_more_details` SET `source` = 'librarianReviews' WHERE `source` = 'editorialReviews';",
				]
			],

			'librarian_reviews-2020.02' => [
				'title'           => 'Librarian Review id',
				'description'     => 'Librarian Review id needs auto increment',
				'continueOnError' => false,
				'sql'             => [
					"ALTER TABLE `librarian_reviews` CHANGE COLUMN `id` `id` INT(11) NOT NULL AUTO_INCREMENT ;",
				]
			],

			'remove_obsolete_tables-2020.05' => [
				'title'           => 'Delete Unused tables',
				'description'     => 'Get rid of unused tables',
				'continueOnError' => true,
				'sql'             => [
					"DROP TABLE IF EXISTS `library_search_source`;",
					"DROP TABLE IF EXISTS `location_search_source`;",
					"DROP TABLE IF EXISTS `syndetics_data`;",
					"DROP TABLE IF EXISTS `merged_records`;",
					"DROP TABLE IF EXISTS `search_stats`;",
				]
			],

			'add_custom_covers_table' => [
				'title'           => 'Add Custom Covers',
				'description'     => 'Database tables to support custom cover uploads',
				'continueOnError' => false,
				'sql'             => [
					"CREATE TABLE IF NOT EXISTS covers (
				    coverId INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				    cover VARCHAR(255)
				    )ENGINE=InnoDB  DEFAULT CHARSET=utf8;"
				]
			],

			'add_current_covers' => [
				'title'           => 'Add Current Covers to Database',
				'description'     => 'Looks in the covers directory and adds files found to database',
				'continueOnError' => false,
				'sql'             => [
					"INSERT INTO covers (cover) VALUES " .
					implode(",", createCoversFromDirectory()) . ";"
				]
			],
			'update-overdrive-logs-2020.07' => [
				'title'           => 'Add column to overdrive logs',
				'description'     => '[THIS NEEDS the econtent db to named econtent]',
				'continueOnError' => true,
				'sql'             => [
					'ALTER TABLE `econtent`.`overdrive_extract_log` ENGINE = InnoDB ,
								ADD COLUMN `numTitlesProcessed` INT UNSIGNED NULL DEFAULT NULL AFTER `numMetadataChanges`;',
					'ALTER TABLE `econtent`.`overdrive_api_products` ENGINE = InnoDB ;',
				]
			],

			'overdrive-remove-numeric-formats-2020.07' => [
				'title'           => 'Remove obsolete numeric formats column from overdrive_api_product_formats',
				'description'     => '[THIS NEEDS the econtent db to named econtent]',
				'continueOnError' => true,
				'sql'             => [
					'ALTER TABLE `econtent`.`overdrive_api_product_formats` DROP COLUMN `numericId`, DROP INDEX `numericId` ;',
				]
			],
			'2022.01.0-add_edition_column_to_overdrive_metadata' => [
				'title'       => 'Add edition column to OverDrive Metadata table',
				'description' => 'Add edition column to OverDrive Metadata table. [THIS NEEDS the econtent db to named econtent]',
				'sql'         => [
					"ALTER TABLE `econtent`.`overdrive_api_product_metadata` ADD COLUMN `edition` VARCHAR(128) NULL AFTER `publishDate`;",
				],
			],

			'add-pickup-branch-to-offline-holds-2021.01' => [
				'title'           => 'Add pickup branch to offline holds table',
				'description'     => '',
				'continueOnError' => true,
				'sql'             => [
					'ALTER TABLE `offline_hold` ADD COLUMN `pickupLocation` VARCHAR(5) NULL AFTER `itemId`;',
				]
			],
			'add_board_book_format' => [
				'title'       => 'Add new board book format',
				'description' => 'Add Board Book format to translation maps',
				'sql'         => [
					"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
						((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
						'BoardBook', 'book')
						,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
						'BoardBook', 'Board Book')
						,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
						'BoardBook', 'Books')
						,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
						'BoardBook', '10')"
					,
				]
			],
			'add_fine_display_option' => [
				'title'       =>'Add Fines Display Amount Option to Library',
				'description' => 'Add option in Library ECommerce to display badges only above set amount',
				'sql'         => [
					"ALTER TABLE `library` ADD COLUMN `fineAlertAmount` FLOAT(11) NOT NULL DEFAULT '0.00' AFTER `minimumFineAmount`"
				]
			],
			'add_OverDrive_Magazine_Issues_table' => [
				'title'       => 'Add OverDrive Magazine Issues to database',
				'description' => 'Add a table to the econtent database in which to store OverDrive Magazines. [THIS NEEDS the econtent db to named econtent]',
				'sql'         => [
					"CREATE TABLE IF NOT EXISTS `econtent`.`overdrive_api_magazine_issues`(
    						id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    						overdriveId varchar(50),
    						crossRefId varchar (50),
    					  title varchar(255),
    						edition varchar(100),
    						pubDate INT(11),
    						coverUrl varchar(200),
    						parentId varchar(50),
    						description TEXT, 
    						dateAdded INT(11),
    						dateUpdated INT(11)
						)
						CHARACTER SET = utf8 ;"
					,
				]
			],
			'add_Index_to_OverDrive_Issues' =>[
				'title'       =>'Add Index to Magazine Issues table',
				'description' => 'Index parentId Column.  [THIS NEEDS the econtent db to named econtent]',
				'sql'         => [
					"ALTER TABLE `econtent`.`overdrive_api_magazine_issues` ADD INDEX `parentId` (`parentId` ASC);"
				]
			],


		] // End of main array
	);


	function createDefaultIpRanges(){
		require_once ROOT_DIR . '/sys/Network/ipcalc.php';
		require_once ROOT_DIR . '/sys/Network/subnet.php';
		$subnet = new subnet();
		$subnet->find();
		while ($subnet->fetch()){
			$subnet->update();
		}
	}


	function setStaffPtypes(){
		global $configArray;
		if (!empty($configArray['Staff P-Types'])){
			foreach ($configArray['Staff P-Types'] as $pTypeNumber => $label){
				$pType        = new PType();
				$pType->pType = $pTypeNumber;
				if ($pType->find(true)){
					$pType->isStaffPType = true;
					$pType->label        = $label;
					$pType->update();
				}else{
					$pType->isStaffPType = true;
					$pType->label        = $label;
					$pType->insert();
				}
			}
		}
	}


	function createCoversFromDirectory(){
		global $configArray;
		$storagePath = $configArray['Site']['coverPath'];
		$files       = [];
		if ($handle = opendir($storagePath . DIR_SEP . "original")){
			while (false !== ($entry = readdir($handle))){
				if ($entry != "." && $entry != ".."){
					$value = '(\'' . htmlentities($entry, ENT_QUOTES) . '\')';
					array_push($files, $value);
				}
			}

			closedir($handle);

		}
		return $files;
	}


}
