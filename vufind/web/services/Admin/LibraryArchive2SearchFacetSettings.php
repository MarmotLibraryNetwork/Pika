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

class LibraryArchive2SearchFacetSettings extends ObjectEditor {

	function getObjectType(){
		return 'LibraryArchive2SearchFacetSetting';
	}

	function getToolName(){
		return 'LibraryArchive2SearchFacetSettings';
	}

	function getPageTitle(){
		return 'Library Archive Search Facets';
	}

	function getAllObjects($orderBy = null){
		$facetsList   = [];
		$facetSetting = new LibraryArchive2SearchFacetSetting();
		if (!empty($_REQUEST['libraryId']) && ctype_digit($_REQUEST['libraryId'])){
			$facetSetting->libraryId = $_REQUEST['libraryId'];
		}
		$facetSetting->orderBy($orderBy ?? 'weight');
		if ($facetSetting->find()){
			while ($facetSetting->fetch()){
				$facetsList[$facetSetting->id] = clone $facetSetting;
			}
		}
		return $facetsList;
	}

	function getObjectStructure(){
		$structure                      = LibraryArchive2SearchFacetSetting::getObjectStructure();
		$structure['libraryId']['type'] = 'label'; // Make LibraryId read-only for user
		// Remove unused settings
		unset($structure['showAboveResults']);
		unset($structure['showInAdvancedSearch']);
		unset($structure['showInAuthorResults']);
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
		UserAccount::getLoggedInUser(); // Populate for fetching roles
		return UserAccount::userHasRole('opacAdmin');
	}

	function canDelete(){
		UserAccount::getLoggedInUser();  // Populate for fetching roles
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
