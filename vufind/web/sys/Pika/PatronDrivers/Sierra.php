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
/**
 *
 * Class Sierra
 *
 * Methods needed for completing patron actions in the Seirra ILS
 *
 * This class implements the Sierra REST Patron API for patron interactions:
 *    https://sandbox.iii.com/iii/sierra-api/swagger/index.html#!/patrons
 *
 *  Barcodes are now stored in barcode field
 *  DEPRECATED: ~~Currently, in the database cat_password or cat_username represent the patron barcodes.~~
 *
 *
 *  For auth type barcode_pin
 *    barcode stored in barcode field
 *    pin is stored in cat_password field (this field can be removed when using the api)
 *
 *  For auth type name_barcode
 *    barcode is stored in barcode field
 *    name is stored in cat_username field
 *
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
use RecordDriverFactory;
use User;
use ReadingHistoryEntry;
use PinReset;
use PHPMailer\PHPMailer\PHPMailer;

require_once ROOT_DIR . '/sys/Account/ReadingHistoryEntry.php';
require_once ROOT_DIR . '/sys/Account/PinReset.php';

class Sierra extends PatronDriverInterface implements \DriverInterface {

	// @var Pika/Memcache instance
	public  $cache;
	// @var $logger Pika/Logger instance
	protected $logger;

	protected $configArray;
	// ----------------------
	/* @var $oAuthToken oAuth2Token */
	protected $oAuthToken;
	/* @var $apiLastError false|string false if no error or last error message */
	protected $apiLastError = false;
	protected $apiLastErrorForPatron = false;
	/** @var  AccountProfile $accountProfile */
	public $accountProfile;
	/* @var $patronBarcode string The patrons barcode */
	protected $patronBarcode;
	/* @var $apiUrl string The url for the Sierra API */
	protected $apiUrl;
	/* @var $tokenUrl string The url for token */
	protected $tokenUrl;
	/* @var $aboutUrl string The url for "about" info that includes version numbers */
	protected $aboutUrl;
	// many ids come from url. example: https://sierra.marmot.org/iii/sierra-api/v5/items/5130034
	protected $urlIdRegExp = "/.*\/(\d*)$/";


	/**
	 * @param \AccountProfile $accountProfile
	 */
	public function __construct($accountProfile) {
		global $configArray;

		$this->configArray    = $configArray;
		$this->accountProfile = $accountProfile;
		$this->logger = new Logger(__CLASS__);

		$cache       = initCache();
		$this->cache = new Cache($cache);
		// build the api url
		// JIC strip any trailing slash and spaces.
		$baseApiUrl     = trim($accountProfile->patronApiUrl, '/ ');
		$apiUrl         = $baseApiUrl . '/iii/sierra-api/v' . $configArray['Catalog']['api_version'] . '/';
		$tokenUrl       = $baseApiUrl . '/iii/sierra-api/token';
		$aboutUrl       = $baseApiUrl . '/iii/sierra-api/about';

		$this->apiUrl   = $apiUrl;
		$this->tokenUrl = $tokenUrl;
		$this->aboutUrl = $aboutUrl;
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
	public function getMyCheckouts($patron, $linkedAccount = false) {

		$patronCheckoutsCacheKey = $this->cache->makePatronKey('checkouts', $patron->id);
		if(!$linkedAccount) {
			if($patronCheckouts = $this->cache->get($patronCheckoutsCacheKey)) {
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
        
				$checkout['checkoutSource'] =  $this->accountProfile->recordSource; //TODO: this might be a bad idea for ILL items for reading history
				$checkout['id']             = $checkoutId;
				$checkout['dueDate']        = strtotime($entry->dueDate);
				$checkout['checkoutDate']   = strtotime($entry->outDate);
				$checkout['renewCount']     = $entry->numberOfRenewals;
				$checkout['recordId']       = 0;
				$checkout['renewIndicator'] = $checkoutId;
//				$checkout['renewMessage']   = '';
				$checkout['coverUrl']       = $coverUrl;
				$checkout['barcode']        = $entry->barcode;
				$checkout['request']        = $entry->callNumber ?? null;
				$checkout['author']         = $titleAndAuthor['author'];
				$checkout['title']          = $titleAndAuthor['title'];
				$checkout['title_sort']     = $titleAndAuthor['sort_title'];
				$checkout['canrenew']       = true;

				$checkouts[] = $checkout;
				unset($checkout);
				continue;
			}

			// grab the bib id and Pika-tize it
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
			$checkout['barcode']        = $entry->barcode ?? '';
			$checkout['itemid']         = $itemId;
			$checkout['canrenew']       = true;
			$checkout['renewIndicator'] = $checkoutId;
			$checkout['renewMessage']   = '';
			if (!isset($entry->barcode)){
				// On occasion we see a checkout missing the item barcode when we can see that the item does in fact have a barcode
				$this->logger->error('Sierra Checkout missing barcode for user id '. $patronId . ' item Id '. $itemId, [$entry]);
			}
			if (!empty($entry->callNumber)){
				// Add call number value for internal ILL processing for Northern Waters
				$checkout['_callNumber'] = $entry->callNumber;
			}
			$recordDriver = new MarcRecord($this->accountProfile->recordSource . ':' . $bibId);
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
				$checkout['coverUrl']      = '';
				$checkout['groupedWorkId'] = '';
				$checkout['format']        = 'Unknown';
				$checkout['author']        = '';
			}

			$checkouts[] = $checkout;
			unset($checkout);
		}

		if(!$linkedAccount) {
			$this->cache->set($patronCheckoutsCacheKey, $checkouts, $this->configArray['Caching']['user']);
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
		$patronCheckoutsCacheKey = $this->cache->makePatronKey('checkouts', $patron->id);
		$this->cache->delete($patronCheckoutsCacheKey);

		$operation = 'patrons/checkouts/'.$checkoutId.'/renewal';

		$r = $this->_doRequest($operation, [], 'POST');
		if(!$r) {
			$message = $this->_getPrettyError();
			if(stristr($message, '500 internal')) {
				$message = "An error occured.";
			}
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
	public function patronLogin($username, $password, $validatedViaSSO = false){
		// check patron credentials depending on login config.
		// the returns from _auth methods should be either a sierra patron id or false.
		// we replace the curly quote with a straight quote in usernames to account for iphone smart punctuation.
		$username = str_replace("’", "'", trim($username));
		$password = trim($password);

		if ($validatedViaSSO){
			// When validated via CAS both $userName and $password as set to the patron's library card, which is what the
			// CAS returns as the user id. See $casUsername in UserAccount
			$this->patronBarcode = $username;
			$patronId            = $this->getPatronId($this->patronBarcode);
			$this->logger->debug("Sierra ID is $patronId for CAS validated user $username ");
		}else{
			if ($this->accountProfile->usingPins()){
				$this->patronBarcode = $username;
				$patronId            = $this->_authBarcodePin($username, $password);
			}else{
				$barcode             = $password;
				$this->patronBarcode = $barcode;
				$patronId            = $this->_authNameBarcode($username, $password);
				// check last api error for duplicate barcodes
				if (stristr($this->apiLastError, 'Duplicate patrons found')){
					// use the /patron/query endpoint to get user ids with barcode.
					$operation = 'patrons/query?offset=0&limit=3';
					$payload   = '{"target": {' .
						'"record": {"type": "patron"},' .
						'"field": {"tag": "b"}},' .
						'"expr": {' .
						'"op": "equals",' .
						'"operands": [ "' . $barcode . '" ]}}';
					$r2        = $this->_doRequest($operation, $payload, 'POST');
					if (!$r2){
						return false;
					}
					// get the sierra ids for the patron/query
					foreach ($r2->entries as $entry){
						$sPID = preg_match($this->urlIdRegExp, $entry->link, $m);
						if ($this->_authPatronIdName($m[1], $username)){
							return $this->getPatron($m[1]);
						}
					}
				}
			}
		}
		// can't find patron
		if (!$patronId){
			$msg = 'Failed to get patron id from Sierra API.';
			$this->logger->debug($msg, ['barcode' => $this->patronBarcode]);
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
	 * @param int $sierraPatronId Unique Sierra patron id
	 * @param bool $noCache true to ignore cached user object
	 * @return User|null
	 * @throws InvalidArgumentException
	 * @throws ErrorException
	 */
	public function getPatron($sierraPatronId, $noCache=false){
		if (!empty($sierraPatronId)){
			$createPatron = false;
			$updatePatron = false;// find user pika id-- needed for cache key
			// the username db field stores the sierra patron id. we'll use that to determine if the user exists.
			// Check for a cached user object
			$patron = new User(); //		$patron->whereAdd("ilsUserId = '{$patronId}'", 'OR');
			//		$patron->whereAdd("username = '{$patronId}'", 'OR'); // if ilsUserId can't be found fall back to username //TODO: temporary, username column is deprecated
			$patron->ilsUserId = $sierraPatronId;
			if ($patron->find(true) && $patron->N != 0){
				if (!$noCache){
					$patronObjectCacheKey = $this->cache->makePatronKey('patron', $patron->id);
					if ($pObj = $this->cache->get($patronObjectCacheKey)){
						$this->logger->info('Found patron in memcache: ' . $patronObjectCacheKey);
						return $pObj;
					}
				}
			}// grab everything from the patron record the api can provide.
			$params    = [
				'fields' => 'names,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,barcodes,patronCodes,createdDate,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate'
			];
			$operation = 'patrons/' . $sierraPatronId;
			$pInfo     = $this->_doRequest($operation, $params);
			if (!$pInfo){
				return null;
			}// Check to see if the user exists in the database
			// does the user exist in database?
			if (!$patron || $patron->N == 0){
				$this->logger->debug('Patron does not exist in Pika database.', ['Sierra ID' => $sierraPatronId]);
				$createPatron = true;
			}// Find the right barcode
			// we need to monkey with the barcodes. barcodes can change!
			// self registered users may have something in the api response that looks like this
			// -- before getting a physical card
			// $pInfo->barcodes['', '201975'] (this is for some marmot online registered accounts where the 2nd barcode is the Sierra id for the barcode, sometimes)
			// -- after getting a physical card
			// $pInfo->barcodes['56369856985', '201975']
			// so we need to look for both barcodes and determine if the temp barcode needs updated to the permanent one
			$barcode = '';
			if (count($pInfo->barcodes) > 1){
				// if the first barcode is set this should be the permanent barcode.
				if ($pInfo->barcodes[0] != ''){
					$barcode = $pInfo->barcodes[0];
				}else{
					if ($pInfo->barcodes[1] != ''){
						$barcode = $pInfo->barcodes[1];
					}
				}
			}elseif (count($pInfo->barcodes)){
				if ($pInfo->barcodes[0] != ''){
					$barcode = $pInfo->barcodes[0];
				}else{
					$this->logger->error("Sierra user id $sierraPatronId did not return a barcode");
				}
			}elseif (!empty($this->patronBarcode)){
				// Since Sacramento's student Id's aren't treated as barcodes, we have to ignore the barcodes array from the API
				$barcode = $this->patronBarcode;
			}// check all the places barcodes are stored and determine if they need updated.
			$patron->source = $this->accountProfile->name;
			if ($patron->barcode != $barcode){
				$updatePatron    = true;
				$patron->barcode = $barcode;
			}// Checks; make sure patron info from sierra matches database. update if needed.
			// ilsUserId
			$ilsUserId = $pInfo->id;
			if ($ilsUserId != $patron->ilsUserId){
				$patron->ilsUserId = $ilsUserId;
				$updatePatron      = true;
			}// check patron type
			if ((int)$pInfo->patronType !== (int)$patron->patronType){
				$updatePatron       = true;
				$patron->patronType = $pInfo->patronType;
			}// check names
			if (!$this->accountProfile->usingPins()){
				if ($patron->cat_username != $pInfo->names[0]){
					$updatePatron         = true;
					$patron->cat_username = $pInfo->names[0];
				}
			}
			if (stristr($pInfo->names[0], ',')){
				// find a comma-- assume the name is in form last, first middle
				$nameParts = explode(',', $pInfo->names[0]);
				$firstName = trim($nameParts[1]);
				$lastName  = trim($nameParts[0]);

			}else{
				// only spaces --assume last name is last
				$nameParts = explode(' ', $pInfo->names[0]);
				// get the last index
				$countNameParts = count($nameParts) - 1;
				$lastName       = $nameParts[$countNameParts];
				if ($countNameParts >= 1){
					unset($nameParts[$countNameParts]);
					$firstName = implode(' ', $nameParts);
				}else{
					$firstName = '';
				}
			}
			if ($firstName != $patron->firstname || $lastName != $patron->lastname){
				$updatePatron      = true;
				$patron->firstname = $firstName;
				$patron->lastname  = $lastName;
				// empty display name so it will reset to new name
				$patron->displayName = '';
			}// Check email
			// email is returned as array from sierra api
			if ((isset($pInfo->emails) && !empty($pInfo->emails)) && $pInfo->emails[0] != $patron->email){
				$updatePatron  = true;
				$patron->email = $pInfo->emails[0];
			}elseif ((empty($pInfo->emails) || !isset($pInfo->emails))){
				// Check for empty email-- update db even if empty
				$updatePatron  = true;
				$patron->email = '';
			}// Check locations
			// home locations
			if ($patron->setUserHomeLocations($pInfo->homeLibraryCode)){
				$updatePatron = true;
			}// things not stored in database so don't need to check for updates but do need to add to object.
			// alt username
			// this is used on sites allowing username login.
			if ($this->hasUsernameField()){
				$fields = $pInfo->varFields;
				$i      = array_filter($fields, function ($k){
					return ($k->fieldTag == 'i');
				});
				if (empty($i)){
					$patron->alt_username = '';
				}else{
					$key                  = array_key_first($i);
					$alt_username         = $i[$key]->content;
					$patron->alt_username = $alt_username;
				}
			}// check phones
			$homePhone   = '';
			$mobilePhone = '';
			if (isset($pInfo->phones) && is_array($pInfo->phones)){
				foreach ($pInfo->phones as $phone){
					if ($phone->type == 't'){
						$homePhone = $phone->number;
					}elseif ($phone->type == 'o'){
						$mobilePhone = $phone->number;
					}elseif ($phone->type == 'p'){
						$patron->workPhone = $phone->number;
					}
				}
				// try home phone first then mobile phone
				if (!empty($homePhone) && $patron->phone != $homePhone){
					$updatePatron  = true;
					$patron->phone = $homePhone;
				}elseif (!isset($homePhone) && isset($mobilePhone) && $patron->phone != $mobilePhone){
					$updatePatron  = true;
					$patron->phone = $mobilePhone;
				}else{
					if (empty($patron->phone)){
						$patron->phone = '';
					}
				}
			}
			if (!isset($patron->phone)){
				$patron->phone = '';
			}                                           // fullname
			$patron->fullname = $pInfo->names[0];       // address
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
			$patron->address1 = '';
			$patron->address2 = '';
			$patron->city     = '';
			$patron->state    = '';
			$patron->zip      = '';
			$patronCity       = '';
			$patronState      = '';
			$patronZip        = '';
			$zipRegExp        = '|\d{5}$|';
			$splitRegExp      = '%([a-zA-Z]+)[\s|,]%';// splits on spaces or commas -- doesn't include zip
			if (isset($pInfo->addresses) && is_array($pInfo->addresses)){
				// get the home address -- we won't handle alt addresses in Pika.
				$homeAddressArray = false;
				foreach ($pInfo->addresses as $address){
					// a = primary address, h = alt address
					if ($address->type == 'a'){
						$homeAddressArray = $address->lines;
					}
				}
				// found a home address
				if ($homeAddressArray){
					$addressLineCount = count($homeAddressArray);
					// 3 lines - if we have three lines the first is c/o or something similar
					if ($addressLineCount == 3){
						// set care of
						$patron->careOf = $homeAddressArray[0];
						array_shift($homeAddressArray);               // shift off the first line
						$addressLineCount = count($homeAddressArray); // reset line count and continue
					}
					// 2 lines (or previously 3) - if we have at least 2 lines we should have a full address
					if ($addressLineCount == 2){
						// we can set address1 and address2
						$patron->address1 = $homeAddressArray[0];
						$patron->address2 = $homeAddressArray[1];
						// check last line for a zip -- this is where we assume it lives
						$zipTest = preg_match($zipRegExp, trim($homeAddressArray[1]), $zipMatch);
						if ($zipTest === 1){
							// OK, this should be a full address
							$patronZip = $zipMatch[0];
							// now split out the rest of the address
							$cityStateTest = preg_match_all($splitRegExp, trim($homeAddressArray[1]), $cityStateMatches);
							if ($cityStateTest){
								$cityState      = $cityStateMatches[1];
								$cityStateCount = count($cityState) - 1; // zero index
								// state should be last
								$patronState = $cityState[$cityStateCount];
								// pop last value and join array
								array_pop($cityState);
								$patronCity = implode(' ', $cityState);
							}
						}elseif ($zipTest === 0){
							// didn't find a zip
							// find a city and state?
							$cityStateArray = explode(',', $homeAddressArray[1]);
							if (count($cityStateArray) == 2){
								$patronCity  = $cityStateArray[0];
								$patronState = $cityStateArray[1];
							}
						}
					}else{
						// 1 line - only one address line -- this could be anything so start checking
						$patron->address1 = $homeAddressArray[0];
						// does it contain a zip? It might be some like grand junction, co 81501
						$zipTest = preg_match($zipRegExp, $homeAddressArray[0], $zipMatch);
						if ($zipTest === 1){
							// found a zip
							$patronZip = $zipMatch[0];
							// find a city and state?
							$cityStateTest = preg_match_all($splitRegExp, trim($homeAddressArray[0]), $cityStateMatches);
							if ($cityStateTest){
								$cityState      = $cityStateMatches[1];
								$cityStateCount = count($cityState) - 1; // zero index
								// state should be last
								$patronState = $cityState[$cityStateCount];
								// unset last value and join array
								array_pop($cityState);
								$patronCity = implode(' ', $cityState);
							}else{
								// well, that should'a matched something
								// todo: what do to?
							}
						}else{
							// couldn't find a zip -- not much to do
							// todo: what do to?
						}
					}
				}
			}
			$patron->city  = $patronCity;
			$patron->state = $patronState;
			$patron->zip   = $patronZip;// mobile phone
			// this triggers sms notifications for libraries using Sierra SMS
			if (isset($mobilePhone)){
				$patron->mobileNumber = $mobilePhone;
			}else{
				$patron->mobileNumber = '';
			}                                                                                                 // account expiration
			$patron->setUserExpirationSettings(empty($pInfo->expirationDate) ? '' : $pInfo->expirationDate);  // notices
			$patron->notices = $pInfo->fixedFields->{'268'}->value;
			switch ($pInfo->fixedFields->{'268'}->value){
				case 't':
					$patron->noticePreferenceLabel = 'Text';
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
				case '-':
				default:
					$patron->noticePreferenceLabel = 'none';
			}                                                                          // number of checkouts from ils
			$patron->numCheckedOutIls = $this->getNumCheckedOutsILS($sierraPatronId);  //TODO: Go back to the below if iii fixes bug. See: D-3447
			//$patron->numCheckedOutIls = $pInfo->fixedFields->{'50'}->value;
			// fines
			$patron->fines    = number_format($pInfo->moneyOwed, 2, '.', '');
			$patron->finesVal = number_format($pInfo->moneyOwed, 2, '.', '');        // hold counts
			$holds            = $this->getMyHolds($patron);
			if ($holds && isset($holds['available'])){
				$patron->numHoldsAvailableIls = count($holds['available']);
				$patron->numHoldsRequestedIls = count($holds['unavailable']);
				$patron->numHoldsIls          = $patron->numHoldsAvailableIls + $patron->numHoldsRequestedIls;
			}
			if (isset($pInfo->varFields)){
				if (!empty($this->configArray['Catalog']['sierraPatronWebNoteField'])){
					$webNotesVarField = $this->configArray['Catalog']['sierraPatronWebNoteField'];
					$webNotes         = $this->_getVarField($webNotesVarField, $pInfo->varFields);
					if (count($webNotes) > 0){
						foreach ($webNotes as $webNote){
							if (!empty($webNote->content)){
								$patron->webNote[] = $webNote->content;
							}
						}
					}
				}
				if (!empty($this->configArray['Catalog']['patronPinSetTimeField'])){
					$varField = $this->_getVarField($this->configArray['Catalog']['patronPinSetTimeField'], $pInfo->varFields);
					if (empty($varField)){
						$patron->pinUpdateRequired = true;
					}else{
						$lastPinUpdateTimeInILS = (reset($varField))->content;
						if (empty($lastPinUpdateTimeInILS)){
							$patron->pinUpdateRequired = true;
						}
					}
				}
			}
			if ($createPatron){
				$patron->created = date('Y-m-d');
				if ($patron->insert() === false){
					$this->logger->error('Could not save patron to Pika database.',
						[
							'barcode'   => $this->patronBarcode,
							'error'     => $patron->_lastError->userinfo,
							'backtrace' => $patron->_lastError->backtrace
						]);
					throw new ErrorException('Error saving patron to Pika database');
				}else{
					$this->logger->debug('Created patron in Pika database.', ['barcode' => $patron->getBarcode()]);
				}
			}elseif ($updatePatron && !$createPatron){
				$result = $patron->update();
				if (!$result){
					if (is_string($patron->_lastError)){
						$this->logger->error("Error updating user $patron->id : " . $patron->_lastError);
						return null;
					}elseif (!empty($patron->_lastError)){
						// Error is pear error
						$this->logger->error("Error updating user $patron->id : " . $patron->_lastError->getUserInfo());
						return $patron->_lastError;
					}
				}
			}// if this is a new user we won't cache -- will happen on next getPatron call
			if (isset($patron->id)){
				$patronObjectCacheKey = $this->cache->makePatronKey('patron', $patron->id);
				$this->logger->debug('Saving patron to memcache: ' . $patronObjectCacheKey);
				$this->cache->set($patronObjectCacheKey, $patron, $this->configArray['Caching']['user']);
			}
			return $patron;
		} else {
			$this->logger->notice('getPatron call with empty Sierra Id number' [$sierraPatronId]);
			return null;
		}
	}

	public function getNumCheckedOutsILS($patronId) {
		$checkoutOperation = 'patrons/'.$patronId.'/checkouts?limit=1';
		try {
			$checkoutRes = $this->_doRequest($checkoutOperation);
		} catch (\Exception $e) {
			$numCheckouts = 0;
		}
		if($checkoutRes && isset($checkoutRes->total)) {
			$numCheckouts = $checkoutRes->total;
		} else {
			$numCheckouts = 0;
		}
		return $numCheckouts;
	}

	private function _getVarField($key, $fields){

		$found = array_filter($fields, function ($k) use ($key){
			return $k->fieldTag == $key;
		});

		return $found;
	}

	/**
	 * Get the unique Sierra patron ID by searching for barcode.
	 *
	 * @param User|string $patronOrBarcode Either a barcode as a string or a User object.
	 * @param bool $searchSacramentoStudentIdField Overrides the var field tag to search to find sierra Id for
	 *                                             Sacramento's students
	 * @return int|false   returns the patron ID or false
	 * @throws ErrorException
	 */
	public function getPatronId($patronOrBarcode, $searchSacramentoStudentIdField = false) {
		// if a patron object was passed
		if (is_object($patronOrBarcode)){
			if (!empty($patronOrBarcode->ilsUserId)){
				return $patronOrBarcode->ilsUserId;
			}
			$barcode = $patronOrBarcode->barcode;
		} elseif (is_string($patronOrBarcode) || is_int($patronOrBarcode)) {
			// the api expects barcode in form of string. Just in case cast to string.
			$barcode = (string)$patronOrBarcode;
		}
		// patron ids are cached by default for 86400
		$patronIdCacheKey = "patron_" . $barcode . "_sierraid";
		if($patronId = $this->cache->get($patronIdCacheKey)) {
			return $patronId;
		}

		$params = [
			'varFieldTag'     => $searchSacramentoStudentIdField ? 'i' : 'b',
			'varFieldContent' => $barcode,
			'fields'          => 'id',
		];
		// make the request
		$r = $this->_doRequest('patrons/find', $params);
		// there was an error with the last call -- use $this->apiLastError for messages.
		if(!$r) {
			$this->logger->warn('Could not get patron ID.', ['barcode' => $barcode, 'error' => $this->apiLastError]);
			return false;
		}

		$this->cache->set($patronIdCacheKey, $r->id, $this->configArray['Caching']['koha_patron_id']);

		return $r->id;
	}


	/**
	 * @param string $barcode
	 * @return User|false
	 * @throws ErrorException
	 */
	public function findNewUser($barcode){
		$sierraUserId = $this->getPatronId($barcode);
		if (!empty($sierraUserId)){
			return $this->getPatron($sierraUserId);
		}
		return false;
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
		$patronId = $this->getPatronId($patron);
		// need the patron object for sites using username fields
		// grab it before removing cache
		if($this->hasUsernameField()) {
			$patron = $this->getPatron($patronId);
		}

		// remove patron object from cache
		$patronCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		$this->cache->delete($patronCacheKey);

		/*
		 * hack to shuffle off some actions
		 * This would be better in a router class
		 * If a method exits in a class extending this class it will be passed a User object.
		 */
		if (isset($_POST['profileUpdateAction'])){
			$profileUpdateAction = trim($_POST['profileUpdateAction']);
			if (method_exists($this, $profileUpdateAction)){
				return $this->$profileUpdateAction($patron);
			}
		}

		if(!$patronId) {
			return [['An error occurred. Please try again later.']];
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
					if (empty($val)){
						$errors[] = 'Street address is required.';
					}else{
						$address1 = $val;
					}
					break;
				case 'city':
				case 'state':
				case 'zip':
					// if library allows address updates
					if((boolean)$library->allowPatronAddressUpdates){
						if(empty($val)) {
							$errors[] = 'City, state and ZIP are required.';
						} else {
							$cityStZip[$key] = $val;
						}
					}
					break;
				case 'phone': // primary phone
					$val      = trim($val);
					$phones[] = (object)['number' => $val, 'type' => 't'];
					if ($val != $patron->phone){
						$patron->phone = $val;
						$patron->update();
					}
					break;
				case 'workPhone': // alt phone
					$phones[] = (object)['number' => $val, 'type' => 'p'];
					break;
				case 'mobileNumber': // mobile phone -- this triggers sms opt in for sierra
					if (!empty($val)){
						$phones[] = (object)['number' => $val, 'type' => 'o'];
					}else{
						$phones[] = (object)['number' => '', 'type' => 'o'];
					}
					break;
				case 'email':
					if (!empty($val)){
						$emails[] = $val;
					}else{
						$emails[] = '';
					}
					if($val != $patron->email) {
						$patron->email = $val;
						$patron->update();
					}
					break;
				case 'pickupLocation':
					$homeLibraryCode = $val;
					break;
				case 'notices':
					if(!empty($val)) {
						$notices = $val;
					}
					break;
				case 'alternate_username':
					$altUsername        = $val;
					$currentAltUsername = $patron->alt_username;
					// check to make sure username isn't already taken
					if ($altUsername != $currentAltUsername){
						$params = [
							'varFieldTag'     => 'i',
							'varFieldContent' => $altUsername,
							'fields'          => 'id'
						];
						$r      = $this->_doRequest('patrons/find', $params);
						if ($r){
							$errors[] = 'Username is already taken. Please choose a different username.';
						}
					}
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
		if(!empty($emails)) {
			$params['emails'] = $emails;
		}
		if (!empty($phones)){
			// we need to send all phone #s on user account (ie, work phone even if it's turned off in Pika) in array or
			// missing phones will be overwritten in Sierra.
			$phonesCount = count($phones);
			if ((isset($patron->phone) && isset($patron->workPhone)) && $phonesCount != 2){
				$addHomePhone = false;
				$addAltPhone  = false;
				foreach ($phones as $phone){
					if ($phone->type == 't'){
						$addAltPhone = true;
					}elseif ($phone->type == 'p'){
						$addHomePhone = true;
					}
				}
				if ($addAltPhone){
					$phones[] = (object)['number' => $patron->workPhone, 'type' => 'p'];
				}
				if ($addHomePhone){
					$phones[] = (object)['number' => $patron->phone, 'type' => 't'];
				}

			}
			$params['phones'] = $phones;
		}
		// allow address updates?
		if((boolean)$library->allowPatronAddressUpdates) {
			// fix up city state zip
			$address2            = $cityStZip['city'] . ', ' . $cityStZip['state'] . ' ' . $cityStZip['zip'];
			$params['addresses'] = [(object)['lines' => [$address1, $address2], "type" => 'a']];
		}

		// if notice preference is present
		if(isset($notices)) {
			$params['fixedFields'] = (object)['268' => (object)['label' => 'Notice Preference', 'value' => $notices]];
		}

		// username if present
		if (isset($altUsername) && $altUsername != '') {
			$params['varFields'] = [(object)['fieldTag' => 'i', 'content' => $altUsername]];
		}

		$operation = 'patrons/'.$patronId;
		$r = $this->_doRequest($operation, $params, 'PUT');

		if(!$r){
			$this->logger->debug('Unable to update patron', ['message' => $this->apiLastError]);
			$errors[] = 'An error occurred. Please try in again later.';
		}

		return $errors;
	}

	function setPatronPinSetTimeInILS(User $patron){
		if (!empty($this->configArray['Catalog']['patronPinSetTimeField'])){
			$params['varFields'] = [(object)[
				'fieldTag' => $this->configArray['Catalog']['patronPinSetTimeField'],
				'content'  => 'Patron set in Pika on ' . date('Y-m-d H:i:s')]
			];
			$operation           = 'patrons/' . $patron->ilsUserId;
			$r                   = $this->_doRequest($operation, $params, 'PUT');
			if (!$r){
				$this->logger->error('Unable to set patron pin set time in Sierra for user ' . $patron->ilsUserId, ["message" => $this->apiLastError]);
				$errors[] = 'An error occurred. Please try in again later.';
				return false;
			}
		}
		return true;
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
		$patronId = $patron->ilsUserId;
		if (empty($patronId)){
			// The user actually should have been already validated at this point
			$patronId = $this->_authBarcodePin($patron->barcode, $oldPin);
		}

		// Note: pin testing & checking is done by the calling updatePin method CatalogConnection class

		if (!$patronId){
			// This really shouldn't occur
			$this->logger->error('Failed to find Sierra patron id for pika user ' . $patron->id);
			return 'Your current ' . translate('pin') . ' is incorrect. Please try again.';
		}

		$operation = 'patrons/' . $patronId;
		$params    = ['pin' => $newPin];
		if (!empty($this->configArray['Catalog']['patronPinSetTimeField'])){
			// Taken from setPatronPinSetTimeInILS()
			// Set the patron pin set time in the same call
			$params['varFields'] = [(object)[
				'fieldTag' => $this->configArray['Catalog']['patronPinSetTimeField'],
				'content'  => 'Patron set in Pika on ' .  date('Y-m-d H:i:s')]
			];
		}
		$r = $this->_doRequest($operation, $params, 'PUT');

		if(!$r) {
			$message = $this->_getPrettyError();
			return 'Could not update ' . translate('pin') . ': '. $message;
		}
		$result = $patron->updatePassword($newPin);
		$patron->pinUpdateRequired = false;

		$patronCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		$this->cache->delete($patronCacheKey);
		// Important: A success message won't be displayed unless the words are EXACTLY as below.
		return 'Your ' . translate('pin') . ' was updated successfully.';
	}

	/**
	 * resetPin
	 *
	 * Handles a pin reset when requested with emailResetPin().
	 *
	 * @param  User $patron
	 * @param  string $newPin
	 * @param  string $resetToken
	 * @return array|bool
	 * @throws ErrorException
	 */
	public function resetPin($patron, $newPin, $resetToken){
		$pinReset         = new PinReset();
		$pinReset->userId = $patron->id;
		$pinReset->find(true);
		if(!$pinReset->N) {
			return ['error' => 'Unable to reset your ' . translate('pin') . '. Please try again later.'];
		} elseif($pinReset->N == 0) {
			return ['error' => 'Unable to reset your ' . translate('pin') . '. You have not requested a ' . translate('pin') . ' reset.'];
		}
		// expired?
		if($pinReset->expires < time()) {
			return ['error' => 'The reset token has expired. Please request a new ' . translate('pin') . ' reset.'];
		}
		$token = $pinReset->selector . $pinReset->token;
		// make sure and type cast the two numbers
		if ((int)$token != (int)$resetToken) {
			return ['error' => 'Unable to reset your ' . translate('pin') . '. Invalid reset token.'];
		}
		// everything is good
		$patronId  = $this->getPatronId($patron);
		$operation = 'patrons/' . $patronId;
		$params    = ['pin' => (string)$newPin];
		if (!empty($this->configArray['Catalog']['patronPinSetTimeField'])){
			// Taken from setPatronPinSetTimeInILS()
			// Set the patron pin set time in the same call
			$params['varFields'] = [(object)[
				'fieldTag' => $this->configArray['Catalog']['patronPinSetTimeField'],
				'content'  => 'Patron set in Pika on ' . date('Y-m-d H:i:s')]
			];
		}

		// update sierra first
		$r = $this->_doRequest($operation, $params, 'PUT');
		if (!$r){
			$message = $this->_getPrettyError();
			$this->logger->error('Error updating pin in Sierra for user ' . $patron->id, [$message]);
			return ['error' => 'Could not update ' . translate('pin') . ': ' . $message];
		}
		$patronCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		$r              = $patron->updatePassword($newPin);
		if (!$r){
			// this shouldn't matter since we hit the api first when logging in a patron, but ....
			$this->logger->error('Error updating pin in pika db for user ' . $patron->id);
			$this->cache->delete($patronCacheKey);
			return ['error' => 'Please try logging in with your new ' . translate('pin') . '. If you are unable to login please contact your library.'];
		}
		$pinReset->delete();
		$this->cache->delete($patronCacheKey);

		return true;
	}

	/**
	 * emailResetPin
	 *
	 * Sends an email reset link to the patrons email address
	 *
	 * @param  string            $barcode
	 * @return array|bool        true if email is sent, error array on fail
	 * @throws ErrorException
	 * @throws \PHPMailer\PHPMailer\Exception
	 */
	public function emailResetPin($barcode) {
		// Check Sierra for an email address.
		// It may be the case the patron updated their email via a means other than
		// Pika. Don't rely on the email in the database as this can cause issues.
		$patronId = $this->getPatronId($barcode);
		if(!$patronId) {
			//TODO: better error message for when sierra is unresponsive.
			return ['error' => 'The barcode you provided is not valid. Please check the barcode and try again.'];
		}
		// getPatron fetches new user data from Sierra and updates any conflicting details and returns a fresh user
		// object. It will also create a new user in the database if one isn't found.
		$patron   = $this->getPatron($patronId, true);

		//$patron          = new User();
		//$patron->barcode = $barcode;
//		if (!$patron->find(true)){
//			// might be a new user
//			if ($patronId = $this->getPatronId($barcode)){
//				// load them in the database.
//				unset($patron);
//				$patron = $this->getPatron($patronId);
//			}else{
//				return ['error' => 'Unable to find an account associated with barcode: ' . $barcode];
//			}
//		}elseif (empty($patron->email)){

		// If the email is empty at this point we don't have a good address for the patron.
		if (empty($patron->email)){
			return ['error' => 'You do not have an email address on your account. Please visit your library to reset your ' . translate('pin') . '.'];
			// Sierra might have an email for the user that Pika doesn't have
//			if ($patronId = $this->getPatronId($barcode)){
//				// load them in the database.
//				unset($patron);
//				$patron = $this->getPatron($patronId);
//			}  else {
//				$this->logger->notice('Patron user found in Pika not found in Sierra for barcode ' . $barcode);
//			}
		}
//		if (!empty($patron->ilsUserId) && empty($patron->email)){
//			// Can't use a check of $patron->N that user data has be found because
//			// "$patron = $this->getPatron($patronId);" lines doesn't populate $patron->N but will have user data (if called successfully)
//			// $patron->ilsUserId must be populated if we have good user data, so we will use that field for checking for a valid user.
//
//			return ['error' => 'You do not have an email address on your account. Please visit your library to reset your ' . translate('pin') . '.'];
//		}

		// make sure there's no old token.
		$pinReset         = new PinReset();
		$pinReset->userId = $patron->id;
		$pinReset->delete();
		// to be safe after delete is called ...
		$pinReset->userId = $patron->id;

		$resetToken = $pinReset->insertReset();
		// build reset url (Note: the site url gets automatically set as the interface url
		$resetUrl = $this->configArray['Site']['url'] . "/MyAccount/ResetPin?uid=".$patron->id.'&resetToken='.$resetToken;

		// build the message
		$pin     = translate('pin');
		$subject = '[DO NOT REPLY] ' . ucfirst($pin) . ' Reset Link';

		global $interface;
		$interface->assign('pin', $pin);
		$interface->assign('resetUrl', $resetUrl);
		$htmlMessage = $interface->fetch('Emails/pin-reset-email.tpl');

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
	 * @param  bool|array   $extraSelfRegParams Extra self reg parameters. This will be array merged with other params
	 * @return array        [success = bool, barcode = null or barcode]
	 * @throws ErrorException
	 */
	public function selfRegister($extraSelfRegParams = false){

		global $library;
		// sanity checks
		if (empty($library->selfRegistrationDefaultpType)){
			$message = 'Missing configuration parameter selfRegistrationDefaultpType for ' . $library->displayName;
			$this->logger->error($message);
			throw new InvalidArgumentException($message);
		}
		if (empty($library->selfRegistrationAgencyCode)){
			$message = 'Missing configuration parameter selfRegistrationAgencyCode for ' . $library->displayName;
			$this->logger->error($message);
			throw new InvalidArgumentException($message);
		}

		$params = [];
		foreach ($_POST as $key => $val){
			switch ($key){
				case 'email':
					$val          = trim($val);
					$successEmail = false;
					if (!empty($val)){
						$successEmail       = $val;
						$params['emails'][] = $val;
					}
					break;
				case 'address': // street part of address
					$val                                = trim($val);
					$params['addresses'][0]['lines'][0] = $val;
					$params['addresses'][0]['type']     = 'a';
					break;
				case 'altaddress':
					$val = trim($val);
					if (!empty($val)){
						$params['addresses'][1]['lines'][0] = $val;
					}else{
						$params['addresses'][1]['lines'][0] = 'none';
					}
					$params['addresses'][1]['type'] = 'h';
					break;
				case 'primaryphone':
					$val = trim($val);
					if (!empty($val)){
						$params['phones'][] = ['number' => $val, 'type' => 't'];
					}
					break;
				case 'altphone':
					$val = trim($val);
					if (!empty($val)){
						$params['phones'][] = ['number' => $val, 'type' => 'p'];
					}
					break;
				case 'birthdate':
					if (!empty($val)){
						$date                = DateTime::createFromFormat('m-d-Y', $val);
						$params['birthDate'] = $date->format('Y-m-d');
					}
					break;
				case 'homelibrarycode':
					if (!empty($val)){
						$params['homeLibraryCode'] = $val;
					}
					break;
				case 'notices' :
					$val                        = substr(trim($val), 0, 1); // Ensure input is a single character code
					$params['fixedFields'][268] = [
						'label' => 'Notice Preference',
						'value' => $val,
					];
					break;
				case 'langPref' :
					$val = substr(trim($val),0, 3); // Ensure input is a three language code
					$params['langPref'] = $val;
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
			$barcode     = (string)mt_rand((int)$min, (int)$max);
			$barcodeTest = $this->getPatronId($barcode);
		} while ($barcodeTest === true);
		$params['barcodes'][] = $barcode;

		// agency code -- not all sierra libraries use the agency field
		if ($library->selfRegistrationAgencyCode >= 1){
			$params['fixedFields']['158'] = [
				'label' => 'PAT AGENCY',
				'value' => $library->selfRegistrationAgencyCode
			];
		}
		// expiration date
		if ($library->selfRegistrationDaysUntilExpire > 0){
			$interval   = 'P' . $library->selfRegistrationDaysUntilExpire . 'D';
			$expireDate = new DateTime();
			$expireDate->add(new DateInterval($interval));
			$params['expirationDate'] = $expireDate->format('Y-m-d');
		}

		// names -- standard is Last, First Middle
		$name = trim($_POST['lastname']) . ', ';
		$name .= trim($_POST['firstname']);
		if (!empty($_POST['middlename'])){
			$name .= ' ' . trim($_POST['middlename']);
		}
		$params['names'][] = $name;

		// city state and zip
		$cityStateZip = trim($_POST['city']) . ', ' . trim($_POST['state']) . ' ' . trim($_POST['zip']);
		// address line 2
		$params['addresses'][0]['lines'][1] = $cityStateZip;

		// if library uses pins
		if ($this->accountProfile->usingPins()){
			$pin        = trim($_POST['pin']);
			$pinConfirm = trim($_POST['pinconfirm']);

			if (!($pin == $pinConfirm)){
				return ['success' => false, 'barcode' => ''];
			}else{
				$params['pin'] = $pin;
			}
		}

		// EXTRA SELF REG PARAMETERS
		// do this last in case there are any parameters set up that need to be overridden
		if ($extraSelfRegParams){
			// Combine fixed fields ahead of merging main parameters (because any set in $params would be overwritten)
			// e.g. selfRegistrationAgencyCode is set; but site driver sets notification preferences
			if (isset($extraSelfRegParams['fixedFields']) && isset($params['fixedFields'])){
                $params['fixedFields'] += $extraSelfRegParams['fixedFields'];
                // Use array + (union) operator in order to preserve the specific numeric keys
                // required for setting the fixedFields on a self-reg user.
                unset($extraSelfRegParams['fixedFields']);
                // Now that the fixedFields element is merged, remove it from extra params array
                // so other extra fields can be merged (below)
			}
			$params = array_merge($params, $extraSelfRegParams);
		}

		// Set Pin set time
		if (!empty($this->configArray['Catalog']['patronPinSetTimeField'])){
			// Have to do have the extra params ($extraSelfRegParams) merging above since there can be more than one varFields
			// to add to self reg users
			$params['varFields'][] = [
				'fieldTag' => $this->configArray['Catalog']['patronPinSetTimeField'],
				'content'  => 'Patron set in Pika on ' . date('Y-m-d H:i:s')
			];
		}

		$this->logger->debug('Self registering patron', ['params' => $params]);
		$operation = 'patrons/';
		$r         = $this->_doRequest($operation, $params, 'POST');

		if (!$r){
			$result = ['success' => false, 'barcode' => ''];
			$this->logger->warning('Failed to self register patron', [$this->apiLastError]);
			if (!empty($this->apiLastErrorForPatron)){
				$result['message'] = $this->apiLastErrorForPatron;
			}
			return $result;
		}

		if ($successEmail){
			$emailSent = $this->sendSelfRegSuccessEmail($barcode);
		}

		$this->logger->debug('Success self registering patron : ' . $barcode);
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

		/** @var Library $library */
		global $library;

		$fields[] = [
			'property'     => 'firstname',
			'type'         => 'text',
			'label'        => 'First name',
			'description'  => 'Your first name',
			'maxLength'    => 50,
			'required'     => true,
			'autocomplete' => 'given-name',
		];

		$fields[] = [
			'property'     => 'middlename',
			'type'         => 'text',
			'label'        => 'Middle name',
			'description'  => 'Your middle name or initial',
			'maxLength'    => 30,
			'required'     => false,
			'autocomplete' => 'additional-name',
		];

		$fields[] = [
			'property'     => 'lastname',
			'type'         => 'text',
			'label'        => 'Last name',
			'description'  => 'Your last name (surname)',
			'maxLength'    => 40,
			'required'     => true,
			'autocomplete' => 'family-name',
		];

		// get the valid home/pickup locations
		$homeLocations = $this->getSelfRegHomeLocations($library);

		$fields[] = [
			'property'    => 'homelibrarycode',
			'type'        => 'enum',
			'label'       => 'Home Library/Preferred pickup location',
			'description' => 'Your home library and preferred pickup location.',
			'values'      => $homeLocations,
			'required'    => true
		];

		// allow usernames?
		if ($this->hasUsernameField()){
			$fields[] = [
				'property'     => 'username',
				'type'         => 'text',
				'label'        => 'Username',
				'description'  => 'Set an optional username.',
				'maxLength'    => 20,
				'required'     => false,
				'autocomplete' => 'username',
			];
		}
		// if library would like a birthdate
		if (isset($library) && $library->promptForBirthDateInSelfReg){
			$fields[] = [
				'property'     => 'birthdate',
				'type'         => 'date',
				'label'        => 'Date of Birth (MM-DD-YYYY)',
				'description'  => 'Date of birth',
				'maxLength'    => 10,
				'required'     => true,
				'autocomplete' => 'bday',
			];
		}

		$fields[] = [
			'property'     => 'address',
			'type'         => 'text',
			'label'        => 'Mailing Address',
			'description'  => 'Mailing Address.',
			'maxLength'    => 40,
			'required'     => true,
			'autocomplete' => 'street-address',

		];

		$fields[] = [
			'property'     => 'city',
			'type'         => 'text',
			'label'        => 'City',
			'description'  => 'The city you receive mail in.',
			'maxLength'    => 128,
			'required'     => true,
			'autocomplete' => 'address-level2',
		];

		$fields[] = [
			'property'     => 'state',
			'type'         => 'text',
			'label'        => 'State',
			'description'  => 'The state you receive mail in.',
			'maxLength'    => 20,
			'required'     => true,
			'autocomplete' => 'address-level1',
		];

		$fields[] = [
			'property'     => 'zip',
			'type'         => 'text',
			'label'        => 'ZIP code',
			'description'  => 'The ZIP code for your mail.',
			'maxLength'    => 16,
			'required'     => true,
			'autocomplete' => 'postal-code',
		];

		$fields[] = [
			'property'     => 'email',
			'type'         => 'email',
			'label'        => 'Email',
			'description'  => 'Your email address',
			'maxLength'    => 128,
			'required'     => false,
			'autocomplete' => 'email',
		];

		$fields[] = [
			'property'     => 'primaryphone',
			'type'         => 'tel',
			'label'        => 'Primary phone (XXX-XXX-XXXX)',
			'description'  => 'Your primary phone number.',
			'maxLength'    => 20,
			'required'     => false,
			'autocomplete' => 'tel-national',
		];

		if ($library && $library->showWorkPhoneInProfile){
			$fields[] = [
				'property'    => 'altphone',
				'type'        => 'text',
				'label'       => 'Work phone (XXX-XXX-XXXX)',
				'description' => 'Work Phone',
				'maxLength'   => 40,
				'required'    => true
			];
		}
		
		// if library uses pins
		if($this->accountProfile->usingPins()) {
			$fields[] = [
				'property'    => 'pin',
				'type'        => 'pin',
				'label'       => translate('PIN'),
				'description' => 'Please set a ' . translate('pin') . '.',
//				'maxLength'   => 10,
				'required'    => true
			];

			$fields[] = [
				'property'    => 'pinconfirm',
				'type'        => 'pin',
				'label'       => 'Confirm ' . translate('PIN'),
				'description' => 'Please confirm your ' . translate('pin') . '.',
//				'maxLength'   => 10,
				'required'    => true
			];
		}

		return $fields;
	}

	/**
	 * @param string $fieldProperty Name of the property to remove from the form
	 * @param array $selfRegistrationFields  The form fields array
	 * @return void
	 */
	function removeSelfRegistrationField(string $fieldProperty, array &$selfRegistrationFields){
		foreach ($selfRegistrationFields as $index => $field){
			if ($field['property'] == $fieldProperty){
				unset($selfRegistrationFields[$index]);
				break;
			}
		}
	}

	/**
	 * Capitalize all the form inputs within $_POST, except field pin, pinconfirm,
	 * and any field provided in $exceptions
	 *
	 * @param $exceptions self reg input fields to exclude from capitalization
	 * @return void
	 */
	function capitalizeAllSelfRegistrationInputs($exceptions = []) : void {
		$exceptions = [... $exceptions, ... ['homelibrarycode', 'pin', 'pinconfirm', 'langPref', 'notices', 'email']];
		foreach ($this->getSelfRegistrationFields() as $field){
			$key = $field['property'];
			if (!in_array($key, $exceptions)){
				if (isset($_POST[$key])){ // :selfRegister() explicitly refers to $_POST instead of $_REQUEST
					$_POST[$key] = strtoupper(trim($_POST[$key]));
				}
			}
		}
	}

	/**
	 * Send a self registration success email
	 *
	 * @param  string $barcode Self registered patrons barcode
	 * @return bool true if email sent false otherwise
	 */
	public function sendSelfRegSuccessEmail($barcode) {
		global $interface;

		if(!$patronId = $this->getPatronId($barcode)){
			$this->logger->error('Failed to get patron Id for ' . __FUNCTION__ . ' for barcode ' . $barcode);
			return false;
		}

		$patron = $this->getPatron($patronId);
		if(!$patron) {
			$this->logger->error('Failed to load patron for ' . __FUNCTION__ . ' for patron ILS id ' . $patronId);
			return false;
		}

		$lib = $patron->getHomeLibrary();

		$emailAddress = $patron->email;
		$patronName   = $patron->firstname . ' ' . $patron->lastname;
		$libraryName  = $lib->displayName;
		$catalogUrl   = $_SERVER['REQUEST_SCHEME'] . '://' . $lib->catalogUrl;
		//$catalogUrl   = $this->configArray['Site']['url'];

		$interface->assign('emailAddress', $emailAddress);
		$interface->assign('patronName', $patronName);
		$interface->assign('libraryName', $libraryName);
		$interface->assign('catalogUrl', $catalogUrl);
		$interface->assign('barcode', $barcode);
		$emailBody = $interface->fetch('Emails/self-registration.tpl');
		try {
			$mailer = new PHPMailer;
			$mailer->setFrom($this->configArray['Site']['email']);
			$mailer->addAddress($emailAddress);
			$mailer->Subject = '[DO NOT REPLY] Your new library card at ' . $libraryName;
			$mailer->Body    = $emailBody;
			$mailer->send();
		} catch (\Exception $e) {
			$this->logger->error($mailer->ErrorInfo);
			return false;
		}
		return true;
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
		$patronFinesCacheKey = $this->cache->makePatronKey('fines', $patron->id);
		if($this->cache->get($patronFinesCacheKey)) {
			return $this->cache->get($patronFinesCacheKey);
		}
		// make the call
		$params = [
			'fields' => 'default,assessedDate,itemCharge,processingFee,billingFee,chargeType,paidAmount,datePaid,description,returnDate,location,description'
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
			$owed = $fine->itemCharge;
			$owed = $owed + $fine->processingFee;
			$owed = $owed + $fine->billingFee;
			$owed = $owed - $fine->paidAmount;
			$amount = number_format($owed, 2);

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
		$this->cache->set($patronFinesCacheKey, $r, 90);
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

		global $library;
		// ILL service name
		$illName = $this->configArray['InterLibraryLoan']['innReachEncoreName'];
		if($library) {
			if(isset($library->interLibraryLoanName) && $library->interLibraryLoanName != '') {
				$illName = $library->interLibraryLoanName;
			}
		}
		$patronHoldsCacheKey = $this->cache->makePatronKey('holds', $patron->id);
		if ($patronHolds = $this->cache->get($patronHoldsCacheKey)) {
			$this->logger->info("Found holds in memcache:" . $patronHoldsCacheKey);
			return $patronHolds;
		}

		if(!$patronId = $this->getPatronId($patron)) {
			return false;
		}

		$operation = "patrons/$patronId/holds";
		if ((integer)$this->configArray['Catalog']['api_version'] > 4){
			$params = [
				'fields' => 'default,pickupByDate,frozen,priority,priorityQueueLength,notWantedBeforeDate,notNeededAfterDate,canFreeze',
				'limit'  => 1000,
				'expand' => 'record'
			];
		}else{
			$params = [
				'fields' => 'default,frozen,priority,priorityQueueLength,notWantedBeforeDate,notNeededAfterDate',
				'limit'  => 1000
			];
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
		$pickupLocations = $patron->getValidPickupBranches($this->accountProfile->recordSource, false);
		// Need to exclude linked accounts here to prevent infinite loop during patron login in cases where accounts are reciprocally linked
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
				// sierra api v4 priority is 0 based index so add 1
				if ($this->configArray['Catalog']['api_version'] == 4 ) {
					$holdPriority = (integer)$hold->priority + 1;
				} else {
					$holdPriority = $hold->priority;
				}
				$h['position'] = $holdPriority . ' of ' . $hold->priorityQueueLength;

			} elseif (isset($hold->priority) && !isset($hold->priorityQueueLength)) {
				// sierra api v4 priority is 0 based index so add 1
				if ($this->configArray['Catalog']['api_version'] == 4 ) {
					$holdPriority = (integer)$hold->priority + 1;
				} else {
					$holdPriority = $hold->priority;
				}
				$h['position'] = $holdPriority;
			} else {
				$h['position'] = false;
			}

			// cancel id
			preg_match($this->urlIdRegExp, $hold->id, $m);
			$h['cancelId'] = $m[1];

			// status, cancelable, freezable
			$recordStatus = $hold->status->code;
			// check item record status
			if ($hold->recordType == 'i') {
				$recordItemStatus = $hold->record->status->code;
				// If this is an inn-reach exclude from check -- this comes later
				if(! strstr($hold->record->id, "@")) {
					// if the item status is "on hold shelf" (!) but the hold record status is "on hold" (0) use "on hold" status
					// the "on hold shelf" status is for another patron.
					if($recordItemStatus != "!" && $recordStatus != '0') {
						// check for in transit status see
						if($recordItemStatus == 't') {
							if(isset($hold->priority) && (int)$hold->priority == 1)
							$recordStatus = 't';
						}
					}
				} else {
					// inn-reach status
					$recordStatus = $recordItemStatus;
				}
			}
			// type hint so '0' != false
			switch ((string)$recordStatus) {
				case '0':
				case '-':
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
				case '!':
					$status       = 'Ready';
					$cancelable   = true;
					$freezeable   = false;
					$updatePickup = false;
					break;
				case 't':
					$status     = 'In transit';
					$cancelable = true;
					$freezeable = false;
					$updatePickup = false;
					break;
				case "&": // inn-reach status
					$status       = "Requested";
					if($illName) {
						$status .= ' from '.$illName;
					}
					$cancelable   = true;
					$freezeable   = false;
					$updatePickup = false;
					break;
				case "#": // inn-reach status (Received)
					$hold->status->code = 'i';
					$status             = 'Ready';
					$freezeable         = false;
					$cancelable         = false;
					$updatePickup = false;
					break;
				default:
					if(isset($recordItemStatusMessage)) {
						$status = $recordItemStatusMessage;
					} else {
						$status = 'On hold';
					}
					$cancelable   = false;
					$freezeable   = false;
					$updatePickup = false;
			}
			if (isset($hold->canFreeze)){
				// Sierra holds now have a canFreeze flag
				$freezeable = true;
			}else{
				// for sierra, holds can't be frozen if patron is next in line
				if (isset($hold->priorityQueueLength)){
					if (isset($hold->priority) && ((int)$hold->priority <= 2 && (int)$hold->priorityQueueLength >= 2)){
						$freezeable = false;
						// if the patron is the only person on wait list hold can't be frozen
					}elseif (isset($hold->priority) && ($hold->priority == 1 && (int)$hold->priorityQueueLength == 1)){
						$freezeable = false;
						// if there is no priority set but queueLength = 1
					}elseif (!isset($hold->priority) && $hold->priorityQueueLength == 1){
						$freezeable = false;
					}
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
				$pickupBranch->whereAdd($where); //TODO: simplify
				if ($pickupBranch->find(1)){
					$h['currentPickupId']   = $pickupBranch->locationId;
					$h['currentPickupName'] = $pickupBranch->displayName;
					$h['location']          = $pickupBranch->displayName;
				}else{
					$h['currentPickupId']   = false;
					$h['currentPickupName'] = $hold->pickupLocation->name;
					$h['location']          = $hold->pickupLocation->name;
				}
			} else{
				//This shouldn't happen, but we have had examples where it did
				$this->logger->error("Patron with barcode {$patron->getBarcode()} has a hold with out a pickup location ");
				$h['currentPickupId']   = false;
				$h['currentPickupName'] = false;
				$h['location']          = false;
			}

			// determine if this is an innreach hold
			// or if it's a regular ILS hold
			if(!empty($hold->record->id) && strstr($hold->record->id, "@")) {
				///////////////
				// INNREACH HOLD
				///////////////
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
				// for item level holds we need to grab the bib id.
				$itemId = $id = $hold->record->id; //$m[1];
				if($recordType == 'i') {
					$id = $this->_getBibIdFromItemId($id);
				}
				// for Pika we need the check digit.
				$recordXD  = $this->getCheckDigit($id);

				// get more info from record
				$bibId             = '.b' . $id . $recordXD;
				$recordSourceAndId = new \SourceAndId($this->accountProfile->recordSource . ':' . $bibId);
				$record            = RecordDriverFactory::initRecordDriverById($recordSourceAndId);
				if ($record->isValid()){
					$h['id']              = $record->getUniqueID();
					$h['shortId']         = $record->getShortId();
					$h['title']           = $record->getTitle();
					$h['sortTitle']       = $record->getSortableTitle();
					$h['author']          = $record->getPrimaryAuthor();
					$h['format']          = $record->getFormat();
					$h['link']            = $record->getRecordUrl();
					$h['coverUrl']        = $record->getBookcoverUrl('medium');
					if($recordType == 'i') {
						// Get volume for Item holds
						$h['volume'] = $record->getItemVolume('.i' . $itemId . $this->getCheckDigit($itemId));
					}
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
			$this->cache->set($patronHoldsCacheKey, $return, $this->configArray['Caching']['user_holds']);
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
			$neededBy = $d ? $d->format('Y-m-d') : false;
		} else {
			$neededBy = false;
		}
		$recordType     = substr($recordId, 1,1); // determine the hold type b = bib, i = item, j = volume
		$recordNumber   = substr($recordId, 2, -1); // remove the .x and the last check digit
		$pickupLocation = $pickupBranch;
		$patronId       = $this->getPatronId($patron);
		if (!$patronId){
			// We may have a bad barcode from a staff placed hold request
			return [
				'success' => false,
				'message' => 'Did not find patron in Sierra'
			];
		}

		// delete memcache holds
		$patronHoldsCacheKey = $this->cache->makePatronKey('holds', $patron->id);
		if(!$this->cache->delete($patronHoldsCacheKey)) {
			$this->logger->warn("Failed to remove holds from memcache: " . $patronHoldsCacheKey);
		}

		// because the patron object has holds information we need to clear that cache too.
		$patronObjectCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		if(!$this->cache->delete($patronObjectCacheKey)) {
			$this->logger->warn("Failed to remove patron from memcache: " . $patronObjectCacheKey);
		}

		$params = [
			'recordType'     => $recordType,
			'recordNumber'   => (int)$recordNumber,
			'pickupLocation' => $pickupLocation,
		];
		if($neededBy) {
			$params['neededBy'] = $neededBy;
		}

		$operation = "patrons/$patronId/holds/requests";

		$r = $this->_doRequest($operation, $params, "POST");

		// check if error we need to do an item level hold
		if ($this->apiLastError && stristr($this->apiLastError, 'Volume record selection is required to proceed')
		   || (stristr($this->apiLastError,"This record is not available") && (integer)$this->configArray['Catalog']['api_version'] == 4)) {

			$this->logger->notice("Sierra patron $patronId hold on $recordId requires item level hold");
			$itemsAsVolumes = $r->details->itemsAsVolumes ?? null; // Response when item level hold is required includes the list of items
			if (!empty($r->detail->itemIds)){
				// API version ~5.5 style response is a list of Ids; convert to a form matching the others;
				$itemsAsVolumes = [];
				foreach ($r->detail->itemIds as $itemId){
					$obj              = new \stdClass();
					$obj->id          = $itemId;
					$itemsAsVolumes[] = $obj;
				}
			}
			$items          = $this->getItemVolumes($patron, $recordId, $itemsAsVolumes);
			$return         = [
				'message'    => 'This title requires item level holds, please select an item to place a hold on.',
				'success'    => 'true',
				'canceldate' => $neededBy,
				'items'      => $items
			];
			return $return;
		}

		// oops! something went wrong.
		if (!$r){
			$return['success'] = false;
			if ($this->apiLastError) {
				$message = $this->_getPrettyError();
				$this->logger->notice("Hold error $message", [$this->apiLastError]);
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

		// get title of record
		$record      = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);
		$recordTitle = $record->isValid() ? $record->getTitle() : null;

		if($recordTitle) {
			$recordTitle = trim($recordTitle, ' /');
			$return['message'] = "Your hold for <strong>{$recordTitle}</strong> was successfully placed.";
		} else {
			$return['message'] = 'Your hold was successfully placed.';
		}

		return $return;
	}


	public function placeItemHold($patron, $recordId, $itemId, $pickupBranch, $cancelDate = null){
		return $this->placeHold($patron, $itemId, $pickupBranch, $cancelDate);
	}

	public function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch){
		return[];
	}

	public function changeHoldPickupLocation($patron, $bibId, $holdId, $newPickupLocation){
		$operation = 'patrons/holds/' . $holdId;
		$params    = ['pickupLocation' => $newPickupLocation];

		// delete holds cache
		$patronHoldsCacheKey = $this->cache->makePatronKey('holds', $patron->id);
		$this->cache->delete($patronHoldsCacheKey);

		$r = $this->_doRequest($operation, $params, "PUT");

		// something went wrong
		if (!$r){
			$return = ['success' => false];
			if ($this->apiLastError){
				$message           = $this->_getPrettyError();
				$return['message'] = $message;
			}else{
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
	 * @param User $patron
	 * @param \SourceAndId $sourceAndId
	 * @return mixed
	 */
	function getHomePickupLocations($patron, $sourceAndId){
		$operation = 'patrons/' . $patron->ilsUserId . '/holds/requests/form';
		$baseId    = preg_replace('/\.?[bij]/', '', $sourceAndId->getRecordId());
		$shortId   = substr($baseId, 0, strlen($baseId) - 1);
		$params    = ['recordNumber' => $shortId];
		$result    = $this->_doRequest($operation, $params);
		if (!$result){
			if ($this->apiLastError){
				$message = $this->_getPrettyError() || 'Error getting valid home pick up item locations from Sierra';
				$this->logger->error($message, [$operation, $params]);
				return false;
			}
		}

		// Get the location codes to lookup in our location table
		$locationCodes = [];
		if(empty($result->holdshelf)){
			$this->logger->error('Did not get a holdshelf response from the holds requests form call. Check sierra version is 6.3 or greater');
			return false;
		}

		if (!empty($result->holdshelf->selected)){
			$locationCodes[] = trim($result->holdshelf->selected->code);
		}
		if (!empty($result->holdshelf->locations)){
			// The Home Pickup Locations
			foreach ($result->holdshelf->locations as $locationOption){
				$locationCodes[] = trim($locationOption->code); // Sierra pickup locations are padded with trailing spaces
			}
		}

		$pickupLocations = [];
		$location        = new Location();
		$location->whereAddIn('code', $locationCodes, 'string');
		if ($location->find()){
			$pickupUsersArray[] = $patron->id;
			foreach ($patron->getLinkedUsers() as $linkedUser){
				//TODO: pType calculations might need to be applied to linked users
				$pickupUsersArray[] = $linkedUser->id;
			}
			//$pickupUsers = implode(',', $pickupUsersArray);
			while ($location->fetch()){
				// Add to pickup location array
				$location->pickupUsers = $pickupUsersArray;
				$pickupLocations[]     = clone $location;
			}
		}

		return $pickupLocations;
	}

	/**
	 * DELETE patrons/holds/{holdId}
	 * @param $patron
	 * @param $bibId
	 * @param $holdId
	 * @return array
	 */
	public function cancelHold($patron, $bibId, $holdId){
		$operation = 'patrons/holds/' . $holdId;

		// delete holds cache
		$patronHoldsCacheKey = $this->cache->makePatronKey('holds', $patron->id);
		if(!$this->cache->delete($patronHoldsCacheKey)) {
			$this->logger->warn("Failed to remove from memcache: ".$patronHoldsCacheKey);
		}

		// because the patron object has holds information we need to clear that cache too.
		$patronObjectCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		if(!$this->cache->delete($patronObjectCacheKey)) {
			$this->logger->warn("Failed to remove patron from memcache: ".$patronObjectCacheKey);
		}

		$this->logger->debug("Canceling hold id ". $holdId . " for patron ". $patron->barcode);

		$r = $this->_doRequest($operation, [], "DELETE");

		// something went wrong
		if(!$r) {
			$return = ['success' => false];
			if($this->apiLastError) {
				$this->logger->error("Failed to cancel hold id " . $holdId . " for patron ". $patron->barcode, ["api_error" => $this->apiLastError]);
				$message = $this->_getPrettyError();
				$return['message'] = $message;
			} else {
				$return['message'] = "Unable to cancel your hold. Please contact your library for further assistance.";
			}
			return $return;
		}

		$this->logger->debug("Successfully canceled hold id ". $holdId . " for patron ". $patron->barcode);

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
		$operation = 'patrons/holds/' . $holdId;
		$params    = ["freeze" => true];

		// delete holds cache
		$patronHoldsCacheKey = $this->cache->makePatronKey('holds', $patron->id);
		if(!$this->cache->delete($patronHoldsCacheKey)) {
			$this->logger->warn('Failed to delete patron holds from cache:'.$patronHoldsCacheKey);
		}

		$r = $this->_doRequest($operation, $params, "PUT");

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
		$operation = 'patrons/holds/' . $holdId;
		$params    = ["freeze" => false];

		// delete holds cache
		$patronHoldsCacheKey = $this->cache->makePatronKey('holds', $patron->id);
		if(!$this->cache->delete($patronHoldsCacheKey)) {
			$this->logger->warn('Failed to delete patron holds cache: '.$patronHoldsCacheKey);
		}

		$r = $this->_doRequest($operation,$params, 'PUT');

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
			'message' => 'Your hold has been thawed.'
		];

		return $return;
	}

	/****
	 * READING HISTORY
	 ***/

	/**
	 * Opt the patron into Reading History within the ILS.
	 *
	 * @param  User $patron
	 * @return bool $success  Whether or not the opt-in action was successful
	 */
	public function optInReadingHistory($patron){
		$patronObjectCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		$this->cache->delete($patronObjectCacheKey);

		$success = false;
		$apiInfo = $this->_getApiInfo();

		if ($apiInfo['VersionMajor'] >= 6 && $apiInfo['VersionMinor'] >= 2){
			$operation = 'patrons/' . $patron->ilsUserId . '/checkouts/history/activationStatus';
			$params    = ['readingHistoryActivation' => true];
			$r         = $this->_doRequest($operation, $params, 'POST');
			if ($r === ''){
				// $r can be false and will wrongly match $r == ""
				$success = true;
			}
		}else{
			$success = $this->_curlOptInOptOut($patron, 'OptIn');
		}
		if (!$success){
			$this->logger->warning('Unable to opt in patron ' . $patron->barcode . ' from ILS reading history. Falling back to Pika.');
			return false;
		}else{
			return true;
		}
	}

	/**
	 * Opt out the patron from Reading History within the ILS.
	 *
	 * @param  User $patron
	 * @return bool Whether the opt-out action was successful
	 */
	public function optOutReadingHistory($patron){
		$patronObjectCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		$this->cache->delete($patronObjectCacheKey);

		$success = false;
		$apiInfo = $this->_getApiInfo();

		if ($apiInfo['VersionMajor'] >= 6 && $apiInfo['VersionMinor'] >= 2){
			$operation = 'patrons/' . $patron->ilsUserId . '/checkouts/history/activationStatus';
			$params    = ['readingHistoryActivation' => false];
			$r         = $this->_doRequest($operation, $params, 'POST');
			if ($r == ''){
				$success = true;
			}
		}else{
			$success = $this->_curlOptInOptOut($patron, 'OptOut');
		}
		if (!$success){
			$this->logger->warning('Unable to opt out patron ' . $patron->barcode . ' from ILS reading history. Falling back to Pika.');
		}
		$patron->trackReadingHistory = false;
		$patron->update();

		return true;
	}


	/**
	 * Get API info
	 *
	 * @return array|null
	 */
	protected function _getApiInfo(){
		$r     = $this->_doRequest('about');
		$r     = strip_tags($r);
		$info  = [];
		$lines = preg_split('/\n/', $r, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($lines as $line){
			$line = trim($line);
			if (empty($line) || !strpos($line, ':')){
				continue;
			}
			$parts = explode(':', $line);
			$index = trim($parts[0]);
			$index = str_replace(' ', '', $index);

			if ($index == 'Version'){
				$indexParts = explode('.', $parts[1]);
				if (count($indexParts) >= 2){
					$info['VersionMajor'] = (int)$indexParts[0];
					$info['VersionMinor'] = (int)$indexParts[1];
					if ($info['VersionMajor'] > $this->configArray['Catalog']['api_version']){
						$this->logger->warn('Sierra API reporting higher API version number than set in config.ini ' . $info['VersionMajor'] . ' vs ' . $this->configArray['Catalog']['api_version']);
					}
				}
			}
			$info[$index] = trim($parts[1]);
		}
		return $info;
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
		$patronId = $this->getPatronId($patron);

		if(!$patronId) {
			return false;
		}

		$patronReadingHistoryCacheKey = $this->cache->makePatronKey('history', $patron->id);
		$this->cache->delete($patronReadingHistoryCacheKey);

		$operation = 'patrons/' . $patronId . '/checkouts/history';
		$r         = $this->_doRequest($operation, [], 'DELETE');

		if(!$r) {
			return false;
		}

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
			$h          = new ReadingHistoryEntry();
			$h->id      = $selectedId;
			$h->find(true);
			if ($h){
				$bibId = 0;
			}
		}

		$patronId = $this->getPatronId($patron);
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

	}

	public function hasNativeReadingHistory(){
		return true;
	}


	/**
	 * Fetch a patrons reading history from Sierra ILS
	 *
	 * @param User $patron
	 * @param int $page
	 * @param int $recordsPerPage
	 * @param string $sortOption
	 * @return array|mixed
	 * @throws ErrorException
	 */
	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut"){
		// history enabled?
		if ($patron->trackReadingHistory != 1){
			return ['historyActive' => false, 'numTitles' => 0, 'titles' => []];
		}

		$patronSierraId = $this->getPatronId($patron);

		$patronReadingHistoryCacheKey = $this->cache->makePatronKey('history', $patron->id);
		$patronCachedReadingHistory   = $this->cache->get($patronReadingHistoryCacheKey);
		if (!$patronCachedReadingHistory || isset($_REQUEST['reload'])){
			//TODO: loop to fetch histories with more than a 2000 entries
			$operation = "patrons/" . $patronSierraId . "/checkouts/history";
			$params    = ['limit'     => 2000, // Sierra api max results as of 9-12-2019
			              'sortField' => 'outDate',
			              'sortOrder' => 'desc'];
			$history   = $this->_doRequest($operation, $params);

			if (!$history){
				// Check api error for not opted in. Not in api docs but error is 146
				if(stristr($this->apiLastError, '146')) {
					return ['historyActive' => false, 'numTitles' => 0, 'titles' => []];
				} else {
					$this->logger->warn('Could not get reading history for ' . $patron->barcode, ['API error' => $this->apiLastError]);
					return ['historyActive' => false, 'numTitles' => 0, 'titles' => []];
				}
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
					$titleEntry['linkUrl']     = $record->getGroupedWorkDriver()->getLinkUrl();
					$titleEntry['coverUrl']    = $record->getBookcoverUrl('medium');
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
				$this->cache->set($patronReadingHistoryCacheKey, $history, 21600);
				$this->logger->info("Saving reading history in memcache:" . $patronReadingHistoryCacheKey);
			}

		}else{
			$history = $patronCachedReadingHistory;
			$this->logger->info("Found reading history in memcache:" . $patronReadingHistoryCacheKey);
		}

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
		$patronId = $this->getPatronId($patron);
		if ($patronId == false){
			return false;
		}
		set_time_limit(300);
		$operation    = "patrons/" . $patronId . "/checkouts/history";
		$limitPerCall = 2000; // Sierra api max results as of 9-12-2019
		$params       = ['limit'     => $limitPerCall,
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
	 * Use sierra patron/$patronId/holds/requests/form endpoint to filter solr records down to item-volumes holdable by
	 * patron.
	 * @param $patron User
	 * @param $bibId
	 * @param null $itemsAsVolumes  list of items already provided by a call from the API
	 * @return array|false
	 * @throws ErrorException
	 */
	public function getItemVolumes($patron, $bibId, $itemsAsVolumes = null){
		// get item records from solr
		$itemDetails = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $bibId);
		$solrRecords = $itemDetails->getGroupedWorkDriver()->getRelatedRecord($this->accountProfile->recordSource . ':' . $bibId);

		// get holdable item ids for patron from api.
		// will only be available in v6+
		$holdableItemNumbers = [];
		if (empty($itemsAsVolumes)){
			$apiVersion  = (int)$this->configArray['Catalog']['api_version'];
			if ($apiVersion >= 6){
				$recordNumber = substr($bibId, 2, -1); // remove the .x and the last check digit
				$patronIlsId  = $this->getPatronId($patron);
				$operation    = "patrons/$patronIlsId/holds/requests/form";
				$params       = ['recordNumber' => $recordNumber];
				$res          = $this->_doRequest($operation, $params);

				if (!empty($res->itemsAsVolumes)){
					// holdable ids
					foreach ($res->itemsAsVolumes as $itemAsVolume){
						$holdableItemNumbers[] = $itemAsVolume->id;
					}
				}

				// Even if we get a bad response, we can still fallback to a list of items based on the Pika holdability determinations below

			}
		} else {
			// holdable ids
			foreach ($itemsAsVolumes as $itemAsVolume){
				$holdableItemNumbers[] = $itemAsVolume->id;
			}

		}
		$items = [];
		foreach ($solrRecords['itemDetails'] as $itemDetails){
			// in the list of items provided by the API; skip if not.
			if (!empty($holdableItemNumbers)){
				$itemNumber = substr($itemDetails['itemId'], 2, -1);
				if (!in_array($itemNumber, $holdableItemNumbers)){
					continue;
				}
			} elseif (!$itemDetails['holdable']) {
				continue; // Item is not holdable based on Pika calculations; don't add to list
			}
			$items[] = [
				'itemNumber' => $itemDetails['itemId'],
				'location'   => $itemDetails['shelfLocation'],
				'callNumber' => $itemDetails['callNumber'],
				'status'     => $itemDetails['status']
			];
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
				// -If any $usernamePart fails to match $valid will be false and iteration breaks. Iteration will continue
				// -over next $patronName.
				// -Assuming $patronName = Doe, John
				// -The following will pass:
				// -john doe, john, doe, john Doe, doe John
				// -The following will fail:
				// -johndoe, jo, jo doe, john do
				// above does not currently apply
				// match any part of the name
				// see:
				// #D-3416
				// #D-3417
				if (preg_match('~\\b' . preg_quote($userNamePart) . '\\b~i', $patronName, $m)) {
					$valid = true;
				}
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

	protected function _authPatronIdName($patronId, $username) {
		$operation = 'patrons/' . $patronId;
		$params    = ['fields' => 'names'];

		$r = $this->_doRequest($operation, $params);
		if(!$r) {
			$this->logger->warn('Could not get patron name.', ['patronId'=>$patronId, 'error'=>$this->apiLastError]);
			return false;
		}
		// check the username against name(s) returned from sierra
		$patronNames = $r->names;
		$username = trim($username);
		// break the username into an array
		$username = str_replace('-', ' ', $username);
		$usernameParts = explode(' ', $username);
		$valid = FALSE;

		foreach ($patronNames as $patronName) {
			// first check for a full string match
			// example Doe, John == Doe, John
			if ($patronName == $username) {
				$valid = true;
				break;
			}
			// iterate over each of usernameParts looking for a match in $patronName
			foreach ($usernameParts as $userNamePart) {
				if (preg_match('~\\b' . preg_quote($userNamePart) . '\\b~i', $patronName, $m)) {
					$valid = true;
				}

				if ($valid === true) {
					//$this->logger->info('Logging in patron: ' . $);
					break;
				}
			}
		}
		// end for each
		if ($valid == true) {
			return $patronId;
		}
		return false;
	}

	/**
	 * Validate a barcode and pin
	 *
	 * patrons/validate
	 *
	 * @param string $barcode
	 * @param string $pin
	 * @return string|false Returns patron id on success false on fail.
	 * @throws ErrorException
	 */
	protected function _authBarcodePin(string $barcode, string $pin) {
		$params = [
			'authMethod'   => 'native',
			'patronId'     => $barcode,
			'patronSecret' => $pin
		];

		$patronId = $this->_doRequest('patrons/auth', $params, 'POST');
		if (!$patronId) {
			return false;
		}

		// check that pin matches database
		$patron            = new User();
		$patron->ilsUserId = $patronId;

		// if we don't find a patron in database, do insert. Will be populated with data retrieved from api
		if (!$patron->find(true)){
			$patron            = new User();
			$patron->created   = date('Y-m-d');
			$patron->ilsUserId = $patronId;
			$patron->barcode   = $barcode;
			$patron->setPassword($pin);
			$patron->insert();
		}else{
			// Update the stored pin if it has changed
			$password = $patron->getPassword();
			if ($password != $pin){
				$patron->updatePassword($pin);
			}
		}

		return $patronId;
	}

	//	protected function _authBarcodePin(string $barcode, string $pin) {
//		// if using username field check if username exists
//		// username replaces barcode
//		if($this->hasUsernameField()) {
//			$params = [
//			'varFieldTag'     => 'i',
//			'varFieldContent' => $barcode,
//			'fields'          => 'barcodes',
//			];
//
//			$provisionSierraUserId = null;
//			$operation = 'patrons/find';
//			$r = $this->_doRequest($operation, $params);
//
//			if(!empty($r->barcodes)) {
//				// Note: for sacramento student ids, this call doesn't return any barcodes
//				$barcode             = $r->barcodes[0];
//				$this->patronBarcode = $barcode;
//				// this call also returns the sierra id; keep it so we can skip an extra call after pin validation
//			}
//
//			if (!empty($r->id)){
//				// The call above can return an Id even if it doesn't return a barcode, eg for sacramento students
//				$provisionSierraUserId = $r->id;
//			}
//		}
//
//		$params = [
//			'barcode' => $barcode,
//			'pin'     => $pin,
//		];
//
//		//This setting is required for Sacramento student Ids to get a good pin validation response.
//		//  I suspect that there is an ILS setting that overrides this any way (pascal 2/6/2020)
//		if ($this->configArray['Catalog']['barcodeCaseSensitive']) {
//			$params['caseSensitivity'] = true;
//		} else {
//			$params['caseSensitivity'] = false;
//		}
//
//		if (!$this->_doRequest('patrons/validate', $params, 'POST')) {
//			return false;
//		}
//
//		if (!empty($provisionSierraUserId)){
//			$patronId = $provisionSierraUserId; // now that the user passed validation, set the patron id from the barcode lookup above.
//		} elseif(!$patronId = $this->getPatronId($barcode)){
//			return false;
//		}
//
//		// check that pin matches database
//		$patron            = new User();
//		$patron->ilsUserId = $patronId;
//		$patron->find(true);
//		// if we don't find a patron in database, do insert. Will be populated with data retrieved from api
//		if ($patron->N == 0){
//			$patron->created   = date('Y-m-d');
//			$patron->ilsUserId = $patronId;
//			$patron->barcode   = $barcode;
//			$patron->insert();
//			// insert pin
//			$patron            = new User();
//			$patron->ilsUserId = $patronId;
//			$patron->find(true);
//			if ($patron->N >= 1) {
//				$patron->updatePassword($pin);
//			}
//		}
//
//		// Update the stored pin if it has changed
//		$password = $patron->getPassword();
//		if($password != $pin) {
//			$patron->updatePassword($pin);
//		}
//
//		return $patronId;
//	}

	/**
	 * function _oAuth
	 *
	 * Send oAuth token request
	 *
	 * @return boolean true on success, false otherwise
	 */
	protected function _oAuthToken(){
		global $offlineMode;
		if (!$offlineMode){
			global $instanceName;
			$oauthTokenMemCacheKey = $instanceName . 'sierra_oauth_token';
			if (!isset($_REQUEST['reload'])){
				// check memcache for valid token and set $this
				if ($token = $this->cache->get($oauthTokenMemCacheKey)){
					$this->oAuthToken = $token;
					return true;
				}
			}

			// setup url
			$url = $this->tokenUrl;
			// $this->logger->info('oAuth URL '.$url);
			// grab clientKey and clientSecret from configArray
			$clientKey    = $this->configArray['Catalog']['clientKey'];
			$clientSecret = $this->configArray['Catalog']['clientSecret'];
			//encode key and secret
			$requestAuth = base64_encode($clientKey . ':' . $clientSecret);

			$headers = [
				'Host'          => parse_url($url, PHP_URL_HOST),
				'Authorization' => 'Basic ' . $requestAuth,
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'grant_type'    => 'client_credentials'
			];

			$opts = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => false,
                CURLOPT_SSL_VERIFYPEER => false, // REMOVE
                CURLOPT_SSL_VERIFYHOST => false, // REMOVE
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
			if ($c->isCurlError()){
				// This will probably never be triggered since we have the try/catch above.
				$message            = 'cUrl Error: ' . $c->errorCode . ': ' . $c->errorMessage;
				$this->apiLastError = $message;
				$this->logger->error($message, ['oauth_url' => $url]);
				return false;
			}elseif ($cInfo['http_code'] != 200){ // check the request returned success (HTTP 200)
				$message            = 'API Error: ' . $c->errorCode . ': ' . $c->errorMessage;
				$this->apiLastError = $message;
				$this->logger->error($message, ['oauth_url' => $url]);
				return false;
			}
			// make sure to set last error to false if no errors.
			$this->apiLastError = false;
			// setup memCache vars
			$token   = $c->response->access_token;
			$expires = $c->response->expires_in;
			$c->close();
			$this->oAuthToken = $token;
			$this->cache->set($oauthTokenMemCacheKey, $token, $expires);
			$this->logger->info('Got new oAuth token.');
			return true;
		}
		return false;
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
	protected function _doRequest($operation, $params = [], $method = 'GET', $extraHeaders = null) {
		$this->apiLastError = false;
		// setup headers
		// These headers are common to all Sierra API except token requests.
		$userAgent = empty($this->configArray['Catalog']['catalogUserAgent']) ? 'Pika' : $this->configArray['Catalog']['catalogUserAgent'];
		$headers = [
			'Host'           => parse_url($this->apiUrl, PHP_URL_HOST),
			'Authorization' => 'Bearer ' . $this->oAuthToken,
			'User-Agent'     => $userAgent,
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
            CURLOPT_SSL_VERIFYPEER => false, // REMOVE
            CURLOPT_SSL_VERIFYHOST => false, // REMOVE
		];

		// instantiate the Curl object and set the base url
		if($operation == 'about'){
			$operationUrl = $this->aboutUrl;
		}else{
			$operationUrl = $this->apiUrl . $operation;
		}
		try {
			$c = new Curl();
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['stacktrace' => $e->getTraceAsString()]);
			return false;
		}
		$c->setHeaders($headers);
		$c->setOpts($opts);

		// make the request using the proper method.
		$method = strtolower($method);
		switch($method) {
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
			case 'get':
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
			$this->logger->warning($message, ['backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)]);
			return false;
		} elseif (isset($c->response->code) || $c->isHttpError()) {
			// this will be a 4xx response
			// first we need to check the response for a code, message from the API because many failed operations (ie,
			// freezeing a hold) will send back a 4xx response code if the operation couldn't be completed.

			// when authenticating with pins, a 400 response will be returned for bad credentials.
			// check for failed authentication
			if($c->errorCode === 400 && $c->response->code === 108) {
				// bad credentials
				$message = 'Authentication Error: ' . $c->response->httpStatus . ': ' . $c->response->name;
				$this->apiLastError = $message;
				$this->logger->debug($message);
				return false;
			}
			if(isset($c->response->code)) {
				//$message = 'API Error: ' . $c->response->code . ': ' . $c->response->name;
				$message = 'API Error: ' . $c->response->code . ': '; // name usually redundant part of the description below
				if(isset($c->response->description)){
					$message .= ' ' . $c->response->description;
					$this->apiLastErrorForPatron = $c->response->description;
					$this->logger->warning($message, ['api_response' => $c->response]);
				} elseif (isset($c->response->name)){
					// So far, this section is needed for :
					// * getting item hold information from bib-level hold call
					$message .= ' ' . $c->response->name;
					$this->logger->warning($message, ['api_response' => $c->response]);
				}
			} else {
				$message                     = 'HTTP Error: ' . $c->getErrorCode() . ': ' . $c->getErrorMessage();
				$this->apiLastErrorForPatron = $c->getErrorMessage();
				$this->logger->warning($message, [$operationUrl, $params]);
			}
			$this->apiLastError = $message;
			if (!empty($c->response->details->itemsAsVolumes)){
				return $c->response;
			} elseif (!empty($c->response->detail->itemIds)){
				// API version 5.5 has a list of itemIds instead when "Volume record selection is required to proceed."
				return $c->response;
			}
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

		$headers       = [
			'Accept'          => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
			'Cache-Control'   => 'max-age=0',
			'Connection'      => 'keep-alive',
			'Accept-Charset'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Accept-Language' => 'en-us,en;q=0.5',
			'User-Agent'      => 'Pika'
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
		$postData = $this->accountProfile->usingPins() ? [
			'code' => $patron->barcode,
			'pin'  => $patron->getPassword()
		] : [
			'name' => $patron->cat_username,
			'code' => $patron->barcode
		];
		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r        = $c->post($loginUrl, $postData);

		if ($c->isError()){
			$c->close();
			return false;
		}

		if (!stristr($r, $patron->cat_username)){
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
				$casLoginUrl = $vendorOpacUrl . $casUrl;
				$r           = $c->post($casLoginUrl, $postData);
				if(!stristr($r, $patron->cat_username)) {
					$this->logger->warning('cas login failed.');
					return false;
				}
				$this->logger->info('cas login success.');
			}
		}

		// now we can call the optin or optout url
		$scope    = $this->configArray['OPAC']['defaultScope'] ?? '93';
		$patronId = $this->getPatronId($patron);
		$optUrl   = $vendorOpacUrl . "/patroninfo~S" . $scope . "/" . $patronId . "/readinghistory/" . $optInOptOut;

		//$c->setUrl();
		$r = $c->get($optUrl);

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
	 * @param Library $library
	 * @return array
	 */
	protected function getSelfRegHomeLocations(Library $library): array{
		$homeLocations = [];
		$l                        = new Location();
		$l->libraryId             = $library->libraryId;
		$l->validHoldPickupBranch = '1';
		$l->orderBy('displayName');
		if ($l->find()){
			$homeLocations = $l->fetchAll('code', 'displayName');
		}
		return $homeLocations;
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
	protected function _getBibIdFromItemId($itemId){
		$operation = "items/" . $itemId;
		$params    = ["fields" => "bibIds"];
		$iR        = $this->_doRequest($operation, $params);
		if ($iR){
			$bid = $iR->bibIds[0];
		}else{
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
			$offset  = (count($messageParts) - 1);
			$message = $messageParts[$offset];
			$return  = $message;
		} else {
			$return = false;
		}
		return $return;
	}

	protected function getCheckDigit($recordId){
		$baseId      = preg_replace('/\.?[bij]/', '', $recordId);
		$sumOfDigits = 0;
		for ($i = 0; $i < strlen($baseId); $i++){
			$curDigit = substr($baseId, $i, 1);
			$multiplier  = (strlen($baseId) + 1) - $i;
			$sumOfDigits += $multiplier * $curDigit;
		}
		$modValue = $sumOfDigits % 11;
		if ($modValue == 10){
			return 'x';
		}else{
			return $modValue;
		}
	}

	public function getNumHoldsOnRecord($bibId){
		// Values tracked in ils_hold_summary table by export/extractor process
		return false;
	}
}
