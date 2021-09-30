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
 * eContent Record Driver to handle data for Side Loaded collections.
 * Each side loaded collection is specified by an indexing profile stored in the database.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 12/18/14
 * Time: 10:50 AM
 */

require_once ROOT_DIR . '/RecordDrivers/BaseEContentDriver.php';
class SideLoadedRecord extends BaseEContentDriver {

	public function getMoreDetailsOptions(){
		global $interface;

		$isbn = $this->getCleanISBN();

		//Load table of contents
		$tableOfContents = $this->getTOC();
		$interface->assign('tableOfContents', $tableOfContents);

		//Get Related Records to make sure we initialize items
		$recordInfo = $this->getGroupedWorkDriver()->getRelatedRecord($this->getIdWithSource());

		//Get copies for the record
		$this->assignCopiesInformation();

		//Load more details options
		$moreDetailsOptions = $this->getBaseMoreDetailsOptions($isbn);


		if (!empty($recordInfo['itemSummary'])){
			$interface->assign('items', $recordInfo['itemSummary']);
			$moreDetailsOptions['copies'] = [
				'label'         => 'Copies',
				'body'          => $interface->fetch('ExternalEContent/view-items.tpl'),
				'openByDefault' => true,
			];
		}

		//TODO: verify this works
		$notes = $this->getNotes();
		if (!empty($notes)){
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

	/**
	 * The indexing profile source name associated with this Record
	 *
	 * @return string
	 */
	function getRecordType(){
		return $this->profileType;
	}

	function getNumHolds(){
		return 0;
	}

	function getFormats(){
		return $this->getFormat();
	}

	/**
	 * This method is strictly for Physical Records.
	 * This overwrites functionality in the Marc Record Driver
	 * @return bool
	 */
	public function hasOpacFieldMessage(){
		return false;
	}

}
