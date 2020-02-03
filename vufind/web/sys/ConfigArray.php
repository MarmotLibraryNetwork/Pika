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

/**
 * Support function -- get the file path to one of the ini files specified in the
 * [Extra_Config] section of config.ini.
 *
 * @param   string $name        The ini's name from the [Extra_Config] section of config.ini
 * @return  string      The file path
 */
function getExtraConfigArrayFile($name)
{
	global $configArray;

	// Load the filename from config.ini, and use the key name as a default
	//     filename if no stored value is found.
	$filename = isset($configArray['Extra_Config'][$name]) ? $configArray['Extra_Config'][$name] : $name . '.ini';

	//Check to see if there is a domain name based subfolder for he configuration
	global $serverName;
	if (file_exists("../../sites/$serverName/conf/$filename")){
		// Return the file path (note that all ini files are in the conf/ directory)
		return "../../sites/$serverName/conf/$filename";
	}elseif (file_exists("../../sites/default/conf/$filename")){
		// Return the file path (note that all ini files are in the conf/ directory)
		return "../../sites/default/conf/$filename";
	} else{
		// Return the file path (note that all ini files are in the conf/ directory)
		return '../../sites/' . $filename;
	}

}

/**
 * Load a translation map from the translation_maps directory
 *
 * @param   string $name        The name of the translation map should not include _map.properties
 * @return  string      The file path
 */
function getTranslationMap($name)
{
	//Check to see if there is a domain name based subfolder for he configuration
	global $serverName;
	/** @var Memcache $memCache */
	global $memCache;
	$mapValues = $memCache->get('translation_map_'. $serverName.'_'. $name);
	if ($mapValues != false && $mapValues != null && !isset($_REQUEST['reload'])){
		return $mapValues;
	}

	// If the requested settings aren't loaded yet, pull them in:
	$mapNameFull = $name . '_map.properties';
	if (file_exists("../../sites/$serverName/translation_maps/$mapNameFull")){
		// Return the file path (note that all ini files are in the conf/ directory)
		$mapFilename = "../../sites/$serverName/translation_maps/$mapNameFull";
	}elseif (file_exists("../../sites/default/translation_maps/$mapNameFull")){
		// Return the file path (note that all ini files are in the conf/ directory)
		$mapFilename = "../../sites/default/translation_maps/$mapNameFull";
	} else{
		// Return the file path (note that all ini files are in the conf/ directory)
		$mapFilename = '../../sites/' . $mapNameFull;
	}


	// Try to load the .ini file; if loading fails, the file probably doesn't
	// exist, so we can treat it as an empty array.
	$mapValues = array();
	$fHnd = fopen($mapFilename, 'r');
	while (($line = fgets($fHnd)) !== false){
		if (substr($line, 0, 1) == '#'){
			//skip the line, it's a comment
		}else{
			$lineData = explode('=', $line, 2);
			if (count($lineData) == 2){
				$mapValues[strtolower(trim($lineData[0]))] = trim($lineData[1]);
			}
		}
	}
	fclose($fHnd);

	global $configArray;
	$memCache->set('translation_map_'. $serverName.'_' . $name, $mapValues, 0, $configArray['Caching']['translation_map']);
	return $mapValues;
}

function mapValue($mapName, $value){
	$map = getTranslationMap($mapName);
	if ($map == null || $map == false){
		return $value;
	}
	$value = str_replace(' ', '_', $value);
	if (isset($map[$value])){
		return $map[$value];
	}elseif (isset($map[strtolower($value)])){
		return $map[strtolower($value)];
	}elseif (isset($map['*'])){
		return ($map['*'] == 'nomap') ?  $value : $map['*'];
	}else{
		return '';
	}
}

/**
 * Support function -- get the contents of one of the ini files specified in the
 * [Extra_Config] section of config.ini.
 *
 * @param string $name The ini's name from the [Extra_Config] section of config.ini
 * @return  array       The retrieved configuration settings.
 */
function getExtraConfigArray($name){
	static $extraConfigs = array();

	// If the requested settings aren't loaded yet, pull them in:
	if (!isset($extraConfigs[$name])){
		// Try to load the .ini file; if loading fails, the file probably doesn't
		// exist, so we can treat it as an empty array.
		$extraConfigs[$name] = @parse_ini_file(getExtraConfigArrayFile($name), true);
		if ($extraConfigs[$name] === false){
			$extraConfigs[$name] = array();
		}
	}

	return $extraConfigs[$name];
}

/**
 * Support function -- merge the contents of two arrays parsed from ini files.
 *
 * @param   array $config_ini  The base config array.
 * @param   array $custom_ini  Overrides to apply on top of the base array.
 * @return  array       The merged results.
 */
function ini_merge($config_ini, $custom_ini)
{
	foreach ($custom_ini as $k => $v) {
		if (is_array($v)) {
			$config_ini[$k] = ini_merge(isset($config_ini[$k]) ? $config_ini[$k] : array(), $custom_ini[$k]);
		} else {
			$config_ini[$k] = $v;
		}
	}
	return $config_ini;
}

/**
 * Support function -- load the main configuration options, overriding with
 * custom local settings if applicable.
 *
 * @return  array       The desired config.ini settings in array format.
 */
function readConfig(){
	//Read default configuration file
	$configFile = '../../sites/default/conf/config.ini';
	$mainArray  = parse_ini_file($configFile, true);

	global $serverName, $instanceName;
	$serverUrl   = $_SERVER['SERVER_NAME'];
	$server      = $serverUrl;
	$serverParts = explode('.', $server);
	$serverName  = 'default';
	while (count($serverParts) > 0){
		$tmpServername = join('.', $serverParts);
		$configFile    = "../../sites/$tmpServername/conf/config.ini";
		if (file_exists($configFile)){
			$serverArray = parse_ini_file($configFile, true);
			$mainArray   = ini_merge($mainArray, $serverArray);
			$serverName  = $tmpServername;

			$passwordFile = "../../sites/$tmpServername/conf/config.pwd.ini";
			if (file_exists($passwordFile)){
				$serverArray = parse_ini_file($passwordFile, true);
				$mainArray   = ini_merge($mainArray, $serverArray);
			}
			break;
		}

		array_shift($serverParts);
	}

	// Sanity checking to make sure we loaded a good file
	if ($serverName == 'default'){
		global $logger;
		if ($logger){
			$logger->log('Did not find servername for server ' . $_SERVER['SERVER_NAME'], PEAR_LOG_ERR);
		}
		PEAR_Singleton::raiseError("Invalid configuration, could not find site for " . $_SERVER['SERVER_NAME']);
		die();
	}

	if ($mainArray == false){
		die("Unable to parse configuration file $configFile, please check syntax");
	}

	// Set a instanceName so that memcache variables can be stored for a specific instance of Pika,
	// rather than the $serverName will depend on the specific interface a user is browsing to.
	$instanceName = parse_url($mainArray['Site']['url'], PHP_URL_HOST);
	// Have to set the instanceName before the transformation of $mainArray['Site']['url'] below.

	//We no longer are doing proxies as described above so we can preserve SSL now.
	if (isset($_SERVER['HTTPS'])){
		$mainArray['Site']['url'] = "https://" . $serverUrl;
	}else{
		$mainArray['Site']['url'] = "http://" . $serverUrl;
	}

	return $mainArray;
}
