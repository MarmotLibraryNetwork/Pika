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

require_once ROOT_DIR . '/sys/mobileesp/mobile_device_detect.php';

// Smarty Extension class
class UInterface extends Smarty {
	public $lang;
	private $pikaTheme; // which theme(s) are active?
	private $themes;    // The themes that are active
	private $isMobile;  // Leave unset till isMobile() is called
	private $url;

	function __construct(){
		parent::__construct();

		global $configArray;
		global $timer;

		global $library;
		if (!empty($library)){
			$this->pikaTheme = $library->themeName;
		}
		$this->pikaTheme .= ',' . $configArray['Site']['theme']
			. ',responsive,default'; //Make sure we always fall back to the default theme so a template does not have to be overridden.

		// Check to see if multiple themes were requested; if so, build an array,
		// otherwise, store a single string.
		$themeArray = array_unique(explode(',', $this->pikaTheme));
		$local      = $configArray['Site']['local'];
		if (count($themeArray) > 1){
			$this->template_dir = array();
			foreach ($themeArray as $currentTheme){
				$currentTheme         = trim($currentTheme);
				$this->template_dir[] = "$local/interface/themes/$currentTheme";
			}
		}else{
			$this->template_dir = "$local/interface/themes/{$this->pikaTheme}";
		}
		$this->themes    = $themeArray;
		$this->pikaTheme = implode(',', $themeArray);

		if (isset($timer)){
			$timer->logTime('Set theme');
		}

		// Create an MD5 hash of the theme name -- this will ensure that it's a
		// writeable directory name (since some config.ini settings may include
		// problem characters like commas or whitespace).
		$md5               = md5($this->pikaTheme);
		$this->compile_dir = "$local/interface/compile/$md5";
		if (!is_dir($this->compile_dir)){
			if (!mkdir($this->compile_dir)){
				die("Could not create compile directory {$this->compile_dir}");
			}
		}
		$this->cache_dir = "$local/interface/cache/$md5";
		if (!is_dir($this->cache_dir)){
			if (!mkdir($this->cache_dir)){
				die("Could not create cache directory {$this->cache_dir}");
			}
		}

		$this->plugins_dir = array('plugins', "$local/interface/plugins", 'Smarty/plugins');
		// TODO: The correct setting for caching is 0, 1 or 2
		// 0 will turn caching off. Not sure what a false value will do.
		$this->caching       = false;
		$this->debugging     = false;
		$this->compile_check = true;
		// debugging
		if (!empty($configArray['System']['debug'])){
			if (isset($configArray['System']['debugTemplates'])){
				$this->debugging = (bool)$configArray['System']['debugTemplates'];
				$this->assign('deviceName', get_device_name()); // footer, only displayed when debug is on
			}
		}

		// todo: this only needs to happen in local and test
		if ((bool)$configArray['Site']['isProduction'] === false){
		$this->compile_check = true;
		}

		$this->register_block('display_if_inconsistent', 'display_if_inconsistent');
//		$this->register_block('display_if_inconsistent_in_any_manifestation', 'display_if_inconsistent_in_any_manifestation');
		$this->register_block('display_if_set', 'display_if_set');
		$this->register_function('translate', 'translate');
//		$this->register_function('char', 'char');

		$this->assign('fullPath', str_replace('&', '&amp;', $_SERVER['REQUEST_URI']));
		$url       = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
		$url       .= $_SERVER['SERVER_NAME'];
		$this->url = $url;
		$this->assign('url', $url);

		if (isset($configArray['Islandora']['repositoryUrl'])){
			$this->assign('repositoryUrl', $configArray['Islandora']['repositoryUrl']);
			$this->assign('encodedRepositoryUrl', str_replace('/', '\/', $configArray['Islandora']['repositoryUrl']));
		}

		$this->assign('siteTitle', $configArray['Site']['title']);
		if (isset($configArray['Site']['libraryName'])){
			$this->assign('consortiumName', $configArray['Site']['libraryName']);
		}
		if (isset($configArray['Site']['email'])){
			$this->assign('supportEmail', $configArray['Site']['email']);
		}
		$this->assign('ils', $configArray['Catalog']['ils']);


		// Determine Offline Mode
		global $offlineMode;
		$offlineMode = false;
		if ($configArray['Catalog']['offline']){
			$offlineMode = true;
			if (isset($configArray['Catalog']['enableLoginWhileOffline'])){
				$this->assign('enableLoginWhileOffline', $configArray['Catalog']['enableLoginWhileOffline']);
			}else{
				$this->assign('enableLoginWhileOffline', false);
			}
		}elseif (!empty($configArray['Catalog']['enableLoginWhileOffline'])){
			// unless offline login is enabled, don't check the offline mode system variable
			$offlineModeSystemVariable = new Variable();
			$offlineModeSystemVariable->get('name', 'offline_mode_when_offline_login_allowed');
			if ($offlineModeSystemVariable && (strtolower(trim($offlineModeSystemVariable->value)) == 'true' || trim($offlineModeSystemVariable->value) == '1')){
				$this->assign('enableLoginWhileOffline', true);
				$offlineMode = true;
			}
		}
		$this->assign('offline', $offlineMode);

		// Detect Internet Explorer 8 to include respond.js for responsive css support
		if (isset($_SERVER['HTTP_USER_AGENT'])){
			$ie8 = stristr($_SERVER['HTTP_USER_AGENT'], 'msie 8') || stristr($_SERVER['HTTP_USER_AGENT'], 'trident/5'); //trident/5 should catch ie9 compability modes
			$this->assign('ie8', $ie8);
		}


		/** @var IndexingProfile $activeRecordIndexingProfile */
		global $activeRecordIndexingProfile;
		if ($activeRecordIndexingProfile){
			$this->assign('activeRecordProfileModule', $activeRecordIndexingProfile->recordUrlComponent);
		}

		$timer->logTime('Interface basic configuration');

	}

