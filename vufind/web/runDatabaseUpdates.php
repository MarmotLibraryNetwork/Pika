<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * Command-line runner for the Database Maintenance updates that are normally
 * applied through the Admin/DBMaintenance web page. Used by the CI/CD deploy
 * pipeline (.github/scripts/deploy.sh) to apply pending updates after a code
 * deployment.
 *
 * Usage:
 *   php runDatabaseUpdates.php <PikaServer> [--list] [--dry-run]
 *     eg: php runDatabaseUpdates.php marmot.test
 *
 *   --list     Show update status without running anything
 *   --dry-run  Alias of --list
 *
 * Exits 0 when all pending updates succeed (or none are pending);
 * exits 1 on any failure.
 */

if (PHP_SAPI !== 'cli'){
	http_response_code(403);
	die('CLI only');
}

$options    = array_filter(array_slice($argv, 1), fn($arg) => str_starts_with($arg, '--'));
$arguments  = array_filter(array_slice($argv, 1), fn($arg) => !str_starts_with($arg, '--'));
$pikaServer = array_shift($arguments);
$listOnly   = in_array('--list', $options) || in_array('--dry-run', $options);

if (empty($pikaServer)){
	fwrite(STDERR, "Usage: php runDatabaseUpdates.php <PikaServer> [--list]\n  eg: php runDatabaseUpdates.php marmot.test\n");
	exit(1);
}

define('ROOT_DIR', __DIR__);
chdir(ROOT_DIR); // readConfig() resolves sites/ config paths relative to vufind/web
set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/composer');
require_once 'vendor/autoload.php';
// Same lookup as bootstrap.php's pika_autoloader() (bootstrap itself can't be
// included here — it starts sessions, memcache, etc.)
spl_autoload_register(function ($class){
	$sourcePath   = ROOT_DIR . DIRECTORY_SEPARATOR . 'sys' . DIRECTORY_SEPARATOR;
	$filePath     = str_replace('\\', DIRECTORY_SEPARATOR, $class);
	$pathParts    = explode('\\', $class);
	$directory    = end($pathParts);
	if (file_exists($sourcePath . $filePath . '.php')){
		include_once $sourcePath . $filePath . '.php';
	}elseif (file_exists($sourcePath . $filePath . DIRECTORY_SEPARATOR . $directory . '.php')){
		include_once $sourcePath . $filePath . DIRECTORY_SEPARATOR . $directory . '.php';
	}
});

require_once ROOT_DIR . '/sys/PEAR_Singleton.php';
PEAR_Singleton::init();

// readConfig() keys off SERVER_NAME to find sites/<PikaServer>/conf
$_SERVER['SERVER_NAME'] = $pikaServer;
require_once ROOT_DIR . '/sys/ConfigArray.php';
global $configArray;
$configArray = readConfig();
if (empty($configArray['Database'])){
	fwrite(STDERR, "Could not load configuration for {$pikaServer}\n");
	exit(1);
}

global $pikaLogger;
$pikaLogger = new Pika\Logger('runDatabaseUpdates');

// Configure DB_DataObject the same way bootstrap.php's initDatabase() does
define('DB_DATAOBJECT_NO_OVERLOAD', 0);
$dataObjectOptions =& PEAR_Singleton::getStaticProperty('DB_DataObject', 'options');
$dataObjectOptions = $configArray['Database'];

require_once ROOT_DIR . '/sys/DBMaintenance/DatabaseUpdates.php';
require_once ROOT_DIR . '/services/Admin/DBMaintenance.php';

// DBMaintenance is a web Admin action; its constructor chain enforces a
// logged-in user, so build the instance without the constructor and wire up
// its database connection directly.
$reflection    = new ReflectionClass('DBMaintenance');
$dbMaintenance = $reflection->newInstanceWithoutConstructor();

$databaseUpdates = new DatabaseUpdates();
$db              = $databaseUpdates->getDatabaseConnection();
if (PEAR::isError($db)){
	fwrite(STDERR, 'Database connection failed: ' . $db->getMessage() . "\n");
	exit(1);
}
$dbProperty = $reflection->getProperty('db');
$dbProperty->setValue($dbMaintenance, $db);

// Bound closures give access to the protected members (and preserve the
// by-reference $update parameters that ReflectionMethod::invoke cannot).
$getSQLUpdates = Closure::bind(function (){
	return $this->getSQLUpdates();
}, $dbMaintenance, DBMaintenance::class);
$runSQLStatement = Closure::bind(function (&$update, $sql){
	return $this->runSQLStatement($update, $sql);
}, $dbMaintenance, DBMaintenance::class);
$runMethodUpdate = Closure::bind(function ($method, &$update){
	return $this->$method($update);
}, $dbMaintenance, DBMaintenance::class);
$markUpdateAsRun = Closure::bind(function ($updateKey){
	$this->markUpdateAsRun($updateKey);
}, $dbMaintenance, DBMaintenance::class);

$availableUpdates = $getSQLUpdates();

// Determine which updates are pending
$pendingUpdates = [];
foreach ($availableUpdates as $key => $update){
	$dbUpdate             = new DatabaseUpdates();
	$dbUpdate->update_key = $key;
	if (!$dbUpdate->find()){
		$pendingUpdates[$key] = $update;
	}
}

echo count($availableUpdates) . ' total updates defined; ' . count($pendingUpdates) . " pending for {$pikaServer}\n";
if (empty($pendingUpdates)){
	echo "Nothing to do.\n";
	exit(0);
}
foreach ($pendingUpdates as $key => $update){
	echo "  pending: {$key} — {$update['title']}\n";
}
if ($listOnly){
	exit(0);
}

// Run the pending updates, mirroring DBMaintenance::launch()
$hadFailure = false;
foreach ($pendingUpdates as $key => $update){
	echo "Running {$key}...\n";
	$updateOk   = true;
	$successAll = true;
	foreach ($update['sql'] as $sql){
		if (method_exists($dbMaintenance, $sql)){
			$updateOk = $runMethodUpdate($sql, $update);
			echo '    ' . ($updateOk ? 'Method Update succeeded' : 'Method Update failed') . "\n";
			if (empty($update['continueOnError']) && !$updateOk){
				break;
			}
		}elseif (function_exists($sql)){
			$updateOk = $sql($update);
			echo '    ' . ($updateOk ? 'Function Update succeeded' : 'Function Update failed') . "\n";
			if (empty($update['continueOnError']) && !$updateOk){
				break;
			}
		}else{
			if (!$runSQLStatement($update, $sql)){
				$successAll = false;
				break;
			}
		}
		if ($successAll){
			$successAll = $updateOk; // Keep updating successAll til it is false
		}
	}
	foreach ($update['status'] ?? [] as $statusMessage){
		echo "    {$statusMessage}\n";
	}
	if ($successAll){
		$markUpdateAsRun($key);
		echo "  {$key}: SUCCESS\n";
	}else{
		$hadFailure = true;
		fwrite(STDERR, "  {$key}: FAILED\n");
		if (empty($update['continueOnError'])){
			fwrite(STDERR, "Stopping: update {$key} failed and is not marked continueOnError.\n");
			break;
		}
	}
}

exit($hadFailure ? 1 : 0);
