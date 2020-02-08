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
require_once ROOT_DIR . '/services/MyResearch/lib/FavoriteHandler.php';
require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

/**
 * This class does not use MyResearch base class (we don't need to connect to
 * the catalog, and we need to bypass the "redirect if not logged in" logic to
 * allow public lists to work properly).
 * @version  $Revision$
 */
class MyAccount_MyList extends MyAccount {
	function __construct(){
		$this->requireLogin = false;
		parent::__construct();
	}

	function launch(){
		global $configArray;
		global $interface;

		// Fetch List object
		$listId = $_REQUEST['id'];
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$list     = new UserList();
		$list->id = $listId;

		//QUESTION : When does this intentionally come into play?
		// It looks to be a way for users to create a list with the number of their own choosing. plb 1-25-2016
		// Pascal this would create the default "My Favorites" list if none currently exists.
		if (!$list->find(true)){
			//TODO: Use the first list?
			$list          = new UserList();
			$list->user_id = UserAccount::getActiveUserId();
			$list->public  = false;
			$list->title   = "My Favorites";
		}

		// Ensure user has privileges to view the list
		if (!isset($list) || (!$list->public && !UserAccount::isLoggedIn())){
			require_once ROOT_DIR . '/services/MyAccount/Login.php';
			$myAccountAction = new MyAccount_Login();
			$myAccountAction->launch();
			exit();
		}
		if (!$list->public && $list->user_id != UserAccount::getActiveUserId()){
			//Allow the user to view if they are admin
			if (UserAccount::isLoggedIn() && UserAccount::userHasRole('opacAdmin')){
				//Allow the user to view
			}else{
				$this->display('invalidList.tpl', 'Invalid List');
				return;
			}
		}

		if (isset($_SESSION['listNotes'])){ // can contain results from bulk add titles action
			$interface->assign('notes', $_SESSION['listNotes']);
			unset($_SESSION['listNotes']);
		}

		// Perform an action on the list, but verify that the user has permission to do so.
		// and load the User object for the owner of the list (if necessary):
		$userCanEdit = false;
		if (UserAccount::isLoggedIn() && (UserAccount::getActiveUserId() == $list->user_id)){
			$listUser    = UserAccount::getActiveUserObj();
			$userCanEdit = $listUser->canEditList($list);
		}elseif ($list->user_id != 0){
			$listUser     = new User();
			$listUser->id = $list->user_id;
			if (!$listUser->find(true)){
				$listUser = false;
			}
		}else{
			$listUser = false;
		}


		if ($userCanEdit && (isset($_REQUEST['myListActionHead']) || isset($_REQUEST['myListActionItem']) || isset($_REQUEST['delete']))){
			if (!empty($_REQUEST['myListActionHead'])){
				$actionToPerform = $_REQUEST['myListActionHead'];
				switch ($actionToPerform){
					case 'makePublic':
						$list->public = 1;
						$list->update();
						break;
					case 'makePrivate':
						$list->public = 0;
						$list->update();
						break;
					case 'saveList':
						$list->title       = $_REQUEST['newTitle'];
						$list->description = strip_tags($_REQUEST['newDescription']);
						$list->defaultSort = $_REQUEST['defaultSort'];
						$list->update();
						break;
					case 'deleteList':
						$list->delete();
						header("Location: /MyAccount/Home");
						die();
						break;
					case 'bulkAddTitles':
						$notes                 = $this->bulkAddTitles($list);
						$_SESSION['listNotes'] = $notes;
						session_commit();
						break;
				}
			}elseif (!empty($_REQUEST['myListActionItem'])){
				$actionToPerform = $_REQUEST['myListActionItem'];
				switch ($actionToPerform){
					case 'deleteMarked':
						//get a list of all titles that were selected
						$itemsToRemove = $_REQUEST['selected'];
						foreach ($itemsToRemove as $id => $selected){
							//add back the leading . to get the full bib record
							$list->removeListEntry($id);
						}
						break;
					case 'deleteAll':
						$list->removeAllListEntries();
						break;
				}
				$list->update();
			}elseif (isset($_REQUEST['delete'])){
				$recordToDelete = $_REQUEST['delete'];
				$list->removeListEntry($recordToDelete);
				$list->update();
			}
			//Redirect back to avoid having the parameters stay in the URL.
			header("Location: /MyAccount/MyList/{$list->id}");
			die();

		}

		// Send list to template so title/description can be displayed:
		$interface->assign('favList', $list);
		$interface->assign('listSelected', $list->id);

		// Create a handler for displaying favorites and use it to assign
		// appropriate template variables:
		$interface->assign('allowEdit', $userCanEdit);
		$favList = new FavoriteHandler($list, $listUser, $userCanEdit);
		$favList->buildListForDisplay();

		$this->display('../MyAccount/list.tpl', isset($list->title) ? $list->title : 'My List');
		// this relative template path is used when an Archive object is in the list;
	}

	/**
	 * @param UserList $list
	 * @return array
	 */
	function bulkAddTitles($list){
		$numAdded        = 0;
		$notes           = array();
		$titlesToAdd     = $_REQUEST['titlesToAdd'];
		$titleSearches[] = preg_split("/\\r\\n|\\r|\\n/", $titlesToAdd);

		foreach ($titleSearches[0] as $titleSearch){
			$titleSearch = trim($titleSearch);
			if (!empty($titleSearch)){
				$_REQUEST['lookfor'] = $titleSearch;
				$isArchiveId         = strpos($titleSearch, ':') !== false;
				$_REQUEST['type']    = $isArchiveId ? 'IslandoraKeyword' : 'Keyword';// Initialise from the current search globals
				$searchObject        = SearchObjectFactory::initSearchObject($isArchiveId ? 'Islandora' : 'Solr');
				if (!empty($searchObject)){
					$searchObject->setLimit(1);
					$searchObject->init();
					$searchObject->clearFacets();
					$results = $searchObject->processSearch(false, false);
					if ($results['response'] && $results['response']['numFound'] >= 1){
						$firstDoc = $results['response']['docs'][0];
						//Get the id of the document
						$id = $isArchiveId ? $firstDoc['PID'] : $firstDoc['id'];
						$numAdded++;
						$userListEntry                         = new UserListEntry();
						$userListEntry->listId                 = $list->id;
						$userListEntry->groupedWorkPermanentId = $id;
						$existingEntry                         = false;
						if ($userListEntry->find(true)){
							$existingEntry = true;
						}
						$userListEntry->notes     = '';
						$userListEntry->dateAdded = time();
						if ($existingEntry){
							$userListEntry->update();
						}else{
							$userListEntry->insert();
						}
					}else{
						$notes[] = "Could not find a title matching " . $titleSearch;
					}
				}
			}
		}

		//Update solr
		$list->update();

		if ($numAdded > 0){
			$notes[] = "Added $numAdded titles to the list";
		}elseif ($numAdded === 0){
			$notes[] = 'No titles were added to the list';
		}

		return $notes;
	}
}
