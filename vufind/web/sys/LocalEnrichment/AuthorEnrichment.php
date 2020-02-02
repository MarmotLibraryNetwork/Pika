<?php

/**
 * Description goes here
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 7/21/2016
 * Time: 11:36 AM
 */
class AuthorEnrichment  extends DB_DataObject{
	public $__table = 'author_enrichment';    // table name
	public $id;
	public $authorName;
	public $hideWikipedia;
	public $wikipediaUrl;

	static function getObjectStructure(){
		$structure = array(
			array(
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the enrichment information',
				'storeDb'     => true,
				'primaryKey'  => true,
			),
			array(
				'property'    => 'authorName',
				'type'        => 'text',
				'size'        => '255',
				'maxLength'   => 255,
				'label'       => 'Author Name (100ad)',
				'description' => 'The name of the author including any dates',
				'storeDb'     => true,
				'required'    => true,
			),
			array(
				'property'    => 'hideWikipedia',
				'type'        => 'checkbox',
				'label'       => 'Hide Wikipedia Information',
				'description' => 'Check to not show Wikipedia data for this author',
				'storeDb'     => true,
				'required'    => false,
			),
			array(
				'property'    => 'wikipediaUrl',
				'type'        => 'text',
				'size'        => '255',
				'maxLength'   => 255,
				'label'       => 'Wikipedia URL',
				'description' => 'The URL to load Wikipedia data from.',
				'storeDb'     => true,
				'required'    => false,
			),
		);
		return $structure;
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->authorName)){
			return $this->authorName;
		}
	}

}