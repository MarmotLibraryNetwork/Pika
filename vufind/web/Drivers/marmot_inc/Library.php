<?php
/**
 * Table Definition for library
 */
require_once 'DB/DataObject.php';
require_once 'DB/DataObject/Cast.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/Holiday.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LibraryFacetSetting.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LibraryArchiveSearchFacetSetting.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LibraryCombinedResultSection.php';
require_once ROOT_DIR . '/sys/Indexing/LibraryRecordOwned.php';
require_once ROOT_DIR . '/sys/Indexing/LibraryRecordToInclude.php';
require_once ROOT_DIR . '/sys/Browse/LibraryBrowseCategory.php';
require_once ROOT_DIR . '/sys/LibraryMoreDetails.php';
require_once ROOT_DIR . '/sys/LibraryArchiveMoreDetails.php';
require_once ROOT_DIR . '/sys/LibraryLink.php';
require_once ROOT_DIR . '/sys/LibraryTopLinks.php';
require_once ROOT_DIR . '/sys/MaterialsRequestFieldsToDisplay.php';
require_once ROOT_DIR . '/sys/MaterialsRequestFormats.php';
require_once ROOT_DIR . '/sys/MaterialsRequestFormFields.php';

class Library extends DB_DataObject
{
	public $__table = 'library';    // table name
	public $isDefault;
	public $libraryId; 				//int(11)
	public $subdomain; 				//varchar(15)
	public $displayName; 			//varchar(50)
	public $showDisplayNameInHeader;
	public $headerText;
	public $abbreviatedDisplayName;
	public $systemMessage;
	public $ilsCode;
	public $themeName; 				//varchar(15)
	public $restrictSearchByLibrary;
	public $includeOutOfSystemExternalLinks;
	public $allowProfileUpdates;   //tinyint(4)
	public $allowFreezeHolds;   //tinyint(4)
	public $scope; 					//smallint(6)
	public $useScope;		 		//tinyint(4)
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
	public $showTableOfContentsTab;
	public $inSystemPickupsOnly;
	public $validPickupSystems;
	public $pTypes;
	public $defaultPType;
	public $facetLabel;
	public $showAvailableAtAnyLocation;
	public $showEcommerceLink;
	public $payFinesLink;
	public $payFinesLinkText;
	public $minimumFineAmount;
	public $showRefreshAccountButton;    // specifically to refresh account after paying fines online
	public $goldRushCode;
	public $repeatSearchOption;
	public $repeatInOnlineCollection;
	public $repeatInProspector;
	public $repeatInWorldCat;

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

	public $hooplaLibraryID;
	public $hooplaMaxPrice;
	public $systemsToRepeatIn;
	public $additionalLocationsToShowAvailabilityFor;
	public $homeLink;
	public $homeLinkText;
	public $useHomeLinkInBreadcrumbs;
	public $useHomeLinkForLogo;
	public $showAdvancedSearchbox;
	public $enablePospectorIntegration;
	public $showProspectorResultsAtEndOfSearch;
	public $prospectorCode;
	public $enableGenealogy;
	public $showHoldCancelDate;
	public $enableCourseReserves;
	public $enableSelfRegistration;
	public $promptForBirthDateInSelfReg;
	public $showItsHere;
	public $holdDisclaimer;
	public $enableMaterialsRequest;
	public $externalMaterialsRequestUrl;
	public $eContentLinkRules;
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
	public $eContentSupportAddress;
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
	public $econtentLocationsToInclude;
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
	public $selfRegistrationFormMessage;
	public $selfRegistrationSuccessMessage;
	public $selfRegistrationTemplate;
	public $addSMSIndicatorToPhone;
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

	function keys() {
		return array('libraryId', 'subdomain');
	}

