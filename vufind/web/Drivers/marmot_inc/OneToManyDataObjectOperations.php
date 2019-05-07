<?php
/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/29/2019
 *
 */


trait OneToManyDataObjectOperations {

	/**
	 * This will be defined by the parent DB_DataObject class using this trait.
	 * The key ties the OneToMany Objects to their parent Object
	 *
	 * @return mixed
	 */
	abstract function keys();

	private $keyThis;  // ID field for parent Data Object
	private $keyOther; // Id Field for OneToMany Data Object

	/**
	 * This uses the keys function above to set the table key to use for searching and/or setting in
	 * the oneToMany database table
	 *
	 * @return mixed
	 */
	private function getKeyThis(){
		if (empty($this->keyThis)){
			$firstKey      = reset($this->keys());
			$this->keyThis = $firstKey;
		}
		return $this->keyThis;
	}

	/**
	 * The property of the oneToMany Object that holds the Parent Object's Id (KeyThis).
	 * This is needed to connect the oneToMany Objects to their parent.
	 *
	 * Store value at $this->keyOther
	 *
	 * @return mixed
	 */
	abstract function getKeyOther();

	/**
	 * Add or Update the oneToMany DB Objects.
	 *
	 * @param DB_DataObject[] $oneToManySettings
	 * @return void
	 */
	private function saveOneToManyOptions($oneToManySettings){
		$parentIdColumn  = $this->getKeyThis();
		$oneToManyColumn = $this->getKeyOther();
		if ($parentIdColumn && $oneToManyColumn){
			foreach ($oneToManySettings as $oneToManyDBObject){
				if (isset($oneToManyDBObject->deleteOnSave) && $oneToManyDBObject->deleteOnSave == true){
					$oneToManyDBObject->delete();
				}else{
					if (isset($oneToManyDBObject->id) && is_numeric($oneToManyDBObject->id)){ // (negative ids need processed with insert)
						$oneToManyDBObject->update();
					}else{
						$oneToManyDBObject->$oneToManyColumn = $this->$parentIdColumn;
						$oneToManyDBObject->insert();
					}
				}
			}
		}
	}

	/**
	 *  Delete any existing OneToMany Entries belonging to the parent object.
	 *
	 * @param string $oneToManyDBObjectClassName
	 * @return mixed Int (No. of rows affected) on success, false on failure, 0 on no data affected
	 */
	private function clearOneToManyOptions($oneToManyDBObjectClassName){
		$parentIdColumn  = $this->getKeyThis();
		$oneToManyColumn = $this->getKeyOther();
		if ($parentIdColumn && $oneToManyColumn){
			/** @var DB_DataObject $oneToManyDBObject */
			$oneToManyDBObject                   = new $oneToManyDBObjectClassName();
			$oneToManyDBObject->$oneToManyColumn = $this->$parentIdColumn;
			return $oneToManyDBObject->delete();
		}
	}

	/**
	 *  Fetch the existing settings for an oneToMany type data object from the database
	 *
	 * @param string      $oneToManyDBObjectClassName
	 * @param string|null $orderBy
	 * @return array
	 */
	private function getOneToManyOptions($oneToManyDBObjectClassName, $orderBy = null){
		$oneToManyOptions = array();
		$parentIdColumn   = $this->getKeyThis();
		$oneToManyColumn  = $this->getKeyOther();
		if ($parentIdColumn && $this->$parentIdColumn && $oneToManyColumn){
			/** @var DB_DataObject $oneToManyDBObject */
			$oneToManyDBObject                   = new $oneToManyDBObjectClassName();
			$oneToManyDBObject->$oneToManyColumn = $this->$parentIdColumn;
			if (!empty($orderBy)){
				$oneToManyDBObject->orderBy($orderBy);
			}
			if ($oneToManyDBObject->find()){
				while ($oneToManyDBObject->fetch()){
					$oneToManyOptions[$oneToManyDBObject->id] = clone $oneToManyDBObject;
				}
			}
		}
		return $oneToManyOptions;
	}

}