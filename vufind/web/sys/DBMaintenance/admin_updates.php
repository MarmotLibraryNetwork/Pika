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

/*
 * Updates to Admin pages
 *
 * */
function getAdminUpdates() {

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
		'2024.04.0_add_library_partner_role' => [
			'release'         => '2024.04.0',
			'title'           => 'Add library partner role',
			'description'     => 'Add a role to allow library admin to modify partner interfaces',
			'continueOnError' => false,
			'sql'             => [
				'INSERT INTO `roles` (`name`, `description`) VALUES (\'partnerAdmin\', \'Allows user to update the library configuration for a partner system of their home library.\');'
			]
		],

		'2024.04.1_materials_request_staff_comments' => [
			'release'         => '2024.04.1',
			'title'           => 'Add Materials Request staff comments',
			'description'     => 'Add a field for internal comments not seen by the patron.',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `materials_request` ADD COLUMN `staffComments` LONGTEXT NULL DEFAULT NULL AFTER `assignedTo`; '
			]
		],

		'2025.01.0_offline_circ_statGroup' => [
			'release'         => '2025.01.0',
			'title'           => 'Add Stat Group to Offline Circ table',
			'description'     => 'Add Sierra statGroup column so that offline checkouts can populate sierra stat groups.',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `offline_circulation` ADD COLUMN `statGroup` VARCHAR(5) NULL DEFAULT NULL AFTER `loginPassword`;'
			]
		],
	];
}


// Functions definitions that get executed by any of the updates above