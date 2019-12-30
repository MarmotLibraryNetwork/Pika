<?php
/**
 * Table Definition for marriage
 */
require_once 'DB/DataObject.php';
//require_once 'DB/DataObject/Cast.php';

class Marriage extends DB_DataObject {
	public $__table = 'marriage';    // table name
	public $marriageId;
	public $personId;
	public $spouseName;
	public $spouseId;
	public $marriageDate;
	public $comments;

	function keys(){
		return array('marriageId');
	}

	function id(){
		return $this->marriageId;
	}

	function label(){
		return $this->spouseName . (isset($this->marriageDate) ? (' - ' . $this->marriageDate) : '');
	}

	function getObjectStructure(){
		$structure = array(
			array('property' => 'marriageId', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the marriage in the database', 'storeDb' => true),
			array('property' => 'personId', 'type' => 'hidden', 'label' => 'Person Id', 'description' => 'The id of the person this marriage is for', 'storeDb' => true),
			//array('property'=>'person', 'type'=>'method', 'label'=>'Person', 'description'=>'The person this obituary is for', 'storeDb' => false),
			array('property' => 'spouseName', 'type' => 'text', 'maxLength' => 100, 'label' => 'Spouse', 'description' => 'The spouse&apos;s name.', 'storeDb' => true),
			array('property' => 'marriageDate', 'type' => 'partialDate', 'label' => 'Date', 'description' => 'The date of the marriage.', 'storeDb' => true, 'propNameMonth' => 'marriageDateMonth', 'propNameDay' => 'marriageDateDay', 'propNameYear' => 'marriageDateYear'),
			array('property' => 'comments', 'type' => 'textarea', 'rows' => 10, 'cols' => 80, 'label' => 'Comments', 'description' => 'Information about the marriage.', 'storeDb' => true, 'hideInLists' => true),
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

	function update(){
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

	function delete(){
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
}