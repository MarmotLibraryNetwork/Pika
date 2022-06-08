<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Table Definition for library
 */
require_once 'DB/DataObject.php';
//
require_once ROOT_DIR . '/sys/OneToManyDataObjectOperations.php';

require_once ROOT_DIR . '/sys/Library/LibraryFacetSetting.php';
require_once ROOT_DIR . '/sys/Library/LibraryArchiveSearchFacetSetting.php';
require_once ROOT_DIR . '/sys/Library/LibraryCombinedResultSection.php';
require_once ROOT_DIR . '/sys/Library/LibraryMoreDetails.php';
require_once ROOT_DIR . '/sys/Library/LibraryArchiveMoreDetails.php';
require_once ROOT_DIR . '/sys/Library/LibraryLink.php';
require_once ROOT_DIR . '/sys/Library/LibraryTopLinks.php';
require_once ROOT_DIR . '/sys/Indexing/LibraryRecordOwned.php';
require_once ROOT_DIR . '/sys/Indexing/LibraryRecordToInclude.php';
require_once ROOT_DIR . '/sys/Hoopla/LibraryHooplaSettings.php';
require_once ROOT_DIR . '/sys/Browse/LibraryBrowseCategory.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestFieldsToDisplay.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestFormats.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestFormFields.php';

class Library extends DB_DataObject {

	use OneToManyDataObjectOperations;

	public $__table = 'library';    // table name
	public $isDefault;
	public $libraryId; 				//int(11)
	public $subdomain; 				//varchar(15)
	public $catalogUrl;
	public $displayName; 			//varchar(50)
	public $showDisplayNameInHeader;
	public $headerText;
	public $abbreviatedDisplayName;
	public $systemMessage;
	public $ilsCode;
	public $themeName; 				//varchar(15)
	public $restrictSearchByLibrary;
	public $allowProfileUpdates;   //tinyint(4)
	public $allowFreezeHolds;   //tinyint(4)
	public $scope; 					//smallint(6) // The Sierra OPAC scope
	public $hideCommentsWithBadWords; //tinyint(4)
	public $showStandardReviews;
	public $showHoldButton;
	public $showHoldButtonInSearchResults;
	public $showHoldButtonForUnavailableOnly;
	public $showLoginButton;
	public $showTextThis;
	public $showEmailThis;
	public $showComments; // User Reviews switch
	public $showTagging;
	public $showRatings; // User Ratings
	public $showFavorites;
	public $inSystemPickupsOnly;
	public $validPickupSystems;
	public $pTypes;
	public $defaultPType;
	public $facetLabel;
	public $showEcommerceLink;
	public $payFinesLink;
	public $payFinesLinkText;
	public $minimumFineAmount;
	public $fineAlertAmount;
	public $showRefreshAccountButton;    // specifically to refresh account after paying fines online
	public $repeatSearchOption;
	public $repeatInOnlineCollection;
	public $repeatInProspector;
	public $repeatInWorldCat;
	/* public $loginConfiguration; todo: [pins] revisit if we go with per library settings. */

	/* Self Registration */
	public $selfRegistrationFormMessage;
	public $selfRegistrationSuccessMessage;

	public $enableSelfRegistration;
	public $externalSelfRegistrationUrl;
	public $promptForBirthDateInSelfReg;
	public $selfRegistrationDefaultpType;
	public $selfRegistrationBarcodeLength;
	public $selfRegistrationDaysUntilExpire;
	public $selfRegistrationAgencyCode;

	//Overdrive Settings
	public $enableOverdriveCollection;
	public $sharedOverdriveCollection;
	public $includeOverDriveAdult;
	public $includeOverDriveTeen;
	public $includeOverDriveKids;
	public $repeatInOverdrive;
	public $overdriveAuthenticationILSName;
	public $overdriveRequirePin;
	public $overdriveAdvantageName;
	public $overdriveAdvantageProductsKey;
	public $eContentSupportAddress;

	/* HOOPLA */
	public $hooplaLibraryID;

	/* GOOGLE ANALYTICS */
	public $gaTrackingId;

	/* USER PROFILE */
	public $showPatronBarcodeImage;

	public $systemsToRepeatIn;
	public $additionalLocationsToShowAvailabilityFor;
	public $homeLink;
	public $homeLinkText;
	public $useHomeLinkInBreadcrumbs;
	public $useHomeLinkForLogo;
	public $showAdvancedSearchbox;
	public $enableProspectorIntegration;
	public $showProspectorResultsAtEndOfSearch;
	public $enableGenealogy;
	public $showHoldCancelDate;
	public $enableCourseReserves;

	public $showItsHere;
	public $holdDisclaimer;
	public $enableMaterialsRequest;
	public $externalMaterialsRequestUrl;
	public $includeNovelistEnrichment;
	public $applyNumberOfHoldingsBoost;
	public $allowAutomaticSearchReplacements;
	public $show856LinksAsTab;
	public $worldCatUrl;
	public $worldCatQt;
	public $preferSyndeticsSummary;
	public $showSimilarAuthors;
	public $showSimilarTitles;
	public $showGoDeeper;
	public $defaultNotNeededAfterDays;
	public $showCheckInGrid;
	public $boostByLibrary;
	public $additionalLocalBoostFactor;
	public $recordsToBlackList;
	public $publicListsToInclude;
	public $showWikipediaContent;

	public $restrictOwningBranchesAndSystems;
	public $allowPatronAddressUpdates;
	public $showWorkPhoneInProfile;
	public $showNoticeTypeInProfile;
	public $showPickupLocationInProfile;
	public $showAlternateLibraryOptionsInProfile;
	public $additionalCss;
	public $maxRequestsPerYear;
	public $maxOpenRequests;
	// Contact Links //
	public $twitterLink;
	public $facebookLink;
	public $youtubeLink;
	public $instagramLink;
	public $goodreadsLink;
	public $generalContactLink;

	public $allowPinReset;
	public $preventExpiredCardLogin;
	public $showLibraryHoursAndLocationsLink;
	public $showLibraryHoursNoticeOnAccountPages;
	public $showSearchTools;
	public $showShareOnExternalSites;
	public $showQRCode;
	public $showGoodReadsReviews;
	public $showStaffView;
	public $barcodePrefix;
	public $minBarcodeLength;
	public $maxBarcodeLength;

	public $showExpirationWarnings;
	public $availabilityToggleLabelSuperScope;
	public $availabilityToggleLabelLocal;
	public $availabilityToggleLabelAvailable;
	public $availabilityToggleLabelAvailableOnline;
	public $includeOnlineMaterialsInAvailableToggle;
	public $loginFormUsernameLabel;
	public $loginFormPasswordLabel;
	public $showDetailedHoldNoticeInformation;
	public $treatPrintNoticesAsPhoneNotices;
	public $includeDplaResults;
	public $showInMainDetails;
	public $showInSearchResultsMainDetails;
	public $defaultBrowseMode;
	public $browseCategoryRatingsMode;
	public $enableMaterialsBooking;
	public $allowLinkedAccounts;
	public $horizontalSearchBar;
	public $sideBarOnRight;
	public $showSidebarMenu;
	public $sidebarMenuButtonText;
	public $enableArchive;
	public $archiveNamespace;
	public $archivePid;
	public $allowRequestsForArchiveMaterials;
	public $archiveRequestMaterialsHeader;
	public $claimAuthorshipHeader;
	public $archiveRequestEmail;
	public $hideAllCollectionsFromOtherLibraries;
	public $collectionsToHide;
	public $objectsToHide;
	public $defaultArchiveCollectionBrowseMode;
	public $showLCSubjects; // Library of Congress Subjects
	public $showBisacSubjects;
	public $showFastAddSubjects;
	public $showOtherSubjects;
	public $maxFinesToAllowAccountUpdates;
	public $edsApiProfile;
	public $edsApiUsername;
	public $edsApiPassword;
	public $edsSearchProfile;
	protected $patronNameDisplayStyle; // Needs to be protected so __get and __set are called
	private $patronNameDisplayStyleChanged = false; // Track changes so we can clear values for existing patrons
	public $includeAllRecordsInShelvingFacets;
	public $includeAllRecordsInDateAddedFacets;
	public $includeOnOrderRecordsInDateAddedFacetValues;
	public $alwaysShowSearchResultsMainDetails;
	public $casHost;
	public $casPort;
	public $casContext;
	public $showPikaLogo;
	public $masqueradeAutomaticTimeoutLength;
	public $allowMasqueradeMode;
	public $allowReadingHistoryDisplayInMasqueradeMode;
	public $newMaterialsRequestSummary;  // (Text at the top of the Materials Request Form.)
	public $materialsRequestDaysToPreserve;
	public $showGroupedHoldCopiesCount;
	public $interLibraryLoanName;
	public $interLibraryLoanUrl;
	public $expiredMessage;
	public $expirationNearMessage;
	public $showOnOrderCounts;

	//Combined Results (Bento Box)
	public $enableCombinedResults;
	public $combinedResultsLabel;
	public $defaultToCombinedResults;

	// Archive Request Form Field Settings
	public $archiveRequestFieldName;
	public $archiveRequestFieldAddress;
	public $archiveRequestFieldAddress2;
	public $archiveRequestFieldCity;
	public $archiveRequestFieldState;
	public $archiveRequestFieldZip;
	public $archiveRequestFieldCountry;
	public $archiveRequestFieldPhone;
	public $archiveRequestFieldAlternatePhone;
//	public $archiveRequestFieldEmail;
	public $archiveRequestFieldFormat;
	public $archiveRequestFieldPurpose;

	public $archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode;

	public $changeRequiresReindexing;



	// Use this to set which details will be shown in the the Main Details section of the record view.
	// You should be able to add options here without needing to change the database.
	// set the key to the desired SMARTY template variable name, set the value to the label to show in the library configuration page
	static $showInMainDetailsOptions = array(
		'showSeries'               => 'Series',
		'showPublicationDetails'   => 'Published',
		'showFormats'              => 'Formats',
		'showEditions'             => 'Editions',
		'showPhysicalDescriptions' => 'Physical Descriptions',
		'showISBNs'                => 'ISBNs',
		'showArInfo'               => 'Show Accelerated Reader Information',
		'showLexileInfo'           => 'Show Lexile Information',
		'showFountasPinnell'       => 'Show Fountas &amp; Pinnell Information  (This data must be present in MARC records)',
	);

	// Use this to set which details will be shown in the the Main Details section of the record in the search results.
	// You should be able to add options here without needing to change the database.
	// set the key to the desired SMARTY template variable name, set the value to the label to show in the library configuration page
	static $searchResultsMainDetailsOptions = array(
		'showSeries'               => 'Show Series',
		'showPublisher'            => 'Publisher',
		'showPublicationDate'      => 'Publisher Date',
		'showEditions'             => 'Editions',
		'showPhysicalDescriptions' => 'Physical Descriptions',
		'showLanguages'            => 'Show Language',
		'showArInfo'               => 'Show Accelerated Reader Information',
		'showLexileInfo'           => 'Show Lexile Information',
		'showFountasPinnell'       => 'Show Fountas &amp; Pinnell Information  (This data must be present in MARC records)',
	);

	static $archiveRequestFormFieldOptions = array('Hidden', 'Optional', 'Required');

	static $archiveMoreDetailsDisplayModeOptions = array(
		'tiled' => 'Tiled',
		'list'  => 'List',
	);

	/**
	 * Needed override for OneToManyDataObjectOperations
	 * @return string
	 */
	function getKeyOther(){
		return 'libraryId';
	}

	function keys(){
		return ['libraryId', 'subdomain'];
	}

