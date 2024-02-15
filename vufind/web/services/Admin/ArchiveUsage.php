<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
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

require_once ROOT_DIR . '/services/Admin/Admin.php';

abstract class Admin_ArchiveUsage extends Admin_Admin {

	function bytesToGigabytes($bytes) {
		return $bytes / 1073741824;
	}
	function getAllowableRoles(){
		return ['archives'];
	}

	/**
	 * @param string $solrFieldToFilterBy
	 * @param $fieldValue
	 * @return array
	 */
	public function getUsageByFacetValue(string $solrFieldToFilterBy, $fieldValue): array{
		$numObjects = 0;
		$bytes      = 0;

		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		//$searchObject->setDebugging(false, false);
		$searchObject->setLimit(250);
		$searchObject->clearFilters();
		$searchObject->clearHiddenFilters();

		$searchObject->setBasicQuery("\"$fieldValue\"", $solrFieldToFilterBy);
		$searchObject->addFieldsToReturn(['fedora_datastream_latest_OBJ_SIZE_ms']);
		$searchObject->setApplyStandardFilters(false);

		$response = $searchObject->processSearch(true, false, true);
		// use $preventQueryModification to stop removal of slash in the content Type value
		if (!empty($response['response']['numFound'])){
			$numProcessed = 0;
			$numObjects   = $response['response']['numFound'];

			while ($numProcessed < $numObjects){
				foreach ($response['response']['docs'] as $doc){
					if (isset($doc['fedora_datastream_latest_OBJ_SIZE_ms'])){
						$bytes += $doc['fedora_datastream_latest_OBJ_SIZE_ms'][0];
					}
					$numProcessed++;
				}
				if ($numProcessed < $response['response']['numFound']){
					$searchObject->setPage($searchObject->getPage() + 1);
					$response = $searchObject->processSearch(true, false, true);
				}
			}

		}
		return [$numObjects, $bytes];
	}

	/**
	 * @param string $solrFieldToFilterBy
	 * @return array
	 */
	public function getSolrFacetValues(string $solrFieldToFilterBy){
		$facetResponse = [];

		/** @var SearchObject_Islandora $searchObject */
		$archiveTypesSearch = SearchObjectFactory::initSearchObject('Islandora');
		$archiveTypesSearch->init();
		//$archiveTypesSearch->setDebugging(false, false);
		$archiveTypesSearch->setLimit(0); // Don't need results
		$archiveTypesSearch->clearFilters();
		$archiveTypesSearch->clearHiddenFilters();
		$archiveTypesSearch->setApplyStandardFilters(false); //TODO: needed?
		//$archiveTypesSearch->setBasicQuery('');
		$archiveTypesSearch->addFacet($solrFieldToFilterBy, 'Filter Option');
		//TODO: set one facet to return
		$contentTypeResponse = $archiveTypesSearch->processSearch(true, false);
		if (!empty($contentTypeResponse['facet_counts']['facet_fields'][$solrFieldToFilterBy])){
			$facetResponse = $contentTypeResponse['facet_counts']['facet_fields'][$solrFieldToFilterBy];
		}
		return $facetResponse;
	}

}
