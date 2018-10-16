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

//			//Don't issue a post, just call the same page (with redirects as needed)
//			$post_string = http_build_query($postData);
//			curl_setopt($this->curl_connection, CURLOPT_POSTFIELDS, $post_string);
//
//			$loginResponse = curl_exec($this->curl_connection);
			$loginResponse = $this->_curlPostPage($curlUrl, $postData);
		}

		if ($loginResponse) {
			$loginResult = false;

			// Check for Login Error Responses
			$numMatches = preg_match('/<span.\s?class="errormessage">(?P<error>.+?)<\/span>/is', $loginResponse, $matches);
			if ($numMatches > 0) {
				$logger->log('Millennium Curl Login Attempt received an Error response : ' . $matches['error'], PEAR_LOG_DEBUG);
//				$loginResult = false;
			} else {
				$numMatches = preg_match('/<div id="msg" class="success">/is', $loginResponse);
				if ($numMatches > 0) {
					$loginResult = true;

					// Pause briefly after logging in as some follow-up millennium operations (done via curl) will fail if done too quickly
//					usleep(150000);
					//TODO: trying with out the login pause
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

	public function updatePin($patron, $oldPin, $newPin, $confirmNewPin){
		$scope = $this->getDefaultScope();

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
			} else if (preg_match('/class="errormessage">(.+?)<\/div>/is', $curlResponse, $matches)){
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
		$fields[]   = array('property'=>'state',           'type'=>'text', 'label'=>'State', 'description'=>'State', 'maxLength' => 32, 'required' => true, 'default'=>'CA');
		$fields[]   = array('property'=>'zip',             'type'=>'text', 'label'=>'Zip Code', 'description'=>'Zip Code', 'maxLength' => 32, 'required' => true);
		$fields[]   = array('property'=>'phone',           'type'=>'text', 'label'=>'Phone (xxx-xxx-xxxx)', 'description'=>'Phone', 'maxLength' => 128, 'required' => false);
		$fields[]   = array('property'=>'email',           'type'=>'email', 'label'=>'E-Mail', 'description'=>'E-Mail', 'maxLength' => 128, 'required' => false);

		$fields[]  = array('property'=>'guardianFirstName', 'type'=>'text', 'label'=>'Parent/Guardian First Name', 'description'=>'Your parent\'s or guardian\'s first name', 'maxLength' => 40, 'required' => false);
		$fields[]  = array('property'=>'guardianLastName',  'type'=>'text', 'label'=>'Parent/Guardian Last Name', 'description'=>'Your parent\'s or guardian\'s last name', 'maxLength' => 40, 'required' => false);
		//These two fields will be made required by javascript in the template

		return $fields;
	}

	function isMiddleNameASeparateFieldInSelfRegistration(){
		return true;
	}
	function combineCityStateZipInSelfRegistration(){
		return false;
	}

	function selfRegister(){
		$address              = trim($_REQUEST['address']);
		$originalAddressInput = $address; // Save for feeding back data input to users (ie undo our special manipulations here)
		$apartmentNumber      = trim($_REQUEST['apartmentNumber']);
		if (!empty($apartmentNumber)) {
			$address .= ' APT ' . $apartmentNumber;
		}

		$guardianFirstName = trim($_REQUEST['guardianFirstName']);
		$guardianLastName  = trim($_REQUEST['guardianLastName']);

		// Reset global variables to be processed by parent method
		if (!empty($guardianFirstName) || !empty($guardianLastName)) {
			// Required for registrants that are under 18 years old
			$_REQUEST['address']       = 'C/O ' . $guardianFirstName . ($guardianFirstName ? ' ' : '' ) . $guardianLastName;
			$_REQUEST['countyAddress'] = $address;
		} else {
			$_REQUEST['address'] = $address;
		}

		//TODO: original form sets a TemplateName value (with a default of web3spl).  Need to verify that this is needed from the pika form

		$selfRegisterResults = parent::selfRegister();

		$_REQUEST['address'] = $originalAddressInput;  // Set the global variable back so the user sees what they inputted
		unset($_REQUEST['countyAddress']);

		if ($selfRegisterResults['success'] && !empty($selfRegisterResults['barcode'])) {
//			$this->requestPinReset($selfRegisterResults['barcode']);
			$patronDump = $this->_getPatronDump();
			$selfRegUser = new User();
			$this->updatePin($selfRegUser, null, 1234, 1234);
		}

		return $selfRegisterResults;
	}


}