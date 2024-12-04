<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
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

include_once ROOT_DIR . '/services/Admin/Admin.php';
include_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';

class NPRBestBooksLists extends Admin_Admin {

	function launch(){
		global $interface;
		global $configArray;

		$availableLists = range((int)date("Y"), 2013, -1);

		$interface->assign('availableLists', $availableLists);

		$isListSelected = !empty($_REQUEST['selectedList']);
		$selectedList   = null;
		if ($isListSelected){
			$selectedList = $_REQUEST['selectedList'];
			$interface->assign('selectedListName', $selectedList);

			if (isset($_REQUEST['submit'])){
				//Find and update the correct Pika list, creating a new list as needed.
				require_once ROOT_DIR . '/services/API/ListAPI.php';
				$listApi = new ListAPI();
				$results = $listApi->createUserListFromNPRBestBooks($selectedList);
				if (!$results['success']){
					$interface->assign('error', $results['message']);
				}else{
					$interface->assign('successMessage', $results['message']);
				}
			}
		}


		// Fetch lists after any updating has been done

		// Get user id and NPR Book lists already created
		$listTitlePrefix      = \ExternalEnrichment\NPRBestBooks::NPRListTitlePrefix;
		$catalog              = CatalogFactory::getCatalogConnectionInstance();
		$nyTimesUser          = new User();
		$nyTimesUser->barcode = $catalog->accountProfile->usingPins() ? $configArray['NYT_API']['pika_username'] : $configArray['NYT_API']['pika_password'];
		if ($nyTimesUser->find(1)){
			// Get User Lists
			$nyTimesUserLists          = new UserList();
			$nyTimesUserLists->user_id = $nyTimesUser->id;
			$nyTimesUserLists->whereAdd("title like '$listTitlePrefix - %'");
			$nyTimesUserLists->orderBy('title');
			$pikaLists = $nyTimesUserLists->fetchAll();

			$interface->assign('pikaLists', $pikaLists);
		}
		$interface->assign('listsTitle', $listTitlePrefix);

		$this->display('nprBestBooksLists.tpl', $listTitlePrefix . 's');
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'libraryManager', 'contentEditor'];
	}
}