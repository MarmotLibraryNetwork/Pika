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
	 * @param $patron
	 * @return bool
	 */
	public function _curl_login($patron) {
		global $logger;
		$loginResult = false;

		$curlUrl = $this->getVendorOpacUrl() . '/iii/cas/login?scope=' .$this->getLibraryScope();
		$curlUrl = str_replace('http://', 'https://', $curlUrl);
		$post_data = $this->_getLoginFormValues($patron);

		$logger->log('Loading page ' . $curlUrl, PEAR_LOG_INFO);

		$loginResponse = $this->_curlPostPage($curlUrl, $post_data);

		//When a library uses IPSSO, the initial login does a redirect and requires additional parameters.
		if (preg_match('/<input type="hidden" name="lt" value="(.*?)" \/>/si', $loginResponse, $loginMatches)) {
			$lt = $loginMatches[1]; //Get the lt value
			//Login again
			$post_data['lt']       = $lt;
			$post_data['_eventId'] = 'submit';

			//Don't issue a post, just call the same page (with redirects as needed)
			$post_string = http_build_query($post_data);
			curl_setopt($this->curl_connection, CURLOPT_POSTFIELDS, $post_string);

			$loginResponse = curl_exec($this->curl_connection);
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

	public function updatePin($user, $oldPin, $newPin, $confirmNewPin){
		$scope = $this->getDefaultScope();

		//First we have to login to classic
		$this->_curl_login($user);

		//Now we can get the page
		$curlUrl = $this->getVendorOpacUrl() . "/patroninfo~S{$scope}/" . $user->username ."/newpin";

		$post = array(
			'pin'        => $oldPin,
			'pin1'       => $newPin,
			'pin2'       => $confirmNewPin,
			'pat_submit' => 'xxx'
		);
		$curlResponse = $this->_curlPostPage($curlUrl, $post);

		if ($curlResponse) {
			if (stripos($curlResponse, 'Your PIN has been modified.')) {
				$user->cat_password = $newPin;
				$user->update();
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
}