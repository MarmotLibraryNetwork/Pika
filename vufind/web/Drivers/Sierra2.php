<?php
/**
 * Sierra REST Patron API
 *
 * Sierra REST API integration for PIKA Discovery Layer
 *
 * @category Pika
 * @author   Chris Froese
 * Date: 5/10/2019
 *
 */

# TODO: PHP7
# namespace Pika\Drivers\SierraAPI;

# TODO: PHP7
#class Sierra2 implements \DriverInterface {
class Sierra2 extends Sierra {
	/* @var $oAuthToken oAuth2Token */
	private $oAuthToken;
	public  $accountProfile;


	/**
	 * Sierra2 constructor.
	 * @param $accountProfile
	 */
	public function __construct($accountProfile) {
		$this->accountProfile = $accountProfile;
		if(!isset($this->oAuthToken)) {
			if($this->_oAuthToken()) {
				// logging happens in _oAuthToken()
				# TODO: what is the return if error
				return FALSE;
			}
		}
	}

	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 * Interface defined in CatalogConnection.php
	 *
	 * @param   string  $username         The patron username or barcode
	 * @param   string  $password         The patron barcode or pin
	 * @param   boolean $validatedViaSSO  FALSE
	 *
	 * @return  User|null           A string of the user's ID number
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	public function patronLogin($username, $password, $validatedViaSSO = FALSE) {
		// get the login configuration barcode_pin or name_barcode
		$loginMethod = $this->accountProfile->loginConfiguration;
		if ($loginMethod == "barcode_pin"){
			$valid = $this->_authBarcodePin($username, $password);
		} elseif ($loginMethod == "name_barcode") {
			$valid = $this->_authNameBarcode($username, $password);
		}
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
		global $configArray;
		// setup headers
		$headers = [
			'Host: '.$configArray['Catalog']['sierraApiHost'],
			'Authorization: Bearer '.$this->token,
			'User-Agent: Pika',
			'X-Forwarded-For: '.$_SERVER['SERVER_ADDR']
		];
		// tidy up barcode
		$barcode = trim($barcode);
		// setup the url.
		// varFieldTag=b: b is for barcode. :) This will find the barcode if it exists and return an array of names.
		$url = $configArray['Catalog']['sierraApiURL']."/patrons/find?varFieldTag=b&varFieldContent=".$barcode."&fields=names";
		// setup curl opts
		$c_opts = [
			CURLOPT_HTTPGET =>        TRUE,
			CURLOPT_URL=>             $url,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HEADER =>         FALSE,
			CURLOPT_HTTPHEADER =>     $headers,
		];
		// setup curl
		$c = curl_init();
		curl_setopt_array($c, $c_opts);
		// make the request
		######### TODO: refactor curl exec and error checks
		$r = curl_exec($c);
		// check for error
		if ($r === FALSE) {
			$curl_error = curl_error($c);
			// TODO: log curl error
			echo $curl_error . PHP_EOL;
			return FALSE;
		}

		$cInfo = curl_getinfo($c);
		curl_close($c);
		// check the request was successful
		if ($cInfo['http_code'] == 401) {
			# TODO: log 401
			return FALSE;
		} elseif ($cInfo['http_code'] != 200) {
			# TODO: log unknown response
			return FALSE;
		}
		// decode json
		$j = json_decode($r);
		// check the json is valid
		if($j === null) {
			#TODO: Log bad json
			return FALSE;
		}
		######### TODO: end refactor
		// barcode is verified. yeah!
		// check the username agains name(s) returned from sierra
		$patronNames = $j->names;
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
			$result = $j->id;
		} else {
			$result = FALSE;
		}
		return $result;
	}

private function _authBarcodePin($barcode, $pin) {
// need something to test against
	return false;

}
	/**
	 * _oAuth
	 *
	 * Send oAuth token request
	 *
	 * @return boolean true on success, false otherwise
	 */
	private function _oAuthToken() {
		global $memCache;
		global $configArray;

		// check memcache for valid token and set $this
		if ($token = $memCache->get("sierra_oauth_token")) {
			$this->oAuthToken = $token;
			return TRUE;
		}
		// grab clientKey and clientSecret from configArray
		$clientKey    = $configArray['Catalog']['apiClientKey'];
		$clientSecret = $configArray['Catalog']['apiClientSecret'];
		// setup url
		$tokenUrl = $configArray['Catalog']['sierraApiURL'] . '/token';
		//encode key and secret
		$requestAuth = base64_encode($clientKey . ':' . $clientSecret);
		// setup post headers
		$headers = [
			'Host: sierra.marmot.org',
			'Authorization: Basic ' . $requestAuth,
			'Content-Type: application/x-www-form-urlencoded',
			'grant_type=client_credentials'
		];
		// setup curl
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, $tokenUrl);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($c, CURLOPT_HEADER, 0);

		######### TODO: refactor curl exec and error checks
		// make the request
		$r = curl_exec($c);
		// check for error
		if ($r === FALSE) {
			$curl_error = curl_error($c);
			// TODO: log curl error
			echo $curl_error . PHP_EOL;
			return FALSE;
		}

		$cInfo = curl_getinfo($c);
		curl_close($c);
		// check the request was successful
		if ($cInfo['http_code'] == 401) {
			#   TODO: log 401
			return FALSE;
		} elseif ($cInfo['http_code'] != 200) {
			# TODO: log unknown response
			return FALSE;
		}
		######### TODO: end refactor
		// decode json
		$j = json_decode($r);
		// check the json is valid
		if($j === null) {
			#TODO: Log bad json
			return FALSE;
		}
		// setup memCache vars
		$token   = $j->access_token;
		$expires = $j->expires_in;
		// set memCache
		$memCache->set("sierra_oauth_token", $token, $expires);
		return TRUE;
	}



}