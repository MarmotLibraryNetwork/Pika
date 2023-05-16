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

class AJAX_JSON extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = [
		'getAutoLogoutPrompt',
		'getReturnToHomePrompt',
		'getPayFinesAfterAction',
	];

	protected $methodsThatRespondWithJSONResultWrapper = [
//		'getUserLists',
		'loginUser',
		'getPayFinesAfterAction',
	];

	protected $methodsThatRespondWithHTML = [
		'getHoursAndLocations',
	];

	function isLoggedIn(){
		return UserAccount::isLoggedIn();
	}

//	function getUserLists(){
//		$user      = UserAccount::getLoggedInUser();
//		$lists     = $user->getLists();
//		$userLists = [];
//		foreach ($lists as $current){
//			$userLists[] = [
//				'id'    => $current->id,
//				'title' => $current->title,
//			];
//		}
//		return $userLists;
//	}

	function loginUser(){
		//Login the user.  Must be called via Post parameters.
		global $interface;
		$isLoggedIn = UserAccount::isLoggedIn();
		if (!$isLoggedIn){
			$user = UserAccount::login();

//			$interface->assign('user', $user); // PLB Assignment Needed before error checking?
			if (!$user || PEAR_Singleton::isError($user)){

				// Expired Card Notice
				if ($user && $user->message == 'expired_library_card'){
					return [
						'success' => false,
						'message' => translate('expired_library_card'),
					];
				}

				// General Login Error
				/** @var PEAR_Error $error */
				$error   = $user;
				$message = PEAR_Singleton::isError($user) ? translate($error->getMessage()) : translate('Sorry that login information was not recognized, please try again.');
				return [
					'success' => false,
					'message' => $message,
				];
			}
		}else{
			$user = UserAccount::getLoggedInUser();
		}

		$return = [
			'success' => true,
			'name'    => $user->displayName,
		];
		if (!empty($user->pinUpdateRequired)){
			$return['forcePinUpdate'] = true;
			$form                     = $this->getPinUpdateForm();
			$return                   = array_merge($return, $form);
		}
		return $return;
	}

	function getPinUpdateForm(){
		/** @var Library $library */
		global $interface;
		global $library;
		global $configArray;

		$interface->assign('enableSelfRegistration', 0);

		$catalog = CatalogFactory::getCatalogConnectionInstance();
		if (!empty($catalog->accountProfile) && $catalog->accountProfile->usingPins() && method_exists($catalog->driver, 'emailResetPin')){
			$interface->assign('showForgotPinLink', true);
		}

		// Password Requirements
		$numericOnlyPins      = $configArray['Catalog']['numericOnlyPins'];
		$alphaNumericOnlyPins = $configArray['Catalog']['alphaNumericOnlyPins'];
		$pinMinimumLength     = $configArray['Catalog']['pinMinimumLength'];
		$pinMaximumLength     = $configArray['Catalog']['pinMaximumLength'];
		$interface->assign('numericOnlyPins', $numericOnlyPins);
		$interface->assign('alphaNumericOnlyPins', $alphaNumericOnlyPins);
		$interface->assign('pinMinimumLength', $pinMinimumLength);
		$interface->assign('pinMaximumLength', $pinMaximumLength);
		$sierraTrivialPin     = !empty($configArray['Catalog']['sierraTrivialPin']) && ($configArray['Catalog']['sierraTrivialPin'] == 1 || $configArray['Catalog']['sierraTrivialPin'] == "true");
		if ($sierraTrivialPin) {
			$interface->assign('sierraTrivialPin', true);
		}

		$title = translate('Update PIN');
		return [
			'title'        => $title,
			'modalBody'    => $interface->fetch('MyAccount/updatePinPopUp.tpl'),
			'modalButtons' => "<button id='pinFormSubmitButton' class='tool btn btn-primary' onclick='$(\"#pinForm\").submit();'>$title</button>",
		];
	}

	function getHoursAndLocations(){
		//Get a list of locations for the current library
		global $library;
		$tmpLocation                              = new Location();
		$tmpLocation->libraryId                   = $library->libraryId;
		$tmpLocation->showInLocationsAndHoursList = 1;
		$tmpLocation->orderBy('isMainBranch DESC, displayName'); // List Main Branches first, then sort by name
		$libraryLocations = [];
		$tmpLocation->find();
		if ($tmpLocation->N == 0){
			//Get all locations
			$tmpLocation                              = new Location();
			$tmpLocation->showInLocationsAndHoursList = 1;
			$tmpLocation->orderBy('displayName');
			$tmpLocation->find();
		}
		while ($tmpLocation->fetch()){
			$locationInfo       = $tmpLocation->getLocationInformation();
			$libraryLocations[] = $locationInfo;
		}

		global $interface;
		$interface->assign('libraryLocations', $libraryLocations);
		return $interface->fetch('AJAX/libraryHoursAndLocations.tpl');
	}

	function getAutoLogoutPrompt(){
		global $interface;
		$masqueradeMode = UserAccount::isUserMasquerading();
		$result         = [
			'title'        => 'Still There?',
			'modalBody'    => $interface->fetch('AJAX/autoLogoutPrompt.tpl'),
			'modalButtons' => "<div id='continueSession' class='btn btn-primary' onclick='continueSession();'>Continue</div>" .
				($masqueradeMode ?
					"<div id='endSession' class='btn btn-masquerade' onclick='Pika.Account.endMasquerade()'>End Masquerade</div>" .
					"<div id='endSession' class='btn btn-warning' onclick='endSession()'>Logout</div>"
					:
					"<div id='endSession' class='btn btn-warning' onclick='endSession()'>Logout</div>"),
		];
		return $result;
	}

	function getReturnToHomePrompt(){
		global $interface;
		$result = [
			'title'        => 'Still There?',
			'modalBody'    => $interface->fetch('AJAX/autoReturnToHomePrompt.tpl'),
			'modalButtons' => "<a id='continueSession' class='btn btn-primary' onclick='continueSession();'>Continue</a>",
		];
		return $result;
	}

	function getPayFinesAfterAction(){
		global $interface;
		return [
			'title'        => 'Pay Fines',
			'modalBody'    => $interface->fetch('AJAX/refreshFinesAccountInfo.tpl'),
			'modalButtons' => '<a class="btn btn-primary" href="/MyAccount/Fines?reload">Refresh My Fines Information</a>',
		];
	}
}
