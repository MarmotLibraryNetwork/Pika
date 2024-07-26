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
 * Updates related to user tables for cleanliness
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/29/14
 * Time: 2:42 PM
 */

function getUserUpdates(){
	return [
		'user_display_name' => [
			'title'       => 'User display name',
			'description' => 'Add displayName field to User table to allow users to have aliases',
			'sql'         => [
				"ALTER TABLE user ADD displayName VARCHAR( 30 ) NOT NULL DEFAULT ''",
				"ALTER TABLE user ADD phone VARCHAR( 30 ) NOT NULL DEFAULT ''",
				"ALTER TABLE user ADD patronType VARCHAR( 30 ) NOT NULL DEFAULT ''",
			],
		],

		'recommendations_optOut' => [
			'title'       => 'Recommendations Opt Out',
			'description' => 'Add tracking for whether the user wants to opt out of recommendations',
			'sql'         => [
				"ALTER TABLE `user` ADD `disableRecommendations` TINYINT NOT NULL DEFAULT '0'",
			],
		],

		'coverArt_suppress' => [
			'title'       => 'Cover Art Suppress',
			'description' => 'Add tracking for whether the user wants to suppress cover art',
			'sql'         => [
				"ALTER TABLE `user` ADD `disableCoverArt` TINYINT NOT NULL DEFAULT '0'",
			],
		],

		'user_overdrive_email' => [
			'title'           => 'User OverDrive Email',
			'description'     => 'Add overDriveEmail field to User table to allow for patrons to use a different email fo notifications when their books are ready',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE user ADD overDriveEmail VARCHAR( 250 ) NOT NULL DEFAULT ''",
				"ALTER TABLE user ADD promptForOverDriveEmail TINYINT DEFAULT 1",
				"UPDATE user SET overDriveEmail = email"
			],
		],

		'user_preferred_library_interface' => [
			'title'           => 'User Preferred Library Interface',
			'description'     => 'Add preferred library interface to ',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE user ADD preferredLibraryInterface INT(11) DEFAULT NULL",
			],
		],

		'user_track_reading_history' => [
			'title'           => 'User Track Reading History',
			'description'     => 'Add Track Reading History ',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE user ADD trackReadingHistory TINYINT DEFAULT 0",
				"ALTER TABLE user ADD initialReadingHistoryLoaded TINYINT DEFAULT 0",
			],
		],

		'2023.01_reading_history_last_updated_time' => [
			'title'           => 'User Reading History Last Update Time',
			'description'     => 'Track when cron process updates user\'s reading history',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE user ADD readingHistoryLastUpdated INT UNSIGNED NULL AFTER initialReadingHistoryLoaded",
			],
		],

		'user_preference_review_prompt' => [
			'title'           => 'User Preference Prompt for Reviews',
			'description'     => 'Users may opt out of doing a review after giving a rating permanently',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE `user` ADD `noPromptForUserReviews` TINYINT(1) DEFAULT 0",
			],
		],

		'user_account' => [
			'title'       => 'User Account Source',
			'description' => 'Store the source of a user account so we can accommodate multiple ilses',
			'sql'         => [
				"ALTER TABLE `user` ADD `source` VARCHAR(50) DEFAULT 'ils'",
				"ALTER TABLE `user` DROP INDEX `username`",
				"ALTER TABLE `user` ADD UNIQUE username(`source`, `username`)",
			],
		],

		'user_linking' => [
			'title'       => 'Setup linking of user accounts',
			'description' => 'Setup linking of user accounts.  This is a one way link.',
			'sql'         => [
				"CREATE TABLE IF NOT EXISTS `user_link` (
					`id` INT(11) NOT NULL AUTO_INCREMENT,
					`primaryAccountId` INT(11),
					`linkedAccountId` INT(11),
					PRIMARY KEY (`id`),
					UNIQUE KEY `user_link` (`primaryAccountId`, `linkedAccountId`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
				"ALTER TABLE `user_link` 
				CHANGE COLUMN `primaryAccountId` `primaryAccountId` INT(11) NOT NULL,
				CHANGE COLUMN `linkedAccountId` `linkedAccountId` INT(11) NOT NULL;",
			],
		],

		'user_link_blocking' => [
			'title'       => 'Setup blocking controls for the linking of user accounts',
			'description' => 'Setup for the blocking of linking user accounts. Either an account can not link to any account, or a specific account can link to a specific account.',
			'sql'         => [
				"CREATE TABLE `user_link_blocks` (
					`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					`primaryAccountId` INT UNSIGNED NOT NULL,
					`blockedLinkAccountId` INT UNSIGNED NULL COMMENT 'A specific account primaryAccountId will not be linked to.',
					`blockLinking` TINYINT UNSIGNED NULL COMMENT 'Indicates primaryAccountId will not be linked to any other accounts.',
					PRIMARY KEY (`id`))
					ENGINE = InnoDB
					DEFAULT CHARACTER SET = utf8;"
			],
		],

		'user_reading_history_index_source_id' => [
			'title'       => 'Index source Id in user reading history',
			'description' => 'Index source Id in user reading history',
			'sql'         => [
				"ALTER TABLE user_reading_history_work ADD INDEX sourceId(sourceId)"
			],
		],

		// NOT Added: See D-3389 & D-3348
		//			'user_reading_history_ils_reading_history_id' => array(
		//				'title'       => 'The history entry Id in the ILS',
		//				'description' => 'The history entry Id in the ILS which may be needed to get more information from the ILS',
		//				'sql'         => array(
		//					"ALTER TABLE `user_reading_history_work` ADD COLUMN `ilsReadingHistoryId` VARCHAR(50) NULL AFTER `sourceId`;"
		//				),
		//			),

		'user_hoopla_confirmation_checkout' => [
			'title'       => 'Hoopla Checkout Confirmation Prompt',
			'description' => 'Stores user preference whether or not to prompt for confirmation before checking out a title from Hoopla',
			'sql'         => [
				"ALTER TABLE `user` ADD COLUMN `hooplaCheckOutConfirmation` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1;"
			],
		],

		'user_table_cleanup' => [
			'title'           => 'Clean up user table',
			'description'     => 'Remove obsolete columns',
			'continueOnError' => true,
			'sql'             => [
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
			],
		],

		'remove_obsolete_rating_table_2020.01' => [
			'title'           => 'Remove obsolete  user rating table',
			'description'     => 'Remove obsolete  user rating table',
			'continueOnError' => true,
			'sql'             => [
				'DROP TABLE IF EXISTS `user_rating`;'
			],
		],

		'use_ilsUserId_2020.02' => [
			'title'           => 'Implement ilsUserId column',
			'description'     => 'Implement ilsUserId column',
			'continueOnError' => true,
			'sql'             => [
				'UPDATE `user` SET `ilsUserId` = username;',
				'ALTER TABLE `user` DROP INDEX `username`, DROP COLUMN `username`',
			],
		],

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

		'cat_password_update_2021.03'     => [
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
				'ALTER TABLE user ADD COLUMN barcode VARCHAR(64) NULL AFTER  ilsUserId, ADD COLUMN password VARCHAR(128) NULL AFTER barcode;',
			],
		],
		'remove_cat_password_2022.02'     => [
			'title'           => 'Remove cat_password column',
			'description'     => 'Remove cat_password column',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE user DROP COLUMN cat_password',
			],
		],
		'2023.01_lastPasswordSetTime'     => [
			'title'           => 'Add last password set time column',
			'description'     => '',
			'continueOnError' => true,
			'sql'             => [
				'ALTER TABLE `user` ADD COLUMN `lastPasswordSetTime` DATETIME NULL AFTER `password`;',
			],
		],
		'readingHistory_work' => [
			'title'       => 'Reading History For Grouped Works',
			'description' => 'Update reading History to remove resources and work with works',
			'sql'         => [
				"CREATE TABLE IF NOT EXISTS	user_reading_history_work(
						id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						userId INT NOT NULL COMMENT 'The id of the user who checked out the item',
						groupedWorkPermanentId CHAR(36) NOT NULL,
						source VARCHAR(25) NOT NULL COMMENT 'The source of the record being checked out',
						sourceId VARCHAR(50) NOT NULL COMMENT 'The id of the item that item that was checked out within the source',
						title VARCHAR(150) NULL COMMENT 'The title of the item in case this is ever deleted',
						author VARCHAR(75) NULL COMMENT 'The author of the item in case this is ever deleted',
						format VARCHAR(50) NULL COMMENT 'The format of the item in case this is ever deleted',
						checkOutDate INT NOT NULL COMMENT 'The first day we detected that the item was checked out to the patron',
						checkInDate INT NULL COMMENT 'The last day we detected that the item was checked out to the patron.',
						INDEX ( userId, checkOutDate ),
						INDEX ( userId, checkInDate ),
						INDEX ( userId, title ),
						INDEX ( userId, author )
						) ENGINE = INNODB DEFAULT CHARSET=utf8 COMMENT = 'The reading history for patrons';",
				"DROP TABLE user_reading_history"
			],
		],

		'readingHistory_deletion' => [
			'title'       => 'Update Reading History Deletion so we mark it as deleted rather than permanently deleting',
			'description' => 'Update Reading History to handle deletions',
			'sql'         => [
				"ALTER TABLE user_reading_history_work ADD `deleted` TINYINT NOT NULL DEFAULT '0'"
			],
		],


		'notInterested' => [
			'title'       => 'Not Interested Table',
			'description' => 'Create a table for records the user is not interested in so they can be ommitted from search results',
			'sql'         => [
				"CREATE TABLE `user_not_interested` (
							id INT(11) NOT NULL AUTO_INCREMENT,
							userId INT(11) NOT NULL,
							resourceId VARCHAR(20) NOT NULL,
							dateMarked INT(11),
							PRIMARY KEY (id),
							UNIQUE INDEX (userId, resourceId),
							INDEX (userId)
						)",
			],
		],

		'notInterestedWorks' => [
			'title'           => 'Not Interested Table Works Update',
			'description'     => 'Update Not Interested Table to Link to Works',
			'continueOnError' => true,
			'sql'             => [
				"TRUNCATE TABLE `user_not_interested`",
				"ALTER TABLE `user_not_interested` ADD COLUMN groupedRecordPermanentId VARCHAR(36)",
				"ALTER TABLE `user_not_interested` DROP resourceId",
				"ALTER TABLE user_not_interested DROP INDEX userId",
				"ALTER TABLE user_not_interested DROP INDEX userId_2",
				"ALTER TABLE user_not_interested ADD INDEX(`userId`)",
			],
		],

		'work_level_ratings' => [
			'title'       => 'Work Level Ratings',
			'description' => 'Stores user ratings at the work level rather than the individual record.',
			'sql'         => [
				"CREATE TABLE user_work_review (
							id INT(11) NOT NULL AUTO_INCREMENT,
							groupedRecordPermanentId VARCHAR(36),
							userId INT(11),
							rating TINYINT(1),
							review MEDIUMTEXT,
							dateRated INT(11),
							INDEX(`groupedRecordPermanentId`),
							INDEX(`userId`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM",
			],
		],

		'work_level_tagging' => [
			'title'       => 'Work Level Tagging',
			'description' => 'Stores tags at the work level rather than the individual record.',
			'sql'         => [
				"CREATE TABLE user_tags (
							id INT(11) NOT NULL AUTO_INCREMENT,
							groupedRecordPermanentId VARCHAR(36),
							userId INT(11),
							tag VARCHAR(50),
							dateTagged INT(11),
							INDEX(`groupedRecordPermanentId`),
							INDEX(`userId`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM",
			],
		],

		'user_list_entry' => [
			'title'       => 'User List Entry (Grouped Work)',
			'description' => 'Add grouped works to lists rather than resources.',
			'sql'         => [
				"CREATE TABLE user_list_entry (
							id INT(11) NOT NULL AUTO_INCREMENT,
							groupedWorkPermanentId VARCHAR(36),
							listId INT(11),
							notes MEDIUMTEXT,
							dateAdded INT(11),
							weight INT(11),
							INDEX(`groupedWorkPermanentId`),
							INDEX(`listId`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM",
			],
		],

		'user_list_indexing' => [
			'title'       => 'Update User List to make indexing easier',
			'description' => 'Add date updated and deleted to the table so we can easily do partial indexes of the data.',
			'sql'         => [
				"ALTER TABLE user_list ADD dateUpdated INT(11)",
				"ALTER TABLE user_list ADD deleted TINYINT(1) DEFAULT 0",
				"ALTER TABLE user_list DROP created",
				"ALTER TABLE user_list ADD created INT(11)",
				"ALTER TABLE `user_list` ADD `defaultSort` VARCHAR(20)",
			]
		],

		'2022.02.0_encryptUserPasswords' => [
			'title'             =>  'Encrypt patron passwords',
			'description'       =>  'Encrypt patron passwords',
			'continueOnError'   => false,
			'sql'               => [
				"ALTER TABLE user CHANGE COLUMN password password VARCHAR(255) NULL DEFAULT NULL;",
				'encryptUserPasswords']
		],

		'splitLargeLists' => [
			'title'             =>  'Split Large Lists',
			'description'       =>  'Split Lists Over 2000 items into separate lists',
			'continueOnError'   => false,
			'sql'               => ['splitLargeLists']
		],

	];


	function encryptUserPasswords() {
		set_time_limit(6000);
		$logger = new Logger(__CLASS__);
		#$sql = "SELECT id, password FROM user WHERE char_length(password) < 56";
		$user = new User();
		$user->whereAdd("char_length(password) < 56");
		$user->find();

		while ($user->fetch()) {
			try {

				$password = $user->password;
				$result   = $user->updatePassword($password);
				if (!$result){
					$logger->error($user->_lastError);
					continue;
				}

			} catch (Exception $e) {
				$logger->error($user->_lastError);
				continue;
			}
		}
		//return true;
	}
	function splitLargeLists(){

		$logger = new Logger('List Splitter');

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';

		$allList = new UserListEntry;

		$allList->selectAdd();
		$allList->selectAdd('listId, count(id) as num');
		$allList->groupBy('listId');
		$allList->having('count(id)>2000');
		$allLists   = $allList->fetchAll();
		$i          = 1;
		$largeLists = [];
		foreach ($allLists as $aList){
			$largeLists[$i] = ['id' => $aList->listId, 'num' => $aList->num];
			$i++;
		}
		foreach ($largeLists as $largeList){

			$listId = $largeList['id'];

			$bigList     = new UserList();
			$bigList->id = $listId;
			if ($bigList->find(true)){ //find check, because list entries may belong to a list that has been deleted
				$n                 = 2;                  #list iteration
				$o                 = 2000;               #max list size
				$x                 = $largeList['num'];  #large list size
				$y                 = 1 + $o;             #recordOffset
				$listItems         = new UserListEntry;
				$listItems->listId = $listId;
				$allItems          = $listItems->fetchAll();
				while ($y <= $x){

					$newList              = new UserList();
					$newList->user_id     = $bigList->user_id;
					$newList->title       = $bigList->title . " " . $n;
					$newList->public      = $bigList->public;
					$newList->description = $bigList->description . " Your existing list " . $bigList->title . " has been separated into separate lists because it contained over 2000 entries. A single user list cannot have more than 2000 titles. ";
					$newList->defaultSort = $bigList->defaultSort;
					$newListId            = $newList->insert();
					$logger->info('List Splitting Original list Id: ' . $listId . ', items: ' . $largeList['num'] . ' New List id: ' . $newListId);
					while ($y >= $o * ($n - 1) && ($y <= ($o * $n) && $y <= $x)){
						if (!empty($allItems[$y]->listId)){
							$allItems[$y]->listId = $newListId;
							$allItems[$y]->update();
						}

						$y++;
					}

					$n++;
				}
			} else {
				$logger->error("List $listId had more than 2000 entries but was not found in the UserList table.", $largeList);
			}
		}

	}

}
