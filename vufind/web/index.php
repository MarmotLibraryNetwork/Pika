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

/** CORE APPLICATION CONTROLLER **/
require_once 'bootstrap.php';

global $timer;
global $memoryWatcher;

//Do additional tasks that are only needed when running the full website
loadModuleActionId();
$timer->logTime('Loaded Module and Action Id');
$memoryWatcher->logMemory('Loaded Module and Action Id');

//  Start session
$handler = new Pika\Session\FileSession();
session_set_save_handler($handler);
// register shutdown function needed to avoid oddities of using an object as session handler
register_shutdown_function('session_write_close');
@session_start();
$timer->logTime('Initialized Pika\Session');

if (isset($_REQUEST['test_role'])){
	handleCookie('test_role', $_REQUEST['test_role'] );
}

// Start Interface
$interface = new UInterface();
$timer->logTime('Create interface');

// Check system availability
checkMaintenanceMode();
$timer->logTime('Checked availability mode');

// Setup Translator
setUpTranslator();

/** @var Location $locationSingleton */
/** @var Library $library */
global $locationSingleton;
global $library;

$location = $locationSingleton->getActiveLocation();
//TODO: set this in bootstrap??
// (I suspect this is how $location is finally set up for global use)


$interface->loadDisplayOptions();
$timer->logTime('Loaded display options within interface');

// Determine Module and Action
$module = $_GET['module'] ?? null;
$action = $_GET['action'] ?? null;

//Set these initially in case user login fails, we will need the module to be set.
$interface->assign('module', $module);
$interface->assign('action', $action);

//if ($action == 'LogOut' && $module == 'MyAccount'){
//	UserAccount::logout();
//	header('Location: /');
//}

killSpammySearchPhrases();


$timer->logTime('Check if user is logged in');

// Process Authentication, must be done here so we can redirect based on user information
// immediately after logging in.

if (!UserAccount::isLoggedIn() && ((isset($_POST['username']) && isset($_POST['password']) && ($action != 'Account' && $module != 'AJAX')) || isset($_REQUEST['casLogin']))){
	//The user is trying to log in
	$user = UserAccount::login();
	$timer->logTime('Login the user');
	if (PEAR_Singleton::isError($user)){
		require_once ROOT_DIR . '/services/MyAccount/Login.php';
		$launchAction = new MyAccount_Login();
		$error_msg    = translate($user->getMessage());
		$launchAction->launch($error_msg);
		exit();
	}elseif (!$user){
		require_once ROOT_DIR . '/services/MyAccount/Login.php';
		$launchAction = new MyAccount_Login();
		$launchAction->launch('Unknown error logging in');
		exit();
	} elseif ($user->pinUpdateRequired){
		require_once ROOT_DIR . '/services/MyAccount/UpdatePin.php';
		$launchAction = new MyAccount_UpdatePin();
		$launchAction->launch();
		exit();
	}
	// Successful login

	//Check to see if there is a followup module and if so, use that module and action for the next page load
	elseif (isset($_REQUEST['returnUrl'])){
		$followupUrl = $_REQUEST['returnUrl'];
		header('Location: ' . $followupUrl);
		exit();
	}
	// Follow up with both module and action
	elseif (isset($_REQUEST['followupModule']) && isset($_REQUEST['followupAction'])){

		// For Masquerade Follow up, start directly instead of a redirect
		if ($_REQUEST['followupAction'] == 'Masquerade' && $_REQUEST['followupModule'] == 'MyAccount'){

			$this->logger->error('Processing Masquerade after logging in');
			require_once ROOT_DIR . '/services/MyAccount/Masquerade.php';
			$masquerade = new MyAccount_Masquerade();
			$masquerade->launch();
			die;
		}

		// Set the module & actions from the follow-up settings
		$module             = $_REQUEST['followupModule'];
		$action             = $_REQUEST['followupAction'];
		$_REQUEST['module'] = $module;
		$_REQUEST['action'] = $action;

		if (!empty($_REQUEST['recordId'])){
			$_REQUEST['id'] = $_REQUEST['recordId'];
		}

		//THE above is to replace this below. We shouldn't need to do a page reload. pascal 1/10/2020
//		echo("Redirecting to followup location");
//		$followupUrl = '/' . strip_tags($_REQUEST['followupModule']);
//		if (!empty($_REQUEST['recordId'])){
//			$followupUrl .= '/' . strip_tags($_REQUEST['recordId']);
//		}elseif (!empty($_REQUEST['id'])){
//			$followupUrl .= '/' . strip_tags($_REQUEST['id']);
//		}
//		$followupUrl .= '/' . strip_tags($_REQUEST['followupAction']);
//		header("Location: " . $followupUrl);
//		exit();

	}

	elseif (isset($_REQUEST['followup']) || isset($_REQUEST['followupModule'])){
		// Follow up when only the module or only the action is set
		global $configArray;

		$module = $_REQUEST['followupModule'] ?? $configArray['Site']['defaultModule'];
		$action = $_REQUEST['followup'] ?? $_REQUEST['followupAction'] ?? 'Home';

		if (!empty($_REQUEST['recordId'])){
			$_REQUEST['id'] = $_REQUEST['recordId'];
		}

		$_REQUEST['module'] = $module;
		$_REQUEST['action'] = $action;
	}

} //End of log in

