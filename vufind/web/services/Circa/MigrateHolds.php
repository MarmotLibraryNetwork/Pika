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
 * Quick template of functions to aid in the migration of holds for onboarding new libraries into Marmot's circulation
 * system.
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 10/16/2019
 *
 */


class Circa_MigrateHolds extends Action {

	function launch(){
		set_time_limit(600);
//		$this->createNewUsersFromBarCodeList();
//		$this->getBibIdFromItemBarcode();
//		$this->getBibIdFromISBN();
//		$this->processDeltaCharges();
//		$this->processDeltaPatrons();
//		$this->processDeltaHolds();

	}

	function createNewUsersFromBarCodeList(){
		$barCodes = [];

		echo '<pre>';
		/** @var Pika\PatronDrivers\Sierra $sierra */
		require_once ROOT_DIR . '/CatalogConnection.php';
		$sierra = CatalogFactory::getCatalogConnectionInstance();

		foreach (array_unique($barCodes) as $barCode){
			$user = new User();
			$user->cat_password = $barCode;
			if (!$user->find()){
				$user = $sierra->findNewUser($barCode);
				if (!is_a($user, 'User')){
					echo "Failed to create user for barcode $barCode\n";
				}else{
					echo "Barcode: $barCode, User id: {$user->id}\n";
				}
			}
//			echo "'$barCode',\n";
		}
		echo '</pre>';

	}

	function getBibIdFromItemBarcode(){
		$itemBarcodes = [];

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
						$relatedRecords   = $groupedWorkDriver->getRelatedRecords();
						if (!empty($relatedRecords)){
							$matchRecordFound = false;
							require_once ROOT_DIR . '/RecordDrivers/Factory.php';
							foreach ($relatedRecords as $relatedRecord){
								if ($relatedRecord['source'] == 'ils'){
									/** @var MarcRecord $recordDriver */
									$recordDriver = $relatedRecord['driver'];
									$marcRecord = $recordDriver->getMarcRecord();

									if ($marcRecord != false){
										$itemTags = $marcRecord->getFields('989');
										foreach ($itemTags as $itemTag){
											if (!empty($itemTag->getSubfield('b'))){
												$itemTagBarcode = trim($itemTag->getSubfield('b')->getData());
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
								echo "No match found for " . $itemBarcode;
							}
						} else {
							echo "no related records for work " . $solrDoc['id'];
						}
					}else{
						echo "Found no match in index for " . $itemBarcode;
					}
				}else{
					echo "Found no match in index for " . $itemBarcode;
				}

			}
			echo "\n";

		}
	}

	function getBibIdFromISBN(){
		$ISBNs = array(
		);
		/** @var SearchObject_Solr $searchObject */
//		global $searchObject;
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();
		foreach ($ISBNs as $ISBN){
			$solrDoc = $searchObject->getRecordByIsbn(array($ISBN));
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
									echo "Had match already Additional match : ";
								}
								$matchRecordFound = true;
								echo $ISBN . "," . str_replace('ils:', '', $relatedRecord['id']) . "," . $relatedRecord['format'];
							}
						}
					}
					if (!$matchRecordFound){
						echo "No match found for " . $ISBN;
					}
				}else{
					echo "Found no match in index for " . $ISBN;
				}
			}else{
				echo "Found no match in index for " . $ISBN;
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
		$row = 1;
		$userIdAndBarcode = [];
		$userId = $itemBarcode = $holdExpires= null;
		if (($handle = fopen("all_holds.flat", "r")) !== false){
			echo '<pre>';
			while (($data = fgetcsv($handle, 0, "|")) !== false){
				$row++;

				if (strpos($data[0], 'USER_ID')){
					$userId = substr($data[1], 1);
				}
				if (strpos($data[0], 'ITEM_ID')){
					$itemBarcode = substr($data[1], 1);
				}
				if (strpos($data[0], 'HOLD_EXPIRES_DATE')){
					$holdExpires = substr($data[1], 1);
				}
				if (!empty($itemBarcode) && !empty($userId) && !empty($holdExpires)){
					$userIdAndBarcode[$userId] = $itemBarcode;
					echo $userId . ", $itemBarcode, Expires: $holdExpires". ( $holdExpires != 'NEVER' && (int) $holdExpires < 20201201 ? ' expired' : '') .  "\n";
					$userId = $itemBarcode = null;
					continue;
				}
			}
			echo '</pre>';
			fclose($handle);
		}
	}

	function processDeltaPatrons(){
		$row = 1;
		$userInfo = [
			'userId'    => '',
			'userName'  => '',
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
}
