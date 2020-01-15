<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

/** CORE APPLICATION CONTROLLER **/
require_once 'bootstrap.php';

global $timer;
global $memoryWatcher;

//Do additional tasks that are only needed when running the full website
loadModuleActionId();
$timer->logTime("Loaded Module and Action Id");
$memoryWatcher->logMemory("Loaded Module and Action Id");

// autoloader stack
spl_autoload_register('pika_autoloader');
spl_autoload_register('vufind_autoloader');

initializeSession();
$timer->logTime("Initialized session");

//global $logger;
//$logger->log("Opening URL " . $_SESSION['REQUEST_URI'], PEAR_LOG_DEBUG);

// PHP 7 logger
$pikaLogger = new Pika\Logger('PikaLogger', true);

if (isset($_REQUEST['test_role'])){
	if ($_REQUEST['test_role'] == ''){
		setcookie('test_role', $_REQUEST['test_role'], time() - 1000, '/');
	}else{
		setcookie('test_role', $_REQUEST['test_role'], 0, '/');
	}
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
global $locationSingleton;
global $library;

$location = $locationSingleton->getActiveLocation();
//TODO: set this in bootstrap??
// (I suspect this is how $location is finally set up for global use)


$interface->loadDisplayOptions();
$timer->logTime('Loaded display options within interface');


// Determine Module and Action
$module = (isset($_GET['module'])) ? $_GET['module'] : null;
$action = (isset($_GET['action'])) ? $_GET['action'] : null;

//Set these initially in case user login fails, we will need the module to be set.
$interface->assign('module', $module);
$interface->assign('action', $action);

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
		$launchAction->launch("Unknown error logging in");
		exit();
	}
	// Successful login

	//Check to see if there is a followup module and if so, use that module and action for the next page load
	elseif (isset($_REQUEST['returnUrl'])){
		$followupUrl = $_REQUEST['returnUrl'];
		header("Location: " . $followupUrl);
		exit();
	}
	// Follow up with both module and action
	elseif (isset($_REQUEST['followupModule']) && isset($_REQUEST['followupAction'])){

		// For Masquerade Follow up, start directly instead of a redirect
		if ($_REQUEST['followupAction'] == 'Masquerade' && $_REQUEST['followupModule'] == 'MyAccount'){
			global $logger;
			$logger->log("Processing Masquerade after logging in", PEAR_LOG_ERR);
			require_once ROOT_DIR . '/services/MyAccount/Masquerade.php';
			$masquerade = new MyAccount_Masquerade();
			$masquerade->launch();
			die;
		}

		// Set the module & actions from the follow up settings
		$module             = $_REQUEST['followupModule'];
		$action             = $_REQUEST['followupAction'];
		$_REQUEST['module'] = $module;
		$_REQUEST['action'] = $action;

		if (!empty($_REQUEST['id'])){
			$id = $_REQUEST['id'];
		}elseif (!empty($_REQUEST['recordId'])){
			$id = $_REQUEST['recordId'];
		}
		if (isset($id)){
			$_REQUEST['id'] = $id;
		}

		//THE above is to replace this below. We shouldn't need to do a page reload. pascal 1/10/2020
//		echo("Redirecting to followup location");
//		$followupUrl = $configArray['Site']['path'] . '/' . strip_tags($_REQUEST['followupModule']);
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

		$module = isset($_REQUEST['followupModule']) ? $_REQUEST['followupModule'] : $configArray['Site']['defaultModule'];
		$action = isset($_REQUEST['followup']) ? $_REQUEST['followup'] : (isset($_REQUEST['followupAction']) ? $_REQUEST['followupAction'] : 'Home');

		if (!empty($_REQUEST['id'])){
			$id = $_REQUEST['id'];
		}elseif (!empty($_REQUEST['recordId'])){
			$id = $_REQUEST['recordId'];
		}
		if (isset($id)){
			$_REQUEST['id'] = $id;
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
require_once ROOT_DIR . '/sys/MaterialsRequest.php';
$interface->assign('enableMaterialsRequest', MaterialsRequest::enableMaterialsRequest());

//Override MyAccount Home as needed
if ($isLoggedIn && $module == 'MyAccount' && $action == 'Home'){
	$user = UserAccount::getLoggedInUser();
	if ($user->getNumCheckedOutTotal() > 0){
		$action ='CheckedOut';
//		header('Location:/MyAccount/CheckedOut');
//		exit();
	}elseif ($user->getNumHoldsTotal() > 0){
		$action = 'Holds';
//		header('Location:/MyAccount/Holds');
//		exit();
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

// Process Solr shard settings
processShards();
$timer->logTime('Process Shards');

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
$timer->writeTimings();
$memoryWatcher->logMemory("Finished index.php");
$memoryWatcher->writeMemory();



function processFollowup(){
	global $configArray;

	switch($_REQUEST['followup']) {
		case 'SaveSearch':
			header("Location: {$configArray['Site']['path']}/".$_REQUEST['followupModule']."/".$_REQUEST['followupAction']."?".$_REQUEST['recordId']);
			die();
			break;
	}
}

/**
 * Process Solr-shard-related parameters and settings.
 *
 * @return void
 */
function processShards()
{
	global $configArray;
	global $interface;

	// If shards are not configured, give up now:
	if (!isset($configArray['IndexShards']) || empty($configArray['IndexShards'])) {
		return;
	}

	// If a shard selection list is found as an incoming parameter, we should save
	// it in the session for future reference:
	$useDefaultShards = false;
	if (array_key_exists('shard', $_REQUEST)) {
		if ($_REQUEST['shard'] == ''){
			$useDefaultShards = true;
		}else{
			$_SESSION['shards'] = $_REQUEST['shard'];
		}

	} else if (!array_key_exists('shards', $_SESSION)) {
		$useDefaultShards = true;
	}
	if ($useDefaultShards){
		// If no selection list was passed in, use the default...

		// If we have a default from the configuration, use that...
		if (isset($configArray['ShardPreferences']['defaultChecked'])
				&& !empty($configArray['ShardPreferences']['defaultChecked'])
				) {
			$checkedShards = $configArray['ShardPreferences']['defaultChecked'];
			$_SESSION['shards'] = is_array($checkedShards) ?
			$checkedShards : array($checkedShards);
		} else {
			// If no default is configured, use all shards...
			$_SESSION['shards'] = array_keys($configArray['IndexShards']);
		}
	}

	// If we are configured to display shard checkboxes, send a list of shards
	// to the interface, with keys being shard names and values being a boolean
	// value indicating whether or not the shard is currently selected.
	if (isset($configArray['ShardPreferences']['showCheckboxes'])
	&& $configArray['ShardPreferences']['showCheckboxes'] == true
	) {
		$shards = array();
		foreach ($configArray['IndexShards'] as $shardName => $shardAddress) {
			$shards[$shardName] = in_array($shardName, $_SESSION['shards']);
		}
		$interface->assign('shards', $shards);
	}
}


/**
 *  Check if the website is available for use and display Unavailable page if not.
 *  Privileged browsers (determined by Ip) can access the site to do Maintenance work
 */
function checkMaintenanceMode(){
	global $configArray;

	if ($configArray['MaintenanceMode']['maintenanceMode']){
		global $interface;

		$isMaintenanceUser = false;
		$activeIp          = $_SERVER['REMOTE_ADDR'];
		if (!empty($configArray['MaintenanceMode']['maintenanceIps'])){
			$maintenanceIps    = explode(',', $configArray['SystemMaintenanceMode']['maintenanceIps']);
			$isMaintenanceUser = in_array($activeIp, $maintenanceIps);
		}

		if ($isMaintenanceUser){
			// If this is a maintenance user, display site as normal but with a system message than maintenance mode is on
			$configArray['System']['systemMessage'] = '<p class="alert alert-danger"><strong>You are currently accessing the site in maintenance mode. Remember to turn off maintenance mode when you are done.</strong></p>';
			$interface->assign('systemMessage', $configArray['System']['systemMessage']);
		}else{
			// Display Unavailable page and quit
			global $library;
			$libraryName = !empty($library->displayName) ? $library->displayName : $configArray['Site']['title'];
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

// Pika drivers autoloader PSR-4 style
function pika_autoloader($class) {
    $sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'sys' . DIRECTORY_SEPARATOR;

    $filePath       = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $pathParts      = explode("\\", $class);
    $directoryIndex = count($pathParts) - 1;
    $directory      = $pathParts[$directoryIndex];
    $fullFilePath   = $sourcePath.$filePath.'.php';
		$fullFolderPath = $sourcePath.$filePath.DIRECTORY_SEPARATOR.$directory.'.php';
    if(file_exists($fullFilePath)) {
    	include_once($fullFilePath);
    } elseif (file_exists($fullFolderPath)) {
	    include_once($fullFolderPath);
    }
}

// Set up autoloader (needed for YAML)
// todo: this needs a total rewrite. it doesn't account for autoloader stacks and throws a fatal error.
function vufind_autoloader($class) {
	if (substr($class, 0, 4) == 'CAS_') {
		return CAS_autoload($class);
	}
	if (strpos($class, '.php') > 0){
		$class = substr($class, 0, strpos($class, '.php'));
	}
	$nameSpaceClass = str_replace('_', '/', $class) . '.php';
	try{
		if (file_exists('sys/' . $class . '.php')){
			$className = ROOT_DIR . '/sys/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('Drivers/' . $class . '.php')){
			$className = ROOT_DIR . '/Drivers/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('Drivers/marmot_inc/' . $class . '.php')){
			$className = ROOT_DIR . '/Drivers/marmot_inc/' . $class . '.php';
			require_once $className;
		} elseif (file_exists('RecordDrivers/' . $class . '.php')){
			$className = ROOT_DIR . '/RecordDrivers/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('services/MyAccount/lib/' . $class . '.php')){
			$className = ROOT_DIR . '/services/MyAccount/lib/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('services/' . $class . '.php')){
			$className = ROOT_DIR . '/services/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('sys/Authentication/' . $class . '.php')){
			$className = ROOT_DIR . '/sys/Authentication/' . $class . '.php';
			require_once $className;
		}else{
			try {
				include_once $nameSpaceClass;
			} catch (Exception $e) {
				// todo: This should fail over to next instead of throwing fatal error.
				// PEAR_Singleton::raiseError("Error loading class $class");
			}
		}
	}catch (Exception $e){
		// PEAR_Singleton::raiseError("Error loading class $class");
		// todo: This should fail over to next instead of throwing fatal error.
	}
}

function loadModuleActionId(){
	//Cleanup method information so module, action, and id are set properly.
	//This ensures that we don't have to change the http-vufind.conf file when new types are added.

	/** @var IndexingProfile[] $indexingProfiles*/
	global $indexingProfiles;

	$module     = null;
	$action     = null;
	$id         = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;
	$requestURI = $_SERVER['REQUEST_URI'];
	$allRecordModules = 'OverDrive|GroupedWork|Record|ExternalEContent|Person|EditorialReview|Library';
	foreach ($indexingProfiles as $profile){
		$allRecordModules .= '|' . $profile->recordUrlComponent;
	}
	if (preg_match("/(MyAccount)\/([^\/?]+)\/([^\/?]+)(\?.+)?/", $requestURI, $matches)){
		$module     = $matches[1];
		$id         = $matches[3];
		$action     = $matches[2];
	}elseif (preg_match("/(MyAccount)\/([^\/?]+)(\?.+)?/", $requestURI, $matches)){
		// things /MyAccount/AJAX
		$module     = $matches[1];
		$action     = $matches[2];
		$id         = '';
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
		// things Browse/AJAX, Search/AJAX, Union/Search
		$module     = $matches[1];
		$action     = $matches[2];
	}

	//Check to see if the module is an indexing profile and adjust the record id number
	if (!empty($id) && isset($module) && $module != 'GroupedWork'){
		global $activeRecordIndexingProfile;
		foreach ($indexingProfiles as $profile){
			if ($profile->recordUrlComponent == $_REQUEST['module']){
				$id          = $profile->name . ':' . $_REQUEST['id'];
				if (!file_exists(ROOT_DIR . '/services/' . $_REQUEST['module'])){
					// When a record view, doesn't have an explicitly made driver, fallback to the standard Record Driver
					$module     = 'Record';
				}
				$activeRecordIndexingProfile = $profile;
				break;
			}
		}
	}

	//Find a reasonable default location to go to
	if ($module == null && $action == null){
		//We have no information about where to go, go to the default location from config
		global $configArray;
		$module = $configArray['Site']['defaultModule'];
		$action = 'Home';
	}elseif ($action == null){
		$action = 'Home';
	}

	$_REQUEST['module'] = $_GET['module'] = preg_replace('/[^\w]/', '', $module);
	$_REQUEST['action'] = $_GET['action'] = preg_replace('/[^\w]/', '', $action);
	$_REQUEST['id']     = $_GET['id']     = $id;

}

function initializeSession(){
	global $configArray;
	global $timer;
	// Initiate Session State
	$session_type = $configArray['Session']['type'];
	$session_lifetime = $configArray['Session']['lifetime'];
	$session_rememberMeLifetime = $configArray['Session']['rememberMeLifetime'];
	register_shutdown_function('session_write_close');
	$sessionClass = ROOT_DIR . '/sys/' . $session_type . '.php';
	require_once $sessionClass;
	if (class_exists($session_type)) {
		/** @var SessionInterface $session */
		$session = new $session_type();
		$session->init($session_lifetime, $session_rememberMeLifetime);
	}
	$timer->logTime('Session initialization ' . $session_type);
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

		// Set up any masquerading
		$interface->assign('canMasquerade', UserAccount::getActiveUserObj()->canMasquerade());
		$masqueradeMode = UserAccount::isUserMasquerading();
		$interface->assign('masqueradeMode', $masqueradeMode);
		if ($masqueradeMode){
			$guidingUser = UserAccount::getGuidingUserObject();
			$interface->assign('guidingUser', $guidingUser);
		}

		//Privileged User settings
		if ($userRoles && UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin', 'cataloging', 'libraryManager', 'locationManager'])){
			$variable       = new Variable();
			$variable->name = 'lastFullReindexFinish';
			if ($variable->find(true)){
				$interface->assign('lastFullReindexFinish', date('m-d-Y H:i:s', $variable->value));
			}else{
				$interface->assign('lastFullReindexFinish', 'Unknown');
			}
			$variable       = new Variable();
			$variable->name = 'lastPartialReindexFinish';
			if ($variable->find(true)){
				$interface->assign('lastPartialReindexFinish', date('m-d-Y H:i:s', $variable->value));
			}else{
				$interface->assign('lastPartialReindexFinish', 'Unknown');
			}
			$timer->logTime("Load Information about Index status");
		}
	}
}

function setUpAutoLogOut($module, $action){
	global $interface;
	global $locationSingleton;
	global $library;
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
				//TODO: ensure we are checking that URL is consistent with location, if not turn off
				// eg: browsing at fort lewis library from garfield county library
				$automaticTimeoutLength          = $ipLocation->automaticTimeoutLength;
				$automaticTimeoutLengthLoggedOut = $ipLocation->automaticTimeoutLengthLoggedOut;
			} // Otherwise, use the main branch's settings or the first location's settings
			elseif ($library) {
				$firstLocation            = new Location();
				$firstLocation->libraryId = $library->libraryId;
				$firstLocation->orderBy('isMainBranch DESC');
				if ($firstLocation->find(true)) {
					// This finds either the main branch, or if there isn't one a location
					$automaticTimeoutLength          = $firstLocation->automaticTimeoutLength;
					$automaticTimeoutLengthLoggedOut = $firstLocation->automaticTimeoutLengthLoggedOut;
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
		setcookie('language', $language, null, '/');
	}else{
		$language = strip_tags((isset($_COOKIE['language'])) ? $_COOKIE['language'] : $configArray['Site']['language']);
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
		if (is_array($_REQUEST['lookfor'])) {
			foreach ($_REQUEST['lookfor'] as $i => $searchTerm) {
				if (preg_match('/http:|mailto:|https:/i', $searchTerm)) {
					PEAR_Singleton::raiseError("Sorry it looks like you are searching for a website, please rephrase your query.");
					$_REQUEST['lookfor'][$i] = '';
					$_GET['lookfor'][$i]     = '';
				}
				if (strlen($searchTerm) >= 256) {
					PEAR_Singleton::raiseError("Sorry your query is too long, please rephrase your query.");
					$_REQUEST['lookfor'][$i] = '';
					$_GET['lookfor'][$i]     = '';
				}
			}

		}
		// Basic Search
		else {
			$searchTerm = $_REQUEST['lookfor'];
			if (preg_match('/http:|mailto:|https:/i', $searchTerm)) {
				PEAR_Singleton::raiseError("Sorry it looks like you are searching for a website, please rephrase your query.");
				$_REQUEST['lookfor'] = '';
				$_GET['lookfor']     = '';
			}
			if (strlen($searchTerm) >= 256) {
				PEAR_Singleton::raiseError("Sorry your query is too long, please rephrase your query.");
				$_REQUEST['lookfor'] = '';
				$_GET['lookfor']     = '';
			}
		}
	}
}

function setUpSearchDisplayOptions($module, $action){
	global $interface;
	global $library;
	global $timer;

	global $solrScope;
	global $scopeType;
	global $isGlobalScope;
	$interface->assign('scopeType', $scopeType);
	$interface->assign('solrScope', "$solrScope - $scopeType");
	$interface->assign('isGlobalScope', $isGlobalScope);


	if (isset($_REQUEST['basicType'])){
		$interface->assign('basicSearchIndex', $_REQUEST['basicType']);
	}else{
		$interface->assign('basicSearchIndex', 'Keyword');
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
			$_REQUEST['type'] = isset($_REQUEST['genealogyType']) ? $_REQUEST['genealogyType'] : 'GenealogyKeyword';
			break;
		case 'islandora':
			$_REQUEST['type'] = isset($_REQUEST['islandoraType']) ? $_REQUEST['islandoraType'] : 'IslandoraKeyword';
			break;
		case 'ebsco':
			$_REQUEST['type'] = isset($_REQUEST['ebscoType']) ? $_REQUEST['ebscoType'] : 'TX';
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
	$basicSearchTypes = is_object($searchObject) ? $searchObject->getBasicTypes() : array();
	$interface->assign('basicSearchTypes', $basicSearchTypes);

	// Set search results display mode in search-box //
	if ($searchObject->getView()) $interface->assign('displayMode', $searchObject->getView());

	//Load repeat search options
	require_once ROOT_DIR . '/Drivers/marmot_inc/SearchSources.php';
	$searchSources = new SearchSources();
	$interface->assign('searchSources', $searchSources->getSearchSources());

	if (isset($configArray['Genealogy']) && $library->enableGenealogy){
		$genealogySearchObject = SearchObjectFactory::initSearchObject('Genealogy');
		$interface->assign('genealogySearchTypes', is_object($genealogySearchObject) ? $genealogySearchObject->getBasicTypes() : array());
	}

	if ($library->enableArchive){
		$islandoraSearchObject = SearchObjectFactory::initSearchObject('Islandora');
		$interface->assign('islandoraSearchTypes', is_object($islandoraSearchObject) ? $islandoraSearchObject->getBasicTypes() : array());
		$interface->assign('enableArchive', true);
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

// polyfill for php 7
function array_key_first(array $arr) {
	foreach($arr as $key => $unused) {
		return $key;
	}
	return NULL;
}
