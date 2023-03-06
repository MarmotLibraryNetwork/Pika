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
		if (isset($_REQUEST['userAction'])){
			if ($_REQUEST['userAction'] == 'resetDisplayName'){
				$barcode = trim($_REQUEST['barcode']);
				if (ctype_alnum($barcode)){
					$patron          = new User();
					$patron->barcode = $barcode;
					if ($patron->find(true)){
						$previousDisplayName = $patron->displayName;
						if ($patron->setUserDisplayName()){
							global $interface;
							$interface->assign('success', 'Display Name was reset to '. $patron->displayName);
						} elseif ($patron->displayName == $previousDisplayName){
							global $interface;
							$interface->assign('success', 'Display Name already set to '. $patron->displayName);
						} else {
							global $interface;
							$interface->assign('error', 'Failed to reset user Display Name.');
						}
					}else{
						global $interface;
						$interface->assign('error', 'Did not find a user with that barcode.');
					}
				}else{
					global $interface;
					$interface->assign('error', 'Invalid barcode.');
				}
			}else{
				global $interface;
				$interface->assign('error', 'Invalid user action.');
			}
		}

		$this->display('userAdmin.tpl', 'User Admin');
	}

	function getAllowableRoles(){
		return ['userAdmin', 'opacAdmin', 'libraryAdmin', 'libraryManager', 'locationManager'];
	}

}