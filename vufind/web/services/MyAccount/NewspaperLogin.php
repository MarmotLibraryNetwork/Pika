<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2025  Marmot Library Network
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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class NewspaperLogin extends MyAccount {

	public String $newpaperName = 'Newspaper'; // Overwrite this value in the extending class
	public String $newpaperUrl; // Set this value in the extending class

	function launch(){
		// Upon successful login redirect to newspaper subscription
		if (UserAccount::isLoggedIn()){
			/** @var Library $library */
			$library = UserAccount::getUserHomeLibrary();
			$column  = $this->newpaperUrl;
			if (!empty($column) && !empty($library->$column)){
				header('Location: ' . $library->$column);
				exit();
			} else {
				// This is for when the user has used an interface that has a subscription URL
				// (that is different their home library), but the user's home library doesn't have a
				// subscription URL
				$this->display('../NewspaperLogin/noAccess.tpl', 'No Access');
				exit;
			}
		}
	}

	public function __construct(){
		/** @var Library $library */
		global $library; // The interface's library
		$column = $this->newpaperUrl ?? null;
		if (!empty($column) && !empty($library->$column)) {
			global $interface;
			$interface->assign([
				'newspaperName'        => $this->newpaperName,
				'newspaperLibraryName' => $library->displayName,
			]);

			// Need to set the display name for the information blurb on the login page because the traditional
			// $librarySystemName template variable is set to the location display name when the location is known.
			parent::__construct();
		} else {
			$this->display('../NewspaperLogin/noAccess.tpl', 'No Access');
			exit;
		}
	}
}