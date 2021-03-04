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

define ('ROOT_DIR', __DIR__);
set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/share/composer");

// autoloader stack
// Composer autoloader
require_once "vendor/autoload.php";
spl_autoload_register('pika_autoloader');
spl_autoload_register('vufind_autoloader');

global $errorHandlingEnabled;
$errorHandlingEnabled = true;

$startTime = microtime(true);
require_once ROOT_DIR . '/sys/Logger.php';
require_once ROOT_DIR . '/sys/PEAR_Singleton.php';
PEAR_Singleton::init();

require_once ROOT_DIR . '/sys/ConfigArray.php';
global $configArray;
$configArray = readConfig();
require_once ROOT_DIR . '/sys/Timer.php';
global $timer;
$timer = new Timer($startTime);
require_once ROOT_DIR . '/sys/MemoryWatcher.php';
global $memoryWatcher;
$memoryWatcher = new MemoryWatcher();

global $logger;
$logger = new Logger();
$timer->logTime("Read Config");

//global $app;
//$app = new \Pika\App();

if ($configArray['System']['debug']) {
	ini_set('display_errors', true);
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
	ini_set('display_errors', 0);
	ini_set('html_errors', 0);
}

// Use output buffering to allow session cookies to have different values
// this can't be determined before session_start is called
ob_start();

initMemcache();
initDatabase();
$timer->logTime("Initialized Database");
requireSystemLibraries();
initLocale();
// Sets global error handler for PEAR errors
PEAR_Singleton::setErrorHandling(PEAR_ERROR_CALLBACK, 'handlePEARError');
$timer->logTime("Basic Initialization");
loadLibraryAndLocation();
$timer->logTime("Finished load library and location");
loadSearchInformation();

$timer->logTime('Bootstrap done');

function initMemcache(){
	//Connect to memcache
	/** @var Memcache $memCache */
	global $memCache;
	global $timer;
	global $configArray;
	// Set defaults if nothing set in config file.
	$host = isset($configArray['Caching']['memcache_host']) ? $configArray['Caching']['memcache_host'] : '127.0.0.1';
	$port = isset($configArray['Caching']['memcache_port']) ? $configArray['Caching']['memcache_port'] : 11211;
	$timeout = isset($configArray['Caching']['memcache_connection_timeout']) ? $configArray['Caching']['memcache_connection_timeout'] : 1;

	// Connect to Memcache:
	$memCache = new Memcache();
	if (!@$memCache->pconnect($host, $port, $timeout)) {
		//Try again with a non-persistent connection
		if (!$memCache->connect($host, $port, $timeout)) {
			PEAR_Singleton::raiseError(new PEAR_Error("Could not connect to Memcache (host = {$host}, port = {$port})."));
		}
	}
	$timer->logTime("Initialize Memcache");
}

// todo: this can all be handled in Pika\Cache
function initCache(){
	global $configArray;
	// Set defaults if nothing set in config file.
	$host = isset($configArray['Caching']['memcache_host']) ? $configArray['Caching']['memcache_host'] : '127.0.0.1';
	$port = isset($configArray['Caching']['memcache_port']) ? $configArray['Caching']['memcache_port'] : 11211;
	$timeout = isset($configArray['Caching']['memcache_connection_timeout']) ? $configArray['Caching']['memcache_connection_timeout'] : 1;
	// Connect to Memcached with persistent
	$memCached = new Memcached('pika');
	// Caution! Since this is a persistent connection adding server adds on every page load
	if (!count($memCached->getServerList())) {
		$memCached->setOption(Memcached::OPT_NO_BLOCK, true);
		$memCached->setOption(Memcached::OPT_TCP_NODELAY, true);
		$memCached->addServer($host, $port);
	}
	return $memCached;
}

function initDatabase(){
	global $configArray;
	// Setup Local Database Connection
	define('DB_DATAOBJECT_NO_OVERLOAD', 0);
	$options =& PEAR_Singleton::getStaticProperty('DB_DataObject', 'options');
	$options = $configArray['Database'];
}

