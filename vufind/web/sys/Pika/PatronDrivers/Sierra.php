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
 *  TODO: For Sierra patrons we will use the Sierra patron ID as a username as this must be unique although a different approach would be preferred.
 *
 *  Currently, in the database password and cat_username represent the patron barcodes. The password is now obsolete
 *  and it's preferred to not store the Sierra ID in the database (see above to do) and prefer barcode as an index for finding a patron
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
use DateInterval;
use DateTime;
use ErrorException;
use InvalidArgumentException;
use Library;
use \Pika\Logger;
use \Pika\Cache;
use Location;
use MarcRecord;
use \Pika\PatronDrivers\MyBooking as MyBooking;
use RecordDriverFactory;
use User;
use ReadingHistoryEntry;
use PinReset;
use PHPMailer\PHPMailer\PHPMailer;

class Sierra {
	// let's swing back around to this later.
//	use \PatronCheckOutsOperations;
//	use \PatronHoldsOperations;
//	use \PatronFinesOperations;
//
//	use \PatronReadingHistoryOperations;
//	use \PatronPinOperations;
//	use \PatronSelfRegistrationOperations;

	// @var Pika/Memcache instance
	public  $memCache;
	// @var $logger Pika/Logger instance
	protected $logger;

	protected $configArray;
	// ----------------------
	/* @var $oAuthToken oAuth2Token */
	protected $oAuthToken;
	/* @var $apiLastError false|string false if no error or last error message */
	protected $apiLastError = false;
	/** @var  AccountProfile $accountProfile */
	public $accountProfile;
	/* @var $patronBarcode string The patrons barcode */
	protected $patronBarcode;
	/* @var $apiUrl string The url for the Sierra API */
	protected $apiUrl;
	/* @var $tokenUrl string The url for token */
	protected $tokenUrl;
	// many ids come from url. example: https://sierra.marmot.org/iii/sierra-api/v5/items/5130034
	protected $urlIdRegExp = "/.*\/(\d*)$/";


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
	 * @return array
	 * @throws ErrorException
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

		$offset = 0;
		$total  = 0;
		$count  = 0;
		$limit  = 100;

		$checkoutEntries = [];
		do {
			$params = [
			 'fields' => 'default,barcode,callNumber',
			 'limit'  => $limit,
			 'offset' => $offset
			];
			$rawCheckouts = $this->_doRequest($operation, $params);
			if(!$rawCheckouts) {
				$this->logger->info($this->apiLastError);
				return [];
			} elseif($rawCheckouts->total == 0) {
				// no checkouts
				return [];
			}

			$checkoutEntries = array_merge($rawCheckouts->entries, $checkoutEntries);
			$offset += $limit;
			$total   = $rawCheckouts->total;
			$count   = count($checkoutEntries) + 1;
		} while ($count < $total);


