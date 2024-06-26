<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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

use Pika\Logger;

require_once ROOT_DIR . '/sys/SIP2.php';
require_once 'Authentication.php';

class SIPAuthentication implements Authentication {
  private static $processedUsers = array();
	private $logger;

	public function __construct($additionalInfo = []) {
		$this->logger = new Logger(__CLASS__);

	}
	
	public function validateAccount($username, $password, $parentAccount, $validatedViaSSO) {
		global $configArray;
		global $timer;
		if (isset($username) && isset($password)) {
			//Check to see if we have already processed this user
			if (array_key_exists($username, self::$processedUsers)){
				return self::$processedUsers[$username];
			}
			
			if (trim($username) != '' && trim($password) != '') {
				// Attempt SIP2 Authentication

				$sipClass = empty($configArray['SIP2']['sip2Class']) ? 'sip2' : $configArray['SIP2']['sip2Class'];
				/** @var KohaSIP|sip2 $mysip */
				$mysip           = new $sipClass;
				$mysip->hostname = $configArray['SIP2']['host'];
				$mysip->port     = $configArray['SIP2']['port'];

				if ($mysip->connect()) {
					//send selfcheck status message
					$in         = $mysip->msgSCStatus();
					$msg_result = $mysip->get_message($in);

					// Make sure the response is 98 as expected
					if (preg_match("/^98/", $msg_result)) {
						$result = $mysip->parseACSStatusResponse($msg_result);

						//  Use result to populate SIP2 settings
						$mysip->AO = empty($result['variable']['AO'][0]) ? null : $result['variable']['AO'][0]; /* set AO to value returned */
						$mysip->AN = empty($result['variable']['AN'][0]) ? null : $result['variable']['AN'][0]; /* set AN to value returned */

						$mysip->patron    = $username;
						$mysip->patronpwd = $password;

						$in         = $mysip->msgPatronStatusRequest();
						$msg_result = $mysip->get_message($in);

						// Make sure the response is 24 as expected
						if (preg_match("/^24/", $msg_result)) {
							$result = $mysip->parsePatronStatusResponse( $msg_result );

							if (($result['variable']['BL'][0] == 'Y') and ($result['variable']['CQ'][0] == 'Y')) {
								//Get patron info as well
								$in         = $mysip->msgPatronInformation('fine');
								$msg_result = $mysip->get_message($in);

								// Make sure the response is 24 as expected
								$patronInfoResponse = null;
								if (preg_match("/^64/", $msg_result)) {
									$patronInfoResponse = $mysip->parsePatronInfoResponse( $msg_result );
								}

								// Success!!!
								$user = $this->processSIP2User($result, $username, $password, $patronInfoResponse);


								$user->setPassword($password);
							}
						}
					}
					$mysip->disconnect();
				}else{
					$this->logger->error('Unable to connect to SIP server');
				}
			}
		}
		
		$timer->logTime("Validated Account in SIP2Authentication");
		if (isset($user)){
			self::$processedUsers[$username] = $user;
			return $user;
		}else{
			return null;
		}
		
	}
	public function authenticate($validatedViaSSO = false) {
		global $configArray;
		global $timer;

		if (isset($_POST['username']) && isset($_POST['password'])) {
			$username = $_POST['username'];
			$password = $_POST['password'];
			//Set this up to use library prefix
			$barcodePrefix = $configArray['Catalog']['barcodePrefix'];
			if (strlen($barcodePrefix) > 0){
				if (strlen($username) == 9){
					$username = substr($barcodePrefix, 0, 5) . $username;
				}elseif (strlen($username) == 8){
					$username = substr($barcodePrefix, 0, 6) . $username;
				}elseif (strlen($username) == 7){
					$username = $barcodePrefix . $username;
				}
			}

		  //Check to see if we have already processed this user
      if (array_key_exists($username, self::$processedUsers)){
        return self::$processedUsers[$username];
      }
      
			if ($username != '' && $password != '') {
				// Attempt SIP2 Authentication

				$sipClass = empty($configArray['SIP2']['sip2Class']) ? 'sip2' : $configArray['SIP2']['sip2Class'];
				/** @var KohaSIP|sip2 $mysip */
				$mysip           = new $sipClass;
				$mysip->hostname = $configArray['SIP2']['host'];
				$mysip->port     = $configArray['SIP2']['port'];

				if ($mysip->connect()) {
					//send selfcheck status message
					$in         = $mysip->msgSCStatus();
					$msg_result = $mysip->get_message($in);

					// Make sure the response is 98 as expected
					if (preg_match("/^98/", $msg_result)) {
						$result = $mysip->parseACSStatusResponse($msg_result);

						//  Use result to populate SIP2 setings
						$mysip->AO = $result['variable']['AO'][0]; /* set AO to value returned */
						if (isset($result['variable']['AN'])){
							$mysip->AN = $result['variable']['AN'][0]; /* set AN to value returned */
						}

						$mysip->patron    = $username;
						$mysip->patronpwd = $password;

						$in         = $mysip->msgPatronStatusRequest();
						$msg_result = $mysip->get_message($in);

						// Make sure the response is 24 as expected
						if (preg_match("/^24/", $msg_result)) {
							$result = $mysip->parsePatronStatusResponse( $msg_result );

							if (($result['variable']['BL'][0] == 'Y') and ($result['variable']['CQ'][0] == 'Y')) {
								//Get patron info as well
								$in         = $mysip->msgPatronInformation('none');
								$msg_result = $mysip->get_message($in);

								// Make sure the response is 24 as expected
								if (preg_match("/^64/", $msg_result)) {
									$patronInfoResponse = $mysip->parsePatronInfoResponse( $msg_result );
									//print_r($patronInfoResponse);
								}
								
								// Success!!!
								$user = $this->processSIP2User($result, $username, $password, $patronInfoResponse);
							} else {
								$user = new PEAR_Error('authentication_error_invalid');
							}
						} else {
							$user = new PEAR_Error('authentication_error_technical');
						}
					} else {
						$user = new PEAR_Error('authentication_error_technical');
					}
					$mysip->disconnect();

				} else {
					$user = new PEAR_Error('authentication_error_technical');
					$this->logger->error('Unable to connect to SIP server');
				}
			} else {
				$user = new PEAR_Error('authentication_error_blank');
			}
			$timer->logTime("Authenticated user in SIP2Authentication");
			self::$processedUsers[$username] = $user;
		} else {
			$user = new PEAR_Error('authentication_error_blank');
		}

		
		return $user;
	}

