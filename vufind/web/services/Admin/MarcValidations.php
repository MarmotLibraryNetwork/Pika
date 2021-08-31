<?php
/*
 * Copyright (C) 2021  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 8/9/2021
 *
 */

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class MarcValidations extends ObjectEditor {

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	public function canAddNew(){
		return false;
	}

	public function canDelete(){
		return false;
	}


	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'MarcValidation';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'MarcValidations';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'Marc Validations';
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		require_once ROOT_DIR . '/sys/Indexing/MarcValidation.php';
		return MarcValidation::getObjectStructure();

		// TODO: Implement getObjectStructure() method.
	}

	function getAllObjects($orderBy = null){
		if (!empty($_REQUEST['source'])){
			/** @var DB_DataObject $object */
			$objectList     = [];
			$objectClass    = $this->getObjectType();
			$objectIdCol    = $this->getIdKeyColumn();
			$object         = new $objectClass;
			$object->source = $_REQUEST['source'];
			if ($orderBy){
				$object->orderBy($orderBy);
			}
			if ($object->find()){
				while ($object->fetch()){
					$objectList[$object->$objectIdCol] = clone $object;
				}
			}
			return $objectList;

		}
		return parent::getAllObjects($orderBy ?? 'source');
	}

	/**
	 * @inheritDoc
	 */
	function getPrimaryKeyColumn(){
		return 'id';
	}

	/**
	 * @inheritDoc
	 */
	function getIdKeyColumn(){
		return 'id';
	}
}