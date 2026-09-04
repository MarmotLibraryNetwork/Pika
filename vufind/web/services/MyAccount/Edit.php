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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class MyAccount_Edit extends MyAccount {

	private function saveChanges(){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$userListEntry     = new UserListEntry();
		$userListEntry->id = $_REQUEST['listEntry'];
		if ($userListEntry->find(true)){
			$userListEntry->notes = strip_tags($_REQUEST['notes']);
			$userListEntry->update();
		}
	}

	/**
	 * The list display parameters (page, page size, sort, filter) the user was
	 * viewing before they started editing, validated the same way MyList validates them.
	 *
	 * @return array
	 */
	private function getListParams(){
		$params = [];
		if (!empty($_REQUEST['pagesize']) && is_numeric($_REQUEST['pagesize'])){
			$params['pagesize'] = $_REQUEST['pagesize'];
		}
		if (!empty($_REQUEST['page']) && is_numeric($_REQUEST['page'])){
			$params['page'] = $_REQUEST['page'];
		}
		if (!empty($_REQUEST['sort']) && in_array($_REQUEST['sort'], ['author', 'title', 'dateAdded', 'recentlyAdded', 'custom'])){
			$params['sort'] = $_REQUEST['sort'];
		}
		if (!empty($_REQUEST['filter'])){
			$params['filter'] = $_REQUEST['filter'];
		}
		return $params;
	}

	function launch($msg = null){
		global $interface;

		// Save Data
		$listId = $_REQUEST['list_id'] ?? null;
		if (is_array($listId)){
			$interface->assign('error', 'Invalid List ID.');
		}
		elseif (!empty($listId) && ctype_digit($listId)){
			// The page the user came from; carried through the form so that saving, or
			// backing out with the Return to List button, lands on the same page,
			// page size, sort and filter of the list they were viewing.
			$params         = $this->getListParams();
			$currentListURL = '/MyAccount/MyList/' . $listId . (empty($params) ? '' : '?' . http_build_query($params));
			$interface->assign('params', $params);
			$interface->assign('currentListURL', $currentListURL);

			if (isset($_POST['submit'])){
				$this->saveChanges();

				// After changes are saved, send the user back to the list they were
				// viewing when they started editing.
				header('Location: ' . $currentListURL);
				exit();
			}

			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			$userList     = new UserList();
			$userList->id = $listId;
			if ($userList->find(true)){
				$interface->assign('list', $userList);

				$id = $_GET['titleIdForListEntry'];
				if (!empty($id)){
					// Item ID
					$interface->assign('recordId', $id);

					// The link into this page carries the id the record driver put in the DOM;
					// user_list_entry stores a different form of it for archive entries.
					require_once ROOT_DIR . '/sys/Islandora2/Functions.php';
					$entryId = userListEntryIdFromDomId((string)$id);

					switch (parseUserListEntryId($entryId)['type']){
						case USER_LIST_ENTRY_ARCHIVE_OBJECT:
							require_once ROOT_DIR . '/RecordDrivers/Islandora2Driver.php';
							$interface->assign('recordDriver', new Islandora2Driver($entryId));
							break;
						case USER_LIST_ENTRY_TAXONOMY_TERM:
							require_once ROOT_DIR . '/RecordDrivers/Islandora2TaxonomyTermDriver.php';
							// The driver reads the tid and the vocabulary straight out of the
							// stored id, so editing a term's notes costs no Islandora lookup.
							$interface->assign('recordDriver', new Islandora2TaxonomyTermDriver($entryId));
							break;
						default:
							// Grouped Works (Catalog Items)
							require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
							$groupedWorkDriver = new GroupedWorkDriver($entryId);
							if ($groupedWorkDriver->isValid){
								$interface->assign('recordDriver', $groupedWorkDriver);
							}
							break;
					}

					// Retrieve saved information about record
					require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
					$userListEntry                         = new UserListEntry();
					$userListEntry->groupedWorkPermanentId = $entryId;
					$userListEntry->listId                 = $listId;
					if ($userListEntry->find(true)){
						$interface->assign('listEntry', $userListEntry);
					}else{
						$interface->assign('error', 'The item you selected is not part of the selected list.');
					}
				}else{
					$interface->assign('error', 'No ID for the list item.');
				}
			}else{
				$interface->assign('error', "List {$listId} was not found.");
			}
		}else{
			$interface->assign('error', 'Invalid List ID.');
		}

		$this->display('editListTitle.tpl', 'Edit List Entry');
	}
}

