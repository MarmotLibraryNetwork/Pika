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

require_once ROOT_DIR . '/AJAXHandler.php';
require_once ROOT_DIR . '/sys/Pika/Functions.php';
use function Pika\Functions\{recaptchaGetQuestion, recaptchaCheckAnswer};

class Help_AJAX extends AJAXHandler {
	protected $methodsThatRespondWithJSONUnstructured = [
		"submitAccessibilityReport",
		"submitOverDriveForm",
	];
	protected $methodsThatRespondThemselves = [];

	function submitOverDriveForm() {
		global $interface;
		global $configArray;

		if (isset($_REQUEST['submit'])){
			if (isset($configArray['ReCaptcha']['privateKey'])) {
				try{
					$recaptchaValid = recaptchaCheckAnswer();
				}catch (Exception $ex){
					$recaptchaValid = false;
				}
			}else{
				$recaptchaValid = true;
			}
			if (!$recaptchaValid) {
				return [
					'title' => "OverDrive Support Form Not Sent",
					'message' => "<p class='alert alert-danger'>The CAPTCHA response was incorrect.</p>"
					. "<p>Please try again.</p>",
				];
			}else{
				require_once  ROOT_DIR . '/sys/Mailer.php';
				$mail           = new VuFindMailer();
				$currentLibrary = Library::getActiveLibrary();
				if (!empty($currentLibrary->eContentSupportAddress)) {
					$to = $currentLibrary->eContentSupportAddress;
				}elseif (!empty($configArray['Site']['email'])) {
					$to = $configArray['Site']['email'];
				}else{
					global $pikaLogger;
					$pikaLogger->error("No email for Site. Please check that an OverDrive email is available");
					return [
						'title'   => 'Support Request Not Sent',
						'message' => "<p>We're sorry, but your request could not be submitted because we do not have a support email address on file.</p><p>Please contact your local library.</p>"
					];
				}
				if (!empty($configArray['Site']['email'])){
					$sendingAddress = $configArray['Site']['email'];
				}else {
					$sendingAddress = $_REQUEST['email'];
				}
				$multipleEmailAddresses = preg_split('/[;,]/', $to, null, PREG_SPLIT_NO_EMPTY);
				if (!empty($multipleEmailAddresses)){
					$to             = str_replace(';', ',', $to); //The newer mailer needs 'to' addresses to be separated by commas rather than semicolon
				}
				$user = null;
				$cardNumber  = !empty($_REQUEST['libraryCardNumber']) ? $_REQUEST['libraryCardNumber'] : 'Not provided';
				if(UserAccount::isLoggedIn()){
					$user = UserAccount::getLoggedInUser();
					$cardNumber = $user->barcode;
					$userLibrary = $user->homeLibraryName;
				}else{
					$tempUser = new User();
					$tempUser->barcode = $cardNumber;
					$tempUser->find(true);
					$userLibrary = $tempUser->homeLibraryName;
				}
				$name       = $_REQUEST['name'];
				$subject     = 'OverDrive Site Support Request From: ' . $name;
				$patronEmail = $user != null ? $user->email :$_REQUEST['email'];
				$browser     = !empty($_REQUEST['browser']) ? $_REQUEST['browser'] : 'Not Entered';
				$interface->assign('libraryName', empty($userLibrary) ? $currentLibrary->displayName : $userLibrary);
				$interface->assign('problem', $_REQUEST['problem']);
				$interface->assign('name', $name);
				$interface->assign('title', $_REQUEST['title']);
				$interface->assign('email', $patronEmail);
				$interface->assign('format', $_REQUEST['format']);
				$interface->assign('operatingSystem', $_REQUEST['operatingSystem']);
				$interface->assign('browser', $browser);
				$interface->assign('libraryCardNumber', $cardNumber);
				$interface->assign('subject', $subject);
				$body        = $interface->fetch('Help/overdriveSupportEmail.tpl');
				$emailResult = $mail->send($to, $sendingAddress, $subject, $body, $patronEmail);
				global $pikaLogger;
				if (PEAR::isError($emailResult)){
					$pikaLogger->error('OverDrive support email not sent: ' . $emailResult->getMessage());
					return [
						'title'   => "OverDrive support email not sent:",
						'message' => "<p class='alert alert-danger'>We're sorry, an error occurred while submitting your report.</p>" . $emailResult->getMessage()
					];
				}elseif ($emailResult){
					$pikaLogger->warn('OverDrive support email was sent successfully with the following message: ' . $emailResult);
					return [
						'title'   => "OverDrive support email was sent",
						'message' => "<p class='alert alert-success'>Your report was sent to our team.</p><p>Thank you for using the catalog.</p>"
					];
				}else{
					$pikaLogger->warn('There was an unknown error sending the OverDrive support email');
					return [
						'title'   => "Support Request Not Sent",
						'message' => "<p class='alert alert-danger'>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"
					];
				}
			}
		}else{
			return [
				'title' => "Error",
				'message' => "<p class='alert alert-danger'>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"
			];
		}
	}
	function submitAccessibilityReport(){
		global $interface;
		global $configArray;

		if (isset($_REQUEST['submit'])){
			if (isset($configArray['ReCaptcha']['privateKey'])){
				try {
					$recaptchaValid = recaptchaCheckAnswer();
				} catch (Exception $e){
					$recaptchaValid = false;
				}
			}else{
				$recaptchaValid = true;
			}
			if (!$recaptchaValid){
				return [
					'title'   => "Accessibility Report Not Sent",
					'message' => "<p class='alert alert-danger'>The CAPTCHA response was incorrect.</p> <p>Please try again.</p>"
				];
			}else{
				require_once ROOT_DIR . '/sys/Mailer.php';
				$mail           = new VuFindMailer();
				$userLibrary    = UserAccount::getUserHomeLibrary();
				$currentLibrary = Library::getActiveLibrary();
				if (!empty($userLibrary->accessibilityEmail)){
					$to = $userLibrary->accessibilityEmail;
				}elseif (!empty($currentLibrary->accessibilityEmail)){
					$to = $currentLibrary->accessibilityEmail;
				}elseif (!empty($configArray['Site']['email'])){
					$to = $configArray['Site']['email'];
				}else{
					global $pikaLogger;
					$pikaLogger->error("No email for Accessibility Report set. Please check that at least a site email is available");
					return [
						'title'   => 'Support Request Not Sent',
						'message' => "<p>We're sorry, but your request could not be submitted because we do not have a support email address on file.</p><p>Please contact your local library.</p>"
					];
				}
				if(!empty($configArray['Site']['email'])){
					$sendingAddress = $configArray['Site']['email'];
				}else {
					$sendingAddress = $_REQUEST['email'];
				}
				$multipleEmailAddresses = preg_split('/[;,]/', $to, null, PREG_SPLIT_NO_EMPTY);
				if (!empty($multipleEmailAddresses)){
					$to             = str_replace(';', ',', $to); //The newer mailer needs 'to' addresses to be separated by commas rather than semicolon
				}

				$name        = $_REQUEST['name'];
				$subject     = 'Accessibility Issue Report From ' . $name;
				$patronEmail = $_REQUEST['email'];
				$cardNumber  = !empty($_REQUEST['libraryCardNumber']) ? $_REQUEST['libraryCardNumber'] : 'Not Entered';
				$browser     = !empty($_REQUEST['browser']) ? $_REQUEST['browser'] : 'Not Entered';
				$interface->assign('libraryName', $userLibrary->displayName ?? $currentLibrary->displayName);
				$interface->assign('report', $_REQUEST['report']);
				$interface->assign('name', $name);
				$interface->assign('email', $patronEmail);
				$interface->assign('browser', $browser);
				$interface->assign('cardNumber', $cardNumber);
				$interface->assign('subject', $subject);

				$body        = $interface->fetch('Help/accessibilityReportEmail.tpl');
				$emailResult = $mail->send($to, $sendingAddress, $subject, $body, $patronEmail);
				global $pikaLogger;
				if (PEAR::isError($emailResult)){

					$pikaLogger->error('Accessibility Report email not sent: ' . $emailResult->getMessage());
					return [
						'title'   => "Accessibility Report Not Sent",
						'message' => "<p class='alert alert-danger'>We're sorry, an error occurred while submitting your report.</p>" . $emailResult->getMessage()
					];
				}elseif ($emailResult){
					$pikaLogger->warn('Accessibility Report was sent successfully with following message: ' . $emailResult);
					return [
						'title'   => "Accessibility Report Sent",
						'message' => "<p class='alert alert-success'>Your report was sent to our team.</p><p>Thank you for using the catalog.</p>"
					];
				}else{
					$pikaLogger->warn('There was an unknown error sending the accessibility e-mail');
					return [
						'title'   => "Support Request Not Sent",
						'message' => "<p class='alert alert-danger'>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"
					];
				}
			}
		}else{
			return [
				'title' => "Error",
				'message' => "<p class='alert alert-danger'>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"
			];
		}

	}

}