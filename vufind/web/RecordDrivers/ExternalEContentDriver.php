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

/**
 * Record Driver to Handle the display of eContent that is stored in the ILS, but accessed
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/7/14
 * Time: 9:48 AM
 */

require_once ROOT_DIR . '/RecordDrivers/BaseEContentDriver.php';

class ExternalEContentDriver extends BaseEContentDriver {

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

		$moreDetailsOptions['copies'] = [
			'label'         => 'Copies',
			'body'          => $interface->fetch('ExternalEContent/view-items.tpl'),
			'openByDefault' => true,
		];

		$notes = $this->getNotes();
		if (count($notes) > 0){
			$interface->assign('notes', $notes);
		}

		$moreDetailsOptions['moreDetails'] = [
			'label' => 'More Details',
			'body'  => $interface->fetch('ExternalEContent/view-more-details.tpl'),
		];

		$this->loadSubjects();
		$moreDetailsOptions['subjects']  = [
			'label' => 'Subjects',
			'body'  => $interface->fetch('Record/view-subjects.tpl'),
		];
		$moreDetailsOptions['citations'] = [
			'label' => 'Citations',
			'body'  => $interface->fetch('Record/cite.tpl'),
		];
		if ($interface->getVariable('showStaffView')){
			$moreDetailsOptions['staff'] = [
				'label' => 'Staff View',
				'body'  => $interface->fetch($this->getStaffView()),
			];
		}

		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	/**
	 * The indexing profile source name associated with this Record
	 *
	 * @return string
	 */
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

	private $format;

	/**
	 * Load the format for the record based off of information stored within the grouped work.
	 * Which was calculated at index time.
	 *
	 * @return string[]
	 */
	function getFormat(){
		if (empty($this->format)){
			//Rather than loading formats here, let's leverage the work we did at index time
			$recordDetails = $this->getGroupedWorkDriver()->getSolrField('record_details');
			if ($recordDetails){
				if (!is_array($recordDetails)){
					$recordDetails = [$recordDetails];
				}
				foreach ($recordDetails as $recordDetailRaw){
					$recordDetail    = explode('|', $recordDetailRaw);
					$idWithOutPrefix = str_replace('external_econtent:', '', $recordDetail[0]);
					if ($idWithOutPrefix == $this->getIdWithSource()){
						$this->format = [$recordDetail[1]];
						return $this->format;
					}
				}
			}
			//We did not find a record for this in the index.  It's probably been deleted.
			$this->format = ['Unknown'];
		}
		return $this->format;
	}

}