	static function getObjectStructure(){
		// get the structure for the library system's holidays
		require_once ROOT_DIR . '/sys/Library/Holiday.php';
		$holidaysStructure = Holiday::getObjectStructure();

		// we don't want to make the libraryId property editable
		// because it is associated with this library system only
		unset($holidaysStructure['libraryId']);

		$facetSettingStructure = LibraryFacetSetting::getObjectStructure();
		unset($facetSettingStructure['weight']);
		unset($facetSettingStructure['libraryId']);
		unset($facetSettingStructure['numEntriesToShowByDefault']);
		unset($facetSettingStructure['showAsDropDown']);
		//unset($facetSettingStructure['sortMode']);

		$archiveSearchfacetSettingStructure = LibraryArchiveSearchFacetSetting::getObjectStructure();
		unset($archiveSearchfacetSettingStructure['weight']);
		unset($archiveSearchfacetSettingStructure['libraryId']);
		unset($archiveSearchfacetSettingStructure['numEntriesToShowByDefault']);
		unset($archiveSearchfacetSettingStructure['showAsDropDown']);
		unset($archiveSearchfacetSettingStructure['showAboveResults']);
		unset($archiveSearchfacetSettingStructure['showInAdvancedSearch']);
		unset($archiveSearchfacetSettingStructure['showInAuthorResults']);
		//unset($archiveSearchfacetSettingStructure['sortMode']);

		require_once ROOT_DIR . '/sys/Library/LibraryMoreDetails.php';
		$libraryMoreDetailsStructure = LibraryMoreDetails::getObjectStructure();
		unset($libraryMoreDetailsStructure['weight']);
		unset($libraryMoreDetailsStructure['libraryId']);

		require_once ROOT_DIR . '/sys/Library/LibraryArchiveMoreDetails.php';
		$libraryArchiveMoreDetailsStructure = LibraryArchiveMoreDetails::getObjectStructure();
		unset($libraryArchiveMoreDetailsStructure['weight']);
		unset($libraryArchiveMoreDetailsStructure['libraryId']);

		require_once ROOT_DIR . '/sys/Library/LibraryLink.php';
		$libraryLinksStructure = LibraryLink::getObjectStructure();
		unset($libraryLinksStructure['weight']);
		unset($libraryLinksStructure['libraryId']);

		require_once ROOT_DIR . '/sys/Library/LibraryTopLinks.php';
		$libraryTopLinksStructure = LibraryTopLinks::getObjectStructure();
		unset($libraryTopLinksStructure['weight']);
		unset($libraryTopLinksStructure['libraryId']);

		$libraryBrowseCategoryStructure = LibraryBrowseCategory::getObjectStructure();
		unset($libraryBrowseCategoryStructure['weight']);
		unset($libraryBrowseCategoryStructure['libraryId']);

		$libraryRecordOwnedStructure = LibraryRecordOwned::getObjectStructure();
		unset($libraryRecordOwnedStructure['libraryId']);

		$libraryRecordToIncludeStructure = LibraryRecordToInclude::getObjectStructure();
		unset($libraryRecordToIncludeStructure['libraryId']);
		unset($libraryRecordToIncludeStructure['weight']);

		$manageMaterialsRequestFieldsToDisplayStructure = MaterialsRequestFieldsToDisplay::getObjectStructure();
		unset($manageMaterialsRequestFieldsToDisplayStructure['libraryId']); //needed?
		unset($manageMaterialsRequestFieldsToDisplayStructure['weight']);

		$materialsRequestFormatsStructure = MaterialsRequestFormats::getObjectStructure();
		unset($materialsRequestFormatsStructure['libraryId']); //needed?
		unset($materialsRequestFormatsStructure['weight']);

		require_once ROOT_DIR . '/sys/Archive/ArchiveExploreMoreBar.php';
		$archiveExploreMoreBarStructure = ArchiveExploreMoreBar::getObjectStructure();
		unset($materialsRequestFormatsStructure['libraryId']); //needed?
		unset($materialsRequestFormatsStructure['weight']);

		$materialsRequestFormFieldsStructure = MaterialsRequestFormFields::getObjectStructure();
		unset($materialsRequestFormFieldsStructure['libraryId']); //needed?
		unset($materialsRequestFormFieldsStructure['weight']);

		$combinedResultsStructure = LibraryCombinedResultSection::getObjectStructure();
		unset($combinedResultsStructure['libraryId']);
		unset($combinedResultsStructure['weight']);

		$hooplaSettingsStructure = LibraryHooplaSettings::getObjectStructure();
		unset($hooplaSettingsStructure['libraryId']);

		$sharedOverdriveCollectionChoices = [];
		global $configArray;
		if (!empty($configArray['OverDrive']['accountId'])){
			$overdriveAccounts     = explode(',', $configArray['OverDrive']['accountId']);
			$sharedCollectionIdNum = -1; // default shared libraryId for overdrive items
			foreach ($overdriveAccounts as $overdriveAccountIgnored){
				$sharedOverdriveCollectionChoices[$sharedCollectionIdNum] = $sharedCollectionIdNum;
				$sharedCollectionIdNum--;
			}
		}else{
			$sharedOverdriveCollectionChoices = [-1 => -1]; // Have the default shared value even if accountId(s) aren't in the config
		}

		$innReachEncoreName = $configArray['InterLibraryLoan']['innReachEncoreName'];

		//$Instructions = 'For more information on ???, see the <a href="">online documentation</a>.';

		$structure = [
			'isDefault'                => ['property' => 'isDefault', 'type' => 'checkbox', 'label' => 'Default Library (one per install!)', 'description' => 'The default library instance for loading scoping information etc', 'hideInLists' => true],
			'libraryId'                => ['property' => 'libraryId', 'type' => 'label', 'label' => 'Library Id', 'description' => 'The unique id of the library within the database'],
			'subdomain'                => ['property' => 'subdomain', 'type' => 'text', 'label' => 'Subdomain', 'description' => 'The unique subdomain of the catalog url for this library', 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
			'catalogUrl'               => ['property' => 'catalogUrl', 'type' => 'label', 'label' => 'Catalog URL', 'description' => 'The catalog url used for this library'],
			'displayName'              => ['property' => 'displayName', 'type' => 'text', 'label' => 'Display Name', 'description' => 'A name to identify the library within the system', 'size' => '40'],
			'showDisplayNameInHeader'  => ['property' => 'showDisplayNameInHeader', 'type' => 'checkbox', 'label' => 'Show Display Name in Header', 'description' => 'Whether or not the display name should be shown in the header next to the logo', 'hideInLists' => true, 'default' => false],
			'abbreviatedDisplayName'   => ['property' => 'abbreviatedDisplayName', 'type' => 'text', 'label' => 'Abbreviated Display Name', 'description' => 'A short name to identify the library when space is low', 'size' => '40'],
			'changeRequiresReindexing' => ['property' => 'changeRequiresReindexing', 'type' => 'dateReadOnly', 'label' => 'Change Requires Reindexing', 'description' => 'Date Time for when this library changed settings needing re-indexing'],
			'systemMessage'            => ['property'      => 'systemMessage', 'type' => 'html', 'label' => 'System Message', 'description' => 'A message to be displayed at the top of the screen', 'size' => '80', 'hideInLists' => true,
			                               'allowableTags' => '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6><sub><sup><img><script>'],

			// Basic Display //
			'displaySection' => [
				'property' => 'displaySection', 'type' => 'section', 'label' => 'Basic Display', 'hideInLists' => true,
				'helpLink' => 'https://marmot-support.atlassian.net/l/c/bc1u5GZi', 'properties' => [
					'themeName'                        => ['property' => 'themeName', 'type' => 'text', 'label' => 'Theme Name', 'description' => 'The name of the theme which should be used for the library', 'hideInLists' => true, 'default' => 'marmot,responsive'],
					'homeLink'                         => ['property' => 'homeLink', 'type' => 'text', 'label' => 'Home Link', 'description' => 'The location to send the user when they click on the home button or logo.  Use default or blank to go back to the Pika home location.', 'size' => '40', 'hideInLists' => true,],
					'additionalCss'                    => ['property' => 'additionalCss', 'type' => 'textarea', 'label' => 'Additional CSS', 'description' => 'Extra CSS to apply to the site.  Will apply to all pages.', 'hideInLists' => true],
					'headerText'                       => ['property' => 'headerText', 'type' => 'html', 'label' => 'Header Text', 'description' => 'Optional Text to display in the header, between the logo and the log in/out buttons.  Will apply to all pages.', 'allowableTags' => '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6><img>', 'hideInLists' => true],
					'showSidebarMenu'                  => ['property' => 'showSidebarMenu', 'type' => 'checkbox', 'label' => 'Display Sidebar Menu', 'description' => 'Determines whether or not the sidebar menu will be shown.  Must also be enabled in config.ini.', 'hideInLists' => true,],
					'sidebarMenuButtonText'            => ['property' => 'sidebarMenuButtonText', 'type' => 'text', 'label' => 'Sidebar Help Button Text', 'description' => 'The text to show for the help/menu button in the sidebar', 'size' => '40', 'hideInLists' => true, 'default' => 'Help'],
					'sideBarOnRight'                   => ['property' => 'sideBarOnRight', 'type' => 'checkbox', 'label' => 'Display Sidebar on the Right Side', 'description' => 'Sidebars will be displayed on the right side of the page rather than the default left side.', 'hideInLists' => true,],
					'useHomeLinkInBreadcrumbs'         => ['property' => 'useHomeLinkInBreadcrumbs', 'type' => 'checkbox', 'label' => 'Use Home Link in Breadcrumbs', 'description' => 'Whether or not the home link should be used in the breadcumbs.', 'hideInLists' => true,],
					'useHomeLinkForLogo'               => ['property' => 'useHomeLinkForLogo', 'type' => 'checkbox', 'label' => 'Use Home Link for Logo', 'description' => 'Whether or not the home link should be used as the link for the main logo.', 'hideInLists' => true,],
					'homeLinkText'                     => ['property' => 'homeLinkText', 'type' => 'text', 'label' => 'Home Link Text', 'description' => 'The text to show for the Home breadcrumb link', 'size' => '40', 'hideInLists' => true, 'default' => 'Home'],
					'showLibraryHoursAndLocationsLink' => ['property' => 'showLibraryHoursAndLocationsLink', 'type' => 'checkbox', 'label' => 'Show Library Hours and Locations Link', 'description' => 'Whether or not the library hours and locations link is shown on the home page.', 'hideInLists' => true, 'default' => true],
					'enableGenealogy'                  => ['property' => 'enableGenealogy', 'type' => 'checkbox', 'label' => 'Enable Genealogy Functionality', 'description' => 'Whether or not patrons can search genealogy.', 'hideInLists' => true, 'default' => 1],
					'enableCourseReserves'             => ['property' => 'enableCourseReserves', 'type' => 'checkbox', 'label' => 'Enable Repeat Search in Course Reserves', 'description' => 'Whether or not patrons can repeat searches within course reserves.', 'hideInLists' => true,],
					'showPikaLogo'                     => ['property' => 'showPikaLogo', 'type' => 'checkbox', 'label' => 'Display Pika Logo', 'description' => 'Determines whether or not the Pika logo will be shown in the footer.', 'hideInLists' => true, 'default' => true],
				],
			],

			// Contact Links //
			'contactSection' => [
				'property'   => 'contact', 'type' => 'section', 'label' => 'Contact Links', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/xVUe90cQ',
				'properties' => [
					'facebookLink'       => ['property' => 'facebookLink', 'type' => 'text', 'label' => 'Facebook Link Url', 'description' => 'The url to Facebook (leave blank if the library does not have a Facebook account', 'size' => '40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/],
					'twitterLink'        => ['property' => 'twitterLink', 'type' => 'text', 'label' => 'Twitter Link Url', 'description' => 'The url to Twitter (leave blank if the library does not have a Twitter account', 'size' => '40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/],
					'youtubeLink'        => ['property' => 'youtubeLink', 'type' => 'text', 'label' => 'Youtube Link Url', 'description' => 'The url to Youtube (leave blank if the library does not have a Youtube account', 'size' => '40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/],
					'instagramLink'      => ['property' => 'instagramLink', 'type' => 'text', 'label' => 'Instagram Link Url', 'description' => 'The url to Instagram (leave blank if the library does not have a Instagram account', 'size' => '40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/],
					'goodreadsLink'      => ['property' => 'goodreadsLink', 'type' => 'text', 'label' => 'GoodReads Link Url', 'description' => 'The url to GoodReads (leave blank if the library does not have a GoodReads account', 'size' => '40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/],
					'generalContactLink' => ['property' => 'generalContactLink', 'type' => 'text', 'label' => 'General Contact Link Url', 'description' => 'The url to a General Contact Page, i.e webform or mailto link', 'size' => '40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/],
				],
			],
			// defaults should be blank so that icons don't appear on page when the link is not set. plb 1-21-2015

			// ILS/Account Integration //
			'ilsSection' => ['property' =>'ilsSection', 'type' => 'section', 'label' =>'ILS/Account Integration', 'hideInLists' => true,
			                 'helpLink' =>'https://marmot-support.atlassian.net/l/c/SaLWEWH7', 'properties' => [
					'ilsCode'                              => ['property' =>'ilsCode', 'type' =>'text', 'label' =>'ILS Code', 'description' =>'The location code that all items for this location start with.', 'size' =>'4', 'hideInLists' => false,],
					'scope'                                => ['property' =>'scope', 'type' =>'text', 'label' =>'Sierra Scope', 'description' =>'The scope for the system in Sierra. Used for Bookings', 'size' =>'4', 'hideInLists' => true,],
					'showExpirationWarnings'               => ['property' =>'showExpirationWarnings', 'type' =>'checkbox', 'label' =>'Show Expiration Warnings', 'description' =>'Whether or not the user should be shown expiration warnings if their card is nearly expired.', 'hideInLists' => true, 'default' => 1],
					'expirationNearMessage'                => ['property' =>'expirationNearMessage', 'type' =>'text', 'label' =>'Expiration Near Message (use the token %date% to insert the expiration date)', 'description' =>'A message to show in the menu when the user account will expire soon', 'hideInLists' => true, 'default' => ''],
					'expiredMessage'                       => ['property' =>'expiredMessage', 'type' =>'text', 'label' =>'Expired Message (use the token %date% to insert the expiration date)', 'description' =>'A message to show in the menu when the user account has expired', 'hideInLists' => true, 'default' => ''],
					'enableMaterialsBooking'               => ['property' =>'enableMaterialsBooking', 'type' =>'checkbox', 'label' =>'Enable Materials Booking (Sierra Only)', 'description' =>'Check to enable integration of Sierra\'s Materials Booking module.', 'hideInLists' => true, 'default' => 0],
					'allowLinkedAccounts'                  => ['property' =>'allowLinkedAccounts', 'type' =>'checkbox', 'label' =>'Allow Linked Accounts', 'description' => 'Whether or not users can link multiple library cards under a single Pika account.', 'hideInLists' => true, 'default' => 1],
					'showLibraryHoursNoticeOnAccountPages' => ['property' =>'showLibraryHoursNoticeOnAccountPages', 'type' =>'checkbox', 'label' =>'Show Library Holidays and Hours Notice on Account Pages', 'description' =>'Whether or not the Library Hours notice should be shown at the top of My Account\'s Checked Out, Holds and Bookings pages.', 'hideInLists' => true, 'default' =>true],
					'pTypesSection'                        => ['property' => 'pTypesSectionSection', 'type' => 'section', 'label' => 'P-Types (Sierra Only)', 'hideInLists' => true,
					                                           'helpLink' =>'https://marmot-support.atlassian.net/l/c/SaLWEWH7', 'properties' => [
						'pTypes'       => ['property' =>'pTypes', 'type' =>'text', 'label' =>'P-Types', 'description' =>'A list of pTypes that are valid for the library.  Separate multiple pTypes with commas. -1 to disable pType calculations for this library.', 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
						'defaultPType' => ['property' =>'defaultPType', 'type' =>'text', 'label' =>'Default P-Type', 'description' =>'The P-Type to use when accessing a subdomain if the patron is not logged in.'],
						]],
					'barcodeSection' => ['property' => 'barcodeSection', 'type' => 'section', 'label' => 'Barcode', 'hideInLists' => true,
					                     'helpLink' => 'https://marmot-support.atlassian.net/l/c/fqu5BzME', 'properties' => [
							'minBarcodeLength' => ['property' =>'minBarcodeLength', 'type' =>'integer', 'label' =>'Min Barcode Length', 'description' =>'A minimum length the patron barcode is expected to be. Leave as 0 to extra processing of barcodes.', 'hideInLists' => true, 'default' =>0],
							'maxBarcodeLength' => ['property' =>'maxBarcodeLength', 'type' =>'integer', 'label' =>'Max Barcode Length', 'description' =>'The maximum length the patron barcode is expected to be. Leave as 0 to extra processing of barcodes.', 'hideInLists' => true, 'default' =>0],
							'barcodePrefix'    => ['property' =>'barcodePrefix', 'type' =>'text', 'label' =>'Barcode Prefix', 'description' =>'A barcode prefix to apply to the barcode if it does not start with the barcode prefix or if it is not within the expected min/max range.  Multiple prefixes can be specified by separating them with commas. Leave blank to avoid additional processing of barcodes.', 'hideInLists' => true, 'default' =>''],
						]],
					'userProfileSection' => ['property' => 'userProfileSection', 'type' => 'section', 'label' => 'User Profile', 'hideInLists' => true,
					                         'helpLink' =>'https://marmot-support.atlassian.net/l/c/QnBxrnfU', 'properties' => [
							'showPatronBarcodeImage'               => ['property' => 'showPatronBarcodeImage', 'type' =>'checkbox', 'label' =>'Show a scannable barcode image in mobile menu', 'description' =>'', 'hideInLists' => true, 'default' => 0],
							'patronNameDisplayStyle'               => ['property' =>'patronNameDisplayStyle', 'type' =>'enum', 'values' => ['firstinitial_lastname' =>'First Initial. Last Name', 'lastinitial_firstname' =>'First Name Last Initial.'], 'label' =>'Patron Display Name Style', 'description' =>'How to generate the patron display name'],
							'allowProfileUpdates'                  => ['property' =>'allowProfileUpdates', 'type' =>'checkbox', 'label' =>'Allow Profile Updates', 'description' =>'Whether or not the user can update their own profile.', 'hideInLists' => true, 'default' => 1],
							'allowPinReset'           => ['property' =>'allowPinReset', 'type' =>'checkbox', 'label' =>'Allow PIN Update', 'description' =>'Whether or not the user can update their PIN in the Account Settings page.', 'hideInLists' => true, 'default' => 0],
							'allowPatronAddressUpdates'            => ['property' => 'allowPatronAddressUpdates', 'type' =>'checkbox', 'label' =>'Allow Patrons to Update Their Address', 'description' =>'Whether or not patrons should be able to update their own address in their profile.', 'hideInLists' => true, 'default' => 1],
							'showAlternateLibraryOptionsInProfile' => ['property' => 'showAlternateLibraryOptionsInProfile', 'type' =>'checkbox', 'label' =>'Allow Patrons to Update their Alternate Libraries', 'description' =>'Allow Patrons to See and Change Alternate Library Settings in the Catalog Options Tab in their profile.', 'hideInLists' => true, 'default' => 1],
							'showWorkPhoneInProfile'               => ['property' => 'showWorkPhoneInProfile', 'type' =>'checkbox', 'label' =>'Show Work Phone in Profile', 'description' =>'Whether or not patrons should be able to change a secondary/work phone number in their profile.', 'hideInLists' => true, 'default' => 0],
							'treatPrintNoticesAsPhoneNotices'      => ['property' => 'treatPrintNoticesAsPhoneNotices', 'type' => 'checkbox', 'label' => 'Treat Print Notices As Phone Notices', 'description' => 'When showing detailed information about hold notices, treat print notices as if they are phone calls', 'hideInLists' => true, 'default' => 0],
							'showNoticeTypeInProfile'              => ['property' => 'showNoticeTypeInProfile', 'type' =>'checkbox', 'label' =>'Show Notice Type in Profile', 'description' =>'Whether or not patrons should be able to change how they receive notices in their profile.', 'hideInLists' => true, 'default' => 0],
							'showPickupLocationInProfile'          => ['property' => 'showPickupLocationInProfile', 'type' =>'checkbox', 'label' =>'Allow Patrons to Update Their Preferred Pickup Location/Home Branch', 'description' => 'Whether or not patrons should be able to update their preferred pickup location in their profile.', 'hideInLists' => true, 'default' => 0],
							'maxFinesToAllowAccountUpdates'        => ['property' => 'maxFinesToAllowAccountUpdates', 'type' =>'currency', 'displayFormat' =>'%0.2f', 'label' =>'Maximum Fine Amount to Allow Account Updates', 'description' =>'The maximum amount that a patron can owe and still update their account. Any value <= 0 will disable this functionality.', 'hideInLists' => true, 'default' => 10]
						]],
					'holdsSection' => ['property' => 'holdsSection', 'type' => 'section', 'label' => 'Holds', 'hideInLists' => true,
					                   'helpLink' =>'https://marmot-support.atlassian.net/l/c/G3VdRGX5', 'properties' => [
							'showHoldButton'                    => ['property' =>'showHoldButton', 'type' =>'checkbox', 'label' =>'Show Hold Button', 'description' =>'Whether or not the hold button is displayed so patrons can place holds on items', 'hideInLists' => true, 'default' => 1],
							'showHoldButtonInSearchResults'     => ['property' =>'showHoldButtonInSearchResults', 'type' =>'checkbox', 'label' =>'Show Hold Button within the search results', 'description' =>'Whether or not the hold button is displayed within the search results so patrons can place holds on items', 'hideInLists' => true, 'default' => 1],
							'showHoldButtonForUnavailableOnly'  => ['property' =>'showHoldButtonForUnavailableOnly', 'type' =>'checkbox', 'label' =>'Show Hold Button for items that are checked out only', 'description' =>'Whether or not the hold button is displayed within the search results so patrons can place holds on items', 'hideInLists' => true, 'default' => 1],
							'showHoldCancelDate'                => ['property' =>'showHoldCancelDate', 'type' =>'checkbox', 'label' =>'Show Cancellation Date of Unavailable Holds', 'description' =>'Whether or not the patron should be able to set a cancellation date (not needed after date) when placing holds.', 'hideInLists' => true, 'default' => 1],
							'allowFreezeHolds'                  => ['property' =>'allowFreezeHolds', 'type' =>'checkbox', 'label' =>'Allow Freezing Holds', 'description' =>'Whether or not the user can freeze their holds.', 'hideInLists' => true, 'default' => 1],
							'defaultNotNeededAfterDays'         => ['property' =>'defaultNotNeededAfterDays', 'type' =>'integer', 'label' =>'Default Not Needed After Days', 'description' =>'Number of days to use for not needed after date by default. Use -1 for no default.', 'hideInLists' => true, 'min' => -1],
							'showDetailedHoldNoticeInformation' => ['property' => 'showDetailedHoldNoticeInformation', 'type' => 'checkbox', 'label' => 'Show Detailed Hold Notice Information', 'description' => 'Whether or not the user should be presented with detailed hold notification information, i.e. you will receive an e-mail/phone call to xxx when the hold is available', 'hideInLists' => true, 'default' => 1],
							'inSystemPickupsOnly'               => ['property' =>'inSystemPickupsOnly', 'type' =>'checkbox', 'label' =>'In System Pickups Only', 'description' =>'Restrict pickup locations to only locations within this library system.', 'hideInLists' => true,],
							'validPickupSystems'                => ['property' =>'validPickupSystems', 'type' =>'text', 'label' =>'Valid Pickup Library Systems', 'description' =>'Additional Library Systems that can be used as pickup locations if the &quot;In System Pickups Only&quot; is on. List the libraries\' subdomains separated by pipes |', 'size' =>'20', 'hideInLists' => true,],
							'holdDisclaimer'                    => ['property' =>'holdDisclaimer', 'type' =>'textarea', 'label' =>'Hold Disclaimer', 'description' =>'A disclaimer to display to patrons when they are placing a hold on items letting them know that their information may be available to other libraries.  Leave blank to not show a disclaimer.', 'hideInLists' => true,],
						]],
					'loginSection' => ['property' => 'loginSection', 'type' => 'section', 'label' => 'Login', 'hideInLists' => true,
					                   'helpLink' => 'https://marmot-support.atlassian.net/l/c/fqu5BzME', 'properties' => [
															 // todo: [pins] revisit if we go with per library config.
							/*'loginConfiguration'   => ['property' => 'loginConfiguration', 'type' => 'enum', 'label' => 'Login Configuration', 'values' => ['barcode_pin' => 'Barcode and Pin', 'name_barcode' => 'Name and Barcode', 'account_profile_based' => 'Account Profile Based' ], 'description' => 'How to configure the prompts for this authentication profile', 'required' => true, 'hideInLists' => true, 'default' => 'account_profile_based',],*/
							'showLoginButton'         => ['property' =>'showLoginButton', 'type' =>'checkbox', 'label' =>'Show Login Button', 'description' =>'Whether or not the login button is displayed so patrons can log into the site', 'hideInLists' => true, 'default' => 1],

							'preventExpiredCardLogin' => ['property' =>'preventExpiredCardLogin', 'type' =>'checkbox', 'label' =>'Prevent Login for Expired Cards', 'description' =>'Users with expired cards will not be allowed to login. They will recieve an expired card notice instead.', 'hideInLists' => true, 'default' => 0],
							'loginFormUsernameLabel'  => ['property' =>'loginFormUsernameLabel', 'type' =>'text', 'label' =>'Login Form Username Label', 'description' =>'The label to show for the username when logging in', 'size' =>'100', 'hideInLists' => true, 'default' =>'Your Name'],
							'loginFormPasswordLabel'  => ['property' =>'loginFormPasswordLabel', 'type' =>'text', 'label' =>'Login Form Password Label', 'description' =>'The label to show for the password when logging in', 'size' =>'100', 'hideInLists' => true, 'default' =>'Library Card Number'],
						]],
					'selfRegistrationSection' => ['property' => 'selfRegistrationSection', 'type' => 'section', 'label' => 'Self Registration', 'hideInLists' => true,
					                              'helpLink' => 'https://marmot-support.atlassian.net/l/c/80ovqAL5', 'properties' => [
							'externalSelfRegistrationUrl'    => ['property' =>'externalSelfRegistrationUrl', 'type' =>'text', 'label' =>'URL for External Self Registration Page', 'description' =>'Enter the site url when using an external self-registration system', 'hideInLists' => true],
							'enableSelfRegistration'         => ['property' =>'enableSelfRegistration', 'type' =>'checkbox', 'label' =>'Enable Self Registration', 'description' => 'Whether or not patrons can self register on the site', 'hideInLists' => true],
							/* sierra patron api self reg */
							'selfRegistrationAgencyCode'     => ['property' =>'selfRegistrationAgencyCode', 'type' =>'text', 'label' =>'Agency Code (Sierra Only)', 'description' =>'Sierra library agency code.', 'hideInLists' => true, 'default' => '', 'maxLength' => '3'],
							'selfRegistrationDefaultpType'   => ['property' =>'selfRegistrationDefaultpType', 'type' =>'text', 'label' =>'Self Registration Patron Type (Sierra Only)', 'description' =>'The default patron type for self registered patrons.', 'hideInLists' => true, 'default' => ''],
							'selfRegistrationBarcodeLength'  => ['property' =>'selfRegistrationBarcodeLength', 'type' =>'text', 'label' =>'Barcode length (Sierra Only)', 'description' =>'The barcode length of a self registered patron.', 'hideInLists' => true, 'default' => '7', 'maxLength' => '2'],
							'selfRegistrationDaysUntilExpire'=> ['property' =>'selfRegistrationDaysUntilExpire', 'type' =>'text', 'label' =>'Days Until Expiration (Sierra Only)', 'description' =>'The number of days the account will be valid.', 'hideInLists' => true, 'default' => '90', 'maxLength' => '3'],

							/* sierra patron api self reg */
							'promptForBirthDateInSelfReg'    => ['property' => 'promptForBirthDateInSelfReg', 'type' => 'checkbox', 'label' => 'Prompt For Birth Date', 'description' =>'Whether or not to prompt for birth date when self registering'],
							'selfRegistrationFormMessage'    => ['property' =>'selfRegistrationFormMessage', 'type' =>'html', 'label' =>'Self Registration Form Message', 'description' =>'Message shown to users with the form to submit the self registration.  Leave blank to give users the default message.', 'allowableTags' => '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6><script>', 'hideInLists' => true],
							'selfRegistrationSuccessMessage' => ['property' =>'selfRegistrationSuccessMessage', 'type' =>'html', 'label' =>'Self Registration Success Message', 'description' =>'Message shown to users when the self registration has been completed successfully.  Leave blank to give users the default message.', 'allowableTags' => '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6><script>', 'hideInLists' => true],
						]],
					'masqueradeModeSection' => ['property'   => 'masqueradeModeSection', 'type' => 'section', 'label' => 'Masquerade Mode', 'hideInLists' => true,
					                            'helpLink' =>'https://marmot-support.atlassian.net/l/c/Vfk2LzSr', 'properties' => [
						                            'allowMasqueradeMode'                        => ['property' =>'allowMasqueradeMode', 'type' =>'checkbox', 'label' =>'Allow Masquerade Mode', 'description' => 'Whether or not staff users (depending on pType setting) can use Masquerade Mode.', 'hideInLists' => true, 'default' => false],
						                            'masqueradeAutomaticTimeoutLength'           => ['property' =>'masqueradeAutomaticTimeoutLength', 'type' =>'integer', 'label' =>'Masquerade Mode Automatic Timeout Length', 'description' =>'The length of time before an idle user\'s Masquerade session automatically ends in seconds.', 'size' =>'8', 'hideInLists' => true, 'max' => 240],
						                            'allowReadingHistoryDisplayInMasqueradeMode' => ['property' =>'allowReadingHistoryDisplayInMasqueradeMode', 'type' =>'checkbox', 'label' =>'Allow Display of Reading History in Masquerade Mode', 'description' =>'This option allows Guiding Users to view the Reading History of the masqueraded user.', 'hideInLists' => true, 'default' => false],
					                            ]],
				]],

			'ecommerceSection' => ['property' =>'ecommerceSection', 'type' => 'section', 'label' =>'Fines/e-commerce', 'hideInLists' => true,
			                       'helpLink' =>'https://marmot-support.atlassian.net/l/c/Q9FvssiU', 'properties' => [
					'showEcommerceLink'        => ['property' =>'showEcommerceLink', 'type' =>'checkbox', 'label' =>'Show E-Commerce Link', 'description' =>'Whether or not users should be given a link to classic opac to pay fines', 'hideInLists' => true,],
					'payFinesLink'             => ['property' =>'payFinesLink', 'type' =>'text', 'label' =>'Pay Fines Link', 'description' =>'The link to pay fines.  Leave as default to link to classic (should have eCommerce link enabled)', 'hideInLists' => true, 'default' => 'default', 'size' => 80],
					'payFinesLinkText'         => ['property' =>'payFinesLinkText', 'type' =>'text', 'label' =>'Pay Fines Link Text', 'description' =>'The text when linking to pay fines.', 'hideInLists' => true, 'default' => 'Click to Pay Fines Online ', 'size' => 80],
					'minimumFineAmount'        => ['property' =>'minimumFineAmount', 'type' =>'currency', 'displayFormat' =>'%0.2f', 'label' =>'Minimum Fine Amount', 'description' =>'The minimum fine amount to display the e-commerce link', 'hideInLists' => true,],
					'fineAlertAmount'        => ['property' =>'fineAlertAmount', 'type' =>'currency', 'displayFormat' =>'%0.2f', 'label' =>'Fine Alert Amount', 'description' =>'The minimum fine amount to display the account fines warning', 'hideInLists' => true,],
					'showRefreshAccountButton' => ['property' =>'showRefreshAccountButton', 'type' =>'checkbox', 'label' =>'Show Refresh Account Button', 'description' =>'Whether or not a Show Refresh Account button is displayed in a pop-up when a user clicks the E-Commerce Link', 'hideInLists' => true, 'default' => true],
				]],

			// Searching //
			'searchingSection' => ['property' =>'searchingSection', 'type' => 'section', 'label' =>'Searching', 'hideInLists' => true,
			                       'helpLink' =>'https://marmot-support.atlassian.net/l/c/2L9neHjr', 'properties' => [
					'restrictSearchByLibrary'                  => ['property' => 'restrictSearchByLibrary', 'type' =>'checkbox', 'label' =>'Restrict Search By Library', 'description' =>'Whether or not search results should only include titles from this library', 'hideInLists' => true],
					'publicListsToInclude'                     => ['property' => 'publicListsToInclude', 'type' =>'enum', 'values' => [0 => 'No Lists', '1' => 'Lists from this library', '3' =>'Lists from library list publishers Only', '4' =>'Lists from all list publishers', '2' => 'All Lists'], 'label' =>'Public Lists To Include', 'description' =>'Which lists should be included in this scope', 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'boostByLibrary'                           => ['property' => 'boostByLibrary', 'type' =>'checkbox', 'label' =>'Boost By Library', 'description' =>'Whether or not boosting of titles owned by this library should be applied', 'hideInLists' => true],
					'additionalLocalBoostFactor'               => ['property' => 'additionalLocalBoostFactor', 'type' =>'integer', 'label' =>'Additional Local Boost Factor', 'description' =>'An additional numeric boost to apply to any locally owned and locally available titles', 'hideInLists' => true],
					'allowAutomaticSearchReplacements'         => ['property' => 'allowAutomaticSearchReplacements', 'type' =>'checkbox', 'label' =>'Allow Automatic Search Corrections', 'description' =>'Turn on to allow Pika to replace search terms that have no results if the current search term looks like a misspelling.', 'hideInLists' => true, 'default' =>true],
					'applyNumberOfHoldingsBoost'               => ['property' => 'applyNumberOfHoldingsBoost', 'type' =>'checkbox', 'label' =>'Apply Number Of Holdings Boost', 'description' =>'Whether or not the relevance will use boosting by number of holdings in the catalog.', 'hideInLists' => true, 'default' => 1],
					'searchBoxSection' => ['property' => 'searchBoxSection', 'type' => 'section', 'label' => 'Search Box', 'hideInLists' => true, 'properties' => [
						'horizontalSearchBar'                    => ['property' => 'horizontalSearchBar', 'type' =>'checkbox', 'label' => 'Use Horizontal Search Bar', 'description' => 'Instead of the default sidebar search box, a horizontal search bar is shown below the header that spans the screen.', 'hideInLists' => true, 'default' => false],
						'systemsToRepeatIn'                      => ['property' => 'systemsToRepeatIn', 'type' => 'text', 'label' => 'Other Libraries or Locations To Repeat In', 'description' => 'A list of library or location codes that you would like to repeat search in separated by pipes |.', 'size' =>'20', 'hideInLists' => true,],
						//Note $systemsToRepeatIn matches by Location->code of Library->subdomain
						'repeatSearchOption'                     => ['property' => 'repeatSearchOption', 'type' =>'enum', 'label' => 'Repeat Search Options (requires Restrict Search to Library to be ON)', 'description' =>'Where to allow repeating search. Valid options are: none, librarySystem, marmot, all', 'values' => ['none' =>'None', 'librarySystem' =>'Library System', 'marmot' =>'Consortium'],],
						'repeatInOnlineCollection'               => ['property' => 'repeatInOnlineCollection', 'type' =>'checkbox', 'label' => 'Repeat In Online Collection', 'description' =>'Turn on to allow repeat search in the Online Collection.', 'hideInLists' => true, 'default' =>false],
						'showAdvancedSearchbox'                  => ['property' => 'showAdvancedSearchbox', 'type' =>'checkbox', 'label' => 'Show Advanced Search Link', 'description' =>'Whether or not users should see the advanced search link below the search box.', 'hideInLists' => true, 'default' => 1],
					]],
					'searchResultsSection' => ['property' => 'searchResultsSection', 'type' => 'section', 'label' => 'Search Results', 'hideInLists' => true, 'properties' => [
						'showSearchTools'                        => ['property' => 'showSearchTools', 'type' => 'checkbox', 'label' => 'Show Search Tools', 'description' => 'Turn on to activate search tools (save search, export to excel, rss feed, etc).', 'hideInLists' => true],
						'showInSearchResultsMainDetails'         => ['property' => 'showInSearchResultsMainDetails', 'type' => 'multiSelect', 'label' => 'Optional details to show for a record in search results : ', 'description' => 'Selected details will be shown in the main details section of a record on a search results page.', 'listStyle' => 'checkboxSimple', 'values' => self::$searchResultsMainDetailsOptions],
						'alwaysShowSearchResultsMainDetails'     => ['property' => 'alwaysShowSearchResultsMainDetails', 'type' => 'checkbox', 'label' => 'Always Show Selected Search Results Main Details', 'description' => 'Turn on to always show the selected details even when there is no info supplied for a detail, or the detail varies due to multiple formats and/or editions). Does not apply to Series & Language', 'hideInLists' => true],
					]],
					'searchFacetsSection' => ['property' => 'searchFacetsSection', 'type' => 'section', 'label' => 'Search Facets', 'hideInLists' => true, 'properties' => [
						'availabilityToggleLabelSuperScope'           => ['property' => 'availabilityToggleLabelSuperScope', 'type' => 'text', 'label' => 'SuperScope Toggle Label', 'description' => 'The label to show when viewing super scope i.e. Consortium Name / Entire Collection / Everything.  Does not show if superscope is not enabled.', 'default' => 'Entire Collection'],
						'availabilityToggleLabelLocal'                => ['property' => 'availabilityToggleLabelLocal', 'type' => 'text', 'label' => 'Local Collection Toggle Label', 'description' => 'The label to show when viewing the local collection i.e. Library Name / Local Collection.  Leave blank to hide the button.', 'default' => ''],
						'availabilityToggleLabelAvailable'            => ['property' => 'availabilityToggleLabelAvailable', 'type' => 'text', 'label' => 'Available Toggle Label', 'description' => 'The label to show when viewing available items i.e. Available Now / Available Locally / Available Here.', 'default' => 'Available Now'],
						'availabilityToggleLabelAvailableOnline'      => ['property' => 'availabilityToggleLabelAvailableOnline', 'type' => 'text', 'label' => 'Available Online Toggle Label', 'description' => 'The label to show when viewing available items i.e. Available Online.', 'default' => 'Available Online'],
						'includeOnlineMaterialsInAvailableToggle'     => ['property' => 'includeOnlineMaterialsInAvailableToggle', 'type' => 'checkbox', 'label' => 'Include Online Materials in Available Toggle', 'description' =>'Turn on to include online materials in both the Available Now and Available Online Toggles.', 'hideInLists' => true, 'default' => false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
						'facetLabel'                                  => ['property' => 'facetLabel', 'type' => 'text', 'label' => 'Library System Facet Label', 'description' => 'The label for the library system in the Library System Facet.', 'size' =>'40', 'hideInLists' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
						'restrictOwningBranchesAndSystems'            => ['property' => 'restrictOwningBranchesAndSystems', 'type' => 'checkbox', 'label' => 'Restrict Owning Branch and System Facets to this library', 'description' => 'Whether or not the Owning Branch and Owning System Facets will only display values relevant to this library. (local_callnumber & availability facets)', 'hideInLists' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
						'additionalLocationsToShowAvailabilityFor'    => ['property' => 'additionalLocationsToShowAvailabilityFor', 'type' => 'text', 'label' => 'Additional Locations to Include in Available At Facet', 'description' => 'A list of library codes that you would like included in the available at facet separated by pipes |.', 'size' =>'20', 'hideInLists' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
						'includeAllRecordsInShelvingFacets'           => ['property' => 'includeAllRecordsInShelvingFacets', 'type' => 'checkbox', 'label' => 'Include All Records In Shelving Facets', 'description' => 'Turn on to include all records (owned and included) in shelving related facets (detailed location, collection).', 'hideInLists' => true, 'default' =>false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
						'includeAllRecordsInDateAddedFacets'          => ['property' => 'includeAllRecordsInDateAddedFacets', 'type' => 'checkbox', 'label' => 'Include All Records In Date Added Facets', 'description' => 'Turn on to include all records (owned and included) in date added facets.', 'hideInLists' => true, 'default' => false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
						'includeOnOrderRecordsInDateAddedFacetValues' => ['property' => 'includeOnOrderRecordsInDateAddedFacetValues', 'type' => 'checkbox', 'label' => 'Include On Order Records In All Date Added Facet Values', 'description' => 'Use On Order records (date added value (tomorrow)) in calculations for all date added facet values. (eg. Added in the last day, week, etc.)', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],

						'facets' => [
						'property'                   => 'facets',
						'type'                       => 'oneToMany',
						'label'                      => 'Facets',
						'description'                => 'A list of facets to display in search results',
						'helpLink'                   => 'https://marmot-support.atlassian.net/l/c/iACzjA50',
						'keyThis'                    => 'libraryId',
						'keyOther'                   => 'libraryId',
						'subObjectType'              => 'LibraryFacetSetting',
						'structure'                  => $facetSettingStructure,
						'sortable'                   => true,
						'storeDb'                    => true,
						'allowEdit'                  => true,
						'canEdit'                    => true,
						'additionalOneToManyActions' => [
							[
								'text' => 'Copy Library Facets',
								'url'  => '/Admin/Libraries?id=$id&amp;objectAction=copyFacetsFromLibrary',
							],
							[
								'text'          => 'Reset Facets To Default',
								'url'           => '/Admin/Libraries?id=$id&amp;objectAction=resetFacetsToDefault',
								'class'         => 'btn-warning',
								'allowed_roles' => ['opacAdmin', 'libraryAdmin'],
							],
						],
					],
					]
					],

					'combinedResultsSection' => [
					'property'   => 'combinedResultsSection', 'type' => 'section', 'label' => 'Combined Results', 'hideInLists' => true,
					'helpLink'   => 'https://marmot-support.atlassian.net/l/c/tq17UkKT',
					'properties' => [
						'enableCombinedResults'    => ['property' => 'enableCombinedResults', 'type' => 'checkbox', 'label' => 'Enable Combined Results', 'description' => 'Whether or not combined results should be shown ', 'hideInLists' => true, 'default' => false],
						'combinedResultsLabel'     => ['property' => 'combinedResultsLabel', 'type' => 'text', 'label' => 'Combined Results Label', 'description' => 'The label to use in the search source box when combined results is active.', 'size' => '20', 'hideInLists' => true, 'default' => 'Combined Results'],
						'defaultToCombinedResults' => ['property' => 'defaultToCombinedResults', 'type' => 'checkbox', 'label' => 'Default To Combined Results', 'description' => 'Whether or not combined results should be the default search source when active ', 'hideInLists' => true, 'default' => true],
						'combinedResultSections'   => [
							'property'                   => 'combinedResultSections',
							'type'                       => 'oneToMany',
							'label'                      => 'Combined Results Sections',
							'description'                => 'Which sections should be shown in the combined results search display',
							'helpLink'                   => '',
							'keyThis'                    => 'libraryId',
							'keyOther'                   => 'libraryId',
							'subObjectType'              => 'LibraryCombinedResultSection',
							'structure'                  => $combinedResultsStructure,
							'sortable'                   => true,
							'storeDb'                    => true,
							'allowEdit'                  => true,
							'canEdit'                    => false,
							'additionalOneToManyActions' => [],
						],
					]],
				]],

			// Catalog Enrichment //
			'enrichmentSection' => ['property' =>'enrichmentSection', 'type' => 'section', 'label' =>'Catalog Enrichment', 'hideInLists' => true,
			                        'helpLink' => 'https://marmot-support.atlassian.net/l/c/5b3zzY8E', 'properties' => [
					'showStandardReviews'      => ['property' =>'showStandardReviews', 'type' =>'checkbox', 'label' =>'Show Standard Reviews', 'description' =>'Whether or not reviews from Content Cafe/Syndetics are displayed on the full record page.', 'hideInLists' => true, 'default' => 1],
					'showGoodReadsReviews'     => ['property' =>'showGoodReadsReviews', 'type' =>'checkbox', 'label' =>'Show GoodReads Reviews', 'description' =>'Whether or not reviews from GoodReads are displayed on the full record page.', 'hideInLists' => true, 'default' =>true],
					'preferSyndeticsSummary'   => ['property' =>'preferSyndeticsSummary', 'type' =>'checkbox', 'label' =>'Prefer Syndetics/Content Cafe Description', 'description' =>'Whether or not the Description loaded from an enrichment service should be preferred over the Description in the Marc Record.', 'hideInLists' => true, 'default' => 1],
					'showSimilarAuthors'       => ['property' =>'showSimilarAuthors', 'type' =>'checkbox', 'label' =>'Show Similar Authors', 'description' =>'Whether or not Similar Authors from Novelist is shown.', 'default' => 1, 'hideInLists' => true,],
					'showSimilarTitles'        => ['property' =>'showSimilarTitles', 'type' =>'checkbox', 'label' =>'Show Similar Titles', 'description' =>'Whether or not Similar Titles from Novelist is shown.', 'default' => 1, 'hideInLists' => true,],
					'showGoDeeper'             => ['property' =>'showGoDeeper', 'type' =>'checkbox', 'label' =>'Show Content Enrichment (TOC, Excerpts, etc)', 'description' =>'Whether or not additional content enrichment like Table of Contents, Exceprts, etc are shown to the user', 'default' => 1, 'hideInLists' => true,],
					'showRatings'              => ['property' =>'showRatings', 'type' =>'checkbox', 'label' =>'Enable User Ratings', 'description' =>'Whether or not ratings are shown', 'hideInLists' => true, 'default' => 1],
					'showComments'             => ['property' =>'showComments', 'type' =>'checkbox', 'label' =>'Enable User Reviews', 'description' =>'Whether or not user reviews are shown (also disables adding user reviews)', 'hideInLists' => true, 'default' => 1],
					// showComments & hideCommentsWithBadWords moved from full record display to this section. plb 6-30-2015
					'hideCommentsWithBadWords' => ['property' =>'hideCommentsWithBadWords', 'type' =>'checkbox', 'label' =>'Hide Comments with Bad Words', 'description' =>'If checked, any User Lists or User Reviews with bad words are completely removed from the user interface for everyone except the original poster.', 'hideInLists' => true,],
					'showFavorites'            => ['property' =>'showFavorites', 'type' =>'checkbox', 'label' =>'Enable User Lists', 'description' =>'Whether or not users can maintain favorites lists', 'hideInLists' => true, 'default' => 1],
					//TODO database column rename?
					'showWikipediaContent'     => ['property' =>'showWikipediaContent', 'type' =>'checkbox', 'label' =>'Show Wikipedia Content', 'description' =>'Whether or not Wikipedia content should be shown on author page', 'default' =>'1', 'hideInLists' => true,],
				]],

			// Full Record Display //
			'fullRecordSection' => ['property' =>'fullRecordSection', 'type' => 'section', 'label' =>'Full Record Display', 'hideInLists' => true,
			                        'helpLink' =>'https://marmot-support.atlassian.net/l/c/ATDdD2Lh', 'properties' => [
					//'showTextThis'             => ['property' =>'showTextThis', 'type' =>'checkbox', 'label' =>'Show Text This', 'description' =>'Whether or not the Text This link is shown', 'hideInLists' => true, 'default' => 1],
					'showEmailThis'            => ['property' =>'showEmailThis', 'type' =>'checkbox', 'label' =>'Show Email This', 'description' =>'Whether or not the Email This link is shown', 'hideInLists' => true, 'default' => 1],
					'showShareOnExternalSites' => ['property' =>'showShareOnExternalSites', 'type' =>'checkbox', 'label' =>'Show Sharing To External Sites', 'description' =>'Whether or not sharing on external sites (Twitter, Facebook, Pinterest, etc. is shown)', 'hideInLists' => true, 'default' => 1],
					'showTagging'              => ['property' =>'showTagging', 'type' =>'checkbox', 'label' =>'Show Tagging', 'description' =>'Whether or not tags are shown (also disables adding tags)', 'hideInLists' => true, 'default' => 1],
					//'exportOptions'            => ['property' =>'exportOptions', 'type' =>'text', 'label' =>'Export Options', 'description' =>'A list of export options that should be enabled separated by pipes.  Valid values are currently RefWorks and EndNote.', 'size' =>'40', 'hideInLists' => true,],
					'show856LinksAsTab'        => ['property' =>'show856LinksAsTab', 'type' =>'checkbox', 'label' =>'Show 856 Links as Tab', 'description' =>'Whether or not 856 links will be shown in their own tab or on the same tab as holdings.', 'hideInLists' => true, 'default' => 1],
					'showCheckInGrid'          => ['property' =>'showCheckInGrid', 'type' =>'checkbox', 'label' =>'Show Check-in Grid (Sierra Only)', 'description' =>'Whether or not the check-in grid is shown for periodicals.', 'default' => 1, 'hideInLists' => true,],
					'showStaffView'            => ['property' =>'showStaffView', 'type' =>'checkbox', 'label' =>'Show Staff View', 'description' =>'Whether or not the staff view is displayed in full record view.', 'hideInLists' => true, 'default' =>true],
					'showQRCode'               =>      ['property'=>'showQRCode',               'type'=>'checkbox', 'label'=>'Show QR Code',                      'description'=>'Whether or not the catalog should show a QR Code in full record view', 'hideInLists' => true, 'default' => 1],
					'showLCSubjects'           => ['property' =>'showLCSubjects', 'type' =>'checkbox', 'label' =>'Show Library of Congress Subjects', 'description' =>'Whether or not standard (LC) subjects are displayed in full record view.', 'hideInLists' => true, 'default' =>true],
					'showBisacSubjects'        => ['property' =>'showBisacSubjects', 'type' =>'checkbox', 'label' =>'Show Bisac Subjects', 'description' =>'Whether or not Bisac subjects are displayed in full record view.', 'hideInLists' => true, 'default' =>true],
					'showFastAddSubjects'      => ['property' =>'showFastAddSubjects', 'type' =>'checkbox', 'label' =>'Show OCLC Fast Subjects', 'description' =>'Whether or not OCLC Fast Add subjects are displayed in full record view.', 'hideInLists' => true, 'default' =>true],
					'showOtherSubjects'        => ['property' =>'showOtherSubjects', 'type' =>'checkbox', 'label' =>'Show Other Subjects', 'description' =>'Whether or other subjects from the MARC are displayed in full record view.', 'hideInLists' => true, 'default' =>true],

					'showInMainDetails' => ['property'  => 'showInMainDetails', 'type' => 'multiSelect', 'label' =>'Which details to show in the main/top details section : ', 'description' => 'Selected details will be shown in the top/main section of the full record view. Details not selected are moved to the More Details accordion.',
                        'listStyle' => 'checkboxSimple',
                        'values'    => self::$showInMainDetailsOptions,
],
					'moreDetailsOptions' => [
					'property'                   => 'moreDetailsOptions',
					'type'                       => 'oneToMany',
					'label'                      => 'Full Record Options',
					'description'                => 'Record Options for the display of full record',
					'keyThis'                    => 'libraryId',
					'keyOther'                   => 'libraryId',
					'subObjectType'              => 'LibraryMoreDetails',
					'structure'                  => $libraryMoreDetailsStructure,
					'sortable'                   => true,
					'storeDb'                    => true,
					'allowEdit'                  => true,
					'canEdit'                    => false,
					'additionalOneToManyActions' => [
						[
							'text'          => 'Reset More Details To Default',
							'url'           => '/Admin/Libraries?id=$id&amp;objectAction=resetMoreDetailsToDefault',
							'class'         => 'btn-warning',
							'allowed_roles' => ['opacAdmin', 'libraryAdmin']
						],
					],
],
				]],

			'holdingsSummarySection' => [
				'property'   => 'holdingsSummarySection', 'type' => 'section', 'label' => 'Holdings Summary', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/VBdaXS4e',
				'properties' => [
					'showItsHere'                => ['property' => 'showItsHere', 'type' => 'checkbox', 'label' => 'Show It\'s Here', 'description' => 'Whether or not the holdings summary should show It\'s here based on IP and the currently logged in patron\'s location.', 'hideInLists' => true, 'default' => 1],
					'showGroupedHoldCopiesCount' => ['property' => 'showGroupedHoldCopiesCount', 'type' => 'checkbox', 'label' => 'Show Hold and Copy Counts', 'description' => 'Whether or not the hold count and copies counts should be visible for grouped works when summarizing formats.', 'hideInLists' => true, 'default' => 1],
					'showOnOrderCounts'          => ['property' => 'showOnOrderCounts', 'type' => 'checkbox', 'label' => 'Show On Order Counts', 'description' => 'Whether or not counts of Order Items should be shown .', 'hideInLists' => true, 'default' => 1],
				],
			],

			// Browse Category Section //
			'browseCategorySection' => [
				'property'   => 'browseCategorySection', 'type' => 'section', 'label' => 'Browse Categories', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/98rtRQZ2',
				'properties' => [
					'defaultBrowseMode'         => [
						'property' => 'defaultBrowseMode', 'type' => 'enum', 'label' => 'Default Viewing Mode for Browse Categories', 'description' => 'Sets how browse categories will be displayed when users haven\'t chosen themselves.', 'hideInLists' => true,
						'values'   => ['covers' => 'Show Covers Only', 'grid' => 'Show as Grid'], 'default' => 'covers',
					],
					'browseCategoryRatingsMode' => [
						'property'   => 'browseCategoryRatingsMode', 'type' => 'enum', 'label' => 'Ratings Mode for Browse Categories ("covers" browse mode only)', 'description' => 'Sets how ratings will be displayed and how user ratings will be enabled when a user is viewing a browse category in the &#34;covers&#34; browse mode. These settings only apply when User Ratings have been enabled. (These settings will also apply to search results viewed in covers mode.)',
						'values'     => [
							'popup' => 'Show rating stars and enable user rating via pop-up form.',
							'stars' => 'Show rating stars and enable user ratings by clicking the stars.',
							'none'  => 'Do not show rating stars.',
						], 'default' => 'popup',
					],

					// The specific categories displayed in the carousel
					'browseCategories' => [
					'property'      => 'browseCategories',
					'type'          => 'oneToMany',
					'label'         => 'Browse Categories',
					'description'   => 'Browse Categories To Show on the Home Screen',
					'keyThis'       => 'libraryId',
					'keyOther'      => 'libraryId',
					'subObjectType' => 'LibraryBrowseCategory',
					'structure'     => $libraryBrowseCategoryStructure,
					'sortable'      => true,
					'storeDb'       => true,
					'allowEdit'     => false,
					'canEdit'       => false,
					'directLink'    => true,
					],
				]],

			'materialsRequestSection' => [
				'property'   => 'materialsRequestSection', 'type' => 'section', 'label' => 'Materials Request', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/48NvZKn2',
				'properties' => [
					'enableMaterialsRequest'         => ['property' => 'enableMaterialsRequest', 'type' => 'checkbox', 'label' => 'Enable Pika Materials Request System', 'description' => 'Enable Materials Request functionality so patrons can request items not in the catalog.', 'hideInLists' => true,],
					'externalMaterialsRequestUrl'    => ['property' => 'externalMaterialsRequestUrl', 'type' => 'text', 'label' => 'External Materials Request URL', 'description' => 'A link to an external Materials Request System to be used instead of the built in Pika system', 'hideInList' => true],
					'maxRequestsPerYear'             => ['property' => 'maxRequestsPerYear', 'type' => 'integer', 'label' => 'Max Requests Per Year', 'description' => 'The maximum number of requests that a user can make within a year', 'hideInLists' => true, 'default' => 60],
					'maxOpenRequests'                => ['property' => 'maxOpenRequests', 'type' => 'integer', 'label' => 'Max Open Requests', 'description' => 'The maximum number of requests that a user can have open at one time', 'hideInLists' => true, 'default' => 5],
					'newMaterialsRequestSummary'     => ['property' => 'newMaterialsRequestSummary', 'type' => 'html', 'label' => 'New Request Summary', 'description' => 'Text displayed at the top of Materials Request form to give users important information about the request they submit', 'size' => '40', 'maxLength' => '512', 'allowableTags' => '<p><div><span><a><strong><b><em><i><ul><ol><li><br><hr><h1><h2><h3><h4><h5><h6><script>', 'hideInLists' => true],
					'materialsRequestDaysToPreserve' => ['property' => 'materialsRequestDaysToPreserve', 'type' => 'integer', 'label' => 'Delete Closed Requests Older than (days)', 'description' => 'The number of days to preserve closed requests.  Requests will be preserved for a minimum of 366 days.  We suggest preserving for at least 395 days.  Setting to a value of 0 will preserve all requests', 'hideInLists' => true, 'default' => 396],

					'materialsRequestFieldsToDisplay' => [
						'property'      => 'materialsRequestFieldsToDisplay',
						'type'          => 'oneToMany',
						'label'         => 'Fields to display on Manage Materials Request Table',
						'description'   => 'Fields displayed when materials requests are listed for Managing',
						'keyThis'       => 'libraryId',
						'keyOther'      => 'libraryId',
						'subObjectType' => 'MaterialsRequestFieldsToDisplay',
						'structure'     => $manageMaterialsRequestFieldsToDisplayStructure,
						'sortable'      => true,
						'storeDb'       => true,
						'allowEdit'     => false,
						'canEdit'       => false,
					],

					'materialsRequestFormats' => [
						'property'                   => 'materialsRequestFormats',
						'type'                       => 'oneToMany',
						'label'                      => 'Formats of Materials that can be Requested',
						'description'                => 'Determine which material formats are available to patrons for request',
						'keyThis'                    => 'libraryId',
						'keyOther'                   => 'libraryId',
						'subObjectType'              => 'MaterialsRequestFormats',
						'structure'                  => $materialsRequestFormatsStructure,
						'sortable'                   => true,
						'storeDb'                    => true,
						'allowEdit'                  => false,
						'canEdit'                    => false,
						'additionalOneToManyActions' => [
							0 => [
								'text'  => 'Set Materials Request Formats To Default',
								'url'   => '/Admin/Libraries?id=$id&amp;objectAction=defaultMaterialsRequestFormats',
								'class' => 'btn-warning',
							],
						],
					],

					'materialsRequestFormFields' => [
						'property'                   => 'materialsRequestFormFields',
						'type'                       => 'oneToMany',
						'label'                      => 'Materials Request Form Fields',
						'description'                => 'Fields that are displayed in the Materials Request Form',
						'keyThis'                    => 'libraryId',
						'keyOther'                   => 'libraryId',
						'subObjectType'              => 'MaterialsRequestFormFields',
						'structure'                  => $materialsRequestFormFieldsStructure,
						'sortable'                   => true,
						'storeDb'                    => true,
						'allowEdit'                  => false,
						'canEdit'                    => false,
						'additionalOneToManyActions' => [
							[
								'text'  => 'Set Materials Request Form Structure To Default',
								'url'   => '/Admin/Libraries?id=$id&amp;objectAction=defaultMaterialsRequestForm',
								'class' => 'btn-warning',
							],
						],
					],

				],
			],

			'interLibraryLoanSection' => [
				'property'   => 'interLibraryLoanSectionSection', 'type' => 'section', 'label' => 'Interlibrary Loaning', 'hideInLists' => true,
				'helpLink' => 'https://marmot-support.atlassian.net/l/c/5QpmuJnU', 'properties' => [
					'interLibraryLoanName' => ['property' => 'interLibraryLoanName', 'type' => 'text', 'label' => 'Name of Interlibrary Loan Service', 'description' => 'The name to be displayed in the link to the ILL service ', 'hideInLists' => true, 'size' => '80'],
					'interLibraryLoanUrl'  => ['property' => 'interLibraryLoanUrl', 'type' => 'text', 'label' => 'Interlibrary Loan URL', 'description' => 'The link for the ILL Service.', 'hideInLists' => true, 'size' => '80'],

					'prospectorSection' => [
						'property' => 'prospectorSection', 'type' => 'section', 'label' => $innReachEncoreName . ' (III INN-Reach & Encore)', 'hideInLists' => true,
						'helpLink' => 'https://marmot-support.atlassian.net/l/c/R5NFsFXP', 'properties' => [
							'enableProspectorIntegration'        => ['property' => 'enableProspectorIntegration', 'type' => 'checkbox', 'label' => 'Enable ' . $innReachEncoreName . ' Integration', 'description' => 'Whether or not ' . $innReachEncoreName . ' Integrations should be displayed for this library.', 'hideInLists' => true, 'default' => 1],
							'repeatInProspector'                 => ['property' => 'repeatInProspector', 'type' => 'checkbox', 'label' => 'Repeat In ' . $innReachEncoreName, 'description' => 'Turn on to allow repeat search in ' . $innReachEncoreName . ' functionality.', 'hideInLists' => true, 'default' => 1],
							'showProspectorResultsAtEndOfSearch' => ['property' => 'showProspectorResultsAtEndOfSearch', 'type' => 'checkbox', 'label' => 'Show ' . $innReachEncoreName . ' Results At End Of Search', 'description' => 'Whether or not ' . $innReachEncoreName . ' Search Results should be shown at the end of search results.', 'hideInLists' => true, 'default' => 1],
						],
					],
					'worldCatSection'   => [
						'property' => 'worldCatSection', 'type' => 'section', 'label' => 'WorldCat', 'hideInLists' => true,
						'helpLink' => 'https://marmot-support.atlassian.net/l/c/JCxYGQF3', 'properties' => [
							'repeatInWorldCat' => ['property' => 'repeatInWorldCat', 'type' => 'checkbox', 'label' => 'Repeat In WorldCat', 'description' => 'Turn on to allow repeat search in WorldCat functionality.', 'hideInLists' => true,],
							'worldCatUrl'      => ['property' => 'worldCatUrl', 'type' => 'text', 'label' => 'WorldCat URL', 'description' => 'A custom World Cat URL to use while searching.', 'hideInLists' => true, 'size' => '80'],
							'worldCatQt'       => ['property' => 'worldCatQt', 'type' => 'text', 'label' => 'WorldCat QT', 'description' => 'A custom World Cat QT term to use while searching.', 'hideInLists' => true, 'size' => '40'],
						],
					],
				],
			],

			'overdriveSection' => ['property' =>'overdriveSection', 'type' => 'section', 'label' =>'OverDrive', 'hideInLists' => true,
			                       'helpLink' =>'https://marmot-support.atlassian.net/l/c/hA8f0gKg', 'properties' => [
					'enableOverdriveCollection'      => ['property' =>'enableOverdriveCollection', 'type' =>'checkbox', 'label' =>'Enable Overdrive Collection', 'description' =>'Whether or not titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'sharedOverdriveCollection'      => ['property' =>'sharedOverdriveCollection', 'type' =>'enum', 'label' =>'Shared Overdrive Collection', 'description' =>'Which shared Overdrive collection should be included in searches', 'hideInLists' => true, 'values' => $sharedOverdriveCollectionChoices, 'default' => -1, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'includeOverDriveAdult'          => ['property' =>'includeOverDriveAdult', 'type' =>'checkbox', 'label' =>'Include Adult Titles', 'description' =>'Whether or not adult titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'includeOverDriveTeen'           => ['property' =>'includeOverDriveTeen', 'type' =>'checkbox', 'label' =>'Include Teen Titles', 'description' =>'Whether or not teen titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'includeOverDriveKids'           => ['property' =>'includeOverDriveKids', 'type' =>'checkbox', 'label' =>'Include Kids Titles', 'description' =>'Whether or not kids titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'repeatInOverdrive'              => ['property' =>'repeatInOverdrive', 'type' =>'checkbox', 'label' =>'Repeat In Overdrive', 'description' =>'Turn on to allow repeat search in Overdrive functionality.', 'hideInLists' => true, 'default' => 0],
					'overdriveAuthenticationILSName' => ['property' =>'overdriveAuthenticationILSName', 'type' =>'text', 'label' =>'The ILS Name Overdrive uses for user Authentication', 'description' =>'The name of the ILS that OverDrive uses to authenticate users logging into the Overdrive website.', 'size' =>'20', 'hideInLists' => true],
					'overdriveRequirePin'            => ['property' =>'overdriveRequirePin', 'type' =>'checkbox', 'label' =>'Is a Pin Required to log into Overdrive website?', 'description' =>'Turn on if users need a PIN to log into the Overdrive website.', 'hideInLists' => true, 'default' => 0],
					'overdriveAdvantageName'         => ['property' =>'overdriveAdvantageName', 'type' =>'text', 'label' =>'Overdrive Advantage Name', 'description' =>'The name of the OverDrive Advantage account if any.', 'size' =>'80', 'hideInLists' => true,],
					'overdriveAdvantageProductsKey'  => ['property' =>'overdriveAdvantageProductsKey', 'type' =>'text', 'label' =>'Overdrive Advantage Products Key', 'description' =>'The products key for use when building urls to the API from the advantageAccounts call.', 'size' =>'80', 'hideInLists' => false, 'isIndexingSetting' => true, 'changeRequiresReindexing' => true],
					'eContentSupportAddress'         => ['property' => 'eContentSupportAddress', 'type' => 'multiemail', 'label' => 'Overdrive Support Address', 'description' => 'An e-mail address to receive support requests for patrons with eContent problems.', 'size' => '80', 'hideInLists' => true, 'default' => 'pika@marmot.org'],
				]],

			'hooplaSection' => [
				'property'   => 'hooplaSection', 'type' => 'section', 'label' => 'Hoopla', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/1zG1kvpA',
				'properties' => [
					'hooplaLibraryID' => ['property' => 'hooplaLibraryID', 'type' => 'integer', 'label' => 'Hoopla Library ID', 'description' => 'The ID Number Hoopla uses for this library', 'hideInLists' => true],
					'hooplaSettings'  => [
						'property'                   => 'hooplaSettings',
						'type'                       => 'oneToMany',
						'label'                      => 'Hoopla Settings',
						'description'                => 'Configure which Hoopla tiles are in search results',
						'keyThis'                    => 'libraryId',
						'keyOther'                   => 'libraryId',
						'subObjectType'              => 'LibraryHooplaSettings',
						'structure'                  => $hooplaSettingsStructure,
						'sortable'                   => false,
						'storeDb'                    => true,
						'allowEdit'                  => true,
						'canEdit'                    => false,
						'isIndexingSetting'          => true,
						'changeRequiresReindexing'   => true,
						'additionalOneToManyActions' => [
							[
								'text'    => 'Clear Hoopla Settings',
								'onclick' => 'Pika.Admin.clearLibraryHooplaSettings($id)',
								'class'   => 'btn-warning',
							],
						],
					],
				],
			],

			'edsSection' => [
				'property'   => 'edsSection', 'type' => 'section', 'label' => 'EBSCO EDS', 'hideInLists' => true,
				'properties' => [
					'edsApiProfile'    => ['property' => 'edsApiProfile', 'type' => 'text', 'label' => 'EDS API Profile', 'description' => 'The profile to use when connecting to the EBSCO API', 'hideInLists' => true],
					'edsSearchProfile' => ['property' => 'edsSearchProfile', 'type' => 'text', 'label' => 'EDS Search Profile', 'description' => 'The profile to use when linking to EBSCO EDS', 'hideInLists' => true],
					'edsApiUsername'   => ['property' => 'edsApiUsername', 'type' => 'text', 'label' => 'EDS API Username', 'description' => 'The username to use when connecting to the EBSCO API', 'hideInLists' => true],
					'edsApiPassword'   => ['property' => 'edsApiPassword', 'type' => 'text', 'label' => 'EDS API Password', 'description' => 'The password to use when connecting to the EBSCO API', 'hideInLists' => true],
				],
			],

			'casSection' => [
				'property'   => 'casSection', 'type' => 'section', 'label' => 'CAS Single Sign On', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/6KkadHSN',
				'properties' => [
					'casHost'    => ['property' => 'casHost', 'type' => 'text', 'label' => 'CAS Host', 'description' => 'The host to use for CAS authentication', 'hideInLists' => true],
					'casPort'    => ['property' => 'casPort', 'type' => 'integer', 'label' => 'CAS Port', 'description' => 'The port to use for CAS authentication (typically 443)', 'hideInLists' => true],
					'casContext' => ['property' => 'casContext', 'type' => 'text', 'label' => 'CAS Context', 'description' => 'The context to use for CAS', 'hideInLists' => true],
				],
			],

			'archiveSection' => ['property' =>'archiveSection', 'type' => 'section', 'label' =>'Local Content Archive', 'hideInLists' => true, 'helpLink' =>'https://marmot-support.atlassian.net/l/c/RdAMY41Q', 'properties' => [
				'enableArchive'                        => ['property' => 'enableArchive', 'type' => 'checkbox', 'label' => 'Allow Searching the Archive', 'description' => 'Whether or not information from the archive is shown in Pika.', 'hideInLists' => true, 'default' => 0],
				'archiveNamespace'                     => ['property' => 'archiveNamespace', 'type' => 'text', 'label' => 'Archive Namespace', 'description' => 'The namespace of your library in the archive', 'hideInLists' => true, 'maxLength' => 30, 'size' => '30'],
				'archivePid'                           => ['property' => 'archivePid', 'type' => 'text', 'label' => 'Organization PID for Library', 'description' => 'A link to a representation of the library in the archive', 'hideInLists' => true, 'maxLength' => 50, 'size' => '50'],
				'hideAllCollectionsFromOtherLibraries' => ['property' => 'hideAllCollectionsFromOtherLibraries', 'type' => 'checkbox', 'label' => 'Hide Collections from Other Libraries', 'description' => 'Whether or not collections created by other libraries is shown in Pika.', 'hideInLists' => true, 'default' => 0],
				'collectionsToHide'                    => ['property' => 'collectionsToHide', 'type' => 'textarea', 'label' => 'Collections To Hide', 'description' => 'Specific collections to hide.', 'hideInLists' => true],
				'objectsToHide'                        => ['property' => 'objectsToHide', 'type' => 'textarea', 'label' => 'Objects To Hide', 'description' => 'Specific objects to hide.', 'hideInLists' => true],
				'defaultArchiveCollectionBrowseMode'   => [
					'property' => 'defaultArchiveCollectionBrowseMode', 'type' => 'enum', 'label' => 'Default Viewing Mode for Archive Collections (Exhibits)', 'description' => 'Sets how archive collections will be displayed by default when users haven\'t chosen a mode themselves.', 'hideInLists' => true,
					'values'   => ['covers' => 'Show Covers', 'list' => 'Show List'], 'default' => 'covers',
				],

				'archiveMoreDetailsSection' => [
					'property'   => 'archiveMoreDetailsSection', 'type' => 'section', 'label' => 'Archive More Details ', 'hideInLists' => true,
					'helpLink'   => '',
					'properties' => [
						'archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode' => ['property' => 'archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode', 'label' => 'Related Object/Entity Sections Display Mode', 'type' => 'enum', 'values' => self::$archiveMoreDetailsDisplayModeOptions, 'default' => 'tiled', 'description' => 'How related objects and entities will be displayed in the More Details accordion on Archive pages.'],

						'archiveMoreDetailsOptions' => [
							'property'                   => 'archiveMoreDetailsOptions',
							'type'                       => 'oneToMany',
							'label'                      => 'More Details Configuration',
							'description'                => 'Configuration for the display of the More Details accordion for archive object views',
							'keyThis'                    => 'libraryId',
							'keyOther'                   => 'libraryId',
							'subObjectType'              => 'LibraryArchiveMoreDetails',
							'structure'                  => $libraryArchiveMoreDetailsStructure,
							'sortable'                   => true,
							'storeDb'                    => true,
							'allowEdit'                  => true,
							'canEdit'                    => false,
							'additionalOneToManyActions' => [
								[
									'text'  => 'Reset Archive More Details To Default',
									'url'   => '/Admin/Libraries?id=$id&amp;objectAction=resetArchiveMoreDetailsToDefault',
									'class' => 'btn-warning',
								],
							],
						],
					]],

				'archiveRequestSection' => [
					'property'   => 'archiveRequestSection', 'type' => 'section', 'label' => 'Archive Copy Requests ', 'hideInLists' => true,
					'helpLink'   => '',
					'properties' => [

						'allowRequestsForArchiveMaterials' => ['property' => 'allowRequestsForArchiveMaterials', 'type' => 'checkbox', 'label' => 'Allow Requests for Copies of Archive Materials', 'description' => 'Enable to allow requests for copies of your archive materials'],
						'archiveRequestMaterialsHeader'    => ['property' => 'archiveRequestMaterialsHeader', 'type' => 'html', 'label' => 'Archive Request Header Text', 'description' => 'The text to be shown above the form for requests of copies for archive materials'],
						'claimAuthorshipHeader'            => ['property' => 'claimAuthorshipHeader', 'type' => 'html', 'label' => 'Claim Authorship Header Text', 'description' => 'The text to be shown above the form when people try to claim authorship of archive materials'],
						'archiveRequestEmail'              => ['property' => 'archiveRequestEmail', 'type' => 'email', 'label' => 'Email to send archive requests to', 'description' => 'The email address to send requests for archive materials to', 'hideInLists' => true],

						// Archive Form Fields
						'archiveRequestFieldName'           => ['property' =>'archiveRequestFieldName', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 2, 'label' =>'Copy Request Field : Name', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldAddress'        => ['property' =>'archiveRequestFieldAddress', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : Address', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldAddress2'       => ['property' =>'archiveRequestFieldAddress2', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : Address2', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldCity'           => ['property' =>'archiveRequestFieldCity', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : City', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldState'          => ['property' =>'archiveRequestFieldState', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : State', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldZip'            => ['property' =>'archiveRequestFieldZip', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : Zip Code', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldCountry'        => ['property' =>'archiveRequestFieldCountry', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : Country', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldPhone'          => ['property' =>'archiveRequestFieldPhone', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 2, 'label' =>'Copy Request Field : Phone', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldAlternatePhone' => ['property' =>'archiveRequestFieldAlternatePhone', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : Alternate Phone', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldFormat'         => ['property' =>'archiveRequestFieldFormat', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 1, 'label' =>'Copy Request Field : Format', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],
						'archiveRequestFieldPurpose'        => ['property' =>'archiveRequestFieldPurpose', 'type' =>'enum', 'values' => self::$archiveRequestFormFieldOptions, 'default' => 2, 'label' =>'Copy Request Field : Purpose', 'description' =>'Should this field be hidden, or displayed as an optional field or a required field'],

					]
				],

				'exploreMoreBar' => [
					'property'      => 'exploreMoreBar',
					'type'          => 'oneToMany',
					'label'         => 'Archive Explore More Bar Configuration',
					'description'   => 'Control the order of Explore More Sections and if they are open by default',
					'keyThis'       => 'libraryId',
					'keyOther'      => 'libraryId',
					'subObjectType' => 'ArchiveExploreMoreBar',
					'structure'     => $archiveExploreMoreBarStructure,
					'sortable'      => true,
					'storeDb'       => true,
					'allowEdit'     => false,
					'canEdit'       => false,
					'additionalOneToManyActions' => [
						[
							'text'  => 'Set Archive Explore More Options To Default',
							'url'   => '/Admin/Libraries?id=$id&amp;objectAction=defaultArchiveExploreMoreOptions',
							'class' => 'btn-warning',
						]
					]
				],

				'archiveSearchFacets' => [
					'property'                   => 'archiveSearchFacets',
					'type'                       => 'oneToMany',
					'label'                      => 'Archive Search Facets',
					'description'                => 'A list of facets to display in archive search results',
					//						'helpLink'                   => '',
					'keyThis'                    => 'libraryId',
					'keyOther'                   => 'libraryId',
					'subObjectType'              => 'LibraryArchiveSearchFacetSetting',
					'structure'                  => $archiveSearchfacetSettingStructure,
					'sortable'                   => true,
					'storeDb'                    => true,
					'allowEdit'                  => true,
					'canEdit'                    => true,
					'additionalOneToManyActions' => [
						[
							'text' => 'Copy Library Archive Search Facets',
							'url'  => '/Admin/Libraries?id=$id&amp;objectAction=copyArchiveSearchFacetsFromLibrary',
						],
						[
							'text'  => 'Reset Archive Search Facets To Default',
							'url'   => '/Admin/Libraries?id=$id&amp;objectAction=resetArchiveSearchFacetsToDefault',
							'class' => 'btn-warning',
						],
					],
				],
			]],

			'dplaSection' => [
				'property'   => 'dplaSection', 'type' => 'section', 'label' => 'DPLA', 'hideInLists' => true,
				'helpLink'   => 'https://marmot-support.atlassian.net/l/c/n8i0w1NL',
				'properties' => [
					'includeDplaResults' => ['property' => 'includeDplaResults', 'type' => 'checkbox', 'label' => 'Include DPLA content in search results', 'description' => 'Whether or not DPLA data should be included for this library.', 'hideInLists' => true, 'default' => 0],
				],
			],

			'googleAnalyticsSection' => [
				'property'   => 'googleAnalyticsSection', 'type' => 'section', 'label' => 'Google Analytics', 'hideInLists' => true,
				// TODO: Add documentation link.
				//'helpLink'   => '',
				'properties' => [
					'gaTrackingId' => ['property' => 'gaTrackingId', 'type' => 'text', 'label' => 'Tracking ID', 'description' => 'For use with library GA account.', 'hideInLists' => true, 'default' => ''],
				],
			],

			'holidays' => [
				'property'      => 'holidays',
				'type'          => 'oneToMany',
				'label'         => 'Holidays',
				'description'   => 'Holidays',
				'helpLink'      => 'https://marmot-support.atlassian.net/l/c/ufYMUT3r',
				'keyThis'       => 'libraryId',
				'keyOther'      => 'libraryId',
				'subObjectType' => 'Holiday',
				'structure'     => $holidaysStructure,
				'sortable'      => false,
				'storeDb'       => true,
			],

			'libraryLinks' => [
				'property'      => 'libraryLinks',
				'type'          => 'oneToMany',
				'label'         => 'Sidebar Links',
				'description'   => 'Links To Show in the sidebar',
				'helpLink'      => 'https://marmot-support.atlassian.net/l/c/Piw2z11i',
				'keyThis'       => 'libraryId',
				'keyOther'      => 'libraryId',
				'subObjectType' => 'LibraryLink',
				'structure'     => $libraryLinksStructure,
				'sortable'      => true,
				'storeDb'       => true,
				'allowEdit'     => true,
				'canEdit'       => true,
			],

			'libraryTopLinks' => [
				'property'      => 'libraryTopLinks',
				'type'          => 'oneToMany',
				'label'         => 'Header Links',
				'description'   => 'Links To Show in the header',
				'helpLink'      => 'https://marmot-support.atlassian.net/l/c/Piw2z11i',
				'keyThis'       => 'libraryId',
				'keyOther'      => 'libraryId',
				'subObjectType' => 'LibraryTopLinks',
				'structure'     => $libraryTopLinksStructure,
				'sortable'      => true,
				'storeDb'       => true,
				'allowEdit'     => false,
				'canEdit'       => false,
			],

			'recordsOwned' => [
				'property'                 => 'recordsOwned',
				'type'                     => 'oneToMany',
				'label'                    => 'Records Owned',
				'description'              => 'Information about what records are owned by the library',
				'helpLink'                 => 'https://marmot-support.atlassian.net/l/c/nTxVC8zb',
				'keyThis'                  => 'libraryId',
				'keyOther'                 => 'libraryId',
				'subObjectType'            => 'LibraryRecordOwned',
				'structure'                => $libraryRecordOwnedStructure,
				'sortable'                 => true,
				'storeDb'                  => true,
				'allowEdit'                => false,
				'canEdit'                  => false,
				'isIndexingSetting'        => true,
				'changeRequiresReindexing' => true,
			],

			'recordsToInclude' => [
				'property'                 => 'recordsToInclude',
				'type'                     => 'oneToMany',
				'label'                    => 'Records To Include',
				'description'              => 'Information about what records to include in this scope',
				'helpLink'                 => 'https://marmot-support.atlassian.net/l/c/nTxVC8zb',
				'keyThis'                  => 'libraryId',
				'keyOther'                 => 'libraryId',
				'subObjectType'            => 'LibraryRecordToInclude',
				'structure'                => $libraryRecordToIncludeStructure,
				'sortable'                 => true,
				'storeDb'                  => true,
				'allowEdit'                => false,
				'canEdit'                  => false,
				'isIndexingSetting'        => true,
				'changeRequiresReindexing' => true,
			],
		];

		if (UserAccount::userHasRole('libraryManager') && !UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			// restrict permissions for library managers, unless they also have higher permissions of library or opac admin
			$structure['subdomain']['type']   = 'label';
			$structure['displayName']['type'] = 'label';
			unset($structure['showDisplayNameInHeader']);
			unset($structure['displaySection']);
			unset($structure['ilsSection']);
			unset($structure['ecommerceSection']);
			unset($structure['searchingSection']);
			unset($structure['enrichmentSection']);
			unset($structure['fullRecordSection']);
			unset($structure['holdingsSummarySection']);
			unset($structure['materialsRequestSection']);
			unset($structure['prospectorSection']);
			unset($structure['worldCatSection']);
			unset($structure['overdriveSection']);
			unset($structure['archiveSection']);
			unset($structure['edsSection']);
			unset($structure['dplaSection']);
			unset($structure['facets']);
			unset($structure['recordsOwned']);
			unset($structure['recordsToInclude']);
			unset($structure['hooplaSection']);
			unset($structure['casSection']);
			unset($structure['interLibraryLoanSection']);
			unset($structure['googleAnalyticsSection']);
		}
		return $structure;
	}

	static $searchLibrary  = array();

	/**
	 * @param null $searchSource
	 * @return Library|null
	 */
	static function getSearchLibrary($searchSource = null){
		if ($searchSource == null){
			global $searchSource;
		}
		if ($searchSource == 'combinedResults'){
			$searchSource = 'local';
		}
		if (!array_key_exists($searchSource, Library::$searchLibrary)){
			$scopingSetting = $searchSource;
			if ($scopingSetting == null){
				return null;
			}else{
				switch ($scopingSetting){
					case 'local':
					case 'econtent':
					case 'library':
					case 'location':
						Library::$searchLibrary[$searchSource] = Library::getActiveLibrary();
						break;
					case 'marmot':
					case 'unscoped':
						//Get the default library
						$library            = new Library();
						$library->isDefault = true;
						if ($library->find(true)){
							Library::$searchLibrary[$searchSource] = clone($library);
						}else{
							Library::$searchLibrary[$searchSource] = null;
						}
						break;
					default:
						$location = Location::getSearchLocation();
						if (is_null($location)){
							//Check to see if we have a library for the subdomain
							$library            = new Library();
							$library->subdomain = $scopingSetting;
							if ($library->find(true)){
								Library::$searchLibrary[$searchSource] = clone($library);
							}else{
								Library::$searchLibrary[$searchSource] = null;
							}
						}else{
							Library::$searchLibrary[$searchSource] = self::getLibraryForLocation($location->locationId);
						}
						break;
				}
			}
		}
		return Library::$searchLibrary[$searchSource];
	}

	static function getActiveLibrary(){
		global $library;
		//First check to see if we have a library loaded based on subdomain (loaded in index)
		if (isset($library)){
			return $library;
		}
		//If there is only one library, that library is active by default.
		$activeLibrary = new Library();
		$activeLibrary->find();
		if ($activeLibrary->N == 1){
			$activeLibrary->fetch();
			return $activeLibrary;
		}
		//Next check to see if we are in a library.
		/** @var Location $locationSingleton */
		global $locationSingleton;
		$physicalLocation = $locationSingleton->getActiveLocation();
		if (!is_null($physicalLocation)){
			//Load the library based on the home branch for the user
			return self::getLibraryForLocation($physicalLocation->libraryId);
		}
		return null;
	}

	static function getPatronHomeLibrary(User $tmpUser = null){
		//Finally check to see if the user has logged in and if so, use that library
		if ($tmpUser != null){
			return self::getLibraryForLocation($tmpUser->homeLocationId);
		}
		if (UserAccount::isLoggedIn()){
			//Load the library based on the home branch for the user
			return UserAccount::getUserHomeLibrary();
		}else{
			return null;
		}
	}

	static function getLibraryForLocation($locationId){
		if (!empty($locationId)){
			$libLookup = new Library();
			$libLookup->whereAdd('libraryId = (SELECT libraryId FROM location WHERE locationId = ' . $libLookup->escape($locationId) . ')');
			// Typical Join operation will overwrite library values with location ones when the column name is the same. eg displayName
			$libLookup->find();
			if ($libLookup->N > 0){
				$libLookup->fetch();
				return clone $libLookup;
			}
		}
		return null;
	}

	private $data = array();

	public function __get($name){
		switch ($name){
			case "holidays":
				if (!isset($this->holidays)){
					$this->holidays = $this->getOneToManyOptions('Holiday', 'date');
				}
				return $this->holidays;
			case "moreDetailsOptions":
				if (!isset($this->moreDetailsOptions)){
					$this->moreDetailsOptions = $this->getOneToManyOptions('LibraryMoreDetails', 'weight');
				}
				return $this->moreDetailsOptions;
			case "archiveMoreDetailsOptions":
				if (!isset($this->archiveMoreDetailsOptions)){
					$this->archiveMoreDetailsOptions = $this->getOneToManyOptions('LibraryArchiveMoreDetails', 'weight');
				}
				return $this->archiveMoreDetailsOptions;
			case "facets":
				if (!isset($this->facets) && $this->libraryId){
					$this->facets = $this->getOneToManyOptions('LibraryFacetSetting', 'weight');
				}
				return $this->facets;
			case "archiveSearchFacets":
				if (!isset($this->archiveSearchFacets)){
					$this->archiveSearchFacets = $this->getOneToManyOptions('LibraryArchiveSearchFacetSetting', 'weight');
				}
				return $this->archiveSearchFacets;
			case 'libraryLinks':
				if (!isset($this->libraryLinks)){
					$libraryLinks = $this->getOneToManyOptions('LibraryLink', 'weight');
					// handle missing linkText
					foreach ($libraryLinks as $libLink){
						if (!isset($libLink->linkText) || $libLink->linkText == ''){
							$libLink->linkText = 'link-' . $libLink->id;
						}
					}
					$this->libraryLinks = $libraryLinks;
				}
				return $this->libraryLinks;
			case 'libraryTopLinks':
				if (!isset($this->libraryTopLinks)){
					$this->libraryTopLinks = $this->getOneToManyOptions('LibraryTopLinks', 'weight');
				}
				return $this->libraryTopLinks;
			case 'recordsOwned':
				if (!isset($this->recordsOwned)){
					$this->recordsOwned = $this->getOneToManyOptions('LibraryRecordOwned');
				}
				return $this->recordsOwned;
			case 'recordsToInclude':
				if (!isset($this->recordsToInclude)){
					$this->recordsToInclude = $this->getOneToManyOptions('LibraryRecordToInclude', 'weight');
				}
				return $this->recordsToInclude;
			case 'browseCategories':
				if (!isset($this->browseCategories)){
					$this->browseCategories = $this->getOneToManyOptions('LibraryBrowseCategory', 'weight');
				}
				return $this->browseCategories;
			case 'materialsRequestFieldsToDisplay':
				if (!isset($this->materialsRequestFieldsToDisplay)){
					$this->materialsRequestFieldsToDisplay = $this->getOneToManyOptions('MaterialsRequestFieldsToDisplay', 'weight');
				}
				return $this->materialsRequestFieldsToDisplay;
			case 'materialsRequestFormats':
				if (!isset($this->materialsRequestFormats)){
					$this->materialsRequestFormats = $this->getOneToManyOptions('MaterialsRequestFormats', 'weight');
				}
				return $this->materialsRequestFormats;
			case 'materialsRequestFormFields':
				if (!isset($this->materialsRequestFormFields)){
					$this->materialsRequestFormFields = $this->getOneToManyOptions('MaterialsRequestFormFields', 'weight');
				}
				return $this->materialsRequestFormFields;
			case 'exploreMoreBar':
				if (!isset($this->exploreMoreBar)){
					$this->exploreMoreBar = $this->getOneToManyOptions('ArchiveExploreMoreBar', 'weight');
				}
				return $this->exploreMoreBar;
			case 'combinedResultSections':
				if (!isset($this->combinedResultSections)){
					$this->combinedResultSections = $this->getOneToManyOptions('LibraryCombinedResultSection', 'weight');
				}
				return $this->combinedResultSections;
			case 'hooplaSettings':
				if (!isset($this->hooplaSettings)){
					$this->hooplaSettings = $this->getOneToManyOptions('LibraryHooplaSettings');
				}
				return $this->hooplaSettings;
			case 'patronNameDisplayStyle':
				return $this->patronNameDisplayStyle;
			default:
				return $this->data[$name];
		}
	}

	public function __set($name, $value){
		if ($name == "holidays") {
			$this->holidays = $value;
		}elseif ($name == "moreDetailsOptions") {
			$this->moreDetailsOptions = $value;
		}elseif ($name == "archiveMoreDetailsOptions") {
			$this->archiveMoreDetailsOptions = $value;
		}elseif ($name == "facets") {
			$this->facets = $value;
		}elseif ($name == "archiveSearchFacets") {
			$this->archiveSearchFacets = $value;
		}elseif ($name == 'libraryLinks'){
			$this->libraryLinks = $value;
		}elseif ($name == 'recordsOwned'){
			$this->recordsOwned = $value;
		}elseif ($name == 'recordsToInclude'){
			$this->recordsToInclude = $value;
		}elseif ($name == 'libraryTopLinks'){
			$this->libraryTopLinks = $value;
		}elseif ($name == 'browseCategories') {
			$this->browseCategories = $value;
		}elseif ($name == 'materialsRequestFieldsToDisplay') {
			$this->materialsRequestFieldsToDisplay = $value;
		}elseif ($name == 'materialsRequestFormats') {
			$this->materialsRequestFormats = $value;
		}elseif ($name == 'materialsRequestFormFields') {
			$this->materialsRequestFormFields = $value;
		}elseif ($name == 'exploreMoreBar') {
			$this->exploreMoreBar = $value;
		}elseif ($name == 'combinedResultSections') {
			$this->combinedResultSections = $value;
		}elseif ($name == 'hooplaSettings') {
			$this->hooplaSettings = $value;
		}elseif ($name == 'patronNameDisplayStyle'){
			if ($this->patronNameDisplayStyle != $value){
				$this->patronNameDisplayStyle = $value;
				$this->patronNameDisplayStyleChanged = true;
			}
		}else{
			$this->data[$name] = $value;
		}
	}
	/**
	 * Override the fetch functionality to fetch related objects
	 *
	 * @see DB/DB_DataObject::fetch()
	 */
	public function fetch(){
		$return = parent::fetch();
		if ($return){
			if (!empty($this->showInMainDetails) && is_string($this->showInMainDetails)){
				// convert to array retrieving from database
				try {
					$this->showInMainDetails = unserialize($this->showInMainDetails);
					if (!$this->showInMainDetails){
						$this->showInMainDetails = array();
					}
				} catch (Exception $e){
					global $logger;
					$logger->log("Error loading $this->libraryId $e", PEAR_LOG_DEBUG);
				}

			}elseif (empty($this->showInMainDetails)){
				// when a value is not set, assume set to show all options, eg null = all
				$default = self::$showInMainDetailsOptions;
				// remove options below that aren't to be part of the default
				unset($default['showISBNs']);
				$default                 = array_keys($default);
				$this->showInMainDetails = $default;
			}
			if (isset($this->showInSearchResultsMainDetails) && is_string($this->showInSearchResultsMainDetails) && !empty($this->showInSearchResultsMainDetails)){
				// convert to array retrieving from database
				$this->showInSearchResultsMainDetails = unserialize($this->showInSearchResultsMainDetails);
				if (!$this->showInSearchResultsMainDetails){
					$this->showInSearchResultsMainDetails = array();
				}
			}
		}
		return $return;
	}

	/**
	 * Override the update functionality to save related objects
	 *
	 * @param bool $dataObject
	 * @return bool|int
	 * @see DB/DB_DataObject::update()
	 */
	public function update($dataObject = false){
		$this->showTextThis = "null";
		if((isset($this->selfRegistrationDefaultpType) && (int)$this->selfRegistrationDefaultpType < 0) || empty($this->selfRegistrationDefaultpType) ) {
			$this->selfRegistrationDefaultpType = "null";
		}
		if(empty($this->selfRegistrationAgencyCode)) {
			$this->selfRegistrationAgencyCode = "null";
		}
		if (isset($this->showInMainDetails) && is_array($this->showInMainDetails)){
			// convert array to string before storing in database
			$this->showInMainDetails = serialize($this->showInMainDetails);
		}
		if (isset($this->showInSearchResultsMainDetails) && is_array($this->showInSearchResultsMainDetails)){
			// convert array to string before storing in database
			$this->showInSearchResultsMainDetails = serialize($this->showInSearchResultsMainDetails);
		}
		$ret = parent::update();
		if ($ret !== false){
			$this->saveHolidays();
			$this->saveFacets();
			$this->saveArchiveSearchFacets();
			$this->saveRecordsOwned();
			$this->saveRecordsToInclude();
			$this->saveManagematerialsRequestFieldsToDisplay();
			$this->saveMaterialsRequestFormFields();
			$this->saveLibraryLinks();
			$this->saveLibraryTopLinks();
			$this->saveBrowseCategories();
			$this->saveMoreDetailsOptions();
			$this->saveArchiveMoreDetailsOptions();

			$this->saveExploreMoreBar();
			$this->saveCombinedResultSections();
			$this->saveHooplaSettings();
		}
		if ($this->patronNameDisplayStyleChanged){
			$libraryLocations            = new Location();
			$libraryLocations->libraryId = $this->libraryId;
			$libraryLocations->find();
			while ($libraryLocations->fetch()){
				$user       = new User();
				$numChanges = $user->query("UPDATE user SET displayName = '' WHERE homeLocationId = {$libraryLocations->locationId}");

			}
		}
		// Do this last so that everything else can update even if we get an error here
		$deleteCheck = $this->saveMaterialsRequestFormats();
		if (PEAR::isError($deleteCheck)){
			$ret = false;
		};

		return $ret;
	}

	/**
	 * Override the insert functionality to save the related objects
	 *
	 * @see DB/DB_DataObject::insert()
	 */
	public function insert(){
		$this->showTextThis = "null";
		if((isset($this->selfRegistrationDefaultpType) && (int)$this->selfRegistrationDefaultpType < 0) || empty($this->selfRegistrationDefaultpType) ) {
			$this->selfRegistrationDefaultpType = "null";
		}
		if((isset($this->selfRegistrationAgencyCode) && (int)$this->selfRegistrationAgencyCode < 1) || empty($this->selfRegistrationAgencyCode)) {
			$this->selfRegistrationAgencyCode = "null";
		}
		if (isset($this->showInMainDetails) && is_array($this->showInMainDetails)){
			// convert array to string before storing in database
			$this->showInMainDetails = serialize($this->showInMainDetails);
		}
		if (isset($this->showInSearchResultsMainDetails) && is_array($this->showInSearchResultsMainDetails)){
			// convert array to string before storing in database
			$this->showInSearchResultsMainDetails = serialize($this->showInSearchResultsMainDetails);
		}
		$ret = parent::insert();
		if ($ret !== false){
			$this->saveHolidays();
			$this->saveFacets();
			$this->saveArchiveSearchFacets();
			$this->saveRecordsOwned();
			$this->saveRecordsToInclude();
			$this->saveManagematerialsRequestFieldsToDisplay();
			$this->saveMaterialsRequestFormats();
			$this->saveMaterialsRequestFormFields();
			$this->saveLibraryLinks();
			$this->saveLibraryTopLinks();
			$this->saveBrowseCategories();
			$this->saveMoreDetailsOptions();
			$this->saveExploreMoreBar();
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
		$this->clearOneToManyOptions('LibraryBrowseCategory');
		$this->browseCategories = array();
	}

	public function saveLibraryLinks(){
		if (isset ($this->libraryLinks) && is_array($this->libraryLinks)){
			$this->saveOneToManyOptions($this->libraryLinks);
			unset($this->libraryLinks);
		}
	}

	public function clearLibraryLinks(){
		$this->clearOneToManyOptions('LibraryLink');
		$this->libraryLinks = array();
	}

	public function saveLibraryTopLinks(){
		if (isset ($this->libraryTopLinks) && is_array($this->libraryTopLinks)){
			$this->saveOneToManyOptions($this->libraryTopLinks);
			unset($this->libraryTopLinks);
		}
	}

	public function clearLibraryTopLinks(){
		$this->clearOneToManyOptions('LibraryTopLinks');
		$this->libraryTopLinks = array();
	}

	public function saveRecordsOwned(){
		if (isset ($this->recordsOwned) && is_array($this->recordsOwned)){
			$this->saveOneToManyOptions($this->recordsOwned);
			unset($this->recordsOwned);
		}
	}

	public function clearRecordsOwned(){
		$this->clearOneToManyOptions('LibraryRecordOwned');
		$this->recordsOwned = array();
	}

	public function saveRecordsToInclude(){
		if (isset ($this->recordsToInclude) && is_array($this->recordsToInclude)){
			$this->saveOneToManyOptions($this->recordsToInclude);
			unset($this->recordsToInclude);
		}
	}

	public function clearRecordsToInclude(){
		$this->clearOneToManyOptions('LibraryRecordToInclude');
//		$object = new LibraryRecordToInclude();
//		$object->libraryId = $this->libraryId;
//		$object->delete();
		$this->recordsToInclude = array();
	}

	public function saveManagematerialsRequestFieldsToDisplay(){
		if (isset ($this->materialsRequestFieldsToDisplay) && is_array($this->materialsRequestFieldsToDisplay)){
			$this->saveOneToManyOptions($this->materialsRequestFieldsToDisplay);
			unset($this->materialsRequestFieldsToDisplay);
		}
	}

	public function saveMaterialsRequestFormats(){
		if (isset ($this->materialsRequestFormats) && is_array($this->materialsRequestFormats)){
			/** @var MaterialsRequestFormats $object */
			foreach ($this->materialsRequestFormats as $object){
				if (isset($object->deleteOnSave) && $object->deleteOnSave == true){
					$deleteCheck = $object->delete();
					if (!$deleteCheck){
						$errorString = 'Materials Request(s) are present for the format "' . $object->format . '".';
						$error       = $this->raiseError($errorString, PEAR_LOG_ERR);
						$error->addUserInfo($errorString);
						return $error;
					}
				}else{
					if (isset($object->id) && is_numeric($object->id)){ // (negative ids need processed with insert)
						$object->update();
					}else{
						$object->libraryId = $this->libraryId;
						$object->insert();
					}
				}
			}
			unset($this->materialsRequestFormats);
		}
	}

	public function saveMaterialsRequestFormFields(){
		if (isset ($this->materialsRequestFormFields) && is_array($this->materialsRequestFormFields)){
			$this->saveOneToManyOptions($this->materialsRequestFormFields);
			unset($this->materialsRequestFormFields);
		}
	}

	private function saveExploreMoreBar(){
		if (isset ($this->exploreMoreBar) && is_array($this->exploreMoreBar)){
			$this->saveOneToManyOptions($this->exploreMoreBar);
			unset($this->exploreMoreBar);
		}
	}

	public function clearExploreMoreBar(){
		$this->clearOneToManyOptions('ArchiveExploreMoreBar');
		$this->exploreMoreBar = array();
	}

	public function saveMoreDetailsOptions(){
		if (isset ($this->moreDetailsOptions) && is_array($this->moreDetailsOptions)){
			$this->saveOneToManyOptions($this->moreDetailsOptions);
			unset($this->moreDetailsOptions);
		}
	}

	public function saveArchiveMoreDetailsOptions(){
		if (isset ($this->archiveMoreDetailsOptions) && is_array($this->archiveMoreDetailsOptions)){
			$this->saveOneToManyOptions($this->archiveMoreDetailsOptions);
			unset($this->archiveMoreDetailsOptions);
		}
	}

	public function clearMoreDetailsOptions(){
		$this->clearOneToManyOptions('LibraryMoreDetails');
		$this->moreDetailsOptions = array();
	}

	public function clearArchiveMoreDetailsOptions(){
		$this->clearOneToManyOptions('LibraryArchiveMoreDetails');
		$this->archiveMoreDetailsOptions = array();
	}

	public function clearMaterialsRequestFormFields(){
		$this->clearOneToManyOptions('MaterialsRequestFormFields');
		$this->materialsRequestFormFields = array();
	}

	public function clearMaterialsRequestFormats(){
		$this->clearOneToManyOptions('MaterialsRequestFormats');
		$this->materialsRequestFormats = array();
	}

	public function saveFacets(){
		if (isset ($this->facets) && is_array($this->facets)){
			$this->saveOneToManyOptions($this->facets);
			unset($this->facets);
		}
	}

	public function saveArchiveSearchFacets(){
		if (isset ($this->archiveSearchFacets) && is_array($this->archiveSearchFacets)){
			$this->saveOneToManyOptions($this->archiveSearchFacets);
			unset($this->archiveSearchFacets);
		}
	}

	public function clearFacets(){
		$this->clearOneToManyOptions('LibraryFacetSetting');
		$this->facets = array();
	}

	public function clearArchiveSearchFacets(){
		$this->clearOneToManyOptions('LibraryArchiveSearchFacetSetting');
		$this->archiveSearchfacets = array();
	}

	public function saveCombinedResultSections(){
		if (isset ($this->combinedResultSections) && is_array($this->combinedResultSections)){
			$this->saveOneToManyOptions($this->combinedResultSections);
			unset($this->combinedResultSections);
		}
	}

	public function clearCombinedResultSections(){
		$this->clearOneToManyOptions('LibraryCombinedResultSection');
		$this->combinedResultSections = array();
	}

	public function saveHooplaSettings(){
		if (isset ($this->hooplaSettings) && is_array($this->hooplaSettings)){
			$this->saveOneToManyOptions($this->hooplaSettings);
			unset($this->hooplaSettings);
		}
	}

	/**
	 * Delete any Hoopla settings there are for this library
	 * @return bool  Whether or not the deletion was successful
	 */
	public function clearHooplaSettings(){
		$success = $this->clearOneToManyOptions('LibraryHooplaSettings');
		$this->hooplaSettings = array();
		return $success >= 1;
	}

	public function saveHolidays(){
		if (isset ($this->holidays) && is_array($this->holidays)){
			$this->saveOneToManyOptions($this->holidays);
			unset($this->holidays);
		}
	}

	static function getDefaultFacets($libraryId = -1){
		global $configArray;
		$defaultFacets = array();

		$facet = new LibraryFacetSetting();
		$facet->setupTopFacet('format_category', 'Format Category');
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;


		if ($configArray['Index']['enableDetailedAvailability']){
			$facet = new LibraryFacetSetting();
			$facet->setupTopFacet('availability_toggle', 'Available?', true);
			$facet->libraryId = $libraryId;
			$facet->weight    = count($defaultFacets) + 1;
			$defaultFacets[]  = $facet;
		}


		if (!$configArray['Index']['enableDetailedAvailability']){
			$facet = new LibraryFacetSetting();
			$facet->setupSideFacet('available_at', 'Available Now At', true);
			$facet->libraryId = $libraryId;
			$facet->weight    = count($defaultFacets) + 1;
			$defaultFacets[]  = $facet;
		}

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('format', 'Format', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('literary_form_full', 'Literary Form', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('target_audience_full', 'Reading Level', true);
		$facet->libraryId                 = $libraryId;
		$facet->weight                    = count($defaultFacets) + 1;
		$facet->numEntriesToShowByDefault = 8;
		$defaultFacets[]                  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('topic_facet', 'Subject', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('time_since_added', 'Added in the Last', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('authorStr', 'Author', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('awards_facet', 'Awards', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('econtent_device', 'Compatible Device', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('econtent_source', 'eContent Source', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('era', 'Era', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('genre_facet', 'Genre', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('itype', 'Item Type', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('language', 'Language', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('lexile_code', 'Lexile code', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('lexile_score', 'Lexile measure', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('mpaa_rating', 'Movie Rating', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('owning_library', 'Owning System', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('owning_location', 'Owning Branch', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('publishDate', 'Publication Date', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('geographic_facet', 'Region', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('rating_facet', 'User Rating', true);
		$facet->libraryId = $libraryId;
		$facet->weight    = count($defaultFacets) + 1;
		$defaultFacets[]  = $facet;

		return $defaultFacets;
	}

	static function getDefaultArchiveSearchFacets($libraryId = -1){
		$defaultFacets     = array();
		$defaultFacetsList = LibraryArchiveSearchFacetSetting::$defaultFacetList;
		foreach ($defaultFacetsList as $facetName => $facetDisplayName){
			$facet = new LibraryArchiveSearchFacetSetting();
			$facet->setupSideFacet($facetName, $facetDisplayName, false);
			$facet->libraryId         = $libraryId;
			$facet->collapseByDefault = true;
			$facet->weight            = count($defaultFacets) + 1;
			$defaultFacets[]          = $facet;
		}

		return $defaultFacets;
	}

	/**
	 * Return the number of locations that belong to the library
	 * @return int
	 */
	public function getNumLocationsForLibrary(){
		$location            = new Location;
		$location->libraryId = $this->libraryId;
		return $location->count();
	}

	/**
	 * get the LocationIds for each location belonging to the library
	 * @return string[]
	 */
	public function getLocationIdsForLibrary(){
		$location            = new Location;
		$location->libraryId = $this->libraryId;
		return $location->fetchAll('locationId');
	}

	public function getArchiveRequestFormStructure(){
		$defaultForm = ArchiveRequest::getObjectStructure();
		foreach ($defaultForm as $index => &$formfield){
			$libraryPropertyName = 'archiveRequestField' . ucfirst($formfield['property']);
			if (isset($this->$libraryPropertyName)){
				$setting = is_null($this->$libraryPropertyName) ? $formfield['default'] : $this->$libraryPropertyName;
				switch ($setting){
					case 0:
						//unset field
						unset($defaultForm[$index]);
						break;
					case 1:
						// set field as optional
						$formfield['required'] = false;
						break;
					case 2:
						// set field as required
						$formfield['required'] = true;
						break;
				}

			}
		}
		return $defaultForm;
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