function requireSystemLibraries(){
	global $timer;
	// Require System Libraries
	require_once ROOT_DIR . '/sys/Interface.php';
	require_once ROOT_DIR . '/sys/Account/UserAccount.php';
	require_once ROOT_DIR . '/sys/Account/User.php';
	require_once ROOT_DIR . '/sys/Account/AccountProfile.php';
	require_once ROOT_DIR . '/sys/Language/Translator.php';
	require_once ROOT_DIR . '/sys/Search/Solr.php';
	require_once ROOT_DIR . '/sys/SearchObject/Factory.php';
	require_once ROOT_DIR . '/sys/Library/Library.php';
	require_once ROOT_DIR . '/sys/Location/Location.php';
	require_once ROOT_DIR . '/Drivers/DriverInterface.php';
	require_once ROOT_DIR . '/RecordDrivers/Factory.php';

}

function initLocale(){
	global $configArray;
	// Try to set the locale to UTF-8, but fail back to the exact string from the config
	// file if this doesn't work -- different systems may vary in their behavior here.
	setlocale(LC_MONETARY, array($configArray['Site']['locale'] . ".UTF-8",
	$configArray['Site']['locale']));
	date_default_timezone_set($configArray['Site']['timezone']);
}

/**
 * Handle an error raised by pear
 *
 * @var PEAR_Error $error;
 * @var string $method
 *
 * @return null
 */
function handlePEARError($error, $method = null){
	global $errorHandlingEnabled;
	if (isset($errorHandlingEnabled) && $errorHandlingEnabled == false){
		return;
	}
	global $configArray;

	// It would be really bad if an error got raised from within the error handler;
	// we would go into an infinite loop and run out of memory.  To avoid this,
	// we'll set a static value to indicate that we're inside the error handler.
	// If the error handler gets called again from within itself, it will just
	// return without doing anything to avoid problems.  We know that the top-level
	// call will terminate execution anyway.
	static $errorAlreadyOccurred = false;
	if ($errorAlreadyOccurred) {
		return;
	} else {
		$errorAlreadyOccurred = true;
	}

	//Clear any output that has been generated so far so the user just gets the error message.
	if (!$configArray['System']['debug']){
		@ob_clean();
		header("Content-Type: text/html");
	}

	// Display an error screen to the user:
	global $interface;
	if (!isset($interface) || $interface == false){
		$interface = new UInterface();
	}

	$interface->assign('error', $error);
	$interface->assign('debug', $configArray['System']['debug']);
	$interface->setTemplate('../error.tpl');
	$interface->display('layout.tpl');

	// Exceptions we don't want to log
	$doLog = true;
	// Microsoft Web Discussions Toolbar polls the server for these two files
	//    it's not script kiddie hacking, just annoying in logs, ignore them.
	if (strpos($_SERVER['REQUEST_URI'], "cltreq.asp") !== false) $doLog = false;
	if (strpos($_SERVER['REQUEST_URI'], "owssvr.dll") !== false) $doLog = false;
	// If we found any exceptions, finish here
	if (!$doLog) exit();

	// Log the error for administrative purposes -- we need to build a variety
	// of pieces so we can supply information at five different verbosity levels:
	$baseError = $error->toString();
	$basicServer = " (Server: IP = {$_SERVER['REMOTE_ADDR']}, " .
        "Referer = " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') . ", " .
        "User Agent = " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') . ", " .
        "Request URI = {$_SERVER['REQUEST_URI']})";
	$detailedServer = "\nServer Context:\n" . print_r($_SERVER, true);
	$basicBacktrace = "\nBacktrace:\n";
	if (is_array($error->backtrace)) {
		foreach($error->backtrace as $line) {
			$basicBacktrace .= (isset($line['file']) ? $line['file'] : 'none') . "  line " . (isset($line['line']) ? $line['line'] : 'none') . " - " .
                "class = " . (isset($line['class']) ? $line['class'] : 'none') . ", function = " . (isset($line['function']) ? $line['function'] : 'none') . "\n";
		}
	}
	$detailedBacktrace = "\nBacktrace:\n" . print_r($error->backtrace, true);
	$errorDetails = array(
	1 => $baseError,
	2 => $baseError . $basicServer,
	3 => $baseError . $basicServer . $basicBacktrace,
	4 => $baseError . $detailedServer . $basicBacktrace,
	5 => $baseError . $detailedServer . $detailedBacktrace
	);

	global $logger;
	$logger->log($errorDetails, PEAR_LOG_ERR);

	exit();
}