	/**
	 *  Set template variables used in the My Account sidebar section dealing with fines.
	 */
	function setFinesRelatedTemplateVariables(){
		if (UserAccount::isLoggedIn()){
			$user = UserAccount::getLoggedInUser();
			//Figure out if we should show a link to pay fines.
//			$homeLibrary       = Library::getLibraryForLocation($user->homeLocationId);
			$homeLibrary       = $user->getHomeLibrary();
			$showECommerceLink = isset($homeLibrary) && $homeLibrary->showEcommerceLink == 1;
			if ($showECommerceLink){
				$this->assign('payFinesLinkText', $homeLibrary->payFinesLinkText);
				$this->assign('showRefreshAccountButton', $homeLibrary->showRefreshAccountButton);

				// Determine E-commerce Link
				$eCommerceLink = null;
				if ($homeLibrary->payFinesLink == 'default'){
					global $configArray;
					$defaultEcommerceLink = $configArray['Site']['ecommerceLink'];
					if (!empty($defaultEcommerceLink)){
						$eCommerceLink = $defaultEcommerceLink;
					}else{
						$showECommerceLink = false;
					}
				}elseif (!empty($homeLibrary->payFinesLink)){
					$eCommerceLink = $homeLibrary->payFinesLink;
				}else{
					$showECommerceLink = false;
				}
				$this->assign('ecommerceLink', $eCommerceLink);
			}
			$this->assign('showEcommerceLink', $showECommerceLink);
			$this->assign('minimumFineAmount', $homeLibrary->minimumFineAmount);
		}
	}

	public function getUrl(){
		return $this->url;
	}

	/**
	 * Get the current active theme setting.
	 *
	 * @access  public
	 * @return  string
	 */
	public function getVuFindTheme(){
		return $this->pikaTheme;
	}

	/*
	 * Get a list of themes that are active in the interface
	 *
	 * @return array
	 */
	public function getThemes(){
		return $this->themes;
	}

	function setTemplate($tpl){
		$this->assign('pageTemplate', $tpl);
	}

	function setPageTitle($title){
		//Marmot override, add the name of the site to the title unless we are using the mobile interface.
		$this->assign('pageTitleShort', translate($title)); //todo: combine pageTitleShort & shortPageTitle
		if ($this->isMobile()){
			$this->assign('pageTitle', translate($title));
		}else{
			$this->assign('pageTitle', translate($title) . ' | ' . $this->get_template_vars('librarySystemName'));
		}
	}

