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
//TODO: Move to Archive/ directory
class ExploreMore {
	private $relatedCollections;

	/** Filter fields whose values are not useful as explore-more search terms. */
	private array $exploreMoreQueryExcludedFields = [
		// Catalog
		'literary_form',
		'literary_form_full',
		'target_audience',
		'target_audience_full',
		// Islandora1
		'mods_genre_s',
		// Islandora2
		'ss_model',
		'ss_name_1',
		'sm_format',
		'sm_name_43',
		'sm_genre',
		'sm_name_2',
		'sm_legacy_resource_type',
		'sm_name_22',
	];

	/**
	 * @param string $activeSection
	 * @param IndexRecord|IslandoraDriver $recordDriver
	 */
	function loadExploreMoreSidebar($activeSection, $recordDriver){
		//TODO: remove title from $exploreMoreSectionsToShow array
		global $interface;
		global $configArray;
		global $timer;
		global $library;

		if (!empty($configArray['Islandora']['enabled'])) {
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
		}
		$exploreMoreSectionsToShow = [];
		$relatedPikaContent        = [];

		if ($activeSection == 'archive'){
			//If this is a book or a page, show a table of contents
			//Check to see if the record is part of a compound object.  If so we will want to link to the parent compound object.
			if ($recordDriver instanceof PageDriver){
				/** @var IslandoraDriver $parentObject */
				$parentObject = $recordDriver->getParentObject();

				if ($parentObject != null){
					/** @var IslandoraDriver $parentDriver */
					$parentDriver = RecordDriverFactory::initRecordDriver($parentObject);

					//If the parent object is a section, then get the parent again
					/** @var IslandoraDriver $parentOfParent */
					$parentOfParent = $parentDriver->getParentObject();
					if ($parentOfParent != null ){
						$parentOfParentDriver = RecordDriverFactory::initRecordDriver($parentOfParent);
						if ($parentOfParentDriver){
							$parentObject = $parentOfParent;
							$parentDriver = $parentOfParentDriver;
						}
					}

					$exploreMoreSectionsToShow['parentBook'] = [
//							'title' => 'Entire Book',
							'format' => 'list',
							'values' => [
								[
									'pid'    => $parentObject->id, // TODO: is id right
									'label'  => $parentDriver->getTitle(),
									'link'   => $parentDriver->getRecordUrl(),
									'image'  => $parentDriver->getBookcoverUrl('small'),
									'object' => $parentObject,
								],
							]
					];

					$exploreMoreSectionsToShow = $this->setupTableOfContentsForBook($parentDriver, $exploreMoreSectionsToShow, false);

					$this->relatedCollections        = $parentDriver->getRelatedCollections();
					$this->relatedCollections['all'] = [
						'label' => 'See All Digital Archive Collections',
						'link'  => '/Archive/Home'
					];

					if (count($this->relatedCollections) > 1){ //Don't show if the only link is back to the All Collections page
						$displayType = count($this->relatedCollections) > 3 ? 'textOnlyList' : 'list';
						$exploreMoreSectionsToShow['relatedCollections'] = [
//								'title' => 'Related Archive Collections',
								'format' => $displayType,
								'values' => $this->relatedCollections
						];
					}
				}
				$timer->logTime('Loaded table of contents');
			}elseif ($recordDriver instanceof BookDriver || $recordDriver instanceof CompoundDriver){
				if ($recordDriver->getFormat() != 'Postcard'){
					/** @var CompoundDriver $bookDriver */
					$isBook = $recordDriver->getFormat();
					$exploreMoreSectionsToShow = $this->setupTableOfContentsForBook($recordDriver, $exploreMoreSectionsToShow, true);
					$timer->logTime("Loaded table of contents for book");
				}
			}

			/** @var IslandoraDriver $archiveDriver */
			$archiveDriver = $recordDriver;
			if (!isset($this->relatedCollections)){
				$this->relatedCollections = $archiveDriver->getRelatedCollections();
				$this->relatedCollections['all'] = [
					'label' => 'See All Digital Archive Collections',
					'link' => '/Archive/Home'
				];
				if (count($this->relatedCollections) > 1){ //Don't show if the only link is back to the All Collections page
					$displayType = count($this->relatedCollections) > 3 ? 'textOnlyList' : 'list';
					$exploreMoreSectionsToShow['relatedCollections'] = [
//							'title' => 'Related Archive Collections',
							'format' => $displayType,
							'values' => $this->relatedCollections
					];
				}
				$timer->logTime("Loaded related collections for archive object");
			}

			//Find content from the catalog that is directly related to the object or collection based on linked data

			if ($library->archiveOnlyInterface ?? false){
				$timer->logTime('Skipped Pika Content');
			}else{
				$relatedPikaContent = $archiveDriver->getRelatedPikaContent();
				if (count($relatedPikaContent) > 0){
					$exploreMoreSectionsToShow['linkedCatalogRecords'] = [
							//						'title' => 'Librarian Picks',
														'format' => 'scroller',
														'values' => $relatedPikaContent
					];
				}
				$timer->logTime('Loaded related Pika content');
			}
			//Find other entities
		}

		//Get subjects that can be used for searching other systems
		$subjects                   = $recordDriver->getAllSubjectHeadings(true, 5);
		$subjectsForSearching       = [];
		$quotedSubjectsForSearching = [];
		foreach ($subjects as $subject){
			if (is_array($subject)){
				$searchSubject = implode(" ", $subject);
			}else{
				$searchSubject = $subject;
			}
			$separatorPosition = strpos($searchSubject, ' -- ');
			if ($separatorPosition > 0){
				$searchSubject = substr($searchSubject, 0, $separatorPosition);
			}
			$searchSubject                = preg_replace('/\(.*?\)/', "", $searchSubject);
			$searchSubject                = trim(preg_replace('/[\/|:.,"]/', "", $searchSubject));
			$subjectsForSearching[]       = $searchSubject;
			$quotedSubjectsForSearching[] = '"' . $searchSubject . '"';
		}

		$subjectsForSearching = array_slice($subjectsForSearching, 0, 5);
		$searchTerm           = implode(' or ', $subjectsForSearching);
		$quotedSearchTerm     = implode(' OR ', $quotedSubjectsForSearching);

		//Get objects from the archive based on search subjects
		if ($activeSection != 'archive'){
			foreach ($subjectsForSearching as $curSubject){
				$exactEntityMatches = $this->loadExactEntityMatches([], $curSubject);
				if (count($exactEntityMatches) > 0){
					$exploreMoreSectionsToShow['exactEntityMatches'] = [
//							'title' => 'Related People, Places &amp; Events',
							'format' => 'list',
							'values' => usort($exactEntityMatches, 'ExploreMore::sortRelatedEntities')
					];
				}
			}
			$timer->logTime('Loaded related entities');
		}

//		//Always load ebsco even if we are already in that section
//		$ebscoMatches = $this->loadEbscoOptions('', [], $searchTerm);
//		if (count($ebscoMatches) > 0){
//			$interface->assign('relatedArticles', $ebscoMatches);
//		}

		//Load related content from the archive

		if ($activeSection == 'archive'){
			/** @var IslandoraDriver $archiveDriver */
			$archiveDriver = $recordDriver;
			$relatedArchiveEntities = $this->getRelatedArchiveEntities($archiveDriver);
			if (count($relatedArchiveEntities) > 0){
				if (isset($relatedArchiveEntities['people'])){
					usort($relatedArchiveEntities['people'], 'ExploreMore::sortRelatedEntities');
					$exploreMoreSectionsToShow['relatedPeople'] = [
//							'title' => 'Associated People',
							'format' => 'textOnlyList',
							'values' => $relatedArchiveEntities['people']
					];
				}
				if (isset($relatedArchiveEntities['places'])){
					usort($relatedArchiveEntities['places'], 'ExploreMore::sortRelatedEntities');
					$exploreMoreSectionsToShow['relatedPlaces'] = [
//							'title' => 'Associated Places',
							'format' => 'textOnlyList',
							'values' => $relatedArchiveEntities['places']
					];
				}
				if (isset($relatedArchiveEntities['organizations'])){
					usort($relatedArchiveEntities['organizations'], 'ExploreMore::sortRelatedEntities');
					$exploreMoreSectionsToShow['relatedOrganizations'] = [
//							'title' => 'Associated Organizations',
							'format' => 'textOnlyList',
							'values' => $relatedArchiveEntities['organizations']
					];
				}
				if (isset($relatedArchiveEntities['events'])){
					usort($relatedArchiveEntities['events'], 'ExploreMore::sortRelatedEntities');
					$exploreMoreSectionsToShow['relatedEvents'] = [
//							'title' => 'Associated Events',
							'format' => 'textOnlyList',
							'values' => $relatedArchiveEntities['events']
					];
				}
			}
		}

		$searchSubjectsOnly    = $activeSection == 'archive';
		$driver                = $activeSection == 'archive' ? $recordDriver : null;
		$relatedArchiveContent = $this->getRelatedArchiveObjects($quotedSearchTerm, $searchSubjectsOnly, $driver);
		if (count($relatedArchiveContent) > 0){
			$exploreMoreSectionsToShow['relatedArchiveData'] = [
				//'title'  => 'From the Archive',
				'format' => 'subsections',
				'values' => $relatedArchiveContent,
			];
		}

		if ($activeSection != 'catalog' && !$library->archiveOnlyInterface){
			$relatedWorks = $this->getRelatedWorks($quotedSubjectsForSearching, $relatedPikaContent);
			if ($relatedWorks['numFound'] > 0){
				$exploreMoreSectionsToShow['relatedCatalog'] = [
					//'title'    => 'More From the Catalog',

					'format'   => 'scrollerWithLink',
					'values'   => $relatedWorks['values'],
					'link'     => $relatedWorks['link'],
					'numFound' => $relatedWorks['numFound'],
				];
			}
		}

		if ($activeSection == 'archive'){
			/** @var IslandoraDriver $archiveDriver */
			$archiveDriver = $recordDriver;

			//Load related subjects
			$relatedSubjects = $this->getRelatedArchiveSubjects($archiveDriver);
			if (count($relatedSubjects) > 0){
				usort($relatedSubjects, 'ExploreMore::sortRelatedEntities');
				$exploreMoreSectionsToShow['relatedSubjects'] = [
					//'title'  => 'Related Subjects',
						'format' => 'textOnlyList',
						'values' => $relatedSubjects
				];
			}

			//Load DPLA Content
			if ($archiveDriver->isEntity()){
				require_once ROOT_DIR . '/sys/SearchObject/DPLA.php';
				$dpla = new DPLA();
				//Check to see if we get any results from DPLA for this entity
				$dplaResults = $dpla->getDPLAResults('"' . $archiveDriver->getTitle() . '"');
				if (count($dplaResults)){
					$exploreMoreSectionsToShow['dpla'] = [
						//'title'           => 'Digital Public Library of America',
						'format'          => 'scrollerWithLink',
						'values'          => $dplaResults['records'],
						'link'            => 'http://dp.la/search?q=' . urlencode('"' . $archiveDriver->getTitle() . '"'),
						'openInNewWindow' => true,
					];
				}
			}else{
				//Display donor and contributor information
				$brandingResults = $archiveDriver->getBrandingInformation();

				if (count($brandingResults) > 0){
					//Sort and filter the acknowledgements
					$foundDuplicatePids = true;
					while ($foundDuplicatePids){
						$foundDuplicatePids = false;
						$indexToRemove      = -1;
						$keys               = array_keys($brandingResults);
						for ($i = 0; $i < count($brandingResults) - 1; $i++){
							for ($j = $i + 1; $j < count($brandingResults); $j++ ){
								if ($brandingResults[$keys[$i]]['pid'] == $brandingResults[$keys[$j]]['pid']){
									$foundDuplicatePids = true;
									if ($brandingResults[$keys[$i]]['sortIndex'] > $brandingResults[$keys[$j]]['sortIndex']){
										$indexToRemove = $i;
									}else{
										$indexToRemove = $j;
									}
									break;
								}
							}
							if ($foundDuplicatePids) break;
						}
						if ($foundDuplicatePids){
							unset($brandingResults[$keys[$indexToRemove]]);
						}
					}

					usort($brandingResults, 'sortBrandingResults');

					$exploreMoreSectionsToShow['acknowledgements'] = [
						//'title'      => 'Acknowledgements',
						'format'     => 'list',
						'values'     => $brandingResults,
						'showTitles' => true,
					];
				}
			}
		}

		$interface->assign('exploreMoreSections', $exploreMoreSectionsToShow);
	}

