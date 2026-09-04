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

/**
 * A class that allows generation of Lists from the New York Times API
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 8/29/2016
 * Time: 12:07 PM
 */
include_once ROOT_DIR . '/services/Admin/Admin.php';
include_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';

class NYTLists extends Admin_Admin {

	function launch(){
		global $interface;
		global $configArray;

		//Display a list of available lists within the New York Times API
		if (!isset($configArray['NYT_API']) || empty($configArray['NYT_API']['books_API_key'])){
			$interface->assign('error', 'The New York Times API is not configured properly, create a books_API_key in the NYT_API section');
		}else{
			$api_key = $configArray['NYT_API']['books_API_key'];

			// instantiate class with api key
			$nyt_api = new ExternalEnrichment\NYTApi($api_key);

			//Get the raw response from the API with a list of all the names
			//Convert into an object that can be processed
			$availableLists = $nyt_api->getLists();

			$interface->assign('availableLists', $availableLists);

			if (isset($_REQUEST['updateAllLists'])){
				//Build or update a Pika list for every list The New York Times is currently publishing
				require_once ROOT_DIR . '/services/API/ListAPI.php';
				$listApi = new ListAPI();
				$results = $listApi->updateAllUserListsFromNYT();
				if (!$results['success']){
					$interface->assign('error', $results['message']);
				}else{
					$interface->assign('successMessage', $this->getUpdateAllListsMessage($results));
				}
			}

			$isListSelected = !empty($_REQUEST['selectedList']);
			$selectedList   = null;
			if ($isListSelected){
				$selectedList = $_REQUEST['selectedList'];
				$interface->assign('selectedListName', $selectedList);

				if (isset($_REQUEST['submit'])){
					//Find and update the correct Pika list, creating a new list as needed.
					require_once ROOT_DIR . '/services/API/ListAPI.php';
					$listApi = new ListAPI();
					$results = $listApi->createUserListFromNYT($selectedList);
					if (!$results['success']){
						$interface->assign('error', $results['message']);
					}else{
						$interface->assign('successMessage', $results['message']);
					}
				}
			}

			// Fetch lists after any updating has been done

			// Get user id
			$catalog              = CatalogFactory::getCatalogConnectionInstance();
			$nyTimesUser          = new User();
			$nyTimesUser->barcode = $catalog->accountProfile->usingPins() ? $configArray['NYT_API']['pika_username'] : $configArray['NYT_API']['pika_password'];
			if ($nyTimesUser->find(true)){
				// Get User Lists
				$nyTimesUserLists          = new UserList();
				$nyTimesUserLists->user_id = $nyTimesUser->id;
				$nyTimesUserLists->deleted = 0;  // Don't include deleted lists
				$nyTimesUserLists->whereAdd('title like "NYT - %"');
				$nyTimesUserLists->orderBy('title');
				$pikaLists = $nyTimesUserLists->fetchAll();

				$interface->assign('pikaLists', $pikaLists);
			}
		}

		$this->display('nytLists.tpl', 'Lists from New York Times');
	}

	/**
	 * Turns the results of ListAPI::updateAllUserListsFromNYT() into a message that can be shown to the user, with a
	 * line for each list saying whether it was built and how many titles were added to it.
	 *
	 * @param array $results the response from ListAPI::updateAllUserListsFromNYT()
	 * @return string the message to display
	 */
	private function getUpdateAllListsMessage(array $results): string{
		$message = htmlspecialchars($results['message']);
		if (empty($results['lists'])){
			return $message;
		}
		$message .= '<ul class="list-unstyled">';
		foreach ($results['lists'] as $listResult){
			if (!empty($listResult['success'])){
				//The message the List API builds for a list holds a link to that list, so it is displayed as it is; the
				//line break it uses to separate the count is not needed when each list is a line of its own.
				$listMessage = str_replace('<br> ', ' - ', $listResult['message']);
			}else{
				$listName    = empty($listResult['listName']) ? 'Unnamed list' : $listResult['listName'];
				$listMessage = 'Failed: ' . htmlspecialchars($listName) . ' - ' . htmlspecialchars($listResult['message']);
			}
			$message .= '<li>' . $listMessage . '</li>';
		}
		$message .= '</ul>';
		return $message;
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'libraryManager', 'contentEditor'];
	}
}
