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

require_once 'PEAR.php';
require_once 'Authentication.php';
require_once 'LDAPConfigurationParameter.php';

class LDAPAuthentication implements Authentication {

	private $username;
	private $password;
	private $ldapConfigurationParameter;

	public function __construct($additionalInfo){
		$this->ldapConfigurationParameter = new LDAPConfigurationParameter();
	}

	public function authenticate($validatedViaSSO){
		$this->username = $_POST['username'];
		$this->password = $_POST['password'];
		if ($this->username == '' || $this->password == ''){
			return new PEAR_Error('authentication_error_blank');
		}
		$this->trimCredentials();
		return $this->bindUser();
	}

	private function trimCredentials(){
		$this->username = trim($this->username);
		$this->password = trim($this->password);
	}

	private function bindUser(){
		$ldapConnectionParameter = $this->ldapConfigurationParameter->getParameter();

		// Try to connect to LDAP and die if we can't; note that some LDAP setups
		// will successfully return a resource from ldap_connect even if the server
		// is unavailable -- we need to check for bad return values again at search
		// time!
		$ldapConnection = @ldap_connect($ldapConnectionParameter['host'],
			$ldapConnectionParameter['port']);
		if (!$ldapConnection){
			return new PEAR_ERROR('authentication_error_technical');
		}

		// Set LDAP options -- use protocol version 3 and then initiate TLS so we
		// can have a secure connection over the standard LDAP port.
		@ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
		if (!@ldap_start_tls($ldapConnection)){
			return new PEAR_ERROR('authentication_error_technical');
		}

		// If bind_username and bind_password were supplied in the config file, use
		// them to access LDAP before proceeding.  In some LDAP setups, these
		// settings can be excluded in order to skip this step.
		if (isset($ldapConnectionParameter['bind_username']) &&
			isset($ldapConnectionParameter['bind_password'])
		){
			$ldapBind = @ldap_bind($ldapConnection, $ldapConnectionParameter['bind_username'],
				$ldapConnectionParameter['bind_password']);
			if (!$ldapBind){
				return new PEAR_ERROR('authentication_error_technical');
			}
		}

		// Search for username
		$ldapFilter = $ldapConnectionParameter['username'] . '=' . $this->username;
		$ldapSearch = @ldap_search($ldapConnection, $ldapConnectionParameter['basedn'],
			$ldapFilter);
		if (!$ldapSearch){
			return new PEAR_ERROR('authentication_error_technical');
		}

		$info = ldap_get_entries($ldapConnection, $ldapSearch);
		if ($info['count']){
			// Validate the user credentials by attempting to bind to LDAP:
			$ldapBind = @ldap_bind($ldapConnection, $info[0]['dn'], $this->password);
			if ($ldapBind){
				// If the bind was successful, we can look up the full user info:
				$ldapSearch = ldap_search($ldapConnection, $ldapConnectionParameter['basedn'],
					$ldapFilter);
				$data       = ldap_get_entries($ldapConnection, $ldapSearch);
				return $this->processLDAPUser($data, $ldapConnectionParameter);
			}
		}

		return new PEAR_ERROR('authentication_error_invalid');
	}

	private function processLDAPUser($data, $ldapConnectionParameter){
		$user             = new User();
		$user->ilsUserId  = $this->username;
		$isUserInDatabase = $this->isInUserTable($user);
		for ($i = 0;$i < $data["count"];$i++){
			for ($j = 0;$j < $data[$i]["count"];$j++){

				if (($data[$i][$j] == $ldapConnectionParameter['firstname']) &&
					($ldapConnectionParameter['firstname'] != "")
				){
					$user->firstname = $data[$i][$data[$i][$j]][0];
				}

				if ($data[$i][$j] == $ldapConnectionParameter['lastname'] &&
					($ldapConnectionParameter['lastname'] != "")
				){
					$user->lastname = $data[$i][$data[$i][$j]][0];
				}

				if ($data[$i][$j] == $ldapConnectionParameter['email'] &&
					($ldapConnectionParameter['email'] != "")
				){
					$user->email = $data[$i][$data[$i][$j]][0];
				}

				if ($data[$i][$j] == $ldapConnectionParameter['cat_username'] &&
					($ldapConnectionParameter['cat_username'] != "")
				){
					$user->cat_username = $data[$i][$data[$i][$j]][0];
				}

				if ($data[$i][$j] == $ldapConnectionParameter['cat_password'] &&
					($ldapConnectionParameter['cat_password'] != "")
				){
					$user->cat_password = $data[$i][$data[$i][$j]][0];
				}

			}
		}
		$this->synchronizeUserTableWithLDAPEntries($isUserInDatabase, $user);
		return $user;
	}

	private function isInUserTable(User $user){
		return $user->find(true);
	}

	private function synchronizeUserTableWithLDAPEntries($userIsInVufindDatabase, $user){
		if ($userIsInVufindDatabase){
			$user->update();
		}else{
			$user->created = date('Y-m-d');
			$user->insert();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function validateAccount($username, $password, $parentAccount, $validatedViaSSO){
		// TODO: Implement validateAccount() method.
	}
}
