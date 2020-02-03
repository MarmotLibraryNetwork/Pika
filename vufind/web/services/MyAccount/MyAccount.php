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

abstract class MyAccount extends Action {
	protected $requireLogin = true;

	function __construct() {

		if ($this->requireLogin && !UserAccount::isLoggedIn()) {
			require_once ROOT_DIR . '/services/MyAccount/Login.php';
			$myAccountAction = new MyAccount_Login();
			$myAccountAction->launch();
			exit();
		}

		// Hide Covers when the user has set that setting on an Account Page
		$this->setShowCovers();
	}

	/**
	 * @param string $mainContentTemplate  Name of the SMARTY template file for the main content of the Account Page
	 * @param string $pageTitle            What to display is the html title tag, gets ran through the translator
	 * @param string|null $sidebar         Sets the sidebar on the page to be displayed
	 */
	function display($mainContentTemplate, $pageTitle='My Account', $sidebar='Search/home-sidebar.tpl') {
		parent::display($mainContentTemplate, translate($pageTitle), $sidebar);
	}
}
