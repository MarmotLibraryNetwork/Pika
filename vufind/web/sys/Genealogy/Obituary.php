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

	function keys() {
		return array('obituaryId');
	}

	function id() {
		return $this->obituaryId;
	}

	function label() {
		return $this->source . ' ' . $this->sourcePage . ' ' . $this->date;
	}

	function getObjectStructure(){
		global $configArray;
		$storagePath = $configArray['Genealogy']['imagePath'];
		$structure = array(
			array('property' => 'obituaryId', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the obituary in the database', 'storeDb' => true),
			array('property' => 'personId', 'type' => 'hidden', 'label' => 'Person Id', 'description' => 'The id of the person this obituary is for', 'storeDb' => true),
			array('property' => 'source', 'type' => 'text', 'maxLength' => 100, 'label' => 'Source', 'description' => 'The source of the obituary', 'storeDb' => true),
			array('property' => 'sourcePage', 'type' => 'text', 'maxLength' => 100, 'label' => 'Source Page', 'description' => 'The page where the obituary was found', 'storeDb' => true),
			array('property' => 'date', 'type' => 'partialDate', 'label' => 'Date', 'description' => 'The date of the obituary.', 'storeDb' => true, 'propNameMonth' => 'dateMonth', 'propNameDay' => 'dateDay', 'propNameYear' => 'dateYear'),
			array('property' => 'contents', 'type' => 'textarea', 'rows' => 10, 'cols' => 80, 'label' => 'Full Text of the Obituary', 'description' => 'The full text of the obituary.', 'storeDb' => true, 'hideInLists' => true),
			array(
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
			),
		);
		return $structure;
	}

	function insert(){
		$ret = parent::insert();
		//Load the person this is for, and update solr
		if ($this->personId){
			require_once ROOT_DIR . '/sys/Genealogy/Person.php';
			$person           = new Person();
			$person->personId = $this->personId;
			$person->find(true);
			$person->saveToSolr();
		}
		return $ret;
	}

	function update($dataObject = false){
		$ret = parent::update();
		//Load the person this is for, and update solr
		if ($this->personId){
			require_once ROOT_DIR . '/sys/Genealogy/Person.php';
			$person           = new Person();
			$person->personId = $this->personId;
			$person->find(true);
			$person->saveToSolr();
		}
		return $ret;
	}

	function delete($useWhere = false){
		$personId = $this->personId;
		$ret      = parent::delete();
		//Load the person this is for, and update solr
		if ($personId){
			require_once ROOT_DIR . '/sys/Genealogy/Person.php';
			$person           = new Person();
			$person->personId = $this->personId;
			$person->find(true);
			$person->saveToSolr();
		}
		return $ret;
	}

	function formattedObitDate(){
		return $this->formatPartialDate($this->dateDay, $this->dateMonth, $this->dateYear);
	}
}
