<?php
/**
 * Record Driver to handle loading data for Hoopla Records
 *
 * @category Pika
 * @author   Mark Noble <mark@marmot.org>
 * Date: 12/18/14
 * Time: 10:50 AM
 */

require_once ROOT_DIR . '/RecordDrivers/SideLoadedRecord.php';
class HooplaRecordDriver extends SideLoadedRecord {

	/**
	 * @param $actions
	 * @return array
	 * @throws File_MARC_Exception
	 */
	public function getAccessLink($actions = null){
		$title      = translate('hoopla_url_action');
		$marcRecord = $this->getMarcRecord();
		/** @var File_MARC_Data_Field[] $linkFields */
		$linkFields = $marcRecord->getFields('856');
		$fileOrUrl  = null;
		foreach ($linkFields as $linkField){
			if ($linkField->getIndicator(1) == 4 && $linkField->getIndicator(2) == 0){
				$linkSubfield = $linkField->getSubfield('u');
				$fileOrUrl    = $linkSubfield->getData();
				break;
			}
		}
		if ($fileOrUrl != null){
			$actions[] = array(
				'url'          => $fileOrUrl,
				'title'        => $title,
				'requireLogin' => false,
			);
		}
		return $actions;
	}

	function getActions(){
		//TODO: If this is added to the related record, pass in the value
		$actions = array();

		if ($this->isHooplaIntegrationEnabled()){
			$actions[] = $this->getHooplaIntegrationActions();
		}else{
			$actions = $this->getAccessLink($actions);
		}

		return $actions;
	}

	function getRecordActions($recordAvailable = null, $recordHoldable = null, $recordBookable = null, $relatedUrls = null, $volumeData = null){
		$actions = array();

		if ($this->isHooplaIntegrationEnabled()){
			$actions[] = $this->getHooplaIntegrationActions();
		}else{
			$title = translate('hoopla_url_action');
			foreach ($relatedUrls as $url){
				$actions[] = array(
					'url'          => $url['url'],
					'title'        => $title,
					'requireLogin' => false,
				);
			}
		}

		return $actions;
	}

	public function getRecordActionsFromIndex(){
		return $this->getRecordActions();
	}

	public function getItemActions($itemInfo){
		return array();
	}

	/**
	 * Determine whether or not Hoopla Patron Integrations are enabled for the Search Library
	 * @return bool
	 */
	private function isHooplaIntegrationEnabled(){
		global $configArray;
		if (!empty($configArray['Hoopla']['HooplaAPIUser']) && !empty($configArray['Hoopla']['HooplaAPIpassword'])){
			/** @var Library $searchLibrary */
			$searchLibrary = Library::getSearchLibrary();
			if ($searchLibrary->hooplaLibraryID > 0){
				return true;
			}
		}
		return false;
	}

	/**
	 * Return action button settings array for Hoopla Patron Integrations
	 * @return array
	 */
	private function getHooplaIntegrationActions(){
		$id        = $this->getId();
		$title     = translate('hoopla_checkout_action');
		return array(
			'onclick' => "return VuFind.Hoopla.getHooplaCheckOutPrompt('$id')",
			'title'   => $title,
		);

	}
	public function getMoreDetailsOptions(){
		global $interface;

		$isbn = $this->getCleanISBN();

		//Load table of contents
		$tableOfContents = $this->getTOC();
		$interface->assign('tableOfContents', $tableOfContents);

		//Load more details options
		$moreDetailsOptions = $this->getBaseMoreDetailsOptions($isbn);
		//Other editions if applicable (only if we aren't the only record!)
		$relatedRecords = $this->getGroupedWorkDriver()->getRelatedRecords();
		if (count($relatedRecords) > 1){
			$interface->assign('relatedManifestations', $this->getGroupedWorkDriver()->getRelatedManifestations());
			$moreDetailsOptions['otherEditions'] = array(
				'label'         => 'Other Editions and Formats',
				'body'          => $interface->fetch('GroupedWork/relatedManifestations.tpl'),
				'hideByDefault' => false,
			);
		}

		$notes = $this->getNotes();
		if (count($notes) > 0){
			$interface->assign('notes', $notes);
		}

		$moreDetailsOptions['moreDetails'] = array(
			'label' => 'More Details',
			'body'  => $interface->fetch('Hoopla/view-more-details.tpl'),
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

	public function getStaffView(){
		parent::getStaffView();

		require_once ROOT_DIR . '/sys/Hoopla/HooplaExtract.php';
		$hooplaExtract = new HooplaExtract();
		$hooplaId      = HooplaDriver::recordIDtoHooplaID($this->id);
		if ($hooplaExtract->get('hooplaId', $hooplaId) == 1){
			$hooplaData = array();
			foreach ($hooplaExtract->table() as $fieldName => $value_ignored){
				$hooplaData[$fieldName] = $hooplaExtract->$fieldName;
			}
			global $interface;
			$interface->assign('hooplaExtract', $hooplaData);
		}
		return 'RecordDrivers/Hoopla/staff-view.tpl';

	}

	function getRecordUrl(){
		global $configArray;
		$recordId = $this->getUniqueID();

		return $configArray['Site']['path'] . '/Hoopla/' . $recordId;
	}

	/**
	 * Return the unique identifier of this record within the Solr index;
	 * useful for retrieving additional information (like tags and user
	 * comments) from the external MySQL database.
	 *
	 * @access  public
	 * @return  string              Unique identifier.
	 */
	public function getShortId(){
		return $this->id;
	}

	protected function getRecordType(){
		return 'hoopla';
	}


	function getNumHolds(){
		return 0;
	}

	function getFormats(){
		return $this->getFormat();
	}

}