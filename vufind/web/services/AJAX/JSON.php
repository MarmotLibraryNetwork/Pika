<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

require_once ROOT_DIR . '/AJAXHandler.php';

class AJAX_JSON extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'getAutoLogoutPrompt',
		'getReturnToHomePrompt',
		'getPayFinesAfterAction',
	);

	protected $methodsThatRespondWithJSONResultWrapper = array(
		'getUserLists',
		'loginUser',
//		'trackEvent',
		'getPayFinesAfterAction',
	);

	protected $methodsThatRespondWithHTML = array(
		'getHoursAndLocations',
	);

	function isLoggedIn(){
		return UserAccount::isLoggedIn();
	}

	function getUserLists(){
		$user      = UserAccount::getLoggedInUser();
		$lists     = $user->getLists();
		$userLists = array();
		foreach ($lists as $current){
			$userLists[] = array(
				'id'    => $current->id,
				'title' => $current->title,
			);
		}
		return $userLists;
	}

	function loginUser(){
		//Login the user.  Must be called via Post parameters.
		global $interface;
		$isLoggedIn = UserAccount::isLoggedIn();
		if (!$isLoggedIn){
			$user = UserAccount::login();

			$interface->assign('user', $user); // PLB Assignment Needed before error checking?
			if (!$user || PEAR_Singleton::isError($user)){

				// Expired Card Notice
				if ($user && $user->message == 'expired_library_card'){
					return array(
						'success' => false,
						'message' => translate('expired_library_card'),
					);
				}

				// General Login Error
				/** @var PEAR_Error $error */
				$error   = $user;
				$message = PEAR_Singleton::isError($user) ? translate($error->getMessage()) : translate("Sorry that login information was not recognized, please try again.");
				return array(
					'success' => false,
					'message' => $message,
				);
			}
		}else{
			$user = UserAccount::getLoggedInUser();
		}

		$patronHomeBranch = Location::getUserHomeLocation();
		//Check to see if materials request should be activated
		require_once ROOT_DIR . '/sys/MaterialsRequest.php';

		return array(
			'success'                => true,
			'name'                   => ucwords($user->firstname . ' ' . $user->lastname),
//			'phone'                  => $user->phone,
//			'email'                  => $user->email,
			'homeLocation'           => isset($patronHomeBranch) ? $patronHomeBranch->code : '',
			'homeLocationId'         => isset($patronHomeBranch) ? $patronHomeBranch->locationId : '',
			'enableMaterialsRequest' => MaterialsRequest::enableMaterialsRequest(true),
		);
	}

//	function trackEvent(){
//		global $analytics;
//		if (!isset($_REQUEST['category']) || !isset($_REQUEST['eventAction'])){
//			return 'Must provide a category and action to track an event';
//		}
//		$analytics->enableTracking();
//		$category = strip_tags($_REQUEST['category']);
//		$action   = strip_tags($_REQUEST['eventAction']);
//		$data     = isset($_REQUEST['data']) ? strip_tags($_REQUEST['data']) : '';
//		$analytics->addEvent($category, $action, $data);
//		return true;
//	}

	function getHoursAndLocations(){
		//Get a list of locations for the current library
		global $library;
		$tmpLocation                              = new Location();
		$tmpLocation->libraryId                   = $library->libraryId;
		$tmpLocation->showInLocationsAndHoursList = 1;
		$tmpLocation->orderBy('isMainBranch DESC, displayName'); // List Main Branches first, then sort by name
		$libraryLocations = array();
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
		$result         = array(
			'title'        => 'Still There?',
			'modalBody'    => $interface->fetch('AJAX/autoLogoutPrompt.tpl'),
			'modalButtons' => "<div id='continueSession' class='btn btn-primary' onclick='continueSession();'>Continue</div>" .
				($masqueradeMode ?
					"<div id='endSession' class='btn btn-masquerade' onclick='VuFind.Account.endMasquerade()'>End Masquerade</div>" .
					"<div id='endSession' class='btn btn-warning' onclick='endSession()'>Logout</div>"
					:
					"<div id='endSession' class='btn btn-warning' onclick='endSession()'>Logout</div>"),
		);
		return $result;
	}

	function getReturnToHomePrompt(){
		global $interface;
		$result = array(
			'title'        => 'Still There?',
			'modalBody'    => $interface->fetch('AJAX/autoReturnToHomePrompt.tpl'),
			'modalButtons' => "<a id='continueSession' class='btn btn-primary' onclick='continueSession();'>Continue</a>",
		);
		return $result;
	}

	function getPayFinesAfterAction(){
		global $interface,
		       $configArray;
		$result = array(
			'title'        => 'Pay Fines',
			'modalBody'    => $interface->fetch('AJAX/refreshFinesAccountInfo.tpl'),
			'modalButtons' => '<a class="btn btn-primary" href="' . $configArray['Site']['path'] . '/MyAccount/Fines?reload">Refresh My Fines Information</a>',
		);
		return $result;
	}
}