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

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequest.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestStatus.php';

class MaterialsRequest_ManageRequests extends Admin_Admin {

	/**
	 *
	 */
	function launch(){
		global $interface;

		//Load status information
		$errors                            = [];
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
			$errors[] = 'No Materials Requests statuses found.';
		}

		$updatingFilters = !empty($_REQUEST['submit']) && $_REQUEST['submit'] == "Update Filters";

		if (isset($_REQUEST['statusFilter']) || $updatingFilters){
			$statusesToShow                           = $_REQUEST['statusFilter'] ?? [];
			$_SESSION['materialsRequestStatusFilter'] = $statusesToShow;
		}elseif (isset($_SESSION['materialsRequestStatusFilter'])){
			$statusesToShow = $_SESSION['materialsRequestStatusFilter'];
		}else{
			$statusesToShow = $defaultStatusesToShow;
		}
		$interface->assign('statusFilter', $statusesToShow);

		$assigneesToShow = [];
		if (isset($_REQUEST['assigneesFilter']) || $updatingFilters){
			$assigneesToShow                             = $_REQUEST['assigneesFilter'] ?? [];
			$_SESSION['materialsRequestAssigneesFilter'] = $assigneesToShow;
		}elseif (!empty($_SESSION['materialsRequestAssigneesFilter'])){
			$assigneesToShow = $_SESSION['materialsRequestAssigneesFilter'];
		}
		$interface->assign('assigneesFilter', $assigneesToShow);
		if ($updatingFilters){
			$showUnassigned                             = !empty($_REQUEST['showUnassigned']) && $_REQUEST['showUnassigned'] == 'on';
			$_SESSION['materialsRequestShowUnassigned'] = $showUnassigned;
		}else{
			$showUnassigned = !empty($_SESSION['materialsRequestShowUnassigned']);
		}
		$interface->assign('showUnassigned', $showUnassigned);

		//Process status change if needed
		if (isset($_REQUEST['newStatus']) && isset($_REQUEST['select']) && $_REQUEST['newStatus'] != 'unselected'){
			//Look for which titles should be modified
			$selectedRequests = $_REQUEST['select'];
			$statusToSet      = $_REQUEST['newStatus'];
			foreach ($selectedRequests as $requestId => $selected){
				$materialRequest     = new MaterialsRequest();
				$materialRequest->id = $requestId;
				if ($materialRequest->find(true)){
					if (empty($materialRequest->assignedTo)){
						$materialRequest->assignedTo = $user->id;
						//updateRequestStatus() below will save this to the db for us automatically
					}
					$error = $materialRequest->updateRequestStatus($statusToSet);
					if (is_string($error)){
						$errors[] = $error;
					}
				}
			}
		}

		// Assign Requests
		if (isset($_REQUEST['newAssignee']) && isset($_REQUEST['select']) && $_REQUEST['newAssignee'] != 'unselected'){
			//Look for which material requests should be modified
			$selectedRequests = $_REQUEST['select'];
			$assignee         = $_REQUEST['newAssignee'];
			if (ctype_digit($assignee) || $assignee == 'unassign'){
				foreach ($selectedRequests as $requestId => $selected){
					$materialRequest     = new MaterialsRequest();
					$materialRequest->id = $requestId;
					if ($materialRequest->find(true)){
						$materialRequest->assignedTo  = $assignee == 'unassign' ? 'null' : $assignee;
						$materialRequest->dateUpdated = time();
						$materialRequest->update();

						//TODO: Email Assignee of the request?

					}
				}
			}else{
				$errors[] = 'User to assign the request to was not valid.';
			}
		}

		$availableFormats = MaterialsRequest::getFormats();
		$interface->assign('availableFormats', $availableFormats);
		if (isset($_REQUEST['formatFilter']) || $updatingFilters){
			$formatsToShow                            = $_REQUEST['formatFilter'] ?? [];
			$_SESSION['materialsRequestFormatFilter'] = $formatsToShow;
		}elseif (isset($_SESSION['materialsRequestFormatFilter'])){
			$formatsToShow = $_SESSION['materialsRequestFormatFilter'];
		}else{
			$defaultFormatsToShow = array_keys($availableFormats);
			$formatsToShow        = $defaultFormatsToShow;
		}
		$interface->assign('formatFilter', $formatsToShow);

