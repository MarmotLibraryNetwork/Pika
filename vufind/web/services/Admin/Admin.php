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

require_once ROOT_DIR . '/Action.php';

abstract class Admin_Admin extends Action {

	function __construct(){
		//If the user isn't logged in, take them to the login page
		if (!UserAccount::isLoggedIn()) {
			require_once ROOT_DIR . '/services/MyAccount/Login.php';
			$myAccountAction = new MyAccount_Login();
			$myAccountAction->launch();
			exit();
		}

		//Make sure the user has permission to access the page
		$allowableRoles = $this->getAllowableRoles();
		if (!UserAccount::userHasRoleFromList($allowableRoles)){
			$this->display('../Admin/noPermission.tpl', 'Access Error');
			die;
		}
	}

	abstract function getAllowableRoles();

}
