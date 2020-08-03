<?php

/*
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

namespace Pika\Session;

/**
 * FileSession.php
 *
 * @category Pika
 * @package  Sessions
 * @author   Chris Froese
 *
 */
use SearchEntry;

class FileSession extends \SessionHandler implements \SessionHandlerInterface {

	public function __construct()
	{
		ini_set('session.save_handler', 'files');
		global $configArray;
		if (isset($configArray['Session']['lifetime'])) {
			$gc_lifetime = (int)$configArray['Session']['lifetime'];
			if ($gc_lifetime > 1) {
				ini_set('session.gc_maxlifetime', $gc_lifetime);
			}
		} else {
			ini_set('session.gc_maxlifetime', 1440); // php default
		}
		return true;
	}


	/**
	 * Close the session
	 *
	 * @link https://php.net/manual/en/sessionhandlerinterface.close.php
	 * @return bool true on success false otherwise
	 * Note this value is returned internally to PHP for processing.
	 */
	public function close()
	{
		return parent::close();
	}

	/**
	 * Destroy a session
	 *
	 * Clean out unsaved patron searches.
	 *
	 * @link https://php.net/manual/en/sessionhandlerinterface.destroy.php
	 * @param string $session_id The session ID being destroyed.
	 * @return bool true on success false otherwise
	 * Note this value is returned internally to PHP for processing.
	 */
	public function destroy($session_id)
	{
		// Delete the searches stored for this session
		require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
		$search     = new SearchEntry();
		$searchList = $search->getSearches($session_id);
		// Make sure there are some
		if (count($searchList) > 0) {
			foreach ($searchList as $oldSearch) {
				// And make sure they aren't saved
				if ($oldSearch->saved == 0) {
					$oldSearch->delete();
				}
			}
		}
		return parent::destroy($session_id);
	}

	/**
	 * Cleanup old sessions
	 *
	 * Memcached session interface handles this nicely.
	 *
	 * @link https://php.net/manual/en/sessionhandlerinterface.gc.php
	 * @param int $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed.
	 * @return bool true on success false otherwise
	 *
	 */
	public function gc($maxlifetime)
	{
		return parent::gc($maxlifetime);
	}

	/**
	 * Initialize session
	 *
	 * @link https://php.net/manual/en/sessionhandlerinterface.open.php
	 * @param string $save_path The path where to store/retrieve the session.
	 * @param string $name The session name.
	 * @return bool true on success false otherwise
	 * Note this value is returned internally to PHP for processing.
	 */
	public function open($save_path, $name)
	{
		return parent::open($save_path, $name);
	}

	/**
	 * Read session data
	 *
	 * @link https://php.net/manual/en/sessionhandlerinterface.read.php
	 * @param string $session_id The session id to read data for.
	 * @return string Returns an encoded string of the read data.
	 * If nothing was read, it must return an empty string.
	 * Note this value is returned internally to PHP for processing.
	 */
	public function read($session_id)
	{
		return @parent::read($session_id);
	}

	/**
	 * Write session data
	 *
	 * @link https://php.net/manual/en/sessionhandlerinterface.write.php
	 * @param string $session_id The session id.
	 * @param string $session_data <p>
	 * The encoded session data. This data is the
	 * result of the PHP internally encoding
	 * the $_SESSION superglobal to a serialized
	 * string and passing it as this parameter.
	 * Please note sessions use an alternative serialization method.
	 * </p>
	 * @return bool true on success false otherwise
	 * Note this value is returned internally to PHP for processing.
	 */
	public function write($session_id, $session_data)
	{
		return parent::write($session_id, $session_data);
	}
}
