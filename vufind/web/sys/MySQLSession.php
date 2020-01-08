<?php

require_once 'SessionInterface.php';
require_once ROOT_DIR . '/services/MyResearch/lib/Session.php';

class MySQLSession extends SessionInterface {

	static $sessionStartTime;

	static public function read($sess_id)
	{
		$s             = new Session();
		$s->session_id = $sess_id;

		$cookieData     = '';
		$saveNewSession = false;
		$curTime        = time();
		if ($s->find(true)) {
			//First check to see if the session expired
			MySQLSession::$sessionStartTime = $curTime;
			if ($s->remember_me == 1) {
				$sessionExpirationTime = $s->last_used + self::$rememberMeLifetime;
			} else {
				$sessionExpirationTime = $s->last_used + self::$lifetime;
			}
			// make sure we don't have an active session
			if (session_status() === PHP_SESSION_ACTIVE || session_status() === 2) {
				$sessionActive = true;
			} else {
				$sessionActive = false;
			}
			if ($curTime > $sessionExpirationTime) {
				if(!$sessionActive){
					$s->delete();
					//Start a new session
					session_start();
					session_regenerate_id(true);
					$sess_id        = session_id();
					$_SESSION       = array();
					$saveNewSession = true;
				}
			} else {
				// updated the session in the database to show that we just used it
				$s->last_used = $curTime;
				$s->update();
				$cookieData = $s->data;
			}
		} else {
			$saveNewSession = true;
		}
		if ($saveNewSession) {
			$s->session_id = $sess_id;
			//There is no active session, we need to create a new one.
			$s->last_used = $curTime;
			// in date format - easier to read
			$s->created = date('Y-m-d h:i:s');
			if (isset($_SESSION['rememberMe']) && $_SESSION['rememberMe'] == true) {
				$s->remember_me = 1;
			} else {
				$s->remember_me = 0;
			}
			$s->insert();
		}
		// make sure this is cast as a string or an error will be generated -- esp if null.
		return (string)$cookieData;
	}

	static public function write($sess_id, $data)
	{
		$s             = new Session();
		$s->session_id = $sess_id;
		// if the session is active we CANNOT write to cookies!
		if (session_status() === PHP_SESSION_ACTIVE || session_status() === 2) {
			$sessionActive = true;
		} else {
			$sessionActive = false;
		}

		if ($s->find(true)) {
			if ($s->last_used != MySQLSession::$sessionStartTime) {
				//parent::$logger->info("Not Writing Session data $sess_id because another process wrote to it already");
				return true;
			}
			if ($s->data != $data) {
				$s->data      = $data;
				$s->last_used = time();
				//parent::$logger->info("Session data changed $sess_id {$s->last_used} ", ['data' => $data]);
			}
			if (isset($_SESSION['rememberMe']) && (bool)$_SESSION['rememberMe'] === true) {
				$s->remember_me = 1;
				setcookie(session_name(), session_id(), time() + self::$rememberMeLifetime, '/');
			} else {
				if (!$sessionActive) {
					session_set_cookie_params(0);
				}
				$s->remember_me = 0;
			}
			parent::write($sess_id, $data);
			$r = $s->update();
			// session was updated
			// session_write_close expects a boolean to be returned from the callback.
			// if it doesn't get the expected value it throws an error.
			if ($r === false) {
				return false;
			} else {
				return true;
			}
		} else {
			//No session active
			return false;
		}
	}

	static public function destroy($sess_id)
	{

		//$this->logger->info("Destroying session $sess_id");
		// Perform standard actions required by all session methods:
		parent::destroy($sess_id);

		// Now do database-specific destruction:
		$s             = new Session();
		$s->session_id = $sess_id;
		return $s->delete();
	}

	static public function gc($sess_maxlifetime)
	{
		//Doing this in PHP  at random times, causes problems for VuFind, do it as part of cron in Java
		/*$s = new Session();
		$s->whereAdd('last_used + ' . $sess_maxlifetime . ' < ' . time());
		$s->whereAdd('remember_me = 0');
		$s->delete(true);

		$s = new Session();
		$s->whereAdd('last_used + ' . SessionInterface::$rememberMeLifetime . ' < ' . time());
		$s->whereAdd('remember_me = 1');
		$s->delete(true);*/
	}

}
