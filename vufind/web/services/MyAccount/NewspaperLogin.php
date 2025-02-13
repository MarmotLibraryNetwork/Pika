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
		/** @var Library $library */
		$library = UserAccount::getUserHomeLibrary();
		$column = $this->newpaperUrl;
		//TODO: validate for url structure
		if (!empty($column) && !empty($library->$column)){
			header('Location: ' . $library->$column);
			exit();
		}else{
			$this->display('../NewspaperLogin/noAccess.tpl', 'No Access');
		}
	}


	public function __construct(){
		/** @var Library $library */
		global $library;
		global $interface;
		$interface->assign([
			'newspaperName'        => $this->newpaperName,
			'newspaperLibraryName' => $library->displayName,
		]);
		// Need to set the display name for the information blurb on the login page because the traditional
		// $librarySystemName template variable is set to the location display name when the location is known.
		parent::__construct();
	}
}