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

require_once ROOT_DIR . '/AJAXHandler.php';
require_once ROOT_DIR . '/sys/Pager.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';

class ListAPI extends AJAXHandler {

	protected $methodsThatRespondWithJSONResultWrapper = array(
		'getAllListIds',
		'getPublicLists',
		'getUserLists',
		'getSystemLists',
		'getListTitles',
		'getCacheInfoForList',
		'getCacheInfoForListId',
		'createList',
		'addTitlesToList',
		'clearListTitles',
		'getAvailableListsFromNYT',
		'createUserListFromNYT',
	);

	protected $methodsThatRespondThemselves = array(
		'getRSSFeed',
	);

	function getAllListIds(){
		$allListNames = array();
		$publicLists  = $this->getPublicLists();
		if ($publicLists['success'] = true){
			foreach ($publicLists['lists'] as $listInfo){
				$allListNames[] = $listInfo['id'];
				$allListNames[] = 'list:' . $listInfo['id'];
			}
		}
		$systemLists = $this->getSystemLists();
		if ($systemLists['success'] = true){
			foreach ($systemLists['lists'] as $listInfo){
				$allListNames[] = $listInfo['id'];
			}
		}
		return $allListNames;
	}

	/**
	 * Get all public lists
	 * includes id, title, description, and number of titles
	 */
	function getPublicLists(){
		$list         = new UserList();
		$list->public = 1;
		$list->find();
		$results = array();
		if ($list->N > 0){
			while ($list->fetch()){
				$userListEntries         = new UserListEntry();
				$userListEntries->listId = $list->id;
				$numTitles               = $userListEntries->count();

				$results[] = array(
					'id'          => $list->id,
					'title'       => $list->title,
					'description' => $list->description,
					'numTitles'   => $numTitles,
				);
			}
		}
		return array('success' => true, 'lists' => $results);
	}

	/**
	 * Get all lists that a particular user has created.
	 * includes id, title, description, number of titles, and whether or not the list is public
	 */
	function getUserLists(){
		$username = $_REQUEST['username'];
		$password = $_REQUEST['password'];
		$user     = UserAccount::validateAccount($username, $password);
		if (!isset($_REQUEST['username']) || !isset($_REQUEST['password'])){
			return array('success' => false, 'message' => 'The username and password must be provided to load lists.');
		}

		if ($user == false){
			return array('success' => false, 'message' => 'Sorry, we could not find a user with those credentials.');
		}

		$userId = $user->id;

		$list          = new UserList();
		$list->user_id = $userId;
		$list->find();
		$results = array();
		if ($list->N > 0){
			while ($list->fetch()){
				$results[] = array(
					'id'          => $list->id,
					'title'       => $list->title,
					'description' => $list->description,
					'numTitles'   => $list->numValidListItems(),
					'public'      => $list->public == 1,
				);
			}
		}
		require_once ROOT_DIR . '/sys/LocalEnrichment/Suggestions.php';
		$suggestions = Suggestions::getSuggestions($userId);
		if (count($suggestions) > 0){
			$results[] = array(
				'id'          => 'recommendations',
				'title'       => 'User Recommendations',
				'description' => 'Personalized Recommendations based on ratings.',
				'numTitles'   => count($suggestions),
				'public'      => false,
			);
		}
		return array('success' => true, 'lists' => $results);
	}

	/**
	 * Gets RSS Feed version of a User List
	 */
	function getRSSFeed(){
		global $configArray;

		$rssFeed = '<rss version="2.0">';
		$rssFeed .= '<channel>';

		if (!isset($_REQUEST['id'])){
			$rssFeed .= '<error>No ID Provided</error>';
		}else{
			$listId  = $_REQUEST['id'];
			$curDate = date("D M j G:i:s T Y");

			//Grab the title based on the list that id that is passed in
			$titleData  = $this->getListTitles($listId);
			$titleCount = count($titleData["titles"]);

			if ($titleCount > 0){

				$listTitle = $titleData["listTitle"];
				$listDesc  = $titleData["listDescription"];

				$rssFeed .= '<title>' . $listTitle . '</title>';
				$rssFeed .= '<language>en-us</language>';
				$rssFeed .= '<description>' . $listDesc . '</description>';
				$rssFeed .= '<lastBuildDate>' . $curDate . '</lastBuildDate>';
				$rssFeed .= '<pubDate>' . $curDate . '</pubDate>';
				$rssFeed .= '<link>' . htmlspecialchars($configArray['Site']['url'] . '/API/ListAPI?method=getRSSFeed&id=' . $listId) . '</link>';

				foreach ($titleData["titles"] as $title){
					$titleId     = $title["id"];
					$image       = $title["image"];
					$bookTitle   = $title["title"];
					$bookTitle   = rtrim($bookTitle, " /");
					$author      = $title["author"];
					$description = $title["description"];
					$length      = $title["length"];
					$publisher   = $title["publisher"];

					if (isset($title["dateSaved"])){
						$pubDate = $title["dateSaved"];
					}else{
						$pubDate = "No Date Available";
					}


					$rssFeed  .= '<item>';
					$rssFeed  .= '<id>' . $titleId . '</id>';
					$rssFeed  .= '<image>' . htmlspecialchars($image) . '</image>';
					$rssFeed  .= '<title>' . htmlspecialchars($bookTitle) . '</title>';
					$rssFeed  .= '<author>' . htmlspecialchars($author) . '</author>';
					$itemLink = htmlspecialchars($configArray['Site']['url'] . '/Record/' . $titleId);

					$fullDescription = "<a href='{$itemLink}'><img src='{$image}' alt='cover'/></a>$description";
					$rssFeed         .= '<description>' . htmlspecialchars($fullDescription) . '</description>';
					$rssFeed         .= '<length>' . $length . '</length>';
					$rssFeed         .= '<publisher>' . htmlspecialchars($publisher) . '</publisher>';
					$rssFeed         .= '<pubDate>' . $pubDate . '</pubDate>';
					$rssFeed         .= '<link>' . $itemLink . '</link>';

					$rssFeed .= '</item>';

				}
			}else{
				$rssFeed .= '<error>No Titles Listed</error>';
			}

		}

		$rssFeed .= '</channel>';
		$rssFeed .= '</rss>';

		$xml = '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
		$xml .= $rssFeed;

		$this->sendHTTPHeaders('text/xml');
		echo $xml;

	}

