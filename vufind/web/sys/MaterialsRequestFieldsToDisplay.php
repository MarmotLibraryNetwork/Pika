<?php

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 12/12/2016
 *
 */
require_once 'DB/DataObject.php';
class MaterialsRequestFieldsToDisplay extends DB_DataObject
{
	public $__table = 'materials_request_fields_to_display';
	public $id;
	public $libraryId;
	public $columnNameToDisplay;
	public $labelForColumnToDisplay;
	public $weight;

	static function getObjectStructure(){
		$materialsRequest = new MaterialsRequest();
		$columnNames        = array_keys($materialsRequest->table());
		$columnToChooseFrom = array_combine($columnNames, $columnNames);

		//specialFormat Fields get handled specially
		unset(
			$columnToChooseFrom['abridged'],
			$columnToChooseFrom['magazineDate'],
			$columnToChooseFrom['magazineNumber'],
			$columnToChooseFrom['magazinePageNumbers'],
			$columnToChooseFrom['magazineTitle'],
			$columnToChooseFrom['magazineVolume'],
			$columnToChooseFrom['season']
	);

		$structure = array(
			'id'                      => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of this association'),
			'weight'                  => array('property'=>'weight', 'type'=>'integer', 'label'=>'Weight', 'description'=>'The sort order of rule', 'default' => 0),
		  'columnNameToDisplay'     => array('property' => 'columnNameToDisplay', 'type' => 'enum', 'label' => 'Name of Column to Display', 'values' => $columnToChooseFrom, 'description' => 'Name of the database column to list in the main table of the Manage Requests Page'),
			'labelForColumnToDisplay' => array('property' => 'labelForColumnToDisplay', 'type' => 'text', 'label' => 'Display Label', 'description' => 'Label to put in the table header of the Manage Requests page.'),
//			'libraryId' => array(), // hidden value or internally updated.

		);
		return $structure;
	}
}