		//Get a list of all materials requests for the user
		$allRequests       = [];
		$materialsRequests = new MaterialsRequest();
		$materialsRequests->joinAdd(new Location(), 'LEFT');
		$materialsRequests->joinAdd(new MaterialsRequestStatus());
		$materialsRequests->joinAdd(new User(), 'INNER', 'user', 'createdBy');
		$materialsRequests->joinAdd(new User(), 'LEFT', 'assignee', 'assignedTo');
		$materialsRequests->selectAdd();
		$materialsRequests->selectAdd('materials_request.*, description as statusLabel, location.displayName as location, user.firstname, user.lastname, user.barcode, assignee.displayName as assignedTo');
		$locationsForLibrary = $homeLibrary->getLocationIdsForLibrary();
		$materialsRequests->whereAdd('user.homeLocationId IN (' . implode(', ', $locationsForLibrary) . ')');
		//TODO: can be likely be simplified to user.homeLibraryId now

		if (!empty($statusesToShow) && count($availableStatuses) > count($statusesToShow)){
			$statusSql = $materialsRequests->buildListOfQuotedAndSQLEscapedItems($statusesToShow);
			$materialsRequests->whereAdd("status in ($statusSql)");
		}

		if (!empty($formatsToShow) && count($availableFormats) > count($formatsToShow)){
			//At least one format is disabled
			$formatSql = $materialsRequests->buildListOfQuotedAndSQLEscapedItems($formatsToShow);
			$materialsRequests->whereAdd("format in ($formatSql)");
		}

		if (!empty($assigneesToShow) || $showUnassigned){
			$condition = $assigneesSql = '';
			if (!empty($assigneesToShow)){
				$assigneesSql = $materialsRequests->buildListOfQuotedAndSQLEscapedItems($assigneesToShow);
				$assigneesSql = "assignedTo IN ($assigneesSql)";
			}
			if ($assigneesSql && $showUnassigned){
				$condition = "($assigneesSql OR assignedTo IS NULL OR assignedTo = 0)";
			}elseif ($assigneesSql){
				$condition = $assigneesSql;
			}elseif ($showUnassigned){
				$condition = '(assignedTo IS NULL OR assignedTo = 0)';
			}
			$materialsRequests->whereAdd($condition);
		}

		if (isset($_REQUEST['startDate'])){
			$startDateString = strip_tags($_REQUEST['startDate']);
			$startDate       = strtotime($startDateString);
			if (!empty($startDate) || empty($startDateString)){
				$_SESSION['MaterialsRequestStartDate'] = $startDateString;
			}
		}elseif (!empty($_SESSION['MaterialsRequestStartDate'])){
			$startDateString = $_SESSION['MaterialsRequestStartDate'];
			$startDate       = strtotime($startDateString);
		}
		if (!empty($startDate)){
			$materialsRequests->whereAdd("dateCreated >= $startDate");
			$interface->assign('startDate', $startDateString);
		}

		if (isset($_REQUEST['endDate'])){
			$endDateString = strip_tags($_REQUEST['endDate']);
			$endDate       = strtotime($endDateString);
			if (!empty($endDate) || empty($endDateString)){
				$_SESSION['MaterialsRequestEndDate'] = $endDateString;
			}
		}elseif (!empty($_SESSION['MaterialsRequestEndDate'])){
			$endDateString = $_SESSION['MaterialsRequestEndDate'];
			$endDate       = strtotime($endDateString);
		}
		if (!empty($endDate)){
			$materialsRequests->whereAdd("dateCreated <= $endDate");
			$interface->assign('endDate', $endDateString);
		}

		if (isset($_REQUEST['idsToShow'])){
			$idsToShow = trim(strip_tags($_REQUEST['idsToShow']));
			if (!empty($idsToShow) || $updatingFilters){
				$_SESSION['MaterialsRequestIdsToShow'] = $idsToShow;
			}
		}elseif (!empty($_SESSION['MaterialsRequestIdsToShow'])){
			$idsToShow = $_SESSION['MaterialsRequestIdsToShow'];
		}
		if (!empty($idsToShow)){
			$ids          = explode(',', $idsToShow);
			$formattedIds = $materialsRequests->buildListOfQuotedAndSQLEscapedItems($ids);
			$materialsRequests->whereAdd("materials_request.id IN ($formattedIds)");
			$interface->assign('idsToShow', $idsToShow);
		}

