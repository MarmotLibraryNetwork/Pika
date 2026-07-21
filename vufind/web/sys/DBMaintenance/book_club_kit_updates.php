<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * Updates related to the Colorado State Book Club Kit request form
 *
 * */
function getBookClubKitUpdates() {

	return [
		'2026.03.0_add_book_club_kit_contact_email' => [
			'release'         => '2026.03.0',
			'title'           => 'Add Book Club Kit contact email to Library',
			'description'     => 'Add a library setting for the email address that Colorado State Book Club Kit requests should be sent to',
			'continueOnError' => false,
			'sql'             => [
				'ALTER TABLE library ADD COLUMN `bookClubKitContactEmail` VARCHAR(150) DEFAULT NULL;'
			]
		],

		'2026.03.0_add_book_club_kit_requests_table' => [
			'release'         => '2026.03.0',
			'title'           => 'Add Book Club Kit requests table',
			'description'     => 'Add a table to store patron requests for Colorado State Book Club Kits',
			'continueOnError' => false,
			'sql'             => [
				'CREATE TABLE `book_club_kit_requests` (`id` INT(11) NOT NULL AUTO_INCREMENT, `libraryId` INT(11) NOT NULL, `userId` INT(11) NOT NULL, `barcode` VARCHAR(20) NOT NULL, `name` VARCHAR(120) NOT NULL, `email` VARCHAR(120) NOT NULL, `recordId` VARCHAR(120) NOT NULL, `title` VARCHAR(255) NOT NULL, `dateCreated` INT(11) NOT NULL, PRIMARY KEY(`id`), KEY (`libraryId`))'
			]
		],

		'2026.03.0_add_book_club_kit_admin_role' => [
			'release'         => '2026.03.0',
			'title'           => 'Add Book Club Kit admin role',
			'description'     => 'Add a role to allow staff to view Book Club Kit requests for their library without granting full library admin access',
			'continueOnError' => false,
			'sql'             => [
				'INSERT INTO `roles` (`name`, `description`) VALUES (\'bookClubKitAdmin\', \'Allows user to view Book Club Kit requests for their library.\');'
			]
		],

	];
}
