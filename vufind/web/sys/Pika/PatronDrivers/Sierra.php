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
 * memcache keys
 *
 * caching keys follow this pattern
 * patron_{barcode}_{object}
 * ie; patron_123456789_checkouts, patron_123456789_holds
 * the patron object is patron_{barcode}_patron
 * when calling an action (ie; placeHold, freezeHold) both the patron cache and the object cache should be unset
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
use \Pika\Logger;
use \Pika\Cache;
use Location;
use MarcRecord;
use RecordDriverFactory;
use User;


class Sierra {

	// Adding global variables to class during object construction to avoid repeated calls to global.
	public  $memCache;
	private $configArray;
	// ----------------------
	/* @var $oAuthToken oAuth2Token */
	private $oAuthToken;
	/* @var $apiLastError false|string false if no error or last error message */
	private $apiLastError = false;
	// Needs to be public
	public $accountProfile;
	/* @var $patronId int Sierra patron id used for all REST calls. */
	private $patronId;
	/* @var $patronBarcode string The patrons barcode */
	private $patronBarcode;
	/* @var $apiUrl string The url for the Sierra API */
	private $apiUrl;
	// many ids come from url. example: https://sierra.marmot.org/iii/sierra-api/v5/items/5130034
	private $urlIdRegExp = "/.*\/(\d*)$/";

	private $logger;

	public function __construct($accountProfile) {
		// Adding standard globals to class to avoid repeated calling of global.
		global $configArray;
		//global $memCache;

		$this->configArray    = $configArray;
		//$this->memCache       = $memCache;
		$this->accountProfile = $accountProfile;
		// TODO: logger.
		$this->logger = new Logger('SierraPatronAPI');
		// TODO: cache
		$cache = initCache();
		$this->memCache = new Cache($cache);
		// build the api url
		// JIC strip any trailing slash and spaces.
		$apiUrl = trim($accountProfile->patronApiUrl,'/ ');
		$apiUrl = $apiUrl . '/iii/sierra-api/v'.$configArray['Catalog']['api_version'] . '/';
		$this->apiUrl = $apiUrl;

		// grab an oAuthToken
		if(!isset($this->oAuthToken)) {
			if(!$this->_oAuthToken()) {
				// logging happens in _oAuthToken()
				# TODO: what is the return if error
				return FALSE;
			}
		}
	}

	/**
	 * Retrieve a patrons checkouts
	 *
	 * GET patrons/{patronId}/checkouts?fields=default%2Cbarcode
	 *
	 * @param $patron
	 * @return array|false
	 */
	public function getMyCheckouts($patron){

		$patronCheckoutsCacheKey = "patron_".$patron->barcode."_checkouts";
		if($patronCheckouts = $this->memCache->get($patronCheckoutsCacheKey)) {
			$this->logger->info("Found checkouts in memcache:".$patronCheckoutsCacheKey);
			return $patronCheckouts;
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
				$checkout['coverUrl']       = '/interface/themes/marmot/images/InnReachCover.png';
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

		$this->memCache->set($patronCheckoutsCacheKey, $checkouts, $this->configArray['Caching']['patron_profile']);
		$this->logger->info("Saving checkouts in memcache:".$patronCheckoutsCacheKey);

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
	 * @param   string  $username         The patron username or barcode
	 * @param   string  $password         The patron barcode or pin
	 * @param   boolean $validatedViaSSO  FALSE
	 *
	 * @return  User|null           User object
	 *                              If an error occurs, return a exception
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
			// TODO: log error
			trigger_error("ERROR: Invalid loginConfiguration setting.", E_USER_ERROR);
		}
		// can't find patron
		if (!$patronId) {
			// need a better return
			return null;
		}

		$this->patronId = $patronId;

		$patron = $this->getPatron($patronId);

		return $patron;
	}

