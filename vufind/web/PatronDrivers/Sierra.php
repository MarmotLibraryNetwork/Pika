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
 * All keys are stored in memcache using this scheme (xxx = home library code, XXX = barcode):
 * patron_xxx_XXX_uid   -> sierra uid
 * patron_xxx_XXX_obj   -> cached patron object
 * patron_xxx_XXX_info  -> cached patron info (patron record returned from Sierra API)
 * patron_xxx_XXX_fines -> patron fines object
 *
 *
 * @category Pika
 * @package  Patron
 * @author   Chris Froese
 * @author   Pascal Brammeier
 * Date      5/13/2019
 *
 */
# namespace Pika\PatronDriver\Sierra;
# TODO: Decide on a namespace convention for Pika
# TODO: How to use Composer on servers.
require_once('vendor\autoload.php');
use Curl\Curl;

require_once ROOT_DIR . "/PatronDrivers/PatronDriverInterface.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronHoldsOperations.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronCheckOutsOperations.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronFineOperations.php";
require_once ROOT_DIR . "/PatronDrivers/Traits/PatronReadingHistoryOperations.php";


class Sierra extends PatronDriverInterface {

	use PatronHoldsOperations;
	use PatronCheckOutsOperations;
	use PatronFineOperations;
	use PatronReadingHistoryOperations;

	// Adding global variables to class during object construction to avoid repeated calls to global.
	public  $memCache;
	private $configArray;
	// ----------------------
	/* @var $oAuthToken oAuth2Token */
	private $oAuthToken;
	/* @var $apiLastError false|string false if no error or last error message */
	private $apiLastError = false;
	private $accountProfile;

	public function __construct($accountProfile) {
		// Adding standard globals to class to avoid repeated calling of global.
		global $configArray;
		global $memCache;
		$this->configArray    = $configArray;
		$this->memCache       = $memCache;

		$this->accountProfile = $accountProfile;
		// grab an oAuthToken if there isn't a valid hit in memcache
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
	 * This is responsible for authenticating a patron against the Sierra catalog.
	 *
	 * @param   string  $username         The patron username or barcode
	 * @param   string  $password         The patron barcode or pin
	 * @param   boolean $validatedViaSSO  FALSE
	 *
	 * @return  User|null           A string of the user's ID number
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	public function patronLogin($username, $password, $validatedViaSSO = FALSE){
		// get the login configuration barcode_pin or name_barcode
		$loginMethod = $this->accountProfile->loginConfiguration;
		// check patron credentials depending on login config.
		if ($loginMethod == "barcode_pin"){
			$barcode = $username;
			$cacheKey = 'patron_'.$username;
			$patronUid = $this->_authBarcodePin($username, $password);
		} elseif ($loginMethod == "name_barcode") {
			$barcode = $password;
			$cacheKey = 'patron_'.$password;
			$patronUid = $this->_authNameBarcode($username, $password);
		} else {
			// TODO: throw error
			trigger_error("Invalid login method method.", E_USER_ERROR);
		}

		if (!$patronUid) {
			// need a better return
			return null;
		}
		// get extra patron info
		// I am here -- call getPatronInfo. Add info to user object.
		// instantiate user object
		$patron = new User();
		$patron->source = $this->accountProfile->name;
		if ($loginMethod == "barcode_pin") {
			$patron->cat_username = $barcode;
		} else {
			$patron->cat_password = $barcode;
		}
		if (!$patron->find(true)) {
			// create the user in pika.
		}


	}


	public function getPatronInfo($uid) {
		// GET patrons/{uid}
		// names, addresses (a = main, p = alternate, h = alternate), phones (t = main,o = mobile), expirationDate, homeLibraryCode, moneyOwed, patronType
		// homeLibraryCode
		// todo: check memcache
		// grab everything from the patron record the api can provide.
		$params = [
			'fields' => 'names,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,barcodes,patronType,patronCodes,createdDate,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate'
		];
		$operation = 'patrons/'.$uid;
		$info = $this->_doRequest($operation, $params);
		if(!$info) {
			return false;
		}
// I am here --- return patron info from sierra


	}
	public function updatePatronInfo($patron, $canUpdateContactInfo){
		// TODO: Implement updatePatronInfo() method.
	}

	public function getMyFines($patron){
		// GET patrons/{uid}/fines
		// TODO: Implement getMyFines() method.
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
	 * names field.
	 *
	 * @param  $username  Patron login name
	 * @param  $barcode   Patron barcode
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
		$clientKey    = $this->configArray['Catalog']['apiClientKey'];
		$clientSecret = $this->configArray['Catalog']['apiClientSecret'];
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
		// set memCache
		$this->memCache->set("sierra_oauth_token", $token, $expires);
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
		// setup headers
		// These headers are common to all Sierra API except token requests.
		$headers = [
			'Host: '.$this->configArray['Catalog']['sierraApiHost'],
			'Authorization: Bearer '.$this->token,
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