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
	];
	protected $methodsThatRespondThemselves = [];

	function submitAccessibilityReport(){


		global $interface;
		global $configArray;

		if (isset($_REQUEST['submit'])){
			require_once ROOT_DIR . '/sys/Mailer.php';
			$mail        = new VuFindMailer();
			$userLibrary = UserAccount::getUserHomeLibrary();
			$currentLibrary = Library::getActiveLibrary();
			if (!empty($userLibrary->accessibilityEmail)){
				$to = $userLibrary->accessibilityEmail;
			}elseif(!empty($currentLibrary) && $currentLibrary->accessibilityEmail != ''){
				$to = $currentLibrary->accessibilityEmail;
			}elseif (!empty($configArray['Site']['email'])){
				$to = $configArray['Site']['email'];
			}else{
				return [
					'title'   => 'Support Request Not Sent',
					'message' => "<p>We're sorry, but your request could not be submitted because we do not have a support email address on file.</p><p>Please contact your local library.</p>"
				];
			}

			$multipleEmailAddresses = preg_split('/[;,]/', $to, null, PREG_SPLIT_NO_EMPTY);
			if (!empty($multipleEmailAddresses)){
				$sendingAddress = $multipleEmailAddresses[0];
				$to             = str_replace(';', ',', $to); //The newer mailer needs 'to' addresses to be separated by commas rather than semicolon
			}else{
				$sendingAddress = $to;
			}

			$name        = $_REQUEST['name'];
			$subject     = 'Accessibility Issue Report from ' . $name;
			$patronEmail = $_REQUEST['email'];
			$cardNumber = !empty($_REQUEST['libraryCardNumber']) ? $_REQUEST['libraryCardNumber'] : 'Not Entered';
			$browser = !empty($_REQUEST['browser']) ? $_REQUEST['browser'] : 'Not Entered';
			$ccAddress = "pika@marmot.org";
			$interface->assign('report', $_REQUEST['report']);
			$interface->assign('name', $name);
			$interface->assign('email', $patronEmail);
			$interface->assign('browser', $browser);
			$interface->assign('cardNumber', $cardNumber);
			$interface->assign('subject', $subject);

			$body = $interface->fetch('Help/accessibilityReportEmail.tpl');
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
				return ['title' => "Accessibility Report Not Sent", 'message' => "<p>The CAPTCHA response was incorrect.</p> <p>Please try again.</p>"];
			}else{
				$emailResult = $mail->send($to, $sendingAddress, $subject, $body, $patronEmail, $ccAddress);
				if (PEAR::isError($emailResult)){
					global $pikaLogger;
					$pikaLogger->error('Accessibility Report email not sent: ' . $emailResult->getMessage());
					return [
						'title'   => "Accessibility Report Not Sent",
						'message' => "<p>We're sorry, an error occurred while submitting your report.</p>" . $emailResult->getMessage()
					];
				}elseif ($emailResult){
					return [
						'title'   => "Accessibility Report Sent",
						'message' => "<p>Your report was sent to our team.</p><p>Thank you for using the catalog.</p>"
					];
				}else{
					return [
						'title'   => "Support Request Not Sent",
						'message' => "<p>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"
					];
				}
			}
		}else{
			return ['title'=> "Error", 'message'=>"<p>We're sorry, but your request could not be submitted to our support team at this time.</p><p>Please try again later.</p>"];
		}

	}

}