<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 5/23/2018
 *
 */

require_once ROOT_DIR . '/Drivers/Sierra.php';

class Sacramento extends Sierra
{

	/**
	 * default login not quite right for Sacramento.
	 *Login url is different; and on login response we look for a success message instead of error messages
	 *(there are no error message, the login form is returned instead)
	 *
	 * @param User $patron
	 * @param bool $linkedAccount  When using linked accounts for Sierra Encore, the curl connection for linked accounts has to be reset
	 * @return bool
	 */
	public function _curl_login($patron, $linkedAccount = false) {
		global $logger;
		$loginResult = false;

		$curlUrl  = $this->getVendorOpacUrl() . '/iii/cas/login?scope=' .$this->getLibraryScope();
		$curlUrl  = str_replace('http://', 'https://', $curlUrl);
		$postData = $this->_getLoginFormValues($patron);

		$logger->log('Loading page ' . $curlUrl, PEAR_LOG_INFO);

		if ($linkedAccount) {
			// For linked users, reset the curl connection so that subsequent logins for the linked users process correctly
			$this->_close_curl();
			$this->curl_connection = false;
		}
		$loginResponse = $this->_curlPostPage($curlUrl, $postData);

		//When a library uses IPSSO, the initial login does a redirect and requires additional parameters.
		if (preg_match('/<input type="hidden" name="lt" value="(.*?)" \/>/si', $loginResponse, $loginMatches)) {
			$lt = $loginMatches[1]; //Get the lt value
			//Login again
			$postData['lt']       = $lt;
			$postData['_eventId'] = 'submit';
			$loginResponse = $this->_curlPostPage($curlUrl, $postData);
		}

		if ($loginResponse) {
			$loginResult = false;

			// Check for Login Error Responses
			$numMatches = preg_match('/<span.\s?class="errormessage">(?P<error>.+?)<\/span>/is', $loginResponse, $matches);
			if ($numMatches > 0) {
				$logger->log('Sacramento Curl Login Attempt received an Error response : ' . $matches['error'], PEAR_LOG_DEBUG);
			} else {
				$numMatches = preg_match('/<div id="msg" class="success">/is', $loginResponse);
				if ($numMatches > 0) {
					$loginResult = true;
				}
			}
		}
		return $loginResult;
	}

	/**
	 * @param $patron
	 * @return array
	 */
	public function _getLoginFormValues($patron){
		$loginData = array();
		$loginData['code']   = $patron->cat_username;
		$loginData['pin']    = $patron->cat_password;
//		$loginData['Log In'] = 'Log In';

		return $loginData;
	}

	public function hasUsernameField(){
		return true;
	}

	/**
	 * @param User    $user          User that the PIN should be changed for
	 * @param string $oldPin         Current PIN
	 * @param string $newPin         New PIN
	 * @param string $confirmNewPin  Second ENtry of PIN for verification of PIN (verification happens in User)
	 * @return string
	 */
	public function updatePin($patron, $oldPin, $newPin, $confirmNewPin){
		$scope = $this->getDefaultScope(); //TODO: Use LibraryScope() instead

		//First we have to login to classic
		$this->_curl_login($patron);

		//Now we can get the page
		$curlUrl = $this->getVendorOpacUrl() . "/patroninfo~S{$scope}/" . $patron->username ."/newpin";

		$post = array(
			'pin'        => $oldPin,
			'pin1'       => $newPin,
			'pin2'       => $confirmNewPin,
			'pat_submit' => 'xxx'
		);
		$curlResponse = $this->_curlPostPage($curlUrl, $post);

		if ($curlResponse) {
			if (stripos($curlResponse, 'Your PIN has been modified.')) {
				$patron->cat_password = $newPin;
				$patron->update();
				return "Your pin number was updated successfully.";
			} else if (preg_match('/class="errormessage">(.+?)<\/span>/is', $curlResponse, $matches)){
				return trim($matches[1]);

			} else {
				return "Sorry, your PIN has not been modified : unknown error. Please try again later.";
			}

		} else {
			return "Sorry, we could not update your pin number. Please try again later.";
		}

	}

