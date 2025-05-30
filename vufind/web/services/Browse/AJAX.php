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
use \Pika\Logger;

class Browse_AJAX extends AJAXHandler {
	const ITEMS_PER_PAGE = 24;

	/** @var SearchObject_Solr|SearchObject_Base $searchObject */
	private $searchObject;

	protected $methodsThatRespondWithJSONUnstructured = [
		'getAddBrowseCategoryForm',
		'createBrowseCategory',
		'getMoreBrowseResults',
		'getBrowseCategoryInfo',
		'getBrowseSubCategoryInfo',
		'getActiveBrowseCategories',
		'getSubCategories',
	];


	function getAddBrowseCategoryForm(){
		global $interface;

		// Select List Creation using Object Editor functions
		require_once ROOT_DIR . '/sys/Browse/SubBrowseCategories.php';
		$temp                            = SubBrowseCategories::getObjectStructure();
		$temp['subCategoryId']['values'] = [0 => 'Select One'] + $temp['subCategoryId']['values'];
		// add default option that denotes nothing has been selected to the options list
		// (this preserves the keys' numeric values (which is essential as they are the Id values) as well as the array's order)
		// btw addition of arrays is kinda a cool trick.
		$interface->assign('propName', 'addAsSubCategoryOf');
		$interface->assign('property', $temp['subCategoryId']);

		// Display Page
		$searchId = strip_tags($_REQUEST['searchId']);
		if (ctype_digit($searchId)){
			$interface->assign('searchId', $searchId);
			$results = [
				'title'        => 'Add as Browse Category to Home Page',
				'modalBody'    => $interface->fetch('Browse/addBrowseCategoryForm.tpl'),
				'modalButtons' => "<button class='tool btn btn-primary' onclick='$(\"#createBrowseCategory\").submit();'>Create Category</button>",
			];
		}else{
			$results = [
				'title'        => 'Add as Browse Category to Home Page',
				'modalBody'    => '<p class="alert alert-warning">Invalid Search ID</p>',
				'modalButtons' => '',
			];
		}
		return $results;
	}

