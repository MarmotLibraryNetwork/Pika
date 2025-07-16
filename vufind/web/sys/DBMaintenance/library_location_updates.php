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
			'title'           => 'Delete selfReg template option',
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

		'2024.04.0_add_archive_only_interface_setting_to_library' => [
			'release'         => '2024.04.0',
			'title'           => 'Add archive only interface to Library settings',
			'description'     => 'Add an option to library settings to enable an archive only view',
			'continueOnError' => false,
			'sql'             => [
				'ALTER TABLE library ADD COLUMN `archiveOnlyInterface` TINYINT(1) DEFAULT 0;'
			]
		],
		'2024.04.0_add_partner_library_setting' => [
			'release'         => '2024.04.0',
			'title'           => 'Add partner setting to library interface',
			'description'     => 'Add an option to library settings to link an archive only partner interface to the parent library',
			'continueOnError' => false,
			'sql'             => [
				'ALTER TABLE library ADD COLUMN `partnerOfSystem` INT(11)  DEFAULT NULL;'
			]
		],

		'2025.01.0_add_newspaper_subscription_url' => [
			'release'         => '2025.01.0',
			'title'           => 'Add Newspaper subscription URLs to library settings',
			'description'     => 'Allow patron authentication for libraries\' newspaper subscriptions',
			'continueOnError' => false,
			'sql'             => [
				'ALTER TABLE `library` ADD COLUMN `nytimesUrl` VARCHAR(128) NULL ',
				'ALTER TABLE `library` ADD COLUMN `wpUrl` VARCHAR(128) NULL ',
				'ALTER TABLE `library` ADD COLUMN `wsjUrl` VARCHAR(128) NULL ',
			]
		],

		'2025.02.0_update_showPatronBarcodeImage' => [
			'release'         => '2025.02.0',
			'title'           => 'Add Patron Barcodes encoding style options',
			'description'     => 'Switch to enum column',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE `library` DROP COLUMN IF EXISTS `showPatronBarcodeImage`; ",
				"ALTER TABLE `library` ADD COLUMN `showPatronBarcodeImage` ENUM('none', 'codabar', 'code39', 'code39mod43', 'code39mod10') NOT NULL DEFAULT 'none' AFTER `maxBarcodeLength`;",
			]
		],

		'2025.03.0_add_hoopla_min_price' => [
			'release'         => '2025.03.0',
			'title'           => 'Add Hoopla Setting for a minimum price',
			'description'     => 'Add Hoopla Setting for a minimum price',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE `library_hoopla_setting` ADD COLUMN `minPrice` DECIMAL(3,2)NULL DEFAULT 0.00 AFTER `kind`;",
				"ALTER TABLE `location_hoopla_setting` ADD COLUMN `minPrice` DECIMAL(3,2)NULL DEFAULT 0.00 AFTER `kind`;",
			]
		],

	];
}
