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

require_once ROOT_DIR . '/services/Admin/Admin.php';

class Admin_UserAdmin extends Admin_Admin {

	function launch(){
		global $interface;
		if (isset($_REQUEST['userAction'])){
			if ($_REQUEST['userAction'] == 'resetDisplayName'){
				$barcode = trim($_REQUEST['barcode']);
				if (ctype_alnum($barcode)){
					$patron          = new User();
					$patron->barcode = $barcode;
					if ($patron->find(true)){
						$previousDisplayName = $patron->displayName;
						if ($patron->setUserDisplayName()){
							$interface->assign('success', 'Display Name was reset to '. $patron->displayName);
						} elseif ($patron->displayName == $previousDisplayName){
							$interface->assign('success', 'Display Name already set to '. $patron->displayName);
						} else {
							$interface->assign('error', 'Failed to reset user Display Name.');
						}
					}else{
						$interface->assign('error', 'Did not find a user with that barcode.');
					}
				}else{
					$interface->assign('error', 'Invalid barcode.');
				}
			}elseif ($_REQUEST['userAction'] == 'showDuplicates'){
				if (UserAccount::userHasRole('userAdmin')){ // Double check that authorized admin user is requesting the action
					$barcode = trim($_REQUEST['barcode']);
					if (ctype_alnum($barcode)){
						$patron          = new User();
						$patron->barcode = $barcode;
						$count           = $patron->find();
						if ($count > 1){
							require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
							require_once ROOT_DIR . '/sys/Account/ReadingHistoryEntry.php';
							require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
							require_once ROOT_DIR . '/sys/LocalEnrichment/NotInterested.php';
							require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';
							require_once ROOT_DIR . '/sys/Administration/UserRoles.php';

							$duplicateUsers = [];
							while ($patron->fetch()){
								// Check for user created and user related data for potential duplicates users
								// that would need to be re-assigned to delete the user.
								$patron->safeToDelete              = false;
								$userList                          = new UserList();
								$userList->user_id                 = $patron->id;
								$patron->userListCount             = $userList->count();
								$userReadingHistoryEntries         = new ReadingHistoryEntry();
								$userReadingHistoryEntries->userId = $patron->id;
								$patron->readingHistoryCount       = $userReadingHistoryEntries->count();
								$userReviews                       = new UserWorkReview();
								$userReviews->userId               = $patron->id;
								$patron->userReviewsCount          = $userReviews->count();
								$userNotInterested                 = new NotInterested();
								$userNotInterested->userId         = $patron->id;
								$patron->notInterestedCount        = $userNotInterested->count();
								$userTags                          = new UserTag();
								$userTags->userId                  = $patron->id;
								$patron->userTagsCount             = $userTags->count();
								$patron->linkedUsersCount          = count($patron->getLinkedUserObjects());
								$role                              = new UserRoles();
								$role->userId                      = $patron->id;
								$patron->roleCount                 = $role->count();
								$usingPins                         = $patron->getAccountProfile()->usingPins();
								$patron->passwordSet               = $usingPins && !empty($patron->password);
								$patron->safeToDelete              = !($patron->userListCount || $patron->readingHistoryCount || $patron->userReviewsCount || $patron->notInterestedCount || $patron->userTagsCount || $patron->linkedUsersCount || $patron->roleCount) && !$patron->passwordSet;
								$duplicateUsers[]                  = clone $patron;
							}
							$interface->assign([
								'duplicateUsers'   => $duplicateUsers,
								'duplicateBarcode' => $barcode
							]);
						}elseif ($count == 1){
							$interface->assign('duplicateError', 'Only one user with that barcode.');
						}else{
							$interface->assign('duplicateError', 'Did not find a user with that barcode : ' . $barcode);
						}
					}
				}
			}elseif ($_REQUEST['userAction'] == 'deleteDuplicate'){
				if (UserAccount::userHasRole('userAdmin')){ // Double check that authorized admin user is requesting the action
					$userId = $_REQUEST['userId'];
					if (ctype_digit($userId) && !empty($userId) && $userId > 0){ // Validate Id number to prevent malicious input
						$patron     = new User();
						$patron->id = $userId;
						if ($patron->find(true) && $patron->N === 1){ // Confirm only one user was found
							global $pikaLogger;
							$logger = $pikaLogger->withName(__CLASS__);
							$logger->warning('FYI: Admin user ' .UserAccount::getUserDisplayName() . " deleting probable duplicate user $patron->id $patron->displayName");

							$patron->whereAdd("id = $userId");
							$patron->limit(1);
							$success = $patron->delete(true);
							$logger->warning("FYI: Probable duplicate user $userId deletion " . ($success ? 'succeeded' : 'failed'));
							if ($success == 1){
								$interface->assign('duplicateSuccess', 'User Account deleted.');
							}else{
								$interface->assign('duplicateError', 'Failed to delete User Account.');
							}
						} else{
							$interface->assign('duplicateError', 'Did not find a user with that id.');
						}
					}else{
						$interface->assign('duplicateError', 'Invalid user Id');
					}
				}
			}else{
				$interface->assign('error', 'Invalid user action.');
			}
		}

		$this->display('userAdmin.tpl', 'User Admin');
	}

	function getAllowableRoles(){
		return ['userAdmin', 'opacAdmin', 'libraryAdmin', 'libraryManager', 'locationManager'];
	}

}