<?php

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/14/2020
 *
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/services/Admin/Libraries.php';

class Admin_HooplaInfo extends Admin_Admin {


	function launch(){
		if (isset($_REQUEST['startDate'])){
			$startDate = new DateTime($_REQUEST['startDate']);
		}else{
			$startDate = new DateTime();
			date_sub($startDate, new DateInterval('P1M')); // 1 day ago
		}
		if (isset($_REQUEST['endDate'])){
			$endDate = new DateTime($_REQUEST['endDate']);
		}else{
			$endDate = new DateTime();
		}
		$endDate->setTime(23,59,59); //second before midnight

		require_once ROOT_DIR . '/Drivers/HooplaDriver.php';
		$driver      = new HooplaDriver();
		$startTime   = $startDate->getTimestamp();
		$endTime     = $endDate->getTimestamp();
		$libraries   = new Admin_Libraries();
		$libraryList = $libraries->getAllObjects(); // accounts for permissions
		$hooplaLibraries = [];
		/** @var Library[] $libraryList */
		foreach ($libraryList as $library){
			if (!empty($library->hooplaLibraryID)){
				$checkOutsResponse = $driver->getLibraryHooplaTotalCheckOuts($library->hooplaLibraryID, $startTime, $endTime);
				if (isset($checkOutsResponse->checkouts)){
					$hooplaLibraries[] = [
						'libraryName' => $library->displayName,
						'checkouts' => $checkOutsResponse->checkouts,
					];
//					echo $library->displayName . ' Total Checkouts : '. $checkOutsResponse->checkouts . "\n";
				}

			}
		}
//		echo '</pre>';

		global $interface;
		$interface->assign('startDate', $startDate->getTimestamp());
		$interface->assign('endDate', $endDate->getTimestamp());

		$interface->assign('hooplaLibraryCheckouts', $hooplaLibraries);
		$this->display('hooplaInfo.tpl', 'Hoopla API Info');

	}

	function getAllowableRoles(){
		return ['opacAdmin'/*, 'cataloging'*//*, 'libraryAdmin', 'libraryManager'*/];
	}
}