	/**
	 * Get all system generated lists that are available.
	 * includes id, title, description, and number of titles
	 */
	function getSystemLists(){
		//System lists are not stored in tables, but are generated based on
		//a variety of factors.
		$systemLists[] = array(
			'id'          => 'newfic',
			'title'       => 'New Fiction',
			'description' => 'A selection of New Fiction Titles that have arrived recently or are on order.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newnonfic',
			'title'       => 'New Non-Fiction',
			'description' => 'A selection of New Non-Fiction Titles that have arrived recently or are on order.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newdvd',
			'title'       => 'New DVDs',
			'description' => 'A selection of New DVDs that have arrived recently or are on order.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newmyst',
			'title'       => 'New Mysteries',
			'description' => 'A selection of New Mystery Books that have arrived recently or are on order.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newaudio',
			'title'       => 'New Audio',
			'description' => 'A selection of New Audio Books that have arrived recently or are on order.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newya',
			'title'       => 'New Young Adult',
			'description' => 'A selection of New Titles appropriate for Young Adult that have arrived recently or are on order.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newkids',
			'title'       => 'New Kids',
			'description' => 'A selection of New Titles appropriate for children that have arrived recently or are on order.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newEpub',
			'title'       => 'New Online Books',
			'description' => 'The most recently added online books in the catalog.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'newebooks',
			'title'       => 'New eBooks',
			'description' => 'The most recently added online books in the catalog.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'comingsoonfic',
			'title'       => 'Coming Soon Fiction',
			'description' => 'A selection of Fiction Titles that are on order and due in soon.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'comingsoonnonfic',
			'title'       => 'Coming Soon Non-Fiction',
			'description' => 'A selection of Non-Fiction Titles that are on order and due in soon.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'comingsoondvd',
			'title'       => 'Coming Soon DVDs',
			'description' => 'A selection of DVDs that are on order and due in soon.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'comingsoonkids',
			'title'       => 'Coming Soon Kids',
			'description' => 'A selection of Kids Titles that are on order and due in soon.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'comingsoonya',
			'title'       => 'Coming Soon Young Adult',
			'description' => 'A selection of Young Adult Titles that are on order and due in soon.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'comingsoonmusic',
			'title'       => 'Coming Soon Music',
			'description' => 'A selection of Music Tiles that are on order and due in soon.',
			'numTitles'   => 30,
		);
/*		$systemLists[] = array(
			'id'          => 'popularEpub',
			'title'       => 'Popular Online Books',
			'description' => 'The most popular books that are available to read online.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'availableEpub',
			'title'       => 'Available Online Books',
			'description' => 'Online books that can be read immediately.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'recommendedEpub',
			'title'       => 'Recommended Online Books',
			'description' => 'Online books that you may like based on your ratings and reading history.',
			'numTitles'   => 30,
		);*/
		$systemLists[] = array(
			'id'          => 'recentlyReviewed',
			'title'       => 'Recently Reviewed',
			'description' => 'Titles that have had new reviews added to them.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'highestRated',
			'title'       => 'Highly Rated',
			'description' => 'Titles that have the highest ratings within the catalog.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'mostPopular',
			'title'       => 'Most Popular Titles',
			'description' => 'Most Popular titles based on checkout history.',
			'numTitles'   => 30,
		);
		$systemLists[] = array(
			'id'          => 'recommendations',
			'title'       => 'Recommended For You',
			'description' => 'Titles Recommended for you based off your ratings.',
			'numTitles'   => 30,
		);
		return array('success' => true, 'lists' => $systemLists);
	}

