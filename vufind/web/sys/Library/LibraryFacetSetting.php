<?php
/**
 * Copyright (C) 2020  Marmot Library Network
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

require_once ROOT_DIR . '/sys/Search/FacetSetting.php';

class LibraryFacetSetting extends FacetSetting {
	public $__table = 'library_facet_setting';    // table name
	public $libraryId;

	static function getObjectStructure($availableFacets = NULL){
		$library = new Library();
		$library->orderBy('displayName');
		if (UserAccount::userHasRoleFromList(['libraryAdmin', 'libraryManager'])){
			$homeLibrary        = UserAccount::getUserHomeLibrary();
			$library->libraryId = $homeLibrary->libraryId;
		}
		$library->find();
		while ($library->fetch()){
			$libraryList[$library->libraryId] = $library->displayName;
		}

		$structure              = parent::getObjectStructure();
		$structure['libraryId'] = array('property' => 'libraryId', 'type' => 'enum', 'values' => $libraryList, 'label' => 'Library', 'description' => 'The id of a library');

		return $structure;
	}

	function getEditLink(){
		return '/Admin/LibraryFacetSettings?objectAction=edit&id=' . $this->id;
	}
}