	/**
	 * Derive a plain-text search term to drive the Explore More bar.
	 *
	 * Returns the current `lookfor` query string if one is present.  When the
	 * search box is empty, falls back to the value of the first applied filter
	 * whose field is not in {@see $exploreMoreQueryExcludedFields}
	 * (e.g., a format facet value that was clicked).  Returns an empty string
	 * when no usable term can be found.
	 *
	 * @return string
	 */
	function getExploreMoreQuery(){
		$searchTerm = !empty($_REQUEST['lookfor']) ? $_REQUEST['lookfor'] : '';
		if (!$searchTerm){
			//No search term found, try to get a search term based on applied filters (just one)
			if (!empty($_REQUEST['filter'])){
				foreach ($_REQUEST['filter'] as $filter){
					if (is_string($filter) && strlen($filter) > 0 && str_contains($filter, ':')){
						$filterVals = explode(':', $filter, 2);  // colon character is the filter delimiter
						if (!in_array($filterVals[0], $this->exploreMoreQueryExcludedFields)) {
							$searchTerm = str_replace('"', '', $filterVals[1]);
							break;
						}
					}
				}
			}
		}
		return $searchTerm;
	}

	/**
	 * @param $exploreMoreOptions
	 * @param string $searchTerm
	 * @return array
	 */
	protected function loadExactEntityMatches($exploreMoreOptions, $searchTerm) {
		global $library;
		global $configArray;
		if ($library->enableArchive) {
			if (isset($configArray['Islandora']['solrUrl']) && $searchTerm) {
				/** @var SearchObject_Islandora $searchObject */
				$searchObject = SearchObjectFactory::initSearchObject('Islandora');
				$searchObject->init();
				$searchObject->setDebugging(false, false);

				//First look specifically for
				$searchObject->setSearchTerms(searchTerms: [
						'lookfor' => $searchTerm,
						'index'   => 'IslandoraTitle'
				]);
				$searchObject->clearHiddenFilters();
				$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
				//First search for people, places, and things
				$searchObject->addHiddenFilter('RELS_EXT_hasModel_uri_s', "(*placeCModel OR *personCModel OR *eventCModel)");
				$response = $searchObject->processSearch(true, false);
				if ($response && $response['response']['numFound'] > 0) {
					//Check the docs to see if we have a match for a person, place, or event
					$numProcessed = 0;
					foreach ($response['response']['docs'] as $doc) {
						$entityDriver         = RecordDriverFactory::initRecordDriver($doc);
						$exploreMoreOptions[] = [
								'label' => $entityDriver->getTitle(),
								'image' => $entityDriver->getBookcoverUrl('medium'),
								'link'  => $entityDriver->getRecordUrl(),
						];
						$numProcessed++;
						if ($numProcessed >= 3) {
							break;
						}
					}
				}
			}
		}

		return $exploreMoreOptions;
	}

