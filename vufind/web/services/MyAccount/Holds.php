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

/**
 * Shows all titles that are on hold for a user (combines all sources)
 *
 * @category Pika
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
class MyAccount_Holds extends MyAccount{
	function launch(){
		global $configArray,
		       $interface,
		       $library;

		$user = UserAccount::getLoggedInUser();

		$interface->assign('allowFreezeHolds', true);
		//User method getMyHolds checks to see if the user account is allowed to freeze holds

		// Set Holds settings that are based on the ILS system
		$ils = $configArray['Catalog']['ils'];
		$showPosition                    = ($ils == 'Horizon' || $ils == 'Koha' || $ils == 'Symphony' || $ils == 'CarlX');
		// for other ils capable of showing hold position.
		// If $showPosition is already true don't override that setting
		// #D-3420
		if(!$showPosition && isset($configArray['OPAC']['showPosition'])) {
			$showPosition = (boolean)$configArray['OPAC']['showPosition'];
		}
		$showExpireTime                  = ($ils == 'Horizon' || $ils == 'Symphony');
		$suspendRequiresReactivationDate = ($ils == 'Horizon' || $ils == 'CarlX' || $ils == 'Symphony');
		$canChangePickupLocation         = ($ils != 'Koha');
		$showPlacedColumn                = ($ils == 'Symphony' || $ils == 'Horizon'); //TODO: is this true for earlier versions of Horizon Drivers
		// for other ils capable of showing date hold was placed.
		// If $showPlacedColumn is already true don't override that setting
		// #D-3420
		if(!$showPlacedColumn && isset($configArray['OPAC']['showDatePlaced'])) {
			$showPlacedColumn = (boolean)$configArray['OPAC']['showDatePlaced'];
		}
		$showDateWhenSuspending          = ($ils == 'Symphony' || $ils == 'Horizon' || $ils == 'CarlX' || $ils == 'Koha');
		if (isset($configArray['suspend_requires_reactivation_date'])) {
			$suspendRequiresReactivationDate = $configArray['suspend_requires_reactivation_date'];
		}
		$interface->assign('suspendRequiresReactivationDate', $suspendRequiresReactivationDate);
		$interface->assign('canChangePickupLocation', $canChangePickupLocation);
		$interface->assign('showPlacedColumn', $showPlacedColumn);
		$interface->assign('showDateWhenSuspending', $showDateWhenSuspending);
		$interface->assign('showPosition', $showPosition);
		$interface->assign('showNotInterested', false);


		// Define sorting options
		$unavailableHoldSortOptions = [
			'title'    => 'Title',
			'author'   => 'Author',
			'format'   => 'Format',
			'status'   => 'Status',
			'location' => 'Pickup Location',
		];
		if ($showPosition){
			$unavailableHoldSortOptions['position'] = 'Position';
		}
		if ($showPlacedColumn){
			$unavailableHoldSortOptions['placed'] = 'Date Placed';
		}

		$availableHoldSortOptions = [
			'title'    => 'Title',
			'author'   => 'Author',
			'format'   => 'Format',
			'expire'   => 'Expiration Date',
			'location' => 'Pickup Location',
		];

		if (count($user->getLinkedUsers()) > 0){
			$unavailableHoldSortOptions['libraryAccount'] = 'Library Account';
			$availableHoldSortOptions['libraryAccount']   = 'Library Account';
		}

		$interface->assign('sortOptions', [
			'available'   => $availableHoldSortOptions,
			'unavailable' => $unavailableHoldSortOptions
		]);

		$selectedAvailableSortOption   = !empty($_REQUEST['availableHoldSort']) ? $_REQUEST['availableHoldSort'] : 'expire';
		$selectedUnavailableSortOption = !empty($_REQUEST['unavailableHoldSort']) ? $_REQUEST['unavailableHoldSort'] : ($showPosition ? 'position' : 'title') ;
		$interface->assign('defaultSortOption', [
			'available'   => $selectedAvailableSortOption,
			'unavailable' => $selectedUnavailableSortOption
		]);


		if ($library->showLibraryHoursNoticeOnAccountPages) {
			$libraryHoursMessage = Location::getLibraryHoursMessage($user->homeLocationId);
			$interface->assign('libraryHoursMessage', $libraryHoursMessage);
		}


		// Get My Transactions
		global $offlineMode;
		if (!$offlineMode) {
			if ($user) {

				// Paging not implemented on holds page
//				$recordsPerPage = isset($_REQUEST['pagesize']) && (is_numeric($_REQUEST['pagesize'])) ? $_REQUEST['pagesize'] : 25;
//				$interface->assign('recordsPerPage', $recordsPerPage);

				$allHolds = $user->getMyHolds(true, $selectedUnavailableSortOption, $selectedAvailableSortOption);
				$interface->assign('recordList', $allHolds);

				//make call to export function
				if ((isset($_GET['exportToExcelAvailable'])) || (isset($_GET['exportToExcelUnavailable']))) {
					if (isset($_GET['exportToExcelAvailable'])) {
						$exportType = "available";
					} else {
						$exportType = "unavailable";
					}
					$this->exportToExcel($allHolds, $exportType, $showDateWhenSuspending, $showPosition, $showExpireTime);
				}
			}
		}

// Not displayed, so skipping fetching offline holds for the patron
		//Load holds that have been entered offline
		global $offlineMode;
		global $configArray;
		$useOfflineHolds = $configArray['Catalog']['useOfflineHoldsInsteadOfRegularHolds'] ?? false;
		if ($user && ($offlineMode ||$useOfflineHolds)){
			$offlineHolds = [];
			require_once ROOT_DIR . '/sys/Circa/OfflineHold.php';
			$users = array_merge([$user], $user->getLinkedUsers());
			foreach ($users as $patron){
				$offlineHoldsObj           = new OfflineHold();
				$offlineHoldsObj->patronId = $patron->id;
				$offlineHoldsObj->whereAdd("status = 'Not Processed'");
//				$offlineHoldsObj->whereAdd(" (status != 'Not Processed' AND FROM_UNIXTIME(timeEntered) >= DATE_SUB(NOW(), INTERVAL 2 DAY)) OR (status = 'Hold Failed' AND FROM_UNIXTIME(timeEntered) >= DATE_SUB(NOW(), INTERVAL 2 WEEK)) ");
				// the more complicated sql doesn't come into effect because usually not in offline mode once holds are processed.
				if ($offlineHoldsObj->find()){
					require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
					while ($offlineHoldsObj->fetch()){
						//Load the title
						$offlineHold = [
							'user'   => $patron->getNameAndLibraryLabel(),
							'userId' => $patron->id,
						];
						$location    = new Location();
						if ($location->get('code', $offlineHoldsObj->pickupLocation)){
							$offlineHold['location'] = $location->displayName;
						}
						$sourceAndId  = new SourceAndId('ils:' . $offlineHoldsObj->bibId);
						$recordDriver = new MarcRecord($sourceAndId);
						if ($recordDriver->isValid()){
							$offlineHold['title']           = $recordDriver->getTitle();
							$offlineHold['author']          = $recordDriver->getPrimaryAuthor();
							$offlineHold['sortTitle']       = $recordDriver->getSortableTitle();
							$offlineHold['format']          = $recordDriver->getFormat();
							$offlineHold['isbn']            = $recordDriver->getCleanISBN();
							$offlineHold['upc']             = $recordDriver->getCleanUPC();
							$offlineHold['format_category'] = $recordDriver->getFormatCategory();
							$offlineHold['coverUrl']        = $recordDriver->getBookcoverUrl('medium');
							$offlineHold['link']            = $recordDriver->getRecordUrl();
						}
						$offlineHold['id']          = $offlineHoldsObj->bibId;
						$offlineHold['bibId']       = $offlineHoldsObj->bibId;
						$offlineHold['timeEntered'] = $offlineHoldsObj->timeEntered;
						$offlineHold['status']      = $offlineHoldsObj->status;
						//$offlineHold['notes']       = $offlineHoldsObj->notes;
						$offlineHold['cancelable'] = true;
						$offlineHold['cancelId']   = $offlineHoldsObj->id;
						$offlineHolds[]            = $offlineHold;
					}
				}
			}
			$interface->assign('offlineHolds', $offlineHolds);
		}


		// Set up explanation blurb for My Holds page
		if (!$library->showDetailedHoldNoticeInformation){
			$notification_method = '';
		}else{
			$notification_method = ($user->noticePreferenceLabel != 'Unknown') ? $user->noticePreferenceLabel : '';
			if ($notification_method == 'Mail' && $library->treatPrintNoticesAsPhoneNotices){
				$notification_method = 'Telephone';
			}
		}
		$interface->assign('notification_method', strtolower($notification_method));

		// Present to the user
		$this->display('holds.tpl', 'My Holds');
	}

	function isValidTimeStamp($timestamp) {
		return is_numeric($timestamp)
			&& ($timestamp <= PHP_INT_MAX)
			&& ($timestamp >= ~PHP_INT_MAX);
	}

	public function exportToExcel($result, $exportType, $showDateWhenSuspending, $showPosition, $showExpireTime) {
		//PHPEXCEL
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		// Set properties
		$objPHPExcel->getProperties()->setCreator("DCL")
		->setLastModifiedBy("DCL")
		->setTitle("Office 2007 XLSX Document")
		->setSubject("Office 2007 XLSX Document")
		->setDescription("Office 2007 XLSX, generated using PHP.")
		->setKeywords("office 2007 openxml php")
		->setCategory("Holds");

		if ($exportType == "available") {
			// Add some data
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', 'Holds - '.ucfirst($exportType))
			->setCellValue('A3', 'Title')
			->setCellValue('B3', 'Author')
			->setCellValue('C3', 'Format')
			->setCellValue('D3', 'Placed')
			->setCellValue('E3', 'Pickup')
			->setCellValue('F3', 'Available')
			->setCellValue('G3', translate('Pick-Up By'));
		} else {
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', 'Holds - '.ucfirst($exportType))
			->setCellValue('A3', 'Title')
			->setCellValue('B3', 'Author')
			->setCellValue('C3', 'Format')
			->setCellValue('D3', 'Placed')
			->setCellValue('E3', 'Pickup');

			if ($showPosition){
				$objPHPExcel->getActiveSheet()->setCellValue('F3', 'Position')
				->setCellValue('G3', 'Status');
				if ($showExpireTime){
					$objPHPExcel->getActiveSheet()->setCellValue('H3', 'Expires');
				}
			}else{
				$objPHPExcel->getActiveSheet()
				->setCellValue('F3', 'Status');
				if ($showExpireTime){
					$objPHPExcel->getActiveSheet()->setCellValue('G3', 'Expires');
				}
			}
		}


		$a=4;
		//Loop Through The Report Data
		foreach ($result[$exportType] as $row) {

			$titleCell = preg_replace("/(\/|:)$/", "", $row['title']);
			if (isset ($row['title2'])){
				$titleCell .= preg_replace("/(\/|:)$/", "", $row['title2']);
			}

			if (isset ($row['author'])){
				if (is_array($row['author'])){
					$authorCell = implode(', ', $row['author']);
				}else{
					$authorCell = $row['author'];
				}
				$authorCell = str_replace('&nbsp;', ' ', $authorCell);
			}else{
				$authorCell = '';
			}
			if (isset($row['format'])){
				if (is_array($row['format'])){
					$formatString = implode(', ', $row['format']);
				}else{
					$formatString = $row['format'];
				}
			}else{
				$formatString = '';
			}

			if (empty($row['create'])) {
				$placedDate = '';
			} else {
				$placedDate = $this->isValidTimeStamp($row['create']) ? $row['create'] : strtotime($row['create']);
				$placedDate = date('M d, Y', $placedDate);
			}

			if (empty($row['expire'])) {
				$expireDate = '';
			} else {
				$expireDate = $this->isValidTimeStamp($row['expire']) ? $row['expire'] : strtotime($row['create']);
				$expireDate = date('M d, Y', $expireDate);
			}

			if ($exportType == "available") {
				if (empty($row['availableTime'])) {
					$availableDate = 'Now';
				} else {
					$availableDate = $this->isValidTimeStamp($row['availableTime']) ? $row['availableTime'] : strtotime($row['availableTime']);
					$availableDate =  date('M d, Y', $availableDate);
				}
				$objPHPExcel->getActiveSheet()
				->setCellValue('A'.$a, $titleCell)
				->setCellValue('B'.$a, $authorCell)
				->setCellValue('C'.$a, $formatString)
				->setCellValue('D'.$a, $placedDate)
				->setCellValue('E'.$a, $row['location'])
				->setCellValue('F'.$a, $availableDate)
				->setCellValue('G'.$a, $expireDate);
			} else {
				if (isset($row['status'])){
					$statusCell = $row['status'];
				}else{
					$statusCell = '';
				}

				if (isset($row['frozen']) && $row['frozen'] && $showDateWhenSuspending && !empty($row['reactivateTime'])){
					$reactivateTime = $this->isValidTimeStamp($row['reactivateTime']) ? $row['reactivateTime'] : strtotime($row['reactivateTime']);
					$statusCell .= " until " . date('M d, Y',$reactivateTime);
				}
				$objPHPExcel->getActiveSheet()
				->setCellValue('A'.$a, $titleCell)
				->setCellValue('B'.$a, $authorCell)
				->setCellValue('C'.$a, $formatString)
				->setCellValue('D'.$a, $placedDate);
				if (isset($row['location'])){
					$objPHPExcel->getActiveSheet()->setCellValue('E'.$a, $row['location']);
				}else{
					$objPHPExcel->getActiveSheet()->setCellValue('E'.$a, '');
				}

				if ($showPosition){
					if (isset($row['position'])){
						$objPHPExcel->getActiveSheet()->setCellValue('F'.$a, $row['position']);
					}else{
						$objPHPExcel->getActiveSheet()->setCellValue('F'.$a, '');
					}

					$objPHPExcel->getActiveSheet()->setCellValue('G'.$a, $statusCell);
					if ($showExpireTime){
						$objPHPExcel->getActiveSheet()->setCellValue('H'.$a, $expireDate);
					}
				}else{
					$objPHPExcel->getActiveSheet()->setCellValue('F'.$a, $statusCell);
					if ($showExpireTime){
						$objPHPExcel->getActiveSheet()->setCellValue('G'.$a, $expireDate);
					}
				}
			}
			$a++;
		}
		$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);


		// Rename sheet
		$objPHPExcel->getActiveSheet()->setTitle('Holds');

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="Holds.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');
		exit;

	}
}
