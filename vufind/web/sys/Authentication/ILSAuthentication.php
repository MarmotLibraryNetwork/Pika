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

require_once 'Authentication.php';
require_once ROOT_DIR . '/CatalogConnection.php';

class ILSAuthentication implements Authentication {
	private $username;
	private $password;
	private $driverName;
	/** @var  AccountProfile */
	private $accountProfile;
	private $catalogConnection;

	public function __construct($additionalInfo) {
		if (array_key_exists('driver', $additionalInfo)){
			$this->driverName     = $additionalInfo['driver'];
			$this->accountProfile = $additionalInfo['accountProfile'];
		}
		$this->catalogConnection = CatalogFactory::getCatalogConnectionInstance($this->driverName, $this->accountProfile);
	}

	public function authenticate($validatedViaSSO){
		global $logger;
		//Check to see if the username and password are provided
		if (!array_key_exists('username', $_REQUEST) && !array_key_exists('password', $_REQUEST)){
			$logger->log("Username and password not provided, returning user if it exists", PEAR_LOG_INFO);
			//If not, check to see if we have a valid user already authenticated
			if (UserAccount::isLoggedIn()){ //TODO: prevent in case of masquerade??
				return UserAccount::getLoggedInUser();
			}
		}
		$this->username = empty($_REQUEST['username']) ? '' : $_REQUEST['username'];
		$this->password = empty($_REQUEST['password']) ? '' : $_REQUEST['password'];

		if (is_array($this->username)){
			$this->username = reset($this->username);
		}
		if (is_array($this->password)){
			$this->password = reset($this->password);
		}

		$logger->log("Authenticating user '{$this->username}', '{$this->password}' via the ILS", PEAR_LOG_DEBUG);
		if(!$validatedViaSSO && ($this->username == '' || $this->password == '')){
			$user = new PEAR_Error('authentication_error_blank');
		} else {
			// Connect to the correct catalog depending on the driver for this account
			$catalog = $this->catalogConnection;

			if ($catalog->status) {
				/** @var User $patron */
				$patron = $catalog->patronLogin($this->username, $this->password, null, $validatedViaSSO);
				if ($patron && !PEAR_Singleton::isError($patron)) {
					/** @var User $user */
					$user = $patron;
				} elseif (PEAR_Singleton::isError($patron)){
					$user = $patron;
				} else{
					$user = new PEAR_Error('authentication_error_invalid');
				}
			} else {
				$user = new PEAR_Error('authentication_error_technical');
			}
		}
		return $user;
	}

	public function validateAccount($username, $password, $parentAccount, $validatedViaSSO) {
		global $logger;
		$this->username = $username;
		$this->password = $password;

		$logger->log("validating account for user '{$this->username}', '{$this->password}' via the ILS", PEAR_LOG_DEBUG);
		if($this->username == '' || ($this->password == '' && !$validatedViaSSO)){
			$validUser = new PEAR_Error('authentication_error_blank');
		} else {
			// Connect to the correct catalog depending on the driver for this account
			$catalog = CatalogFactory::getCatalogConnectionInstance($this->driverName);

			if ($catalog->status) {
				$patron = $catalog->patronLogin($this->username, $this->password, $parentAccount, $validatedViaSSO);
				if ($patron && !PEAR_Singleton::isError($patron)) {
					$validUser = $patron;
				} elseif (PEAR_Singleton::isError($patron)){
					$validUser = $patron;
				} else{
					$validUser = new PEAR_Error('authentication_error_invalid');
				}
			} else {
				$validUser = new PEAR_Error('authentication_error_technical');
			}
		}
		return $validUser;
	}
}
