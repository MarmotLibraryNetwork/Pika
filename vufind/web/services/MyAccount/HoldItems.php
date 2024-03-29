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

//require_once ROOT_DIR . '/CatalogConnection.php';
//
//require_once ROOT_DIR . '/Action.php';
//
//class HoldItems extends Action
//{
//	var $catalog;
//
//	function launch()
//	{
//		global $configArray;
//
//		try {
//			$this->catalog = CatalogFactory::getCatalogConnectionInstance();
//		} catch (PDOException $e) {
//			// What should we do with this error?
//			if ($configArray['System']['debug']) {
//				echo '<pre>';
//				echo 'DEBUG: ' . $e->getMessage();
//				echo '</pre>';
//			}
//		}
//
//		// Check How to Process Hold
//		if (method_exists($this->catalog->driver, 'placeHold')) {
//			$this->placeHolds();
//		} else {
//			PEAR_Singleton::raiseError(new PEAR_Error('Cannot Process Place Hold - ILS Not Supported'));
//		}
//	}
//
//	function placeHolds()
//	{
//		$selectedTitles = $_REQUEST['title'];
//		global $interface;
//		global $configArray;
//		$user = UserAccount::getLoggedInUser();
//		global $pikaLogger;
//
//		$ids = array();
//		foreach ($selectedTitles as $recordId => $itemNumber){
//			$ids[] = $recordId;
//		}
//		$interface->assign('ids', $ids);
//
//		$hold_message_data = array(
//          'successful' => 'all',
//          'campus' => $_REQUEST['campus'],
//          'titles' => array()
//		);
//
//		$atLeast1Successful = false;
//		foreach ($selectedTitles as $recordId => $itemNumber){
//			$return = $this->catalog->placeItemHold($user, $recordId, $itemNumber, '', $_REQUEST['type']);
//			$hold_message_data['titles'][] = $return;
//			if (!$return['success']){
//				$hold_message_data['successful'] = 'partial';
//			}else{
//				$atLeast1Successful = true;
//			}
//			//Check to see if there are item level holds that need follow-up by the user
//			if (isset($return['items'])){
//				$hold_message_data['showItemForm'] = true;
//			}
//			$showMessage = true;
//		}
//		if (!$atLeast1Successful){
//			$hold_message_data['successful'] = 'none';
//		}
//
//		$class = $configArray['Index']['engine'];
//		$db = new $class($configArray['Index']['url']);
//
//		$_SESSION['hold_message'] = $hold_message_data;
//		if (isset($_SESSION['hold_referrer'])){
//			//Redirect for hold cancellation or update
//			header("Location: " . $_SESSION['hold_referrer']);
//			unset($_SESSION['hold_referrer']);
//			if (isset($_SESSION['autologout'])){
//				unset($_SESSION['autologout']);
//				$masqueradeMode = UserAccount::isUserMasquerading();
//				if ($masqueradeMode) {
//					require_once ROOT_DIR . '/services/MyAccount/Masquerade.php';
//					MyAccount_Masquerade::endMasquerade();
//				} else {
//					UserAccount::softLogout();
//				}
//			}
//		}else{
//			header("Location: " . '/MyResearch/Holds');
//		}
//	}
//}