	public function getSelfRegistrationFields()
	{
		global $library;
		$fields = array();
		if ($library && $library->promptForBirthDateInSelfReg){
			$fields[] = array('property'=>'birthDate',       'type'=>'date', 'label'=>'Date of Birth (MM-DD-YYYY)', 'description'=>'Date of birth', 'maxLength' => 10, 'required' => true);
		}
		$fields[]   = array('property'=>'firstName',       'type'=>'text', 'label'=>'First Name', 'description'=>'Your first name', 'maxLength' => 40, 'required' => true);
		$fields[]   = array('property'=>'middleName',      'type'=>'text', 'label'=>'Middle Initial', 'description'=>'Your middle initial', 'maxLength' => 40, 'required' => false);
		// gets added to the first name separated by a space
		$fields[]   = array('property'=>'lastName',        'type'=>'text', 'label'=>'Last Name', 'description'=>'Your last name', 'maxLength' => 40, 'required' => true);
		$fields[]   = array('property'=>'address',         'type'=>'text', 'label'=>'Mailing Address', 'description'=>'Mailing Address', 'maxLength' => 128, 'required' => true);
		$fields[]   = array('property'=>'apartmentNumber', 'type'=>'text', 'label'=>'Apartment Number', 'description'=>'Apartment Number', 'maxLength' => 10, 'required' => false);
		$fields[]   = array('property'=>'city',            'type'=>'text', 'label'=>'City', 'description'=>'City', 'maxLength' => 48, 'required' => true);
		$fields[]   = array('property'=>'state',           'type'=>'text', 'label'=>'State', 'description'=>'State', 'maxLength' => 2, 'required' => true, 'default'=>'CA');
		$fields[]   = array('property'=>'zip',             'type'=>'text', 'label'=>'Zip Code', 'description'=>'Zip Code', 'maxLength' => 32, 'required' => true);
		$fields[]   = array('property'=>'phone',           'type'=>'text', 'label'=>'Phone (xxx-xxx-xxxx)', 'description'=>'Phone', 'maxLength' => 128, 'required' => false);
		$fields[]   = array('property'=>'email',           'type'=>'email', 'label'=>'E-Mail', 'description'=>'E-Mail', 'maxLength' => 128, 'required' => false);

		$fields[]   = array('property'=>'guardianFirstName', 'type'=>'text', 'label'=>'Parent/Guardian First Name', 'description'=>'Your parent\'s or guardian\'s first name', 'maxLength' => 40, 'required' => false);
		$fields[]   = array('property'=>'guardianLastName',  'type'=>'text', 'label'=>'Parent/Guardian Last Name', 'description'=>'Your parent\'s or guardian\'s last name', 'maxLength' => 40, 'required' => false);
		//These two fields will be made required by javascript in the template

		$fields[]   = array('property'=>'pin',         'type'=>'pin',   'label'=>'Pin', 'description'=>'Your desired pin', /*'maxLength' => 4, 'size' => 4,*/ 'required' => true);
		$fields[]   = array('property'=>'pin1',        'type'=>'pin',   'label'=>'Confirm Pin', 'description'=>'Re-type your desired pin', /*'maxLength' => 4, 'size' => 4,*/ 'required' => true);

		return $fields;
	}

	function isMiddleNameASeparateFieldInSelfRegistration(){
		return true;
	}