	static function getObjectStructure(){
		// get the structure for the library system's holidays
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

		$libraryMoreDetailsStructure = LibraryMoreDetails::getObjectStructure();
		unset($libraryMoreDetailsStructure['weight']);
		unset($libraryMoreDetailsStructure['libraryId']);

		$libraryArchiveMoreDetailsStructure = LibraryArchiveMoreDetails::getObjectStructure();
		unset($libraryArchiveMoreDetailsStructure['weight']);
		unset($libraryArchiveMoreDetailsStructure['libraryId']);

		$libraryLinksStructure = LibraryLink::getObjectStructure();
		unset($libraryLinksStructure['weight']);
		unset($libraryLinksStructure['libraryId']);

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

		$archiveExploreMoreBarStructure = ArchiveExploreMoreBar::getObjectStructure();
		unset($materialsRequestFormatsStructure['libraryId']); //needed?
		unset($materialsRequestFormatsStructure['weight']);

		$materialsRequestFormFieldsStructure = MaterialsRequestFormFields::getObjectStructure();
		unset($materialsRequestFormFieldsStructure['libraryId']); //needed?
		unset($materialsRequestFormFieldsStructure['weight']);

		$combinedResultsStructure = LibraryCombinedResultSection::getObjectStructure();
		unset($combinedResultsStructure['libraryId']);
		unset($combinedResultsStructure['weight']);

		require_once ROOT_DIR . '/sys/ListWidget.php';
		$widget = new ListWidget();
		if ((UserAccount::userHasRole('libraryAdmin') || UserAccount::userHasRole('contentEditor')) && !UserAccount::userHasRole('opacAdmin') || UserAccount::userHasRole('libraryManager') || UserAccount::userHasRole('locationManager')){
			$patronLibrary = Library::getPatronHomeLibrary();
			if ($patronLibrary){
				$widget->libraryId = $patronLibrary->libraryId;
			}
		}
		$availableWidgets = array();
		$widget->orderBy('name');
		$widget->find();
		$availableWidgets[0] = 'No Widget';
		while ($widget->fetch()){
			$availableWidgets[$widget->id] = $widget->name;
		}

		$sharedOverdriveCollectionChoices = array();
		global $configArray;
		if (!empty($configArray['OverDrive']['accountId'])) {
			$overdriveAccounts = explode(',', $configArray['OverDrive']['accountId']);
			$sharedCollectionIdNum = -1; // default shared libraryId for overdrive items
			foreach ($overdriveAccounts as $overdriveAccountIgnored) {
				$sharedOverdriveCollectionChoices[$sharedCollectionIdNum] = $sharedCollectionIdNum;
				$sharedCollectionIdNum--;
			}
		} else {
			$sharedOverdriveCollectionChoices = array(-1 => -1); // Have the default shared value even if accountId(s) aren't in the config
		}

		$innReachEncoreName = $configArray['InterLibraryLoan']['innReachEncoreName'];

		//$Instructions = 'For more information on ???, see the <a href="">online documentation</a>.';

		$structure = array(
			'isDefault' => array('property' => 'isDefault', 'type'=>'checkbox', 'label' => 'Default Library (one per install!)', 'description' => 'The default library instance for loading scoping information etc', 'hideInLists' => true),
			'libraryId' => array('property'=>'libraryId', 'type'=>'label', 'label'=>'Library Id', 'description'=>'The unique id of the library within the database'),
			'subdomain' => array('property'=>'subdomain', 'type'=>'text', 'label'=>'Subdomain', 'description'=>'A unique id to identify the library within the system'),
			'displayName' => array('property'=>'displayName', 'type'=>'text', 'label'=>'Display Name', 'description'=>'A name to identify the library within the system', 'size'=>'40'),
			'showDisplayNameInHeader' => array('property'=>'showDisplayNameInHeader', 'type'=>'checkbox', 'label'=>'Show Display Name in Header', 'description'=>'Whether or not the display name should be shown in the header next to the logo', 'hideInLists' => true, 'default'=>false),
			'abbreviatedDisplayName' => array('property'=>'abbreviatedDisplayName', 'type'=>'text', 'label'=>'Abbreviated Display Name', 'description'=>'A short name to identify the library when space is low', 'size'=>'40'),
			'systemMessage' => array('property'=>'systemMessage', 'type'=>'html', 'label'=>'System Message', 'description'=>'A message to be displayed at the top of the screen', 'size'=>'80', 'maxLength' =>'512', 'allowableTags' => '<a><b><em><div><script><span><p><strong><sub><sup>', 'hideInLists' => true),

			// Basic Display //
			'displaySection' =>array('property'=>'displaySection', 'type' => 'section', 'label' =>'Basic Display', 'hideInLists' => true,
					'helpLink' => 'https://docs.google.com/document/d/18XXYAn3m9IGbjKwDGluFhPoHDXdIFUhdgmoIEdgRVcM', 'properties' => array(
				'themeName' => array('property'=>'themeName', 'type'=>'text', 'label'=>'Theme Name', 'description'=>'The name of the theme which should be used for the library', 'hideInLists' => true, 'default' => 'marmot,responsive'),
				'homeLink' => array('property'=>'homeLink', 'type'=>'text', 'label'=>'Home Link', 'description'=>'The location to send the user when they click on the home button or logo.  Use default or blank to go back to the Pika home location.', 'size'=>'40', 'hideInLists' => true,),
				'additionalCss' => array('property'=>'additionalCss', 'type'=>'textarea', 'label'=>'Additional CSS', 'description'=>'Extra CSS to apply to the site.  Will apply to all pages.', 'hideInLists' => true),
				'headerText' => array('property'=>'headerText', 'type'=>'html', 'label'=>'Header Text', 'description'=>'Optional Text to display in the header, between the logo and the log in/out buttons.  Will apply to all pages.', 'allowableTags' => '<a><b><em><div><span><p><strong><sub><sup><h1><h2><h3><h4><h5><h6><img>', 'hideInLists' => true),
				'showSidebarMenu' => array('property'=>'showSidebarMenu', 'type'=>'checkbox', 'label'=>'Display Sidebar Menu', 'description'=>'Determines whether or not the sidebar menu will be shown.  Must also be enabled in config.ini.', 'hideInLists' => true,),
				'sidebarMenuButtonText' => array('property'=>'sidebarMenuButtonText', 'type'=>'text', 'label'=>'Sidebar Help Button Text', 'description'=>'The text to show for the help/menu button in the sidebar', 'size'=>'40', 'hideInLists' => true, 'default' => 'Help'),
				'sideBarOnRight' => array('property'=>'sideBarOnRight', 'type'=>'checkbox', 'label'=>'Display Sidebar on the Right Side', 'description'=>'Sidebars will be displayed on the right side of the page rather than the default left side.', 'hideInLists' => true,),
				'useHomeLinkInBreadcrumbs' => array('property'=>'useHomeLinkInBreadcrumbs', 'type'=>'checkbox', 'label'=>'Use Home Link in Breadcrumbs', 'description'=>'Whether or not the home link should be used in the breadcumbs.', 'hideInLists' => true,),
				'useHomeLinkForLogo' => array('property'=>'useHomeLinkForLogo', 'type'=>'checkbox', 'label'=>'Use Home Link for Logo', 'description'=>'Whether or not the home link should be used as the link for the main logo.', 'hideInLists' => true,),
				'homeLinkText' => array('property'=>'homeLinkText', 'type'=>'text', 'label'=>'Home Link Text', 'description'=>'The text to show for the Home breadcrumb link', 'size'=>'40', 'hideInLists' => true, 'default' => 'Home'),
				'showLibraryHoursAndLocationsLink' => array('property'=>'showLibraryHoursAndLocationsLink', 'type'=>'checkbox', 'label'=>'Show Library Hours and Locations Link', 'description'=>'Whether or not the library hours and locations link is shown on the home page.', 'hideInLists' => true, 'default' => true),
				'eContentSupportAddress'  => array('property'=>'eContentSupportAddress', 'type'=>'multiemail', 'label'=>'E-Content Support Address', 'description'=>'An e-mail address to receive support requests for patrons with eContent problems.', 'size'=>'80', 'hideInLists' => true, 'default'=>'askmarmot@marmot.org'),

				'enableGenealogy' => array('property'=>'enableGenealogy', 'type'=>'checkbox', 'label'=>'Enable Genealogy Functionality', 'description'=>'Whether or not patrons can search genealogy.', 'hideInLists' => true, 'default' => 1),
				'enableCourseReserves' => array('property'=>'enableCourseReserves', 'type'=>'checkbox', 'label'=>'Enable Repeat Search in Course Reserves', 'description'=>'Whether or not patrons can repeat searches within course reserves.', 'hideInLists' => true,),
				'showPikaLogo' => array('property'=>'showPikaLogo', 'type'=>'checkbox', 'label'=>'Display Pika Logo', 'description'=>'Determines whether or not the Pika logo will be shown in the footer.', 'hideInLists' => true, 'default' => true),
			)),

			// Contact Links //
			'contactSection' => array('property'=>'contact', 'type' => 'section', 'label' =>'Contact Links', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1KTYDrTQAK38dxsMG0R4q5w0sNJ5mbyz5Y6YofqWWBxI','properties' => array(
				'facebookLink' => array('property'=>'facebookLink', 'type'=>'text', 'label'=>'Facebook Link Url', 'description'=>'The url to Facebook (leave blank if the library does not have a Facebook account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'twitterLink' => array('property'=>'twitterLink', 'type'=>'text', 'label'=>'Twitter Link Url', 'description'=>'The url to Twitter (leave blank if the library does not have a Twitter account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'youtubeLink' => array('property'=>'youtubeLink', 'type'=>'text', 'label'=>'Youtube Link Url', 'description'=>'The url to Youtube (leave blank if the library does not have a Youtube account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'instagramLink' => array('property'=>'instagramLink', 'type'=>'text', 'label'=>'Instagram Link Url', 'description'=>'The url to Instagram (leave blank if the library does not have a Instagram account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'goodreadsLink' => array('property'=>'goodreadsLink', 'type'=>'text', 'label'=>'GoodReads Link Url', 'description'=>'The url to GoodReads (leave blank if the library does not have a GoodReads account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'generalContactLink' => array('property'=>'generalContactLink', 'type'=>'text', 'label'=>'General Contact Link Url', 'description'=>'The url to a General Contact Page, i.e webform or mailto link', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
			)),
			// defaults should be blank so that icons don't appear on page when the link is not set. plb 1-21-2015

			// ILS/Account Integration //
			'ilsSection' => array('property'=>'ilsSection', 'type' => 'section', 'label' =>'ILS/Account Integration', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1SmCcWYIV8bnUEaGu4HYvyiF8iqOKt06ooBbJukkJdO8', 'properties' => array(
				'ilsCode'                              => array('property'=>'ilsCode', 'type'=>'text', 'label'=>'ILS Code', 'description'=>'The location code that all items for this location start with.', 'size'=>'4', 'hideInLists' => false,),
				'scope'                                => array('property'=>'scope', 'type'=>'text', 'label'=>'Sierra Scope', 'description'=>'The scope for the system in Millennium/Sierra. Used for Self-Registration', 'size'=>'4', 'hideInLists' => true,),
				'useScope'                             => array('property'=>'useScope', 'type'=>'checkbox', 'label'=>'Use Scope', 'description'=>'Whether or not the scope should be used when displaying holdings.', 'hideInLists' => true,),
				'showExpirationWarnings'               => array('property'=>'showExpirationWarnings', 'type'=>'checkbox', 'label'=>'Show Expiration Warnings', 'description'=>'Whether or not the user should be shown expiration warnings if their card is nearly expired.', 'hideInLists' => true, 'default' => 1),
				'expirationNearMessage'                => array('property'=>'expirationNearMessage', 'type'=>'text', 'label'=>'Expiration Near Message (use the token %date% to insert the expiration date)', 'description'=>'A message to show in the menu when the user account will expire soon', 'hideInLists' => true, 'default' => ''),
				'expiredMessage'                       => array('property'=>'expiredMessage', 'type'=>'text', 'label'=>'Expired Message (use the token %date% to insert the expiration date)', 'description'=>'A message to show in the menu when the user account has expired', 'hideInLists' => true, 'default' => ''),
				'enableMaterialsBooking'               => array('property'=>'enableMaterialsBooking', 'type'=>'checkbox', 'label'=>'Enable Materials Booking', 'description'=>'Check to enable integration of Sierra\'s Materials Booking module.', 'hideInLists' => true, 'default' => 0),
				'allowLinkedAccounts'                  => array('property'=>'allowLinkedAccounts', 'type'=>'checkbox', 'label'=>'Allow Linked Accounts', 'description' => 'Whether or not users can link multiple library cards under a single Pika account.', 'hideInLists' => true, 'default' => 1),
				'showLibraryHoursNoticeOnAccountPages' => array('property'=>'showLibraryHoursNoticeOnAccountPages', 'type'=>'checkbox', 'label'=>'Show Library Hours Notice on Account Pages', 'description'=>'Whether or not the Library Hours notice should be shown at the top of My Account\'s Checked Out, Holds and Bookings pages.', 'hideInLists' => true, 'default'=>true),
				'pTypesSection'                        => array('property' => 'pTypesSectionSection', 'type' => 'section', 'label' => 'P-Types', 'hideInLists' => true,
						'helpLink'=>'https://docs.google.com/document/d/1SmCcWYIV8bnUEaGu4HYvyiF8iqOKt06ooBbJukkJdO8','properties' => array(
					'pTypes'       => array('property'=>'pTypes', 'type'=>'text', 'label'=>'P-Types', 'description'=>'A list of pTypes that are valid for the library.  Separate multiple pTypes with commas.'),
					'defaultPType' => array('property'=>'defaultPType', 'type'=>'text', 'label'=>'Default P-Type', 'description'=>'The P-Type to use when accessing a subdomain if the patron is not logged in.'),
				)),
				'barcodeSection' => array('property' => 'barcodeSection', 'type' => 'section', 'label' => 'Barcode', 'hideInLists' => true,
						'helpLink' => 'https://docs.google.com/document/d/13vk5Cx_bWRwc_XtwwzKei92ZeTGS8LcMnZadE2CxaBU', 'properties' => array(
					'minBarcodeLength' => array('property'=>'minBarcodeLength', 'type'=>'integer', 'label'=>'Min Barcode Length', 'description'=>'A minimum length the patron barcode is expected to be. Leave as 0 to extra processing of barcodes.', 'hideInLists' => true, 'default'=>0),
					'maxBarcodeLength' => array('property'=>'maxBarcodeLength', 'type'=>'integer', 'label'=>'Max Barcode Length', 'description'=>'The maximum length the patron barcode is expected to be. Leave as 0 to extra processing of barcodes.', 'hideInLists' => true, 'default'=>0),
					'barcodePrefix'    => array('property'=>'barcodePrefix', 'type'=>'text', 'label'=>'Barcode Prefix', 'description'=>'A barcode prefix to apply to the barcode if it does not start with the barcode prefix or if it is not within the expected min/max range.  Multiple prefixes can be specified by separating them with commas. Leave blank to avoid additional processing of barcodes.', 'hideInLists' => true,'default'=>''),
				)),
				'userProfileSection' => array('property' => 'userProfileSection', 'type' => 'section', 'label' => 'User Profile', 'hideInLists' => true,
						'helpLink'=>'https://docs.google.com/document/d/1S8s8KYPaw6x7IIcxUbzkXgCnnHXR6t8W_2CwXiQyjrE', 'properties' => array(
					'patronNameDisplayStyle'               => array('property'=>'patronNameDisplayStyle', 'type'=>'enum', 'values'=>array('firstinitial_lastname'=>'First Initial. Last Name', 'lastinitial_firstname'=>'First Name Last Initial.'), 'label'=>'Patron Display Name Style', 'description'=>'How to generate the patron display name'),
					'allowProfileUpdates'                  => array('property'=>'allowProfileUpdates', 'type'=>'checkbox', 'label'=>'Allow Profile Updates', 'description'=>'Whether or not the user can update their own profile.', 'hideInLists' => true, 'default' => 1),
					'allowPatronAddressUpdates'            => array('property' => 'allowPatronAddressUpdates', 'type'=>'checkbox', 'label'=>'Allow Patrons to Update Their Address', 'description'=>'Whether or not patrons should be able to update their own address in their profile.', 'hideInLists' => true, 'default' => 1),
					'showAlternateLibraryOptionsInProfile' => array('property' => 'showAlternateLibraryOptionsInProfile', 'type'=>'checkbox', 'label'=>'Allow Patrons to Update their Alternate Libraries', 'description'=>'Allow Patrons to See and Change Alternate Library Settings in the Catalog Options Tab in their profile.', 'hideInLists' => true, 'default' => 1),
					'showWorkPhoneInProfile'               => array('property' => 'showWorkPhoneInProfile', 'type'=>'checkbox', 'label'=>'Show Work Phone in Profile', 'description'=>'Whether or not patrons should be able to change a secondary/work phone number in their profile.', 'hideInLists' => true, 'default' => 0),
					'treatPrintNoticesAsPhoneNotices'      => array('property' => 'treatPrintNoticesAsPhoneNotices', 'type' => 'checkbox', 'label' => 'Treat Print Notices As Phone Notices', 'description' => 'When showing detailed information about hold notices, treat print notices as if they are phone calls', 'hideInLists' => true, 'default' => 0),
					'showNoticeTypeInProfile'              => array('property' => 'showNoticeTypeInProfile', 'type'=>'checkbox', 'label'=>'Show Notice Type in Profile', 'description'=>'Whether or not patrons should be able to change how they receive notices in their profile.', 'hideInLists' => true, 'default' => 0),
					'showPickupLocationInProfile'          => array('property' => 'showPickupLocationInProfile', 'type'=>'checkbox', 'label'=>'Allow Patrons to Update Their Pickup Location', 'description'=>'Whether or not patrons should be able to update their preferred pickup location in their profile.', 'hideInLists' => true, 'default' => 0),
					'addSMSIndicatorToPhone'               => array('property' => 'addSMSIndicatorToPhone', 'type'=>'checkbox', 'label'=>'Add SMS Indicator to Primary Phone', 'description'=>'Whether or not add ### TEXT ONLY to the user\'s primary phone number when they opt in to SMS notices.', 'hideInLists' => true, 'default' => 0),
					'maxFinesToAllowAccountUpdates'        => array('property' => 'maxFinesToAllowAccountUpdates', 'type'=>'currency', 'displayFormat'=>'%0.2f', 'label'=>'Maximum Fine Amount to Allow Account Updates', 'description'=>'The maximum amount that a patron can owe and still update their account. Any value <= 0 will disable this functionality.', 'hideInLists' => true, 'default' => 10)
				)),
				'holdsSection' => array('property' => 'holdsSection', 'type' => 'section', 'label' => 'Holds', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1tFkmGhqBrTdluS2tOzQ_xtzl3HxfjGhmFgk4r3BTVY8', 'properties' => array(
					'showHoldButton'                    => array('property'=>'showHoldButton', 'type'=>'checkbox', 'label'=>'Show Hold Button', 'description'=>'Whether or not the hold button is displayed so patrons can place holds on items', 'hideInLists' => true, 'default' => 1),
					'showHoldButtonInSearchResults'     => array('property'=>'showHoldButtonInSearchResults', 'type'=>'checkbox', 'label'=>'Show Hold Button within the search results', 'description'=>'Whether or not the hold button is displayed within the search results so patrons can place holds on items', 'hideInLists' => true, 'default' => 1),
					'showHoldButtonForUnavailableOnly'  => array('property'=>'showHoldButtonForUnavailableOnly', 'type'=>'checkbox', 'label'=>'Show Hold Button for items that are checked out only', 'description'=>'Whether or not the hold button is displayed within the search results so patrons can place holds on items', 'hideInLists' => true, 'default' => 1),
					'showHoldCancelDate'                => array('property'=>'showHoldCancelDate', 'type'=>'checkbox', 'label'=>'Show Cancellation Date', 'description'=>'Whether or not the patron should be able to set a cancellation date (not needed after date) when placing holds.', 'hideInLists' => true, 'default' => 1),
					'allowFreezeHolds'                  => array('property'=>'allowFreezeHolds', 'type'=>'checkbox', 'label'=>'Allow Freezing Holds', 'description'=>'Whether or not the user can freeze their holds.', 'hideInLists' => true, 'default' => 1),
					'defaultNotNeededAfterDays'         => array('property'=>'defaultNotNeededAfterDays', 'type'=>'integer', 'label'=>'Default Not Needed After Days', 'description'=>'Number of days to use for not needed after date by default. Use -1 for no default.', 'hideInLists' => true,),
					'showDetailedHoldNoticeInformation' => array('property' => 'showDetailedHoldNoticeInformation', 'type' => 'checkbox', 'label' => 'Show Detailed Hold Notice Information', 'description' => 'Whether or not the user should be presented with detailed hold notification information, i.e. you will receive an e-mail/phone call to xxx when the hold is available', 'hideInLists' => true, 'default' => 1),
					'inSystemPickupsOnly'               => array('property'=>'inSystemPickupsOnly', 'type'=>'checkbox', 'label'=>'In System Pickups Only', 'description'=>'Restrict pickup locations to only locations within this library system.', 'hideInLists' => true,),
					'validPickupSystems'                => array('property'=>'validPickupSystems', 'type'=>'text', 'label'=>'Valid Pickup Library Systems', 'description'=>'Additional Library Systems that can be used as pickup locations if the &quot;In System Pickups Only&quot; is on. List the libraries\' subdomains separated by pipes |', 'size'=>'20', 'hideInLists' => true,),
					'holdDisclaimer'                    => array('property'=>'holdDisclaimer', 'type'=>'textarea', 'label'=>'Hold Disclaimer', 'description'=>'A disclaimer to display to patrons when they are placing a hold on items letting them know that their information may be available to other libraries.  Leave blank to not show a disclaimer.', 'hideInLists' => true,),
				)),
				'loginSection' => array('property' => 'loginSection', 'type' => 'section', 'label' => 'Login', 'hideInLists' => true,
						'helpLink' => 'https://docs.google.com/document/d/13vk5Cx_bWRwc_XtwwzKei92ZeTGS8LcMnZadE2CxaBU', 'properties' => array(
					'showLoginButton'         => array('property'=>'showLoginButton', 'type'=>'checkbox', 'label'=>'Show Login Button', 'description'=>'Whether or not the login button is displayed so patrons can login to the site', 'hideInLists' => true, 'default' => 1),
					'allowPinReset'           => array('property'=>'allowPinReset', 'type'=>'checkbox', 'label'=>'Allow PIN Update', 'description'=>'Whether or not the user can update their PIN in the Account Settings page.', 'hideInLists' => true, 'default' => 0),
					'preventExpiredCardLogin' => array('property'=>'preventExpiredCardLogin', 'type'=>'checkbox', 'label'=>'Prevent Login for Expired Cards', 'description'=>'Users with expired cards will not be allowed to login. They will recieve an expired card notice instead.', 'hideInLists' => true, 'default' => 0),
					'loginFormUsernameLabel'  => array('property'=>'loginFormUsernameLabel', 'type'=>'text', 'label'=>'Login Form Username Label', 'description'=>'The label to show for the username when logging in', 'size'=>'100', 'hideInLists' => true, 'default'=>'Your Name'),
					'loginFormPasswordLabel'  => array('property'=>'loginFormPasswordLabel', 'type'=>'text', 'label'=>'Login Form Password Label', 'description'=>'The label to show for the password when logging in', 'size'=>'100', 'hideInLists' => true, 'default'=>'Library Card Number'),
				)),
				'selfRegistrationSection' => array('property' => 'selfRegistrationSection', 'type' => 'section', 'label' => 'Self Registration', 'hideInLists' => true,
						'helpLink' => 'https://docs.google.com/document/d/1MZAOlg3F2IEa0WKsJmDQiCFUrw-pVo_fnSNexAV4MbQ', 'properties' => array(
					'enableSelfRegistration'         => array('property'=>'enableSelfRegistration', 'type'=>'checkbox', 'label'=>'Enable Self Registration', 'description'=>'Whether or not patrons can self register on the site', 'hideInLists' => true),
					'promptForBirthDateInSelfReg'    => array('property' => 'promptForBirthDateInSelfReg', 'type' => 'checkbox', 'label' => 'Prompt For Birth Date', 'description'=>'Whether or not to prompt for birth date when self registering'),
					'selfRegistrationFormMessage'    => array('property'=>'selfRegistrationFormMessage', 'type'=>'html', 'label'=>'Self Registration Form Message', 'description'=>'Message shown to users with the form to submit the self registration.  Leave blank to give users the default message.', 'allowableTags' => '<a><b><em><div><script><span><p><strong><sub><sup><ul><li>', 'hideInLists' => true),
					'selfRegistrationSuccessMessage' => array('property'=>'selfRegistrationSuccessMessage', 'type'=>'html', 'label'=>'Self Registration Success Message', 'description'=>'Message shown to users when the self registration has been completed successfully.  Leave blank to give users the default message.',  'allowableTags' => '<a><b><em><div><script><span><p><strong><sub><sup><ul><li>', 'hideInLists' => true),
					'selfRegistrationTemplate'       => array('property'=>'selfRegistrationTemplate', 'type'=>'text', 'label'=>'Self Registration Template', 'description'=>'The ILS template to use during self registration (Sierra and Millennium).', 'hideInLists' => true, 'default' => 'default'),
				)),
				'masqueradeModeSection' => array('property' => 'masqueradeModeSection', 'type' => 'section', 'label' => 'Masquerade Mode', 'hideInLists' => true,
				                                  'properties' => array(
					'allowMasqueradeMode'                        => array('property'=>'allowMasqueradeMode', 'type'=>'checkbox', 'label'=>'Allow Masquerade Mode', 'description' => 'Whether or not staff users (depending on pType setting) can use Masquerade Mode.', 'hideInLists' => true, 'default' => false),
					'masqueradeAutomaticTimeoutLength'           => array('property'=>'masqueradeAutomaticTimeoutLength', 'type'=>'integer', 'label'=>'Masquerade Mode Automatic Timeout Length', 'description'=>'The length of time before an idle user\'s Masquerade session automatically ends in seconds.', 'size'=>'8', 'hideInLists' => true, 'max' => 240),
					'allowReadingHistoryDisplayInMasqueradeMode' => array('property'=>'allowReadingHistoryDisplayInMasqueradeMode', 'type'=>'checkbox', 'label'=>'Allow Display of Reading History in Masquerade Mode', 'description'=>'This option allows Guiding Users to view the Reading History of the masqueraded user.', 'hideInLists' => true, 'default' => false),
				)),
			)),

			'ecommerceSection' => array('property'=>'ecommerceSection', 'type' => 'section', 'label' =>'Fines/e-commerce', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1PNoYpn01Yn0Bnqnk9R1CkAMM3RiqBLrk-U4azb2xrZg', 'properties' => array(
				'showEcommerceLink'        => array('property'=>'showEcommerceLink', 'type'=>'checkbox', 'label'=>'Show E-Commerce Link', 'description'=>'Whether or not users should be given a link to classic opac to pay fines', 'hideInLists' => true,),
				'payFinesLink'             => array('property'=>'payFinesLink', 'type'=>'text', 'label'=>'Pay Fines Link', 'description'=>'The link to pay fines.  Leave as default to link to classic (should have eCommerce link enabled)', 'hideInLists' => true, 'default' => 'default', 'size' => 80),
				'payFinesLinkText'         => array('property'=>'payFinesLinkText', 'type'=>'text', 'label'=>'Pay Fines Link Text', 'description'=>'The text when linking to pay fines.', 'hideInLists' => true, 'default' => 'Click to Pay Fines Online ', 'size' => 80),
				'minimumFineAmount'        => array('property'=>'minimumFineAmount', 'type'=>'currency', 'displayFormat'=>'%0.2f', 'label'=>'Minimum Fine Amount', 'description'=>'The minimum fine amount to display the e-commerce link', 'hideInLists' => true,),
				'showRefreshAccountButton' => array('property'=>'showRefreshAccountButton', 'type'=>'checkbox', 'label'=>'Show Refresh Account Button', 'description'=>'Whether or not a Show Refresh Account button is displayed in a pop-up when a user clicks the E-Commerce Link', 'hideInLists' => true, 'default' => true),
			)),

			// Searching //
			'searchingSection' => array('property'=>'searchingSection', 'type' => 'section', 'label' =>'Searching', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1QQ7bNfGx75ImTguxEOmf7eCtdrVN9vi8FpWtWY_O3OU', 'properties' => array(
				'restrictSearchByLibrary'                  => array('property' => 'restrictSearchByLibrary', 'type'=>'checkbox', 'label'=>'Restrict Search By Library', 'description'=>'Whether or not search results should only include titles from this library', 'hideInLists' => true),
				'includeOutOfSystemExternalLinks'          => array('property' => 'includeOutOfSystemExternalLinks', 'type'=>'checkbox', 'label'=>'Include Out Of System External Links', 'description'=>'Whether or not to include external links from other library systems.  Should only be enabled for global scope.', 'hideInLists' => true, 'default'=>0),
				'publicListsToInclude'                     => array('property' => 'publicListsToInclude', 'type'=>'enum', 'values' => array(0 => 'No Lists', '1' => 'Lists from this library', '3'=>'Lists from library list publishers Only', '4'=>'Lists from all list publishers', '2' => 'All Lists'), 'label'=>'Public Lists To Include', 'description'=>'Which lists should be included in this scope'),
				'boostByLibrary'                           => array('property' => 'boostByLibrary', 'type'=>'checkbox', 'label'=>'Boost By Library', 'description'=>'Whether or not boosting of titles owned by this library should be applied', 'hideInLists' => true),
				'additionalLocalBoostFactor'               => array('property' => 'additionalLocalBoostFactor', 'type'=>'integer', 'label'=>'Additional Local Boost Factor', 'description'=>'An additional numeric boost to apply to any locally owned and locally available titles', 'hideInLists' => true),
				'allowAutomaticSearchReplacements'         => array('property' => 'allowAutomaticSearchReplacements', 'type'=>'checkbox', 'label'=>'Allow Automatic Search Corrections', 'description'=>'Turn on to allow Pika to replace search terms that have no results if the current search term looks like a misspelling.', 'hideInLists' => true, 'default'=>true),
				'applyNumberOfHoldingsBoost'               => array('property' => 'applyNumberOfHoldingsBoost', 'type'=>'checkbox', 'label'=>'Apply Number Of Holdings Boost', 'description'=>'Whether or not the relevance will use boosting by number of holdings in the catalog.', 'hideInLists' => true, 'default' => 1),
				'searchBoxSection' => array('property' => 'searchBoxSection', 'type' => 'section', 'label' => 'Search Box', 'hideInLists' => true, 'properties' => array(
					'horizontalSearchBar'                    => array('property' => 'horizontalSearchBar',      'type'=>'checkbox', 'label' => 'Use Horizontal Search Bar',   'description' => 'Instead of the default sidebar search box, a horizontal search bar is shown below the header that spans the screen.', 'hideInLists' => true, 'default' => false),
					'systemsToRepeatIn'                      => array('property' => 'systemsToRepeatIn',        'type' => 'text',   'label' => 'Systems To Repeat In',        'description' => 'A list of library codes that you would like to repeat search in separated by pipes |.', 'size'=>'20', 'hideInLists' => true,),
					'repeatSearchOption'                     => array('property' => 'repeatSearchOption',       'type'=>'enum',     'label' => 'Repeat Search Options (requires Restrict Search to Library to be ON)',       'description'=>'Where to allow repeating search. Valid options are: none, librarySystem, marmot, all', 'values'=>array('none'=>'None', 'librarySystem'=>'Library System','marmot'=>'Consortium'),),
					'repeatInOnlineCollection'               => array('property' => 'repeatInOnlineCollection', 'type'=>'checkbox', 'label' => 'Repeat In Online Collection', 'description'=>'Turn on to allow repeat search in the Online Collection.', 'hideInLists' => true, 'default'=>false),
					'showAdvancedSearchbox'                  => array('property' => 'showAdvancedSearchbox',    'type'=>'checkbox', 'label' => 'Show Advanced Search Link',   'description'=>'Whether or not users should see the advanced search link below the search box.', 'hideInLists' => true, 'default' => 1),
				)),
				'searchResultsSection' => array('property' => 'searchResultsSection', 'type' => 'section', 'label' => 'Search Results', 'hideInLists' => true, 'properties' => array(
					'showSearchTools'                        => array('property' => 'showSearchTools',                    'type' => 'checkbox',    'label' => 'Show Search Tools',                                          'description' => 'Turn on to activate search tools (save search, export to excel, rss feed, etc).', 'hideInLists' => true),
					'showInSearchResultsMainDetails'         => array('property' => 'showInSearchResultsMainDetails',     'type' => 'multiSelect', 'label' => 'Optional details to show for a record in search results : ', 'description' => 'Selected details will be shown in the main details section of a record on a search results page.', 'listStyle' => 'checkboxSimple', 'values' => self::$searchResultsMainDetailsOptions),
					'alwaysShowSearchResultsMainDetails'     => array('property' => 'alwaysShowSearchResultsMainDetails', 'type' => 'checkbox',    'label' => 'Always Show Selected Search Results Main Details',           'description' => 'Turn on to always show the selected details even when there is no info supplied for a detail, or the detail varies due to multiple formats and/or editions). Does not apply to Series & Language', 'hideInLists' => true),
				)),
				'searchFacetsSection' => array('property' => 'searchFacetsSection', 'type' => 'section', 'label' => 'Search Facets', 'hideInLists' => true, 'properties' => array(
					'availabilityToggleLabelSuperScope'           => array('property' => 'availabilityToggleLabelSuperScope',           'type' => 'text',     'label' => 'SuperScope Toggle Label',                                  'description' => 'The label to show when viewing super scope i.e. Consortium Name / Entire Collection / Everything.  Does not show if superscope is not enabled.', 'default' => 'Entire Collection'),
					'availabilityToggleLabelLocal'                => array('property' => 'availabilityToggleLabelLocal',                'type' => 'text',     'label' => 'Local Collection Toggle Label',                            'description' => 'The label to show when viewing the local collection i.e. Library Name / Local Collection.  Leave blank to hide the button.', 'default' => ''),
					'availabilityToggleLabelAvailable'            => array('property' => 'availabilityToggleLabelAvailable',            'type' => 'text',     'label' => 'Available Toggle Label',                                   'description' => 'The label to show when viewing available items i.e. Available Now / Available Locally / Available Here.', 'default' => 'Available Now'),
					'availabilityToggleLabelAvailableOnline'      => array('property' => 'availabilityToggleLabelAvailableOnline',      'type' => 'text',     'label' => 'Available Online Toggle Label', 'description' => 'The label to show when viewing available items i.e. Available Online.', 'default' => 'Available Online'),
					'includeOnlineMaterialsInAvailableToggle'     => array('property' => 'includeOnlineMaterialsInAvailableToggle',     'type' => 'checkbox', 'label' => 'Include Online Materials in Available Toggle', 'description'=>'Turn on to include online materials in both the Available Now and Available Online Toggles.', 'hideInLists' => true, 'default'=>false),
					'facetLabel'                                  => array('property' => 'facetLabel',                                  'type' => 'text',     'label' => 'Library System Facet Label',                               'description' => 'The label for the library system in the Library System Facet.', 'size'=>'40', 'hideInLists' => true,),
					'restrictOwningBranchesAndSystems'            => array('property' => 'restrictOwningBranchesAndSystems',            'type' => 'checkbox', 'label' => 'Restrict Owning Branch and System Facets to this library', 'description' => 'Whether or not the Owning Branch and Owning System Facets will only display values relevant to this library.', 'hideInLists' => true),
					'showAvailableAtAnyLocation'                  => array('property' => 'showAvailableAtAnyLocation',                  'type' => 'checkbox', 'label' => 'Show Available At Any Location?',                          'description' => 'Whether or not to show any Marmot Location within the Available At facet', 'hideInLists' => true),
					'additionalLocationsToShowAvailabilityFor'    => array('property' => 'additionalLocationsToShowAvailabilityFor',    'type' => 'text',     'label' => 'Additional Locations to Include in Available At Facet',    'description' => 'A list of library codes that you would like included in the available at facet separated by pipes |.', 'size'=>'20', 'hideInLists' => true,),
					'includeAllRecordsInShelvingFacets'           => array('property' => 'includeAllRecordsInShelvingFacets',           'type' => 'checkbox', 'label' => 'Include All Records In Shelving Facets',                   'description' => 'Turn on to include all records (owned and included) in shelving related facets (detailed location, collection).', 'hideInLists' => true, 'default'=>false),
					'includeAllRecordsInDateAddedFacets'          => array('property' => 'includeAllRecordsInDateAddedFacets',          'type' => 'checkbox', 'label' => 'Include All Records In Date Added Facets',                 'description' => 'Turn on to include all records (owned and included) in date added facets.', 'hideInLists' => true, 'default'=>false),
					'includeOnOrderRecordsInDateAddedFacetValues' => array('property' => 'includeOnOrderRecordsInDateAddedFacetValues', 'type' => 'checkbox', 'label' => 'Include On Order Records In All Date Added Facet Values',  'description' => 'Use On Order records (date added value (tomorrow)) in calculations for all date added facet values. (eg. Added in the last day, week, etc.)', 'hideInLists' => true, 'default'=>true),

					'facets' => array(
						'property' => 'facets',
						'type' => 'oneToMany',
						'label' => 'Facets',
						'description' => 'A list of facets to display in search results',
						'helpLink' => 'https://docs.google.com/document/d/1DIOZ-HCqnrBAMFwAomqwI4xv41bALk0Z1Z2fMrhQ3wY',
						'keyThis' => 'libraryId',
						'keyOther' => 'libraryId',
						'subObjectType' => 'LibraryFacetSetting',
						'structure' => $facetSettingStructure,
						'sortable' => true,
						'storeDb' => true,
						'allowEdit' => true,
						'canEdit' => true,
						'additionalOneToManyActions' => array(
							array(
								'text' => 'Copy Library Facets',
								'url' => '/Admin/Libraries?id=$id&amp;objectAction=copyFacetsFromLibrary',
							),
							array(
								'text' => 'Reset Facets To Default',
								'url' => '/Admin/Libraries?id=$id&amp;objectAction=resetFacetsToDefault',
								'class' => 'btn-warning',
							),
						)
					),
				)),

				'combinedResultsSection' => array('property' => 'combinedResultsSection', 'type' => 'section', 'label' => 'Combined Results', 'hideInLists' => true,
						'helpLink' => 'https://docs.google.com/document/d/1dcG12grGAzYlWAl6LWUnr9t-wdqcmMTJVwjLuItRNwk', 'properties' => array(
						'enableCombinedResults' => array('property' => 'enableCombinedResults', 'type'=>'checkbox', 'label'=>'Enable Combined Results', 'description'=>'Whether or not combined results should be shown ', 'hideInLists' => true, 'default' => false),
						'combinedResultsLabel' => array('property' => 'combinedResultsLabel', 'type' => 'text', 'label' => 'Combined Results Label', 'description' => 'The label to use in the search source box when combined results is active.', 'size'=>'20', 'hideInLists' => true, 'default' => 'Combined Results'),
						'defaultToCombinedResults' => array('property' => 'defaultToCombinedResults', 'type'=>'checkbox', 'label'=>'Default To Combined Results', 'description'=>'Whether or not combined results should be the default search source when active ', 'hideInLists' => true, 'default' => true),
						'combinedResultSections' => array(
								'property' => 'combinedResultSections',
								'type' => 'oneToMany',
								'label' => 'Combined Results Sections',
								'description' => 'Which sections should be shown in the combined results search display',
								'helpLink' => '',
								'keyThis' => 'libraryId',
								'keyOther' => 'libraryId',
								'subObjectType' => 'LibraryCombinedResultSection',
								'structure' => $combinedResultsStructure,
								'sortable' => true,
								'storeDb' => true,
								'allowEdit' => true,
								'canEdit' => false,
								'additionalOneToManyActions' => array(
								)
						),
				)),


			)),

			// Catalog Enrichment //
			'enrichmentSection' => array('property'=>'enrichmentSection', 'type' => 'section', 'label' =>'Catalog Enrichment', 'hideInLists' => true,
					'helpLink' => 'https://docs.google.com/document/d/1fJ2Sc62fTieJlPvaFz4XUoSr8blou_3MfxDGh1luI84', 'properties' => array(
				'showStandardReviews'      => array('property'=>'showStandardReviews', 'type'=>'checkbox', 'label'=>'Show Standard Reviews', 'description'=>'Whether or not reviews from Content Cafe/Syndetics are displayed on the full record page.', 'hideInLists' => true, 'default' => 1),
				'showGoodReadsReviews'     => array('property'=>'showGoodReadsReviews', 'type'=>'checkbox', 'label'=>'Show GoodReads Reviews', 'description'=>'Whether or not reviews from GoodReads are displayed on the full record page.', 'hideInLists' => true, 'default'=>true),
				'preferSyndeticsSummary'   => array('property'=>'preferSyndeticsSummary', 'type'=>'checkbox', 'label'=>'Prefer Syndetics/Content Cafe Description', 'description'=>'Whether or not the Description loaded from an enrichment service should be preferred over the Description in the Marc Record.', 'hideInLists' => true, 'default' => 1),
				'showSimilarAuthors'       => array('property'=>'showSimilarAuthors', 'type'=>'checkbox', 'label'=>'Show Similar Authors', 'description'=>'Whether or not Similar Authors from Novelist is shown.', 'default' => 1, 'hideInLists' => true,),
				'showSimilarTitles'        => array('property'=>'showSimilarTitles', 'type'=>'checkbox', 'label'=>'Show Similar Titles', 'description'=>'Whether or not Similar Titles from Novelist is shown.', 'default' => 1, 'hideInLists' => true,),
				'showGoDeeper'             => array('property'=>'showGoDeeper', 'type'=>'checkbox', 'label'=>'Show Content Enrichment (TOC, Excerpts, etc)', 'description'=>'Whether or not additional content enrichment like Table of Contents, Exceprts, etc are shown to the user', 'default' => 1, 'hideInLists' => true,),
				'showRatings'              => array('property'=>'showRatings', 'type'=>'checkbox', 'label'=>'Enable User Ratings', 'description'=>'Whether or not ratings are shown', 'hideInLists' => true, 'default' => 1),
				'showComments'             => array('property'=>'showComments', 'type'=>'checkbox', 'label'=>'Enable User Reviews', 'description'=>'Whether or not user reviews are shown (also disables adding user reviews)', 'hideInLists' => true, 'default' => 1),
				// showComments & hideCommentsWithBadWords moved from full record display to this section. plb 6-30-2015
				'hideCommentsWithBadWords' => array('property'=>'hideCommentsWithBadWords', 'type'=>'checkbox', 'label'=>'Hide Comments with Bad Words', 'description'=>'If checked, any User Lists or User Reviews with bad words are completely removed from the user interface for everyone except the original poster.', 'hideInLists' => true,),
				'showFavorites'            => array('property'=>'showFavorites', 'type'=>'checkbox', 'label'=>'Enable User Lists', 'description'=>'Whether or not users can maintain favorites lists', 'hideInLists' => true, 'default' => 1),
				//TODO database column rename?
				'showWikipediaContent'     => array('property'=>'showWikipediaContent', 'type'=>'checkbox', 'label'=>'Show Wikipedia Content', 'description'=>'Whether or not Wikipedia content should be shown on author page', 'default'=>'1', 'hideInLists' => true,),
			)),

			// Full Record Display //
			'fullRecordSection' => array('property'=>'fullRecordSection', 'type' => 'section', 'label' =>'Full Record Display', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1ZZsoKW2NOfGMad36BkWeF5ROqH5Wyg5up3eIhki5Lec', 'properties' => array(
// disabled				'showTextThis'             => array('property'=>'showTextThis',             'type'=>'checkbox', 'label'=>'Show Text This',                    'description'=>'Whether or not the Text This link is shown', 'hideInLists' => true, 'default' => 1),
				'showEmailThis'            => array('property'=>'showEmailThis',            'type'=>'checkbox', 'label'=>'Show Email This',                   'description'=>'Whether or not the Email This link is shown', 'hideInLists' => true, 'default' => 1),
				'showShareOnExternalSites' => array('property'=>'showShareOnExternalSites', 'type'=>'checkbox', 'label'=>'Show Sharing To External Sites',    'description'=>'Whether or not sharing on external sites (Twitter, Facebook, Pinterest, etc. is shown)', 'hideInLists' => true, 'default' => 1),
				'showQRCode'               => array('property'=>'showQRCode',               'type'=>'checkbox', 'label'=>'Show QR Code',                      'description'=>'Whether or not the catalog should show a QR Code in full record view', 'hideInLists' => true, 'default' => 1),
				'showTagging'              => array('property'=>'showTagging',              'type'=>'checkbox', 'label'=>'Show Tagging',                      'description'=>'Whether or not tags are shown (also disables adding tags)', 'hideInLists' => true, 'default' => 1),
//				'exportOptions'            => array('property'=>'exportOptions',            'type'=>'text',     'label'=>'Export Options',                    'description'=>'A list of export options that should be enabled separated by pipes.  Valid values are currently RefWorks and EndNote.', 'size'=>'40', 'hideInLists' => true,),
				'show856LinksAsTab'        => array('property'=>'show856LinksAsTab',        'type'=>'checkbox', 'label'=>'Show 856 Links as Tab',             'description'=>'Whether or not 856 links will be shown in their own tab or on the same tab as holdings.', 'hideInLists' => true, 'default' => 1),
				'showCheckInGrid'          => array('property'=>'showCheckInGrid',          'type'=>'checkbox', 'label'=>'Show Check-in Grid',                'description'=>'Whether or not the check-in grid is shown for periodicals.', 'default' => 1, 'hideInLists' => true,),
				'showStaffView'            => array('property'=>'showStaffView',            'type'=>'checkbox', 'label'=>'Show Staff View',                   'description'=>'Whether or not the staff view is displayed in full record view.', 'hideInLists' => true, 'default'=>true),
				'showLCSubjects'           => array('property'=>'showLCSubjects',           'type'=>'checkbox', 'label'=>'Show Library of Congress Subjects', 'description'=>'Whether or not standard (LC) subjects are displayed in full record view.', 'hideInLists' => true, 'default'=>true),
				'showBisacSubjects'        => array('property'=>'showBisacSubjects',        'type'=>'checkbox', 'label'=>'Show Bisac Subjects',               'description'=>'Whether or not Bisac subjects are displayed in full record view.', 'hideInLists' => true, 'default'=>true),
				'showFastAddSubjects'      => array('property'=>'showFastAddSubjects',      'type'=>'checkbox', 'label'=>'Show OCLC Fast Subjects',           'description'=>'Whether or not OCLC Fast Add subjects are displayed in full record view.', 'hideInLists' => true, 'default'=>true),
				'showOtherSubjects'        => array('property'=>'showOtherSubjects',        'type'=>'checkbox', 'label'=>'Show Other Subjects',               'description'=>'Whether or other subjects from the MARC are displayed in full record view.', 'hideInLists' => true, 'default'=>true),

				'showInMainDetails' => array('property' => 'showInMainDetails', 'type' => 'multiSelect', 'label'=>'Which details to show in the main/top details section : ', 'description'=> 'Selected details will be shown in the top/main section of the full record view. Details not selected are moved to the More Details accordion.',
					'listStyle' => 'checkboxSimple',
				  'values' => self::$showInMainDetailsOptions,
				),
				'moreDetailsOptions' => array(
						'property' => 'moreDetailsOptions',
						'type' => 'oneToMany',
						'label' => 'Full Record Options',
						'description' => 'Record Options for the display of full record',
						'keyThis' => 'libraryId',
						'keyOther' => 'libraryId',
						'subObjectType' => 'LibraryMoreDetails',
						'structure' => $libraryMoreDetailsStructure,
						'sortable' => true,
						'storeDb' => true,
						'allowEdit' => true,
						'canEdit' => false,
						'additionalOneToManyActions' => array(
							0 => array(
								'text' => 'Reset More Details To Default',
								'url' => '/Admin/Libraries?id=$id&amp;objectAction=resetMoreDetailsToDefault',
								'class' => 'btn-warning',
							)
						)
				),
			)),

			'holdingsSummarySection' => array('property'=>'holdingsSummarySection', 'type' => 'section', 'label' =>'Holdings Summary', 'hideInLists' => true,
			                                  'helpLink' => 'https://docs.google.com/document/d/1PjlFlhPVNRVcg_uzzHLQLkRicyPEB1KeVNok4Wkye1I', 'properties' => array(
					'showItsHere' => array('property'=>'showItsHere', 'type'=>'checkbox', 'label'=>'Show It\'s Here', 'description'=>'Whether or not the holdings summary should show It\'s here based on IP and the currently logged in patron\'s location.', 'hideInLists' => true, 'default' => 1),
					'showGroupedHoldCopiesCount' => array('property'=>'showGroupedHoldCopiesCount', 'type'=>'checkbox', 'label'=>'Show Hold and Copy Counts', 'description'=>'Whether or not the hold count and copies counts should be visible for grouped works when summarizing formats.', 'hideInLists' => true, 'default' => 1),
					'showOnOrderCounts' => array('property'=>'showOnOrderCounts', 'type'=>'checkbox', 'label'=>'Show On Order Counts', 'description'=>'Whether or not counts of Order Items should be shown .', 'hideInLists' => true, 'default' => 1),
				)),

			// Browse Category Section //
			'browseCategorySection' => array('property' => 'browseCategorySection', 'type' => 'section', 'label' => 'Browse Categories', 'hideInLists' => true, 'helpLink'=> 'https://docs.google.com/document/d/11biGMw6UDKx9UBiDCCj_GBmatx93UlJBLMESNf_RtDU', 'properties' => array(
				'defaultBrowseMode' => array('property' => 'defaultBrowseMode', 'type' => 'enum', 'label'=>'Default Viewing Mode for Browse Categories', 'description' => 'Sets how browse categories will be displayed when users haven\'t chosen themselves.', 'hideInLists' => true,
				                             'values'=> array('covers' => 'Show Covers Only', 'grid' => 'Show as Grid'), 'default' => 'covers'),
				'browseCategoryRatingsMode' => array('property' => 'browseCategoryRatingsMode', 'type' => 'enum', 'label' => 'Ratings Mode for Browse Categories ("covers" browse mode only)', 'description' => 'Sets how ratings will be displayed and how user ratings will be enabled when a user is viewing a browse category in the &#34;covers&#34; browse mode. These settings only apply when User Ratings have been enabled. (These settings will also apply to search results viewed in covers mode.)',
				                                     'values' => array('popup' => 'Show rating stars and enable user rating via pop-up form.',
				                                                       'stars' => 'Show rating stars and enable user ratings by clicking the stars.',
				                                                       'none' => 'Do not show rating stars.'
					                                     ), 'default' => 'popup'
				),

				// The specific categories displayed in the carousel
				'browseCategories' => array(
						'property'=>'browseCategories',
						'type'=>'oneToMany',
						'label'=>'Browse Categories',
						'description'=>'Browse Categories To Show on the Home Screen',
						'keyThis' => 'libraryId',
						'keyOther' => 'libraryId',
						'subObjectType' => 'LibraryBrowseCategory',
						'structure' => $libraryBrowseCategoryStructure,
						'sortable' => true,
						'storeDb' => true,
						'allowEdit' => false,
						'canEdit' => false,
				),
			)),

			'materialsRequestSection'=> array('property'=>'materialsRequestSection', 'type' => 'section', 'label' =>'Materials Request', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/18Sah0T8sWUextphL5ykg8QEM_YozniSXqOo1nfi6gnc',
					'properties' => array(
				'enableMaterialsRequest'      => array('property'=>'enableMaterialsRequest', 'type'=>'checkbox', 'label'=>'Enable Pika Materials Request System', 'description'=>'Enable Materials Request functionality so patrons can request items not in the catalog.', 'hideInLists' => true,),
				'externalMaterialsRequestUrl' => array('property'=>'externalMaterialsRequestUrl', 'type'=>'text', 'label'=>'External Materials Request URL', 'description'=>'A link to an external Materials Request System to be used instead of the built in Pika system', 'hideInList' => true),
				'maxRequestsPerYear'          => array('property'=>'maxRequestsPerYear', 'type'=>'integer', 'label'=>'Max Requests Per Year', 'description'=>'The maximum number of requests that a user can make within a year', 'hideInLists' => true, 'default' => 60),
				'maxOpenRequests'             => array('property'=>'maxOpenRequests', 'type'=>'integer', 'label'=>'Max Open Requests', 'description'=>'The maximum number of requests that a user can have open at one time', 'hideInLists' => true, 'default' => 5),
				'newMaterialsRequestSummary'  => array('property'=>'newMaterialsRequestSummary', 'type'=>'html', 'label'=>'New Request Summary', 'description'=>'Text displayed at the top of Materials Request form to give users important information about the request they submit', 'size'=>'40', 'maxLength' =>'512', 'allowableTags' => '<a><b><em><div><script><span><p><strong><sub><sup>', 'hideInLists' => true),
				'materialsRequestDaysToPreserve' => array('property' => 'materialsRequestDaysToPreserve', 'type'=>'integer', 'label'=>'Delete Closed Requests Older than (days)', 'description' => 'The number of days to preserve closed requests.  Requests will be preserved for a minimum of 366 days.  We suggest preserving for at least 395 days.  Setting to a value of 0 will preserve all requests', 'hideInLists' => true, 'default' => 396),

				'materialsRequestFieldsToDisplay' => array(
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
				),

				'materialsRequestFormats' => array(
					'property'      => 'materialsRequestFormats',
					'type'          => 'oneToMany',
					'label'         => 'Formats of Materials that can be Requested',
					'description'   => 'Determine which material formats are available to patrons for request',
					'keyThis'       => 'libraryId',
					'keyOther'      => 'libraryId',
					'subObjectType' => 'MaterialsRequestFormats',
					'structure'     => $materialsRequestFormatsStructure,
					'sortable'      => true,
					'storeDb'       => true,
					'allowEdit'     => false,
					'canEdit'       => false,
					'additionalOneToManyActions' => array(
						0 => array(
							'text' => 'Set Materials Request Formats To Default',
							'url' => '/Admin/Libraries?id=$id&amp;objectAction=defaultMaterialsRequestFormats',
							'class' => 'btn-warning',
						)
					)
				),

				'materialsRequestFormFields' => array(
					'property'      => 'materialsRequestFormFields',
					'type'          => 'oneToMany',
					'label'         => 'Materials Request Form Fields',
					'description'   => 'Fields that are displayed in the Materials Request Form',
					'keyThis'       => 'libraryId',
					'keyOther'      => 'libraryId',
					'subObjectType' => 'MaterialsRequestFormFields',
					'structure'     => $materialsRequestFormFieldsStructure,
					'sortable'      => true,
					'storeDb'       => true,
					'allowEdit'     => false,
					'canEdit'       => false,
					'additionalOneToManyActions' => array(
						0 => array(
							'text' => 'Set Materials Request Form Structure To Default',
							'url' => '/Admin/Libraries?id=$id&amp;objectAction=defaultMaterialsRequestForm',
								'class' => 'btn-warning',
						)
					)
				),

			)),
			'interLibraryLoanSection' => array('property'=>'interLibraryLoanSectionSection', 'type' => 'section', 'label' =>'Interlibrary Loaning', 'hideInLists' => true,  'properties' => array(
				'interLibraryLoanName' => array('property'=>'interLibraryLoanName', 'type'=>'text', 'label'=>'Name of Interlibrary Loan Service', 'description'=>'The name to be displayed in the link to the ILL service ', 'hideInLists' => true, 'size'=>'80'),
				'interLibraryLoanUrl' => array('property'=>'interLibraryLoanUrl',   'type'=>'text', 'label'=>'Interlibrary Loan URL', 'description'=>'The link for the ILL Service.', 'hideInLists' => true, 'size'=>'80'),

			'prospectorSection' => array('property'=>'prospectorSection', 'type' => 'section', 'label' => $innReachEncoreName . ' (III INN-Reach & Encore)', 'hideInLists' => true,
					'helpLink' =>'https://docs.google.com/document/d/18SVEhciSjO99hcFLLdFR6OpC4_OtjOafTkuWPGXOhu4', 'properties' => array(
				'repeatInProspector'                 => array('property'=>'repeatInProspector',                 'type'=>'checkbox', 'label'=>'Repeat In '.$innReachEncoreName, 'description' => 'Turn on to allow repeat search in '.$innReachEncoreName.' functionality.', 'hideInLists' => true, 'default' => 1),
				'enablePospectorIntegration'         => array('property'=>'enablePospectorIntegration',         'type'=>'checkbox', 'label'=>'Enable '.$innReachEncoreName.' Integration', 'description' => 'Whether or not '.$innReachEncoreName.' Integrations should be displayed for this library.', 'hideInLists' => true, 'default' => 1),
				'showProspectorResultsAtEndOfSearch' => array('property'=>'showProspectorResultsAtEndOfSearch', 'type'=>'checkbox', 'label'=>'Show '.$innReachEncoreName.' Results At End Of Search', 'description' => 'Whether or not '.$innReachEncoreName.' Search Results should be shown at the end of search results.', 'hideInLists' => true, 'default' => 1),
				//'prospectorCode'                   => array('property'=>'prospectorCode', 'type'=>'text', 'label'=>'Prospector Code', 'description'=>'The code used to identify this location within Prospector. Leave blank if items for this location are not in Prospector.', 'hideInLists' => true,),
				// No references in pika code. pascal 8-24-2018
			)),
			'worldCatSection' => array('property'=>'worldCatSection', 'type' => 'section', 'label' =>'WorldCat', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1z6krQ9bf8qSEcYnWWbHA_EZsJyp9gXf9QYiZYf964w8', 'properties' => array(
				'repeatInWorldCat'  => array('property'=>'repeatInWorldCat', 'type'=>'checkbox', 'label'=>'Repeat In WorldCat', 'description'=>'Turn on to allow repeat search in WorldCat functionality.', 'hideInLists' => true,),
				'worldCatUrl' => array('property'=>'worldCatUrl', 'type'=>'text', 'label'=>'WorldCat URL', 'description'=>'A custom World Cat URL to use while searching.', 'hideInLists' => true, 'size'=>'80'),
				'worldCatQt' => array('property'=>'worldCatQt', 'type'=>'text', 'label'=>'WorldCat QT', 'description'=>'A custom World Cat QT term to use while searching.', 'hideInLists' => true, 'size'=>'40'),
			)),
			)),