$timer->logTime('User authentication');
$isLoggedIn = UserAccount::isLoggedIn();
$interface->assign('loggedIn', $isLoggedIn);
//Load user data for the user as long as we aren't in the act of logging out.
if ($isLoggedIn && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'Logout')){
	loadUserData();
	$timer->logTime('Load user data');
}else{
	$interface->assign('pType', 'logged out');
	$interface->assign('homeLibrary', 'n/a');
	$interface->assign('masqueradeMode', false);
}

//Determine whether or not materials request functionality should be enabled
// (set this after user log-in checking is done)
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequest.php';
$interface->assign('enableMaterialsRequest', MaterialsRequest::enableMaterialsRequest());

//Override MyAccount Home as needed
if ($isLoggedIn){
	if (!($action == 'AJAX' && $module == 'MyAccount' && in_array($_REQUEST['method'] , ['updatePin', 'LoginForm']))){
		// exception for updatePin popup process
		$user = UserAccount::getLoggedInUser();
		if (!empty($user->pinUpdateRequired)
			&& !in_array($action, ['Logout', 'EmailResetPin']) // Force pin update when logged in, except for users that are clicking the log-out button or using pin reset
			&& empty($_SESSION['guidingUserId']) // don't force pin update when masquerading as user
		){
			$module = 'MyAccount';
			$action = 'UpdatePin';
		}elseif ($module == 'MyAccount' && $action == 'Home'){
			if ($user->getNumCheckedOutTotal() > 0){
				$action = 'CheckedOut';
			}elseif ($user->getNumHoldsTotal() > 0){
				$action = 'Holds';
			}elseif ($user->getNumBookingsTotal() > 0){
				$action = 'Bookings';
			}
		}
	}
}

$interface->assign('module', $module);
$interface->assign('action', $action);
$timer->logTime('Assign module and action');

//Determine if the top search box and breadcrumbs should be shown.  Not showing these
//Does have a slight performance advantage.
if (in_array($action, ['AJAX', 'JSON', 'OpenSearch'])){
	$interface->assign('showBreadcrumbs', 0);
}else{
	setUpSearchDisplayOptions($module, $action);
}

setUpAutoLogOut($module, $action);
$timer->logTime('Check whether or not to include auto logout code');

// Process Login Followup
//TODO:  this code seems to be obsolete; for following up
//if (isset($_REQUEST['followup'])) {
//	processFollowup();
//	$timer->logTime('Process followup');
//}

