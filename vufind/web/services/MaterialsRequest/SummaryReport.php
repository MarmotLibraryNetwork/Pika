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
require_once ROOT_DIR . "/PHPExcel.php";

class MaterialsRequest_SummaryReport extends Admin_Admin {

	function launch(){
		global $interface;

		$period = $_REQUEST['period'] ?? 'week';
		if ($period == 'week'){
			$periodLength = new DateInterval("P1W");
		}elseif ($period == 'day'){
			$periodLength = new DateInterval("P1D");
		}elseif ($period == 'month'){
			$periodLength = new DateInterval("P1M");
		}else{ //year
			$periodLength = new DateInterval("P1Y");
		}
		$interface->assign('period', $period);

		$endDate = (!empty($_REQUEST['endDate'])) ? DateTime::createFromFormat('m/d/Y', $_REQUEST['endDate']) : new DateTime();
		$interface->assign('endDate', $endDate->format('m/d/Y'));

		if (!empty($_REQUEST['startDate'])){
			$startDate = DateTime::createFromFormat('m/d/Y', $_REQUEST['startDate']);
		}else{
			if ($period == 'day'){
				$startDate = new DateTime($endDate->format('m/d/Y') . " - 7 days");
			}elseif ($period == 'week'){
				//Get the sunday after this
				$endDate->setISODate($endDate->format('Y'), $endDate->format("W"), 0);
				$endDate->modify("+7 days");
				$startDate = new DateTime($endDate->format('m/d/Y') . " - 28 days");
			}elseif ($period == 'month'){
				$endDate->modify("+1 month");
				$numDays = $endDate->format("d");
				$endDate->modify(" -$numDays days");
				$startDate = new DateTime($endDate->format('m/d/Y') . " - 6 months");
			}else{ //year
				$endDate->modify("+1 year");
				$numDays = $endDate->format("m");
				$endDate->modify(" -$numDays months");
				$numDays = $endDate->format("d");
				$endDate->modify(" -$numDays days");
				$startDate = new DateTime($endDate->format('m/d/Y') . " - 2 years");
			}
		}

		$interface->assign('startDate', $startDate->format('m/d/Y'));

		//Set the end date to the end of the day
		$endDate->setTime(24, 0, 0);
		$startDate->setTime(0, 0, 0);

		//Create the periods that are being represented
		$periods   = [];
		$periodEnd = clone $endDate;
		while ($periodEnd >= $startDate){
			array_unshift($periods, clone $periodEnd);
			$periodEnd->sub($periodLength);
		}
		//print_r($periods);

		//Load data for each period
		//this will be a two-dimensional array
		//         Period 1, Period 2, Period 3
		//Status 1
		//Status 2
		//Status 3
		$periodData = [];

		$user                  = UserAccount::getLoggedInUser();
		$userHomeLibrary       = UserAccount::getUserHomeLibrary();
		$locationsForLibrary   = $userHomeLibrary->getLocationIdsForLibrary();
		$locationsToRestrictTo = implode(', ', $locationsForLibrary);

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
					$defaultStatuses[$materialsRequestStatus->id] = $materialsRequestStatus->description;
				}elseif ($materialsRequestStatus->isOpen == 1){
					$openStatuses[$materialsRequestStatus->id] = $materialsRequestStatus->description;
				}else{
					$defaultStatusesToShow[]                     = $materialsRequestStatus->id;
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


		$statuses = ['Created' => 'Created'];
		for ($i = 0;$i < count($periods) - 1;$i++){
			/** @var DateTime $periodStart */
			/** @var DateTime $periodEnd */
			$periodStart                  = clone $periods[$i];
			$periodEnd                    = clone $periods[$i + 1];
			$periodTimestamp              = $periodStart->getTimestamp();
			$periodData[$periodTimestamp] = [];

			//Determine how many requests were created
			$materialsRequest = new MaterialsRequest();
			$materialsRequest->joinAdd(new User(), 'INNER', 'user', 'createdBy');
			$materialsRequest->selectAdd();
			$materialsRequest->selectAdd('COUNT(materials_request.id) as numRequests');
			$materialsRequest->whereAdd('dateCreated >= ' . $periodTimestamp . ' AND dateCreated < ' . $periodEnd->getTimestamp());
			if ($locationsToRestrictTo != ''){
				$materialsRequest->whereAdd('user.homeLocationId IN (' . $locationsToRestrictTo . ')');
			}

			$periodData[$periodTimestamp]['Created'] = $materialsRequest->find(true) ? $materialsRequest->numRequests : 0;

			//Get a list of all requests by the status of the request
			$materialsRequest = new MaterialsRequest();
			$materialsRequest->joinAdd(new MaterialsRequestStatus());
			$materialsRequest->joinAdd(new User(), 'INNER', 'user', 'createdBy');
			$materialsRequest->selectAdd();
			$materialsRequest->selectAdd('COUNT(materials_request.id) AS numRequests,description');
			$materialsRequest->whereAdd('dateUpdated >= ' . $periodTimestamp . ' AND dateUpdated < ' . $periodEnd->getTimestamp());
			$materialsRequest->whereAdd('user.homeLocationId IN (' . implode(', ', $locationsForLibrary) . ')');
			if (!empty($statusesToShow) && count($availableStatuses) > count($statusesToShow)){
				$statusSql = $materialsRequest->buildListOfQuotedAndSQLEscapedItems($statusesToShow);
				$materialsRequest->whereAdd("status IN ($statusSql)");
			}

			$materialsRequest->groupBy('status');
			$materialsRequest->orderBy('status');
			$materialsRequest->find();
			while ($materialsRequest->fetch()){
				$periodData[$periodTimestamp][$materialsRequest->description] = $materialsRequest->numRequests;
				$statuses[$materialsRequest->description] = $materialsRequest->description;
			}
		}

		$interface->assign('periodData', $periodData);
		$interface->assign('statuses', $statuses);

		//Check to see if we are exporting to Excel
		if (isset($_REQUEST['exportToExcel'])){
			global $configArray;
			$libraryName = !empty($userHomeLibrary->displayName) ? $userHomeLibrary->displayName : $configArray['Site']['libraryName'];
			$this->exportToExcel($periodData, $statuses, $libraryName);
		}else{
			//Generate the graph
			$this->generateGraph($periodData, $statuses);
		}

		$this->display('summaryReport.tpl', 'Materials Request Summary Report');
	}

