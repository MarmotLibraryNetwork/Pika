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

require_once ROOT_DIR . '/AJAXHandler.php';

class Archive_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = [
		'getRelatedObjectsForScroller',
		'getRelatedObjectsForTimelineExhibit',
		'getRelatedObjectsForMappedCollection',
		'getRelatedObjectsForExhibit',
		'getEntityFacetValuesForExhibit',
		'getFacetValuesForExhibit',
		'getExploreMoreContent',
		'getObjectInfo',
		'getMetadata',
		'setMoreDetailsDisplayMode',
		'getNextRandomObject',
		'getTranscript',
		'getAdditionalRelatedObjects',
		'getSaveToListForm',
		'saveToList',
		'setupTimelineFacetsAndFilters',
		'setupTimelineSorts',
		'processTimelineData',
		'setCoversDisplayMode',
		'clearCache',
	];

	protected $methodsThatRespondThemselves = [
		'getVtt',
	];

	function getRelatedObjectsForExhibit(){
		if (isset($_REQUEST['collectionId'])){
			global $interface;
			global $timer;
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pid         = urldecode($_REQUEST['collectionId']);
			$interface->assign('exhibitPid', $pid);

			$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
			$interface->assign('page', $page);

			$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'title';
			$interface->assign('sort', $sort);

			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '0');
			}

			$displayType = 'basic';
			$interface->assign('displayType', $displayType);

			$this->setShowCovers();
			$displayMode = $this->setCoversDisplayMode();

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->addHiddenFilter('!RELS_EXT_isConstituentOf_uri_ms', "*");
			$searchObject->clearFilters();
			$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$pid}\"");
			$searchObject->clearFacets();

			$searchObject->setLimit(24);