		$checkouts = [];
		foreach($checkoutEntries as $entry) {
			// standard stuff
			// get checkout id
			preg_match($this->urlIdRegExp, $entry->id, $m);
			$checkoutId = $m[1];

			if(strstr($entry->item, "@")) {
				///////////////
				// INNREACH CHECKOUT
				///////////////
				// todo: need to get inn-reach item id.
				$innReach = new InnReach();
				$titleAndAuthor = $innReach->getCheckoutTitleAuthor($checkoutId);
				$coverUrl = $innReach->getInnReachCover();
        
				$checkout['checkoutSource'] =  $this->accountProfile->recordSource;
				$checkout['id']             = $checkoutId;
				$checkout['dueDate']        = strtotime($entry->dueDate);
				$checkout['checkoutDate']   = strtotime($entry->outDate);
				$checkout['renewCount']     = $entry->numberOfRenewals;
				$checkout['recordId']       = 0;
				$checkout['renewIndicator'] = $checkoutId;
//				$checkout['renewMessage']   = '';
				$checkout['coverUrl']       = $coverUrl;
				$checkout['barcode']        = $entry->barcode;
				$checkout['request']        = $entry->callNumber;
				$checkout['author']         = $titleAndAuthor['author'];
				$checkout['title']          = $titleAndAuthor['title'];
				$checkout['title_sort']     = $titleAndAuthor['sort_title'];
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

			$checkout['checkoutSource'] =  $this->accountProfile->recordSource;
			$checkout['recordId']       = $bibId;
			$checkout['id']             = $checkoutId;
			$checkout['dueDate']        = strtotime($entry->dueDate);
			$checkout['checkoutDate']   = strtotime($entry->outDate);
			$checkout['renewCount']     = $entry->numberOfRenewals;
			$checkout['barcode']        = $entry->barcode;
//			$checkout['request']        = $entry->callNumber;
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
	 * todo: Legacy?
	 *
	 */
	public function hasFastRenewAll() {
		return false;
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
	 * @throws ErrorException
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
	 * @param string  $username        The patron username or barcode
	 * @param string  $password        The patron barcode or pin
	 * @param boolean $validatedViaSSO If the patron was validated outside Pika
	 *
	 * @return  User|null           User object or null
	 * @access  public
	 * @throws ErrorException
	 */
	public function patronLogin($username, $password, $validatedViaSSO = FALSE){
		$this->logger->info("patronLogin called from ".debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class']);
		// get the login configuration barcode_pin or name_barcode
		// TODO: Need to pull login from session, db, memcache, etc, so login isn't called repeatably on each request.
		$loginMethod = $this->accountProfile->loginConfiguration;
		// check patron credentials depending on login config.
		// the returns from _auth methods should be either a sierra patron id or false.
		$username = trim($username);
		$password = trim($password);

		if($validatedViaSSO) {
			$patronId = $this->getPatronId($password);
			$this->patronBarcode = $password;
		} elseif ($loginMethod == "barcode_pin") {
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
		return $this->getPatron($patronId);

	}

	/**
	 * Builds, updates (if needed) and returns the patron object
	 *
	 * Use caution with sites using usernames. If you don't write functions for usernames it will break.
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

		$createPatron = false;
		$updatePatron = false;

		// 1. check for a cached user object
		$patronObjectCacheKey = 'patron_'.$this->patronBarcode.'_patron';
		if ($pObj = $this->memCache->get($patronObjectCacheKey)) {
			$this->logger->info("Found patron in memcache:".$patronObjectCacheKey);
			return $pObj;
		}

		// 2. grab everything from the patron record the api can provide.
		$params = [
			'fields' => 'names,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,barcodes,patronCodes,createdDate,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate'
		];
		$operation = 'patrons/'.$patronId;
		$pInfo = $this->_doRequest($operation, $params);
		if(!$pInfo) {
			return null;
		}

		// 3. Check to see if the user exists in the database
		// the username db field stores the sierra patron id. we'll use that to determine if the user exists.
		// todo: store the sierra id in another database column.
		$patron = new User();
		$patron->username = $patronId;
		// does the user exist in database?
		if(!$patron->find(true)) {
			$this->logger->info('Patron does not exits in Pika database.', ['barcode'=>$this->patronBarcode]);
			$createPatron = true;
		}

		// 4. Find the right barcode
		// we need to monkey with the barcodes. barcodes can change!
		// self registered users may have something in the api response that looks like this
		// -- before getting a physical card
		// $pInfo->barcodes['', '201975']
		// -- after getting a physical card
		// $pInfo->barcodes['56369856985', '201975']
		// so we need to look for both barcodes and determine if the temp barcode needs updated to the permanent one
		$barcode = '';
		if(count($pInfo->barcodes > 1)) {
			// if the first barcode is set this should be the permanent barcode.
			if($pInfo->barcodes[0] != '') {
				$barcode = $pInfo->barcodes[0];
			} else {
				if($pInfo->barcodes[1] != '') {
					$barcode = $pInfo->barcodes[1];
				}
			}
		}
		// barcode isn't actually in database, but is stored in User->data['barcode']
		$patron->barcode = $barcode;

		// 5. check all the places barcodes are stored and determine if they need updated.
		$loginMethod    = $this->accountProfile->loginConfiguration;
		$patron->source = $this->accountProfile->name;

		if ($loginMethod == "barcode_pin") {
			if($patron->cat_username != $barcode) {
				$updatePatron = true;
				$patron->cat_username = $barcode;
			}
		} else {
			if($patron->cat_password != $barcode) {
				$updatePatron = true;
				$patron->cat_password = $barcode;
			}
		}

		// 5. Checks; make sure patron info from sierra matches database. update if needed.
		// 5.1 username
		$username = $pInfo->id;
		if($username != $patron->username) {
			$patron->username = $username;
			$updatePatron = true;
		}

		// 5.2 check patron type
		if((int)$pInfo->patronType !== (int)$patron->patronType) {
			$updatePatron = true;
			$patron->patronType = $pInfo->patronType;
		}

		// 5.3 check names
		if ($loginMethod == "name_barcode") {
			if($patron->cat_username != $pInfo->names[0]) {
				$updatePatron = true;
				$patron->cat_username = $pInfo->names[0];
			}
		}

		if(stristr($pInfo->names[0], ',')) {
			// find a comma-- assume the name is in form last, first middle
			$nameParts = explode(',', $pInfo->names[0]);
			$firstName = trim($nameParts[1]);
			$lastName  = trim($nameParts[0]);

		} else {
			// only spaces --assume last name is last
			$nameParts = explode(' ', $pInfo->names[0]);
			// get the last index
			$countNameParts = count($nameParts) - 1;
			$lastName = $nameParts[$countNameParts];
			if ($countNameParts >= 1) {
				unset($nameParts[$countNameParts]);
				$firstName = implode(' ', $nameParts);
			} else {
				$firstName = '';
			}
		}
		if($firstName != $patron->firstname || $lastName != $patron->lastname) {
			$updatePatron = true;
			$patron->firstname = $firstName;
			$patron->lastname  = $lastName;
		}

		// 5.4 check email
		if((isset($pInfo->emails) && !empty($pInfo->emails)) && $pInfo->emails[0] != $patron->email) {
			$updatePatron = true;
			$patron->email = $pInfo->emails[0];
		} else {
			if(empty($patron->email)) {
				$patron->email = '';
			}
		}

		// 5.5 check locations
		// 5.5.1 home locations
		$location       = new Location();
		$location->code = $pInfo->homeLibraryCode;
		$location->find(true);
		$homeLocationId = $location->locationId;
		if($homeLocationId != $patron->homeLocationId) {
			$updatePatron = true;
			$patron->homeLocationId = $homeLocationId;
		}
		$patron->homeLocation = $location->displayName;

		// 5.5.2 location1
		if(empty($patron->myLocation1Id)) {
			$updatePatron = true;
			$patron->myLocation1Id     = ($location->nearbyLocation1 > 0) ? $location->nearbyLocation1 : $location->locationId;
			$myLocation1             = new Location();
			$myLocation1->locationId = $patron->myLocation1Id;
			if ($myLocation1->find(true)) {
				$patron->myLocation1 = $myLocation1->displayName;
			}
		}

		// 5.5.3 location2
		if(empty($patron->myLocation2Id)) {
			$updatePatron = true;
			$patron->myLocation2Id     = ($location->nearbyLocation2 > 0) ? $location->nearbyLocation2 : $location->locationId;
			$myLocation2             = new Location();
			$myLocation2->locationId = $patron->myLocation2Id;
			if ($myLocation2->find(true)) {
				$patron->myLocation2 = $myLocation2->displayName;
			}
		}

		// 6. things not stored in database so don't need to check for updates but do need to add to object.
		// 6.1 alt username
		// this is used on sites allowing username login.
		if($this->hasUsernameField()) {
			$fields = $pInfo->varFields;
			$i = array_filter($fields, function($k) {
				return ($k->fieldTag == 'i');
			});
			if(empty($i)) {
				$patron->alt_username = '';
			} else {
				$key = array_key_first($i);
				$alt_username = $i[$key]->content;
				$patron->alt_username = $alt_username;
			}
		}

		// 6.2 check phones
		$homePhone   = '';
		$mobilePhone = '';
		if(isset($pInfo->phones) && is_array($pInfo->phones)){
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
		}
		if(!isset($patron->phone)) {
			$patron->phone = '';
		}

		// 6.3 fullname
		$patron->fullname  = $pInfo->names[0];

		// 6.4 address
		// some libraries may not use ','  after city so make sure we have all the parts
		// can assume street as a line and city, st. zip as a line
		// Note: There are other unusual entries for address as well:
		// ART
		// CAMPUS ADDRESS
		// another:
		// Words on Wheels Patron Wednesday 1 (Aaron)
		// another:
		// Salida Co, 81201 <- comma in the wrong place
		//Another ugly one; single line (actual street address removed): 123 streetname Drive Apt 8 Woody Creek, CO 81656

		// set these early to avoid warnings.
		$patron->address1    = '';
		$patron->address2    = '';
		$patron->city        = '';
		$patron->state       = '';
		$patron->zip         = '';
		$patronCity          = '';
		$patronState         = '';
		$patronZip           = '';

		$zipRegExp    = '|\d{5}$|';
		$splitRegExp  = '%([a-zA-Z]+)[\s|,]%'; // splits on spaces or commas -- doesn't include zip
		if(isset($pInfo->addresses) && is_array($pInfo->addresses)){
			// get the home address -- we won't handle alt addresses in Pika.
			$homeAddressArray = false;
			foreach ($pInfo->addresses as $address) {
				// a = primary address, h = alt address
				if ($address->type == 'a') {
					$homeAddressArray = $address->lines;
				}
			}
			// found a home address
			if($homeAddressArray) {
				$addressLineCount = count($homeAddressArray);
				// 3 lines - if we have three lines the first is c/o or something similar, currently don't need
				if ($addressLineCount == 3) {
					array_shift($homeAddressArray); // shift off the first line
					$addressLineCount = count($homeAddressArray); // reset line count
				}
				// 2 lines (or previously 3) - if we have at least 2 lines we should have a full address
			  if($addressLineCount == 2) {
					// we can set address1 and address2
				  $patron->address1 = $homeAddressArray[0];
				  $patron->address2 = $homeAddressArray[1];
				  // check last line for a zip -- this is where we assume it lives
				  $zipTest = preg_match($zipRegExp, trim($homeAddressArray[1]), $zipMatch);
				  if($zipTest === 1) {
					  // OK, this should be a full address
					  $patronZip = $zipMatch[0];
					  // now split out the rest of the address
					  $cityStateTest = preg_match_all($splitRegExp, trim($homeAddressArray[1]), $cityStateMatches);
					  if($cityStateTest) {
						  $cityState = $cityStateMatches[1];
						  $cityStateCount = count($cityState) - 1; // zero index
						  // state should be last
						  $patronState = $cityState[$cityStateCount];
						  // pop last value and join array
						  array_pop($cityState);
						  $patronCity = implode(' ', $cityState);
					  }
				  } else {
				  	// no zip, not a full address
					  // todo: how to handle, if at all
				  }
			  } else {
				  // 1 line - only one address line -- this could be anything so start checking
				  $patron->address1 = $homeAddressArray[0];
				  // does it contain a zip? It might be some like grand junction, co 81501
				  $zipTest = preg_match($zipRegExp, $homeAddressArray[0], $zipMatch);
				  if ($zipTest === 1) {
					  // found a zip
					  $patronZip = $zipMatch[0];
					  // find a city and state?
					  $cityStateTest = preg_match_all($splitRegExp, trim($homeAddressArray[0]), $cityStateMatches);
					  if ($cityStateTest) {
						  $cityState      = $cityStateMatches[1];
						  $cityStateCount = count($cityState) - 1; // zero index
						  // state should be last
						  $patronState = $cityState[$cityStateCount];
						  // unset last value and join array
						  array_pop($cityState);
						  $patronCity = implode(' ', $cityState);
					  } else {
						  // well, that should'a matched something
						  // todo: what do to?
					  }
				  } else {
					  // couldn't find a zip -- not much to do
					  // todo: what do to?
				  }
			  }
			}
		}
		$patron->city  = $patronCity;
		$patron->state = $patronState;
		$patron->zip   = $patronZip;

		// 6.5 mobile phone
		// this triggers sms notifications for libraries using Sierra SMS
		if(isset($mobilePhone)) {
			$patron->mobileNumber = $mobilePhone;
		} else {
			$patron->mobileNumber = '';
		}

		// 6.6 account expiration
		if (!empty($pInfo->expirationDate)){
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
		}else{
			$patron->expires = '00-00-0000';
		}

		// 6.7 notices
		$patron->notices = $pInfo->fixedFields->{'268'}->value;
		switch($pInfo->fixedFields->{'268'}->value) {
			case '-':
				$patron->noticePreferenceLabel = 'none';
				break;
			case 'a':
				$patron->noticePreferenceLabel = 'Mail';
				break;
			case 'p':
				$patron->noticePreferenceLabel = 'Telephone';
				break;
			case 'z':
				$patron->noticePreferenceLabel = 'E-mail';
				break;
			default:
				$patron->noticePreferenceLabel = 'none';
		}

		// 6.8 number of checkouts from ils
		$patron->numCheckedOutIls = $pInfo->fixedFields->{'50'}->value;

		// 6.9 fines
		$patron->fines = number_format($pInfo->moneyOwed, 2, '.', '');
		$patron->finesVal = number_format($pInfo->moneyOwed, 2, '.', '');

		// 6.10 hold counts
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
			if ($this->accountProfile->loginConfiguration == "barcode_pin") {
				$barcode = $patronOrBarcode->cat_username ;
			} else {
				$barcode = $patronOrBarcode->cat_password;
			}
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
			$this->logger->warn('Could not get patron ID.', ['barcode'=>$barcode, 'error'=>$this->apiLastError]);
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
	 * @param User $patron
	 * @param bool $canUpdateContactInfo
	 * @return array Array of errors or empty array on success
	 * @throws ErrorException
	 */
	public function updatePatronInfo($patron, $canUpdateContactInfo){
		if(!$canUpdateContactInfo) {
			return ['You can not update your information.'];
		}

		/*
		 * hack to shuffle off some actions
		 * This would be better in a router class
		 * If a method exits in a class extending this class it will be passed a User object.
		 */
		if(isset($_POST['profileUpdateAction'])) {
			$profileUpdateAction = trim($_POST['profileUpdateAction']);
			if(method_exists($this, $profileUpdateAction)) {
				return $this->$profileUpdateAction($patron);
			}
		}

		$patronId = $this->getPatronId($patron);
		if(!$patronId) {
			return ['An error occurred. Please try again later.'];
		}

		$library = $patron->getHomeLibrary();

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
					// if library allows address updates
					if((boolean)$library->allowPatronAddressUpdates){
						if(empty($val)) {
							$errors[] = "City, state and ZIP are required.";
						} else {
							$cityStZip[$key] = $val;
						}
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
					if(!empty($val) && $val != '') {
						$notices = $val;
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

		$params = [];

		if(isset($homeLibraryCode) && $homeLibraryCode != '') {
			$params['homeLibraryCode'] = $homeLibraryCode;
		}
		if(isset($emails) && !empty($emails)) {
			$params['emails'] = $emails;
		}
		if(isset($phones) && !empty($phones)) {
			$params['phones'] = $phones;
		}
		// allow address updates?
		if((boolean)$library->allowPatronAddressUpdates) {
			// fix up city state zip
			$address2 = $cityStZip['city'] . ', ' . $cityStZip['state'] . ' ' . $cityStZip['zip'];
			$params['addresses'] = [ (object)['lines' => [$address1, $address2], "type" => 'a'] ];
		}

		// if notice preference is present
		if(isset($notices)) {
			$params['fixedFields'] = (object)['268'=>(object)["label" => "Notice Preference", "value" => $notices]];
		}

		// username if present
		if (isset($altUsername) && $altUsername != '') {
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
	 * @throws ErrorException
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
			return [['Could not update PIN: '. $message]];
		}
		$patron->cat_password = $newPin;
		$patron->update();

		$this->memCache->delete('patron_'.$patron->barcode.'_patron');

		return [['Your pin number was updated successfully.']];
	}

	public function resetPin($patron, $newPin, $resetToken){

		$pinReset = new PinReset();
		$pinReset->userId = $patron->id;
		$pinReset->find(true);
		if(!$pinReset->N) {
			return ['error' => 'Unable to reset your PIN. Please try again later.'];
		} elseif($pinReset->N == 0) {
			return ['error' => 'Unable to reset your PIN. You have not requested a PIN reset.'];
		}
		// expired?
		if($pinReset->expires < time()) {
			return ['error' => 'The reset token has expired. Please request a new PIN reset.'];
		}
		$token = $pinReset->selector.$pinReset->token;
		// make sure and type cast the two numbers
		if ((int)$token != (int)$resetToken) {
			return ['error' => 'Unable to reset your PIN. Invalid reset token.'];
		}
		// everything is good
		$patronId = $this->getPatronId($patron);
		$operation = 'patrons/'.$patronId;
		$params    = ['pin' => (string)$newPin];

		// update sierra first
		$r = $this->_doRequest($operation, $params, 'PUT');
		if(!$r) {
			$message = $this->_getPrettyError();
			return ['error' => 'Could not update PIN: '. $message];
		}

		$patron->cat_password = $newPin;
		if(!$patron->update()) {
			// this shouldn't matter since we hit the api first when logging in a patron, but ....
			$this->memCache->delete('patron_'.$patron->barcode.'_patron');
			return ['error' => 'Please try logging in with you new PIN. If you are unable to login please contact your library.'];
		}
		$pinReset->delete();
		$this->memCache->delete('patron_'.$patron->barcode.'_patron');

		return true;
	}

	public function emailResetPin($barcode) {
		$patron = new User();

		$loginMethod = $this->accountProfile->loginConfiguration;
		if ($loginMethod == "barcode_pin"){
			$patron->cat_username = $barcode;
		} elseif ($loginMethod == "name_barcode") {
			$patron->cat_password = $barcode;
		}

		$patron->find(true);
		if(! $patron->N || $patron->N == 0) {
			return ['error' => 'Unable to find an account associated with barcode: '.$barcode ];
		}
		if(!isset($patron->email) || $patron->email == '') {
			return ['error' => 'You do not have an email address on your account. Please visit your library to reset your pin.'];
		}
		// Create tokens
		// todo: PHP7 use random_int or random_bytes
		$selector = bin2hex(mt_rand(10000000, 900000000));
		$token = mt_rand(1000000000000000, 9999999999999999);
		$now = new DateTime('NOW');
		$now->add(new DateInterval('PT01H')); // 1 hour
		$expires = $now->format('U');
		$resetToken = $selector.$token;
		// make sure there's no old token.
		$pinReset = new PinReset();
		$pinReset->userId = $patron->id;
		$pinReset->delete();

		// insert pin reset request
		$pinReset->expires = $expires;
		$pinReset->token = $token;
		$pinReset->selector = $selector;
		$pinReset->insert();

		// build reset url
		$resetUrl = $this->configArray['Site']['url'] . "/MyAccount/ResetPin?uid=".$patron->id.'&resetToken='.$resetToken;

		// build the message
		$subject = "Pin Reset Link";
		$htmlMessage = <<<EOT
		<p>We received a password reset request. The link to reset your password is below.  
If you did not make this request, you can ignore this email</p>  
<p>Here is your password reset link:</br>  
$resetUrl
EOT;

		$mail = new PHPMailer;
		$mail->setFrom($this->configArray['Site']['email']);
		$mail->addAddress($patron->email);
		$mail->Subject = $subject;
		$mail->msgHTML($htmlMessage);
		$mail->AltBody = strip_tags($htmlMessage);

		if(!$mail->send()) {
			$this->logger->error('Can not send email from Sierra.php');
			return ['error' => "We're sorry. We are unable to send mail at this time. Please try again."];
		}
		return true;
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
	 * Send a Self Registration request to the ILS.
	 *
	 * PUT patrons
	 * @return  array  [success = bool, barcode = null or barcode]
	 * @throws InvalidArgumentException If missing configuration parameters
	 * @throws ErrorException
	 */
	public function selfRegister(){

		global $library;
		// sanity checks
		if(!property_exists($library, 'selfRegistrationDefaultpType') || empty($library->selfRegistrationDefaultpType)) {
			$message = 'Missing configuration parameter selfRegistrationDefaultpType for ' . $library->displayName;
			$this->logger->error($message);
			throw new InvalidArgumentException($message);
		}
		if(!property_exists($library, 'selfRegistrationAgencyCode') || empty($library->selfRegistrationAgencyCode)) {
			$message = 'Missing configuration parameter selfRegistrationAgencyCode for ' . $library->displayName;
			$this->logger->error($message);
			throw new InvalidArgumentException($message);
		}

		$params = [];
		foreach ($_POST as $key=>$val) {
			switch ($key) {
				case 'email':
					$val = trim($val);
					if(!empty($val)){
						$params['emails'][] = $val;
					}
					break;
				case 'address': // street part of address
					$val = trim($val);
					$params['addresses'][0]['lines'][0] = $val;
					$params['addresses'][0]['type'] = 'a';
					break;
				case 'altaddress':
					$val = trim($val);
					if(!empty($val)){
						$params['addresses'][1]['lines'][0] = $val;
					}else{
						$params['addresses'][1]['lines'][0] = 'none';
					}
					$params['addresses'][1]['type'] = 'h';
					break;
				case 'primaryphone':
					$val = trim($val);
					if(!empty($val)){
						$params['phones'][] = ['number'=>$val, 'type'=>'t'];
					}
					break;
				case 'altphone':
					$val = trim($val);
					if(!empty($val)){
						$params['phones'][] = ['number'=>$val, 'type'=>'p'];
					}
					break;
				case 'birthdate':
					if(!empty($val)) {
						$date                = DateTime::createFromFormat('m-d-Y', $val);
						$params['birthDate'] = $date->format('Y-m-d');
					}
					break;
				case 'homelibrarycode':
					if(!empty($val)){
						$params['homeLibraryCode'] = $val;
					}
					break;
			}
		}

		// default patron type
		$params['patronType'] = (int)$library->selfRegistrationDefaultpType;

		// generate a random temp barcode
		$min = str_pad(1, $library->selfRegistrationBarcodeLength, 0);
		$max = str_pad(9, $library->selfRegistrationBarcodeLength, 9);
		// it's possible to register a patron with a barcode that is already in Sierra so make sure this doesn't happen
		$barcodeTest = true;
		do {
			$barcode = (string)mt_rand((int)$min, (int)$max);
			$barcodeTest = $this->getPatronId($barcode);
		} while ($barcodeTest === true);
		$params['barcodes'][] = $barcode;

		// agency code
		$params['fixedFields']["158"] = ["label" => "PAT AGENCY",
		                                 "value" => $library->selfRegistrationAgencyCode];
		// expiration date
		$interval = 'P'.$library->selfRegistrationDaysUntilExpire.'D';
		$expireDate = new DateTime();
		$expireDate->add(new DateInterval($interval));
		$params['expirationDate'] = $expireDate->format('Y-m-d');

		// names -- standard is Last, First Middle
		$name  = trim($_POST['lastname']) . ", ";
		$name .= trim($_POST['firstname']);
		if(!empty($_POST['middlename'])) {
			$name .= ' '.trim($_POST['middlename']);
		}
		$params['names'][] = $name;

		// city state and zip
		$cityStateZip = trim($_POST['city']).', '.trim($_POST['state']).' '.trim($_POST['zip']);
		// address line 2
		$params['addresses'][0]['lines'][1] = $cityStateZip;

		// todo: special handling for VAIL
		// include test and production
		$libSubDomain = strtolower($library->subdomain);
		if($libSubDomain == 'vail' || $libSubDomain == 'vail2') {
			$params['varFields'][] = ["fieldTag" => "u",
		                            "content"  => "#"];
			$params['varFields'][] = ["fieldTag" => "i",
			                          "content"  => "#"];
			$params['varFields'][] = ["fieldTag" => "q",
			                          "content"  => "XXXLLFF"];
			$params['pMessage']    = 'f';
		}
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
	 * Used to build the form for self registration.
	 * Fields will be displayed in order of indexed array
	 *
	 * @return array Self registration fields
	 */
	public function getSelfRegistrationFields(){
		$fields = [];

		global $library;
		// get the valid home/pickup locations
		$l = new Location();
		$l->libraryId = $library->libraryId;
		$l->validHoldPickupBranch = '1';
		$l->find();
		if(!$l->N) {
			return ['success'=>false, 'barcode'=>''];
		}
		$l->orderBy('displayName');
		$homeLocations = $l->fetchAll('code', 'displayName');

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
		             'required'   => false];

		$fields[] = ['property'   => 'lastname',
		             'type'       => 'text',
		             'label'      => 'Last name',
		             'description'=> 'Your last name (surname)',
		             'maxLength'  => 30,
		             'required'   => true];

		$fields[] = ['property'   => 'homelibrarycode',
		             'type'       => 'enum',
		             'label'      => 'Home Library/Preferred pickup location',
		             'description'=> 'Your home library and preferred pickup location.',
		             'values'     => $homeLocations,
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
		             'label'      => 'Mailing Address',
		             'description'=> 'Mailing Address.',
		             'maxLength'  => 40,
		             'required'   => true];

		$fields[] = ['property'   => 'altaddress',
		             'type'       => 'text',
		             'label'      => 'Physical Address',
		             'description'=> 'Physical Address.',
		             'maxLength'  => 40,
		             'required'   => false];

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
		// Bemis Signature Field
		if ($library->selfRegistrationTemplate == 'beself'){
			$fields[] = [
				'property' => 'signature',
				'type' => 'text',
				'label' => 'Signature',
				'description' => 'Enter your name',
				'maxLength' => 40,
				'required' => true
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
			$details = false;
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
				if (!empty($fine->returnDate)){
					$details = [[
						            "label" => "Returned: ",
						            "value" => date('m-d-Y', strtotime($fine->returnDate))
					            ]];
				}
				// if it's not an item charge look for a description
			} elseif (isset($fine->description)) {
				$title = 'Description: '.$fine->description;
			} else {
				$title = 'Unknown';
			}
			$amount = number_format($fine->itemCharge, 2);
			if(isset($fine->assessedDate) && !empty($fine->assessedDate)) {
				$date   = date('m-d-Y', strtotime($fine->assessedDate));
				if(!$date) {
					$date = '';
				}
			} else {
				$date = '';
			}
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
		if((integer)$this->configArray['Catalog']['api_version'] > 4) {
			$params=["fields" => "default,pickupByDate,frozen,priority,priorityQueueLength,notWantedBeforeDate,notNeededAfterDate",
			         "limit"  => 1000];
		} else {
			$params=["fields" => "default,frozen,priority,priorityQueueLength,notWantedBeforeDate,notNeededAfterDate",
			         "limit"  => 1000];
		}
		$holds = $this->_doRequest($operation, $params);

		if(!$holds) {
			return false;
		}

		if($holds->total == 0) {
			return [
				'available'   => [],
				'unavailable' => []
			];
		}
		// these will be consistent for every hold
		$displayName  = $patron->getNameAndLibraryLabel();
		$pikaPatronId = $patron->id;
		// can we change pickup location?
		$pickupLocations = $patron->getValidPickupBranches($this->accountProfile->recordSource);
		if(is_array($pickupLocations)) {
			if (count($pickupLocations) > 1) {
				$canUpdatePL = true;
			} else {
				$canUpdatePL = false;
			}
		} else {
			$canUpdatePL = false;
		}

		$availableHolds   = [];
		$unavailableHolds = [];
		foreach ($holds->entries as $hold) {
			// standard stuff
			$h['holdSource']      = $this->accountProfile->recordSource;
			$h['userId']          = $pikaPatronId;
			$h['user']            = $displayName;

			// get what's available from this call
			$h['frozen']                = $hold->frozen;
			$h['create']                = strtotime($hold->placed); // date hold created
			// innreach holds don't include notNeededAfterDate
			$h['automaticCancellation'] = isset($hold->notNeededAfterDate) ? strtotime($hold->notNeededAfterDate) : null; // not needed after date
			$h['expire']                = isset($hold->pickupByDate) ? strtotime($hold->pickupByDate) : false; // pick up by date // this isn't available in api v4

			// fix up hold position
			// #D-3420
			if (isset($hold->priority) && isset($hold->priorityQueueLength)) {
				$h['position'] = $hold->priority . ' of ' . $hold->priorityQueueLength;
			} elseif (isset($hold->priority) && !isset($hold->priorityQueueLength)) {
				$h['position'] = $hold->priority;
			} else {
				$h['position'] = false;
			}

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
				if(isset($hold->priority) && ((int)$hold->priority <= 2 && (int)$hold->priorityQueueLength >= 2)) {
					$freezeable = false;
				// if the patron is the only person on wait list hold can't be frozen
				} elseif(isset($hold->priority) && ($hold->priority == 1 && (int)$hold->priorityQueueLength == 1)) {
					$freezeable = false;
				// if there is no priority set but queueLength = 1
				} elseif(!isset($hold->priority) && $hold->priorityQueueLength == 1) {
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
			if (!empty($hold->pickupLocation)){
				$pickupBranch = new Location();
				$where        = "code = '{$hold->pickupLocation->code}'";
				$pickupBranch->whereAdd($where);
				$pickupBranch->find(1);
				if ($pickupBranch->N > 0){
					$pickupBranch->fetch();
					$h['currentPickupId']   = $pickupBranch->locationId;
					$h['currentPickupName'] = $pickupBranch->displayName;
					$h['location']          = $pickupBranch->displayName;
				}else{
					$h['currentPickupId']   = false;
					$h['currentPickupName'] = $hold->pickupLocation->name;
					$h['location']          = $hold->pickupLocation->name;
				}
			} else{
				//This shouldn't happen but we have had examples where it did
				$this->logger->error("Patron with barcode {$patron->getBarcode()} has a hold with out a pickup location ");
				$h['currentPickupId']   = false;
				$h['currentPickupName'] = false;
				$h['location']          = false;
			}

			// determine if this is an innreach hold
			// or if it's a regular ILS hold
			if(strstr($hold->record, "@")) {
				///////////////
				// INNREACH HOLD
				///////////////
				// get the inn-reach item id
				$regExp = '/.*\/(.*)$/';
				// we have to query for the item status (it will be an innreach status) as hold status for
				// inn-reach will always show 0
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
				$coverImage = $innReach->getInnReachCover();
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
				$h['coverUrl']           = $coverImage;
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

	/****
	 * READING HISTORY
	 ***/

	/**
	 * Route reading history actions to the appropriate function according to the readingHistoryAction
	 * URL parameter which will be one of:
	 *
	 * deleteAll    -> deleteAllReadingHistory
	 * deleteMarked -> deleteMarkedReadingHistory
	 * optIn        -> optInReadingHistory
	 * optOut       -> optOutReadingHistory
	 *
	 * @param  User   $patron
	 * @param  string $action One of the following; deleteAll, deleteMarked, optIn, optOut
	 * @return mixed
	 */
//	public function doReadingHistoryAction($patron, $action, $selectedTitles) {
//
//		switch ($action) {
//			case 'optIn':
//				return $this->optInReadingHistory($patron);
//				break;
//			case 'optOut':
//				return $this->optOutReadingHistory($patron);
//				break;
//			case 'deleteAll':
//				return $this->deleteAllReadingHistory($patron);
//				break;
//			case 'deleteMarked':
//				return $this->deleteMarkedReadingHistory($patron, $selectedTitles);
//				break;
//			default:
//				return false;
//		}
//	}

	/**
	 * Opt the patron into Reading History within the ILS.
	 *
	 * @param  User $patron
	 * @return bool $success  Whether or not the opt-in action was successful
	 */
	public function optInReadingHistory($patron) {
		$patronObjectCacheKey = 'patron_'.$patron->barcode.'_patron';
		$this->memCache->delete($patronObjectCacheKey);

		$success = $this->_curlOptInOptOut($patron, 'OptIn');
		if(!$success) {
			$this->logger->warning('Unable to opt in patron '. $patron->barcode . ' from ILS reading history. Falling back to Pika.');
		}
		$patron->trackReadingHistory = true;
		$patron->update();

		return true;
	}

	/**
	 * Opt out the patron from Reading History within the ILS.
	 *
	 * @param  User $patron
	 * @return bool Whether or not the opt-out action was successful
	 */
	public function optOutReadingHistory($patron) {
		$patronObjectCacheKey = 'patron_'.$patron->barcode.'_patron';
		$this->memCache->delete($patronObjectCacheKey);

		$success = $this->_curlOptInOptOut($patron, 'OptOut');
		if(!$success) {
			$this->logger->warning('Unable to opt out patron '. $patron->barcode . ' from ILS reading history. Falling back to Pika.');
		}
		$patron->trackReadingHistory = false;
		$patron->update();

		return true;
	}

	/**
	 * Delete all Reading History within the ILS for the patron.
	 *
	 * [DELETE] patrons/{id}/checkouts/history
	 * @param User $patron
	 * @return bool    Whether or not the delete all action was successful
	 * @throws ErrorException
	 */
	public function deleteAllReadingHistory($patron) {
		$patronId = $this->getPatronId($patron->barcode);
		if(!$patronId) {
			return false;
		}

		$patronReadingHistoryCacheKey = "patron_".$patron->barcode."_history";

		$operation = 'patrons/'.$patronId.'/checkouts/history';
		$r = $this->_doRequest($operation, [], 'DELETE');

		if(!$r) {
			return false;
		}

		$this->memCache->delete($patronReadingHistoryCacheKey);

		return true;
	}

	/**
	 * Delete selected items from reading history
	 *
	 * @param  User  $patron
	 * @param  array $selectedTitles
	 * @return bool
	 */
	public function deleteMarkedReadingHistory($patron, $selectedTitles) {

		$bibIds = [];
		foreach($selectedTitles as $key => $val) {
			$selectedId = trim($val, 'rsh');
			$h = new ReadingHistoryEntry();
			$h->id = $selectedId;
			$h->find(true);
			if($h) {
				$bibId = 0;
			}
		}

		$patronId = $this->getPatronId($patron->barcode);
		if(!$patronId) {
			return false;
		}

		$operation = "patrons/" . $patronId . "/checkouts/history";

		$offset = 0;
		$total  = 0;
		$count  = 0;
		$limit  = 2000;

		$historyEntries = [];
		 do {
		 	$params = [
				 'limit' => $limit,
				 'offset'=> $offset
		  ];
			$rawHistory = $this->_doRequest($operation, $params);
			if(!$rawHistory) {
				return false;
			}

			$historyEntries = array_merge($rawHistory->entries, $historyEntries);
			$offset += $limit;
			$total   = $rawHistory->total;
			$count   = count($historyEntries) + 1;
		} while ($count < $total);

		// selected -> get bib
		// format bib # for reading history search
		// iterate entries stristr bib #
		// if found get history id

	}

	public function hasNativeReadingHistory(){
		return true;
	}


	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut"){
		// history enabled?
		if ($patron->trackReadingHistory != 1){
			return ['historyActive' => false, 'numTitles' => 0, 'titles' => []];
		}

		$patronSierraId = $this->getPatronId($patron->barcode);

		$patronReadingHistoryCacheKey = "patron_" . $patron->barcode . "_history";
		$patronCachedReadingHistory         = $this->memCache->get($patronReadingHistoryCacheKey);
		if (!$patronCachedReadingHistory || isset($_REQUEST['reload'])){
			//TODO: loop to fetch histories with more than a 2000 entries
			$operation = "patrons/" . $patronSierraId . "/checkouts/history";
			$params    = ['limit'     => 2000, // Sierra api max results as of 9-12-2019
			              'sortField' => 'outDate',
			              'sortOrder' => 'desc'];
			$history   = $this->_doRequest($operation, $params);

			if (!$history){
				return false;
			}

			if ($history->total == 0){
				return [
					'historyActive' => true,
					'numTitles'     => 0,
					'titles'        => []
				];
			}
			$readingHistory = [];
			foreach ($history->entries as $historyEntry){
				$titleEntry = [];
				// make the Pika style bib Id
				preg_match($this->urlIdRegExp, $historyEntry->bib, $bibMatch);
				$x     = $this->getCheckDigit($bibMatch[1]);
				$bibId = '.b' . $bibMatch[1] . $x; // full bib id
				// get the checkout id --> becomes itemindex
				preg_match($this->urlIdRegExp, $historyEntry->id, $coIdMatch);
				$itemindex         = $coIdMatch[1];
				$checkOutTimestamp = strtotime($historyEntry->outDate);
				// get the rest from the MARC record
				$record = new MarcRecord($this->accountProfile->recordSource . ':' . $bibId);

				if ($record->isValid()){
					$titleEntry['permanentId'] = $record->getPermanentId();
					$titleEntry['title']       = $record->getTitle();
					$titleEntry['author']      = $record->getPrimaryAuthor();
					$titleEntry['format']      = $record->getFormat();
					$titleEntry['title_sort']  = $record->getSortableTitle();
					$titleEntry['ratingData']  = $record->getRatingData();
					$titleEntry['permanentId'] = $record->getPermanentId();
					$titleEntry['linkUrl']     = $record->getGroupedWorkDriver()->getLinkUrl();
					$titleEntry['coverUrl']    = $record->getBookcoverUrl('medium');
					$titleEntry['format']      = $record->getFormats();
				}else{
					// check the api
					$operation = 'bibs/' . $bibMatch[1];
					$params    = [
						'fields' => 'deleted,title,author,materialType,normTitle'
					];
					$bibRes    = $this->_doRequest($operation, $params);
					if (!$bibRes || $bibRes->deleted == true){
						$titleEntry['title']      = '';
						$titleEntry['author']     = '';
						$titleEntry['format']     = '';
						$titleEntry['title_sort'] = '';
					}else{
						$titleEntry['title']      = $bibRes->title;
						$titleEntry['author']     = $bibRes->author;
//						$titleEntry['format']     = $bibRes->materialType->value;
						$titleEntry['title_sort'] = $bibRes->normTitle;
					}
					$titleEntry['permanentId'] = '';
					$titleEntry['ratingData']  = '';
					$titleEntry['permanentId'] = '';
					$titleEntry['linkUrl']     = '';
					$titleEntry['coverUrl']    = '';
				}
				$titleEntry['checkout']     = $checkOutTimestamp;
				$titleEntry['shortId']      = $bibMatch[1];
				$titleEntry['borrower_num'] = $patronSierraId;
				$titleEntry['recordId']     = $bibId;
				$titleEntry['itemindex']    = $itemindex; // checkout id
				$readingHistory[]           = $titleEntry;
				// clear out before
				unset($titleEntry);
			}

			$total   = count($readingHistory);
			$history = [
				'historyActive' => true,
				'numTitles'     => $total,
				'titles'        => $readingHistory,
			];

			if ($recordsPerPage == -1){
				// Only cache if fetching all of the reading history
				$this->memCache->set($patronReadingHistoryCacheKey, $history, 21600);
				$this->logger->info("Saving reading history in memcache:" . $patronReadingHistoryCacheKey);
			}

		}else{
			$history = $patronCachedReadingHistory;
			$this->logger->info("Found reading history in memcache:" . $patronReadingHistoryCacheKey);
		}

		// search test
		//$search =  $this->searchReadingHistory($patron, 'colorado');
		//$history['titles'] = $search;
		//return $history;

		// Let the CatalogConnection Driver sort the results if $recordsPerPage == -1

		if ($recordsPerPage > -1){
			//TODO: sorting routine
			$historyPages      = array_chunk($history['titles'], $recordsPerPage);
			$pageIndex         = $page - 1;
			$history['titles'] = $historyPages[$pageIndex];
		}

		return $history;
	}

	public function searchReadingHistory($patron, $search){
		$history = $this->getReadingHistory($patron, 1, -1); // Fetch all of the patron's reading history from the ILS

		$found = array_filter($history['titles'], function ($k) use ($search){
			return stristr($k['title'], $search) || stristr($k['author'], $search);
		});

		return $found;
	}

	/**
	 * Get patron's reading history
	 *
	 * GET patrons/{patron_id}/checkouts/history
	 * This method is meant to be used by the Pika cron process load patron's reading history. It returns only the information needed
	 * to add reading history entries into the database.
	 *
	 * @param User $patron Patron Object
	 * @param null|int $loadAdditional The batch of reading history entries to load, eg 2nd batch, 3rd, etc
	 * @return array|false [titles]=>[borrower_num(Pika ID), recordId(bib ID), permanentId(grouped work ID), title,
	 *                     author, checkout]
	 * @throws ErrorException
	 */
	public function loadReadingHistoryFromIls($patron, $loadAdditional = null){
		set_time_limit(300);
		$patronId       = $this->getPatronId($patron->barcode);
		$operation      = "patrons/" . $patronId . "/checkouts/history";
		$limitPerCall   = 2000; // Sierra api max results as of 9-12-2019
		$params         = ['limit'     => $limitPerCall,
		                   'sortField' => 'outDate',
		                   'sortOrder' => 'desc'];
		$loadAdditional = empty($loadAdditional) ? 0 : $loadAdditional;
		$startAt        = $loadAdditional * $limitPerCall;

		if ($startAt){
			$params['offset'] = $startAt;
		}
		$history = $this->_doRequest($operation, $params);

		if (!$history){
			return false;
		}

		if ($history->total == 0){
			return [
				'numTitles' => 0,
				'titles'    => []
			];
		}
		$additionalLoadsRequired = false;
		if ($history->total > $startAt + $limitPerCall){
			$additionalLoadsRequired = true;
			$nextRound               = empty($loadAdditional) ? 1 : $loadAdditional + 1;
		}

		$readingHistory = [];
		foreach ($history->entries as $historyEntry){
			$titleEntry = [];
			// make the Pika style bib Id
			preg_match($this->urlIdRegExp, $historyEntry->bib, $bibMatch);
			$x     = $this->getCheckDigit($bibMatch[1]);
			$bibId = '.b' . $bibMatch[1] . $x; // full bib id

			// get the checkout id --> becomes ilsReadingHistoryId
			preg_match($this->urlIdRegExp, $historyEntry->id, $coIdMatch);
			$ilsReadingHistoryId               = $coIdMatch[1];
			$ts                                = strtotime($historyEntry->outDate);
			$titleEntry['checkout']            = $ts;
			$titleEntry['recordId']            = $bibId;
			$titleEntry['ilsReadingHistoryId'] = $ilsReadingHistoryId; // checkout id
			$titleEntry['source']              = $this->accountProfile->recordSource; // Record source for this catalog that the user is attached to)

			// get the rest from the MARC record
			$record = new MarcRecord($this->accountProfile->recordSource . ':' . $bibId);
			if ($record->isValid()){
				$titleEntry['permanentId'] = $record->getPermanentId();
				$titleEntry['title']       = $record->getTitle();
				$titleEntry['author']      = $record->getPrimaryAuthor();
				$titleEntry['format']      = $record->getFormat();
			}else{

				// see if we can get info from the api
				$operation = 'bibs/' . $bibMatch[1];
				$params    = [
					'fields' => 'deleted,title,author,normTitle'
				];
				$bibRes    = $this->_doRequest($operation, $params);
				if (!$bibRes || $bibRes->deleted == true){
					$titleEntry['title']      = '';
					$titleEntry['author']     = '';
					$titleEntry['format']     = '';
					$titleEntry['title_sort'] = '';
				}else{
					$titleEntry['title']      = empty($bibRes->title) ? '' : $bibRes->title;
					$titleEntry['author']     = empty($bibRes->author) ? '' :$bibRes->author;
					$titleEntry['title_sort'] = empty($bibRes->normTitle) ? '' :$bibRes->normTitle;
				}
			}
			$readingHistory[] = $titleEntry;
			// clear out before
			unset($titleEntry);
		}
		$total  = count($readingHistory);
		$return = ['numTitles' => $total,
		           'titles'    => $readingHistory];
		if ($additionalLoadsRequired){
			$return['nextRound'] = $nextRound;
		}

		return $return;
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
	 * _authNameBarcode
	 *
	 * Find a patron by barcode (field b) and match username against API response from patron/find API call that returns
	 * Sierra patron id.
	 *
	 * @param  $username  string   login name
	 * @param  $barcode   string   barcode
	 * @return string|false Returns unique patron id from Sierra on success or false on fail.
	 * @throws ErrorException
	 */
	protected function _authNameBarcode($username, $barcode) {
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
		$username = str_replace('-', ' ', $username);
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
					$valid = true;
				}
				// there's been a bit of uproar at libraries over name matches. To revert behavior to full matches
				// uncomment the else statement below and the above description will work.
				// cf 12-13-2019
				// #D-3416
				// #D-3417
				//else {
					//$valid = false;
					//break;
				//}
			}
			// If a match is found, break outer foreach and valid is true
			if ($valid === true) {
				$this->logger->info('Logging in patron: '. $barcode);
				break;
			}
		}

		// return either false on fail or user sierra id on success.
		if($valid === true) {
			$result = $r->id;
		} else {
			$this->logger->warning('Can not login patron: ' . $barcode . '. Name and barcode do not match.');
			$result = false;
		}
		return $result;
	}

	/**
	 * Validate a barcode and pin
	 *
	 * patrons/validate
	 *
	 * @param string $barcode
	 * @param string $pin
	 * @return string|false Returns patron id on success false on fail.
	 */
	protected function _authBarcodePin($barcode, $pin) {

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

		// check that pin matches database
		$patron = new User();
		$patron->username = $patronId;
		$patron->find(true);
		// if we don't find a patron then new user create it. Will be populated
		if($patron->N == 0) {
			$patron->created      = date('Y-m-d');
			$patron->username     = $patronId;
			$patron->cat_username = $barcode;
			$patron->insert();
		}

		if($patron->cat_password != $pin) {
			$patron->cat_password = $pin;
			$patron->update();
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
	protected function _oAuthToken() {
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
		// $this->logger->info('oAuth URL '.$url);
		// grab clientKey and clientSecret from configArray
		$clientKey    = $this->configArray['Catalog']['clientKey'];
		$clientSecret = $this->configArray['Catalog']['clientSecret'];
		//encode key and secret
		$requestAuth  = base64_encode($clientKey . ':' . $clientSecret);

		$headers = [
			'Host'          => parse_url($url, PHP_URL_HOST),
			'Authorization' => 'Basic ' . $requestAuth,
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'grant_type'    => 'client_credentials'
		];

		$opts = [
			CURLOPT_RETURNTRANSFER => true,
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
			$this->logger->error($message, ['oauth_url'=>$url]);
			throw new ErrorException($message);
		} elseif ($cInfo['http_code'] != 200) { // check the request returned success (HTTP 200)
			$message = 'API Error: '.$c->errorCode.': '.$c->errorMessage;
			$this->apiLastError = $message;
			$this->logger->error($message, ['oauth_url'=>$url]);
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
	 * @param string $operation    The API method to call ie; patrons/find
	 * @param array  $params       Request parameters
	 * @param string $method       Request method
	 * @param null   $extraHeaders Additional headers
	 * @return bool|object         Returns false fail or JSON object
	 * @throws ErrorException
	 */
	protected function _doRequest($operation, $params = array(), $method = "GET", $extraHeaders = null) {
		$this->apiLastError = false;
		// setup headers
		// These headers are common to all Sierra API except token requests.
		$headers = [
			'Host'           => parse_url($this->apiUrl, PHP_URL_HOST),
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
		//$this->logger->debug('API response for ['.$method.']'.$operation, ['method'=>$operation, 'response'=>$r]);
		$c->close();
		return $r;
	}


	protected function _curlOptInOptOut($patron, $optInOptOut = 'OptIn') {
		$c = new Curl();
		// base url for following calls
		$vendorOpacUrl = $this->accountProfile->vendorOpacUrl;

		$headers = [
			"Accept"         => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"  => "max-age=0",
			"Connection"     => "keep-alive",
			"Accept-Charset" => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language"=> "en-us,en;q=0.5",
			"User-Agent"     => "Pika"
		];
		$c->setHeaders($headers);

		$cookie = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts  = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		// first log patron in
		if($this->accountProfile->loginConfiguration == "barcode_pin") {
			$postData = [
				'code' => $patron->cat_username,
				'pin'  => $patron->cat_password
			];
		} else {
			$postData = [
				'name' => $patron->cat_username,
				'code' => $patron->cat_password
			];
		}
		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r = $c->post($loginUrl, $postData);

		if($c->isError()) {
			$c->close();
			return false;
		}

		if(!stristr($r, $patron->cat_username)) {
			// check for cas login. do cas login if possible
			$casUrl = '/iii/cas/login';
			if(stristr($r, $casUrl)) {
				$this->logger->info('Trying cas login.');
				preg_match('|<input type="hidden" name="lt" value="(.*)"|', $r, $m);
				if($m) {
					$postData['lt']       = $m[1];
					$postData['_eventId'] = 'submit';
				} else {
					return false;
				}
				$casLoginUrl = $vendorOpacUrl.$casUrl;
				$r = $c->post($casLoginUrl, $postData);
				if(!stristr($r, $patron->cat_username)) {
					$this->logger->warning('cas login failed.');
					return false;
				}
				$this->logger->info('cas login success.');
			}
		}

		// now we can call the optin or optout url
		$scope = isset($this->configArray['OPAC']['defaultScope']) ? $this->configArray['OPAC']['defaultScope'] : '93';
		$patronId = $this->getPatronId($patron->barcode);
		$optUrl = $vendorOpacUrl . "/patroninfo~S". $scope. "/" . $patronId . "/readinghistory/" . $optInOptOut;

		$c->setUrl($optUrl);
		$r = $c->get();

		if($c->isError()) {
			$c->close();
			return false;
		}

		if($optInOptOut == 'OptIn') {
			if (stristr($r, 'Stop recording')) {
				$success = true;
			} else {
				$success = false;
			}
		} elseif($optInOptOut == 'OptOut') {
			$success = true;
		} else {
			$success = false;
		}

		$c->close();
		return $success;
	}

	/**
	 * Get a variable field value.
	 *
	 * @param  string $tag       The single letter tag to return
	 * @param  array  $varFields The variable fields array
	 * @return false|string      Returns false if the field isn't found or the variable field value
	 */
	protected function getVarField($tag, array $varFields) {
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
	protected function _getBibIdFromItemId($itemId) {
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
	protected function _getPrettyError() {
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

	protected function getCheckDigit($baseId){
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

	public function getMyBookings($patron){

		// Fetch Classic WebPac Bookings page
		$html = $this->_curlLegacy($patron, 'bookings');

		// Parse out Bookings Information
		/** @var MyBooking[] $bookings */
		$bookings = $this->parseBookingsPage($html);

		require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
		foreach ($bookings as &$booking){
			$booking->userDisplayName = $patron->getNameAndLibraryLabel();
			$booking->userId          = $patron->id;

			$recordDriver = new MarcRecord($booking->id);
			if ($recordDriver->isValid()){
				$booking->title         = $recordDriver->getTitle();
				$booking->sortTitle     = $recordDriver->getSortableTitle();
				$booking->author        = $recordDriver->getAuthor();
				$booking->format        = $recordDriver->getFormat();
				$booking->linkUrl       = $recordDriver->getRecordUrl();
				$booking->coverUrl      = $recordDriver->getBookcoverUrl('medium');
				$booking->groupedWorkId = $recordDriver->getGroupedWorkId();
				$booking->ratingData    = $recordDriver->getRatingData();
			}
		}

		return $bookings;
	}

	public function bookMaterial($patron, $recordId, $startDate, $startTime = null, $endDate = null, $endTime = null){
		if (empty($recordId) || empty($startDate)){ // at least these two fields should be required input
			return array('success' => false, 'message' => empty($startDate) ? 'Start Date Required.' : 'Record ID required');
		}
		if (!$startTime){
			$startTime = '8:00am';   // set a default start time if not specified (a morning time)
		}
		if (!$endDate){
			$endDate = $startDate;   // set a default end date to the start date if not specified
		}
		if (!$endTime){
			$endTime = '8:00pm';     // set a default end time if not specified (an evening time)
		}

		// set bib number in format .b{recordNumber}
		$bib = $this->getShortId($recordId->getRecordId());

		$startDateTime = new \DateTime("$startDate $startTime");// create a date with input and set it to the format the ILS expects
		if (!$startDateTime){
			return array('success' => false, 'message' => 'Invalid Start Date or Time.');
		}

		$endDateTime = new \DateTime("$endDate $endTime"); // create a date with input and set it to the format the ILS expects
		if (!$endDateTime){
			return array('success' => false, 'message' => 'Invalid End Date or Time.');
		}

		$bookingUrl = "/webbook?/$bib=&back=";
		// the strange get url parameters ?/$bib&back= is needed to avoid a response from the server claiming a 502 proxy error
		// Scope appears to be unnecessary at this point.

		// Get pagen from form
		/** @var Curl $c */
		$c            = $this->_curlLegacy($patron, $bookingUrl, null, false);
		$curlResponse = $c->getResponse();

		if (preg_match('/You cannot book this material/i', $curlResponse)){
			return array(
				'success' => false,
				'message' => 'Sorry, you cannot schedule this item.'
			);
		}

		$tag               = 'input';
		$tag_pattern       =
			'@<(?P<tag>' . $tag . ')           # <tag
      (?P<attributes>\s[^>]+)?       # attributes, if any
            \s*/?>                   # /> or just >, being lenient here
            @xsi';
		$attribute_pattern =
			'@
        (?P<name>\w+)                         # attribute name
        \s*=\s*
        (
            (?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)    # a quoted value
                                    |                           # or
            (?P<value_unquoted>[^\s"\']+?)(?:\s+|$)             # an unquoted value (terminated by whitespace or EOF)
        )
        @xsi';

		if (preg_match_all($tag_pattern, $curlResponse, $matches)){
			foreach ($matches['attributes'] as $attributes){
				if (preg_match_all($attribute_pattern, $attributes, $attributeMatches)){
					$search = array_flip($attributeMatches['name']); //flip so that index can be used to get actual names & values of attributes
					if (array_key_exists('name', $search)){ // find name attribute
						$attributeName  = trim($attributeMatches['value_quoted'][$search['name']], '"\'');
						$attributeValue = trim($attributeMatches['value_quoted'][$search['value']], '"\'');
						if ($attributeName == 'webbook_pagen'){
							$pageN = $attributeValue;
						}elseif ($attributeName == 'webbook_loc'){
							$loc = $attributeValue;
						}
					}
				}
			}
		}

		$patronId = $this->getPatronId($patron); // username seems to be the patron Id

		$post = array(
			'webbook_pnum'        => $patronId,
			'webbook_pagen'       => empty($pageN) ? '2' : $pageN, // needed, reading from screen scrape; 2 or 4 are the only values i have seen so far. plb 7-16-2015
			'webbook_bgn_Month'   => $startDateTime->format('m'),
			'webbook_bgn_Day'     => $startDateTime->format('d'),
			'webbook_bgn_Year'    => $startDateTime->format('Y'),
			'webbook_bgn_Hour'    => $startDateTime->format('h'),
			'webbook_bgn_Min'     => $startDateTime->format('i'),
			'webbook_bgn_AMPM'    => $startDateTime->format('H') > 11 ? 'PM' : 'AM',
			'webbook_end_n_Month' => $endDateTime->format('m'),
			'webbook_end_n_Day'   => $endDateTime->format('d'),
			'webbook_end_n_Year'  => $endDateTime->format('Y'),
			'webbook_end_n_Hour'  => $endDateTime->format('h'),
			'webbook_end_n_Min'   => $endDateTime->format('i'),
			'webbook_end_n_AMPM'  => $endDateTime->format('H') > 11 ? 'PM' : 'AM', // has to be uppercase for the screen scraping
			'webbook_note'        => '', // the web note doesn't seem to be displayed to the user any where after submit
		);
		if (!empty($loc)){
			// if we have this info add it, don't include otherwise.
			$post['webbook_loc'] = $loc;
		}
		$curlResponse = $c->post($bookingUrl, $post);
		if ($c->error){
			global $logger;
			$logger->log('Curl error during booking, code: ' . $c->getErrorMessage(), PEAR_LOG_WARNING);
			return array(
				'success' => false,
				'message' => 'There was an error communicating with the circulation system.'
			);
		}

		// Look for Success Messages
		$numMatches = preg_match('/<span.\s?class="bookingsConfirmMsg">(?P<success>.+?)<\/span>/', $curlResponse, $matches);
		if ($numMatches){
			return array(
				'success' => true,
				'message' => is_array($matches['success']) ? implode('<br>', $matches['success']) : $matches['success']
			);
		}

		// Look for Account Error Messages
		// <h1>There is a problem with your record.  Please see a librarian.</h1>
		$numMatches = preg_match('/<h1>(?P<error>There is a problem with your record\..\sPlease see a librarian.)<\/h1>/', $curlResponse, $matches);
		// ?P<name> syntax will creates named matches in the matches array
		if ($numMatches){
			return array(
				'success' => false,
				'message' => is_array($matches['error']) ? implode('<br>', $matches['error']) : $matches['error'],
				'retry'   => true, // communicate back that we think the user could adjust their input to get success
			);
		}


		// Look for Error Messages
		$numMatches = preg_match('/<span.\s?class="errormessage">(?P<error>.+?)<\/span>/is', $curlResponse, $matches);
		// ?P<name> syntax will creates named matches in the matches array
		if ($numMatches){
			return array(
				'success' => false,
				'message' => is_array($matches['error']) ? implode('<br>', $matches['error']) : $matches['error'],
				'retry'   => true, // communicate back that we think the user could adjust their input to get success
			);
		}

		// Catch all Failure
		global $logger;
		$logger->log('Unkown error during booking', PEAR_LOG_ERR);
		return array(
			'success' => false,
			'message' => 'There was an unexpected result while scheduling your item'
		);
	}

	/**
	 * Cancel a Booking
	 *
	 * @param User $patron The user the booking belongs to
	 * @param string|array $cancelIds The Id or array of Ids needed to cancel the booking
	 * @return array
	 */
	public function cancelBookedMaterial($patron, $cancelIds){
		//NOTE the library's scope for the classic OPAC is needed to delete bookings!
		if (empty($cancelIds)){
			return array('success' => false, 'message' => 'Item ID required');
		}elseif (!is_array($cancelIds)){
			$cancelIds = array($cancelIds);  // for a single item
		}

		$post = array(
			'canbooksome' => 'YES'
			//			'requestCanBookSome' => 'requestCanBookSome',
		);

		foreach ($cancelIds as $i => $cancelId){
			if (is_numeric($i)){
				$post['canbook' . $i] = $cancelId; // recreating the cancelName variable canbookX
			}else{
				$post[$i] = $cancelId; // when cancelName is passed back
			}
		}


		$html = $this->_curlLegacy($patron, 'bookings', $post);

		$errors = array();
		if (!$html){
			return array(
				'success' => false,
				'message' => 'There was an error communicating with the circulation system.'
			);
		}

		// check the bookings again, to verify that they were in fact really cancelled.
		if (!empty($html)){
			foreach ($cancelIds as $cancelId){
				if (strpos($html, $cancelId) !== false){ // looking for this booking in results, meaning it failed to cancel.
					if (empty($errors)){
						$bookings = $this->parseBookingsPage($html); // get current bookings on first error
					}
					foreach ($bookings as $booking){
						if ($booking->cancelValue == $cancelId){
							break;
						}
					}
//					$errors[$booking['cancelValue']] = 'Failed to cancel scheduled item <strong>' . $booking['title'] . '</strong> from ' . strftime('%b %d, %Y at %I:%M %p', $booking['startDateTime']) . ' to ' . strftime('%b %d, %Y at %I:%M %p', $booking['endDateTime']);
					// Time included
					$errors[$booking->cancelValue] = 'Failed to cancel scheduled item <strong>' . $booking->title . '</strong> from ' . strftime('%b %d, %Y', $booking->startDateTime) . ' to ' . strftime('%b %d, %Y', $booking->endDateTime);
					// Dates only

				}

			}
		}

		if (empty($errors)){
			return array(
				'success' => true,
				'message' => 'Your scheduled item' . (count($cancelIds) > 1 ? 's were' : ' was') . ' successfully canceled.'
			);
		}else{
			return array(
				'success' => false,
				'message' => $errors
			);
		}
	}

	public function cancelAllBookedMaterial($patron){
		//NOTE the library's scope for the classic OPAC is needed to delete bookings!
		$post = array(
			'canbookall' => 'YES'
		);

		$html = $this->_curlLegacy($patron, 'bookings', $post);

		$errors = array();
		if (!$html){
			return array(
				'success' => false,
				'message' => 'There was an error communicating with the circulation system.'
			);
		}

		// get the bookings again, to verify that they were in fact really cancelled.
		if (!strpos($html, 'No bookings found')){ // 'No bookings found' is our success phrase
			$bookings = $this->parseBookingsPage($html);
			if (!empty($bookings)){ // a booking wasn't canceled
				foreach ($bookings as $booking){
//					$errors[$booking['cancelValue']] = 'Failed to cancel scheduled item <strong>' . $booking['title'] . '</strong> from ' . strftime('%b %d, %Y at %I:%M %p', $booking['startDateTime']) . ' to ' . strftime('%b %d, %Y at %I:%M %p', $booking['endDateTime']);
					// Time included
					$errors[$booking['cancelValue']] = 'Failed to cancel scheduled item <strong>' . $booking['title'] . '</strong> from ' . strftime('%b %d, %Y', $booking['startDateTime']) . ' to ' . strftime('%b %d, %Y', $booking['endDateTime']);
					// Dates only
				}
			}
		}

		if (empty($errors)){
			return array(
				'success' => true,
				'message' => 'Your scheduled items were successfully canceled.'
			);
		}else{
			return array(
				'success' => false,
				'message' => $errors
			);
		}
	}


	/**
	 *  Fetch the calendar to use with scheduling a booking
	 *
	 * @param User $patron
	 * @param \SourceAndId $sourceAndId The record to book
	 *
	 * @return string  An HTML table
	 */
	public function getBookingCalendar($patron, $sourceAndId){
		// Create Hourly Calendar URL
		$bib       = $this->getShortId($sourceAndId->getRecordId());
		$scope     = $this->getLibraryScope();
		$timestamp = time(); // the webpac hourly calendar give 30 (maybe 31) days worth from the given timestamp.
		// Since today is the soonest a user could book, let's get from today
		$hourlyCalendarUrl = "webbook~S$scope?/$bib/hourlycal$timestamp=&back=";

		//Can only get the hourly calendar html by submitting the bookings form
		$post                   = array(
			'webbook_pnum'        => $this->getPatronId($patron),
			'webbook_pagen'       => '2', // needed, reading from screen scrape; 2 or 4 are the only values i have seen so far. plb 7-16-2015
			//			'refresh_cal' => '0', // not needed
			'webbook_bgn_Month'   => '',
			'webbook_bgn_Day'     => '',
			'webbook_bgn_Year'    => '',
			'webbook_bgn_Hour'    => '',
			'webbook_bgn_Min'     => '',
			'webbook_bgn_AMPM'    => '',
			'webbook_end_n_Month' => '',
			'webbook_end_n_Day'   => '',
			'webbook_end_n_Year'  => '',
			'webbook_end_n_Hour'  => '',
			'webbook_end_n_Min'   => '',
			'webbook_end_n_AMPM'  => '',
			'webbook_note'        => '',
		);
		$HourlyCalendarResponse = $this->_curlLegacy($patron, $hourlyCalendarUrl, $post, false);

		// Extract Hourly Calendar from second response
		if (preg_match('/<div class="bookingsSelectCal">.*?<table border>(?<HourlyCalendarTable>.*?<\/table>.*?)<\/table>.*?<\/div>/si', $HourlyCalendarResponse, $table)){
			// Modify Calendar html for our needs
			$calendarTable = str_replace(array('unavailable', 'available', 'closed', 'am'), array('active', 'success', 'active', ''), $table['HourlyCalendarTable']);
			$calendarTable = preg_replace('#<th.*?>.*?</th>#s', '<th colspan="2">Date</th><th colspan="17">Time <small>(6 AM - 11 PM)&nbsp; Times in green are Available.</small></th>', $calendarTable); // cut out the table header with the unwanted links in it.
			$calendarTable = '<table class="table table-condensed">' . $calendarTable . '</table>'; // add table tag with styling attributes
			return $calendarTable;
		}
	}

	/**
	 * For Bookings Calls
	 * @param $patron
	 * @param $pageToCall
	 * @param array $postParams
	 * @param bool $patronAction
	 * @return bool|Curl|mixed
	 * @throws ErrorException
	 */
	private function _curlLegacy($patron, $pageToCall, $postParams = array(), $patronAction = true){

		$c = new Curl();

		// base url for following calls
		$vendorOpacUrl = $this->accountProfile->vendorOpacUrl;

		$headers = [
			"Accept"          => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"   => "max-age=0",
			"Connection"      => "keep-alive",
			"Accept-Charset"  => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language" => "en-us,en;q=0.5",
			"User-Agent"      => "Pika"
		];
		$c->setHeaders($headers);

		$cookie   = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		// first log patron in
		$postData = [
			'name' => $patron->cat_username,
			'code' => $patron->cat_password
		];
		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r        = $c->post($loginUrl, $postData);

		if ($c->isError()){
			$c->close();
			return false;
		}

		if (!stristr($r, $patron->cat_username)){
			$c->close();
			return false;
		}

		$scope    = $this->getLibraryScope(); // IMPORTANT: Scope is needed for Bookings Actions to work
		$patronId = $this->getPatronId($patron->barcode);
		$optUrl   = $patronAction ? $vendorOpacUrl . '/patroninfo~S' . $scope . '/' . $patronId . '/' . $pageToCall
			: $vendorOpacUrl . '/' . $pageToCall;
		// Most curl calls are patron interactions, getting the bookings calendar isn't

		$c->setUrl($optUrl);
		if (!empty($postParams)){
			$r = $c->post($postParams);
		}else{
			$r = $c->get($optUrl);
		}

		if ($c->isError()){
			return false;
		}

		if (stripos($pageToCall, 'webbook?/') !== false){
			// Hack to complete booking a record
			return $c;
		}
		return $r;
	}

	/**
	 * @param String $html Html text of classic opac booking page for the patron's account
	 * @return array
	 */
	private function parseBookingsPage($html){
		$bookings = array();

		// Table Rows for each Booking
		if (preg_match_all('/<tr\\s+class="patFuncEntry">(?<bookingRow>.*?)<\/tr>/si', $html, $rows, PREG_SET_ORDER)){
			foreach ($rows as $index => $row){ // Go through each row

				// Get Record/Title
				if (!preg_match('/.*?<a href=\\"\/record=(?<recordId>.*?)(?:~S\\d{1,3})\\">(?<title>.*?)<\/a>.*/', $row['bookingRow'], $matches)){
					$this->logger->error("Failed to parse My Bookings page from classic");
				}

				$shortId = $matches['recordId'];
				$bibId   = '.' . $shortId . $this->getCheckDigit($shortId);
				$title   = strip_tags($matches['title']);

				// Get From & To Dates
				$startTimestamp = null;
				$endTimestamp   = null;
				if (preg_match_all('/.*?<td nowrap class=\\"patFuncBookDate\\">(?<bookingDate>.*?)<\/td>.*/', $row['bookingRow'], $matches, PREG_SET_ORDER)){
					$startDateTime = trim($matches[0]['bookingDate']); // time component looks ambiguous
					$endDateTime   = trim($matches[1]['bookingDate']);

					// pass as timestamps so that the SMARTY template can handle it.
					$dateTimeObject = date_create_from_format('m-d-Y g:i', $startDateTime);
					if (!$dateTimeObject){
						$dateTimeObject = date_create_from_format('m-d-Y', $startDateTime);
					}
					if ($dateTimeObject){
						$startTimestamp = date_timestamp_get($dateTimeObject);
					}
					$dateTimeObject = date_create_from_format('m-d-Y g:i', $endDateTime);
					if (!$dateTimeObject){
						$dateTimeObject = date_create_from_format('m-d-Y', $endDateTime);
					}
					if ($dateTimeObject){
						$endTimestamp = date_timestamp_get($dateTimeObject);
					}
				}

				// Get Status
				if (preg_match('/.*?<td nowrap class=\\"patFuncStatus\\">(?<status>.*?)<\/td>.*/', $row['bookingRow'], $matches)){
					$status = ($matches['status'] == '&nbsp;') ? '' : $matches['status']; // at this point, I don't know what status we will ever see
				}else{
					$status = '';
				}

				// Get Cancel Ids
//				<td class="patFuncMark"><input type="CHECKBOX" name="canbook0" id="canbook0" value="i9459912F08-17-20154:00T08-17-20154:00" /></td>
				if (preg_match('/.*?<input type="CHECKBOX".*?name=\\"(?<cancelName>.*?)\\".*?value=\\"(?<cancelValue>.*?)\\" \/>.*/', $row['bookingRow'], $matches)){
					$cancelName  = $matches['cancelName'];
					$cancelValue = $matches['cancelValue'];
				}else{
					$cancelValue = $cancelName = '';
				}

				$booking                = new MyBooking();
				$booking->id            = $bibId;
				$booking->title         = $title;
				$booking->startDateTime = $startTimestamp;
				$booking->endDateTime   = $endTimestamp;
				$booking->status        = $status;
				$booking->cancelName    = $cancelName;
				$booking->cancelValue   = $cancelValue;

				$bookings[] = $booking;

			}
		}
		return $bookings;
	}

	/**
	 * Classic OPAC scope for legacy screen scraping calls
	 * @param bool $checkLibraryRestrictions  Whether or not to condition the use of Sierra OPAC scope by the library setting $restrictSearchByLibrary;
	 * @return mixed|string
	 */
	protected function getLibraryScope($checkLibraryRestrictions = false){

		//Load the holding label for the branch where the user is physically.
		$searchLocation = Location::getSearchLocation();
		if (!empty($searchLocation->scope)){
			return $searchLocation->scope;
		}

		$searchLibrary = Library::getSearchLibrary();
		if (!empty($searchLibrary->scope)){
			if (!$checkLibraryRestrictions || $searchLibrary->restrictSearchByLibrary){
			return $searchLibrary->scope;
		}
		}
		return $this->getDefaultScope();
	}

	protected function getDefaultScope(){
		global $configArray;
		return isset($configArray['OPAC']['defaultScope']) ? $configArray['OPAC']['defaultScope'] : '93';
	}

	/**
	 * Taken from the class MarcRecord method getShortId.
	 *
	 * @param string $longId III record Id with a trailing check digit included
	 * @return mixed|string   the initial dot & the trailing check digit removed
	 */
	protected static function getShortId($longId){
		$shortId = str_replace('.b', 'b', $longId);
		$shortId = substr($shortId, 0, strlen($shortId) - 1);
		return $shortId;
	}


	/**
	 * Legacy Screen Scraping to get holding_record information for periodicals
	 *
	 * @param $recordId
	 * @param $checkInGridId
	 * @return array
	 * @throws ErrorException
	 */
	function getCheckInGrid($recordId, $checkInGridId){
		//Issue summaries are loaded from the main record page.
		$sourceAndId = new \SourceAndId($recordId);

		$id_         = $this->getShortId($sourceAndId->getRecordId());
		$host        = $this->accountProfile->vendorOpacUrl;
		$branchScope = $this->getLibraryScope();
		$url         = $host . "/search~S{$branchScope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/$checkInGridId&FF=1,0,";
		$c           = new Curl();
		$headers     = [
			"Accept"          => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"   => "max-age=0",
			"Connection"      => "keep-alive",
			"Accept-Charset"  => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language" => "en-us,en;q=0.5",
			"User-Agent"      => "Pika"
		];
		$c->setHeaders($headers);

		$cookie   = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		$result = $c->get($url);

		if ($c->isError()){
			return array();
		}

		//Extract the actual table
		$checkInData = array();
		if (preg_match('/<table  class="checkinCardTable">(.*?)<\/table>/s', $result, $matches)){
			$checkInTable = trim($matches[1]);

			//Extract each item from the grid.
			preg_match_all('/.*?<td valign="top" class="(.*?)">(.*?)<\/td>/s', $checkInTable, $checkInCellMatch, PREG_SET_ORDER);
			for ($matchi = 0;$matchi < count($checkInCellMatch);$matchi++){
				$cellData             = trim($checkInCellMatch[$matchi][2]);
				$checkInCell          = array();
				$checkInCell['class'] = $checkInCellMatch[$matchi][1];
				//Load issue date, status, date received, issue number, copies received
				if (preg_match('/(.*?)<br\\s*\/?>.*?<span class="(?:.*?)">(.*?)<\/span>.*?on (\\d{1,2}-\\d{1,2}-\\d{1,2})<br\\s*\/?>(.*?)(?:<!-- copies --> \\((\\d+) copy\\))?<br\\s*\/?>/s', $cellData, $matches)){
					$checkInCell['issueDate']   = trim($matches[1]);
					$checkInCell['status']      = trim($matches[2]);
					$checkInCell['statusDate']  = trim($matches[3]);
					$checkInCell['issueNumber'] = trim($matches[4]);
					if (isset($matches[5])){
						$checkInCell['copies'] = trim($matches[5]);
					}
				}
				$checkInData[] = $checkInCell;
			}
		}
		return $checkInData;
	}

	/**
	 * Legacy Screen Scraping to get holding_record information for periodicals
	 *
	 * If there are issue summaries available, it will return them in an array.
	 * With holdings below them.
	 *
	 * If there are no issue summaries, null will be returned from the summary.
	 *
	 * @param string $recordId
	 * @return array|null - array or null
	 * @throws ErrorException
	 */
	public function getIssueSummaries($recordId){
		$scope         = $this->getLibraryScope(true); // Use library scope if searching is restricted to the library
		$id_           = $this->getShortId($recordId);
		$host          = $this->accountProfile->vendorOpacUrl;
		$c             = new Curl();
		$headers       = [
			"Accept"          => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"   => "max-age=0",
			"Connection"      => "keep-alive",
			"Accept-Charset"  => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language" => "en-us,en;q=0.5",
			"User-Agent"      => "Pika"
		];
		$cookie   = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setHeaders($headers);
		$c->setOpts($curlOpts);

		//Legacy process would load this page first. The results weren't used, and it doesn't appear required to read the URL further down
//		$url                       = $host . "/search~S{$scope}/." . $id_ . "/." . $id_ . "/1,1,1,B/holdings~" . $id_;
//		$issuesSummaryHoldingsInfo = $c->get($url);  // Not used any where
//		if ($c->isError()){
//			return null;
//		}

		$url                       = $host . "/search~S{$scope}/." . $id_ . "/." . $id_ . "/1,1,1,B/frameset~" . $id_;
		$issuesSummaryFrameSetInfo = $c->get($url);
		if ($c->isError()){
			return null;
}

		if (preg_match('/class\\s*=\\s*\\"bibHoldings\\"/s', $issuesSummaryFrameSetInfo)){
			//There are issue summaries available
			//Extract the table with the holdings
			$issueSummaries = array();
			$matches        = array();
			if (preg_match('/<table\\s.*?class=\\"bibHoldings\\">(.*?)<\/table>/s', $issuesSummaryFrameSetInfo, $matches)){
				$issueSummaryTable = trim($matches[1]);
				//Each holdingSummary begins with a holdingsDivider statement
				$summaryMatches = explode('<tr><td colspan="2"><hr  class="holdingsDivider" /></td></tr>', $issueSummaryTable);
				if (count($summaryMatches) > 1){
					//Process each match independently
					foreach ($summaryMatches as $summaryData){
						$summaryData = trim($summaryData);
						if (strlen($summaryData) > 0){
							//Get each line within the summary
							$issueSummary         = array();
							$issueSummary['type'] = 'issueSummary';
							$summaryLines         = array();
							preg_match_all('/<tr\\s*>(.*?)<\/tr>/s', $summaryData, $summaryLines, PREG_SET_ORDER);
							for ($matchi = 0;$matchi < count($summaryLines);$matchi++){
								$summaryLine = trim(str_replace('&nbsp;', ' ', $summaryLines[$matchi][1]));
								$summaryCols = array();
								if (preg_match('/<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>/s', $summaryLine, $summaryCols)){
									$labelOriginal = $label = trim($summaryCols[1]);
									$value = trim(strip_tags($summaryCols[2]));
									//Check to see if this has a link to a check-in grid.
									if (preg_match('/.*?<a href="(.*?)">.*/s', $label, $linkData)){
										//Parse the check-in id
										$checkInLink = $linkData[1];
										if (preg_match('/\/search~S\\d+\\?\/.*?\/.*?\/.*?\/(.*?)&.*/', $checkInLink, $checkInGridInfo)){
											$issueSummary['checkInGridId'] = $checkInGridInfo[1];
										}
									}
									//Convert to camel case
									$label = lcfirst(preg_replace('/[^\\w]/', '', strip_tags($label)));
									if ($label == 'location'){
										//Try to trim the courier code if any
										if (preg_match('/(.*?)\\sC\\d{3}\\w{0,2}$/', $value, $locationParts)){
											$value = $locationParts[1];
										}
									}elseif ($label == 'holdings'){
										//Change the label to avoid conflicts with actual holdings
										$label = 'holdingStatement';
									}
									$issueSummary[$label] = $value;
								}
							}
							$issueSummaries[$issueSummary['location'] . count($issueSummaries)] = $issueSummary;
						}
					}
				}
			}

			return $issueSummaries;
		}
		return null;
	}


}
