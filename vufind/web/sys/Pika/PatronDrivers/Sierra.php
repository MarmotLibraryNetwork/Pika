<?php
/**
 *
 * Class Sierra
 *
 * Main driver to define the main methods needed for completing patron actions in the Seirra ILS
 *
 * This class implements the Sierra REST Patron API for patron interactions:
 *    https://sandbox.iii.com/iii/sierra-api/swagger/index.html#!/patrons
 *
 * NOTES:
 *  Pika stores the Sierra patron id in the username field in user table.
 *  Currently, in the database password and cat_username represent the patron bar codes. The password is now obsolete
 *  and it's preferred to not store the Sierra ID in the database and prefer barcode as an index for finding a patron
 *  in the database.
 *
 *  For auth type barcode_pin
 *    barcode stored in cat_username field
 *    pin is stored in cat_password field (this field can be removed when using the api)
 *
 *  For auth type name_barcode
 *    barcode is stored in cat_password field
 *    name is stored in cat_username field
 *
 * Caching
 *
 * caching keys follow this pattern
 * patron_{barcode}_{object}
 * ie; patron_123456789_checkouts, patron_123456789_holds
 * the patron object is patron_{barcode}_patron
 * when calling an action (ie; placeHold, updatePatronInfo) both the patron cache and the object cache should be unset
 *
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * @author   Pascal Brammeier
 * Date      5/13/2019
 *
 */
namespace Pika\PatronDrivers;

use Curl\Curl;
use DateTime;
use ErrorException;
use InvalidArgumentException;
use \Pika\Logger;
use \Pika\Cache;
use Location;
use MarcRecord;
use RecordDriverFactory;
use User;


class Sierra {
	// TODO: Clean up logging

	// @var Pika/Memcache instance
	public  $memCache;
	// @var $logger Pika/Logger instance
	private $logger;

	private $configArray;
	// ----------------------
	/* @var $oAuthToken oAuth2Token */
	private $oAuthToken;
	/* @var $apiLastError false|string false if no error or last error message */
	private $apiLastError = false;
	// Needs to be public
	public $accountProfile;
	/* @var $patronBarcode string The patrons barcode */
	private $patronBarcode;
	/* @var $apiUrl string The url for the Sierra API */
	private $apiUrl;
	/* @var $tokenUrl string The url for token */
	private $tokenUrl;
	// many ids come from url. example: https://sierra.marmot.org/iii/sierra-api/v5/items/5130034
	private $urlIdRegExp = "/.*\/(\d*)$/";


	public function __construct($accountProfile) {
		global $configArray;

		$this->configArray    = $configArray;
		$this->accountProfile = $accountProfile;
		$this->logger = new Logger('SierraPatronAPI');

		$cache = initCache();
		$this->memCache = new Cache($cache);
		// build the api url
		// JIC strip any trailing slash and spaces.
		$baseApiUrl = trim($accountProfile->patronApiUrl,'/ ');
		$apiUrl = $baseApiUrl . '/iii/sierra-api/v'.$configArray['Catalog']['api_version'] . '/';
		$tokenUrl = $baseApiUrl . '/iii/sierra-api/token';
		$this->apiUrl   = $apiUrl;
		$this->tokenUrl = $tokenUrl;

		// grab an oAuthToken
		if(!isset($this->oAuthToken)) {
			if(!$this->_oAuthToken()) {
				// logging happens in _oAuthToken()
				return null;
			}
		}
	}

	/**
	 * Retrieve a patrons checkouts
	 *
	 * GET patrons/{patronId}/checkouts?fields=default%2Cbarcode
	 *
	 * @param User $patron
	 * @param bool $linkedAccount
	 * @return array|false
	 */
	public function getMyCheckouts($patron, $linkedAccount = false){

		$patronCheckoutsCacheKey = "patron_".$patron->barcode."_checkouts";
		if(!$linkedAccount) {
			if($patronCheckouts = $this->memCache->get($patronCheckoutsCacheKey)) {
				$this->logger->info("Found checkouts in memcache:".$patronCheckoutsCacheKey);
				return $patronCheckouts;
			}
		}
		$patronId = $this->getPatronId($patron);

		$operation = 'patrons/'.$patronId.'/checkouts';
		$params = [
			'fields'=>'default,barcode,callNumber'
		];

		$r = $this->_doRequest($operation,$params);

		if (!$r) {
			$this->logger->info($this->apiLastError);
			return false;
		}

		// no checkouts
		if($r->total == 0) {
			return [];
		}

		$checkouts = [];
		foreach($r->entries as $entry) {
			// standard stuff
			// get checkout id
			preg_match($this->urlIdRegExp, $entry->id, $m);
			$checkoutId = $m[1];

			if(strstr($entry->item, "@")) {
				///////////////
				// INNREACH CHECKOUT
				///////////////

				// $theme to look for cover image
				$theme = $this->configArray['Site']['theme'];

				$innReach = new InnReach();
				$titleAndAuthor = $innReach->getCheckoutTitleAuthor($checkoutId);

				$checkout['checkoutSource'] = 'ILS';
				$checkout['id']             = $checkoutId;
				$checkout['dueDate']        = strtotime($entry->dueDate);
				$checkout['checkoutDate']   = strtotime($entry->outDate);
				$checkout['renewCount']     = $entry->numberOfRenewals;
				$checkout['recordId']       = 0;
				$checkout['renewIndicator'] = $checkoutId;
				$checkout['renewMessage']   = '';
				$checkout['coverUrl']       = '/interface/themes/'.$theme.'/images/InnReachCover.png';
				$checkout['barcode']        = $entry->barcode;
				$checkout['request']        = $entry->callNumber;
				$checkout['author']        = $titleAndAuthor['author'];
				$checkout['title']         = $titleAndAuthor['title'];
				$checkout['title_sort']    = $titleAndAuthor['sort_title'];
				// todo: can innreach checkouts be renewed?
				$checkout['canrenew']       = true;

				$checkouts[] = $checkout;
				unset($checkout);
				continue;
			}

			// grab the bib id and make Pika-tize it
			preg_match($this->urlIdRegExp, $entry->item, $m);
			$itemId = $m[1];
			$bid = $this->_getBibIdFromItemId($itemId);

			// for Pika we need the check digit.
			$recordXD  = $this->getCheckDigit($bid);
			$bibId = '.b'.$bid.$recordXD;

			$checkout['checkoutSource'] = 'ILS';
			$checkout['recordId']       = $bibId;
			$checkout['id']             = $checkoutId;
			$checkout['dueDate']        = strtotime($entry->dueDate);
			$checkout['checkoutDate']   = strtotime($entry->outDate);
			$checkout['renewCount']     = $entry->numberOfRenewals;
			$checkout['barcode']        = $entry->barcode;
			$checkout['request']        = $entry->callNumber;
			$checkout['itemid']         = $itemId;
			$checkout['canrenew']       = true;
			$checkout['renewIndicator'] = $checkoutId;
			$checkout['renewMessage']   = '';
			$recordDriver = new MarcRecord($this->accountProfile->recordSource . ":" . $bibId);
			if ($recordDriver->isValid()) {
				$checkout['coverUrl']      = $recordDriver->getBookcoverUrl('medium');
				$checkout['groupedWorkId'] = $recordDriver->getGroupedWorkId();
				$checkout['ratingData']    = $recordDriver->getRatingData();
				$checkout['format']        = $recordDriver->getPrimaryFormat();
				$checkout['author']        = $recordDriver->getPrimaryAuthor();
				$checkout['title']         = $recordDriver->getTitle();
				$checkout['title_sort']    = $recordDriver->getSortableTitle();
				$checkout['link']          = $recordDriver->getLinkUrl();
			} else {
				$checkout['coverUrl']      = "";
				$checkout['groupedWorkId'] = "";
				$checkout['format']        = "Unknown";
				$checkout['author']        = "";
			}

			$checkouts[] = $checkout;
			unset($checkout);
		}

		if(!$linkedAccount) {
			$this->memCache->set($patronCheckoutsCacheKey, $checkouts, $this->configArray['Caching']['user']);
			$this->logger->info("Saving checkouts in memcache:".$patronCheckoutsCacheKey);
		}
		return $checkouts;
	}

