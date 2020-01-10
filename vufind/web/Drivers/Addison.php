<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 9/19/2018
 *
 */


class Addison extends Sierra
{

	public function _getLoginFormValues($patron){
		$loginData = array();
		$loginData['code'] = $patron->cat_username;
		$loginData['pin']  = $patron->cat_password;
//		$loginData['pat_submit']  = 'xxx';

		return $loginData;
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