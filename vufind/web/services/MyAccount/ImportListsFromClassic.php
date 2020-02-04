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
 * Imports Lists for a user from prior catalog (Sierra WebPAC, Encore, Etc).
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/26/14
 * Time: 10:35 PM
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class ImportListsFromClassic extends MyAccount {

	/**
	 * Process parameters and display the page.
	 *
	 * @return void
	 * @access public
	 */
	public function launch(){
		global $interface;
		$user = UserAccount::getLoggedInUser();

		//Import Lists from the ILS
		$results = $user->importListsFromIls();
		$interface->assign('importResults', $results);

		//Reload all lists for the user
		$listList = $user->getLists();
		$interface->assign('listList', $listList);

		$this->display('listImportResults.tpl', 'Import Lists');
	}

}
