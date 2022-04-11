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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

class Obituaries extends ObjectEditor {
	function getObjectType(){
		return 'Obituary';
	}

	function getToolName(){
		return 'Obituaries';
	}

	function getPageTitle(){
		return 'Obituaries';
	}

	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'date');
	}

	function getObjectStructure(){
		return Obituary::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return ['personId', 'source', 'date'];
	}

	function getIdKeyColumn(){
		return 'obituaryId';
	}

	function getAllowableRoles(){
		return ['genealogyContributor'];
	}

	function getRedirectLocation($curObject, $objectAction = null){
		return '/Person/' . $curObject->personId;
	}

	function getAdditionalObjectActions($existingObject){
		return [
			[
				'text' => 'Return to Person',
				'url' => '/Person/' . $existingObject->personId
			]
		];
	}

	function showReturnToList(){
		return false;
	}
}