	/**
	 * Renew a checkout
	 * POST patrons/checkouts/{checkoutId}/renewal
	 *
	 * @param      $patron
	 * @param      $bibId
	 * @param      $checkoutId
	 * @param null $itemIndex
	 * @return array
	 */
	public function renewItem($patron, $bibId, $checkoutId, $itemIndex = NULL){
		// unset cache
		$patronCheckoutsCacheKey = "patron_".$patron->barcode."_checkouts";
		$this->logger->info("Removing checkouts from memcache:".$patronCheckoutsCacheKey);
		$this->memCache->delete($patronCheckoutsCacheKey);

		$operation = 'patrons/checkouts/'.$checkoutId.'/renewal';

		$r = $this->_doRequest($operation, [], 'POST');
		if(!$r) {
			$message = $this->_getPrettyError();
			$return = [
				'success' => false,
				'message' => "Unable to renew your checkout: ".$message
			];
			return $return;
		}

		$recordDriver = new MarcRecord($this->accountProfile->recordSource . ":" . $bibId);
		if ($recordDriver->isValid()) {
			$title = $recordDriver->getTitle();
		} else {
			$title = false;
		}

		$return = ['success' => true];
		if($title) {
			$return['message'] = $title.' has been renewed.';
		} else {
			$return['message'] = 'Your item has been renewed';
		}

		return $return;
	}

