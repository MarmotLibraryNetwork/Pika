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

class DatabaseAuthentication implements Authentication {
	public function __construct($additionalInfo) {

	}

	public function authenticate($validatedViaSSO) {
		$username = $_POST['username'];
		$password = $_POST['password'];
		return $this->login($username, $password);
	}

	public function validateAccount($username, $password, $parentAccount = null, $validatedViaSSO = false) {
		return $this->login($username, $password);
	}

	private function login($username, $password){
		if (empty($username) || empty($password)) {
			$user = new PEAR_Error('authentication_error_blank');
		} else {
			$user               = new User();
			$user->cat_username = $username;
			$user->cat_password = $password;
			if (!$user->find(true)) {
				$user = new PEAR_Error('authentication_error_invalid');
			}
		}
		return $user;
	}
}