	function createBrowseCategory(){
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::isLoggedIn()){
			if (UserAccount::userHasRoleFromList(['libraryAdmin', 'libraryManager', 'contentEditor', 'locationManager', 'opacAdmin'])){

				if (UserAccount::userHasRole('locationManager')){
					// Only use home branch for location managers
					$searchLocation = Location::getUserHomeLocation();
				}elseif (UserAccount::userHasRole('opacAdmin')){
					// Use the interface library for opac admins (can be different from the user home library)
					if (Location::getSearchLocation()){
						$searchLocation = Location::getSearchLocation();
					} else {
						global $library;
					}
				}elseif (UserAccount::userHasRoleFromList(['libraryAdmin', 'libraryManager', 'contentEditor'])){
					// Otherwise, use User's home library
					$library = $user->getHomeLibrary();
				}

				$categoryName       = $_REQUEST['categoryName'] ?? '';
				$addAsSubCategoryOf = !empty($_REQUEST['addAsSubCategoryOf']) ? $_REQUEST['addAsSubCategoryOf'] : null;// value of zero means nothing was selected.
				$textId             = str_replace(' ', '_', strtolower(trim($categoryName)));
				$textId             = preg_replace('/[^\w\d_]/', '', $textId);
				if (empty($textId)){
					return [
						'success' => false,
						'message' => 'Please enter a category name',
					];
				}
				if (isset($searchLocation)){
					$textId = $searchLocation->code . '_' . $textId;
				}elseif (isset($library)){
					$textId = $library->subdomain . '_' . $textId;
				}

				//Check to see if we have an existing browse category
				require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
				$browseCategory         = new BrowseCategory();
				$browseCategory->textId = $textId;
				if ($browseCategory->find(true)){
					return [
						'success' => false,
						'message' => "Sorry the title of the category was not unique.  Please enter a new name.",
					];
				}else{
					if (!empty($_REQUEST['searchId'])){
						if (ctype_digit($_REQUEST['searchId'])){
							$searchId = $_REQUEST['searchId'];

							/** @var SearchObject_Solr|SearchObject_Base $searchObject */
							$searchObject = SearchObjectFactory::initSearchObject();
							$searchObject->init();
							$searchObject = $searchObject->restoreSavedSearch($searchId, false, true);

							if (!$browseCategory->updateFromSearch($searchObject)){
								return [
									'success' => false,
									'message' => 'Sorry, this search is too complex to create a category from.',
								];
							}
						} else {
							return [
								'success' => false,
								'message' => 'Invalid Search ID.',
							];
						}
					}elseif (!empty($_REQUEST['listId'])){
						if (ctype_digit($_REQUEST['listId'])){
							$listId                       = $_REQUEST['listId'];
							$browseCategory->sourceListId = $listId;
						} else {
							return [
								'success' => false,
								'message' => "Invalid List ID.",
							];
						}
					} else {
						return [
							'success' => false,
							'message' => "Sorry, no set parameter to create a category from.",
						];
					}

					$categoryName                = htmlspecialchars($categoryName, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
					$browseCategory->label       = $categoryName;
					$browseCategory->userId      = UserAccount::getActiveUserId();
					$browseCategory->description = '';


					//setup and add the category
					if (!$browseCategory->insert()){
						return [
							'success' => false,
							'message' => "There was an error saving the category.  Please contact Marmot.",
						];
					}elseif ($addAsSubCategoryOf){
						$id                            = $browseCategory->id; // get from above insertion operation
						$subCategory                   = new SubBrowseCategories();
						$subCategory->browseCategoryId = $addAsSubCategoryOf;
						$subCategory->subCategoryId    = $id;
						if (!$subCategory->insert()){
							return [
								'success' => false,
								'message' => "There was an error saving the category as a sub-category.  Please contact Marmot.",
							];
						}
					}

					//Now add to the library/location
					if (!$addAsSubCategoryOf){
						if (isset($library)){
							// Only add main browse categories to the library carousel
							require_once ROOT_DIR . '/sys/Browse/LibraryBrowseCategory.php';
							$libraryBrowseCategory                       = new LibraryBrowseCategory();
							$libraryBrowseCategory->libraryId            = $library->libraryId;
							$libraryBrowseCategory->browseCategoryTextId = $textId;
							$libraryBrowseCategory->insert();
						}elseif ($searchLocation){
							require_once ROOT_DIR . '/sys/Browse/LocationBrowseCategory.php';
							$locationBrowseCategory                       = new LocationBrowseCategory();
							$locationBrowseCategory->locationId           = $searchLocation->locationId;
							$locationBrowseCategory->browseCategoryTextId = $textId;
							$locationBrowseCategory->insert();
						}
					}

					return [
						'success' => true,
						'message' => 'This search was added to the homepage successfully.',
						'buttons' => '<a class="btn btn-primary" href="/Admin/BrowseCategories?objectAction=edit&id='
							. $browseCategory->id . '" role="button">Edit Browse Category</a>'
							. '<a class="btn btn-primary" href="/" role="button">View Homepage</a>'
					];
				}
			}else{
				return [
					'success' => false,
					'message' => 'You do not have permission to create a Browse Category',
				];
			}
		}else{
			return [
				'success' => false,
				'message' => 'Not Logged In.',
			];
		}
	}

	/** @var  BrowseCategory $browseCategory */
	private $browseCategory;


	/**
	 * @param bool $reload Reload object's BrowseCategory
	 * @return BrowseCategory
	 */
	private function getBrowseCategory($reload = false){
		if ($this->browseCategory && !$reload){
			return $this->browseCategory;
		}

		require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
		$browseCategory         = new BrowseCategory();
		$browseCategory->textId = $this->textId;
		$result                 = $browseCategory->find(true);
		if ($result){
			$this->browseCategory = $browseCategory;
		}
		return $this->browseCategory;
	}

	private function getSuggestionsBrowseCategoryResults(){
		// Only Fetches one page of results
		$browseMode = $this->setBrowseMode();
		if (!isset($_REQUEST['reload'])){
			/** @var Memcache $memCache */
			global $memCache, $solrScope;
			$activeUserId       = UserAccount::getActiveUserId();
			$key                = 'browse_category_' . $this->textId . '_' . $activeUserId . '_' . $solrScope . '_' . $browseMode;
			$browseCategoryInfo = $memCache->get($key);
			if ($browseCategoryInfo != false){
				return $browseCategoryInfo;
			}
		}

		global $interface;
		$interface->assign('browseCategoryId', $this->textId);
		$result['success']   = true;
		$result['textId']    = $this->textId;
		$result['label']     = translate('Recommended for you');
		$result['searchUrl'] = '/MyAccount/SuggestedTitles';

		require_once ROOT_DIR . '/sys/LocalEnrichment/Suggestions.php';
		$suggestions = Suggestions::getSuggestions(-1, self::ITEMS_PER_PAGE);
		$records     = [];
		foreach ($suggestions as $suggestedItemId => $suggestionData){
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
			if (array_key_exists('recordDriver', $suggestionData['titleInfo'])){
				$groupedWork = $suggestionData['titleInfo']['recordDriver'];
			}else{
				$groupedWork = new GroupedWorkDriver($suggestionData['titleInfo']);
			}
			if ($groupedWork->isValid){
				if (method_exists($groupedWork, 'getBrowseResult')){
					$records[] = $interface->fetch($groupedWork->getBrowseResult());
				}else{
					$records[] = 'Browse Result not available';
				}
			}
		}
		if (count($records) == 0){
			$records[] = $interface->fetch('Browse/noResults.tpl');
		}

		$result['records']    = implode('', $records);
		$result['numRecords'] = count($records);

		global $memCache, $configArray, $solrScope;
		$activeUserId = UserAccount::getActiveUserId();
		$key          = 'browse_category_' . $this->textId . '_' . $activeUserId . '_' . $solrScope . '_' . $browseMode;
		$memCache->add($key, $result, 0, $configArray['Caching']['browse_category_info']);

		return $result;
	}

