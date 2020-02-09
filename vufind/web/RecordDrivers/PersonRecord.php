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

require_once ROOT_DIR . '/RecordDrivers/IndexRecord.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

/**
 * Person Record Driver
 *
 * This class is designed to handle List records.  Much of its functionality
 * is inherited from the default index-based driver.
 */
class PersonRecord extends IndexRecord
{
	/** @var Person $person */
	private $person;
	private $id;
	private $shortId;
	public function __construct($record){
		// Call the parent's constructor...
		parent::__construct($record, -1); // -1 to prevent uselessly trying to fetch a grouped work for a person record

		$this->id      = $this->getUniqueID();
		$this->shortId = substr($this->id, 6);
	}

	private function getPerson(){
		if (!isset($this->person)){
			$person = new Person();
			if ($person->get($this->shortId)){
				$this->person = $person;
			}
		}
		return $this->person;
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @param string $view          The view style for this search entry. (Only the 'list' view is applicable for genealogy searching)
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getSearchResult($view = 'list'){
		global $interface;

		$interface->assign('summId', $this->id);
		$interface->assign('summShortId', $this->shortId); //Trim the person prefix for the short id

		$person = $this->getPerson();
		if (!empty($person)){
			$interface->assign('summPicture', $person->picture);
			$interface->assign('birthDate', $person->formatPartialDate($person->birthDateDay, $person->birthDateMonth, $person->birthDateYear));
			$interface->assign('deathDate', $person->formatPartialDate($person->deathDateDay, $person->deathDateMonth, $person->deathDateYear));
			$interface->assign('lastUpdate', $person->lastModified);
			$interface->assign('dateAdded', $person->dateAdded);
			$interface->assign('numObits', count($person->obituaries));
		}

		$name   = trim($this->getName());
		$interface->assign('summTitle', $name);

		return 'RecordDrivers/Person/result.tpl';
	}

	function getBreadcrumb(){
		return $this->getName();
	}

	function getName(){
		$name = '';
		if (isset($this->fields['firstName'])){
			$name = $this->fields['firstName'];
		}
		if (isset($this->fields['middleName'])){
			$name .= ' ' . $this->fields['middleName'];
		}
		if (!empty($this->fields['nickName'])){
			$name .= ' "' . $this->fields['nickName'] . '"';
		}
		if (!empty($this->fields['maidenName'])){
			$name .= ' (' . $this->fields['maidenName'] . ')';
		}
		if (!empty($this->fields['lastName'])) {
			$name .= ' ' . $this->fields['lastName'];
		}
		return $name;
	}

	function getPermanentId() {
		return $this->shortId;
	}

	function getRecordUrl(){
		$recordId = $this->getPermanentId();
		return '/' . $this->getModule() . '/' . $recordId;
	}

	function getAbsoluteUrl(){
		global $configArray;
		$recordId = $this->getPermanentId();

		return $configArray['Site']['url'] . '/' . $this->getModule() . '/' . $recordId;
	}

	function getBookcoverUrl($size = 'small'){
		global $configArray;
		$person = $this->getPerson();
		if ($person->picture){
			return '/files/thumbnail/' . $this->person->picture;
		}else{
			return '/interface/themes/default/images/person.png';
		}
	}

	public function getModule() {
		return 'Person';
	}
}