	function selfRegister(){
		global $library;
		// sacramento test and production, woodlands test
		if ($library->subdomain == 'catalog' || $library->subdomain == 'spl' || $library->subdomain == 'woodland' || $library->subdomain == 'cityofwoodland'){
			//Capitalize All Input, expect pin passwords
			foreach ($this->getSelfRegistrationFields() as $formField){
				$formFieldName = $formField['property'];
				if ($formField != 'pin' && $formField != 'pin1'){
					$_REQUEST[$formFieldName] = strtoupper($_REQUEST[$formFieldName]);
				}
			}
		}
		$address              = trim($_REQUEST['address']);
		$originalAddressInput = $address; // Save for feeding back data input to users (ie undo our special manipulations here)
		$apartmentNumber      = trim($_REQUEST['apartmentNumber']);
		if (!empty($apartmentNumber)) {
			$address .= ' APT ' . $apartmentNumber;
		}

		if(isset($_REQUEST['phone'])) {
			$phone_number = $_REQUEST['phone'];
			// strip everything except digits
			$phone_number = preg_replace("/[^\d]/", "", $phone_number);
			$length       = strlen($phone_number);
			// if number is 11 try and trim leading 1
			if ($length == 11) {
				rtrim($phone_number, '1');
				// get the length again
				$length = strlen($phone_number);
			}
			if ($length == 10) {
				$phone_number = preg_replace("/^1?(\d{3})(\d{3})(\d{4})$/", "$1-$2-$3", $phone_number);
			}
			$_REQUEST['phone'] = $phone_number;
		}
		$guardianFirstName = trim($_REQUEST['guardianFirstName']);
		$guardianLastName  = trim($_REQUEST['guardianLastName']);

		// Reset global variables to be processed by parent method
		if (!empty($guardianFirstName) || !empty($guardianLastName)) {
			// Required for registrants that are under 18 years old
			$_REQUEST['address']       = 'C/O ' . $guardianFirstName . ($guardianFirstName ? ' ' : '' ) . $guardianLastName;
			$_REQUEST['countyAddress'] = $address;
		} else {
//			// Check age if no guardian name
//			$birthDate = trim($_REQUEST['birthDate']);
//			$date      = DateTime::createFromFormat('m-d-Y',$birthDate);
//			if ($date == false){
//				return array(
//					'success' => false,
//					'message' => 'Could not process birthdate. Please re-enter.'
//				);
//
//			}
//			$age       = $date->diff(new DateTime())->y;
//			if ($age < 18){
//				return array(
//					'success' => false,
//					'message' => "Those under 18 must include a parent or guardian's name. Please fill in this field."
//				);
//			}

			$_REQUEST['address'] = $address;
		}

		$selfRegisterResults = parent::selfRegister();

		$_REQUEST['address'] = $originalAddressInput;  // Set the global variable back so the user sees what they inputted
		unset($_REQUEST['countyAddress']);

		if ($selfRegisterResults['success'] && !empty($selfRegisterResults['barcode'])) {
			$pin = trim($_REQUEST['pin']);
			if (!empty($pin) && $pin == trim($_REQUEST['pin1'])) {
				$pinSetSuccess = $this->setSelfRegisteredUserPIN($selfRegisterResults['barcode'], $pin);
				global $interface;
				if ($pinSetSuccess) {
					$interface->assign('pinSetSuccess', 'Your PIN has been set');
				} else {
					$interface->assign('pinSetFail', 'Your PIN was not set');
				}
			}
		}

		return $selfRegisterResults;
	}

	function setSelfRegisteredUserPIN($barcode, $pin) {
		$baseUrl = $this->getVendorOpacUrl() . '/iii/cas/login';
		$baseUrl = str_replace('http://', 'https://', $baseUrl);
		$curlUrl = $baseUrl . '?scope=' .$this->getLibraryScope();

		$postData['code'] = $barcode;

		$loginResponse = $this->_curlPostPage($curlUrl, $postData);

		if (preg_match('/<input type="hidden" name="lt" value="(.*?)" \/>/si', $loginResponse, $loginMatches)) {
			$lt = $loginMatches[1]; //Get the lt value
		}
		$setPinURL = $baseUrl . '?service=' . str_replace('cas/login', 'encore/j_acegi_cas_security_check', $baseUrl);
		$postData  = array(
			'code'     => $barcode,
			'pin'      => null,
			'pin1'     => $pin,
			'pin2'     => $pin,
			'lt'       => $lt,
			'_eventId' => 'submit'
		);
		$setPinResponse = $this->_curlPostPage($setPinURL, $postData);
//		if (preg_match('/class="errormessage">(.+?)<\/span>/is', $setPinResponse, $matches)){
//			return trim($matches[1]);
//		}
		$patronDump = $this->_getPatronDump($barcode, true);
		if (empty($patronDump['PIN'])) {
			global $logger;
			$logger->log('Failed to set initial PIN for Self Registered user with barcode ' . $barcode, PEAR_LOG_ERR);
			return false;
		} else {
			return true;
		}
	}
}