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
 * A home page for the archive displaying all available projects as well as links to content by
 * content type
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/27/2016
 * Time: 8:26 AM
 */
require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
class Archive_Home extends Action{

	function launch() {
		global $interface;
		global $timer;
		global $library;

		$relatedProjects = $this->loadRelatedProjects(true);
		$interface->assign('relatedProjectsLibrary', $relatedProjects);

		if (!$library->hideAllCollectionsFromOtherLibraries) {
			$relatedProjects = $this->loadRelatedProjects(false);
			$interface->assign('relatedProjectsOther', $relatedProjects);
		}

		$archiveName = $library->displayName;
		//Get the archive name from islandora
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->clearFacets();
		$searchObject->clearFilters();
		$searchObject->clearHiddenFilters();
		$searchObject->setBasicQuery("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/islandora:root\" AND PID:{$library->archiveNamespace}*");
		//Get the library top-level collection

		$searchObject->setApplyStandardFilters(false);
		$response = $searchObject->processSearch(true, false, true);
		if ($response && isset($response['response']) && $response['response']['numFound'] > 0){
			$firstObject = reset($response['response']['docs']);
			$archiveName = $firstObject['fgs_label_s'];
		}
		$interface->assign('archiveName', $archiveName);

		//Get a list of content types and count the number of objects per content type
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->addFacet('mods_genre_s');
		$searchObject->setLimit(1);
		$searchObject->setSort('fgs_label_s');

		if ($library->hideAllCollectionsFromOtherLibraries){
			$searchObject->addHiddenFilter('PID', $library->archiveNamespace . '*');
		}
		$timer->logTime('Setup Search');

		$response = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($response)) {
			PEAR_Singleton::raiseError($response->getMessage());
		}
		$timer->logTime('Process Search for related content types');

		$relatedContentTypes = array();
		if ($response && isset($response['response'])){
			foreach ($response['facet_counts']['facet_fields']['mods_genre_s'] as $genre) {
				/** @var SearchObject_Islandora $searchObject2 */
				$searchObject2 = SearchObjectFactory::initSearchObject('Islandora');
				$searchObject2->init();
				$searchObject2->setDebugging(false, false);
				$searchObject2->clearFilters();
				$searchObject2->addFilter("mods_genre_s:{$genre[0]}");
				$response2 = $searchObject2->processSearch(true, false);
				if ($response2 && $response2['response']['numFound'] > 0) {
					$firstObject = reset($response2['response']['docs']);
					/** @var IslandoraDriver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($firstObject);
					$numMatches = $response2['response']['numFound'];
					$contentType = ucwords($genre[0]);
					if ($numMatches == 1) {
						$relatedContentTypes[] = array(
								'title' => "{$contentType} ({$numMatches})",
								'description' => "{$contentType} related to this",
								'image' => $firstObjectDriver->getBookcoverUrl('medium'),
								'link' => $firstObjectDriver->getRecordUrl(),
						);
					} else {
						$relatedContentTypes[] = array(
								'title' => "{$contentType}s ({$numMatches})",
								'description' => "{$contentType}s related to this",
								'image' => $firstObjectDriver->getBookcoverUrl('medium'),
								'link' => $searchObject2->renderSearchUrl(),
						);
					}
				}
			}

		}
		$interface->assign('showExploreMore', false);
		$interface->assign('relatedContentTypes', $relatedContentTypes);
		$this->endExhibitContext();

		parent::display('home.tpl', $library->displayName . ' Digital Collection');
	}

	protected function endExhibitContext()
	{
		$_SESSION['ExhibitContext']  = null;
		$_SESSION['exhibitSearchId'] = null;
		$_SESSION['placePid']        = null;
		$_SESSION['dateFilter']      = null;
	}

	/**
	 * @param boolean $libraryProjects
	 * @return array
	 */
	public function loadRelatedProjects($libraryProjects)
	{
		global $interface;
		/** @var Timer $timer */
		global $timer;
		global $library;

		//Get a list of all available projects
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->addFilter('mods_extension_marmotLocal_pikaOptions_showOnPikaArchiveHomepage_ms:yes');
		if ($libraryProjects){
			$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:info\\:fedora/{$library->archiveNamespace}\\:*");
		}else{
			$searchObject->addFilter("!RELS_EXT_isMemberOfCollection_uri_ms:info\\:fedora/{$library->archiveNamespace}\\:*");
		}

		$searchObject->setLimit(50);
		$searchObject->setSort('fgs_label_s');
		$timer->logTime('Setup Search');

		$response = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($response)) {
			PEAR_Singleton::raiseError($response->getMessage());
		}
		$timer->logTime('Process Search for collections');

		if ($libraryProjects){
			$interface->assign('libraryProjectsUrl', $searchObject->renderSearchUrl());
		}else{
			$interface->assign('otherProjectsUrl', $searchObject->renderSearchUrl());
		}

		$relatedProjects = array();
		if ($response && isset($response['response'])) {
			//Get information about each project
			if ($searchObject->getResultTotal() > 0) {
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd', $summary['endRecord']);

				foreach ($response['response']['docs'] as $objectInCollection) {
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
					$relatedProjects[] = array(
							'title' => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image' => $firstObjectDriver->getBookcoverUrl('small'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link' => $firstObjectDriver->getRecordUrl(),
							'pid' => $firstObjectDriver->getUniqueID()
					);
					$timer->logTime('Loaded related object');
				}
			}
		}
		return $relatedProjects;
	}

}