function loadLibraryAndLocation(){
	global $timer;
	global $librarySingleton;
	global $locationSingleton;
	//Create global singleton instances for Library and Location
	$librarySingleton = new Library();
	$timer->logTime('Created library singleton');
	$locationSingleton = new Location();
	$timer->logTime('Created Location singleton');

	global $active_ip;
	$active_ip = $locationSingleton->getActiveIp();
	handleCookie('test_ip', $active_ip);
	$timer->logTime('Got active ip address');

	$branch = $locationSingleton->getBranchLocationCode();
	handleCookie('branch', $branch);
	$timer->logTime('Got branch');

	$sublocation = $locationSingleton->getSublocationCode();
	handleCookie('sublocation', $sublocation);
	$timer->logTime('Got sublocation');

	getLibraryObject();
}

/**
 *  Set or unset a cookie based on the value
 *
 * @param $cookieName
 * @param $cookieValue
 */
function handleCookie($cookieName, $cookieValue){
	global $configArray;
	if (!isset($_COOKIE[$cookieName]) || $cookieValue != $_COOKIE[$cookieName]){
		if ($cookieValue == ''){
			if(!$configArray['Site']['isDevelopment']){
				setcookie($cookieName, $cookieValue, time() - 1000, '/', null, 1, 1);
			}else{
				setcookie($cookieName, $cookieValue, time() - 1000, '/', null, 0, 1);
			}
		}else{
			if(!$configArray['Site']['isDevelopment']){
				setcookie($cookieName, $cookieValue, 0, '/', null, 1, 1);
			}
			else
			{
				setcookie($cookieName, $cookieValue, 0, '/', null, 0, 1);
			}
		}
	}
}

/**
 * Set the global $library variable based on URL; and set the global $location variable when the URL is associated
 * with a specific location
 */
function getLibraryObject(){
	// Make the library information global so we can work with it later.
	global $library;

	$library             = new Library();
	$library->catalogUrl = $_SERVER['SERVER_NAME'];
	if (!$library->find(true)){
		$location             = new Location();
		$location->catalogUrl = $_SERVER['SERVER_NAME'];
		if ($location->find(true)){
			$location->setActiveLocation(clone $location);
			$library   = $library::getLibraryForLocation($location->locationId);
		}else{
			$library            = new Library();
			$library->isDefault = 1;
			if ($library->find(true) != 1){
				die('Could not determine the correct library to use for this installation');
			}
		}
	}
}

