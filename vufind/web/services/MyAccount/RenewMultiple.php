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
require_once ROOT_DIR . '/CatalogConnection.php';

require_once ROOT_DIR . '/Action.php';

class RenewMultiple extends Action
{
	/** @var CatalogConnection */
	private $catalog;
	function launch()
	{

		global $configArray;

		try {
			$this->catalog = CatalogFactory::getCatalogConnectionInstance();;
		} catch (PDOException $e) {
			// What should we do with this error?
			if ($configArray['System']['debug']) {
				echo '<pre>';
				echo 'DEBUG: ' . $e->getMessage();
				echo '</pre>';
			}
		}

		//Renew the hold
		if (method_exists($this->catalog->driver, 'renewItem')) {
			$selectedItems = $_GET['selected'];
			$renewMessages = array();
			$_SESSION['renew_message']['Unrenewed'] = 0;
			$_SESSION['renew_message']['Renewed'] = 0;
			$i = 0;
			foreach ($selectedItems as $itemInfo => $selectedState){
				if ($i != 0){
					usleep(1000);
				}
				$i++;
				list($itemId, $itemIndex) = explode('|', $itemInfo);
				$renewResult = $this->catalog->driver->renewItem($itemId, $itemIndex);
				$_SESSION['renew_message'][$renewResult['itemId']] = $renewResult;
				$_SESSION['renew_message']['Total']++;
				if ($renewResult['success']){
					$_SESSION['renew_message']['Renewed']++;
				}else{
					$_SESSION['renew_message']['Unrenewed']++;
				}
			}
		} else {
			PEAR_Singleton::raiseError(new PEAR_Error('Cannot Renew Item - ILS Not Supported'));
		}

		//Redirect back to the hold screen with status from the renewal
		header("Location: " . $configArray['Site']['path'] . '/MyAccount/CheckedOut');
	}

}
