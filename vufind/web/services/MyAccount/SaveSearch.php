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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class SaveSearch extends MyAccount
{
	function launch()
	{
		global $configArray;

		$searchId = null;
		$todo = 'addSearch';
		if (isset($_REQUEST['delete']) && $_REQUEST['delete']) {
			$todo = 'deleteSearch';
			$searchId = $_REQUEST['delete'];
		}
		// If for some strange reason the user tries
		//    to do both, just focus on the save.
		if (isset($_REQUEST['save']) && $_REQUEST['save']) {
			$todo = 'addSearch';
			$searchId = $_REQUEST['save'];
		}

		$search = new SearchEntry();
		$search->id = $searchId;
		if ($search->find(true)) {
			// Found, make sure this is a search from this user
			if ($search->session_id == session_id() || $search->user_id == UserAccount::getActiveUserId()) {
				// Call whichever function is required below
				$this->$todo($search);
			}
		}

		// If we are in "edit history" mode, stay in Search History:
		if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'history') {
			header("Location: /Search/History");
		} else {
			// If the ID wasn't found, or some other error occurred, nothing will
			//   have processed be now, let the error handling on the display
			//   screen take care of it.
			header("Location: /Search/Results?saved=$searchId");
		}
	}


	/**
	 * Add a search to the database
	 *
	 * @param SearchEntry $search
	 */
	private function addSearch($search)
	{
		if ($search->saved != 1) {
			$search->user_id = UserAccount::getActiveUserId();
			$search->saved = 1;
			$search->update();
		}
	}

	/**
	 * Delete a search from the database
	 *
	 * @param SearchEntry $search
	 */
	private function deleteSearch($search)
	{
		if ($search->saved != 0) {
			$search->saved = 0;
			$search->update();
		}
	}
}