			'goldrushSection' => array('property'=>'goldrushSection', 'type' => 'section', 'label' =>'Gold Rush', 'hideInLists' => true,
			                           'helpLink' => 'https://docs.google.com/document/d/1OfVcwdalgi8YNEqTAXXv7Oye15eQwxGGKX5IIaeuT7U', 'properties' => array(
					'goldRushCode'  => array('property'=>'goldRushCode', 'type'=>'text', 'label'=>'Gold Rush Inst Code', 'description'=>'The INST Code to use with Gold Rush.  Leave blank to not link to Gold Rush.', 'hideInLists' => true,),
				)),

			'overdriveSection' => array('property'=>'overdriveSection', 'type' => 'section', 'label' =>'OverDrive', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1HG7duKI4-gbOlgDvMlQrib52LV0BBUhzGD7Q69QLziM', 'properties' => array(
				'enableOverdriveCollection'      => array('property'=>'enableOverdriveCollection', 'type'=>'checkbox', 'label'=>'Enable Overdrive Collection', 'description'=>'Whether or not titles from the Overdrive collection should be included in searches', 'hideInLists' => true),
				'sharedOverdriveCollection'      => array('property'=>'sharedOverdriveCollection', 'type'=>'enum',     'label'=>'Shared Overdrive Collection', 'description'=>'Which shared Overdrive collection should be included in searches', 'hideInLists' => true, 'values' => $sharedOverdriveCollectionChoices, 'default' => -1),
				'includeOverDriveAdult'          => array('property'=>'includeOverDriveAdult',     'type'=>'checkbox', 'label'=>'Include Adult Titles', 'description'=>'Whether or not adult titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true),
				'includeOverDriveTeen'           => array('property'=>'includeOverDriveTeen',      'type'=>'checkbox', 'label'=>'Include Teen Titles', 'description'=>'Whether or not teen titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true),
				'includeOverDriveKids'           => array('property'=>'includeOverDriveKids',      'type'=>'checkbox', 'label'=>'Include Kids Titles', 'description'=>'Whether or not kids titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true),
				'repeatInOverdrive'              => array('property'=>'repeatInOverdrive',         'type'=>'checkbox', 'label'=>'Repeat In Overdrive', 'description'=>'Turn on to allow repeat search in Overdrive functionality.', 'hideInLists' => true, 'default' => 0),
				'overdriveAuthenticationILSName' => array('property'=>'overdriveAuthenticationILSName', 'type'=>'text', 'label'=>'The ILS Name Overdrive uses for user Authentication', 'description'=>'The name of the ILS that OverDrive uses to authenticate users logging into the Overdrive website.', 'size'=>'20', 'hideInLists' => true),
				'overdriveRequirePin'            => array('property'=>'overdriveRequirePin',        'type'=>'checkbox', 'label'=>'Is a Pin Required to log into Overdrive website?', 'description'=>'Turn on to allow repeat search in Overdrive functionality.', 'hideInLists' => true, 'default' => 0),
				'overdriveAdvantageName'         => array('property'=>'overdriveAdvantageName',     'type'=>'text',     'label'=>'Overdrive Advantage Name', 'description'=>'The name of the OverDrive Advantage account if any.', 'size'=>'80', 'hideInLists' => true,),
				'overdriveAdvantageProductsKey'  => array('property'=>'overdriveAdvantageProductsKey', 'type'=>'text', 'label'=>'Overdrive Advantage Products Key', 'description'=>'The products key for use when building urls to the API from the advantageAccounts call.', 'size'=>'80', 'hideInLists' => false,),
			)),
			'hooplaSection' => array('property'=>'hooplaSection', 'type' => 'section', 'label' =>'Hoopla', 'hideInLists' => true,
//					'helpLink'=>'',
				'properties' => array(
				'hooplaLibraryID'      => array('property'=>'hooplaLibraryID', 'type'=>'integer', 'label'=>'Hoopla Library ID', 'description'=>'The ID Number Hoopla uses for this library', 'hideInLists' => true),
				'hooplaMaxPrice'       => array('property'=>'hooplaMaxPrice',  'type'=>'integer', 'label'=>'Hoopla Max. Price', 'description'=>'The maximum price per use to include in search results. (0 = include everything)', 'min' => 0, 'step' => "0.01", 'hideInLists' => true),
					)),
			'archiveSection' => array('property'=>'archiveSection', 'type' => 'section', 'label' =>'Local Content Archive', 'hideInLists' => true, 'helpLink'=>'https://docs.google.com/a/marmot.org/document/d/128wrNtZu_sUqm2_NypC6Sx8cOvM2cdmeOUDp0hUhQb4/edit?usp=sharing_eid&ts=57324e27', 'properties' => array(
					'enableArchive'                        => array('property'=>'enableArchive', 'type'=>'checkbox', 'label'=>'Allow Searching the Archive', 'description'=>'Whether or not information from the archive is shown in Pika.', 'hideInLists' => true, 'default' => 0),
					'archiveNamespace'                     => array('property'=>'archiveNamespace', 'type'=>'text', 'label'=>'Archive Namespace', 'description'=>'The namespace of your library in the archive', 'hideInLists' => true, 'maxLength' => 30, 'size'=>'30'),
					'archivePid'                           => array('property'=>'archivePid', 'type'=>'text', 'label'=>'Organization PID for Library', 'description'=>'A link to a representation of the library in the archive', 'hideInLists' => true, 'maxLength' => 50, 'size'=>'50'),
					'hideAllCollectionsFromOtherLibraries' => array('property'=>'hideAllCollectionsFromOtherLibraries', 'type'=>'checkbox', 'label'=>'Hide Collections from Other Libraries', 'description'=>'Whether or not collections created by other libraries is shown in Pika.', 'hideInLists' => true, 'default' => 0),
					'collectionsToHide'                    => array('property'=>'collectionsToHide', 'type'=>'textarea', 'label'=>'Collections To Hide', 'description'=>'Specific collections to hide.', 'hideInLists' => true),
					'objectsToHide'                        => array('property'=>'objectsToHide', 'type'=>'textarea', 'label'=>'Objects To Hide', 'description'=>'Specific objects to hide.', 'hideInLists' => true),
					'defaultArchiveCollectionBrowseMode'   => array('property' => 'defaultArchiveCollectionBrowseMode', 'type' => 'enum', 'label'=>'Default Viewing Mode for Archive Collections (Exhibits)', 'description' => 'Sets how archive collections will be displayed by default when users haven\'t chosen a mode themselves.', 'hideInLists' => true,
					                             'values'=> array('covers' => 'Show Covers', 'list' => 'Show List'), 'default' => 'covers'),

					'archiveMoreDetailsSection' => array('property'=>'archiveMoreDetailsSection', 'type' => 'section', 'label' => 'Archive More Details ', 'hideInLists' => true,
					                                     /* 'helpLink'=>'https://docs.google.com/document/d/1ZZsoKW2NOfGMad36BkWeF5ROqH5Wyg5up3eIhki5Lec',*/ 'properties' => array(
							'archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode' => array('property' => 'archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode', 'label' => 'Related Object/Entity Sections Display Mode', 'type' => 'enum', 'values' => self::$archiveMoreDetailsDisplayModeOptions, 'default' => 'tiled', 'description' => 'How related objects and entities will be displayed in the More Details accordion on Archive pages.'),

							'archiveMoreDetailsOptions' => array(
								'property' => 'archiveMoreDetailsOptions',
								'type' => 'oneToMany',
								'label' => 'More Details Configuration',
								'description' => 'Configuration for the display of the More Details accordion for archive object views',
								'keyThis' => 'libraryId',
								'keyOther' => 'libraryId',
								'subObjectType' => 'LibraryArchiveMoreDetails',
								'structure' => $libraryArchiveMoreDetailsStructure,
								'sortable' => true,
								'storeDb' => true,
								'allowEdit' => true,
								'canEdit' => false,
								'additionalOneToManyActions' => array(
									0 => array(
										'text' => 'Reset Archive More Details To Default',
										'url' => '/Admin/Libraries?id=$id&amp;objectAction=resetArchiveMoreDetailsToDefault',
										'class' => 'btn-warning',
									)
								)
							),
						)),

					'archiveRequestSection' => array('property'=>'archiveRequestSection', 'type' => 'section', 'label' =>'Archive Copy Requests ', 'hideInLists' => true,
					                            /* 'helpLink'=>'https://docs.google.com/document/d/1ZZsoKW2NOfGMad36BkWeF5ROqH5Wyg5up3eIhki5Lec',*/ 'properties' => array(

							'allowRequestsForArchiveMaterials' => array('property'=>'allowRequestsForArchiveMaterials', 'type'=>'checkbox', 'label'=>'Allow Requests for Copies of Archive Materials', 'description'=>'Enable to allow requests for copies of your archive materials'),
							'archiveRequestMaterialsHeader' => array('property'=>'archiveRequestMaterialsHeader', 'type'=>'html', 'label'=>'Archive Request Header Text', 'description'=>'The text to be shown above the form for requests of copies for archive materials'),
							'claimAuthorshipHeader' => array('property'=>'claimAuthorshipHeader', 'type'=>'html', 'label'=>'Claim Authorship Header Text', 'description'=>'The text to be shown above the form when people try to claim authorship of archive materials'),
							'archiveRequestEmail' => array('property'=>'archiveRequestEmail', 'type'=>'email', 'label'=>'Email to send archive requests to', 'description'=>'The email address to send requests for archive materials to', 'hideInLists' => true),

							// Archive Form Fields
							'archiveRequestFieldName'           => array('property'=>'archiveRequestFieldName',           'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 2, 'label'=>'Copy Request Field : Name', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldAddress'        => array('property'=>'archiveRequestFieldAddress',        'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : Address', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldAddress2'       => array('property'=>'archiveRequestFieldAddress2',       'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : Address2', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldCity'           => array('property'=>'archiveRequestFieldCity',           'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : City', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldState'          => array('property'=>'archiveRequestFieldState',          'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : State', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldZip'            => array('property'=>'archiveRequestFieldZip',            'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : Zip Code', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldCountry'        => array('property'=>'archiveRequestFieldCountry',        'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : Country', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldPhone'          => array('property'=>'archiveRequestFieldPhone',          'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 2, 'label'=>'Copy Request Field : Phone', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldAlternatePhone' => array('property'=>'archiveRequestFieldAlternatePhone', 'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : Alternate Phone', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldFormat'         => array('property'=>'archiveRequestFieldFormat',         'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 1, 'label'=>'Copy Request Field : Format', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),
							'archiveRequestFieldPurpose'        => array('property'=>'archiveRequestFieldPurpose',        'type'=>'enum', 'values'=> self::$archiveRequestFormFieldOptions, 'default'=> 2, 'label'=>'Copy Request Field : Purpose', 'description'=>'Should this field be hidden, or displayed as an optional field or a required field'),

						)),

					'exploreMoreBar' => array(
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
						'additionalOneToManyActions' => array(
							0 => array(
								'text'  => 'Set Archive Explore More Options To Default',
								'url'   => '/Admin/Libraries?id=$id&amp;objectAction=defaultArchiveExploreMoreOptions',
								'class' => 'btn-warning',
							)
						)
					),

					'archiveSearchFacets' => array(
						'property' => 'archiveSearchFacets',
						'type' => 'oneToMany',
						'label' => 'Archive Search Facets',
						'description' => 'A list of facets to display in archive search results',
//						'helpLink' => '',
						'keyThis' => 'libraryId',
						'keyOther' => 'libraryId',
						'subObjectType' => 'LibraryArchiveSearchFacetSetting',
						'structure' => $archiveSearchfacetSettingStructure,
						'sortable' => true,
						'storeDb' => true,
						'allowEdit' => true,
						'canEdit' => true,
						'additionalOneToManyActions' => array(
							array(
								'text' => 'Copy Library Archive Search Facets',
								'url' => '/Admin/Libraries?id=$id&amp;objectAction=copyArchiveSearchFacetsFromLibrary',
							),
							array(
								'text' => 'Reset Archive Search Facets To Default',
								'url' => '/Admin/Libraries?id=$id&amp;objectAction=resetArchiveSearchFacetsToDefault',
								'class' => 'btn-warning',
							),
						)
					),
			)),

			'edsSection' => array('property'=>'edsSection', 'type' => 'section', 'label' =>'EBSCO EDS', 'hideInLists' => true, 'properties' => array(
					'edsApiProfile' => array('property'=>'edsApiProfile', 'type'=>'text', 'label'=>'EDS API Profile', 'description'=>'The profile to use when connecting to the EBSCO API', 'hideInLists' => true),
					'edsSearchProfile' => array('property'=>'edsSearchProfile', 'type'=>'text', 'label'=>'EDS Search Profile', 'description'=>'The profile to use when linking to EBSCO EDS', 'hideInLists' => true),
					'edsApiUsername' => array('property'=>'edsApiUsername', 'type'=>'text', 'label'=>'EDS API Username', 'description'=>'The username to use when connecting to the EBSCO API', 'hideInLists' => true),
					'edsApiPassword' => array('property'=>'edsApiPassword', 'type'=>'text', 'label'=>'EDS API Password', 'description'=>'The password to use when connecting to the EBSCO API', 'hideInLists' => true),
			)),

			'casSection' => array('property'=>'casSection', 'type' => 'section', 'label' =>'CAS Single Sign On', 'hideInLists' => true, 'helpLink'=>'https://docs.google.com/document/d/1KQ_RMVvHhB2ulTyXnGF7rJXUQuzbL5RVTtnqlXdoNTk/edit?usp=sharing', 'properties' => array(
					'casHost' => array('property'=>'casHost', 'type'=>'text', 'label'=>'CAS Host', 'description'=>'The host to use for CAS authentication', 'hideInLists' => true),
					'casPort' => array('property'=>'casPort', 'type'=>'integer', 'label'=>'CAS Port', 'description'=>'The port to use for CAS authentication (typically 443)', 'hideInLists' => true),
					'casContext' => array('property'=>'casContext', 'type'=>'text', 'label'=>'CAS Context', 'description'=>'The context to use for CAS', 'hideInLists' => true),
			)),

			'dplaSection' => array('property'=>'dplaSection', 'type' => 'section', 'label' =>'DPLA', 'hideInLists' => true, 'helpLink'=> 'https://docs.google.com/document/d/1I6RuNhKNwDJOMpM63a4V5Lm0URgWp23465HegEIkP_w', 'properties' => array(
				'includeDplaResults' => array('property'=>'includeDplaResults', 'type'=>'checkbox', 'label'=>'Include DPLA content in search results', 'description'=>'Whether or not DPLA data should be included for this library.', 'hideInLists' => true, 'default' => 0),
			)),

			'holidays' => array(
				'property' => 'holidays',
				'type' => 'oneToMany',
				'label' => 'Holidays',
				'description' => 'Holidays',
				'helpLink' => 'https://docs.google.com/document/d/12UGkTOZja5p_ms9IuqfGj4ruJk-OvousGXWvN870Qbc',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'Holiday',
				'structure' => $holidaysStructure,
				'sortable' => false,
				'storeDb' => true
			),

			'libraryLinks' => array(
				'property' => 'libraryLinks',
				'type' => 'oneToMany',
				'label' => 'Sidebar Links',
				'description' => 'Links To Show in the sidebar',
				'helpLink' => 'https://docs.google.com/document/d/1wEzrwkxLCeykNcX_1-0Jd1Acw251L05IoZ9ipV-HaWA',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryLink',
				'structure' => $libraryLinksStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => true,
				'canEdit' => true,
			),

			'libraryTopLinks' => array(
				'property' => 'libraryTopLinks',
				'type' => 'oneToMany',
				'label' => 'Header Links',
				'description' => 'Links To Show in the header',
				'helpLink' => 'https://docs.google.com/document/d/1wEzrwkxLCeykNcX_1-0Jd1Acw251L05IoZ9ipV-HaWA',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryTopLinks',
				'structure' => $libraryTopLinksStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
			),

			'recordsOwned' => array(
				'property' => 'recordsOwned',
				'type' => 'oneToMany',
				'label' => 'Records Owned',
				'description' => 'Information about what records are owned by the library',
				'helpLink' => 'https://docs.google.com/document/d/1pFio8rYsgR5QVzZJfvceWwJ5aCF9eaoEJ0lunV_9CE0',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryRecordOwned',
				'structure' => $libraryRecordOwnedStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
			),

			'recordsToInclude' => array(
				'property' => 'recordsToInclude',
				'type' => 'oneToMany',
				'label' => 'Records To Include',
				'description' => 'Information about what records to include in this scope',
				'helpLink' => 'https://docs.google.com/document/d/1pFio8rYsgR5QVzZJfvceWwJ5aCF9eaoEJ0lunV_9CE0',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryRecordToInclude',
				'structure' => $libraryRecordToIncludeStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
			),
		);

		if (UserAccount::userHasRole('libraryManager')){
			$structure['subdomain']['type'] = 'label';
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
			unset($structure['goldrushSection']);
			unset($structure['prospectorSection']);
			unset($structure['worldCatSection']);
			unset($structure['overdriveSection']);
			unset($structure['archiveSection']);
			unset($structure['edsSection']);
			unset($structure['dplaSection']);
			unset($structure['facets']);
			unset($structure['recordsOwned']);
			unset($structure['recordsToInclude']);
		}
		return $structure;
	}

	static $searchLibrary  = array();
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
			} else if ($scopingSetting == 'local' || $scopingSetting == 'econtent' || $scopingSetting == 'library' || $scopingSetting == 'location'){
				Library::$searchLibrary[$searchSource] = Library::getActiveLibrary();
			}else if ($scopingSetting == 'marmot' || $scopingSetting == 'unscoped'){
				//Get the default library
				$library = new Library();
				$library->isDefault = true;
				$library->find();
				if ($library->N > 0){
					$library->fetch();
					Library::$searchLibrary[$searchSource] = clone($library);
				}else{
					Library::$searchLibrary[$searchSource] = null;
				}
			}else{
				$location = Location::getSearchLocation();
				if (is_null($location)){
					//Check to see if we have a library for the subdomain
					$library = new Library();
					$library->subdomain = $scopingSetting;
					$library->find();
					if ($library->N > 0){
						$library->fetch();
						Library::$searchLibrary[$searchSource] = clone($library);
						return clone($library);
					}else{
						Library::$searchLibrary[$searchSource] = null;
					}
				}else{
					Library::$searchLibrary[$searchSource] = self::getLibraryForLocation($location->locationId);
				}
			}
		}
		return Library::$searchLibrary[$searchSource];
	}

	static function getActiveLibrary(){
		global $library;
		//First check to see if we have a library loaded based on subdomain (loaded in index)
		if (isset($library)) {
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

	static function getPatronHomeLibrary($tmpUser = null){
		//Finally check to see if the user has logged in and if so, use that library
		if ($tmpUser != null){
			return self::getLibraryForLocation($tmpUser->homeLocationId);
		}
		if (UserAccount::isLoggedIn()){
			//Load the library based on the home branch for the user
			return self::getLibraryForLocation(UserAccount::getUserHomeLocationId());
		}else{
			return null;
		}

	}

	static function getLibraryForLocation($locationId){
		if (isset($locationId)){
			$libLookup = new Library();
			require_once(ROOT_DIR . '/Drivers/marmot_inc/Location.php');
			$libLookup->whereAdd('libraryId = (SELECT libraryId FROM location WHERE locationId = ' . $libLookup->escape($locationId) . ')');
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
		if ($name == "holidays") {
			if (!isset($this->holidays) && $this->libraryId){
				$this->holidays = array();
				$holiday = new Holiday();
				$holiday->libraryId = $this->libraryId;
				$holiday->orderBy('date');
				$holiday->find();
				while($holiday->fetch()){
					$this->holidays[$holiday->id] = clone($holiday);
				}
			}
			return $this->holidays;
		}elseif ($name == "moreDetailsOptions") {
			if (!isset($this->moreDetailsOptions) && $this->libraryId){
				$this->moreDetailsOptions = array();
				$moreDetailsOptions = new LibraryMoreDetails();
				$moreDetailsOptions->libraryId = $this->libraryId;
				$moreDetailsOptions->orderBy('weight');
				$moreDetailsOptions->find();
				while($moreDetailsOptions->fetch()){
					$this->moreDetailsOptions[$moreDetailsOptions->id] = clone($moreDetailsOptions);
				}
			}
			return $this->moreDetailsOptions;
		}elseif ($name == "archiveMoreDetailsOptions") {
			if (!isset($this->archiveMoreDetailsOptions) && $this->libraryId){
				$this->archiveMoreDetailsOptions = array();
				$moreDetailsOptions = new LibraryArchiveMoreDetails();
				$moreDetailsOptions->libraryId = $this->libraryId;
				$moreDetailsOptions->orderBy('weight');
				$moreDetailsOptions->find();
				while($moreDetailsOptions->fetch()){
					$this->archiveMoreDetailsOptions[$moreDetailsOptions->id] = clone($moreDetailsOptions);
				}
			}
			return $this->archiveMoreDetailsOptions;
		}elseif ($name == "facets") {
			if (!isset($this->facets) && $this->libraryId){
				$this->facets = array();
				$facet = new LibraryFacetSetting();
				$facet->libraryId = $this->libraryId;
				$facet->orderBy('weight');
				$facet->find();
				while($facet->fetch()){
					$this->facets[$facet->id] = clone($facet);
				}
			}
			return $this->facets;
		}elseif ($name == "archiveSearchFacets") {
			if (!isset($this->archiveSearchFacets) && $this->libraryId){
				$this->archiveSearchFacets = array();
				$facet = new LibraryArchiveSearchFacetSetting();
				$facet->libraryId = $this->libraryId;
				$facet->orderBy('weight');
				$facet->find();
				while($facet->fetch()){
					$this->archiveSearchFacets[$facet->id] = clone($facet);
				}
			}
			return $this->archiveSearchFacets;
		}elseif ($name == 'libraryLinks'){
			if (!isset($this->libraryLinks) && $this->libraryId){
				$this->libraryLinks = array();
				$libraryLink = new LibraryLink();
				$libraryLink->libraryId = $this->libraryId;
				$libraryLink->orderBy('weight');
				$libraryLink->find();
				while ($libraryLink->fetch()){
					$this->libraryLinks[$libraryLink->id] = clone($libraryLink);
				}
			}
			return $this->libraryLinks;
		}elseif ($name == 'libraryTopLinks'){
			if (!isset($this->libraryTopLinks) && $this->libraryId){
				$this->libraryTopLinks = array();
				$libraryLink = new LibraryTopLinks();
				$libraryLink->libraryId = $this->libraryId;
				$libraryLink->orderBy('weight');
				$libraryLink->find();
				while($libraryLink->fetch()){
					$this->libraryTopLinks[$libraryLink->id] = clone($libraryLink);
				}
			}
			return $this->libraryTopLinks;
		}elseif ($name == 'recordsOwned'){
			if (!isset($this->recordsOwned) && $this->libraryId){
				$this->recordsOwned = array();
				$object = new LibraryRecordOwned();
				$object->libraryId = $this->libraryId;
				$object->find();
				while($object->fetch()){
					$this->recordsOwned[$object->id] = clone($object);
				}
			}
			return $this->recordsOwned;
		}elseif ($name == 'recordsToInclude'){
			if (!isset($this->recordsToInclude) && $this->libraryId){
				$this->recordsToInclude = array();
				$object = new LibraryRecordToInclude();
				$object->libraryId = $this->libraryId;
				$object->orderBy('weight');
				$object->find();
				while($object->fetch()){
					$this->recordsToInclude[$object->id] = clone($object);
				}
			}
			return $this->recordsToInclude;
		}elseif  ($name == 'browseCategories') {
			if (!isset($this->browseCategories) && $this->libraryId) {
				$this->browseCategories    = array();
				$browseCategory            = new LibraryBrowseCategory();
				$browseCategory->libraryId = $this->libraryId;
				$browseCategory->orderBy('weight');
				$browseCategory->find();
				while ($browseCategory->fetch()) {
					$this->browseCategories[$browseCategory->id] = clone($browseCategory);
				}
			}
			return $this->browseCategories;
		}elseif ($name == 'materialsRequestFieldsToDisplay') {
			if (!isset($this->materialsRequestFieldsToDisplay) && $this->libraryId) {
				$this->materialsRequestFieldsToDisplay = array();
				$materialsRequestFieldsToDisplay = new MaterialsRequestFieldsToDisplay();
				$materialsRequestFieldsToDisplay->libraryId = $this->libraryId;
				$materialsRequestFieldsToDisplay->orderBy('weight');
				if ($materialsRequestFieldsToDisplay->find()) {
					while ($materialsRequestFieldsToDisplay->fetch()) {
						$this->materialsRequestFieldsToDisplay[$materialsRequestFieldsToDisplay->id] = clone $materialsRequestFieldsToDisplay;
					}
				}
				return $this->materialsRequestFieldsToDisplay;
			}
		}elseif ($name == 'materialsRequestFormats') {
			if (!isset($this->materialsRequestFormats) && $this->libraryId) {
				$this->materialsRequestFormats = array();
				$materialsRequestFormats = new MaterialsRequestFormats();
				$materialsRequestFormats->libraryId = $this->libraryId;
				$materialsRequestFormats->orderBy('weight');
				if ($materialsRequestFormats->find()) {
					while ($materialsRequestFormats->fetch()) {
						$this->materialsRequestFormats[$materialsRequestFormats->id] = clone $materialsRequestFormats;
					}
				}
				return $this->materialsRequestFormats;
			}
		}elseif ($name == 'materialsRequestFormFields') {
			if (!isset($this->materialsRequestFormFields) && $this->libraryId) {
				$this->materialsRequestFormFields = array();
				$materialsRequestFormFields = new MaterialsRequestFormFields();
				$materialsRequestFormFields->libraryId = $this->libraryId;
				$materialsRequestFormFields->orderBy('weight');
				if ($materialsRequestFormFields->find()) {
					while ($materialsRequestFormFields->fetch()) {
						$this->materialsRequestFormFields[$materialsRequestFormFields->id] = clone $materialsRequestFormFields;
					}
				}
				return $this->materialsRequestFormFields;
			}
		}elseif ($name == 'exploreMoreBar') {
			if (!isset($this->exploreMoreBar) && $this->libraryId) {
				$this->exploreMoreBar = array();
				$exploreMoreBar = new ArchiveExploreMoreBar();
				$exploreMoreBar->libraryId = $this->libraryId;
				$exploreMoreBar->orderBy('weight');
				if ($exploreMoreBar->find()) {
					while ($exploreMoreBar->fetch()) {
						$this->exploreMoreBar[$exploreMoreBar->id] = clone $exploreMoreBar;
					}
				}
				return $this->exploreMoreBar;
			}
		}elseif ($name == 'combinedResultSections') {
			if (!isset($this->combinedResultSections) && $this->libraryId){
				$this->combinedResultSections = array();
				$combinedResultSection = new LibraryCombinedResultSection();
				$combinedResultSection->libraryId = $this->libraryId;
				$combinedResultSection->orderBy('weight');
				if ($combinedResultSection->find()) {
					while ($combinedResultSection->fetch()) {
						$this->combinedResultSections[$combinedResultSection->id] = clone $combinedResultSection;
					}
				}
				return $this->combinedResultSections;
			}
		}elseif ($name == 'patronNameDisplayStyle'){
			return $this->patronNameDisplayStyle;
		}else{
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
		if ($return) {
			if (isset($this->showInMainDetails) && is_string($this->showInMainDetails) && !empty($this->showInMainDetails)) {
				// convert to array retrieving from database
				try{
					$this->showInMainDetails = unserialize($this->showInMainDetails);
					if (!$this->showInMainDetails) $this->showInMainDetails = array();
				}catch (Exception $e){
					global $logger;
					$logger->log("Error loading $this->libraryId $e", PEAR_LOG_DEBUG);
				}

			}
			elseif (empty($this->showInMainDetails)) {
				// when a value is not set, assume set to show all options, eg null = all
				$default = self::$showInMainDetailsOptions;
				// remove options below that aren't to be part of the default
				unset($default['showISBNs']);
				$default = array_keys($default);
				$this->showInMainDetails = $default;
			}
			if (isset($this->showInSearchResultsMainDetails) && is_string($this->showInSearchResultsMainDetails) && !empty($this->showInSearchResultsMainDetails)) {
				// convert to array retrieving from database
				$this->showInSearchResultsMainDetails = unserialize($this->showInSearchResultsMainDetails);
				if (!$this->showInSearchResultsMainDetails) $this->showInSearchResultsMainDetails = array();
			}
		}
		return $return;
	}
	/**
	 * Override the update functionality to save related objects
	 *
	 * @see DB/DB_DataObject::update()
	 */
	public function update(){
		if (isset($this->showInMainDetails) && is_array($this->showInMainDetails)) {
			// convert array to string before storing in database
			$this->showInMainDetails = serialize($this->showInMainDetails);
		}
		if (isset($this->showInSearchResultsMainDetails) && is_array($this->showInSearchResultsMainDetails)) {
			// convert array to string before storing in database
			$this->showInSearchResultsMainDetails = serialize($this->showInSearchResultsMainDetails);
		}
		$ret = parent::update();
		if ($ret !== FALSE ){
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
		}
		if ($this->patronNameDisplayStyleChanged){
			$libraryLocations = new Location();
			$libraryLocations->libraryId = $this->libraryId;
			$libraryLocations->find();
			while ($libraryLocations->fetch()){
				$user = new User();
				$numChanges = $user->query("update user set displayName = '' where homeLocationId = {$libraryLocations->locationId}");

			}
		}
		// Do this last so that everything else can update even if we get an error here
		$deleteCheck = $this->saveMaterialsRequestFormats();
		if (PEAR::isError($deleteCheck)) {
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
		if (isset($this->showInMainDetails) && is_array($this->showInMainDetails)) {
			// convert array to string before storing in database
			$this->showInMainDetails = serialize($this->showInMainDetails);
		}
		if (isset($this->showInSearchResultsMainDetails) && is_array($this->showInSearchResultsMainDetails)) {
			// convert array to string before storing in database
			$this->showInSearchResultsMainDetails = serialize($this->showInSearchResultsMainDetails);
		}
		$ret = parent::insert();
		if ($ret !== FALSE ){
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
					if (!$deleteCheck) {
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

	private function saveOneToManyOptions($oneToManySettings) {
		foreach ($oneToManySettings as $oneToManyDBObject){
			if (isset($oneToManyDBObject->deleteOnSave) && $oneToManyDBObject->deleteOnSave == true){
				$oneToManyDBObject->delete();
			}else{
				if (isset($oneToManyDBObject->id) && is_numeric($oneToManyDBObject->id)){ // (negative ids need processed with insert)
					$oneToManyDBObject->update();
				}else{
					$oneToManyDBObject->libraryId = $this->libraryId;
					$oneToManyDBObject->insert();
				}
			}
		}
	}

	private function clearOneToManyOptions($oneToManyDBObjectClassName) {
		$oneToManyDBObject = new $oneToManyDBObjectClassName();
		$oneToManyDBObject->libraryId = $this->libraryId;
		$oneToManyDBObject->delete();

	}

	private function saveExploreMoreBar() {
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
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;


		if ($configArray['Index']['enableDetailedAvailability']){
			$facet = new LibraryFacetSetting();
			$facet->setupTopFacet('availability_toggle', 'Available?', true);
			$facet->libraryId = $libraryId;
			$facet->weight = count($defaultFacets) + 1;
			$defaultFacets[] = $facet;
		}


		if (!$configArray['Index']['enableDetailedAvailability']){
			$facet = new LibraryFacetSetting();
			$facet->setupSideFacet('available_at', 'Available Now At', true);
			$facet->libraryId = $libraryId;
			$facet->weight = count($defaultFacets) + 1;
			$defaultFacets[] = $facet;
		}

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('format', 'Format', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('literary_form_full', 'Literary Form', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('target_audience_full', 'Reading Level', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$facet->numEntriesToShowByDefault = 8;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('topic_facet', 'Subject', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('time_since_added', 'Added in the Last', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('authorStr', 'Author', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('awards_facet', 'Awards', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('econtent_device', 'Compatible Device', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('econtent_source', 'eContent Source', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('econtent_protection_type', 'eContent Protection', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('era', 'Era', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('genre_facet', 'Genre', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('itype', 'Item Type', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('language', 'Language', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('lexile_code', 'Lexile code', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('lexile_score', 'Lexile measure', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('mpaa_rating', 'Movie Rating', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('owning_library', 'Owning System', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('owning_location', 'Owning Branch', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('publishDate', 'Publication Date', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('geographic_facet', 'Region', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('rating_facet', 'User Rating', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		return $defaultFacets;
	}

	static function getDefaultArchiveSearchFacets($libraryId = -1) {
		$defaultFacets = array();
		$defaultFacetsList = LibraryArchiveSearchFacetSetting::$defaultFacetList;
		foreach ($defaultFacetsList as $facetName => $facetDisplayName){
			$facet = new LibraryArchiveSearchFacetSetting();
			$facet->setupSideFacet($facetName, $facetDisplayName, false);
			$facet->libraryId = $libraryId;
			$facet->collapseByDefault = true;
			$facet->weight = count($defaultFacets) + 1;
			$defaultFacets[] = $facet;
		}

		return $defaultFacets;
	}

	public function getNumLocationsForLibrary(){
		$location = new Location;
		$location->libraryId = $this->libraryId;
		return $location->count();
	}

	public function getArchiveRequestFormStructure() {
		$defaultForm = ArchiveRequest::getObjectStructure();
		foreach ($defaultForm as $index => &$formfield) {
			$libraryPropertyName = 'archiveRequestField' . ucfirst($formfield['property']);
			if (isset($this->$libraryPropertyName)) {
				$setting = is_null($this->$libraryPropertyName) ? $formfield['default'] : $this->$libraryPropertyName;
				switch ($setting) {
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

}