	function getShortPageTitle(){
		return $this->get_template_vars('shortPageTitle');
	}

	function getLanguage(){
		return $this->lang;
	}

	function setLanguage($lang){
		global $configArray;

		$this->lang = $lang;
		$this->assign('userLang', $lang);
		$this->assign('allLangs', $configArray['Languages']);
	}

	/**
	 * executes & returns or displays the template results
	 *
	 * @param string $resource_name
	 * @param string $cache_id
	 * @param string $compile_id
	 * @param boolean $display
	 *
	 * @return string
	 */
	function fetch($resource_name, $cache_id = null, $compile_id = null, $display = false){
		global $timer;
		$resource = parent::fetch($resource_name, $cache_id, $compile_id, $display);
		$timer->logTime("Finished fetching $resource_name");
		return $resource;
	}

	public function isMobile(){
		if (!isset($this->isMobile)){
			$this->isMobile = mobile_device_detect();
		}
		return $this->isMobile;
	}

	function loadDisplayOptions(){
		/** @var Library $library */
		global $library;
		global $locationSingleton;
		global $configArray;
		global $subdomain;
		global $offlineMode;

		$productionServer = $configArray['Site']['isProduction'];
		$this->assign('productionServer', $productionServer);

		// Debugging for web pages
		if (!empty($configArray['System']['debugJs'])){
			$this->assign('debugJs', true);
		}
		if (!empty($configArray['System']['debugCss'])){
			$this->assign('debugCss', true);
		}

		//Set System Message
		if ($configArray['System']['systemMessage']){
			$this->assign('systemMessage', $configArray['System']['systemMessage']);
			// Note Maintenance Mode depends on this
		}elseif ($offlineMode){
			$this->assign('systemMessage', "<p class='alert alert-warning'><strong>The circulation system is currently offline.</strong>  Access to account information and availability is limited.</p>");
		}elseif (!empty($library->systemMessage)){
			$this->assign('systemMessage', $library->systemMessage);
		}

		// Global Sidebar settings
		$displaySidebarMenu = false;
		if (isset($configArray['Site']['sidebarMenu'])){
			// config.ini setting can disable for entire site, or the library setting can turn off for its view.
			$displaySidebarMenu = ((bool)$configArray['Site']['sidebarMenu']) && $library->showSidebarMenu;
		}
		$this->assign('displaySidebarMenu', $displaySidebarMenu);

		$this->assign('showFines', $configArray['Catalog']['showFines']);
		$this->assign('showConvertListsFromClassic', $configArray['Catalog']['showConvertListsFromClassic']);


		// Global google settings
		//Figure out google translate id
		if (!empty($configArray['Translation']['google_translate_key'])){
			$this->assign('google_translate_key', $configArray['Translation']['google_translate_key']);
			$this->assign('google_included_languages', $configArray['Translation']['includedLanguages']);
		}

		//Check to see if we have a google site verification key
		if (!empty($configArray['Site']['google_verification_key'])){
			$this->assign('google_verification_key', $configArray['Site']['google_verification_key']);
		}

		//Set up google maps integrations
		if (!empty($configArray['Maps']['apiKey'])){
			$mapsKey = $configArray['Maps']['apiKey'];
			$this->assign('mapsKey', $mapsKey);
		}
		if (!empty($configArray['Maps']['browserKey'])){
			$mapsKey = $configArray['Maps']['browserKey'];
			$this->assign('mapsBrowserKey', $mapsKey);
		}

		// Google Analytics
		$googleAnalyticsId        = !empty($configArray['Analytics']['googleAnalyticsId']) ? $configArray['Analytics']['googleAnalyticsId'] : false;
		$googleAnalyticsLibraryId = !empty($library->gaTrackingId) ? $library->gaTrackingId : false;
		$googleAnalyticsLinkingId = !empty($configArray['Analytics']['googleAnalyticsLinkingId']) ? $configArray['Analytics']['googleAnalyticsLinkingId'] : false;
		$trackTranslation         = !empty($configArray['Analytics']['trackTranslation']) ? $configArray['Analytics']['trackTranslation'] : false;
		$this->assign('googleAnalyticsId', $googleAnalyticsId);
		$this->assign('googleAnalyticsLibraryId', $googleAnalyticsLibraryId);
		$this->assign('trackTranslation', $trackTranslation);
		$this->assign('googleAnalyticsLinkingId', $googleAnalyticsLinkingId);
		if ($googleAnalyticsId){
			$googleAnalyticsDomainName = isset($configArray['Analytics']['domainName']) ? $configArray['Analytics']['domainName'] : strstr($_SERVER['SERVER_NAME'], '.');
			// check for a config setting, use that if found, otherwise grab domain name  but remove the first subdomain
			$this->assign('googleAnalyticsDomainName', $googleAnalyticsDomainName);
		}

		/** @var Location $location */
		$location = $locationSingleton->getActiveLocation();

		// Header Info
		$homeLink = !empty($location->homeLink) && $location->homeLink != 'default' ? $location->homeLink :
			(!empty($library->homeLink) && $library->homeLink != 'default' ? $library->homeLink : false);
		$this->assign('homeLink', $homeLink);
		$this->assign('logoLink', empty($library->useHomeLinkForLogo) ? '' : $homeLink);
		$this->assign('logoAlt', empty($library->useHomeLinkForLogo) ? 'Return to Catalog Home' : 'Library Home Page');

		// Check for overriding images of the theme's main logo
		$themes = explode(',', $library->themeName);
		foreach ($themes as $themeName){
			// This overrides the theme's logo image for a location if the image directory contains a image file named as:
			if ($location != null && file_exists('./interface/themes/' . $themeName . '/images/' . $location->code . '_logo_responsive.png')){
				$responsiveLogo = '/interface/themes/' . $themeName . '/images/' . $location->code . '_logo_responsive.png';
				break;
			}
			// This overrides the theme's logo image for a library if the image directory contains a image file named as:
			if ($subdomain != null && file_exists('./interface/themes/' . $themeName . '/images/' . $subdomain . '_logo_responsive.png')){
				$responsiveLogo = '/interface/themes/' . $themeName . '/images/' . $subdomain . '_logo_responsive.png';
				break;
			}
		}
		if (isset($responsiveLogo)){
			$this->assign('responsiveLogo', $responsiveLogo);
		}

		// Footer Info
		$sessionId = session_id();
		if ($sessionId){
			$rememberMe = isset($_COOKIE['rememberMe']) ? $_COOKIE['rememberMe'] : false;
			$sessionStr = $sessionId . ', remember me ' . $rememberMe;
		} else {
			$sessionStr = ' - not saved';
		}
		$this->assign('session', $sessionStr);
		$this->assign('deviceName', get_device_name()); // footer & eContent support email
		$this->assign('activeIp', Location::getActiveIp());

		$this->getGitBranch();

		//$inLibrary is used to :
		// * pre-select auto-logout on place hold forms;
		// * to hide the remember me option on login pages;
		// * to show the Location in the page footer
		if ($locationSingleton->getIPLocation() != null){
			$this->assign('inLibrary', true);
			$physicalLocation = $locationSingleton->getIPLocation()->displayName;
		}else{
			$this->assign('inLibrary', false);
			$physicalLocation = 'Home';
		}
		$this->assign('physicalLocation', $physicalLocation);

		// Library-level settings
		if (isset($library)){
			$showHoldButton                = $library->showHoldButton;
			$showHoldButtonInSearchResults = $library->showHoldButtonInSearchResults;
			$this->assign('librarySystemName', $library->displayName);
			$this->assign('showDisplayNameInHeader', $library->showDisplayNameInHeader);
			$this->assign('facebookLink', $library->facebookLink);
			$this->assign('twitterLink', $library->twitterLink);
			$this->assign('youtubeLink', $library->youtubeLink);
			$this->assign('instagramLink', $library->instagramLink);
			$this->assign('goodreadsLink', $library->goodreadsLink);
			$this->assign('generalContactLink', $library->generalContactLink);
			$this->assign('showLoginButton', $library->showLoginButton);
			$this->assign('showAdvancedSearchbox', $library->showAdvancedSearchbox);
			$this->assign('enableProspectorIntegration', $library->enableProspectorIntegration);
			$this->assign('showTagging', $library->showTagging);
			$this->assign('showRatings', $library->showRatings);
			$this->assign('show856LinksAsTab', $library->show856LinksAsTab);
			$this->assign('showSearchTools', $library->showSearchTools);
			$this->assign('alwaysShowSearchResultsMainDetails', $library->alwaysShowSearchResultsMainDetails);
			$this->assign('showExpirationWarnings', $library->showExpirationWarnings);
			$this->assign('expiredMessage', $library->expiredMessage);
			$this->assign('expirationNearMessage', $library->expirationNearMessage);
			$this->assign('showSimilarTitles', $library->showSimilarTitles);
			$this->assign('showSimilarAuthors', $library->showSimilarAuthors);
			$this->assign('showItsHere', $library->showItsHere);
			$this->assign('enableMaterialsBooking', $library->enableMaterialsBooking);
			$this->assign('showHoldButtonForUnavailableOnly', $library->showHoldButtonForUnavailableOnly);
			$this->assign('horizontalSearchBar', $library->horizontalSearchBar);
			$this->assign('sideBarOnRight', $library->sideBarOnRight);
			$this->assign('showHoldCancelDate', $library->showHoldCancelDate);
			$this->assign('showPikaLogo', $library->showPikaLogo);
			$this->assign('allowMasqueradeMode', $library->allowMasqueradeMode);
			$this->assign('allowReadingHistoryDisplayInMasqueradeMode', $library->allowReadingHistoryDisplayInMasqueradeMode);
			$this->assign('interLibraryLoanName', $library->interLibraryLoanName);
			$this->assign('interLibraryLoanUrl', $library->interLibraryLoanUrl);
			$this->assign('sidebarMenuButtonText', $library->sidebarMenuButtonText);
			$this->assign('showGroupedHoldCopiesCount', $library->showGroupedHoldCopiesCount);
			$this->assign('showOnOrderCounts', $library->showOnOrderCounts);
			$this->assign('allowPinReset', $library->allowPinReset);
			$this->assign('externalMaterialsRequestUrl', $library->externalMaterialsRequestUrl);
			$this->assign('showPatronBarcodeImage', $library->showPatronBarcodeImage);

			$this->assign('showFavorites', $library->showFavorites);
			$this->assign('showComments', $library->showComments);
//			$this->assign('showTextThis', $library->showTextThis);
			$this->assign('showEmailThis', $library->showEmailThis);
			$this->assign('showShareOnExternalSites', $library->showShareOnExternalSites);
			$this->assign('showStaffView', $library->showStaffView);
			$this->assign('showQRCode', $library->showQRCode);
			$this->assign('showStaffView', $library->showStaffView);
			$this->assign('showGoodReadsReviews', $library->showGoodReadsReviews);
			$this->assign('showStandardReviews', $library->showStandardReviews);
			$this->assign('showSimilarTitles', $library->showSimilarTitles);
			$this->assign('showSimilarAuthors', $library->showSimilarAuthors);

			if ($library->showLibraryHoursAndLocationsLink){
				$this->assign('showLibraryHoursAndLocationsLink', true);
				//Check to see if we should just call it library location
				$numLocations = $library->getNumLocationsForLibrary();
				$this->assign('numLocations', $numLocations);
				if ($numLocations == 1){
					$locationForLibrary            = new Location();
					$locationForLibrary->libraryId = $library->libraryId;
					$locationForLibrary->find(true);
					$numHours = $locationForLibrary->getNumHours();
					$this->assign('numHours', $numHours);
				}
			}
			$this->setUpLibraryLinks($library); // Load library links
		}else{
			// Defaults for when a library isn't set
			$showHoldButton                = 1;
			$showHoldButtonInSearchResults = 1;
			$this->assign('librarySystemName', $configArray['Site']['libraryName']);
			$this->assign('showDisplayNameInHeader', 0);
			$this->assign('showLoginButton', 1);
			$this->assign('showAdvancedSearchbox', 1);
			$this->assign('enableProspectorIntegration', !empty($configArray['Content']['Prospector']));
			$this->assign('showTagging', 1);
			$this->assign('showRatings', 1);
			$this->assign('show856LinksAsTab', 1);
			$this->assign('showSearchTools', 1);
			$this->assign('alwaysShowSearchResultsMainDetails', 0);
			$this->assign('showExpirationWarnings', 1);
			$this->assign('showSimilarTitles', 1);
			$this->assign('showSimilarAuthors', 1);
			$this->assign('showItsHere', 0);
			$this->assign('enableMaterialsBooking', 0);
			$this->assign('showHoldButtonForUnavailableOnly', 0);
			$this->assign('horizontalSearchBar', 0);
			$this->assign('sideBarOnRight', 0);
			$this->assign('showHoldCancelDate', 0);
			$this->assign('showPikaLogo', 1);
			$this->assign('allowMasqueradeMode', 0);
			$this->assign('allowReadingHistoryDisplayInMasqueradeMode', 0);
			$this->assign('showGroupedHoldCopiesCount', 1);
			$this->assign('showOnOrderCounts', true);
			$this->assign('allowPinReset', 0);
			$this->assign('showLibraryHoursAndLocationsLink', 1);

			$this->assign('showFavorites', 1);
			$this->assign('showComments', 1);
//			$this->assign('showTextThis', 1);
			$this->assign('showEmailThis', 1);
			$this->assign('showShareOnExternalSites', 1);
			$this->assign('showQRCode', 1);
			$this->assign('showStaffView', 1);
			$this->assign('showGoodReadsReviews', 1);
			$this->assign('showStandardReviews', 1);
		}

		// Location-level settings
		if (isset($location)){
			if (isset($library)){
				// Settings that must be on both at the location and library levels to be enabled
				$showHoldButton                = (($location->showHoldButton == 1) && ($library->showHoldButton == 1)) ? 1 : 0;
				$showHoldButtonInSearchResults = (($location->showHoldButton == 1) && ($library->showHoldButtonInSearchResults == 1)) ? 1 : 0;
				$this->assign('showFavorites', $location->showFavorites && $library->showFavorites);
				$this->assign('showComments', $location->showComments && $library->showComments);
				//			$this->assign('showTextThis', $location->showTextThis && $library->showTextThis);
				$this->assign('showEmailThis', $location->showEmailThis && $library->showEmailThis);
				$this->assign('showShareOnExternalSites', $location->showShareOnExternalSites && $library->showShareOnExternalSites);
				$this->assign('showStaffView', $location->showStaffView && $library->showStaffView);
				$this->assign('showQRCode', $location->showQRCode && $library->showQRCode);
				$this->assign('showStaffView', $location->showStaffView && $library->showStaffView);
				$this->assign('showGoodReadsReviews', $location->showGoodReadsReviews && $library->showGoodReadsReviews);
				$this->assign('showStandardReviews', (($location->showStandardReviews == 1) && ($library->showStandardReviews == 1)) ? 1 : 0);
			}else{
				// location only (no library set); location settings that should override the defaults
				$showHoldButton = $location->showHoldButton;
				$this->assign('showFavorites', $location->showFavorites);
				$this->assign('showComments', $location->showComments);
				//			$this->assign('showTextThis', $location->showTextThis);
				$this->assign('showEmailThis', $location->showEmailThis);
				$this->assign('showShareOnExternalSites', $location->showShareOnExternalSites);
				$this->assign('showStaffView', $location->showStaffView);
				$this->assign('showQRCode', $location->showQRCode);
				$this->assign('showStaffView', $location->showStaffView);
				$this->assign('showGoodReadsReviews', $location->showGoodReadsReviews);
				$this->assign('showStandardReviews', $location->showStandardReviews);
			}

			// Location settings that should override both library and default settings
			$this->assign('showDisplayNameInHeader', $location->showDisplayNameInHeader);
			$this->assign('librarySystemName', $location->displayName);
		}

		if ($showHoldButton == 0){
			$showHoldButtonInSearchResults = 0;
		}
		$this->assign('showHoldButton', $showHoldButton);
		$this->assign('showHoldButtonInSearchResults', $showHoldButtonInSearchResults);
		$this->assign('showNotInterested', true);

		if (!empty($library->additionalCss)){
			$this->assign('additionalCss', $library->additionalCss);
		}elseif (!empty($location->additionalCss)){
			$this->assign('additionalCss', $location->additionalCss);
		}
		if (!empty($library->headerText)){
			$this->assign('headerText', $library->headerText);
		}elseif (!empty($location->headerText)){
			$this->assign('headerText', $location->headerText);
		}
	}

