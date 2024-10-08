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
 * A Customizable section of the catalog that can be browsed within
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 1/25/14
 * Time: 10:04 AM
 */
require_once ROOT_DIR . '/sys/Browse/SubBrowseCategories.php';

class BrowseCategory extends DB_DataObject{
	public $__table = 'browse_category';
	public $id;
	public $textId;  //A textual id to make it easier to transfer browse categories between systems

	public $userId; //The user who created the browse category

	public $label; //A label for the browse category to be shown in the browse category listing
	public $description; //A description of the browse category

	public $searchTerm;
	public $defaultFilter;
	public $sourceListId;
	public $defaultSort;

	public $numTimesShown;
	public $numTitlesClickedOn;

	public function getSubCategories(){
		if (!isset($this->subBrowseCategories) && $this->id) {
			$this->subBrowseCategories     = [];
			$subCategory                   = new SubBrowseCategories();
			$subCategory->browseCategoryId = $this->id;
			$subCategory->orderBy('weight');
			$subCategory->find();
			while ($subCategory->fetch()) {
				$this->subBrowseCategories[$subCategory->id] = clone($subCategory);
			}
		}
		return $this->subBrowseCategories;
	}

	private $data = [];
	public function __get($name){
		if ($name == 'subBrowseCategories') {
			$this->getSubCategories();
			return $this->subBrowseCategories;
		}else{
			return $this->data[$name];
		}
	}

	public function __set($name, $value){
		if ($name == 'subBrowseCategories') {
			$this->subBrowseCategories = $value;
		}else{
			$this->data[$name] = $value;
		}
	}
	/**
	 * Override the fetch functionality to save related objects
	 *
	 * @see DB/DB_DataObject::fetch()
	 */
//	public function fetch(){
//		$return = parent::fetch();
//		if ($return !== FALSE) {
//			// check for any sub-categories
//			$this->getSubCategories();
//		}
//		return $return;
//	}
	/**
	 * Override the update functionality to save related objects
	 *
	 * @see DB/DB_DataObject::update()
	 */
	public function update($dataObject = false){
		$ret = parent::update();
		if ($ret !== FALSE ){
			$this->saveSubBrowseCategories();
			//delete any cached results for browse category
			$this->deleteCachedBrowseCategoryResults();
		}
		return $ret;
	}

	/**
	 * call this method when updating the browse categories views statistics, so that all the other functionality
	 * in update() is avoided (and isn't needed)
	 *
	 * @return int
	 */
	public function update_stats_only(){
		$ret = parent::update();
		return $ret;
	}

	/**
	 * Override the update functionality to save the related objects
	 *
	 * @see DB/DB_DataObject::insert()
	 */
	public function insert(){
		$ret = parent::insert();
		if ($ret !== FALSE ){
			$this->saveSubBrowseCategories();
		}
		return $ret;
	}

	public function deleteCachedBrowseCategoryResults(){
		// key structure
		// $key = 'browse_category_' . $this->textId . '_' . $solrScope . '_' . $browseMode;

		$libraries         = new Library();
		$librarySubDomains = $libraries->fetchAll('subdomain');
		$locations         = new Location();
		$locationCodes     = $locations->fetchAll('code');
		$solrScopes        = array_merge($librarySubDomains, $locationCodes);

		if (!empty($solrScopes)) { // don't bother if we didn't get any solr scopes
			// Valid Browse Modes (taken from class Browse_AJAX)
			$browseModes = ['covers', 'grid'];

			/* @var MemCache $memCache */
			global $memCache;

			$keyFormat = 'browse_category_' . $this->textId; // delete all stored items with beginning with this key format.
			foreach ($solrScopes as $solrScope) {
				foreach ($browseModes as $browseMode) {
					$key = $keyFormat . '_' . $solrScope . '_' . $browseMode;
					if ($memCache->get($key)) { // check if this key is in fact storing a value
//					$success[$key] =
						$memCache->delete($key);
					}
				}
			}
		}
	}

	public function saveSubBrowseCategories(){
		if (isset ($this->subBrowseCategories) && is_array($this->subBrowseCategories)) {
			/** @var BrowseCategory[] $subBrowseCategories */
			/** @var BrowseCategory   $subCategory */
			foreach ($this->subBrowseCategories as $subCategory) {
				if (isset($subCategory->deleteOnSave) && $subCategory->deleteOnSave == true) {
					$subCategory->delete();
				} else {
					if (isset($subCategory->id) && is_numeric($subCategory->id)) {
						$subCategory->update();
					} else {
						$subCategory->browseCategoryId = $this->id;
						$subCategory->insert();
					}
				}
			}
			unset($this->subBrowseCategories);
		}
	}

