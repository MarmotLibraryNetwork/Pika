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
	public $__table = 'library_archive_search_facet_setting';    // table name
	public $libraryId;

	static $defaultFacetList = [
		'ss_name_1'               => 'Model',
		'ss_model'                => 'Model',
		'sm_name_43'              => 'Format',
		'sm_format'               => 'Format',
		'sm_name_2'               => 'Genre',
		'sm_genre'                => 'Genre',
		'sm_name_22'              => 'Legacy Resource Type',
		'sm_legacy_resource_type' => 'Legacy Resource Type',
		'sm_field_subject'        => 'Subject',
		'sm_name_3'               => 'Subject (Geography)',
		'sm_subject_geographic'   => 'Subject (Geography)',
		'sm_name_8'               => 'Related People',
		'sm_related_person'       => 'Related People',
		'sm_name_9'               => 'Related Places',
		'sm_related_place'        => 'Related Places',
		'sm_name_10'              => 'Related Organizations',
		'sm_related_organization' => 'Related Organizations',
		'sm_name_11'              => 'Related Events',
		'sm_related_event'        => 'Related Events',
		'sm_field_linked_agent'   => 'Linked Agents',
		'sm_title_2'              => 'Archive Collection',
		'sm_collection'           => 'Archive Collection',
		'ss_name_23'              => 'Contributing Library',
		'ss_library'              => 'Contributing Library',
		'sm_name_20'              => 'Music Genre',
		'sm_music_genre'          => 'Music Genre',
		'sm_field_physical_form'  => 'Physical Form',
		'sm_physical_form'        => 'Physical Form',
		'sm_name_13'              => 'Research Level',
		'sm_research_level'       => 'Research Level',
		'sm_field_research_type'  => 'Research Type',
		'sm_research_type'        => 'Research Type',
		'sm_name_21'              => 'Rights Creator',
		'sm_rights_creator'       => 'Rights Creator',
		'sm_name_17'              => 'Rights Holder',
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