		$numRequests = $materialsRequests->find();
		if (!empty($numRequests)){
			if ($numRequests < 5000){
				$allRequests = $materialsRequests->fetchAll();
			}else{
				// Some filter settings can cause us to retrieve too many material requests.
				// So we've set the limit at 5,000 for now, though that seems like quite a large number also.
				$interface->assign([
					'error'         => 'Sorry, the filter criteria return too many results. Please review your filters to reduce the number of results.'
					, 'filterError' => true
				]);
			}
		}
		$interface->assign('allRequests', $allRequests);

		// $assignees used for both set assignee dropdown and filter by assigned To checkboxes
		$role = new Role();
		if ($role->get('name', 'library_material_requests')){
			// Get Available Assignees
			require_once ROOT_DIR . '/sys/Administration/UserRoles.php';
			$assignees                = [];
			$materialsRequestManagers = new User();
			$userRole                 = new UserRoles();
			$userRole->roleId         = $role->roleId;
			$materialsRequestManagers->joinAdd($userRole);
			$materialsRequestManagers->whereAdd('user.homeLocationId IN (' . implode(', ', $locationsForLibrary) . ')');
			//TODO: can be likely be simplified to user.homeLibraryId now
			if ($materialsRequestManagers->find()){
				$assignees = $materialsRequestManagers->fetchAll('id', 'displayName');
			}
			$interface->assign('assignees', $assignees);
		}

		$materialsRequestFieldsToDisplay            = new MaterialsRequestFieldsToDisplay();
		$materialsRequestFieldsToDisplay->libraryId = $homeLibrary->libraryId;
		$materialsRequestFieldsToDisplay->orderBy('weight');
		if ($materialsRequestFieldsToDisplay->find()){
			$columnsToDisplay = $materialsRequestFieldsToDisplay->fetchAll('columnNameToDisplay', 'labelForColumnToDisplay');
		}else{
			$columnsToDisplay = $this->defaultColumnsToShow();
		}
		$interface->assign('columnsToDisplay', $columnsToDisplay);