	private function getGitBranch(){
		global $interface;
		global $configArray;

		$gitName    = $configArray['System']['gitVersionFile'];
		$branchName = 'Unknown';
		if ($gitName == 'HEAD'){
			$stringFromFile = file('../../.git/HEAD', FILE_USE_INCLUDE_PATH);
			$stringFromFile = $stringFromFile[0]; //get the string from the array
			$explodedString = explode("/", $stringFromFile); // separate out by the "/" in the string
			$branchName     = $explodedString[2]; //get the one that is always the branch name
		}else{
			$stringFromFile = file('../../.git/FETCH_HEAD', FILE_USE_INCLUDE_PATH);
			if (!empty($stringFromFile)) {
				$stringFromFile = $stringFromFile[0];//get the string from the array
				if (preg_match('/(.*?)\s+branch\s+\'(.*?)\'.*/', $stringFromFile, $matches)) {
					$branchName = $matches[2];  //get the branch name
					if (!empty($matches[1])){
						$commit = $matches[1];
						$interface->assign('gitCommit', $commit);
					}
				}
			}
		}
		$interface->assign('gitBranch', $branchName);
	}

	/**
	 * @param string $variableName
	 * @return string|array|object
	 */
	public function getVariable($variableName){
		return $this->get_template_vars($variableName);
	}

