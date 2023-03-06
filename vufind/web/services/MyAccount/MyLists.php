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
require_once ROOT_DIR . '/sys/LocalEnrichment/FavoriteHandler.php';
require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class MyAccount_MyLists extends MyAccount{

	function __construct(){
		$this->requireLogin = true;
		parent::__construct();
		$this->cache = new Pika\Cache();
	}

	function launch(){
		global $configArray;
		global $interface;
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		if (UserAccount::isLoggedIn()){
			$user           = UserAccount::getLoggedInUser();
			$staffUser      = $user->isStaff();
			$shortPageTitle = 'My Lists';
			$interface->assign('shortPageTitle', $shortPageTitle);
			//Load a list of lists
			$userListsData = $this->cache->get('user_lists_data_' . UserAccount::getActiveUserId());
			if ($userListsData == null || isset($_REQUEST['reload'])){
				$myLists = [];
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
				$tmpList          = new UserList();
				$tmpList->user_id = UserAccount::getActiveUserId();
				$tmpList->deleted = 0;
				$tmpList->orderBy('title ASC');

				if ($tmpList->find()){
					while ($tmpList->fetch()){
						$defaultSort = 'Title';
						if (!empty($tmpList->defaultSort)){
							switch ($tmpList->defaultSort){
								case 'recentlyAdded':
									$defaultSort = 'Recently Added';
									break;
								case 'dateAdded':
									$defaultSort = 'Date Added';
									break;
								case 'custom':
									$defaultSort = 'User Defined';
									break;
								default:
									$defaultSort = 'Title';
							}

						}
						$myLists[$tmpList->id] = [
							'name'        => $tmpList->title,
							'url'         => '/MyAccount/MyList/' . $tmpList->id,
							'id'          => $tmpList->id,
							'numTitles'   => $tmpList->numValidListItems(),
							'description' => $tmpList->description,
							'defaultSort' => $defaultSort,
							'isPublic'    => $tmpList->public,
						];
					}
				}
				$this->cache->set('user_lists_data_' . UserAccount::getActiveUserId(), $userListsData, $configArray['Caching']['user']);
				//$timer->logTime("Load Lists");
			}else{
				$myLists = $userListsData;
				//$timer->logTime("Load Lists from cache");
			}
			if (!empty($_REQUEST['myListActionHead'])){
				$actionToPerform = $_REQUEST['myListActionHead'];
				switch ($actionToPerform){
					case 'exportToExcel':
						$listId = $_REQUEST['myListActionData'];
						$list   = new UserList();
						if ($list->get('id', $listId)){
							require_once ROOT_DIR . '/services/MyAccount/MyList.php';
							MyAccount_MyList::exportToExcel($list);
						}
						break;
					case 'deleteSelectedLists':
						$listNumbers = $_REQUEST['myListActionData'];
						$listNumbers = substr($listNumbers, 0, strrpos($listNumbers, ','));

						$lists = explode(',', $listNumbers);
						foreach ($lists as $listId){
							$delId    = $listId;
							$list     = new UserList;
							$list->id = $delId;
							$list->removeAllListEntries();
							$list->delete();
						}
						header('Location: /MyAccount/MyLists');
						break;
					case 'clearSelectedLists':
						$listNumbers = $_REQUEST['myListActionData'];
						$listNumbers = substr($listNumbers, 0, strrpos($listNumbers, ','));

						$lists = explode(',', $listNumbers);
						foreach ($lists as $listId){
							$clearId  = $listId;
							$list     = new UserList;
							$list->id = $clearId;
							$list->removeAllListEntries();
						}
						header('Location: /MyAccount/MyLists');
						break;
				}
			}

			$interface->assign('myLists', $myLists);
			$interface->assign('staff', $staffUser);

			$this->display('../MyAccount/myLists.tpl', 'My Lists');
		}
	}

}