		if (isset($_REQUEST['exportSelected'])){
			$this->exportToExcel($_REQUEST['select'], $allRequests);
		}else{
			if (!empty($errors)){
				$interface->assign('error', $errors);
			}
			$staffEmail     = $user->materialsRequestReplyToAddress; // fetched via __get()
			if (empty($staffEmail)){
				$interface->assign('materialRequestStaffSettingsWarning', true);
			}
			$interface->assign('instructions', $this->getInstructions());
			$this->display('manageRequests.tpl', 'Manage Materials Requests');
		}
	}

	function defaultColumnsToShow(){
		return [
			'id'                     => 'Id',
			'title'                  => 'Title',
			'author'                 => 'Author',
			'format'                 => 'Format',
			'createdBy'              => 'Patron',
			'placeHoldWhenAvailable' => 'Place a Hold',
			'illItem'                => 'Inter-Library Loan',
			'assignedTo'             => 'Assigned To',
			'status'                 => 'Status',
			'dateCreated'            => 'Created On',
			'dateUpdated'            => 'Updated On',
		];
	}

	function exportToExcel($selectedRequestIds, $allRequests){
		global $configArray;
		//May need more time to export all records
		set_time_limit(600);
		//PHPEXCEL
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		// Set properties
		global $interface;
		$gitBranch = $interface->getVariable('gitBranch');
		$objPHPExcel->getProperties()->setCreator('Pika ' . $gitBranch)
			->setLastModifiedBy('Pika ' . $gitBranch)
			->setTitle("Office 2007 XLSX Document")
			->setSubject("Office 2007 XLSX Document")
			->setDescription("Office 2007 XLSX, generated using PHP.")
			->setKeywords("office 2007 openxml php")
			->setCategory("Materials Requests Report");

		// Add some data
		$activeSheet = $objPHPExcel->setActiveSheetIndex(0);
		$activeSheet->setCellValueByColumnAndRow(0, 1, 'Materials Requests');

		//Define table headers
		$curRow = 3;
		$curCol = 0;

		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'ID');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Title');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Season');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Magazine');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Author');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Format');
		if ($configArray['MaterialsRequest']['showEbookFormatField']/* || $configArray['MaterialsRequest']['showEaudioFormatField']*/){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Sub Format');
		}
		if ($configArray['MaterialsRequest']['showBookTypeField']){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Type');
		}
		if ($configArray['MaterialsRequest']['showAgeField']){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Age Level');
		}
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'ISBN');
		$objPHPExcel->getActiveSheet()->getStyle($curCol . $curRow)->getNumberFormat()->setFormatCode(PHPExcel_Style_numberFormat::FORMAT_NUMBER);
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'UPC');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'ISSN');
		$objPHPExcel->getActiveSheet()->getStyle($curCol . $curRow)->getNumberFormat()->setFormatCode(PHPExcel_Style_numberFormat::FORMAT_NUMBER);
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'OCLC Number');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Publisher');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Publication Year');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Abridged');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'How did you hear about this?');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Comments');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Name');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Barcode');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Email');

		if ($configArray['MaterialsRequest']['showPlaceHoldField']){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Hold');
		}
		if ($configArray['MaterialsRequest']['showIllField']){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'ILL');
		}
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Status');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Date Created');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Assigned To');

		$numCols = $curCol;
		//Loop Through The Report Data
		/** @var MaterialsRequest $request */
		foreach ($allRequests as $request){
			if (array_key_exists($request->id, $selectedRequestIds)){
				$curRow++;
				$curCol = 0;

				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->id);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->title);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->season);
				$magazineInfo = '';
				if ($request->magazineTitle){
					$magazineInfo .= $request->magazineTitle . ' ';
				}
				if ($request->magazineDate){
					$magazineInfo .= $request->magazineDate . ' ';
				}
				if ($request->magazineVolume){
					$magazineInfo .= 'volume ' . $request->magazineVolume . ' ';
				}
				if ($request->magazineNumber){
					$magazineInfo .= 'number ' . $request->magazineNumber . ' ';
				}
				if ($request->magazinePageNumbers){
					$magazineInfo .= 'p. ' . $request->magazinePageNumbers . ' ';
				}
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, trim($magazineInfo));
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->author);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->format);
				if ($configArray['MaterialsRequest']['showEbookFormatField']/* || $configArray['MaterialsRequest']['showEaudioFormatField']*/){
					$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->subFormat);
				}
				if ($configArray['MaterialsRequest']['showBookTypeField']){
					$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->bookType);
				}
				if ($configArray['MaterialsRequest']['showAgeField']){
					$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->ageLevel);
				}
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->isbn);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->upc);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->issn);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->oclcNumber);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->publisher);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->publicationYear);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->abridged == 0 ? 'Unabridged' : ($request->abridged == 1 ? 'Abridged' : 'Not Applicable'));
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->about);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $request->comments);
				$requestUser = new User();
				$requestUser->get($request->createdBy);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $requestUser->lastname . ', ' . $requestUser->firstname);
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $requestUser->getBarcode());
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $requestUser->email);
				if ($configArray['MaterialsRequest']['showPlaceHoldField']){
					if ($request->placeHoldWhenAvailable == 1){
						$value = 'Yes ' . $request->holdPickupLocation;
						if ($request->bookmobileStop){
							$value .= ' ' . $request->bookmobileStop;
						}
					}else{
						$value = 'No';
					}
					$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $value);
				}
				if ($configArray['MaterialsRequest']['showIllField']){
					if ($request->illItem == 1){
						$value = 'Yes';
					}else{
						$value = 'No';
					}
					$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, $value);
				}
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, translate($request->status));
				$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, date('m/d/Y', $request->dateCreated));
				$activeSheet->setCellValueByColumnAndRow($curCol, $curRow, $request->assignedTo);
			}
		}

		for ($i = 0;$i < $numCols;$i++){
			$activeSheet->getColumnDimensionByColumn($i)->setAutoSize(true);
		}

		// Rename sheet
		$activeSheet->setTitle('Materials Requests');

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename=MaterialsRequests.xls');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');
		exit;
	}

	function getAllowableRoles(){
		return ['library_material_requests'];
	}

	function getInstructions(){
		return 'For more information about Manage Requests configuration, see the <a href="https://marmot-support.atlassian.net/l/c/tmgM8ypn">online documentation</a>.';
	}

}
