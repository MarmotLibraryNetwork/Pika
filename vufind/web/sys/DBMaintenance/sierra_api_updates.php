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
 * Updates related to sierra api implementation for cleanliness
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 7/29/14
 * Time: 2:25 PM
 */

function getSierraAPIUpdates(){
	return array(
		'sierra_exportLog' => array(
			'title'       => 'Sierra API export log',
			'description' => 'Create log for sierra export via api.',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS sierra_api_export_log(
									`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of log', 
									`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the run started', 
									`endTime` INT(11) NULL COMMENT 'The timestamp when the run ended', 
									`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the run last updated (to check for stuck processes)', 
									`notes` TEXT COMMENT 'Additional information about the run', 
									PRIMARY KEY ( `id` )
									) ENGINE = INNODB;",
			)
		),

		'sierra_exportLog_stats' => array(
			'title'       => 'Sierra API export log stats',
			'description' => 'Add stats to sierra export via api log.',
			'sql'         => array(
				"ALTER TABLE sierra_api_export_log ADD COLUMN numRecordsToProcess INT(11)",
				"ALTER TABLE sierra_api_export_log ADD COLUMN numRecordsProcessed INT(11)",
				"ALTER TABLE sierra_api_export_log ADD COLUMN numErrors INT(11)",
				"ALTER TABLE sierra_api_export_log ADD COLUMN numRemainingRecords INT(11)",
			)
		),

		'sierra_export_field_mapping' => array(
			'title'       => 'Sierra API export field mapping',
			'description' => 'Setup field mappings for sierra export via api.',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS sierra_export_field_mapping(
											`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of field mapping', 
											`indexingProfileId` INT(11) NOT NULL COMMENT 'The indexing profile this field mapping is associated with',
											`bcode3DestinationField` CHAR(3) NOT NULL COMMENT 'The field to place bcode3 into', 
											`bcode3DestinationSubfield` CHAR(1) NULL COMMENT 'The subfield to place bcode3 into', 
											PRIMARY KEY ( `id` )
											) ENGINE = INNODB;",
			)
		),

		'sierra_export_field_mapping_item_fields' => array(
			'title'       => 'Sierra API export item field mapping',
			'description' => 'Add item export information for sierra export.',
			'sql'         => array(
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN callNumberExportFieldTag CHAR(1)",
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN callNumberPrestampExportSubfield CHAR(1)",
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN callNumberExportSubfield CHAR(1)",
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN callNumberCutterExportSubfield CHAR(1)",
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN callNumberPoststampExportSubfield CHAR(5)",
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN volumeExportFieldTag CHAR(1)",
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN urlExportFieldTag CHAR(1)",
				"ALTER TABLE sierra_export_field_mapping ADD COLUMN eContentExportFieldTag CHAR(1)",
			)
		),
	);
}
