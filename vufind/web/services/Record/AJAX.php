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
require_once ROOT_DIR . '/services/AJAX/MARC_AJAX_Basic.php';

class Record_AJAX extends AJAXHandler {

	use MARC_AJAX_Basic;

	protected $methodsThatRepondWithJSONUnstructured = array(
		'getPlaceHoldForm',
		'getPlaceHoldEditionsForm',
		'getBookMaterialForm',
		'placeHold',
		'bookMaterial',
		'reloadCover',
	);

	protected $methodsThatRespondWithHTML = array(
		'getBookingCalendar',
		'GetProspectorInfo', // Appears deprecated. pascal 4/26/2019
	);

	protected $methodsThatRespondWithXML = array(
		'IsLoggedIn',
	);

	protected $methodsThatRespondThemselves = array(
		'downloadMarc',
	);

	function IsLoggedIn(){
		return "<result>" . (UserAccount::isLoggedIn() ? "True" : "False") . "</result>";
	}

// Appears deprecated.  GroupedWork version appears to be the version still in use.  pascal 4/26/2019
	function GetProspectorInfo(){
		require_once ROOT_DIR . '/Drivers/marmot_inc/Prospector.php';
		global $configArray;
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();
		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		/** @var SearchObject_Solr $db */
		$db = new $class($url);

		// Retrieve Full record from Solr
		if (!($record = $db->getRecord($id))){
			PEAR_Singleton::raiseError(new PEAR_Error('Record Does Not Exist'));
		}

		$prospector = new Prospector();

		$searchTerms = array(
			array(
				'lookfor' => $record['title'],
				'index'   => 'Title',
			),
		);
		if (isset($record['author'])){
			$searchTerms[] = array(
				'lookfor' => $record['author'],
				'index'   => 'Author',
			);
		}
		$prospectorResults = $prospector->getTopSearchResults($searchTerms, 10);
		$interface->assign('prospectorResults', $prospectorResults['records']);
		return $interface->fetch('Record/ajax-prospector.tpl');
	}

