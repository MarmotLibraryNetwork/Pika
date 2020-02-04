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

class LibraryArchiveSearchFacetSettings extends ObjectEditor {

	function getObjectType(){
		return 'LibraryArchiveSearchFacetSetting';
	}

	function getToolName(){
		return 'LibraryArchiveSearchFacetSettings';
	}

	function getPageTitle(){
		return 'Library Archive Search Facets';
	}

	function getAllObjects(){
		$facetsList = array();
		$library    = new LibraryArchiveSearchFacetSetting();
		if (!empty($_REQUEST['libraryId'])){
			$libraryId          = $_REQUEST['libraryId'];
			$library->libraryId = $libraryId;
		}
		$library->orderBy('weight');
		$library->find();
		while ($library->fetch()){
			$facetsList[$library->id] = clone $library;
		}

		return $facetsList;
	}

	function getObjectStructure(){
		return LibraryArchiveSearchFacetSetting::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin');
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
		$objectActions = array();
		if (isset($existingObject) && $existingObject != null){
			$objectActions[] = array(
				'text' => 'Return to Library',
				'url'  => '/Admin/Libraries?objectAction=edit&id=' . $existingObject->libraryId,
			);
		}
		return $objectActions;
	}
}