// Call Action
// Note: ObjectEditor classes typically have the class name of DB_Object with an 's' added to the end.
//       This distinction prevents the DB_Object from being mistakenly called as the Action class.
if (!is_dir(ROOT_DIR . "/services/$module")){
	$module = 'Error';
	$action = 'Handle404';
	$interface->assign('module', 'Error');
	$interface->assign('action', 'Handle404');
	require_once ROOT_DIR . "/services/Error/Handle404.php";
	$actionClass = new Error_Handle404();
	$actionClass->launch();
}elseif (is_readable("services/$module/$action.php")) {
	$actionFile = ROOT_DIR . "/services/$module/$action.php";
	require_once $actionFile;
	$moduleActionClass = "{$module}_{$action}";
	if (class_exists($moduleActionClass, false)) {
		$timer->logTime('Start launch of action');
		/** @var Action $service */
		$service = new $moduleActionClass();
		$service->launch();
		$timer->logTime('Finish launch of action');
	}elseif (class_exists($action, false)) {
		$timer->logTime('Start launch of action');
		/** @var Action $service */
		$service = new $action();
		$service->launch();
		$timer->logTime('Finish launch of action');
	}else{
		PEAR_Singleton::raiseError(new PEAR_Error('Unknown Action'));
	}
} else {
	$interface->assign('showBreadcrumbs', false);
	$interface->assign('sidebar', 'Search/home-sidebar.tpl');
	$requestURI = $_SERVER['REQUEST_URI'];
	$cleanedUrl = strip_tags(urldecode($_SERVER['REQUEST_URI']));
	if ($cleanedUrl != $requestURI){
		PEAR_Singleton::RaiseError(new PEAR_Error("Cannot Load Action and Module the URL provided is invalid"));
	}else{
		PEAR_Singleton::RaiseError(new PEAR_Error("Cannot Load Action '$action' for Module '$module' request '$requestURI'"));
	}
}
$timer->logTime('Finished Index.php');
//$timer->writeTimings(); // The $timer destruct() will write out timing messages
$memoryWatcher->logMemory('Finished index.php');
$memoryWatcher->writeMemory();


/**
 *  Check if the website is available for use and display Unavailable page if not.
 *  Privileged browsers (determined by Ip) can access the site to do Maintenance work
 */
function checkMaintenanceMode(){
	global $configArray;

	if ($configArray['MaintenanceMode']['maintenanceMode']){
		global $interface;

		$isMaintenanceUser = false;
		$activeIp          = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
		if (!empty($configArray['MaintenanceMode']['maintenanceIps'])){
			$maintenanceIps    = explode(',', $configArray['MaintenanceMode']['maintenanceIps']);
			$isMaintenanceUser = in_array($activeIp, $maintenanceIps);
		}

		if ($isMaintenanceUser){
			// If this is a maintenance user, display site as normal but with a system message than maintenance mode is on
			$configArray['System']['systemMessage'] = '<p class="alert alert-danger"><strong>You are currently accessing the site in maintenance mode. Remember to turn off maintenance mode when you are done.</strong></p>';
			$interface->assign('systemMessage', $configArray['System']['systemMessage']);
		}else{
			// Display Unavailable page and quit
			global $library;
			$libraryName = !empty($library->displayName) ? $library->displayName : $configArray['Site']['libraryName'];
			$interface->assign('libraryName', $libraryName);
			if ($configArray['MaintenanceMode']['maintenanceMessage']){
				$interface->assign('maintenanceMessage', $configArray['MaintenanceMode']['maintenanceMessage']);
			}
			if ($configArray['MaintenanceMode']['showLinkToClassicInMaintenanceMode']){
				$accountProfile               = new AccountProfile();
				$accountProfile->recordSource = 'ils';
				if ($accountProfile->find(true) && !empty($accountProfile->vendorOpacUrl)){
					$interface->assign('showLinkToClassicInMaintenanceMode', true);
					$interface->assign('classicCatalogUrl', $accountProfile->vendorOpacUrl);
				}
			}
			$interface->assign('activeIp', $activeIp);

			$interface->display('unavailable.tpl');
			exit();
		}
	}
}

