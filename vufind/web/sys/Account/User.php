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
/**
 * User Object is the central driver to run user actions through
 */
require_once 'DB/DataObject.php';
use Pika\Cache;
use Pika\Logger;

class User extends DB_DataObject {

	public $__table = 'user';                // table name
	public $id;                              // int(11)  not_null primary_key auto_increment
	public $source;
//	public $username;                        // string(30)  not_null unique_key
	public $displayName;                     // string(30)
	public $firstname;                       // string(50)  not_null
	public $lastname;                        // string(50)  not_null
	public $email;                           // string(250)  not_null
	public $phone;                           // string(30)
	public $alt_username;                    // An alternate username used by patrons to login.
	public $cat_username;                    // string(50)
	// cat_password are protected for logging purposes
	/**
	 * @deprecated No longer used. Use getPassword()
	 */
	protected $cat_password;
	public $barcode;                        // string(50) Replaces $cat_username for sites using barcode/pin auth
	protected $password;                       // string(128) password - Replaces $cat_password
	public $patronType;
	public $created;                         // datetime(19)  not_null binary
	public $homeLocationId;                  // int(11)
	public $myLocation1Id;                   // int(11)
	public $myLocation2Id;                   // int(11)
	public $trackReadingHistory;             // tinyint
	public $initialReadingHistoryLoaded;
	public $bypassAutoLogout;                //tinyint
	public $disableRecommendations;          //tinyint
	public $disableCoverArt;                 //tinyint
	public $overDriveEmail;
	public $promptForOverDriveEmail;
	public $promptForOverDriveLendingPeriods;
	public $hooplaCheckOutConfirmation;
	public $preferredLibraryInterface;
	public $noPromptForUserReviews; //tinyint(1)

	private $roles;
	private $masqueradingRoles;
	private $masqueradeLevel;

	/** @var User $parentUser */
	private $parentUser;
	/** @var User[] $linkedUsers */
	private $linkedUsers;
	private $viewers;

	//Data that we load, but don't store in the User table
	public $fullname; //TODO: remove, I think this only get set by the catalog drivers, and is never used anywhere else
	public $address1;
	public $address2; //TODO: obsolete; only used in hold success pop-up and is populated by $city, $state
	public $city;
	public $state;
	public $zip;
	public $workPhone;
	public $mobileNumber; //TODO: obsolete
	public $web_note;
	public $expires;
	public $expired;
	public $expireClose;
	public $fines;
	public $finesVal;
	private $ilsFinesForUser;
	public $homeLibraryId;
	public $homeLibrary;
	public $homeLibraryName; //Only populated as part of loading administrators
	public $homeLocationCode;
	public $homeLocation;
	public $myLocation1;
	public $myLocation2;
	public $numCheckedOutIls;
	public $numHoldsIls;
	public $numHoldsAvailableIls;
	public $numHoldsRequestedIls;
	private $numCheckedOutOverDrive = 0;
	private $numHoldsOverDrive = 0;
	private $numHoldsAvailableOverDrive = 0;
	private $numHoldsRequestedOverDrive = 0;
	private $numCheckedOutHoopla = 0;
	public $numBookings;
	public $notices;
	// $noticePreferenceLabel
	// This is strict and used for comparison in several places. values are:
	// Mail, Telephone, E-mail
	public $noticePreferenceLabel;
	private $numMaterialsRequests = 0;
	private $readingHistorySize = 0;
	private $accountProfile;
	private $catalogDriver;
	private $materialsRequestReplyToAddress;
	private $materialsRequestEmailSignature;
	public $ilsUserId;
	private $linkedUserObjects;
// Account Blocks //
	private $blockAll = null;        // set to null to signal unset, boolean when set
	private $blockedAccounts = null; // set to null to signal unset, array when set

	private $data = [];

	// CarlX Option
	public $emailReceiptFlag;
	public $availableHoldNotice;
	public $comingDueNotice;
	public $phoneType;

	private $logger;

	public function __construct(){
		$this->logger = new Logger(__CLASS__);
	}

	function getTags(){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';
		$tagList = [];

		$escapedId = $this->escape($this->id, false);
		$sql       = "SELECT id, groupedWorkPermanentId, tag, COUNT(groupedWorkPermanentId) AS cnt " .
			"FROM user_tags WHERE " .
			"userId = '{$escapedId}' ";
		$sql       .= "GROUP BY tag ORDER BY tag ASC";
		$tag       = new UserTag();
		$tag->query($sql);
		if ($tag->N){
			while ($tag->fetch()){
				$tagList[] = clone($tag);
			}
		}
		return $tagList;
	}

//	function getLists(){
//		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
//
//		$lists = [];
//
//		$escapedId = $this->escape($this->id, false);
//		$sql       = "SELECT user_list.* FROM user_list " .
//			"WHERE user_list.user_id = '$escapedId' " .
//			"ORDER BY user_list.title";
//		$list      = new UserList();
//		$list->query($sql);
//		if ($list->N){
//			while ($list->fetch()){
//				$lists[] = clone($list);
//			}
//		}
//		return $lists;
//	}

	/**
	 * Get a connection to the catalog for the user
	 *
	 * @return CatalogConnection
	 */
	function getCatalogDriver(){
		if ($this->catalogDriver == null){
			//Based off the source of the user, get the AccountProfile
			$accountProfile = $this->getAccountProfile();
			if ($accountProfile){
				$catalogDriver       = $accountProfile->driver;
				$this->catalogDriver = CatalogFactory::getCatalogConnectionInstance($catalogDriver, $accountProfile);
			}
		}
		return $this->catalogDriver;
	}

	/**
	 * @return AccountProfile
	 */
	function getAccountProfile(){
		if ($this->accountProfile != null){
			return $this->accountProfile;
		}
		require_once ROOT_DIR . '/sys/Account/AccountProfile.php';
		$accountProfile       = new AccountProfile();
		$accountProfile->name = $this->source;
		if ($accountProfile->find(true)){
			$this->accountProfile = $accountProfile;
		}else{
			$this->accountProfile = null;
		}
		return $this->accountProfile;
	}

	/**
	 * Get unencrypted password
	 *
	 * @return string
	 */
	public function getPassword() {
		$password = $this->_decryptPassword($this->password);
		return $password;
	}


	/**
	 * setPassword
	 * Use when setting a password on a newly instantiated object or when the object will call update() later in the code.
	 * This will not update the password in the database.
	 * @param $password
	 * @return void
	 */
	public function setPassword($password) {
		$encryptedPassword = $this->_encryptPassword($password);
		$this->password = $encryptedPassword;
	}

	/**
	 * updatePassword
	 * Update an existing password in the database. Use this method when updating a password or setting a new
	 * password for the user.
	 * @param $password
	 * @return boolean True on success or false on failure.
	 */
	public function updatePassword($password) {
		$encryptedPassword = $this->_encryptPassword($password);
		$sql = "UPDATE user SET password = '" . $encryptedPassword . "' WHERE id = " . $this->id . " LIMIT 1";

		$result = $this->query($sql);
		if(PEAR_Singleton::isError($result)) {
			$this->logger->warn("Error updating password.", ["message"=>$result->getMessage(), "info"=>$result->userinfo]);
		}elseif($result >= 1) {
			return true;
		}
		return false;
	}

