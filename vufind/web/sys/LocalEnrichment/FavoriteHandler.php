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
 * FavoriteHandler Class
 *
 * This class contains shared logic for displaying lists of favorites (based on
 * earlier logic duplicated between the MyResearch/Home and MyResearch/MyList
 * actions).
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
class FavoriteHandler {
	/** @var UserList */
	private $list;
	private $listId;
	private $allowEdit;
	private $favorites = [];
	private $catalogIds = [];
	private $archiveIds = [];
	private $defaultSort = 'dateAdded'; // initial setting (Use a userlist sorting option initially)
	private $sort;
	private $isUserListSort;          // true for sorting options not done by Solr
	private $isMixedUserList = false; // Flag for user lists that have both catalog & archive items (and eventually other type of items)

	protected $userListSortOptions = [];  // user list sort options handled by Pika SQL database
	protected $solrSortOptions = ['title', 'author'], // user list sorting options handled by Solr engine.
			// Note these values need to match options found in searches.ini Sorting section
		$islandoraSortOptions = ['fgs_label_s']; // user list sorting options handled by the Islandora Solr engine.
			// Note these values need to match options found in islandoraSearches.ini Sorting section

	/**
	 * Constructor.
	 *
	 * @access  public
	 * @param UserList $list User List Object.
	 * @param bool $allowEdit Should we display edit controls?
	 */
	public function __construct($list, $allowEdit = true){
		$this->list                = $list;
		$this->listId              = $list->id;
		$this->allowEdit           = $allowEdit;
		$this->userListSortOptions = $list->getUserListSortOptions(); // Keep the UserList Sort options in the UserList class since it used there as well.


		// Determine Sorting Option //
		if (isset($list->defaultSort)){
			$this->defaultSort = $list->defaultSort; // when list as a sort setting use that
		}
		if (isset($_REQUEST['sort']) && (in_array($_REQUEST['sort'], $this->solrSortOptions) || in_array($_REQUEST['sort'], array_keys($this->userListSortOptions)))){
			// if URL variable is a valid sort option, set the list's sort setting
			$this->sort           = $_REQUEST['sort'];
			$userSpecifiedTheSort = true;
		}else{
			$this->sort           = $this->defaultSort;
			$userSpecifiedTheSort = false;
		}

		$this->isUserListSort = in_array($this->sort, array_keys($this->userListSortOptions));

		// Get the Favorites //
		$userListSort = $this->isUserListSort ? $this->userListSortOptions[$this->sort] : null;
		[$this->favorites, $this->catalogIds, $this->archiveIds] = $list->getListEntries($userListSort);
		// when using a user list sorting, rather than solr sorting, get results in user list sorted order
		// we start with a default userlist sorting until we determine whether the userlist is Mixed items or not.

		$hasCatalogItems = !empty($this->catalogIds);
		$hasArchiveItems = !empty($this->archiveIds);

		// Determine if this UserList mixes catalog & archive Items
		if ($hasArchiveItems && $hasCatalogItems){
			$this->isMixedUserList = true;
		}elseif ($hasArchiveItems && !$hasCatalogItems){
			// Archive Only Lists
			if (!$userSpecifiedTheSort && !isset($list->defaultSort)){
				// If no actual sorting settings were set, reset default to an Islandora Sort
				$this->defaultSort    = $this->islandoraSortOptions[0];
				$this->sort           = $this->defaultSort;
				$this->isUserListSort = false;
			}
		}elseif ($hasCatalogItems && !$hasArchiveItems){
			// Catalog Only Lists
			if (!$userSpecifiedTheSort && !isset($list->defaultSort)){
				// If no actual sorting settings were set, reset default to an Solr Sort
				$this->defaultSort    = $this->solrSortOptions[0];
				$this->sort           = $this->defaultSort;
				$this->isUserListSort = false;
			}
		}

	}

