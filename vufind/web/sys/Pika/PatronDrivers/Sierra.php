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
 * // TODO: We need a standard for caching and keys so they can be reused.
 * caching keys follow this pattern
 * User
 * patron_{barcode}_{object}
 * ie; patron_123456789_checkouts, patron_123456789_holds
 * the patron object is patron_{barcode}_patron
 * when calling an action (ie; placeHold) cache should be unset
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
use Location;
use MarcRecord;
use RecordDriverFactory;
use User;


class Sierra extends PatronDriverInterface {

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

	public function __construct($accountProfile) {
		// Adding standard globals to class to avoid repeated calling of global.
		global $configArray;
		global $memCache;
		//global $logger;
		$this->configArray    = $configArray;
		$this->memCache       = $memCache;
		$this->accountProfile = $accountProfile;
		//$this->logger         = $logger;
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

	public function getMyCheckouts($patron){
		// TODO: Implement getMyCheckouts() method.
	}

	public function renewItem($patron, $renewItemId){
		// TODO: Implement renewItem() method.
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

		$patronObjectCacheKey = 'patron_' . $patronId . '_patron';
		if ($pObj = $this->memCache->get($patronObjectCacheKey)) {
			return $pObj;
		}

		$createPatron = false;
		$updatePatron = false;

		$patron = new User();
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
		if($pInfo->emails[0] != $patron->email) {
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
		$e = $this->memCache->set($patronObjectCacheKey, $patron, MEMCACHE_COMPRESSED, $this->configArray['Caching']['patron_profile']);
		return $patron;
	}

	/**
	 * Get the unique Sierra patron ID by searching for barcode.
	 *
	 * @param  User|string $patronOrBarcode
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

		$this->memCache->set($patronIdCacheKey, $r->id, 0, $this->configArray['Caching']['koha_patron_id']);

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
		// TODO: finish this up.
		//return [];
		// find the sierra patron id
		if (!isset($this->patronId)) {
			$patronId = $this->getPatronId($patron);
			if($patronId) {
				$this->patronId = $patronId;
			} else {
				return false;
			}
		}
		$patronFinesCacheKey = 'patron_'.$this->patronId.'_fines';
		if($this->memCache->get($patronFinesCacheKey)) {
			return $this->memCache->get($patronFinesCacheKey);
		}
		// check memCache

		$params = [
			'fields' => 'default,id,item,assessedDate,itemCharge,chargeType,paidAmount,datePaid,description,returnDate,location'
		];
		$operation = 'patrons/' . $this->patronId . '/fines';
		$fInfo = $this->_doRequest($operation, $params);
		if(!$fInfo) {
			// TODO: check last error.
			return false;
		}
		//
		if($fInfo->total == 0) {
			return [];
		}

		$fines = $fInfo->entries;
		$r = [];
		foreach($fines as $fine) {
			$amount = number_format($fine->itemCharge, 2);
			$date   = date('m-d-Y', strtotime($fine->assessedDate));
			$r[] = [
				'date'              => $date,
				'reason'            => $fine->chargeType->display,
				'amount'            => $amount,
				'amountOutstanding' => $amount,
				'message'           => ''
			];
		}

		$this->memCache->set($patronFinesCacheKey, $r, 0, $this->configArray['Caching']['patron_profile']);
		return $r;
	}

	/**
	 *
	 * patrons/$patronId/holds
	 * default,locations
	 * @param  User $patron
	 * @return array|bool
	 */

	public function getMyHolds($patron){

		if(!$patronId = $this->getPatronId($patron)) {
			// TODO: need to do something here
			return false;
		}

		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		if($patronHolds = $this->memCache->get($patronHoldsCacheKey)) {
			return $patronHolds;
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
		//
		$availableHolds   = [];
		$unavailableHolds = [];
		foreach ($holds->entries as $hold) {
			// 1. standard stuff
			$h['holdSource']      = 'ILS';
			$h['userId']          = $pikaPatronId;
			$h['user']            = $displayName;

			// 2. get what's available from this call
			$h['position']              = $hold->priority;
			$h['frozen']                = $hold->frozen;
			$h['create']                = strtotime($hold->placed); // date hold created
			$h['automaticCancellation'] = strtotime($hold->notNeededAfterDate); // not needed after date
			$h['expire']                = isset($hold->pickUpByDate) ? strtotime($hold->pickUpByDate) : false; // pick up by date
			// cancel id
			preg_match($this->urlIdRegExp, $hold->id, $m);
			$h['cancelId'] = $m[1];

			// status, cancelable, freezable
			switch ($hold->status->code) {
				case "0":
					$status     = "On hold";
					$cancelable = true;
					$freezeable = true;
					break;
				case "b":
				case "j":
				case "i":
					$status     = "Ready";
					$cancelable = true;
					$freezeable = false;
					break;
				case "t":
					$status     = "In transit";
					$cancelable = true;
					$freezeable = false;
					break;
				default:
					$status     = "Unknown";
					$cancelable = false;
					$freezeable = true;
			}
			$h['status']    = $status;
			$h['freezeable']= $freezeable;
			$h['cancelable']= $cancelable;

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

			// record type and record id
			$recordType = $hold->recordType;
			preg_match($this->urlIdRegExp, $hold->record,$m);
			// for item level holds we need to grab the bib id.
			$id = $m[1];
			if($recordType == "i") {
				// items/{itemId}
				$operation = "items/".$id;
				$params = ["fields"=>"bibIds"];
				$r = $this->_doRequest($operation, $params);
				if($r) {
					$id = $r->bibIds[0];
				}
			}
			// for Pika we need the check digit.
			$recordXD  = $this->getCheckDigit($id);

			// get more info from record
			$bibId = 'b'.$id.$recordXD;

			$recordDriver = new MarcRecord($this->accountProfile->recordSource . ":" . $bibId);
			if ($recordDriver->isValid()){
				$h['id']              = $recordDriver->getUniqueID();
				$h['shortId']         = $recordDriver->getShortId();
				$h['title']           = $recordDriver->getTitle();
				$h['sortTitle']       = $recordDriver->getSortableTitle();
				$h['author']          = $recordDriver->getAuthor();
				$h['format']          = $recordDriver->getFormat(); // todo: this isn't pulling in tyhe format
				$h['format_category'] = $recordDriver->getFormatCategory();
				$h['link']            = $recordDriver->getRecordUrl();
				$h['coverUrl']        = $recordDriver->getBookcoverUrl('medium');
			};

			if($hold->status->code == "b" || $hold->status->code == "j" || $hold->status->code == "i") {
				$availableHolds[] = $h;
			} else {
				$unavailableHolds[] = $h;
			}

		} // end foreach

		$return['available']   = $availableHolds;
		$return['unavailable'] = $unavailableHolds;

		$this->memCache->set($patronHoldsCacheKey, $return, 0, $this->configArray['Caching']['patron_profile']);

		return $return;
	}

	/**
	 * Determines the hold type and places the hold
	 *
	 * patrons/{patronId}}/holds/requests
	 *
	 * @param User        $patron
	 * @param string      $recordId
	 * @param string      $pickupBranch
	 * @param string|null $cancelDate
	 * @return array
	 */
	public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null) {

		$d = \DateTime::createFromFormat('m/d/Y', $cancelDate); // convert needed by date
		$recordType     = substr($recordId, 1,1); // determine the hold type b = bib, i = item, j = volume
		$recordNumber   = substr($recordId, 2, -1); // remove the .x and the last check digit
		$pickupLocation = $pickupBranch;
		$neededBy       = $d->format('Y-m-d');
		$patronId       = $this->getPatronId($patron);

		// delete memcache holds
		$patronHoldsCacheKey = "patron_".$patron->barcode."_holds";
		$this->memCache->delete($patronHoldsCacheKey);

		// get title of record
		$record = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);
		$recordTitle  = $record->isValid() ? $record->getTitle() : null;

		$params = [
			"recordType"     => $recordType,
			"recordNumber"   => (int)$recordNumber,
			"pickupLocation" => $pickupLocation,
			"neededBy"       => $neededBy
		];

		$operation = "patrons/{$patronId}/holds/requests";

		$r = $this->_doRequest($operation, $params, "POST");
		$return = [];
		// oops! something went wrong.
		if(!$r) {
			$return['success'] = false;
			if ($this->apiLastError) {
				$return['message'] = $this->apiLastError;
			} else {
				$return['message'] = "Unable to place your hold. Please contact our library.";
			}
			return $return;
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
		// TODO: Implement placeItemHold() method.
	}

	public function placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch){
		// TODO: Implement placeVolumeHold() method.
	}

	public function changeHoldPickupLocation($patron, $holdId, $newPickupLocation){
		// TODO: Implement changeHoldPickupLocation() method.
	}

	public function cancelHold($patron, $cancelId){
		// TODO: Implement cancelHold() method.
	}

	/**
	 * @param      $patron
	 * @param      $holdToFreezeId
	 * @param null $dateToReactivate
	 */
	public function freezeHold($patron, $holdToFreezeId, $dateToReactivate = null){

	}

	public function thawHold($patron, $holdToThawId){
		// TODO: Implement thawHold() method.
	}

	public function hasNativeReadingHistory(){
		// TODO: Implement hasNativeReadingHistory() method.
	}

	public function loadReadingHistoryFromIls($patron, $loadAdditional = null){
		// TODO: Implement loadReadingHistoryFromIls() method.
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
			"barcode" =>$barcode,
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
		if ($token = $this->memCache->get("sierra_oauth_token")) {
			$this->oAuthToken = $token;
			return TRUE;
		}
		// setup url
		$url = $this->apiUrl."token";
		// grab clientKey and clientSecret from configArray
		$clientKey    = $this->configArray['Catalog']['clientKey'];
		$clientSecret = $this->configArray['Catalog']['clientSecret'];
		//encode key and secret
		$requestAuth  = base64_encode($clientKey . ':' . $clientSecret);

		$headers = [
			'Host: '.$this->configArray['Catalog']['sierraApiHost'],
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
			$message = $c->errorCode.': '.$c->errorMessage;
			$this->apiLastError = $message;
			return false;
		} elseif ($cInfo['http_code'] != 200) { // check the request returned success (HTTP 200)
			$message = 'API Error '.$c->response->code.': '.$c->response->name;
			$this->apiLastError = $message;
			return false;
		}
		// make sure to set last error to false if no errors.
		$this->apiLastError = false;
		// setup memCache vars
		$token   = $c->response->access_token;
		$expires = $c->response->expires_in;
		$c->close();
		$this->oAuthToken = $token;
		$this->memCache->set("sierra_oauth_token", $token, 0, $expires);
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
			'Host'           => $this->configArray['Catalog']['sierraApiHost'],
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
				$c->delete($operationUrl, $params);
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
			if(isset($c->response->code)) {
				$message = 'API Error ' . $c->response->code . ': ' . $c->response->name;
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