	private function _getUserListTitles($listId, $numTitlesToShow){
		//The list is a patron generated list
		$list     = new UserList();
		$list->id = $listId;
		if ($list->find(true)){
			//Make sure the user has access to the list
			if ($list->public == 0){
				if (!isset($user)){
					return array('success' => false, 'message' => 'The user was invalid.  A valid user must be provided for private lists.');
				}elseif ($list->user_id != $user->id){
					return array('success' => false, 'message' => 'The user does not have access to this list.');
				}
			}

			require_once ROOT_DIR . '/sys/LocalEnrichment/FavoriteHandler.php';
			$user               = UserAccount::getLoggedInUser();
			$favoriteHandler    = new FavoriteHandler($list, false);
			$isMixedContentList = $favoriteHandler->isMixedUserList();
			$orderedListOfIds   = $isMixedContentList ? $favoriteHandler->getFavorites() : array();
			// Use this array to combined Mixed Lists Back into their list-defined order

			$catalogItems = $archiveItems = array();
			$catalogIds   = $favoriteHandler->getCatalogIds();
			$archiveIds   = $favoriteHandler->getArchiveIds();
			if (count($catalogIds) > 0){
				$catalogItems = $this->loadTitleInformationForIds($catalogIds, $numTitlesToShow, $orderedListOfIds);
			}
			if (count($archiveIds) > 0){
				$archiveItems = $this->loadArchiveInformationForIds($archiveIds, $numTitlesToShow, $orderedListOfIds);
			}
			if ($isMixedContentList){
				$titles = $catalogItems + $archiveItems;
				ksort($titles, SORT_NUMERIC);
				$titles = array_slice($titles, 0, $numTitlesToShow);

			}else{
				$titles = $catalogItems + $archiveItems; // One of these should always be empty, but add them together just in case
			}


			return array('success' => true, 'listName' => $list->title, 'listDescription' => $list->description, 'titles' => $titles, 'cacheLength' => 24);
		}else{
			return array('success' => false, 'message' => 'The specified list could not be found.');
		}
	}

	/**
	 * Returns information about the titles within a list including:
	 * - Title, Author, Bookcover URL, description, record id
	 *
	 * @param string  $listId          - The list to show
	 * @param integer $numTitlesToShow - the maximum number of titles that should be shown
	 * @return array
	 */
	function getListTitles($listId = null, $numTitlesToShow = 25){
		global $configArray;

		if (!$listId){
			if (!isset($_REQUEST['id'])){
				return ['success' => false, 'message' => 'The id of the list to load must be provided as the id parameter.'];
			}
			$listId = $_REQUEST['id'];
		}

		if (isset($_REQUEST['username']) && isset($_REQUEST['password'])){
			$username = $_REQUEST['username'];
			$password = $_REQUEST['password'];
			$user     = UserAccount::validateAccount($username, $password);
		}else{
			$user = UserAccount::getLoggedInUser();
		}

		if (!is_numeric($numTitlesToShow)){
			$numTitlesToShow = 25;
		}

		if (is_numeric($listId) || preg_match('/list[-:](.*)/', $listId, $listInfo)){
			if (isset($listInfo)){
				$listId = $listInfo[1];
			}
			return $this->_getUserListTitles($listId, $numTitlesToShow);
		}elseif (preg_match('/search:(?<searchID>.*)/', $listId, $searchInfo)){
			if (is_numeric($searchInfo[1])){
				$titles = $this->getSavedSearchTitles($searchInfo[1], $numTitlesToShow);
				if ($titles === false){ // Didn't find saved search
					return ['success' => false, 'message' => 'The specified search could not be found.'];
				}else{ // successful search with or without any results. (javascript can handle no results returned.)
					return ['success' => true, 'listTitle' => $listId, 'listDescription' => "Search Results", 'titles' => $titles, 'cacheLength' => 4];
				}
			}else{
				//Do a default search
				$titles = $this->getSystemListTitles($listId, $numTitlesToShow);
				if (count($titles) > 0){
					return ['success' => true, 'listTitle' => $listId, 'listDescription' => "System Generated List", 'titles' => $titles, 'cacheLength' => 4];
				}else{
					return ['success' => false, 'message' => 'The specified list could not be found.'];
				}
			}

		}else{
			$systemList  = null;
			$systemLists = $this->getSystemLists();
			foreach ($systemLists['lists'] as $curSystemList){
				if ($curSystemList['id'] == $listId){
					$systemList = $curSystemList;
					break;
				}
			}
			//The list is a system generated list
			switch ($listId){
				case 'recommendations':
					if (!$user){
						return ['success' => false, 'message' => 'A valid user must be provided to load recommendations.'];
					}else{
						$userId = $user->id;
						require_once ROOT_DIR . '/sys/LocalEnrichment/Suggestions.php';
						$suggestions = Suggestions::getSuggestions($userId);
						$titles      = [];
						foreach ($suggestions as $id => $suggestion){
							$imageUrl = $configArray['Site']['coverUrl'] . "/bookcover.php?id=" . $id;
							if (isset($suggestion['titleInfo']['issn'])){
								$imageUrl .= "&issn=" . $suggestion['titleInfo']['issn'];
							}
							if (isset($suggestion['titleInfo']['isbn10'])){
								$imageUrl .= "&isn=" . $suggestion['titleInfo']['isbn10'];
							}
							if (isset($suggestion['titleInfo']['upc'])){
								$imageUrl .= "&upc=" . $suggestion['titleInfo']['upc'];
							}
							if (isset($suggestion['titleInfo']['format_category'])){
								$imageUrl .= "&category=" . $suggestion['titleInfo']['format_category'];
							}
							$smallImageUrl = $imageUrl . "&size=small";
							$imageUrl      .= "&size=medium";
							$titles[]      = [
								'id'          => $id,
								'image'       => $imageUrl,
								'small_image' => $smallImageUrl,
								'title'       => $suggestion['titleInfo']['title'],
								'author'      => $suggestion['titleInfo']['author'],
							];
						}
						return ['success' => true, 'listTitle' => $systemList['title'], 'listDescription' => $systemList['description'], 'titles' => $titles, 'cacheLength' => 0];
					}
				case 'highestRated':
				case 'recentlyReviewed':
				case 'mostPopular':
				default :
					return ['success' => false, 'message' => 'The specified list could not be found.'];

			}
		}
	}