	private function getBrowseCategoryResults($pageToLoad = 1){
		if ($this->textId == 'system_recommended_for_you'){
			return $this->getSuggestionsBrowseCategoryResults();
		}else{
			$browseMode = $this->setBrowseMode();
			if ($pageToLoad == 1 && !isset($_REQUEST['reload'])){
				// only first page is cached
				/** @var Memcache $memCache */
				global $memCache, $solrScope;
				$key                = 'browse_category_' . $this->textId . '_' . $solrScope . '_' . $browseMode;
				$browseCategoryInfo = $memCache->get($key);
				if ($browseCategoryInfo != false){
					return $browseCategoryInfo;
				}
			}

			$result         = ['success' => false];
			$browseCategory = $this->getBrowseCategory();
			if ($browseCategory){
				global $interface;
				$interface->assign('browseCategoryId', $this->textId);
				$records           = [];
				$result['success'] = true;
				$result['textId']  = $browseCategory->textId;
				$result['label']   = $browseCategory->label;
				//$result['description'] = $browseCategory->description; // the description is not used anywhere on front end. plb 1-2-2015

				// User List Browse Category //
				if ($browseCategory->sourceListId != null && $browseCategory->sourceListId > 0){
					require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
					$sourceList     = new UserList();
					$sourceList->id = $browseCategory->sourceListId;
					if ($sourceList->find(true)){
						$records = $sourceList->getBrowseRecords($pageToLoad);
					}
					$result['searchUrl'] = '/MyAccount/MyList/' . $browseCategory->sourceListId;

					// Search Browse Category //
				}else{
					$this->searchObject = SearchObjectFactory::initSearchObject();
					if ($this->searchObject->pingServer(false)){
						$defaultFilterInfo = $browseCategory->defaultFilter;
						$defaultFilters    = preg_split('/[\r\n,;]+/', $defaultFilterInfo);
						foreach ($defaultFilters as $filter){
							$this->searchObject->addFilter(trim($filter));
						}
						$this->searchObject->setSort($browseCategory->defaultSort);
						if ($browseCategory->searchTerm != ''){
							if ($browseCategory->searchTerm[0] == '(' && $browseCategory->searchTerm[strlen($browseCategory->searchTerm) - 1] == ')'){
								// Some simple Advanced Searches have been saved as browse categories of the form "(SearchType:searchPhrase)"
								// so strip the parentheses, so it can be treated as a basic search term.
								$browseCategory->searchTerm = substr($browseCategory->searchTerm, 1, -1);
							}
							$this->searchObject->setSearchTermForBrowseCategory($browseCategory->searchTerm);
						}                                                       //Get titles for the list
						$this->searchObject->clearFacets();
						$this->searchObject->disableSpelling();
						$this->searchObject->disableLogging();
						$this->searchObject->setLimit(self::ITEMS_PER_PAGE);
						$this->searchObject->setPage($pageToLoad);
						$this->searchObject->processSearch();
						$records = $this->searchObject->getBrowseRecordHTML();// Do we need to initialize the ajax ratings?
						if ($this->browseMode == 'covers'){
							// Rating Settings
							global $library;
							/** @var Library $library */
							$location                  = Location::getActiveLocation();
							$browseCategoryRatingsMode = null;
							if ($location){
								$browseCategoryRatingsMode = $location->browseCategoryRatingsMode;
							} // Try Location Setting
							if (!$browseCategoryRatingsMode){
								$browseCategoryRatingsMode = $library->browseCategoryRatingsMode;
							}  // Try Library Setting

							// when the Ajax rating is turned on, they have to be initialized with each load of the category.
							if ($browseCategoryRatingsMode == 'stars'){
								$records[] = '<script>Pika.Ratings.initializeRaters()</script>';
							}
						}
						$result['searchUrl'] = $this->searchObject->renderSearchUrl();// let front end know if we have reached the end of the result set
						if ($this->searchObject->getPage() * $this->searchObject->getLimit() >= $this->searchObject->getResultTotal()){
							$result['lastPage'] = true;
						}// Shutdown the search object
						$this->searchObject->close();
					}
				}
				$recordCount          = count($records);
				$result['records']    = implode('', $records);
				$result['numRecords'] = $recordCount;
				if ($recordCount == 0){
					$result['records']  = $interface->fetch('Browse/noResults.tpl');
					$result['lastPage'] = true;
				}

				elseif ($pageToLoad == 1){
					// Store first page of browse category in the MemCache (if there were any results (don't cache empty results)
					global $memCache, $configArray, $solrScope;
					$key = 'browse_category_' . $this->textId . '_' . $solrScope . '_' . $browseMode;
					$memCache->add($key, $result, 0, $configArray['Caching']['browse_category_info']);
				}

			}

			return $result;
		}
	}

