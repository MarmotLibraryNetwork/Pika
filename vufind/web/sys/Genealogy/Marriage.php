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
 * Table Definition for marriage
 */
require_once 'DB/DataObject.php';
require_once ROOT_DIR . '/sys/Genealogy/GenealogyTrait.php';

class Marriage extends DB_DataObject {

	use GenealogyTrait;

	public $__table = 'marriage';    // table name
	public $marriageId;
	public $personId;
	public $spouseName;
	public $spouseId;
	public $marriageDate;
	public $marriageDateDay;
	public $marriageDateMonth;
	public $marriageDateYear;
	public $comments;

	function keys(){
		return ['marriageId'];
	}

	function id(){
		return $this->marriageId;
	}

	function label(){
		return $this->spouseName . (isset($this->marriageDate) ? (' - ' . $this->marriageDate) : '');
	}

	static function getObjectStructure(){
		$structure = [
			['property' => 'marriageId', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the marriage in the database', 'storeDb' => true],
			['property' => 'personId', 'type' => 'hidden', 'label' => 'Person Id', 'description' => 'The id of the person this marriage is for', 'storeDb' => true],
			['property' => 'spouseName', 'type' => 'text', 'maxLength' => 100, 'label' => 'Spouse', 'description' => 'The spouse&apos;s name.', 'storeDb' => true],
			['property' => 'marriageDate', 'type' => 'partialDate', 'label' => 'Marriage Date', 'description' => 'The date of the marriage.', 'storeDb' => true, 'propNameMonth' => 'marriageDateMonth', 'propNameDay' => 'marriageDateDay', 'propNameYear' => 'marriageDateYear', 'serverValidation' => 'validateMarriageDate'],
			['property' => 'comments', 'type' => 'textarea', 'rows' => 10, 'cols' => 80, 'label' => 'Comments', 'description' => 'Information about the marriage.', 'storeDb' => true, 'hideInLists' => true],
		];
		return $structure;
	}

	function insert(){
		$ret = parent::insert();
		//Load the person this is for, and update solr
		$person = $this->getPerson();
		if (!empty($person)){
			$person->saveToSolr();
		}
		return $ret;
	}

	function update($dataObject = false){
		$ret = parent::update();
		//Load the person this is for, and update solr
		$person = $this->getPerson();
		if (!empty($person)){
			$person->saveToSolr();
		}
		return $ret;
	}

	function delete($useWhere = false){
		$ret    = parent::delete();
		//Load the person this is for, and update solr
		$person = $this->getPerson();
		if (!empty($person)){
			$person->saveToSolr();
		}
		return $ret;
	}

	/**
	 * Server Validation method for DataObjectUtil
	 *
	 * Tests the validity of the marriage year compared to birth or death year
	 *
	 * @return array
	 */
	function validateMarriageDate(){
		//Setup validation return array
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

		$person = $this->getPerson();
		if (!empty($person)){
			if (!empty($this->marriageDate)){
				$marriageTimeStamp = strtotime($this->marriageDate);
				if ($marriageTimeStamp){
					if (!empty($person->birthDate)){
						$birthTimeStamp = strtotime($person->birthDate);
						if ($birthTimeStamp){
							if ($marriageTimeStamp < $birthTimeStamp){
								$validationResults['validatedOk'] = false;
								$validationResults['errors'][]    = "Marriage date $this->marriageDate is before birth date $person->birthDate";
							}
						}
					}
					if (!empty($person->deathDate)){
						$deathTimeStamp = strtotime($person->deathDate);
						if ($deathTimeStamp){
							if ($marriageTimeStamp > $deathTimeStamp){
								$validationResults['validatedOk'] = false;
								$validationResults['errors'][]    = "Marriage date $this->marriageDate is after death date $person->deathDate";
							}
						}
					}
				}
			} elseif (!empty($this->marriageDateYear)){
				if (!empty($person->birthDateYear) && $this->marriageDateYear < $person->birthDateYear){
					$validationResults['validatedOk'] = false;
					$validationResults['errors'][]    = "Marriage year $this->marriageDateYear is before birth year $person->birthDateYear";
				} elseif (!empty($person->deathDateYear) && $this->marriageDateYear > $person->deathDateYear){
					$validationResults['validatedOk'] = false;
					$validationResults['errors'][]    = "Marriage year $this->marriageDateYear is after death year $person->deathDateYear";
				}
			}
		}
		return $validationResults;
	}

}
