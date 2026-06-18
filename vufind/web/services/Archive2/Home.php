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
 * A home page for the Islandora2 archive displaying available projects and links
 * to content by content type. Mirrors Archive_Home but searches against Islandora2.
 *
 * @category Pika
 * @author   Pascal Brammeier <pika@marmot.org>
 */
class Archive2_Home extends Action {

	function launch() {
		global $interface;
		global $timer;
		global $library;
		global $pikaLogger;
		$logger = $pikaLogger->withName(__CLASS__);

		// The archive home page is a dedicated landing page: always show the archive
		// search box regardless of the user's session searchSource. The session is
		// intentionally not updated here, so navigating away restores the user's prior
		// search context.
		//Note: the global variable also needs updating for the horizontal searchbox
		global $searchSource;
		$searchSource = 'islandora2';
		$interface->assign('searchSource', 'islandora2');

		$relatedProjects = $this->loadRelatedProjects(true);
		$interface->assign('relatedProjectsLibrary', $relatedProjects);
		// If there are no library projects, the other projects carousel below will display
		// as the main carousel.
		$timer->logTime('Loaded library projects carousel');

		if (!$library->hideAllCollectionsFromOtherLibraries) {
			$relatedProjects = $this->loadRelatedProjects(false);
			$interface->assign('relatedProjectsOther', $relatedProjects);
			$timer->logTime('Loaded other-library projects carousel');
		}

		// Get the archive name from the library's taxonomy term in Islandora2
		$archiveName = $library->displayName;
		if (!is_numeric($library->libraryTid)) {
			$logger->warning("Library $library->subdomain libraryTid is not a number", ['libraryTid' => $library->libraryTid]);
		} else{
			/** @var SearchObject_Islandora2 $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora2');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->setApplyStandardFilters(false);
			// Since we are fetching a taxonomy term, we need to turn off standard filtering
			$searchObject->addFilter("its_tid:{$library->libraryTid}");
			$searchObject->setLimit(1);
			$response = $searchObject->processSearch(true, false, true);
			if (isset($response['error'])){
				$logger->error('Solr error fetching collection archive name', ['msg' => $response['error']['msg']]);
			}
			if (!empty($response['response']['docs']) && $response['response']['numFound'] > 0){
				$firstObject = reset($response['response']['docs']);
				if (!empty($firstObject['tm_X3b_en_name'][0])){
					$archiveName = $firstObject['tm_X3b_en_name'][0];
				}
			}
			$timer->logTime('Fetched archive name from Islandora2');
		}
		$interface->assign('archiveName', $archiveName);

		// Get a list of content types and count the number of objects per content type
		$searchObject = SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->addFacet('sm_genre');
		$searchObject->setLimit(0); // Fetch facet data only
		//TODO: sort facet by value, instead of default facet counts
		//$searchObject->setSort('sm_genre asc'); // Set sort by collection name
		//$searchObject->setSort('ds_created desc');
		$timer->logTime('Setup genre facet search');

		$response = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($response)) {
			PEAR_Singleton::raiseError($response->getMessage());
		} elseif (isset($response['error'])) {
			$logger->error('Solr error fetching content types', ['msg' => $response['error']['msg']]);
		}
		$timer->logTime('Fetched genre facets');

		$relatedContentTypes = [];
		if (!empty($response['response'])) {
			foreach ($response['facet_counts']['facet_fields']['sm_genre'] as $genre) {
				/** @var SearchObject_Islandora2 $searchObject2 */
				$searchObject2 = SearchObjectFactory::initSearchObject('Islandora2');
				$searchObject2->init();
				$searchObject2->setDebugging(false, false);
				// Allow standard filters to apply so we aren't fetching anything we should not be.
				$searchObject2->setLimit(1); // Only Fetch one object per genre
				//TODO: random order
				$searchObject2->addFilter("sm_genre:{$genre[0]}");
				$response2 = $searchObject2->processSearch(true);
				if (isset($response2['error'])) {
					$logger->error('Solr error fetching genre results', ['genre' => $genre[0], 'msg' => $response2['error']['msg']]);
				}
				$timer->logTime("Genre search: {$genre[0]}");
				if ($response2 && $response2['response']['numFound'] > 0) {
					$firstObject = reset($response2['response']['docs']);
					/** @var Islandora2Driver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($firstObject); //TODO: initializing appears to be relatively slow for archive home page loading
					$timer->logTime("Init record driver: {$genre[0]}");
					$numMatches        = $response2['response']['numFound'];
					$contentType       = ucwords($genre[0]);
					if ($numMatches == 1) {
						$relatedContentTypes[] = [
							'title'       => "{$contentType} ({$numMatches})",
							// description is not used in home.tpl
							//'description' => "{$contentType} related to this",
							'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
							'link'        => $firstObjectDriver->getRecordUrl(),
						];
					} else {
						$relatedContentTypes[] = [
							'title'       => "{$contentType}s ({$numMatches})",
							// description is not used in home.tpl
							//'description' => "{$contentType}s related to this",
							'image'       => $firstObjectDriver->getBookcoverUrl('medium'),
							'link'        => $searchObject2->renderSearchUrl(),
						];
					}
				}
			}
		}
		$timer->logTime('Built content types list');
		$interface->assign('showExploreMore', false);
		$interface->assign('relatedContentTypes', $relatedContentTypes);

		parent::display('home.tpl', $library->displayName . ' Digital Collection');
	}

	/**
	 * @param boolean $libraryProjects
	 * @return array
	 */
	public function loadRelatedProjects($libraryProjects) {
		global $interface;
		/** @var Timer $timer */
		global $timer;
		global $library;
		global $pikaLogger;
		$logger = $pikaLogger->withName(__CLASS__);

		/** @var SearchObject_Islandora2 $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->addFilter('bs_field_pika_shown_homepage:true');
		if ($libraryProjects) {
			$searchObject->addFilter("itm_field_library:{$library->libraryTid}");
			$searchObject->setSort('sm_collection asc'); // Set sort by collection name
			//$searchObject->setSort('sm_title_2 asc'); // Set sort by collection name //TODO: remove
		} else {
			$searchObject->addFilter("!itm_field_library:{$library->libraryTid}");
			$searchObject->setSort('ds_created desc');
			$searchObject->setLimit(20); // was 50; currently takes too long to load that many collection objects
		}

		if (!is_numeric($library->libraryTid)) {
			$logger->warning(__FUNCTION__ . " library $library->subdomain libraryTid is not a number", ['libraryTid' => $library->libraryTid]);
		}

		$label = $libraryProjects ? 'library' : 'other-library';
		$timer->logTime("Setup $label projects search");

		$response = $searchObject->processSearch(true);
		if (PEAR_Singleton::isError($response)) {
			PEAR_Singleton::raiseError($response->getMessage());
		} elseif (isset($response['error'])) {
			$logger->error(__FUNCTION__ . ' Solr error fetching collections', ['msg' => $response['error']['msg']]);
		}
		$timer->logTime("Fetched $label projects from Solr");

		if ($libraryProjects) {
			// A search of the library's collections with the 'show on homepage' filter setting on
			$interface->assign('libraryProjectsUrl', $searchObject->renderSearchUrl());
		} else {
			$interface->assign('otherProjectsUrl', $searchObject->renderSearchUrl());
		}

		$relatedProjects = [];
		if (!empty($response['response'])) {
			if ($searchObject->getResultTotal() > 0) {
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd'  , $summary['endRecord']);

				foreach ($response['response']['docs'] as $objectInCollection) {
					/** @var Islandora2Driver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
					$relatedProjects[] = [
						'title'       => $firstObjectDriver->getTitle(),
						// description is not used in home.tpl; skipped to avoid triggering a lazy API load per object
						//'description' => $firstObjectDriver->getDescription(),
						'image'       => $firstObjectDriver->getBookcoverUrl('small'),
						'link'        => $firstObjectDriver->getRecordUrl(),
						'nodeId'      => $firstObjectDriver->getUniqueID(),
					];
					$timer->logTime("Loaded $label project object");
				}
			}
		}
		return $relatedProjects;
	}

}
