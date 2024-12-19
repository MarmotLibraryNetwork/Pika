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

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequest.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestStatus.php';
require_once ROOT_DIR . "/sys/pChart/class/pData.class.php";
require_once ROOT_DIR . "/sys/pChart/class/pDraw.class.php";
require_once ROOT_DIR . "/sys/pChart/class/pImage.class.php";

class MaterialsRequest_UserReport extends Admin_Admin {

	function launch(){
		global $configArray;
		global $interface;
		$user        = UserAccount::getLoggedInUser();
		$homeLibrary = UserAccount::getUserHomeLibrary();

		//Load status information
		$availableStatuses                 = [];
		$defaultStatusesToShow             = [];
		$defaultStatuses                   = [];
		$openStatuses                      = [];
		$closedStatuses                    = [];
		$materialsRequestStatus            = new MaterialsRequestStatus();
		$user                              = UserAccount::getLoggedInUser();
		$homeLibrary                       = $user->getHomeLibrary();
		$materialsRequestStatus->libraryId = $homeLibrary->libraryId;
		$materialsRequestStatus->orderBy('isDefault DESC, isOpen DESC, description ASC');
		if ($materialsRequestStatus->find()){
			while ($materialsRequestStatus->fetch()){
				$availableStatuses[$materialsRequestStatus->id] = $materialsRequestStatus->description;
				if ($materialsRequestStatus->isDefault == 1){
					$defaultStatusesToShow[]                      = $materialsRequestStatus->id;
					$defaultStatuses[$materialsRequestStatus->id] = $materialsRequestStatus->description;
				}elseif ($materialsRequestStatus->isOpen == 1){
					$openStatuses[$materialsRequestStatus->id] = $materialsRequestStatus->description;
					$defaultStatusesToShow[]                   = $materialsRequestStatus->id;
				}else{
					$closedStatuses[$materialsRequestStatus->id] = $materialsRequestStatus->description;
				}
			}
			$interface->assign([
				'availableStatuses' => $availableStatuses,
				'defaultStatuses'   => $defaultStatuses,
				'openStatuses'      => $openStatuses,
				'closedStatuses'    => $closedStatuses,
			]);
		}else{
			$interface->assign('error', 'No Materials Requests statuses found.');
		}

		if (isset($_REQUEST['statusFilter'])){
			$statusesToShow = $_REQUEST['statusFilter'];
		}else{
			$statusesToShow = $defaultStatusesToShow;
		}
		$interface->assign('statusFilter', $statusesToShow);

		//Get a list of users that have requests open
		$materialsRequest = new MaterialsRequest();
		$materialsRequest->joinAdd(['createdBy', new User(), 'id']);
		$materialsRequest->joinAdd(new MaterialsRequestStatus());
		$materialsRequest->selectAdd();
		$materialsRequest->selectAdd('COUNT(materials_request.id) as numRequests');
		$materialsRequest->selectAdd('user.id as userId, status, description, user.firstName, user.lastName, user.barcode');
		$locationsForLibrary = $homeLibrary->getLocationIdsForLibrary();
		$materialsRequest->whereAdd('user.homeLocationId IN (' . implode(', ', $locationsForLibrary) . ')');

		if (!empty($statusesToShow) && count($availableStatuses) > count($statusesToShow)){
			$statusSql = $materialsRequest->buildListOfQuotedAndSQLEscapedItems($statusesToShow);
			$materialsRequest->whereAdd("status in ($statusSql)");
		}
		if (isset($_REQUEST['startDate'])){
			$startDateString = strip_tags($_REQUEST['startDate']);
			$startDate       = strtotime($startDateString);
			if (!empty($startDate)){
				$materialsRequest->whereAdd("dateCreated >= $startDate");
				$interface->assign('startDate', $startDateString);
			}
		}

		if (isset($_REQUEST['endDate'])){
			$endDateString = strip_tags($_REQUEST['endDate']);
			$endDate       = strtotime($endDateString);
			if (!empty($endDate)){
				$materialsRequest->whereAdd("dateCreated <= $endDate");
				$interface->assign('endDate', $endDateString);
			}
		}

		$materialsRequest->groupBy('userId, status');
		if ($materialsRequest->find()){

			$userData = [];
			while ($materialsRequest->fetch()){
				if (!array_key_exists($materialsRequest->userId, $userData)){
					$userData[$materialsRequest->userId]                     = [];
					$userData[$materialsRequest->userId]['firstName']        = $materialsRequest->firstName;
					$userData[$materialsRequest->userId]['lastName']         = $materialsRequest->lastName;
					$userData[$materialsRequest->userId]['barcode']          = $materialsRequest->barcode;
					$userData[$materialsRequest->userId]['totalRequests']    = 0;
					$userData[$materialsRequest->userId]['requestsByStatus'] = [];
				}
				$userData[$materialsRequest->userId]['requestsByStatus'][$materialsRequest->description] = $materialsRequest->numRequests;
				$userData[$materialsRequest->userId]['totalRequests']                                    += $materialsRequest->numRequests;
			}
			$interface->assign('userData', $userData);

			//Get a list of all the statuses that will be shown
			$statuses = [];
			foreach ($userData as $userInfo){
				foreach ($userInfo['requestsByStatus'] as $status => $numRequests){
					$statuses[$status] = $status;
				}
			}
			$interface->assign('statuses', $statuses);

			//Check to see if we are exporting to Excel
			if (isset($_REQUEST['exportToExcel'])){
				$libraryName = !empty($userHomeLibrary->displayName) ? $userHomeLibrary->displayName : $configArray['Site']['libraryName'];
				$this->exportToExcel($userData, $statuses, $libraryName);
			}
		}else{
			$interface->assign('error', 'No Requests found matching the filters.');
		}

		$this->display('userReport.tpl', 'Materials Request User Report');
	}

