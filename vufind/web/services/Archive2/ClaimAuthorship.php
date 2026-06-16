<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * @category Pika
 * @author   Marmot Library Networks
 */
require_once ROOT_DIR . '/sys/Pika/Functions.php';
require_once ROOT_DIR . '/sys/Archive2/ClaimAuthorshipRequest.php';
use function Pika\Functions\{recaptchaGetQuestion, recaptchaCheckAnswer};

class Archive2_ClaimAuthorship extends Action{

	function launch(){
		global $configArray;
		global $interface;
		global $pikaLogger;
		$claimAuthorshipFields = Archive2\ClaimAuthorshipRequest::getObjectStructure();

		if (!isset($_REQUEST['id'])){
			$interface->assign('error','No id provided, you must select which object you wish to claim.');
			//TODO: log error also; the raise error generates a decent error message page.
		}

		$nid = ctype_digit($_REQUEST['id']) ? $_REQUEST['id'] : null;

		if(!empty($nid)){

			/** @var \Islandora2Driver $claimedObject */
			$claimedObject = new Islandora2Driver($nid);
			require_once ROOT_DIR . '/sys/Islandora2/Request.php';
			$request        = new Islandora2\Request();
			if($claimedObject->getNodeId() && $requestedArray = $request->fetch("node", $nid)){
				$owningTid = $requestedArray['field_library']['tid'];
				require_once ROOT_DIR . '/sys/Library/Library.php';
				$owningLibrary             = new Library();
				$owningLibrary->libraryTid = $owningTid;
				$owningLibrary->find(true);
				if (empty($owningLibrary->libraryId)){
					$interface->assign('error', "We could not determine which library owns this object, cannot claim authorship.");
				}
				$claimAuthorshipFields['nid']['default'] = $nid;
				$claimAuthorshipFields['nid']['value'] = $nid;
				$claimAuthorshipFields['libraryTid']['default'] = $owningLibrary->libraryTid;
			}else{
				$interface->assign('error', "The requested record could not be processed by the archive system. Please try another item or try again later.");
			}
		}else{
				$interface->assign('error', "An invalid ID was provided. Please use only numeric ids.");
		}
		if (isset($_REQUEST['submit'])) {
			if (isset($configArray['ReCaptcha']['privateKey'])){
				try {
					$recaptchaValid = recaptchaCheckAnswer();
				} catch (Exception $e) {
					$recaptchaValid = false;
				}
			}else{
				$recaptchaValid = true;
			}

			if (!$recaptchaValid) {
				$interface->assign('captchaMessage', 'The CAPTCHA response was incorrect, please try again.');

				// Pre-fill form with user-supplied data
				foreach ($claimAuthorshipFields as &$property) {
					if (isset($_REQUEST[$property['property']])){
						$uservalue           = $_REQUEST[$property['property']];
						$property['default'] = $uservalue;
					}
				}

			} else {
				$claimAuthorshipFields['dateRequested']['value'] = time();
				$claimAuthorshipFields['nid']['default'] = $nid;
				$claimAuthorshipFields['nid']['value']   = $nid;
				/** @var \Archive2\ClaimAuthorshipRequest $newObject */
				$newObject = $this->insertObject($claimAuthorshipFields);
				$interface->assign('requestSubmitted', true);
				if ($newObject !== false){
					$interface->assign('requestResult', $newObject);
					$interface->assign('requestedObject', $claimedObject);
					$body = $interface->fetch('Emails/archive2-claim-authorship.tpl');


					if (!empty($owningLibrary)){
						//Send a copy of the request to the proper administrator
						if (strpos($body, 'http') === false && strpos($body, 'mailto') === false && $body == strip_tags($body)){
							require_once ROOT_DIR . '/sys/Mailer.php';
							$body        .= $claimedObject->getAbsoluteUrl();
							$libraryArchiveEmail = $owningLibrary->archiveRequestEmail ?? $configArray['Site']['email'];
							$mail        = new VuFindMailer();
							$subject     = 'New Authorship Claim for Archive Content';
							$emailResult = $mail->send($libraryArchiveEmail, $newObject->email, $subject, $body);

							if ($emailResult === true){
							} elseif (PEAR_Singleton::isError($emailResult)){
								$interface->assign('error', "Your request could not be sent: {$emailResult->message}.");
								$pikaLogger->error("Archive Claim Authorship Mail Error: {$emailResult->message}", is_array($emailResult->backtrace) ? $emailResult->backtrace : []);
							} else {
								$interface->assign('error', "Your request could not be sent due to an unknown error.");
								$pikaLogger->error("Mail List Failure (unknown reason), parameters: $owningLibrary->archiveRequestEmail, $newObject->email, $subject, $body");
							}
						} else {
							$interface->assign('error', 'Please do not include html or links within your request');
							$newObject->delete();
						}
					} else {
						$interface->assign('error', "Your request could not be sent because the library does not accept authorship claims.");
					}

				}else{
					$interface->assign('error', $_SESSION['lastError']);
				}
			}
		}

		unset($claimAuthorshipFields['dateRequested']);

		$interface->assign('submitUrl', '/Archive2/ClaimAuthorship/' . $nid);
		$interface->assign('structure', $claimAuthorshipFields);
		$interface->assign('saveButtonText', 'Submit Request');
		$interface->assign('claimAuthorshipHeader', $owningLibrary->claimAuthorshipHeader);

		// Set up captcha to limit spam submissions
		if (isset($configArray['ReCaptcha']['publicKey'])) {
			$captchaCode        = recaptchaGetQuestion();
			$interface->assign('captcha', $captchaCode);
		}

		$fieldsForm = $interface->fetch('DataObjectUtil/objectEditForm.tpl');
		$interface->assign('requestForm', $fieldsForm);

		$this->display('claimAuthorship.tpl', 'Archival Material Claim Authorship');
	}

	function insertObject($structure){
		require_once ROOT_DIR . '/sys/DataObjectUtil.php';
		$newObject = new Archive2\ClaimAuthorshipRequest();
		//Check to see if we are getting default values from the
		DataObjectUtil::updateFromUI($newObject, $structure);
		$newObject->nid    = $structure['nid']['value'];
		$validationResults = DataObjectUtil::validateObject($structure, $newObject);
		if ($validationResults['validatedOk']) {
			$ret = $newObject->insert();
			if (!$ret) {
				global $pikaLogger;
				if ($newObject->_lastError) {
					$errorDescription = $newObject->_lastError->getUserInfo();
				} else {
					$errorDescription = 'Unknown error';
				}
				$pikaLogger->error('Could not insert new object ' . $ret . ' ' . $errorDescription);
				$_SESSION['lastError'] = "An error occurred inserting $structure->nid <br>{$errorDescription}";
				return false;
			}
		} else {
			global $pikaLogger;
			$errorDescription = implode(', ', $validationResults['errors']);
			$pikaLogger->error('Could not validate new object Claim Authorship Request ' . $errorDescription);
			$_SESSION['lastError'] = "The information entered was not valid. <br>" . implode('<br>', $validationResults['errors']);
			return false;
		}
		return $newObject;
	}
}
