<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class MyAccount_Profile extends MyAccount
{
	function launch()
	{
		global $configArray;
		global $interface;
		$user = UserAccount::getLoggedInUser();

		$ils = $configArray['Catalog']['ils'];
		if ($ils == 'Sierra') {
			// SMS Options are for the iii's ils-integration SMS service only
			$smsEnabled = $configArray['Catalog']['smsEnabled'];
			if ($smsEnabled) {
				$interface->assign('showSMSNoticesInProfile', true);
				$smsTermsLink = $configArray['Catalog']['smsTermsLink'];
				if ($smsTermsLink) {
					$interface->assign('smsTermsLink', $smsTermsLink);
				}
			}
		}

		if ($user) {

			// Determine which user we are showing/updating settings for
			$linkedUsers = $user->getLinkedUsers();
			$patronId    = isset($_REQUEST['patronId']) ? $_REQUEST['patronId'] : $user->id;
			/** @var User $patron */
			$patron = $user->getUserReferredTo($patronId);

			// Linked Accounts Selection Form set-up
			if (count($linkedUsers) > 0) {
				array_unshift($linkedUsers, $user); // Adds primary account to list for display in account selector
			}
			// these need to get to template even if linkedusers is empty array so we don't get a bunch of warnings.
			$interface->assign('linkedUsers', $linkedUsers);
			$interface->assign('selectedUser', $patronId);
			/** @var Library $librarySingleton */
			global $librarySingleton;
			// Get Library Settings from the home library of the current user-account being displayed
			$patronHomeLibrary = $patron->getHomeLibrary();
			if ($patronHomeLibrary == null) {
				$canUpdateContactInfo                 = true;
				$canUpdateAddress                     = true;
				$showWorkPhoneInProfile               = false;
				$showNoticeTypeInProfile              = true;
				$showPickupLocationInProfile          = false;
				$treatPrintNoticesAsPhoneNotices      = false;
				$allowPinReset                        = false;
				$showAlternateLibraryOptionsInProfile = true;
				$allowAccountLinking                  = true;
			} else {
				$canUpdateContactInfo                 = ($patronHomeLibrary->allowProfileUpdates == 1);
				$canUpdateAddress                     = ($patronHomeLibrary->allowPatronAddressUpdates == 1);
				$showWorkPhoneInProfile               = ($patronHomeLibrary->showWorkPhoneInProfile == 1);
				$showNoticeTypeInProfile              = ($patronHomeLibrary->showNoticeTypeInProfile == 1);
				$treatPrintNoticesAsPhoneNotices      = ($patronHomeLibrary->treatPrintNoticesAsPhoneNotices == 1);
				$showPickupLocationInProfile          = ($patronHomeLibrary->showPickupLocationInProfile == 1);
				$allowPinReset                        = ($patronHomeLibrary->allowPinReset == 1);
				$showAlternateLibraryOptionsInProfile = ($patronHomeLibrary->showAlternateLibraryOptionsInProfile == 1);
				$allowAccountLinking                  = ($patronHomeLibrary->allowLinkedAccounts == 1);
				if (($user->finesVal > $patronHomeLibrary->maxFinesToAllowAccountUpdates) && ($patronHomeLibrary->maxFinesToAllowAccountUpdates > 0)) {
					$canUpdateContactInfo = false;
					$canUpdateAddress     = false;
				}
			}

			if ($allowPinReset) {
				$numericOnlyPins      = $configArray['Catalog']['numericOnlyPins'];
				$alphaNumericOnlyPins = $configArray['Catalog']['alphaNumericOnlyPins'];
				$pinMinimumLength     = $configArray['Catalog']['pinMinimumLength'];
				$interface->assign('numericOnlyPins', $numericOnlyPins);
				$interface->assign('alphaNumericOnlyPins', $alphaNumericOnlyPins);
				$interface->assign('pinMinimumLength', $pinMinimumLength);
			}

			$interface->assign('showUsernameField', $patron->getShowUsernameField());
			$interface->assign('canUpdateContactInfo', $canUpdateContactInfo);
			$interface->assign('canUpdateContactInfo', $canUpdateContactInfo);
			$interface->assign('canUpdateAddress', $canUpdateAddress);
			$interface->assign('showWorkPhoneInProfile', $showWorkPhoneInProfile);
			$interface->assign('showPickupLocationInProfile', $showPickupLocationInProfile);
			$interface->assign('showNoticeTypeInProfile', $showNoticeTypeInProfile);
			$interface->assign('treatPrintNoticesAsPhoneNotices', $treatPrintNoticesAsPhoneNotices);
			$interface->assign('allowPinReset', $allowPinReset);
			$interface->assign('showAlternateLibraryOptions', $showAlternateLibraryOptionsInProfile);
			$interface->assign('allowAccountLinking', $allowAccountLinking);

			// Determine Pickup Locations
			$pickupLocations = $patron->getValidPickupBranches($patron->getAccountProfile()->recordSource, false);
			$interface->assign('pickupLocations', $pickupLocations);

			// Save/Update Actions
			global $offlineMode;
			// TODO: This should be dynamic
			// if(method_exits($patron, $updateScope)) {
			//    $patron->$updateScope
			// }
			if (isset($_POST['updateScope']) && !$offlineMode) {
				$updateScope = $_REQUEST['updateScope'];
				if ($updateScope == 'contact') {
					$errors = $patron->updatePatronInfo($canUpdateContactInfo);
					// session start is generating this warning:
					// PHP Notice: session_start(): A session had already been started - ignoring
					// since session is already started no need to do so here.
					// session_start(); // any writes to the session storage also closes session. Happens in updatePatronInfo (for Horizon). plb 4-21-2015
					$_SESSION['profileUpdateErrors'] = $errors;

				}  elseif ($updateScope == 'userPreference') {
					$patron->updateUserPreferences();
				}  elseif ($updateScope == 'staffSettings') {

					$patron->updateUserPreferences(); // update bypass autolog out option

					if (isset($_REQUEST['materialsRequestEmailSignature'])) {
						$patron->setMaterialsRequestEmailSignature($_REQUEST['materialsRequestEmailSignature']);
					}
					if (isset($_REQUEST['materialsRequestReplyToAddress'])) {
						$patron->setMaterialsRequestReplyToAddress($_REQUEST['materialsRequestReplyToAddress']);
					}
						$patron->setStaffSettings();
				} elseif ($updateScope == 'overdrive') {
					$patron->updateOverDriveOptions();
				} elseif ($updateScope == 'hoopla') {
					$patron->updateHooplaOptions();
				} elseif ($updateScope == 'pin') {
					$errors = $patron->updatePin();
					session_start();
					$_SESSION['profileUpdateErrors'] = $errors;
				}

				$res = session_write_close();
				$actionUrl = $configArray['Site']['path'] . '/MyAccount/Profile' . ( $patronId == $user->id ? '' : '?patronId='.$patronId ); // redirect after form submit completion
				header("Location: " . $actionUrl);
				exit();
			} elseif (!$offlineMode) {
				$interface->assign('edit', true);
			} else {
				$interface->assign('edit', false);
			}

//			$interface->assign('overDriveUrl', $configArray['OverDrive']['url']);
			/** @var I18N_Translator $translator */
			global $translator;
			$notice         = $translator->translate('overdrive_account_preferences_notice');
			$replacementUrl = empty($configArray['OverDrive']['url']) ? '#' : $configArray['OverDrive']['url'];
			$notice         = str_replace('{OVERDRIVEURL}', $replacementUrl, $notice); // Insert the Overdrive URL into the notice
			$interface->assign('overdrivePreferencesNotice', $notice);


			if (!empty($_SESSION['profileUpdateErrors'])) {
				$interface->assign('profileUpdateErrors', $_SESSION['profileUpdateErrors']);
				unset($_SESSION['profileUpdateErrors']);
			}

			if ($showAlternateLibraryOptionsInProfile) {
				//Get the list of locations for display in the user interface.

				$locationList = array();
				$locationList['0'] = "No Alternate Location Selected";
				foreach ($pickupLocations as $pickupLocation){
					if (!is_string($pickupLocation)){
						$locationList[$pickupLocation->locationId] = $pickupLocation->displayName;
					}
				}
				$interface->assign('locationList', $locationList);
			}

			$userIsStaff = $patron->isStaff();
			$interface->assign('userIsStaff', $userIsStaff);

			$interface->assign('profile', $patron); //
			$interface->assign('barcodePin', $patron->getAccountProfile()->loginConfiguration == 'barcode_pin');
				// Switch for displaying the barcode in the account profile
		}

		// switch for hack for Millennium driver profile updating when updating is allowed but address updating is not allowed.
		//TODO: restrict to Sierra only? Is this needed for sierra any longer
		$millenniumNoAddress = $canUpdateContactInfo && !$canUpdateAddress && $ils == 'Sierra';
		$interface->assign('millenniumNoAddress', $millenniumNoAddress);


		// CarlX Specific Options
		if ($ils == 'CarlX' && !$offlineMode) {
			// Get Phone Types
			$phoneTypes = array();
			/** @var CarlX $driver */
			$driver        = CatalogFactory::getCatalogConnectionInstance();
			$rawPhoneTypes = $driver->getPhoneTypeList();
			foreach ($rawPhoneTypes as $rawPhoneTypeSubArray){
				foreach ($rawPhoneTypeSubArray as $phoneType => $phoneTypeLabel) {
					$phoneTypes["$phoneType"] = $phoneTypeLabel;
				}
			}
			$interface->assign('phoneTypes', $phoneTypes);
		}

		$this->display('profile.tpl', 'Account Settings');
	}

}
