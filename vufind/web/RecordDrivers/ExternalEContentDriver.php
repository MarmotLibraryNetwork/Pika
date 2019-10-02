<?php
/**
 * Record Driver to Handle the display of eContent that is stored in the ILS, but accessed
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 2/7/14
 * Time: 9:48 AM
 */

require_once ROOT_DIR . '/RecordDrivers/BaseEContentDriver.php';
class ExternalEContentDriver extends BaseEContentDriver{
	function getValidProtectionTypes(){
		return array('external');
	}

	function isItemAvailable($itemId, $totalCopies){
		return true;
	}
	function isEContentHoldable($locationCode, $eContentFieldData){
		return false;
	}
	// This function is not called anywhere. pascal 7-17-2018
//	function isLocalItem($locationCode, $eContentFieldData){
//		return $this->isLibraryItem($locationCode, $eContentFieldData);
//	}

	// This function is not called anywhere. pascal 7-17-2018
//	function isLibraryItem($locationCode, $eContentFieldData){
//		$sharing = $this->getSharing($locationCode, $eContentFieldData);
//		if ($sharing == 'shared'){
//			return true;
//		}else if ($sharing == 'library'){
//			$searchLibrary = Library::getSearchLibrary();
//			if ($searchLibrary == null
//				|| $searchLibrary->econtentLocationsToInclude == 'all'
//				|| strlen($searchLibrary->econtentLocationsToInclude) == 0
//				|| $searchLibrary->includeOutOfSystemExternalLinks
//				|| (strlen($searchLibrary->ilsCode) > 0 && strpos($locationCode, $searchLibrary->ilsCode) === 0)){
//				// TODO: econtentLocationsToInclude setting no longer in use. plb 5-17-2016
//				// TODO: I think using the ilsCode here is obsolete also pascal 7-17-2018
//				return true;
//			}else{
//				return false;
//			}
//		}else{
//			$searchLibrary = Library::getSearchLibrary();
//			$searchLocation = Location::getSearchLocation();
//			if ($searchLibrary == null || $searchLibrary->includeOutOfSystemExternalLinks || strpos($locationCode, $searchLocation->code) === 0){
//				return true;
//			}else{
//				return false;
//			}
//		}
//	}

	function isValidForUser($locationCode, $eContentFieldData){
		$sharing = $this->getSharing($locationCode, $eContentFieldData);
		if ($sharing == 'shared'){
			$searchLibrary = Library::getSearchLibrary();
			if ($searchLibrary == null || $searchLibrary->econtentLocationsToInclude == 'all' || strlen($searchLibrary->econtentLocationsToInclude) == 0 || (strpos($searchLibrary->econtentLocationsToInclude, $locationCode) !== FALSE)){
				return true;
			}else{
				return false;
			}
		}else if ($sharing == 'library'){
			$searchLibrary = Library::getSearchLibrary();
			if ($searchLibrary == null || $searchLibrary->includeOutOfSystemExternalLinks || (strlen($searchLibrary->ilsCode) > 0 && strpos($locationCode, $searchLibrary->ilsCode) === 0)){
				return true;
			}else{
				return false;
			}
		}else{
			$searchLibrary = Library::getSearchLibrary();
			$searchLocation = Location::getSearchLocation();
			if ($searchLibrary->includeOutOfSystemExternalLinks || strpos($locationCode, $searchLocation->code) === 0){
				return true;
			}else{
				return false;
			}
		}
	}

	function getSharing($locationCode, $eContentFieldData){
		if (strpos($locationCode, 'mdl') === 0){
			return 'shared';
		}else{
			$sharing = 'library';
			if (count($eContentFieldData) >= 3){
				$sharing = trim(strtolower($eContentFieldData[2]));
			}
			return $sharing;
		}
	}

	protected function isValidProtectionType($protectionType) {
		return in_array(strtolower($protectionType), $this->getValidProtectionTypes());
	}

