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


function getOverDriveUpdates(): array{
	global $configArray;
	return [
		'overdrive_api_data' => [
			'title'       => 'OverDrive API Data',
			'description' => 'Build tables to store data loaded from the OverDrive API so the reindex process can use cached data and so we can add additional logic for lastupdate time, etc.',
			'sql'         => [
				"CREATE TABLE IF NOT EXISTS overdrive_api_products (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						overdriveId VARCHAR(36) NOT NULL,
						mediaType  VARCHAR(50) NOT NULL,
						title VARCHAR(512) NOT NULL,
						series VARCHAR(215),
						primaryCreatorRole VARCHAR(50),
						primaryCreatorName VARCHAR(215),
						cover VARCHAR(215),
						dateAdded INT(11),
						dateUpdated INT(11),
						lastMetadataCheck INT(11),
						lastMetadataChange INT(11),
	          lastAvailabilityCheck INT(11),
						lastAvailabilityChange INT(11),
						deleted TINYINT(1) DEFAULT 0,
						dateDeleted INT(11) DEFAULT NULL,
						UNIQUE(overdriveId),
						INDEX(dateUpdated),
						INDEX(lastMetadataCheck),
						INDEX(lastAvailabilityCheck),
	                    INDEX(deleted)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_formats (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						productId INT,
						textId VARCHAR(25),
						name VARCHAR(512),
						fileName  VARCHAR(215),
						fileSize INT,
						partCount TINYINT,
	          sampleSource_1 VARCHAR(215),
	          sampleUrl_1 VARCHAR(215),
	          sampleSource_2 VARCHAR(215),
	          sampleUrl_2 VARCHAR(215),
						INDEX(productId),
						UNIQUE(productId, textId)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_metadata (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						productId INT,
						checksum BIGINT,
						sortTitle VARCHAR(512),
						publisher VARCHAR(215),
						publishDate INT(11),
						isPublicDomain TINYINT(1),
						isPublicPerformanceAllowed TINYINT(1),
						shortDescription TEXT,
						fullDescription TEXT,
						starRating FLOAT,
						popularity INT,
						UNIQUE(productId)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_creators (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						productId INT,
						role VARCHAR(50),
						name VARCHAR(215),
						fileAs VARCHAR(215),
						INDEX (productId)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_identifiers (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						productId INT,
						type VARCHAR(50),
						value VARCHAR(75),
						INDEX (productId),
	                    INDEX (type)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_languages (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						code VARCHAR(10),
						name VARCHAR(50),
						INDEX (code)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_languages_ref (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						productId INT,
						languageId INT,
						UNIQUE (productId, languageId)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_subjects (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						name VARCHAR(512),
						INDEX(name)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_subjects_ref (
						productId INT,
						subjectId INT,
						UNIQUE (productId, subjectId)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_api_product_availability (
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						productId INT,
						libraryId INT,
						available TINYINT(1),
						copiesOwned INT,
						copiesAvailable INT,
						numberOfHolds INT,
						INDEX (productId),
						INDEX (libraryId),
						UNIQUE(productId, libraryId)
					)",
				"CREATE TABLE IF NOT EXISTS overdrive_extract_log(
						`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						`startTime` INT(11),
						`endTime` INT(11),
						`lastUpdate` INT(11),
						numProducts INT(11) DEFAULT 0,
						numErrors INT(11) DEFAULT 0,
						numAdded INT(11) DEFAULT 0,
						numDeleted INT(11) DEFAULT 0,
	                    numUpdated INT(11) DEFAULT 0,
	                    numSkipped INT(11) DEFAULT 0,
						numAvailabilityChanges INT(11) DEFAULT 0,
						numMetadataChanges INT(11) DEFAULT 0,
						`notes` TEXT
					)",
			]
		],

		'overdrive_api_data_update_1' => [
			'title'       => 'OverDrive API Data Update 1',
			'description' => 'Update MetaData tables to store thumbnail, cover, and raw metadata.  Also update product to store raw metadata',
			'sql'         => [
				"ALTER TABLE overdrive_api_products ADD COLUMN rawData MEDIUMTEXT",
				"ALTER TABLE overdrive_api_product_metadata ADD COLUMN rawData MEDIUMTEXT",
				"ALTER TABLE overdrive_api_product_metadata ADD COLUMN thumbnail VARCHAR(255)",
				"ALTER TABLE overdrive_api_product_metadata ADD COLUMN cover VARCHAR(255)",
			],
		],

		'overdrive_api_data_update_2' => [
			'title'       => 'OverDrive API Data Update 2',
			'description' => 'Update Product table to add subtitle',
			'sql'         => [
				"ALTER TABLE overdrive_api_products ADD COLUMN subtitle VARCHAR(255)",
			],
		],

		'overdrive_api_data_availability_type' => [
			'title'       => 'Add availability type to OverDrive API',
			'description' => 'Update Availability table to add availability type',
			'sql'         => [
				"ALTER TABLE overdrive_api_product_availability ADD COLUMN availabilityType VARCHAR(35) DEFAULT 'Normal'",
			],
		],

		'overdrive_api_data_metadata_isOwnedByCollections' => [
			'title'       => 'Add isOwnedByCollections to OverDrive Metadata API',
			'description' => 'Update isOwnedByCollections table to add metadata table',
			'sql'         => [
				"ALTER TABLE overdrive_api_product_metadata ADD COLUMN isOwnedByCollections TINYINT(1) DEFAULT '1'",
			],
		],

		'overdrive_api_data_needsUpdate' => [
			'title'       => 'Add needsUpdate to OverDrive Product API',
			'description' => 'Update overdrive_api_product table to add needsUpdate to determine if the record should be reloaded from the API',
			'sql'         => [
				"ALTER TABLE overdrive_api_products ADD COLUMN needsUpdate TINYINT(1) DEFAULT '0'",
			],
		],

		'overdrive_api_data_crossRefId' => [
			'title'       => 'Add crossRefId to OverDrive Product API',
			'description' => 'Update overdrive_api_product table to add crossRefId to allow quering of product data ',
			'sql'         => [
				"ALTER TABLE overdrive_api_products ADD COLUMN crossRefId INT(11) DEFAULT '0'",
			],
		],

		'utf8_update' => [
			'title'        => 'Update to UTF-8',
			'description'  => 'Update database to use UTF-8 encoding',
			'dependencies' => [],
			'sql'          => [
				"ALTER DATABASE " . $configArray['Database']['database_econtent_dbname'] . " DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;",
				"ALTER TABLE db_update CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
			],
		],

		'update-overdrive-logs-2020.07' => [
			'title'           => 'Add column to overdrive logs',
			'description'     => '',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE ' . $configArray['Database']['database_econtent_dbname'] . '.`overdrive_extract_log` ENGINE = InnoDB ,
								ADD COLUMN `numTitlesProcessed` INT UNSIGNED NULL DEFAULT NULL AFTER `numMetadataChanges`;',
				'ALTER TABLE ' . $configArray['Database']['database_econtent_dbname'] . '.`overdrive_api_products` ENGINE = InnoDB ;',
			]
		],

		'overdrive-remove-numeric-formats-2020.07'           => [
			'title'           => 'Remove obsolete numeric formats column from overdrive_api_product_formats',
			'description'     => '[THIS NEEDS the econtent db to named econtent]',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE ' . $configArray['Database']['database_econtent_dbname'] . '.`overdrive_api_product_formats` DROP COLUMN `numericId`, DROP INDEX `numericId` ;',
			]
		],
		'2022.01.0-add_edition_column_to_overdrive_metadata' => [
			'title'       => 'Add edition column to OverDrive Metadata table',
			'description' => 'Add edition column to OverDrive Metadata table.',
			'sql'         => [
				'ALTER TABLE ' . $configArray['Database']['database_econtent_dbname'] . '.`overdrive_api_product_metadata` ADD COLUMN `edition` VARCHAR(128) NULL AFTER `publishDate`;',
			],
		],

		'add_OverDrive_Magazine_Issues_table' => [
			'title'       => 'Add OverDrive Magazine Issues to database',
			'description' => 'Add a table to the eContent database in which to store OverDrive Magazines.',
			'sql'         => [
				'CREATE TABLE IF NOT EXISTS ' . $configArray['Database']['database_econtent_dbname'] . '`overdrive_api_magazine_issues`(
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
						CHARACTER SET = utf8 ;',
			]
		],

		'add_Index_to_OverDrive_Issues' => [
			'title'       => 'Add Index to Magazine Issues table',
			'description' => 'Index parentId Column. ',
			'sql'         => [
				"ALTER TABLE " . $configArray['Database']['database_econtent_dbname'] . ".`overdrive_api_magazine_issues` ADD INDEX `parentId` (`parentId` ASC);"
			]
		],

	];
}