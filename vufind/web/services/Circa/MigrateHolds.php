<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * Quick template of functions to aid in the migration of holds for onboarding new libraries into Marmot's circulation
 * system.
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 10/16/2019
 *
 */


class Circa_MigrateHolds extends Action {

	//TODO: Migrate class to the place CJ created her Migration scripts


	function launch(){
		set_time_limit(600);
		// Clearview Migration

		//Need to comment or remove comments from methods you need to run.
		// This process is meant to be run locally (on the development environment)
		// so that needed changes can be made conveniently.

		// Set pickup code; find item barcodes to match in Sierra with
		//$this->preprocessClearviewHolds();
		// run this step on the clearview instance

		// Find Sierra BibIds based on item barcodes
		$holdsArray = $this->findNewMLN2SierraBibIds();
		// [$patronBarcode, $pickupCode, $itemBarcode, &$bibId, $holdDate, &$sierraItemId]

		$this->writeCSVfile($holdsArray, 'clearview_holds_phase_three.csv');
		// run this step on the mln2 instance
		// adding the sierra item id, in case item hold is needed

		// Sort Holds by creation date
		$holdsArray = $this->sortClearviewHoldsByCreationDate($holdsArray);
		// [$patronBarcode, $pickupCode, $itemBarcode, &$bibId, $holdDate, &$sierraItemId]
		$this->writeCSVfile($holdsArray, 'clearview_holds_phase_four.csv');

		$barcodes = array_column($holdsArray, 0);
		$this->createNewUsersFromBarCodeList($barcodes);
		// run this step on the mln2 instance

		$holdsArrayFinal = $this->placeHoldsInSierra($holdsArray);
		$this->writeCSVfile($holdsArrayFinal, 'clearview_holds_final.csv');
		// run this step on the mln2 instance




		// Delta Migration
//		$this->createNewUsersFromBarCodeList();
//		$this->getBibIdFromItemBarcode();
//		$this->getBibIdFromISBN();
//		$this->processDeltaCharges();
//		$this->processDeltaPatrons();
//		$this->processDeltaHolds();

	}

	function createNewUsersFromBarCodeList($barcodes = null){
		$barcodes ??= []; // Manually input barcodes here

		echo '<pre>';
		/** @var Pika\PatronDrivers\Sierra $sierra */
		require_once ROOT_DIR . '/CatalogConnection.php';
		$sierra = CatalogFactory::getCatalogConnectionInstance();

		foreach (array_unique($barcodes) as $barcode){
			$user          = new User();
			$user->barcode = $barcode;
			if (!$user->find()){
				$user = $sierra->findNewUser($barcode);
				if (!is_a($user, 'User')){
					echo "Failed to create user for barcode $barcode\n";
				}else{
					echo "Barcode: $barcode, Pika User id: {$user->id}\n";
					//TODO: write to a csv
				}
			}
//			echo "'$barCode',\n";
		}
		echo '</pre>';

	}

//	const string itemRecordtag = '989';
	const string itemRecordTag = '949';
	const string itemBarcodeSubfield = 'p';
	function getBibIdFromItemBarcode(array $itemBarcodes = null){
		// Use the array below if the passed in parameter for $itemBarcodes is null
		$itemBarcodes ??= [

		];

		/** @var SearchObject_Solr $searchObject */
//		global $searchObject;
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		foreach ($itemBarcodes as $itemBarcode){
			$itemBarcode = trim($itemBarcode);
			if (!empty($itemBarcode)){
				$solrDoc = $searchObject->getRecordByBarcode($itemBarcode);
				if (!empty($solrDoc)){
					$groupedWorkDriver = RecordDriverFactory::initRecordDriver($solrDoc);
					if (!empty($groupedWorkDriver) && $groupedWorkDriver->isValid()){
						$relatedRecords = $groupedWorkDriver->getRelatedRecords();
						if (!empty($relatedRecords)){
							$matchRecordFound = false;
							require_once ROOT_DIR . '/RecordDrivers/Factory.php';
							foreach ($relatedRecords as $relatedRecord){
								if ($relatedRecord['source'] == 'ils'){
									/** @var MarcRecord $recordDriver */
									$recordDriver = $relatedRecord['driver'];
									$marcRecord   = $recordDriver->getMarcRecord();

									if ($marcRecord != false){
										$itemTags = $marcRecord->getFields(self::itemRecordTag);
										foreach ($itemTags as $itemTag){
											if (!empty($itemTag->getSubfield(self::itemBarcodeSubfield))){
												$itemTagBarcode = trim($itemTag->getSubfield(self::itemBarcodeSubfield)->getData());
												if ($itemBarcode == $itemTagBarcode){
													$matchRecordFound = true;
													echo "$itemBarcode, " . str_replace('ils:', '', $relatedRecord['id']);
													break 2;
												}
											}
										}
									}


								}
							}
							if (!$matchRecordFound){
								echo 'No match found for ' . $itemBarcode;
							}
						} else {
							echo 'no related records for work ' . $solrDoc['id'];
						}
					}else{
						echo 'Found no match in index for ' . $itemBarcode;
					}
				}else{
					echo 'Found no match in index for ' . $itemBarcode;
				}

			}
			echo "\n";

		}
	}

