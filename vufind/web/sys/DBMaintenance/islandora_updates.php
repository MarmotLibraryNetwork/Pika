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

/**
 * Updates related to Islandora for cleanliness
 *
 */

function getIslandoraUpdates(): array{


	// Array Entry Template
//		'[release-number]_[update-order-#-if-needed]_[unique-update-key-name]' => [
//			'release'         => '[release-number/git-branch]',
//			'title'           => 'Title of Update',
//			'description'     => 'Description of what the updates are.',
//			'continueOnError' => false,
//			'sql'             => [
//				'[SQL]',
//				'[nameOfFunctionToRun]'
//			]
//		],


	return [

		'Islandora2_convert_collectionsToHide_pids_to_nodeIds' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'title'           => 'Convert collectionsToHide PIDs to nodeIds',
			'description'     => 'Converts library.collectionsToHide entries from legacy Islandora PID format (namespace:id) to plain integer nodeIds.',
			'continueOnError' => false,
			'sql'             => [
				'convertCollectionsToHidePidsToNodeIds'
			]
		],

		'Islandora2_convert_privateCollections_pids_to_nodeIds' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'title'           => 'Convert archive_private_collections PIDs to nodeIds',
			'description'     => 'Converts archive_private_collections.privateCollections entries from legacy Islandora PID format (namespace:id) to plain integer nodeIds.',
			'continueOnError' => false,
			'sql'             => [
				'convertPrivateCollectionsPidsToNodeIds'
			]
		],

		'Islandora2_library_archive_search_facet_setting_migration' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'title'           => 'Migrate Archive Search Facet Settings to Islandora2',
			'description'     => 'DONT RUN TILL FACET CONFIGURATION DONE; Updates facetName values in library_archive_search_facet_setting from legacy Islandora (MODS) field names to their Islandora2 Solr field equivalents.',
			'continueOnError' => true,
			'sql'             => [
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_field_subject' WHERE facetName = 'mods_subject_topic_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_name_2' WHERE facetName = 'mods_genre_s';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_title_2' WHERE facetName = 'RELS_EXT_isMemberOfCollection_uri_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_name_8' WHERE facetName = 'mods_extension_marmotLocal_relatedEntity_person_entityTitle_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_name_9' WHERE facetName = 'mods_extension_marmotLocal_relatedEntity_place_entityTitle_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_name_11' WHERE facetName = 'mods_extension_marmotLocal_relatedEntity_event_entityTitle_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'ss_name_23' WHERE facetName = 'namespace_s';",
				// TODO: Determine Islandora2 equivalent for 'Described Entity' and update the statement below
				//"UPDATE library_archive_search_facet_setting SET facetName = 'ISLANDORA2_EQUIVALENT' WHERE facetName = 'mods_extension_marmotLocal_describedEntity_entityTitle_ms';",
				// TODO: Determine Islandora2 equivalent for 'Pictured Entity' and update the statement below
				//"UPDATE library_archive_search_facet_setting SET facetName = 'ISLANDORA2_EQUIVALENT' WHERE facetName = 'mods_extension_marmotLocal_picturedEntity_entityTitle_ms';",
				// TODO: Determine Islandora2 equivalent for 'Included In' (ancestors_ms) and update the statement below
				//"UPDATE library_archive_search_facet_setting SET facetName = 'ISLANDORA2_EQUIVALENT' WHERE facetName = 'ancestors_ms';",
			]
		],

	];
}

// Functions definitions that get executed by any of the updates above

function convertPrivateCollectionsPidsToNodeIds(): bool {
	// Populate this array: key = legacy PID, value = nodeId integer
	$pidToNodeId = [
		// 'namespace:1234' => 5678,
		'cmc:3'           => 149225,
		// These fortlewis items are objects, not collections, that are also included in the collections to hide setting for the library
		'fortlewis:12699' => 35830,
		'fortlewis:12700' => 35836,
		'fortlewis:12701' => 35833,
		'fortlewis:12702' => 35832,
		'fortlewis:12703' => 35835,
	];

	require_once ROOT_DIR . '/sys/Archive/ArchivePrivateCollection.php';
	$collection = new ArchivePrivateCollection();
	$collection->find(true); // single row

	if (empty($collection->privateCollections)) {
		return true;
	}

	$entries   = explode("\n", $collection->privateCollections);
	$converted = array_map(function ($entry) use ($pidToNodeId) {
		$trimmed = trim($entry);
		return isset($pidToNodeId[$trimmed])
			? (string) $pidToNodeId[$trimmed]
			: $trimmed;
	}, $entries);

	$newValue = implode("\n", $converted);
	if ($newValue === $collection->privateCollections) {
		return true;
	}

	$collection->privateCollections = $newValue;
	return $collection->update() !== false;
}

function convertCollectionsToHidePidsToNodeIds(): bool {
	// Populate this array: key = legacy PID, value = nodeId integer
	$pidToNodeId = [
		'adams:12'        => 680,
		'ccu:2'           => 149191,
		'evld:5206'       => 18524,
		'evld:5208'       => 18523,
		'evld:5958'       => 18533,
		'fortlewis:12699' => 35830,
		'fortlewis:12700' => 35836,
		'fortlewis:12701' => 35833,
		'fortlewis:12702' => 35832,
		'fortlewis:12703' => 35835,
		'salida:555'      => 49867,
	];

	require_once ROOT_DIR . '/sys/Library/Library.php';
	$library = new Library();
	$library->whereAdd('collectionsToHide IS NOT NULL');
	$library->whereAdd("collectionsToHide != ''");
	$library->find();

	$success = true;
	while ($library->fetch()) {
		$entries   = explode("\r\n", $library->collectionsToHide);
		$converted = array_map(function ($entry) use ($pidToNodeId) {
			$trimmed = trim($entry);
			return isset($pidToNodeId[$trimmed])
				? (string) $pidToNodeId[$trimmed]
				: $trimmed;
		}, $entries);

		$newValue = implode("\r\n", $converted);
		if ($newValue !== $library->collectionsToHide) {
			$library->collectionsToHide = $newValue;
			if ($library->update() === false) {
				$success = false;
			}
		}
	}
	return $success;
}