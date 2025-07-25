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
 * Table Definition for location
 */

use Pika\Cache;
use Pika\Logger;

require_once 'DB/DataObject.php';
require_once ROOT_DIR . '/sys/OneToManyDataObjectOperations.php';
require_once ROOT_DIR . '/sys/Location/LocationHours.php';
require_once ROOT_DIR . '/sys/Location/LocationFacetSetting.php';
require_once ROOT_DIR . '/sys/Location/LocationCombinedResultSection.php';
require_once ROOT_DIR . '/sys/Browse/LocationBrowseCategory.php';
require_once ROOT_DIR . '/sys/Indexing/LocationRecordOwned.php';
require_once ROOT_DIR . '/sys/Indexing/LocationRecordToInclude.php';
require_once ROOT_DIR . '/sys/Hoopla/LocationHooplaSettings.php';

class Location extends DB_DataObject {

	use OneToManyDataObjectOperations;

	const DEFAULT_AUTOLOGOUT_TIME            = 90;
	const DEFAULT_AUTOLOGOUT_TIME_LOGGED_OUT = 450;

	public $__table = 'location';   // table name
	public $locationId;        //int(11)
	public $code;          //varchar(5)
	public $catalogUrl;
	public $displayName;      //varchar(40)
	public $showDisplayNameInHeader;
	public $headerText;
	public $libraryId;        //int(11)
	public $address;
	public $phone;
	public $isMainBranch; // tinyint(1)
	public $showInLocationsAndHoursList;
	public $validHoldPickupBranch;  //tinyint(4)
	public $nearbyLocation1;    //int(11)
	public $nearbyLocation2;    //int(11)
	public $ilsLocationId;
	public $facetLabel;
	public $restrictSearchByLocation;
	/* OVERDRIVE */
	public $enableOverdriveCollection;
	public $includeOverDriveAdult;
	public $includeOverDriveTeen;
	public $includeOverDriveKids;

	public $showHoldButton;
	public $showStandardReviews;
	public $repeatSearchOption;
	public $repeatInOnlineCollection;
	public $repeatInProspector;
	public $repeatInWorldCat;
	public $repeatInOverdrive;
	public $repeatInAlternateOverdriveLibrary;
	public $systemsToRepeatIn;
	public $homeLink;
	public $defaultPType;
	public $boostByLocation;
	public $additionalLocalBoostFactor;
	public $recordsToBlackList;
	public $publicListsToInclude;
	public $automaticTimeoutLength;
	public $automaticTimeoutLengthLoggedOut;
	public $additionalCss;
	public $showTextThis;
	public $showEmailThis;
	public $showShareOnExternalSites;
	public $showFavorites;
	public $showComments;
	public $showQRCode;
	public $showStaffView;
	public $showGoodReadsReviews;
	public $availabilityToggleLabelSuperScope;
	public $availabilityToggleLabelLocal;
	public $availabilityToggleLabelAvailable;
	public $availabilityToggleLabelAvailableOnline;
	public $baseAvailabilityToggleOnLocalHoldingsOnly;
	public $includeOnlineMaterialsInAvailableToggle;
	public $defaultBrowseMode;
	public $browseCategoryRatingsMode;
	public $includeAllLibraryBranchesInFacets;
	public $additionalLocationsToShowAvailabilityFor;
	public $includeAllRecordsInShelvingFacets;
	public $includeAllRecordsInDateAddedFacets;
	public $includeOnOrderRecordsInDateAddedFacetValues;
	public $includeLibraryRecordsToInclude;

	//Combined Results (Bento Box)
	public $enableCombinedResults;
	public $combinedResultsLabel;
	public $defaultToCombinedResults;
	public $useLibraryCombinedResultsSettings;
    
    // Used to track multiple linked users having the same pick-up locations
    public $pickupUsers;
	public $changeRequiresReindexing;

	/** @var  array $data */
	protected $data;

	private $logger;
	private $cache;

//	public $hours;
// Don't explicitly declare this property.  Calls to it trigger its look up when it isn't set

	public function __construct(){
		$this->cache  = new Pika\Cache();
		$this->logger = new Pika\Logger('Location');
	}

	function keys(){
		return ['locationId', 'code'];
	}

	/**
	 * Needed override for OneToManyDataObjectOperations
	 * @return string
	 */
	public function getKeyOther(){
		return 'locationId';
	}