	static function getObjectStructure(){
		// Get All User Lists
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		$userLists         = new UserList();
		$userLists->public = 1;
		$userLists->orderBy('title asc');
		$userLists->find();
		$sourceLists     = [];
		$sourceLists[-1] = 'Generate from search term and filters';
		while ($userLists->fetch()){
			$numItems = $userLists->numValidListItems();
			if ($numItems > 0){
				$sourceLists[$userLists->id] = "($userLists->id) $userLists->title - $numItems entries";
			}
		}

		// Get Structure for Sub-categories
		$browseSubCategoryStructure = SubBrowseCategories::getObjectStructure();
		unset($browseSubCategoryStructure['weight']);
		unset($browseSubCategoryStructure['browseCategoryId']);

		/** @var SearchObject_Solr|SearchObject_Base $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$sortOptions = $searchObject->getSortOptions();
		foreach ($sortOptions as $key => &$value){
			$value = translate($value);
		}

		$structure = [
			'id'          => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of this association'],
			'label'       => ['property' => 'label', 'type' => 'text', 'label' => 'Label', 'description' => 'The label to show to the user', 'maxLength' => 50, 'required' => true],
			'textId'      => ['property' => 'textId', 'type' => 'text', 'label' => 'textId', 'description' => 'A textual id to identify the category',
			                    'serverValidation' => 'validateTextId', 'maxLength' => 50, 'required' => true],
			'userId'      => ['property' => 'userId', 'type' => 'label', 'label' => 'userId', 'description' => 'The User Id who created this category', 'default' => UserAccount::getActiveUserId()],
			'description' => ['property' => 'description', 'type' => 'html', 'label' => 'Description', 'description' => 'A description of the category.', 'hideInLists' => true],

			// Define oneToMany interface for choosing and arranging sub-categories
			'subBrowseCategories' => [
				'property'      => 'subBrowseCategories',
				'type'          => 'oneToMany',
				'label'         => 'Browse Sub-Categories',
				'description'   => 'Browse Categories that will be displayed as sub-categories of this Browse Category',
				'keyThis'       => 'id',
				'keyOther'      => 'browseCategoryId',
				'subObjectType' => 'SubBrowseCategories',
				'structure'     => $browseSubCategoryStructure,
				'sortable'      => true,
				'storeDb'       => true,
				'allowEdit'     => false,
				'canEdit'       => true,
			],

			'searchTerm'         => ['property' => 'searchTerm', 'type' => 'text', 'label' => 'Search Term', 'description' => 'A default search term to apply to the category', 'default' => '', 'hideInLists' => true, 'maxLength' => 500],
			'defaultFilter'      => ['property' => 'defaultFilter', 'type' => 'textarea', 'label' => 'Search Filter(s)', 'description' => 'Filters to apply to the search by default.', 'hideInLists' => true, 'rows' => 3, 'cols' => 80],
			'defaultSort'        => ['property' => 'defaultSort', 'type' => 'enum', 'label' => 'Search Sort (does not apply to Source Lists) ', 'values' => $sortOptions, 'description' => 'The sort to apply to the search results', 'default' => 'relevance', 'hideInLists' => true],
			'sourceListId'       => ['property' => 'sourceListId', 'type' => 'enum', 'values' => $sourceLists, 'label' => 'Source List', 'description' => 'A public list to display titles from'],
		];

		return $structure;
	}

	/**
	 * The Object Editor uses this method to check the text Id of browse categories
	 * @return array
	 */
	function validateTextId(){
		//Setup validation return array
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

		if (empty($this->textId)){
			$this->textId = $this->label;
			$location     = Location::getUserHomeLocation();
			if (!empty($location)){
				$this->textId .= '_' . $location->code;
			}else{
				global $library;
				if (!empty($library->getPatronHomeLibrary()->subdomain)){
					$this->textId .= '_' . $library->getPatronHomeLibrary()->subdomain;
				}
			}
		}

		//First convert the text id to all lower case
		$this->textId = strtolower($this->textId);

		//Next convert any non word characters to an underscore
		$this->textId = preg_replace('/\W/', '_', $this->textId);

		//Make sure the length is less than 50 characters
		if (strlen($this->textId) > 50){
			$this->textId = substr($this->textId, 0, 50);
		}

		return $validationResults;
	}

	/**
	 *  Convert the BrowseCategory sort options into equivalent Solr sort values for building a search
	 * @param SearchObject_Solr $searchObject
	 *
	 * @return string
	 */
	public function getSolrSort($searchObject){
		$searchOptions = $searchObject->getSortOptions();
		if (array_key_exists($this->defaultSort, $searchOptions)){
			return $this->defaultSort;
		}
		return 'relevance';
	}

	/**
	 * @param SearchObject_Solr $searchObject
	 *
	 * @return boolean
	 */
	public function updateFromSearch($searchObject) {
		//Search terms
		$searchTerms = $searchObject->getSearchTerms();
		if (is_array($searchTerms)){
			if (count($searchTerms) > 1){
				return false;
			}elseif (!empty($searchTerms[0]['group'])){
				if (count($searchTerms[0]['group']) == 1
					&& in_array($searchTerms[0]['group'][0]['field'], $searchObject->getBasicTypes())
				){
					// Simplest form of an advanced search can be converted to a browse category search
					$this->searchTerm = $searchTerms[0]['group'][0]['field'] . ':' . $searchTerms[0]['group'][0]['lookfor'];
				}else{
					// Advanced search is too complex to convert to browse category
					return false;
				}
			}elseif ($searchTerms[0]['index'] == 'Keyword'){
				$this->searchTerm = $searchTerms[0]['lookfor'];
			}else{
				$this->searchTerm = $searchTerms[0]['index'] . ':' . $searchTerms[0]['lookfor'];
			}
		}else{
			$this->searchTerm = $searchTerms;
		}

		//Default Filter
		$filters          = $searchObject->getFilterList();
		$formattedFilters = '';
		foreach ($filters as $filter){
			if (strlen($formattedFilters) > 0){
				$formattedFilters .= "\r\n";
			}
			$formattedFilters .= $filter[0]['field'] . ':' . $filter[0]['value'];
		}
		$this->defaultFilter = $formattedFilters;

		//Default sort
		$this->defaultSort = $searchObject->getSort();
		return true;
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->textId)){
			return $this->textId;
		}
	}

}
