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

require_once ROOT_DIR . '/AJAXHandler.php';

class OverDrive_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'CheckoutOverDriveItem',
		'PlaceOverDriveHold',
		'CancelOverDriveHold',
		'GetOverDriveHoldPrompts',
		'ReturnOverDriveItem',
		'SelectOverDriveDownloadFormat',
		'GetDownloadLink',
		'GetOverDriveCheckoutPrompts',
		'forceUpdateFromAPI',
		'getSupportForm',
		'submitSupportForm',
	);

	function forceUpdateFromAPI(){
		require_once ROOT_DIR . '/sys/OverDrive/OverDriveAPIProduct.php';
		$id                            = $_REQUEST['id'];
		$overDriveProduct              = new OverDriveAPIProduct();
		$overDriveProduct->overdriveId = $id;
		if ($overDriveProduct->find(true)){
			if ($overDriveProduct->needsUpdate == true){
				return array('success' => true, 'message' => 'This title was already marked to be updated from the API again the next time the extract is run.');
			}
			$overDriveProduct->needsUpdate = true;
			$numRows                       = $overDriveProduct->update();
			if ($numRows == 1){
				return array('success' => true, 'message' => 'This title will be updated from the API again the next time the extract is run.');
			}else{
				return array('success' => false, 'message' => 'Unable to mark the title for needing update. Could not update the title.');
			}
		}else{

			return array('success' => false, 'message' => 'Unable to mark the title for needing update. Could not find the title.');
		}
	}

	function PlaceOverDriveHold(){
		$user = UserAccount::getLoggedInUser();

		$overDriveId = $_REQUEST['overDriveId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				if (isset($_REQUEST['overdriveEmail'])){
					if ($_REQUEST['overdriveEmail'] != $patron->overdriveEmail){
						$patron->overdriveEmail = $_REQUEST['overdriveEmail'];
						$patron->update();
					}
				}
				if (isset($_REQUEST['promptForOverdriveEmail'])){
					$patron->promptForOverdriveEmail = $_REQUEST['promptForOverdriveEmail'];
					$patron->update();
				}

				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver      = OverDriveDriverFactory::getDriver();
				$holdMessage = $driver->placeOverDriveHold($overDriveId, $patron);
				if ($holdMessage['success']){
					$holdMessage['buttons'] = '<a class="btn btn-primary" href="/MyAccount/Holds" role="button">View My Holds</a>';
				}
				return $holdMessage;
			}else{
				return array('result' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to place holds for that user.');
			}
		}else{
			return array('result' => false, 'message' => 'You must be logged in to place a hold.');
		}
	}

	function CheckoutOverDriveItem(){
		$user        = UserAccount::getLoggedInUser();
		$overDriveId = $_REQUEST['overDriveId'];
		//global $logger;
		//$logger->log("Lending period = $lendingPeriod", PEAR_LOG_INFO);
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->checkoutOverDriveItem($overDriveId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				if ($result['success']){
					$result['buttons'] = '<a class="btn btn-primary" href="/MyAccount/CheckedOut" role="button">View My Check Outs</a>';
				}
				return $result;
			}else{
				return array('result' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to checkout titles for that user.');
			}
		}else{
			return array('result' => false, 'message' => 'You must be logged in to checkout an item.');
		}
	}

	function ReturnOverDriveItem(){
		$user          = UserAccount::getLoggedInUser();
		$overDriveId   = $_REQUEST['overDriveId'];
		$transactionId = $_REQUEST['transactionId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->returnOverDriveItem($overDriveId, $transactionId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return $result;
			}else{
				return array('result' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to return titles for that user.');
			}
		}else{
			return array('result' => false, 'message' => 'You must be logged in to return an item.');
		}
	}

	function SelectOverDriveDownloadFormat(){
		$user        = UserAccount::getLoggedInUser();
		$overDriveId = $_REQUEST['overDriveId'];
		$formatId    = $_REQUEST['formatId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->selectOverDriveDownloadFormat($overDriveId, $formatId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return $result;
			}else{
				return array('result' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to download titles for that user.');
			}
		}else{
			return array('result' => false, 'message' => 'You must be logged in to download a title.');
		}
	}

	function GetDownloadLink(){
		$user        = UserAccount::getLoggedInUser();
		$overDriveId = $_REQUEST['overDriveId'];
		$formatId    = $_REQUEST['formatId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->getDownloadLink($overDriveId, $formatId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return $result;
			}else{
				return array('result' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to download titles for that user.');
			}
		}else{
			return array('result' => false, 'message' => 'You must be logged in to download a title.');
		}
	}

	function GetOverDriveHoldPrompts(){
		$user = UserAccount::getLoggedInUser();
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('overDriveId', $id);
		if ($user->overdriveEmail == 'undefined'){
			$user->overdriveEmail = '';
		}
		$promptForEmail = false;
		if (strlen($user->overdriveEmail) == 0 || $user->promptForOverdriveEmail == 1){
			$promptForEmail = true;
		}

		$overDriveUsers = $user->getRelatedOverDriveUsers();
		$interface->assign('overDriveUsers', $overDriveUsers);
		if (count($overDriveUsers) == 1){
			$interface->assign('patronId', reset($overDriveUsers)->id);
		}

		$interface->assign('overdriveEmail', $user->overdriveEmail);
		$interface->assign('promptForEmail', $promptForEmail);
		if ($promptForEmail || count($overDriveUsers) > 1){
			$promptTitle = 'OverDrive Hold Options';
			return array(
				'promptNeeded' => true,
				'promptTitle'  => $promptTitle,
				'prompts'      => $interface->fetch('OverDrive/ajax-overdrive-hold-prompt.tpl'),
				'buttons'      => '<input class="btn btn-primary" type="submit" name="submit" value="Place Hold" onclick="return VuFind.OverDrive.processOverDriveHoldPrompts();"/>',
			);
		}else{
			return array(
				'patronId'                => reset($overDriveUsers)->id,
				'promptNeeded'            => false,
				'overdriveEmail'          => $user->overdriveEmail,
				'promptForOverdriveEmail' => $promptForEmail,
			);
		}
	}

	function GetOverDriveCheckoutPrompts(){
		$user = UserAccount::getLoggedInUser();
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('overDriveId', $id);

		$overDriveUsers = $user->getRelatedOverDriveUsers();
		$interface->assign('overDriveUsers', $overDriveUsers);

		if (count($overDriveUsers) > 1){
			$promptTitle = 'OverDrive Checkout Options';
			return array(
				'promptNeeded' => true,
				'promptTitle'  => $promptTitle,
				'prompts'      => $interface->fetch('OverDrive/ajax-overdrive-checkout-prompt.tpl'),
				'buttons'      => '<input class="btn btn-primary" type="submit" name="submit" value="Checkout Title" onclick="return VuFind.OverDrive.processOverDriveCheckoutPrompts();">',
			);
		}elseif (count($overDriveUsers) == 1){
			return array(
				'patronId'     => reset($overDriveUsers)->id,
				'promptNeeded' => false,
			);
		}else{
			// No Overdrive Account Found, give the user an error message
			global $logger;
			$logger->log('No valid Overdrive account was found to check out an Overdrive title.', PEAR_LOG_ERR);
			return array(
				'promptNeeded' => true,
				'promptTitle'  => 'Error',
				'prompts'      => 'No valid Overdrive account was found to check this title out with.',
				'buttons'      => '',
			);
		}

	}

	function CancelOverDriveHold(){
		$user        = UserAccount::getLoggedInUser();
		$overDriveId = $_REQUEST['overDriveId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron   = $user->getUserReferredTo($patronId);
			if ($patron){
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->cancelOverDriveHold($overDriveId, $patron);
				return $result;
			}else{
				return array('result' => false, 'message' => 'Sorry, it looks like you don\'t have permissions to download cancel holds for that user.');
			}
		}else{
			return array('result' => false, 'message' => 'You must be logged in to cancel holds.');
		}
	}

	function getSupportForm(){
		global $interface;
		$user = UserAccount::getActiveUserObj();

		// Presets for the form to be filled out with
		$interface->assign('lightbox', true);
		if ($user){
			$name = $user->firstname . ' ' . $user->lastname;
			$interface->assign('name', $name);
			$interface->assign('email', $user->email);
		}

		$results = array(
			'title'        => 'eContent Support Request',
			'modalBody'    => $interface->fetch('OverDrive\eContentSupport.tpl'),
			'modalButtons' => '<span class="tool btn btn-primary" onclick="return $(\'#eContentSupport\').submit()">Submit</span>', // .submit() triggers form validation
		);
		return $results;
	}

	function submitSupportForm(){
		global $interface;
		global $configArray;

		if (isset($_REQUEST['submit'])){
			//E-mail the library with details of the support request
			require_once ROOT_DIR . '/sys/Mailer.php';
			$mail        = new VuFindMailer();
			$userLibrary = UserAccount::getUserHomeLibrary();
			if (!empty($userLibrary->eContentSupportAddress)){
				$to = $userLibrary->eContentSupportAddress;
			}elseif (!empty($configArray['Site']['email'])){
				$to = $configArray['Site']['email'];
			}else{
				return array(
					'title'   => "Support Request Not Sent",
					'message' => "<p>We're sorry, but your request could not be submitted because we do not have a support email address on file.</p><p>Please contact your local library.</p>"
				);
			}
			$multipleEmailAddresses = preg_split('/[;,]/', $to, null, PREG_SPLIT_NO_EMPTY);
			if (!empty($multipleEmailAddresses)){
				$sendingAddress = $multipleEmailAddresses[0];
			}else{
				$sendingAddress = $to;
			}

			$name        = $_REQUEST['name'];
			$subject     = 'eContent Support Request from ' . $name;
			$patronEmail = $_REQUEST['email'];
			$interface->assign('bookAuthor', $_REQUEST['bookAuthor']);
			$interface->assign('device', $_REQUEST['device']);
			$interface->assign('format', $_REQUEST['format']);
			$interface->assign('operatingSystem', $_REQUEST['operatingSystem']);
			$interface->assign('problem', $_REQUEST['problem']);
			$interface->assign('name', $name);
			$interface->assign('email', $patronEmail);
			$interface->assign('deviceName', get_device_name()); // footer & eContent support email

			$body        = $interface->fetch('Help/eContentSupportEmail.tpl');
			$emailResult = $mail->send($to, $sendingAddress, $subject, $body, $patronEmail);
			if (PEAR::isError($emailResult)){
				return array(
					'title'   => "Support Request Not Sent",
					'message' => "<p>We're sorry, an error occurred while submitting your request.</p>" . $emailResult->getMessage()
				);
			}elseif ($emailResult){
				return array(
					'title'   => "Support Request Sent",
					'message' => "<p>Your request was sent to our support team.  We will respond to your request as quickly as possible.</p><p>Thank you for using the catalog.</p>"
				);
			}else{
				return array(
					'title'   => "Support Request Not Sent",
					'message' => "<p>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"
				);
			}
		}else{
			return  $this->getSupportForm();
		}
	}


}
