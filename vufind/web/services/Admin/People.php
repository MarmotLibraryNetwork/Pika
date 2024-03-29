<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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

require_once ROOT_DIR . '/services/Admin/GenealogyObjectEditor.php';

class People extends GenealogyObjectEditor {
	function getObjectType(){
		return 'Person';
	}

	function getToolName(){
		return 'People';
	}

	function getPageTitle(){
		return 'People';
	}

	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'lastName, firstName');
	}

	function getObjectStructure(){
		$person = new Person();
		return $person->getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return ['lastName', 'firstName', 'middleName', 'birthDate'];
	}

	function getIdKeyColumn(){
		return 'personId';
	}

	function getRedirectLocation($curObject, $objectAction = null){
		if ($objectAction == 'delete'){
			return '/Union/Search?searchSource=genealogy&lookfor=&genealogyType=GenealogyName';
		}elseif (!empty( $curObject->personId)){
			return '/Person/' . $curObject->personId;
		} else {
			return null;
		}
	}

}
