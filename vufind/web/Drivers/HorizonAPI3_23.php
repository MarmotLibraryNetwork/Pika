<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 8/16/2016
 *
 */
require_once ROOT_DIR . '/Drivers/HorizonAPI.php';
abstract class HorizonAPI3_23 extends HorizonAPI
{
	private function getBaseWebServiceUrl() {
		$webServiceURL     = $this->getWebServiceURL();
		$urlParts          = parse_url($webServiceURL);
		$baseWebServiceUrl = $urlParts['scheme']. '://'. $urlParts['host']. (!empty($urlParts['port']) ? ':'. $urlParts['port'] : '');

		return $baseWebServiceUrl;
	}

	/**
	 * @param User   $patron         The user to update PIN for
	 * @param string $oldPin         The current PIN
	 * @param string $newPin         The PIN to update to
	 * @param string $confirmNewPin  A second entry to confirm the new PIN number (checked in User now)
	 * @return string
	 */
	function updatePin($patron, $oldPin, $newPin, $confirmNewPin){
		//Log the user in
		list($userValid, $sessionToken) = $this->loginViaWebService($patron);
		if (!$userValid){
			return 'Sorry, it does not look like you are logged in currently.  Please login and try again';
		}

		$updatePinUrl = $this->getBaseWebServiceUrl() . '/hzws/user/patron/changeMyPin';
		$jsonParameters = array(
			'currentPin' => $oldPin,
			'newPin'     => $newPin,
		);
		$updatePinResponse = $this->getWebServiceResponseUpdated($updatePinUrl, $jsonParameters, $sessionToken);
		if (isset($updatePinResponse['messageList'])) {
			$errors = '';
			foreach ($updatePinResponse['messageList'] as $errorMessage) {
				$errors .= $errorMessage['message'] . ';';
			}
			global $logger;
			$logger->log('Horizon API 3.23 Driver error updating user\'s Pin :'.$errors, PEAR_LOG_ERR);
			return 'Sorry, we encountered an error while attempting to update your pin. Please contact your local library.';
		} elseif (!empty($updatePinResponse['sessionToken'])){
			// Success response isn't particularly clear, but returning the session Token seems to indicate the pin updated. plb 8-15-2016
			$patron->cat_password = $newPin;
			$patron->update();
			return "Your pin number was updated successfully.";
		}else{
			return "Sorry, we could not update your pin number. Please try again later.";
		}
	}


	/**
	 * @param User        $patron
	 * @param string      $newPin
	 * @param null|string $resetToken
	 * @return array
	 */
	function resetPin($patron, $newPin, $resetToken=null){
		if (empty($resetToken)) {
			global $logger;
			$logger->log('No Reset Token passed to resetPin function', PEAR_LOG_ERR);
			return array(
				'error' => 'Sorry, we could not update your pin. The reset token is missing. Please try again later'
			);
		}

		$changeMyPinAPIUrl = $this->getBaseWebServiceUrl() . '/hzws/user/patron/changeMyPin';
		$jsonParameters = array(
			'resetPinToken' => $resetToken,
			'newPin'        => $newPin,
		);
		$changeMyPinResponse = $this->getWebServiceResponseUpdated($changeMyPinAPIUrl, $jsonParameters);
		if (isset($changeMyPinResponse['messageList'])) {
			$errors = '';
			foreach ($changeMyPinResponse['messageList'] as $errorMessage) {
				$errors .= $errorMessage['message'] . ';';
			}
			global $logger;
			$logger->log('WCPL Driver error updating user\'s Pin :'.$errors, PEAR_LOG_ERR);
			return array(
				'error' => 'Sorry, we encountered an error while attempting to update your pin. Please contact your local library.'
			);
		} elseif (!empty($changeMyPinResponse['sessionToken'])){
			if ($patron->username == $changeMyPinResponse['patronKey']) { // Check that the ILS user matches the Pika user
				$patron->cat_password = $newPin;
				$patron->update();
			}
			return array(
				'success' => true,
			);
//			return "Your pin number was updated successfully.";
		}else{
			return array(
				'error' => "Sorry, we could not update your pin number. Please try again later."
			);
		}
	}