function loadModuleActionId(){
	//Cleanup method information so module, action, and id are set properly.
	//This ensures that we don't have to change the httpd-[pika-site].conf file when new types are added.

	/** @var IndexingProfile[] $indexingProfiles*/
	global $indexingProfiles;

	$module     = null;
	$action     = null;
	$id         = $_REQUEST['id'] ?? null;
	$requestURI = $_SERVER['REQUEST_URI'];
	$allRecordModules = 'OverDrive|GroupedWork|Record|ExternalEContent|Person|LibrarianReview|Library';
	foreach ($indexingProfiles as $profile){
		$allRecordModules .= '|' . $profile->recordUrlComponent;
	}
	if (preg_match('/(MyAccount)\/([^\/?]+)\/([^\/?]+)(\?.+)?/', $requestURI, $matches)){
		// things like /MyAccount/MyList/19141
		$module     = $matches[1];
		$id         = $matches[3];
		$action     = $matches[2];
	}elseif (preg_match('/(MyAccount)\/([^\/?]+)(\?.+)?/', $requestURI, $matches)){
		// things /MyAccount/AJAX, /MyAccount/Home, /MyAccount/CiteList
		$module     = $matches[1];
		$action     = $matches[2];
//		$id         = ''; //todo: when should this clear out the id when it has been set?  (it is useful for logging in while going to a private user list)
	}elseif (preg_match("/(MyAccount)\/?/", $requestURI, $matches)){
		$module     = $matches[1];
		$action     = 'Home';
		$id     = '';
	}elseif (preg_match('/\/(Archive)\/((?:[\\w\\d:]|%3A)+)\/([^\/?]+)/', $requestURI, $matches)){
		$module     = $matches[1];
		$id         = urldecode($matches[2]); // Decodes colons % codes back into colons.
		$action     = $matches[3];
		//Redirect things /GroupedWork/AJAX to the proper action
	}elseif (preg_match("/($allRecordModules)\/([a-zA-Z]+)(?:\?|\/?$)/", $requestURI, $matches)){
		$module     = $matches[1];
		$action     = $matches[2];
		//Redirect things /Record/.b3246786/Home to the proper action
		//Also things like /OverDrive/84876507-043b-b3ce-2930-91af93d2a4f0/Home
	}elseif (preg_match("/($allRecordModules)\/([^\/?]+?)\/([^\/?]+)/", $requestURI, $matches)){
		$module     = $matches[1];
		$id         = $matches[2];
		$action     = $matches[3];
		//Redirect things /Record/.b3246786 to the proper action
	}elseif (preg_match("/($allRecordModules)\/([^\/?]+?)(?:\?|\/?$)/", $requestURI, $matches)){
		$module     = $matches[1];
		$id         = $matches[2];
		$action     = 'Home';
	}elseif (preg_match("/([^\/?]+)\/([^\/?]+)/", $requestURI, $matches)){
		// things Browse/AJAX, Search/AJAX, Union/Search, Admin/ListWidgets
		$module     = $matches[1];
		$action     = $matches[2];
//		$id         = '';
	}
	if (!empty($action) && !empty($id) && $action == $id){
		// When the action is originally incorrectly set at the Id, clear out the id
		$id = '';
	}

	//Check to see if the module is an indexing profile and adjust the record id number
	if (!empty($id) && isset($module) && $module != 'GroupedWork' && $action != 'MyList'){
		// Grouped work and User list ids don't need a profile
		global $activeRecordIndexingProfile;
		foreach ($indexingProfiles as $profile){
			if ($profile->recordUrlComponent == $module){
				$id          = $profile->sourceName . ':' . $id;
				if (!file_exists(ROOT_DIR . '/services/' . $module)){
					// When a record view doesn't have an explicitly made driver, fallback to the standard full record View
					$module     = 'Record';
				}
				$activeRecordIndexingProfile = $profile;
				break;
			}
		}
	}

	//Find a reasonable default location to go to
	if ($module == null && $action == null){
		global $library;
		if ($library->archiveOnlyInterface ?? false){
				$module = 'Archive';
		}else{
			//We have no information about where to go, go to the default location from config
			global $configArray;
			$module = $configArray['Site']['defaultModule'];
		}
		$action = 'Home';
	}elseif ($action == null){
		$action = 'Home';
	}

	$_REQUEST['module'] = $_GET['module'] = preg_replace('/[^\w]/', '', $module);
	$_REQUEST['action'] = $_GET['action'] = preg_replace('/[^\w]/', '', $action);
	$_REQUEST['id']     = $_GET['id']     = $id;

}

