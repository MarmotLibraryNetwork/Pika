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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class LibraryFacetSettings extends ObjectEditor {

	function getObjectType(){
		return 'LibraryFacetSetting';
	}

	function getToolName(){
		return 'LibraryFacetSettings';
	}

	function getPageTitle(){
		return 'Library Facets';
	}

	function getAllObjects($orderBy = null){
		$facetsList   = [];
		$facetSetting = new LibraryFacetSetting();
		if (!empty($_REQUEST['libraryId']) && ctype_digit($_REQUEST['libraryId'])){
			$facetSetting->libraryId = $_REQUEST['libraryId'];
		}
		$facetSetting->orderBy($orderBy ?? 'weight');
		if($facetSetting->find()){
			while ($facetSetting->fetch()){
				$facetsList[$facetSetting->id] = clone $facetSetting;
			}
		}
		return $facetsList;
	}

	function getObjectStructure(){
		$structure                      = LibraryFacetSetting::getObjectStructure();
		$structure['libraryId']['type'] = 'label'; // Make LibraryId read-only for users
		return $structure;
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin'];
	}

	function canAddNew(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	function canDelete(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	function getAdditionalObjectActions($existingObject){
		$objectActions = [];
		if (isset($existingObject) && $existingObject != null){
			$objectActions[] = [
				'text' => 'Return to Library',
				'url'  => '/Admin/Libraries?objectAction=edit&id=' . $existingObject->libraryId,
			];
		}
		return $objectActions;
	}
}