	/**
	 * Process SIP2 User Account
	 *
	 * @param   array    $info                An array of user information
	 * @param   string   $username            The user's ILS username
	 * @param   string   $password            The user's ILS password
	 * @param   array    $patronInfoResponse  The user's ILS password
	 * @return  User
	 * @access  public
	 * @author  Bob Wicksall <bwicksall@pls-net.org>
	 */
	private function processSIP2User($info, $username, $password, $patronInfoResponse){
		global $timer;
		$user            = new User();
		$user->ilsUserId = $info['variable']['AA'][0];
		$insert          = !$user->find(true);
		
		// This could potentially be different depending on the ILS.  Name could be Bob Wicksall or Wicksall, Bob.
		// This is currently assuming Wicksall, Bob
		if (strpos($info['variable']['AE'][0], ',') !== false){
			$user->firstname = trim(substr($info['variable']['AE'][0], 1 + strripos($info['variable']['AE'][0], ',')));
			$user->lastname  = trim(substr($info['variable']['AE'][0], 0, strripos($info['variable']['AE'][0], ',')));
		}else{
			$user->lastname  = trim(substr($info['variable']['AE'][0], 1 + strripos($info['variable']['AE'][0], ' ')));
			$user->firstname = trim(substr($info['variable']['AE'][0], 0, strripos($info['variable']['AE'][0], ' ')));
		}

		// I'm inserting the sip username and password since the ILS is the source.
		// Should revisit this.
		$user->setPassword($password);
		$user->cat_username = $username;
		$user->email        = $patronInfoResponse['variable']['BE'][0] ?? '';
		$user->phone        = $patronInfoResponse['variable']['BF'][0] ?? '';
		$user->patronType   = empty($patronInfoResponse['variable']['PC'][0]) ? '' : $patronInfoResponse['variable']['PC'][0];
		
		//Get home location
		//Check AO?
		if ((!isset($user->homeLocationId) || $user->homeLocationId == 0) && (isset($patronInfoResponse['variable']['AQ']) || isset($patronInfoResponse['variable']['AO']))){
			$location = new Location();
			if (isset($patronInfoResponse['variable']['AQ'])){
				$location->code = $patronInfoResponse['variable']['AQ'][0];
			}else{
				$location->code = $patronInfoResponse['variable']['AO'][0];
			}

			if (!empty($location->code)) {
				$location->find();
				if ($location->find(true)) {
					$user->homeLocationId = $location->locationId;
				}
				if (empty($user->homeLocationId)) {
					// Logging for Diagnosing PK-1846
					$this->logger->warning('Sip Authentication: Attempted look up user\'s homeLocationId and failed to find one. User : ' . $user->id);
				}
			}
		}

		if ($insert) {
			$user->created = date('Y-m-d');
			$user->insert();
		} else {
			$user->update();
		}

		$timer->logTime('Processed SIP2 User');
		return $user;
	}
}
