<?php
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