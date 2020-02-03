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
 * Updates related to islandora for cleanliness
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 7/29/14
 * Time: 2:25 PM
 */

function getIslandoraUpdates(){
	return array(
		'islandora_driver_cache' => array(
			'title'       => 'Islandora Driver Caching',
			'description' => 'Caching for Islandora to store information about the driver to use',
			'sql'         => array(
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
			),
		),

		'islandora_samePika_cache' => array(
			'title'       => 'Islandora Same Pika Cache ',
			'description' => 'Caching for Islandora same pika link to limit the times we need to load data',
			'sql'         => array(
				"CREATE TABLE islandora_samepika_cache (
									id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
									groupedWorkId CHAR(36) NOT NULL,
									pid VARCHAR(100),
									archiveLink VARCHAR(255),
									UNIQUE (groupedWorkId)
									) ENGINE = INNODB",
			),
		),
	);
}
