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

require_once ROOT_DIR . "/Action.php";

class MyAccount_Login extends Action {

	function __construct(){
	}

	function launch($msg = null){
		global $interface;
		global $module;
		global $action;
		global $library;
		global $configArray;

		// We should never access this module directly -- this is called by other
		// actions as a support function.  If accessed directly, just redirect to
		// the MyAccount home page.
		if ($module == 'MyAccount' && $action == 'Login') {
			header('Location: /MyAccount/Home');
			die();
		}

		//TODO: explain when this comes into effect
		if (!empty($_REQUEST['return'])){
			header('Location: ' . $_REQUEST['return']);
			die();
		}

		// Assign the followup task to come back to after they login -- note that
		//     we need to check for a pre-existing followup task in case we've
		//     looped back here due to an error (bad username/password, etc.).
		$followup = isset($_REQUEST['followup']) ?  strip_tags($_REQUEST['followup']) : $action;
		//TODO: obsolete followup for the follow module and action variables; or use return or returnUrl variable

		// Don't go to the trouble if we're just logging in to the Home action
		if ($followup != 'Home' || (isset($_REQUEST['followupModule']) && isset($_REQUEST['followupAction']))) {
			$interface->assign('followup', $followup);
			$interface->assign('followupModule', isset($_REQUEST['followupModule']) ? strip_tags($_REQUEST['followupModule']) : $module);
			$interface->assign('followupAction', $_REQUEST['followupAction'] ?? $action);

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
			} elseif (isset($_REQUEST['recordId'])) {
				$interface->assign('returnUrl', $_REQUEST['recordId']);
			}

			// comments and tags also need to be preserved if present
			if (isset($_REQUEST['comment'])) {
				$interface->assign('comment', $_REQUEST['comment']);
			}

			// preserve card Number for Masquerading
			if (isset($_REQUEST['cardNumber'])) {
				$interface->assign('cardNumber', $_REQUEST['cardNumber']);
				$interface->assign('followupModule', 'MyAccount');
				$interface->assign('followupAction', 'Masquerade');
			}

		}
		$interface->assign('message', $msg);
		if (isset($_REQUEST['username'])) {
			$interface->assign('username', $_REQUEST['username']);
		}

		$catalog              = CatalogFactory::getCatalogConnectionInstance();
		$defaultUserNameLabel = $catalog->accountProfile->usingPins() ? 'Library Card Number' : 'Name';
		$defaultPasswordLabel = $catalog->accountProfile->usingPins() ? translate('PIN') : 'Library Card Number';

		if (isset($library)){
			$interface->assign('enableSelfRegistration', $library->enableSelfRegistration || $library->externalSelfRegistrationUrl);
			$interface->assign('selfRegLink', empty($library->externalSelfRegistrationUrl) ? '/MyAccount/SelfReg' : $library->externalSelfRegistrationUrl);
			$interface->assign('usernameLabel', !empty($library->loginFormUsernameLabel) ? $library->loginFormUsernameLabel : $defaultUserNameLabel);
			$interface->assign('passwordLabel', !empty($library->loginFormPasswordLabel) ? $library->loginFormPasswordLabel : $defaultPasswordLabel);
		}else{
			$interface->assign('enableSelfRegistration', 0);
			$interface->assign('usernameLabel', $defaultUserNameLabel);
			$interface->assign('passwordLabel', $defaultPasswordLabel);
		}

		if (!empty($catalog->accountProfile) && $catalog->accountProfile->usingPins() && method_exists($catalog->driver, 'emailResetPin') && empty($library->hideResetPinLink)){
			$interface->assign('showForgotPinLink', true);
		}

		$interface->assign('isLoginPage', true);

		$this->display('../MyAccount/login.tpl', 'Login');
	}
}