	public function getObjectStructure(){
		//Load Libraries for lookup values
		$library = new Library();
		$library->orderBy('displayName');
		if (!UserAccount::userHasRole('opacAdmin') && UserAccount::userHasRoleFromList(['libraryAdmin', 'libraryManager', 'locationManager'])){
			$homeLibrary        = UserAccount::getUserHomeLibrary();
			$library->libraryId = $homeLibrary->libraryId;
		}
		$library->find();
		$libraryList = UserAccount::userHasRole('opacAdmin') ? ['' => 'Choose a Library'] : [];
		while ($library->fetch()){
			$libraryList[$library->libraryId] = $library->displayName;
		}

		//Look lookup information for display in the user interface
		$locationLookupList = self::getLocationLookupList();

		// get the structure for the location's hours
		$hoursStructure = LocationHours::getObjectStructure();

		global $configArray;
		$innReachEncoreName = $configArray['InterLibraryLoan']['innReachEncoreName'];

		// we don't want to make the locationId property editable
		// because it is associated with this location only
		unset($hoursStructure['locationId']);

		$facetSettingStructure = LocationFacetSetting::getObjectStructure();
		unset($facetSettingStructure['weight']);
		unset($facetSettingStructure['locationId']);
		unset($facetSettingStructure['numEntriesToShowByDefault']);
		unset($facetSettingStructure['showAsDropDown']);
		//unset($facetSettingStructure['sortMode']);

		$locationBrowseCategoryStructure = LocationBrowseCategory::getObjectStructure();
		unset($locationBrowseCategoryStructure['weight']);
		unset($locationBrowseCategoryStructure['locationId']);

		$locationMoreDetailsStructure = LocationMoreDetails::getObjectStructure();
		unset($locationMoreDetailsStructure['weight']);
		unset($locationMoreDetailsStructure['locationId']);

		$locationRecordOwnedStructure = LocationRecordOwned::getObjectStructure();
		unset($locationRecordOwnedStructure['locationId']);

		$locationRecordToIncludeStructure = LocationRecordToInclude::getObjectStructure();
		unset($locationRecordToIncludeStructure['locationId']);
		unset($locationRecordToIncludeStructure['weight']);

		$combinedResultsStructure = LocationCombinedResultSection::getObjectStructure();
		unset($combinedResultsStructure['locationId']);
		unset($combinedResultsStructure['weight']);

		$hooplaSettingsStructure = LocationHooplaSettings::getObjectStructure();
		unset($hooplaSettingsStructure['locationId']);

		$structure = [
			'locationId'                      => ['property' => 'locationId', 'type' => 'label', 'label' => 'Location Id', 'description' => 'The unique id of the location within the database'],
			'code'                            => ['property' => 'code', 'type' => 'text', 'label' => 'Code', /*(Search scope name, and often the hold pick-up branch key in the ILS)'*/ 'description' => 'The code to use when communicating with the ILS', 'required' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
			'catalogUrl'                      => ['property' => 'catalogUrl', 'type' => 'label', 'label' => 'Catalog URL', 'description' => 'The catalog url used for this location'],
			'displayName'                     => ['property' => 'displayName', 'type' => 'text', 'label' => 'Display Name', 'description' => 'The full name of the location for display to the user', 'size' => '40'],
			'showDisplayNameInHeader'         => ['property' => 'showDisplayNameInHeader', 'type' => 'checkbox', 'label' => 'Show Display Name in Header', 'description' => 'Whether or not the display name should be shown in the header next to the logo', 'hideInLists' => true, 'default' => false],
			'libraryId'                       => ['property' => 'libraryId', 'type' => 'enum', 'label' => 'Library', 'values' => $libraryList, 'description' => 'A link to the library which the location belongs to', 'required' => true,],
			'isMainBranch'                    => ['property' => 'isMainBranch', 'type' => 'checkbox', 'label' => 'Is Main Branch', 'description' => 'Is this location the main branch for it\'s library', 'default' => false],
			'changeRequiresReindexing'        => ['property' => 'changeRequiresReindexing', 'type' => 'dateReadOnly', 'label' => 'Change Requires Reindexing', 'description' => 'Date Time for when this location changed settings needing re-indexing'],
			'showInLocationsAndHoursList'     => ['property' => 'showInLocationsAndHoursList', 'type' => 'checkbox', 'label' => 'Show In Locations And Hours List', 'description' => 'Whether or not this location should be shown in the list of library hours and locations', 'hideInLists' => true, 'default' => true],
			'address'                         => ['property' => 'address', 'type' => 'textarea', 'label' => 'Address', 'description' => 'The address of the branch.', 'hideInLists' => true],
			'phone'                           => ['property' => 'phone', 'type' => 'text', 'label' => 'Phone Number', 'description' => 'The main phone number for the site .', 'size' => '40', 'hideInLists' => true],
			'nearbyLocation1'                 => ['property' => 'nearbyLocation1', 'type' => 'enum', 'values' => $locationLookupList, 'label' => 'Nearby Location 1', 'description' => 'A secondary location which is nearby and could be used for pickup of materials.', 'hideInLists' => true],
			'nearbyLocation2'                 => ['property' => 'nearbyLocation2', 'type' => 'enum', 'values' => $locationLookupList, 'label' => 'Nearby Location 2', 'description' => 'A tertiary location which is nearby and could be used for pickup of materials.', 'hideInLists' => true],
			'automaticTimeoutLength'          => ['property' => 'automaticTimeoutLength', 'type' => 'integer', 'label' => 'Automatic Timeout Length (logged in)', 'description' => 'The length of time before the user is automatically logged out in seconds.', 'size' => '8', 'hideInLists' => true, 'default' => self::DEFAULT_AUTOLOGOUT_TIME],
			'automaticTimeoutLengthLoggedOut' => ['property' => 'automaticTimeoutLengthLoggedOut', 'type' => 'integer', 'label' => 'Automatic Timeout Length (logged out)', 'description' => 'The length of time before the catalog resets to the home page set to 0 to disable.', 'size' => '8', 'hideInLists' => true, 'default' => self::DEFAULT_AUTOLOGOUT_TIME_LOGGED_OUT],

			'displaySection' => [
				'property' => 'displaySection', 'type' => 'section', 'label' => 'Basic Display', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'properties' => [
					['property' => 'homeLink', 'type' => 'text', 'label' => 'Home Link', 'description' => 'The location to send the user when they click on the home button or logo.  Use default or blank to go back to the vufind home location.', 'hideInLists' => true, 'size' => '40'],
					['property' => 'additionalCss', 'type' => 'textarea', 'label' => 'Additional CSS', 'description' => 'Extra CSS to apply to the site.  Will apply to all pages.', 'hideInLists' => true],
					['property' => 'headerText', 'type' => 'html', 'label' => 'Header Text', 'description' => 'Optional Text to display in the header, between the logo and the log in/out buttons.  Will apply to all pages.', 'allowableTags' => '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6><img>', 'hideInLists' => true],
				],
			],

			'ilsSection' => [
				'property' => 'ilsSection', 'type' => 'section', 'label' => 'ILS/Account Integration', 'hideInLists' => true,
				'helpLink' => 'https://marmot-support.atlassian.net/l/c/SaLWEWH7',
				'properties' => [
					['property' => 'ilsLocationId', 'type' => 'text', 'label' => 'ILS Location Id  (Polaris Organization ID or Sierra Scope)', 'description' => 'The ID for the location in the ILS. Previously, the scope for the branch used in the Sierra Classic OPAC.'],
					['property' => 'defaultPType', 'type' => 'text', 'label' => 'Default P-Type', 'description' => 'The P-Type to use when accessing a subdomain if the patron is not logged in.  Use -1 to use the library default PType.', 'default' => -1],
					['property' => 'validHoldPickupBranch', 'type' => 'enum', 'values' => ['1' => 'Valid for all patrons', '0' => 'Valid for patrons of this branch only', '2' => 'Not Valid'], 'label' => 'Valid Hold Pickup Branch?', 'description' => 'Determines if the location can be used as a pickup location if it is not the patrons home location or the location they are in.', 'hideInLists' => true, 'default' => 1],
					['property' => 'showHoldButton', 'type' => 'checkbox', 'label' => 'Show Hold Button', 'description' => 'Whether or not the hold button is displayed so patrons can place holds on items', 'hideInLists' => true, 'default' => true],
				],
			],

			'searchingSection'  => [
				'property'   => 'searchingSection', 'type' => 'section', 'label' => 'Searching', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'properties' => [
					['property' => 'restrictSearchByLocation', 'type' => 'checkbox', 'label' => 'Restrict Search By Location', 'description' => 'Whether or not search results should only include titles from this location', 'hideInLists' => true, 'default' => false],
					['property' => 'publicListsToInclude', 'type' => 'enum', 'values' => [0 => 'No Lists', '1' => 'Lists from this library', '4' => 'Lists from library list publishers Only', '2' => 'Lists from this location', '5' => 'Lists from list publishers at this location Only', '6' => 'Lists from all list publishers', '3' => 'All Lists'], 'label' => 'Public Lists To Include', 'description' => 'Which lists should be included in this scope', 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					['property' => 'boostByLocation', 'type' => 'checkbox', 'label' => 'Boost By Location', 'description' => 'Whether or not boosting of titles owned by this location should be applied', 'hideInLists' => true, 'default' => true],
					['property' => 'additionalLocalBoostFactor', 'type' => 'integer', 'label' => 'Additional Local Boost Factor', 'description' => 'An additional numeric boost to apply to any locally owned and locally available titles', 'hideInLists' => true, 'default' => 1],
					['property'   => 'searchBoxSection', 'type' => 'section', 'label' => 'Search Box', 'hideInLists' => true,
					 'properties' => [
						 ['property' => 'systemsToRepeatIn', 'type' => 'text', 'label' => 'Other Libraries or Locations To Repeat In', 'description' => 'A list of library codes that you would like to repeat search in separated by pipes |.', 'hideInLists' => true],
						 ['property' => 'repeatSearchOption', 'type' => 'enum', 'values' => ['none' => 'None', 'librarySystem' => 'Library System', 'marmot' => 'Entire Consortium'], 'label' => 'Repeat Search Options (requires Restrict Search By Location to be ON)', 'description' => 'Where to allow repeating search. Valid options are: none, librarySystem, marmot, all', 'default' => 'marmot'],
						 ['property' => 'repeatInOnlineCollection', 'type' => 'checkbox', 'label' => 'Repeat In Online Collection', 'description' => 'Turn on to allow repeat search in the Online Collection.', 'hideInLists' => true, 'default' => false],
						 ['property' => 'repeatInProspector', 'type' => 'checkbox', 'label' => 'Repeat In ' . $innReachEncoreName, 'description' => 'Turn on to allow repeat search in ' . $innReachEncoreName . ' functionality.', 'hideInLists' => true, 'default' => false],
						 ['property' => 'repeatInWorldCat', 'type' => 'checkbox', 'label' => 'Repeat In WorldCat', 'description' => 'Turn on to allow repeat search in WorldCat functionality.', 'hideInLists' => true, 'default' => false],
					 ],
					],
					[
						'property'   => 'searchFacetsSection', 'type' => 'section', 'label' => 'Search Facets', 'hideInLists' => true,
						'properties' => [
							['property' => 'availabilityToggleLabelSuperScope', 'type' => 'text', 'label' => 'SuperScope Toggle Label', 'description' => 'The label to show when viewing super scope i.e. Consortium Name / Entire Collection / Everything.  Does not show if superscope is not enabled.', 'default' => 'Entire Collection'],
							['property' => 'availabilityToggleLabelLocal', 'type' => 'text', 'label' => 'Local Collection Toggle Label', 'description' => 'The label to show when viewing the local collection i.e. Library Name / Local Collection.  Leave blank to hide the button.', 'default' => '{display name}'],
							['property' => 'availabilityToggleLabelAvailable', 'type' => 'text', 'label' => 'Available Toggle Label', 'description' => 'The label to show when viewing available items i.e. Available Now / Available Locally / Available Here.', 'default' => 'Available Now'],
							['property' => 'availabilityToggleLabelAvailableOnline', 'type' => 'text', 'label' => 'Available Online Toggle Label', 'description' => 'The label to show when viewing available items i.e. Available Online.', 'default' => 'Available Online'],
							['property' => 'baseAvailabilityToggleOnLocalHoldingsOnly', 'type' => 'checkbox', 'label' => 'Base Availability Toggle on Local Holdings Only', 'description' => 'Turn on to use local materials only in availability toggle.', 'hideInLists' => true, 'default' => false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							['property' => 'includeOnlineMaterialsInAvailableToggle', 'type' => 'checkbox', 'label' => 'Include Online Materials in Available Toggle', 'description' => 'Turn on to include online materials in both the Available Now and Available Online Toggles.', 'hideInLists' => true, 'default' => false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							['property' => 'facetLabel', 'type' => 'text', 'label' => 'Facet Label', 'description' => 'The label of the facet that identifies this location.', 'hideInLists' => true, 'size' => '40', 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							['property' => 'includeAllLibraryBranchesInFacets', 'type' => 'checkbox', 'label' => 'Include All Library Branches In Facets', 'description' => 'Turn on to include all branches of the library within facets (ownership and availability).', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							['property' => 'additionalLocationsToShowAvailabilityFor', 'type' => 'text', 'label' => 'Additional Locations to Include in Available At Facet', 'description' => 'A list of library codes that you would like included in the available at facet separated by pipes |.', 'size' => '20', 'hideInLists' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							['property' => 'includeAllRecordsInShelvingFacets', 'type' => 'checkbox', 'label' => 'Include All Records In Shelving Facets', 'description' => 'Turn on to include all records (owned and included) in shelving related facets (detailed location, collection).', 'hideInLists' => true, 'default' => false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							['property' => 'includeAllRecordsInDateAddedFacets', 'type' => 'checkbox', 'label' => 'Include All Records In Date Added Facets', 'description' => 'Turn on to include all records (owned and included) in date added facets.', 'hideInLists' => true, 'default' => false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							['property' => 'includeOnOrderRecordsInDateAddedFacetValues', 'type' => 'checkbox', 'label' => 'Include On Order Records In All Date Added Facet Values', 'description' => 'Use On Order records (date added value (tomorrow)) in calculations for all date added facet values. (eg. Added in the last day, week, etc.)', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
							'facets' => [
								'property'                   => 'facets',
								'type'                       => 'oneToMany',
								'label'                      => 'Facets',
								'description'                => 'A list of facets to display in search results',
								'keyThis'                    => 'locationId',
								'keyOther'                   => 'locationId',
								'subObjectType'              => 'LocationFacetSetting',
								'structure'                  => $facetSettingStructure,
								'sortable'                   => true,
								'storeDb'                    => true,
								'allowEdit'                  => true,
								'canEdit'                    => true,
								'additionalOneToManyActions' => [
									[
										'text'    => 'Copy Facets Settings from Location',
										'onclick' => 'Pika.Admin.copyFacetsSettings($id)',
									],
									[
										'text'    => 'Reset Facets to Default',
										'onclick' => 'Pika.Admin.resetFacetsToDefault($id)',
									],
								],
							],

						],
					],
					'combinedResultsSection' => [
						'property'   => 'combinedResultsSection', 'type' => 'section', 'label' => 'Combined Results', 'hideInLists' => true,
						'helpLink'   => 'https://marmot-support.atlassian.net/l/c/tq17UkKT',
						'properties' => [
							'useLibraryCombinedResultsSettings' => ['property' => 'useLibraryCombinedResultsSettings', 'type' => 'checkbox', 'label' => 'Use Library Settings', 'description' => 'Whether or not settings from the library should be used rather than settings from here', 'hideInLists' => true, 'default' => true],
							'enableCombinedResults'             => ['property' => 'enableCombinedResults', 'type' => 'checkbox', 'label' => 'Enable Combined Results', 'description' => 'Whether or not combined results should be shown ', 'hideInLists' => true, 'default' => false],
							'combinedResultsLabel'              => ['property' => 'combinedResultsLabel', 'type' => 'text', 'label' => 'Combined Results Label', 'description' => 'The label to use in the search source box when combined results is active.', 'size' => '20', 'hideInLists' => true, 'default' => 'Combined Results'],
							'defaultToCombinedResults'          => ['property' => 'defaultToCombinedResults', 'type' => 'checkbox', 'label' => 'Default To Combined Results', 'description' => 'Whether or not combined results should be the default search source when active ', 'hideInLists' => true, 'default' => true],
							'combinedResultSections'            => [
								'property'                   => 'combinedResultSections',
								'type'                       => 'oneToMany',
								'label'                      => 'Combined Results Sections',
								'description'                => 'Which sections should be shown in the combined results search display',
								'helpLink'                   => '',
								'keyThis'                    => 'locationId',
								'keyOther'                   => 'locationId',
								'subObjectType'              => 'LocationCombinedResultSection',
								'structure'                  => $combinedResultsStructure,
								'sortable'                   => true,
								'storeDb'                    => true,
								'allowEdit'                  => true,
								'canEdit'                    => false,
								'additionalOneToManyActions' => [],
							],
						],
					],
				],
			],

			// Catalog Enrichment //
			'enrichmentSection' => [
				'property' => 'enrichmentSection', 'type' => 'section', 'label' => 'Catalog Enrichment', 'hideInLists' => true,
				'helpLink' => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk/l/c/5b3zzY8E',
				'properties' => [
					['property' => 'showStandardReviews', 'type' => 'checkbox', 'label' => 'Show Standard Reviews', 'description' => 'Whether or not reviews from Content Cafe/Syndetics are displayed on the full record page.', 'hideInLists' => true, 'default' => true],
					['property' => 'showGoodReadsReviews', 'type' => 'checkbox', 'label' => 'Show GoodReads Reviews', 'description' => 'Whether or not reviews from GoodReads are displayed on the full record page.', 'hideInLists' => true, 'default' => true],
					'showFavorites' => ['property' => 'showFavorites', 'type' => 'checkbox', 'label' => 'Enable User Lists', 'description' => 'Whether or not users can maintain favorites lists', 'hideInLists' => true, 'default' => 1],
					//TODO database column rename?
					'showComments'  => ['property' => 'showComments', 'type' => 'checkbox', 'label' => 'Enable User Reviews', 'description' => 'Whether or not user reviews are shown (also disables adding user reviews)', 'hideInLists' => true, 'default' => 1],
				],
			],

			// Full Record Display //
			'fullRecordSection' => [
				'property' => 'fullRecordSection', 'type' => 'section', 'label' => 'Full Record Display', 'hideInLists' => true,
				'helpLink' => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'properties' => [
//	disabled					'showTextThis'  => ['property' =>'showTextThis', 'type' =>'checkbox', 'label' =>'Show Text This', 'description' =>'Whether or not the Text This link is shown', 'hideInLists' => true, 'default' => 1],
					'showEmailThis'            => ['property' => 'showEmailThis', 'type' => 'checkbox', 'label' => 'Show Email This', 'description' => 'Whether or not the Email This link is shown', 'hideInLists' => true, 'default' => 1],
					'showShareOnExternalSites' => ['property' => 'showShareOnExternalSites', 'type' => 'checkbox', 'label' => 'Show Sharing To External Sites', 'description' => 'Whether or not sharing on external sites (Twitter, Facebook, Pinterest, etc. is shown)', 'hideInLists' => true, 'default' => 1],
					'showStaffView'            => ['property' => 'showStaffView', 'type' => 'checkbox', 'label' => 'Show Staff View', 'description' => 'Whether or not the staff view is displayed in full record view.', 'hideInLists' => true, 'default' => true],
					'showQRCode'               => ['property' => 'showQRCode', 'type' => 'checkbox', 'label' => 'Show QR Code', 'description' => 'Whether or not the catalog should show a QR Code in full record view', 'hideInLists' => true, 'default' => 1],
					'moreDetailsOptions'       => [
						'property'      => 'moreDetailsOptions',
						'type'          => 'oneToMany',
						'label'         => 'Full Record Options',
						'description'   => 'Record Options for the display of full record',
						'keyThis'       => 'locationId',
						'keyOther'      => 'locationId',
						'subObjectType' => 'LocationMoreDetails',
						'structure'     => $locationMoreDetailsStructure,
						'sortable'      => true,
						'storeDb'       => true,
						'allowEdit'     => true,
						'canEdit'       => true,
						'additionalOneToManyActions' => [
							[
								'text'    => 'Copy Full Record Display from Location',
								'onclick' => 'Pika.Admin.copyFullRecordDisplay($id)',
							],
							[
								'text'    => 'Reset Full Record Display to Default',
								'onclick' => 'Pika.Admin.resetMoreDetailsToDefault($id)',
							],
						],
					],
				],
			],

			// Browse Category Section //
			'browseCategorySection' => [
				'property'   => 'browseCategorySection', 'type' => 'section', 'label' => 'Browse Categories', 'hideInLists' => true,
				'instructions' => 'For more information on how to set up browse categories, see the <a href="https://marmot-support.atlassian.net/l/c/98rtRQZ2">online documentation</a>.',
				'helpLink' => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'properties' => [
					'defaultBrowseMode'         => [
						'property' => 'defaultBrowseMode', 'type' => 'enum', 'label' => 'Default Viewing Mode for Browse Categories', 'description' => 'Sets how browse categories will be displayed when users haven\'t chosen themselves.', 'hideInLists' => true,
						'values'   => [
							''       => null, // empty value option is needed so that if no option is specifically chosen for location, the library setting will be used instead.
							'covers' => 'Show Covers Only',
							'grid'   => 'Show as Grid',
						],
					],
					'browseCategoryRatingsMode' => [
						'property' => 'browseCategoryRatingsMode', 'type' => 'enum', 'label' => 'Ratings Mode for Browse Categories ("covers" browse mode only)', 'description' => 'Sets how ratings will be displayed and how user ratings will be enabled when a user is viewing a browse category in the "covers" browse mode. (This only applies when User Ratings have been enabled.)',
						'values'   => [
							''      => null, // empty value option is needed so that if no option is specifically chosen for location, the library setting will be used instead.
							'popup' => 'Show rating stars and enable user rating via pop-up form.',
							'stars' => 'Show rating stars and enable user ratings by clicking the stars.',
							'none'  => 'Do not show rating stars.',
						],
					],

					'browseCategories' => [
						'property'      => 'browseCategories',
						'type'          => 'oneToMany',
						'label'         => 'Browse Categories',
						'description'   => 'Browse Categories To Show on the Home Screen',
						'keyThis'       => 'locationId',
						'keyOther'      => 'locationId',
						'subObjectType' => 'LocationBrowseCategory',
						'structure'     => $locationBrowseCategoryStructure,
						'sortable'      => true,
						'storeDb'       => true,
						'allowEdit'     => false,
						'canEdit'       => true,
						'directLink'    => true,
						'additionalOneToManyActions' => [
							[
								'text'    => 'Copy Browse Categories from Location',
								'onclick' => 'Pika.Admin.copyBrowseCategories($id)',
							],
						],
					],
				],
			],

			/* OVERDRIVE SECTION */
			'overdriveSection'  => [
				'property' => 'overdriveSection', 'type' => 'section', 'label' => 'OverDrive', 'hideInLists' => true,
				'helpLink' => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'properties' => [
					'enableOverdriveCollection'         => ['property' => 'enableOverdriveCollection', 'type' => 'checkbox', 'label' => 'Enable Overdrive Collection', 'description' => 'Whether or not titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'includeOverDriveAdult'             => ['property' => 'includeOverDriveAdult', 'type' => 'checkbox', 'label' => 'Include Adult Titles', 'description' => 'Whether or not adult titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'includeOverDriveTeen'              => ['property' => 'includeOverDriveTeen', 'type' => 'checkbox', 'label' => 'Include Teen Titles', 'description' => 'Whether or not teen titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'includeOverDriveKids'              => ['property' => 'includeOverDriveKids', 'type' => 'checkbox', 'label' => 'Include Kids Titles', 'description' => 'Whether or not kids titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'repeatInOverdrive'                 => ['property' => 'repeatInOverdrive', 'type' => 'checkbox', 'label' => 'Repeat In Overdrive', 'description' => 'Turn on to allow repeat search in Overdrive functionality.', 'hideInLists' => true, 'default' => false],
					'repeatInAlternateOverdriveLibrary' => ['property' => 'repeatInAlternateOverdriveLibrary', 'type' => 'text', 'label' => 'Repeat In Alternate Overdrive Libraries', 'description' => 'A list of the alternate OverDrive library codes from the OverDrive URL that you would like to repeat search in separated by pipes |.', 'hideInLists' => true],
				],
			],

			/* HOOPLA SECTION */
			'hooplaSection' => [
				'property'   => 'hooplaSection', 'type' => 'section', 'label' => 'Hoopla', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'properties' => [
					'hooplaSettings' => [
						'property'                   => 'hooplaSettings',
						'type'                       => 'oneToMany',
						'label'                      => 'Hoopla Settings',
						'description'                => 'Configure which Hoopla tiles are in search results',
						'keyThis'                    => 'locationId',
						'keyOther'                   => 'locationId',
						'subObjectType'              => 'LocationHooplaSettings',
						'structure'                  => $hooplaSettingsStructure,
						'sortable'                   => false,
						'storeDb'                    => true,
						'allowEdit'                  => true,
						'canEdit'                    => false,
						'isIndexingSetting'          => true,
						'changeRequiresReindexing'   => true,
						'additionalOneToManyActions' => [
							[
								'text'    => 'Copy Hoopla Settings From Parent Library',
								'onclick' => 'Pika.Admin.copyLibraryHooplaSettings($id)',
							],
							[
								'text'    => 'Copy Hoopla Settings From Location',
								'onclick' => 'Pika.Admin.copyLocationHooplaSettings($id)',
							],
							[
								'text'    => 'Clear Hoopla Settings',
								'onclick' => 'Pika.Admin.clearLocationHooplaSettings($id)',
								'class'   => 'btn-warning',
							],
						],
					],
				],
			],

			[
				'property'                   => 'hours',
				'type'                       => 'oneToMany',
				'keyThis'                    => 'locationId',
				'keyOther'                   => 'locationId',
				'subObjectType'              => 'LocationHours',
				'structure'                  => $hoursStructure,
				'label'                      => 'Hours',
				'description'                => 'Library Hours',
				'helpLink'                   => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'sortable'                   => false,
				'storeDb'                    => true,
				'additionalOneToManyActions' => [
					[
						'text'    => 'Copy Hours from Location',
						'onclick' => 'Pika.Admin.copyLocationHours($id)',
					],
				],
			],

			'recordsOwned' => [
				'property'                 => 'recordsOwned',
				'type'                     => 'oneToMany',
				'label'                    => 'Records Owned',
				'description'              => 'Information about what records are owned by the location',
				'helpLink'                 => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'keyThis'                  => 'locationId',
				'keyOther'                 => 'locationId',
				'subObjectType'            => 'LocationRecordOwned',
				'structure'                => $locationRecordOwnedStructure,
				'sortable'                 => true,
				'storeDb'                  => true,
				'allowEdit'                => false,
				'canEdit'                  => false,
				'isIndexingSetting'        => true,
				'changeRequiresReindexing' => true
			],

			'recordsToInclude'               => [
				'property'                   => 'recordsToInclude',
				'type'                       => 'oneToMany',
				'label'                      => 'Records To Include',
				'description'                => 'Information about what records to include in this scope',
				'helpLink'                   => 'https://marmot-support.atlassian.net/l/c/EXBe0oAk',
				'keyThis'                    => 'locationId',
				'keyOther'                   => 'locationId',
				'subObjectType'              => 'LocationRecordToInclude',
				'structure'                  => $locationRecordToIncludeStructure,
				'sortable'                   => true,
				'storeDb'                    => true,
				'allowEdit'                  => false,
				'canEdit'                    => false,
				'isIndexingSetting'          => true,
				'changeRequiresReindexing'   => true,
				'additionalOneToManyActions' => [
					[
						'text'    => 'Copy Included Records from Location',
						'onclick' => 'Pika.Admin.copyLocationIncludedRecords($id)',
					],
				],
			],
			'includeLibraryRecordsToInclude' => ['property' => 'includeLibraryRecordsToInclude', 'type' => 'checkbox', 'label' => 'Include Library Records To Include', 'description' => 'Whether or not the records to include from the parent library should be included for this location', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
		];

		if (UserAccount::userHasRoleFromList(['locationManager', 'libraryManager']) && !UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			// restrict permissions for location and library managers, unless they also have higher permissions of library or opac admin
			unset($structure['code']);
			$structure['displayName']['type'] = 'label';
			unset($structure['showDisplayNameInHeader']);
			unset($structure['displaySection']);
			unset($structure['ilsSection']);
			unset($structure['enrichmentSection']);
			unset($structure['fullRecordSection']);
			unset($structure['searchingSection']);
			unset($structure['overdriveSection']);
			unset($structure['facets']);
			unset($structure['recordsOwned']);
			unset($structure['recordsToInclude']);
			unset($structure['hooplaSection']);
			unset($structure['includeLibraryRecordsToInclude']);

			// Further Restrict location Manager settings
			if (UserAccount::userHasRole('locationManager')){
				unset($structure['nearbyLocation1']);
				unset($structure['nearbyLocation2']);
				unset($structure['showInLocationsAndHoursList']);
				unset($structure['address']);
				unset($structure['phone']);
				unset($structure['automaticTimeoutLength']);
				unset($structure['automaticTimeoutLengthLoggedOut']);
			}

		}

		if (!UserAccount::userHasRole('opacAdmin') && !UserAccount::userHasRole('libraryAdmin')){
			unset($structure['isMainBranch']);
		}
		return $structure;
	}
	

	/**
	 * @param User $patron
	 * @param int  $selectedBranchId
	 * @param bool $isLinkedUser
	 * @return Location[]
	 */
	function getPickupBranches($patron, $selectedBranchId = null, $isLinkedUser = false){
		// Note: Some calls to this function will set $patron to false. (No Patron is logged in)
		// For Example: MaterialsRequest_NewRequest
		$homeLibraryInList      = false;
		$alternateLibraryInList = false;

		//Get the library for the patron's home branch.
		if ($patron){
			$homeLibrary = $patron->getHomeLibrary();
		}

		if (isset($homeLibrary) && $homeLibrary->inSystemPickupsOnly == 1){
			/** The user can only pickup within their home system */
			if (!empty($homeLibrary->validPickupSystems)){
				/** The system has additional related systems that you can pick up within */
				$pickupIds          = [];
				$pickupIds[]        = $homeLibrary->libraryId;
				$validPickupSystems = explode('|', $homeLibrary->validPickupSystems);
				foreach ($validPickupSystems as $pickupSystem){
					$pickupLocation            = new Library();
					$pickupLocation->subdomain = $pickupSystem;
					$pickupLocation->find();
					if ($pickupLocation->N == 1){
						$pickupLocation->fetch();
						$pickupIds[] = $pickupLocation->libraryId;
					}
				}
				$this->whereAdd("libraryId IN (" . implode(',', $pickupIds) . ")", 'AND');
				//Deal with Steamboat Springs Juvenile which is a special case.
				$this->whereAdd("code <> 'ssjuv'", 'AND');
			}else{
				/** Only this system is valid */
				$this->whereAdd("libraryId = {$homeLibrary->libraryId}", 'AND');
				$this->whereAdd("validHoldPickupBranch = 1", 'AND');
				//$this->whereAdd("locationId = {$patronProfile['homeLocationId']}", 'OR');
			}
		}else{
			$this->whereAdd("validHoldPickupBranch = 1");
		}

		$this->orderBy('displayName');

		$this->find();


		// Add the user id to each pickup location to track multiple linked accounts having the same pick-up location.
		if ($patron){
			$this->pickupUsers[] = $patron->id;
		}

		//Load the locations and sort them based on the user profile information as well as their physical location.
		$physicalLocation = $this->getPhysicalLocation();
		$locationList     = [];
		while ($this->fetch()){
			if (($this->validHoldPickupBranch == 1) || ($this->validHoldPickupBranch == 0 && !empty($patron) && $patron->homeLocationId == $this->locationId)){
				// Value 0 is valid for patrons of that branch only
				$this->selected = !empty($selectedBranchId) && $this->locationId == $selectedBranchId ? 'selected' : '';
				// Each location is prepended with a number to keep precedence for given locations when sorted by ksort below
				if (isset($physicalLocation) && $physicalLocation->locationId == $this->locationId){
					//If the user is in a branch, those holdings come first.
					$locationList['1' . $this->displayName] = clone $this;
				}elseif (!empty($patron) && $this->locationId == $patron->homeLocationId){
					//Next come the user's home branch if the user is logged in or has the home_branch cookie set.
					$locationList['21' . $this->displayName] = clone $this;
					$homeLibraryInList                       = true;
				}elseif (isset($patron->myLocation1Id) && $this->locationId == $patron->myLocation1Id){
					//Next come nearby locations for the user
					$locationList['3' . $this->displayName] = clone $this;
					$alternateLibraryInList                 = true;
				}elseif (isset($patron->myLocation2Id) && $this->locationId == $patron->myLocation2Id){
					//Next come nearby locations for the user
					$locationList['4' . $this->displayName] = clone $this;
				}elseif (isset($homeLibrary) && $this->libraryId == $homeLibrary->libraryId){
					//Other locations that are within the same library system
					$locationList['5' . $this->displayName] = clone $this;
				}else{
					//Finally, all other locations are shown sorted alphabetically.
					$locationList['6' . $this->displayName] = clone $this;
				}
			}
		}
		ksort($locationList);
		//TODO: should this be done just before the return to include locations from below

		//MDN 8/14/2015 always add the home location #PK-81
		// unless the option to pickup at the home location is specifically disabled #PK-1250
		//if (count($locationList) == 0 && (isset($homeLibrary) && $homeLibrary->inSystemPickupsOnly == 1)){
		if (!empty($patron) && $patron->homeLocationId != 0){
			/** @var Location $homeLocation */
			$homeLocation             = new Location();
			$homeLocation->locationId = $patron->homeLocationId;
			if ($homeLocation->find(true)){
				if ($homeLocation->validHoldPickupBranch != 2){
					//We didn't find any locations.  This for schools where we want holds available, but don't want the branch to be a
					//pickup location anywhere else.
					$homeLocation->pickupUsers[] = $patron->id; // Add the user id to each pickup location to track multiple linked accounts having the same pick-up location.
					$existingLocation            = false;
					foreach ($locationList as $location){
						if ($location->libraryId == $homeLocation->libraryId && $location->locationId == $homeLocation->locationId){
							$existingLocation = true;
							if (!$isLinkedUser){
								$location->selected = true;
							}
							//TODO: update sorting key as well?
							break;
						}
					}
					if (!$existingLocation){
						if (!$isLinkedUser){
							$homeLocation->selected                         = true;
							$locationList['1' . $homeLocation->displayName] = clone $homeLocation;
							$homeLibraryInList                              = true;
						}else{
							$locationList['22' . $homeLocation->displayName] = clone $homeLocation;
						}
					}
				}
			}
		}

		if (!$homeLibraryInList && !$alternateLibraryInList && !$isLinkedUser){
			$locationList['0default'] = 'Please Select a Location';
		}

		return $locationList;
	}

	private static $activeLocation = 'unset';

	/**
	 * Returns the active location to use when doing search scoping, etc.
	 * This does not include the IP address
	 *
	 * @return Location|null
	 */
	static function getActiveLocation(){
		if (self::$activeLocation !== 'unset'){
			return self::$activeLocation;
		}

		//default value
		self::$activeLocation = null;

		//load information about the library we are in.
		global $library;
		if (is_null($library)){
			//If we are not in a library, then do not allow branch scoping, etc.
			self::$activeLocation = null;
		}else{

			//Check to see if a branch location has been specified.
			$_location = new Location();
            $locationCode = $_location->getBranchLocationCode();
			if (!empty($locationCode) && $locationCode != 'all'){
				//Check to see if we can get the active location based off the location's code
				$activeLocation       = new Location();
				$activeLocation->code = $locationCode;
				if ($activeLocation->find(true)){
					//Only use the location if we are in the subdomain for the parent library
					if ($library->libraryId == $activeLocation->libraryId){
						self::$activeLocation = clone $activeLocation;
					}else{
						// If the active location doesn't belong to the library we are browsing at, turn off the active location
						self::$activeLocation = null;
					}
				}
			}else{
				// Check if we know physical location by the ip table
                $_location = new Location();
				$physicalLocation = $_location->getPhysicalLocation();
				if ($physicalLocation !== null){
					if ($library->libraryId === $physicalLocation->libraryId){
						self::$activeLocation = $physicalLocation;
					}else{
						// If the physical location doesn't belong to the library we are browsing at, turn off the active location
						self::$activeLocation = null;
					}
				}
			}
			global $timer;
			$timer->logTime('Finished getActiveLocation');
		}

		return Location::$activeLocation;
	}

	function setActiveLocation($location){
		Location::$activeLocation = $location;
	}
	/** var Location $userHomeLocation */
	private static $userHomeLocation = 'unset';

	/**
	 * Get the home location for the currently logged in user.
	 *
	 * @return Location
	 */
	static function getUserHomeLocation(){
		if (isset(Location::$userHomeLocation) && Location::$userHomeLocation != 'unset'){
			return Location::$userHomeLocation;
		}

		// default value
		Location::$userHomeLocation = null;

		if (UserAccount::isLoggedIn()){
			$homeLocation             = new Location();
			$homeLocation->locationId = UserAccount::getUserHomeLocationId();
			if ($homeLocation->find(true)){
				Location::$userHomeLocation = clone($homeLocation);
			}
		}

		return Location::$userHomeLocation;
	}


	private $branchLocationCode = 'unset';

	function getBranchLocationCode(){
		if (isset($this->branchLocationCode) && $this->branchLocationCode != 'unset'){
			return $this->branchLocationCode;
		}
		$this->branchLocationCode = $_GET['branch'] ?? $_COOKIE['branch'] ?? '';

		if ($this->branchLocationCode == 'all'){
			$this->branchLocationCode = '';
		}
		return $this->branchLocationCode;
	}

	/**
	 * The physical location where the user is based on
	 * IP address and branch parameter, and only for It's Here messages
	 *
	 */
	private $physicalLocation = 'unset';

	function getPhysicalLocation(){
		if ($this->physicalLocation != 'unset'){
			return $this->physicalLocation;
		}

		if ($this->getBranchLocationCode() != ''){
			$this->physicalLocation = $this->getActiveLocation();
		}else{
			$this->physicalLocation = $this->getIPLocation();
		}
		return $this->physicalLocation;
	}

	static $searchLocation = [];

	/**
	 * @param null $searchSource
	 * @return Location|null
	 */
	static function getSearchLocation($searchSource = null){
		if ($searchSource == null){
			global $searchSource;
		}
		if ($searchSource == 'combinedResults'){
			$searchSource = 'local';
		}
		if (!array_key_exists($searchSource, Location::$searchLocation)){
			$scopingSetting = $searchSource;
			if ($searchSource == null){
				Location::$searchLocation[$searchSource] = null;
			}elseif ($scopingSetting == 'local' || $scopingSetting == 'econtent' || $scopingSetting == 'location'){
				global $locationSingleton;
				Location::$searchLocation[$searchSource] = $locationSingleton->getActiveLocation();
			}elseif ($scopingSetting == 'marmot' || $scopingSetting == 'unscoped'){
				Location::$searchLocation[$searchSource] = null;
			}else{
				$location       = new Location();
				$location->code = $scopingSetting;
				$location->find();
				if ($location->N > 0){
					$location->fetch();
					Location::$searchLocation[$searchSource] = clone($location);
				}else{
					Location::$searchLocation[$searchSource] = null;
				}
			}
		}
		return Location::$searchLocation[$searchSource];
	}

	/**
	 * The location we are in based solely on IP address.
	 *
	 * @var string
	 */
	private $ipLocation = 'unset';
	private $ipId       = 'unset';

	function getIPLocation(){
		if ($this->ipLocation != 'unset'){
			return $this->ipLocation;
		}
		global $timer;
		global $configArray;
		//Check the current IP address to see if we are in a branch
		$activeIp = $this->getActiveIp();
		$this->ipLocation = $this->cache->get('location_for_ip_' . $activeIp);
		$this->ipId       = $this->cache->get('ipId_for_ip_' . $activeIp);
		if ($this->ipId == -1){
			$this->ipLocation = false;
		}

		if ($this->ipLocation == false || $this->ipId == false){
			$timer->logTime('Starting getIPLocation');
			//echo("Active IP is $activeIp");
			require_once ROOT_DIR . '/sys/Network/subnet.php';
			$subnet = new subnet();
			$ipVal  = ip2long($activeIp);

			$this->ipLocation = null;
			$this->ipId       = -1;
			if (is_numeric($ipVal)){
				disableErrorHandler();
				$subnet->whereAdd('startIpVal <= ' . $ipVal);
				$subnet->whereAdd('endIpVal >= ' . $ipVal);
				$subnet->orderBy('(endIpVal - startIpVal)');
				if ($subnet->find(true)){
					$matchedLocation             = new Location();
					$matchedLocation->locationId = $subnet->locationid;
					if ($matchedLocation->find(true)){
						//Only use the physical location regardless of where we are
						$this->ipLocation = clone $matchedLocation;
						$this->ipLocation->setOpacStatus((boolean)$subnet->isOpac);

						$this->ipId = $subnet->id;
					}else{
						$this->logger->warn("Did not find location for ip location id {$subnet->locationid}");
					}
				}
				enableErrorHandler();
			}

			$this->cache->set('ipId_for_ip_' . $activeIp, $this->ipId, $configArray['Caching']['ipId_for_ip']);
			$this->cache->set('location_for_ip_' . $activeIp, $this->ipLocation, $configArray['Caching']['location_for_ip']);
			$timer->logTime('Finished getIPLocation');
		}

		return $this->ipLocation;
	}

	/**
	 * Must be called after the call to getIPLocation
	 * Enter description here ...
	 */
	function getIPid(){
		return $this->ipId;
	}

	private static $activeIp = null;

	static function getActiveIp(){
		if (!is_null(Location::$activeIp)){
			return Location::$activeIp;
		}
		global $timer;
		//Make sure gets and cookies are processed in the correct order.
		if (isset($_GET['test_ip'])){
			$ip = $_GET['test_ip'];
			//Set a cookie so we don't have to transfer the ip from page to page.
//			setcookie('test_ip', $ip, 0, '/', NULL, 1, 1);
			handleCookie('test_ip', $ip);
		}elseif (!empty($_COOKIE['test_ip']) && $_COOKIE['test_ip'] != '127.0.0.1'){
			$ip = $_COOKIE['test_ip'];
		}else{
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] // Cloudflare Proxy parameter
				?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_FORWARDED'] ??
				$_SERVER['HTTP_FORWARDED_FOR'] ?? $_SERVER['HTTP_FORWARDED'] ?? $_SERVER['HTTP_FORWARDED'] ??
				$_SERVER['REMOTE_HOST'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
		}
		Location::$activeIp = $ip;
		$timer->logTime('Location::getActiveIp()');
		return Location::$activeIp;
	}

	/* Add on if the Main Branch gets used more frequently
		private static $mainBranchLocation = 'unset';
		function getMainBranchLocation() {
			if (Location::$mainBranchLocation != 'unset') return Location::$mainBranchLocation;
			Location::$mainBranchLocation = null; // set default value
			global $library;
			if (!empty($library->libraryId)) {
				$mainBranch = new Location();
				$mainBranch->libraryId = $library->libraryId;
				$mainBranch->isMainBranch = true;
				if ($mainBranch->find(true)) {
					Location::$mainBranchLocation =  clone $mainBranch;
				}
			}
			return Location::$mainBranchLocation;
		}
	*/

	function getLocationsFacetsForLibrary($libraryId){
		$facets              = [];
		$location            = new Location();
		$location->libraryId = $libraryId;
		$location->find();
		if ($location->N > 0){
			while ($location->fetch()){
				$facets[] = $location->facetLabel;
			}
		}
		return $facets;
	}

	/**
	 * Return a default location for a library.
	 *
	 * For a single branch library the only location will be returned.
	 * For a multi-branch library, return the main branch, or if that isn't set the first branch in the table.
	 * For a library with no locations, return false.
	 *
	 * @param $libraryId
	 * @return false|Location
	 */
	static function getDefaultLocationForLibrary($libraryId){
		if (!empty($libraryId)){
			$tempLocation            = new Location();
			$tempLocation->libraryId = $libraryId;
			$tempLocation->orderBy('isMainBranch desc');
			if ($tempLocation->find(true)){
				return $tempLocation;
			}
		}
		return false;
	}

	public function __get($name){
		if ($name == "hours"){
			if (!isset($this->hours) && $this->locationId){
				$this->hours = $this->getOneToManyOptions('LocationHours', 'day');
			}
			return $this->hours;
		}elseif ($name == "moreDetailsOptions"){
			if (!isset($this->moreDetailsOptions) && $this->locationId){
				$this->moreDetailsOptions = $this->getOneToManyOptions('LocationMoreDetails', 'weight');
			}
			return $this->moreDetailsOptions;
		}elseif ($name == "facets"){
			if (!isset($this->facets) && $this->locationId){
					$this->facets = $this->getOneToManyOptions('LocationFacetSetting', 'weight');
			}
			return $this->facets;
		}elseif ($name == 'recordsOwned'){
			if (!isset($this->recordsOwned) && $this->locationId){
				$this->recordsOwned = $this->getOneToManyOptions('LocationRecordOwned');
			}
			return $this->recordsOwned;
		}elseif ($name == 'recordsToInclude'){
			if (!isset($this->recordsToInclude) && $this->locationId){
				$this->recordsToInclude = $this->getOneToManyOptions('LocationRecordToInclude', 'weight');
			}
			return $this->recordsToInclude;
		}elseif ($name == 'browseCategories'){
			if (!isset($this->browseCategories) && $this->locationId){
				$this->browseCategories = $this->getOneToManyOptions('LocationBrowseCategory', 'weight');
			}
			return $this->browseCategories;
		}elseif ($name == 'combinedResultSections'){
			if (!isset($this->combinedResultSections) && $this->locationId){
				$this->combinedResultSections = $this->getOneToManyOptions('LocationCombinedResultSection', 'weight');
				return $this->combinedResultSections;
			}
		}elseif ($name == 'hooplaSettings'){
			if (!isset($this->hooplaSettings) && $this->locationId){
				$this->hooplaSettings = $this->getOneToManyOptions('LocationHooplaSettings');
				return $this->hooplaSettings;
			}
		}else{
			return $this->data[$name];
		}

	}

	public function __set($name, $value){
		if ($name == 'hours'){
			$this->hours = $value;
		}elseif ($name == 'moreDetailsOptions'){
			$this->moreDetailsOptions = $value;
		}elseif ($name == 'facets'){
			$this->facets = $value;
		}elseif ($name == 'browseCategories'){
			$this->browseCategories = $value;
		}elseif ($name == 'recordsOwned'){
			$this->recordsOwned = $value;
		}elseif ($name == 'recordsToInclude'){
			$this->recordsToInclude = $value;
		}elseif ($name == 'combinedResultSections'){
			$this->combinedResultSections = $value;
		}elseif ($name == 'hooplaSettings'){
			$this->hooplaSettings = $value;
		}else{
			$this->data[$name] = $value;
		}
	}

	/**
	 * Override the update functionality to save the hours
	 *
	 * @see DB/DB_DataObject::update()
	 */
	public function update($dataObject = false){
		$ret = parent::update();
		if ($ret !== false){
			$this->saveHours();
			$this->saveFacets();
			$this->saveBrowseCategories();
			$this->saveMoreDetailsOptions();
			$this->saveRecordsOwned();
			$this->saveRecordsToInclude();
			$this->saveCombinedResultSections();
			$this->saveHooplaSettings();
		}
		return $ret;
	}

	/**
	 * Override the update functionality to save the hours
	 *
	 * @see DB/DB_DataObject::insert()
	 */
	public function insert(){
		$ret = parent::insert();
		if ($ret !== false){
			$this->saveHours();
			$this->saveFacets();
			$this->saveBrowseCategories();
			$this->saveMoreDetailsOptions();
			$this->saveRecordsOwned();
			$this->saveRecordsToInclude();
			$this->saveCombinedResultSections();
			$this->saveHooplaSettings();
		}
		return $ret;
	}

	public function saveBrowseCategories(){
		if (isset ($this->browseCategories) && is_array($this->browseCategories)){
			$this->saveOneToManyOptions($this->browseCategories);
			unset($this->browseCategories);
		}
	}

	public function clearBrowseCategories(){
		$this->clearOneToManyOptions('LocationBrowseCategory');
		$this->browseCategories = [];
	}

	public function saveMoreDetailsOptions(){
		if (isset ($this->moreDetailsOptions) && is_array($this->moreDetailsOptions)){
			$this->saveOneToManyOptions($this->moreDetailsOptions);
			unset($this->moreDetailsOptions);
		}
	}

	public function clearMoreDetailsOptions(){
		$this->clearOneToManyOptions('LocationMoreDetails');
		$this->moreDetailsOptions = [];
	}

	public function saveCombinedResultSections(){
		if (isset ($this->combinedResultSections) && is_array($this->combinedResultSections)){
			$this->saveOneToManyOptions($this->combinedResultSections);
			unset($this->combinedResultSections);
		}
	}

	public function clearCombinedResultSections(){
		$this->clearOneToManyOptions('LocationCombinedResultSection');
		$this->combinedResultSections = [];
	}

	public function saveFacets(){
		if (isset ($this->facets) && is_array($this->facets)){
			$this->saveOneToManyOptions($this->facets);
			unset($this->facets);
		}
	}

	public function clearFacets(){
		$this->clearOneToManyOptions('LocationFacetSetting');
		$this->facets = [];
	}



	public function saveHours(){
		if (isset ($this->hours) && is_array($this->hours)){
			$this->saveOneToManyOptions($this->hours);
			unset($this->hours);
		}
	}

	public function saveHooplaSettings(){
		if (isset ($this->hooplaSettings) && is_array($this->hooplaSettings)){
			$this->saveOneToManyOptions($this->hooplaSettings);
			unset($this->hooplaSettings);
		}
	}

	/**
	 * Delete any Hoopla settings there are for this location
	 * @return bool  Whether or not the deletion was successful
	 */
	public function clearHooplaSettings(){
		$success = $this->clearOneToManyOptions('LocationHooplaSettings');
		$this->hooplaSettings = [];
		return $success >= 1;
	}

	/**
	 * Copy the Hoopla settings for the parent library to the location.
	 * This function will get called through an AJAX operation
	 *
	 * @return bool  returns false if any insert failed.
	 */
	public function copyLibraryHooplaSettings(){
		$success = true;
		$libraryHooplaSettings = new LibraryHooplaSettings();
		$libraryHooplaSettings->libraryId = $this->libraryId;
		/** @var LibraryHooplaSettings[] $hooplaSettings */
		$hooplaSettings = $libraryHooplaSettings->fetchAll();
		foreach ($hooplaSettings as $setting){
			$locationHooplaSetting                            = new LocationHooplaSettings();
			$locationHooplaSetting->locationId                = $this->locationId;
			$locationHooplaSetting->kind                      = $setting->kind;
			$locationHooplaSetting->maxPrice                  = $setting->maxPrice;
			$locationHooplaSetting->excludeParentalAdvisory   = $setting->excludeParentalAdvisory;
			$locationHooplaSetting->excludeProfanity          = $setting->excludeProfanity;
			$locationHooplaSetting->includeChildrenTitlesOnly = $setting->includeChildrenTitlesOnly;
			if (!$locationHooplaSetting->insert()){
				$success = false;
			}
		}
		return $success;
	}

	/**
	 * Copy the Hoopla settings from a specified location to the current location.
	 *
	 *
	 * @param int $copyFromLocationId the location to be copied from
	 * @return bool  returns false if any insert failed.
	 */
	public function copyLocationHooplaSettings($copyFromLocationId){
		$success                            = true;
		$copyFromHooplaSettings             = new LocationHooplaSettings();
		$copyFromHooplaSettings->locationId = $copyFromLocationId;
		$hooplaSettings                     = $copyFromHooplaSettings->fetchAll();
		foreach ($hooplaSettings as $setting){
			$copyToHooplaSetting                            = new LocationHooplaSettings();
			$copyToHooplaSetting->locationId                = $this->locationId;
			$copyToHooplaSetting->kind                      = $setting->kind;
			$copyToHooplaSetting->maxPrice                  = $setting->maxPrice;
			$copyToHooplaSetting->excludeParentalAdvisory   = $setting->excludeParentalAdvisory;
			$copyToHooplaSetting->excludeProfanity          = $setting->excludeProfanity;
			$copyToHooplaSetting->includeChildrenTitlesOnly = $setting->includeChildrenTitlesOnly;

			if (!$copyToHooplaSetting->insert()){
				$success = false;
			}
		}
		return $success;
	}

	public static function getLibraryHours($locationId, $timeToCheck){
		$location             = new Location();
		$location->locationId = $locationId;
		if ($locationId > 0 && $location->find(true)){
			// format $timeToCheck according to MySQL default date format
			$todayFormatted = date('Y-m-d', $timeToCheck);

			// check to see if today is a holiday
			require_once ROOT_DIR . '/sys/Library/Holiday.php';
			$holiday            = new Holiday();
			$holiday->date      = $todayFormatted;
			$holiday->libraryId = $location->libraryId;
			if ($holiday->find(true)){
				return [
					'closed'        => true,
					'closureReason' => $holiday->name,
				];
			}

			// get the day of the week (0=Sunday to 6=Saturday)
			$dayOfWeekToday = strftime('%w', $timeToCheck);

			// find library hours for the above day of the week
			require_once ROOT_DIR . '/sys/Location/LocationHours.php';
			$hours             = new LocationHours();
			$hours->locationId = $locationId;
			$hours->day        = $dayOfWeekToday;
			if ($hours->find(true)){
				$hours->fetch();
				return [
					'open'           => ltrim($hours->open, '0'),
					'close'          => ltrim($hours->close, '0'),
					'closed'         => $hours->closed ? true : false,
					'openFormatted'  => ($hours->open == '12:00' ? 'Noon' : date("g:i A", strtotime($hours->open))),
					'closeFormatted' => ($hours->close == '12:00' ? 'Noon' : date("g:i A", strtotime($hours->close))),
				];
			}
		}


		// no hours found
		return null;
	}

	public static function getLibraryHoursMessage($locationId){
		$today              = time();
		$todaysLibraryHours = Location::getLibraryHours($locationId, $today);
		if (isset($todaysLibraryHours) && is_array($todaysLibraryHours)){
			$location = new Location();
			$location->get($locationId);
			$locationName = '<strong>' . $location->displayName . '</strong>';
			if (isset($todaysLibraryHours['closed']) && ($todaysLibraryHours['closed'] == true || $todaysLibraryHours['closed'] == 1)){
				if (isset($todaysLibraryHours['closureReason'])){
					$closureReason = $todaysLibraryHours['closureReason'];
				}
				//Library is closed now
				$nextDay      = time() + (24 * 60 * 60);
				$nextDayHours = Location::getLibraryHours($locationId, $nextDay);
				$daysChecked  = 0;
				while (isset($nextDayHours['closed']) && $nextDayHours['closed'] == true && $daysChecked < 7){
					$nextDay      += (24 * 60 * 60);
					$nextDayHours = Location::getLibraryHours($locationId, $nextDay);
					$daysChecked++;
				}

				$nextDayOfWeek = strftime('%a', $nextDay);
				if (isset($nextDayHours['closed']) && $nextDayHours['closed'] == true){
					if (isset($closureReason)){
						$libraryHoursMessage = "$locationName is closed today for $closureReason.";
					}else{
						$libraryHoursMessage = "$locationName is closed today.";
					}
				}else{
					if (isset($closureReason)){
						$libraryHoursMessage = "$locationName is closed today for $closureReason. It will reopen on $nextDayOfWeek from {$nextDayHours['openFormatted']} to {$nextDayHours['closeFormatted']}";
					}else{
						$libraryHoursMessage = "$locationName is closed today. It will reopen on $nextDayOfWeek from {$nextDayHours['openFormatted']} to {$nextDayHours['closeFormatted']}";
					}
				}
			}else{
				//Library is open
				$currentHour = strftime('%H', $today);
				$openHour    = strftime('%H', strtotime($todaysLibraryHours['open']));
				$closeHour   = strftime('%H', strtotime($todaysLibraryHours['close']));
				if ($closeHour == 0 && $closeHour < $openHour){
					$closeHour = 24;
				}
				if ($currentHour < $openHour){
					$libraryHoursMessage = "$locationName will be open today from " . $todaysLibraryHours['openFormatted'] . " to " . $todaysLibraryHours['closeFormatted'] . ".";
				}else{
					if ($currentHour > $closeHour){
						$tomorrowsLibraryHours = Location::getLibraryHours($locationId, time() + (24 * 60 * 60));
						if (isset($tomorrowsLibraryHours['closed']) && ($tomorrowsLibraryHours['closed'] == true || $tomorrowsLibraryHours['closed'] == 1)){
							if (isset($tomorrowsLibraryHours['closureReason'])){
								$libraryHoursMessage = "$locationName will be closed tomorrow for {$tomorrowsLibraryHours['closureReason']}.";
							}else{
								$libraryHoursMessage = "$locationName will be closed tomorrow";
							}

						}else{
							$libraryHoursMessage = "$locationName will be open tomorrow from " . $tomorrowsLibraryHours['openFormatted'] . " to " . $tomorrowsLibraryHours['closeFormatted'] . ".";
						}
					}else{
						$libraryHoursMessage = "$locationName is open today from " . $todaysLibraryHours['openFormatted'] . " to " . $todaysLibraryHours['closeFormatted'] . ".";
					}
				}
			}
		}else{
			$libraryHoursMessage = null;
		}
		return $libraryHoursMessage;
	}

	public function saveRecordsOwned(){
		if (isset ($this->recordsOwned) && is_array($this->recordsOwned)){
			/** @var LibraryRecordOwned $object */
			foreach ($this->recordsOwned as $object){
				if (isset($object->deleteOnSave) && $object->deleteOnSave == true){
					$object->delete();
				}else{
					if (isset($object->id) && is_numeric($object->id)){
						$object->update();
					}else{
						$object->locationId = $this->locationId;
						$object->insert();
					}
				}
			}
			unset($this->recordsOwned);
		}
	}

	public function clearRecordsOwned(){
		$object             = new LocationRecordOwned();
		$object->locationId = $this->locationId;
		$object->delete();
		$this->recordsOwned = [];
	}

	public function saveRecordsToInclude(){
		if (isset ($this->recordsToInclude) && is_array($this->recordsToInclude)){
			/** @var LibraryRecordOwned $object */
			foreach ($this->recordsToInclude as $object){
				if (isset($object->deleteOnSave) && $object->deleteOnSave === true){
					$object->delete();
				}else{
					if (isset($object->id) && is_numeric($object->id)){
						$object->update();
					}else{
						$object->locationId = $this->locationId;
						$object->insert();
					}
				}
			}
			unset($this->recordsToInclude);
		}
	}
    
    public function __isset($name) {
        return isset($this->$name);
    }

	public function clearRecordsToInclude(){
		$object             = new LibraryRecordToInclude();
		$object->locationId = $this->locationId;
		$object->delete();
		$this->recordsToInclude = [];
	}

	public function clearLocationRecordsToInclude(){
		$success     = $this->clearOneToManyOptions('LocationRecordToInclude');
		$this->hours = [];
		return $success;
	}

	static function getDefaultFacets($locationId = -1){
		global $configArray;
		$defaultFacets = [];

		$facet = new LocationFacetSetting();
		$facet->setupTopFacet('format_category', 'Format Category');
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		if ($configArray['Index']['enableDetailedAvailability']){
			$facet = new LocationFacetSetting();
			$facet->setupTopFacet('availability_toggle', 'Available?', true);
			$facet->locationId = $locationId;
			$facet->weight     = count($defaultFacets) + 1;
			$defaultFacets[]   = $facet;

			$facet = new LocationFacetSetting();
			$facet->setupSideFacet('available_at', 'Available Now At', true);
			$facet->locationId = $locationId;
			$facet->weight     = count($defaultFacets) + 1;
			$defaultFacets[]   = $facet;
		}

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('format', 'Format', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('literary_form_full', 'Literary Form', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('target_audience_full', 'Reading Level', true);
		$facet->locationId                = $locationId;
		$facet->weight                    = count($defaultFacets) + 1;
		$facet->numEntriesToShowByDefault = 8;
		$defaultFacets[]                  = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('topic_facet', 'Subject', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('time_since_added', 'Added in the Last', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('authorStr', 'Author', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupAdvancedFacet('awards_facet', 'Awards', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('econtent_device', 'Compatible Device', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupAdvancedFacet('econtent_source', 'eContent Source', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupAdvancedFacet('era', 'Era', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('genre_facet', 'Genre', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('itype', 'Item Type', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('language', 'Language', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupAdvancedFacet('lexile_code', 'Lexile code', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupAdvancedFacet('lexile_score', 'Lexile measure', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupAdvancedFacet('mpaa_rating', 'Movie Rating', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('owning_library', 'Owning System', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('owning_location', 'Owning Branch', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('publishDate', 'Publication Date', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupAdvancedFacet('geographic_facet', 'Region', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		$facet = new LocationFacetSetting();
		$facet->setupSideFacet('rating_facet', 'User Rating', true);
		$facet->locationId = $locationId;
		$facet->weight     = count($defaultFacets) + 1;
		$defaultFacets[]   = $facet;

		return $defaultFacets;
	}

	/** @return LocationHours[] */
	function getHours(){
		return $this->hours;
	}

	public function getNumHours(){
		$hours             = new LocationHours();
		$hours->locationId = $this->locationId;
		return $hours->count();
	}

	public function clearHours(){
		$success     = $this->clearOneToManyOptions('LocationHours');
		$this->hours = [];
		return $success;
	}

	public function getHoursFormatted(){
		unset($this->hours); // clear out any previous hours data that has been set
		$hours = $this->getHours();
		foreach ($hours as $key => $hourObj){
			if (!$hourObj->closed){
				$formattedHoursObject         = new LocationHours();
				$formattedHoursObject->day    = $hourObj->day;
				$formattedHoursObject->open   = $hourObj->formatHours($hourObj->open);
				$formattedHoursObject->close  = $hourObj->formatHours($hourObj->close);
				$formattedHoursObject->closed = $hourObj->closed;
				$hours[$key]                  = $formattedHoursObject;
			}
		}
		return $hours;
	}

	/**
	 * @return array
	 */
	public static function getLocationLookupList(){
		$location = new Location();
		$location->orderBy('displayName');
		$location->find();
		$locationList           = [];
		$locationLookupList     = [];
		$locationLookupList[-1] = '<No Nearby Location>';
		while ($location->fetch()){
			$locationLookupList[$location->locationId] = $location->displayName;
			$locationList[$location->locationId]       = clone $location;
		}
		return $locationLookupList;
	}

	public function getLocationInformation(){
		global $configArray;
		$hours        = $this->getHoursFormatted();
		$mapAddress   = urlencode(preg_replace('/\r\n|\r|\n/', '+', $this->address));
		$mapLink      = $_SERVER['REQUEST_SCHEME'] . "://maps.google.com/maps?f=q&hl=en&geocode=&q=$mapAddress&ie=UTF8&z=15&iwloc=addr&om=1&t=m";
		$mapImageLink = $_SERVER['REQUEST_SCHEME'] . "://maps.googleapis.com/maps/api/staticmap?center=$mapAddress&zoom=15&size=300x300&sensor=false&markers=color:red%7C$mapAddress&key=" . $configArray['Maps']['apiKey'];
		$locationInfo = [
			'id'        => $this->locationId,
			'name'      => $this->displayName,
			'address'   => preg_replace('/\r\n|\r|\n/', '<br>', $this->address),
			'phone'     => $this->phone,
			'map_image' => $mapImageLink,
			'map_link'  => $mapLink,
			'hours'     => $hours,
		];
		return $locationInfo;
	}

	private $opacStatus = null;

	/**
	 * Check whether the system is an OPAC station.
	 * - First check to see if an opac paramter has been passed.  If so, use that information and set a cookie for future pages.
	 * - Next check the cookie to see if we have overridden the value
	 * - Finally check to see if we have an active location based on the IP address.  If we do, use that to determine if this is an opac station
	 *
	 * @return bool
	 */
	public function getOpacStatus(){
		global $configArray;
		if (is_null($this->opacStatus)){
			if (isset($_GET['opac'])){
				$this->opacStatus = $_GET['opac'] == 1 || strtolower($_GET['opac']) == 'true' || strtolower($_GET['opac']) == 'on';
				if ($_GET['opac'] == ''){
					//Clear any existing cookie

					if (!$configArray['Site']['isDevelopment']){
						setcookie('opac', $this->opacStatus, time() - 1000, '/', null, 1, 1);
					}else{
						setcookie('opac', $this->opacStatus, time() - 1000, '/', null, 0, 1);
					}
				}elseif (!isset($_COOKIE['opac']) || $this->opacStatus != $_COOKIE['opac']){
					if (!$configArray['Site']['isDevelopment']){
						setcookie('opac', $this->opacStatus ? '1' : '0', 0, '/', null, 1, 1);
					}else{
						setcookie('opac', $this->opacStatus ? '1' : '0', 0, '/', null, 0, 1);
					}
				}
			}elseif (isset($_COOKIE['opac'])){
				$this->opacStatus = (boolean)$_COOKIE['opac'];
			}else{
				if ($this->getIPLocation()){
					$this->opacStatus = $this->getIPLocation()->opacStatus;
				}else{
					$this->opacStatus = false;
				}
			}
		}
		return $this->opacStatus;
	}

	/**
	 * Primarily Intended to set the opac status for the ipLocation object
	 * when the iptable indicates that the ip is to be treated as a public opac
	 *
	 * @param null $opacStatus
	 */
	public function setOpacStatus($opacStatus = null){
		$this->opacStatus = $opacStatus;
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->displayName)){
			return $this->displayName;
		}
	}

}
