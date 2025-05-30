<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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

use Pika\Cache;
use Pika\Logger;

require_once ROOT_DIR . '/sys/Authentication/AuthenticationFactory.php';

class UserAccount {
	private static $isLoggedIn = null;
	private static $primaryUserData = null;
	/** @var User|false */
	private static $primaryUserObjectFromDB = null;
	/** @var User|false $guidingUserObjectFromDB */
	private static $guidingUserObjectFromDB = null;
	private static $userRoles = null;
	private static $validatedAccounts = [];

	private static $logger;

	/**
	 * @return Logger
	 */
	static function getLogger(){
		if (is_null(self::$logger)){
			self::$logger = new Pika\Logger(__CLASS__);
		}
		return self::$logger;
	}

	/**
	 * Checks whether the user is logged in.
	 *
	 * When logged in we store information the id of the active user within the session.
	 * The actual user is stored within memcache
	 *
	 * @return bool|User
	 */
	public static function isLoggedIn(){
		if (UserAccount::$isLoggedIn == null){
			if (isset($_SESSION['activeUserId'])){
				UserAccount::$isLoggedIn = true;
			}else{
				UserAccount::$isLoggedIn = false;
				//Need to check cas just in case the user logged in from another site
				//if ($action != 'AJAX' && $action != 'DjatokaResolver' && $action != 'Logout' && $module != 'MyAccount' && $module != 'API' && !isset($_REQUEST['username'])){
				//If the library uses CAS/SSO we may already be logged in even though they never logged in within Pika

				global $library;
				global $action;
				global $module;
				if (strlen($library->casHost) > 0){
					$checkCAS = !isset($_SESSION['lastCASCheck']) || time() - $_SESSION['lastCASCheck'] > 10;
					if ($checkCAS && !isset($_REQUEST['username']) && !in_array($action, ['AJAX', 'DjatokaResolver', 'Logout']) && !in_array($module, ['MyAccount', 'API'])){
						//Check CAS first
						//require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';

						$casAuthentication        = new CASAuthentication(null);
						//self::getLogger()->debug("Do CAS validation in UserAccount::isLoggedIn()");
						$casUsername              = $casAuthentication->validateAccount(null, null, null, false);
						$_SESSION['lastCASCheck'] = time();
						self::getLogger()->debug("Checked CAS Authentication from UserAccount::isLoggedIn result was $casUsername");
						if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
							//The user could not be authenticated in CAS
							UserAccount::$isLoggedIn = false;
						}else{
							self::getLogger()->debug('We got a valid user from CAS, getting the user from the database');
							//We have a valid user via CAS, need to do a log into Pika
							$_REQUEST['casLogin']    = true;
							UserAccount::$isLoggedIn = true;
							//Set the active user id for the user
							$user          = new User();
							$user->barcode = $casUsername;
							if ($user->find(true)){
								$_SESSION['activeUserId']             = $user->id;
								UserAccount::$primaryUserObjectFromDB = $user;
							}
						}
					}
				}
			}
		}
		//self::getLogger()->debug('UserAccount::$isLoggedIn = ' . (UserAccount::$isLoggedIn ? 'true' : 'false'));
		return UserAccount::$isLoggedIn;
	}

	public static function getActiveUserId(){
		return $_SESSION['activeUserId'] ?? false;
	}

	public static function userHasRole($roleName){
		$userRoles = UserAccount::getActiveRoles();
		return in_array($roleName, $userRoles);
	}

	public static function userHasRoleFromList(array $roleNames){
		$userRoles = UserAccount::getActiveRoles();
		foreach ($roleNames as $roleName){
			if (in_array($roleName, $userRoles)){
				return true;
			}
		}
		return false;
	}

	public static function getActiveRoles(){
		if (UserAccount::$userRoles == null){
			UserAccount::$userRoles = [];
			if (UserAccount::isLoggedIn()){

				//Roles for the user
				require_once ROOT_DIR . '/sys/Administration/Role.php';
				$role = new Role();
				$role->joinAdd(['roleId', 'user_roles:roleId']);
				$role->whereAdd('userId = ' . UserAccount::getActiveUserId());
				$role->orderBy('name');
				UserAccount::$userRoles = $role->fetchAll('name');
				$canUseTestRoles        = in_array('userAdmin', UserAccount::$userRoles);

				if ($canUseTestRoles){
					//Test roles if we are doing overrides
					$testRole = $_REQUEST['test_role'] ?? $_COOKIE['test_role'] ?? '';
					if ($testRole != ''){
						//Ignore the standard roles for the user
						UserAccount::$userRoles = [];

						$testRoles = is_array($testRole) ? $testRole : [$testRole];
						foreach ($testRoles as $tmpRole){
							$role = new Role();
							if (is_numeric($tmpRole)){
								$role->roleId = $tmpRole;
							}else{
								$role->name = $tmpRole;
							}
							if ($role->find(true)){
								UserAccount::$userRoles[$role->name] = $role->name;
							}
						}
					}
				}

				//TODO: Figure out roles for masquerade mode see User.php line 251

			}
		}
		return UserAccount::$userRoles;
	}

	private static function loadUserObjectFromDatabase(){
		if (UserAccount::$primaryUserObjectFromDB == null){
			$activeUserId = UserAccount::getActiveUserId();
			if ($activeUserId){
				$user     = new User();
				$user->id = $activeUserId;
				if ($user->find(true)){
					//self::getLogger()->debug('Loading User DB Object');
					UserAccount::$primaryUserObjectFromDB = $user;
					return;
				}
			}
			//self::getLogger()->debug('Did not load User DB Object');
			UserAccount::$primaryUserObjectFromDB = false;
		}
	}

	/**
	 * @return false|User
	 */
	public static function getActiveUserObj(){
		UserAccount::loadUserObjectFromDatabase();
		return UserAccount::$primaryUserObjectFromDB;
	}

	public static function getUserDisplayName(){
		UserAccount::loadUserObjectFromDatabase();
		if (UserAccount::$primaryUserObjectFromDB != false){
			if (strlen(UserAccount::$primaryUserObjectFromDB->displayName)){
				return UserAccount::$primaryUserObjectFromDB->displayName;
			}else{
				return UserAccount::$primaryUserObjectFromDB->firstname . ' ' . UserAccount::$primaryUserObjectFromDB->lastname;
			}
		}
		return '';
	}

	public static function getUserPType(){
		UserAccount::loadUserObjectFromDatabase();
		if (UserAccount::$primaryUserObjectFromDB != false){
			return UserAccount::$primaryUserObjectFromDB->patronType;
		}
		return 'logged out';
	}

	public static function getDisableCoverArt(){
		UserAccount::loadUserObjectFromDatabase();
		if (UserAccount::$primaryUserObjectFromDB != false){
			return UserAccount::$primaryUserObjectFromDB->disableCoverArt;
		}
		return 'logged out';
	}

	public static function hasLinkedUsers(){
		UserAccount::loadUserObjectFromDatabase();
		if (UserAccount::$primaryUserObjectFromDB != false){
			return count(UserAccount::$primaryUserObjectFromDB->getLinkedUserObjects()) > 0;
		}
		return 'false';
	}

	public static function getUserHomeLocationId(){
		UserAccount::loadUserObjectFromDatabase();
		if (UserAccount::$primaryUserObjectFromDB != false){
			return UserAccount::$primaryUserObjectFromDB->homeLocationId;
		}
		return -1;
	}

	/**
	 * Fetch the home library of the main logged in User.
	 * Prefer this method so that the library doesn't need to be retrieved from the database repeatedly
	 * @return bool|Library|null
	 */
	public static function getUserHomeLibrary(){
		UserAccount::loadUserObjectFromDatabase();
		if (UserAccount::$primaryUserObjectFromDB != false){
			return UserAccount::$primaryUserObjectFromDB->getHomeLibrary();
		}
		return false;
	}

	public static function getUserPartnerLibraries(){
		$userLibrary = self::getUserHomeLibrary();
		$userLibraryId = $userLibrary->libraryId;
		$library = new Library();
		$library->partnerOfSystem = $userLibraryId;
		$library->find();
		$partners = [];
		while ($library->fetch()){
			$partners[$library->libraryId] = $library->displayName;
		}
		if (empty($partners)){
			return false;
		}else{
			return $partners;
		}
	}

	public static function isUserMasquerading(){
		return !empty($_SESSION['guidingUserId']);
	}

	public static function getGuidingUserObject(){
		if (UserAccount::$guidingUserObjectFromDB == null){
			UserAccount::$guidingUserObjectFromDB = false; // default value
			if (UserAccount::isUserMasquerading()){
				$activeUserId = $_SESSION['guidingUserId'];
				if (!empty($activeUserId)){
					$user     = new User();
					$user->id = $activeUserId;
					if ($user->find(true)){
						UserAccount::$guidingUserObjectFromDB = $user;
					}
				}
			}
		}
		return UserAccount::$guidingUserObjectFromDB;
	}

	/**
	 * @return bool|null|User
	 */

	public static function getLoggedInUser(){
		global $action;
		global $module;
		global $library;
		global $interface;
		$cache  = new Pika\Cache();
		if (UserAccount::$isLoggedIn != null){
			if (UserAccount::$isLoggedIn){
				if (!is_null(UserAccount::$primaryUserData)){
					return UserAccount::$primaryUserData;
				}
			}else{
				return false;
			}
		}
		$userData = false;
		if (!empty($_SESSION['activeUserId'])){
			$activeUserId   = $_SESSION['activeUserId'];
			$patronCacheKey = $cache->makePatronKey('patron', $activeUserId);
			$userData       = $cache->get($patronCacheKey, false);
			if ($userData === false || isset($_REQUEST['reload'])){
				//Load the user from the database
				$userData     = new User();
				$userData->id = $activeUserId;
				if (!empty($userData->find(true))){
					$accountProfile = $userData->getAccountProfile();
					if (!is_null($accountProfile)){
						if (!$accountProfile->usingPins()){
							$cat_username   = $userData->cat_username;
							$barcode_or_pin = $userData->barcode;
						}else{
							$cat_username   = $userData->barcode;
							$barcode_or_pin = $userData->getPassword();
						}
						//self::getLogger()->debug("Loading user with ID $userData->id because we didn't have data in memcache");
						$userData = UserAccount::validateAccount($cat_username, $barcode_or_pin, $userData->source);
						self::updateSession($userData);
					}else{
						self::getLogger()->error('Failed to fetch account profile for active User Id' . $activeUserId);
						return false;
					}
				} else {
					self::getLogger()->error('Did not find user data for active User Id ' . $activeUserId);
					return false;
				}
			}
			UserAccount::$isLoggedIn = true;

			$masqueradeMode = UserAccount::isUserMasquerading();
			if ($masqueradeMode){
				global $guidingUser;
				// todo: This is never saved to memcache
				// $guidingUser = $cache->get("user_{$serverName}_{$_SESSION['guidingUserId']}"); //TODO: check if this ever works
				$guidingUser = false;
				if ($guidingUser === false || isset($_REQUEST['reload'])){
					$guidingUser = new User();
					$guidingUser->get($_SESSION['guidingUserId']);
					if (!$guidingUser){
						self::getLogger()->warn('Invalid Guiding User ID in session variable: ' . $_SESSION['guidingUserId']);
						$masqueradeMode = false;
						unset($_SESSION['guidingUserId']); // session_start(); session_commit(); probably needed for this to take effect, but might have other side effects
					}
				}
			}

			//Check to see if the patron is already logged in within CAS as long as we aren't on a page that is likely to be a login page
		}elseif ($action != 'AJAX' && $action != 'DjatokaResolver' && $action != 'Logout' && $module != 'MyAccount' && $module != 'API' && !isset($_REQUEST['username'])){
			//If the library uses CAS/SSO we may already be logged in even though they never logged in within Pika
			if (strlen($library->casHost) > 0){
				//Check CAS first
				//require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
				$casAuthentication = new CASAuthentication(null);
				self::getLogger()->debug('Checking CAS Authentication from UserAccount::getLoggedInUser');
				$casUsername = $casAuthentication->validateAccount(null, null, null, false);
				if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
					//The user could not be authenticated in CAS
					UserAccount::$isLoggedIn = false;
					return false;
				}else{
					//We have a valid user via CAS, need to do a log into Pika
					$_REQUEST['casLogin']    = true;
					$userData                = UserAccount::login();
					UserAccount::$isLoggedIn = true;
				}
			}
		}
		if (UserAccount::$isLoggedIn){
			UserAccount::$primaryUserData = $userData;
			if ($interface){
				$interface->assign('user', $userData);
			}
		}
		return UserAccount::$primaryUserData;
	}

	/**
	 * Updates the user information in the session
	 *
	 * @param User $user
	 */
	public static function updateSession($user){

		if(!is_object($user)) {
			$st = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
			self::getLogger()->warn('Can\'t update session. User not set.', ['stack_trace' => $st]);
			return false;
		}

		$_SESSION['activeUserId'] = $user->id;

		if (isset($_REQUEST['rememberMe']) && ($_REQUEST['rememberMe'] === 'true' || $_REQUEST['rememberMe'] === 'on')){
			$_SESSION['rememberMe'] = true;
		}else{
			$_SESSION['rememberMe'] = false;
		}

		// If the user browser has the showCovers settings stored, set the Session variable
		// Used for showing or hiding covers on MyAccount Pages
		if (isset($_REQUEST['showCovers'])){
			$showCovers             = ($_REQUEST['showCovers'] == 'on' || $_REQUEST['showCovers'] == 'true');
			$_SESSION['showCovers'] = $showCovers;
		}

		session_commit();
	}

	/**
	 * Try to log in the user using current query parameters
	 * return User object on success, PEAR error on failure.
	 *
	 * @return PEAR_Error|User
	 * @throws UnknownAuthenticationMethodException
	 */
	public static function login(){
		$cache  = new Pika\Cache();
		global $configArray;

		$validUsers = [];

		$validatedViaSSO = false;
		if (isset($_REQUEST['casLogin'])){
			self::getLogger()->info('Logging the user in via CAS');
			//Check CAS first
		//	require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
			$casAuthentication = new CASAuthentication();
			$casUsername       = $casAuthentication->authenticate();
			if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
				//The user could not be authenticated in CAS
				self::getLogger()->info('The user could not be logged in via CAS');
				return new PEAR_Error('Could not authenticate in sign on service');
			}else{
				self::getLogger()->info("User logged in OK CAS Username $casUsername");
				//Set both username and password since authentication methods could use either.
				//Each authentication method will need to deal with the possibility that it gets a barcode for both user and password
				$_REQUEST['username'] = $casUsername;
				$_REQUEST['password'] = $casUsername;
				$validatedViaSSO      = true;
			}
		}

		/** @var User $primaryUser */
		$primaryUser   = null;
		$lastError     = null;
		$driversToTest = self::loadAccountProfiles();

		//Test each driver in turn.  We do test all of them in case an account is valid in
		//more than one system
		foreach ($driversToTest as $driverName => $driverData){
			// Perform authentication:
			try {
				$authN    = AuthenticationFactory::initAuthentication($driverData['authenticationMethod'], $driverData);
				$tempUser = $authN->authenticate($validatedViaSSO);

				// If we authenticated, store the user in the session:
				if (!PEAR_Singleton::isError($tempUser)){
					if ($validatedViaSSO){
						@session_start();
						$_SESSION['loggedInViaCAS'] = true;
					}
					global $library;
					if (isset($library) && $library->preventExpiredCardLogin && $tempUser->expired){
						// Create error
						$cardExpired = new PEAR_Error('expired_library_card');
						return $cardExpired;
					}

					$patronCacheKey = $cache->makePatronKey('patron', $tempUser->id);
					$cache->set($patronCacheKey, $tempUser, $configArray['Caching']['user']);

					if ($primaryUser == null){
						$primaryUser = $tempUser;
						self::updateSession($primaryUser);
					}else{
						//We have more than one account with these credentials, automatically link them
						$primaryUser->addLinkedUser($tempUser);
					}
				}else{
					$username = str_replace("’", "'",$_REQUEST['username']) ?? 'No username provided';
					self::getLogger()->error("Error authenticating patron $username for driver {$driverName}",
						['last_error' => $tempUser->toString()]);
				}
			} catch (UnknownAuthenticationMethodException $e){
				self::getLogger()->error($e->getMessage() . $e->getTraceAsString());
				$tempUser = new PEAR_Error('Unknown Authentication Method');
			}
		}

		// Send back the user object (which may be a PEAR error):
		if ($primaryUser){
			UserAccount::$isLoggedIn      = true;
			UserAccount::$primaryUserData = $primaryUser;
			return $primaryUser;
		}else{
			return $tempUser;
		}
	}

	/**
	 * Validate the account information (username and password are correct).
	 * Returns the account, but does not set the global user variable.
	 *
	 * @param $username       string
	 * @param $password       string
	 * @param $accountSource  string The source of the user account if known or null to test all sources
	 * @param $parentAccount  User   The parent user if any
	 *
	 * @return User|false
	 */
	public static function validateAccount($username, $password, $accountSource = null, $parentAccount = null){
		self::getLogger()->debug("Running UserAccount::validateAccount", $_SESSION);
		global $library;
		global $configArray;
		$cache  = new Pika\Cache();
		$username = str_replace("’", "'",  $username);
		if (array_key_exists($username . $password, UserAccount::$validatedAccounts)){
			return UserAccount::$validatedAccounts[$username . $password];
		}
		// Perform authentication:
		//Test all valid authentication methods and see which (if any) result in a valid login.
		$driversToTest = self::loadAccountProfiles();

		$validatedViaSSO = false;
//		self::getLogger()->debug('cas check',
//			[
//				'cas host'       => $library->casHost,
//				'username'       => $username,
//				'loggedInViaCAS' => $_SESSION['loggedInViaCAS'],
//			]);

		if (strlen($library->casHost) > 0 && !empty($_SESSION['loggedInViaCAS'])){
			//Check CAS first
			//require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
			$casAuthentication = new CASAuthentication(null);
			self::getLogger()->debug('Checking CAS Authentication from UserAccount::validateAccount');
			$casUsername = $casAuthentication->validateAccount(null, null, $parentAccount, false);
			if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
				//The user could not be authenticated in CAS
				self::getLogger()->debug('User could not be authenticated in CAS');
				UserAccount::$validatedAccounts[$username . $password] = false;
				return false;
			}else{
				self::getLogger()->debug('User was authenticated in CAS');
				//Set both username and password since authentication methods could use either.
				//Each authentication method will need to deal with the possibility that it gets a barcode for both user and password
				$username        = $casUsername;
				$password        = $casUsername;
				$validatedViaSSO = true;
			}
		}

		foreach ($driversToTest as $driverName => $additionalInfo){
			if ($accountSource == null || $accountSource == $additionalInfo['accountProfile']->name){
				try {
					$authN         = AuthenticationFactory::initAuthentication($additionalInfo['authenticationMethod'], $additionalInfo);
					$validatedUser = $authN->validateAccount($username, $password, $parentAccount, $validatedViaSSO);
					if ($validatedUser && !PEAR_Singleton::isError($validatedUser)){
						$patronCacheKey = $cache->makePatronKey('patron', $validatedUser->id);
						$cache->set($patronCacheKey, $validatedUser, $configArray['Caching']['user']);
						self::getLogger()->debug("Cached user {$validatedUser->id}", ['key' => $patronCacheKey]);
						if ($validatedViaSSO){
							@session_start();
							$_SESSION['loggedInViaCAS'] = true;
						}
						UserAccount::$validatedAccounts[$username . $password] = $validatedUser;
						return $validatedUser;
					}
				} catch (UnknownAuthenticationMethodException $e){
					self::getLogger()->error($e->getMessage());
				}
			}
		}
		UserAccount::$validatedAccounts[$username . $password] = false;
		return false;
	}

	/**
	 * Completely logout the user annihilating their entire session.
	 */
	public static function logout(){
		UserAccount::softLogout();
		session_regenerate_id(true);
	}

	/**
	 * Remove user info from the session so the user is not logged in, but
	 * preserve hold message and search information
	 */
	public static function softLogout(){
		// TODO: this wont work in php 8
		@session_start();
		// This is needed so that the logout() above doesn't generate the error :
		// "session_regenerate_id(): Cannot regenerate session id - session is not active"
		// Especially needed for ending Masquerade Mode
		if (isset($_SESSION['activeUserId'])){
			if (isset($_SESSION['guidingUserId'])){
				// Shouldn't end up here while in Masquerade Mode, but if does happen end masquerading as well
				unset($_SESSION['guidingUserId']);
			}
			if (isset($_SESSION['loggedInViaCAS']) && $_SESSION['loggedInViaCAS']){
				//require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
				$casAuthentication = new CASAuthentication(null);
				$casAuthentication->logout();
			}
			unset($_SESSION['activeUserId']);
			if (isset($_SESSION['lastCASCheck'])){
				unset($_SESSION['lastCASCheck']);
			}
			// unset all session variables - Session variables are being displayed after logouts so unset all
			session_unset();

			UserAccount::$isLoggedIn              = false;
			UserAccount::$primaryUserData         = null;
			UserAccount::$primaryUserObjectFromDB = null;
			UserAccount::$guidingUserObjectFromDB = null;
		}
	}

	/**
	 * @return array
	 */
	protected static function loadAccountProfiles(){
		$cache = new Cache();
		global $instanceName;
		global $configArray;
		$accountProfiles = $cache->get('account_profiles_' . $instanceName);

		if ($accountProfiles == false || isset($_REQUEST['reload'])){
			$accountProfiles = [];

			//Load a list of authentication methods to test and see which (if any) result in a valid login.
			require_once ROOT_DIR . '/sys/Account/AccountProfile.php';
			$accountProfile = new AccountProfile();
			$accountProfile->orderBy('weight, name');
			$accountProfile->find();
			while ($accountProfile->fetch()){
				$additionalInfo                         = [
					'driver'               => $accountProfile->driver,
					'authenticationMethod' => $accountProfile->authenticationMethod,
					'accountProfile'       => clone($accountProfile)
				];
				$accountProfiles[$accountProfile->name] = $additionalInfo;
			}
			if (count($accountProfiles) == 0){
				$msg = 'No Account Profiles set. A default account (usually ils) must be in db.';
				self::getLogger()->critical($msg);
				die($msg);
			}

			$cache->set('account_profiles_' . $instanceName, $accountProfiles, $configArray['Caching']['account_profiles']);
			global $timer;
			$timer->logTime("Loaded Account Profiles");
		}
		return $accountProfiles;
	}


	/**
	 * Look up in ILS for a user that has never logged into Pika before, based on the patron's barcode.
	 *
	 * @param $patronBarcode
	 * @return bool|User
	 */
	public static function findNewUser($patronBarcode){
		$driversToTest = self::loadAccountProfiles();
		foreach ($driversToTest as $driverName => $driverData){
			$catalogConnectionInstance = CatalogFactory::getCatalogConnectionInstance($driverData['driver'], $driverData['accountProfile']);
			if (method_exists($catalogConnectionInstance->driver, 'findNewUser')){
				$tmpUser = $catalogConnectionInstance->driver->findNewUser($patronBarcode);
				if (!empty($tmpUser) && !PEAR_Singleton::isError($tmpUser)){
					return $tmpUser;
				}
			}
		}
		return false;
	}
}
