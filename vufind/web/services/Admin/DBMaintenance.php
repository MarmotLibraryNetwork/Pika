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

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/DBMaintenance/DatabaseUpdates.php';

use \Pika\Logger;
/**
 * Provides a method of running SQL updates to the database.
 * Shows a list of updates that are available with a description of the updates
 */
class DBMaintenance extends Admin_Admin {

	/** @var DB $db */
	protected $db;

	const TITLE = 'Database Maintenance';

	public function __construct(){
		parent::__construct();
		$temp = new DatabaseUpdates();
		$this->db = $temp->getDatabaseConnection();
		if (PEAR::isError($this->db)){
			die($this->db->getMessage());
		}
	}

	function launch(){
		global $interface;

		//Create updates table if one doesn't exist already
//		$this->createUpdatesTable();  //Generally the table exists

		$availableUpdates = $this->getSQLUpdates();

		if (isset($_REQUEST['submit'])){
			$interface->assign('showStatus', true);

			//Process the updates
			foreach ($availableUpdates as $key => $update){
				if (isset($_REQUEST["selected"][$key])){
					$sqlStatements = $update['sql'];
					$updateOk      = true;
					foreach ($sqlStatements as $sql){
						if (method_exists($this, $sql)){
							$this->$sql($update);
						}else{
							if (!$this->runSQLStatement($update, $sql)){
								break;
							}
						}
					}
					if ($updateOk){
						$this->markUpdateAsRun($key);
					}
					$availableUpdates[$key] = $update;
				}
			}
		}

		//Check to see which updates have already been performed.
		$this->checkWhichUpdatesHaveRun($availableUpdates);
		$interface->assign('sqlUpdates', $availableUpdates);

		$this->display('dbMaintenance.tpl', self::TITLE);
	}


	protected function checkWhichUpdatesHaveRun(&$availableUpdates){
		foreach ($availableUpdates as $key => &$update){
			$update['alreadyRun'] = false;
			$dbUpdate             = new DatabaseUpdates();
			$dbUpdate->update_key = $key;
			if ($dbUpdate->find()){
				$update['alreadyRun'] = true;
			}
		}
	}

	protected function markUpdateAsRun($update_key){
		$dbUpdate = new DatabaseUpdates();
		if ($dbUpdate->get($update_key)){
			$dbUpdate->date_run = time();
			$dbUpdate->update();
		}else{
			$dbUpdate->update_key = $update_key;
//			$dbUpdate->date_run = time(); // table should auto-set this column
			$dbUpdate->insert();
		}
	}

	function getAllowableRoles(){
		return array('userAdmin', 'opacAdmin');
	}

	protected function runSQLStatement(&$update, $sql){
		set_time_limit(500);
		$result   = $this->db->query($sql);
		$updateOk = true;
		if (empty($result)){ // got an error
			if (!empty($update['continueOnError'])){
				if (!isset($update['status'])){
					$update['status'] = '';
				}
				$update['status'] .= 'Warning: ' . $this->db->error() . "<br>";
			}else{
				$update['status'] = 'Update failed ' . $this->db->error();
				$updateOk         = false;
			}
		}else{
			if (!isset($update['status'])){
				$update['status'] = 'Update succeeded';
			}
		}
		return $updateOk;
	}

	protected function createUpdatesTable(){
		$tableFound = false;
		//Check to see if the updates table exists
		/** @var DB_result $result */
		$result =& $this->db->query('SHOW TABLES');
		if ($result){
			while ($row =& $result->fetchRow()){
				if ($row[0] == 'db_update'){
					$tableFound = true;
					break;
				}
			}
		}
		if (!$tableFound){
			//Create the table to mark which updates have been run.
			$this->db->query("CREATE TABLE db_update (" .
				"update_key VARCHAR( 100 ) NOT NULL PRIMARY KEY ," .
				"date_run TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP" .
				") ENGINE = InnoDB");
		}
	}

	protected function getSQLUpdates(){
		global $configArray;

		require_once ROOT_DIR . '/sys/DBMaintenance/library_location_updates.php';
		$library_location_updates = getLibraryLocationUpdates();
		require_once ROOT_DIR . '/sys/DBMaintenance/grouped_work_updates.php';
		$grouped_work_updates = getGroupedWorkUpdates();
		require_once ROOT_DIR . '/sys/DBMaintenance/user_updates.php';
		$user_updates = getUserUpdates();
		require_once ROOT_DIR . '/sys/DBMaintenance/list_widget_updates.php';
		$list_widget_updates = getListWidgetUpdates();
		require_once ROOT_DIR . '/sys/DBMaintenance/indexing_updates.php';
		$indexing_updates = getIndexingUpdates();
		require_once ROOT_DIR . '/sys/DBMaintenance/islandora_updates.php';
		$islandora_updates = getIslandoraUpdates();
		require_once ROOT_DIR . '/sys/DBMaintenance/hoopla_updates.php';
		$hoopla_updates = getHooplaUpdates();
		require_once ROOT_DIR . '/sys/DBMaintenance/sierra_api_updates.php';
		$sierra_api_updates = getSierraAPIUpdates();

		return array_merge(
			$library_location_updates,
			$user_updates,
			$grouped_work_updates,
			$list_widget_updates,
			$indexing_updates,
			$islandora_updates,
			$hoopla_updates,
			$sierra_api_updates,
			array(
				'new_search_stats' => array(
					'title'       => 'Create new search stats table with better performance',
					'description' => 'Create an optimized table for performing auto completes based on prior searches',
					'sql'         => array(
						"CREATE TABLE `search_stats_new` (
						  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'The unique id of the search statistic',
						  `phrase` varchar(500) NOT NULL COMMENT 'The phrase being searched for',
						  `lastSearch` int(16) NOT NULL COMMENT 'The last time this search was done',
						  `numSearches` int(16) NOT NULL COMMENT 'The number of times this search has been done.',
						  PRIMARY KEY (`id`),
						  KEY `numSearches` (`numSearches`),
						  KEY `lastSearch` (`lastSearch`),
						  KEY `phrase` (`phrase`),
						  FULLTEXT `phrase_text` (`phrase`)
						) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='Statistical information about searches for use in reporting '",
						"INSERT INTO search_stats_new (phrase, lastSearch, numSearches) SELECT TRIM(REPLACE(phrase, char(9), '')) as phrase, MAX(lastSearch), sum(numSearches) FROM search_stats WHERE numResults > 0 GROUP BY TRIM(REPLACE(phrase,char(9), ''))",
						"DELETE FROM search_stats_new WHERE phrase LIKE '%(%'",
						"DELETE FROM search_stats_new WHERE phrase LIKE '%)%'",
					),
				),


				'genealogy' => array(
					'title'           => 'Genealogy Setup',
					'description'     => 'Initial setup of genealogy information',
					'continueOnError' => true,
					'sql'             => array(
						//-- setup tables related to the genealogy section
						//-- person table
						"CREATE TABLE `person` (
						`personId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
						`firstName` VARCHAR( 100 ) NULL ,
						`middleName` VARCHAR( 100 ) NULL ,
						`lastName` VARCHAR( 100 ) NULL ,
						`maidenName` VARCHAR( 100 ) NULL ,
						`otherName` VARCHAR( 100 ) NULL ,
						`nickName` VARCHAR( 100 ) NULL ,
						`birthDate` DATE NULL ,
						`birthDateDay` INT NULL COMMENT 'The day of the month the person was born empty or null if not known',
						`birthDateMonth` INT NULL COMMENT 'The month the person was born, null or blank if not known',
						`birthDateYear` INT NULL COMMENT 'The year the person was born, null or blank if not known',
						`deathDate` DATE NULL ,
						`deathDateDay` INT NULL COMMENT 'The day of the month the person died empty or null if not known',
						`deathDateMonth` INT NULL COMMENT 'The month the person died, null or blank if not known',
						`deathDateYear` INT NULL COMMENT 'The year the person died, null or blank if not known',
						`ageAtDeath` TEXT NULL ,
						`cemeteryName` VARCHAR( 255 ) NULL ,
						`cemeteryLocation` VARCHAR( 255 ) NULL ,
						`mortuaryName` VARCHAR( 255 ) NULL ,
						`comments` MEDIUMTEXT NULL,
						`picture` VARCHAR( 255 ) NULL
						) ENGINE = MYISAM COMMENT = 'Stores information about a particular person for use in genealogy';",

						//-- marriage table
						"CREATE TABLE `marriage` (
						`marriageId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
						`personId` INT NOT NULL COMMENT 'A link to one person in the marriage',
						`spouseName` VARCHAR( 200 ) NULL COMMENT 'The name of the other person in the marriage if they aren''t in the database',
						`spouseId` INT NULL COMMENT 'A link to the second person in the marriage if the person is in the database',
						`marriageDate` DATE NULL COMMENT 'The date of the marriage if known.',
						`marriageDateDay` INT NULL COMMENT 'The day of the month the marriage occurred empty or null if not known',
						`marriageDateMonth` INT NULL COMMENT 'The month the marriage occurred, null or blank if not known',
						`marriageDateYear` INT NULL COMMENT 'The year the marriage occurred, null or blank if not known',
						`comments` MEDIUMTEXT NULL
						) ENGINE = MYISAM COMMENT = 'Information about a marriage between two people';",


						//-- obituary table
						"CREATE TABLE `obituary` (
						`obituaryId` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
						`personId` INT NOT NULL COMMENT 'The person this obituary is for',
						`source` VARCHAR( 255 ) NULL ,
						`date` DATE NULL ,
						`dateDay` INT NULL COMMENT 'The day of the month the obituary came out empty or null if not known',
						`dateMonth` INT NULL COMMENT 'The month the obituary came out, null or blank if not known',
						`dateYear` INT NULL COMMENT 'The year the obituary came out, null or blank if not known',
						`sourcePage` VARCHAR( 25 ) NULL ,
						`contents` MEDIUMTEXT NULL ,
						`picture` VARCHAR( 255 ) NULL
						) ENGINE = MYISAM	COMMENT = 'Information about an obituary for a person';",
					),
				),