function loadUserData(){
	global $interface;
	global $timer;

	//Assign User information to the interface
	if (UserAccount::isLoggedIn()){
		$user            = UserAccount::getActiveUserObj();
		$userId          = UserAccount::getActiveUserId();
		$userDisplayName = UserAccount::getUserDisplayName();
		$userRoles       = UserAccount::getActiveRoles();
		$disableCoverArt = UserAccount::getDisableCoverArt();
		$hasLinkedUsers  = UserAccount::hasLinkedUsers();
		$interface->assign('user', $user);
		$interface->assign('activeUserId', $userId);
		$interface->assign('userDisplayName', $userDisplayName);
		$interface->assign('userRoles', $userRoles);
		$interface->assign('disableCoverArt', $disableCoverArt);
		$interface->assign('hasLinkedUsers', $hasLinkedUsers);
		$interface->assign('pType', UserAccount::getUserPType());
		$interface->assign('homeLibrary', $user->getHomeLibrarySystemName());
		$homeLibrary = $user->getHomeLibrary();
		if (!empty($homeLibrary) && $homeLibrary->showPatronBarcodeImage != 'none'){
			// Display the barcode value beneath the mobile barcode display only if barcode/pin scheme in use.
			// and not if the name/barcode scheme is used.
			$interface->assign('displayBarcodeValue', !empty($user->getAccountProfile()->usingPins()));
		}

		// Set up any masquerading
		$interface->assign('canMasquerade', UserAccount::getActiveUserObj()->canMasquerade());
		$masqueradeMode = UserAccount::isUserMasquerading();
		$interface->assign('masqueradeMode', $masqueradeMode);
		if ($masqueradeMode){
			global $guidingUser; // Make the guiding user global so that the transition to log out
			$guidingUser = UserAccount::getGuidingUserObject();
			$interface->assign('guidingUser', $guidingUser);
		}

		//Privileged User settings
		if ($userRoles && UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin', 'cataloging', 'libraryManager', 'locationManager'])){
			$variable       = new Variable();
			$variable->name = 'lastFullReindexFinish';
			if ($variable->find(true) && $variable->value !== ''){
				$interface->assign('lastFullReindexFinish', date('m-d-Y H:i:s', $variable->value));
			}else{
				$interface->assign('lastFullReindexFinish', 'Unknown');
			}
			$variable       = new Variable();
			$variable->name = 'lastPartialReindexFinish';
			if ($variable->find(true) && $variable->value !== ''){
				$interface->assign('lastPartialReindexFinish', date('m-d-Y H:i:s', $variable->value));
			}else{
				$interface->assign('lastPartialReindexFinish', 'Unknown');
			}
			$timer->logTime('Load Information about Index status');
		}
	}
}

