<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2022  Marmot Library Network
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

/*
 *  Simple abstract class extension to hold the methods common to all of the Genealogy Objects
 *
 * */
abstract class GenealogyObjectEditor extends ObjectEditor {

	function getAllowableRoles(){
		return ['genealogyContributor'];
	}

	function getRedirectLocation($curObject, $objectAction = null){
		return '/Person/' . $curObject->personId;
	}

	function getAdditionalObjectActions($existingObject){
		return empty($existingObject->personId) ? [] : [
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