				'genealogy_1' => array(
					'title'       => 'Genealogy Update 1',
					'description' => 'Update Genealogy 1 for Steamboat Springs to add cemetery information.',
					'sql'         => array(
						"ALTER TABLE person ADD COLUMN veteranOf VARCHAR(100) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN addition VARCHAR(100) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN block VARCHAR(100) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN lot INT(11) NULL",
						"ALTER TABLE person ADD COLUMN grave INT(11) NULL",
						"ALTER TABLE person ADD COLUMN tombstoneInscription TEXT",
						"ALTER TABLE person ADD COLUMN addedBy INT(11) NOT NULL DEFAULT -1",
						"ALTER TABLE person ADD COLUMN dateAdded INT(11) NULL",
						"ALTER TABLE person ADD COLUMN modifiedBy INT(11) NOT NULL DEFAULT -1",
						"ALTER TABLE person ADD COLUMN lastModified INT(11) NULL",
						"ALTER TABLE person ADD COLUMN privateComments TEXT",
						"ALTER TABLE person ADD COLUMN importedFrom VARCHAR(50) NULL",
						"ALTER TABLE person ADD COLUMN ledgerVolume VARCHAR(20) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN ledgerYear VARCHAR(20) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN ledgerEntry VARCHAR(20) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN sex VARCHAR(20) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN race VARCHAR(20) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN residence VARCHAR(255) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN causeOfDeath VARCHAR(255) NULL DEFAULT ''",
						"ALTER TABLE person ADD COLUMN cemeteryAvenue VARCHAR(255) NULL DEFAULT ''",
						"ALTER TABLE person CHANGE lot lot VARCHAR(20) NULL DEFAULT ''",
					),
				),

				'editorial_review' => array(
					'title'       => 'Create Editorial Review table',
					'description' => 'Create editorial review tables for external reviews, i.e. book-a-day blog',
					'sql'         => array(
						"CREATE TABLE editorial_reviews (" .
						"editorialReviewId int NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
						"recordId VARCHAR(50) NOT NULL, " .
						"title VARCHAR(255) NOT NULL, " .
						"pubDate BIGINT NOT NULL, " .
						"review TEXT, " .
						"source VARCHAR(50) NOT NULL" .
						")",
					),
				),
				'editorial_review_update_2020_01' => array(
					'title'       => 'Update Editorial Review table',
					'description' => 'use grouped workIds and use timestamp column',
					'sql'         => array(
						"ALTER TABLE editorial_reviews CHANGE COLUMN `recordId` `groupedWorkPermanentId` CHAR(36) NOT NULL ;",
						"ALTER TABLE editorial_reviews DROP COLUMN `tabName` ;",
						"ALTER TABLE editorial_reviews DROP COLUMN `teaser` ;",
						"ALTER TABLE editorial_reviews DROP COLUMN `pubDate` ;",
						"ALTER TABLE editorial_reviews ADD COLUMN  `pubDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ;",
					),
				),

				'readingHistory_work' => array(
					'title'       => 'Reading History For Grouped Works',
					'description' => 'Update reading History to remove resources and work with works',
					'sql'         => array(
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
					),
				),

				'readingHistory_deletion' => array(
					'title'       => 'Update Reading History Deletion so we mark it as deleted rather than permanently deleting',
					'description' => 'Update Reading History to handle deletions',
					'sql'         => array(
						"ALTER TABLE user_reading_history_work ADD `deleted` TINYINT NOT NULL DEFAULT '0'"
					),
				),