	public function assignAppendToExisting($variableName, $newValue){
		$originalValue = $this->get_template_vars($variableName);
		if ($originalValue == null){
			$this->assign($variableName, $newValue);
		}else{
			if (is_array($originalValue)){
				$valueToAssign = array_merge($originalValue, $newValue);
			}else{
				$valueToAssign   = array();
				$valueToAssign[] = $originalValue;
				$valueToAssign[] = $newValue;
			}
			$this->assign($variableName, $valueToAssign);
		}
	}

	public function assignAppendUniqueToExisting($variableName, $newValue){
		$originalValue = $this->get_template_vars($variableName);
		if ($originalValue == null){
			$this->assign($variableName, $newValue);
		}else{
			if (is_array($originalValue)){
				$valueToAssign = $originalValue;
				foreach ($newValue as $tmpValue){
					if (!in_array($tmpValue, $valueToAssign)){
						$valueToAssign[] = $tmpValue;
					}
				}
			}else{
				if ($newValue != $originalValue){
					$valueToAssign   = array();
					$valueToAssign[] = $originalValue;
					$valueToAssign[] = $newValue;
				}else{
					return;
				}
			}
			$this->assign($variableName, $valueToAssign);
		}
	}

	/**
	 * Set up custom url links of the library to be displayed in the sidebar and below the header
	 * @param Library $library
	 */
	private function setUpLibraryLinks(Library $library){
		$links                  = $library->libraryLinks;
		$libraryHelpLinks       = [];
		$libraryAccountLinks    = [];
		$expandedLinkCategories = [];
		/** @var LibraryLink $libraryLink */
		foreach ($links as $libraryLink){
			if ($libraryLink->showInHelp || (!$libraryLink->showInHelp && !$libraryLink->showInAccount)){
				// Links with neither showInHelp or showInAccount checked should still show in the Help section
				if (empty($libraryLink->category)){
					// Links without categories should be displayed in the order they are listed
					$libraryHelpLinks[][$libraryLink->linkText] = $libraryLink;
				}else{
					if (!array_key_exists($libraryLink->category, $libraryHelpLinks)){
						$libraryHelpLinks[$libraryLink->category] = array();
					}
					$libraryHelpLinks[$libraryLink->category][$libraryLink->linkText] = $libraryLink;
				}
			}
			if ($libraryLink->showInAccount){
				if (empty($libraryLink->category)){
					// Links without categories should be displayed in the order they are listed
					$libraryAccountLinks[][$libraryLink->linkText] = $libraryLink;
				}else{
					if (!array_key_exists($libraryLink->category, $libraryAccountLinks)){
						$libraryAccountLinks[$libraryLink->category] = [];
					}
					$libraryAccountLinks[$libraryLink->category][$libraryLink->linkText] = $libraryLink;
				}
			}
			if ($libraryLink->showExpanded){
				$expandedLinkCategories[$libraryLink->category] = 1;
			}
		}
		$this->assign('libraryAccountLinks', $libraryAccountLinks);
		$this->assign('libraryHelpLinks', $libraryHelpLinks);
		$this->assign('expandedLinkCategories', $expandedLinkCategories);

		$topLinks = $library->libraryTopLinks;
		$this->assign('topLinks', $topLinks);
	}
}