	/**
	 * @param int $patronId
	 * @return User|null
	 */
	public function getPatron($patronId = null) {
		// grab everything from the patron record the api can provide.
		// titles on hold to patron object
		if(!isset($patronId) && !isset($this->patronId)) {
			trigger_error("ERROR: getPatron expects at least on parameter.", E_USER_ERROR);
		} else {
			$patronId = isset($patronId) ? $patronId : $this->patronId;
		}

		$patronObjectCacheKey = 'patron_'.$this->patronBarcode.'_patron';
		if ($pObj = $this->memCache->get($patronObjectCacheKey)) {
			$this->logger->info("Found patron in memcache:".$patronObjectCacheKey);
			return $pObj;
		}

		$createPatron = false;
		$updatePatron = false;

		$patron = new User();
		$patron->barcode = $this->patronBarcode;
		// check if the user exists in database
		// use barcode as sierra patron id is no longer be stored in database as username.
		// get the login configuration barcode_pin or name_barcode
		$loginMethod    = $this->accountProfile->loginConfiguration;
		$patron->source = $this->accountProfile->name;
		if ($loginMethod == "barcode_pin") {
			$patron->cat_username = $this->patronBarcode;
		} else {
			$patron->cat_password = $this->patronBarcode;
		}
		// does the user exist in database?
		if(!$patron->find(true)) {
			$createPatron = true;
		}

		// make api call for info
		$params = [
			'fields' => 'names,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,barcodes,patronType,patronCodes,createdDate,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate'
		];
		$operation = 'patrons/'.$patronId;
		$pInfo = $this->_doRequest($operation, $params);
		if(!$pInfo) {
			// TODO: check last error.
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
		}
		// check phones
		$homePhone   = '';
		$mobilePhone = '';
		foreach($pInfo->phones as $phone) {
			if($phone->type == 't') {
				$homePhone  = $phone->number;
			} elseif ($phone->type == 'o') {
				$mobilePhone = $phone->number;
			}
		}
		// TODO: Need to figure out which phone to use. Maybe mobile phone?
		if(isset($homePhone) && $patron->phone != $homePhone) {
			$updatePatron = true;
			$patron->phone = $homePhone;
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
		// address1 and address2
		foreach ($pInfo->addresses as $address) {
			// a = primary address, h = alt address
			if ($address->type == 'a') {
				$patron->address1 = $address->lines[0];
				$patron->address2 = $address->lines[1];
			}
		}
		if(!isset($patron->address1)) {
			$patron->address1 = '';
		}
		if(!isset($patron->address2)) {
			$patron->address2 = '';
		}
		// city state zip
		if($patron->address2 != '') {
			$addressParts = explode(',', $patron->address2);
			// some libraries may not use ','  after city so make sure we have parts
			if (count($addressParts) > 1 ){
				$city = trim($addressParts[0]);
				$stateZip = trim($addressParts[1]);
				$stateZipParts = explode(' ', $stateZip);
				$state = trim($stateZipParts[0]);
				$zip   = trim($stateZipParts[1]);
			} else {
				$regExp = "/^([^,]+)\s([A-Z]{2})(?:\s(\d{5}))?$/";
				preg_match($regExp, $patron->address2, $matches);
				if($matches) {
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
		// mobile phone
		if(isset($mobilePhone)) {
			$patron->mobileNumber = $mobilePhone;
		} else {
			$patron->mobileNumber = '';
		}
		// account expiration
		try {
			$expiresDate = new \DateTime($pInfo->expirationDate);
			$patron->expires = $expiresDate->format('m-d-Y');
			$nowDate     = new \DateTime('now');
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
			// TODO: need to log the error
			echo $e->getMessage();
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
			$patron->insert();
		} elseif ($updatePatron && !$createPatron) {
			$patron->update();
		}
		$this->logger->info("Saving patron to memcache:".$patronObjectCacheKey);
		$this->memCache->set($patronObjectCacheKey, $patron, $this->configArray['Caching']['patron_profile']);
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
		if(isset($this->patronId)) {
			return $this->patronId;
		}
		// if a patron object was passed
		if(is_object($patronOrBarcode)) {
			$barcode = $patronOrBarcode->barcode;
			$barcode = trim($barcode);
		} elseif (is_string($patronOrBarcode)) {
			$barcode = $patronOrBarcode;
		}

		$patronIdCacheKey = "patron_".$barcode."barcode";
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
			return false;
		}

		$this->memCache->set($patronIdCacheKey, $r->id, $this->configArray['Caching']['koha_patron_id']);

		$this->patronId = $r->id;

		return $this->patronId;
	}

	public function updatePatronInfo($patron, $canUpdateContactInfo){
		// TODO: Implement updatePatronInfo() method.
	}

	/**
	 * Get fines for a patron
	 * GET patrons/{uid}/fines
	 * Returns array of fines
	 * array(
	 * [
	 * amount,
	 * amountOutstanding,
	 * date,
	 * reason,
	 * message
	 * ]
	 *
	 * @param $patron User
	 * @return array|bool
	 */
	public function getMyFines($patron){

		// find the sierra patron id
		if (!isset($this->patronId)) {
			$patronId = $this->getPatronId($patron);
			if($patronId) {
				$this->patronId = $patronId;
			} else {
				return false;
			}
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
		$operation = 'patrons/'.$this->patronId.'/fines';
		$fInfo = $this->_doRequest($operation, $params);
		if(!$fInfo) {
			// TODO: check last error.
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
		$this->memCache->set($patronFinesCacheKey, $r, $this->configArray['Caching']['patron_profile']);
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
	public function getMyHolds($patron){

		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		if($patronHolds = $this->memCache->get($patronHoldsCacheKey)) {
			$this->logger->info("Found holds in memcache:".$patronHoldsCacheKey);
			return $patronHolds;
		}

		if(!$patronId = $this->getPatronId($patron)) {
			// TODO: need to do something here
			return false;
		}

		$operation = "patrons/".$patronId."/holds";
		$params=["fields"=>"default,pickupByDate,frozen"];
		$holds = $this->_doRequest($operation, $params);

		if(!$holds) {
			// check last error
			// todo: message? log?
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
			$h['expire']                = isset($hold->pickUpByDate) ? strtotime($hold->pickUpByDate) : false; // pick up by date
			// cancel id
			preg_match($this->urlIdRegExp, $hold->id, $m);
			$h['cancelId'] = $m[1];

			// status, cancelable, freezable
			switch ($hold->status->code) {
				case '0':
					$status     = 'On hold';
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
					$status       = "Requested from Prospector";
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
				// get the hold id
				preg_match($this->urlIdRegExp, $hold->id, $mIr);
				$innReachHoldId = $mIr[1];
				$innReach = new InnReach();
				$titleAndAuthor = $innReach->getHoldTitleAuthor($innReachHoldId);
				if(!$titleAndAuthor) {
					// todo: this needs attention
					continue;
				}
				$h['title']              = $titleAndAuthor['title'];
				$h['author']             = $titleAndAuthor['author'];
				$h['sortTitle']          = $titleAndAuthor['sort_title'];
				$h['coverUrl']           = '/interface/themes/marmot/images/InnReachCover.png';
				$h['freezeable']         = false;
				$h['locationUpdateable'] = false;
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

		$this->memCache->set($patronHoldsCacheKey, $return, $this->configArray['Caching']['patron_profile']);
		$this->logger->info("Saving holds in memcache:".$patronHoldsCacheKey);

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
			$d        = \DateTime::createFromFormat('m/d/Y', $cancelDate); // convert needed by date
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
				$return['message'] = $this->apiLastError;
			} else {
				$return['message'] = "Unable to change pickup location. Please contact your library for further assistance.";
			}
			return $return;
		}
		// todo: get title
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
				$return['message'] = $this->apiLastError;
			} else {
				$return['message'] = "Unable to cancel your hold. Please contact your library for further assistance.";
			}
			return $return;
		}
		// todo: get title
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
		// todo: get title
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
		// todo: show this search test
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
				// todo: should fall back to api here
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
			$titleEntry['details']      = ''; // todo: nothing to put here
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
		// TODO: Implement optOutReadingHistory() method.
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
			$this->patronId = $result;
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

		$params = [
			"barcode" => $barcode,
			"pin"     => $pin
		];

		if (!$this->_doRequest("patrons/validate", $params, "POST")) {
			// todo: need to do error checks here
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
	 */
	private function _oAuthToken() {
		// check memcache for valid token and set $this
		$this->logger->info('Checking for oAuth token in memcache');
		if ($token = $this->memCache->get("sierra_oauth_token")) {
			$this->logger->info('Found oAuth token in memcache');
			$this->oAuthToken = $token;
			return TRUE;
		}
		$this->logger->info('No oAuth token in memcache. Requesting new toke.');
		// setup url
		$url = $this->apiUrl."token";
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

		try {
			$c = new Curl();
		} catch (ErrorException $e) {
			// TODO: log exception
			return false;
		}

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
			$this->logger->info($message);
			return false;
		} elseif ($cInfo['http_code'] != 200) { // check the request returned success (HTTP 200)
			$message = 'API Error: '.$c->response->code.': '.$c->response->name;
			$this->apiLastError = $message;
			$this->logger->info($message);
			return false;
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
		return TRUE;
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
		} catch (ErrorException $e) {
			// TODO: log exception, set curl error
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
			return false;
		} elseif ($c->isHttpError()) {
			// this will be a 4xx response
			// first we need to check the response for a code, message from the API because many failed operations (ie,
			// freezeing a hold) will send back a 4xx response code if the operation couldn't be completed.
			if(isset($c->response->code)) {
				$message = 'API Error: ' . $c->response->code . ': ' . $c->response->name;
				if(isset($c->response->description)){
					$message = $message . " " . $c->response->description;
				}
			} else {
				$message = 'HTTP Error: '.$c->getErrorCode().': '.$c->getErrorMessage();
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