function setUpAutoLogOut($module, $action){
	global $interface;
	global $locationSingleton;
	global /** @var Library $library */
	$library;
	global $offlineMode;

	$location = $locationSingleton->getActiveLocation();
	//Determine if we should include autoLogout Code
	$ipLocation = $locationSingleton->getPhysicalLocation();
	if (!empty($ipLocation) && !empty($library) && $ipLocation->libraryId != $library->libraryId){
		// This is to cover the case of being within one library but the user is browsing another library catalog
		// This will turn off the auto-log out and Internal IP functionality
		// (unless the user includes the opac parameter)
		$ipLocation = null;
	}
	$isOpac = $locationSingleton->getOpacStatus();
	$interface->assign('isOpac', $isOpac);

	$masqueradeMode                  = UserAccount::isUserMasquerading();
	$onInternalIP                    = false;
	$includeAutoLogoutCode           = false;
	$automaticTimeoutLength          = 0;
	$automaticTimeoutLengthLoggedOut = 0;
	if (!$offlineMode && ($isOpac || $masqueradeMode || (!empty($ipLocation) && $ipLocation->getOpacStatus()) )) {
		// Make sure we don't have timeouts if we are offline (because it's super annoying when doing offline checkouts and holds)

		//$isOpac is set by URL parameter or cookie; ipLocation->getOpacStatus() returns $opacStatus private variable which comes from the ip tables

		// Turn on the auto log out
		$onInternalIP                    = true;
		$includeAutoLogoutCode           = true;
		$automaticTimeoutLength          = $locationSingleton::DEFAULT_AUTOLOGOUT_TIME;
		$automaticTimeoutLengthLoggedOut = $locationSingleton::DEFAULT_AUTOLOGOUT_TIME_LOGGED_OUT;

		if ($masqueradeMode) {
			// Masquerade Time Out Lengths
			$automaticTimeoutLength = empty($library->masqueradeAutomaticTimeoutLength) ? 90 : $library->masqueradeAutomaticTimeoutLength;
		} else {
			// Determine Regular Time Out Lengths
			if (UserAccount::isLoggedIn()) {
					$user = UserAccount::getActiveUserObj();

				// User has bypass AutoLog out setting turned on
				if ($user->bypassAutoLogout == 1) {
					// The account setting profile template only presents this option to users that are staff
					$includeAutoLogoutCode = false;
				}
			}elseif ($module == 'Search' && $action == 'Home') {
				// Not logged in only include auto logout code if we are not on the home page
					$includeAutoLogoutCode = false;
			}

			// If we know the branch, use the timeout settings from that branch
			if ($isOpac && $location) {
				$automaticTimeoutLength          = $location->automaticTimeoutLength;
				$automaticTimeoutLengthLoggedOut = $location->automaticTimeoutLengthLoggedOut;
			} // If we know the branch by iplocation, use the settings based on that location
			elseif ($ipLocation) {
				$automaticTimeoutLength          = $ipLocation->automaticTimeoutLength;
				$automaticTimeoutLengthLoggedOut = $ipLocation->automaticTimeoutLengthLoggedOut;
			} // Otherwise, use the main branch's settings or the first location's settings
			elseif ($library) {
				$defaultLocationForLibrary = Location::getDefaultLocationForLibrary($library->libraryId);
				if ($defaultLocationForLibrary) {
					// This finds either the main branch, or if there isn't one a location
					$automaticTimeoutLength          = $defaultLocationForLibrary->automaticTimeoutLength;
					$automaticTimeoutLengthLoggedOut = $defaultLocationForLibrary->automaticTimeoutLengthLoggedOut;
				}
			}
		}
	}
	$interface->assign('automaticTimeoutLength', $automaticTimeoutLength);
	$interface->assign('automaticTimeoutLengthLoggedOut', $automaticTimeoutLengthLoggedOut);
	$interface->assign('onInternalIP', $onInternalIP);
	$interface->assign('includeAutoLogoutCode', $includeAutoLogoutCode);
}