	/**
	 * encrypt password
	 * @param string  $password
	 * @return string Encrypted password
	 */
	private function _encryptPassword($password) {
		global $configArray;
		$key = base64_decode($configArray["Site"]["passwordEncryptionKey"]);
		$v   = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
		$e   = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $v);
		$p   = base64_encode($e . '::' . $v);
		return $p;
	}

	/**
	 * Decrypt password
	 *
	 * @return string Decrypted password
	 */
	private function _decryptPassword() {
		if (empty($this->password)){
			$password = '';
		}else{
			global $configArray;
			$key    = base64_decode($configArray['Site']['passwordEncryptionKey']);
			$string = base64_decode($this->password);
			[$encryptedPW, $v] = explode('::', $string, 2);
			$password = openssl_decrypt($encryptedPW, 'aes-256-cbc', $key, 0, $v);
		}
		return $password;
	}

	function __get($name){
		if ($name == 'roles'){
			return $this->getRoles();
		}elseif ($name == 'linkedUsers'){
			return $this->getLinkedUsers();
		}elseif ($name == 'materialsRequestReplyToAddress'){
			if (!isset($this->materialsRequestReplyToAddress)){
				$this->getStaffSettings();
			}
			return $this->materialsRequestReplyToAddress;
		}elseif ($name == 'materialsRequestEmailSignature'){
			if (!isset($this->materialsRequestEmailSignature)){
				$this->getStaffSettings();
			}
			return $this->materialsRequestEmailSignature;
		}

		// accessing the password attribute directly will return the encrupted password.
		if($name == "password") {
			//$calledBy = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
			//$this->logger->debug("Please use getPassword() when getting password from user object.", array("trace" => $calledBy));
			return $this->password;
		}

		// handle deprecated cat_password and cat_username
		if($name == 'cat_password') {
			if ($accountProfile = $this->getAccountProfile()){
				if ($accountProfile->loginConfiguration == 'barcode_pin' && $name == 'cat_username'){
					return $this->barcode;
				}elseif ($accountProfile->loginConfiguration == 'name_barcode' && $name == 'cat_password'){
					$calledBy = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
					$this->logger->debug($name . '" accessed by " '. $calledBy['function'], ['trace' => $calledBy]);
					return $this->barcode;
				} else {
					return $this->{$name};
				}
			} else {
				return $this->{$name};
			}
		}else{
			return $this->data[$name];
		}
	}

	function __set($name, $value){
		// for passwords, allows new object to set password for methods like $user->find
		if($name == "password") {
			$calledBy = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
			$this->logger->debug($name . " being set by " . $calledBy['function'], array("trace" => $calledBy));
			$this->setPassword($value);
			return;
		}

		// Handle deprecated cat_* properties
		// If needed update barcode or password field
		if($name == 'cat_password' || $name == 'cat_username') {
			$calledBy = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
			$this->logger->debug($name . " being set by " . $calledBy['function'], array("trace" => $calledBy));
			if ($accountProfile = $this->getAccountProfile()){
				if ($accountProfile->loginConfiguration == 'barcode_pin' && $name == 'cat_username'){
					$this->barcode = $value;
				}elseif ($accountProfile->loginConfiguration == 'name_barcode' && $name == 'cat_password'){
					$this->barcode = $value;
				} else {
					$this->{$name} = $value;
				}
			} else {
				$this->{$name} = $value;
			}
			return $this->update();
		}

		if ($name == 'roles'){
			$this->roles = $value;
			//Update the database, first remove existing values
			$this->saveRoles();
		}else{
			$this->data[$name] = $value;
		}
	}

	function getRoles($isGuidingUser = false){
		if (is_null($this->roles)){
			$this->roles = [];
			if ($this->id){
				//Load roles for the user from the user
				require_once ROOT_DIR . '/sys/Administration/Role.php';
				$role = new Role();
				$role->selectAs();
				$role->joinAdd(['roleId', 'user_roles:roleId']);
				$role->whereAdd('userId = ' . $this->id);
				$role->orderBy('name');
				$this->roles     = $role->fetchAll('roleId', 'name');
				$canUseTestRoles = in_array('userAdmin', $this->roles);

				if ($canUseTestRoles){
					$testRole = isset($_REQUEST['test_role']) ? $_REQUEST['test_role'] : (isset($_COOKIE['test_role']) ? $_COOKIE['test_role'] : false);
					if ($testRole){
						$testRoles = is_array($testRole) ? $testRole : [$testRole];
						foreach ($testRoles as $tmpRole){
							$role = new Role();
							if (is_numeric($tmpRole)){
								$role->roleId = $tmpRole;
							}else{
								$role->name = $tmpRole;
							}
							if ($role->find(true)){
								$this->roles[$role->roleId] = $role->name;
							}
						}
					}
				}
			}

		}

		//Setup masquerading as different users
		$masqueradeMode = UserAccount::isUserMasquerading();
		if ($masqueradeMode && !$isGuidingUser){
			if (is_null($this->masqueradingRoles)){
				global /** @var User $guidingUser */
				$guidingUser;
				$guidingUserRoles = $guidingUser->getRoles(true);
				if (in_array('opacAdmin', $guidingUserRoles)){
					$this->masqueradingRoles = $this->roles;
				}else{
					$this->masqueradingRoles = array_intersect($this->roles, $guidingUserRoles);
				}
			}
			return $this->masqueradingRoles;
		}
		return $this->roles;
	}

	function getStaffSettings(){
		require_once ROOT_DIR . '/sys/Account/UserStaffSettings.php';
		$staffSettings = new UserStaffSettings();
		$staffSettings->get('userId', $this->id);
		$this->materialsRequestReplyToAddress = $staffSettings->materialsRequestReplyToAddress;
		$this->materialsRequestEmailSignature = $staffSettings->materialsRequestEmailSignature;
	}

	function setStaffSettings(){
		require_once ROOT_DIR . '/sys/Account/UserStaffSettings.php';
		$staffSettings                                 = new UserStaffSettings();
		$staffSettings->userId                         = $this->id;
		$doUpdate                                      = $staffSettings->find(true);
		$staffSettings->materialsRequestReplyToAddress = $this->materialsRequestReplyToAddress;
		$staffSettings->materialsRequestEmailSignature = $this->materialsRequestEmailSignature;
		if ($doUpdate){
			$staffSettings->update();
		}else{
			$staffSettings->insert();
		}
	}

	/**
	 * Patron barcode.
	 * @return string|void
	 */
	public function getBarcode(){
		return $this->barcode;
	}


	function saveRoles(){
		if (isset($this->id) && isset($this->roles) && is_array($this->roles)){
			require_once ROOT_DIR . '/sys/Administration/UserRoles.php';
			$role      = new UserRoles();
			$escapedId = $this->escape($this->id, false);
			$role->query("DELETE FROM user_roles WHERE userId = " . $escapedId);
			//Now add the new values.
			if (count($this->roles) > 0){
				$values = [];
				foreach ($this->roles as $roleId => $roleName){
					$values[] = "({$this->id},{$roleId})";
				}
				$values = join(', ', $values);
				$role->query("INSERT INTO user_roles ( `userId` , `roleId` ) VALUES $values");
			}
		}
	}

	/**
	 * Fetches additional User objects that have been linked to this User object.
	 *
	 * @return User[]
	 */
	function getLinkedUsers(){
		if (is_null($this->linkedUsers)){
			$this->linkedUsers = [];
			/* var Library $library */
			global $library;
			/** @var Memcache $memCache */
			global $memCache;
			global $serverName;

			if ($this->id && $library->allowLinkedAccounts){
				require_once ROOT_DIR . '/sys/Account/UserLink.php';
				$userLink                   = new UserLink();
				$userLink->primaryAccountId = $this->id;
				if ($userLink->find()){
					while ($userLink->fetch()){
						if (!$this->isBlockedAccount($userLink->linkedAccountId)){
							$linkedUser     = new User();
							$linkedUser->id = $userLink->linkedAccountId;
							if ($linkedUser->find(true)){
								$cacheKey = $_SERVER['SERVER_NAME'] . "-patron-" . $linkedUser->id; /* todo: update to new caching */
								$userData = $memCache->get($cacheKey);
								if (empty($userData) || isset($_REQUEST['reload'])){
									//Load full information from the catalog
									$linkedAccountProfile = $linkedUser->getAccountProfile();
									if($linkedAccountProfile->loginConfiguration == "barcode_pin") {
										$userName = $linkedUser->barcode;
										$password = $linkedUser->getPassword();
									} else {
										$userName = $linkedUser->cat_username;
										$password = $linkedUser->barcode;
									}
									$linkedUser = UserAccount::validateAccount($userName, $password, $linkedUser->source, $this);
								}else{
									$linkedUser = $userData;
								}
								if ($linkedUser && !PEAR_Singleton::isError($linkedUser)){
									$this->linkedUsers[] = clone($linkedUser);
								}
							}
						}
					}
				}
			}
		}
		return $this->linkedUsers;
	}

	function getLinkedUserObjects(){
		if (is_null($this->linkedUserObjects)){
			$this->linkedUserObjects = [];
			/* var Library $library */
			global $library;
			if ($this->id && $library->allowLinkedAccounts){
				require_once ROOT_DIR . '/sys/Account/UserLink.php';
				$userLink                   = new UserLink();
				$userLink->primaryAccountId = $this->id;
				$userLink->find();
				while ($userLink->fetch()){
					if (!$this->isBlockedAccount($userLink->linkedAccountId)){
						$linkedUser     = new User();
						$linkedUser->id = $userLink->linkedAccountId;
						if ($linkedUser->find(true)){
							/** @var User $userData */
							$this->linkedUserObjects[] = clone($linkedUser);
						}
					}
				}
			}
		}
		return $this->linkedUserObjects;
	}

	public function setParentUser($user){
		$this->parentUser = $user;
	}

	/**
	 * Checks if there is any settings disallowing the account $accountIdToCheck to be linked to this user.
	 *
	 * @param  $accountIdToCheck string   linked account Id to check for blocking
	 * @return bool                       true for blocking, false for no blocking
	 */
	public function isBlockedAccount($accountIdToCheck){
		if (is_null($this->blockAll)){
			$this->setAccountBlocks();
		}
		return $this->blockAll || in_array($accountIdToCheck, $this->blockedAccounts);
	}

	private function setAccountBlocks(){
		// default settings
		$this->blockAll        = false;
		$this->blockedAccounts = [];

		require_once ROOT_DIR . '/sys/Administration/BlockPatronAccountLink.php';
		$accountBlock                   = new BlockPatronAccountLink();
		$accountBlock->primaryAccountId = $this->id;
		if ($accountBlock->find()){
			while ($accountBlock->fetch(false)){
				if ($accountBlock->blockLinking){
					$this->blockAll = true;
				} // any one row that has block all on will set this setting to true for this account.
				if ($accountBlock->blockedLinkAccountId){
					$this->blockedAccounts[] = $accountBlock->blockedLinkAccountId;
				}
			}
		}
	}

	/**
	 *  Get all linked users that are valid OverDrive Users as well
	 *
	 * @return User[]
	 */
	function getRelatedOverDriveUsers(){
		$overDriveUsers = [];
		if ($this->isValidForOverDrive()){
			$overDriveUsers[$this->cat_username] = $this;
		}
		foreach ($this->getLinkedUsers() as $linkedUser){
			if ($linkedUser->isValidForOverDrive()){
				if (!array_key_exists($linkedUser->cat_username, $overDriveUsers)){
					$overDriveUsers[$linkedUser->cat_username] = $linkedUser;
				}
			}
		}

		return $overDriveUsers;
	}

	function isValidForOverDrive(){
		if ($this->parentUser == null || ($this->getBarcode() != $this->parentUser->getBarcode())){
			$userHomeLibrary = $this->getHomeLibrary();
			if ($userHomeLibrary && $userHomeLibrary->enableOverdriveCollection){
				return true;
			}
		}
		return false;
	}

	function isValidForHoopla(){
		if ($this->parentUser == null || ($this->getBarcode() != $this->parentUser->getBarcode())){
			$userHomeLibrary = $this->getHomeLibrary();
			if ($userHomeLibrary && $userHomeLibrary->hooplaLibraryID > 0){
				return true;
			}
		}
		return false;
	}

	function getRelatedHooplaUsers(){
		$hooplaUsers = [];
		if ($this->isValidForHoopla()){
			$hooplaUsers[$this->cat_username] = $this;
		}
		foreach ($this->getLinkedUsers() as $linkedUser){
			if ($linkedUser->isValidForHoopla()){
				if (!array_key_exists($linkedUser->cat_username, $hooplaUsers)){
					$hooplaUsers[$linkedUser->cat_username] = $linkedUser;
				}
			}
		}
		return $hooplaUsers;
	}

	/**
	 * Returns a list of users that can view this account through Pika's Linked Accounts
	 *
	 * @return User[]
	 */
	function getViewers(){
		if (is_null($this->viewers)){
			$this->viewers = [];
			/* var Library $library */
			global $library;
			if ($this->id && $library->allowLinkedAccounts){
				require_once ROOT_DIR . '/sys/Account/UserLink.php';
				$userLink                  = new UserLink();
				$userLink->linkedAccountId = $this->id;
				$userLink->find();
				while ($userLink->fetch()){
					$linkedUser     = new User();
					$linkedUser->id = $userLink->primaryAccountId;
					if ($linkedUser->find(true)){
						if (!$linkedUser->isBlockedAccount($this->id)){
							$this->viewers[] = clone($linkedUser);
						}
					}
				}
			}
		}
		return $this->viewers;
	}

	/**
	 * @param User $user
	 *
	 * @return boolean
	 */
	function addLinkedUser($user){
		/* var Library $library */
		global $library;
		if ($library->allowLinkedAccounts && $user->id != $this->id){ // library allows linked accounts and the account to link is not itself
			$linkedUsers = $this->getLinkedUsers();
			/** @var User $existingUser */
			foreach ($linkedUsers as $existingUser){
				if ($existingUser->id == $user->id){
					//We already have a link to this user
					return true;
				}
			}

			// Check for Account Blocks
			if ($this->isBlockedAccount($user->id)){
				return false;
			}

			//Check to make sure the account we are linking to allows linking
			$linkLibrary = $user->getHomeLibrary();
			if (!$linkLibrary->allowLinkedAccounts){
				return false;
			}

			// Add Account Link
			require_once ROOT_DIR . '/sys/Account/UserLink.php';
			$userLink                   = new UserLink();
			$userLink->primaryAccountId = $this->id;
			$userLink->linkedAccountId  = $user->id;
			$result                     = $userLink->insert();
			if (true == $result){
				$this->linkedUsers[] = clone($user);
				return true;
			}
		}
		return false;
	}

	function removeLinkedUser($userId){
		/* var Library $library */
		global $library;
		if ($library->allowLinkedAccounts){
			require_once ROOT_DIR . '/sys/Account/UserLink.php';
			$userLink                   = new UserLink();
			$userLink->primaryAccountId = $this->id;
			$userLink->linkedAccountId  = $userId;
			$ret                        = $userLink->delete();

			//Force a reload of data
			$this->linkedUsers = null;
			$this->getLinkedUsers();

			return $ret == 1;
		}
		return false;
	}


	function update($dataObject = false){
		$phone = $this->phone;
		if(count_chars($phone) > 30) {
			$phoneParts = str_split($phone, 30);
			$this->phone = $phoneParts[0];
		}
		$result = parent::update();
		$this->clearCache(); // Every update to object requires clearing the Memcached version of the object
		return $result;
	}

	function insert(){
		//set default values as needed
		if (!isset($this->homeLocationId)){
			$this->homeLocationId = 0;

			$this->logger->warning('No Home Location ID was set for newly created user.');
		}
		if (!isset($this->myLocation1Id)){
			$this->myLocation1Id = 0;
		}
		if (!isset($this->myLocation2Id)){
			$this->myLocation2Id = 0;
		}
		if (!isset($this->bypassAutoLogout)){
			$this->bypassAutoLogout = 0;
		}
		if(count_chars($this->phone) > 30) {
			$phoneParts = str_split($this->phone, 30);
			$this->phone = $phoneParts[0];
		}

		$r = parent::insert();
//		$this->saveRoles(); // this should happen in the __set() method
		$this->clearCache();
		return $r;
	}

	function hasRole($roleName){
		$myRoles = $this->__get('roles');
		return in_array($roleName, $myRoles);
	}

	static function getObjectStructure(){
		require_once ROOT_DIR . '/sys/Administration/Role.php';
		$user                   = UserAccount::getActiveUserObj();
		$displayBarcode         = $user->getAccountProfile()->loginConfiguration != 'name_barcode'; // Do not show barcodes in list of admins when using name_barcode login scheme
		$thisIsNotAListOfAdmins = isset($_REQUEST['objectAction']) && $_REQUEST['objectAction'] != 'list';
		$roleList               = Role::fetchAllRoles($thisIsNotAListOfAdmins);  // Lookup available roles in the system, don't show the role description is lists of admins
		$structure              = [
			'id'              => ['property' => 'id', 'type' => 'label', 'label' => 'Administrator Id', 'description' => 'The unique id of the in the system'],
			'firstname'       => ['property' => 'firstname', 'type' => 'label', 'label' => 'First Name', 'description' => 'The first name for the user.'],
			'lastname'        => ['property' => 'lastname', 'type' => 'label', 'label' => 'Last Name', 'description' => 'The last name of the user.'],
			'homeLibraryName' => ['property' => 'homeLibraryName', 'type' => 'label', 'label' => 'Home Library', 'description' => 'The library the user belongs to.'],
			'homeLocation'    => ['property' => 'homeLocation', 'type' => 'label', 'label' => 'Home Location', 'description' => 'The branch the user belongs to.'],
		];

		if ($displayBarcode || $thisIsNotAListOfAdmins){
			//When not displaying barcode, show it for the individual admin
			$structure['barcode'] = ['property' => 'barcode', 'type' => 'label', 'label' => 'Barcode', 'description' => 'The barcode for the user.'];
		}

		$structure['roles'] = ['property' => 'roles', 'type' => 'multiSelect', 'listStyle' => 'checkbox', 'values' => $roleList, 'label' => 'Roles', 'description' => 'A list of roles that the user has.'];

		return $structure;
	}

	function hasRatings(){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';

		$rating = new UserWorkReview();
//		$rating->userid = $this->id;
		$rating->whereAdd("`userId` = {$this->id}");
		$rating->whereAdd('`rating` > 0'); // Some entries are just reviews (and therefore have a default rating of -1)
		$rating->find();
		return $rating->N > 0 ? true : false;
	}

	private $runtimeInfoUpdated = false;

	function updateRuntimeInformation(){
		if (!$this->runtimeInfoUpdated){
			if ($this->getCatalogDriver()){
				$this->getCatalogDriver()->updateUserWithAdditionalRuntimeInformation($this);
			}else{
				echo("Catalog Driver is not configured properly.  Please update indexing profiles and setup Account Profiles");
			}
			$this->runtimeInfoUpdated = true;
		}
	}

	function updateOverDriveOptions(){
		if (isset($_REQUEST['promptForOverDriveEmail']) && ($_REQUEST['promptForOverDriveEmail'] == 'yes' || $_REQUEST['promptForOverDriveEmail'] == 'on')){
			// if set check & on check must be combined because checkboxes/radios don't report 'offs'
			$this->promptForOverDriveEmail = 1;
		}else{
			$this->promptForOverDriveEmail = 0;
		}
		if (isset($_REQUEST['promptForOverDriveLendingPeriods']) && ($_REQUEST['promptForOverDriveLendingPeriods'] == 'yes' || $_REQUEST['promptForOverDriveLendingPeriods'] == 'on')){
			// if set check & on check must be combined because checkboxes/radios don't report 'offs'
			$this->promptForOverDriveLendingPeriods = 1;
		}else{
			$this->promptForOverDriveLendingPeriods = 0;
		}
		if (isset($_REQUEST['overDriveEmail'])){
			$this->overDriveEmail = strip_tags($_REQUEST['overDriveEmail']);
		}
		$this->update();
		if (!empty($_REQUEST['lendingPeriods'])){
			$cache             = new Cache();
			$cacheKey          = $cache->makePatronKey('overdrive_settings', $this->id);
			$overDriveSettings = $cache->get($cacheKey);
			foreach ($_REQUEST['lendingPeriods'] as $formatClass => $lendingPeriodDays){
				if (empty($overDriveSettings['lendingPeriods'][$formatClass]) || $lendingPeriodDays != $overDriveSettings['lendingPeriods'][$formatClass]->lendingPeriod){
					// Only update settings if they have changed from what is cached or we don't have them cached
					$overDriveDriver ??= Pika\PatronDrivers\EcontentSystem\OverDriveDriverFactory::getDriver();
					$overDriveDriver->updateLendingPeriod($this, $formatClass, $lendingPeriodDays);
				}
			}
		}
	}

	function updateHooplaOptions(){
		if (isset($_REQUEST['hooplaCheckOutConfirmation']) && ($_REQUEST['hooplaCheckOutConfirmation'] == 'yes' || $_REQUEST['hooplaCheckOutConfirmation'] == 'on')){
			// if set check & on check must be combined because checkboxes/radios don't report 'offs'
			$this->hooplaCheckOutConfirmation = 1;
		}else{
			$this->hooplaCheckOutConfirmation = 0;
		}
		$this->update();
	}

	function setUserDisplayName(){
		if ($this->firstname == ''){
			$this->displayName = $this->lastname;
		}else{
			// #PK-979 Make display name configurable firstname, last initial, vs first initial last name
			/** @var Library $homeLibrary */
			$homeLibrary = $this->getHomeLibrary();
			if ($homeLibrary == null || ($homeLibrary->patronNameDisplayStyle == 'firstinitial_lastname')){
				// #PK-979 Make display name configurable firstname, last initial, vs first initial last name
				$this->displayName = substr($this->firstname, 0, 1) . '. ' . $this->lastname;
			}elseif ($homeLibrary->patronNameDisplayStyle == 'lastinitial_firstname'){
				$this->displayName = $this->firstname . ' ' . substr($this->lastname, 0, 1) . '.';
			}
		}
		return $this->update();
	}

	function updateUserPreferences(){
		// Validate that the input data is correct
		if (isset($_POST['myLocation1']) && !is_array($_POST['myLocation1']) && preg_match('/^\d{1,3}$/', $_POST['myLocation1']) == 0){
			PEAR_Singleton::raiseError('The 1st location had an incorrect format.');
		}
		if (isset($_POST['myLocation2']) && !is_array($_POST['myLocation2']) && preg_match('/^\d{1,3}$/', $_POST['myLocation2']) == 0){
			PEAR_Singleton::raiseError('The 2nd location had an incorrect format.');
		}
		if (isset($_REQUEST['bypassAutoLogout']) && ($_REQUEST['bypassAutoLogout'] == 'yes' || $_REQUEST['bypassAutoLogout'] == 'on')){
			$this->bypassAutoLogout = 1;
		}else{
			$this->bypassAutoLogout = 0;
		}

		//Make sure the selected location codes are in the database.
		if (isset($_POST['myLocation1'])){
			if ($_POST['myLocation1'] == 0){
				$this->myLocation1Id = $_POST['myLocation1'];
			}else{
				$location = new Location();
				$location->get('locationId', $_POST['myLocation1']);
				if ($location->N != 1){
					PEAR_Singleton::raiseError('The 1st location could not be found in the database.');
				}else{
					$this->myLocation1Id = $_POST['myLocation1'];
				}
			}
		}
		if (isset($_POST['myLocation2'])){
			if ($_POST['myLocation2'] == 0){
				$this->myLocation2Id = $_POST['myLocation2'];
			}else{
				$location = new Location();
				$location->get('locationId', $_POST['myLocation2']);
				if ($location->N != 1){
					PEAR_Singleton::raiseError('The 2nd location could not be found in the database.');
				}else{
					$this->myLocation2Id = $_POST['myLocation2'];
				}
			}

		}

		$this->noPromptForUserReviews = (isset($_POST['noPromptForUserReviews']) && $_POST['noPromptForUserReviews'] == 'on') ? 1 : 0;
		$this->clearCache();
		return $this->update();
	}

	/**
	 * Clear out the cached version of the patron profile.
	 */
	function clearCache(){
		$cache          = new Cache();
		$patronCacheKey = $cache->makePatronKey('patron', $this->id);
		$cache->delete($patronCacheKey);
	}

	/**
	 * @param $list UserList           object of the user list to check permission for
	 * @return  bool       true if this user can edit passed list
	 */
	function canEditList($list){
		if ($this->id == $list->user_id){
			return true;
		}elseif ($this->hasRole('opacAdmin')){
			return true;
		}elseif ($this->hasRole('libraryAdmin') || $this->hasRole('contentEditor') || $this->hasRole('libraryManager')){
			$listUser     = new User();
			$listUser->id = $list->user_id;
			$listUser->find(true);
			$listLibrary = $listUser->getHomeLibrary();
			$userLibrary = $this->getHomeLibrary();
//			$listLibrary = Library::getLibraryForLocation($listUser->homeLocationId);
//			$userLibrary = Library::getLibraryForLocation($this->homeLocationId);
			if ($userLibrary->libraryId == $listLibrary->libraryId){
				return true;
			}elseif (strpos($list->title, 'NYT - ') === 0 && ($this->hasRole('libraryAdmin') || $this->hasRole('contentEditor'))){
				//Allow NYT Times lists to be edited by any library admins and library managers
				return true;
			}
		}elseif ($this->hasRole('locationManager')){
			$listUser     = new User();
			$listUser->id = $list->user_id;
			$listUser->find(true);
			if ($this->homeLocationId == $listUser->homeLocationId){
				return true;
			}
		}
		return false;
	}

	/**
	 * @return Library|null
	 */
	function getHomeLibrary(){
		// Note: Use this for one persistent User.
		// If fetching multiple Users in a fetch loop use Library::getPatronHomeLibrary($user) instead

		if ($this->homeLibrary == null){
			$this->homeLibrary = Library::getPatronHomeLibrary($this);
		}
		return $this->homeLibrary;
	}

	/**
	 * Get the display name of the User's library
	 *
	 * @return string
	 */
	function getHomeLibrarySystemName(){
		$library = $this->getHomeLibrary();
		return empty($library) ? '' : $library->displayName;
	}

	public function getNumCheckedOutTotal($includeLinkedUsers = true){
		$this->updateRuntimeInformation();
		$myCheckouts = $this->numCheckedOutIls + $this->numCheckedOutOverDrive + $this->numCheckedOutHoopla;
		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->getLinkedUsers() as $user){
					$myCheckouts += $user->getNumCheckedOutTotal(false);
				}
			}
		}
		return $myCheckouts;
	}

	public function getNumHoldsTotal($includeLinkedUsers = true){
		$this->updateRuntimeInformation();
		$myHolds = $this->numHoldsIls + $this->numHoldsOverDrive;
		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->linkedUsers as $user){
					$myHolds += $user->getNumHoldsTotal(false);
				}
			}
		}
		return $myHolds;
	}

	public function getNumHoldsAvailableTotal($includeLinkedUsers = true){
		$this->updateRuntimeInformation();
		$myHolds = $this->numHoldsAvailableIls + $this->numHoldsAvailableOverDrive;
		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->linkedUsers as $user){
					$myHolds += $user->getNumHoldsAvailableTotal(false);
				}
			}
		}

		return $myHolds;
	}

	public function getNumBookingsTotal($includeLinkedUsers = true){
		$myBookings  = 0;
		$homeLibrary = $this->getHomeLibrary();
		if ($homeLibrary->enableMaterialsBooking){
			$myBookings = count($this->getMyBookings(false));
		}
		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->linkedUsers as $user){
					$myBookings += $user->getNumBookingsTotal(false);
				}
			}
		}

		return $myBookings;
	}

	private $totalFinesForLinkedUsers = -1;

	public function getTotalFines($includeLinkedUsers = true){
		$totalFines = $this->finesVal;
		if ($includeLinkedUsers){
			if ($this->totalFinesForLinkedUsers == -1){
				if ($this->getLinkedUsers() != null){
					/** @var User $user */
					foreach ($this->linkedUsers as $user){
						$totalFines += $user->getTotalFines(false);
					}
				}
				$this->totalFinesForLinkedUsers = $totalFines;
			}else{
				$totalFines = $this->totalFinesForLinkedUsers;
			}

		}
		return $totalFines;
	}

	/**
	 * Return all titles that are currently checked out by the user.
	 *
	 * Will check:
	 * 1) The current ILS for the user
	 * 2) OverDrive
	 *
	 * @param bool $includeLinkedUsers
	 * @return array
	 */
	public function getMyCheckouts($includeLinkedUsers = true){
		global $timer;

		//Get checked out titles from the ILS
		$ilsCheckouts = $this->getCatalogDriver()->getMyCheckouts($this, !$includeLinkedUsers);
		// When working with linked users with Sierra Encore, curl connections need to be reset for logins to process correctly
		$timer->logTime("Loaded transactions from catalog.");

		//Get checked out titles from OverDrive
		//Do not load OverDrive titles if the parent barcode (if any) is the same as the current barcode
		if ($this->isValidForOverDrive()){
			$overDriveDriver          = Pika\PatronDrivers\EcontentSystem\OverDriveDriverFactory::getDriver();
			$overDriveCheckedOutItems = $overDriveDriver->getOverDriveCheckouts($this);
		}else{
			$overDriveCheckedOutItems = [];
		}

		$allCheckedOut = array_merge($ilsCheckouts, $overDriveCheckedOutItems);

		//Get checked out titles from Hoopla
		//Do not load Hoopla titles if the parent barcode (if any) is the same as the current barcode
		if ($this->isValidForHoopla()){
			require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
			$hooplaDriver          = new HooplaDriver();
			$hooplaCheckedOutItems = $hooplaDriver->getHooplaCheckedOutItems($this);
			$allCheckedOut         = array_merge($allCheckedOut, $hooplaCheckedOutItems);
		}

		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->getLinkedUsers() as $user){
					$allCheckedOut = array_merge($allCheckedOut, $user->getMyCheckouts(false));
				}
			}
		}
		return $allCheckedOut;
	}

	public function getMyHolds($includeLinkedUsers = true, $unavailableSort = 'sortTitle', $availableSort = 'expire'){
		$ilsHolds = $this->getCatalogDriver()->getMyHolds($this, !$includeLinkedUsers);
		// When working with linked users with Sierra Encore, curl connections need to be reset for logins to process correctly
		if (PEAR_Singleton::isError($ilsHolds)){
			$ilsHolds = [];
		}

		//Get holds from OverDrive
		if ($this->isValidForOverDrive()){
			$overDriveDriver = Pika\PatronDrivers\EcontentSystem\OverDriveDriverFactory::getDriver();
			$overDriveHolds  = $overDriveDriver->getOverDriveHolds($this);
		}else{
			$overDriveHolds = [];
		}

		$allHolds = array_merge_recursive($ilsHolds, $overDriveHolds);

		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */

				foreach ($this->getLinkedUsers() as $user){
					$allHolds = array_merge_recursive($allHolds, $user->getMyHolds(false, $unavailableSort, $availableSort));
				}
			}
		}

		$indexToSortBy = 'sortTitle';
		$holdSort      = function ($a, $b) use (&$indexToSortBy){
			$a = isset($a[$indexToSortBy]) ? $a[$indexToSortBy] : null;
			$b = isset($b[$indexToSortBy]) ? $b[$indexToSortBy] : null;

			// Put empty values (except for specified values of zero) at the bottom of the sort
			if (modifiedEmpty($a) && modifiedEmpty($b)){
				return 0;
			}elseif (!modifiedEmpty($a) && modifiedEmpty($b)){
				return -1;
			}elseif (modifiedEmpty($a) && !modifiedEmpty($b)){
				return 1;
			}

			if ($indexToSortBy == 'format'){
				$a = implode($a, ',');
				$b = implode($b, ',');
			}

			return strnatcasecmp($a, $b);
			// This will sort numerically correctly as well
		};

		if (isset($allHolds['available']) && count($allHolds['available']) >= 1){
			switch ($availableSort){
				case 'author' :
				case 'format' :
					$indexToSortBy = $availableSort;
					break;
				case 'title' :
					$indexToSortBy = 'sortTitle';
					break;
				case 'libraryAccount' :
					$indexToSortBy = 'user';
					break;
				case 'expire' :
				default :
					$indexToSortBy = 'expire';
			}
			uasort($allHolds['available'], $holdSort);
		}
		if (isset($allHolds['unavailable']) && count($allHolds['unavailable']) >= 1){
			switch ($unavailableSort){
				case 'author' :
				case 'location' :
				case 'position' :
				case 'status' :
				case 'format' :
					$indexToSortBy = $unavailableSort;
					break;
				case 'placed' :
					$indexToSortBy = 'create';
					break;
				case 'libraryAccount' :
					$indexToSortBy = 'user';
					break;
				case 'title' :
				default :
					$indexToSortBy = 'sortTitle';
			}
			uasort($allHolds['unavailable'], $holdSort);
		}

		return $allHolds;
	}

	public function getMyBookings($includeLinkedUsers = true){
		$ilsBookings = $this->getCatalogDriver()->getMyBookings($this);
		if (PEAR_Singleton::isError($ilsBookings)){
			$ilsBookings = [];
		}

		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->getLinkedUsers() as $user){
					$ilsBookings = array_merge_recursive($ilsBookings, $user->getMyBookings(false));
				}
			}
		}
		return $ilsBookings;
	}


	public function getMyFines($includeLinkedUsers = true){

		if (!isset($this->ilsFinesForUser)){
			$this->ilsFinesForUser = $this->getCatalogDriver()->getMyFines($this, false, !$includeLinkedUsers);
			// When working with linked users with Sierra Encore, curl connections need to be reset for logins to process correctly
			if (PEAR_Singleton::isError($this->ilsFinesForUser)){
				$this->ilsFinesForUser = [];
			}
		}
		$ilsFines[$this->id] = $this->ilsFinesForUser;

		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->getLinkedUsers() as $user){
					$ilsFines += $user->getMyFines(false); // keep keys as userId
				}
			}
		}
		return $ilsFines;
	}

	public function getNameAndLibraryLabel(){
		return $this->displayName . ' - ' . $this->getHomeLibrarySystemName();
	}

	/**
	 * Get a list of locations where a record can be picked up.  Handles linked accounts
	 * and filtering to make sure that the user is able to
	 *
	 * @param string $recordSource The source of the record that we are placing a hold on
	 * @param bool $includeLinkedAccounts Whether or not to include accounts linked to this account
	 * @return Location[]
	 */
	public function getValidPickupBranches($recordSource, $includeLinkedAccounts = true){
		//Get the list of pickup branch locations for display in the user interface.
		// using $user to be consistent with other code use of getPickupBranches()
		$userLocation = new Location();
		if ($recordSource == $this->getAccountProfile()->recordSource){
			$locations = $userLocation->getPickupBranches($this, $this->homeLocationId);
		}else{
			$locations = [];
		}
		if ($includeLinkedAccounts){
			$linkedUsers = $this->getLinkedUsers();
			foreach ($linkedUsers as $linkedUser){
				if ($recordSource == $linkedUser->source){
					$linkedUserLocation        = new Location();
					$linkedUserPickupLocations = $linkedUserLocation->getPickupBranches($linkedUser, null, true);
					foreach ($linkedUserPickupLocations as $sortingKey => $pickupLocation){
						foreach ($locations as $mainSortingKey => $mainPickupLocation){
							// Check For Duplicated Pickup Locations
							if ($mainPickupLocation->libraryId == $pickupLocation->libraryId && $mainPickupLocation->locationId == $pickupLocation->locationId){
								// Merge Linked Users that all have this pick-up location
								$pickupUsers                     = array_unique(array_merge($mainPickupLocation->pickupUsers, $pickupLocation->pickupUsers));
								$mainPickupLocation->pickupUsers = $pickupUsers;
								$pickupLocation->pickupUsers     = $pickupUsers;

								// keep location with better sort key, remove the other
								if ($mainSortingKey == $sortingKey || $mainSortingKey[0] < $sortingKey[0]){
									unset ($linkedUserPickupLocations[$sortingKey]);
								}elseif ($mainSortingKey[0] == $sortingKey[0]){
									if (strcasecmp($mainSortingKey, $sortingKey) > 0){
										unset ($locations[$mainSortingKey]);
									}else{
										unset ($linkedUserPickupLocations[$sortingKey]);
									}
								}else{
									unset ($locations[$mainSortingKey]);
								}

							}
						}
					}
					$locations = array_merge($locations, $linkedUserPickupLocations);
				}
			}
		}
		ksort($locations);
		return $locations;
	}

	/**
	 * Place Hold
	 *
	 * Place a hold for the current user within their ILS
	 *
	 * @param string $recordId The id of the bib record
	 * @param string $pickupBranch The branch where the user wants to pickup the item when available
	 * @param null|string $cancelDate The date to cancel the hold if it isn't fulfilled
	 * @return  mixed                    An array with the following keys:
	 *                                    result - true/false
	 *                                    message - the message to display
	 * @access  public
	 */
	function placeHold($recordId, $pickupBranch, $cancelDate = null){
		global $offlineMode;
		global $configArray;
		$useOfflineHolds = $configArray['Catalog']['useOfflineHoldsInsteadOfRegularHolds'] ?? false;
		if ($offlineMode || $useOfflineHolds){
			$enableOfflineHolds = $configArray['Catalog']['enableOfflineHolds'];
			if ($enableOfflineHolds || $useOfflineHolds){
				$result = $this->placeOfflineHold($recordId, $pickupBranch);
			}else{
				$result = [
					'bib'     => $recordId,
					'success' => false,
					'message' => 'The circulation system is currently offline.  Please try again later.'
				];
			}
		}else{
			if (empty($cancelDate)){
				//Set not need after date, if not supplied, based on library settings
				$cancelDate = $this->getHoldNotNeededAfterDate();
			}

			$result = $this->getCatalogDriver()->placeHold($this, $recordId, $pickupBranch, $cancelDate);
			$this->updateAltLocationForHold($pickupBranch);
			if ($result['success']){
				$this->clearCache();
			}
		}
		return $result;
	}


	function placeVolumeHold($recordId, $volumeId, $pickupBranch, $cancelDate = null){
		global $offlineMode;
		if ($offlineMode){
			global $configArray;
			$enableOfflineHolds = $configArray['Catalog']['enableOfflineHolds'];
			if ($enableOfflineHolds){
				//TODO: Offline Volume Level Holds aren't possible at this time
				$result = [
					'success' => false,
					'message' => 'The circulation system is currently offline.  Please try again later.'
				];
			}else{
				$result = [
					'bib'     => $recordId,
					//					'volumeId' => $volumeId, // TODO: No special handling exists in forms for the return I believe. pascal 11/27/2018
					'success' => false,
					'message' => 'The circulation system is currently offline.  Please try again later.'
				];
			}
		}else{
			if (empty($cancelDate)){
				//Set not need after date, if not supplied, based on library settings
				$cancelDate = $this->getHoldNotNeededAfterDate();
			}

			$result = $this->getCatalogDriver()->placeVolumeHold($this, $recordId, $volumeId, $pickupBranch, $cancelDate);
			$this->updateAltLocationForHold($pickupBranch);
			if ($result['success']){
				$this->clearCache();
			}
		}

		return $result;
	}

	/**
	 * Record an Offline Hold that can be processed later when the circulation system is back online
	 *
	 * @param string $recordId The id of the bib record
	 * @param null $pickupLocation Optional pickup branch location
	 * @param null|string $itemId The id of the item to hold
	 * @return array
	 */
	function placeOfflineHold($recordId, $pickupLocation = null, $itemId = null){
		$sourceAndId = new SourceAndId('ils:' . $recordId);

		require_once ROOT_DIR . '/sys/Circa/OfflineHold.php';
		$offlineHold                 = new OfflineHold();
		$offlineHold->bibId          = $sourceAndId->getRecordId(); //TODO: store full source and id (will need handling in catalog drive place hold actions)
		$offlineHold->itemId         = $itemId;
		$offlineHold->pickupLocation = $pickupLocation;
		$offlineHold->patronBarcode  = $this->getBarcode();
		$offlineHold->patronId       = $this->id;
		$offlineHold->timeEntered    = time();
		$offlineHold->status         = 'Not Processed';

		$title = null;
		// Retrieve Full Marc Record
		require_once ROOT_DIR . '/RecordDrivers/Factory.php';
		$record = RecordDriverFactory::initRecordDriverById($sourceAndId);
		if (!empty($record) && $record->isValid()){
			$title = $record->getTitle();
		}


		if ($offlineHold->insert()){
			return [
				'title'   => $title,
				'bib'     => $recordId,
				'success' => true,
				'message' => 'The circulation system is currently offline.  This hold will be entered for you automatically when the circulation system is online.'];
		}else{
			return [
				'title'   => $title,
				'bib'     => $recordId,
				'success' => false,
				'message' => 'The circulation system is currently offline and we could not place this hold.  Please try again later.'];
		}
	}

	function cancelOfflineHold($recordId, $cancelId){
		require_once ROOT_DIR . '/sys/Circa/OfflineHold.php';
		$sourceAndId        = new SourceAndId('ils:' . $recordId);
		$offlineHold        = new OfflineHold();
		$offlineHold->bibId = $sourceAndId->getRecordId(); //TODO: store full source and id (will need handling in catalog drive place hold actions)
		$offlineHold->id    = $cancelId;
		if ($offlineHold->find(true)){
			if ($offlineHold->delete()){
				return [
					'success' => true,
					'message' => 'Offline hold canceled.',
				];
			}
		}
		return [
			'success' => false,
			'message' => 'Failed to cancel Offline hold.',
		];
	}

	private function getHoldNotNeededAfterDate(){
		$cancelDate  = null;
		$homeLibrary = $this->getHomeLibrary();
		if ($homeLibrary->defaultNotNeededAfterDays != -1){
			$daysFromNow = $homeLibrary->defaultNotNeededAfterDays == 0 ? 182.5 : $homeLibrary->defaultNotNeededAfterDays;
			//Default to a date 6 months (half a year) in the future.
			$nnaDate    = time() + $daysFromNow * 24 * 60 * 60;
			$cancelDate = date('m/d/Y', $nnaDate);
		}
		return $cancelDate;
	}

	function bookMaterial($recordId, $startDate, $startTime, $endDate, $endTime){
		$result = $this->getCatalogDriver()->bookMaterial($this, $recordId, $startDate, $startTime, $endDate, $endTime);
		if ($result['success']){
			$this->clearCache();
		}
		return $result;
	}


	/**
	 * Sets the user's expiration date settings given any string parsable by the standard
	 * at https://www.php.net/manual/en/datetime.formats.php
	 * If the date string isn't valid, it set the user's setting to the standard defaults;
	 *
	 * NOTE: this does NOT update the database.
	 *
	 * @param $dateString
	 */
	function setUserExpirationSettings($dateString){
		$this->expires     = '00-00-0000';
		$this->expireClose = 0;
		$this->expired     = 0;

		if (!empty($dateString)){
			try {
				$expiresDate   = new DateTime($dateString);
				$this->expires = $expiresDate->format('m-d-Y');
				$nowDate       = new DateTime('now');
				$dateDiff      = $nowDate->diff($expiresDate);
				if ($dateDiff->days <= 30){
					$this->expireClose = 1;
				}
				if ($dateDiff->days <= 0){
					$this->expired = 1;
				}
			} catch (\Exception $e){
			}
		}
	}

	/**
	 * This sets the User's home locations and nearby locations.
	 * If there isn't a match to the code, there is a fall-back
	 * to using the main branch or first location of the current library.
	 * After which, there is a fall-back to main branch or first location
	 * of the site's default library.
	 *
	 * NOTE: this does NOT update the database.
	 *
	 * @param string $homeBranchCode The ILS location code for a library branch (matching column code in location table)
	 * @return bool   Whether or not the user needs to be updated in the database.
	 */
	function setUserHomeLocations($homeBranchCode){
		$currentHomeLocation = false;
		if (!empty($this->homeLocationId) && $this->homeLocationId != -1){
			$tempLocation = new Location();
			if ($tempLocation->get($this->homeLocationId)){
				$currentHomeLocation = $tempLocation;
			}
		}

		$locationFromILS = false;
		if (!empty($homeBranchCode)){
			$homeBranchCode = strtolower($homeBranchCode);
			if (!empty($currentHomeLocation->code) && $currentHomeLocation->code == $homeBranchCode){
				// If the current home location's code matches the home branch code, prevent an unneeded database lookup
				$locationFromILS = $currentHomeLocation;
			}else{
				$tempLocation = new Location();
				if ($tempLocation->get('code', $homeBranchCode)){
					$locationFromILS = $tempLocation;
				}
			}
		}

		$updateUserNeeded = true;
		$updateLocation   = false;
		if (!empty($currentHomeLocation) && !empty($locationFromILS) && $currentHomeLocation->locationId == $locationFromILS->locationId){
			// Nothing's changed, Just set alternate locations
			$updateLocation = $currentHomeLocation;
			if ($this->homeLibraryId == $locationFromILS->libraryId){
				// Make sure the home library id is set too
				$updateUserNeeded = false;
			}
		}elseif (!empty($locationFromILS)){
			// Got a good location from the ILS
			$updateLocation = $locationFromILS;
		}else{
			// Get current library and use default location for home location
			global /** @var Library $library */
			$library;
			if (!empty($library->libraryId)){
				$updateLocation = Location::getDefaultLocationForLibrary($library->libraryId);
			}

			// Fall-back library
			if (!$updateLocation){
				// The library isn't set or didn't have any locations, so now we will fall back to the site's default library and use a location for that library
				$defaultLibrary            = new Library();
				$defaultLibrary->isDefault = true;
				if ($defaultLibrary->find(true)){
					$updateLocation = Location::getDefaultLocationForLibrary($defaultLibrary->libraryId);
				}
			}
		}

		// Set home location, home library, and alternate locations
		if (!empty($updateLocation)){
			$this->homeLocationId = $updateLocation->locationId;
			$this->homeLibraryId  = $updateLocation->libraryId;

			// Get display names that aren't stored
			$this->homeLocationCode = $updateLocation->code;
			$this->homeLocation     = $updateLocation->displayName;

			// Set default alternate locations if they haven't been set yet
			if (empty($this->myLocation1Id)){
				// if the user hasn't set an alternate location, use location's values or current location
				$this->myLocation1Id = ($updateLocation->nearbyLocation1 > 0) ? $updateLocation->nearbyLocation1 : $updateLocation->locationId;
			}
			if (empty($this->myLocation2Id)){
				// if the user hasn't set an alternate location, use location's values or current location
				$this->myLocation1Id = ($updateLocation->nearbyLocation2 > 0) ? $updateLocation->nearbyLocation2 : $updateLocation->locationId;
			}

			// Get display name for preferred location 1
			if ($this->myLocation1Id == $updateLocation->locationId){
				$this->myLocation1 = $updateLocation->displayName;
			}else{
				$tempLocation = new Location();
				if ($tempLocation->get($this->myLocation1Id)){
					$this->myLocation1 = $tempLocation->displayName;
				}
			}

			// Get display name for preferred location 2
			if ($this->myLocation2Id == $updateLocation->locationId){
				$this->myLocation2 = $updateLocation->displayName;
			}else{
				$tempLocation = new Location();
				if ($tempLocation->get($this->myLocation2Id)){
					$this->myLocation2 = $tempLocation->displayName;
				}
			}
		}
		return $updateUserNeeded;
	}

	function updateAltLocationForHold($pickupBranch){
		if ($this->homeLocationCode != $pickupBranch){

			$this->logger->info("The selected pickup branch is not the user's home location, checking to see if we need to set an alternate branch");
			$location       = new Location();
			$location->code = $pickupBranch;
			if ($location->find(true)){
				$this->logger->info("Found the location for the pickup branch $pickupBranch {$location->locationId}");
				if ($this->myLocation1Id == 0){
					$this->logger->info("Alternate location 1 is blank updating that");
					$this->myLocation1Id = $location->locationId;
					$this->update();
				}else{
					if ($this->myLocation2Id == 0 && $location->locationId != $this->myLocation1Id){
						$this->logger->info("Alternate location 2 is blank updating that");
						$this->myLocation2Id = $location->locationId;
						$this->update();
					}
				}
			}else{
				$this->logger->error("Could not find location for $pickupBranch");
			}
		}
	}

	function cancelBookedMaterial($cancelId){
		$result = $this->getCatalogDriver()->cancelBookedMaterial($this, $cancelId);
		$this->clearCache();
		return $result;
	}

	function cancelAllBookedMaterial($includeLinkedUsers = true){
		$result = $this->getCatalogDriver()->cancelAllBookedMaterial($this);
		$this->clearCache();

		if ($includeLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->getLinkedUsers() as $user){

					$additionalResults = $user->cancelAllBookedMaterial(false);
					if (!$additionalResults['success']){ // if we received failures
						if ($result['success']){
							$result = $additionalResults; // first set of failures, overwrite currently successful results
						}else{ // if there were already failures, add the extra failure messages
							$result['message'] = array_merge($result['message'], $additionalResults['message']);
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for placing item level holds.
	 *
	 * @param string $recordId The id of the bib record
	 * @param string $itemId The id of the item to hold
	 * @param string $pickupBranch The branch where the user wants to pickup the item when available
	 * @param null|string $cancelDate The date to cancel the hold if it isn't fulfilled
	 * @return  mixed                    True if successful, false if unsuccessful
	 *                                   If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function placeItemHold($recordId, $itemId, $pickupBranch, $cancelDate = null){
		global $offlineMode;
		global $configArray;
		$useOfflineHolds = $configArray['Catalog']['useOfflineHoldsInsteadOfRegularHolds'] ?? false;
		if ($offlineMode || $useOfflineHolds){
			$enableOfflineHolds = $configArray['Catalog']['enableOfflineHolds'];
			if ($enableOfflineHolds || $useOfflineHolds){
				$result = $this->placeOfflineHold($recordId, $pickupBranch, $itemId);
			}else{
				$result = [
					'bib'     => $recordId,
					//					'itemId'  => $itemId, // TODO: No special handling exists in forms for the return I believe. pascal 11/27/2018
					'success' => false,
					'message' => 'The circulation system is currently offline.  Please try again later.'
				];
			}
		}else{
			if (empty($cancelDate)){
				//Set not need after date, if not supplied, based on library settings
				$cancelDate = $this->getHoldNotNeededAfterDate();
			}

			$result = $this->getCatalogDriver()->placeItemHold($this, $recordId, $itemId, $pickupBranch, $cancelDate);
			$this->updateAltLocationForHold($pickupBranch);
			if ($result['success']){
				$this->clearCache();
			}
		}
		return $result;
	}

	/**
	 * Get the user referred to by id.  Will return false if the specified patron id is not
	 * the id of this user or one of the users that is linked to this user.
	 *
	 * @param $patronId     int  The patron to check
	 * @return User|false
	 */
	function getUserReferredTo($patronId){
		$patron = false;
		//Get the correct patron based on the information passed in.
		if ($patronId == $this->id){
			$patron = $this;
		}else{
			foreach ($this->getLinkedUsers() as $tmpUser){
				if ($tmpUser->id == $patronId){
					$patron = $tmpUser;
					break;
				}
			}
		}
		return $patron;
	}

	/**
	 * Cancels a hold for the user in their ILS
	 *
	 * @param $recordId string  The Id of the record being cancelled
	 * @param $cancelId string  The Id of the hold to be cancelled.  Structure varies by ILS
	 *
	 * @return array            Information about the result of the cancellation process
	 */
	function cancelHold($recordId, $cancelId){
		global $offlineMode;
		global $configArray;
		$useOfflineHolds = $configArray['Catalog']['useOfflineHoldsInsteadOfRegularHolds'] ?? false;
		if ($offlineMode || $useOfflineHolds){
			$enableOfflineHolds = $configArray['Catalog']['enableOfflineHolds'];
			if ($enableOfflineHolds || $useOfflineHolds){
				$result = $this->cancelOfflineHold($recordId, $cancelId);
			}
			else{
				$result = [
					'bib'     => $recordId,
					'success' => false,
					'message' => 'The circulation system is currently offline.  Please try again later.'
				];
			}
		}else{
			$result = $this->getCatalogDriver()->cancelHold($this, $recordId, $cancelId);
		}
		$this->clearCache();
		return $result;
	}

//		function changeHoldPickUpLocation($recordId, $itemToUpdateId, $newPickupLocation){
	//$recordId is not used to update change hold pick up location in driver
	function changeHoldPickUpLocation($itemToUpdateId, $newPickupLocation){
		$result = $this->getCatalogDriver()->changeHoldPickupLocation($this, null, $itemToUpdateId, $newPickupLocation);
		$this->clearCache();
		return $result;
	}

	function freezeHold($recordId, $holdId, $reactivationDate){
		$result = $this->getCatalogDriver()->freezeHold($this, $recordId, $holdId, $reactivationDate);
		$this->clearCache();
		return $result;
	}

	function thawHold($recordId, $holdId){
		$result = $this->getCatalogDriver()->thawHold($this, $recordId, $holdId);
		$this->clearCache();
		return $result;
	}

	function renewItem($recordId, $itemId, $itemIndex){
		$result = $this->getCatalogDriver()->renewItem($this, $recordId, $itemId, $itemIndex);
		$this->clearCache();
		return $result;
	}

	function renewAll($renewLinkedUsers = false){
		$renewAllResults = $this->getCatalogDriver()->renewAll($this);
		//Also renew linked Users if needed
		if ($renewLinkedUsers){
			if ($this->getLinkedUsers() != null){
				/** @var User $user */
				foreach ($this->getLinkedUsers() as $user){
					$linkedResults = $user->renewAll(false);
					//Merge results
					$renewAllResults['Renewed']   += $linkedResults['Renewed'];
					$renewAllResults['Unrenewed'] += $linkedResults['Unrenewed'];
					$renewAllResults['Total']     += $linkedResults['Total'];
					if ($renewAllResults['success'] && !$linkedResults['success']){
						$renewAllResults['success'] = false;
						$renewAllResults['message'] = $linkedResults['message'];
					}else{
						if (!$renewAllResults['success'] && !$linkedResults['success']){
							//Append the new message

							array_merge($renewAllResults['message'], $linkedResults['message']);
						}
					}
				}
			}
		}
		$this->clearCache();
		return $renewAllResults;
	}

	public function getReadingHistory($page, $recordsPerPage, $selectedSortOption, $searchTerm, $searchFields){
		return $this->getCatalogDriver()->getReadingHistory($this, $page, $recordsPerPage, $selectedSortOption, $searchTerm, $searchFields);
	}

	/**
	 * Filter a patrons reading history by search term and title and/or author
	 * @param $searchTerm
	 * @param $searchFields
	 */
	public function searchReadingHistory($page, $recordsPerPage, $selectedSortOption, $searchTerm, $searchFields) {
		return $this->getCatalogDriver()->getReadingHistory($this, $page, $recordsPerPage, $selectedSortOption, $searchTerm, $searchFields);
	}

	public function loadReadingHistoryFromILS($loadAdditional = null){
		$catalogDriver = $this->getCatalogDriver();
		if (method_exists($catalogDriver, 'loadReadingHistoryFromIls')){
			return $catalogDriver->loadReadingHistoryFromIls($this, $loadAdditional);
		} else {
			return $catalogDriver->getReadingHistory($this);
		}
	}

	/**
	 * Opt the user into Pika's reading history functionality
	 */
	public function optInReadingHistory(){
		$result = $this->getCatalogDriver()->optInReadingHistory($this);
		$this->clearCache();
		return $result;
	}

	public function optOutReadingHistory(){
		$result = $this->getCatalogDriver()->optOutReadingHistory($this);
		$this->clearCache();
		return $result;
	}

	public function deleteAllReadingHistory(){
		$result = $this->getCatalogDriver()->deleteAllReadingHistory($this);
		$this->clearCache();
		return $result;
	}

	public function deleteMarkedReadingHistory($selectedTitles) {
		$result = $this->getCatalogDriver()->deleteMarkedReadingHistory($this, $selectedTitles);
		$this->clearCache();
		return $result;

	}

private $staffPtypes = null;
	/**
	 * Used by Account Profile, to show users any additional Admin roles they may have.
	 * @return bool
	 */
	public function isStaff(){
		if (count($this->getRoles()) > 0){
			return true;
		}else{
			if (is_null($this->staffPtypes)){
				$pType               = new PType();
				$pType->isStaffPType = true;
				$this->staffPtypes   = $pType->fetchAll('Ptype');
			}
			if (!empty($this->staffPtypes) && in_array($this->patronType, $this->staffPtypes)){
				return true;
			}
		}
		return false;
	}

	public function updatePatronInfo($canUpdateContactInfo){
		$result = $this->getCatalogDriver()->updatePatronInfo($this, $canUpdateContactInfo);
		$this->clearCache();
		return $result;
	}

	public function updatePin(){
		global $configArray;

		$pinMinimumLength = $configArray['Catalog']['pinMinimumLength'];
		$pinMaximumLength = $configArray['Catalog']['pinMaximumLength'];

		if (isset($_REQUEST['pin'])){
			$oldPin = $_REQUEST['pin'];
		}else{
			return "Please enter your current pin number";
		}
		if ($this->getPassword() != $oldPin){
			return "The old pin number is incorrect";
		}
		if (!empty($_REQUEST['pin1'])){
			$newPin = $_REQUEST['pin1'];
		}else{
			return "Please enter the new pin number";
		}
		if (!empty($_REQUEST['pin2'])){
			$confirmNewPin = $_REQUEST['pin2'];
		}else{
			return "Please enter the new pin number again";
		}
		if ($newPin != $confirmNewPin){
			return "New PINs do not match. Please try again.";
		}
		// pin min and max length check 
		$pinLength = strlen($newPin);
		if ($pinLength < $pinMinimumLength OR $pinLength > $pinMaximumLength) {
			if ($pinMinimumLength == $pinMaximumLength){
				return "New PIN must be exactly " . $pinMinimumLength . " characters.";
			}else{
				return "New PIN must be " . $pinMinimumLength . " to " . $pinMaximumLength . " characters.";
			}
		}
		$result = $this->getCatalogDriver()->updatePin($this, $oldPin, $newPin, $confirmNewPin);
		$this->clearCache();
		return $result;
	}

	function getRelatedPTypes($includeLinkedUsers = true){
		$relatedPTypes                    = array();
		$relatedPTypes[$this->patronType] = $this->patronType;
		if ($includeLinkedUsers){
			if ($this->getLinkedUserObjects() != null){
				/** @var User $user */
				foreach ($this->getLinkedUserObjects() as $user){
					$relatedPTypes = array_merge($relatedPTypes, $user->getRelatedPTypes(false));
				}
			}
		}
		return $relatedPTypes;
	}

	function importListsFromIls(){
		$result = $this->getCatalogDriver()->importListsFromIls($this);
		return $result;
	}

	public function getShowUsernameField(){
		return $this->getCatalogDriver()->getShowUsernameField();
	}

	/**
	 * @return string
	 */
	public function getMasqueradeLevel(){
		if (empty($this->masqueradeLevel)){
			$this->setMasqueradeLevel();
		}
		return $this->masqueradeLevel;
	}

	private function setMasqueradeLevel(){
		$this->masqueradeLevel = 'none';
		if (isset($this->patronType) && !is_null($this->patronType) && $this->patronType !== false){ // (patronType 0 can be a valid value)
			require_once ROOT_DIR . '/sys/Account/PType.php';
			$pType = new pType();
			$pType->get('pType', $this->patronType);
			if ($pType->N > 0){
				$this->masqueradeLevel = $pType->masquerade;
			}
		}
	}

	public function canMasquerade(){
		return $this->getMasqueradeLevel() != 'none';
	}

	/**
	 * @param mixed $materialsRequestReplyToAddress
	 */
	public function setMaterialsRequestReplyToAddress($materialsRequestReplyToAddress){
		$this->materialsRequestReplyToAddress = $materialsRequestReplyToAddress;
	}

	/**
	 * @param mixed $materialsRequestEmailSignature
	 */
	public function setMaterialsRequestEmailSignature($materialsRequestEmailSignature){
		$this->materialsRequestEmailSignature = $materialsRequestEmailSignature;
	}

	function setNumCheckedOutHoopla($val){
		$this->numCheckedOutHoopla = $val;
	}

	function setNumCheckedOutOverDrive($val){
		$this->numCheckedOutOverDrive = $val;
	}

	function setNumHoldsAvailableOverDrive($val){
		$this->numHoldsAvailableOverDrive = $val;
		$this->numHoldsOverDrive          += $val;
	}

	function setNumHoldsRequestedOverDrive($val){
		$this->numHoldsRequestedOverDrive = $val;
		$this->numHoldsOverDrive          += $val;
	}

	function setNumMaterialsRequests($val){
		$this->numMaterialsRequests = $val;
	}

	function getNumMaterialsRequests(){
		$this->updateRuntimeInformation();
		return $this->numMaterialsRequests;
	}

	function setReadingHistorySize($val){
		$this->readingHistorySize = $val;
	}

	function getReadingHistorySize(){
		$this->updateRuntimeInformation();
		return $this->readingHistorySize;
	}
}

function modifiedEmpty($var){
	// specified values of zero will not be considered empty
	return empty($var) && $var !== 0 && $var !== '0';
}