	public function buildListForBrowseCategory($page = 1, $recordsPerPage = 24){
		global $interface;

		/*			Use Cases:
					Only Catalog items, user sort
					Only Catalog items, solr sort
					Only Archive items, user sort
					Only Archive items, islandora sort
					Mixed Items, user sort
				*/
		$catalogResourceList = [];
		$startRecord         = ($page - 1) * $recordsPerPage;
		if ($startRecord < 0){
			$startRecord = 0;
		}

		if (count($this->catalogIds) > 0){
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';

			$catalogSearchObject = SearchObjectFactory::initSearchObject();
			$catalogSearchObject->init();
			$catalogSearchObject->setLimit($recordsPerPage);
			if (!$this->isUserListSort && !$this->isMixedUserList){
				$catalogSearchObject->setSort($this->sort);
			}

			if (!$this->isMixedUserList){
				if ($this->isUserListSort){
					$idsToFetch = array_slice($this->catalogIds, $startRecord, $recordsPerPage);
					if (count($idsToFetch)){
						$catalogSearchObject->setPage(1);              // set to the first page for the search only
						$catalogSearchObject->setQueryIDs($idsToFetch);// do solr search by Ids
						$catalogResults = $catalogSearchObject->processSearch();
						foreach ($catalogResults['response']['docs'] as $catalogResult){
							$groupedWork = new GroupedWorkDriver($catalogResult);
							if ($groupedWork->isValid){
								$key = array_search($catalogResult['id'], $idsToFetch);
								if ($key !== false){
									$catalogResourceList[$key] = $interface->fetch($groupedWork->getBrowseResult());
								}
							}
						}
						ksort($catalogResourceList, SORT_NUMERIC);// Requires re-ordering to display in the correct order
					}
				} // Solr Sorted Catalog Only Search //
				else{
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogSearchObject->setPage($page);
					$catalogResults = $catalogSearchObject->processSearch();
					foreach ($catalogResults['response']['docs'] as $catalogResult){
						$groupedWork = new GroupedWorkDriver($catalogResult);
						if ($groupedWork->isValid){
							$catalogResourceList[] = $interface->fetch($groupedWork->getBrowseResult());
						}
					}
				}
			} else{
				// Mixed User List - Process catalog items

				// Removed all catalog items from previous page searches
				$totalItemsFromPreviousPages = $recordsPerPage * ($page - 1);
				for ($i = 0;$i < $totalItemsFromPreviousPages;$i++){
					$IdToTest = $this->favorites[$i];
					$key      = array_search($IdToTest, $this->catalogIds);
					if ($key !== false){
						unset($this->catalogIds[$key]);
					}
				}
				$this->catalogIds = array_slice($this->catalogIds, 0, $recordsPerPage);
				if (!empty($this->catalogIds)){
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogSearchObject->setPage(1);
					$catalogResults = $catalogSearchObject->processSearch();
					foreach ($catalogResults['response']['docs'] as $catalogResult){
						$groupedWork = new GroupedWorkDriver($catalogResult);
						if ($groupedWork->isValid){
							$key = array_search($this->favorites, $catalogResult['id']);
							if ($key !== false){
								$catalogResourceList[$key] = $interface->fetch($groupedWork->getBrowseResult());
							}
						}
					}

				}
			}
		}

		// Archive Search
		$archiveResourceList = [];
		if (count($this->archiveIds) > 0){
			require_once ROOT_DIR . '/RecordDrivers/IslandoraDriver.php';
			// Initialise from the current search globals
			/** @var SearchObject_Islandora $archiveSearchObject */
			$archiveSearchObject = SearchObjectFactory::initSearchObject('Islandora');
			$archiveSearchObject->init();
			$archiveSearchObject->setPrimarySearch(true);
			$archiveSearchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$archiveSearchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
			$archiveSearchObject->setLimit($recordsPerPage); //MDN 3/30 this was set to 200, but should be based off the page size

			if (!$this->isUserListSort && !$this->isMixedUserList){ // is a solr sort
				$archiveSearchObject->setSort($this->sort);           // set solr sort. (have to set before retrieving solr sort options below)
			}

			// Archive Only Searches //
			if (!$this->isMixedUserList){
				// User Sorted Archive Only Searches
				if ($this->isUserListSort){
					$idsToFetch = array_slice($this->archiveIds, $page, $recordsPerPage);
					if (count($idsToFetch)){
						$archiveSearchObject->setPage(1);              // set to the first page for the search only
						$archiveSearchObject->setQueryIDs($idsToFetch);// do solr search by Ids
						$archiveResult = $archiveSearchObject->processSearch(false, false, false);
						foreach ($archiveResult['response']['docs'] as $result){
							/** @var IslandoraDriver $archiveWork */
							$archiveWork = RecordDriverFactory::initRecordDriver($result);
							$key         = array_search($result['PID'], $idsToFetch);
							if ($key !== false){
								$archiveResourceList[] = $interface->fetch($archiveWork->getBrowseResult());
							}
						}
						ksort($archiveResourceList, SORT_NUMERIC);// Requires re-ordering to display in the correct order
					}
				}// Islandora Sorted Archive Only Searches
				else{
					//TODO: set sort
					$archiveSearchObject->setQueryIDs($this->archiveIds); // do Islandora search by Ids
					$archiveSearchObject->setPage(1);                     // set to the first page for the search only
					$archiveResult = $archiveSearchObject->processSearch(false, false, false);
					foreach ($archiveResult['response']['docs'] as $result){
						$archiveWork = RecordDriverFactory::initRecordDriver($result);
						if (method_exists($archiveWork, 'getBrowseResult')){
							$archiveResourceList[] = $interface->fetch($archiveWork->getBrowseResult());
						}
					}
				}

			}// Mixed Items Searches (All User Sorted) //
			else{
				// Remove all archive items from previous page searches
				$totalItemsFromPreviousPages = $recordsPerPage * ($page - 1);
				for ($i = 0;$i < $totalItemsFromPreviousPages;$i++){
					$IdToTest = $this->favorites[$i];
					$key      = array_search($IdToTest, $this->archiveIds);
					if ($key !== false){
						unset($this->archiveIds[$key]);
					}
				}
				$this->archiveIds = array_slice($this->archiveIds, 0, $recordsPerPage);
				if (!empty($this->archiveIds)){
					$archiveSearchObject->setPage(1); // set to the first page for the search only

					$archiveSearchObject->setQueryIDs($this->archiveIds); // do solr search by Ids
					$archiveResult = $archiveSearchObject->processSearch();
					foreach ($archiveResult['response']['docs'] as $result){
						/** @var IslandoraDriver $archiveWork */
						$archiveWork = RecordDriverFactory::initRecordDriver($result);
						$key         = array_search($result['PID'], $idsToFetch);
						if ($key !== false){
							$archiveResourceList[] = $interface->fetch($archiveWork->getBrowseResult());
						}
					}
				}
			}

		}


		$browseRecords = [];
		if ($this->isMixedUserList){
			$browseRecords = $catalogResourceList + $archiveResourceList;
			// Depends on numbered indexing reflect each item's position in the list
			//$browseRecordsAlt = array_replace($catalogResourceList, $archiveResourceList); // Equivalent of above
			ksort($browseRecords, SORT_NUMERIC); // Requires re-ordering to display in the correct order
			$browseRecords = array_slice($browseRecords, 0, $recordsPerPage); // reduce to the correct page size
		}else{
			if (count($this->catalogIds) > 0){
				$browseRecords = $catalogResourceList;
			}
			if (count($this->archiveIds) > 0){
				$browseRecords = $archiveResourceList;
			}
		}

		return $browseRecords;
	}

