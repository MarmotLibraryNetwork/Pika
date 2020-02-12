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

// TODO: Delete me
class SessionInterface {

	public static int $lifetime = 3600;
	public static int $rememberMeLifetime = 1209600;


	public function init($lt, $rememberMeLifetime)
	{
		self::$lifetime           = $lt;
		self::$rememberMeLifetime = $rememberMeLifetime;
		session_set_save_handler(array(get_class($this), 'open'), array(get_class($this), 'close'),
		 array(get_class($this), 'read'), array(get_class($this), 'write'), array(get_class($this), 'destroy'),
		 array(get_class($this), 'gc'));
		//Have to set the default timeout before we call session start, set a really long timeout by default since PHP doesn't like to extend the PHPSESSION timeout
		//Set one year by default
		session_set_cookie_params(0, '/');
		session_start();
	}

	// the following need to be static since they are used as callback functions
	static public function open($sess_path, $sess_name)
	{
		return true;
	}

	static public function close()
	{
		return true;
	}

	static public function read($sess_id)
	{
	}

	static public function write($sess_id, $data)
	{
		return true;
	}

	// IMPORTANT:  The functionality defined in this method is global to all session
	//      mechanisms.  If you override this method, be sure to still call
	//      parent::destroy() in addition to any new behavior.
	static public function destroy($sess_id)
	{
		// Delete the searches stored for this session
		require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
		$search     = new SearchEntry();
		$searchList = $search->getSearches($sess_id);
		// Make sure there are some
		if (count($searchList) > 0) {
			foreach ($searchList as $oldSearch) {
				// And make sure they aren't saved
				if ($oldSearch->saved == 0) {
					$oldSearch->delete();
				}
			}
		}
	}

	// how often does this get called (if at all)?

	// *** 08/Oct/09 - Greg Pendlebury
	// Clearly this is being called. Production installs with
	//   thousands of sessions active are showing no old sessions.
	// What I can't do is reproduce for testing. It might need the
	//   search delete code from 'destroy()' if it is not calling it.
	// *** 09/Oct/09 - Greg Pendlebury
	// Anecdotal testing Today and Yesterday seems to indicate destroy()
	//   is called by the garbage collector and everything is good.
	// Something to keep in mind though.
	static public function gc($sess_maxlifetime)
	{
	}
}
