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
 * Updates related to islandora for cleanliness
 *
 */

function getIslandoraUpdates(){
	return [
		'islandora_driver_cache' => [
			'title'       => 'Islandora Driver Caching',
			'description' => 'Caching for Islandora to store information about the driver to use',
			'sql'         => [
				"CREATE TABLE islandora_object_cache (
								id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
								pid VARCHAR (100) NOT NULL,
								driverName VARCHAR(25) NOT NULL,
								driverPath VARCHAR(100) NOT NULL,
								UNIQUE(pid)
							) ENGINE = INNODB",
				"ALTER TABLE islandora_object_cache ADD COLUMN title VARCHAR (255)",
				"ALTER TABLE islandora_object_cache ADD COLUMN hasLatLong TINYINT DEFAULT NULL",
				"ALTER TABLE islandora_object_cache ADD COLUMN latitude FLOAT DEFAULT NULL",
				"ALTER TABLE islandora_object_cache ADD COLUMN longitude FLOAT DEFAULT NULL",
				"ALTER TABLE islandora_object_cache ADD COLUMN lastUpdate INT(11) DEFAULT 0",
				"ALTER TABLE islandora_object_cache ADD COLUMN smallCoverUrl VARCHAR (255) DEFAULT ''",
				"ALTER TABLE islandora_object_cache ADD COLUMN mediumCoverUrl VARCHAR (255) DEFAULT ''",
				"ALTER TABLE islandora_object_cache ADD COLUMN largeCoverUrl VARCHAR (255) DEFAULT ''",
				"ALTER TABLE islandora_object_cache ADD COLUMN originalCoverUrl VARCHAR (255) DEFAULT ''",
			],
		],

		'islandora_samePika_cache' => [
			'title'       => 'Islandora Same Pika Cache ',
			'description' => 'Caching for Islandora same pika link to limit the times we need to load data',
			'sql'         => [
				"CREATE TABLE islandora_samepika_cache (
									id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
									groupedWorkId CHAR(36) NOT NULL,
									pid VARCHAR(100),
									archiveLink VARCHAR(255),
									UNIQUE (groupedWorkId)
									) ENGINE = INNODB",
			],
		],


		'archive_private_collections' => [
			'title'           => 'Archive Private Collections',
			'description'     => 'Create a table to store information about collections that should be private to the owning library',
			'continueOnError' => true,
			'sql'             => [
				"CREATE TABLE IF NOT EXISTS archive_private_collections (
									  `id` int(11) NOT NULL AUTO_INCREMENT,
									  privateCollections MEDIUMTEXT,
									  PRIMARY KEY (`id`)
									) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
			]
		],

		'archive_subjects' => [
			'title'           => 'Archive Subjects',
			'description'     => 'Create a table to store information about what subjects should be ignored and restricted',
			'continueOnError' => true,
			'sql'             => [
				"CREATE TABLE IF NOT EXISTS archive_subjects (
									  `id` int(11) NOT NULL AUTO_INCREMENT,
									  subjectsToIgnore MEDIUMTEXT,
									  subjectsToRestrict MEDIUMTEXT,
									  PRIMARY KEY (`id`)
									) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
			]
		],

		'archive_requests' => [
			'title'           => 'Archive Requests',
			'description'     => 'Create a table to store information about the requests for copies of archive information',
			'continueOnError' => true,
			'sql'             => [
				"CREATE TABLE IF NOT EXISTS archive_requests (
									  `id` int(11) NOT NULL AUTO_INCREMENT,
									  name VARCHAR(100) NOT NULL,
									  address VARCHAR(200),
									  address2 VARCHAR(200),
									  city VARCHAR(200),
									  state VARCHAR(200),
									  zip VARCHAR(12),
									  country VARCHAR(50),
									  phone VARCHAR(20),
									  alternatePhone VARCHAR(20),
									  email VARCHAR(100),
									  format MEDIUMTEXT,
									  purpose MEDIUMTEXT,
									  pid VARCHAR(50),
									  dateRequested INT(11),
									  PRIMARY KEY (`id`),
									  INDEX(`pid`),
									  INDEX(`name`)
									) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
			]
		],

		'claim_authorship_requests' => [
			'title'           => 'Claim Authorship Requests',
			'description'     => 'Create a table to store information about the people who are claiming authorship of archive information',
			'continueOnError' => true,
			'sql'             => [
				"CREATE TABLE IF NOT EXISTS claim_authorship_requests (
									  `id` int(11) NOT NULL AUTO_INCREMENT,
									  name VARCHAR(100) NOT NULL,
									  phone VARCHAR(20),
									  email VARCHAR(100),
									  message MEDIUMTEXT,
									  pid VARCHAR(50),
									  dateRequested INT(11),
									  PRIMARY KEY (`id`),
									  INDEX(`pid`),
									  INDEX(`name`)
									) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
			]
		],

	];
}