	/**
	 * Patron Login
	 *
	 * Authenticate a patron against the Sierra REST API.
	 *
	 * TODO: Use caution with sites using usernames. If you don't write functions for usernames it will break.
	 *
	 * @param   string  $username         The patron username or barcode
	 * @param   string  $password         The patron barcode or pin
	 * @param   boolean $validatedViaSSO  FALSE
	 *
	 * @return  User|null           User object or null
	 * @access  public
	 */
	public function patronLogin($username, $password, $validatedViaSSO = FALSE){
		$this->logger->info("patronLogin called from ".debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class']);
		// get the login configuration barcode_pin or name_barcode
		// TODO: Need to pull login from session, db, memcache, etc, so login isn't called repeatably on each request.
		$loginMethod = $this->accountProfile->loginConfiguration;
		// check patron credentials depending on login config.
		// the returns from _auth methods should be either a sierra patron id or false.
		if ($loginMethod == "barcode_pin"){
			$barcode = $username;
			$this->patronBarcode = $barcode;
			$patronId = $this->_authBarcodePin($username, $password);
		} elseif ($loginMethod == "name_barcode") {
			$barcode = $password;
			$this->patronBarcode = $barcode;
			$patronId = $this->_authNameBarcode($username, $password);
		} else {
			$msg = "Invalid loginConfiguration setting.";
			$this->logger->error($msg);
			throw new InvalidArgumentException($msg);
		}
		// can't find patron
		if (!$patronId) {
			$msg = "Can't get patron id from Sierra API.";
			$this->logger->warn($msg, ['barcode'=>$this->patronBarcode]);
			return null;
		}

		$patron = $this->getPatron($patronId);
		return $patron;
	}

	/**
	 * Builds and returns the patron object
	 *
	 * TODO: Use caution with sites using usernames. If you don't write functions for usernames it will break.
	 *
	 * Because every library has a unique way of entering address, a "hook" has been included to do accommodate.
	 * Any class extending this base class can include a method name processPatronAddress($addresses) for handling
	 * that particularly finicky bit.
	 *
	 * @param int $patronId Unique Sierra patron id
	 * @return User|null
	 * @throws InvalidArgumentException
	 * @throws ErrorException
	 */
	public function getPatron($patronId) {
		// grab everything from the patron record the api can provide.
		// titles on hold to patron object
		if(!isset($patronId)) {
			throw new InvalidArgumentException("ERROR: getPatron expects at least on parameter.");
		}

		$patronObjectCacheKey = 'patron_'.$this->patronBarcode.'_patron';
		if ($pObj = $this->memCache->get($patronObjectCacheKey)) {
			$this->logger->info("Found patron in memcache:".$patronObjectCacheKey);
			return $pObj;
		}

		$createPatron = false;
		$updatePatron = false;

		$patron = new User();
		// check if the user exists in Pika database
		// use barcode as sierra patron id is no longer stored in database as username.
		// get the login configuration barcode_pin or name_barcode
		$loginMethod    = $this->accountProfile->loginConfiguration;
		$patron->source = $this->accountProfile->name;
		if ($loginMethod == "barcode_pin") {
			$patron->cat_username = $this->patronBarcode;
		} else {
			$patron->cat_password = $this->patronBarcode;
		}

		$patron->barcode = $this->patronBarcode;
		// does the user exist in database?
		if(!$patron->find(true)) {
			$this->logger->info('Patron does not exits in Pika database.', ['barcode'=>$this->patronBarcode]);
			$createPatron = true;
		}

		// make api call for info
		$params = [
			'fields' => 'names,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,barcodes,patronType,patronCodes,createdDate,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate'
		];
		$operation = 'patrons/'.$patronId;
		$pInfo = $this->_doRequest($operation, $params);
		if(!$pInfo) {
			return null;
		}

		// checks; make sure patron info from sierra matches database. update if needed.
		// check names
		if ($loginMethod == "name_barcode") {
			if($patron->cat_username != $pInfo->names[0]) {
				$updatePatron = true;
				$patron->cat_username = $pInfo->names[0];
			}
		}
		$nameParts = explode(',', $pInfo->names[0]);
		$firstName = trim($nameParts[1]);
		$lastName  = trim($nameParts[0]);
		if($firstName != $patron->firstname || $lastName != $patron->lastname) {
			$updatePatron = true;
			$patron->firstname = $firstName;
			$patron->lastname  = $lastName;
		}

		// check patron type
		if((int)$pInfo->patronType !== (int)$patron->patronType) {
			$updatePatron = true;
			$patron->patronType = $pInfo->patronType;
		}
		// check email
		if(isset($pInfo->emails) && $pInfo->emails[0] != $patron->email) {
			$updatePatron = true;
			$patron->email = $pInfo->emails[0];
		} else {
			if(empty($patron->email)) {
				$patron->email = '';
			}
		}
		// username
		if($this->hasUsernameField()) {
			$fields = $pInfo->varFields;
			$i = array_filter($fields, function($k) {
				return ($k->fieldTag == 'i');
			});
			if(empty($i)) {
				$username = strtolower($firstName) . '.' . strtolower($lastName);
			} else {
				$key = array_key_first($i);
				$patron->alt_username = $i[$key]->content;
				$username = $patron->alt_username;
			}
		} else {
			if(!empty($patron->email)) {
				$username = $patron->email;
			} else {
				$username = strtolower($firstName) . '.' . strtolower($lastName);
			}
		}
		if($username != $patron->username) {
			$patron->username = $username;
			//$updatePatron = true;
		}
		// check phones
		$homePhone   = '';
		$mobilePhone = '';
		foreach($pInfo->phones as $phone) {
			if($phone->type == 't') {
				$homePhone  = $phone->number;
			} elseif ($phone->type == 'o') {
				$mobilePhone = $phone->number;
			} elseif ($phone->type == 'p') {
				$patron->workPhone = $phone->number;
			}
		}
		// try home phone first then mobile phone
		if(!empty($homePhone) && $patron->phone != $homePhone) {
			$updatePatron = true;
			$patron->phone = $homePhone;
		} elseif(!isset($homePhone) && isset($mobilePhone) && $patron->phone != $mobilePhone) {
			$updatePatron = true;
			$patron->phone = $mobilePhone;
		} else {
			if(empty($patron->phone)) {
				$patron->phone = '';
			}
		}
		// check home location
		$location       = new Location();
		$location->code = $pInfo->homeLibraryCode;
		$location->find(true);
		$homeLocationId = $location->locationId;
		if($homeLocationId != $patron->homeLocationId) {
			$updatePatron = true;
			$patron->homeLocationId = $homeLocationId;
		}
		$patron->homeLocation = $location->displayName;

		// location1
		if(empty($patron->myLocation1Id)) {
			$updatePatron = true;
			$patron->myLocation1Id     = ($location->nearbyLocation1 > 0) ? $location->nearbyLocation1 : $location->locationId;
			$myLocation1             = new Location();
			$myLocation1->locationId = $patron->myLocation1Id;
			if ($myLocation1->find(true)) {
				$patron->myLocation1 = $myLocation1->displayName;
			}
		}
		// location2
		if(empty($patron->myLocation2Id)) {
			$updatePatron = true;
			$patron->myLocation2Id     = ($location->nearbyLocation2 > 0) ? $location->nearbyLocation2 : $location->locationId;
			$myLocation2             = new Location();
			$myLocation2->locationId = $patron->myLocation2Id;
			if ($myLocation2->find(true)) {
				$patron->myLocation2 = $myLocation2->displayName;
			}
		}
		// things not stored in database so don't need to check but need to add to object.
		// fullname
		$patron->fullname  = $pInfo->names[0];
		// barcodes
		$patron->barcode   = $pInfo->barcodes[0];


		if(method_exists($this, 'processPatronAddress')) {
			/** Hook for processing addresses **/
			$patron = $this->processPatronAddress($pInfo->addresses, $patron);
		} else {
			foreach ($pInfo->addresses as $address) {
				// a = primary address, h = alt address
				if ($address->type == 'a') {
					$lineCount = count($address->lines) - 1;

					$patron->address1 = $address->lines[$lineCount - 1];
					$patron->address2 = $address->lines[$lineCount];
				}
			}
			if (!isset($patron->address1)) {
				$patron->address1 = '';
			}
			if (!isset($patron->address2)) {
				$patron->address2 = '';
			}
			// city state zip
			if ($patron->address2 != '') {
				$addressParts = explode(',', $patron->address2);
				// some libraries may not use ','  after city so make sure we have parts
				// can assume street as a line and city, st. zip as a line
				if (count($addressParts) > 1) {
					$city          = trim($addressParts[0]);
					$stateZip      = trim($addressParts[1]);
					$stateZipParts = explode(' ', $stateZip);
					$state         = trim($stateZipParts[0]);
					$zip           = trim($stateZipParts[1]);
				} else {
					$regExp = "/^([^,]+)\s([A-Z]{2})(?:\s(\d{5}))?$/";
					preg_match($regExp, $patron->address2, $matches);
					if ($matches) {
						$city  = $matches[1];
						$state = $matches[2];
						$zip   = $matches[3];
					}
				}

				$patron->city  = $city;
				$patron->state = $state;
				$patron->zip   = $zip;
			} else {
				$patron->city  = '';
				$patron->state = '';
				$patron->zip   = '';
			}
		}
		// mobile phone
		if(isset($mobilePhone)) {
			$patron->mobileNumber = $mobilePhone;
		} else {
			$patron->mobileNumber = '';
		}
		// account expiration
		try {
			$expiresDate = new DateTime($pInfo->expirationDate);
			$patron->expires = $expiresDate->format('m-d-Y');
			$nowDate     = new DateTime('now');
			$dateDiff    = $nowDate->diff($expiresDate);
			if($dateDiff->days <= 30) {
				$patron->expireClose = 1;
			} else {
				$patron->expireClose = 0;
			}
			if($dateDiff->days <= 0) {
				$patron->expired = 1;
			} else {
				$patron->expired = 0;
			}
		} catch (\Exception $e) {
			$patron->expires = '00-00-0000';
		}
		// notices
		$patron->notices = $pInfo->fixedFields->{'268'}->value;
		switch($pInfo->fixedFields->{'268'}->value) {
			case '-':
				$patron->noticePreferenceLabel = 'none';
				break;
			case 'a':
				$patron->noticePreferenceLabel = 'mail';
				break;
			case 'p':
				$patron->noticePreferenceLabel = 'phone';
				break;
			case 'z':
				$patron->noticePreferenceLabel = 'email';
				break;
			default:
				$patron->noticePreferenceLabel = 'none';
		}
		// number of checkouts from ils
		$patron->numCheckedOutIls = $pInfo->fixedFields->{'50'}->value;
		// fines
		$patron->fines = number_format($pInfo->moneyOwed, 2, '.', '');
		$patron->finesVal = number_format($pInfo->moneyOwed, 2, '.', '');
		// holds
		$holds = $this->getMyHolds($patron);
		if($holds && isset($holds['available'])){
			$patron->numHoldsAvailableIls = count($holds['available']);
			$patron->numHoldsRequestedIls = count($holds['unavailable']);
			$patron->numHoldsIls = $patron->numHoldsAvailableIls + $patron->numHoldsRequestedIls;
		}

		if($createPatron) {
			$patron->created = date('Y-m-d');
			if($patron->insert() === false) {
				$this->logger->error('Could not save patron to Pika database.', ['barcode'=>$this->patronBarcode,
				                                                                 'error'=>$patron->_lastError->userinfo,
				                                                                 'backtrace'=>$patron->_lastError->backtrace]);
				throw new ErrorException('Error saving patron to Pika database');

			} else {
				$this->logger->info('Saved patron to Pika database.', ['barcode'=>$this->patronBarcode]);
			}
		} elseif ($updatePatron && !$createPatron) {
			$patron->update();
		}
		$this->logger->info("Saving patron to memcache:".$patronObjectCacheKey);
		$this->memCache->set($patronObjectCacheKey, $patron, $this->configArray['Caching']['user']);
		return $patron;
	}

	/**
	 * Get the unique Sierra patron ID by searching for barcode.
	 *
	 * @param  User|string $patronOrBarcode  Either a barcode as a string or a User object.
	 * @return int|false   returns the patron ID or false
	 */
	public function getPatronId($patronOrBarcode)
	{
		// if a patron object was passed
		if(is_object($patronOrBarcode)) {
			$barcode = $patronOrBarcode->barcode;
			$barcode = trim($barcode);
		} elseif (is_string($patronOrBarcode)) {
			$barcode = $patronOrBarcode;
		}

		$patronIdCacheKey = "patron_".$barcode."_sierraid";
		if($patronId = $this->memCache->get($patronIdCacheKey)) {
			return $patronId;
		}

		$params = [
			'varFieldTag'     => 'b',
			'varFieldContent' => $barcode,
			'fields'          => 'id',
		];
		// make the request
		$r = $this->_doRequest('patrons/find', $params);
		// there was an error with the last call -- use $this->apiLastError for messages.
		if(!$r) {
			$this->logger->warn('Could not get patron ID.', ['barcode'=>$patron->barcode, 'error'=>$this->apiLastError]);
			return false;
		}

		$this->memCache->set($patronIdCacheKey, $r->id, $this->configArray['Caching']['koha_patron_id']);

		return $r->id;
	}


	/**
	 * Update a patrons profile information
	 *
	 *
	 * PUT patrons/{id}
	 * @param  User  $patron
	 * @param  bool  $canUpdateContactInfo
	 * @return array Array of errors or empty array on success
	 */
	public function updatePatronInfo($patron, $canUpdateContactInfo){
		if(!$canUpdateContactInfo) {
			return ['You can not update your information.'];
		}

		$patronId = $this->getPatronId($patron);
		if(!$patronId) {
			return ['An error occurred. Please try again later.'];
		}

		// store city, state, zip in address2 so we can put them together.
		$cityStZip = [];
		$phones    = [];
		$emails    = [];
		$errors    = [];
		foreach($_POST as $key=>$val) {
			switch($key) {
				case 'address1':
					if(empty($val)) {
						$errors[] = "Street address is required.";
					} else {
						$address1 = $val;
					}
					break;
				case 'city':
				case 'state':
				case 'zip':
					if(empty($val)) {
						$errors[] = "City, state and ZIP are required.";
					} else {
						$cityStZip[$key] = $val;
					}
					break;
				case 'phone': // primary phone
					// todo: does this need to set an empty object-- if someone wanted to remove their phone #?
					if(!empty($val)){
						$phones[] = (object)['number'=>$val, 'type'=>'t'];
					}
					break;
				case 'workPhone': // alt phone
					if(!empty($val)){
						$phones[] = (object)['number'=>$val, 'type'=>'p'];
					}
					break;
				case 'mobileNumber': // mobile phone -- this triggers sms opt in for sierra
					if(!empty($val)){
						$phones[] = (object)['number'=>$val, 'type'=>'o'];
					}
					break;
				case 'email':
					if(!empty($val)) {
						$emails[] = $val;
					}
					break;
				case 'pickupLocation':
					$homeLibraryCode = $val;
					break;
				case 'notices':
					if(!empty($val)) {
						$notices = $val;
					} else {
						$notices = '-';
					}
					break;
				case 'alternate_username':
					$altUsername = $val;
					break;
			}
		}

		if(!empty($errors)) {
			return $errors;
		}

		// fix up city state zip
		$address2 = $cityStZip['city'] . ', ' . $cityStZip['state'] . ' ' . $cityStZip['zip'];

		$params = [
			'emails'          => $emails,
			'addresses'       => [ (object)['lines' => [$address1, $address2], "type" => 'a'] ],
			'phones'          => $phones,
			'homeLibraryCode' => $homeLibraryCode,
			'fixedFields'     => (object)['268'=>(object)["label" => "Notice Preference", "value" => $notices]]
		];

		// username if present
		if (isset($altUsername)) {
			$params['varFields'] = [(object)['fieldTag'=>'i', 'content'=>$altUsername]];
		}


		$operation = 'patrons/'.$patronId;
		$r = $this->_doRequest($operation, $params, 'PUT');

		if(!$r){
			$this->logger->warn("Unable to update patron", ["message"=>$this->apiLastError]);
			$errors[] = "An error occurred. Please try in again later.";
		}

		// remove patron object from cache
		$this->memCache->delete('patron_'.$patron->barcode.'_patron');

		return $errors;
	}


	/**
	 * Update a users PIN
	 *
	 * PUT patrons/{id}
	 *
	 * @param User   $patron
	 * @param string $oldPin
	 * @param string $newPin
	 * @param string $confirmNewPin
	 * @return string Error or success message.
	 */
	public function updatePin($patron, $oldPin, $newPin, $confirmNewPin){
		$patronId = $this->_authBarcodePin($patron->barcode, $oldPin);

		if(!$patronId) {
			return "Your current PIN is incorrect. Please try again.";
		}

		if(!($newPin == $confirmNewPin)) {
			return "PIN and PIN confirmation do not match. Please try again.";
		}

		$operation = 'patrons/'.$patronId;
		$params    = ['pin' => $newPin];

		$r = $this->_doRequest($operation, $params, 'PUT');

		if(!$r) {
			$message = $this->_getPrettyError();
			return 'Could not update PIN: '. $message;
		}
		$patron->cat_password = $newPin;
		$patron->update();

		$this->memCache->delete('patron_'.$patron->barcode.'_patron');

		return 'Your PIN has been updated';
	}

	public function resetPin($patron, $newPin, $resetToken = null){
		// TODO: Implement resetPin() method.
	}

	/**
	 * Send a Self Registration request to the ILS.
	 *
	 * PUT patrons
	 * @return  array  [success = bool, barcode = null or barcode]
	 */
	public function selfRegister(){

		$params = [];

		global $library;
		// get library code
		$location            = new Location();
		$location->libraryId = $library->libraryId;
		$location->find(true);
		if(!$location) {
			return ['success'=>false, 'barcode'=>''];
		}
		$params['homeLibraryCode'] = $location->code;
		// default patron type
		$params['patronType']      = (int)$library->defaultPType;
		// generate a random 8 digit number to serve as temp barcode
		$barcode = (string)rand(10000000, 99999999);
		$params['barcodes'][] = $barcode;

		foreach ($_POST as $key=>$val) {
			switch ($key) {
				case 'email':
					$val = trim($val);
					$params['emails'][] = $val;
					break;
				case 'address': // street part of address
					$val = trim($val);
					$params['addresses'][0]['lines'][0] = $val;
					$params['addresses'][0]['type'] = 'a';
					break;
				case 'primaryphone':
					$val = trim($val);
					$params['phones'][] = ['number'=>$val, 'type'=>'t'];
					break;
				case 'altphone':
					$val = trim($val);
					$params['phones'][] = ['number'=>$val, 'type'=>'p'];
					break;
				case 'birthdate':
					$date = DateTime::createFromFormat('d-m-Y', $val);
					$params['birthDate'] = $date->format('Y-m-d');
					break;
			}
		}

		// names -- standard is Last, First Middle
		$name  = trim($_POST['lasttname']) . ", ";
		$name .= trim($_POST['firstname']);
		if(!empty($_POST['middlename'])) {
			$name .= ' '.trim($_POST['middlename']);
		}

		$params['names'][] = $name;

		// city state and zip
		$cityStateZip = trim($_POST['city']).', '.trim($_POST['state']).' '.trim($_POST['zip']);
		// address line 2
		$params['addresses'][0]['lines'][1] = $cityStateZip;

		// if library uses pins
		if($this->accountProfile->loginConfiguration == "barcode_pin") {
			$pin = trim($_POST['pin']);
			$pinConfirm = trim($_POST['pinconfirm']);

			if(!($pin == $pinConfirm)) {
				return ['success'=>false, 'barcode'=>''];
			} else {
				$params['pin'] = $pin;
			}
		}

		$this->logger->debug('Self registering patron', ['params'=>$params]);
		$operation = "patrons/";
		$r = $this->_doRequest($operation, $params, "POST");

		if(!$r) {
			$this->logger->warning('Failed to self register patron');
			return ['success'=>false, 'barcode'=>''];
		}
		$this->logger->debug('Success self registering patron');
		return ['success' => true, 'barcode' => $barcode];
	}


	/**
	 * If library uses username field
	 *
	 * @return bool
	 */
	public function hasUsernameField(){
		if(isset($this->configArray['OPAC']['allowUsername'])) {
			return (bool)$this->configArray['OPAC']['allowUsername'];
		} else {
			return false;
		}
	}
	/**
	 * Used to build the form for self registration.
	 * Fields will be displayed in order of indexed array
	 *
	 * @return array Self registration fields
	 */
	public function getSelfRegistrationFields(){
		$fields = [];

		global $library;
		$fields[] = ['property'   => 'firstname',
		             'type'       => 'text',
		             'label'      => 'First name',
		             'description'=> 'Your first name',
		             'maxLength'  => 30,
		             'required'   => true];

		$fields[] = ['property'   => 'middlename',
		             'type'       => 'text',
		             'label'      => 'Middle name',
		             'description'=> 'Your middle name or initial',
		             'maxLength'  => 30,
		             'required'   => false

		];
		$fields[] = ['property'   => 'lastname',
		             'type'       => 'text',
		             'label'      => 'Last name',
		             'description'=> 'Your last name (surname)',
		             'maxLength'  => 30,
		             'required'   => true];
		// allow usernames?
		if($this->hasUsernameField()) {
			$fields[] = ['property'   => 'username',
			             'type'       => 'text',
			             'label'      => 'Username',
			             'description'=> 'Set an optional username.',
			             'maxLength'  => 20,
			             'required'   => false];
		}
		// if library would like a birthdate
		if ($library && $library->promptForBirthDateInSelfReg){
			$fields[] = ['property'   => 'birthdate',
			             'type'       => 'date',
			             'label'      => 'Date of Birth (MM-DD-YYYY)',
			             'description'=> 'Date of birth',
			             'maxLength'  => 10,
			             'required'   => true];
		}

		$fields[] = ['property'   => 'address',
		             'type'       => 'text',
		             'label'      => 'Address',
		             'description'=> 'Street address or PO Box where you receive mail.',
		             'maxLength'  => 40,
		             'required'   => true];

		$fields[] = ['property'   => 'city',
		             'type'       => 'text',
		             'label'      => 'City',
		             'description'=> 'The city you receive mail in.',
		             'maxLength'  => 20,
		             'required'   => true];

		$fields[] = ['property'   => 'state',
		             'type'       => 'text',
		             'label'      => 'State',
		             'description'=> 'The state you receive mail in.',
		             'maxLength'  => 20,
		             'required'   => true];

		$fields[] = ['property'   => 'zip',
		             'type'       => 'text',
		             'label'      => 'ZIP code',
		             'description'=> 'The ZIP code for your mail.',
		             'maxLength'  => 16,
		             'required'   => true];

		$fields[] = ['property'   => 'email',
		             'type'       => 'email',
		             'label'      => 'Email',
		             'description'=> 'Your email address',
		             'maxLength'  => 50,
		             'required'   => false];

		$fields[] = ['property'   => 'primaryphone',
		             'type'       => 'text',
		             'label'      => 'Primary phone (XXX-XXX-XXXX)',
		             'description'=> 'Your primary phone number.',
		             'maxLength'  => 20,
		             'required'   => false];

		// if library wants an alt phone
		if ($library && $library->showWorkPhoneInProfile) {
			$fields[] = [
				'property'    => 'altphone',
				'type'        => 'text',
				'label'       => 'Work phone (XXX-XXX-XXXX)',
				'description' => 'Alternate phone number.',
				'maxLength'   => 20,
				'required'    => false
			];
		}

		// if library uses pins
		if($this->accountProfile->loginConfiguration == "barcode_pin") {
			$fields[] = [
				'property'    => 'pin',
				'type'        => 'pin',
				'label'       => 'PIN',
				'description' => 'Please set a PIN (personal identification number).',
				'maxLength'   => 10,
				'required'    => true
			];

			$fields[] = [
				'property'    => 'pinconfirm',
				'type'        => 'pin',
				'label'       => 'Confirm PIN',
				'description' => 'Please reenter your PIN.',
				'maxLength'   => 10,
				'required'    => true
			];
		}

		return $fields;
	}

	/**
	 * Get fines for a patron
	 * GET patrons/{uid}/fines
	 * Returns array of fines
	 * array([amount,amountOutstanding,date,reason,message])
	 *
	 * @param $patron User
	 * @return array|bool
	 */
	public function getMyFines($patron){
		// find the sierra patron id
		$patronId = $this->getPatronId($patron);
		if(!$patronId) {
			return false;
		}
		// check memCache
		$patronFinesCacheKey = 'patron_'.$patron->barcode.'_fines';
		if($this->memCache->get($patronFinesCacheKey)) {
			return $this->memCache->get($patronFinesCacheKey);
		}
		// make the call
		$params = [
			'fields' => 'default,assessedDate,itemCharge,chargeType,paidAmount,datePaid,description,returnDate,location,description'
		];
		$operation = 'patrons/'.$patronId.'/fines';
		$fInfo = $this->_doRequest($operation, $params);
		if(!$fInfo) {
			return false;
		}
		// no fines. good person.
		if($fInfo->total == 0) {
			return [];
		}

		$fines = $fInfo->entries;
		$r = [];
		foreach($fines as $fine) {
			// get the bib ids if item is present
			if (isset($fine->item)){
				preg_match($this->urlIdRegExp, $fine->item, $m);
				$itemId    = $m[1];
				$operation = "items/" . $itemId;
				$params    = ["fields" => "bibIds"];
				$resp      = $this->_doRequest($operation, $params);
				$title = false;
				if (isset($resp->bibIds[0])) {
					$id = $resp->bibIds[0];
					// for Pika we need the check digit.
					$recordXD = $this->getCheckDigit($id);
					// get more info from record
					$bibId        = 'b' . $id . $recordXD;
					$recordDriver = new MarcRecord($this->accountProfile->recordSource . ":" . $bibId);
					if ($recordDriver->isValid()) {
						$title = $recordDriver->getTitle();
					} else {
						$title = 'Unknown Title';
					}
				}
				if(!$title) {
					$title = 'Unknown title';
				}
				$details = [[
					"label" => "Returned: ",
					"value" => date('m-d-Y', strtotime($fine->returnDate))
				]];
			// if it's not an item charge look for a description
			} elseif (isset($fine->description)) {
				$title = 'Description: '.$fine->description;
				$details = false;
			} else {
				$title = 'Unknown';
				$details = false;
			}
			$amount = number_format($fine->itemCharge, 2);
			$date   = date('m-d-Y', strtotime($fine->assessedDate));


			$r[]    = [
				'title'  => $title,
				'date'   => $date,
				'reason' => $fine->chargeType->display,
				'amount' => $amount,
				'amountOutstanding' => $amount,
				'message' => $title,
				'details' => $details
			];
		}
		$this->memCache->set($patronFinesCacheKey, $r, $this->configArray['Caching']['user']);
		return $r;
	}

	/**
	 * Get the holds for a patron
	 *
	 * GET patrons/$patronId/holds
	 *
	 * @param  User $patron
	 * @return array|bool
	 */
	public function getMyHolds($patron, $linkedAccount = false) {

		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		if ($patronHolds = $this->memCache->get($patronHoldsCacheKey)) {
			$this->logger->info("Found holds in memcache:" . $patronHoldsCacheKey);
			return $patronHolds;
		}

		if(!$patronId = $this->getPatronId($patron)) {
			return false;
		}

		$operation = "patrons/".$patronId."/holds";
		$params=["fields"=>"default,pickupByDate,frozen"];
		$holds = $this->_doRequest($operation, $params);

		if(!$holds) {
			return false;
		}

		if($holds->total == 0) {
			return [];
		}
		// these will be consistent for every hold
		$displayName  = $patron->getNameAndLibraryLabel();
		$pikaPatronId = $patron->id;
		// can we change pickup location?
		$pickupLocations = $patron->getValidPickupBranches('ils');
		if(is_array($pickupLocations)) {
			if (count($pickupLocations) > 1) {
				$canUpdatePL = true;
			} else {
				$canUpdatePL = false;
			}
		} else {
			$canUpdatePL = false;
		}
		//
		$availableHolds   = [];
		$unavailableHolds = [];
		foreach ($holds->entries as $hold) {
			// standard stuff
			$h['holdSource']      = 'ILS';
			$h['userId']          = $pikaPatronId;
			$h['user']            = $displayName;

			// get what's available from this call
			$h['position']              = isset($hold->priority) ? $hold->priority : 1;
			$h['frozen']                = $hold->frozen;
			$h['create']                = strtotime($hold->placed); // date hold created
			// innreach holds don't include notNeededAfterDate
			$h['automaticCancellation'] = isset($hold->notNeededAfterDate) ? strtotime($hold->notNeededAfterDate) : null; // not needed after date
			$h['expire']                = isset($hold->pickupByDate) ? strtotime($hold->pickupByDate) : false; // pick up by date
			// cancel id
			preg_match($this->urlIdRegExp, $hold->id, $m);
			$h['cancelId'] = $m[1];

			// status, cancelable, freezable
			switch ($hold->status->code) {
				case '0':
					if($hold->frozen) {
						$status = "Frozen";
					} else {
						$status = 'On hold';
					}
					$cancelable = true;
					$freezeable = true;
					if($canUpdatePL) {
						$updatePickup = true;
					} else {
						$updatePickup = false;
					}
					break;
				case 'b':
				case 'j':
				case 'i':
					$status       = 'Ready';
					$cancelable   = true;
					$freezeable   = false;
					$updatePickup = false;
					break;
				case 't':
					$status     = 'In transit';
					$cancelable = true;
					$freezeable = false;
					if($canUpdatePL) {
						$updatePickup = true;
					} else {
						$updatePickup = false;
					}
					break;
				case "&":
					$status       = "Requested from INN-Reach";
					$cancelable   = true;
					$freezeable   = false;
					$updatePickup = false;
					break;
				default:
					$status       = 'Unknown';
					$cancelable   = false;
					$freezeable   = false;
					$updatePickup = false;
			}
			// for sierra, holds can't be frozen if patron is next in line
			if(isset($hold->priorityQueueLength)) {
				if((int)$hold->priority <= 2 && (int)$hold->priorityQueueLength >= 2) {
					$freezeable = false;
				// if the patron is the only person on wait list hold can't be frozen
				} elseif($hold->priority == 1 && (int)$hold->priorityQueueLength == 1) {
					$freezeable = false;
				}
			}
			$h['status']    = $status;
			$h['freezeable']= $freezeable;
			$h['cancelable']= $cancelable;
			$h['locationUpdateable'] = $updatePickup;
			// unset for next round.
			unset($status, $freezeable, $cancelable, $updatePickup);

			// pick up location
			$pickupBranch = new Location();
			$where = "code = '{$hold->pickupLocation->code}'";
			$pickupBranch->whereAdd($where);
			$pickupBranch->find(1);
			if ($pickupBranch->N > 0){
				$pickupBranch->fetch();
				$h['currentPickupId']   = $pickupBranch->locationId;
				$h['currentPickupName'] = $pickupBranch->displayName;
				$h['location']          = $pickupBranch->displayName;
			} else {
				$h['currentPickupId']   = false;
				$h['currentPickupName'] = $hold->pickupLocation->name;
				$h['location']          = $hold->pickupLocation->name;
			}

			// determine if this is an innreach hold
			// or if it's a regular ILS hold
			if(strstr($hold->record, "@")) {
				///////////////
				// INNREACH HOLD
				///////////////
				// get the inn-reach item id
				$regExp = '/.*\/(.*)$/';
				preg_match($regExp, $hold->record, $itemId);
				$itemParams    = ['fields'=>'status'];
				$itemOperation = 'items/'.$itemId[1];
				$itemRes = $this->_doRequest($itemOperation,$itemParams);
				if($itemRes) {
					if($itemRes->status->code != '&') {
						$h['cancelable']         = false;
					}
					if($itemRes->status->code == '#') {
						$hold->status->code = 'i';
						$h['status']             = 'Ready';
						$h['freezeable']         = false;
						$h['cancelable']         = false;
						$h['locationUpdateable'] = false;
					}
				}
				// get the hold id
				preg_match($this->urlIdRegExp, $hold->id, $mIr);
				$innReachHoldId = $mIr[1];
				$innReach = new InnReach();
				$titleAndAuthor = $innReach->getHoldTitleAuthor($innReachHoldId);
				if(!$titleAndAuthor) {
					$h['title']     = 'Unknown';
					$h['author']    = 'Unknown';
					$h['sortTitle'] = '';
				} else {
					$h['title']     = $titleAndAuthor['title'];
					$h['author']    = $titleAndAuthor['author'];
					$h['sortTitle'] = $titleAndAuthor['sort_title'];
				}
				$h['freezeable']         = false;
				$h['locationUpdateable'] = false;

				// grab the theme for Inn reach cover
				$themeParts = explode(',', $this->configArray['Site']['theme']);
				$theme = $themeParts[0];
				$h['coverUrl'] = '/interface/themes/' . $theme . '/images/InnReachCover.png';
			} else {
				///////////////
				// ILS HOLD
				//////////////
				// record type and record id
				$recordType = $hold->recordType;
				preg_match($this->urlIdRegExp, $hold->record,$m);
				// for item level holds we need to grab the bib id.
				$id = $m[1];
				if($recordType == 'i') {
					$id = $this->_getBibIdFromItemId($id);
				}
				// for Pika we need the check digit.
				$recordXD  = $this->getCheckDigit($id);

				// get more info from record
				$bibId = '.b'.$id.$recordXD;
				$recordSourceAndId = new \SourceAndId($this->accountProfile->recordSource . ":" . $bibId);
				$record = RecordDriverFactory::initRecordDriverById($recordSourceAndId);
				if ($record->isValid()){
					$h['id']              = $record->getUniqueID();
					$h['shortId']         = $record->getShortId();
					$h['title']           = $record->getTitle();
					$h['sortTitle']       = $record->getSortableTitle();
					$h['author']          = $record->getAuthor();
					$h['format']          = $record->getFormat();
					$h['link']            = $record->getRecordUrl();
					$h['coverUrl']        = $record->getBookcoverUrl('medium');
				};
			}
			if($hold->status->code == "b" || $hold->status->code == "j" || $hold->status->code == "i") {
				$availableHolds[] = $h;
			} else {
				$unavailableHolds[] = $h;
			}
			// unset for next loop
			unset($h);
		} // end foreach

		$return['available']   = $availableHolds;
		$return['unavailable'] = $unavailableHolds;
		// for linked accounts we might run into problems
		unset($availableHolds, $unavailableHolds);

		if(!$linkedAccount){
			$this->memCache->set($patronHoldsCacheKey, $return, $this->configArray['Caching']['user']);
			$this->logger->info("Saving holds in memcache:".$patronHoldsCacheKey);
		}

		return $return;
	}

	/**
	 * Place hold
	 *
	 * POST patrons/{patronId}}/holds/requests
	 *
	 * @param User        $patron
	 * @param string      $recordId
	 * @param string      $pickupBranch
	 * @param string|null $cancelDate
	 * @return array|false
	 */
	public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null) {
		if($cancelDate) {
			$d        = DateTime::createFromFormat('m/d/Y', $cancelDate); // convert needed by date
			$neededBy = $d->format('Y-m-d');
		} else {
			$neededBy = false;
		}
		$recordType     = substr($recordId, 1,1); // determine the hold type b = bib, i = item, j = volume
		$recordNumber   = substr($recordId, 2, -1); // remove the .x and the last check digit
		$pickupLocation = $pickupBranch;
		$patronId       = $this->getPatronId($patron);

		// delete memcache holds
		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";

		if($this->memCache->delete($patronHoldsCacheKey)) {
			$this->logger->info("Removed holds from memcache: ".$patronHoldsCacheKey);
		} else {
			$this->logger->warn("Failed to remove holds from memcache: ".$patronHoldsCacheKey);
		}

		// because the patron object has holds information we need to clear that cache too.
		$patronObjectCacheKey = 'patron_'.$patron->barcode.'_patron';

		if($this->memCache->delete($patronObjectCacheKey)) {
			$this->logger->info("Removed patron from memcache: ".$patronObjectCacheKey);
		} else {
			$this->logger->warn("Failed to remove patron from memcache: ".$patronObjectCacheKey);
		}

		// get title of record
		$record = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);
		$recordTitle  = $record->isValid() ? $record->getTitle() : null;