function setUpTranslator(){
	global $translator;
	global $language;

	global $configArray;
	global $serverName;

	if (isset($_REQUEST['mylang'])){
		$language = strip_tags($_REQUEST['mylang']);
		handleCookie('language', $language);
	}else{
		$language = strip_tags( $_COOKIE['language'] ?? $configArray['Site']['language']);
	}

	/** @var Memcache $memCache */
	global $memCache;
	$memCacheKey = "translator_{$serverName}_{$language}";
	$translator  = $memCache->get($memCacheKey);
	if ($translator == false || isset($_REQUEST['reloadTranslator'])){
		// Make sure language code is valid, reset to default if bad:
		$validLanguages = array_keys($configArray['Languages']);
		if (!in_array($language, $validLanguages)){
			$language = $configArray['Site']['language'];
		}
		$translator = new I18N_Translator('lang', $language, $configArray['System']['missingTranslations']);
		$memCache->set($memCacheKey, $translator, 0, $configArray['Caching']['translator']);
//		$timer->logTime('Translator setup');
	}
	global $interface;
	$interface->setLanguage($language);
}

function killSpammySearchPhrases(){
	//Look for spammy searches and kill them
	if (!empty($_REQUEST['lookfor'])) {
		// Advanced Search with only the default search group (multiple search groups are named lookfor0, lookfor1, ... )
		// TODO: Actually the lookfor is inconsistent; reloading from results in an array : lookfor[]
//		if (is_array($_REQUEST['lookfor'])) {
//			foreach ($_REQUEST['lookfor'] as $i => $searchTerm) {
//				if (preg_match('/http:|mailto:|https:/i', $searchTerm)) {
//					PEAR_Singleton::raiseError('Sorry it looks like you are searching for a website, please rephrase your query.');
//					$_REQUEST['lookfor'][$i] = '';
//					$_GET['lookfor'][$i]     = '';
//				}
//				if (strlen($searchTerm) >= 256) {
//					PEAR_Singleton::raiseError('Sorry your query is too long, please rephrase your query.');
//					$_REQUEST['lookfor'][$i] = '';
//					$_GET['lookfor'][$i]     = '';
//				}
//			}
//
//		}
//		else {
		// Basic Search
			$searchTerm = $_REQUEST['lookfor'];
			if (preg_match('/http:|mailto:|https:/i', $searchTerm)) {
				$_REQUEST['lookfor'] = '';
				$_GET['lookfor']     = '';
				setUpSearchDisplayOptions($_GET['module'], $_GET['action']);
				PEAR_Singleton::raiseError('Sorry it looks like you are searching for a website, please rephrase your query.');
				$_REQUEST['lookfor'] = '';
				$_GET['lookfor']     = '';
			}
			if (strlen($searchTerm) >= 256) {
				$_REQUEST['lookfor'] = '';
				$_GET['lookfor']     = '';
				setUpSearchDisplayOptions($_GET['module'], $_GET['action']);
				PEAR_Singleton::raiseError('Sorry your query is too long, please rephrase your query.');
			}
//		}
	}
}

