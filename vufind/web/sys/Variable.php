<?php

/**
 * A persistent variable defined within the system
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 4/27/14
 * Time: 2:23 PM
 */
class Variable extends DB_DataObject {
	public $__table = 'variables'; // table name
	public $id;
	public $name;
	public $value;

	/**
	 * Variable constructor. Optionally auto-fetch a variable if the name is passed in the constuctor call
	 * @param null|string $variableNameToFetch The name of the variable to fetch.
	 */
	function __construct($variableNameToFetch = null){
		if (!empty($variableNameToFetch)){
			$this->name = $variableNameToFetch;
			if ($this->find(true)){
				return $this;
			}else{
				return false;
			}
		}
	}

	static function getObjectStructure(){
		$structure = array(
			'id'          => array(
				'property'    => 'id',
				'type'        => 'hidden',
				'label'       => 'Id',
				'description' => 'The unique id of the variable.',
				'primaryKey'  => true,
				'storeDb'     => true,
			),
			'name'        => array(
				'property'    => 'name',
				'type'        => 'text',
				'label'       => 'Name',
				'description' => 'The name of the variable.',
				'maxLength'   => 255,
				'size'        => 100,
				'storeDb'     => true,
			),
			'value'       => array(
				'property'    => 'value',
				'type'        => 'text',
				'label'       => 'Value',
				'description' => 'The value of the variable',
				'storeDb'     => true,
				'maxLength'   => 255,
				'size'        => 100,
			),
			'timeDisplay' => array(
				'property'    => 'timeDisplay',
				'type'        => 'text',
				'label'       => 'Date Time',
				'description' => 'Date Time equivalent for any time stamp variables',
				'storeDb'     => false,
			),
		);
		return $structure;
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->name)){
			return $this->name;
		}
	}
} 