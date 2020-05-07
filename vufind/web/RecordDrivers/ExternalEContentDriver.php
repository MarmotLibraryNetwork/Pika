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
 * Record Driver to Handle the display of eContent that is stored in the ILS, but accessed
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/7/14
 * Time: 9:48 AM
 */

require_once ROOT_DIR . '/RecordDrivers/BaseEContentDriver.php';
class ExternalEContentDriver extends BaseEContentDriver{
//	function getValidProtectionTypes(){
//		return array('external');
//	}

//	function isItemAvailable($itemId, $totalCopies){
//		return true;
//	}
//	function isEContentHoldable($locationCode, $eContentFieldData){
//		return false;
//	}

//	function getSharing($locationCode, $eContentFieldData){
//		if (strpos($locationCode, 'mdl') === 0){
//			return 'shared';
//		}else{
//			$sharing = 'library';
//			if (count($eContentFieldData) >= 3){
//				$sharing = trim(strtolower($eContentFieldData[2]));
//			}
//			return $sharing;
//		}
//	}

//	protected function isValidProtectionType($protectionType) {
//		return in_array(strtolower($protectionType), $this->getValidProtectionTypes());
//	}

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
		$recordId = $this->getUniqueID();
		return '/' . $this->getModule() . '/' . $recordId;
	}

	function getAbsoluteUrl(){
		global $configArray;
		$recordId = $this->getUniqueID();

		return $configArray['Site']['url'] . '/' . $this->getModule() . '/' . $recordId;
	}

	function getModule(){
		return 'ExternalEContent';
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
