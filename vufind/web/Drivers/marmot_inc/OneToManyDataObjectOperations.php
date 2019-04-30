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

	private $mainKeyName;

	/**
	 * This uses the keys function above to set the table key to use for searching and/or setting in
	 * the oneToMany database table
	 *
	 * @return mixed
	 */
	public function getMainKeyName(){
		if (empty($this->mainKeyName)){
			$firstKey          = reset($this->keys());
			$this->mainKeyName = $firstKey;
		}
		return $this->mainKeyName;
	}

	/**
	 * Add or Update the oneToMany DB Objects.
	 *
	 * @param DB_DataObject[] $oneToManySettings
	 * @return void
	 */
	private function saveOneToManyOptions($oneToManySettings){
		$mainKeyName = $this->getMainKeyName();
		if ($mainKeyName){
			foreach ($oneToManySettings as $oneToManyDBObject){
				if (isset($oneToManyDBObject->deleteOnSave) && $oneToManyDBObject->deleteOnSave == true){
					$oneToManyDBObject->delete();
				}else{
					if (isset($oneToManyDBObject->id) && is_numeric($oneToManyDBObject->id)){ // (negative ids need processed with insert)
						$oneToManyDBObject->update();
					}else{
						$oneToManyDBObject->$mainKeyName = $this->$mainKeyName;
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
	 * @return void
	 */
	private function clearOneToManyOptions($oneToManyDBObjectClassName){
		$mainKeyName = $this->getMainKeyName();
		if ($mainKeyName){
			$oneToManyDBObject               = new $oneToManyDBObjectClassName();
			$oneToManyDBObject->$mainKeyName = $this->$mainKeyName;
			$oneToManyDBObject->delete();
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
		$mainKeyName      = $this->getMainKeyName();
		if ($mainKeyName && $this->$mainKeyName){
			/** @var DB_DataObject $oneToManyDBObject */
			$oneToManyDBObject               = new $oneToManyDBObjectClassName();
			$oneToManyDBObject->$mainKeyName = $this->$mainKeyName;
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