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

/**
 * Displays Information about Digital Repository (Islandora) Exhibit
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 8/7/2015
 * Time: 7:55 AM
 */

require_once ROOT_DIR . '/services/Archive/Object.php';

class Archive_Exhibit extends Archive_Object {


	function launch(){
		global $interface;
		global $configArray;
		global $timer;


		$fedoraUtils = FedoraUtils::getInstance();

		$this->loadArchiveObjectData();
		$timer->logTime('Loaded Archive Object Data');
		//$this->loadExploreMoreContent();
		//$timer->logTime('Loaded Explore More Content');

		if (isset($_REQUEST['style'])){
			$pikaCollectionDisplay = $_REQUEST['style'];
		}else{
			$pikaCollectionDisplay = $this->recordDriver->getModsValue('pikaCollectionDisplay', 'marmot');
		}

		$repositoryLink = $configArray['Islandora']['repositoryUrl'] . '/islandora/object/' . $this->recordDriver->getUniqueID();
		$interface->assign('repositoryLink', $repositoryLink);

		$description = html_entity_decode($this->recordDriver->getDescription());
		$description = FedoraUtils::modsValuesLineEndings2br($description);
		if (strlen($description)){
			$interface->assign('description', $description);
		}

		$displayType = 'basic';
		switch ($pikaCollectionDisplay){
			//TODO: I have refactored in Islandora 2 that map is map no time line;
			// and that a map with a timeline will be designated mapTimeline
			case 'map':
				$displayType = 'map';
				$mapZoom     = $this->recordDriver->getModsValue('mapZoomLevel', 'marmot');
				if ($mapZoom == null){
					$mapZoom = 9;
				}
				$interface->assign('mapZoom', $mapZoom);
				$interface->assign('showTimeline', 'true');
				break;
			case 'mapNoTimeline':
				$displayType = 'mapNoTimeline';
				$mapZoom     = $this->recordDriver->getModsValue('mapZoomLevel', 'marmot');
				$interface->assign('mapZoom', $mapZoom);
				$interface->assign('showTimeline', 'false');
				break;
			case 'custom':
				$displayType = 'custom';
				//Load the options to show
				$collectionOptionsRaw = $this->recordDriver->getModsValue('collectionOptions', 'marmot');
				$collectionOptions    = explode("\r\n", html_entity_decode($collectionOptionsRaw));
				break;
			case 'timeline':
				$displayType = 'timeline';
				break;
		}
		$additionalCollections = [];
		if ($this->recordDriver->getModsValue('pikaCollectionDisplay', 'marmot') == 'custom' && ($displayType == 'map' || $displayType == 'mapNoTimeline')){
			//Load the options to show
			$collectionOptionsOriginalRaw = $this->recordDriver->getModsValue('collectionOptions', 'marmot');
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
		$interface->assign('displayType', $displayType);
		$this->loadRelatedObjects($displayType, $additionalCollections);
		$timer->logTime('Loaded Related Objects');

		if ($this->archiveObject->getDatastream('BANNER') != null){
			$interface->assign('main_image', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/BANNER/view");
		}

		if ($this->archiveObject->getDatastream('TN') != null){
			$interface->assign('thumbnail', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/TN/view");
			$exhibitThumbnailURL = $this->recordDriver->getModsValue('thumbnailURL', 'marmot');
			if (!empty($exhibitThumbnailURL)){
				$interface->assign('exhibitThumbnailURL', $exhibitThumbnailURL);
			}
		}

		$interface->assign('showExploreMore', true);

		$imageMapPID = $this->recordDriver->getModsValue('imageMapPID', 'marmot');
		if (!empty($imageMapPID)){
			/** @var FedoraObject $imageMapObject */
			$imageMapObject = $fedoraUtils->getObject($imageMapPID);
			if (!empty($imageMapObject)){
				$imageMapDriver = RecordDriverFactory::initRecordDriver($imageMapObject);
				$imageMapImage  = $imageMapDriver->getBookcoverUrl('large');
				$imageMapMap    = $imageMapObject->getDatastream('MAP')->content;//Substitute the imageMap for the source in the map
				$imageMapMap    = preg_replace('/src="(.*?)"/', "src=\"{$imageMapImage}\"", $imageMapMap);
				$interface->assign('imageMap', $imageMapMap);
				$interface->assign('hasImageMap', true);
				$interface->assign('imageMapPID', $imageMapPID);
			}
		}

		// Determine what type of page to show
		if ($displayType == 'basic'){
			// Set Exhibit Navigation
			$this->startExhibitContext();
			$interface->assign('exhibitPid', $this->pid); // Enables sorting function on exhibit page
			$this->display('exhibit.tpl');
		}elseif ($displayType == 'timeline'){
			// Set Exhibit Navigation
			$this->startExhibitContext();
			$this->display('timelineExhibit.tpl');
		}elseif ($displayType == 'map' || $displayType == 'mapNoTimeline'){
			//Get a list of related places for the object by searching solr to find all objects
			// Set Exhibit Navigation

			$this->startExhibitContext();
			if ($displayType == 'map'){
				$interface->assign('timeline', true);
			}else{
				$interface->assign('timeline', false);
			}

			$this->recordDriver->getRelatedPlaces();
			$this->display('mapExhibit.tpl');

		}elseif ($displayType == 'custom'){
//			$this->endExhibitContext();
			$this->startExhibitContext();
			$collectionTemplates = [];
			foreach ($collectionOptions as $option){
				if (strpos($option, 'searchCollection') === 0){
					$filterOptions     = explode('|', $option);
					$browseFilterImage = isset($filterOptions[1]) ? $filterOptions[1] : "/interface/themes/responsive/images/search_component.png";
					$interface->assign('searchComponentImage', $browseFilterImage);
					$collectionTemplates[] = $interface->fetch('Archive/searchComponent.tpl');
				}elseif (strpos($option, 'googleMap') === 0){
					$filterOptions = explode('|', $option);
					if (count($filterOptions) > 1){
						$interface->assign('additionalMapCollections', $filterOptions[1]);
					}else{
						$interface->assign('additionalMapCollections', '');
					}
					$mapZoom = $this->recordDriver->getModsValue('mapZoomLevel', 'marmot');
					$interface->assign('mapZoom', $mapZoom);

					$this->recordDriver->getRelatedPlaces();
					$collectionTemplates[] = $interface->fetch('Archive/browseByMapComponent.tpl');
				}elseif (strpos($option, 'browseCollectionByTitle') === 0 || strpos($option, 'scroller') === 0){
					$collectionTemplate = 'browse';
					if (strpos($option, 'browseCollectionByTitle') === 0){
						$collectionToLoadFromPID = str_replace('browseCollectionByTitle|', '', $option);
					}else{
						$collectionTemplate      = 'scroller';
						$collectionToLoadFromPID = str_replace('scroller|', '', $option);
					}

					$collectionToLoadFromObject = $fedoraUtils->getObject($collectionToLoadFromPID);
					/** @var CollectionDriver|BookDriver $collectionDriver */
					$collectionDriver = RecordDriverFactory::initRecordDriver($collectionToLoadFromObject);

					$collectionObjects = $collectionDriver->getChildren();
					$collectionTitles  = [];

					//Check the MODS for the collection to see if it has information about ordering
					// Likely used for Exhibits that have 1 page.
					// Might be for Exhibit of Exhibits
					$sortingInfo = $collectionDriver->getModsValue('collectionOrder', 'marmot');
					if ($sortingInfo){
						$sortingInfo  = explode("&#xD;\n", $sortingInfo);
						$existingPids = array_flip($collectionObjects);
						foreach ($sortingInfo as $pid){
							if (!array_key_exists($pid, $existingPids)){
								$collectionObjects[] = $pid;
							}
						}
					}
					foreach ($collectionObjects as $childPid){
						$childObject     = RecordDriverFactory::initRecordDriver($fedoraUtils->getObject($childPid));
						$collectionTitle = [
							'pid'   => $childPid,
							'title' => $childObject->getTitle(),
							'link'  => $childObject->getRecordUrl(),
						];
						if ($collectionTemplate == 'scroller'){
							$collectionTitle['image'] = $childObject->getBookcoverUrl('medium');
							//MDN 12/27/2016 Jordan and I talked today and decided that we would just show the actual object rather than using the scroller as a facet.
							//$collectionTitle['onclick'] = "return Pika.Archive.handleCollectionScrollerClick('{$childObject->getUniqueID()}')";
							if ($childObject->getViewAction() == 'Exhibit'){
								// Always an Exhibit?
								$collectionTitle['isExhibit'] = true;
							}
						}
						$collectionTitles[$childPid] = $collectionTitle;
					}

					//Check the MODS for the collection to see if it has information about ordering
					// Likely used for Exhibits that have 1 page.
					// Might be for Exhibit of Exhibits
					if ($sortingInfo){
						$sortedImages = [];
						foreach ($sortingInfo as $sortingPid){
							if (array_key_exists($sortingPid, $collectionTitles)){
								$sortedImages[] = $collectionTitles[$sortingPid];
								unset($collectionTitles[$sortingPid]);
							}
						}
						//Add any images that weren't specifically sorted
						$collectionTitles = $sortedImages + $collectionTitles;
					}

					$browseCollectionTitlesData = [
						'collectionPid'    => $collectionToLoadFromObject,
						'title'            => $collectionToLoadFromObject->label,
						'collectionTitles' => $collectionTitles,
					];
					$interface->assignAppendToExisting('browseCollectionTitlesData', $browseCollectionTitlesData);
					if (count($collectionTitles) > 0){
						if ($collectionTemplate == 'browse'){
							// TODO: determine exhibit navigation
							$interface->assign('isCollectionOnExhibitPage', true); // only needed for the fetch below
							$collectionTemplates[] = $interface->fetch('Archive/browseCollectionTitles.tpl');
						}else{
							$collectionTemplates[] = $interface->fetch('Archive/collectionScroller.tpl');
						}
					}

				}elseif (strpos($option, 'randomImage') === 0){
					$filterOptions      = explode('|', $option);
					$randomObjectPids   = [];
					$randomObjectPids[] = $this->pid;
					if (count($filterOptions) > 1){
						$randomObjectPids = explode(',', $filterOptions[1]);
					}
					//Select a collection to load from at random
					$rand                = rand(0, count($randomObjectPids) - 1);
					$randomCollectionPid = $randomObjectPids[$rand];
					$interface->assign('randomObjectPids', implode(',', $randomObjectPids));
					/** @var CollectionDriver $randomObjectCollectionDriver */
					$randomObjectCollectionDriver = RecordDriverFactory::initIslandoraDriverFromPid(trim($randomCollectionPid));
					$randomImagePid               = $randomObjectCollectionDriver->getRandomObject();
					if ($randomImagePid != null){
						$randomObject     = RecordDriverFactory::initRecordDriver($fedoraUtils->getObject(trim($randomImagePid)));
						$randomObjectInfo = [
							'label' => $randomObject->getTitle(),
							'link'  => $randomObject->getRecordUrl(),
							'image' => $randomObject->getBookcoverUrl('medium'),
						];
						$interface->assign('randomObject', $randomObjectInfo);
						$collectionTemplates[] = $interface->fetch('Archive/randomImageComponent.tpl');
					}

				}elseif ($option == 'browseAllObjects'){
					$collectionTemplates[] = $interface->fetch('Archive/browseCollectionComponent.tpl');
				}elseif ((strpos($option, 'browseFilter') === 0) || strpos($option, 'browseEntityFilter') === 0){
					$filterOptions         = explode('|', $option);
					$browseFilterFacetName = $filterOptions[1];
					$browseFilterLabel     = $filterOptions[2];
					$interface->assign('browseFilterLabel', $browseFilterLabel);
					$interface->assign('browseFilterFacetName', $browseFilterFacetName);
					$browseFilterImage = isset($filterOptions[3]) ? $filterOptions[3] : "/interface/themes/responsive/images/search_component.png";
					$interface->assign('browseFilterImage', $browseFilterImage);

					if (strpos($option, 'browseEntityFilter') === 0){
						$collectionTemplates[] = $interface->fetch('Archive/browseEntityFilterComponent.tpl');
					}else{
						$collectionTemplates[] = $interface->fetch('Archive/browseFilterComponent.tpl');
					}

				}

			}
			$interface->assign('collectionTemplates', $collectionTemplates);

			$this->display('customExhibit.tpl');
		}
	}

	function loadRelatedObjects($displayType, $additionalCollections){
		global $interface;
		global $timer;
		global $pikaLogger;
		$fedoraUtils = FedoraUtils::getInstance();
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->clearHiddenFilters();
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		//Don't show pages of books or parts of an album
		$searchObject->addHiddenFilter('!RELS_EXT_isConstituentOf_uri_ms', "*");
		$searchObject->clearFilters();
		if (isset($additionalCollections) && count($additionalCollections) > 0){
			$filter = "RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$this->pid}\"";
			foreach ($additionalCollections as $collection){
				$filter .= " OR RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/" . trim($collection) . "\"";
			}
			$searchObject->addFilter($filter);
		}else{
			$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$this->pid}\"");
		}

		$searchObject->clearFacets();
		if ($displayType == 'map' || $displayType == 'mapNoTimeline' || $displayType == 'custom'){
			$searchObject->addFacet('mods_extension_marmotLocal_relatedEntity_place_entityPid_ms');
			$searchObject->addFacet('mods_extension_marmotLocal_relatedPlace_entityPlace_entityPid_ms');
			$searchObject->addFacet('mods_extension_marmotLocal_militaryService_militaryRecord_relatedPlace_entityPlace_entityPid_ms');
			$searchObject->addFacet('mods_extension_marmotLocal_describedEntity_entityPid_ms');
			$searchObject->addFacet('mods_extension_marmotLocal_picturedEntity_entityPid_ms');
			$searchObject->setFacetLimit(250);
		}

		$searchObject->setLimit(24);

		$searchObject->setSort('fgs_label_s');
		$interface->assign('showThumbnailsSorted', true);

		$relatedImages  = [];
		$mappedPlaces   = [];
		$unmappedPlaces = [];
		$response       = $searchObject->processSearch(true, false);
		$summary        = $searchObject->getResultSummary();
		$recordIndex    = $summary['startRecord'];
		$page           = $summary['page'];
		$interface->assign('page', $page);

//			// Add Collection Navigation Links
		$searchObject->close(); // Trigger save search
		$lastExhibitObjectsSearch = $searchObject->getSearchId(); // Have to save the search first.

//		$interface->assign('collectionSearchId', $lastExhibitObjectsSearch);
		$timer->logTime('Did initial search for related objects');
		if ($response && $response['response']['numFound'] > 0){
			if ($displayType == 'map' || $displayType == 'mapNoTimeline' || $displayType == 'custom'){
				$minLat            = null;
				$minLong           = null;
				$maxLat            = null;
				$maxLong           = null;
				$geometricMeanLat  = 0;
				$geometricMeanLong = 0;
				$numPoints         = 0;
				foreach ($response['facet_counts']['facet_fields'] as $facetField){
					foreach ($facetField as $facetInfo){
						if (substr($facetInfo[0], 0, 5) == 'place'){
							$mappedPlace = array(
								'pid'   => $facetInfo[0],
								'count' => $facetInfo[1],
							);
							$cache       = new IslandoraObjectCache();
							$cache->pid  = $facetInfo[0];
							$updateCache = true;
							if ($cache->find(true)){
								if ($cache->hasLatLong != null){
									$updateCache = false;
								}
							}
							if ($updateCache){
								/** @var PlaceDriver $placeEntityDriver */
								$fedoraObject               = $fedoraUtils->getObject($mappedPlace['pid']);
								if (!empty($fedoraObject)){
									$placeEntityDriver = RecordDriverFactory::initRecordDriver($fedoraObject);
									if (!PEAR::isError($placeEntityDriver)){
										$mappedPlace['label'] = $placeEntityDriver->getTitle();
										$mappedPlace['url']   = $placeEntityDriver->getRecordUrl();
										if ($placeEntityDriver instanceof PlaceDriver){
											$geoData = $placeEntityDriver->getGeoData();
										}else{
											//echo("Warning {$placeEntityDriver->getTitle()} ({$placeEntityDriver->getUniqueID()}) was not a place");
											continue;
										}
										if ($geoData){
											$mappedPlace['latitude']  = $geoData['latitude'];
											$mappedPlace['longitude'] = $geoData['longitude'];
										}
										$cache      = new IslandoraObjectCache();
										$cache->pid = $facetInfo[0];//Should always find the cache now since it gets built when creating the record driver
										if ($cache->find(true)){
											if ($geoData){
												$cache->latitude   = $mappedPlace['latitude'];
												$cache->longitude  = $mappedPlace['longitude'];
												$cache->hasLatLong = 1;
											}else{
												$cache->latitude   = null;
												$cache->longitude  = null;
												$cache->hasLatLong = 0;
											}
											$cache->lastUpdate = time();
											$cache->update();
										}
										$timer->logTime('Loaded information about related place');
									} else {
										$pikaLogger->error('Failed to initialize driver for place entity' . $mappedPlace['pid']);
									}
								}
							}else{
								$mappedPlace['label'] = $cache->title;
								$mappedPlace['url']   = '/Archive/' . $cache->pid . '/Place';
								if ($cache->hasLatLong){
									$mappedPlace['latitude']  = $cache->latitude;
									$mappedPlace['longitude'] = $cache->longitude;
								}
								$timer->logTime('Loaded information about related place from cache');
							}

							if (isset($mappedPlace['latitude']) && isset($mappedPlace['longitude'])){
								$geometricMeanLat  += $mappedPlace['latitude'] * $mappedPlace['count'];
								$geometricMeanLong += $mappedPlace['longitude'] * $mappedPlace['count'];
								$numPoints         += $mappedPlace['count'];

								if ($minLat == null || $mappedPlace['latitude'] < $minLat){
									$minLat = $mappedPlace['latitude'];
								}
								if ($maxLat == null || $mappedPlace['latitude'] > $maxLat){
									$maxLat = $mappedPlace['latitude'];
								}
								if ($minLong == null || $mappedPlace['longitude'] < $minLong){
									$minLong = $mappedPlace['longitude'];
								}
								if ($maxLong == null || $mappedPlace['longitude'] > $maxLong){
									$maxLong = $mappedPlace['longitude'];
								}

								if (array_key_exists($mappedPlace['pid'], $mappedPlaces)){
									$mappedPlaces[$mappedPlace['pid']]['count'] += $mappedPlace['count'];
								}else{
									$mappedPlaces[$mappedPlace['pid']] = $mappedPlace;
								}

								if (count($mappedPlaces) == 1){
									$interface->assign('selectedPlace', $mappedPlace['pid']);
									$_SESSION['placePid'] = $mappedPlace['pid'];
								}
							}elseif (array_key_exists($mappedPlace['pid'], $unmappedPlaces)){
								$unmappedPlaces[$mappedPlace['pid']]['count'] += $mappedPlace['count'];
							}else{
								$unmappedPlaces[$mappedPlace['pid']] = $mappedPlace;
							}
						}
					}
				}

				if (isset($_REQUEST['placePid'])){
					$interface->assign('selectedPlace', urldecode($_REQUEST['placePid']));
					$_SESSION['placePid'] = $_REQUEST['placePid'];
				}
				$interface->assign('mappedPlaces', $mappedPlaces);
				$interface->assign('unmappedPlaces', $unmappedPlaces);

				$geolocatedObjects    = $this->recordDriver->getGeolocatedObjects();
				$totalMappedLocations = count($mappedPlaces) + $geolocatedObjects['numFound'];
				$interface->assign('geolocatedObjects', $geolocatedObjects['objects']);
				foreach ($geolocatedObjects['objects'] as $object){
					$geometricMeanLat  += $object['latitude'] * $object['count'];
					$geometricMeanLong += $object['longitude'] * $object['count'];
					$numPoints         += $object['count'];

					if ($minLat == null || $object['latitude'] < $minLat){
						$minLat = $object['latitude'];
					}
					if ($maxLat == null || $object['latitude'] > $maxLat){
						$maxLat = $object['latitude'];
					}
					if ($minLong == null || $object['longitude'] < $minLong){
						$minLong = $object['longitude'];
					}
					if ($maxLong == null || $object['longitude'] > $maxLong){
						$maxLong = $object['longitude'];
					}
				}
				$interface->assign('minLat', $minLat);
				$interface->assign('maxLat', $maxLat);
				$interface->assign('minLong', $minLong);
				$interface->assign('maxLong', $maxLong);
				if ($numPoints > 0){
					$interface->assign('mapCenterLat', $geometricMeanLat / $numPoints);
					$interface->assign('mapCenterLong', $geometricMeanLong / $numPoints);
				}
				$interface->assign('totalMappedLocations', $totalMappedLocations);
			}else{
				$_SESSION['exhibitSearchId'] = $lastExhibitObjectsSearch;
//				$pikaLogger->debug("Setting exhibit search id to $lastExhibitObjectsSearch");

				$displayMode = $this->archiveCollectionDisplayMode();
				$this->setShowCovers();
				if ($displayMode == 'list'){
					$recordSet = $searchObject->getResultRecordHTML();
					$interface->assign('recordSet', $recordSet);

				}else{
					//Load related objects
					$allObjectsAreCollections = true;
					foreach ($response['response']['docs'] as $objectInCollection){
						/** @var IslandoraDriver $firstObjectDriver */
						$firstObjectDriver                                = RecordDriverFactory::initRecordDriver($objectInCollection);
						$relatedImages[$firstObjectDriver->getUniqueID()] = [
							'pid'         => $firstObjectDriver->getUniqueID(),
							'title'       => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
							'link'        => $firstObjectDriver->getRecordUrl(),
							'recordIndex' => $recordIndex++,
						];

						if (!($firstObjectDriver instanceof CollectionDriver)){
							$allObjectsAreCollections = false;
						}
						$timer->logTime('Loaded related object');
					}
					$interface->assign('showWidgetView', $allObjectsAreCollections);
				}
//				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd', $summary['endRecord']);

				//Check the MODS for the collection to see if it has information about ordering
				// Likely used for Exhibits that have 1 page.
				// Might be for Exhibit of Exhibits
				$sortingInfo = $this->recordDriver->getModsValue('collectionOrder', 'marmot');
				if ($sortingInfo){
					$sortingInfo  = explode('&#xD;\n', $sortingInfo);
					$sortedImages = array();
					foreach ($sortingInfo as $sortingPid){
						if (array_key_exists($sortingPid, $relatedImages)){
							$sortedImages[] = $relatedImages[$sortingPid];
							unset($relatedImages[$sortingPid]);
						}
					}
					//Add any images that weren't specifically sorted
					$relatedImages = $sortedImages + $relatedImages;
					//TODO: set navigation order
				}

				$interface->assign('relatedImages', $relatedImages);
			}
		}
	}

	private function startExhibitContext(){
		global $pikaLogger;

		$_SESSION['ExhibitContext']   = $this->recordDriver->getUniqueID(); // Set Exhibit object ID
		$_COOKIE['exhibitNavigation'] = true; // Make sure exhibit context isn't cleared when loading the exhibit
		if (!empty($_REQUEST['placePid'])){ // May never be actually set here.
			// Add the place PID to the session if this is a Map Exhibit
			$_SESSION['placePid'] = $_REQUEST['placePid'];
			$pikaLogger->debug("Starting exhibit context " . $this->recordDriver->getUniqueID() . " place {$_SESSION['placePid']}");
		}else{
			$pikaLogger->debug("Starting exhibit context " . $this->recordDriver->getUniqueID() . " no place");
			$_SESSION['placePid']   = null;
			$_SESSION['placeLabel'] = null;
		}
		if (!empty($_COOKIE['exhibitInAExhibitParentPid'])){
			$_SESSION['exhibitInAExhibitParentPid'] = $_COOKIE['exhibitInAExhibitParentPid'];
		}else{
			$_SESSION['exhibitInAExhibitParentPid'] = null;
		}
	}
}