	public function getMoreDetailsOptions(){
		global $interface;

		$isbn = $this->getCleanISBN();

		//Load table of contents
		$tableOfContents = $this->getTOC();
		$interface->assign('tableOfContents', $tableOfContents);

		//Get Related Records to make sure we initialize items
		$recordInfo = $this->getGroupedWorkDriver()->getRelatedRecord('external_econtent:' . $this->getIdWithSource());

		$interface->assign('items', $recordInfo['itemSummary']);

		//Load more details options
		$moreDetailsOptions = $this->getBaseMoreDetailsOptions($isbn);

		$moreDetailsOptions['copies'] = array(
			'label'         => 'Copies',
			'body'          => $interface->fetch('ExternalEContent/view-items.tpl'),
			'openByDefault' => true,
		);

		$notes = $this->getNotes();
		if (count($notes) > 0){
			$interface->assign('notes', $notes);
		}

		$moreDetailsOptions['moreDetails'] = array(
			'label' => 'More Details',
			'body'  => $interface->fetch('ExternalEContent/view-more-details.tpl'),
		);

		$this->loadSubjects();
		$moreDetailsOptions['subjects']  = array(
			'label' => 'Subjects',
			'body'  => $interface->fetch('Record/view-subjects.tpl'),
		);
		$moreDetailsOptions['citations'] = array(
			'label' => 'Citations',
			'body'  => $interface->fetch('Record/cite.tpl'),
		);
		if ($interface->getVariable('showStaffView')){
			$moreDetailsOptions['staff'] = array(
				'label' => 'Staff View',
				'body'  => $interface->fetch($this->getStaffView()),
			);
		}

		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	function getRecordType(){
		return $this->profileType;
	}

	function getRecordUrl(){
		global $configArray;
		$recordId = $this->getUniqueID();

		return $configArray['Site']['path'] . '/' . $this->getModule() . '/' . $recordId;
	}

	function getAbsoluteUrl(){
		global $configArray;
		$recordId = $this->getUniqueID();

		return $configArray['Site']['url'] . '/' . $this->getModule() . '/' . $recordId;
	}

	function getModule(){
		return 'ExternalEContent';
	}

	function getFormats(){
		global $configArray;
		//TODO: use indexing profile settings
		$formats = array();
		//Get the format based on the iType
		$itemFields = $this->getMarcRecord()->getFields('989');
		/** @var File_MARC_Data_Field[] $itemFields */
		foreach ($itemFields as $itemField){
			$locationCode = trim($itemField->getSubfield('d') != null ? $itemField->getSubfield('d')->getData() : '');
			$eContentData = trim($itemField->getSubfield('w') != null ? $itemField->getSubfield('w')->getData() : '');
			if ($eContentData && strpos($eContentData, ':') > 0){
				$eContentFieldData = explode(':', $eContentData);
				$protectionType = trim($eContentFieldData[1]);
				if ($this->isValidProtectionType($protectionType)){
					if ($this->isValidForUser($locationCode, $eContentFieldData)){
						$iTypeField = $itemField->getSubfield($configArray['Reindex']['iTypeSubfield'])->getData();
						$format = mapValue('econtent_itype_format', $iTypeField);
						$formats[$format] = $format;
					}
				}
			}
		}
		return $formats;
	}
	//TODO: doesn't get used, should it?
	function getEContentFormat($fileOrUrl, $iType){
		return mapValue('econtent_itype_format', $iType);
	}

//	/**
//	 * @param string $itemId
//	 * @param string $fileOrUrl
//	 * @param string|null $acsId
//	 * @return array
//	 */
//	function getActionsForItem($itemId, $fileOrUrl, $acsId){
//		$actions = array();
//		$title   = translate('externalEcontent_url_action');
//		if (!empty($fileOrUrl)){
//			if (strlen($fileOrUrl) >= 3){
//				$extension = strtolower(substr($fileOrUrl, strlen($fileOrUrl), 3));
//				if ($extension == 'pdf'){
//					$title = 'Access PDF';
//				}
//			}
//			$actions[] = array(
//				'url'          => $fileOrUrl,
//				'title'        => $title,
//				'requireLogin' => false,
//			);
//		}
//
//		return $actions;
//	}


}
