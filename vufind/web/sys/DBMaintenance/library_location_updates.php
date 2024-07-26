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
 * Updates related to library & location configuration
 *
 */

function getLibraryLocationUpdates(): array{

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
		'2024.03.0_remove_selfReg_template_option' => [
			'release'         => '2024.03.0',
			'title'           => 'Delete  selfReg template option',
			'description'     => 'Get rid of the template library setting used for opac self reg screen scraping',
			'continueOnError' => false,
			'sql'             => [
				"ALTER TABLE `library` DROP COLUMN `selfRegistrationTemplate`;",
			]
		],

		'2024.03.0_rename_and_repurpose_location_field_scope' => [
			'release'         => '2024.03.0',
			'title'           => 'Change Location "scope" to "ilsLocationId"',
			'description'     => 'Repurpose this field to be used for the Polaris Organization Id.',
			'continueOnError' => false,
			'sql'             => [
				'ALTER TABLE `location` CHANGE `scope` `ilsLocationId` SMALLINT UNSIGNED DEFAULT NULL COMMENT "The ID for the location in the ILS. ";'
			]
		],

	];
}
