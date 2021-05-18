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
 * Record Driver to handle loading data for Hoopla Records
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
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
		$marcRecord = $this->getMarcRecord();
		if (!empty($marcRecord)){
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
				$title     = translate('hoopla_url_action');
				$actions[] = [
					'url'          => $fileOrUrl,
					'title'        => $title,
					'requireLogin' => false,
				];
			}
		}
		return $actions;
	}

	function getActions(){
		//TODO: If this is added to the related record, pass in the value
		$actions = [];

		if ($this->isHooplaIntegrationEnabled()){
			$actions[] = $this->getHooplaIntegrationActions();
		}else{
			$actions = $this->getAccessLink($actions);
		}

		return $actions;
	}

	function getRecordActions($recordAvailable = null, $recordHoldable = null, $recordBookable = null, $relatedUrls = null, $volumeData = null){
		return $this->getActions();
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
		return [
			'onclick' => "return Pika.Hoopla.getHooplaCheckOutPrompt('$id')",
			'title'   => $title,
		];

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

		//Get copies for the record
		$this->assignCopiesInformation();

//		$moreDetailsOptions['copies'] = array(
//			'label'         => 'Copies',
//			'body'          => $interface->fetch('ExternalEContent/view-items.tpl'),
//			'openByDefault' => true,
//		);

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
		global $interface;

		require_once ROOT_DIR . '/sys/Hoopla/HooplaExtract.php';
		$hooplaId      = HooplaDriver::recordIDtoHooplaID($this->sourceAndId, $this);
		$hooplaExtract = new HooplaExtract();
		$success       = $hooplaExtract->get('hooplaId', $hooplaId);
		if ($success == 1){
			$hooplaData = [];
			foreach ($hooplaExtract->table() as $fieldName => $value_ignored){
				$hooplaData[$fieldName] = $hooplaExtract->$fieldName;
			}
			$interface->assign('hooplaExtract', $hooplaData);
		}
		if (!strpos($this->sourceAndId, $hooplaId)){
			$interface->assign('matchedByAccessUrl', true);
		}

		return 'RecordDrivers/Marc/staff-view.tpl';
	}

	function getRecordUrl(){
		$recordId = $this->getUniqueID();
		return '/Hoopla/' . $recordId;
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

	function getRecordType(){
		return 'hoopla';
	}


	function getNumHolds(){
		return 0;
	}

	function getFormats(){
		return $this->getFormat();
	}

}
