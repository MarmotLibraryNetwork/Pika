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

require_once 'SessionInterface.php';

class MemcacheSession extends SessionInterface {

	static private $connection;
	
	public function init($lt, $rememberMeLifetime) {
		global $configArray;
	
		// Set defaults if nothing set in config file.
		$host = isset($configArray['Session']['memcache_host']) ?
		$configArray['Session']['memcache_host'] : 'localhost';
		$port = isset($configArray['Session']['memcache_port']) ?
		$configArray['Session']['memcache_port'] : 11211;
		$timeout = isset($configArray['Session']['memcache_connection_timeout']) ?
		$configArray['Session']['memcache_connection_timeout'] : 1;

		// Connect to Memcache:
		self::$connection = new Memcache();
		if (!@self::$connection->connect($host, $port, $timeout)) {
			PEAR_Singleton::raiseError(new PEAR_Error("Could not connect to Memcache (host = {$host}, port = {$port})."));
		}

		// Call standard session initialization from this point.
		parent::init($lt, $rememberMeLifetime);
	}

	static public function read($sess_id)
	{
		return self::$connection->get("vufind_sessions/{$sess_id}");
	}
	 
	static public function write($sess_id, $data)
	{
		if (isset($_SESSION['rememberMe']) && $_SESSION['rememberMe'] == true){
			$sessionLifetime = self::$rememberMeLifetime; 
		}else{
			$sessionLifetime = self::$lifetime;
		}
		return self::$connection->set("vufind_sessions/{$sess_id}", $data, 0, $sessionLifetime);
	}

	static public function destroy($sess_id)
	{
		// Perform standard actions required by all session methods:
		parent::destroy($sess_id);

		// Perform Memcache-specific cleanup:
		return self::$connection->delete("vufind_sessions/{$sess_id}");
	}
}