//			$searchObject->setSort('fgs_label_s');
			$this->setupTimelineSorts($sort, $searchObject);
			//TODO: Do these sorts work for a basic exhibit?

			$relatedObjects = array();
			$response       = $searchObject->processSearch(true, false);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0){
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd', $summary['endRecord']);

				$recordIndex = $summary['startRecord'];
				if ($displayMode == 'list'){
					$recordSet = $searchObject->getResultRecordHTML();
					$interface->assign('recordSet', $recordSet);
				}else{
					$interface->assign('showThumbnailsSorted', true);
					foreach ($response['response']['docs'] as $objectInCollection){
						/** @var IslandoraDriver $firstObjectDriver */
						$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
						$relatedObject     = [
							'title'       => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link'        => $firstObjectDriver->getRecordUrl(),
							'pid'         => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++,
						];
						if ($sort == 'dateAdded'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_createdDate_dt']));
						}elseif ($sort == 'dateModified'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_lastModifiedDate_dt']));
							//TODO: should the array index be 'dateCreated' or should it be changed to 'dateModified'
						}
						$relatedObjects[] = $relatedObject;
						$timer->logTime('Loaded related object');
					}

				}

				//Get a list of sub collections to use for searching
				$exhibitObject = $fedoraUtils->getObject($pid);

				$interface->assign('relatedObjects', $relatedObjects);
			}
			return [
				'success'        => true,
				'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl'),
			];
		}else{
			return [
				'success' => false,
				'message' => 'You must supply the collection and place to load data for',
			];
		}
	}

	function getRelatedObjectsForScroller(){
		if (isset($_REQUEST['pid'])){
			global $interface;
			global $timer;

			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pid         = urldecode($_REQUEST['pid']);
			$interface->assign('exhibitPid', $pid);

			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '1');
			}

			$page = $_REQUEST['page'] ?? 1;
			$interface->assign('page', $page);

			$sort = $_REQUEST['sort'] ?? 'title';
			$interface->assign('sort', $sort);

			$displayType = 'scroller';
			$interface->assign('displayType', $displayType);

			$this->setShowCovers();
			$displayMode = $this->setCoversDisplayMode();

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->addHiddenFilter('!RELS_EXT_isConstituentOf_uri_ms', "*");
			$searchObject->clearFilters();
			$searchObject->clearFacets();

			$searchObject->setSearchTerms([
				'lookfor' => '"' . $pid . '"',
				'index'   => 'IslandoraRelationshipsById',
			]);

			$searchObject->setLimit(24);

			$this->setupTimelineSorts($sort, $searchObject);
			$interface->assign('showThumbnailsSorted', true);

			$relatedObjects = array();
			$response       = $searchObject->processSearch(true, false);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0){
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd', $summary['endRecord']);
				$recordIndex = $summary['startRecord'];
				$page        = $summary['page'];
				$interface->assign('page', $page);

				if ($displayMode == 'list'){
					$recordSet = $searchObject->getResultRecordHTML();
					$interface->assign('recordSet', $recordSet);
				}

				// Save the search with Map query and filters
				$searchObject->close(); // Trigger save search
				$lastExhibitObjectsSearch    = $searchObject->getSearchId(); // Have to save the search first.
				$_SESSION['exhibitSearchId'] = $lastExhibitObjectsSearch;
				$this->logger->debug("Setting exhibit search id to $lastExhibitObjectsSearch");

				if ($displayMode == 'covers'){
					foreach ($response['response']['docs'] as $objectInCollection){
						/** @var IslandoraDriver $firstObjectDriver */
						$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
						$relatedObject     = [
							'title'       => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link'        => $firstObjectDriver->getRecordUrl(),
							'pid'         => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++,
						];
						if ($sort == 'dateAdded'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_createdDate_dt']));
						}elseif ($sort == 'dateModified'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_lastModifiedDate_dt']));
							//TODO: should the array index be 'dateCreated' or should it be changed to 'dateModified'
						}
						$relatedObjects[] = $relatedObject;
						$timer->logTime('Loaded related object');
					}
				}


				$this->processTimelineData($response, $interface);
			}

			$interface->assign('relatedObjects', $relatedObjects);

			return [
				'success'        => true,
				'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl'),
			];
		}else{
			return [
				'success' => false,
				'message' => 'You must supply the collection and place to load data for',
			];
		}
	}

	function getRelatedObjectsForTimelineExhibit(){
		if (isset($_REQUEST['collectionId'])){
			global $interface;
			global $timer;

			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$pid = urldecode($_REQUEST['collectionId']);
			$interface->assign('exhibitPid', $pid);

			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '1');
			}

			$page = $_REQUEST['page'] ?? 1;
			$interface->assign('page', $page);

			$sort = $_REQUEST['sort'] ?? 'title';
			$interface->assign('sort', $sort);

			$displayType = 'timeline';
			$interface->assign('displayType', $displayType);

			$this->setShowCovers();
			$displayMode = $this->setCoversDisplayMode();

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			//$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->addHiddenFilter('!RELS_EXT_isConstituentOf_uri_ms', "*");
			$searchObject->clearFilters();
			$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$pid}\"");
			$searchObject->clearFacets();
			//Add filtering based on date filters
			$timeLineSetUp = false;
			if (!empty($_SESSION['ExhibitContext']) && $_SESSION['ExhibitContext'] == $pid){

				if (!empty($_REQUEST['dateFilter']) && $_REQUEST['dateFilter'] != 'all'){
					$_SESSION['dateFilter'] = $_REQUEST['dateFilter']; // store applied date filters
				}else{
					// Clear time data
					unset($_SESSION['dateFilter']);
				}
			}
			$this->setupTimelineFacetsAndFilters($searchObject);

			$searchObject->setLimit(24);

			$this->setupTimelineSorts($sort, $searchObject);
			$interface->assign('showThumbnailsSorted', true);

			$relatedObjects = [];
			$response       = $searchObject->processSearch(true, false);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0){
				$summary = $searchObject->getResultSummary();
				if (!$timeLineSetUp){
					$interface->assign('recordCount', $summary['resultTotal']);
				}
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd', $summary['endRecord']);
				$recordIndex = $summary['startRecord'];
				$page        = $summary['page'];
				$interface->assign('page', $page);

				if ($displayMode == 'list'){
					$recordSet = $searchObject->getResultRecordHTML();
					$interface->assign('recordSet', $recordSet);
				}


				// Save the search with Map query and filters
				$searchObject->close(); // Trigger save search
				$lastExhibitObjectsSearch    = $searchObject->getSearchId(); // Have to save the search first.
				$_SESSION['exhibitSearchId'] = $lastExhibitObjectsSearch;
				$this->logger->debug("Setting exhibit search id to $lastExhibitObjectsSearch");

				if ($displayMode == 'covers'){
					foreach ($response['response']['docs'] as $objectInCollection){
						/** @var IslandoraDriver $firstObjectDriver */
						$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
						$relatedObject     = [
							'title'       => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link'        => $firstObjectDriver->getRecordUrl(),
							'pid'         => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++,
						];
						if ($sort == 'dateAdded'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_createdDate_dt']));
						}elseif ($sort == 'dateModified'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_lastModifiedDate_dt']));
							//TODO: should the array index be 'dateCreated' or should it be changed to 'dateModified'
						}
						$relatedObjects[] = $relatedObject;
						$timer->logTime('Loaded related object');
					}
				}

				if (!$timeLineSetUp){
					$this->processTimelineData($response, $interface);
				}
			}

			$interface->assign('relatedObjects', $relatedObjects);
			return [
				'success'        => true,
				'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl'),
			];
		}else{
			return [
				'success' => false,
				'message' => 'You must supply the collection and place to load data for',
			];
		}
	}

	function getRelatedObjectsForMappedCollection(){
		if (isset($_REQUEST['collectionId']) && isset($_REQUEST['placeId'])){
			global $interface;
			global $timer;

			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pid         = urldecode($_REQUEST['collectionId']);
			/** @var IslandoraDriver $collectionDriver */
			$collectionDriver = RecordDriverFactory::initIslandoraDriverFromPid($pid);
			$interface->assign('exhibitPid', $pid);
			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '1');
			}

			$additionalCollections = [];
			if ($collectionDriver->getModsValue('pikaCollectionDisplay', 'marmot') == 'custom'){
				//Load the options to show
				$collectionOptionsOriginalRaw = $collectionDriver->getModsValue('collectionOptions', 'marmot');
				$collectionOptionsOriginal    = explode("\r\n", html_entity_decode($collectionOptionsOriginalRaw));
				$additionalCollections        = [];
				if (isset($collectionOptionsOriginal)){
					foreach ($collectionOptionsOriginal as $collectionOption){
						if (strpos($collectionOption, 'googleMap') === 0){
							$filterOptions = explode('|', $collectionOption);
							if (count($filterOptions) > 1){
								$additionalCollections = explode(',', $filterOptions[1]);
								break;
							}
						}
					}
				}
			}

			if (isset($_REQUEST['showTimeline']) && $_REQUEST['showTimeline'] != 'undefined'){
				$interface->assign('showTimeline', $_REQUEST['showTimeline']);
				$showTimeline = $_REQUEST['showTimeline'] == 'true';
			}else{
				$interface->assign('showTimeline', true);
				$showTimeline = true;
			}

			$placeId = urldecode($_REQUEST['placeId']);
			$this->logger->debug("Setting place information for context $placeId");
			@session_start();
			$_SESSION['placePid'] = $placeId;
			$interface->assign('placePid', $placeId);

			/** @var FedoraObject $placeObject */
			$placeObject            = $fedoraUtils->getObject($placeId);
			$_SESSION['placeLabel'] = $placeObject->label;
			$this->logger->debug("Setting place label for context $placeObject->label");

			if ($showTimeline){
				$interface->assign('displayType', 'map');
			}else{
				$interface->assign('displayType', 'mapNoTimeline');
			}

			$interface->assign('label', $placeObject->label);

			$page = $_REQUEST['page'] ?? 1;
			$interface->assign('page', $page);

			$sort = $_REQUEST['sort'] ?? 'title';
			$interface->assign('sort', $sort);

			$this->setShowCovers();
			$displayMode = $this->setCoversDisplayMode();

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->addHiddenFilter('!RELS_EXT_isConstituentOf_uri_ms', "*");
			$searchObject->clearFilters();
			if (isset($additionalCollections) && !empty($additionalCollections)){
				$filter = "RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$pid}\"";
				foreach ($additionalCollections as $collection){
					$filter .= " OR RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/" . trim($collection) . "\"";
				}
				$searchObject->addFilter($filter);
			}else{
				$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$pid}\"");
			}
			$searchObject->setBasicQuery("mods_extension_marmotLocal_relatedEntity_place_entityPid_ms:\"{$placeId}\" OR " .
				"mods_extension_marmotLocal_relatedPlace_entityPlace_entityPid_ms:\"{$placeId}\" OR " .
				"mods_extension_marmotLocal_militaryService_militaryRecord_relatedPlace_entityPlace_entityPid_ms:\"{$placeId}\" OR " .
				"mods_extension_marmotLocal_describedEntity_entityPid_ms:\"{$placeId}\" OR " .
				"mods_extension_marmotLocal_picturedEntity_entityPid_ms:\"{$placeId}\""
			);

			$searchObject->clearFacets();
			if ($showTimeline){
				$this->setupTimelineFacetsAndFilters($searchObject);
			}
			$this->setupTimelineSorts($sort, $searchObject);
			$interface->assign('showThumbnailsSorted', true);

			$searchObject->setLimit(24);

			$relatedObjects = [];
			$response       = $searchObject->processSearch(true, false, true);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0){
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd', $summary['endRecord']);
				$recordIndex = $summary['startRecord'];

				if ($displayMode == 'list'){
					$recordSet = $searchObject->getResultRecordHTML();
					$interface->assign('recordSet', $recordSet);
				}

				// Save the search with Map query and filters
				$searchObject->close(); // Trigger save search
				$lastExhibitObjectsSearch    = $searchObject->getSearchId(); // Have to save the search first.
				$_SESSION['exhibitSearchId'] = $lastExhibitObjectsSearch;
				$this->logger->debug("Setting exhibit search id to $lastExhibitObjectsSearch");

				if ($displayMode == 'covers'){
					foreach ($response['response']['docs'] as $objectInCollection){
						/** @var IslandoraDriver $firstObjectDriver */
						$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
						$relatedObject     = [
							'title'       => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link'        => $firstObjectDriver->getRecordUrl(),
							'pid'         => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++,
						];
						if ($sort == 'dateAdded'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_createdDate_dt']));
						}elseif ($sort == 'dateModified'){
							$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_lastModifiedDate_dt']));
							//TODO: should the array index be 'dateCreated' or should it be changed to 'dateModified'
						}
						$relatedObjects[] = $relatedObject;
						$timer->logTime('Loaded related object');
					}
				}
				if ($showTimeline){
					$this->processTimelineData($response, $interface);
				}
			}

			$interface->assign('relatedObjects', $relatedObjects);
			return [
				'success'        => true,
				'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl'),
			];
		}else{
			return [
				'success' => false,
				'message' => 'You must supply the collection and place to load data for',
			];
		}
	}

	function getEntityFacetValuesForExhibit(){
		if (!isset($_REQUEST['id'])){
			return [
				'success' => false,
				'message' => 'You must supply the id to load facet data for',
			];
		}
		if (!isset($_REQUEST['facetName'])){
			return [
				'success' => false,
				'message' => 'You must supply the facetName to load facet data for',
			];
		}

		$pid = urldecode($_REQUEST['id']);

		//get a list of all collections and books within the main exhibit so we can find all related data.
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils   = FedoraUtils::getInstance();
		$exhibitObject = $fedoraUtils->getObject($pid);
		/** @var IslandoraDriver $exhibitDriver */
		$exhibitDriver = RecordDriverFactory::initRecordDriver($exhibitObject);

		global $interface;
		$facetName = urldecode($_REQUEST['facetName']);
		$interface->assign('exhibitPid', $pid);
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->clearHiddenFilters();
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->clearFilters();

		$collectionFilter = "ancestors_ms:\"$pid\"";
		$searchObject->addFilter($collectionFilter);
		$searchObject->clearFacets();
		$searchObject->addFacet($facetName);

		$searchObject->setLimit(1);

		$facetValues = [];
		$response    = $searchObject->processSearch(true, false);
		if ($response && isset($response['error'])){
			$interface->assign('solrError', $response['error']['msg']);
			$interface->assign('solrLink', $searchObject->getFullSearchUrl());
		}
		if (isset($response['facet_counts']) && isset($response['facet_counts']['facet_fields'][$facetName])){
			$facetFieldData = $response['facet_counts']['facet_fields'][$facetName];
			foreach ($facetFieldData as $field){
				$entityDriver = RecordDriverFactory::initIslandoraDriverFromPid($field[0]);
				if (!PEAR_Singleton::isError($entityDriver) && $entityDriver != null){
					$facetValues[$entityDriver->getTitle()] = [
						'display' => $entityDriver->getTitle(),
						'url'     => $entityDriver->getRecordUrl(),
						'count'   => $field[1],
					];
				}
			}
			ksort($facetValues);
		}

		$interface->assign('facetValues', $facetValues);
		$results = [
			'modalBody' => $interface->fetch('Archive/browseFacetPopup.tpl'),
		];
		return $results;
	}

	function getFacetValuesForExhibit(){
		if (!isset($_REQUEST['id'])){
			return [
				'success' => false,
				'message' => 'You must supply the id to load facet data for',
			];
		}
		if (!isset($_REQUEST['facetName'])){
			return [
				'success' => false,
				'message' => 'You must supply the facetName to load facet data for',
			];
		}

		$pid = urldecode($_REQUEST['id']);

		//get a list of all collections and books within the main exhibit so we can find all related data.
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils   = FedoraUtils::getInstance();
		$exhibitObject = $fedoraUtils->getObject($pid);
		/** @var IslandoraDriver $exhibitDriver */
		$exhibitDriver = RecordDriverFactory::initRecordDriver($exhibitObject);

		global $interface;
		$facetName = urldecode($_REQUEST['facetName']);
		$interface->assign('exhibitPid', $pid);
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->clearHiddenFilters();
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->clearFilters();

		$collectionFilter = "ancestors_ms:\"$pid\"";
		$searchObject->addFilter($collectionFilter);
		$searchObject->clearFacets();
		$searchObject->addFacet($facetName);

		$searchObject->setLimit(1);

		$facetValues = [];
		$response    = $searchObject->processSearch(true, false);
		if ($response && isset($response['error'])){
			$interface->assign('solrError', $response['error']['msg']);
			$interface->assign('solrLink', $searchObject->getFullSearchUrl());
		}
		if (isset($response['facet_counts']) && isset($response['facet_counts']['facet_fields'][$facetName])){
			$facetFieldData = $response['facet_counts']['facet_fields'][$facetName];
			foreach ($facetFieldData as $field){
				$searchLink             = $searchObject->renderLinkWithFilter("$facetName:$field[0]");
				$facetValues[$field[0]] = [
					'display' => $field[0],
					'url'     => $searchLink,
					'count'   => $field[1],
				];
			}
			ksort($facetValues);
		}

		$interface->assign('facetValues', $facetValues);
		$results = [
			'modalBody' => $interface->fetch("Archive/browseFacetPopup.tpl"),
		];
		return $results;
	}

	function getExploreMoreContent(){
		if (!isset($_REQUEST['id'])){
			return [
				'success' => false,
				'message' => 'You must supply the id to load explore more content for',
			];
		}
		global $interface;
		global $timer;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();
		$pid         = urldecode($_REQUEST['id']);
		$interface->assign('pid', $pid);
		$archiveObject = $fedoraUtils->getObject($pid);
		/** @var IslandoraDriver $recordDriver */
		$recordDriver  = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);
		$timer->logTime("Loaded record driver for main object");

		require_once ROOT_DIR . '/sys/ExploreMore.php';
		$exploreMore = new ExploreMore();
		$exploreMore->loadExploreMoreSidebar('archive', $recordDriver);
		$timer->logTime("Called loadExploreMoreSidebar");

		$relatedSubjects = $recordDriver->getAllSubjectHeadings(true, 5);
		$ebscoMatches    = $exploreMore->loadEbscoOptions('archive', [], implode( ' or ', $relatedSubjects));
		if (!empty($ebscoMatches)){
			$interface->assign('relatedArticles', $ebscoMatches);
		}
		$timer->logTime('Loaded Ebsco options');

		require_once ROOT_DIR . '/sys/Archive/ArchiveExploreMoreBar.php';
		global $library;
		$exploreMoreSettings = $library->exploreMoreBar;
		if (empty($exploreMoreSettings)){
			$exploreMoreSettings = ArchiveExploreMoreBar::getDefaultArchiveExploreMoreOptions();
		}
		$interface->assign('exploreMoreSettings', $exploreMoreSettings);
		$interface->assign('archiveSections', ArchiveExploreMoreBar::$archiveSections);
		$timer->logTime('Loaded Settings');

		return [
			'success'     => true,
			'exploreMore' => $interface->fetch('explore-more-sidebar.tpl'),
		];
	}

	public function getObjectInfo(){
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$pid = urldecode($_REQUEST['id']);
		$interface->assign('pid', $pid);
		$archiveObject = $fedoraUtils->getObject($pid);
		$recordDriver  = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);

		$url   = $recordDriver->getLinkUrl();
		$title = $recordDriver->getTitle();
		$interface->assign('url', $url);
		$interface->assign('title', $title);
		$interface->assign('description', $recordDriver->getDescription());
		$interface->assign('image', $recordDriver->getBookcoverUrl('medium'));

		$urlStr               = "<a href=\"$url\" onclick='Pika.Archive.setForExhibitNavigation({$_COOKIE['recordIndex']},{$_COOKIE['page']})'>";
		$escapedPid           = urlencode($pid);
		$addToFavoritesLabel  = translate('Add to favorites');
		$addToFavoritesButton = "<button onclick=\"return Pika.Archive.showSaveToListForm(this, '$escapedPid');\" class=\"modal-buttons btn btn-primary\" style='float: left'>$addToFavoritesLabel</button>";
		return [
			'title'        => "{$urlStr}{$title}</a>",
			'modalBody'    => $interface->fetch('Archive/archivePopup.tpl'),
			'modalButtons' => "$addToFavoritesButton{$urlStr}<button class='modal-buttons btn btn-primary'>More Info</button></a>",
		];
	}

	public function getMetadata(){
		global $interface;
		$id = urldecode($_REQUEST['id']);
		$interface->assign('pid', $id);

		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$archiveObject = $fedoraUtils->getObject($id);
		/** @var IslandoraDriver $recordDriver */
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);

		$this->setMoreDetailsDisplayMode();
		//TODO: Not sure what this code blocks ending effect is to be
		if (array_key_exists('secondaryId', $_REQUEST)){
			$secondaryId = urldecode($_REQUEST['secondaryId']);

			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();

			$secondaryObject = $fedoraUtils->getObject($secondaryId);
			/** @var IslandoraDriver $secondaryDriver */
			$secondaryDriver = RecordDriverFactory::initRecordDriver($secondaryObject);

			$secondaryDriver->getMoreDetailsOptions();
		}
		$interface->assign('moreDetailsOptions', $recordDriver->getMoreDetailsOptions());


		$metadata = $interface->fetch('Archive/moredetails-accordion.tpl');
		return [
			'success'  => true,
			'metadata' => $metadata,
		];
	}

	private function setMoreDetailsDisplayMode(){
		// Set Display Mode for More Details Accordion Related Objects and Entities sections
		global $library, $interface;
		$displayMode = empty($library->archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode) ? 'tiled' : $library->archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode;
		$interface->assign('archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode', $displayMode);

	}

	public function getNextRandomObject(){
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$pids     = explode(',', $_REQUEST['id']);
		$pidIndex = rand(0, count($pids) - 1);
		$pid      = $pids[$pidIndex];

		$archiveObject = $fedoraUtils->getObject(trim($pid));
		/** @var IslandoraDriver $recordDriver */
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);

		$randomImagePid = $recordDriver->getRandomObject();
		if ($randomImagePid != null){
			$randomObject     = RecordDriverFactory::initRecordDriver($fedoraUtils->getObject($randomImagePid));
			$randomObjectInfo = [
				'label' => $randomObject->getTitle(),
				'link'  => $randomObject->getRecordUrl(),
				'image' => $randomObject->getBookcoverUrl('medium'),
			];
			$interface->assign('randomObject', $randomObjectInfo);
			return [
				'success' => true,
				'image'   => $interface->fetch('Archive/randomImage.tpl'),
			];
		}else{
			return [
				'success' => false,
				'message' => 'No ID provided',
			];
		}
	}

	public function getTranscript(){
		global $configArray;
		$objectUrl            = $configArray['Islandora']['objectUrl'];
		$transcriptIdentifier = urldecode($_REQUEST['transcriptId']);
		if (strlen($transcriptIdentifier) == 0){
			//Check to see if we can get it based on the
			return [
				'success'    => true,
				'transcript' => 'There is no transcription available for this page.',
			];
		}elseif (strpos($transcriptIdentifier, 'mods:') === 0){
			$objectPid = str_replace('mods:', '', $transcriptIdentifier);
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pageObject  = $fedoraUtils->getObject($objectPid);
			$mods        = $fedoraUtils->getModsData($pageObject);
			$transcript  = $fedoraUtils->getModsValue('transcriptionText', 'marmot', $mods);
			$transcript  = FedoraUtils::modsValuesLineEndings2br($transcript);
			if (strlen($transcript) > 0){
				return [
					'success'    => true,
					'transcript' => $transcript,
				];
			}
		}else{
			$transcriptUrl = $objectUrl . '/' . $transcriptIdentifier;
			$transcript    = file_get_contents($transcriptUrl);

			if ($transcript){
				return [
					'success'    => true,
					'transcript' => $transcript,
				];
			}
		}
		return [
			'success' => false,
		];
	}

	/**
	 * Output vtt file stored in the islandora.
	 * Used to get around the requirements for setting up CORS requests on the Islandora server.
	 */
	public function getVtt(){
		$pid = urldecode($_REQUEST['pid']);
		if (!empty($pid)){
			global $configArray;
			$objectUrl          = $configArray['Islandora']['objectUrl'];
			$closedCaptionsUrl  = $objectUrl . '/' . $pid . '/datastream/CC/view';
			$closedCaptionsData = file_get_contents($closedCaptionsUrl);

			if ($closedCaptionsData){
				header('Content-Type: text/vtt');
				echo $closedCaptionsData;


			}
		}else{
			//TODO: log error
			echo ''; //TODO Echo an httpd error code?

		}

	}

	public function getAdditionalRelatedObjects(){
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$pid = $_REQUEST['id'];
		$interface->assign('pid', $pid);
		$archiveObject = $fedoraUtils->getObject($pid);
		/** @var IslandoraDriver $recordDriver */
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);
		$directlyRelatedObjects = $recordDriver->getDirectlyRelatedArchiveObjects();

		$interface->assign('directlyRelatedObjects', $directlyRelatedObjects);

		$this->setMoreDetailsDisplayMode();

		return [
			'success'           => true,
			'additionalObjects' => $interface->fetch('Archive/additionalRelatedObjects.tpl'),
		];
	}

	function getSaveToListForm(){
		global $interface;

		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';

		//Get a list of all lists for the user
		$containingLists    = [];
		$nonContainingLists = [];
		$listsTooLarge      = [];

		$userLists          = new UserList();
		$userLists->user_id = UserAccount::getActiveUserId();
		$userLists->deleted = 0;
		$userLists->orderBy('dateUpdated > unix_timestamp()-300 desc, if(dateUpdated > unix_timestamp()-300 , dateUpdated, 0) desc, title');
		// Sort lists first by any that have been recently updated (within the last 5 mins)  eg a user is currently build a specific list
		// second sort factor for multiple lists updated within the last five minutes is the last updated time; other lists come next
		// finally alphabetical order for lists that haven't been updated recently
		//This complex sorting allows for a list that has had an entry added to it recently,
		// to show as the default selected list in the dropdown list of which user lists to add a title to
		$userLists->find();
		while ($userLists->fetch()){
			if ($userLists->numValidListItems() >= 2000){
				$listsTooLarge[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}
			//Check to see if the user has already added the title to the list.
			$userListEntry                         = new UserListEntry();
			$userListEntry->listId                 = $userLists->id;
			$userListEntry->groupedWorkPermanentId = $id;
			if ($userListEntry->find(true)){
				$containingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}else{
				$nonContainingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}
		}

		$interface->assign('containingLists', $containingLists);
		$interface->assign('nonContainingLists', $nonContainingLists);
		$interface->assign('largeLists', $listsTooLarge);

		$results = [
			'title'        => 'Add To List',
			'modalBody'    => $interface->fetch('GroupedWork/save.tpl'),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.Archive.saveToList(\"{$id}\"); return false;'>Save To List</button>",
		];
		return $results;
	}

	function saveToList(){
		$result = [];

		if (!UserAccount::isLoggedIn()){
			$result['success'] = false;
			$result['message'] = 'Please log in before adding a title to list.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$result['success'] = true;
			$id                = urldecode($_REQUEST['id']);
			if (!preg_match("/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}|[A-Z0-9_-]+:[A-Z0-9_-]+$/i", $id)){
				// Is not a valid grouped work Id or archive PID
				$result['success'] = false;
				$result['message'] = 'That is not a valid title to add to the list.';
			}else{
				$listId = $_REQUEST['listId'];
				$notes  = $_REQUEST['notes'];

				//Check to see if we need to create a list
				$userList = new UserList();
				$listOk   = true;
				if (empty($listId)){
					$existingList          = new UserList();
					$existingList->user_id = UserAccount::getActiveUserId();
					$existingList->title   = 'My Favorites';
					$existingList->deleted = 0;
					//Make sure we don't create duplicate My Favorites List
					if ($existingList->find(true)){
						$userList = $existingList;
					}else{
						$userList->title       = 'My Favorites';
						$userList->user_id     = UserAccount::getActiveUserId();
						$userList->public      = 0;
						$userList->description = '';
						$userList->insert();
					}

				}else{
					$userList->id = $listId;
					if (!$userList->find(true)){
						$result['success'] = false;
						$result['message'] = 'Sorry, we could not find that list in the system.';
						$listOk            = false;
					}
				}

				if ($listOk){
					$userListEntry                         = new UserListEntry();
					$userListEntry->listId                 = $userList->id;
					$userListEntry->groupedWorkPermanentId = $id;

					$existingEntry = false;
					if ($userListEntry->find(true)){
						$existingEntry = true;
					}
					$userListEntry->notes     = strip_tags($notes);
					$userListEntry->dateAdded = time();
					if ($userList->defaultSort == 'custom'){
						$weight = $userList->getNextWeightForUserDefinedSort();
						if ($weight){
							$userListEntry->weight = $weight;
						}
					}
					if ($existingEntry){
						$userListEntry->update();
					}else{
						$userListEntry->insert();
					}

					$result['success'] = true;
					$result['message'] = 'This title was saved to your list successfully.';
				}
			}
		}

		return $result;
	}

	/**
	 * @param SearchObject_Islandora $searchObject
	 */
	public function setupTimelineFacetsAndFilters($searchObject){
		if (isset($_REQUEST['dateFilter']) && $_REQUEST['dateFilter'] != 'all'){

			$filter = '';
			$date   = $_REQUEST['dateFilter'];
			if ($date == 'before1880'){
				$filter .= "dateCreated:[* TO 1879-12-31T23:59:59Z]";
			}elseif ($date == 'unknown'){
				$filter .= '-dateCreated:[* TO *]';
			}else{
				$startYear = substr($date, 0, 4);
				$endYear   = (int)$startYear + 9;
				$filter    .= "dateCreated:[$date TO $endYear-12-31T23:59:59Z]";
			}

			if (strlen($filter)){
				$filter = trim($filter, '()');
				$searchObject->addFilter($filter);
			}

		}
		$searchObject->addFacet('dateCreated', 'Date Created');

		$searchObject->addFacetOptions([
			'facet.range'                       => ['dateCreated'],
			'f.dateCreated.facet.missing'       => 'true',
			'f.dateCreated.facet.range.start'   => '1880-01-01T00:00:00Z',
			'f.dateCreated.facet.range.end'     => 'NOW/YEAR',
			'f.dateCreated.facet.range.hardend' => 'true',
			'f.dateCreated.facet.range.gap'     => '+10YEAR',
			'f.dateCreated.facet.range.other'   => 'all',
		]);
	}

	/**
	 * @param                        $sort
	 * @param SearchObject_Islandora $searchObject
	 */
	public function setupTimelineSorts($sort, $searchObject){
		if ($sort == 'title'){
			$searchObject->setSort('fgs_label_s');
		}elseif ($sort == 'newest'){
			$searchObject->setSort('mods_originInfo_qualifier__dateIssued_dt desc,dateCreated desc,fgs_label_s asc');
		}elseif ($sort == 'oldest'){
			$searchObject->setSort('mods_originInfo_qualifier__dateIssued_dt asc,dateCreated asc,fgs_label_s asc');
		}elseif ($sort == 'dateAdded'){
			$searchObject->setSort('fgs_createdDate_dt desc,fgs_label_s asc');
		}elseif ($sort == 'dateModified'){
			$searchObject->setSort('fgs_lastModifiedDate_dt desc,fgs_label_s asc');
		}
	}

	/**
	 * @param $response
	 * @param $interface
	 */
	public function processTimelineData($response, $interface){
		if (!empty($response['facet_counts']['facet_ranges'])){
			$dateFacetInfo = [];
			if (isset($response['facet_counts']['facet_ranges']['dateCreated'])){
				$dateCreatedInfo = $response['facet_counts']['facet_ranges']['dateCreated'];
				if ($dateCreatedInfo['before'] > 0){
					$dateFacetInfo['1870'] = [
						'label' => 'Before 1880',
						'count' => $dateCreatedInfo['before'],
						'value' => 'before1880',
					];
				}
				foreach ($dateCreatedInfo['counts'] as $facetInfo){
					$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'] = [
						'label' => substr($facetInfo[0], 0, 4) . '\'s',
						'count' => $facetInfo[1],
						'value' => $facetInfo[0],
					];
				}
			}

			//Figure out how many unknown dates there are
			$totalFound = 0;
			foreach ($dateFacetInfo as $dateFacet){
				$totalFound += $dateFacet['count'];
			}
			$numUnknown = $response['response']['numFound'] - $totalFound;
			/*if ($numUnknown > 0){
				$dateFacetInfo['Unknown'] = [
						'label' => 'Unknown',
						'count' => $numUnknown,
						'value' => 'unknown'
				];
			}*/
			$interface->assign('numObjectsWithUnknownDate', $numUnknown);

			ksort($dateFacetInfo);

			$interface->assign('dateFacetInfo', $dateFacetInfo);
		}
	}

	private function setCoversDisplayMode(){
		global $interface,
		       $library;
		if (!empty($_REQUEST['archiveCollectionView'])){
			$displayMode = $_REQUEST['archiveCollectionView'];
		}elseif (!empty($_SESSION['archiveCollectionDisplayMode'])){
			$displayMode = $_SESSION['archiveCollectionDisplayMode'];
		}elseif (!empty($library->defaultArchiveCollectionBrowseMode)){
			$displayMode = $library->defaultArchiveCollectionBrowseMode;
		}else{
			$displayMode = 'covers'; // Pika default mode is covers
		}

		$_SESSION['archiveCollectionDisplayMode'] = $displayMode;
		$interface->assign('displayMode', $displayMode);
		return $displayMode;
	}

	public function clearCache(){
		if (empty($_REQUEST['id'])){
			return [
				'success' => false,
				'message' => 'You must supply the id to clear cached data for.',
			];
		}
		$id = $_REQUEST['id'];

		$mainCacheCleared = false;
		$cacheMessage     = '';
		require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
		$objectCache      = new IslandoraObjectCache();
		$objectCache->pid = $id;
		if ($objectCache->find(true)){
			if ($objectCache->delete()){
				/** @var Memcache $memCache */
				global $memCache;
				$memCache->delete('islandora_object_valid_in_pika_' . $id);
				$mainCacheCleared = true;

			}else{
				$cacheMessage = 'Could not delete cached data.<br>';
			}
		}else{
			$mainCacheCleared = true;
			$cacheMessage     = 'Cached data does not exist for that id.<br>';
		}

		$samePikaCleared = false;
		require_once ROOT_DIR . '/sys/Islandora/IslandoraSamePikaCache.php';
		//Check for cached links
		$samePikaCache      = new IslandoraSamePikaCache();
		$samePikaCache->pid = $id;
		if ($samePikaCache->find(true)){
			if ($samePikaCache->delete()){
				$samePikaCleared = true;
			}else{
				$cacheMessage .= 'Could not delete same pika cache';
			}

		}else{
			$cacheMessage .= 'Data not cached for same pika link';
		}

		return [
			'success' => $mainCacheCleared || $samePikaCleared,
			'message' => $cacheMessage,
		];
	}
}