	function getBibIdFromISBN(){
		$ISBNs = [
		];
		/** @var SearchObject_Solr $searchObject */
//		global $searchObject;
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();
		foreach ($ISBNs as $ISBN){
			$solrDoc = $searchObject->getRecordByIsbn([$ISBN]);
			if (!empty($solrDoc)){
				$groupedWorkDriver = RecordDriverFactory::initRecordDriver($solrDoc);
				if (!empty($groupedWorkDriver) && $groupedWorkDriver->isValid()){
					$relatedRecords   = $groupedWorkDriver->getRelatedRecords();
					$matchRecordFound = false;
					require_once ROOT_DIR . '/RecordDrivers/Factory.php';
					foreach ($relatedRecords as $relatedRecord){
						if ($relatedRecord['source'] == 'ils'){
							/** @var MarcRecord $relatedRecord */
							$recordDriver = $relatedRecord['driver'];
							$recordISBNs  = $recordDriver->getISBNs();
							if (in_array($ISBN, $recordISBNs)){
								if ($matchRecordFound) {
									echo 'Had match already Additional match : ';
								}
								$matchRecordFound = true;
								echo $ISBN . ',' . str_replace('ils:', '', $relatedRecord['id']) . "," . $relatedRecord['format'];
							}
						}
					}
					if (!$matchRecordFound){
						echo 'No match found for ' . $ISBN;
					}
				}else{
					echo 'Found no match in index for ' . $ISBN;
				}
			}else{
				echo 'Found no match in index for ' . $ISBN;
			}
			echo "\n";
		}
	}

	function processDeltaCharges(){
		$row = 1;
		$userIdAndBarcode = [];
		$userId = $itemBarcode = null;
		if (($handle = fopen("all_charges.flat", "r")) !== false){
			echo '<pre>';
			while (($data = fgetcsv($handle, 0, "|")) !== false){
				$row++;

				if (strpos($data[0], 'USER_ID')){
					$userId = substr($data[1], 1);
				}
				if (strpos($data[0], 'ITEM_ID')){
					$itemBarcode = substr($data[1], 1);
				}
				if (!empty($itemBarcode) && !empty($userId)){
					$userIdAndBarcode[$userId] = $itemBarcode;
					echo $userId . ", $itemBarcode\n";
					$userId = $itemBarcode = null;
					continue;
				}
			}
			echo '</pre>';
			fclose($handle);
		}
	}

