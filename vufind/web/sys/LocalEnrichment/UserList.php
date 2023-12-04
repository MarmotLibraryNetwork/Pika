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
 * Database object for User Lists
 */
require_once 'DB/DataObject.php';

class UserList extends DB_DataObject {

	public $__table = 'user_list';						// table name
	public $id;																// int(11)	not_null primary_key auto_increment
	public $user_id;													// int(11)	not_null multiple_key
	public $title;														// string(200)	not_null
	public $description;											// string(500)
	public $created;													// datetime(19)	not_null binary
	public $public;														// int(11)	not_null
	public $deleted;
	public $dateUpdated;
	public $defaultSort;											// string(20) null

	// Used by FavoriteHandler as well//
	protected $userListSortOptions = [
		// URL_value => SQL code for Order BY clause
		'dateAdded'     => 'dateAdded ASC',
		'recentlyAdded' => 'dateAdded DESC',
		'custom'        => 'weight ASC',  // this puts items with no set weight towards the end of the list
		//'custom'        => 'weight IS NULL, weight ASC',  // this puts items with no set weight towards the end of the list
	];


	static function getObjectStructure(){
		$structure = [
			'id'          => [
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'primaryKey'  => true,
				'description' => 'The unique id of the e-pub file.',
				'storeDb'     => true,
				'storeSolr'   => false,
			],
			'title'       => [
				'property'    => 'title',
				'type'        => 'text',
				'size'        => 100,
				'maxLength'   => 255,
				'label'       => 'Title',
				'description' => 'The title of the item.',
				'required'    => true,
				'storeDb'     => true,
				'storeSolr'   => true,
			],
			'description' => [
				'property'    => 'description',
				'type'        => 'textarea',
				'label'       => 'Description',
				'rows'        => 3,
				'cols'        => 80,
				'description' => 'A brief description of the file for indexing and display if there is not an existing record within the catalog.',
				'required'    => false,
				'storeDb'     => true,
				'storeSolr'   => true,
			],
		];
		return $structure;
	}

	function numValidListItems() {
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$listEntry = new UserListEntry();
		$listEntry->listId = $this->id;

		// These conditions retrieve list items with a valid groupedwork ID or archive ID.
		// (This prevents list strangeness when our searches don't find the ID in the search indexes)
		$listEntry->whereAdd(
			'(
     (user_list_entry.groupedWorkPermanentId NOT LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT permanent_id FROM grouped_work) )
    OR
    (user_list_entry.groupedWorkPermanentId LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT pid FROM islandora_object_cache) )
)'
		);

		return $listEntry->count();
	}