	/**
	 * Loads caching information to determine what the list should be cached as
	 * and whether it is cached for all users and products (general), for a single user,
	 * or for a single product.
	 */
	function getCacheInfoForList(){
		if (!isset($_REQUEST['id'])){
			return ['success' => false, 'message' => 'The id of the list to load must be provided as the id parameter.'];
		}

		$listId = $_REQUEST['id'];
		return $this->getCacheInfoForListId($listId);
	}

	function getCacheInfoForListId($listId){
		global $configArray;

		if (is_numeric($listId) || preg_match('/list[-:](.*)/', $listId, $listInfo)){
			if (isset($listInfo[1])){
				$listId = $listInfo[1];
			}
			return [
				'cacheType'    => 'general',
				'cacheName'    => 'list_general_list:' . $listId,
				'cacheLength'  => $configArray['Caching']['list_general'],
				'fullListLink' => '/MyAccount/MyList/' . $listId,
			];
		}elseif ($listId == 'highestRated'){
			return [
				'cacheType'    => 'general',
				'cacheName'    => 'list_highest_rated_' . $listId,
				'cacheLength'  => $configArray['Caching']['list_highest_rated'],
				'fullListLink' => '',
			];
		}elseif ($listId == 'recentlyReviewed'){
			return [
				'cacheType'    => 'general',
				'cacheName'    => 'list_recently_reviewed_' . $listId,
				'cacheLength'  => $configArray['Caching']['list_recently_reviewed'],
				'fullListLink' => '',
			];
		}elseif ($listId == 'mostPopular'){
			return [
				'cacheType'    => 'general',
				'cacheName'    => 'list_most_popular_' . $listId,
				'cacheLength'  => $configArray['Caching']['list_most_popular'],
				'fullListLink' => '',
			];
		}elseif ($listId == 'recommendations'){

			if (isset($_REQUEST['username']) && isset($_REQUEST['password'])){
				$username = $_REQUEST['username'];
				$password = $_REQUEST['password'];
				$user     = UserAccount::validateAccount($username, $password);
			}else{
				$user = UserAccount::getLoggedInUser();
			}

			return [
				'cacheType'    => 'user',
				'cacheName'    => 'list_recommendations_' . $listId . '_' . $user->id,
				'cacheLength'  => $configArray['Caching']['list_recommendations'],
				'fullListLink' => '',
			];
		}elseif (preg_match('/^search:(.*)/', $listId, $searchInfo)){
			if (is_numeric($searchInfo[1])){
				$searchId = $searchInfo[1];
				return [
					'cacheType'    => 'general',
					'cacheName'    => 'list_general_search_' . $searchId,
					'cacheLength'  => $configArray['Caching']['list_general'],
					'fullListLink' => '/Search/Results?saved=' . $searchId,
				];
			}else{
				$requestUri = $_SERVER['REQUEST_URI'];
				$requestUri = str_replace("&reload", "", $requestUri);
				return [
					'cacheType'    => 'general',
					'cacheName'    => 'list_general_search_' . md5($requestUri),
					'cacheLength'  => $configArray['Caching']['list_general'],
					'fullListLink' => '',
				];
			}
		}else{
			return [
				'cacheType'    => 'general',
				'cacheName'    => 'list_general_' . $listId,
				'cacheLength'  => $configArray['Caching']['list_general'],
				'fullListLink' => '',
			];
		}
	}

	private function loadTitleInformationForIds($ids, $numTitlesToShow, $orderedListOfIds = array()){
		$titles = array();
		if (count($ids) > 0){
			/** @var SearchObject_Solr $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init();
			$searchObject->setLimit($numTitlesToShow);
			$searchObject->setQueryIDs($ids);
			$searchObject->processSearch();
			$titles = $searchObject->getListWidgetTitles($orderedListOfIds);
		}
		return $titles;
	}

	private function loadArchiveInformationForIds($ids, $numTitlesToShow, $orderedListOfIds = array()){
		$titles = array();
		if (count($ids) > 0){
			/** @var SearchObject_Islandora $archiveSearchObject */
			$archiveSearchObject = SearchObjectFactory::initSearchObject('Islandora');
			$archiveSearchObject->init();
			$archiveSearchObject->setPrimarySearch(true);
			$archiveSearchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$archiveSearchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
			$archiveSearchObject->setLimit($numTitlesToShow);
			$archiveSearchObject->setQueryIDs($ids);
			$archiveSearchObject->processSearch();
			$titles = $archiveSearchObject->getListWidgetTitles($orderedListOfIds);
		}
		return $titles;
	}

	private function getSavedSearchTitles($searchId, $numTitlesToShow){
		/** @var Memcache $memCache */
		global $memCache;
		global $configArray;
		$memCacheKey    = 'saved_search_titles_' . $searchId;
		$listTitles = $memCache->get($memCacheKey);
		if ($listTitles == false || isset($_REQUEST['reload'])){
			//return a random selection of 30 titles from the list.
			/** @var SearchObject_Solr|SearchObject_Base $searchObj */
			$searchObj = SearchObjectFactory::initSearchObject();
			$searchObj->init();
			$searchObj = $searchObj->restoreSavedSearch($searchId, false, true);
			if ($searchObj){ // check that the saved search was retrieved successfully
				if (isset($_REQUEST['numTitles'])){
					$searchObj->setLimit($_REQUEST['numTitles']);
				}else{
					$searchObj->setLimit($numTitlesToShow);
				}
				$searchObj->processSearch(false, false);
				$listTitles = $searchObj->getListWidgetTitles();

				$memCache->set($memCacheKey, $listTitles, 0, $configArray['Caching']['list_saved_search']);
			}
		}

		return $listTitles;
	}

