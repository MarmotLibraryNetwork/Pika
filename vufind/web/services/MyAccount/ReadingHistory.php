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

use Pika\Logger;

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
require_once ROOT_DIR . '/sys/Pager.php';

class ReadingHistory extends MyAccount {
	private $logger;

	public function __construct(){
		$this->logger = new Logger(__CLASS__);
		parent::__construct();
	}

	function launch(){
		global $offlineMode;
		if (!$offlineMode){
			global $interface;
			$interface->assign('offline', false);

			// Get My Transactions
			$user = UserAccount::getLoggedInUser();
			if ($user){
				$linkedUsers = $user->getLinkedUsers();
				$patronId    = empty($_REQUEST['patronId']) ? $user->id : $_REQUEST['patronId'];
				$patron      = $user->getUserReferredTo($patronId);
				if ($patron){
					if (count($linkedUsers) > 0){
						array_unshift($linkedUsers, $user);
					}
					// make sure linkedUsers makes to template even if empty, so we don't get warnings
					$interface->assign('linkedUsers', $linkedUsers);
					$interface->assign('selectedUser', $patronId); // needs to be set even when there is only one user so that the patronId hidden input gets a value in the reading history form.

					// Define sorting options
					$sortOptions = [
						'title'      => 'Title',
						'author'     => 'Author',
						'checkedOut' => 'Checkout Date',
						'format'     => 'Format',
					];
					$selectedSortOption = !empty($_REQUEST['accountSort']) && isset($sortOptions[$_REQUEST['accountSort']]) ? $_REQUEST['accountSort'] : 'checkedOut';
					$interface->assign('sortOptions', $sortOptions);
					$interface->assign('defaultSortOption', $selectedSortOption);

					$page = !empty($_REQUEST['page']) && ctype_digit($_REQUEST['page']) ? $_REQUEST['page'] : 1;
					$interface->assign('page', $page);

					$recordsPerPage = isset($_REQUEST['pagesize']) && is_numeric($_REQUEST['pagesize']) ? $_REQUEST['pagesize'] : 25;
					$interface->assign('recordsPerPage', $recordsPerPage);
					if (isset($_REQUEST['readingHistoryAction']) && $_REQUEST['readingHistoryAction'] == 'exportToExcel'){
						$recordsPerPage = -1;
						$page           = 1;
					}

					// Setup history search variables
					$searchBy   = false;
					$searchTerm = false;
					$interface->assign('isReadingHistorySearch', false);
					if (isset($_REQUEST['readingHistoryAction']) && $_REQUEST['readingHistoryAction'] == 'searchReadingHistory'){
						$searchTerm = $_REQUEST['searchTerm'] ?? '';
						$searchBy   = in_array($_REQUEST['searchBy'], ['title', 'author']) ? $_REQUEST['searchBy'] : 'title';

						$interface->assign('searchTerm', $searchTerm);
						$interface->assign('searchBy', $searchBy);
						$interface->assign('isReadingHistorySearch', true);
					}

					// Construct Variables for URLs
					$newLocation = '/MyAccount/ReadingHistory';
					$params[] = 'patronId=' . $patronId;
					if ($selectedSortOption != 'checkedOut'){
						$params[] = 'accountSort=' . $selectedSortOption;
					}
					if ($recordsPerPage != 25){
						$params[] = 'pagesize=' . $recordsPerPage;
					}
					if (!empty($searchTerm) && $_REQUEST['readingHistoryAction'] == 'searchReadingHistory'){
						$params[] = 'readingHistoryAction=searchReadingHistory';
						$params[] = 'searchTerm=' . $searchTerm;
						$params[] = 'searchBy=' . $searchBy;
					}


					// Check to see if there is an action to perform.
					if (!empty($_REQUEST['readingHistoryAction']) && !is_array($_REQUEST['readingHistoryAction']) &&
						$_REQUEST['readingHistoryAction'] != 'exportToExcel' && $_REQUEST['readingHistoryAction'] != 'searchReadingHistory'){

						//Perform the requested action
						$selectedTitles       = $_REQUEST['selected'] ?? [];
						$readingHistoryAction = trim($_REQUEST['readingHistoryAction']);
						switch ($readingHistoryAction){
							case 'optIn':
								$patron->optInReadingHistory();
								break;
							case 'optOut':
								$patron->optOutReadingHistory();
								break;
							case 'deleteAll':
								$patron->deleteAllReadingHistory();
								break;
							case 'deleteMarked':
								$patron->deleteMarkedReadingHistory($selectedTitles);
								break;
							default:
								// Deprecated action; should be replaced with above action-specific calls
								$this->logger->warn('Call to undefined reading history action : ' . $readingHistoryAction);
						}

						// Redirect back to the current location without the action.
						if ($page > 1 && $readingHistoryAction != 'deleteAll' && $readingHistoryAction != 'optOut'){
							$params[] = 'page=' . $_REQUEST['page'];
						}

						if (count($params) > 0){
							$additionalParams = implode('&', $params);
							$newLocation      .= '?' . $additionalParams;
						}
						header("Location: $newLocation");
						die();
					}

					$result = $patron->getReadingHistory($page, $recordsPerPage, $selectedSortOption, $searchTerm, $searchBy); //$searchTerm, $searchBy

					$link = $newLocation . '?' . implode('&', $params);
					if (preg_match('/[&?]page=/', $link)){
						$link = preg_replace('/page=\\d+/', 'page=%d', $link);
					}elseif (strpos($link, '?') > 0){
						$link .= '&page=%d';
					}else{
						$link .= '?page=%d';
					}
					if ($recordsPerPage != '-1'){
						$options = [
							'totalItems' => $result['numTitles'],
							'fileName'   => $link,
							'perPage'    => $recordsPerPage,
							'append'     => false,
						];
						$pager   = new VuFindPager($options);
						$interface->assign('pageLinks', $pager->getLinks());
					}
					if (!PEAR_Singleton::isError($result)){
						$interface->assign('historyActive', $result['historyActive']);
						$interface->assign('transList', $result['titles']);
						if (isset($_REQUEST['readingHistoryAction']) && $_REQUEST['readingHistoryAction'] == 'exportToExcel'){
							$this->exportToExcel($result['titles']);
						}
					}
				} else {
					$this->logger->warn("Invalid Patron Id for Reading History $patronId", [$_SERVER['REQUEST_URI']]);
				}
			}
		}

		$this->display('readingHistory.tpl', 'Reading History');
	}

	public function exportToExcel($readingHistory){
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
			->setCategory("Checked Out Items");

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', 'Reading History')
			->setCellValue('A3', 'Title')
			->setCellValue('B3', 'Author')
			->setCellValue('C3', 'Format')
			->setCellValue('D3', 'From')
			->setCellValue('E3', 'To');

		$a = 4;
		//Loop Through The Report Data
		foreach ($readingHistory as $row){

			$format       = is_array($row['format']) ? implode(',', $row['format']) : $row['format'];
			$lastCheckout = isset($row['lastCheckout']) ? date('Y-M-d', $row['lastCheckout']) : '';
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $a, $row['title'])
				->setCellValue('B' . $a, $row['author'])
				->setCellValue('C' . $a, $format)
				->setCellValue('D' . $a, date('Y-M-d', $row['checkout']))
				->setCellValue('E' . $a, $lastCheckout);

			$a++;
		}
		$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);

		// Rename sheet
		$objPHPExcel->getActiveSheet()->setTitle('Reading History');

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="ReadingHistory.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');
		exit;

	}
}
