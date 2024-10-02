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
require_once ROOT_DIR . '/sys/Pika/Functions.php';
require_once ROOT_DIR . '/Action.php';

use function Pika\Functions\{recaptchaGetQuestion, recaptchaCheckAnswer};

class SelfReg extends Action {
	protected $catalog;

	function __construct(){
		/** @var Library $library */
		global $library;
//		if (!empty($library->externalSelfRegistrationUrl)){
//			header('Location: ' . $library->externalSelfRegistrationUrl);
//			die;
//		}else
		// Code block above is disabled because Sacramento would like use the Pika self-reg for patrons without email addresses
		// as a fallback from their default external service.
			if (!$library->enableSelfRegistration){
			// Do not display self-registration page or allow form-submission when the library hasn't enabled self-registration.
			global $interface;
			$pageTitle = 'Access Error';
			$interface->assign('shortPageTitle', $pageTitle);
			$this->display('../Admin/noPermission.tpl', $pageTitle);
			die;
		}
		// Connect to Catalog
		$this->catalog = CatalogFactory::getCatalogConnectionInstance();
	}
    
	function launch($msg = null){
		global $interface;
		global $library;
		global $configArray;

		/** @var  CatalogConnection $catalog */
//		$catalog = CatalogFactory::getCatalogConnectionInstance();
		$selfRegFields = $this->catalog->getSelfRegistrationFields();
		// For Arlington, this function call causes a page redirect to an external web page. plb 1-15-2016

		$pinMinimumLength = $configArray['Catalog']['pinMinimumLength'];
		$pinMaximumLength = $configArray['Catalog']['pinMaximumLength'];

		if (isset($_REQUEST['submit'])){

			if (!empty($configArray['ReCaptcha']['privateKey'])){
				try {
					$recaptchaValid = recaptchaCheckAnswer();
				} catch (Exception $e){
					$recaptchaValid = false;
				}
			}else{
				$recaptchaValid = true;
			}
			if ($library->enableSelfRegistration && isset($_POST['pin'])){
				$pinLength = strlen($_POST['pin']);
				if ($pinLength < $pinMinimumLength or $pinLength > $pinMaximumLength){
					if ($pinMinimumLength == $pinMaximumLength){
						return 'New ' . translate('pin') . ' must be exactly ' . $pinMinimumLength . ' characters.';
					}else{
						return 'New ' . translate('pin') . ' must be ' . $pinMinimumLength . " to " . $pinMaximumLength . ' characters.';
					}
				}
			}
			if (!$recaptchaValid){
				$interface->assign('captchaMessage', 'The CAPTCHA response was incorrect, please try again.');
			}else{
				//Submit the form to ILS
				$result = $this->catalog->selfRegister();
				$interface->assign('selfRegResult', $result);
			}

			// Pre-fill form with user supplied data
			foreach ($selfRegFields as &$property){
                if(isset($property['property']) && $property['type'] !== 'header' ) {
				    $userValue           = $_REQUEST[$property['property']];
				    $property['default'] = $userValue;
                }
			}
            unset($property);
		}
        
		$interface->assign('submitUrl', '/MyAccount/SelfReg');
		$interface->assign('structure', $selfRegFields);
		$interface->assign('saveButtonText', 'Register');

		// Set up captcha to limit spam self registrations
		if (isset($configArray['ReCaptcha']['publicKey']) && $configArray['ReCaptcha']['publicKey'] !== ''){
			$captchaCode = recaptchaGetQuestion();
			$interface->assign('captcha', $captchaCode);
		}

		$numericOnlyPins      = $configArray['Catalog']['numericOnlyPins'];
		$alphaNumericOnlyPins = $configArray['Catalog']['alphaNumericOnlyPins'];
		$sierraTrivialPin     = !empty($configArray['Catalog']['sierraTrivialPin']) && ($configArray['Catalog']['sierraTrivialPin'] == 1 || $configArray['Catalog']['sierraTrivialPin'] == "true");
		$selfRegStateRegex    = $configArray['Catalog']['selfRegStateRegex'];
		$selfRegStateMessage  = $configArray['Catalog']['selfRegStateMessage'];
		$selfRegZipRegex      = $configArray['Catalog']['selfRegZipRegex'];
		$selfRegZipMessage    = $configArray['Catalog']['selfRegZipMessage'];
		$interface->assign('numericOnlyPins', $numericOnlyPins);
		$interface->assign('alphaNumericOnlyPins', $alphaNumericOnlyPins);
		$interface->assign('pinMinimumLength', $pinMinimumLength);
		$interface->assign('pinMaximumLength', $pinMaximumLength);
		$interface->assign('selfRegStateRegex', $selfRegStateRegex);
		$interface->assign('selfRegStateMessage', $selfRegStateMessage);
		$interface->assign('selfRegZipRegex', $selfRegZipRegex);
		$interface->assign('selfRegZipMessage', $selfRegZipMessage);
		if ($sierraTrivialPin) {
			$interface->assign('sierraTrivialPin', true);
		}

		$fieldsForm = $interface->fetch('DataObjectUtil/objectEditForm.tpl');
		$interface->assign('selfRegForm', $fieldsForm);

		$interface->assign('selfRegistrationFormMessage', $library->selfRegistrationFormMessage);
		$interface->assign('selfRegistrationSuccessMessage', $library->selfRegistrationSuccessMessage);
		$interface->assign('promptForBirthDateInSelfReg', $library->promptForBirthDateInSelfReg);

		$this->display('selfReg.tpl', 'Self Registration');

	}
}
