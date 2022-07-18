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

	protected $userListSortOptions = [];
	/*	protected $userListSortOptions = array(
									// URL_value => SQL code for Order BY clause
									'dateAdded' => 'dateAdded ASC',
									'custom' => 'weight ASC',  // this puts items with no set weight towards the end of the list
	//								'custom' => 'weight IS NULL, weight ASC',  // this puts items with no set weight towards the end of the list
								);*/
	protected $solrSortOptions = ['title', 'author asc,title asc'], // user list sorting options handled by Solr engine.
		$islandoraSortOptions = ['fgs_label_s']; // user list sorting options handled by the Islandora Solr engine.

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

	public function buildListForBrowseCategory($start, $numItems, $defaultSort){
		global $interface;

		$browseRecords = array();
		$sortOptions = $defaultSortOptions = array();
		$ids = [];

		/*			Use Cases:
					Only Catalog items, user sort
					Only Catalog items, solr sort
					Only Archive items, user sort
					Only Archive items, islandora sort
					Mixed Items, user sort
				*/
		$catalogResourceList = [];
		if (count($this->catalogIds) > 0){
			$catalogSearchObject = SearchObjectFactory::initSearchObject();
			$catalogSearchObject->init();
			$catalogSearchObject->setLimit($numItems);
			if(!$this->isUserListSort && !$this->isMixedUserList){
				if($defaultSort == "title" || $defaultSort =="author")
				{
					$this->sort = $defaultSort;
				}
				$catalogSearchObject->setSort($this->sort);
			}
			if(!$this->isMixedUserList()){
				$solrSortList = $catalogSearchObject->getSortList();
				foreach($this->solrSortOptions as $option){
					if (isset($solrSortList[$option])){
						$sortOptions[$option]         = $solrSortList[$option];
						$defaultSortOptions[$option]  = $solrSortList[$option]['desc'];
					}
				}
			}
			foreach ($this->userListSortOptions as $option => $value_ignored){
				$sortOptions[$option] = [
					'sortUrl' => $catalogSearchObject->renderLinkWithSort($option),
					'desc'    => "sort_{$option}_userlist",
					'selected' => ($option == $this->sort)
				];
				$defaultSortOptions[$option] = "sort_{option}_userlist";
			}
			if(!$this->isMixedUserList){
				if ($this->isUserListSort) {
					$this->ids = array_slice($this->ids, $start - 1, $numItems);
					$catalogSearchObject->setPage(1); // set to the first page for the search only

					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogResults = $catalogSearchObject->processSearch();
					foreach($catalogResults['response']['docs'] as $catalogResult)
					{
							require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
							$groupedWork = new GroupedWorkDriver($catalogResult);
							if ($groupedWork->isValid){
								if(method_exists($groupedWork, 'getBrowseResult')){
									$browseRecords[$catalogResult['id']] = $interface->fetch($groupedWork->getBrowseResult());
								}
							}
						}
				} // Solr Sorted Catalog Only Search //
				else {
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogSearchObject->setPage($start);
					$catalogResults       = $catalogSearchObject->processSearch();
					foreach($catalogResults['response']['docs'] as $catalogResult)
					{
						require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
						$groupedWork = new GroupedWorkDriver($catalogResult);
						if ($groupedWork->isValid){
							if(method_exists($groupedWork, 'getBrowseResult')){
								$browseRecords[$catalogResult['id']] = $interface->fetch($groupedWork->getBrowseResult());
							}
						}
					}
				}
			}
			else {
				// Removed all catalog items from previous page searches
				$totalItemsFromPreviousPages = $numItems * ($start - 1);
				for ($i = 0; $i < $totalItemsFromPreviousPages; $i++ ) {
					$IdToTest = $this->favorites[$i];
					$key      = array_search($IdToTest, $this->catalogIds);
					if ($key !== false) {
						unset($this->catalogIds[$key]);
					}
				}
				$this->catalogIds = array_slice($this->catalogIds, 0, $numItems);
				if (!empty($this->catalogIds)) {
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogSearchObject->setPage(1); // set to the first page for the search only
					$catalogResults       = $catalogSearchObject->processSearch();
					foreach($catalogResults['response']['docs'] as $catalogResult)
					{
						require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
						$groupedWork = new GroupedWorkDriver($catalogResult);
						if ($groupedWork->isValid){
							if(method_exists($groupedWork, 'getBrowseResult')){
								$browseRecords[$catalogResult['id']] = $interface->fetch($groupedWork->getBrowseResult());
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
				$archiveSearchObject->setLimit($numItems); //MDN 3/30 this was set to 200, but should be based off the page size

				if (!$this->isUserListSort && !$this->isMixedUserList){ // is a solr sort
					$archiveSearchObject->setSort($this->sort);           // set solr sort. (have to set before retrieving solr sort options below)
				}
				if (!$this->isMixedUserList){
					$IslandoraSortList = $archiveSearchObject->getSortList(); // get all the search sort options (retrieve after setting solr sort option)
					foreach ($this->islandoraSortOptions as $option){ // extract just the ones we want
						if (isset ($IslandoraSortList[$option])){
							$sortOptions[$option]        = $IslandoraSortList[$option];
							$defaultSortOptions[$option] = $IslandoraSortList[$option]['desc'];
						}
					}
				}
				foreach ($this->userListSortOptions as $option => $value_ignored){ // Non-Solr options
					if (!isset($sortOptions[$option])){ // Skip if already done by the catalog searches above
						$sortOptions[$option]        = [
							'sortUrl'  => $archiveSearchObject->renderLinkWithSort($option),
							'desc'     => "sort_{$option}_userlist", // description in translation dictionary
							'selected' => ($option == $this->sort)
						];
						$defaultSortOptions[$option] = "sort_{$option}_userlist";
					}
				}


				// Archive Only Searches //
				if (!$this->isMixedUserList){
					// User Sorted Archive Only Searches
					if ($this->isUserListSort){
						$this->archiveIds = array_slice($this->archiveIds, $start - 1, $numItems);
						$archiveSearchObject->setPage(1); // set to the first page for the search only

						$archiveSearchObject->setQueryIDs($this->archiveIds); // do solr search by Ids
						$archiveResult = $archiveSearchObject->processSearch(false, true);
						foreach($archiveResult['response']['docs'] as $result){

							$archiveWork = RecordDriverFactory::initRecordDriver($result);
							if (method_exists($archiveWork, 'getBrowseResult')){
									$browseRecords[$result['id']] = $interface->fetch($archiveWork->getBrowseResult());
							   }
						}


					}// Islandora Sorted Archive Only Searches
					else{
						$archiveSearchObject->setQueryIDs($this->archiveIds); // do Islandora search by Ids
						$archiveSearchObject->setPage(1);                 // set to the first page for the search only
						$archiveResult       = $archiveSearchObject->processSearch(false, true);
						foreach($archiveResult['response']['docs'] as $result){
							$archiveWork = RecordDriverFactory::initRecordDriver($result);
							if (method_exists($archiveWork, 'getBrowseResult')){
								$browseRecords[$result['id']] = $interface->fetch($archiveWork->getBrowseResult());
							}
						}
					}

				}// Mixed Items Searches (All User Sorted) //
				else{
					// Remove all archive items from previous page searches
					$totalItemsFromPreviousPages = $numItems * ($start - 1);
					for ($i = 0;$i < $totalItemsFromPreviousPages;$i++){
						$IdToTest = $this->favorites[$i];
						$key      = array_search($IdToTest, $this->archiveIds);
						if ($key !== false){
							unset($this->archiveIds[$key]);
						}
					}
					$this->archiveIds = array_slice($this->archiveIds, 0, $numItems);
					if (!empty($this->archiveIds)){
						$archiveSearchObject->setPage(1); // set to the first page for the search only

						$archiveSearchObject->setQueryIDs($this->archiveIds); // do solr search by Ids
						$archiveResult       = $archiveSearchObject->processSearch();
						foreach($archiveResult['response']['docs'] as $result){

							$archiveWork = RecordDriverFactory::initRecordDriver($result);
							if (method_exists($archiveWork, 'getBrowseResult')){
								$browseRecords[$result['id']] = $interface->fetch($archiveWork->getBrowseResult());
							}
						}
					}
				}

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
		$recordsPerPage = isset($_REQUEST['pagesize']) && (is_numeric($_REQUEST['pagesize'])) ? $_REQUEST['pagesize'] : $recordsPerPage;
		$page           = $_REQUEST['page'] ?? $page;
		$startRecord    = ($page - 1) * $recordsPerPage + 1;
		if ($startRecord < 0){
			$startRecord = 0;
		}
		$endRecord = $page * $recordsPerPage;
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
			$catalogSearchObject = SearchObjectFactory::initSearchObject('UserListSolr');
			$catalogSearchObject->userListSort = $this->sort;
			$catalogSearchObject->init();
			$catalogSearchObject->setLimit($recordsPerPage);
			$catalogSearchObject->disableScoping(); // Return all works in list, even if outside current scope

			if (!$this->isUserListSort && !$this->isMixedUserList){ // is a solr sort
				$catalogSearchObject->setSort($this->sort);           // set solr sort. (have to set before retrieving solr sort options below)
			}
			if (!$this->isMixedUserList){
				$solrSortList = $catalogSearchObject->getSortList(); // get all the search sort options (retrieve after setting solr sort option)
				foreach ($this->solrSortOptions as $option){ // extract just the ones we want
					if (isset($solrSortList[$option])){
						$sortOptions[$option]        = $solrSortList[$option];
						$defaultSortOptions[$option] = $solrSortList[$option]['desc'];
					}
				}
			}
			foreach ($this->userListSortOptions as $option => $value_ignored){ // Non-Solr options
				$sortOptions[$option]        = [
					'sortUrl'  => $catalogSearchObject->renderLinkWithSort($option),
					'desc'     => "sort_{$option}_userlist", // description in translation dictionary
					'selected' => ($option == $this->sort)
				];
				$defaultSortOptions[$option] = "sort_{$option}_userlist";
			}


			// Catalog Only Searches //
			if (!$this->isMixedUserList){

				// User Sorted Catalog Only Search
				if ($this->isUserListSort){
					// Just get facets first
					$catalogSearchObject->setPrimarySearch(true);
					$catalogSearchObject->setLimit(0);
					$catalogSearchObject->setQueryIDs($this->catalogIds); // do solr search by Ids
					$catalogResult = $catalogSearchObject->processSearch(false, true, true);
					$interface->assign('userListHasSearchFilters', true);
					$interface->assign('topRecommendations', $catalogSearchObject->getRecommendationsTemplates('top'));
					$interface->assign('sideRecommendations', $catalogSearchObject->getRecommendationsTemplates('side'));

					if (!empty($_REQUEST['filter'])){
						$searchFilteredIds         = $catalogSearchObject->getFilteredIds($this->catalogIds);
						$pageInfo['resultTotal']   = count($searchFilteredIds);
						$remainingIdsInSortedOrder = array_intersect($this->catalogIds, $searchFilteredIds);

						$catalogSearchObject->setPage($page);              // Set back to the actual page of the list now that search was processed
						$catalogSearchObject->setLimit($recordsPerPage);   // Set the actual limit per page
					} else {
						$remainingIdsInSortedOrder = $this->catalogIds;
					}

					// Get ids for a page of the list after search filters have been applied
					$idsToDisplayForThisPage = array_slice($remainingIdsInSortedOrder, $startRecord - 1, $recordsPerPage);
					$catalogSearchObject->setQueryIDs($idsToDisplayForThisPage); // do solr search by Ids
					$catalogSearchObject->setLimit($recordsPerPage);
					$catalogSearchObject->setPrimarySearch(false);
					$catalogResult           = $catalogSearchObject->processSearch(false, false, true);
					$catalogResourceList     = $catalogSearchObject->getResultListHTML($this->listId, $this->allowEdit, $idsToDisplayForThisPage);

				} // Solr Sorted Catalog Only Search //
				else{
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
			/** @var SearchObject_Islandora $archiveSearchObject */
			$archiveSearchObject = SearchObjectFactory::initSearchObject('Islandora');
			$archiveSearchObject->init();
			$archiveSearchObject->setPrimarySearch(true);
			$archiveSearchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', 'administrator');
			$archiveSearchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', 'no');
			$archiveSearchObject->setLimit($recordsPerPage);

			if (!$this->isUserListSort && !$this->isMixedUserList){ // is a solr sort
				$archiveSearchObject->setSort($this->sort);           // set solr sort. (have to set before retrieving solr sort options below)
			}
			if (!$this->isMixedUserList){
				$IslandoraSortList = $archiveSearchObject->getSortList(); // get all the search sort options (retrieve after setting solr sort option)
				foreach ($this->islandoraSortOptions as $option){ // extract just the ones we want
					if (isset ($IslandoraSortList[$option])){
						$sortOptions[$option]        = $IslandoraSortList[$option];
						$defaultSortOptions[$option] = $IslandoraSortList[$option]['desc'];
					}
				}
			}
			foreach ($this->userListSortOptions as $option => $value_ignored){ // Non-Solr options
				if (!isset($sortOptions[$option])){ // Skip if already done by the catalog searches above
					$sortOptions[$option]        = [
						'sortUrl'  => $archiveSearchObject->renderLinkWithSort($option),
						'desc'     => "sort_{$option}_userlist", // description in translation dictionary
						'selected' => ($option == $this->sort)
					];
					$defaultSortOptions[$option] = "sort_{$option}_userlist";
				}
			}


			// Archive Only Searches //
			if (!$this->isMixedUserList){
				// User Sorted Archive Only Searches
				if ($this->isUserListSort){
					$archiveSearchObject->setLimit(count($this->archiveIds)); // fetch all archive items so that search filters can be applied
					$archiveSearchObject->setPage(1); // set to the first page for the search only

					$archiveSearchObject->setQueryIDs($this->archiveIds); // do solr search by Ids
					$archiveResult           = $archiveSearchObject->processSearch(false, true, true);
					$pageInfo['resultTotal'] = $archiveResult['response']['numFound'];
					$archiveSearchObject->setPage($page); // Set back to the actual page of the list now that search was processed
					$catalogSearchObject->setLimit($recordsPerPage); // Set the actual limit per page

					// Get ids for list after search filters have been applied
					$searchFilteredIds         = array_column($archiveResult['response']['docs'], 'id');
					$remainingIdsInSortedOrder = array_intersect($this->archiveIds, $searchFilteredIds);
					$idsToDisplayForThisPage   = array_slice($remainingIdsInSortedOrder, $startRecord - 1, $recordsPerPage);

					$archiveResourceList = $archiveSearchObject->getResultListHTML($this->listId, $this->allowEdit, $idsToDisplayForThisPage);
				}// Islandora Sorted Archive Only Searches
				else{
					$archiveSearchObject->setQueryIDs($this->archiveIds); // do Islandora search by Ids
					$archiveSearchObject->setPage($page);                 // set to the first page for the search only
					$archiveResult       = $archiveSearchObject->processSearch(false, true);
					$archiveResourceList = $archiveSearchObject->getResultListHTML($this->listId, $this->allowEdit);
					$pageInfo['resultTotal'] = $archiveResult['response']['numFound'];
				}

				//Only show search filter options when not mixed user list
				$interface->assign('userListHasSearchFilters', true);
				$interface->assign('topRecommendations', $archiveSearchObject->getRecommendationsTemplates('top'));
				$interface->assign('sideRecommendations', $archiveSearchObject->getRecommendationsTemplates('side'));
				// Display search facets on a user list. Has to happen after processSearch() where recommendations are initialized.

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
		$interface->assign('recordEnd', $pageInfo['endRecord']);
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

	function getTitles($numListEntries, $applyFiltering = false){
		// Currently, only used by AJAX call for emailing lists

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
		return array_merge($catalogRecordSet, $archiveRecordSet);
	}

	function getCitations($citationFormat){
		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Retrieve records from index (currently, only Solr IDs supported):
		if (count($this->favorites) > 0){
			$searchObject->setQueryIDs($this->favorites);

			$searchObject->processSearch();
			return $searchObject->getCitations($citationFormat);
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
