<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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

require_once ROOT_DIR . '/sys/Search/FacetSetting.php';

class LibraryArchive2SearchFacetSetting extends FacetSetting {
	const string ISLANDORA2_FACET_INI = 'islandora2Facets';
	public $__table                   = 'library_archive_search_facet_setting';    // table name
	public $libraryId;

	static $defaultFacetList = [
		'ss_model'                => 'Model',
		'sm_format'               => 'Format',
		'sm_genre'                => 'Genre',
		'sm_legacy_resource_type' => 'Legacy Resource Type',
		'sm_collection'           => 'Archive Collection',
		'ss_library'              => 'Contributing Library',
		'sm_related_person'       => 'Related People',
		'sm_related_place'        => 'Related Places',
		'sm_related_organization' => 'Related Organizations',
		'sm_related_event'        => 'Related Events',
		'sm_linked_agent'         => 'Linked Agents',
		'sm_subject'              => 'Subject',
		'sm_subject_geographic'   => 'Subject (Geography)',
		'sm_music_genre'          => 'Music Genre',
		'sm_physical_form'        => 'Physical Form',
		'sm_research_level'       => 'Research Level',
		'sm_research_type'        => 'Research Type',
		'sm_rights_creator'       => 'Rights Creator',
		'sm_rights_holder'        => 'Rights Holder',
	];

	static function getObjectStructure($availableFacets = null){
		$libraryList = [];
		$library     = new Library();
		$library->orderBy('displayName');
		if (UserAccount::userHasRoleFromList(['libraryAdmin', 'libraryManager'])){
			$homeLibrary        = UserAccount::getUserHomeLibrary();
			$library->libraryId = $homeLibrary->libraryId;
		}
		if ($library->find()){
			while ($library->fetch()){
				$libraryList[$library->libraryId] = $library->displayName;
			}
		}

		$structure              = parent::getObjectStructure(self::getAvailableFacets());
		$structure['libraryId'] = ['property' => 'libraryId', 'type' => 'enum', 'values' => $libraryList, 'label' => 'Library', 'description' => 'The id of a library'];
		//TODO: needed? for copy facets button?

		return $structure;
	}

	function getEditLink(){
		return '/Admin/' . __CLASS__ .'?objectAction=edit&id=' . $this->id;
	}
	static public function getAvailableFacets(){
		$config          = getExtraConfigArray(self::ISLANDORA2_FACET_INI);
		$availableFacets = $config['Results'] ?? self::$defaultFacetList;
		return $availableFacets;
	}
}

