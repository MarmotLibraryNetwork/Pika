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
 * Home Page for Account Functionality
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 10/10/13
 * Time: 1:11 PM
 */
require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/Suggestions.php';
class MyAccount_Home extends MyAccount{
	function launch(){
		global $interface;

		// The script should only execute when a user is logged in, otherwise it calls Login.php
		if (UserAccount::isLoggedIn()){
			$user = UserAccount::getLoggedInUser();
			// Check to see if the user has rated any titles
			$interface->assign('hasRatings', $user->hasRatings());

			$this->display('home.tpl');
		}
	}
}
