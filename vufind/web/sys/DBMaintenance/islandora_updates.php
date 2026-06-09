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

		'Islandora2_drop_archive_subjects' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 0,
			'title'           => 'Remove Archive Subjects table',
			'description'     => 'Drop the unused archive_subjects table; the ArchiveSubject feature was never put into use.',
			'continueOnError' => true,
			'sql'             => [
				'DROP TABLE IF EXISTS `archive_subjects`;',
			],
		],

		'Islandora2_convert_objectsToHide_pids_to_nodeIds' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 1,
			'title'           => 'Convert objectsToHide PIDs to nodeIds',
			'description'     => 'Converts library.objectsToHide entries from legacy Islandora PID format (namespace:id) to plain integer nodeIds.',
			'continueOnError' => false,
			'sql'             => [
				'convertObjectsToHidePidsToNodeIds'
			]
		],

		'Islandora2_convert_collectionsToHide_pids_to_nodeIds' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 2,
			'title'           => 'Convert collectionsToHide PIDs to nodeIds',
			'description'     => 'Converts library.collectionsToHide entries from legacy Islandora PID format (namespace:id) to plain integer nodeIds.',
			'continueOnError' => false,
			'sql'             => [
				'convertCollectionsToHidePidsToNodeIds'
			]
		],

		'Islandora2_archive_private_collections_add_type' => [
			'release'         => 'Islandora2',  // TODO: change to release number
			'releaseStep'     => 3,
			'title'           => 'Add type column to archive_private_collections',
			'description'     => 'Adds an enum type column to distinguish collection vs object entries; tags the existing row as type=collection.',
			'continueOnError' => false,
			'sql'             => [
				"ALTER TABLE archive_private_collections ADD COLUMN type ENUM('collection','object') NOT NULL DEFAULT 'collection' AFTER id",
			]
		],

		'Islandora2_convert_privateCollections_pids_to_nodeIds' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 4,
			'title'           => 'Convert archive_private_collections PIDs to nodeIds',
			'description'     => 'Converts archive_private_collections.privateCollections entries from legacy Islandora PID format (namespace:id) to plain integer nodeIds.',
			'continueOnError' => false,
			'sql'             => [
				'convertPrivateCollectionsPidsToNodeIds'
			]
		],

		'Islandora2_convert_archiveNamespace_to_libraryTid' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 5,
			'title'           => 'Convert library.archiveNamespace to libraryTid',
			'description'     => 'Adds a libraryTid column, looks up each library\'s archivePid entity PID in Islandora2 Solr to find its taxonomy term ID, stores the result, then alters the column to INT UNSIGNED.',
			'continueOnError' => false,
			'sql'             => [
				"ALTER TABLE library ADD COLUMN libraryTid INT UNSIGNED NULL AFTER archivePid",
				'convertArchiveNamespaceToLibraryTid'
			]
		],

		'Islandora2_library_add_corporateBodyTid' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 6,
			'title'           => 'Add corporateBodyTid column to library table and convert archivePid to corporateBodyTid',
			'description'     => 'Adds a corporateBodyTid column to store the Islandora2 Corporate Body taxonomy term ID for each library, used to populate acknowledgement thumbnails on Archive object pages.',
			'continueOnError' => false,
			'sql'             => [
				"ALTER TABLE library ADD COLUMN corporateBodyTid INT UNSIGNED NULL AFTER `libraryTid`",
				'convertArchivePidToCorporateBodyTid'
			]
		],

		'Islandora2_library_archive_search_facet_setting_migration' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 7,
			'title'           => 'Migrate Archive Search Facet Settings to Islandora2',
			'description'     => 'Updates facetName values in library_archive_search_facet_setting from legacy Islandora (MODS) field names to their Islandora2 Solr field equivalents.',
			'continueOnError' => true,
			'sql'             => [
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_subject' WHERE facetName = 'mods_subject_topic_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_genre' WHERE facetName = 'mods_genre_s';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_collection' WHERE facetName = 'RELS_EXT_isMemberOfCollection_uri_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_related_person' WHERE facetName = 'mods_extension_marmotLocal_relatedEntity_person_entityTitle_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_related_place' WHERE facetName = 'mods_extension_marmotLocal_relatedEntity_place_entityTitle_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'sm_related_event' WHERE facetName = 'mods_extension_marmotLocal_relatedEntity_event_entityTitle_ms';",
				"UPDATE library_archive_search_facet_setting SET facetName = 'ss_library' WHERE facetName = 'namespace_s';",
				// No Equivalents for these facets so will remove them
				"DELETE FROM library_archive_search_facet_setting WHERE facetName = 'mods_extension_marmotLocal_describedEntity_entityTitle_ms';",
				"DELETE FROM library_archive_search_facet_setting WHERE facetName = 'mods_extension_marmotLocal_picturedEntity_entityTitle_ms';",
				"DELETE FROM library_archive_search_facet_setting WHERE facetName = 'ancestors_ms';",
			]
		],

		'Islandora2_add_nid_and_convert_legacy_pid_to_nid' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 8,
			'title'           => 'Add nid column to Archive Requests',
			'description'     => 'Adds Node ID column to Archive Requests table to reflect the new Islandora Structure',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE archive_requests ADD COLUMN nid INT(11) NULL AFTER pid;",
				'convertPidToNid',
			]
		],

		'Islandora2_add_LibraryTid_and_lookup_by_nid' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 9,
			'title'           => 'RUN AFTER "Add nid column to Archive Requests" Adds LibraryTid',
			'description'     => 'Adds librayTid column to Archive Requests table to enable filtering',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE archive_requests ADD COLUMN libraryTid INT(11) NULL AFTER nid;",
				'getTidFromNid'
			]
		],
		'Islandora2_add_nid_and_convert_legacy_pid_to_nid_for_authorship_claim' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 10,
			'title'           => 'Add nid column to Authorship Claims',
			'description'     => 'Adds Node ID column to Authorship Claims table to reflect the new Islandora Structure',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE claim_authorship_requests ADD COLUMN nid INT(11) NULL AFTER pid;",
				'convertPidToNidAuthorship',
			]
		],
		'Islandora2_add_LibraryTid_and_lookup_by_nid_for_authorship_claim' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 11,
			'title'           => 'Add libraryTid for filtering purposes. RUN AFTER "Add nid column to Authorship Claims',
			'description'     => 'Adds librayTid column to Archive Requests table to enable filtering',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE claim_authorship_requests ADD COLUMN libraryTid INT(11) NULL AFTER nid;",
				'getTidFromNidAuthorship'
			]
		],
		'Islandora2_convert_list_pid_to_nid' => [
			'release'         => 'Islandora2', // TODO: change to release number
			'releaseStep'     => 12,
			'title'           => 'Convert List PID to Nid; RUN THIS STEP BY ITSELF',
			'description'     => 'Converts the archive user list pid to the nid in the user_list_entry',
			'continueOnError' => true,
			'sql'             => [
				"ALTER TABLE user_list_entry ADD COLUMN hidden BOOL NULL DEFAULT FALSE AFTER weight;",
				'convertListPidToNid'
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

function convertObjectsToHidePidsToNodeIds(): bool {
	// Populate this array: key = legacy PID, value = nodeId integer
	$pidToNodeId = [
		'fortlewis:12699' => 35830,
		'fortlewis:12700' => 35836,
		'fortlewis:12701' => 35833,
		'fortlewis:12702' => 35832,
		'fortlewis:12703' => 35835,
	];

	require_once ROOT_DIR . '/sys/Library/Library.php';
	$library = new Library();
	$library->whereAdd('objectsToHide IS NOT NULL');
	$library->whereAdd('objectsToHide != ""');
	$library->find();

	global $pikaLogger;
	$lineSeparator = "\r\n";
	$eolSplitter   = "\n"; // Use only the \n character to break into array, depending on trim to catch \r as whitespace
	$success       = true;
	while ($library->fetch()) {
		if (!str_contains($library->objectsToHide, $eolSplitter)){
			$pikaLogger->error("library {$library->subdomain} objectsToHide is not a multi-line string. It is: {$library->objectsToHide}");
			$success = false;
			continue; // short-circuit loop on error; TODO: end loop instead?
		}
		$entries   = explode($eolSplitter, $library->objectsToHide);
		$converted = array_map(function ($entry) use ($pidToNodeId) {
			$trimmed = trim($entry);
			return isset($pidToNodeId[$trimmed])
				? (string) $pidToNodeId[$trimmed]
				: $trimmed;
		}, $entries);
		$pikaLogger->info("library {$library->subdomain} collections to hide : ", $entries);
		$pikaLogger->info("library {$library->subdomain} converted to : ", $converted);

		$newValue = implode($lineSeparator, $converted);
		if (str_contains($newValue, ':')){
			$success = false;
			$pikaLogger->error("Conversion Objects to Hide failed for {$library->subdomain}.", $converted);
		}
		if ($newValue !== $library->objectsToHide) {
			$library->objectsToHide = $newValue;
			if ($library->update() === false) {
				$success = false;
				$pikaLogger->error("library {$library->subdomain} update failed");
			}
		}
	}
	return $success;
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
	global $pikaLogger;

	$library = new Library();
	$library->whereAdd('collectionsToHide IS NOT NULL');
	$library->whereAdd("collectionsToHide != ''");
	$library->find();

	$lineSeparator = "\r\n";
	$eolSplitter   = "\n"; // Use only the \n character to break into array, depending on trim to catch \r as whitespace
	$success       = true;
	while ($library->fetch()) {
		$entries   = explode($eolSplitter, $library->collectionsToHide);
		$converted = array_map(function ($entry) use ($pidToNodeId) {
			$trimmed = trim($entry);
			return isset($pidToNodeId[$trimmed])
				? (string) $pidToNodeId[$trimmed]
				: $trimmed;
		}, $entries);

		$newValue = implode($lineSeparator, $converted);
		if (str_contains($newValue, ':')){
			$pikaLogger->error("Conversion Collections to Hide failed for {$library->subdomain}.", $converted);
			$success = false;
		}
		if ($newValue !== $library->collectionsToHide) {
			$library->collectionsToHide = $newValue;
			if ($library->update() === false) {
				$success = false;
			}
		}
	}
	return $success;
}

function convertArchiveNamespaceToLibraryTid(): bool {
	// Maps library subdomain (= archiveNamespace) to the Islandora2 contributing-library taxonomy term ID (TID).
	// These TIDs correspond to the ss_library facet, not the legacy entity PIDs.
	$namespaceTidMap = [
		'adams'           => 303,
		'ccu'             => 428,
		'cmc'             => 112135,
		'englewood'       => 315,
		'evld'            => 337,
		'fortlewis'       => 249,
		'garfield'        => 324,
		'gunnison'        => 24147,
		'lafayette'       => 354,
		'mesa'            => 261,
		//'montrose'        => null, //TODO: set a library TID?
		'pineriver'       => 243,
		'pitkin'          => 351,
		'salida'          => 365,
		'steamboatlibrary'=> 277,
		'vail'            => 294,
		'western'         => 433,
		'wilkinson'       => 30940,
	];

	$library = new Library();
	$library->whereAdd('archiveNamespace IS NOT NULL');
	$library->whereAdd("archiveNamespace != ''");
	$library->find();

	global $pikaLogger;
	$success = true;
	while ($library->fetch()) {
		$tid = $namespaceTidMap[$library->archiveNamespace] ?? null;
		if ($tid === null) {
			$success = false;
			$pikaLogger->error("Found no TID for library archiveNamespace $library->archiveNamespace.");
			continue;
		}
		$library->libraryTid = $tid;
		if ($library->update() === false) {
			$pikaLogger->error("Failed to update Library TID for library $library->subdomain.");
			$success = false;
		}
	}
	return $success;
}
function convertArchivePidToCorporateBodyTid(): bool {
	global $pikaLogger;
	$success = true;
	$library = new Library();
	$library->whereAdd('archivePid IS NOT NULL');
	$library->whereAdd("archivePid != ''");
	if ($library->find()){
		/** @var SearchObject_Islandora2 $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora2');

		while ($library->fetch()){
			$TIDs = $searchObject->getLegacyEntitiesTIDs([$library->archivePid]);
			if (empty($TIDs)){
				$pikaLogger->error("Found no Corporate Body TID for legacy archivePID $library->archivePid.");
				$success = false;
				continue;
			}
			$library->corporateBodyTid = (int) reset($TIDs);
			if ($library->update() === false){
				$pikaLogger->error("Failed to update Library Corporate Body TID for library $library->subdomain.");
				$success = false;
			}
		}
	}
	return $success;
}

function convertPidToNid(){
	global $pikaLogger;
	$success = true;
	require_once ROOT_DIR . '/sys/Archive2/ArchiveRequest.php';
	$archiveRequest = new Archive2\ArchiveRequest();
	$archiveRequest->whereAdd('pid IS NOT NULL');
	$archiveRequest->whereAdd("pid != ''");
	if ($archiveRequest->find()){
		// Get the Islandora2 search object
		require_once ROOT_DIR . '/sys/SearchObject/Factory.php';
		/** @var SearchObject_Islandora2 $islandora2Search */
		$islandora2Search = SearchObjectFactory::initSearchObject('Islandora2');
		while ($archiveRequest->fetch()){
			$pid = $archiveRequest->pid;
			if ($nids = $islandora2Search->getNodeIdsbyLegacyPIDs([$pid])){
				foreach ($nids as $nid){
					$archiveRequest->nid = $nid;
					if ($archiveRequest->update() === false) {
						$pikaLogger->error("Failed to update NID for archive request $archiveRequest->id.");
						$success = false;
					}
				}
			}else{
				$pikaLogger->error("Failed to convert PID $pid to NID for archive request $archiveRequest->id.");
				$success = false;
			}
		}
	}
	return $success;
}
function convertPidToNidAuthorship(){
	global $pikaLogger;
	require_once ROOT_DIR . '/sys/Archive2/ClaimAuthorshipRequest.php';
	$authorshipClaim = new Archive2\ClaimAuthorshipRequest();
	$authorshipClaim->whereAdd('pid IS NOT NULL');
	$authorshipClaim->whereAdd("pid != ''");
	$success = true;
	if ($authorshipClaim->find()){
		// Get the Islandora2 search object
		require_once ROOT_DIR . '/sys/SearchObject/Factory.php';
		/** @var SearchObject_Islandora2 $islandora2Search */
		$islandora2Search = SearchObjectFactory::initSearchObject('Islandora2');
		while ($authorshipClaim->fetch()){
			$pid = $authorshipClaim->pid;
			if ($nids = $islandora2Search->getNodeIdsbyLegacyPIDs([$pid])){
				foreach ($nids as $nid){
					$authorshipClaim->nid = $nid;
					if ($authorshipClaim->update() === false) {
						$pikaLogger->error("Failed to update NID for authorship claim $authorshipClaim->id.");
						$success = false;
					}
				}
			}else{
				$pikaLogger->error("Failed to convert PID $pid to NID for authorship claim $authorshipClaim->id.");
				$success = false;
			}
		}
	}
	return $success;
}

function getTidFromNid(){
	global $pikaLogger;
	require_once ROOT_DIR . '/sys/Archive2/ArchiveRequest.php';
	$archiveRequest = new Archive2\ArchiveRequest();
	$archiveRequest->whereAdd('nid IS NOT NULL');
	$success = true;
	if ($archiveRequest->find()){
		require_once ROOT_DIR . '/sys/SearchObject/Factory.php';
		/** @var SearchObject_Islandora2 $islandora2Search */
		$islandora2Search = SearchObjectFactory::initSearchObject('Islandora2');
		$islandora2Search->addFieldsToReturn(['itm_field_library']);
		while ($archiveRequest->fetch()){
			$nid = $archiveRequest->nid;
			$record     = $islandora2Search->getRecord($nid);
			$libraryTid = $record[0]['itm_field_library'][0] ?? null;
			if ($libraryTid !== null){
				$archiveRequest->libraryTid = $libraryTid;
				if ($archiveRequest->update() === false) {
					$pikaLogger->error("Failed to update libraryTid for archive request $archiveRequest->id.");
					$success = false;
				}
			}else{
				$pikaLogger->error("Failed to get library TID for NID $nid for archive request $archiveRequest->id.");
				$success = false;
			}
		}
	}
	return $success;
}
function getTidFromNidAuthorship(){
	global $pikaLogger;
	require_once ROOT_DIR . '/sys/Archive2/ClaimAuthorshipRequest.php';
	$authorshipClaim = new Archive2\ClaimAuthorshipRequest();
	$authorshipClaim->whereAdd('nid IS NOT NULL');
	$success = true;
	if ($authorshipClaim->find()){
		require_once ROOT_DIR . '/sys/SearchObject/Factory.php';
		/** @var SearchObject_Islandora2 $islandora2Search */
		$islandora2Search = SearchObjectFactory::initSearchObject('Islandora2');
		$islandora2Search->addFieldsToReturn(['itm_field_library']);
		while ($authorshipClaim->fetch()){
			$nid = $authorshipClaim->nid;
			$record     = $islandora2Search->getRecord($nid);
			$libraryTid = $record[0]['itm_field_library'][0] ?? null;
			if ($libraryTid !== null){
				$authorshipClaim->libraryTid = $libraryTid;
				if ($authorshipClaim->update() === false) {
					$pikaLogger->error("Failed to update libraryTid for authorship claim $authorshipClaim->id.");
					$success = false;
				}
			}else{
				$pikaLogger->error("Failed to get library TID for NID $nid for authorship claim $authorshipClaim->id.");
				$success = false;
			}
		}
	}
	return $success;
}

/**
 * @return bool
 */
function convertListPidToNid():bool {
	global $pikaLogger;
	require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
	require_once ROOT_DIR . '/sys/Library/Library.php';
	require_once ROOT_DIR . '/sys/SearchObject/Factory.php';

	$library = new Library();
	$library->whereAdd('archiveNamespace IS NOT NULL && archiveNamespace != ""');
	$library->find();
	$nameSpace = $library->fetchAll('archiveNamespace');

	/** @var SearchObject_Islandora2 $islandora2Search */
	$islandora2Search = SearchObjectFactory::initSearchObject('Islandora2');

	$userListEntry = new UserListEntry();
	$userListEntry->find();
	$success = true; // Set as true if there are no list entries to process
	while($userListEntry->fetch()){
		$pid   = $userListEntry->groupedWorkPermanentId;
		$parts = explode(':', $pid);
		if (count($parts) > 1){
			if (in_array($parts[0], $nameSpace)){
				if ($nids = $islandora2Search->getNodeIdsbyLegacyPIDs([$pid])){
					foreach ($nids as $nid){
						$userListEntry->groupedWorkPermanentId = $nid;
						$userListEntry->hidden                 = false;
						if ($userListEntry->update() === false) {
							$pikaLogger->error("Failed to update list entry for PID $pid.");
							$success = false;
						}
					}
				}else{
					$pikaLogger->error("There was an error processing the record", $parts);
					$success = false;
				}
			}else{
				$pikaLogger->warning("The object may be a taxonomy", $parts);
				$userListEntry->hidden = true;
				if ($userListEntry->update() === false) {
					$pikaLogger->error("Failed to hide list entry for PID $pid.");
					$success = false;
				}
			}
		}else{
			$pikaLogger->notice("Not an archive object", $parts);
			$success = true;
		}
	}
	return $success;
}