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
 * An entry within the User Id
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 3/10/14
 * Time: 3:50 PM
 */
require_once 'DB/DataObject.php';

class UserListEntry extends DB_DataObject {
	public $__table = 'user_list_entry';     // table name
	public $id;                              // int(11)  not_null primary_key auto_increment
	public $groupedWorkPermanentId;          // NOTE: this isn't *only* groupedWork ids anymore. This can be archive ids too.
	public $listId;                          // int(11)  multiple_key
	public $notes;                           // blob(65535)  blob
	public $dateAdded;                       // timestamp(19)  not_null unsigned zerofill binary timestamp
	public $weight;                          //Where to position the entry in the overall list

	/**
	 * @return bool
	 */
	function insert($checkListSize = true, $updateParentList = true){
		if ($checkListSize || $updateParentList){
			if (!empty($this->listId)){
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
				$list = new UserList();
				if (!$list->get($this->listId)){
					return false;
				}
			}else{
				return false;
			}
		}

		if ($checkListSize){
			$listCount = $list->numValidListItems();
			if ($listCount >= 2000){
				return false;
			}
		}
		$result = parent::insert();
		if ($result){
			$this->flushUserListBrowseCategory();
			if ($updateParentList && $list->N){
				$list->update();// Update the parent List's dateUpdated
			}
		}
		return $result;
	}

	/**
	 * @param bool $dataObject
	 * @return bool|int|mixed
	 */
	function update($dataObject = false, $updateParentList = true){
		$result = parent::update($dataObject);
		if ($result && $updateParentList){
			$this->flushUserListBrowseCategory();
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			$list     = new UserList();
			$list->id = $this->listId;
			if ($list->find(true)){
				$list->update();// Update the parent List's dateUpdated
			}
		}
		return $result;
	}

	/**
	 * @param bool $useWhere
	 * @return bool|int|mixed
	 */
	function delete($useWhere = false){
		$result = parent::delete($useWhere);
		if ($result){
			$this->flushUserListBrowseCategory();
		}
		return $result;
	}

	private function flushUserListBrowseCategory(){
		if (!empty($this->listId)){
			// Check if the list is a part of a browse category and clear the cache.
			require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
			$userListBrowseCategory               = new BrowseCategory();
			$userListBrowseCategory->sourceListId = $this->listId;
			if ($userListBrowseCategory->find()){
				while ($userListBrowseCategory->fetch()){
					$userListBrowseCategory->deleteCachedBrowseCategoryResults();
				}
			}
		}
	}
}