	public $browseModes = // Valid Browse Modes
		[
			'covers', // default Mode
			'grid',
		],
		$browseMode; // Selected Browse Mode

	function setBrowseMode(){
		// Set Browse Mode //
		if (isset($_REQUEST['browseMode']) && in_array($_REQUEST['browseMode'], $this->browseModes)){ // user is setting mode (will be in most calls)
			$browseMode = $_REQUEST['browseMode'];
		}elseif (!empty($this->browseMode)){ // mode is already set
			$browseMode = $this->browseMode;
		}else{ // check library & location settings
			global $location;
			if (!empty($location->defaultBrowseMode)){ // check location setting
				$browseMode = $location->defaultBrowseMode;
			}else{
				global $library;  /** @var Library $library */
				if (!empty($library->defaultBrowseMode)){ // check location setting
					$browseMode = $library->defaultBrowseMode;
				}else{
					$browseMode = $this->browseModes[0];
				} // default setting
			}
		}

		$this->browseMode = $browseMode;

		global $interface;
		$interface->assign('browseMode', $browseMode); // sets the template switch that is created in GroupedWork object

		return $browseMode;
	}

	private $textId;

	/**
	 * @param null $textId Optional Id to set the object's textId to
	 * @return null         Return the object's textId value
	 */
	function setTextId($textId = null){
		if ($textId){
			$this->textId = $textId;
		}elseif ($this->textId == null){ // set Id only once
			$this->textId = $_REQUEST['textId'] ?? null;
		}
		return $this->textId;
	}

	function getBrowseCategoryInfo($textId = null){
		$textId = $this->setTextId($textId);
		if ($textId == null){
			return ['success' => false];
		}
		$response['textId'] = $textId;

		// Get Any Subcategories for the subcategory menu
		$response['subcategories'] = $this->getSubCategories();

		// If this category has subcategories, get the results of a sub-category instead.
		if (!empty($this->subCategories)){
			// passed URL variable, or first sub-category
			if (!empty($_REQUEST['subCategoryTextId'])){
				//$test              = array_search($_REQUEST['subCategoryTextId'], $this->subCategories);
				$subCategoryTextId = $_REQUEST['subCategoryTextId'];
			}else{
				$subCategoryTextId = $this->subCategories[0]->textId;
			}
			$response['subCategoryTextId'] = $subCategoryTextId;

			// Set the main category label before we fetch the sub-categories main results
			$response['label'] = $this->browseCategory->label;

			// Reset Main Category with SubCategory to fetch main results
			$this->setTextId($subCategoryTextId);
			$this->getBrowseCategory(true); // load sub-category
		}

		// Get the Browse Results for the Selected Category
		$result = $this->getBrowseCategoryResults();

		// Update Stats
		// $this->upBrowseCategoryCounter();

		return array_merge($result, $response);
	}

	/**
	 *  Updates the displayed Browse Category's Shown Stats. Use near the end of
	 *  your actions.
	 */
	private function upBrowseCategoryCounter(){
		if ($this->browseCategory){
			$this->browseCategory->numTimesShown += 1;
//			if ($this->subCategories){ // Avoid unneeded sql update calls of subBrowseCategories
//				unset ($this->browseCategory->subBrowseCategories);
//			}
			$this->browseCategory->update_stats_only();
		}
	}

