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
 * Genealogy Obituary Object
 */
require_once 'DB/DataObject.php';
require_once ROOT_DIR . '/sys/Genealogy/GenealogyTrait.php';

class Obituary extends DB_DataObject {

	use GenealogyTrait;

	public $__table = 'obituary'; // table name
	public $obituaryId;
	public $personId;
	public $source;
	public $date;
	public $dateDay;
	public $dateMonth;
	public $dateYear;
	public $sourcePage;
	public $contents;
	public $picture;

	function keys(){
		return ['obituaryId'];
	}

	function id(){
		return $this->obituaryId;
	}

	function label(){
		return $this->source . ' ' . $this->sourcePage . ' ' . $this->date;
	}

	function getObjectStructure(){
		global $configArray;
		$storagePath = $configArray['Genealogy']['imagePath'];
		$structure   = [
			['property' => 'obituaryId', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the obituary in the database', 'storeDb' => true],
			['property' => 'personId', 'type' => 'hidden', 'label' => 'Person Id', 'description' => 'The id of the person this obituary is for', 'storeDb' => true],
			['property' => 'source', 'type' => 'text', 'maxLength' => 100, 'label' => 'Source', 'description' => 'The source of the obituary', 'storeDb' => true],
			['property' => 'sourcePage', 'type' => 'text', 'maxLength' => 100, 'label' => 'Source Page', 'description' => 'The page where the obituary was found', 'storeDb' => true],
			['property' => 'date', 'type' => 'partialDate', 'label' => 'Obituary Date', 'description' => 'The date of the obituary.', 'storeDb' => true, 'propNameMonth' => 'dateMonth', 'propNameDay' => 'dateDay', 'propNameYear' => 'dateYear', 'serverValidation' => 'validateObituaryDate'],
			['property' => 'contents', 'type' => 'textarea', 'rows' => 10, 'cols' => 80, 'label' => 'Full Text of the Obituary', 'description' => 'The full text of the obituary.', 'storeDb' => true, 'hideInLists' => true],
			[
				'property'    => 'picture',
				'type'        => 'image',
				'storagePath' => $storagePath,
				'thumbWidth'  => 65,
				'mediumWidth' => 250,
				'label'       => 'Picture',
				'description' => 'A scanned image of the obituary.',
				'storeDb'     => true,
				'storeSolr'   => false,
				'hideInLists' => true
			],
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
	 * Tests the validity of the obituary year compared to birth or death year
	 *
	 * @return array
	 */
	function validateObituaryDate(){
		//Setup validation return array
		$validationResults = [
			'validatedOk' => true,
			'errors'      => [],
		];

		$person = $this->getPerson();
		if (!empty($person)){
			if (!empty($this->date)){
				$obitTimeStamp = strtotime($this->date);
				if ($obitTimeStamp){
					if (!empty($person->birthDate)){
						$birthTimeStamp = strtotime($person->birthDate);
						if ($birthTimeStamp){
							if ($obitTimeStamp < $birthTimeStamp){
								$validationResults['validatedOk'] = false;
								$validationResults['errors'][]    = "Obituary date $this->date is before birth date $person->birthDate";
							}
						}
					}
					if (!empty($person->deathDate)){
						$deathTimeStamp = strtotime($person->deathDate);
						if ($deathTimeStamp){
							if ($obitTimeStamp < $deathTimeStamp){
								$validationResults['validatedOk'] = false;
								$validationResults['errors'][]    = "Obituary date $this->date is before death date $person->deathDate";
							}
						}
					}
				}
			} elseif (!empty($this->dateYear)){
				if (!empty($person->birthDateYear) && $this->dateYear < $person->birthDateYear){
					$validationResults['validatedOk'] = false;
					$validationResults['errors'][]    = "Obituary year $this->dateYear is before birth year $person->birthDateYear";
				} elseif (!empty($person->deathDateYear) && $this->dateYear < $person->deathDateYear){
					$validationResults['validatedOk'] = false;
					$validationResults['errors'][]    = "Obituary year $this->dateYear is before death year $person->deathDateYear";
				}
			}
		}
		return $validationResults;
	}

	function formattedObitDate(){
		return $this->formatPartialDate($this->dateDay, $this->dateMonth, $this->dateYear);
	}
}