	function exportToExcel($userData, $statuses, $creator){
		//PHPEXCEL
		// Create new PHPExcel object
		$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

		// Set properties
		$objPHPExcel->getProperties()->setCreator($creator)
			->setLastModifiedBy($creator)
			->setTitle("Office 2007 XLSX Document")
			->setSubject("Office 2007 XLSX Document")
			->setDescription("Office 2007 XLSX, generated using PHP.")
			->setKeywords("office 2007 openxml php")
			->setCategory("Materials Request User Report");

		// Add some data
		$objPHPExcel->setActiveSheetIndex(0);
		$activeSheet = $objPHPExcel->getActiveSheet();
		$activeSheet->setCellValue('A1', 'Materials Request User Report');
		$activeSheet->setCellValue('A3', 'Last Name');
		$activeSheet->setCellValue('B3', 'First Name');
		$activeSheet->setCellValue('C3', 'Barcode');
		$column = 3;
		foreach ($statuses as $statusLabel){
			$activeSheet->setCellValue([$column++, 3], $statusLabel);
		}
		$activeSheet->setCellValue([$column, 3], 'Total');

		$row    = 4;
		$column = 0;
		//Loop Through The Report Data
		foreach ($userData as $userInfo){
			$activeSheet->setCellValue([$column++, $row], $userInfo['lastName']);
			$activeSheet->setCellValue([$column++, $row], $userInfo['firstName']);
			$activeSheet->setCellValue([$column++, $row], $userInfo['barcode']);
			foreach ($statuses as $status => $statusLabel){
				$activeSheet->setCellValue([$column++, $row], $userInfo['requestsByStatus'][$status] ?? 0);
			}
			$activeSheet->setCellValue([$column, $row], $userInfo['totalRequests']);
			$row++;
			$column = 0;
		}
		for ($i = 0;$i < count($statuses) + 3;$i++){
			$activeSheet->getColumnDimensionByColumn($i)->setAutoSize(true);
		}

		// Rename sheet
		$activeSheet->setTitle('User Report');

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="MaterialsRequestUserReport.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPHPExcel, 'Xls');
		$objWriter->save('php://output');
		exit;

	}

	function getAllowableRoles(){
		return ['library_material_requests'];
	}
}