function loadSearchInformation(){
	//Determine the Search Source, need to do this always.
	global $searchSource;
	global $library;
	/** @var Memcache $memCache */
	global $memCache;
	global $instanceName;
	global $configArray;


	$searchSource = 'global';
	if (!empty($_GET['searchSource'])){
		if (is_array($_GET['searchSource'])){
			$_GET['searchSource'] = reset($_GET['searchSource']);
		}
		$searchSource             = $_GET['searchSource'];
		$_REQUEST['searchSource'] = $searchSource; //Update request since other check for it here
		$_SESSION['searchSource'] = $searchSource; //Update the session so we can remember what the user was doing last.
	}elseif (isset($_SESSION['searchSource'])){ //Didn't get a source, use what the user was doing last
		$searchSource             = $_SESSION['searchSource'];
		$_REQUEST['searchSource'] = $searchSource;
	}else{
		//Use a default search source
		$module = $_GET['module'] ?? null;
		if ($module == 'Person'){
			$searchSource = 'genealogy';
		}elseif ($module == 'Archive'){
			$searchSource = 'islandora';
		}elseif ($module == 'EBSCO'){
			$searchSource = 'ebsco';
		}else{
			require_once ROOT_DIR . '/sys/Search/SearchSources.php';
			$searchSources = new SearchSources();
			global $locationSingleton;
			$location = $locationSingleton->getActiveLocation();
			[$enableCombinedResults, $showCombinedResultsFirst, $combinedResultsName] = $searchSources::getCombinedSearchSetupParameters($location, $library);
			if ($enableCombinedResults && $showCombinedResultsFirst){
				$searchSource = 'combinedResults';
			}else{
				$searchSource = 'local';
			}
		}
		$_REQUEST['searchSource'] = $searchSource;
	}

	/** @var Library $searchLibrary */
	$searchLibrary  = Library::getSearchLibrary($searchSource);
	$searchLocation = Location::getSearchLocation($searchSource);

	//set searchSource for 'Repeat Search within Marmot Collection' option
	if ($searchSource == 'marmot' || $searchSource == 'global'){
		$searchSource = $searchLibrary->subdomain;
	}

	//Based on the search source, determine the search scope and set a global variable
	global $solrScope;
	global $scopeType;
	global $isGlobalScope;
	$solrScope = false;
	$scopeType = '';
	$isGlobalScope = false;

	if ($searchLibrary){
		$solrScope = $searchLibrary->subdomain;
		$scopeType = 'Library';
		if (!$searchLibrary->restrictOwningBranchesAndSystems){
			$isGlobalScope = true;
		}
	}
	if ($searchLocation){
		$solrScope = strtolower($searchLocation->code);
		if (!empty($searchLocation->subLocation)){
			$solrScope = strtolower($searchLocation->subLocation);
		}
		$scopeType = 'Location';
	}

	$solrScope = trim($solrScope);
	$solrScope = preg_replace('/[^a-zA-Z0-9_]/', '', $solrScope);
	if (strlen($solrScope) == 0){
		$solrScope = false;
		$scopeType = 'Unscoped';
	}

	//Load indexing profiles
	require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
	/** @var $indexingProfiles IndexingProfile[] */
	global $indexingProfiles;
	$memCacheKey              = "{$instanceName}_indexing_profiles";
	$indexingProfiles = $memCache->get($memCacheKey);
	if ($indexingProfiles === false || isset($_REQUEST['reload'])){
		$indexingProfiles = IndexingProfile::getAllIndexingProfiles();
//		global $logger;
//		$logger->log("Updating memcache variable {$instanceName}_indexing_profiles", PEAR_LOG_DEBUG);
		if (!$memCache->set($memCacheKey, $indexingProfiles, 0, $configArray['Caching']['indexing_profiles'])) {
			global $logger;
			$logger->log("Failed to update memcache variable $memCacheKey", PEAR_LOG_ERR);
		};
	}
}

function disableErrorHandler(){
	global $errorHandlingEnabled;
	$errorHandlingEnabled = false;
}
function enableErrorHandler(){
	global $errorHandlingEnabled;
	$errorHandlingEnabled = true;
}

function array_remove_by_value($array, $value){
	return array_values(array_diff($array, array($value)));
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
		}elseif (file_exists('sys/Library/' . $class . '.php')){
			$className = ROOT_DIR . '/sys/Library/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('sys/Location/' . $class . '.php')){
			$className = ROOT_DIR . '/sys/Location/' . $class . '.php';
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
		}elseif (file_exists('sys/Covers/' . $class . '.php')){
            $className = ROOT_DIR . '/sys/Covers/' . $class . '.php';
            require_once $className;
        }elseif (file_exists('sys/Authentication/' . $class . '.php')){
			$className = ROOT_DIR . '/sys/Authentication/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('sys/Archive/' . $class . '.php')){
		    $className = ROOT_DIR . '/sys/Archive/' . $class . '.php';
		    require_once $className;
        }elseif (file_exists('sys/' . $nameSpaceClass)){
			require_once 'sys/' . $nameSpaceClass;
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