	/**
	 * Assign all necessary values to the interface.
	 *
	 * @access  public
	 */
	public function buildListForDisplay($recordsPerPage = 20, $page = 1){
		global $interface;

//		unset($_REQUEST['type']); // Remove variable so that search filter links don't include type parameter
		$isPageSizeParamSet = isset($_REQUEST['pagesize']) && is_numeric($_REQUEST['pagesize']);
		$recordsPerPage     = $isPageSizeParamSet ? $_REQUEST['pagesize'] : $recordsPerPage;
		$page               = $_REQUEST['page'] ?? $page;
		$startRecord        = ($page - 1) * $recordsPerPage + 1;
		$endRecord          = $page * $recordsPerPage;
		if ($startRecord < 0){
			$startRecord = 0;
		}
		if ($endRecord > count($this->favorites)){
			$endRecord = count($this->favorites);
		}
		$pageInfo = [
			'resultTotal' => count($this->favorites),
			'startRecord' => $startRecord,
			'endRecord'   => $endRecord,
			'perPage'     => $recordsPerPage
		];

		$sortOptions = $defaultSortOptions = [];
		// $sortOptions populates dropdown for sorting the list for the current display
		// $defaultSortOptions populates the dropdown for the edit list form (So list owner's can set the sort order for initial display)

		/*			Use Cases:
			Only Catalog items, user sort
			Only Catalog items, solr sort
			Only Archive items, user sort
			Only Archive items, islandora sort
			Mixed Items, user sort
		*/

		// Catalog Search
		$catalogResourceList = [];
		if (count($this->catalogIds) > 0){
			// Initialise from the current search globals
			/** @var SearchObject_UserListSolr $catalogSearchObject */
			$catalogSearchObject               = SearchObjectFactory::initSearchObject('UserListSolr');
			$catalogSearchObject->userListSort = $this->sort;
			if ($isPageSizeParamSet){
				$catalogSearchObject->userListPageSize = $recordsPerPage;
			}
			$catalogSearchObject->init();
			$catalogSearchObject->setLimit($recordsPerPage);
			//$catalogSearchObject->disableScoping(); // Return all works in list, even if outside current scope
			// After implementing facets for user lists, disabling scoping is no longer a good idea, since out of scope list entries
			// will not contribute to any scoped search facets displayed on the user list. See D-3851

			if (!$this->isUserListSort && !$this->isMixedUserList){ // is a solr sort
				$catalogSearchObject->setSort($this->sort);           // set solr sort. (have to set before retrieving solr sort options below)
			}

			// Range filters need special processing in order to be used
			$catalogSearchObject->processAllRangeFilters();

			$this->populateSortOptionsForList($catalogSearchObject, $this->solrSortOptions, $sortOptions, $defaultSortOptions);

			// Catalog Only Searches //
			if (!$this->isMixedUserList){

				// User Sorted Catalog Only Search
				if ($this->isUserListSort){
					// Just get facets first
					$catalogSearchObject->setPrimarySearch(true);
					$catalogSearchObject->setLimit(0); // Return no results, we only want faceting
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogResult = $catalogSearchObject->processSearch(false, true, true);
					$interface->assign('userListHasSearchFilters', true);
					$interface->assign('topRecommendations', $catalogSearchObject->getRecommendationsTemplates('top'));
					$interface->assign('sideRecommendations', $catalogSearchObject->getRecommendationsTemplates('side'));

					if (!empty($_REQUEST['filter'])){
						$searchFilteredIds         = $catalogSearchObject->getFilteredIds($this->catalogIds);
						$pageInfo['resultTotal']   = count($searchFilteredIds);
						$remainingIdsInSortedOrder = array_intersect($this->catalogIds, $searchFilteredIds);
					} else {
						$remainingIdsInSortedOrder = $this->catalogIds;
					}

					// Get ids for a page of the list after search filters have been applied
					$idsToDisplayForThisPage = array_slice($remainingIdsInSortedOrder, $startRecord - 1, $recordsPerPage);
					if (count($idsToDisplayForThisPage)){
						$catalogSearchObject->setQueryIDs($idsToDisplayForThisPage);// do solr search by Ids
						$catalogSearchObject->setPage(1);
						$catalogSearchObject->setLimit($recordsPerPage);
						$catalogSearchObject->setPrimarySearch(false);
						$catalogResult       = $catalogSearchObject->processSearch(false, false, true);
						$catalogSearchObject->setPage($page);
						// Set back to the actual page of the list now that search was processed
						// (This ensures the position numbering is correct when not on page 1)
						$catalogResourceList = $catalogSearchObject->getResultListHTML($this->listId, $this->allowEdit, $idsToDisplayForThisPage);
					}

				} // Solr Sorted Catalog Only Search //
				else{
					$catalogSearchObject->setPrimarySearch(true);
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogResult           = $catalogSearchObject->processSearch(false, true);
					$catalogResourceList     = $catalogSearchObject->getResultListHTML($this->listId, $this->allowEdit);
					$pageInfo['resultTotal'] = $catalogResult['response']['numFound'];

					//Only show search filter options when not mixed user list
					$interface->assign('userListHasSearchFilters', true);
					$interface->assign('topRecommendations', $catalogSearchObject->getRecommendationsTemplates('top'));
					$interface->assign('sideRecommendations', $catalogSearchObject->getRecommendationsTemplates('side'));
					// Display search facets on a user list. Has to happen after processSearch() where recommendations are initialized.
				}

			}// Mixed Items Searches (All User Sorted) //
			else{
				// Removed all catalog items from previous page searches
				$totalItemsFromPreviousPages = $recordsPerPage * ($page - 1);
				for ($i = 0;$i < $totalItemsFromPreviousPages;$i++){
					$IdToTest = $this->favorites[$i];
					$key      = array_search($IdToTest, $this->catalogIds);
					if ($key !== false){
						unset($this->catalogIds[$key]);
					}
				}
				$this->catalogIds = array_slice($this->catalogIds, 0, $recordsPerPage);
				if (!empty($this->catalogIds)){
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogSearchObject->setPage(1);                     // set to the first page for the search only
					$catalogResult       = $catalogSearchObject->processSearch();
					$catalogResourceList = $catalogSearchObject->getResultListHTML($this->listId, $this->allowEdit, $this->favorites, $this->isMixedUserList);
				}
			}
		}


		// Archive Search
		$archiveResourceList = [];
		if (count($this->archiveIds) > 0){

			// Initialise from the current search globals
			/** @var SearchObject_UserListIslandora $archiveSearchObject */
			$archiveSearchObject = SearchObjectFactory::initSearchObject('UserListIslandora');
			$archiveSearchObject->userListSort = $this->sort;
			if ($isPageSizeParamSet){
				$archiveSearchObject->userListPageSize = $recordsPerPage;
			}
			$archiveSearchObject->init();
			$archiveSearchObject->setPrimarySearch(true);
			$archiveSearchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', 'administrator'); //TODO: move to construct()/init() for user list
			$archiveSearchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', 'no'); //TODO: move to construct()/init() for user list
			$archiveSearchObject->setLimit($recordsPerPage);

			if (!$this->isUserListSort && !$this->isMixedUserList){ // is a solr sort
				$archiveSearchObject->setSort($this->sort);           // set solr sort. (have to set before retrieving solr sort options below)
			}

			$this->populateSortOptionsForList($archiveSearchObject, $this->islandoraSortOptions, $sortOptions, $defaultSortOptions);

			// Archive Only Searches //
			if (!$this->isMixedUserList){
				// User Sorted Archive Only Searches
				if ($this->isUserListSort){
					$archiveSearchObject->setLimit(0); // Return no results, we only want faceting
					$archiveSearchObject->setQueryIDs($this->archiveIds); // do solr search by Ids
					$archiveResult = $archiveSearchObject->processSearch(false, true, true);
					//Only show search filter options when not mixed user list
					$interface->assign('userListHasSearchFilters', true);
					$interface->assign('sideRecommendations', $archiveSearchObject->getRecommendationsTemplates('side')); // only side facet needed for archive searches

					if (!empty($_REQUEST['filter'])){
						$searchFilteredIds         = $archiveSearchObject->getFilteredPIDs($this->archiveIds);
						$pageInfo['resultTotal']   = count($searchFilteredIds);
						$remainingIdsInSortedOrder = array_intersect($this->archiveIds, $searchFilteredIds);
					} else {
						$remainingIdsInSortedOrder = $this->archiveIds;
					}

					// Get ids for a page of the list after search filters have been applied
					$idsToDisplayForThisPage = array_slice($remainingIdsInSortedOrder, $startRecord - 1, $recordsPerPage);
					if (count($idsToDisplayForThisPage)){
						$archiveSearchObject->setQueryIDs($idsToDisplayForThisPage);// do solr search by Ids
						$archiveSearchObject->setLimit($recordsPerPage);
						$archiveSearchObject->setPage(1);
						$archiveSearchObject->setPrimarySearch(false);
						$archiveResult       = $archiveSearchObject->processSearch(false, false, true);
						$archiveSearchObject->setPage($page);
						// Set back to the actual page of the list now that search was processed
						// (This ensures the position numbering is correct when not on page 1)
						$archiveResourceList = $archiveSearchObject->getResultListHTML($this->listId, $this->allowEdit, $idsToDisplayForThisPage);
					}
				}// Islandora Sorted Archive Only Searches
				else{
					$archiveSearchObject->setQueryIDs($this->archiveIds); // do Islandora search by Ids
					$archiveResult           = $archiveSearchObject->processSearch(false, true, true);
					$archiveResourceList     = $archiveSearchObject->getResultListHTML($this->listId, $this->allowEdit);
					$pageInfo['resultTotal'] = $archiveResult['response']['numFound'];

					//Only show search filter options when not mixed user list
					$interface->assign('userListHasSearchFilters', true);
					$interface->assign('sideRecommendations', $archiveSearchObject->getRecommendationsTemplates('side'));  // only side facet needed for archive searches
					// Display search facets on a user list. Has to happen after processSearch() where recommendations are initialized.
				}

			}// Mixed Items Searches (All User Sorted) //
			else{
				// Remove all archive items from previous page searches
				$totalItemsFromPreviousPages = $recordsPerPage * ($page - 1);
				for ($i = 0;$i < $totalItemsFromPreviousPages;$i++){
					$IdToTest = $this->favorites[$i];
					$key      = array_search($IdToTest, $this->archiveIds);
					if ($key !== false){
						unset($this->archiveIds[$key]);
					}
				}
				$this->archiveIds = array_slice($this->archiveIds, 0, $recordsPerPage);
				//TODO: can not sort till after search filtering occurs now

				if (!empty($this->archiveIds)){
					$archiveSearchObject->setPage(1); // set to the first page for the search only

					$archiveSearchObject->setQueryIDs($this->archiveIds); // do solr search by Ids
					$archiveResult       = $archiveSearchObject->processSearch();
					$archiveResourceList = $archiveSearchObject->getResultListHTML($this->listId, $this->allowEdit, $this->favorites, $this->isMixedUserList);
				}
			}

		}

		$interface->assign('sortList', $sortOptions);
		$interface->assign('defaultSortList', $defaultSortOptions);
		$interface->assign('defaultSort', $this->defaultSort);
		$interface->assign('userSort', ($this->getSort() == 'custom')); // switch for when users can sort their list


		$resourceList = [];
		if ($this->isMixedUserList){
			$resourceList = $catalogResourceList + $archiveResourceList;
			// Depends on numbered indexing reflect each item's position in the list
			//$resourceListAlt = array_replace($catalogResourceList, $archiveResourceList); // Equivalent of above
			ksort($resourceList, SORT_NUMERIC);                             // Requires re-ordering to display in the correct order
			$resourceList = array_slice($resourceList, 0, $recordsPerPage); // reduce to the correct page size
		}else{
			if (count($this->catalogIds) > 0){
				$resourceList = $catalogResourceList;
			}
			if (count($this->archiveIds) > 0){
				$resourceList = $archiveResourceList;
			}
		}

		$interface->assign('resourceList', $resourceList);

		// Set up paging of list contents:
		$interface->assign('recordCount', $pageInfo['resultTotal']);
		$interface->assign('recordStart', $pageInfo['startRecord']);
		$interface->assign('recordEnd', min($pageInfo['endRecord'], $pageInfo['resultTotal']));  //search filtering may reduce the number of entries being displayed
		$interface->assign('recordsPerPage', $pageInfo['perPage']);

		$link = $_SERVER['REQUEST_URI'];
		if (preg_match('/[&?]page=/', $link)){
			$link = preg_replace("/page=\\d+/", 'page=%d', $link);
		}elseif (strpos($link, '?') > 0){
			$link .= '&page=%d';
		}else{
			$link .= '?page=%d';
		}
		$options = [
			'totalItems' => $pageInfo['resultTotal'],
			'perPage'    => $pageInfo['perPage'],
			'fileName'   => $link,
			'append'     => false
		];
		require_once ROOT_DIR . '/sys/Pager.php';
		$pager = new VuFindPager($options);
		$interface->assign('pageLinks', $pager->getLinks());
	}