function translate($params){
	global $translator;

	// If no translator exists yet, create one -- this may be necessary if we
	// encounter a failure before we are able to load the global translator
	// object.
	if (!is_object($translator)){
		global $configArray;

		$translator = new I18N_Translator('lang', $configArray['Site']['language'],
			$configArray['System']['missingTranslations']);
	}
	if (is_array($params)){
		return $translator->translate($params['text']);
	}else{
		return $translator->translate($params);
	}
}

function display_if_inconsistent($params, $content, &$smarty, &$repeat){
	//This function is called twice, once for the opening tag and once for the
	//closing tag.  Content is only set if
	if (isset($content)){
		$array = $params['array'];
		$key   = $params['key'];

		if (count($array) === 1){
			// If we have only one row of items, display that row
			return empty($array[0][$key]) ? '' : $content;
		}
		$consistent      = true;
		$firstValue      = null;
		$iterationNumber = 0;
		foreach ($array as $arrayValue){
			if ($iterationNumber == 0){
				$firstValue = $arrayValue[$key];
			}elseif ($firstValue != $arrayValue[$key]){
				$consistent = false;
				break;
			}
			$iterationNumber++;
		}
		return $consistent == false ? $content : '';
	}
	return null;
}

//function display_if_inconsistent_in_any_manifestation($params, $content, &$smarty, &$repeat){
//	//This function is called twice, once for the opening tag and once for the
//	//closing tag.  Content is only set if
//	if (isset($content)) {
//		$manifestations = $params['array'];
//		$key            = $params['key'];
//
////		if (count($manifestations) === 1) {
////			// If we have only one row of items, display that row
////			return empty($manifestations[0][$key]) ? '' : $content;
////		}
//		$consistent      = true;
//		$firstValue      = null;
//		$iterationNumber = 0;
//		foreach ($manifestations as $manifestation) {
//
//			foreach ($manifestation['relatedRecords'] as $arrayValue) {
//				if ($iterationNumber == 0) {
//					$firstValue = $arrayValue[$key];
//				} else {
//					if ($firstValue != $arrayValue[$key]) {
//						$consistent = false;
//						break;
//					}
//				}
//				$iterationNumber++;
//			}
//		}
//		if ($consistent == false){
//			return $content;
//		}else{
//			return "";
//		}
//	}
//	return null;
//}

function display_if_set($params, $content, &$smarty, &$repeat){
	//This function is called twice, once for the opening tag and once for the
	//closing tag.  Content is only set if
	if (isset($content)){
		$hasData    = false;
		$firstValue = null;
		$array      = $params['array'];
		$key        = $params['key'];
		foreach ($array as $arrayValue){
			if (isset($arrayValue[$key]) && !empty($arrayValue[$key])){
				$hasData = true;
				break;
			}
		}
		return $hasData ? $content : '';
	}
	return null;
}
