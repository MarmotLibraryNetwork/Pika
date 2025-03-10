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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

class MyAccount_Profile extends MyAccount {
	function launch(){
		global $configArray;
		global $interface;
		$user = UserAccount::getLoggedInUser();

		$ils = $configArray['Catalog']['ils'];
		if ($ils == 'Sierra'){
			// SMS Options are for the iii's ils-integration SMS service only
			$smsEnabled = $configArray['Catalog']['smsEnabled'];
			if ($smsEnabled){
				$interface->assign('showSMSNoticesInProfile', true);
				$smsTermsLink = $configArray['Catalog']['smsTermsLink'];
				if ($smsTermsLink){
					$interface->assign('smsTermsLink', $smsTermsLink);
				}
			}
		}

		// Polaris specific options
		if ($ils === 'Polaris'){
			$interface->assign('showLegalName', (bool)$configArray['Polaris']['showLegalName']);
			$interface->assign('showPhone2', (bool)$configArray['Polaris']['showPhone2']);
			$interface->assign('showPhone3', (bool)$configArray['Polaris']['showPhone3']);
			$phone_carriers = $configArray['Carriers'];
			$interface->assign('phoneCarriers', $phone_carriers);

			$driver = CatalogFactory::getCatalogConnectionInstance();
			$interface->assign('notificationOptions', $driver->getNotificationOptions());
			$interface->assign('eReceiptOptions', $driver->getErecieptionOptions());
			$interface->assign('emailFormatOptions', $driver->getEmailFormatOptions());
		}

		if ($user) {
			// Determine which user we are showing/updating settings for
			$linkedUsers = $user->getLinkedUsers();
			$patronId    = $_REQUEST['patronId'] ?? $user->id;
			/** @var User $patron */
			$patron = $user->getUserReferredTo($patronId);

			// Linked Accounts Selection Form set-up
			if (count($linkedUsers) > 0) {
				array_unshift($linkedUsers, $user); // Adds primary account to list for display in account selector
			}
			// these need to get to template even if linkedusers is empty array so we don't get a bunch of warnings.
			$interface->assign('linkedUsers', $linkedUsers);
			$interface->assign('selectedUser', $patronId);
			// Get Library Settings from the home library of the current user-account being displayed
			$patronHomeLibrary = $patron->getHomeLibrary();
			if ($patronHomeLibrary === null) {
				$canUpdateContactInfo                 = true;
				$canUpdateAddress                     = true;
				$showWorkPhoneInProfile               = false;
				$showNoticeTypeInProfile              = true;
				$showPickupLocationInProfile          = false;
				$treatPrintNoticesAsPhoneNotices      = false;
				$allowPinReset                        = false;
				$showAlternateLibraryOptionsInProfile = true;
				$allowAccountLinking                  = true;
				$showPatronBarcodeImage               = false;
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
				$showPatronBarcodeImage               = ($patronHomeLibrary->showPatronBarcodeImage == 1);
				if (($user->finesVal > $patronHomeLibrary->maxFinesToAllowAccountUpdates) && ($patronHomeLibrary->maxFinesToAllowAccountUpdates > 0)) {
					$canUpdateContactInfo = false;
					$canUpdateAddress     = false;
				}
			}

			if ($allowPinReset) {
				$numericOnlyPins      = $configArray['Catalog']['numericOnlyPins'];
				$alphaNumericOnlyPins = $configArray['Catalog']['alphaNumericOnlyPins'];
				$pinMinimumLength     = $configArray['Catalog']['pinMinimumLength'];
				$pinMaximumLength     = $configArray['Catalog']['pinMaximumLength'];
				$sierraTrivialPin     = !empty($configArray['Catalog']['sierraTrivialPin']) && ($configArray['Catalog']['sierraTrivialPin'] == 1 || $configArray['Catalog']['sierraTrivialPin'] == "true");
				$interface->assign('numericOnlyPins', $numericOnlyPins);
				$interface->assign('alphaNumericOnlyPins', $alphaNumericOnlyPins);
				$interface->assign('pinMinimumLength', $pinMinimumLength);
				$interface->assign('pinMaximumLength', $pinMaximumLength);
				if ($sierraTrivialPin) {
					$interface->assign('sierraTrivialPin', true);
				}
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
			$interface->assign('showPatronBarcodeImage', $showPatronBarcodeImage);
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
					session_start();
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

				session_write_close();
				$actionUrl = $configArray['Site']['path'] . '/MyAccount/Profile' . ( $patronId == $user->id ? '' : '?patronId='.$patronId ); // redirect after form submit completion
				header("Location: " . $actionUrl);
				exit();
			}

			$cache             = new Pika\Cache();
			$cacheKey          = $cache->makePatronKey('overdrive_settings', $patronId);
			$overDriveSettings = $cache->get($cacheKey);
			if (empty($overDriveSettings)){
				$overDriveDriver   = Pika\PatronDrivers\EcontentSystem\OverDriveDriverFactory::getDriver();
				$overDriveSettings = $overDriveDriver->getUserOverDriveAccountSettings($patron);
			}
			if (!empty($overDriveSettings)){
				$notice         = translate('overdrive_account_preferences_notice');
				$replacementUrl = $overDriveSettings['overDriveWebsite'] ?? '#';
				$notice         = str_replace('{OVERDRIVEURL}', $replacementUrl, $notice);// Insert the Overdrive URL into the notice
				$interface->assign('overDrivePreferencesNotice', $notice);
				$interface->assign('overDriveSettings', $overDriveSettings);
			}

			if (!empty($_SESSION['profileUpdateErrors'])) {
				$interface->assign('profileUpdateErrors', $_SESSION['profileUpdateErrors']);
				unset($_SESSION['profileUpdateErrors']);
			}

			if ($showAlternateLibraryOptionsInProfile) {
				//Get the list of locations for display in the user interface.

				$locationList      = [];
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
			$interface->assign('barcodePin', $patron->getAccountProfile()->usingPins());
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
		unset($_SESSION['profileUpdateErrors']); // Remove error messages after displayed so that they aren't displayed on subsequent page loads
	}

}
