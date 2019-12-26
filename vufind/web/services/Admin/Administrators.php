<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
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

	function getAllObjects(){
		/** @var User $admin */
		$admin = new User();
		$admin->query('SELECT * FROM user INNER JOIN user_roles on user.id = user_roles.userId ORDER BY cat_password');
		$adminList = array();
		while ($admin->fetch()){

			$homeLibrary            = Library::getLibraryForLocation($admin->homeLocationId);
			$admin->homeLibraryName = $homeLibrary != null ? $homeLibrary->displayName : 'Unknown';

			$location             = new Location();
			$location->locationId = $admin->homeLocationId;
			$admin->homeLocation  = $location->find(true) ? $location->displayName : 'Unknown';

			$adminList[$admin->id] = clone $admin;
		}
		return $adminList;
	}

	function getExistingObjectById($id){
		$allAdmins = $this->getAllObjects(); // need to populate the library and location display names
		return $allAdmins[$id];
//		/** @var User $user */
//		$user = parent::getExistingObjectById($id);
//		$user->getHomeLibrary();
//		$user->homeLibraryName = $user->homeLibrary->displayName;
//		return $user;
	}

	function getObjectStructure(){
		return User::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		global $configArray;
		return $configArray['Catalog']['barcodeProperty'];
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return array('userAdmin');
	}

	function canAddNew(){
		return false;
	}

	function customListActions(){
		return array(
			array('label' => 'Add Administrator', 'action' => 'addAdministrator'),
		);
	}

	function addAdministrator(){
		global $interface;
		//Basic List
		$interface->setTemplate('addAdministrator.tpl');
	}

	function processNewAdministrator(){
		global $interface;
		global $configArray;
		$barcodeProperty = $configArray['Catalog']['barcodeProperty'];
		$barcode         = trim($_REQUEST['barcode']);
		$interface->assign('barcode', $barcode);

		if (!empty($_REQUEST['roles'])){
			$newAdmin        = new User();
			$newAdmin->get($barcodeProperty, $barcode);
			if ($newAdmin->N == 1){
				require_once ROOT_DIR . '/sys/Administration/UserRoles.php';
				$existingRoles         = new UserRoles();
				$existingRoles->userId = $newAdmin->id;
				if ($existingRoles->count() === 0){
					$newAdmin->roles = $_REQUEST['roles'];
					$newAdmin->update();
					global $configArray;
					header("Location: {$configArray['Site']['path']}/Admin/{$this->getToolName()}");
					die;
				}else{
					$interface->assign('error', $barcode . ' is already an administrator.');
				}
			}elseif ($newAdmin->N == 0){
				$interface->assign('error', 'Could not find a user with that barcode. (The user needs to have logged in at least once.)');
			}else{
				$interface->assign('error', "Found multiple users with that barcode {$newAdmin->N}. (The database needs to be cleaned up.)");
			}
		}else{
			$interface->assign('error', 'No roles assigned to new administrator');
		}

		$interface->setTemplate('addAdministrator.tpl');
	}

	function getInstructions(){
		return 'For more information about what each role can do, see the <a href="https://docs.google.com/spreadsheets/d/1sPR8mIidkg00B2XzgiEq1MMDO3Y2ZOZNH-y_xonN-zA">online documentation</a>.';
	}

	function getListInstructions(){
		return $this->getInstructions();
	}
}