	function processDeltaHolds(){
		$userIdAndBarcode = [];
		$userId           = $itemBarcode = $holdDate = $pickupCode = $holdExpires = null;
		// Process All holds file
		if (($handle = fopen("all_holds.flat", "r")) !== false){
			echo '<pre>';
			while (($data = fgetcsv($handle, 0, "|")) !== false){
				if (strpos($data[0], 'USER_ID')){
					$userId = substr($data[1], 1);
				}
				if (strpos($data[0], 'ITEM_ID')){
					$itemBarcode = substr($data[1], 1);
				}
				if (strpos($data[0], 'HOLD_DATE')){
					$holdDate = substr($data[1], 1);
				}
				if (strpos($data[0], 'HOLD_PICKUP_LIBRARY')){
					$pickupCode = substr($data[1], 1);
				}
//				if (strpos($data[0], 'HOLD_EXPIRES_DATE')){
//					$holdExpires = substr($data[1], 1);
//				}
				if (!empty($itemBarcode) && !empty($userId) && !empty($holdDate) && !empty($pickupCode) /*&& !empty($holdExpires)*/){
					$userIdAndBarcode[] = [$userId, $itemBarcode, $holdDate, $pickupCode];
//					echo $userId . ", $itemBarcode\n";
//					echo $userId . ", $itemBarcode, Expires: $holdExpires". ( $holdExpires != 'NEVER' && (int) $holdExpires < 20201201 ? ' expired' : '') .  "\n";
					$userId = $holdDate = $pickupCode = $itemBarcode = null;
					continue;
				}
			}
		}
		fclose($handle);

		// Remove inactive entries
		$userId = $holdDate = $pickupCode = $itemBarcode = null;
		if (($handle = fopen("holdinactiveav.flat", "r")) !== false){
			echo '<pre>';
			while (($data = fgetcsv($handle, 0, "|")) !== false){
				if (strpos($data[0], 'USER_ID')){
					$userId = substr($data[1], 1);
				}
				if (strpos($data[0], 'ITEM_ID')){
					$itemBarcode = substr($data[1], 1);
				}
				if (strpos($data[0], 'HOLD_DATE')){
					$holdDate = substr($data[1], 1);
				}
				if (strpos($data[0], 'HOLD_PICKUP_LIBRARY')){
					$pickupCode = substr($data[1], 1);
				}
				if (!empty($itemBarcode) && !empty($userId) && !empty($holdDate) && !empty($pickupCode)){
					$this->removeHold($userId, $itemBarcode, $holdDate, $pickupCode, $userIdAndBarcode);
					$userId = $holdDate = $pickupCode = $itemBarcode = null;
					continue;
				}
			}
		}
		fclose($handle);

		// Remove inactive entries
		$userId = $holdDate = $pickupCode = $itemBarcode = null;
		if (($handle = fopen("holdinactivenotav.flat", "r")) !== false){
			echo '<pre>';
			while (($data = fgetcsv($handle, 0, "|")) !== false){
				if (strpos($data[0], 'USER_ID')){
					$userId = substr($data[1], 1);
				}
				if (strpos($data[0], 'ITEM_ID')){
					$itemBarcode = substr($data[1], 1);
				}
				if (strpos($data[0], 'HOLD_DATE')){
					$holdDate = substr($data[1], 1);
				}
				if (strpos($data[0], 'HOLD_PICKUP_LIBRARY')){
					$pickupCode = substr($data[1], 1);
				}
				if (!empty($itemBarcode) && !empty($userId) && !empty($holdDate) && !empty($pickupCode)){
					$this->removeHold($userId, $itemBarcode, $holdDate, $pickupCode, $userIdAndBarcode);
					$userId = $holdDate = $pickupCode = $itemBarcode = null;
					continue;
				}
			}
		}
		fclose($handle);

		$BibIdMatches = [];
		if (($handle = fopen("temp-delta-hold-user-barcode-item-barcode-item-barcode-again-bidId-Match.csv", "r")) !== false){
			echo '<pre>';
			while (($data = fgetcsv($handle)) !== false){
				$BibIdMatches[] = $data;
			}
		}
		fclose($handle);

		$pickupLocations = [
			'CE' => 'dc',
			'CR' => 'dr',
			'DE' => 'dd',
			'HO' => 'dh',
			'PA' => 'dp',
			'TS' => 'dh',
		];

//		$sortedArray = $userIdAndBarcode;
//		usort($sortedArray, function ($a, $b){ return $a[2] <=> $b[2];});
//		foreach ($sortedArray as $index => $anUserIdBarcode){
		foreach ($userIdAndBarcode as $index => $anUserIdBarcode){
			[$holdUser, $holdBarcode, $holdsHoldDate, $holdPickupCode] = $anUserIdBarcode;
			foreach ($BibIdMatches as $data){
				if ($holdUser == $data[0] && $holdBarcode == $data[1]){
					echo "$holdUser, {$data[3]}, {$pickupLocations[$holdPickupCode]}\n";
//					echo "$holdUser, {$data[3]}, $holdsHoldDate, {$pickupLocations[$holdPickupCode]}\n";
					break;
				}
			}
		}

//		foreach ($userIdAndBarcode as $index => &$anUserIdBarcode){
//			[$holdUser, $holdBarcode] = $anUserIdBarcode;
//			echo "$holdUser, $holdBarcode\n";
//		}

		echo '</pre>';
	}

	const string HOLDS_FILE_NAME = 'clearview_holds.csv';
	// Save file is base web directory pika/vufind/web/

