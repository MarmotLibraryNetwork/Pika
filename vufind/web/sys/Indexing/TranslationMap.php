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
 * A container to hold information about Translation Maps to allow for multiple data sources and provide for updates without code changes
 *
 * @category VuFind-Plus-2014 
 * @author Mark Noble <mark@marmot.org>
 * Date: 6/30/2015
 * Time: 1:44 PM
 */

require_once ROOT_DIR . '/sys/Indexing/TranslationMapValue.php';
class TranslationMap extends DB_DataObject{
	public $__table = 'translation_maps';    // table name

	public $id;
	public $indexingProfileId;
	public $name;
	public $usesRegularExpressions;

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
			'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id within the database'),
			'indexingProfileId' => array('property' => 'indexingProfileId', 'type' => 'enum', 'values' => $indexingProfiles, 'label' => 'Indexing Profile Id', 'description' => 'The Indexing Profile this map is associated with'),
			'name' => array('property'=>'name', 'type'=>'text', 'label'=>'Name', 'description'=>'The name of the translation map', 'maxLength' => '50', 'required' => true),
			'usesRegularExpressions' => array('property'=>'usesRegularExpressions', 'type'=>'checkbox', 'label'=>'Use Regular Expressions', 'description'=>'When on, values will be treated as regular expressions', 'hideInLists' => false, 'default'=>false),

			'translationMapValues' => array(
				'property' => 'translationMapValues',
				'type'=> 'oneToMany',
				'label' => 'Values',
				'description' => 'The values for the translation map.',
				'keyThis' => 'id',
				'keyOther' => 'translationMapId',
				'subObjectType' => 'TranslationMapValue',
				'structure' => TranslationMapValue::getObjectStructure(),
				'sortable' => false,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
			),
		);
		return $structure;
	}

	public function __get($name){
		if ($name == "translationMapValues") {
			if (!isset($this->translationMapValues)){
				//Get the list of translation maps
				if ($this->id){
					$this->translationMapValues = array();
					$value = new TranslationMapValue();
					$value->translationMapId = $this->id;
					$value->orderBy('value ASC');
					$value->find();
					while($value->fetch()){
						$this->translationMapValues[$value->id] = clone($value);
					}
				}
			}
			return $this->translationMapValues;
		}
		return null;
	}

	public function __set($name, $value){
		if ($name == "translationMapValues") {
			$this->translationMapValues = $value;
		}
	}

	/**
	 * Override the update functionality to save the associated translation maps
	 *
	 * @see DB/DB_DataObject::update()
	 */
	public function update($dataObject = false){
		$ret = parent::update();
		if ($ret === FALSE ){
			return $ret;
		}else{
			$this->saveMapValues();
		}
		return true;
	}

	/**
	 * Override the update functionality to save the associated translation maps
	 *
	 * @see DB/DB_DataObject::insert()
	 */
	public function insert(){
		$ret = parent::insert();
		if ($ret === FALSE ){
			return $ret;
		}else{
			$this->saveMapValues();
		}
		return true;
	}

	public function saveMapValues(){
		if (isset ($this->translationMapValues)){
			foreach ($this->translationMapValues as $value){
				if (isset($value->deleteOnSave) && $value->deleteOnSave == true){
					$value->delete();
				}else{
					if (isset($value->id) && is_numeric($value->id)){
						$value->update();
					}else{
						$value->translationMapId = $this->id;
						$value->insert();
					}
				}
			}
			//Clear the translation maps so they are reloaded the next time
			unset($this->translationMapValues);
		}
	}

	public function getEditLink(){
		return '/Admin/TranslationMaps?objectAction=edit&id=' . $this->id;
	}

	public function mapValue($valueToMap){
		/** @var TranslationMapValue[] $value */
		$default = null;
		foreach ($this->translationMapValues as $value){
			if ($value->value == '#'){
				$default = $value->translation;
			}else{
				if ($this->usesRegularExpressions){
					if (preg_match('/' . $value->value . '/', $valueToMap)){
						return $valueToMap;
					}else{
						if (strcasecmp($value, $valueToMap)){
							return $valueToMap;
						}
					}
				}
			}
		}
		if ($default){
			return $default;
		}
	}
}