	function getBrowseSubCategoryInfo(){
		$subCategoryTextId = $_REQUEST['subCategoryTextId'] ?? null;
		if ($subCategoryTextId == null){
			return ['success' => false];
		}

		// Get Main Category Info
		$this->setTextId();
		$this->getBrowseCategory();
		if ($this->browseCategory){
			$result['textId'] = $this->browseCategory->textId;
			$result['label']  = $this->browseCategory->label;
		}

		// Reload with sub-category
		$this->setTextId($subCategoryTextId); // Override to fetch sub-category results
		$this->getBrowseCategory(true); // Fetch Selected Sub-Category
		$subCategoryResult = $this->getBrowseCategoryResults(); // Get the Browse Results for the Selected Sub Category

		if (isset($subCategoryResult['label'])){
			$subCategoryResult['subCategoryLabel'] = $subCategoryResult['label'];
//			unset($subCategoryResult['label']);
		}
		if (isset($subCategoryResult['textId'])){
			$subCategoryResult['subCategoryTextId'] = $subCategoryResult['textId'];
//			unset($subCategoryResult['textId']);
		}

		// Update Stats
//		$this->upBrowseCategoryCounter();

		$result = (isset($result)) ? array_merge($subCategoryResult, $result) : $subCategoryResult;
		return $result;
	}

	function getMoreBrowseResults($textId = null, $pageToLoad = null){
		$textId = $this->setTextId($textId);
		if ($textId == null){
			return ['success' => false];
		}

		// Get More Results requires a defined page to load
		if ($pageToLoad == null){
			$pageToLoad = (int)$_REQUEST['pageToLoad'];
			if (!is_int($pageToLoad)){
				return ['success' => false];
			}
		}
		$result = $this->getBrowseCategoryResults($pageToLoad);
		return $result;
	}

	/** @var  BrowseCategory $subCategories []   Browse category info for each sub-category */
	private $subCategories;

	/**
	 * @return string
	 */
	function getSubCategories(){
		$this->setTextId();
		$this->getBrowseCategory();
		if ($this->browseCategory){
			$subCategories = [];
			/** @var SubBrowseCategories $subCategory */
			foreach ($this->browseCategory->getSubCategories() as $subCategory){

				// Get Needed Info about sub-category
				/** @var BrowseCategory $temp */
				$temp     = new BrowseCategory();
				$temp->id = $subCategory->subCategoryId;
				if ($temp->find(true)){
					$this->subCategories[] = $temp;
					$subCategories[]       = ['label' => $temp->label, 'textId' => $temp->textId];
				}else{

					$this->logger->warning("Did not find subcategory with id {$subCategory->subCategoryId}");
				}
			}
			if ($subCategories){
				global $interface;
				$interface->assign('subCategories', $subCategories);
				$result = $interface->fetch('Search/browse-sub-category-menu.tpl');
				return $result;
			}
		}
	}

	/**
	 * Return a list of browse categories that are assigned to the home page for the current library.
	 *
	 * TODO: Support loading sub categories.
	 */
	function getActiveBrowseCategories(){
		//Figure out which library or location we are looking at
		global $library;
		/** @var Location $locationSingleton */
		global $locationSingleton;
		global $configArray;
		//Check to see if we have an active location, will be null if we don't have a specific locatoin
		//based off of url, branch parameter, or IP address
		$activeLocation = $locationSingleton->getActiveLocation();

		//Get a list of browse categories for that library / location
		/** @var LibraryBrowseCategory[]|LocationBrowseCategory[] $browseCategories */
		if ($activeLocation == null){
			//We don't have an active location, look at the library
			$browseCategories = $library->browseCategories;
		}else{
			//We have a location get data for that
			$browseCategories = $activeLocation->browseCategories;
		}

		require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
		//Format for return to the user, we want to return
		// - the text id of the category
		// - the display label
		// - Clickable link to load the category
		$formattedCategories = [];
		foreach ($browseCategories as $curCategory){
			$categoryInformation         = new BrowseCategory();
			$categoryInformation->textId = $curCategory->browseCategoryTextId;

			if ($categoryInformation->find(true)){
				$formattedCategories[] = [
					'text_id'       => $curCategory->browseCategoryTextId,
					'display_label' => $categoryInformation->label,
					'link'          => $configArray['Site']['url'] . '?browseCategory=' . $curCategory->browseCategoryTextId,
				];
			}
		}
		return $formattedCategories;
	}
}
