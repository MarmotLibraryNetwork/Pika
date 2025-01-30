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
 * EmailResetPin
 *
 * Allow patrons to request an email with a pin reset link.
 *
 * Calls emailResetPin() in the connecting patron controller.
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 8/16/2016
 *
 */

require_once ROOT_DIR . "/Action.php";
require_once ROOT_DIR . '/CatalogConnection.php';

class EmailResetPin extends Action {

	function __construct(){
//		$catalogConnection = CatalogFactory::getCatalogConnectionInstance(); // This will use the $activeRecordIndexingProfile to get the catalog connector
//		if (!empty($catalogConnection->accountProfile->driver)){
//			if (strpos($catalogConnection->accountProfile->driver, "\Clearview") !== false){
//				$classicOpacBaseURL = rtrim($catalogConnection->accountProfile->vendorOpacUrl, '/');
//				header("Location: $classicOpacBaseURL/logon.aspx?forgotPassword=1&ctx=1.1033.0.0.6");
//				die;
//			}
//		}

	}

	function launch($msg = null){
		global $interface;
		global $user;
        global $offlineMode;
        
		if (!empty($user) && $user->pinUpdateRequired){
			// Because we are forcing a Pin update we can not display the convince buttons at the top of page and sidebars
			$interface->assign('isUpdatePinPage', true);
			$interface->assign('displaySidebarMenu', false);
			$sidebarTemplate = '';
		} else {
			$sidebarTemplate = 'Search/home-sidebar.tpl';
		}

        if($offlineMode) {
            $offlineMessage = [
                'error' => 'The circulation system is currently offline. Please try again later.',
            ];
            $interface->assign('emailResult', $offlineMessage);
            $this->display('emailResetPinResults.tpl', translate('Email to Reset Pin'), $sidebarTemplate);
            return false;
        }
        
		if (isset($_REQUEST['submit'])){
			$catalog = CatalogFactory::getCatalogConnectionInstance(null, null);
			$driver  = $catalog->driver;
			if (method_exists($driver, 'emailResetPin')){
				$barcode     = strip_tags($_REQUEST['barcode']);
				$emailResult = $driver->emailResetPin($barcode);
			}else{
				$emailResult = [
					'error' => 'This functionality is not available in the circulation system.',
				];
			}
			$interface->assign('emailResult', $emailResult);
			$this->display('emailResetPinResults.tpl', translate('Email to Reset Pin'), $sidebarTemplate);
		}else{

			/** @var Library $library */
			global $library;
			$catalog              = CatalogFactory::getCatalogConnectionInstance();
			if ($catalog->accountProfile->usingPins()){
				$barcodeLabel = empty($library->loginFormUsernameLabel) ? 'Library Card Number' : $library->loginFormUsernameLabel;
			}else {
				//Note: Name/Barcode schema shouldn't really need to support password reset
				$barcodeLabel = empty($library->loginFormPasswordLabel) ? 'Library Card Number' : $library->loginFormPasswordLabel;
			}
			$interface->assign('barcodeLabel', $barcodeLabel);

			$this->display('emailResetPin.tpl', translate('Email to Reset Pin'), $sidebarTemplate);
		}
	}
}
