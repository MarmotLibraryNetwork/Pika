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

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 2/10/2017
 *
 */
require_once ROOT_DIR . '/sys/Library/LibraryFacetSetting.php';

class LibraryArchiveSearchFacetSetting extends LibraryFacetSetting {
	public $__table = 'library_archive_search_facet_setting';    // table name
	public $libraryId;

	static $defaultFacetList = [
		'mods_subject_topic_ms'                                          => 'Subject',
		'mods_genre_s'                                                   => 'Type',
		'RELS_EXT_isMemberOfCollection_uri_ms'                           => 'Archive Collection',
		'mods_extension_marmotLocal_relatedEntity_person_entityTitle_ms' => 'Related People',
		'mods_extension_marmotLocal_relatedEntity_place_entityTitle_ms'  => 'Related Places',
		'mods_extension_marmotLocal_relatedEntity_event_entityTitle_ms'  => 'Related Events',
		'mods_extension_marmotLocal_describedEntity_entityTitle_ms'      => 'Described Entity',
		'mods_extension_marmotLocal_picturedEntity_entityTitle_ms'       => 'Pictured Entity',
		'namespace_s'                                                    => 'Contributing Library',
//		'ancestors_ms'                                                   => "Included In"
	];

	function getEditLink(){
		return '/Admin/LibraryArchiveSearchFacetSettings?objectAction=edit&id=' . $this->id;
	}

	/**
	 * @return string[]
	 */
	static function getAvailableFacets(){
		$config = getExtraConfigArray('islandoraFacets');
		return $config['Results'] ?? self::$defaultFacetList;
	}
}


