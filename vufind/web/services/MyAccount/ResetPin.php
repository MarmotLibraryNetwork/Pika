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

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 8/16/2016
 *
 */

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/CatalogConnection.php';

class ResetPin extends Action{
	protected $catalog;

	function __construct()
	{
	}

	function launch($msg = null){
		global $interface;

		if (!empty($_REQUEST['resetToken'])) {
			$interface->assign('resetToken', $_REQUEST['resetToken']);
		}
		if (!empty($_REQUEST['uid'])) {
			$interface->assign('userID', $_REQUEST['uid']);
		}
        if (!empty($_REQUEST['bc'])) {
            $interface->assign('bc', $_REQUEST['bc']);
        }

		global $configArray;
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

		if (isset($_REQUEST['submit'])){
			$this->catalog = CatalogFactory::getCatalogConnectionInstance();
			$driver = $this->catalog->driver;
			if (method_exists($driver,'resetPin')) {
				$newPin        = trim($_REQUEST['pin1']);
				$confirmNewPin = trim($_REQUEST['pin2']);
				$resetToken    = $_REQUEST['resetToken'];
				$userID        = $_REQUEST['uid'];
				$bc            = $_REQUEST['bc'] ?? null; //TODO: php variable $bc not being used?
				$newPinLength  = strlen($newPin);

				if (!empty($userID)){

					$patron = new User;
					$patron->get($userID);

					// For Polaris, we don't need to have a user in Pika to update the PIN.
					if (empty($patron->id) && $driver !== "Polaris"){
						// Did not find a matching user to the uid
						// This check could be optional if the resetPin method verifies that the ILS user matches the Pika user.
						$resetPinResult = [
							'error' => 'Invalid parameter. Your ' . translate('pin') . ' can not be reset.'
						];
					}elseif (empty($newPin)){
						$resetPinResult = [
							'error' => 'Please enter a new ' . translate('pin') . '.'
						];
					}elseif (empty($confirmNewPin)){
						$resetPinResult = [
							'error' => 'Please confirm your new ' . translate('pin') . '.'
						];
					}elseif ($newPin !== $confirmNewPin){
						$resetPinResult = [
							'error' => 'The new ' . translate('pin') . 's do not match. Please try again.'
						];
					}elseif (empty($resetToken) || empty($userID)){
						// These checks are for Horizon Driver, this may need to be moved into resetPin function if used for another ILS
						$resetPinResult = [
							'error' => 'Required parameter missing. Your ' . translate('pin') . ' can not be reset.'
						];
					}elseif ($newPinLength < $pinMinimumLength or $newPinLength > $pinMaximumLength){
						if ($pinMinimumLength == $pinMaximumLength){
							$resetPinResult = ['error' => 'New ' . translate('pin') . ' must be exactly ' . $pinMinimumLength . ' characters.'];
						}else{
							$resetPinResult = ['error' => 'New ' . translate('pin') . ' must be ' . $pinMinimumLength . " to " . $pinMaximumLength . ' characters.'];
						}
					}else{
						$resetPinResult = $driver->resetPin($patron, $newPin, $resetToken);
					}
				}
			}else{
				$resetPinResult = [
					'error' => 'This functionality is not available in the circulation system.',
				];
			}
			$interface->assign('resetPinResult', $resetPinResult);
		}
		$this->display('resetPin.tpl', translate('Reset My PIN'));
	}
}
