<?php
/**
 * Provides information for mapping fixed bib fields and variable item fields to MARC records when using the Sierra Export.
 *
 * User: mnoble
 * Date: 4/16/2018
 * Time: 12:17 PM
 */

class SierraExportFieldMapping extends DB_DataObject {
	public $__table = 'sierra_export_field_mapping';    // table name
	public $id;
	public $indexingProfileId;
//	public $bcode3DestinationField; //TODO: remove these obsolete columns from the table
//	public $bcode3DestinationSubfield; //TODO: remove these obsolete columns from the table
	public $callNumberExportFieldTag;
	public $callNumberPrestampExportSubfield;
	public $callNumberExportSubfield;
	public $callNumberCutterExportSubfield;
	public $callNumberPoststampExportSubfield;
	public $volumeExportFieldTag;
	public $urlExportFieldTag;
	public $eContentExportFieldTag;

	function getObjectStructure(){
		$indexingProfiles = array();
		require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
		$indexingProfile = new IndexingProfile();
		$indexingProfile->orderBy('name');
		$indexingProfile->find();
		while ($indexingProfile->fetch()){
			$indexingProfiles[$indexingProfile->id] = $indexingProfile->name;
		}
		$structure = array(
			'id'                                => array('property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id within the database'),
			'indexingProfileId'                 => array('property' => 'indexingProfileId', 'type' => 'enum', 'label' => 'Indexing Profile Id', 'description' => 'The Indexing Profile this map is associated with', 'values' => $indexingProfiles,),
			'callNumberExportFieldTag'          => array('property' => 'callNumberExportFieldTag', 'type' => 'text', 'label' => 'Item Call Number Field Tag', 'maxLength' => 1, 'description' => 'The Item Variable field tag where the call number is exported (in JSON)'),
			'callNumberPrestampExportSubfield'  => array('property' => 'callNumberPrestampExportSubfield', 'type' => 'text', 'label' => 'Item Call Number Prestamp Export Subfield', 'maxLength' => 1, 'description' => 'The subfield where the call number prestamp is exported (in JSON)'),
			'callNumberExportSubfield'          => array('property' => 'callNumberExportSubfield', 'type' => 'text', 'label' => 'Item Call Number Subfield', 'maxLength' => 1, 'description' => 'The subfield where the call number is exported (in JSON)'),
			'callNumberCutterExportSubfield'    => array('property' => 'callNumberCutterExportSubfield', 'type' => 'text', 'label' => 'Item Call Number Cutter Subfield', 'maxLength' => 1, 'description' => 'The subfield where the call number cutter is exported (in JSON)'),
			'callNumberPoststampExportSubfield' => array('property' => 'callNumberPoststampExportSubfield', 'type' => 'text', 'label' => 'Item Call Number Poststamp Subfield', 'maxLength' => 5, 'description' => 'The subfield where the call number poststamp is exported (in JSON).  Multiple can be specified.  I.e. eS is both e and S'),
			'volumeExportFieldTag'              => array('property' => 'volumeExportFieldTag', 'type' => 'text', 'label' => 'Item Volume  Field Tag', 'maxLength' => 1, 'description' => 'The Item Variable field tag where volume is exported (in JSON)'),
			'urlExportFieldTag'                 => array('property' => 'urlExportFieldTag', 'type' => 'text', 'label' => 'Item URL Field Tag', 'maxLength' => 1, 'description' => 'The Item Variable field tag where the url is exported (in JSON)'),
			'eContentExportFieldTag'            => array('property' => 'eContentExportFieldTag', 'type' => 'text', 'label' => 'Item eContent Descriptor Export Field Tag', 'maxLength' => 1, 'description' => 'The Item Variable field tag where eContent information (Marmot Only) is exported (in JSON)'),
		);
		return $structure;
	}
}