		$params = [
			'recordType'     => $recordType,
			'recordNumber'   => (int)$recordNumber,
			'pickupLocation' => $pickupLocation,
		];
		if($neededBy) {
			$params['neededBy'] = $neededBy;
		}

		$operation = "patrons/{$patronId}/holds/requests";

		$r = $this->_doRequest($operation, $params, "POST");

		// check if error we need to do an item level hold
		if($this->apiLastError && stristr($this->apiLastError,"Volume record selection is required to proceed")) {
			$items = $this->getItemVolumes($recordId);
			$return = [
				'message' => 'This title requires item level holds, please select an item to place a hold on.',
				'success' => 'true',
				'canceldate' => $neededBy,
				'items'   => $items
			];
			return $return;
		}

		// oops! something went wrong.
		if(!$r) {
			$return['success'] = false;
			if ($this->apiLastError) {
				$message = $this->_getPrettyError();
				if($message) {
					$return['message'] = $message;
				} else {
					$return['message'] = 'Unable to place your hold. Please contact our library.';
				}
				return $return;
			}

		}
		// success! weeee :)
		$return['success'] = true;
		if($recordTitle) {
			$recordTitle = trim($recordTitle, ' /');
			$return['message'] = "Your hold for <strong>{$recordTitle}</strong> was successfully placed.";
		} else {
			$return['message'] = "Your hold was successfully placed.";
		}

