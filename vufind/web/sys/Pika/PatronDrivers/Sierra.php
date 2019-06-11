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
 * All keys are stored in memcache using this scheme (XXX = patron id or barcode):
 * patron_XXX_uid   -> sierra uid
 * patron_XXX_obj   -> cached patron object
 * patron_XXX_info  -> cached patron extra info (patron record returned from Sierra API)
 * patron_XXX_fines -> patron fines object
 *
 *
 * @category Pika
 * @package  Patron
 * @author   Chris Froese
 * @author   Pascal Brammeier
 * Date      5/13/2019
 *
 */
namespace Pika\PatronDrivers;

//require_once __DIR__.'/PatronDriverInterface.php';
//require_once __DIR__.'/Traits/PatronHoldsOperations.php';
//require_once __DIR__.'/Traits/PatronCheckOutsOperations.php';
//require_once __DIR__.'/Traits/PatronFineOperations.php';
//require_once __DIR__.'/Traits/PatronReadingHistoryOperations.php';
require_once 'C:\Composer\vendor\php-curl-class\php-curl-class\src\Curl\Curl.php';
use Curl\Curl;
use Location;
use User;


class Sierra extends PatronDriverInterface {

    //use PatronHoldsOperations;
    //use PatronCheckOutsOperations;
    //use PatronFineOperations;
    //use PatronReadingHistoryOperations;

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

    public function __construct($accountProfile) {
        // Adding standard globals to class to avoid repeated calling of global.
        global $configArray;
        global $memCache;
        $this->configArray    = $configArray;
        $this->memCache       = $memCache;

        $this->accountProfile = $accountProfile;
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
        $loginMethod = $this->accountProfile->loginConfiguration;
        // check patron credentials depending on login config.
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

    public function getPatron($patronId = null) {
        // grab everything from the patron record the api can provide.
        if(!isset($patronId) && !isset($this->patronId)) {
            trigger_error("ERROR: getPatron expects at least on parameter.", E_USER_ERROR);
        } else {
            $patronId = isset($patronId) ? $patronId : $this->patronId;
        }

        $pObjCacheKey = 'patron_' . $patronId . '_obj';
        if ($pObj = $this->memCache->get($pObjCacheKey)) {
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
            $city = trim($addressParts[0]);
            $stateZip = trim($addressParts[1]);
            $stateZipParts = explode(' ', $stateZip);
            $state = trim($stateZipParts[0]);
            $zip   = trim($stateZipParts[1]);
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
        // holds
        //$this->getMyHolds();

        if($createPatron) {
            $patron->created = date('Y-m-d');
            $patron->insert();
        } elseif ($updatePatron && !$createPatron) {
            $patron->update();
        }
        $this->memCache->set($pObjCacheKey, $patron, MEMCACHE_COMPRESSED, $this->configArray['Caching']['patron_profile']);
        return $patron;
    }

    public function getPatronInfo($uid = null) {
        // GET patrons/{uid}
        // names, addresses (a = main, p = alternate, h = alternate), phones (t = main,o = mobile), expirationDate,
        // homeLibraryCode, moneyOwed, patronType, homeLibraryCode

        // grab everything from the patron record the api can provide.
        if(!isset($uid) && !isset($this->patronId)) {
            trigger_error("ERROR: getPatronInfo expects at least on parameter. ", E_USER_ERROR);
        } else {
            $uid = isset($uid) ? $uid : $this->patronId;
        }

        $cacheKey = 'patron_' . $uid . '_info';
        if ($pInfo = $this->memCache->get($cacheKey)) {
            return $pInfo;
        }

        $params = [
          'fields' => 'names,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,barcodes,patronType,patronCodes,createdDate,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate'
        ];
        $operation = 'patrons/'.$uid;
        $pInfo = $this->_doRequest($operation, $params);
        if(!$pInfo) {
            // TODO: check last error.
            return false;
        }

        $this->memCache->set($cacheKey, $pInfo, MEMCACHE_COMPRESSED, $this->configArray['Caching']['patron_profile']);
        return $pInfo;
    }

    /**
     * Get the unique Sierra patron ID by searching for barcode.
     *
     * @param  $patron
     * @return int
     */
    public function getPatronId($patron)
    {
        $barcode = $patron->barcode;
        $barcode = trim($barcode);
        $params = [
          'varFieldTag'     => 'b',
          'varFieldContent' => $barcode,
          'fields'          => 'id',
        ];
        // make the request
        $r = $this->_doRequest('patrons/find', $params);
        // there was an error with the last call
        if(!$r) {
            return false;
        }
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
        $finesCacheKey = 'patron_'.$patronId.'_fines';
        if($this->memCache->get($finesCacheKey)) {
            return $this->memCache->get($finesCacheKey);
        }
        // check memCache

        $params = [
          'fields' => 'id,item,assessedDate,itemCharge,chargeType,paidAmount,datePaid,description,returnDate,location'
        ];
        $operation = 'patrons/'.$patronId.'/fines';
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

        $this->memCache->set($finesCacheKey, $r, 0, $this->configArray['Caching']['patron_profile']);
        return $r;
    }

    public function getMyHolds($patron){
        // TODO: Implement getMyHolds() method.
    }

    public function placeHold($patron, $recordId, $pickupBranch, $cancelDate = null){
        // TODO: Implement placeHold() method.

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

    public function freezeHold($patron, $holdToFreezeId, $dateToReactivate = null){
        // TODO: Implement freezeHold() method.
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
     * @return $uid|FALSE Returns unique patron id from Sierra on success or FALSE on fail.
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

    private function _authBarcodePin($barcode, $pin) {

        return false;

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
        $url = $this->configArray['Catalog']['sierraApiURL'];
        // grab clientKey and clientSecret from configArray
        $clientKey    = $this->configArray['Catalog']['sierraApiClientKey'];
        $clientSecret = $this->configArray['Catalog']['sierraApiClientSecret'];
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
            $c = new Curl($url);
        } catch (ErrorException $e) {
            // TODO: log exception
            return false;
        }

        $c->setHeaders($headers);
        $c->setOpts($opts);
        $c->post('token');
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
          'Host: '.$this->configArray['Catalog']['sierraApiHost'],
          'Authorization: Bearer '.$this->oAuthToken,
          'User-Agent: Pika',
          'X-Forwarded-For: '.$_SERVER['SERVER_ADDR']
        ];
        // merge headers
        if ($extraHeaders) {
            $headers = array_merge($headers, $extraHeaders);
        }
        // setup default curl opts
        $opts = [
          CURLOPT_RETURNTRANSFER => TRUE,
          CURLOPT_HEADER         => FALSE,
          CURLOPT_HTTPHEADER     => $headers,
        ];
        // instantiate the Curl object and set the base url
        try {
            $c = new Curl($this->configArray['Catalog']['sierraApiURL']);
        } catch (ErrorException $e) {
            // TODO: log exception
            return false;
        }
        $c->setHeaders($headers);
        $c->setOpts($opts);

        // make the request using the proper method.
        $method = strtolower($method);
        switch($method) {
            case 'get':
                $c->get($operation, $params);
                break;
            case 'post':
                $c->post($operation, $params);
                break;
            case 'put':
                $c->put($operation, $params);
                break;
            case 'delete':
                $c->delete($operation, $params);
                break;
            default:
                $c->get($operation, $params);
        }

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
        // make sure apiLastError is false after error checks
        $this->apiLastError = false;
        $r = $c->response;
        $c->close();
        return $r;
    }

}