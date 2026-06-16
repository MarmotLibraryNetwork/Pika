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
 * Displays and processes the patron-facing form to request a copy of an archival object.
 * Resolves the owning library from the archive node, saves the request via ArchiveRequest,
 * and emails a notification to the library's configured archive request address.
 * Supports reCAPTCHA to prevent spam submissions.
 *
 * @category Pika
 * @author CJ O'Hara <pika@marmot.org>
 */
require_once ROOT_DIR . '/sys/Pika/Functions.php';
require_once ROOT_DIR . '/sys/Archive2/ArchiveRequest.php';

use function Pika\Functions\{recaptchaGetQuestion, recaptchaCheckAnswer};

class Archive2_RequestCopy extends Action {
	function launch(){
		global $configArray;
		global $interface;
		if (!isset($_REQUEST['id'])){
			$interface->assign('error','No id provided, you must select which object you want a copy of');
			//TODO: log error also; the raise error generates a decent error message page.
		}
		$nid = ctype_digit($_REQUEST['id']) ? $_REQUEST['id'] : null;
		//Find the owning library
		require_once ROOT_DIR . '/sys/Islandora2/Request.php';

		if(!empty($nid)){
			/** @var \Islandora2Driver $requestedObject */
			$requestedObject = new Islandora2Driver($nid);
			$request         = new Islandora2\Request();
			if($requestedObject->getNodeId() && $requestedArray = $request->fetch('node', $nid)){
				$owningTid = $requestedArray['field_library']['tid'];
				require_once ROOT_DIR . '/sys/Library/Library.php';
				$owningLibrary             = new Library();
				$owningLibrary->libraryTid = $owningTid;
				$owningLibrary->find(true);
				if (empty($owningLibrary->libraryId)){
					$interface->assign('error', 'Could not determine which library owns this object, cannot request a copy.');
				}
				$archiveRequestFields                          = $owningLibrary->getArchiveRequestFormStructure();
				$archiveRequestFields['nid']['default']        = $nid;
				$archiveRequestFields['nid']['value']          = $nid;
				$archiveRequestFields['libraryTid']['default'] = $owningLibrary->libraryTid;
			}else{
				$interface->assign('error', "The requested record could not be processed by the archive system. Please try another item or try again later.");
			}
		}else{
			$interface->assign('error', "An invalid ID was provided. Please use only numeric ids.");
		}
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
					$interface->assign('captchaMessage', 'The CAPTCHA response was incorrect, please try again.');

					// Pre-fill form with user-supplied data
					foreach ($archiveRequestFields as &$property){
						if (isset($_REQUEST[$property['property']])){
							$userValue           = $_REQUEST[$property['property']];
							$property['default'] = $userValue;
						}
					}

				}else{
					$archiveRequestFields['dateRequested']['value'] = time();
					$archiveRequestFields['nid']['value']           = $nid;
					/** @var Archive2\ArchiveRequest $newObject */
					$newObject = $this->insertObject($archiveRequestFields);
					$interface->assign('requestSubmitted', true);
					if ($newObject !== false && !empty($newObject->nid)){
						$interface->assign('requestResult', $newObject);
						$interface->assign('requestedObject', $requestedObject);
						$body = $interface->fetch('Emails/archive2-request.tpl');

						//Find the owning library

						if (!empty($owningLibrary)){
							//Send a copy of the request to the proper administrator
							if (strpos($body, 'http') === false && strpos($body, 'mailto') === false && $body == strip_tags($body)){
								$body .= $requestedObject->getAbsoluteUrl();
								require_once ROOT_DIR . '/sys/Mailer.php';
								$libraryArchiveEmail = $owningLibrary->archiveRequestEmail ?? $configArray['Site']['email'];
								$mail        = new VuFindMailer();
								$subject     = 'New Request for Copies of Archive Content';
								$emailResult = $mail->send($libraryArchiveEmail, $newObject->email, $subject, $body);
								if ($emailResult === true){

								}elseif (PEAR_Singleton::isError($emailResult)){
									$interface->assign('error', "Your request could not be sent: {$emailResult->message}.");
								}else{
									$interface->assign('error', "Your request could not be sent due to an unknown error.");
									global $pikaLogger;
									$pikaLogger->error("Mail List Failure (unknown reason), parameters: $owningLibrary->archiveRequestEmail, $newObject->email, $subject, $body");
								}
							}else{
								$interface->assign('error', 'Please do not include html or links within your request');
								$newObject->delete();
							}
						}else{
							$interface->assign('error', "Your request could not be sent because the library does not accept requests for copies.");
						}
					}else{
						$interface->assign('error', $_SESSION['lastError']);
					}
				}
			}

			unset($archiveRequestFields['dateRequested']);

			$interface->assign('submitUrl', '/Archive2/RequestCopy/' . $nid);
			$interface->assign('structure', $archiveRequestFields);
			$interface->assign('saveButtonText', 'Submit Request');
			$interface->assign('archiveRequestMaterialsHeader', $owningLibrary->archiveRequestMaterialsHeader);

			// Set up captcha to limit spam submission
			if (isset($configArray['ReCaptcha']['publicKey'])){
				$captchaCode = recaptchaGetQuestion();
				$interface->assign('captcha', $captchaCode);
			}

		$fieldsForm = $interface->fetch('DataObjectUtil/objectEditForm.tpl');
		$interface->assign('requestForm', $fieldsForm);

		$this->display('requestCopy.tpl', 'Archival Material Copy Request');
	}

	function insertObject($structure){
		require_once ROOT_DIR . '/sys/DataObjectUtil.php';
		$newObject = new Archive2\ArchiveRequest();
		//Check to see if we are getting default values from the
		DataObjectUtil::updateFromUI($newObject, $structure);
		$newObject->nid    = $structure['nid']['value'];
		$validationResults = DataObjectUtil::validateObject($structure, $newObject);
		if ($validationResults['validatedOk']){
			$ret = $newObject->insert();
			if (!$ret){
				global $pikaLogger;
				if ($newObject->_lastError){
					$errorDescription = $newObject->_lastError->getUserInfo();
				}else{
					$errorDescription = 'Unknown error';
				}
				$pikaLogger->debug('Could not insert new object ' . $ret . ' ' . $errorDescription);
				$_SESSION['lastError'] = "An error occurred inserting {$newObject->nid} <br>{$errorDescription}";
				return false;
			}
		}else{
			global $pikaLogger;
			$errorDescription = implode(', ', $validationResults['errors']);
			$pikaLogger->debug('Could not validate new object Archive Request ' . $errorDescription);
			$_SESSION['lastError'] = "The information entered was not valid. <br>" . implode('<br>', $validationResults['errors']);
			return false;
		}
		return $newObject;
	}
}