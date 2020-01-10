<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */
require_once ROOT_DIR . '/Drivers/Sierra.php';

/**
 * Pika Connector for Arlington's Innovative catalog (Sierra)
 *
 * This class uses screen scraping techniques to gather record holdings written
 * by Adam Bryn of the Tri-College consortium.
 *
 * @author Adam Brin <abrin@brynmawr.com>
 *
 * Extended by Mark Noble and CJ O'Hara based on specific requirements for
 * Marmot Library Network.
 *
 * @author Mark Noble <mnoble@turningleaftech.com>
 * @author CJ O'Hara <cj@marmot.org>
 */
class Arlington extends Sierra{
	public function _getLoginFormValues($patron){
		$loginData = array();
		$loginData['pin']    = $patron->cat_password;
		$loginData['code']   = $patron->cat_username;
		$loginData['submit'] = 'submit';
		return $loginData;
	}

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
				$logger->log('Arlington Curl Login Attempt received an Error response : ' . $matches['error'], PEAR_LOG_DEBUG);
			} else {
				$numMatches = preg_match('/<div id="msg" class="success">/is', $loginResponse);
				if ($numMatches > 0) {
					$loginResult = true;
				}
			}
		}
		return $loginResult;
	}


	public function getSelfRegistrationFields() {
		header('Location: http://library.arlingtonva.us/services/accounts-and-borrowing/get-a-free-library-card/');
		die;
	}

	public function hasUsernameField(){
		return true;
	}

	/**
	 * @param User    $user          User that the PIN should be changed for
	 * @param string $oldPin         Current PIN
	 * @param string $newPin         New PIN
	 * @param $string confirmNewPin  Second ENtry of PIN for verification of PIN (verification happens in User)
	 * @return string
	 */
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