	function getPlaceHoldForm(){
		global $interface;
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::isLoggedIn()){
			$id           = $_REQUEST['id'];
			$recordSource = $_REQUEST['recordSource'];
			$interface->assign('recordSource', $recordSource);
			if (isset($_REQUEST['volume'])){
				$interface->assign('volume', $_REQUEST['volume']);
			}

			//Get information to show a warning if the user does not have sufficient holds
			require_once ROOT_DIR . '/Drivers/marmot_inc/PType.php';
			$maxHolds = -1;
			//Determine if we should show a warning
			$ptype        = new PType();
			$ptype->pType = UserAccount::getUserPType();
			if ($ptype->find(true)){
				$maxHolds = $ptype->maxHolds;
			}
			$currentHolds = $user->numHoldsIls;
			//TODO: this check will need to account for linked accounts now
			if ($maxHolds != -1 && ($currentHolds + 1 > $maxHolds)){
				$interface->assign('showOverHoldLimit', true);
				$interface->assign('maxHolds', $maxHolds);
				$interface->assign('currentHolds', $currentHolds);
			}

			//Check to see if the user has linked users that we can place holds for as well
			//If there are linked users, we will add pickup locations for them as well
			$locations                      = $user->getValidPickupBranches($recordSource);
			$multipleAccountPickupLocations = false;
			$linkedUsers                    = $user->getLinkedUsers();
			if (count($linkedUsers)){
				foreach ($locations as $location){
					if (count($location->pickupUsers) > 1){
						$multipleAccountPickupLocations = true;
						break;
					}
				}
			}

			$interface->assign('pickupLocations', $locations);
			$interface->assign('multipleUsers', $multipleAccountPickupLocations); // switch for displaying the account drop-down (used for linked accounts)

			global $library;
			$interface->assign('showHoldCancelDate', $library->showHoldCancelDate);
			$interface->assign('defaultNotNeededAfterDays', $library->defaultNotNeededAfterDays);
			$interface->assign('showDetailedHoldNoticeInformation', $library->showDetailedHoldNoticeInformation);
			$interface->assign('treatPrintNoticesAsPhoneNotices', $library->treatPrintNoticesAsPhoneNotices);

			$holdDisclaimers = array();
			$patronLibrary   = $user->getHomeLibrary();
			if (strlen($patronLibrary->holdDisclaimer) > 0){
				$holdDisclaimers[$patronLibrary->displayName] = $patronLibrary->holdDisclaimer;
			}
			foreach ($linkedUsers as $linkedUser){
				$linkedLibrary = $linkedUser->getHomeLibrary();
				if (strlen($linkedLibrary->holdDisclaimer) > 0){
					$holdDisclaimers[$linkedLibrary->displayName] = $linkedLibrary->holdDisclaimer;
				}
			}

			$interface->assign('holdDisclaimers', $holdDisclaimers);

			require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
			$marcRecord = new MarcRecord($id);
			$title      = rtrim($marcRecord->getTitle(), ' /');
			$interface->assign('id', $marcRecord->getId());
			if (count($locations) == 0){
				$results = array(
					'title'        => 'Unable to place hold',
					'modalBody'    => '<p>Sorry, no copies of this title are available to your account.</p>',
					'modalButtons' => "",
				);
			}else{
				$results = array(
					'title'        => empty($title) ? 'Place Hold' : 'Place Hold on ' . $title,
					'modalBody'    => $interface->fetch("Record/hold-popup.tpl"),
					'modalButtons' => "<input type='submit' name='submit' id='requestTitleButton' value='Submit Hold Request' class='btn btn-primary' onclick='return VuFind.Record.submitHoldForm();'>",
				);
			}

		}else{
			$results = array(
				'title'        => 'Please login',
				'modalBody'    => "You must be logged in.  Please close this dialog and login before placing your hold.",
				'modalButtons' => "",
			);
		}
		return $results;
	}

	function getPlaceHoldEditionsForm(){
		global $interface;
		if (UserAccount::isLoggedIn()){

			$id           = $_REQUEST['id'];
			$recordSource = $_REQUEST['recordSource'];
			$interface->assign('recordSource', $recordSource);
			if (isset($_REQUEST['volume'])){
				$interface->assign('volume', $_REQUEST['volume']);
			}

			require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
			$marcRecord            = new MarcRecord($id);
			$groupedWork           = $marcRecord->getGroupedWorkDriver();
			$relatedManifestations = $groupedWork->getRelatedManifestations();
			$format                = $marcRecord->getFormat();
			$relatedManifestations = $relatedManifestations[$format[0]];
			$interface->assign('relatedManifestation', $relatedManifestations);
			$results = array(
				'title'        => 'Place Hold on Alternate Edition?',
				'modalBody'    => $interface->fetch('Record/hold-select-edition-popup.tpl'),
				'modalButtons' => '<a href="#" class="btn btn-primary" onclick="return VuFind.Record.showPlaceHold(\'Record\', \'' . $id . '\', false);">No, place a hold on this edition</a>',
			);
		}else{
			$results = array(
				'title'        => 'Please login',
				'modalBody'    => "You must be logged in.  Please close this dialog and login before placing your hold.",
				'modalButtons' => '',
			);
		}
		return $results;
	}

	function placeHold(){
		global $interface;
		global $analytics;
		$analytics->enableTracking();
		$recordId = $_REQUEST['id'];
		if (strpos($recordId, ':') > 0){
			list($source, $shortId) = explode(':', $recordId, 2);
		}else{
			$shortId = $recordId;
		}

		$user = UserAccount::getLoggedInUser();
		if ($user){
			//The user is already logged in

			if (!empty($_REQUEST['campus'])){
				//Check to see what account we should be placing a hold for
				//Rather than asking the user for this explicitly, we do it based on the pickup location
				$campus = $_REQUEST['campus'];

				$patron = null;
				if (!empty($_REQUEST['selectedUser'])){
					$selectedUserId = $_REQUEST['selectedUser'];
					if (is_numeric($selectedUserId)){ // we expect an id
						if ($user->id == $selectedUserId){
							$patron = $user;
						}else{
							$linkedUsers = $user->getLinkedUsers();
							foreach ($linkedUsers as $tmpUser){
								if ($tmpUser->id == $selectedUserId){
									$patron = $tmpUser;
									break;
								}
							}
						}
					}
				}else{
					//block below sets the $patron variable to place the hold through pick-up location. (shouldn't be needed anymore. plb 10-27-2015)
					$location = new Location();
					/** @var Location[] $userPickupLocations */
					$userPickupLocations = $location->getPickupBranches($user);
					foreach ($userPickupLocations as $tmpLocation){
						if ($tmpLocation->code == $campus){
							$patron = $user;
							break;
						}
					}
					if ($patron == null){
						//Check linked users
						$linkedUsers = $user->getLinkedUsers();
						foreach ($linkedUsers as $tmpUser){
							$location = new Location();
							/** @var Location[] $userPickupLocations */
							$userPickupLocations = $location->getPickupBranches($tmpUser);
							foreach ($userPickupLocations as $tmpLocation){
								if ($tmpLocation->code == $campus){
									$patron = $tmpUser;
									break;
								}
							}
							if ($patron != null){
								break;
							}
						}
					}
				}
				if ($patron == null){
					$results = array(
						'success' => false,
						'message' => 'You must select a valid user to place the hold for.',
						'title'   => 'Select valid user',
					);
				}else{
					$homeLibrary = $patron->getHomeLibrary();

					$cancelDate = empty($_REQUEST['canceldate']) ? null : trim($_REQUEST['canceldate']);

					if (isset($_REQUEST['selectedItem'])){
						$return = $patron->placeItemHold($shortId, $_REQUEST['selectedItem'], $campus, $cancelDate);
					}else{
						if (isset($_REQUEST['volume'])){
							$return = $patron->placeVolumeHold($shortId, $_REQUEST['volume'], $campus, $cancelDate);
						}else{

							$return = $patron->placeHold($shortId, $campus, $cancelDate);
							// If the hold requires an item-level hold, but there is only one item to choose from, just complete the hold with that one item
							if (!empty($return['items']) && count($return['items']) == 1){
								$return = $patron->placeItemHold($shortId, $return['items'][0]['itemNumber'], $campus, $cancelDate);
							}
						}
					}

					if (!empty($return['items'])){ // only go to item-level hold prompt if there are holdable items to choose from
						$interface->assign('campus', $campus);
						$interface->assign('canceldate', $cancelDate);
						$items = $return['items'];
						$interface->assign('items', $items);
						$interface->assign('message', $return['message']);
						$interface->assign('id', $shortId);
						$interface->assign('patronId', $patron->id);
						if (!empty($_REQUEST['autologout'])){
							$interface->assign('autologout', $_REQUEST['autologout']);
						} // carry user selection to Item Hold Form

						$interface->assign('showDetailedHoldNoticeInformation', $homeLibrary->showDetailedHoldNoticeInformation);
						$interface->assign('treatPrintNoticesAsPhoneNotices', $homeLibrary->treatPrintNoticesAsPhoneNotices);

						// Need to place item level holds.
						$results = array(
							'success'            => true,
							'needsItemLevelHold' => true,
							'message'            => $interface->fetch('Record/item-hold-popup.tpl'),
							'title'              => isset($return['title']) ? $return['title'] : '',
						);
					}else{ // Completed Hold Attempt
						$interface->assign('message', $return['message']);
						$interface->assign('success', $return['success']);

						//Get library based on patron home library since that is what controls their notifications rather than the active interface.
						//$library = Library::getPatronHomeLibrary();

//						global $library;
//						$canUpdateContactInfo = $library->allowProfileUpdates == 1;
//						// set update permission based on active library's settings. Or allow by default.
//						$canChangeNoticePreference = $library->showNoticeTypeInProfile == 1;
//						// when user preference isn't set, they will be shown a link to account profile. this link isn't needed if the user can not change notification preference.
//						$interface->assign('canUpdate', $canUpdateContactInfo);
//						$interface->assign('canChangeNoticePreference', $canChangeNoticePreference);
//						$interface->assign('showDetailedHoldNoticeInformation', $library->showDetailedHoldNoticeInformation);
//						$interface->assign('treatPrintNoticesAsPhoneNotices', $library->treatPrintNoticesAsPhoneNotices);

						$canUpdateContactInfo = $homeLibrary->allowProfileUpdates == 1;
						// set update permission based on active library's settings. Or allow by default.
						$canChangeNoticePreference = $homeLibrary->showNoticeTypeInProfile == 1;
						// when user preference isn't set, they will be shown a link to account profile. this link isn't needed if the user can not change notification preference.
						$interface->assign('canUpdate', $canUpdateContactInfo);
						$interface->assign('canChangeNoticePreference', $canChangeNoticePreference);
						$interface->assign('showDetailedHoldNoticeInformation', $homeLibrary->showDetailedHoldNoticeInformation);
						$interface->assign('treatPrintNoticesAsPhoneNotices', $homeLibrary->treatPrintNoticesAsPhoneNotices);
						$interface->assign('profile', $patron); // Use the account the hold was placed with for the success message.

						$results = array(
							'success' => $return['success'],
							'message' => $interface->fetch('Record/hold-success-popup.tpl'),
							'title'   => isset($return['title']) ? $return['title'] : '',
						);
						if (isset($_REQUEST['autologout'])){
							$masqueradeMode = UserAccount::isUserMasquerading();
							if ($masqueradeMode){
								require_once ROOT_DIR . '/services/MyAccount/Masquerade.php';
								MyAccount_Masquerade::endMasquerade();
							}else{
								UserAccount::softLogout();
							}
							$results['autologout'] = true;
							unset($_REQUEST['autologout']); // Prevent entering the second auto log out code-block below.
						}
					}
				}
			}else{
				$results = array(
					'success' => false,
					'message' => 'No pick-up location is set.  Please choose a Location for the title to be picked up at.',
				);
			}

			if (isset($_REQUEST['autologout']) && !(isset($results['needsItemLevelHold']) && $results['needsItemLevelHold'])){
				// Only go through the auto-logout when the holds process is completed. Item level holds require another round of interaction with the user.
				$masqueradeMode = UserAccount::isUserMasquerading();
				if ($masqueradeMode){
					require_once ROOT_DIR . '/services/MyAccount/Masquerade.php';
					MyAccount_Masquerade::endMasquerade();
				}else{
					UserAccount::softLogout();
				}
				$results['autologout'] = true;
			}
		}else{
			$results = array(
				'success' => false,
				'message' => 'You must be logged in to place a hold.  Please close this dialog and login.',
				'title'   => 'Please login',
			);
		}
		return $results;
	}

	function getBookMaterialForm($errorMessage = null){
		global $interface;
		if (UserAccount::isLoggedIn()){
			$id = $_REQUEST['id'];

			require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
			$marcRecord = new MarcRecord($id);
			$title      = $marcRecord->getTitle();
			$interface->assign('id', $id);
			if ($errorMessage){
				$interface->assign('errorMessage', $errorMessage);
			}
			$results = array(
				'title'        => 'Schedule ' . $title,
				'modalBody'    => $interface->fetch("Record/book-materials-form.tpl"),
				'modalButtons' => '<button class="btn btn-primary" onclick="$(\'#bookMaterialForm\').submit()">Schedule Item</button>'
				// Clicking invokes submit event, which allows the validator to act before calling the ajax handler
			);
		}else{
			$results = array(
				'title'        => 'Please login',
				'modalBody'    => "You must be logged in.  Please close this dialog and login before scheduling this item.",
				'modalButtons' => "",
			);
		}
		return $results;
	}

	function getBookingCalendar(){
		$recordId = $_REQUEST['id'];
		if (strpos($recordId, ':') !== false){
			list(, $recordId) = explode(':', $recordId, 2);
		} // remove any prefix from the recordId
		if (!empty($recordId)){
			$user    = UserAccount::getLoggedInUser();
			$catalog = $user->getCatalogDriver();
//			$catalog = CatalogFactory::getCatalogConnectionInstance();
			return $catalog->getBookingCalendar($recordId);
		}
	}

	function bookMaterial(){
		if (!empty($_REQUEST['id'])){
			$recordId = $_REQUEST['id'];
			if (strpos($recordId, ':') !== false){
				list(, $recordId) = explode(':', $recordId, 2);
			} // remove any prefix from the recordId
		}
		if (empty($recordId)){
			return array('success' => false, 'message' => 'Item ID is required.');
		}
		if (isset($_REQUEST['startDate'])){
			$startDate = $_REQUEST['startDate'];
		}else{
			return array('success' => false, 'message' => 'Start Date is required.');
		}

		$startTime = empty($_REQUEST['startTime']) ? null : $_REQUEST['startTime'];
		$endDate   = empty($_REQUEST['endDate']) ? null : $_REQUEST['endDate'];
		$endTime   = empty($_REQUEST['endTime']) ? null : $_REQUEST['endTime'];

		$user = UserAccount::getLoggedInUser();
		if ($user){ // The user is already logged in
			return $user->bookMaterial($recordId, $startDate, $startTime, $endDate, $endTime);

		}else{
			return array('success' => false, 'message' => 'User not logged in.');
		}
	}

}
