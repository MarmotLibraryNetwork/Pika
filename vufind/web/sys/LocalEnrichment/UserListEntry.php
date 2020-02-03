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
 * An entry within the User Id
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 3/10/14
 * Time: 3:50 PM
 */
require_once 'DB/DataObject.php';
class UserListEntry extends DB_DataObject{
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
	function insert()
	{
		$result = parent::insert();
		if ($result) {
			$this->flushUserListBrowseCategory();
		}
		return $result;
	}

	/**
	 * @param bool $dataObject
	 * @return bool|int|mixed
	 */
	function update($dataObject = false)
	{
		$result = parent::update($dataObject);
		if ($result) {
			$this->flushUserListBrowseCategory();
		}
		return $result;
	}

	/**
	 * @param bool $useWhere
	 * @return bool|int|mixed
	 */
	function delete($useWhere = false)
	{
		$result = parent::delete($useWhere);
		if ($result) {
			$this->flushUserListBrowseCategory();
		}
		return $result;
	}

	private function flushUserListBrowseCategory(){
		// Check if the list is a part of a browse category and clear the cache.
		require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
		$userListBrowseCategory = new BrowseCategory();
		$userListBrowseCategory->sourceListId = $this->listId;
		if ($userListBrowseCategory->find()) {
			while ($userListBrowseCategory->fetch()) {
				$userListBrowseCategory->deleteCachedBrowseCategoryResults();
			}
		}
	}
}