	private function populateSortOptionsForList(SearchOBject_Base &$searchObject, $searchOptions, array &$sortOptions, array &$defaultSortOptions){
		if (!$this->isMixedUserList){
			$solrSortList = $searchObject->getSortList(); // get all the search sort options (retrieve after setting solr sort option)
			foreach ($searchOptions as $option){ // extract just the ones we want
				if (isset($solrSortList[$option])){
					$sortOptions[$option]        = $solrSortList[$option];
					$defaultSortOptions[$option] = $solrSortList[$option]['desc'];
				}
			}
		}
		foreach ($this->userListSortOptions as $option => $value_ignored){ // Non-Solr options
			$sortOptions[$option]        = [
				'sortUrl'  => $searchObject->renderLinkWithSort($option),
				'desc'     => "sort_{$option}_userlist", // description in translation dictionary
				'selected' => ($option == $this->sort)
			];
			$defaultSortOptions[$option] = "sort_{$option}_userlist";
		}
	}

	function getTitles($numListEntries, $applyFiltering = false){
		$catalogRecordSet = $archiveRecordSet = [];
		// Retrieve records from index (currently, only Solr IDs supported):
		if (count($this->catalogIds) > 0){
			// Initialise from the current search globals
			/** @var SearchObject_Solr $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init();
			// these are added for emailing list  plb 10-8-2014
			$searchObject->setLimit($numListEntries); // only get results for each item

			$searchObject->setQueryIDs($this->catalogIds);

			$searchObject->processSearch(false, $applyFiltering);
			$catalogRecordSet = $searchObject->getResultRecordSet();

			//TODO: user list sorting here
		}
		if (count($this->archiveIds) > 0){
			// Initialise from the current search globals
			/** @var SearchObject_Islandora $archiveSearchObject */
			$archiveSearchObject = SearchObjectFactory::initSearchObject('Islandora');
			$archiveSearchObject->init();
			$archiveSearchObject->setPrimarySearch(true);
			$archiveSearchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$archiveSearchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
			$archiveSearchObject->setQueryIDs($this->archiveIds);

			$archiveSearchObject->processSearch(false, $applyFiltering);

			$archiveRecordSet = $archiveSearchObject->getResultRecordSet();


		}
		return [...$catalogRecordSet, ...$archiveRecordSet];
	}