function setUpSearchDisplayOptions($module, $action){
	global $interface;
	global $library;  /** @var Library $library */
	global $timer;

	global $solrScope;
	global $scopeType; // Library, Location or Unscoped
	global $isGlobalScope;
	$interface->assign('scopeType', $scopeType);
	$interface->assign('solrScope', "$solrScope - $scopeType");
	$interface->assign('isGlobalScope', $isGlobalScope);


	if (isset($_REQUEST['basicType'])){
		$interface->assign('basicSearchIndex', $_REQUEST['basicType'] ?? 'Keyword');
	}

	if (isset($_REQUEST['genealogyType'])){
		$interface->assign('genealogySearchIndex', $_REQUEST['genealogyType']);
	}else{
		$interface->assign('genealogySearchIndex', 'GenealogyKeyword');
	}

	global $searchSource;
	$interface->assign('searchSource', $searchSource);
	// Set $_REQUEST['type']
	switch ($searchSource){
		case 'genealogy':
			$_REQUEST['type'] = $_REQUEST['genealogyType'] ?? 'GenealogyKeyword';
			break;
		case 'islandora':
			$_REQUEST['type'] = $_REQUEST['islandoraType'] ?? 'IslandoraKeyword';
			break;
		case 'ebsco':
			$_REQUEST['type'] = $_REQUEST['ebscoType'] ?? 'TX';
			break;
		default:
			if (isset($_REQUEST['basicType'])){
				$_REQUEST['type'] = $_REQUEST['basicType'];
			}elseif (!isset($_REQUEST['type'])){
				$_REQUEST['type'] = 'Keyword';
			}
			break;
	}

	//Load basic search types for use in the interface.
	/** @var SearchObject_Solr|SearchObject_Base $searchObject */
	$searchObject = SearchObjectFactory::initSearchObject();
	$timer->logTime('Create Search Object');
	$searchObject->init();
	$timer->logTime('Init Search Object');
	$basicSearchTypes = is_object($searchObject) ? $searchObject->getBasicTypes() : [];
	$interface->assign('basicSearchTypes', $basicSearchTypes);

	// Set search results display mode in search-box //
	if ($searchObject->getView()) $interface->assign('displayMode', $searchObject->getView());

	//Load repeat search options
	require_once ROOT_DIR . '/sys/Search/SearchSources.php';
	$interface->assign('searchSources', SearchSources::getSearchSources());

	global $configArray;
	if (isset($configArray['Genealogy']) && $library->enableGenealogy){
		$genealogySearchObject = SearchObjectFactory::initSearchObject('Genealogy');
		if ($genealogySearchObject != false){
			$interface->assign('genealogySearchTypes', $genealogySearchObject->getBasicTypes() ?? []);
		}
	}

	if (!empty($configArray['Islandora']['enabled']) && $library->enableArchive){
		$islandoraSearchObject = SearchObjectFactory::initSearchObject('Islandora');
		if ($islandoraSearchObject != false){
			$interface->assign('islandoraSearchTypes', $islandoraSearchObject->getBasicTypes() ?? []);
			$interface->assign('enableArchive', true);
		}
	}

	//TODO: Reenable once we do full EDS integration
	/*if ($library->edsApiProfile){
		require_once ROOT_DIR . '/sys/Ebsco/EDS_API.php';
		$ebscoSearchObject = new EDS_API();
		$interface->assign('ebscoSearchTypes', $ebscoSearchObject->getSearchTypes());
	}*/

	if (!($module == 'Search' && $action == 'Home')){
		//Load information about the search so we can display it in the search box
		/** @var SearchObject_Base $savedSearch */
		$savedSearch = $searchObject->loadLastSearch();
		if (!is_null($savedSearch)){
			$searchIndex = $savedSearch->getSearchIndex();
			$interface->assign('lookfor',     $savedSearch->displayQuery());
			$interface->assign('searchType',  $savedSearch->getSearchType());
			$interface->assign('filterList',  $savedSearch->getFilterList());
			$interface->assign('savedSearch', $savedSearch->isSavedSearch());
			$interface->assign('searchIndex', $searchIndex);
		}
		$timer->logTime('Load last search for redisplay');
	}

	if (($action =="Home" && ($module=="Search" /*|| $module=="WorldCat"*/)) || $action == "AJAX" || $action == "JSON"){
		$interface->assign('showBreadcrumbs', 0);
	}else{
		$interface->assign('showBreadcrumbs', 1);
		if (!empty($library) && $library->useHomeLinkInBreadcrumbs){
			$interface->assign('homeBreadcrumbLink', $library->homeLink);
		}else{
			$interface->assign('homeBreadcrumbLink', '/');
		}
		if (!empty($library->homeLinkText)){
			$interface->assign('homeLinkText', $library->homeLinkText);
		}else{
			$interface->assign('homeLinkText', 'Home');
		}
	}
}