		return $return;
	}


	public function placeItemHold($patron, $recordId, $itemId, $pickupBranch){
		return $this->placeHold($patron, $itemId, $pickupBranch);
	}

	public function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch){
		return[];
		$recordId = $recordId;
	}

	public function changeHoldPickupLocation($patron, $bibId, $holdId, $newPickupLocation){
		$operation = "patrons/holds/".$holdId;
		$params = ["pickupLocation"=>$newPickupLocation];

		// delete holds cache
		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		$this->memCache->delete($patronHoldsCacheKey);

		$r = $this->_doRequest($operation,$params, "PUT");

		// something went wrong
		if(!$r) {
			$return = ['success' => false];
			if($this->apiLastError) {
				$message = $this->_getPrettyError();
				$return['message'] = $message;
			} else {
				$return['message'] = "Unable to change pickup location. Please contact your library for further assistance.";
			}
			return $return;
		}

		$return = [
			'success' => true,
			'message' => 'Pickup location updated.'];

		return $return;
	}

	/**
	 * DELETE patrons/holds/{holdId}
	 * @param $patron
	 * @param $bibId
	 * @param $holdId
	 * @return array
	 */
	public function cancelHold($patron, $bibId, $holdId){
		$operation = "patrons/holds/".$holdId;

		// delete holds cache
		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		if($this->memCache->delete($patronHoldsCacheKey)) {
			$this->logger->info("Removed patron from memcache: ".$patronHoldsCacheKey);
		} else {
			$this->logger->warn("Failed to remove from memcache: ".$patronHoldsCacheKey);
		}

		// because the patron object has holds information we need to clear that cache too.
		$patronObjectCacheKey = 'patron_'.$patron->barcode.'_patron';
		if($this->memCache->delete($patronObjectCacheKey)) {
			$this->logger->info("Removed patron from memcache: ".$patronObjectCacheKey);
		} else {
			$this->logger->warn("Failed to remove patron from memcache: ".$patronObjectCacheKey);
		}

		$r = $this->_doRequest($operation, [], "DELETE");

		// something went wrong
		if(!$r) {
			$return = ['success' => false];
			if($this->apiLastError) {
				$message = $this->_getPrettyError();
				$return['message'] = $message;
			} else {
				$return['message'] = "Unable to cancel your hold. Please contact your library for further assistance.";
			}
			return $return;
		}

		$return = [
			'success' => true,
			'message' => 'Your hold has been canceled.'];

		return $return;
	}

	/**
	 * PUT patrons/holds/{holdId}
	 *
	 * @param  User   $patron
	 * @param  string $bibId
	 * @param  string $holdId
	 * @param  null   $dateToReactivate
	 * @return array An array with success and message
	 */
	public function freezeHold($patron, $bibId, $holdId, $dateToReactivate = null){
		$operation = "patrons/holds/".$holdId;
		$params = ["freeze"=>true];

		// delete holds cache
		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		$this->logger->info("Removing holds from memcache:".$patronHoldsCacheKey);
		$this->memCache->delete($patronHoldsCacheKey);

		$r = $this->_doRequest($operation,$params, "PUT");

		// something went wrong
		if(!$r) {
			$return = ['success' => false];
				if($this->apiLastError) {
					$message = $this->_getPrettyError();
					$return['message'] = $message;
				} else {
					$return['message'] = "Unable to freeze your hold. Please contact your library for further assistance.";
				}
			return $return;
		}

		$return = [
			'success' => true,
			'message' => 'Your hold has been frozen.'
		];

		return $return;
	}

	/**
	 * Thaw a frozen hold
	 * PUT patrons/holds/{holdId}
	 * @param  User   $patron
	 * @param  string $bibId
	 * @param  string $holdId
	 * @return array
	 */
	public function thawHold($patron, $bibId, $holdId){
		$operation = "patrons/holds/".$holdId;
		$params = ["freeze"=>false];

		// delete holds cache
		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		$this->logger->info("Removing holds from memcache:".$patronHoldsCacheKey);
		$this->memCache->delete($patronHoldsCacheKey);

		$r = $this->_doRequest($operation,$params, "PUT");

		// something went wrong
		if(!$r) {
			$return = ['success' => false];
			if($this->apiLastError) {
				$message = $this->_getPrettyError();
				$return['message'] = $message;
			} else {
				$return['message'] = "Unable to thaw your hold. Please contact your library for further assistance.";
			}
			return $return;
		}

		$return = [
			'success' => true,
			'message' => 'Your hold has been thawed.'];

		return $return;
	}

	public function hasNativeReadingHistory(){
		return true;
	}

	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut")
	{
			// history enabled?
		if($patron->trackReadingHistory != 1) {
			return ['historyActive' => false, 'numTitles' => 0, 'titles' => []];
		}

		$history = $this->loadReadingHistoryFromIls($patron);

		if(!$history) {
			return false;
		}
		$history['historyActive'] = true;
		// search test
		//$search =  $this->searchReadingHistory($patron, 'colorado');
		//$history['titles'] = $search;
		//return $history;

		$historyPages = array_chunk($history['titles'], $recordsPerPage);
		$pageIndex = $page - 1;
		$history['titles'] = $historyPages[$pageIndex];

		return $history;
	}

	public function searchReadingHistory($patron, $search) {
		$history = $this->loadReadingHistoryFromIls($patron);

		$found = array_filter($history['titles'], function($k) use ($search) {
      return stristr($k['title'], $search) || stristr($k['author'], $search);
		});

		return $found;
	}

	/**
	 * Get patron's reading history
	 *
	 * GET patrons/{patron_id}/checkouts/history
	 * This method is meant to be used by the Pika cron process load patron's reading history.
	 *
	 * @param  User        $patron          Patron Object
	 * @param  null|int    $loadAdditional  ????????
	 * @return array|false [titles]=>[borrower_num(Pika ID), recordId(bib ID), permanentId(grouped work ID), title, author, checkout]
	 */
	public function loadReadingHistoryFromIls($patron, $loadAdditional = null){
		$patronId = $this->getPatronId($patron->barcode);

		$patronReadingHistoryCacheKey = "patron_".$patron->barcode."_history";
		if($patronReadingHistory = $this->memCache->get($patronReadingHistoryCacheKey)) {
			$this->logger->info("Found reading history in memcache:".$patronReadingHistoryCacheKey);
			return $patronReadingHistory;
		}
		$operation = "patrons/".$patronId."/checkouts/history";
		$params = ['limit'     => 2000, // Sierra api max results as of 9-12-2019
		           'sortField' => 'outDate',
		           'sortOrder' => 'desc'];
		$history = $this->_doRequest($operation, $params);

		if(!$history) {
			return false;
		}

		if($history->total == 0) {
			return [];
		}
		$patronPikaId = $patronId;
		$readingHistory = [];
		foreach($history->entries as $historyEntry) {
			$titleEntry = [];
			// make the Pika style bib Id
			preg_match($this->urlIdRegExp, $historyEntry->bib, $bibMatch);
			$x = $this->getCheckDigit($bibMatch[1]);
			$bibId = '.b'.$bibMatch[1].$x; // full bib id
			// get the checkout id --> becomes itemindex
			preg_match($this->urlIdRegExp, $historyEntry->id, $coIdMatch);
			$itemindex = $coIdMatch[1];
			// format the date
			$ts = strtotime($historyEntry->outDate);
			$checkOutDate = date('m-d-Y', $ts);
			// get the rest from the MARC record
			$record = new MarcRecord($this->accountProfile->recordSource.':'.$bibId);

			if ($record->isValid()) {
				$titleEntry['permanentId'] = $record->getPermanentId();
				$titleEntry['title']       = $record->getTitle();
				$titleEntry['author']      = $record->getAuthor();
				$titleEntry['format']      = $record->getFormat();
				$titleEntry['title_sort']  = $record->getSortableTitle();
				$titleEntry['ratingData']  = $record->getRatingData();
				$titleEntry['permanentId'] = $record->getPermanentId();
				$titleEntry['linkUrl']     = $record->getGroupedWorkDriver()->getLinkUrl();
				$titleEntry['coverUrl']    = $record->getBookcoverUrl('medium');
				$titleEntry['format']      = $record->getFormats();

			} else {
				$titleEntry['permanentId'] = '';
				$titleEntry['ratingData']  = '';
				$titleEntry['permanentId'] = '';
				$titleEntry['linkUrl']     = '';
				$titleEntry['coverUrl']    = '';
				$titleEntry['format']      = '';
				// todo: should fall back to api here?
				$titleEntry['title']       = '';
				$titleEntry['author']      = '';
				$titleEntry['format']      = '';
				$titleEntry['title_sort']  = '';
			}
			$titleEntry['checkout']     = $checkOutDate;
			$titleEntry['shortId']      = $bibMatch[1];
			$titleEntry['borrower_num'] = $patronPikaId;
			$titleEntry['recordId']     = $bibId;
			$titleEntry['itemindex']    = $itemindex; // checkout id
			$titleEntry['details']      = ''; // todo: nothing to put here?
			$readingHistory[] = $titleEntry;
		}
		$total = count($readingHistory);
		$return = ['numTitles'  => $total,
		           'titles'     => $readingHistory];

		$this->memCache->set($patronReadingHistoryCacheKey, $return, 21600);
		$this->logger->info("Saving reading history in memcache:".$patronReadingHistoryCacheKey);

		return $return;
	}


	public function optInReadingHistory($patron){
		// TODO: Implement optInReadingHistory() method.
	}

	public function optOutReadingHistory($patron){

		$patron->trackReadingHistory = false;
		$patron->update();
	}

	public function deleteAllReadingHistory($patron){
		// TODO: Implement deleteAllReadingHistory() method.
	}

	/**
	 * _authNameBarcode
	 *
	 * Find a patron by barcode (field b) and match username against API response from patron/find API call that returns
	 * Sierra patron id.
	 *
	 * @param  $username  string   login name
	 * @param  $barcode   string   barcode
	 * @return string|false Returns unique patron id from Sierra on success or false on fail.
	 */
	private function _authNameBarcode($username, $barcode) {
		// tidy up barcode
		$barcode = trim($barcode);
		// build get params
		// varFieldTag=b: b is for barcode. :) This will find the barcode if it exists and return an array of names
		$params = [
			'varFieldTag'     => 'b',
			'varFieldContent' => $barcode,
			'fields'          => 'names',
		];
		// make the request
		$r = $this->_doRequest('patrons/find', $params);
		// there was an error with the last call
		if($this->apiLastError) {
			return false;
		}
		// barcode found
		// check the username agains name(s) returned from sierra
		$patronNames = $r->names;
		$username = trim($username);
		// break the username into an array
		$usernameParts = explode(' ', $username);
		$valid = FALSE;
		// check each part of the username for a match against $j->name
		// if any part fails valid = false
		// iterate over the names array returned from sierra
		foreach ($patronNames as $patronName) {
			// first check for a full string match
			// example Doe, John == Doe, John
			if($patronName == $username) {
				$valid = true;
				break;
			}
			// iterate over each of usernameParts looking for a match in $patronName
			foreach ($usernameParts as $userNamePart) {
				// This will match a COMPLETE (case insensitive) $usernamePart on word boundary.
				// If any $usernamePart fails to match $valid will be false and iteration breaks. Iteration will continue
				// over next $patronName.
				// Assuming $patronName = Doe, John
				// The following will pass:
				// john doe, john, doe, john Doe, doe John
				// The following will fail:
				// johndoe, jo, jo doe, john do
				if (preg_match('~\\b' . $userNamePart . '\\b~i', $patronName, $m)) {
					$valid = TRUE;
				} else {
					$valid = FALSE;
					break;
				}
			}
			// If a match is found, break outer foreach and valid is true
			if ($valid === TRUE) {
				break;
			}
		}

		// return either false on fail or user sierra id on success.
		if($valid === TRUE) {
			$result = $r->id;
		} else {
			$result = FALSE;
		}
		return $result;
	}

	/**
	 * patrons/validate
	 *
	 * @param string $barcode
	 * @param string $pin
	 * @return string|false Returns patron id on success false on fail.
	 */
	private function _authBarcodePin($barcode, $pin) {

		// if using username field check if username exists
		// username replaces barcode
		if($this->hasUsernameField()) {
			$params = [
			'varFieldTag'     => 'i',
			'varFieldContent' => $barcode,
			'fields'          => 'barcodes',
			];

			$operation = 'patrons/find';
			$r = $this->_doRequest($operation, $params);
			if($r) {
				$barcode = $r->barcodes[0];
				$this->patronBarcode = $barcode;
			}
		}

		$params = [
			"barcode" => $barcode,
			"pin"     => $pin
		];

		if (!$this->_doRequest("patrons/validate", $params, "POST")) {
			return false;
		}

		if(!$patronId = $this->getPatronId($barcode)){
			return false;
		}

		return $patronId;

	}

	/**
	 * function _oAuth
	 *
	 * Send oAuth token request
	 *
	 * @return boolean true on success, false otherwise
	 * @throws ErrorException
	 */
	private function _oAuthToken() {
		// check memcache for valid token and set $this
		$this->logger->info('Checking for oAuth token in memcache');
		if ($token = $this->memCache->get("sierra_oauth_token")) {
			$this->logger->info('Found oAuth token in memcache');
			$this->oAuthToken = $token;
			return TRUE;
		}
		$this->logger->info('No oAuth token in memcache. Requesting new token.');
		// setup url
		$url = $this->tokenUrl;
		// grab clientKey and clientSecret from configArray
		$clientKey    = $this->configArray['Catalog']['clientKey'];
		$clientSecret = $this->configArray['Catalog']['clientSecret'];
		//encode key and secret
		$requestAuth  = base64_encode($clientKey . ':' . $clientSecret);

		$headers = [
			'Host: '.$_SERVER['SERVER_NAME'],
			'Authorization: Basic ' . $requestAuth,
			'Content-Type: application/x-www-form-urlencoded',
			'grant_type=client_credentials'
		];

		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_HEADER         => false
		];

		// If there's an exception here, let it play out
		$c = new Curl();

		$c->setHeaders($headers);
		$c->setOpts($opts);
		$c->post($url);
		### ERROR CHECKS ###
		// for HTTP errors we'll grab the code from curl->info and the message from API.
		// if an error does occur set the $this->apiLastError.
		// get the info so we can check the headers for a good response.
		$cInfo = $c->getInfo();
		// first check for a curl error
		// we don't want to use $c->error because it will report HTTP errors.
		if ($c->isCurlError()) {
			// This will probably never be triggered since we have the try/catch above.
			$message = 'cUrl Error: '.$c->errorCode.': '.$c->errorMessage;
			$this->apiLastError = $message;
			$this->logger->error($message);
			throw new ErrorException($message);
		} elseif ($cInfo['http_code'] != 200) { // check the request returned success (HTTP 200)
			$message = 'API Error: '.$c->errorCode.': '.$c->errorMessage;
			//$message = 'API Error: '.$c->response->code.': '.$c->response->name;
			$this->apiLastError = $message;
			$this->logger->error($message);
			throw new ErrorException($message);
		}
		// make sure to set last error to false if no errors.
		$this->apiLastError = false;
		// setup memCache vars
		$token   = $c->response->access_token;
		$expires = $c->response->expires_in;
		$c->close();
		$this->oAuthToken = $token;
		$this->memCache->set("sierra_oauth_token", $token, $expires);
		$this->logger->info('Got new oAuth token.');
		return true;
	}

	/**
	 * _doRequest
	 *
	 * Perform a curl request to the Sierra API.
	 *
	 * @param  string $operation       The API method to call ie; patrons/find
	 * @param  array  $params          Request parameters
	 * @param  string $method          Request method
	 * @param  null   $extraHeaders    Additional headers
	 * @return bool|object             Returns false fail or JSON object
	 */
	private function _doRequest($operation, $params = array(), $method = "GET", $extraHeaders = null) {
		$this->apiLastError = false;
		// setup headers
		// These headers are common to all Sierra API except token requests.
		$headers = [
			'Host'           => $_SERVER['SERVER_NAME'],
			'Authorization'  => 'Bearer '.$this->oAuthToken,
			'User-Agent'     => 'Pika',
			'X-Forwarded-For'=> $_SERVER['SERVER_ADDR']
		];

		// merge headers
		if ($extraHeaders) {
			$headers = array_merge($headers, $extraHeaders);
		}
		// setup default curl opts
		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => false,
		];
		// instantiate the Curl object and set the base url
		$operationUrl = $this->apiUrl.$operation;
		try {
			$c = new Curl();
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);
			return false;
		}
		$c->setHeaders($headers);
		$c->setOpts($opts);

		// make the request using the proper method.
		$method = strtolower($method);
		switch($method) {
			case 'get':
				$c->get($operationUrl, $params);
				break;
			case 'post':
				$c->setHeader('Content-Type', 'application/json');
				$c->post($operationUrl, $params);
				break;
			case 'put':
				$c->setHeader('Content-Type', 'application/json');
				$c->put($operationUrl, $params);
				break;
			case 'delete':
				if(!empty($params)) {
					$c->delete($operationUrl, $params);
				} else {
					$c->delete($operationUrl);
				}
				break;
			default:
				$c->get($operationUrl, $params);
		}

		### ERROR CHECKS ###
		// if an error does occur set the $this->apiLastError.

		// get the info so we can check the headers for a good response.
		$cInfo = $c->getInfo();
		// first check for a curl error
		// we don't want to use $c->error because it will report HTTP errors.
		if ($c->isCurlError()) {
			// This will probably never be triggered since we have the try/catch above.
			$message = 'curl Error: '.$c->getCurlErrorCode().': '.$c->getCurlErrorMessage();
			$this->apiLastError = $message;
			$this->logger->warning($message);
			return false;
		} elseif ($c->isHttpError()) {
			// this will be a 4xx response
			// first we need to check the response for a code, message from the API because many failed operations (ie,
			// freezeing a hold) will send back a 4xx response code if the operation couldn't be completed.
			if(isset($c->response->code)) {
				$message = 'API Error: ' . $c->response->code . ': ' . $c->response->name;
				if(isset($c->response->description)){
					$message = $message . " " . $c->response->description;
					$this->logger->warning($message, ['api_response'=>$c->response]);
				}
			} else {
				$message = 'HTTP Error: '.$c->getErrorCode().': '.$c->getErrorMessage();
				$this->logger->warning($message);
			}
			$this->apiLastError = $message;
			return false;
		}
		// no errors
		// make sure apiLastError is false after error checks
		$this->apiLastError = false;
		// handle a "no response body" status code
		if($c->getHttpStatusCode() == '204') {
			$r = true;
		} else {
			$r = $c->response;
		}
		$this->logger->debug('API response for ['.$method.']'.$operation, ['method'=>$operation, 'response'=>$r]);
		$c->close();
		return $r;
	}

	/**
	 * Get volumes for item level holds
	 *
	 * @param $bibId
	 * @return array|false
	 */
	public function getItemVolumes($bibId) {
		$record = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $bibId);
		$solrRecords = $record->getGroupedWorkDriver()->getRelatedRecord($this->accountProfile->recordSource . ':' . $bibId);

		if(!isset($solrRecords['itemDetails']) || count($solrRecords['itemDetails']) > 1) {
			// todo: something
		}

		$items = [];
		foreach($solrRecords['itemDetails'] as $record) {
			// todo: is holdable?
			$items[] = array(
				'itemNumber' => $record['itemId'],
				'location'   => $record['shelfLocation'],
				'callNumber' => $record['callNumber'],
				'status'     => $record['status']
			);
		}
		return $items;
	}

	/**
	 * Get a variable field value.
	 *
	 * @param  string $tag       The single letter tag to return
	 * @param  array  $varFields The variable fields array
	 * @return false|string      Returns false if the field isn't found or the variable field value
	 */
	private function getVarField($tag, array $varFields) {
		$i = array_filter($varFields, function($k) use ($tag) {
			return ($k->fieldTag == $tag);
		});

		if(empty($i)) {
			$content = false;
		} else {
			$key = array_key_first($i);
			$content = $i[$key]->content;
		}
		return $content;
	}

	/**
	 * @param $itemId
	 * @return int
	 */
	private function _getBibIdFromItemId($itemId) {
		$operation = "items/".$itemId;
		$params = ["fields"=>"bibIds"];
		$iR = $this->_doRequest($operation, $params);
		if($iR) {
			$bid = $iR->bibIds[0];
		} else {
			$bid = false;
		}
		return $bid;
	}

	/**
	 * Try to find a prettier error message.
	 *
	 * @return string|false
	 */
	private function _getPrettyError() {
		if($this->apiLastError) {
			// grab a user friendlier string for the fail message
			$messageParts = explode(':', $this->apiLastError);
			// just grab the last part of message
			$offset = (count($messageParts) - 1);
			$message = $messageParts[$offset];
			$return = $message;
		} else {
			$return = false;
		}
		return $return;
	}

	private function getCheckDigit($baseId){
		$baseId = preg_replace('/\.?[bij]/', '', $baseId);
		$sumOfDigits = 0;
		for ($i = 0; $i < strlen($baseId); $i++){
			$curDigit = substr($baseId, $i, 1);
			$sumOfDigits += ((strlen($baseId) + 1) - $i) * $curDigit;
		}
		$modValue = $sumOfDigits % 11;
		if ($modValue == 10){
			return "x";
		}else{
			return $modValue;
		}
	}

}