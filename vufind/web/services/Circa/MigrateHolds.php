<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 10/16/2019
 *
 */


class Circa_MigrateHolds extends Action {

	function launch(){
//		$this->createNewUsersFromBarCodeList();
//		$this->getBibIdFromItemBarcode();
//		$this->getBibIdFromISBN();

	}

	function createNewUsersFromBarCodeList(){
		$barCodes = array(
		);

		foreach ($barCodes as $barCode){

			/** @var Sierra $sierra */
			require_once ROOT_DIR . '/Drivers/Sierra.php';
			$sierra = CatalogFactory::getCatalogConnectionInstance('Sierra');
			$patronDump = $sierra->_getPatronDump($barCode);
			$_REQUEST['username'] = $patronDump['PATRN_NAME'];
			$_REQUEST['password'] = $barCode;
			try {
				$user = UserAccount::login();
				if ($user == false){
					echo $barCode . " Failed to build user";
				}
			} catch (UnknownAuthenticationMethodException $e){
				echo $e->getMessage();
			}

		}
	}

	function getBibIdFromItemBarcode(){
		$itemBarcodes = array(
		);

		/** @var SearchObject_Solr $searchObject */
		global $searchObject;
		foreach ($itemBarcodes as $itemBarcode){
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
												if ($itemBarcode = $itemTagBarcode){
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
//		$searchObject = SearchObjectFactory::initSearchObject();

		/** @var SearchObject_Solr $searchObject */
		global $searchObject;
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
}