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
require_once ROOT_DIR . '/services/AJAX/MARC_AJAX_Basic.php';

class Record_AJAX extends AJAXHandler {

	use MARC_AJAX_Basic;

	protected array $methodsThatRespondWithJSONUnstructured = array(
	 'getPlaceHoldForm',
	 'getPlaceHoldEditionsForm',
	 'getBookMaterialForm',
	 'placeHold',
	 'bookMaterial',
	 'reloadCover',
	 'forceReExtract',
	 'getCheckInGrid',
	);

	protected array $methodsThatRespondWithHTML = array(
	 'getBookingCalendar',
	);

	protected array $methodsThatRespondWithXML = array(
	 'IsLoggedIn',
	);

	protected array $methodsThatRespondThemselves = array(
	 'downloadMarc',
	);

	function IsLoggedIn(){
		return "<result>" . (UserAccount::isLoggedIn() ? "True" : "False") . "</result>";
	}


	function getPlaceHoldForm(){
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::isLoggedIn()){
			global $interface;
			require_once ROOT_DIR . '/services/SourceAndId.php';
			$sourceAndId        = new SourceAndId($_REQUEST['id']);
			$recordSource       = $_REQUEST['recordSource'];
			$hasHomePickupItems = $_REQUEST['hasHomePickupItems'] == '1' || $_REQUEST['hasHomePickupItems'] == 'true';
			$interface->assign('recordSource', $recordSource);
			if (isset($_REQUEST['volume'])){
				$interface->assign('volume', $_REQUEST['volume']);
			}

			//Get information to show a warning if the user does not have sufficient holds
			$this->getMaxHoldsWarnings($user);

			if ($hasHomePickupItems){
				$interface->assign('hasHomePickupItems', true);
				$locations = $user->getHomePickupLocations($sourceAndId);
			}
			else{
				//if (!$hasHomePickupItems || empty($locations)){
				//Check to see if the user has linked users that we can place holds for as well
				//If there are linked users, we will add pickup locations for them as well
				$locations = $user->getValidPickupBranches($recordSource);
			}

			$multipleAccountPickupLocations = false;
			$linkedUsers                    = $user->getLinkedUsers();
			if (count($linkedUsers) >= 1){
				foreach ($locations as $location){
					if (!empty($location->pickupUsers) && is_array($location->pickupUsers) && count($location->pickupUsers) > 1){
						$_pu = implode(',', $location->pickupUsers);
						unset($location->pickupUsers);
						$location->pickupUsers          = $_pu;
						$multipleAccountPickupLocations = true;
					}
				}
			}


			$interface->assign('pickupLocations', $locations);
			$interface->assign('multipleUsers', $multipleAccountPickupLocations); // switch for displaying the account drop-down (used for linked accounts)

			global $library;
			$interface->assign('showDetailedHoldNoticeInformation', $library->showDetailedHoldNoticeInformation);
			$interface->assign('treatPrintNoticesAsPhoneNotices', $library->treatPrintNoticesAsPhoneNotices);

			$holdDisclaimers = $autoCancels = [];
			$patronLibrary   = $user->getHomeLibrary();
			if (!empty($patronLibrary->holdDisclaimer)){
				$holdDisclaimers[$patronLibrary->displayName] = $patronLibrary->holdDisclaimer;
			}
			$autoCancels = $this->getAutoCancelHoldMessages($patronLibrary);

			global $configArray;
			$ils = $configArray['Catalog']['ils'];
			if ($ils == 'Sierra' && $user->isStaff()){
				$interface->assign('allowStaffPlacedHoldRequest', true);
				if ($user->getAccountProfile()->usingPins()) {
					$patronBarcodeLabel = !empty($patronLibrary->loginFormUsernameLabel) ? $patronLibrary->loginFormUsernameLabel : 'Library Card Number';
				} else {
					$patronBarcodeLabel = !empty($patronLibrary->loginFormPasswordLabel) ? $patronLibrary->loginFormPasswordLabel : 'Library Card Number';
				}
				$interface->assign('patronBarcodeLabel', $patronBarcodeLabel);
			}

			foreach ($linkedUsers as $linkedUser){
				$this->getMaxHoldsWarnings($linkedUser);

				$linkedLibrary = $linkedUser->getHomeLibrary();
				if (!empty($linkedLibrary->holdDisclaimer)){
					$holdDisclaimers[$linkedLibrary->displayName] = $linkedLibrary->holdDisclaimer;
				}

				$autoCancels = $this->getAutoCancelHoldMessages($linkedLibrary, $autoCancels);
			}

			$interface->assign('holdDisclaimers', $holdDisclaimers);
			$interface->assign('autoCancels', $autoCancels);

			/** @var MarcRecord $marcRecord */
			$marcRecord = RecordDriverFactory::initRecordDriverById($sourceAndId);
			$title      = rtrim($marcRecord->getTitle(), ' /');
			$interface->assign('id', $marcRecord->getId());
			if (count($locations) == 0){
				$results = [
					'title'        => 'Unable to place hold',
					'modalBody'    => '<p>Sorry, no copies of this title are available to your account.</p>',
					'modalButtons' => '',
				];
			}else{
				$results = [
					'title'        => empty($title) ? 'Place Hold' : 'Place Hold on ' . $title,
					'modalBody'    => $interface->fetch("Record/hold-popup.tpl"),
					'modalButtons' => "<input type='submit' name='submit' id='requestTitleButton' value='Submit Hold Request' class='btn btn-primary' onclick=\"return Pika.Record.submitHoldForm();\">",
				];
			}
			if (!empty($interface->getVariable('googleAnalyticsId'))){ // this template variable gets set in the bootstrap
				$results['modalButtons'] = "<input type='submit' name='submit' id='requestTitleButton' value='Submit Hold Request' class='btn btn-primary' onclick=\"trackHoldTitleClick('{$sourceAndId}'); return Pika.Record.submitHoldForm();\">";

			}

		}else{
			$results = [
				'title'        => 'Please log in',
				'modalBody'    => 'You must be logged in.  Please close this dialog and login before placing your hold.',
				'modalButtons' => '',
			];
		}
		return $results;
	}

	/**
	 * Return an array of cancellation setting notices for the library of the User.
	 *
	 * @param Library $patronLibrary The User's library
	 * @param string[] $autoCancels The array of notices to add to
	 * @return string[] The array of notices
	 */
	private function getAutoCancelHoldMessages($patronLibrary, array $autoCancels = []){
		if (!empty($patronLibrary->showHoldCancelDate)){
			global $interface;
			$interface->assign('showHoldCancelDate', true);
			// Turn on cancel date if any users' library has the setting on

			if ($patronLibrary->defaultNotNeededAfterDays > -1 && empty($autoCancels[$patronLibrary->displayName])){
				// -1 would mean no cancel date will be set; 0 will default to 6 months (182.5 days)
				$daysFromNow                              = $patronLibrary->defaultNotNeededAfterDays == 0 ? 182 : $patronLibrary->defaultNotNeededAfterDays;
				$cancelMessage                            = "If not set, for {$patronLibrary->displayName}, the cancel date will automatically be set to $daysFromNow days from today.";
				$autoCancels[$patronLibrary->displayName] = $cancelMessage;
			}
		}
		return $autoCancels;
	}

	/**
	 * Set template message for when a User has reached the maximum holds they can place with the ils
	 *
	 * @param User $user The User to check
	 */
	private function getMaxHoldsWarnings(User $user){
		require_once ROOT_DIR . '/sys/Account/PType.php';
		$pType        = new PType();
		if ($pType->get('pType', $user->patronType)){
			$maxHolds = $pType->maxHolds;
			if ($user->numHoldsIls >= $maxHolds){
				global $interface;
				$message = "{$user->getNameAndLibraryLabel()}, has reached the maximum of <span class='badge'>{$maxHolds}</span> holds for their account.  You will need to cancel a hold before you can place a hold on a title with this account.";
				$interface->append('maxHolds', $message);
			}
		}
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

			/** @var MarcRecord $marcRecord */
			$marcRecord = RecordDriverFactory::initRecordDriverById($id);
			$groupedWork = $marcRecord->getGroupedWorkDriver();
			$relatedManifestations = $groupedWork->getRelatedManifestations();
			$format                = $marcRecord->getFormat();
			$relatedManifestations = $relatedManifestations[$format[0]];
			$interface->assign('relatedManifestation', $relatedManifestations);
			$results = [
				'title'        => 'Place Hold on Alternate Edition?',
				'modalBody'    => $interface->fetch('Record/hold-select-edition-popup.tpl'),
				'modalButtons' => '<button class="btn btn-primary" onclick="return Pika.Record.showPlaceHold(\'Record\', \'' . $id . '\', false);">No, place a hold on this edition</button>',
			];
		}else{
			$results = [
				'title'        => 'Please log in',
				'modalBody'    => "You must be logged in.  Please close this dialog and login before placing your hold.",
				'modalButtons' => '',
			];
		}
		return $results;
		}

	function placeHold(){
		global $interface;
		$recordId = $_REQUEST['id'];
		if (strpos($recordId, ':') > 0){
			[$source, $shortId] = explode(':', $recordId, 2);
		}else{
			$shortId = $recordId;
		}

		$user = UserAccount::getLoggedInUser();
		if ($user){
			//The user is already logged in

			if (!empty($_REQUEST['campus'])){
				//Check to see what account we should be placing a hold for
				//Rather than asking the user for this explicitly, we do it based on the pickup location
				$campus               = $_REQUEST['campus'];
				$cancelDate           = empty($_REQUEST['canceldate']) ? null : trim($_REQUEST['canceldate']);
				$patron               = null;
				$doingStaffPlacedHold = !empty($_REQUEST['patronBarcode']);

				if (!$doingStaffPlacedHold){
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
				}
				if ($patron == null && !$doingStaffPlacedHold){
					$results = [
						'success' => false,
						'message' => 'You must select a valid user to place the hold for.',
						'title'   => 'Select valid user',
					];
				}
				else{
					if ($doingStaffPlacedHold){
						$patronBarcode = trim(strip_tags($_REQUEST['patronBarcode']));
						$return        = $user->staffPlacedHold($patronBarcode, $shortId, $campus, $cancelDate, $_REQUEST['selectedItem'] ?? null, $_REQUEST['volume'] ?? null);
					}elseif (isset($_REQUEST['selectedItem'])){
						$return = $patron->placeItemHold($shortId, $_REQUEST['selectedItem'], $campus, $cancelDate);
					}elseif (isset($_REQUEST['volume'])){
						$return = $patron->placeVolumeHold($shortId, $_REQUEST['volume'], $campus, $cancelDate);
					}else{
						$return = $patron->placeHold($shortId, $campus, $cancelDate);
						// If the hold requires an item-level hold, but there is only one item to choose from, just complete the hold with that one item
						if (!empty($return['items']) && count($return['items']) == 1){
							global $pikaLogger;
							$logger = $pikaLogger->withName(__CLASS__);
							$logger->notice("Automatically placing item-level hold on single holdable item for {$return['items'][0]['itemNumber']}");
							$return = $patron->placeItemHold($shortId, $return['items'][0]['itemNumber'], $campus, $cancelDate);
						}
					}

					if (!empty($return['items'])){ // only go to item-level hold prompt if there are holdable items to choose from
						$items = $return['items'];
						$interface->assign('campus', $campus);
						$interface->assign('canceldate', $cancelDate);
						$interface->assign('items', $items);
						$interface->assign('message', $return['message']);
						$interface->assign('id', $shortId);
						if (!empty($_REQUEST['autologout'])){
							$interface->assign('autologout', $_REQUEST['autologout']);
						} // carry user selection to Item Hold Form

						if ($doingStaffPlacedHold){
							$interface->assign('patronBarcode', $patronBarcode);
						}else{
							$homeLibrary = $patron->getHomeLibrary();
							$interface->assign('patronId', $patron->id);
							$interface->assign('showDetailedHoldNoticeInformation', $homeLibrary->showDetailedHoldNoticeInformation);
							$interface->assign('treatPrintNoticesAsPhoneNotices', $homeLibrary->treatPrintNoticesAsPhoneNotices);
						}
						// Need to place item level holds.
						$results = [
							'success'            => true,
							'needsItemLevelHold' => true,
							'message'            => $interface->fetch('Record/item-hold-popup.tpl'),
							'title'              => $return['title'] ?? '',
						];
					}else{                                                                                       // Completed Hold Attempt
						$interface->assign('message', $return['message']);
						$interface->assign('success', $return['success']);

						if (!$doingStaffPlacedHold){
							$homeLibrary          = $patron->getHomeLibrary();
							$canUpdateContactInfo = $homeLibrary->allowProfileUpdates == 1;
							// set update permission based on active library's settings. Or allow by default.
							$canChangeNoticePreference = $homeLibrary->showNoticeTypeInProfile == 1;
							// when user preference isn't set, they will be shown a link to account profile. this link isn't needed if the user can not change notification preference.
							$interface->assign('canUpdate', $canUpdateContactInfo && !isset($_REQUEST['autologout'])); //Don't allow updating if the auto log out has been set (because the user will be logged out instead).
							$interface->assign('canChangeNoticePreference', $canChangeNoticePreference);
							$interface->assign('showDetailedHoldNoticeInformation', $homeLibrary->showDetailedHoldNoticeInformation);
							$interface->assign('treatPrintNoticesAsPhoneNotices', $homeLibrary->treatPrintNoticesAsPhoneNotices);
							$interface->assign('profile', $patron); // Use the account the hold was placed with for the success message.
						}

						$results = [
							'success' => $return['success'],
							'message' => $interface->fetch('Record/hold-success-popup.tpl'),
							//'title'   => isset($return['title']) ? $return['title'] : '',
							'buttons' => '', // This removes the submit button
						];
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
						}else{
							if (!$doingStaffPlacedHold){
								// Only show View My Holds button if the user did not select the auto log out option.
								$results['buttons'] = '<a class="btn btn-primary" href="/MyAccount/Holds" role="button">View My Holds</a>';
							}
						}
					}
				}
			}else{
				$results = [
					'success' => false,
					'message' => 'No pick-up location is set.  Please choose a Location for the title to be picked up at.',
				];
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
			$results = [
				'success' => false,
				'message' => 'You must be logged in to place a hold.  Please close this dialog and login.',
				'title'   => 'Please log in',
			];
		}
		return $results;
	}

	function getBookMaterialForm($errorMessage = null){
		global $interface;
		if (UserAccount::isLoggedIn()){
			$id = $_REQUEST['id'];

			/** @var MarcRecord $marcRecord */
			$marcRecord = RecordDriverFactory::initRecordDriverById($id);
			$title      = $marcRecord->getTitle();
			$interface->assign('id', $id);
			if ($errorMessage){
				$interface->assign('errorMessage', $errorMessage);
			}
			$results = [
				'title'        => 'Schedule ' . $title,
				'modalBody'    => $interface->fetch("Record/book-materials-form.tpl"),
				'modalButtons' => '<button class="btn btn-primary" onclick="$(\'#bookMaterialForm\').submit()">Schedule Item</button>'
				// Clicking invokes submit event, which allows the validator to act before calling the ajax handler
			];
		}else{
			$results = [
				'title'        => 'Please log in',
				'modalBody'    => "You must be logged in.  Please close this dialog and login before scheduling this item.",
				'modalButtons' => ""
			];
		}
		return $results;
	}

	function getBookingCalendar(){
		if (!empty($_REQUEST['id'])){
			$sourceAndId = new SourceAndId(trim($_REQUEST['id']));
			$user        = UserAccount::getLoggedInUser();
			if (!empty($user)){ // The user should be logged in at this point
				$catalog = $user->getCatalogDriver();
				return $catalog->getBookingCalendar($user, $sourceAndId);
			}
		}
	}

	function getCheckInGrid(){
		if (!empty($_REQUEST['id'])){
			if (!empty($_REQUEST['checkInGridId'])){
				$driver      = CatalogFactory::getCatalogConnectionInstance();
				$checkInGrid = $driver->getCheckInGrid(strip_tags($_REQUEST['id']), strip_tags($_REQUEST['checkInGridId']));

				global $interface;
				$interface->assign('checkInGrid', $checkInGrid);
				return array(
					'title'        => 'Check-In Grid',
					'modalBody'    => $interface->fetch('Record/checkInGrid.tpl'),
					'modalButtons' => ""
				);
			}
		}
	}

	function bookMaterial(){
		$user = UserAccount::getLoggedInUser();
		if ($user){ // The user is already logged in
			if (!empty($_REQUEST['id'])){
				$recordId = new SourceAndId($_REQUEST['id']);
				if (!empty($recordId->getRecordId())){

					if (!empty($_REQUEST['startDate'])){
						$startDate = $_REQUEST['startDate'];

						$startTime = empty($_REQUEST['startTime']) ? null : $_REQUEST['startTime'];
						$endDate   = empty($_REQUEST['endDate']) ? null : $_REQUEST['endDate'];
						$endTime   = empty($_REQUEST['endTime']) ? null : $_REQUEST['endTime'];

						$result = $user->bookMaterial($recordId, $startDate, $startTime, $endDate, $endTime);
						if ($result['success']){
							$result['buttons'] = '<a class="btn btn-primary" href="/MyAccount/Bookings" role="button">View My Scheduled Items</a>';
						}
						return $result;
					}
					return ['success' => false, 'message' => 'Start Date is required.'];
				}
			}
			return ['success' => false, 'message' => 'Record ID is required.'];
		}
		return ['success' => false, 'message' => 'User not logged in.'];
	}

	function forceReExtract(){
		if (!empty($_REQUEST['id'])){
			require_once ROOT_DIR . '/services/SourceAndId.php';
			$recordId = new SourceAndId($_REQUEST['id']);
			if ($recordId->getSource() && $recordId->getRecordId() && $recordId->getIndexingProfile() != null){
				require_once ROOT_DIR . '/sys/Extracting/IlsExtractInfo.php';
				$extractInfo                    = new IlsExtractInfo();
				$extractInfo->indexingProfileId = $recordId->getIndexingProfile()->id;
				$extractInfo->ilsId             = $recordId->getRecordId();
				if ($extractInfo->find(true)){
					if ($extractInfo->markForReExtraction()){
						return ['success' => true, 'message' => 'Record was marked for re-extraction.'];
					}else{
						return ['success' => false, 'message' => 'Failed to mark record for re-extraction.'];
					}
				}else{
					// This is for cases where a record is present but has never been extracted before
//					$extractInfo->lastExtracted = null;
					if ($extractInfo->insert()){
						return ['success' => true, 'message' => 'Record was marked for re-extraction.'];
					}else{
						return ['success' => false, 'message' => 'Failed to mark record for re-extraction.'];
					}
				}
			}
		}
		return ['success' => false, 'message' => 'Invalid record Id.'];
	}

}