	function preprocessClearviewHolds(){
		echo '<pre>';
		$holdArray = $this->processClearviewHoldsFile();
		// NEXT set pickup branch code
		$holdArray = $this->setPickupBranchCode($holdArray);
		// Save phase one
		$this->writeCSVfile($holdArray, 'clearview_holds_phase_one.csv');
		echo "\n\n";
		// Set item barcodes for Polaris bib Ids
		$holdArray = $this->findClearviewItemBarcodeForBibID($holdArray);
		// Save as phase two
		$this->writeCSVfile($holdArray, 'clearview_holds_phase_two.csv');
		echo "\n\n";
		//NEXT search Sierra bibId from item barcodes

		// Save as phase 3

		// Next input holds into Sierra

		echo '</pre>';
	}

	private function writeCSVfile(array $CSVData, string $filename){
		$handle = fopen($filename, 'w');
		foreach ($CSVData as $row){
			fputcsv($handle, $row);
		}
		fclose($handle);
	}
	private function processClearviewHoldsFile() : array{
		$holdArray = [];
		$patronBarcode         = $itemBarcode = $holdDate = $pickupCode = null;
		// Process All holds file
		if (($handle = fopen(self::HOLDS_FILE_NAME, 'r')) !== false){
			$data                  = fgetcsv($handle, 0, ',');
			$patronBarcodePosition = 0;
			$itemBarcodePosition   = 0;
			$bibIdPosition         = 0;
			$holdDatePosition      = 0;
			$pickupBranchPosition  = 0;
			foreach ($data as $position => $columnTitle){
				if (str_starts_with($columnTitle, 'Barcode')){
					$patronBarcodePosition = $position;
					continue;
				}
				if (str_starts_with($columnTitle, 'ItemBarcode')){
					$itemBarcodePosition = $position;
					continue;
				}
				if (str_starts_with($columnTitle, 'PickupBranchID')){
					$pickupBranchPosition = $position;
					continue;
				}
				if (str_starts_with($columnTitle, 'BibliographicRecordID')){
					$bibIdPosition = $position;
					continue;
				}
				// Capture hold creation data so we can order the holds by creation date
				if (str_starts_with($columnTitle, 'CreationDate')){
					$holdDatePosition = $position;
					continue;
				}
			}

			if ($patronBarcodePosition && $itemBarcodePosition && $pickupBranchPosition && $bibIdPosition && $holdDatePosition){
				while (($data = fgetcsv($handle, 0, ',')) !== false){
					$patronBarcode = $data[$patronBarcodePosition];
					$itemBarcode   = str_replace('NULL', '', $data[$itemBarcodePosition]);
					$bibId         = $data[$bibIdPosition];
					$pickupCode    = $data[$pickupBranchPosition];
					$holdDate      = $data[$holdDatePosition];
					if ((!empty($itemBarcode) || !empty($bibId)) && !empty($patronBarcode) && !empty($holdDate) && !empty($pickupCode)){
						$holdArray[] = [$patronBarcode, $pickupCode, $itemBarcode, $bibId, $holdDate];
						echo "$patronBarcode, $pickupCode, $itemBarcode, $bibId, $holdDate\n";
					} else {
						echo "Row with insufficient data :" .implode(',', $data). "\n";
					}
				}
			}
		}
		fclose($handle);
		return $holdArray;
	}

	private function removeHold($userId, $itemBarcode, $holdDate, $pickupCode, &$userIdAndBarcode){
		foreach ($userIdAndBarcode as $index => &$anUserIdBarcode){
			[$holdUser, $holdBarcode, $holdsHoldDate, $holdPickupCode] = $anUserIdBarcode;
			if ($holdUser == $userId && $itemBarcode == $holdBarcode && $holdDate == $holdsHoldDate && $pickupCode == $holdPickupCode){
				unset($userIdAndBarcode[$index]);
				return;
			}
		}
		echo "Error: did not find hold for $userId, $itemBarcode, $holdDate, $pickupCode\n";
	}