//	function numValidListItems() {
//		$archiveItems = $this->num_archive_items();
//		$catalogItems = $this->num_titles();
//		return $archiveItems + $catalogItems;
//	);
//	function num_archive_items() {
//		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
//		//Join with grouped work to make sure we only load valid entries
//		$listEntry = new UserListEntry();
//		$listEntry->listId = $this->id;
//
//		require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
//		$islandoraObject = new IslandoraObjectCache();
//		$listEntry->joinAdd($islandoraObject);
//		return $listEntry->count();
//	}
//
//	function num_titles(){
//		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
//		//Join with grouped work to make sure we only load valid entries
//		$listEntry = new UserListEntry();
//		$listEntry->listId = $this->id;
//
//		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
//		$groupedWork = new GroupedWork();
//		$listEntry->joinAdd($groupedWork);
//		return $listEntry->count();
//	}

	function insert($createNow = true){
		if ($createNow) {
			$this->created     = time();
			$this->dateUpdated = time();
		}
		return parent::insert();
	}
	function update($dataObject = false){
		if ($this->created == 0){
			$this->created = time();
		}
		$this->dateUpdated = time();
		$result            = parent::update();
		if ($result) {
			$this->flushUserListBrowseCategory();
		}
		return $result;
	}
	function delete($useWhere = false){
		global $pikaLogger;
		global $user;
		$pikaLogger->notice("User $user->id marking list $this->id as deleted");

		$this->deleted     = 1;
		$this->dateUpdated = time();
		return parent::update();
		// Mark the list as deleted so that indexing can remove it from search
		// The Cron Process DatabaseCleanup will actually delete the list from the databse
	}

	/**
	 * @var array An array of resources keyed by the list id since we can iterate over multiple lists while fetching from the DB
	 */
	private $listTitles = [];

	/**
	 * @param string|null $userListSortOption  optional SQL for the query's ORDER BY clause
	 * @return array      of list entries
	 */
	function getListEntries($userListSortOption = null){
		$listEntries = $archiveIDs = $catalogIDs = [];
		if (!empty($this->id)){
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry         = new UserListEntry();
			$listEntry->listId = $this->id;
			if (!empty($userListSortOption) && in_array($userListSortOption, $this->userListSortOptions)){
				// Only do database sorting for options that are for the database; The others will be sorted by search later on
				$listEntry->orderBy($userListSortOption);
			}
			// These conditions retrieve list items with a valid groupedWork ID or archive ID.
			// (This prevents list strangeness when our searches don't find the ID in the search indexes)
			$listEntry->whereAdd(
				'(' .
				'(user_list_entry.groupedWorkPermanentId NOT LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT permanent_id FROM grouped_work) )' .
				' OR ' .
				'(user_list_entry.groupedWorkPermanentId LIKE "%:%")' .
//				'(user_list_entry.groupedWorkPermanentId LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT pid FROM islandora_object_cache) )' .
				')'
			//TODO: checking the islandora cache does not really check that pid is valid. Probably should remove
			);
			if($listEntry->find()){
				while ($listEntry->fetch()){
					if (strpos($listEntry->groupedWorkPermanentId, ':') !== false){
						$archiveIDs[] = $listEntry->groupedWorkPermanentId;
					}else{
						$catalogIDs[] = $listEntry->groupedWorkPermanentId;
					}
					$listEntries[] = $listEntry->groupedWorkPermanentId;
				}
			}
		}

		return [$listEntries, $catalogIDs, $archiveIDs];
	}

	/**
	 * @param bool $cleanListOfBadWords
	 * @return UserListEntry[]|null
	 */
	function getListTitles($cleanListOfBadWords = false){
		if (!$cleanListOfBadWords && isset($this->listTitles[$this->id])){
			return $this->listTitles[$this->id];
		}
		$listTitles = [];

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$listEntry         = new UserListEntry();
		$listEntry->listId = $this->id;
		$listEntry->find();

		while ($listEntry->fetch()){
			if ($cleanListOfBadWords){
				$cleanedEntry = $this->cleanListEntry(clone($listEntry));
				if ($cleanedEntry != false){
					$listTitles[] = $cleanedEntry;
				}
			} else {
				$listTitles[] = clone($listEntry);
			}
		}

		if (!$cleanListOfBadWords){
			$this->listTitles[$this->id] = $listTitles;
		}
		return $cleanListOfBadWords? $listTitles : $this->listTitles[$this->id];
	}

	/**
	 * Remove bad words from a list's title or descriptions, and the list entry's notes;
	 * OR hide the entry completely for libraries with hideCommentsWithBadWords on
	 *
	 * @param UserListEntry $listEntry - The resource to be cleaned
	 * @return UserListEntry|bool
	 */
	function cleanListEntry($listEntry){
		if (!UserAccount::isLoggedIn() || $this->user_id != UserAccount::getActiveUserId()){
			// Only Filter list for bad words when it isn't the users list. (The user gets to see their own list uncensored)

			// Load all bad words.
			require_once ROOT_DIR . '/sys/Language/BadWord.php';
			$badWords = new BadWord();

			// Determine if we should censor bad words only or hide the comment completely.
			global $library;
			$hideListEntriesWithBadWordsCompletely = !empty($library->hideCommentsWithBadWords);
			if ($hideListEntriesWithBadWordsCompletely){
				// Check for bad words in the list's title or description, or the list entry's notes
				$text = $this->title;
				if (!empty($this->description)){
					$text .= ' ' . $this->description;
				}
				if (!empty($listEntry->notes)){
					$text .= ' ' . $listEntry->notes;
				}

				if ($badWords->hasBadWords($text)){
					return false;
				}
			}else{
				//Filter Title
				$titleText   = $badWords->censorBadWords($this->title);
				$this->title = $titleText;

				//Filter description
				$descriptionText   = $badWords->censorBadWords($this->description);
				$this->description = $descriptionText;

				//Filter notes
				$notesText        = $badWords->censorBadWords($listEntry->notes);
				$listEntry->notes = $notesText;
			}
		}
		return $listEntry;
	}

	/**
	 * @param String $workToRemove
	 */
	function removeListEntry($workToRemove){
		// Remove the Saved List Entry
		if ($workToRemove instanceof UserListEntry){
			$workToRemove->delete();
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry                         = new UserListEntry();
			$listEntry->groupedWorkPermanentId = $workToRemove;
			$listEntry->listId                 = $this->id;
			$listEntry->delete();
		}

		unset($this->listTitles[$this->id]);
	}

	/**
		* remove all resources within this list
		*/
	function removeAllListEntries(){
		global $pikaLogger;
		global $user;
		$pikaLogger->notice("User $user->id clearing list entries for list $this->id");
		$allListEntries = $this->getListTitles();
		foreach ($allListEntries as $listEntry){
			$this->removeListEntry($listEntry);
		}
	}


	public function getBrowseRecords($page = 1, $recordsPerPage = 24){
		require_once ROOT_DIR . '/sys/LocalEnrichment/FavoriteHandler.php';
		$favoriteHandler = new FavoriteHandler($this);
		return $favoriteHandler->buildListForBrowseCategory($page, $recordsPerPage);
	}

	/**
	 * @return array
	 */
	public function getUserListSortOptions(){
		return $this->userListSortOptions;
	}

	private function flushUserListBrowseCategory(){
		// Check if the list is a part of a browse category and clear the cache.
		require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
		$userListBrowseCategory = new BrowseCategory();
		$userListBrowseCategory->sourceListId = $this->id;
		if ($userListBrowseCategory->find()) {
			while ($userListBrowseCategory->fetch()) {
				$userListBrowseCategory->deleteCachedBrowseCategoryResults();
			}
		}
	}

	/**
	 * User defined sorting depends on the weight column of the table.
	 * Since this is left null for entries when the list sort isn't set to 'custom',
	 * preset weight values for the list using the previous sort value when it is
	 * a user list sort option, or the default date added user list sort option otherwise. (eg. solr sort options.)
	 *
	 * @param string|null $previousDefaultSort  The sort option used before being changed to 'custom'
	 * @return void
	 */
	public function initializeUserDefinedSort($previousDefaultSort){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$weight = $this->getNextWeightForUserDefinedSort();

		// Set weighted order by previous user sort order
		$userListSortOptions = $this->getUserListSortOptions();
		$currentSorting      = is_null($previousDefaultSort) ? reset($userListSortOptions) : $userListSortOptions[$previousDefaultSort];
		$listEntry           = new UserListEntry();
		$listEntry->listId   = $this->id;
		if ($weight > 0){
			// If weights have been set previously, just set the one's without weights.
			$listEntry->whereAdd('weight IS NULL');
		}
		$listEntry->orderBy($currentSorting);
		if ($listEntry->find()){
			while ($listEntry->fetch()){
				$listEntry->weight = $weight++;
				$listEntry->update(false, false);  // Prevent unneeded (& repeated) parent list updating
			}
		}
	}

	/**
	 * Get the next weight value to use for a list entry when the user list default sort is set to 'custom'
	 * @return false|int
	 */
	public function getNextWeightForUserDefinedSort(){
		$lastPlacedEntry         = new UserListEntry();
		$lastPlacedEntry->listId = $this->id;
		$lastPlacedEntry->orderBy('weight desc');
		if ($lastPlacedEntry->find(true)){
			$weight = is_null($lastPlacedEntry->weight) ? 0 : ++$lastPlacedEntry->weight;
			return $weight;
		}
		return false;
	}

}
