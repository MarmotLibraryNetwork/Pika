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
	private static $validatedAccounts = array();

	private $cache;
	private $logger;

	public function __construct()
	{
		$this->cache  = new Cache();
		$this->logger = new Logger('UserAccount');
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
		$logger = new Logger('UserAccount');
		global $library;
		global $action;
		global $module;
		if (UserAccount::$isLoggedIn == null){
			if (isset($_SESSION['activeUserId'])){
				UserAccount::$isLoggedIn = true;
			}else{
				UserAccount::$isLoggedIn = false;
				//Need to check cas just in case the user logged in from another site
				//if ($action != 'AJAX' && $action != 'DjatokaResolver' && $action != 'Logout' && $module != 'MyAccount' && $module != 'API' && !isset($_REQUEST['username'])){
				//If the library uses CAS/SSO we may already be logged in even though they never logged in within Pika

				if (strlen($library->casHost) > 0){
					$checkCAS = false;
					$curTime  = time();
					if (!isset($_SESSION['lastCASCheck'])){
						$checkCAS = true;
					}elseif ($curTime - $_SESSION['lastCASCheck'] > 10){
						$checkCAS = true;
					}

					if ($checkCAS && $action != 'AJAX' && $action != 'DjatokaResolver' && $action != 'Logout' && $module != 'MyAccount' && $module != 'API' && !isset($_REQUEST['username'])){
						//Check CAS first
						require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';

						$casAuthentication        = new CASAuthentication(null);
						$casUsername              = $casAuthentication->validateAccount(null, null, null, false);
						$_SESSION['lastCASCheck'] = time();
						$logger->debug("Checked CAS Authentication from UserAccount::isLoggedIn result was $casUsername");
						if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
							//The user could not be authenticated in CAS
							UserAccount::$isLoggedIn = false;

						}else{
							$logger->debug("We got a valid user from CAS, getting the user from the database");
							//We have a valid user via CAS, need to do a login to Pika
							$_REQUEST['casLogin']    = true;
							UserAccount::$isLoggedIn = true;
							//Set the active user id for the user
							$user = new User();
							//TODO this may need to change if anyone but Fort Lewis ever does CAS authentication
							$user->cat_password = $casUsername;
							if ($user->find(true)){
								$_SESSION['activeUserId']             = $user->id;
								UserAccount::$primaryUserObjectFromDB = $user;
							}
						}
					}
				}
			}
		}
		return UserAccount::$isLoggedIn;
	}

	public static function getActiveUserId(){
		if (isset($_SESSION['activeUserId'])){
			return $_SESSION['activeUserId'];
		}else{
			return false;
		}
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
			UserAccount::$userRoles = array();
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
					$testRole = isset($_REQUEST['test_role']) ? $_REQUEST['test_role'] : (isset($_COOKIE['test_role']) ? $_COOKIE['test_role'] : '');
					if ($testRole != ''){
						//Ignore the standard roles for the user
						UserAccount::$userRoles = array();

						$testRoles = is_array($testRole) ? $testRole : array($testRole);
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
					UserAccount::$primaryUserObjectFromDB = $user;
					return;
				}
			}
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

	public static function isUserMasquerading(){
		return !empty($_SESSION['guidingUserId']);
	}

	public static function getGuidingUserObject(){
		if (UserAccount::$guidingUserObjectFromDB == null){
			if (UserAccount::isUserMasquerading()){
				$activeUserId = $_SESSION['guidingUserId'];
				if ($activeUserId){
					$user     = new User();
					$user->id = $activeUserId;
					if ($user->find(true)){
						UserAccount::$guidingUserObjectFromDB = $user;
					}else{
						UserAccount::$guidingUserObjectFromDB = false;
					}
				}else{
					UserAccount::$guidingUserObjectFromDB = false;
				}
			}else{
				UserAccount::$guidingUserObjectFromDB = false;
			}
		}
		return UserAccount::$guidingUserObjectFromDB;
	}

	/**
	 * @return bool|null|User
	 */

	public static function getLoggedInUser() {
		global $action;
		global $module;
		global $library;
		global $interface;
		$logger = new Pika\Logger('UserAccount');
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
		if (isset($_SESSION['activeUserId'])){
			$activeUserId = $_SESSION['activeUserId'];
			$patronCacheKey = $cache->makePatronKey('patron', $activeUserId);
			$userData = $cache->get($patronCacheKey, false);
			if ($userData === false || isset($_REQUEST['reload'])) {
				//Load the user from the database
				$userData = new User();
				$userData->id = $activeUserId;
				$userData->find(true);
				if ($userData->N != 0 && $userData->N != false){
					//$logger->debug("Loading user {$userData->cat_username}, {$userData->cat_password} because we didn't have data in memcache");
					$userData = UserAccount::validateAccount($userData->cat_username, $userData->cat_password, $userData->source);
					self::updateSession($userData);
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
						$logger->warn('Invalid Guiding User ID in session variable: ' . $_SESSION['guidingUserId']);
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
				require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
				$casAuthentication = new CASAuthentication(null);
				$logger->debug("Checking CAS Authentication from UserAccount::getLoggedInUser");
				$casUsername = $casAuthentication->validateAccount(null, null, null, false);
				if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
					//The user could not be authenticated in CAS
					UserAccount::$isLoggedIn = false;
					return false;
				}else{
					//We have a valid user via CAS, need to do a login to Pika
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
	 * Updates the user information in the session and in memcache
	 *
	 * @param User $user
	 */
	public static function updateSession($user){
		$_SESSION['activeUserId'] = $user->id;

		if (isset($_REQUEST['rememberMe']) && ($_REQUEST['rememberMe'] === "true" || $_REQUEST['rememberMe'] === "on")){
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
		$logger = new Pika\Logger('UserAccount');
		$cache  = new Pika\Cache();
		global $configArray;

		$validUsers = array();

		$validatedViaSSO = false;
		if (isset($_REQUEST['casLogin'])){
			$logger->info("Logging the user in via CAS");
			//Check CAS first
			require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
			$casAuthentication = new CASAuthentication(null);
			$casUsername       = $casAuthentication->authenticate(false);
			if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
				//The user could not be authenticated in CAS
				$logger->info("The user could not be logged in");
				return new PEAR_Error('Could not authenticate in sign on service');
			}else{
				$logger->info("User logged in OK CAS Username $casUsername");
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
			$authN    = AuthenticationFactory::initAuthentication($driverData['authenticationMethod'], $driverData);
			$tempUser = $authN->authenticate($validatedViaSSO);

			// If we authenticated, store the user in the session:
			if (!PEAR_Singleton::isError($tempUser)){
				if ($validatedViaSSO){
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

				$validUsers[] = $tempUser;
				if ($primaryUser == null){
					$primaryUser = $tempUser;
					self::updateSession($primaryUser);
				}else{
					//We have more than one account with these credentials, automatically link them
					$primaryUser->addLinkedUser($tempUser);
				}
			}else{
				$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : 'No username provided';
				$logger->error("Error authenticating patron $username for driver {$driverName}",
				 ['last_error' => $tempUser->toString()]);
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
		global $library;
		global $configArray;
		$logger = new Pika\Logger('UserAccount');
		$cache  = new Pika\Cache();

		if (array_key_exists($username . $password, UserAccount::$validatedAccounts)){
			return UserAccount::$validatedAccounts[$username . $password];
		}
		// Perform authentication:
		//Test all valid authentication methods and see which (if any) result in a valid login.
		$driversToTest = self::loadAccountProfiles();

		$validatedViaSSO = false;
		if (strlen($library->casHost) > 0 && $username == null && $password == null){
			//Check CAS first
			require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
			$casAuthentication = new CASAuthentication(null);
			$logger->debug("Checking CAS Authentication from UserAccount::validateAccount");
			$casUsername = $casAuthentication->validateAccount(null, null, $parentAccount, false);
			if ($casUsername == false || PEAR_Singleton::isError($casUsername)){
				//The user could not be authenticated in CAS
				$logger->debug("User could not be authenticated in CAS");
				UserAccount::$validatedAccounts[$username . $password] = false;
				return false;
			}else{
				$logger->debug("User was authenticated in CAS");
				//Set both username and password since authentication methods could use either.
				//Each authentication method will need to deal with the possibility that it gets a barcode for both user and password
				$username        = $casUsername;
				$password        = $casUsername;
				$validatedViaSSO = true;
			}
		}

		foreach ($driversToTest as $driverName => $additionalInfo){
			if ($accountSource == null || $accountSource == $additionalInfo['accountProfile']->name){
				$authN         = AuthenticationFactory::initAuthentication($additionalInfo['authenticationMethod'], $additionalInfo);
				$validatedUser = $authN->validateAccount($username, $password, $parentAccount, $validatedViaSSO);
				if ($validatedUser && !PEAR_Singleton::isError($validatedUser)){
					$patronCacheKey = $cache->makePatronKey('patron', $validatedUser->id);
					$cache->set($patronCacheKey, $validatedUser, $configArray['Caching']['user']);
					$logger->debug("Cached user {$validatedUser->id}");
					if ($validatedViaSSO){
						$_SESSION['loggedInViaCAS'] = true;
					}
					UserAccount::$validatedAccounts[$username . $password] = $validatedUser;
					return $validatedUser;
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
		if (isset($_SESSION['activeUserId'])){
			if (isset($_SESSION['guidingUserId'])){
				// Shouldn't end up here while in Masquerade Mode, but if does happen end masquerading as well
				unset($_SESSION['guidingUserId']);
			}
			if (isset($_SESSION['loggedInViaCAS']) && $_SESSION['loggedInViaCAS']){
				require_once ROOT_DIR . '/sys/Authentication/CASAuthentication.php';
				$casAuthentication = new CASAuthentication(null);
				$casAuthentication->logout();
			}
			unset($_SESSION['activeUserId']);
			if (isset($_SESSION['lastCASCheck'])){
				unset($_SESSION['lastCASCheck']);
			}
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
			$accountProfiles = array();

			//Load a list of authentication methods to test and see which (if any) result in a valid login.
			require_once ROOT_DIR . '/sys/Account/AccountProfile.php';
			$accountProfile = new AccountProfile();
			$accountProfile->orderBy('weight, name');
			$accountProfile->find();
			while ($accountProfile->fetch()){
				$additionalInfo                         = array(
					'driver'               => $accountProfile->driver,
					'authenticationMethod' => $accountProfile->authenticationMethod,
					'accountProfile'       => clone($accountProfile)
				);
				$accountProfiles[$accountProfile->name] = $additionalInfo;
			}
			if (count($accountProfiles) == 0){
				//Create default information for historic login.  This will eventually be obsolete
				$accountProfile                       = new AccountProfile();
				$accountProfile->recordSource         = 'ils';
				$accountProfile->name                 = 'ils';
				$accountProfile->authenticationMethod = 'ils';
				$accountProfile->driver               = $configArray['Catalog']['driver'];
				$accountProfile->loginConfiguration   = ($configArray['Catalog']['barcodeProperty'] == 'cat_password') ? 'name_barcode' : 'barcode_pin';
				if (isset($configArray['Catalog']['url'])){
					$accountProfile->vendorOpacUrl = $configArray['Catalog']['url'];
				}
				if (isset($configArray['OPAC']['patron_host'])){
					$accountProfile->patronApiUrl = $configArray['OPAC']['patron_host'];
				}

				$additionalInfo                         = array(
					'driver'               => $accountProfile->driver,
					'authenticationMethod' => 'ILS',
					'accountProfile'       => $accountProfile
				);
				$accountProfiles[$accountProfile->name] = $additionalInfo;
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