	function processDeltaPatrons(){
		$row = 1;
		$userInfo = [
			'userId'    => '',
			'userName'  => '',
			'gender'    => '',
			'pobox'     => '',
			'street1'   => '',
			'street2'   => '',
			'street3'   => '',
			'street4'   => '',
			'cityState' => '',
			'zip'       => '',
			'email'     => '',
			'homephone' => '',
			'cellphone' => '',
		];
		$users = [];
		$userId = $itemBarcode = null;
		if (($handle = fopen("all_users.flat", "r")) !== false){
			echo '<pre>';
			foreach (array_keys($userInfo) as $key){
				echo $key .',';
			}
			echo "\n";
			while (($data = fgetcsv($handle, 0, "|")) !== false){
				$row++;
				if (strpos($data[0], '*** DOCUMENT BOUNDARY ***') !== false){
					// Input user and reset for next one;
//					$users[] = $userInfo;

//					if ($row > 3){
//						echo $userInfo['userName'];
//						exit();
//					}

					foreach (array_keys($userInfo) as $key){
						echo '"'.$userInfo[$key] .'",';
					}
					echo "\n";

					//Set up for next user
					$userInfo = [
						'userId'    => '',
						'userName'  => '',
						'gender'     => '',
						'pobox'     => '',
						'street1'   => '',
						'street2'   => '',
						'street3'   => '',
						'street4'   => '',
						'cityState' => '',
						'zip'       => '',
						'email'     => '',
						'homephone' => '',
						'cellphone' => '',
					];
					$collectAddress = false;

				}

				if (strpos($data[0], 'USER_ID') !== false){
					$field = substr($data[1], 1);
					$userInfo['userId'] = $field;
					continue;
				}
				if (strpos($data[0], 'USER_NAME.') !== false){
					$field = substr($data[1], 1);
					$userInfo['userName'] = $field;
					continue;
				}
				if (strpos($data[0], 'USER_CATEGORY1.') !== false){
					$field = substr($data[1], 1);
					$userInfo['gender'] = $field;
					continue;
				}
				if (strpos($data[0], 'USER_ADDR1_BEGIN') !== false){
					$collectAddress = true;
				}
				if (strpos($data[0], 'USER_ADDR1_END') !== false){
					$collectAddress = false;
				}

				// Address data
				if ($collectAddress && strpos($data[0], 'POBOX') !== false){
					$field = substr($data[1], 1);
					$userInfo['pobox'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'STREET1') !== false){
					$field = substr($data[1], 1);
					$userInfo['street1'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'STREET2') !== false){
					$field = substr($data[1], 1);
					$userInfo['street2'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'STREET3') !== false){
					$field = substr($data[1], 1);
					$userInfo['street3'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'STREET4') !== false){
					$field = substr($data[1], 1);
					$userInfo['street4'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'CITY/STATE') !== false){
					$field = substr($data[1], 1);
					$userInfo['cityState'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'ZIP') !== false){
					$field = substr($data[1], 1);
					$userInfo['zip'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'EMAIL') !== false){
					$field = substr($data[1], 1);
					$userInfo['email'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'HOMEPHONE') !== false){
					$field = substr($data[1], 1);
					$userInfo['homephone'] = $field;
					continue;
				}
				if ($collectAddress && strpos($data[0], 'CELLPHONE') !== false){
					$field = substr($data[1], 1);
					$userInfo['cellphone'] = $field;
					continue;
				}


			}
			echo '</pre>';
			fclose($handle);
		}
	}

	/**
	 * Use the modified ils_itemid_to_ilsid table with an item barcode column
	 * to get an item barcode from the Clearview Polaris bibId.
	 *
	 * NOTE: NEED TO RUN WITH A CLEARVIEW INSTANCE with data populated in the
	 * modified ils_itemid_to_ilsid table.
	 *
	 * Since the item barcode won't change in the migration into Sierra, we can use
	 * item barcodes to find the new Sierra bib ID, which will then be used to place
	 * bib-level barcodes.
	 *
	 * @param array $holdsArray
	 * @return array
	 */
	private function findClearviewItemBarcodeForBibID(array $holdsArray):array{
		$db = $this->getDBConnection();
		foreach ($holdsArray as [$patronBarcode, $pickupCode, &$itemBarcode, $bibId, $holdDate]){
			if (empty($itemBarcode)){
				if (!empty($bibId)){
					$result = $db->query("SELECT `itembarcode` FROM `ils_itemid_to_ilsid` WHERE `ilsId` = '$bibId' LIMIT 1");
					[$foundItemBarcode] = $result->fetchRow();
					if (!empty($foundItemBarcode) && ctype_digit($foundItemBarcode)){
						echo " Found item barcode $foundItemBarcode for bib Id $bibId\n";
						$itemBarcode = $foundItemBarcode;
					}else{
						echo 'ERROR getting item barcode for bib ID: ' . $bibId . ", found '$foundItemBarcode'\n";
					}
				}else{
					echo "Hold entry without an item barcode or an Bib Id for patron $patronBarcode hold date $holdDate\n";
				}
			}
		}
		return $holdsArray;
	}