				'notInterested' => array(
					'title'       => 'Not Interested Table',
					'description' => 'Create a table for records the user is not interested in so they can be ommitted from search results',
					'sql'         => array(
						"CREATE TABLE `user_not_interested` (
							id INT(11) NOT NULL AUTO_INCREMENT,
							userId INT(11) NOT NULL,
							resourceId VARCHAR(20) NOT NULL,
							dateMarked INT(11),
							PRIMARY KEY (id),
							UNIQUE INDEX (userId, resourceId),
							INDEX (userId)
						)",
					),
				),

				'notInterestedWorks' => array(
					'title'           => 'Not Interested Table Works Update',
					'description'     => 'Update Not Interested Table to Link to Works',
					'continueOnError' => true,
					'sql'             => array(
						"TRUNCATE TABLE `user_not_interested`",
						"ALTER TABLE `user_not_interested` ADD COLUMN groupedRecordPermanentId VARCHAR(36)",
						"ALTER TABLE `user_not_interested` DROP resourceId",
						"ALTER TABLE user_not_interested DROP INDEX userId",
						"ALTER TABLE user_not_interested DROP INDEX userId_2",
						"ALTER TABLE user_not_interested ADD INDEX(`userId`)",
					),
				),

				'materialsRequest' => array(
					'title'       => 'Materials Request Table Creation',
					'description' => 'Update reading History to include an id table',
					'sql'         => array(
						'CREATE TABLE IF NOT EXISTS materials_request (' .
						'id int(11) NOT NULL AUTO_INCREMENT, ' .
						'title varchar(255), ' .
						'author varchar(255), ' .
						'format varchar(25), ' .
						'ageLevel varchar(25), ' .
						'isbn varchar(15), ' .
						'oclcNumber varchar(30), ' .
						'publisher varchar(255), ' .
						'publicationYear varchar(4), ' .
						'articleInfo varchar(255), ' .
						'abridged TINYINT, ' .
						'about TEXT, ' .
						'comments TEXT, ' .
						"status enum('pending', 'owned', 'purchased', 'referredToILL', 'ILLplaced', 'ILLreturned', 'notEnoughInfo', 'notAcquiredOutOfPrint', 'notAcquiredNotAvailable', 'notAcquiredFormatNotAvailable', 'notAcquiredPrice', 'notAcquiredPublicationDate', 'requestCancelled') DEFAULT 'pending', " .
						'dateCreated int(11), ' .
						'createdBy int(11), ' .
						'dateUpdated int(11), ' .
						'PRIMARY KEY (id) ' .
						') ENGINE=InnoDB',
					),
				),

				'materialsRequest_update1' => array(
					'title'       => 'Materials Request Update 1',
					'description' => 'Material Request add fields for sending emails and creating holds',
					'sql'         => array(
						'ALTER TABLE `materials_request` ADD `emailSent` TINYINT NOT NULL DEFAULT 0',
						'ALTER TABLE `materials_request` ADD `holdsCreated` TINYINT NOT NULL DEFAULT 0',
						'ALTER TABLE `materials_request` ADD `email` VARCHAR(80)',
						'ALTER TABLE `materials_request` ADD `phone` VARCHAR(15)',
						'ALTER TABLE `materials_request` ADD `season` VARCHAR(80)',
						'ALTER TABLE `materials_request` ADD `magazineTitle` VARCHAR(255)',
						//'ALTER TABLE `materials_request` CHANGE `isbn_upc` `isbn` VARCHAR( 15 )',
						'ALTER TABLE `materials_request` ADD `upc` VARCHAR(15)',
						'ALTER TABLE `materials_request` ADD `issn` VARCHAR(8)',
						'ALTER TABLE `materials_request` ADD `bookType` VARCHAR(20)',
						'ALTER TABLE `materials_request` ADD `subFormat` VARCHAR(20)',
						'ALTER TABLE `materials_request` ADD `magazineDate` VARCHAR(20)',
						'ALTER TABLE `materials_request` ADD `magazineVolume` VARCHAR(20)',
						'ALTER TABLE `materials_request` ADD `magazinePageNumbers` VARCHAR(20)',
						'ALTER TABLE `materials_request` ADD `placeHoldWhenAvailable` TINYINT',
						'ALTER TABLE `materials_request` ADD `holdPickupLocation` VARCHAR(10)',
						'ALTER TABLE `materials_request` ADD `bookmobileStop` VARCHAR(50)',
						'ALTER TABLE `materials_request` ADD `illItem` VARCHAR(80)',
						'ALTER TABLE `materials_request` ADD `magazineNumber` VARCHAR(80)',
						'ALTER TABLE `materials_request` ADD INDEX(createdBy)',
						'ALTER TABLE `materials_request` ADD INDEX(dateUpdated)',
						'ALTER TABLE `materials_request` ADD INDEX(dateCreated)',
						'ALTER TABLE `materials_request` ADD INDEX(emailSent)',
						'ALTER TABLE `materials_request` ADD INDEX(holdsCreated)',
						'ALTER TABLE `materials_request` ADD INDEX(format)',
						'ALTER TABLE `materials_request` ADD INDEX(subFormat)',
						'ALTER TABLE `materials_request` ADD COLUMN `assignedTo` INT NULL',
					),
				),

				'materialsRequestStatus' => array(
					'title'       => 'Materials Request Status Table Creation',
					'description' => 'Update reading History to include an id table',
					'sql'         => array(
						'CREATE TABLE IF NOT EXISTS materials_request_status (' .
						'id int(11) NOT NULL AUTO_INCREMENT, ' .
						'description varchar(80), ' .
						'isDefault TINYINT DEFAULT 0, ' .
						'sendEmailToPatron TINYINT, ' .
						'emailTemplate TEXT, ' .
						'isOpen TINYINT, ' .
						'isPatronCancel TINYINT, ' .
						'PRIMARY KEY (id) ' .
						') ENGINE=InnoDB',

						"INSERT INTO materials_request_status (description, isDefault, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Request Pending', 1, 0, '', 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Already owned/On order', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The Library already owns this item or it is already on order. Please access our catalog to place this item on hold.	Please check our online catalog periodically to put a hold for this item.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Item purchased', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. Outcome: The library is purchasing the item you requested. Please check our online catalog periodically to put yourself on hold for this item. We anticipate that this item will be available soon for you to place a hold.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Referred to Collection Development - Adult', 0, '', 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Referred to Collection Development - J/YA', 0, '', 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Referred to Collection Development - AV', 0, '', 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('ILL Under Review', 0, '', 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Request Referred to ILL', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The library\\'s Interlibrary loan department is reviewing your request. We will attempt to borrow this item from another system. This process generally takes about 2 - 6 weeks.', 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Request Filled by ILL', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. Our Interlibrary Loan Department is set to borrow this item from another library.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Ineligible ILL', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. Your library account is not eligible for interlibrary loan at this time.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Not enough info - please contact Collection Development to clarify', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We need more specific information in order to locate the exact item you need. Please re-submit your request with more details.', 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - out of print', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item is out of print.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - not available in the US', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item is not available in the US.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - not available from vendor', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item is not available from a preferred vendor.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - not published', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The item you requested has not yet been published. Please check our catalog when the publication date draws near.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - price', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item does not fit our collection guidelines.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unable to acquire the item - publication date', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. We regret that we are unable to acquire the item you requested. This item does not fit our collection guidelines.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Unavailable', 1, 'This e-mail is to let you know the status of your recent request for an item that you did not find in our catalog. The item you requested cannot be purchased at this time from any of our regular suppliers and is not available from any of our lending libraries.', 0)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen, isPatronCancel) VALUES ('Cancelled by Patron', 0, '', 0, 1)",
						"INSERT INTO materials_request_status (description, sendEmailToPatron, emailTemplate, isOpen) VALUES ('Cancelled - Duplicate Request', 0, '', 0)",

						"UPDATE materials_request SET status = (SELECT id FROM materials_request_status WHERE isDefault =1)",

						"ALTER TABLE materials_request CHANGE `status` `status` INT(11)",
					),
				),

				'manageMaterialsRequestFieldsToDisplay' => array(
					'title'       => 'Manage Material Requests Fields to Display Table Creation',
					'description' => 'New table to manage columns displayed in lists of materials requests on the manage page.',
					'sql'         => array(
						"CREATE TABLE `materials_request_fields_to_display` ("
						. "  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,"
						. "  `libraryId` int(11) NOT NULL,"
						. "  `columnNameToDisplay` varchar(30) NOT NULL,"
						. "  `labelForColumnToDisplay` varchar(45) NOT NULL,"
						. "  `weight` smallint(2) unsigned NOT NULL DEFAULT '0',"
						. "  PRIMARY KEY (`id`),"
						. "  UNIQUE KEY `columnNameToDisplay` (`columnNameToDisplay`,`libraryId`),"
						. "  KEY `libraryId` (`libraryId`)"
						. ") ENGINE=InnoDB DEFAULT CHARSET=utf8;"
					),
				),

				'materialsRequestFormats' => array(
					'title'       => 'Material Requests Formats Table Creation',
					'description' => 'New table to manage materials formats that can be requested.',
					'sql'         => array(
						'CREATE TABLE `materials_request_formats` ('
						. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
						. '`libraryId` INT UNSIGNED NOT NULL,'
						. ' `format` VARCHAR(30) NOT NULL,'
						. '`formatLabel` VARCHAR(60) NOT NULL,'
						. '`authorLabel` VARCHAR(45) NOT NULL,'
						. '`weight` SMALLINT(2) UNSIGNED NOT NULL DEFAULT 0,'
						. "`specialFields` SET('Abridged/Unabridged', 'Article Field', 'Eaudio format', 'Ebook format', 'Season') NULL,"
						. 'PRIMARY KEY (`id`),'
						. 'INDEX `libraryId` (`libraryId` ASC));'
					),
				),

				'materialsRequestFormFields' => array(
					'title'       => 'Material Requests Form Fields Table Creation',
					'description' => 'New table to manage materials request form fields.',
					'sql'         => array(
						'CREATE TABLE `materials_request_form_fields` ('
						. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
						. '`libraryId` INT UNSIGNED NOT NULL,'
						. '`formCategory` VARCHAR(55) NOT NULL,'
						. '`fieldLabel` VARCHAR(255) NOT NULL,'
						. '`fieldType` VARCHAR(30) NULL,'
						. '`weight` SMALLINT(2) UNSIGNED NOT NULL,'
						. 'PRIMARY KEY (`id`),'
						. 'UNIQUE INDEX `id_UNIQUE` (`id` ASC),'
						. 'INDEX `libraryId` (`libraryId` ASC));'
					),
				),

				'staffSettingsTable' => array(
					'title'       => 'Staff Settings Table Creation',
					'description' => 'New table to contain user settings for staff users.',
					'sql'         => array(
						'CREATE TABLE `user_staff_settings` ('
						. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
						. '`userId` INT UNSIGNED NOT NULL,'
						. '`materialsRequestReplyToAddress` VARCHAR(70) NULL,'
						. '`materialsRequestEmailSignature` TINYTEXT NULL,'
						. 'PRIMARY KEY (`id`),'
						. 'UNIQUE INDEX `userId_UNIQUE` (`userId` ASC),'
						. 'INDEX `userId` (`userId` ASC));'
					),
				),

				'materialsRequestLibraryId' => array(
					'title'       => 'Add LibraryId to Material Requests Table',
					'description' => 'Add LibraryId column to Materials Request table and populate column for existing requests.',
					'sql'         => array(
						'ALTER TABLE `materials_request` '
						. 'ADD COLUMN `libraryId` INT UNSIGNED NULL AFTER `id`, '
						. 'ADD COLUMN `formatId` INT UNSIGNED NULL AFTER `format`; ',

						'ALTER TABLE `materials_request` '
						. 'CHANGE COLUMN `illItem` `illItem` TINYINT(4) NULL DEFAULT NULL ;',

						'UPDATE  `materials_request`'
						. 'LEFT JOIN `user` ON (user.id=materials_request.createdBy) '
						. 'LEFT JOIN `location` ON (location.locationId=user.homeLocationId) '
						. 'SET materials_request.libraryId = location.libraryId '
						. 'WHERE materials_request.libraryId IS null '
						. 'and user.id IS NOT null '
						. 'and location.libraryId IS not null;',

						'UPDATE `materials_request` '
						. 'LEFT JOIN `location` ON (location.locationId=materials_request.holdPickupLocation) '
						. 'SET materials_request.libraryId = location.libraryId '
						. ' WHERE materials_request.libraryId IS null and location.libraryId IS not null;'
					),
				),

				'materialsRequestStatus_update1' => array(
					'title'       => 'Materials Request Status Update 1',
					'description' => 'Material Request Status add library id',
					'sql'         => array(
						"ALTER TABLE `materials_request_status` ADD `libraryId` INT(11) DEFAULT '-1'",
						'ALTER TABLE `materials_request_status` ADD INDEX (`libraryId`)',
					),
				),

				'catalogingRole' => array(
					'title'       => 'Create cataloging role',
					'description' => 'Create cataloging role to handle materials requests, econtent loading, etc.',
					'sql'         => array(
						"INSERT INTO `roles` (`name`, `description`) VALUES ('cataloging', 'Allows user to perform cataloging activities.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('library_material_requests', 'Allows user to manage material requests for a specific library.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('libraryManager', 'Allows user to do basic configuration for their library.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('locationManager', 'Allows user to do basic configuration for their location.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('circulationReports', 'Allows user to view offline circulation reports.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('libraryAdmin', 'Allows user to update library configuration for their library system only for their home location.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('contentEditor', 'Allows entering of librarian reviews and creation of widgets.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('listPublisher', 'Optionally only include lists from people with this role in search results.')",
						"INSERT INTO `roles` (`name`, `description`) VALUES ('archives', 'Control overall archives integration.')",
						"INSERT INTO roles (name, description) VALUES ('locationReports', 'Allows the user to view reports for their location.')",
					),
				),

				'ip_lookup_1' => array(
					'title'           => 'IP Lookup Update 1',
					'description'     => 'Add start and end ranges for IP Lookup table to improve performance.',
					'continueOnError' => true,
					'sql'             => array(
						"ALTER TABLE ip_lookup ADD COLUMN startIpVal BIGINT",
						"ALTER TABLE ip_lookup ADD COLUMN endIpVal BIGINT",
						"ALTER TABLE `ip_lookup` ADD INDEX ( `startIpVal` )",
						"ALTER TABLE `ip_lookup` ADD INDEX ( `endIpVal` )",
						"ALTER TABLE `ip_lookup` CHANGE `startIpVal` `startIpVal` BIGINT NULL DEFAULT NULL ",
						"ALTER TABLE `ip_lookup` CHANGE `endIpVal` `endIpVal` BIGINT NULL DEFAULT NULL ",
						"ALTER TABLE `ip_lookup` ADD COLUMN `isOpac` TINYINT UNSIGNED NOT NULL DEFAULT 1",
						"createDefaultIpRanges"

					),
				),

				'nongrouped_records' => array(
					'title'       => 'Non-grouped Records Table',
					'description' => 'Create non-grouped Records table to store records that should not be grouped',
					'sql'         => array(
						"CREATE TABLE `nongrouped_records` (
									id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
									`source` VARCHAR( 50 ) NOT NULL,
									`recordId` VARCHAR( 36 ) NOT NULL,
									`notes` VARCHAR( 255 ) NOT NULL,
									UNIQUE INDEX (source, recordId)
								)",
					),
				),

				'author_enrichment' => array(
					'title'       => 'Author Enrichment',
					'description' => 'Create table to store enrichment for authors',
					'sql'         => array(
						"CREATE TABLE `author_enrichment` (
									id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
									`authorName` VARCHAR( 255 ) NOT NULL,
									`hideWikipedia` TINYINT( 1 ),
									`wikipediaUrl` VARCHAR( 255 ),
									INDEX(authorName)
								)",
					),
				),

				'variables_table' => array(
					'title'       => 'Variables Table',
					'description' => 'Create Variables Table for storing basic variables for use in programs (system writable config)',
					'sql'         => array(
						"CREATE TABLE `variables` (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							`name` VARCHAR( 128 ) NOT NULL,
							`value` VARCHAR( 255 ),
							INDEX(name)
						)",
						"ALTER TABLE variables ADD UNIQUE (name)",
						"INSERT INTO variables (name, value) VALUES ('validateChecksumsFromDisk', 'false')",
						"INSERT INTO variables (name, value) VALUES ('offline_mode_when_offline_login_allowed', 'false')",
						"INSERT INTO variables (name, value) VALUES ('fullReindexIntervalWarning', '86400')",
						"INSERT INTO variables (name, value) VALUES ('fullReindexIntervalCritical', '129600')",
					),
				),

				'utf8_update' => array(
					'title'           => 'Update to UTF-8',
					'description'     => 'Update database to use UTF-8 encoding',
					'continueOnError' => true,
					'sql'             => array(
						"ALTER DATABASE " . $configArray['Database']['database_vufind_dbname'] . " DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;",
						//"ALTER TABLE administrators CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE bad_words CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE comments CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE db_update CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE editorial_reviews CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE ip_lookup CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE library CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE list_widgets CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE list_widget_lists CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE location CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE resource CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE resource_tags CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE roles CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE search CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE search_stats CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE session CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE spelling_words CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE tags CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE user CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE user_list CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
						"ALTER TABLE user_roles CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;",
					),
				),

				'reindexLog' => array(
					'title'       => 'Reindex Log table',
					'description' => 'Create Reindex Log table to track reindexing.',
					'sql'         => array(
						"CREATE TABLE IF NOT EXISTS reindex_log(" .
						"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of reindex log', " .
						"`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the reindex started', " .
						"`endTime` INT(11) NULL COMMENT 'The timestamp when the reindex process ended', " .
						"PRIMARY KEY ( `id` )" .
						") ENGINE = MYISAM;",
						"CREATE TABLE IF NOT EXISTS reindex_process_log(" .
						"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of reindex process', " .
						"`reindex_id` INT(11) NOT NULL COMMENT 'The id of the reindex log this process ran during', " .
						"`processName` VARCHAR(50) NOT NULL COMMENT 'The name of the process being run', " .
						"`recordsProcessed` INT(11) NOT NULL COMMENT 'The number of records processed from marc files', " .
						"`eContentRecordsProcessed` INT(11) NOT NULL COMMENT 'The number of econtent records processed from the database', " .
						"`resourcesProcessed` INT(11) NOT NULL COMMENT 'The number of resources processed from the database', " .
						"`numErrors` INT(11) NOT NULL COMMENT 'The number of errors that occurred during the process', " .
						"`numAdded` INT(11) NOT NULL COMMENT 'The number of additions that occurred during the process', " .
						"`numUpdated` INT(11) NOT NULL COMMENT 'The number of items updated during the process', " .
						"`numDeleted` INT(11) NOT NULL COMMENT 'The number of items deleted during the process', " .
						"`numSkipped` INT(11) NOT NULL COMMENT 'The number of items skipped during the process', " .
						"`notes` TEXT COMMENT 'Additional information about the process', " .
						"PRIMARY KEY ( `id` ), INDEX ( `reindex_id` ), INDEX ( `processName` )" .
						") ENGINE = MYISAM;",
						"ALTER TABLE reindex_log ADD COLUMN `notes` TEXT COMMENT 'Notes related to the overall process'",
						"ALTER TABLE reindex_log ADD `lastUpdate` INT(11) COMMENT 'The last time the log was updated'",
						"ALTER TABLE reindex_log ADD COLUMN numWorksProcessed INT(11) NOT NULL DEFAULT 0",
						"ALTER TABLE reindex_log ADD COLUMN numListsProcessed INT(11) NOT NULL DEFAULT 0"
					),
				),


				'cronLog' => array(
					'title'       => 'Cron Log table',
					'description' => 'Create Cron Log table to track reindexing.',
					'sql'         => array(
						"CREATE TABLE IF NOT EXISTS cron_log(" .
						"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of the cron log', " .
						"`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the cron run started', " .
						"`endTime` INT(11) NULL COMMENT 'The timestamp when the cron run ended', " .
						"`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the cron run last updated (to check for stuck processes)', " .
						"`notes` TEXT COMMENT 'Additional information about the cron run', " .
						"PRIMARY KEY ( `id` )" .
						") ENGINE = MYISAM;",
						"CREATE TABLE IF NOT EXISTS cron_process_log(" .
						"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of cron process', " .
						"`cronId` INT(11) NOT NULL COMMENT 'The id of the cron run this process ran during', " .
						"`processName` VARCHAR(50) NOT NULL COMMENT 'The name of the process being run', " .
						"`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the process started', " .
						"`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the process last updated (to check for stuck processes)', " .
						"`endTime` INT(11) NULL COMMENT 'The timestamp when the process ended', " .
						"`numErrors` INT(11) NOT NULL DEFAULT 0 COMMENT 'The number of errors that occurred during the process', " .
						"`numUpdates` INT(11) NOT NULL DEFAULT 0 COMMENT 'The number of updates, additions, etc. that occurred', " .
						"`notes` TEXT COMMENT 'Additional information about the process', " .
						"PRIMARY KEY ( `id` ), INDEX ( `cronId` ), INDEX ( `processName` )" .
						") ENGINE = MYISAM;",

					),
				),

				'add_indexes'  => array(
					'title'           => 'Add indexes',
					'description'     => 'Add indexes to tables that were not defined originally',
					'continueOnError' => true,
					'sql'             => array(
						'ALTER TABLE `editorial_reviews` ADD INDEX `RecordId` ( `recordId` ) ',
						'ALTER TABLE `list_widget_lists` ADD INDEX `ListWidgetId` ( `listWidgetId` ) ',
						'ALTER TABLE `location` ADD INDEX `ValidHoldPickupBranch` ( `validHoldPickupBranch` ) ',
					),
				),

				'add_indexes2' => array(
					'title'           => 'Add indexes 2',
					'description'     => 'Add additional indexes to tables that were not defined originally',
					'continueOnError' => true,
					'sql'             => array(
						'ALTER TABLE `materials_request_status` ADD INDEX ( `isDefault` )',
						'ALTER TABLE `materials_request_status` ADD INDEX ( `isOpen` )',
						'ALTER TABLE `materials_request_status` ADD INDEX ( `isPatronCancel` )',
						'ALTER TABLE `materials_request` ADD INDEX ( `status` )'
					),
				),

				'spelling_optimization' => array(
					'title'       => 'Spelling Optimization',
					'description' => 'Optimizations to spelling to ensure indexes are used',
					'sql'         => array(
						'ALTER TABLE `spelling_words` ADD `soundex` VARCHAR(20) ',
						'ALTER TABLE `spelling_words` ADD INDEX `Soundex` (`soundex`)',
						'UPDATE `spelling_words` SET soundex = SOUNDEX(word) '
					),
				),

				'boost_disabling' => array(
					'title'       => 'Disabling Lib and Loc Boosting',
					'description' => 'Allow boosting of library and location boosting to be disabled',
					'sql'         => array(
						"ALTER TABLE `library` ADD `boostByLibrary` TINYINT DEFAULT '1'",
						"ALTER TABLE `location` ADD `boostByLocation` TINYINT DEFAULT '1'",
					),
				),

				//				'addTablelistWidgetListsLinks' => array(
				//					'title' => 'Widget Lists',
				//					'description' => 'Add a new table: list_widget_lists_links',
				//					'sql' => array('addTableListWidgetListsLinks'),
				//				),
				//

				'loan_rule_determiners_1' => array(
					'title'       => 'Loan Rule Determiners',
					'description' => 'Build tables to store loan rule determiners',
					'sql'         => array(
						"CREATE TABLE IF NOT EXISTS loan_rules (" .
						"`id` INT NOT NULL AUTO_INCREMENT, " .
						"`loanRuleId` INT NOT NULL COMMENT 'The location id', " .
						"`name` varchar(50) NOT NULL COMMENT 'The location code the rule applies to', " .
						"`code` char(1) NOT NULL COMMENT '', " .
						"`normalLoanPeriod` INT(4) NOT NULL COMMENT 'Number of days the item checks out for', " .
						"`holdable` TINYINT NOT NULL DEFAULT '0', " .
						"`bookable` TINYINT NOT NULL DEFAULT '0', " .
						"`homePickup` TINYINT NOT NULL DEFAULT '0', " .
						"`shippable` TINYINT NOT NULL DEFAULT '0', " .
						"PRIMARY KEY ( `id` ), " .
						"INDEX ( `loanRuleId` ), " .
						"INDEX (`holdable`) " .
						") ENGINE=InnoDB",
						"CREATE TABLE IF NOT EXISTS loan_rule_determiners (" .
						"`id` INT NOT NULL AUTO_INCREMENT, " .
						"`rowNumber` INT NOT NULL COMMENT 'The row of the determiner.  Rules are processed in reverse order', " .
						"`location` varchar(10) NOT NULL COMMENT '', " .
						"`patronType` VARCHAR(50) NOT NULL COMMENT 'The patron types that this rule applies to', " .
						"`itemType` VARCHAR(255) NOT NULL DEFAULT '0' COMMENT 'The item types that this rule applies to', " .
						"`ageRange` varchar(10) NOT NULL COMMENT '', " .
						"`loanRuleId` varchar(10) NOT NULL COMMENT 'Close hour (24hr format) HH:MM', " .
						"`active` TINYINT NOT NULL DEFAULT '0', " .
						"PRIMARY KEY ( `id` ), " .
						"INDEX ( `rowNumber` ), " .
						"INDEX (`active`) " .
						") ENGINE=InnoDB",
						"ALTER TABLE loan_rule_determiners CHANGE COLUMN patronType `patronType` VARCHAR(255) NOT NULL COMMENT 'The patron types that this rule applies to'",
					),
				),

				'location_hours' => array(
					'title'       => 'Location Hours',
					'description' => 'Build table to store hours for a location',
					'sql'         => array(
						"CREATE TABLE IF NOT EXISTS location_hours (" .
						"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of hours entry', " .
						"`locationId` INT NOT NULL COMMENT 'The location id', " .
						"`day` INT NOT NULL COMMENT 'Day of the week 0 to 7 (Sun to Monday)', " .
						"`closed` TINYINT NOT NULL DEFAULT '0' COMMENT 'Whether or not the library is closed on this day', " .
						"`open` varchar(10) NOT NULL COMMENT 'Open hour (24hr format) HH:MM', " .
						"`close` varchar(10) NOT NULL COMMENT 'Close hour (24hr format) HH:MM', " .
						"PRIMARY KEY ( `id` ), " .
						"UNIQUE KEY (`locationId`, `day`) " .
						") ENGINE=InnoDB",
					),
				),
				'holiday'        => array(
					'title'       => 'Holidays',
					'description' => 'Build table to store holidays',
					'sql'         => array(
						"CREATE TABLE IF NOT EXISTS holiday (" .
						"`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of holiday', " .
						"`libraryId` INT NOT NULL COMMENT 'The library system id', " .
						"`date` date NOT NULL COMMENT 'Date of holiday', " .
						"`name` varchar(100) NOT NULL COMMENT 'Name of holiday', " .
						"PRIMARY KEY ( `id` ), " .
						"UNIQUE KEY (`date`) " .
						") ENGINE=InnoDB",
						"ALTER TABLE holiday DROP INDEX `date`",
						"ALTER TABLE holiday ADD INDEX Date (`date`) ",
						"ALTER TABLE holiday ADD INDEX Library (`libraryId`) ",
						"ALTER TABLE holiday ADD UNIQUE KEY LibraryDate(`date`, `libraryId`) ",
					),
				),

				'ptype' => array(
					'title'       => 'P-Type',
					'description' => 'Build tables to store information related to P-Types.',
					'sql'         => array(
						'CREATE TABLE IF NOT EXISTS ptype(
							id INT(11) NOT NULL AUTO_INCREMENT,
							pType INT(11) NOT NULL,
							maxHolds INT(11) NOT NULL DEFAULT 300,
							UNIQUE KEY (pType),
							PRIMARY KEY (id)
						)',
						'ALTER TABLE `ptype` ADD COLUMN `masquerade` VARCHAR(45) NOT NULL DEFAULT \'none\' AFTER `maxHolds`;',
						'ALTER TABLE `ptype`  CHANGE COLUMN `pType` `pType` VARCHAR(20) NOT NULL ;',
						"ALTER TABLE ptype ADD COLUMN label VARCHAR(60) NULL",
					)
				),

				'add_staff_ptype_2021.01.0' => [
					'title'       => 'Add Staff P-Type',
					'description' => 'Add isStaffPType column to P-Types table.',
					'sql'         => [
						'ALTER TABLE `ptype` ADD COLUMN `isStaffPType` BOOLEAN NOT NULL DEFAULT false AFTER `maxHolds`;',
						'setStaffPtypes'
					]
				],

				'session_update_1' => array(
					'title'       => 'Session Update 1',
					'description' => 'Add a field for whether or not the session was started with remember me on.',
					'sql'         => array(
						"ALTER TABLE session ADD COLUMN `remember_me` TINYINT NOT NULL DEFAULT 0 COMMENT 'Whether or not the session was started with remember me on.'",
					),
				),

				'offline_holds' => array(
					'title'       => 'Offline Holds',
					'description' => 'Stores information about holds that have been placed while the circulation system is offline',
					'sql'         => array(
						"CREATE TABLE offline_hold (
							`id` INT(11) NOT NULL AUTO_INCREMENT,
							`timeEntered` INT(11) NOT NULL,
							`timeProcessed` INT(11) NULL,
							`bibId` VARCHAR(10) NOT NULL,
							`patronId` INT(11) NOT NULL,
							`patronBarcode` VARCHAR(20),
							`status` ENUM('Not Processed', 'Hold Succeeded', 'Hold Failed'),
							`notes` VARCHAR(512),
							INDEX(`timeEntered`),
							INDEX(`timeProcessed`),
							INDEX(`patronBarcode`),
							INDEX(`patronId`),
							INDEX(`bibId`),
							INDEX(`status`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM",
						"ALTER TABLE `offline_hold` CHANGE `patronId` `patronId` INT( 11 ) NULL",
						"ALTER TABLE `offline_hold` ADD COLUMN `patronName` VARCHAR( 200 ) NULL",
						"ALTER TABLE `offline_hold` ADD COLUMN `itemId` VARCHAR( 20 ) NULL",
					)
				),

				'offline_circulation' => array(
					'title'       => 'Offline Circulation',
					'description' => 'Stores information about circulation activities done while the circulation system was offline',
					'sql'         => array(
						"CREATE TABLE offline_circulation (
							`id` INT(11) NOT NULL AUTO_INCREMENT,
							`timeEntered` INT(11) NOT NULL,
							`timeProcessed` INT(11) NULL,
							`itemBarcode` VARCHAR(20) NOT NULL,
							`patronBarcode` VARCHAR(20),
							`patronId` INT(11) NULL,
							`login` VARCHAR(50),
							`loginPassword` VARCHAR(50),
							`initials` VARCHAR(50),
							`initialsPassword` VARCHAR(50),
							`type` ENUM('Check In', 'Check Out'),
							`status` ENUM('Not Processed', 'Processing Succeeded', 'Processing Failed'),
							`notes` VARCHAR(512),
							INDEX(`timeEntered`),
							INDEX(`patronBarcode`),
							INDEX(`patronId`),
							INDEX(`itemBarcode`),
							INDEX(`login`),
							INDEX(`initials`),
							INDEX(`type`),
							INDEX(`status`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM"
					)
				),

				'novelist_data' => array(
					'title'       => 'Novelist Data',
					'description' => 'Stores basic information from Novelist for efficiency purposes.  We can\'t cache everything due to contract.',
					'sql'         => array(
						"CREATE TABLE novelist_data (
							id INT(11) NOT NULL AUTO_INCREMENT,
							groupedRecordPermanentId VARCHAR(36),
							lastUpdate INT(11),
							hasNovelistData TINYINT(1),
							groupedRecordHasISBN TINYINT(1),
							primaryISBN VARCHAR(13),
							seriesTitle VARCHAR(255),
							seriesNote VARCHAR(255),
							volume VARCHAR(32),
							INDEX(`groupedRecordPermanentId`),
							PRIMARY KEY(`id`)
						) ENGINE = MYISAM",
					),
				),

				'ils_marc_checksums' => array(
					'title'       => 'ILS MARC Checksums',
					'description' => 'Add a table to store checksums of MARC records stored in the ILS so we can determine if the record needs to be updated during grouping.',
					'sql'         => array(
						"CREATE TABLE IF NOT EXISTS ils_marc_checksums (
							id INT(11) NOT NULL AUTO_INCREMENT,
							ilsId VARCHAR(20) NOT NULL,
							checksum BIGINT(20) UNSIGNED NOT NULL,
							PRIMARY KEY (id),
							UNIQUE (ilsId)
						) ENGINE=MyISAM  DEFAULT CHARSET=utf8",
						"ALTER TABLE ils_marc_checksums ADD dateFirstDetected BIGINT UNSIGNED NULL",
						"ALTER TABLE ils_marc_checksums CHANGE dateFirstDetected dateFirstDetected BIGINT SIGNED NULL",
						"ALTER TABLE ils_marc_checksums ADD source VARCHAR(50) NOT NULL DEFAULT 'ils'",
						"ALTER TABLE ils_marc_checksums ADD UNIQUE (`source`, `ilsId`)",
					),
				),

				'work_level_ratings' => array(
					'title'       => 'Work Level Ratings',
					'description' => 'Stores user ratings at the work level rather than the individual record.',
					'sql'         => array(
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
					),
				),

				'work_level_tagging' => array(
					'title'       => 'Work Level Tagging',
					'description' => 'Stores tags at the work level rather than the individual record.',
					'sql'         => array(
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
					),
				),

				'user_list_entry' => array(
					'title'       => 'User List Entry (Grouped Work)',
					'description' => 'Add grouped works to lists rather than resources.',
					'sql'         => array(
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
					),
				),

				'user_list_indexing' => array(
					'title'       => 'Update User List to make indexing easier',
					'description' => 'Add date updated and deleted to the table so we can easily do partial indexes of the data.',
					'sql'         => array(
						"ALTER TABLE user_list ADD dateUpdated INT(11)",
						"ALTER TABLE user_list ADD deleted TINYINT(1) DEFAULT 0",
						"ALTER TABLE user_list DROP created",
						"ALTER TABLE user_list ADD created INT(11)",
						"ALTER TABLE `user_list` ADD `defaultSort` VARCHAR(20)",
					)
				),

				'browse_categories' => array(
					'title'       => 'Browse Categories',
					'description' => 'Setup Browse Category Table',
					'sql'         => array(
						"CREATE TABLE browse_category (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							textId VARCHAR(60) NOT NULL DEFAULT -1,
							userId INT(11),
							sharing ENUM('private', 'location', 'library', 'everyone') DEFAULT 'everyone',
							label VARCHAR(50) NOT NULL,
							description MEDIUMTEXT,
							catalogScoping ENUM('unscoped', 'library', 'location'),
							defaultFilter TEXT,
							defaultSort ENUM('relevance', 'popularity', 'newest_to_oldest', 'oldest_to_newest', 'author', 'title', 'user_rating'),
							UNIQUE (textId)
						) ENGINE = MYISAM",
						"ALTER TABLE browse_category ADD searchTerm VARCHAR(100) NOT NULL DEFAULT ''",
						"ALTER TABLE browse_category ADD numTimesShown MEDIUMINT NOT NULL DEFAULT 0",
						"ALTER TABLE browse_category ADD numTitlesClickedOn MEDIUMINT NOT NULL DEFAULT 0",
						"ALTER TABLE browse_category CHANGE searchTerm searchTerm VARCHAR(300) NOT NULL DEFAULT ''",
						"ALTER TABLE browse_category CHANGE searchTerm searchTerm VARCHAR(500) NOT NULL DEFAULT ''",
						"ALTER TABLE browse_category ADD sourceListId MEDIUMINT NULL DEFAULT NULL",
					),
				),

				'sub-browse_categories' => array(
					'title'       => 'Enable Browse Sub-Categories',
					'description' => 'Add a the ability to define a browse category from a list',
					'sql'         => array(
						"CREATE TABLE `browse_category_subcategories` (
							  `id` int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
							  `browseCategoryId` int(11) NOT NULL,
							  `subCategoryId` int(11) NOT NULL,
							  `weight` SMALLINT(2) UNSIGNED NOT NULL DEFAULT '0',
							  UNIQUE (`subCategoryId`,`browseCategoryId`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8"
					),
				),

				'localized_browse_categories' => array(
					'title'       => 'Localized Browse Categories',
					'description' => 'Setup Localized Browse Category Tables',
					'sql'         => array(
						"CREATE TABLE browse_category_library (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							libraryId INT(11) NOT NULL,
							browseCategoryTextId VARCHAR(60) NOT NULL DEFAULT -1,
							weight INT NOT NULL DEFAULT '0',
							UNIQUE (libraryId, browseCategoryTextId)
						) ENGINE = MYISAM",
						"CREATE TABLE browse_category_location (
							id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
							locationId INT(11) NOT NULL,
							browseCategoryTextId VARCHAR(60) NOT NULL DEFAULT -1,
							weight INT NOT NULL DEFAULT '0',
							UNIQUE (locationId, browseCategoryTextId)
						) ENGINE = MYISAM",
					),
				),

				'remove_old_resource_tables' => array(
					'title'       => 'Remove old Resource Tables',
					'description' => 'Remove old tables that were used for storing information based on resource',
					'sql'         => array(
						"DROP TABLE IF EXISTS comments",
						"DROP TABLE IF EXISTS resource_tags",
						"DROP TABLE IF EXISTS user_resource",
						"DROP TABLE IF EXISTS resource",
					),
				),

				'authentication_profiles' => array(
					'title'       => 'Setup Authentication Profiles',
					'description' => 'Setup authentication profiles to store information about how to authenticate',
					'sql'         => array(
						"CREATE TABLE IF NOT EXISTS `account_profiles` (
						  `id` int(11) NOT NULL AUTO_INCREMENT,
						  `name` varchar(50) NOT NULL DEFAULT 'ils',
						  `driver` varchar(50) NOT NULL,
						  `loginConfiguration` enum('barcode_pin','name_barcode') NOT NULL,
						  `authenticationMethod` enum('ils','sip2','db','ldap') NOT NULL DEFAULT 'ils',
						  `vendorOpacUrl` varchar(100) NOT NULL,
						  `patronApiUrl` varchar(100) NOT NULL,
						  `recordSource` varchar(50) NOT NULL,
						  PRIMARY KEY (`id`),
						  UNIQUE KEY `name` (`name`)
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
						"ALTER TABLE `account_profiles` ADD `vendorOpacUrl` varchar(100) NOT NULL",
						"ALTER TABLE `account_profiles` ADD `patronApiUrl` varchar(100) NOT NULL",
						"ALTER TABLE `account_profiles` ADD `recordSource` varchar(50) NOT NULL",
						"ALTER TABLE `account_profiles` ADD `weight` int(11) NOT NULL",
					)
				),

				'archive_private_collections' => array(
					'title'           => 'Archive Private Collections',
					'description'     => 'Create a table to store information about collections that should be private to the owning library',
					'continueOnError' => true,
					'sql'             => array(
						"CREATE TABLE IF NOT EXISTS archive_private_collections (
									  `id` int(11) NOT NULL AUTO_INCREMENT,
									  privateCollections MEDIUMTEXT,
									  PRIMARY KEY (`id`)
									) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
					)
				),

				'archive_subjects' => array(
					'title'           => 'Archive Subjects',
					'description'     => 'Create a table to store information about what subjects should be ignored and restricted',
					'continueOnError' => true,
					'sql'             => array(
						"CREATE TABLE IF NOT EXISTS archive_subjects (
									  `id` int(11) NOT NULL AUTO_INCREMENT,
									  subjectsToIgnore MEDIUMTEXT,
									  subjectsToRestrict MEDIUMTEXT,
									  PRIMARY KEY (`id`)
									) ENGINE=InnoDB  DEFAULT CHARSET=utf8",
					)
				),

				'archive_requests' => array(
					'title'           => 'Archive Requests',
					'description'     => 'Create a table to store information about the requests for copies of archive information',
					'continueOnError' => true,
					'sql'             => array(
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
					)
				),

				'claim_authorship_requests' => array(
					'title'           => 'Claim Authorship Requests',
					'description'     => 'Create a table to store information about the people who are claiming authorship of archive information',
					'continueOnError' => true,
					'sql'             => array(
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
					)
				),

				'add_search_source_to_saved_searches' => array(
					'title'           => 'Store the Search Source with saved searches',
					'description'     => 'Add column to store the source for a search in the search table',
					'continueOnError' => true,
					'sql'             => array(
						"ALTER TABLE `search` 
									ADD COLUMN `searchSource` VARCHAR(30) NOT NULL DEFAULT 'local' AFTER `search_object`;",
					)
				),

				'record_grouping_log' => array(
					'title'           => 'Record Grouping Log',
					'description'     => 'Create Log for record grouping',
					'continueOnError' => false,
					'sql'             => array(
						"CREATE TABLE IF NOT EXISTS record_grouping_log(
									`id` INT NOT NULL AUTO_INCREMENT COMMENT 'The id of log', 
									`startTime` INT(11) NOT NULL COMMENT 'The timestamp when the run started', 
									`endTime` INT(11) NULL COMMENT 'The timestamp when the run ended', 
									`lastUpdate` INT(11) NULL COMMENT 'The timestamp when the run last updated (to check for stuck processes)', 
									`notes` TEXT COMMENT 'Additional information about the run includes stats per source', 
									PRIMARY KEY ( `id` )
									) ENGINE = MYISAM;",
					)
				),

				'create_pin_reset_table' => array(
					'title'           => 'Create table for secure pin reset.',
					'description'     => 'Creates table for pin reset',
					'continueOnError' => true,
					'sql'             => array(
						"CREATE TABLE IF NOT EXISTS pin_reset (
				    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				    userId VARCHAR(255),
				    selector CHAR(16),
				    token CHAR(64),
				    expires BIGINT(20)
						)",
					)
				),

				'remove_obsolete_tables-2020.01' => array(
					'title'           => 'Delete Unused tables',
					'description'     => 'Get rid of unused tables',
					'continueOnError' => true,
					'sql'             => array(
						"DROP TABLE IF EXISTS `marc_import`;",
						"DROP TABLE IF EXISTS `analytics_city`;",
						"DROP TABLE IF EXISTS `analytics_country`;",
						"DROP TABLE IF EXISTS `analytics_device`;",
						"DROP TABLE IF EXISTS `analytics_event`;",
						"DROP TABLE IF EXISTS `analytics_page_view`;",
						"DROP TABLE IF EXISTS `analytics_patron_type`;",
						"DROP TABLE IF EXISTS `analytics_physical_location`;",
						"DROP TABLE IF EXISTS `analytics_search`;",
						"DROP TABLE IF EXISTS `analytics_session`;",
						"DROP TABLE IF EXISTS `analytics_session_old`;",
						"DROP TABLE IF EXISTS `analytics_state`;",
						"DROP TABLE IF EXISTS `analytics_theme`;",
						"DROP TABLE IF EXISTS `millennium_cache`;",
						"DROP TABLE IF EXISTS `tag`;",
						"DROP TABLE IF EXISTS `book_store`;",
						"DROP TABLE IF EXISTS `circulation_status`;",
						"DROP TABLE IF EXISTS `evoke_record`;",
						"DROP TABLE IF EXISTS `external_link_tracking`;",
						"DROP TABLE IF EXISTS `nearby_book_store`;",
						"DROP TABLE IF EXISTS `non_holdable_locations`;",
						"DROP TABLE IF EXISTS `ptype_restricted_locations`;",
						"DROP TABLE IF EXISTS `purchase_link_tracking`;",
						"DROP TABLE IF EXISTS `purchaselinktracking_old`;",
						"DROP TABLE IF EXISTS `resource_callnumber`;",
						"DROP TABLE IF EXISTS `resource_subject`;",
						"DROP TABLE IF EXISTS `user_suggestions`;",
					)
				),

				'rename_editorial_reviews-2020.02' => array(
					'title'           => 'Refactor Editorial Reviews name',
					'description'     => 'Rename Editorial Reviews to Librarian Reviews',
					'continueOnError' => false,
					'sql'             => array(
						"UPDATE `roles` SET `description`='Allows entering of librarian reviews and creation of widgets.' WHERE `name`='contentEditor';",
						"ALTER TABLE `editorial_reviews` CHANGE COLUMN `editorialReviewId` `id` INT(11) NOT NULL , RENAME TO  `pika`.`librarian_reviews` ;",
						"UPDATE `library_more_details` SET `source` = 'librarianReviews' WHERE `source` = 'editorialReviews';",
					)
				),

				'librarian_reviews-2020.02' => array(
					'title'           => 'Librarian Review id',
					'description'     => 'Librarian Review id needs auto increment',
					'continueOnError' => false,
					'sql'             => [
						"ALTER TABLE `librarian_reviews` CHANGE COLUMN `id` `id` INT(11) NOT NULL AUTO_INCREMENT ;",
					]
				),
				'remove_selfReg_template_option' => array(
                  'title'       => 'Delete selfReg template option',
                  'description' => 'Get rid of the template option',
                  'continueOnError' => false,
                  'sql' => array(
                      "ALTER TABLE `library` DELETE COLUMN 'selfRegistrationTemplate';",
                  )
                ),
				'update_eContentSupportAddress_default_value' => array(
                    'title'       => 'Update e-Content support Address default address',
                    'description' => 'Update e-Content support Address default e-mail address to pika@marmot.org',
                    'continueOnError' => false,
                    'sql' => array(
                        "ALTER TABLE `library` ALTER COLUMN 'eContentSupportAddress' SET DEFAULT 'pika@marmot.org';",
                    )
                ),

				'remove_obsolete_tables-2020.05' => array(
					'title'           => 'Delete Unused tables',
					'description'     => 'Get rid of unused tables',
					'continueOnError' => true,
					'sql'             => array(
						"DROP TABLE IF EXISTS `library_search_source`;",
						"DROP TABLE IF EXISTS `location_search_source`;",
						"DROP TABLE IF EXISTS `syndetics_data`;",
						"DROP TABLE IF EXISTS `merged_records`;",
						"DROP TABLE IF EXISTS `search_stats`;",
					)
				),

				'add_custom_covers_table' => array(
                  'title'           => 'Add Custom Covers',
                  'description'     => 'Database tables to support custom cover uploads',
                  'continueOnError' => false,
                  'sql'             => array(
                      "CREATE TABLE IF NOT EXISTS covers (
				    coverId INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				    cover VARCHAR(255)
				    )ENGINE=InnoDB  DEFAULT CHARSET=utf8;"
                  )
                ),

				'add_current_covers'    => array(
                    'title'             => 'Add Current Covers to Database',
                    'description'       => 'Looks in the covers directory and adds files found to database',
                    'continueOnError'   => false,
                    'sql'               => array(
                        "INSERT INTO covers (cover) VALUES " .
                        implode(",",$this->createCoversFromDirectory()) . ";"
                    )
                ),
				'update-overdrive-logs-2020.07' => [
					'title'           => 'Add column to overdrive logs',
					'description'     => '[THIS NEEDS the econtent db to named econtent]',
					'continueOnError' => true,
					'sql'             => [
						'ALTER TABLE `econtent`.`overdrive_extract_log` ENGINE = InnoDB ,
								ADD COLUMN `numTitlesProcessed` INT UNSIGNED NULL DEFAULT NULL AFTER `numMetadataChanges`;',
						'ALTER TABLE `econtent`.`overdrive_api_products` ENGINE = InnoDB ;',
					]
				],
				'splitLargeLists' => array(
                    'title'             =>  'Split Large Lists',
                    'description'       =>  'Split Lists Over 2000 items into separate lists',
                    'continueOnError'   => false,
                    'sql'               => array('splitLargeLists')
                ),

				'overdrive-remove-numeric-formats-2020.07' => [
					'title'           => 'Remove obsolete numeric formats column from overdrive_api_product_formats',
					'description'     => '[THIS NEEDS the econtent db to named econtent]',
					'continueOnError' => true,
					'sql'             => [
						'ALTER TABLE `econtent`.`overdrive_api_product_formats` DROP COLUMN `numericId`, DROP INDEX `numericId` ;',
					]
				],

				'remove-econtent-protection-facet-2020.07' => [
					'title'           => 'Remove library settings that have econtent protection set',
					'description'     => '',
					'continueOnError' => true,
					'sql'             => [
						'DELETE FROM library_facet_setting WHERE `facetName` = "econtent_protection_type";',
					]
				],

				'add-pickup-branch-to-offline-holds-2021.01' => [
					'title'           => 'Add pickup branch to offline holds table',
					'description'     => '',
					'continueOnError' => true,
					'sql'             => [
						'ALTER TABLE `offline_hold` ADD COLUMN `pickupLocation` VARCHAR(5) NULL AFTER `itemId`;',
					]
				],
				'add_board_book_format' => [
					'title'       => 'Add new board book format',
					'description' => 'Add Board Book format to translation maps',
					'sql'         => [
						"INSERT INTO `translation_map_values` ( `translationMapId`, `value`, `translation`) VALUES 
						((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'grouping_categories'),
						'BoardBook', 'book')
						,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format'),
						'BoardBook', 'Board Book')
						,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_category'),
						'BoardBook', 'Book')
						,((SELECT id FROM translation_maps WHERE indexingProfileId = (SELECT id FROM indexing_profiles WHERE sourceName = 'ils') AND name = 'format_boost'),
						'BoardBook', '10')"
						,
					]
				],
				'add_fine_display_option' => [
					'title'       =>'Add Fines Display Amount Option to Library',
					'description' => 'Add option in Library ECommerce to display badges only above set amount',
					'sql'         => [
						"ALTER TABLE `pika`.`library` ADD COLUMN `fineAlertAmount` FLOAT(11) NOT NULL DEFAULT '0.00' AFTER `minimumFineAmount`"
					]
				],

			)); // End of main array
	}

	private function createDefaultIpRanges(){
		require_once ROOT_DIR . '/sys/Network/ipcalc.php';
		require_once ROOT_DIR . '/sys/Network/subnet.php';
		$subnet = new subnet();
		$subnet->find();
		while ($subnet->fetch()){
			$subnet->update();
		}
	}


	private function updateShowSeriesInMainDetails(){
		$library = new Library();
		$library->find();
		while ($library->fetch()){
			if (!count($library->showInMainDetails) == 0){
				$library->showInMainDetails[] = 'showSeries';
				$library->update();
			}
		}
	}

	private function createCoversFromDirectory()
    {
        global $configArray;
        $storagePath = $configArray['Site']['coverPath'];
        $files = array();
        if($handle = opendir($storagePath . DIR_SEP . "original"))
        {
            while (false !== ($entry = readdir($handle))){
                if ($entry != "." && $entry != ".."){
                    $value = '(\'' . htmlentities($entry, ENT_QUOTES) . '\')';
                    array_push($files, $value);
                }
            }

            closedir($handle);

        }
        return $files;
    }
    protected $logger;

	private function splitLargeLists(){

		$this->logger = new Logger('List Splitter');

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
					$this->logger->info('List Splitting Original list Id: ' . $listId . ', items: ' . $largeList['num'] . ' New List id: ' . $newListId);
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
				$this->logger->error("List $listId had more than 2000 entries but was not found in the UserList table.", $largeList);
			}
		}

	}