	// Newer Horizon API version
	public function emailResetPin($barcode)
	{
		if (empty($barcode)) {
			$barcode = $_REQUEST['barcode'];
		}

		$patron = new User;
		$patron->get('cat_username', $barcode);
		if (!empty($patron->id)) {
			global $configArray;
			$userID = $patron->id;

			// If possible, check if Horizon has an email address for the patron
			if (!empty($patron->cat_password)) {
				list($userValid, $sessionToken, $ilsUserID) = $this->loginViaWebService($patron);
				if ($userValid) {
					// Yay! We were able to login with the pin Pika has!

					//Now check for an email address
					$lookupMyAccountInfoResponse = $this->getWebServiceResponse( $this->getWebServiceURL() . '/standard/lookupMyAccountInfo?clientID=' . $configArray['Catalog']['clientId'] . '&sessionToken=' . $sessionToken . '&includeAddressInfo=true');
					if ($lookupMyAccountInfoResponse) {
						if (isset($lookupMyAccountInfoResponse->AddressInfo)){
							if (empty($lookupMyAccountInfoResponse->AddressInfo->email)){
								// return an error message because horizon doesn't have an email.
								return array(
									'error' => 'The circulation system does not have an email associated with this card number. Please contact your library to reset your pin.'
								);
							}
						}
					}
				}
			}

			// email the pin to the user
			$resetPinAPIUrl = $this->getBaseWebServiceUrl() . '/hzws/user/patron/resetMyPin';
			$jsonPOST       = array(
				'login'       => $barcode,
				'resetPinUrl' => $configArray['Site']['url'] . '/MyAccount/ResetPin?resetToken=<RESET_PIN_TOKEN>' . (empty($userID) ?  '' : '&uid=' . $userID)
			);

			$resetPinResponse = $this->getWebServiceResponseUpdated($resetPinAPIUrl, $jsonPOST);
			// Reset Pin Response is empty JSON on success.

			if ($resetPinResponse === array() && !isset($resetPinResponse['messageList'])) {
				return array(
					'success' => true,
				);
			} else {
				$result = array(
					'error' => "Sorry, we could not e-mail your pin to you.  Please visit the library to reset your pin."
				);
				if (isset($resetPinResponse['messageList'])) {
					$errors = '';
					foreach ($resetPinResponse['messageList'] as $errorMessage) {
						$errors .= $errorMessage['message'] . ';';
					}
					global $logger;
					$logger->log('WCPL Driver error updating user\'s Pin :' . $errors, PEAR_LOG_ERR);
				}
				return $result;
			}



		} else {
			return array(
				'error' => 'Sorry, we did not find the card number you entered or you have not logged into the catalog previously.  Please contact your library to reset your pin.'
			);
		}
	}

	protected $usingNewerAPICalls = false;
	protected $sessionToken;
	protected function getCustomHeaders() {
		if ($this->usingNewerAPICalls) {
			global $configArray;
			$requestHeaders = array(
				'Accept: application/json',
				'Content-Type: application/json',
				'SD-Originating-App-Id: Pika',
				'x-sirs-clientId: ' . $configArray['Catalog']['clientId'],
			);

			if (!empty($this->sessionToken)) {
				$requestHeaders[] = "x-sirs-sessionToken: $this->sessionToken";
			}
			return $requestHeaders;
		}
		return null;
	}

	/**
	 *  Handles API calls to the newer Horizon APIs.
	 *
	 * @param $url         URL to call
	 * @param array $post  POST variables get encoded as JSON
	 * @return bool|mixed  return false or the response
	 */
	public function getWebServiceResponseUpdated($url, $post = array(), $sessionToken = ''){
		$this->usingNewerAPICalls = true; // Set to use custom headers
		$this->sessionToken       = $sessionToken; // used to build custom headers
		$this->_close_curl();

		$curlResponse = $this->_curlPostBodyData($url, $post, true);

		// Close up connections in case an older call is used later (though probably not)
		$this->usingNewerAPICalls = false;
		$this->_close_curl();

		if ($curlResponse !== false && $curlResponse !== 'false'){
			$response = json_decode($curlResponse, true);
			if (json_last_error() == JSON_ERROR_NONE) {
				return $response;
			} else {
				global $logger;
				$logger->log('Error Parsing JSON response in Horizon 3.23 Driver: ' . json_last_error_msg(), PEAR_LOG_ERR);
				return false;
			}
		}else{
			global $logger;
			$logger->log('Curl problem in getWebServiceResponseUpdated', PEAR_LOG_WARNING);
			return false;
		}
	}

}