	function getCitations($citationFormat){
		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$citations = array();

			if(!empty($this->catalogIds)){
				$searchObject = SearchObjectFactory::initSearchObject();
				$searchObject->init();
				$searchObject->setQueryIDs($this->catalogIds);
				$searchObject->processSearch();
				foreach($searchObject->getCitations($citationFormat) as $citation){
					array_push($citations, $citation);
				}

			}
			if(!empty($this->archiveIds)){
				$archiveObject = SearchObjectFactory::initSearchObject('Islandora');
				$archiveObject->init();
				$archiveObject->setQueryIds($this->archiveIds);
				$archiveObject->processSearch();
				foreach($archiveObject->getCitations($citationFormat) as $citation){
					array_push($citations, $citation);
				}

			}
		if(count($citations) > 0){


		// Retrieve records from index (currently, only Solr IDs supported):

			return $citations;
		}else{
			return [];
		}
	}

	/**
	 * @return string
	 */
	public function getSort(){
		return $this->sort;
	}

	/**
	 * @return array
	 */
	public function getCatalogIds(){
		return $this->catalogIds;
	}

	/**
	 * @return array
	 */
	public function getArchiveIds(){
		return $this->archiveIds;
	}

	/**
	 * @return boolean
	 */
	public function isMixedUserList(){
		return $this->isMixedUserList;
	}

	/**
	 * @return UserListEntry[]
	 */
	public function getFavorites(){
		return $this->favorites;
	}
}