//	public function addTableListWidgetListsLinks() {
//		set_time_limit(120);
//		$sql = 'CREATE TABLE IF NOT EXISTS `list_widget_lists_links`( ' .
//			'`id` int(11) NOT NULL AUTO_INCREMENT, ' .
//			'`listWidgetListsId` int(11) NOT NULL, ' .
//			'`name` varchar(50) NOT NULL, ' .
//			'`link` text NOT NULL, ' .
//			'`weight` int(3) NOT NULL DEFAULT \'0\',' .
//			'PRIMARY KEY (`id`) ' .
//			') ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
//		$this->db->query($sql);
//	}

	function setStaffPtypes(){
		global $configArray;
		if (!empty($configArray['Staff P-Types'])){
			foreach ($configArray['Staff P-Types'] as $pTypeNumber => $label){
				$pType        = new PType();
				$pType->pType = $pTypeNumber;
				if ($pType->find(true)){
					$pType->isStaffPType = true;
					$pType->label        = $label;
					$pType->update();
				}else{
					$pType->isStaffPType = true;
					$pType->label        = $label;
					$pType->insert();
				}
			}
		}
	}

	function setCoverSource(){
		require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
		/** @var $indexingProfiles IndexingProfile[] */
		$indexingProfiles = IndexingProfile::getAllIndexingProfiles();

		foreach ($indexingProfiles as $indexingProfile){
			$name       = $indexingProfile->name;
			$sourceName = $indexingProfile->sourceName;
			if ($sourceName == "ils"){
				$indexingProfile->coverSource = 'ILS MARC';
			}elseif ($name == 'Colorado State Government Documents'){
				$indexingProfile->coverSource = 'Colorado State Government Documents';
			}elseif ($name == 'Classroom Video on Demand'){
				$indexingProfile->coverSource = 'Classroom Video on Demand';
			}elseif (stripos($name, 'films on demand') !== false){
				$indexingProfile->coverSource = 'Films on Demand';
			}elseif (stripos($name, 'proquest') !== false || stripos($name, 'ebrary') !== false){
				$indexingProfile->coverSource = 'Proquest';
			}elseif (stripos($name, 'Creative Bug') !== false || stripos($name, 'CreativeBug') !== false){
				$indexingProfile->coverSource = 'CreativeBug';
			}elseif (stripos($sourceName, 'chnc') !== false){
				$indexingProfile->coverSource = 'CHNC';
			}elseif (stripos($name, 'rbdigital') !== false || stripos($name, 'zinio') !== false){
				$indexingProfile->coverSource = 'Zinio';
			}else{
				$indexingProfile->coverSource = 'SideLoad General';
			}
			$indexingProfile->update();
		}
	}

	function setCatalogURLs(){
		global $configArray;
		$error     = [];
		$localPath = $configArray['Site']['local'];
		$sitesPath = realpath("$localPath/../../sites/");
		$sites     = array_diff(scandir($sitesPath), ['..', '.']);
		$library   = new Library();
		$libraries = $library->fetchAll();
		/** @var Library $library */
		foreach ($libraries as $library){
			//Attempt to match left-most subdomain
			if (!empty($library->subdomain)){
				foreach ($sites as $i => $urlLink){
					if (preg_match('/^' . preg_quote($library->subdomain) . '[23]??\..*/si', $urlLink)){
						// [23]?? to match marmot test urls
						$library->catalogUrl = $urlLink;
						$library->update();
						unset($sites[$i]);
						break;
					}
				}
				if (empty($library->catalogUrl)){
					//Now attempt to match subdomain interior of the url
					foreach ($sites as $i => $urlLink){
						if (preg_match('/\.' . preg_quote($library->subdomain) . '\..*/si', $urlLink)){
							$library->catalogUrl = $urlLink;
							$library->update();
							unset($sites[$i]);
							break;
						}
					}
				}
			}
			if (empty($library->catalogUrl)){
				$error[] = 'Did not set an URL for ' . $library->subdomain;
			}
		}
		$location  = new Location();
		$locations = $location->fetchAll();
		/** @var Location $location */
		foreach ($locations as $location){
			foreach ($sites as $i => $urlLink){
				if (!empty($location->code)){
					if (preg_match('/^' . preg_quote($location->code) . '[23]??\..*/si', $urlLink)){
						$location->catalogUrl = $urlLink;
						$location->update();
						unset($sites[$i]);
						break;
					}
				}
			}
		}
		if (!empty($error)){
			print_r($error);
		}
	}

}