	/**
	 * Create a User list for the user.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>title    - The title of the list to create.</li>
	 * <li>description - A description for the list (optional).</li>
	 * <li>public   - Set to true or 1 if the list should be public.  (optional, defaults to private).</li>
	 * </ul>
	 *
	 * Note: You may also provide the parameters to addTitlesToList and titles will be added to the list
	 * after the list is created.
	 *
	 * Returns:
	 * <ul>
	 * <li>success - true if the account is valid and the list could be created, false if the username or password were incorrect or the list could not be created.</li>
	 * <li>listId - the id of the new list that is created.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * http://catalog.douglascountylibraries.org/API/ListAPI?method=createList&username=23025003575917&password=1234&title=Test+List&description=Test&public=0
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{"success":true,"listId":"1688"}}
	 * </code>
	 */
	function createList(){
		$username = $_REQUEST['username'];
		$password = $_REQUEST['password'];
		if (!isset($_REQUEST['title'])){
			return ['success' => false, 'message' => 'You must provide the title of the list to be created.'];
		}
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$list              = new UserList();
			$list->title       = $_REQUEST['title'];
			$list->description = strip_tags($_REQUEST['description'] ?? '');
			$list->public      = isset($_REQUEST['public']) ? (($_REQUEST['public'] == true || $_REQUEST['public'] == 1) ? 1 : 0) : 0;
			$list->user_id     = $user->id;
			$list->insert();
			$list->find();
			if (isset($_REQUEST['recordIds'])){
				$_REQUEST['listId'] = $list->id;
				$result             = $this->addTitlesToList();
				return $result;
			}
			return ['success' => true, 'listId' => $list->id];
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Add titles to a user list.
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>listId   - The id of the list to add items to.</li>
	 * <li>recordIds - The id of the record(s) to add to the list.</li>
	 * <li>tags   - A comma separated string of tags to apply to the titles within the list. (optional)</li>
	 * <li>notes  - descriptive text to apply to the titles.  Can be viewed while on the list.  Notes will apply to all titles being added.  (optional)</li>
	 * </ul>
	 *
	 * Note: You may also provide the parameters to addTitlesToList and titles will be added to the list
	 * after the list is created.
	 *
	 * Returns:
	 * <ul>
	 * <li>success - true if the account is valid and the titles could be added to the list, false if the username or password were incorrect or the list could not be created.</li>
	 * <li>listId - the id of the list that titles were added to.</li>
	 * <li>numAdded - the number of titles that were added to the list.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * http://catalog.douglascountylibraries.org/API/ListAPI?method=createList&username=23025003575917&password=1234&title=Test+List&description=Test&public=0
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{"success":true,"listId":"1688"}}
	 * </code>
	 */
	function addTitlesToList(){
		$username = $_REQUEST['username'];
		$password = $_REQUEST['password'];
		if (!isset($_REQUEST['listId'])){
			return ['success' => false, 'message' => 'You must provide the listId to add titles to.'];
		}
		$recordIds = [];
		if (!isset($_REQUEST['recordIds'])){
			return ['success' => false, 'message' => 'You must provide one or more records to add to the list.'];
		}else{
			if (!is_array($_REQUEST['recordIds'])){
				$recordIds[] = $_REQUEST['recordIds'];
			}else{
				$recordIds = $_REQUEST['recordIds'];
			}
		}
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$list          = new UserList();
			$list->id      = $_REQUEST['listId'];
			$list->user_id = $user->id;
			if (!$list->find(true)){
				return ['success' => false, 'message' => 'Unable to find the list to add titles to.'];
			}else{
				$numAdded = 0;
				foreach ($recordIds as $id){
					require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
					$userListEntry         = new UserListEntry();
					$userListEntry->listId = $list->id;
					if (preg_match("/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}|[A-Z0-9_-]+:[A-Z0-9_-]+$/i", $id)){
						// Is not a valid grouped work Id or archive PID
						$userListEntry->groupedWorkPermanentId = $id;

						$existingEntry = false;
						if ($userListEntry->find(true)){
							$existingEntry = true;
						}

						if (isset($_REQUEST['notes'])){
							$notes = $_REQUEST['notes'];
						}else{
							$notes = '';
						}
						$userListEntry->notes     = strip_tags($notes);
						$userListEntry->dateAdded = time();
						if ($existingEntry){
							$userListEntry->update();
						}else{
							$userListEntry->insert();
						}
						$numAdded++;
					}
				}
				return ['success' => true, 'listId' => $list->id, 'numAdded' => $numAdded];
			}


		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	/**
	 * Clears all titles on a list given a list id
	 *
	 * Parameters:
	 * <ul>
	 * <li>username - The barcode of the user.  Can be truncated to the last 7 or 9 digits.</li>
	 * <li>password - The pin number for the user. </li>
	 * <li>listId   - The id of the list to add items to.</li>
	 * </ul>
	 *
	 * Returns:
	 * <ul>
	 * <li>success - true if the account is valid and the titles could be added to the list, false if the username or password were incorrect or the list could not be created.</li>
	 * </ul>
	 *
	 * Sample Call:
	 * <code>
	 * http://catalog.douglascountylibraries.org/API/ListAPI?method=clearListTitles&username=23025003575917&password=1234&listId=1234
	 * </code>
	 *
	 * Sample Response:
	 * <code>
	 * {"result":{"success":true}}
	 * </code>
	 */
	function clearListTitles(){
		$username = $_REQUEST['username'];
		$password = $_REQUEST['password'];
		if (!isset($_REQUEST['listId'])){
			return ['success' => false, 'message' => 'You must provide the listId to clear titles from.'];
		}
		$user = UserAccount::validateAccount($username, $password);
		if ($user && !PEAR_Singleton::isError($user)){
			$list          = new UserList();
			$list->id      = $_REQUEST['listId'];
			$list->user_id = $user->id;
			if (!$list->find(true)){
				return array('success' => false, 'message' => 'Unable to find the list to clear titles from.');
			}else{
				$list->removeAllListEntries();
				return ['success' => true];
			}
		}else{
			return ['success' => false, 'message' => 'Login unsuccessful'];
		}
	}

	private function getSystemListTitles($listName, $numTitlesToShow){
		/** @var Memcache $memCache */
		global $memCache;
		global $configArray;
		$listTitles = $memCache->get('system_list_titles_' . $listName);
		if ($listTitles == false || isset($_REQUEST['reload'])){
			//return a random selection of 30 titles from the list.
			$searchObj = SearchObjectFactory::initSearchObject();
			$searchObj->init();
			$searchObj->setBasicQuery("*:*");
			if (!preg_match('/^search:/', $listName)){
				$searchObj->addFilter("system_list:$listName");
			}
			if (isset($_REQUEST['numTitles'])){
				$searchObj->setLimit($_REQUEST['numTitles']);
			}else{
				$searchObj->setLimit($numTitlesToShow);
			}
			$searchObj->processSearch(false, false);
			$listTitles = $searchObj->getListWidgetTitles();

			$memCache->set('system_list_titles_' . $listName, $listTitles, 0, $configArray['Caching']['system_list_titles']);
		}
		return $listTitles;
	}

	/**
	 * Retrieves the Available New York Times Lists from their API
	 * If url parameter 'name' is set to an list_name_encoded it will return their data about that list
	 *
	 * @return array|mixed
	 */
	public function getAvailableListsFromNYT(){
		global $configArray;

		if (empty($configArray['NYT_API']['books_API_key'])){
			return [
				'success' => false,
				'message' => 'API Key missing',
			];
		}
		$api_key = $configArray['NYT_API']['books_API_key'];

		$listName = 'names';
		if (!empty($_REQUEST['name']) && !is_array($_REQUEST['name'])){
			$listName = trim($_REQUEST['name']);
		}

		// Get the raw response from the API with a list of all the names
		$nyt_api = new ExternalEnrichment\NYTApi($api_key);
		return $nyt_api->getList($listName);
	}

	/**
	 * Creates or updates a user defined list from information obtained from the New York Times API
	 *
	 * @param string|null $selectedList machine-readable name of the new york times list
	 * @return array
	 */
	public function createUserListFromNYT(string $selectedList = null){
		global $configArray;

		if ($selectedList == null){
			$selectedList = $_REQUEST['listToUpdate'];
		}

		if (empty($configArray['NYT_API']['books_API_key'])){
			return [
				'success' => false,
				'message' => 'API Key missing',
			];
		}
		$api_key      = $configArray['NYT_API']['books_API_key'];
		$pikaUsername = $configArray['NYT_API']['pika_username'];
		$pikaPassword = $configArray['NYT_API']['pika_password'];

		if (empty($pikaUsername) || empty($pikaPassword)){
			return [
				'success' => false,
				'message' => 'Pika NY Times user not set',
			];
		}

		$pikaUser = UserAccount::validateAccount($pikaUsername, $pikaPassword);
		if (!$pikaUser || PEAR_Singleton::isError($pikaUser)){
			return [
				'success' => false,
				'message' => 'Invalid Pika NY Times user',
			];
		}

		//Get from the API with a list of all the names
		$nyt_api        = new ExternalEnrichment\NYTApi($api_key);
		$availableLists = $nyt_api->getList('names');

		//Get the human-readable title for our selected list
		$selectedListTitle       = null;
		$selectedListTitleShort  = null;
		$selectedListDescription = null;
		//Get the title and description for the selected list
		foreach ($availableLists->results as $listInformation){
			if ($listInformation->list_name_encoded == $selectedList){
				$selectedListTitle       = 'NYT - ' . $listInformation->display_name;
				$selectedListTitleShort  = $listInformation->display_name;
				$selectedListDescription = 'New York Times - ' . $selectedListTitleShort . ' - Published on ' . $listInformation->newest_published_date;
				break;
			}
		}
		if (empty($selectedListTitleShort)){
			return [
				'success' => false,
				'message' => "We did not find list '$selectedList' in The New York Times API",
			];
		}

		// Look for selected List
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		$nytList          = new UserList();
		$nytList->user_id = $pikaUser->id;
		$nytList->title   = $selectedListTitle;
		$listExistsInPika = $nytList->find(true);

		//We didn't find the list in Pika, create one
		if (!$listExistsInPika){
			$nytList              = new UserList();
			$nytList->title       = $selectedListTitle;
			$nytList->description = $selectedListDescription;
			$nytList->public      = 1;
			$nytList->defaultSort = 'custom';
			$nytList->user_id     = $pikaUser->id;
			$success              = $nytList->insert();
			$nytList->find(true);

			if ($success){
				$listID  = $nytList->id;
				$results = [
					'success' => true,
					'message' => "Created list <a href='/MyAccount/MyList/{$listID}'>{$selectedListTitle}</a>",
				];
			}else{
				return [
					'success' => false,
					'message' => 'Could not create list',
				];
			}

		}else{
			$listID  = $nytList->id;
			$results = [
				'success' => true,
				'message' => "Updated list <a href='/MyAccount/MyList/{$listID}'>{$selectedListTitle}</a>",
			];
			//We already have a list, clear the contents, so we don't have titles from last time
			$nytList->removeAllListEntries();
		}

		// We need to add titles to the list //

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Search/' . $configArray['Index']['engine'] . '.php';
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();

		// Include UserListEntry Class
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';

		//Get a list of titles from NYT API
		$availableLists = $nyt_api->getList($selectedList);
		$numTitlesAdded = 0;
		$titleResults = $availableLists->results;
		foreach ($availableLists->results->books as $bookDetails){
			$pikaID = null;
			// go through each list item

			require_once ROOT_DIR . '/sys/ISBN/ISBN.php';
			// Try fetching the primary ISBN first
			if (!empty($bookDetails)){
				$ISBNs = [];
				if (!empty($bookDetails->primary_isbn13) && strcasecmp($bookDetails->primary_isbn13, 'none') != 0){
					$ISBNs[] = $bookDetails->primary_isbn13;
				}
				if (!empty($bookDetails->primary_isbn10) && strcasecmp($bookDetails->primary_isbn10, 'none') != 0){
					$isbnObj = new ISBN($bookDetails->primary_isbn10);
					$ISBNs[] = $isbnObj->get13();
				}
				if (!empty($ISBNs)){
					$searchDocument = $searchObject->getRecordByIsbn(array_unique($ISBNs), 'id');
					$pikaID         = $searchDocument['id'] ?? null;
				}
			}
			// If no luck with Primary ISBNs, or no primary ISBNs, try the rest of the ISBNs
			if ($pikaID == null){
				$ISBNs = [];
				if (!empty($bookDetails->isbns)){
					//Note : each entry typically comes with an isbn10 & isbn13, which are usually equivalent; but we have seen
					//  an example of this not being so (and the isbn10 being the one we needed [once converted to isbn13]).
					foreach ($bookDetails->isbns as $isbnEntry){
						if (!empty($isbnEntry->isbn13) && ISBN::isValidISBN13($isbnEntry->isbn13)){
							$ISBNs[] = $isbnEntry->isbn13;
						}
						if (!empty($isbnEntry->isbn10)){
							// We have now seen an example where the isbn10 was "None"
							$isbnObj = new ISBN($isbnEntry->isbn10);
							if ($isbnObj->isValid()){
								$ISBNs[] = $isbnObj->get13();
							}
						}
					}
				}
				$searchDocument = $searchObject->getRecordByIsbn(array_unique($ISBNs), 'id');
				$pikaID         = $searchDocument['id'] ?? null;
			}

			if ($pikaID != null){
				$note = "#{$bookDetails->rank} on the {$titleResults->display_name} list for {$titleResults->published_date}.";
				if ($bookDetails->rank_last_week != 0){
					$note .= '  Last week it was ranked ' . $bookDetails->rank_last_week . '.';
				}
				if ($bookDetails->weeks_on_list != 0){
					$note .= "  It has been on the list for {$bookDetails->weeks_on_list} week(s).";
				}

				$userListEntry                         = new UserListEntry();
				$userListEntry->listId                 = $nytList->id;
				$userListEntry->groupedWorkPermanentId = $pikaID;

				$existingEntry = false;
				if ($userListEntry->find(true)){
					$existingEntry = true;
				}

				$userListEntry->weight    = $bookDetails->rank;
				$userListEntry->notes     = $note;
				$userListEntry->dateAdded = time();
				if ($existingEntry){
					if ($userListEntry->update()){
						$numTitlesAdded++;
					}
				}else{
					if ($userListEntry->insert()){
						$numTitlesAdded++;
					}
				}
			}
		}

		if ($results['success']){
			$results['message'] .= "<br> Added $numTitlesAdded Titles to the list";
			if ($listExistsInPika){
				$nytList->update(); // set a new update time on the main list when it already exists
			}
		}

		return $results;
	}

	/**
	 * Creates or updates a user defined list from information obtained from the New York Times API
	 *
	 * @param string|null $selectedList machine-readable name of the new york times list
	 * @return array
	 */
	public function createUserListFromNPRBestBooks(string $selectedList = null){
		global $configArray;

		if ($selectedList == null){
			$selectedList = $_REQUEST['listToUpdate'];
		}

		$pikaUsername = $configArray['NYT_API']['pika_username'];
		$pikaPassword = $configArray['NYT_API']['pika_password'];

		if (empty($pikaUsername) || empty($pikaPassword)){
			return [
				'success' => false,
				'message' => 'Pika NY Times user not set',
			];
		}

		$pikaUser = UserAccount::validateAccount($pikaUsername, $pikaPassword);
		if (!$pikaUser || PEAR_Singleton::isError($pikaUser)){
			return [
				'success' => false,
				'message' => 'Invalid Pika NY Times user',
			];
		}

		if (ctype_digit($selectedList) && $selectedList > 2012){

			//Get a list of titles from NPR site
			$nprBestBooks = new ExternalEnrichment\NPRBestBooks();
			$nprBookList  = $nprBestBooks->getList($selectedList);
			if (!empty($nprBookList)){
				$selectedListTitle = $nprBestBooks::NPRListTitlePrefix . " - $selectedList";

				// Look for selected List
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
				$nprList          = new UserList();
				$nprList->user_id = $pikaUser->id;
				$nprList->title   = $selectedListTitle;
				$listExistsInPika = $nprList->find(true);

				//We didn't find the list in Pika, create one
				if (!$listExistsInPika){
					$nprList              = new UserList();
					$nprList->title       = $selectedListTitle;
					$nprList->description = 'List of titles based on ' . $nprBestBooks::NPRListTitlePrefix . ' found at ' .  $nprBestBooks::BASE_URL;
					$nprList->public      = 1;
					$nprList->defaultSort = 'custom';
					$nprList->user_id     = $pikaUser->id;
					$success              = $nprList->insert();
					$nprList->find(true);

					if ($success){
						$listID  = $nprList->id;
						$results = [
							'success' => true,
							'message' => "Created list <a href='/MyAccount/MyList/{$listID}'>{$selectedListTitle}</a>",
						];
					}else{
						return [
							'success' => false,
							'message' => 'Could not create list',
						];
					}

				}else{
					$listID  = $nprList->id;
					$results = [
						'success' => true,
						'message' => "Updated list <a href='/MyAccount/MyList/{$listID}'>{$selectedListTitle}</a>",
					];
					//We already have a list, clear the contents, so we don't have titles from last time
					$nprList->removeAllListEntries();
				}

				// We need to add titles to the list //

//			// Include Search Engine Class
//			require_once ROOT_DIR . '/sys/Search/' . $configArray['Index']['engine'] . '.php';
//			/** @var SearchObject_Solr $searchObject */
//			$searchObject = SearchObjectFactory::initSearchObject();

				$class = $configArray['Index']['engine'];
				$url   = $configArray['Index']['url'];
				/** @var Solr $solr */
				$solr = new $class($url);


				// Include UserListEntry Class
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';

				require_once ROOT_DIR . '/sys/ISBN/ISBN.php';
				$numTitlesAdded = $total = 0;
				foreach ($nprBookList as $titleResult){
					$total++;
					$pikaID = null;
					// go through each list item

					// Try fetching the primary ISBN first
					if (!empty($titleResult->isbn13)){
						$searchDocument = $solr->getRecordByIsbn([$titleResult->isbn13], 'id');
						$pikaID         = $searchDocument['id'] ?? null;
					}

					// If no luck with Primary ISBNs, or no primary ISBNs, try the rest of the ISBNs
					if ($pikaID == null){
						$ISBNs = [];
						if (!empty($titleResult->isbn10)){
							$isbnObj = new ISBN($titleResult->isbn10);
							if ($isbnObj->isValid()){
								$ISBNs[] = $isbnObj->get13();
							}
						}
						if (!empty($titleResult->isbn)){
							$isbnObj = new ISBN($titleResult->isbn);
							if ($isbnObj->isValid()){
								$ISBNs[] = $isbnObj->get13();
							}
						}

						$searchDocument = $solr->getRecordByIsbn(array_unique($ISBNs), 'id');
						$pikaID         = $searchDocument['id'] ?? null;
					}

					if ($pikaID == null){
						$searchDocument = $solr->getRecordByTitleAuthor($titleResult->title, $titleResult->author, 'id');
						$pikaID         = $searchDocument['id'] ?? null;
						if ($pikaID == null && stripos($titleResult->title, ': A novel') !== false){
							$searchDocument = $solr->getRecordByTitleAuthor(str_ireplace(': A Novel', '', $titleResult->title), $titleResult->author, 'id');
							$pikaID         = $searchDocument['id'] ?? null;
						}
					}
					if ($pikaID != null){
						$note = "# $titleResult->id<br>";
						$note .= $titleResult->text;
						foreach ($titleResult->links as $linkArray){
							$note .= "<p><a href='$linkArray->url'>$linkArray->source article : \"$linkArray->text\"</a></p>";
						}

						$userListEntry                         = new UserListEntry();
						$userListEntry->listId                 = $nprList->id;
						$userListEntry->groupedWorkPermanentId = $pikaID;

						$existingEntry = false;
						if ($userListEntry->find(true)){
							$existingEntry = true;
						}

						$userListEntry->weight    = $titleResult->id;
						$userListEntry->notes     = $note;
						$userListEntry->dateAdded = time();
						if ($existingEntry){
							if ($userListEntry->update()){
								$numTitlesAdded++;
							}
						}else{
							if ($userListEntry->insert()){
								$numTitlesAdded++;
							}
						}

						// Add their tags
						foreach ($titleResult->tags as $tag){
							$userTag                         = new UserTag();
							$userTag->userId                 = $pikaUser->id;
							$userTag->groupedWorkPermanentId = $pikaID;
							$userTag->tag                    = $nprBestBooks::NPRListTitlePrefix . ' tag: ' . ucwords($tag);
							if (!$userTag->find()){
								$userTag->dateTagged = time();
								$userTag->insert();
							}
						}
					}

				} // End of foreach ($nprBookList

				if ($results['success']){
					$results['message'] .= "<br> Added $numTitlesAdded Titles to the list of the total : " . $total;
					if ($listExistsInPika){
						$nprList->update(); // set a new update time on the main list when it already exists
					}
				}
			} else {
				$results = [
					'success' => false,
					'message' => 'Failed to find list for that year, or another error fetching list.',
				];
			}

		} else {
			$results = [
				'success' => false,
				'message' => "Invalid year selected."
			];
		}
		return $results;
	}

}
