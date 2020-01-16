<?php

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 1/26/2017
 *
 */
require_once 'DB/DataObject.php';
class ArchiveExploreMoreBar extends DB_DataObject
{
	public $__table = 'library_archive_explore_more_bar';
	public $id;
	public $libraryId;
	public $section;
	public $displayName;
	public $openByDefault;
	public $weight;

	static public $archiveSections = array(
		'parentBook'           => 'Entire Book',
		'tableOfContents'      => 'Table of Contents',
		'relatedCollections'   => 'Related Archive Collections',
		'linkedCatalogRecords' => 'Librarian Picks',
		'exactEntityMatches'   => 'Related People, Places &amp; Events',
		'relatedPeople'        => 'Associated People',
		'relatedPlaces'        => 'Associated Places',
		'relatedOrganizations' => 'Associated Organizations',
		'relatedEvents'        => 'Associated Events',
		'relatedArchiveData'   => 'From the Archive',
		'relatedCatalog'       => 'More From the Catalog',
		'relatedSubjects'      => 'Related Subjects',
		'dpla'                 => 'Digital Public Library of America',
		'acknowledgements'     => 'Acknowledgements',
	);


	public static function getObjectStructure()
	{
		$structure = array(
			'id'            => array('property' => 'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of this association'),
//			'weight'        => array('property' => 'weight', 'type'=>'integer', 'label'=>'Sort', 'description'=>'The sort order of rule', 'default' => 0),
			'section'        => array('property' => 'section', 'type' => 'enum', 'label' => 'Explore More Section', 'description' => 'The section of the Explore More Bar to be displayed','hideInLists' => true,
			                          'values' => self::$archiveSections),
			'displayName'   => array('property' => 'displayName', 'type' => 'text', 'label' => 'Display Name (optional)', 'description' => 'Label for the section that will be displayed to users. If blank, the section\'s default name will be used.'),
			'openByDefault'   => array('property' => 'openByDefault', 'type' => 'checkbox', 'label' => 'Is Section Open By Default', 'description' => 'Whether or not the section will be displayed as open to users initially.','hideInLists' => true, 'default' => true),
			//			'libraryId' => array(), // hidden value or internally updated.

		);
		return $structure;
	}

	public static function getDefaultArchiveExploreMoreOptions($libraryId = -1) {
		$defaultExportMoreOptions = array();

		foreach (self::$archiveSections as $section => $sectionLabel_ignored) {
			$defaultExploreMoreOption                = new self();
			$defaultExploreMoreOption->libraryId     = $libraryId;
			$defaultExploreMoreOption->section       = $section;
			$defaultExploreMoreOption->openByDefault = 1;
			$defaultExploreMoreOption->weight        = count($defaultExportMoreOptions) + 101; //Make correspond with the oneToMany normal weighting scheme
			$defaultExportMoreOptions[] = $defaultExploreMoreOption;
		}
		return $defaultExportMoreOptions;
	}

}