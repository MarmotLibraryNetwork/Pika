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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_Administrators extends ObjectEditor {
	function getObjectType(){
		return 'User';
	}

	function getToolName(){
		return 'Administrators';
	}

	function getPageTitle(){
		return 'Administrators';
	}

	function getAllObjects($orderBy = null){
		$admin = new User();
		$admin->query('SELECT DISTINCT user.* FROM user INNER JOIN user_roles ON user.id = user_roles.userId ORDER BY ' . ( $orderBy ?? 'cat_username' ));
		$adminList = array();
		while ($admin->fetch()){
			$homeLibrary            = Library::getLibraryForLocation($admin->homeLocationId);
			$admin->homeLibraryName = empty($homeLibrary->displayName) ? 'Unknown' : $homeLibrary->displayName;
			$location               = new Location();
			$admin->homeLocation    = $location->get($admin->homeLocationId) ? $location->displayName : 'Unknown';
			$adminList[$admin->id]  = clone $admin;
		}
		return $adminList;
	}

	function getExistingObjectById($id){
		/** @var User $user */
		$user                  = parent::getExistingObjectById($id);
		$user->homeLibraryName = $user->getHomeLibrarySystemName();
		$location              = new Location();
		$user->homeLocation    = $location->get($user->homeLocationId) ? $location->displayName : 'Unknown';
		return $user;
	}

	function getObjectStructure(){
		return (new User)->getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return ['userAdmin'];
	}

	function canAddNew(){
		return false;
	}

	function canDelete(){
		return false;
		// Delete action would delete User object rather than admin roles
	}

	function customListActions(){
		return [
			['label' => 'Add Administrator', 'action' => 'addAdministrator'],
		];
	}

	function addAdministrator(){
		global $interface;
		$interface->setTemplate('addAdministrator.tpl');
	}

	function editObject($objectAction, $structure){
		$roleNotAllowedToOverlap = ['opacAdmin', 'libraryAdmin', 'libraryManager', 'locationManager'];
		$roles                   = new Role();
		$roles->whereAddIn('roleId', $_REQUEST['roles'], 'string');
		$roleNames       = $roles->fetchAll('name');
		$moreThanOneRole = array_intersect($roleNames, $roleNotAllowedToOverlap);
		if (count($moreThanOneRole) > 1){
			$_SESSION['lastError'] = 'This administrator may only have one of the these roles at a time : <strong>' . implode(', ', $moreThanOneRole) . '</strong>';
			header("Location: {$_SERVER['REQUEST_URI']}");
			die();
		}

		parent::editObject($objectAction, $structure);
	}


	function processNewAdministrator(){
		global $interface;
		$user = UserAccount::getActiveUserObj();

		$barcode         = trim($_REQUEST['barcode']);
		$interface->assign('barcode', $barcode);
		$interface->setTemplate('addAdministrator.tpl');

		if (!empty($_REQUEST['roles'])){
			$newAdmin = new User();
			$newAdmin->get('barcode', $barcode);
			$success = ($newAdmin->N == 1); // Call success if we found exactly one user (multiple users is an error also)
			if ($newAdmin->N == 0){
				//Try searching ILS for user if no user was found
				$newAdmin = UserAccount::findNewUser($barcode);
				$success  = is_a($newAdmin, 'User') && !empty($newAdmin->ilsUserId);
				if (!$success){
					$interface->assign('error', 'Could not find a user with that barcode, and did not find a user in ILS with that barcode or can\'t look up new users in ILS');
					return;
				}
			}
			if ($success){
				require_once ROOT_DIR . '/sys/Administration/UserRoles.php';
				$existingRoles         = new UserRoles();
				$existingRoles->userId = $newAdmin->id;
				if ($existingRoles->count() === 0){
					$newAdmin->roles = $_REQUEST['roles'];
					$newAdmin->update();
					global $configArray;
					header("Location: /Admin/{$this->getToolName()}");
					die;
				}else{
					$interface->assign('error', $barcode . ' is already an administrator.');
				}
			}elseif ($newAdmin->N == 0){
				$interface->assign('error', 'Could not find a user with that barcode. (The user needs to have logged in at least once.)');
			}elseif ($newAdmin->N > 1){
				$interface->assign('error', "Found multiple ({$newAdmin->N}) users with that barcode. (The database needs to be cleaned up.)");
			}else{
				$interface->assign('error', 'Unknown error while looking for administrator');
			}
		}else{
			$interface->assign('error', 'No roles assigned to new administrator');
		}
	}

	function getInstructions(){
		return 'For more information about what each role can do, see the <a href="https://marmot-support.atlassian.net/l/c/zJP1kcDf">online documentation</a>.';
	}

}