	function exportToExcel($periodData, $statuses, $creator){
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		// Set properties
		$objPHPExcel->getProperties()->setCreator($creator)
			->setLastModifiedBy($creator)
			->setTitle("Materials Request Summary Report")
			->setSubject("Materials Request")
			->setCategory("Materials Request Summary Report");

		// Add some data
		$objPHPExcel->setActiveSheetIndex(0);
		$activeSheet = $objPHPExcel->getActiveSheet();
		$activeSheet->setCellValue('A1', 'Materials Request Summary Report');
		$activeSheet->setCellValue('A3', 'Date');
		$column = 1;
		foreach ($statuses as $statusLabel){
			$activeSheet->setCellValueByColumnAndRow($column++, 3, $statusLabel);
		}

		$row    = 4;
		$column = 0;
		//Loop Through The Report Data
		foreach ($periodData as $date => $periodInfo){
			$activeSheet->setCellValueByColumnAndRow($column++, $row, date('M-d-Y', $date));
			foreach ($statuses as $status => $statusLabel){
				$activeSheet->setCellValueByColumnAndRow($column++, $row, $periodInfo[$status] ?? 0);
			}
			$row++;
			$column = 0;
		}
		for ($i = 0;$i < count($statuses) + 1;$i++){
			$activeSheet->getColumnDimensionByColumn($i)->setAutoSize(true);
		}

		// Rename sheet
		$activeSheet->setTitle('Summary Report');

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="MaterialsRequestSummaryReport.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');
		exit;

	}

	function generateGraph($periodData, $statuses){
		$reportData = new pData();

		//Add points for each status
		$periodsFormatted = [];
		foreach ($statuses as $status => $statusLabel){
			$statusData = [];
			foreach ($periodData as $date => $periodInfo){
				$periodsFormatted[$date] = date('M-d-y', $date);
				$statusData[$date]       = $periodInfo[$status] ?? 0;
			}
			$reportData->addPoints($statusData, $status);
		}

		$reportData->setAxisName(0, 'Requests');
		$reportData->addPoints($periodsFormatted, 'Dates');
		$reportData->setAbscissa('Dates');

		// Create the pChart object
		$imageWidth     = 880;
		$imageHeight    = 600;
		$legendWidth    = 150;
		$fontProperties = ['FontName' => ROOT_DIR . '/sys/pChart/Fonts/verdana.ttf', 'FontSize' => 9];
		$gridGray       = ['GridR' => 225, 'GridG' => 225, 'GridB' => 225];
		$myPicture      = new pImage($imageWidth, $imageWidth, $reportData);

		// Add a border to the picture
		$myPicture->drawRectangle(0, 0, $imageWidth-1, $imageHeight-1, ['R' => 0, 'G' => 0, 'B' => 0]);

		$myPicture->setFontProperties($fontProperties);
		$myPicture->setGraphArea(50, 20, $imageWidth-($legendWidth+10), $imageHeight-100);
		$myPicture->drawScale(array_merge(['DrawSubTicks' => true, 'LabelRotation' => 90], $gridGray));
		$myPicture->drawLineChart(['DisplayValues' => true, 'DisplayColor' => DISPLAY_AUTO]);

		// Write the chart legend
		$myPicture->drawLegend($imageWidth-$legendWidth, 20, ["Style" => LEGEND_NOBORDER]);

		// Render the picture (choose the best way)
		global $configArray;
		global $interface;
		$chartHref = '/images/charts/materialsRequestSummary' . time() . '.png';
		$chartPath = $configArray['Site']['local'] . $chartHref;
		$myPicture->render($chartPath);
		$interface->assign('chartPath', $chartHref);
	}

	function getAllowableRoles(){
		return ['library_material_requests'];
	}
}
