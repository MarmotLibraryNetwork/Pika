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
 * Allow the user to select an interface to use to access the site.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/8/13
 * Time: 2:32 PM
 */
class MyAccount_SelectInterface extends Action {
	function launch(){
		global $interface;

		global $locationSingleton;
		$physicalLocation  = $locationSingleton->getActiveLocation();
		$redirectLibraryId = null;
		$user              = UserAccount::getLoggedInUser();
		if (!empty($_REQUEST['library']) && ctype_digit($_REQUEST['library'])){
			$redirectLibraryId = $_REQUEST['library'];
		}elseif (!is_null($physicalLocation)){
			$redirectLibraryId = $physicalLocation->libraryId; // automatically redirect from within a library building
		}elseif (!empty($user->preferredLibraryInterface) && is_numeric($user->preferredLibraryInterface)){
			$redirectLibraryId = $user->preferredLibraryInterface; // automatically redirect when user's preference is already set.
		}elseif (!empty($_COOKIE['PreferredLibrarySystem'])){
			$redirectLibraryId = $_COOKIE['PreferredLibrarySystem'];
		}

		if ($redirectLibraryId != null){
			global $pikaLogger;
			$pikaLogger->debug("Selected library interface $redirectLibraryId");
			$redirectLibrary = new Library();
			if ($redirectLibrary->get($redirectLibraryId) && !empty($redirectLibrary->catalogUrl)){
				$baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $redirectLibrary->catalogUrl;

				if (!empty($_REQUEST['gotoModule'])){
					$baseUrl .= '/' . $_REQUEST['gotoModule'];
				}
				if (!empty($_REQUEST['gotoAction'])){
					$gotoAction = $_REQUEST['gotoAction'];
					$baseUrl    .= '/' . $_REQUEST['gotoModule'];
				}

				if (isset($_REQUEST['rememberThis']) && isset($_REQUEST['submit'])){
					if ($user){
						$user->preferredLibraryInterface = $redirectLibraryId;
						$user->update();
						$_SESSION['userinfo'] = serialize($user);
					}
					//Set a cookie to remember the location when not logged in
					//Remember for a year
					setcookie('PreferredLibrarySystem', $redirectLibraryId, time() + 60 * 60 * 24 * 365, '/');
				}

				header('Location:' . $baseUrl);
				die;
			}else{
				$interface->assign('error', 'Failed to find library URL.');
			}
		}

		$library = new Library();
		$library->whereAdd('catalogUrl IS NOT NULL AND catalogUrl != ""');
		$library->orderBy('displayName');
		$libraries = $library->fetchAll('libraryId', 'displayName');
		$interface->assign('libraries', $libraries);

		if (!empty($_REQUEST['gotoModule'])){
			$interface->assign('gotoModule', $_REQUEST['gotoModule']);
		}
		if (!empty($_REQUEST['gotoAction'])){
			$interface->assign('gotoAction', $_REQUEST['gotoAction']);
		}

		$this->display('selectInterface.tpl', 'Select Library Catalog', false);
	}
}
