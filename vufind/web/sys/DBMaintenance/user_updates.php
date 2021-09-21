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
 * Updates related to user tables for cleanliness
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/29/14
 * Time: 2:42 PM
 */

function getUserUpdates(){
	return array(
		'user_display_name' => array(
			'title'       => 'User display name',
			'description' => 'Add displayName field to User table to allow users to have aliases',
			'sql'         => array(
				"ALTER TABLE user ADD displayName VARCHAR( 30 ) NOT NULL DEFAULT ''",
				"ALTER TABLE user ADD phone VARCHAR( 30 ) NOT NULL DEFAULT ''",
				"ALTER TABLE user ADD patronType VARCHAR( 30 ) NOT NULL DEFAULT ''",
			),
		),

		'recommendations_optOut' => array(
			'title'       => 'Recommendations Opt Out',
			'description' => 'Add tracking for whether the user wants to opt out of recommendations',
			'sql'         => array(
				"ALTER TABLE `user` ADD `disableRecommendations` TINYINT NOT NULL DEFAULT '0'",
			),
		),

		'coverArt_suppress' => array(
			'title'       => 'Cover Art Suppress',
			'description' => 'Add tracking for whether the user wants to suppress cover art',
			'sql'         => array(
				"ALTER TABLE `user` ADD `disableCoverArt` TINYINT NOT NULL DEFAULT '0'",
			),
		),

		'user_overdrive_email' => array(
			'title'           => 'User OverDrive Email',
			'description'     => 'Add overDriveEmail field to User table to allow for patrons to use a different email fo notifications when their books are ready',
			'continueOnError' => true,
			'sql'             => array(
				"ALTER TABLE user ADD overDriveEmail VARCHAR( 250 ) NOT NULL DEFAULT ''",
				"ALTER TABLE user ADD promptForOverDriveEmail TINYINT DEFAULT 1",
				"UPDATE user SET overDriveEmail = email"
			),
		),

		'user_preferred_library_interface' => array(
			'title'           => 'User Preferred Library Interface',
			'description'     => 'Add preferred library interface to ',
			'continueOnError' => true,
			'sql'             => array(
				"ALTER TABLE user ADD preferredLibraryInterface INT(11) DEFAULT NULL",
			),
		),

		'user_track_reading_history' => array(
			'title'           => 'User Track Reading History',
			'description'     => 'Add Track Reading History ',
			'continueOnError' => true,
			'sql'             => array(
				"ALTER TABLE user ADD trackReadingHistory TINYINT DEFAULT 0",
				"ALTER TABLE user ADD initialReadingHistoryLoaded TINYINT DEFAULT 0",
			),
		),

		'user_preference_review_prompt' => array(
			'title'           => 'User Preference Prompt for Reviews',
			'description'     => 'Users may opt out of doing a review after giving a rating permanently',
			'continueOnError' => true,
			'sql'             => array(
				"ALTER TABLE `user` ADD `noPromptForUserReviews` TINYINT(1) DEFAULT 0",
			),
		),

		'user_account' => array(
			'title'       => 'User Account Source',
			'description' => 'Store the source of a user account so we can accommodate multiple ilses',
			'sql'         => array(
				"ALTER TABLE `user` ADD `source` VARCHAR(50) DEFAULT 'ils'",
				"ALTER TABLE `user` DROP INDEX `username`",
				"ALTER TABLE `user` ADD UNIQUE username(`source`, `username`)",
			),
		),

		'user_linking' => array(
			'title'       => 'Setup linking of user accounts',
			'description' => 'Setup linking of user accounts.  This is a one way link.',
			'sql'         => array(
				"CREATE TABLE IF NOT EXISTS `user_link` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`primaryAccountId` int(11),
					`linkedAccountId` int(11),
					PRIMARY KEY (`id`),
					UNIQUE KEY `user_link` (`primaryAccountId`, `linkedAccountId`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
				"ALTER TABLE `user_link` 
				CHANGE COLUMN `primaryAccountId` `primaryAccountId` INT(11) NOT NULL,
				CHANGE COLUMN `linkedAccountId` `linkedAccountId` INT(11) NOT NULL;",
			),
		),

		'user_link_blocking' => array(
			'title'       => 'Setup blocking controls for the linking of user accounts',
			'description' => 'Setup for the blocking of linking user accounts. Either an account can not link to any account, or a specific account can link to a specific account.',
			'sql'         => array(
				"CREATE TABLE `user_link_blocks` (
					`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					`primaryAccountId` INT UNSIGNED NOT NULL,
					`blockedLinkAccountId` INT UNSIGNED NULL COMMENT 'A specific account primaryAccountId will not be linked to.',
					`blockLinking` TINYINT UNSIGNED NULL COMMENT 'Indicates primaryAccountId will not be linked to any other accounts.',
					PRIMARY KEY (`id`))
					ENGINE = InnoDB
					DEFAULT CHARACTER SET = utf8;"
			),
		),

		'user_reading_history_index_source_id' => array(
			'title'       => 'Index source Id in user reading history',
			'description' => 'Index source Id in user reading history',
			'sql'         => array(
				"ALTER TABLE user_reading_history_work ADD INDEX sourceId(sourceId)"
			),
		),

		// NOT Added: See D-3389 & D-3348
		//			'user_reading_history_ils_reading_history_id' => array(
		//				'title'       => 'The history entry Id in the ILS',
		//				'description' => 'The history entry Id in the ILS which may be needed to get more information from the ILS',
		//				'sql'         => array(
		//					"ALTER TABLE `user_reading_history_work` ADD COLUMN `ilsReadingHistoryId` VARCHAR(50) NULL AFTER `sourceId`;"
		//				),
		//			),

		'user_hoopla_confirmation_checkout' => array(
			'title'       => 'Hoopla Checkout Confirmation Prompt',
			'description' => 'Stores user preference whether or not to prompt for confirmation before checking out a title from Hoopla',
			'sql'         => array(
				"ALTER TABLE `user` ADD COLUMN `hooplaCheckOutConfirmation` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1;"
			),
		),

		'user_table_cleanup' => array(
			'title'           => 'Clean up user table',
			'description'     => 'Remove obsolete columns',
			'continueOnError' => true,
			'sql'             => array(
				'ALTER TABLE `user` CHANGE COLUMN `created` `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ;',
				// The above is needed to be compatible with the mariadb, so that the changes below can happen.
				'ALTER TABLE `user` DROP COLUMN `major`, DROP COLUMN `college`, DROP COLUMN `password`, '
				. 'ADD COLUMN `ilsUserId` VARCHAR(30) NULL AFTER `username`, '
				. 'CHANGE COLUMN `displayName` `displayName` VARCHAR(30) NOT NULL DEFAULT \'\' AFTER `ilsUserId`, '
				. 'CHANGE COLUMN `source` `source` VARCHAR(50) NULL DEFAULT \'ils\' AFTER `username`, '
				. 'ADD COLUMN `homeLibraryId` INT NULL AFTER `created`;',

				'DELETE FROM `roles` WHERE `name`="epubAdmin";',
				'UPDATE `user` SET `ilsUserId` = username;',
				'UPDATE `user`, `location` SET `homeLibraryId` = location.libraryId WHERE user.homeLocationId = location.locationId'
			),
		),

		'remove_obsolete_rating_table_2020.01' => array(
			'title'           => 'Remove obsolete  user rating table',
			'description'     => 'Remove obsolete  user rating table',
			'continueOnError' => true,
			'sql'             => array(
				'DROP TABLE IF EXISTS `user_rating`;'
			),
		),

		'use_ilsUserId_2020.02' => array(
			'title'           => 'Implement ilsUserId column',
			'description'     => 'Implement ilsUserId column',
			'continueOnError' => true,
			'sql'             => array(
				'UPDATE `user` SET `ilsUserId` = username;',
				'ALTER TABLE `user` DROP INDEX `username`, DROP COLUMN `username`',
			),
		),

		'overdrive_user_settings_2020.07' => [
			'title'           => 'Add prompt for OverDrive lending period setting',
			'description'     => 'Update Pika\'s OverDrive settings',
			'continueOnError' => false,
			'sql'             => [
				'DELETE FROM `user` WHERE `id`=\'1\' AND `ilsUserId` = \'pika\';', //Remove the beginner user (It has an invalid created datetime and prevents the other sql from working)
				'ALTER TABLE `user` CHANGE COLUMN `promptForOverdriveEmail` `promptForOverDriveEmail` TINYINT(1) UNSIGNED NULL DEFAULT \'1\','
					. 'ADD COLUMN `promptForOverDriveLendingPeriods` TINYINT(1) UNSIGNED NOT NULL DEFAULT \'1\' AFTER `promptForOverDriveEmail`,'
					. 'CHANGE COLUMN `overdriveEmail` `overDriveEmail` VARCHAR(250) NULL DEFAULT NULL ;',
			],
		],

		'cat_password_update_2021.03' => [
			'title'           => 'Update cat password to 64 chars',
			'description'     => 'Update cat password to 64 chars',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE user CHANGE COLUMN `cat_password` `cat_password` VARCHAR(64) NULL DEFAULT NULL ',
			],
		],
		'create_barcode_password_2021.04' => [
			'title'           => 'Create barcode and password columns',
			'description'     => 'Create barcode and password columns',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE user ADD COLUMN barcode VARCHAR(64) NULL AFTER  ilsUserId, ADD COLUMN password VARCHAR(64) NULL AFTER barcode;',
			],
		],
	);
}