	/**
	 * @param $exploreMoreOptions
	 * @param $searchTerm
	 * @return array
	 */
	public function loadCatalogOptions($exploreMoreOptions, $searchTerm) {
		if (!empty($searchTerm)) {
				/** @var SearchObject_Solr $searchObject */
				$searchObjectSolr = SearchObjectFactory::initSearchObject();
				$searchObjectSolr->init('local');
				$searchObjectSolr->setSearchTerms([
					'lookfor' => $searchTerm,
					'index'   => 'Keyword'
				]);
				$searchObjectSolr->clearHiddenFilters();
				$searchObjectSolr->clearFilters();
				$searchObjectSolr->addFilter('literary_form_full:Non Fiction');
				$searchObjectSolr->addFilter('target_audience:Adult');
				$searchObjectSolr->setPage(1);
				$searchObjectSolr->setLimit(5);
				$results = $searchObjectSolr->processSearch(true, false);

				if (!empty($results['response'])) {
					$numCatalogResultsAdded = 0;
					$numCatalogResults      = $results['response']['numFound'];
					foreach ($results['response']['docs'] as $doc) {
						/** @var GroupedWorkDriver $driver */
						$driver            = RecordDriverFactory::initRecordDriver($doc);
						if ($numCatalogResultsAdded == 4 && $numCatalogResults > 5) {
							//Add a link to the remaining catalog results
							$exploreMoreOptions[] = [
								'label'       => "Catalog Results ($numCatalogResults)",
								'description' => "Catalog Results ($numCatalogResults)",
								'image'       => '/interface/themes/responsive/images/library_symbol.png',
								'link'        => $searchObjectSolr->renderSearchUrl(),
								'usageCount'  => 1
							];
						} else {
							//Add a link to the actual title
							$exploreMoreOptions[] = [
								'label'       => $driver->getTitle(),
								'description' => $driver->getTitle(),
								'image'       => $driver->getBookcoverUrl('medium'),
								'link'        => $driver->getLinkUrl(),
								'usageCount'  => 1
							];
						}
						$numCatalogResultsAdded++;
					}
				}
			}
		return $exploreMoreOptions;
	}

