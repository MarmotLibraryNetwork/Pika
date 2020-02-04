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

class CASAuthentication implements Authentication {
	static $clientInitialized = false;


	public function __construct($additionalInfo) {

	}

	public function authenticate($validatedViaSSO){
		$this->initializeCASClient();

		try{
			global $logger;
			$logger->log("Forcing CAS authentication", PEAR_LOG_DEBUG);
			$isValidated = phpCAS::forceAuthentication();
			if ($isValidated){
				$userAttributes = phpCAS::getAttributes();
				//TODO: If we use other CAS systems we will need a configuration option to store which
				//attribute the id is in
				$userId = $userAttributes['flcid'];
				return $userId;
			}else{
				return false;
			}
		}catch (CAS_AuthenticationException $e){
			global $logger;
			$logger->log("Error authenticating in CAS $e", PEAR_LOG_ERR);
			$isValidated = false;
		}

		return $isValidated;
	}

	/**
	 * @param $username       string Should be null for CAS
	 * @param $password       string Should be null for CAS
	 * @param $parentAccount  User|null
	 * @param $validatedViaSSO boolean
	 * @return bool|PEAR_Error|string return false if the user cannot authenticate, the barcode if they can, and an error if configuration is incorrect
	 */
	public function validateAccount($username, $password, $parentAccount, $validatedViaSSO) {
		if($username == '' || $password == ''){
			$this->initializeCASClient();

			try{
				global $logger;
				$logger->log("Checking CAS Authentication", PEAR_LOG_DEBUG);
				$isValidated = phpCAS::checkAuthentication();
				$logger->log("isValidated = ". ($isValidated ? 'true' : 'false'), PEAR_LOG_DEBUG);
			}catch (CAS_AuthenticationException $e){
				global $logger;
				$logger->log("Error validating account in CAS $e", PEAR_LOG_ERR);
				$isValidated = false;
			}

			if ($isValidated) {
				//We have a valid user within CAS.  Return the user id
				$userAttributes = phpCAS::getAttributes();
				//TODO: If we use other CAS systems we will need a configuration option to store which
				//attribute the id is in
				if (isset($userAttributes['flcid'])) {
					$userId = $userAttributes['flcid'];
					return $userId;
				}else{
					$logger->log("Did not find flcid in user attributes " . print_r($userAttributes, true), PEAR_LOG_WARNING);
				}
			}else{
				return false;
			}
		} else {
			return new PEAR_Error('Should not pass username and password to account validation for CAS');
		}
	}

	public function logout() {
		//global $logger;
		$this->initializeCASClient();
		//$logger->log('Logging the user out from CAS', PEAR_LOG_INFO);
		phpCAS::logout();
	}

	protected function initializeCASClient() {
		if (!CASAuthentication::$clientInitialized) {
			require_once ROOT_DIR . '/CAS-1.3.4/CAS.php';

			global $library;
			global $configArray;
			if ($configArray['System']['debug']) {
				phpCAS::setDebug();
				phpCAS::setVerbose(true);
			}

			global $logger;
			$logger->log("Initializing CAS Client", PEAR_LOG_DEBUG);

			phpCAS::client(CAS_VERSION_3_0, $library->casHost, (int)$library->casPort, $library->casContext);

			phpCAS::setNoCasServerValidation();

			CASAuthentication::$clientInitialized = true;
		}
	}
}
