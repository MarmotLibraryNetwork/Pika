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
use jasig\phpcas\CAS;
use Pika\Logger;

require_once ROOT_DIR . '/CatalogConnection.php';


class CASAuthentication implements Authentication {
	static $clientInitialized = false;
	private $logger;

	public function __construct($additionalInfo = []) {
		$this->logger = new Logger(__CLASS__);
		$this->logger->debug('Initialized logger in CAS Authentication class');
	}

	public function authenticate($validatedViaSSO = false){
		$this->initializeCASClient();

		try{

			$this->logger->debug("Forcing CAS authentication");
			$isValidated = phpCAS::forceAuthentication();
			if ($isValidated){
				$userAttributes = phpCAS::getAttributes();
				//TODO: If we use other CAS systems we will need a configuration option to store which
				//attribute the id is in
				$userId = $userAttributes['flcid'];
				$this->logger->debug("CAS authenticated user, reporting Sierra barcode $userId", $userAttributes);
				return $userId;
			}else{
				return false;
			}
		}catch (CAS_AuthenticationException $e){

			$this->logger->error("Error authenticating in CAS $e");
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
				$this->logger->debug('Checking CAS Authentication');
				$isValidated = phpCAS::checkAuthentication();
				$this->logger->debug('CAS isValidated = '. ($isValidated ? 'true' : 'false'));
			}catch (CAS_AuthenticationException $e){
				$this->logger->error("Error validating account in CAS $e");
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
					$this->logger->warning('Did not find flcid in user attributes ',  $userAttributes);
				}
			}else{
				$this->logger->debug('Returning false for CAS validation');
				return false;
			}
		} else {
			$this->logger->debug('Returning PEAR error for CAS validation');
			return new PEAR_Error('Should not pass username and password to account validation for CAS');
		}
	}

	public function logout() {
		$this->initializeCASClient();
		$this->logger->info('Logging the user out from CAS');
		phpCAS::logout();
		$this->logger->info('Logging the user out from CAS');
	}

	protected function initializeCASClient() {
		if (!CASAuthentication::$clientInitialized) {

			/** @var Library $library */
			global $library;
			global $configArray;
			if ($configArray['System']['debug']) {
				phpCAS::setLogger($this->logger);
				phpCAS::setVerbose(true);
			}


			$this->logger->debug('Initializing CAS Client');

			$service_name = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'];
			// Service name allows the cas system to determine if our site is allowed to get validation
			phpCAS::client(CAS_VERSION_3_0, $library->casHost, (int)$library->casPort, $library->casContext, $service_name);

			phpCAS::setNoCasServerValidation();

			CASAuthentication::$clientInitialized = true;
		}
	}
}
