<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2022  Marmot Library Network
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
 * Date: 12/7/2022
 *
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class MyAccount_UpdatePin extends MyAccount {

	function launch($msg = null){
		global $interface;
		global $module;
		global $action;
		global $library;
		global $configArray;

		// Submit action
		if (!empty($_POST['update'])){
			// only accept POST requests

			$user = UserAccount::getLoggedInUser();
			if ($user){
				$errors = $user->updatePin();
				if (is_string($errors) && stripos($errors, 'success')){
					// Successful Pin Update

					// Follow-up actions taken from index.php
					if (isset($_REQUEST['returnUrl'])){
						$followupUrl = $_REQUEST['returnUrl'];
						header('Location: ' . $followupUrl);
						exit();
					}
					// Follow up with both module and action
					elseif (isset($_REQUEST['followupModule']) && isset($_REQUEST['followupAction'])){

						// Set the module & actions from the follow-up settings
						$module = strip_tags($_REQUEST['followupModule']);
						$action = strip_tags($_REQUEST['followupAction']);
						if (!empty($_REQUEST['recordId'])){
							$followupUrl =  '/' . $module .  '/' . strip_tags($_REQUEST['recordId']) .  '/' . $action;
						} elseif (!empty($_REQUEST['id'])){
							// User List action
							$followupUrl =  '/' . $module .  '/' . strip_tags($_REQUEST['id']) .  '/' . $action;
						} else {
							$followupUrl =  '/' . $module .  '/' . $action;
						}
						header('Location: ' . $followupUrl);
						exit();

					} elseif (isset($_REQUEST['followup']) || isset($_REQUEST['followupModule'])){
						// Follow up when only the module or only the action is set
						$module = strip_tags($_REQUEST['followupModule'] ?? $configArray['Site']['defaultModule']);
						$action = strip_tags($_REQUEST['followup'] ?? $_REQUEST['followupAction'] ?? 'Home');

						if (!empty($_REQUEST['recordId'])){
							$followupUrl =  '/' . $module .  '/' . strip_tags($_REQUEST['recordId']) .  '/' . $action;
						} elseif (!empty($_REQUEST['id'])){
							// User List action
							$followupUrl =  '/' . $module .  '/' . strip_tags($_REQUEST['id']) .  '/' . $action;
						} else {
							$followupUrl =  '/' . $module .  '/' . $action;
						}
						header('Location: ' . $followupUrl);
						exit();
					} else {
						header('Location: /MyAccount/Home');
						die();
					}


				} else {
					$interface->assign('pinUpdateErrors', $errors);
				}
			}

		}


		// Assign the followup task to come back to after they login -- note that
		//     we need to check for a pre-existing followup task in case we've
		//     looped back here due to an error (bad username/password, etc.).
//		$followup = isset($_REQUEST['followup']) ?  strip_tags($_REQUEST['followup']) : $action;
		//TODO: obsolete followup for the follow module and action variables; or use return or returnUrl variable

		// Don't go to the trouble if we're just logging in to the Home action
		if (/*$followup != 'Home' || */(isset($_REQUEST['followupModule']) && isset($_REQUEST['followupAction']))) {
//			$interface->assign('followup', $followup);
			$interface->assign('followupModule', isset($_REQUEST['followupModule']) ? strip_tags($_REQUEST['followupModule']) : $module);
			$interface->assign('followupAction', $_REQUEST['followupAction'] ?? $action);

			//TODO: List Actions needed?

			// Special case -- if user is trying to view a private list, we need to
			// attach the list ID to the action:
			if ($action == 'MyList') {
				if (isset($_GET['id'])){
					$interface->assign('id', $_GET['id']);
				}
			}

			// If we have a save or delete action, create the appropriate recordId
			//     parameter.  If we've looped back due to user error and already have
			//     a recordId parameter, remember it for future reference.
			if (isset($_REQUEST['delete'])) {
				$interface->assign('returnUrl', $_SERVER['REQUEST_URI']);
			} elseif (isset($_REQUEST['save'])) {
				$interface->assign('returnUrl', $_SERVER['REQUEST_URI']);
				//TODO: I don't think the below would have worked correctly
//			} elseif (isset($_REQUEST['recordId'])) {
//				$interface->assign('returnUrl', $_REQUEST['recordId']);
			}

			// comments and tags also need to be preserved if present
			if (isset($_REQUEST['comment'])) {
				$interface->assign('comment', $_REQUEST['comment']);
			}

//			// preserve card Number for Masquerading
//			if (isset($_REQUEST['cardNumber'])) {
//				$interface->assign('cardNumber', $_REQUEST['cardNumber']);
//				$interface->assign('followupModule', 'MyAccount');
//				$interface->assign('followupAction', 'Masquerade');
//			}

		}

		//Error messages taken from login attempt in index.php
		$interface->assign('message', $msg);
		//TODO: is username needed?
		if (isset($_REQUEST['username'])) {
			$interface->assign('username', $_REQUEST['username']);
		}
		$interface->assign('enableSelfRegistration', 0);

		if ($configArray['Catalog']['ils'] == 'Horizon' || $configArray['Catalog']['ils'] == 'Symphony'){
			$interface->assign('showForgotPinLink', true);
			$catalog          = CatalogFactory::getCatalogConnectionInstance();
			$useEmailResetPin = method_exists($catalog->driver, 'emailResetPin');
			$interface->assign('useEmailResetPin', $useEmailResetPin);
		}elseif ($configArray['Catalog']['ils'] == 'Sierra'){
			$catalog = CatalogFactory::getCatalogConnectionInstance();
			if (!empty($catalog->accountProfile->loginConfiguration) && $catalog->accountProfile->loginConfiguration == 'barcode_pin'){
				$interface->assign('showForgotPinLink', true);
				$useEmailResetPin = method_exists($catalog->driver, 'emailResetPin');
				$interface->assign('useEmailResetPin', $useEmailResetPin);
			}
		}

		// Because we are forcing a Pin update we can not display the convince buttons at the top of page and side bars
		$interface->assign('isUpdatePinPage', true);
		$interface->assign('displaySidebarMenu', false);

		// Password Requirements
		$numericOnlyPins      = $configArray['Catalog']['numericOnlyPins'];
		$alphaNumericOnlyPins = $configArray['Catalog']['alphaNumericOnlyPins'];
		$pinMinimumLength     = $configArray['Catalog']['pinMinimumLength'];
		$pinMaximumLength     = $configArray['Catalog']['pinMaximumLength'];
		$interface->assign('numericOnlyPins', $numericOnlyPins);
		$interface->assign('alphaNumericOnlyPins', $alphaNumericOnlyPins);
		$interface->assign('pinMinimumLength', $pinMinimumLength);
		$interface->assign('pinMaximumLength', $pinMaximumLength);

		$this->display('../MyAccount/updatePin.tpl', 'Update My Pin', '');
	}
}