	const array PICKUP_BRANCH_CODES = [
			'3' => 'cw', // windsor
			'8' => 'cs', // severence
			'4' => 'cb', // bookmobile
			'5' => 'ca', //storage/admin
		];

	private function setPickupBranchCode(array $holdArray){
		foreach ($holdArray as [$patronBarcode, &$pickupCode, $itemBarcode, $bibId, $holdDate]){
			if (!empty($pickupCode)){
				if (array_key_exists($pickupCode, self::PICKUP_BRANCH_CODES)){
					$pickupCode = self::PICKUP_BRANCH_CODES[$pickupCode];
				} else {
					echo "Did not find pickup code $pickupCode in our code array\n";
				}
			} else {
				echo "Missing pickup code $pickupCode in hold array with patron barcode $patronBarcode\n";
			}
		}
		return $holdArray;
		}

	private function findNewMLN2SierraBibIds($filename = 'clearview_holds_phase_two.csv'){
		echo "<pre>\n";
		$holdsArray = $this->loadCSVintoHoldsArray($filename);


		$db = $this->getDBConnection();
		foreach ($holdsArray as [$patronBarcode, $pickupCode, $itemBarcode, &$bibId, $holdDate, &$sierraItemId]){
			if (!empty($itemBarcode)){
				$result = $db->query("SELECT `ilsId`, `itemId` FROM `ils_itemid_to_ilsid` WHERE `itemBarcode` = '$itemBarcode' LIMIT 1");
				[$foundBibId, $foundItemId] = $result->fetchRow();
				if (empty($foundBibId)){
					echo "Did not find new Bib Id for item barcode $itemBarcode\n";
					$bibId = null; // empty out the bib id to remove the old bib id
				} else {
					$bibId = $foundBibId;
				}
				if (!empty($foundItemId)){
					$sierraItemId = $foundItemId;
				}
			} else {
				echo "No item barcode to search for Bib id for patron $patronBarcode with hold date $holdDate\n";
				$bibId = null; // empty out the old bib id
			}
		}
		echo "</pre>\n";
		return $holdsArray;
	}

	private function sortClearviewHoldsByCreationDate(array $holdsArray) :array{
		foreach ($holdsArray as [$patronBarcode, $pickupCode, $itemBarcode, $bibId, &$holdDate, $sierraItemId]){
			$holdDate = str_replace([
				' 1:',
				' 2:',
				' 3:',
				' 4:',
				' 5:',
				' 6:',
				' 7:',
				' 8:',
				' 9:',
			], [
				' 01:',
				' 02:',
				' 03:',
				' 04:',
				' 05:',
				' 06:',
				' 07:',
				' 08:',
				' 09:',
			],  $holdDate);
			// Replace single-digit hours with double-digit version so the sorting function below works correctly
		}

			usort($holdsArray, fn($a, $b) => strcmp($a[4], $b[4]));
		return $holdsArray;
	}

	private function getDBConnection(){
		global /** @var Library $library */
		$library;
		// Use a previously set Dataobject to get to the database connection in order to run our queries
		$db = $library->getDatabaseConnection();
		return $db;
	}

	private function placeHoldsInSierra(array $holdsArray) : array{
		foreach ($holdsArray as [$patronBarcode, $pickupCode, $itemBarcode, $bibId, $holdDate, $sierraItemId, &$success, &$message]){
			if (empty($success)){ // skip any previously processed
				$success         = 0;
				$patron          = new User();
				$patron->barcode = $patronBarcode;
				if ($patron->find(true)){
					if (!empty($bibId)){
						$response = $patron->placeHold($bibId, $pickupCode);
						$success  = (int) $response['success'];
						$message  = $response['message'];
						//TODO: needs item level hold check

					} elseif (!empty($sierraItemId)){
						//TODO: need item id to place item hold;
						$response = $patron->placeItemHold(null, $sierraItemId, $pickupCode);
						$success  = (int) $response['success'];
						$message  = $response['message'];
					}
				} else {
					$message = 'Did not find user is Pika';
				}
			}
		}
		return $holdsArray;
	}

	/**
	 * @param mixed $filename
	 * @return array
	 */
	public function loadCSVintoHoldsArray(mixed $filename): array{
		$holdsArray = [];
		$lines      = file($filename, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
		foreach ($lines as $line){
			$holdsArray[] = str_getcsv($line);
		}
		return $holdsArray;
	}
}