	/**
	 * @param array $exploreMoreOptions parameter to populate with results
	 * @param string $searchTerm
	 * @return array
	 */
	public function loadLegacyArchiveOptions($exploreMoreOptions, $searchTerm) {
		global $library;

		$islandoraActive       = false;
		$islandoraSearchObject = null;
		if ($library->enableArchive){
			/** @var SearchObject_Islandora $islandoraSearchObject */
			$islandoraSearchObject = SearchObjectFactory::initSearchObject('Islandora');
			$islandoraSearchObject->init();
			$islandoraActive = $islandoraSearchObject->pingServer(false);
			if (!$islandoraActive){
				global $pikaLogger;
				$pikaLogger->warn('Explore More: Islandora search ping failed.');
			}
		}

		if ($islandoraActive){
			//Check the archive to see if we match an entity.
			$exploreMoreOptions = $this->loadExactEntityMatches($exploreMoreOptions, $searchTerm);

			if (!empty($searchTerm)){
				$islandoraSearchObject->setDebugging(false, false);

				//Get a list of objects in the archive related to this search
				$searchTerms = [
					'lookfor' => $searchTerm,
					'index'   => 'IslandoraKeyword'
				];
				$islandoraSearchObject->setSearchTerms($searchTerms);
				$islandoraSearchObject->addFacet('mods_genre_s', 'Format');
				$islandoraSearchObject->addFacet('RELS_EXT_isMemberOfCollection_uri_ms', 'Collection');
				$islandoraSearchObject->addFacet('mods_extension_marmotLocal_relatedEntity_person_entityPid_ms', 'People');
				$islandoraSearchObject->addFacet('mods_extension_marmotLocal_relatedEntity_place_entityPid_ms', 'Places');
				$islandoraSearchObject->addFacet('mods_extension_marmotLocal_relatedEntity_event_entityPid_ms', 'Events');
				$islandoraSearchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', 'no');

				$response = $islandoraSearchObject->processSearch(true, false);
				if (!empty($response['response']['numFound'])){
					//Related content by Type
					foreach ($response['facet_counts']['facet_fields']['mods_genre_s'] as $relatedContentType){
						/** @var SearchObject_Islandora $searchObject2 */
						$searchObject2 = SearchObjectFactory::initSearchObject('Islandora');
						$searchObject2->init();
						$searchObject2->setDebugging(false, false);
						$searchObject2->setSearchTerms($searchTerms);
						$searchObject2->addFilter("mods_genre_s:{$relatedContentType[0]}");
						$searchObject2->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', 'no');
						$response2 = $searchObject2->processSearch(true, false);
						if ($response2 && $response2['response']['numFound'] > 0){
							$firstObject = reset($response2['response']['docs']);
							/** @var IslandoraDriver $firstObjectDriver */
							$firstObjectDriver = RecordDriverFactory::initRecordDriver($firstObject);
							$numMatches        = $response2['response']['numFound'];
							$contentType       = ucwords(translate($relatedContentType[0]));
							if ($numMatches == 1){
								$exploreMoreOptions[] = [
									'label'       => "{$contentType}s ({$numMatches})",
									'description' => "{$contentType}s related to {$searchObject2->getQuery()}",
									'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
									'link'        => $firstObjectDriver->getRecordUrl(),
								];
							}else{
								$exploreMoreOptions[] = [
									'label'       => "{$contentType}s ({$numMatches})",
									'description' => "{$contentType}s related to {$searchObject2->getQuery()}",
									'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
									'link'        => $searchObject2->renderSearchUrl(),
								];
							}
						}
					}

					require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
					$fedoraUtils = FedoraUtils::getInstance();

					//Related collections
					foreach ($response['facet_counts']['facet_fields']['RELS_EXT_isMemberOfCollection_uri_ms'] as $collectionInfo){
						$archiveObject = $fedoraUtils->getObject($collectionInfo[0]);
						if ($archiveObject != null){
							$okToAdd = $fedoraUtils->isObjectValidForPika($archiveObject);

							if ($okToAdd){
								$exploreMoreOptions[] = [
									'label'       => $archiveObject->label,
									'description' => $archiveObject->label,
									'image'       => $fedoraUtils->getObjectImageUrl($archiveObject, 'medium'),
									'link'        => "/Archive/{$archiveObject->id}/Exhibit",
									'usageCount'  => $collectionInfo[1]
								];
							}
						}
					}

					//Related Entities
					if (!empty($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_person_entityPid_ms'])){
						$personInfo = reset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_person_entityPid_ms']);
						$numPeople  = count($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_person_entityPid_ms']);
						if ($numPeople == 100){
							$numPeople = '100+';
						}
						$archiveObject = $fedoraUtils->getObject($personInfo[0]);
						$islandoraSearchObject->clearFilters();
						$islandoraSearchObject->addFilter('RELS_EXT_hasModel_uri_s:info:fedora/islandora:personCModel');
						if ($archiveObject != null){
							$exploreMoreOptions[] = [
								'label'       => 'People (' . $numPeople . ')',
								'description' => "People related to {$islandoraSearchObject->getQuery()}",
								'image'       => $fedoraUtils->getObjectImageUrl($archiveObject, 'medium', 'personCModel'),
								'link'        => '/Archive/RelatedEntities?lookfor=' . urlencode($searchTerm) . '&entityType=person',
								'usageCount'  => $numPeople
							];
						}
					}
					if (!empty($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_place_entityPid_ms'])){
						$placeInfo = reset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_place_entityPid_ms']);
						$numPlaces = count($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_place_entityPid_ms']);
						if ($numPlaces == 100){
							$numPlaces = '100+';
						}
						$archiveObject = $fedoraUtils->getObject($placeInfo[0]);
						$islandoraSearchObject->clearFilters();
						$islandoraSearchObject->addFilter('RELS_EXT_hasModel_uri_s:info:fedora/islandora:placeCModel');
						if ($archiveObject != null){
							$exploreMoreOptions[] = [
								'label'       => 'Places (' . $numPlaces . ')',
								'description' => "Places related to {$islandoraSearchObject->getQuery()}",
								'image'       => $fedoraUtils->getObjectImageUrl($archiveObject, 'medium', 'placeCModel'),
								'link'        => '/Archive/RelatedEntities?lookfor=' . urlencode($searchTerm) . '&entityType=place',
								'usageCount'  => $numPlaces
							];
						}
					}
					if (!empty($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_event_entityPid_ms'])){
						$eventInfo = reset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_event_entityPid_ms']);
						$numEvents = count($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_event_entityPid_ms']);
						if ($numEvents == 100){
							$numEvents = '100+';
						}
						$archiveObject = $fedoraUtils->getObject($eventInfo[0]);
						$islandoraSearchObject->clearFilters();
						$islandoraSearchObject->addFilter('RELS_EXT_hasModel_uri_s:info:fedora/islandora:eventCModel');
						if ($archiveObject != null){
							$exploreMoreOptions[] = [
								'label'       => 'Events (' . $numEvents . ')',
								'description' => "Events related to {$islandoraSearchObject->getQuery()}",
								'image'       => $fedoraUtils->getObjectImageUrl($archiveObject, 'medium', 'eventCModel'),
								'link'        => '/Archive/RelatedEntities?lookfor=' . urlencode($searchTerm) . '&entityType=event',
								'usageCount'  => $numEvents
							];
						}
					}
				}
			}
		}

		return $exploreMoreOptions;
	}

	/**
	 * @param $activeSection
	 * @param $searchTerm
	 * @param $exploreMoreOptions
	 * @return array
	 */
	public function loadEbscoOptions($activeSection, $exploreMoreOptions, $searchTerm) {
		global $library;
		global $configArray;
		//TODO: Reenable once we do full EDS integration
		if (false && $library->edsApiProfile && $activeSection != 'ebsco') {
			//Load EDS options
			require_once ROOT_DIR . '/sys/Ebsco/EDS_API.php';
			$edsApi = EDS_API::getInstance();
			if ($edsApi->authenticate()) {
				//Find related titles
				$edsResults = $edsApi->getSearchResults($searchTerm);
				if ($edsResults) {
					$numMatches = $edsResults->Statistics->TotalHits;
					if ($numMatches > 0) {
						//Check results based on common facets
						foreach ($edsResults->AvailableFacets->AvailableFacet as $facetInfo) {
							if ($facetInfo->Id == 'SourceType') {
								foreach ($facetInfo->AvailableFacetValues->AvailableFacetValue as $facetValue) {
									$facetValueStr = (string)$facetValue->Value;
									if (in_array($facetValueStr, ['Magazines', 'News', 'Academic Journals', 'Primary Source Documents'])) {
										$numFacetMatches      = (int)$facetValue->Count;
										$iconName             = 'ebsco_' . str_replace(' ', '_', strtolower($facetValueStr));
										$exploreMoreOptions[] = [
											'label'       => "$facetValueStr ({$numFacetMatches})",
											'description' => "{$facetValueStr} in EBSCO related to {$searchTerm}",
											'image'       => "/interface/themes/responsive/images/{$iconName}.png",
											'link'        => '/EBSCO/Results?lookfor=' . urlencode($searchTerm) . '&' . urlencode('filter[]') . '=' . $facetInfo->Id . ':' . $facetValueStr,
										];
									}

								}
							}
						}

						$exploreMoreOptions[] = [
							'label'       => "All EBSCO Results ({$numMatches})",
							'description' => "All Results in EBSCO related to {$searchTerm}",
							'image'       => '/interface/themes/responsive/images/ebsco_eds.png',
							'link'        => '/EBSCO/Results?lookfor=' . urlencode($searchTerm)
						];
					}
				}
			}
		}
		return $exploreMoreOptions;
	}

	/**
	 * @param IslandoraDriver $archiveDriver
	 *
	 * @return array
	 */
	public function getRelatedArchiveSubjects($archiveDriver){
		$relatedObjects = $archiveDriver->getDirectlyRelatedArchiveObjects();
		$relatedSubjects = [];

		foreach ($relatedObjects['objects'] as $object){
			/** @var IslandoraDriver $relatedObjectDriver */
			$relatedObjectDriver = $object['driver'];
			foreach ($relatedObjectDriver->getAllSubjectsWithLinks() as $subject){
				if (!isset($relatedSubjects[$subject['label']])){
					$relatedSubjects[$subject['label']] = $subject;
					if (!isset($relatedSubjects[$subject['label']]['linkingReason'])) {
						$relatedSubjects[$subject['label']]['linkingReason'] = "Used in: ";
					}
				}

				if (strpos($relatedSubjects[$subject['label']]['linkingReason'], "\r\n - " . $relatedObjectDriver->getTitle()) === false){
					$relatedSubjects[$subject['label']]['linkingReason'] .= "\r\n - " . $relatedObjectDriver->getTitle();
				}

			}
		}
		return $relatedSubjects;
	}

	/**
	 * @param string $searchTerm
	 * @param bool   $searchSubjectsOnly
	 * @param IslandoraDriver $archiveDriver
	 * @return array
	 */
	public function getRelatedArchiveObjects($searchTerm, $searchSubjectsOnly, $archiveDriver = null) {
		global $timer;
		$relatedArchiveContent = array();

		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);

		//Get a list of objects in the archive related to this search
		$searchObject->setSearchTerms(array(
				'lookfor' => $searchTerm,
				//TODO: do additional testing with this since it was reversed.
				'index' => 'IslandoraKeyword'
				//'index' => $searchSubjectsOnly ? 'IslandoraSubject' : 'IslandoraKeyword'
		));
		$searchObject->clearHiddenFilters();
		$searchObject->clearFilters();
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		if ($archiveDriver != null){
			$searchObject->addHiddenFilter('!PID', str_replace(':', '\:', $archiveDriver->getUniqueID()));
		}
		$searchObject->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
		$searchObject->addFacet('mods_genre_s', 'Format');

		$response = $searchObject->processSearch(true, false);
		if ($response && $response['response']['numFound'] > 0) {
			//Using the facets, look for related entities
			foreach ($response['facet_counts']['facet_fields']['mods_genre_s'] as $relatedContentType) {
				/** @var SearchObject_Islandora $searchObject2 */
				$searchObject2 = SearchObjectFactory::initSearchObject('Islandora');
				$searchObject2->init();
				$searchObject2->setDebugging(false, false);
				if ($archiveDriver != null){
					$searchObject2->addHiddenFilter('!PID', str_replace(':', '\:', $archiveDriver->getUniqueID()));
				}
				$searchObject2->setSearchTerms(array(
						'lookfor' => $searchTerm,
						'index' => 'IslandoraKeyword'
						//'index' => $searchSubjectsOnly ? 'IslandoraSubject' : 'IslandoraKeyword'
				));
				$searchObject2->clearFilters();
				$searchObject2->clearHiddenFilters();
				$searchObject2->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
				if ($archiveDriver != null){
					$searchObject2->addHiddenFilter('!PID', str_replace(':', '\:', $archiveDriver->getUniqueID()));
				}
				$searchObject2->addHiddenFilter('!mods_extension_marmotLocal_pikaOptions_showInSearchResults_ms', "no");
				$searchObject2->addFilter("mods_genre_s:{$relatedContentType[0]}");
				$response2 = $searchObject2->processSearch(true, false);
				if ($response2 && $response2['response']['numFound'] > 0) {
					$firstObject = reset($response2['response']['docs']);
					$numMatches = $response2['response']['numFound'];
					if ($archiveDriver != null && $firstObject['PID'] == $archiveDriver->getUniqueID()){
						if ($numMatches == 1) {
							continue;
						}else{
							$firstObject = next($response2['response']['docs']);
						}
					}
					/** @var IslandoraDriver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($firstObject);
					$contentType = ucwords(translate($relatedContentType[0]));
					if ($numMatches == 1) {
						$relatedArchiveContent[] = array(
								'title' => $firstObjectDriver->getTitle(),
								'description' => $firstObjectDriver->getTitle(),
								'image' => $firstObjectDriver->getBookcoverUrl('medium'),
								'link' => $firstObjectDriver->getRecordUrl(),
						);
					} else {
						$relatedArchiveContent[] = array(
								'title' => "{$contentType}s ({$numMatches})",
								'description' => "{$contentType}s related to this",
								'image' => $firstObjectDriver->getBookcoverUrl('medium'),
								'link' => $searchObject2->renderSearchUrl(),
						);
					}
				}
			}
		}
		$timer->logTime('Loaded related archive objects');
		return $relatedArchiveContent;
	}

	/**
	 * Load entities that are related to this entity but that are not directly related.
	 * I.e. we want to see
	 *
	 * @param IslandoraDriver $archiveDriver
	 * @return array
	 */
	public function getRelatedArchiveEntities($archiveDriver){
		global $timer;
		$directlyRelatedPeople = $archiveDriver->getRelatedPeople();
		$directlyRelatedPlaces = $archiveDriver->getRelatedPlaces();
		$directlyRelatedOrganizations = $archiveDriver->getRelatedOrganizations();
		$directlyRelatedEvents = $archiveDriver->getRelatedEvents();

		$relatedPeople = array();
		$relatedPlaces = array();
		$relatedOrganizations = array();
		$relatedEvents = array();
		$relatedObjects = $archiveDriver->getDirectlyRelatedArchiveObjects();

		foreach ($relatedObjects['objects'] as $object){
			/** @var IslandoraDriver $objectDriver */
			$objectDriver = $object['driver'];

			$peopleRelatedToObject = $objectDriver->getRelatedPeople();
			foreach($peopleRelatedToObject as $entity){
				if ($entity['pid'] != $archiveDriver->getUniqueID() && !array_key_exists($entity['pid'], $directlyRelatedPeople)){
					$relatedPeople = $this->addAssociatedEntity($entity, $relatedPeople, $objectDriver);
				}
			}

			$placesRelatedToObject = $objectDriver->getRelatedPlaces();
			foreach($placesRelatedToObject as $entity){
				if ($entity['pid'] != $archiveDriver->getUniqueID() && !array_key_exists($entity['pid'], $directlyRelatedPlaces)){
					$relatedPlaces = $this->addAssociatedEntity($entity, $relatedPlaces, $objectDriver);
				}
			}

			$organizationsRelatedToObject = $objectDriver->getRelatedOrganizations();
			foreach($organizationsRelatedToObject as $entity){
				if ($entity['pid'] != $archiveDriver->getUniqueID() && !array_key_exists($entity['pid'], $directlyRelatedOrganizations)){
					$relatedOrganizations = $this->addAssociatedEntity($entity, $relatedOrganizations, $objectDriver);
				}
			}

			$eventsRelatedToObject = $objectDriver->getRelatedEvents();
			foreach($eventsRelatedToObject as $entity){
				if ($entity['pid'] != $archiveDriver->getUniqueID() && !array_key_exists($entity['pid'], $directlyRelatedEvents)){
					$relatedEvents = $this->addAssociatedEntity($entity, $relatedEvents, $objectDriver);
				}
			}
		}

		$relatedEntities = array();
		if (count($relatedPeople) > 0){
			$relatedEntities['people'] = $relatedPeople;
		}
		if (count($relatedPlaces) > 0){
			$relatedEntities['places'] = $relatedPlaces;
		}
		if (count($relatedOrganizations) > 0){
			$relatedEntities['organizations'] = $relatedOrganizations;
		}
		if (count($relatedEvents) > 0){
			$relatedEntities['events'] = $relatedEvents;
		}
		$timer->logTime('Loaded related entities');
		return $relatedEntities;
	}

	/**
	 * @param string[] $relatedSubjects
	 * @param array    $directlyRelatedRecords
	 *
	 * @return array
	 */
	public function getRelatedWorks($relatedSubjects, $directlyRelatedRecords){
		//Load related catalog content
		$searchTerm = implode(' OR ', $relatedSubjects);

		$similarTitles = [
			'numFound' => 0,
			'link'     => '',
			'values'   => []
		];

		if (strlen($searchTerm) > 0){
			//Blacklist any records that we have specific links to
			$recordsToAvoid = '';
			foreach ($directlyRelatedRecords as $record){
				if (strlen($recordsToAvoid) > 0){
					$recordsToAvoid .= ' OR ';
				}
				$recordsToAvoid .= $record['id'];
			}

			/** @var SearchObject_Solr $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init('local', $searchTerm);
			$searchObject->setSearchTerms([
				'lookfor' => $searchTerm,
				'index'   => 'Keyword'
			]);
			$searchObject->addFilter('literary_form_full:Non Fiction');
			$searchObject->addFilter('target_audience:(Adult OR Unknown)');

			if (strlen($recordsToAvoid) > 0){
				$searchObject->addHiddenFilter('!id', $recordsToAvoid);
			}

			$searchObject->setPage(1);
			$searchObject->setLimit(5);
			$results = $searchObject->processSearch(true, false);

			if ($results && isset($results['response'])){
				$similarTitles = [
					'numFound' => $results['response']['numFound'],
					'link'     => $searchObject->renderSearchUrl(),
					'topHits'  => []
				];
				foreach ($results['response']['docs'] as $doc){
					/** @var GroupedWorkDriver $driver */
					$driver                    = RecordDriverFactory::initRecordDriver($doc);
					$similarTitle              = [
						'label' => $driver->getTitle(),
						'link'  => $driver->getLinkUrl(),
						'image' => $driver->getBookcoverUrl('medium')
					];
					$similarTitles['values'][] = $similarTitle;
				}
			}
		}
		return $similarTitles;
	}

	private function addAssociatedEntity($entity, $relatedEntities, $objectDriver) {
		if (!isset($relatedEntities[$entity['pid']])){
			$relatedEntities[$entity['pid']] = $entity;
			if (!isset($relatedEntities[$entity['pid']]['linkingReason'])) {
				$relatedEntities[$entity['pid']]['linkingReason'] = "Both link to: ";
			}
		}

		if (strpos($relatedEntities[$entity['pid']]['linkingReason'], "\r\n - " . $objectDriver->getTitle()) === false){
			$relatedEntities[$entity['pid']]['linkingReason'] .= "\r\n - " . $objectDriver->getTitle();
		}

		return $relatedEntities;
	}

	static function sortRelatedEntities($a, $b){
		return strcasecmp($a["label"], $b["label"]);
	}

	/**
	 * @param CompoundDriver $bookDriver
	 * @param array $exploreMoreSectionsToShow
	 * @param bool $currentlyShowingBook
	 * @return array
	 */
	private function setupTableOfContentsForBook($bookDriver, $exploreMoreSectionsToShow, $currentlyShowingBook) {
		global $interface;
		$bookContents = $bookDriver->loadBookContents();

		if (count($bookContents) > 1){
			$exploreMoreSectionsToShow['tableOfContents'] = array(
					'title' => 'Table of Contents',
					'format' => 'tableOfContents',
					'values' => array()
			);

			foreach ($bookContents as $section){
				$firstPageInSection = reset($section['pages']);
				if (!$currentlyShowingBook){
					$exploreMoreSectionsToShow['tableOfContents']['format'] = 'textOnlyList';
				}
				if ($firstPageInSection){
					$section = [
						'pid'   => $firstPageInSection['pid'],
						'label' => $section['title'],
					];
				}else{
					$section =[
						'pid' => $section['pid'],
						'label' => $section['title'],
					];
				}

				if (!$currentlyShowingBook){
					$section['link'] = $bookDriver->getRecordUrl() . '?pagePid=' . $firstPageInSection['pid'];
				}
				$exploreMoreSectionsToShow['tableOfContents']['values'][] = $section;

			}
		}
		$interface->assign('bookPid', $bookDriver->getUniqueId());
		return $exploreMoreSectionsToShow;
	}
}

function sortBrandingResults($a, $b){
	//TODO: add handling when 'sortIndex' isn't set
	if ($a['sortIndex'] == $b['sortIndex']){
		return strcasecmp($a['label'], $b['label']);
	}
	return ($a['sortIndex'] < $b['sortIndex']) ? -1 : 1;
}
