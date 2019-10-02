<?php
/**
 * The primary identifier for a particular record in the database.
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/6/13
 * Time: 9:51 AM
 */

class GroupedWorkPrimaryIdentifier extends DB_DataObject{
	public $__table = 'grouped_work_primary_identifiers';    // table name

	public $id;
	public $grouped_work_id;
	public $type;
	public $identifier;


	/**
	 *  Return the SourceAndId object used for handling specific records
	 *
	 * @return SourceAndId|null
	 */
	public function getSourceAndId(){
		if (!empty($this->type) && !empty($this->identifier)){
			require_once ROOT_DIR . '/services/SourceAndId.php';
			return new SourceAndId($this->type . ':' . $this->identifier);
		}
		return null;
	}

}