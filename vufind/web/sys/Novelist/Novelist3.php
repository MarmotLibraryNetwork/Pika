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

require_once ROOT_DIR . '/sys/ISBN/ISBNConverter.php';
require_once ROOT_DIR . '/sys/Novelist/NovelistData.php';

use Curl\Curl;
use Pika\Logger;

class Novelist3{
	private $novelistEnabled = false;
	private $profile;
	private $pwd;
	private $apiUrl;
	private $cachingPeriod = 43200; // 12 hours
	private Logger $logger;

	/**
	 * @throws Exception
	 */
	public function __construct(){
		global $configArray;
		$this->logger = new Logger(get_class($this));
		if (!empty($configArray['Novelist']['profile'])){
			$this->novelistEnabled = true;
			$this->apiUrl          = $configArray['Novelist']['apiBaseUrl'];
			$this->profile         = $configArray['Novelist']['profile'];
			$this->pwd             = $configArray['Novelist']['pwd'];
			if (!empty($configArray['Caching']['novelist_enrichment'])){
				$this->cachingPeriod = $configArray['Caching']['novelist_enrichment'];
			}
		}
	}

	function doesGroupedWorkHaveCachedSeries($groupedRecordId){
		if (!empty($groupedRecordId)){
			$novelistData                         = new NovelistData();
			$novelistData->groupedWorkPermanentId = $groupedRecordId;
			if ($novelistData->count()){
				return true;
			}
		}
		return false;
	}

	public function getRawNovelistJSON($isbn){
		global $timer;
		$requestUrl = $this->apiUrl . "/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
		try{
			//Get the JSON from the service
			$curl     = new Curl();
			$curl->setDefaultDecoder('json_decode');
			$data = $curl->get($requestUrl);
			if ($curl->isError()) {
				$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();

				$this->logger->warning($message);
				//No enrichment for this isbn, go to the next one

			}
			$timer->logTime("Made call to Novelist to get info for: $isbn");
			return $data;

		}catch (Exception $e) {
			return $e;
		}
	}

	/**
	 * Generates the Novelist Enrichment data object, fetches it if is in the table, and runs through the logic of whether
	 * or not the enrichment data should be updated from Novelist
	 *
	 * @param string $groupedRecordId
	 * @param string[] $ISBNs
	 * @param bool $allowReload
	 * @return array
	 */
	private function doUpdateOfEnrichment($groupedRecordId, $ISBNs, $allowReload = true){
		$novelistData                         = new NovelistData();
		$novelistData->groupedWorkPermanentId = $groupedRecordId;

		//Check to see if a reload is being forced
		if (isset($_REQUEST['reload'])){
			$doUpdate = true;
		} else{
			//Now check the database
			if ($novelistData->find(true)){
				$doUpdate     = false;

				if ($novelistData->hasNovelistData){
					//We already have data loaded, make sure the data is still "fresh"
					//First check to see if the record had ISBNs before we update
					//We do have at least one ISBN
					//If it's been more than 30 days since we updated, update 20% of the time
					//We do it randomly to spread out the updates.
					if ($allowReload && ($novelistData->groupedRecordHasISBN || count($ISBNs) > 0)){
						$now = time();
						if ($novelistData->lastUpdate < $now - (30 * 24 * 60 * 60)){
							$random = rand(1, 100);
							if ($random <= 20){
								$doUpdate = true;
							}
						}
					}//else, no ISBNs, don't update
				}

			} else {
				// data doesn't exist in the table so do need to update
				$doUpdate = true;
			}
		}
		return [$novelistData, $doUpdate];

	}

	function loadBasicEnrichment($groupedRecordId, $ISBNs, $allowReload = true){
		//First make sure that Novelist is enabled
		if (!$this->novelistEnabled || empty($groupedRecordId)){
			return null;
		}

		//Check to see if we have cached data, first check MemCache.
		/** @var Memcache $memCache */
		global $memCache;
		$memCacheKey          = "novelist_enrichment_basic_$groupedRecordId";
		$novelistData = $memCache->get($memCacheKey);
		if ($novelistData != false && !isset($_REQUEST['reload'])){
			return $novelistData;
		}

		global $timer;
		$timer->logTime("Starting to load data from novelist for $groupedRecordId");
		//Now check the database
		[$novelistData, $doUpdate] = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs, $allowReload);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;

		//Check to see if we need to do an update
		if ($doUpdate){

			//Update the last update time to optimize caching
			$novelistData->lastUpdate      = time();
			$novelistData->hasNovelistData = false;

			if (!isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
				//Just check the primary ISBN since we know that was good.
				$ISBNs = [$novelistData->primaryISBN];
			}

			if (count($ISBNs)){
				//Check each ISBN for enrichment data
				foreach ($ISBNs as $isbn){
					$requestUrl = $this->apiUrl . "/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
					try{
						//Get the JSON from the service
						$curl     = new Curl();
						$curl->setDefaultDecoder('json_decode');
						$data = $curl->get($requestUrl);
						if ($curl->isError()) {
							$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();
							$this->logger->warning($message);
							//No enrichment for this isbn, go to the next one
							continue;
						}


						$timer->logTime("Made call to Novelist to get basic enrichment info $isbn");

						//Related ISBNs

						if (!empty($data->FeatureContent)){
							//We got data!
							$novelistData->hasNovelistData = true;
							$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
							$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn ?? null;
							//Series Information
							if (isset($data->FeatureContent->SeriesInfo)){
								if(in_array($novelistData->primaryISBN, $ISBNs))
								{
									$this->loadSeriesInfoFast($data->FeatureContent->SeriesInfo, $novelistData);
									$timer->logTime("loaded series data");
								}else{
									require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
									$groupedWorkDriver = new GroupedWorkDriver($groupedRecordId);

									$novelistData->primaryISBN = $groupedWorkDriver->getPrimaryIsbn();
									$this->logger->debug("Wrong NoveList ISBN");
									$this->loadSeriesInfo($groupedRecordId, $data->FeatureContent->SeriesInfo,$novelistData);
								}
								if (!empty($data->FeatureContent->SeriesInfo->series_titles)) {
									//We got good data, quit looking at ISBNs
									break;
								}
							}
						}
					}catch (Exception $e) {
						$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);

						if (isset($response)){
							$this->logger->debug($response);
						}
						$enrichment = null;
					}
				}//Loop on each ISBN
			}//Check for number of ISBNs

			if ($novelistData->N == 1){ // Data entry already exists
				$novelistData->update();
			}
			//Don't add basic enrichment to database since it is incomplete
//		else{
//			$novelistData->insert();
//		}

		}//Don't need to do an update

		$memCache->set($memCacheKey, $novelistData, 0, $this->cachingPeriod);
		return $novelistData;
	}

	/**
	 * Loads Novelist data from Novelist for a grouped record
	 *
	 * @param String    $groupedRecordId  The permanent id of the grouped record
	 * @param String[]  $ISBNs            a list of ISBNs for the record
	 * @return NovelistData
	 */
	function loadEnrichment($groupedRecordId, $ISBNs){
//		global $memoryWatcher;

		//First make sure that Novelist is enabled
		if (!$this->novelistEnabled || empty($groupedRecordId)){
			return null;
		}


		//Check to see if we have cached data, first check MemCache.
		/** @var Memcache $memCache */
		global $memCache;
		$memCacheKey  = "novelist_enrichment_$groupedRecordId";
		$novelistData = $memCache->get($memCacheKey);
		if ($novelistData != false && !isset($_REQUEST['reload'])){
//			$memoryWatcher->logMemory('Got novelist data from memcache');
			return $novelistData;
		}

		//Now check the database
		[$novelistData, $doFullUpdate] = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we always need to load the data since we can't cache due to terms of service
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = [$novelistData->primaryISBN];
		}

		if (count($ISBNs)){
			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = $this->apiUrl . "/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
//					$curl->setJsonDecoder('json_decode'); //This doesn't work because novelist doesn't send back a good response header for content-type to indicate JSON
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();

						$this->logger->warning($message);
						//No enrichment for this isbn, go to the next one
						continue;
					}

					//Related ISBNs

					if (!empty($data->FeatureContent)){
						//We got data!
						$novelistData->hasNovelistData = true;
						$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn ?? null;

						//Series Information
						if (isset($data->FeatureContent->SeriesInfo)){
							//verify that API returned ISBN matches the current ISBN before it is added
							if(in_array($novelistData->primaryISBN, $ISBNs))
							{
								$this->loadSeriesInfo($groupedRecordId, $data->FeatureContent->SeriesInfo, $novelistData);
							}else{

								//log the incorrect ISBN
								$this->logger->warning("Novelist ISBN for record " . $groupedRecordId . " does not match local holdings");
								//$this->loadSeriesInfoMissingISBN($groupedRecordId, $data->FeatureContent->SeriesInfo, $novelistData);
								require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
								$groupedWorkDriver = new GroupedWorkDriver($groupedRecordId);

								$novelistData->primaryISBN = $groupedWorkDriver->getPrimaryIsbn();
								$this->loadSeriesInfo($groupedRecordId, $data->FeatureContent->SeriesInfo, $novelistData);

							}
						}

						//Similar Titles
						if (isset($data->FeatureContent->SimilarTitles)){
							$this->loadSimilarTitleInfo($groupedRecordId, $data->FeatureContent->SimilarTitles, $novelistData);
						}

						//Similar Authors
						if (isset($data->FeatureContent->SimilarAuthors)){
							$this->loadSimilarAuthorInfo($data->FeatureContent->SimilarAuthors, $novelistData);
						}

						//Similar Series
						if (isset($data->FeatureContent->SimilarSeries)){
							$this->loadSimilarSeries($data->FeatureContent->SimilarSeries, $novelistData);
						}

						//Related Content
//						if (isset($data->FeatureContent->RelatedContent)){
//							$this->loadRelatedContent($data->FeatureContent->RelatedContent, $novelistData);
//						}

						//GoodReads Ratings
						if (isset($data->FeatureContent->GoodReads)){
							$this->loadGoodReads($data->FeatureContent->GoodReads, $novelistData);
						}

						//print_r($data);
						//We got good data, quit looking at ISBNs
						//If we get series data, stop.
						//Sometimes we get data for an audioBook that is less complete.
						if (!empty($data->FeatureContent->SeriesInfo->series_titles)) {
							break;
						}
					}
				}catch (Exception $e) {
					$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);

					if (isset($response)){
						$this->logger->debug($response);
					}
					$enrichment = null;
				}
			}//Loop on each ISBN
		}//Check for number of ISBNs

		if ($doFullUpdate){
			if ($novelistData->N == 1){ // Data entry already exists
				$novelistData->update();
			}else{
				$novelistData->insert();
			}
		}

		//Ignore warnings if the object is too large for the cache
		$memCache->set($memCacheKey, $novelistData, 0, $this->cachingPeriod);
		return $novelistData;
	}

	/**
	 * Loads Novelist data from Novelist for a grouped record
	 *
	 * @param String    $groupedRecordId  The permanent id of the grouped record
	 * @param String[]  $ISBNs            a list of ISBNs for the record
	 * @return NovelistData
	 */
	function getSimilarTitles($groupedRecordId, $ISBNs){
		//First make sure that Novelist is enabled
		if (!$this->novelistEnabled || empty($groupedRecordId)){
			return null;
		}

		//Check to see if we have cached data, first check MemCache.
		/** @var Memcache $memCache */
		global $memCache;
		$memCacheKey  = "novelist_similar_titles_$groupedRecordId";
		$novelistData = $memCache->get($memCacheKey);
		if ($novelistData != false && !isset($_REQUEST['reload'])){
			return $novelistData;
		}

		[$novelistData, $doFullUpdate] = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we aways need to load the data since we can't cache due to terms of sevice
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = [$novelistData->primaryISBN];
		}

		//Update the last update time to optimize caching

		if (count($ISBNs)){

			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = $this->apiUrl . "/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();
						$this->logger->warning($message);
						//No enrichment for this isbn, go to the next one
						continue;
					}

					global $timer;
					$timer->logTime("Made call to Novelist for enrichment information");

					//Related ISBNs
					if (!empty($data->FeatureContent)){
						//We got data!
						$novelistData->hasNovelistData = true;
						$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn ?? null;

						//Similar Titles
						if (isset($data->FeatureContent->SimilarTitles)){
							$this->loadSimilarTitleInfo($groupedRecordId, $data->FeatureContent->SimilarTitles, $novelistData);
						}

						//We got good data, quit looking at ISBNs
						break;
					}
				}catch (Exception $e) {

					$this->logger->error("Error fetching data from NoveList $e");
					if (isset($response)){
						$this->logger->debug($response);
					}
					$enrichment = null;
				}
			}//Loop on each ISBN
		}//Check for number of ISBNs

		if ($doFullUpdate){
			if ($novelistData->N == 1){ // Data entry already exists
				$novelistData->update();
			}
		}
		$memCache->set($memCacheKey, $novelistData, 0, $this->cachingPeriod);
		return $novelistData;
	}

	/**
	 * Loads Novelist data from Novelist for a grouped record
	 *
	 * @param String    $groupedRecordId  The permanent id of the grouped record
	 * @param String[]  $ISBNs            a list of ISBNs for the record
	 * @return NovelistData
	 */
	function getSimilarAuthors($groupedRecordId, $ISBNs){
		//First make sure that Novelist is enabled
		if (!$this->novelistEnabled || empty($groupedRecordId)){
			return null;
		}

		//Check to see if we have cached data, first check MemCache.
		/** @var Memcache $memCache */
		global $memCache;
		$memCacheKey  = "novelist_similar_authors_$groupedRecordId";
		$novelistData = $memCache->get($memCacheKey);
		if ($novelistData != false && !isset($_REQUEST['reload'])){
			return $novelistData;
		}

		[$novelistData, $doFullUpdate] = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we aways need to load the data since we can't cache due to terms of sevice
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = [$novelistData->primaryISBN];
		}

		//Update the last update time to optimize caching

		if (count($ISBNs)){

			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = $this->apiUrl . "/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();
						$this->logger->warning($message);
						//No enrichment for this isbn, go to the next one
						continue;
					}

					global $timer;
					$timer->logTime("Made call to Novelist for enrichment information");

					//Related ISBNs
					if (!empty($data->FeatureContent)){
						//We got data!
						$novelistData->hasNovelistData = true;
						$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn ?? null;

						//Similar Authors
						if (isset($data->FeatureContent->SimilarAuthors)){
							$this->loadSimilarAuthorInfo($data->FeatureContent->SimilarAuthors, $novelistData);
						}

						//We got good data, quit looking at ISBNs
						break;
					}
				}catch (Exception $e) {

					$this->logger->error("Error fetching data from NoveList $e");
					if (isset($response)){
						$this->logger->debug($response);
					}
					$enrichment = null;
				}
			}//Loop on each ISBN
		}//Check for number of ISBNs

		if ($doFullUpdate){
			if ($novelistData->N == 1){ // Data entry already exists
				$novelistData->update();
			}
		}
		$memCache->set($memCacheKey, $novelistData, 0, $this->cachingPeriod);
		return $novelistData;
	}

	private function loadSimilarAuthorInfo($feature, &$enrichment){
		$authors = [];
		$items   = $feature->authors;
		foreach ($items as $item){
			$authors[] = [
				'name'   => $item->full_name,
				'reason' => $item->reason,
				'link'   => '/Author/Home/?author="' . urlencode($item->full_name) . '"',
			];
		}
		$enrichment->authors            = $authors;
		$enrichment->similarAuthorCount = count($authors);
	}

	/**
	 * Loads Novelist data from Novelist for a grouped record
	 *
	 * @param String    $groupedRecordId  The permanent id of the grouped record
	 * @param String[]  $ISBNs            a list of ISBNs for the record
	 * @return NovelistData
	 */
	function getSeriesTitles($groupedRecordId, $ISBNs){
		//First make sure that Novelist is enabled
		if (!$this->novelistEnabled || empty($groupedRecordId)){
			return null;
		}

		//Check to see if we have cached data, first check MemCache.
		/** @var Memcache $memCache */
		global $memCache;
		global $solrScope;
		$memCacheKey          = "novelist_series_{$groupedRecordId}_{$solrScope}";
		$novelistData = $memCache->get($memCacheKey);
		if ($novelistData != false && !isset($_REQUEST['reload'])){
			return $novelistData;
		}

		//Now check the database
		[$novelistData, $doFullUpdate] = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we always need to load the data since we can't cache due to terms of service
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = [$novelistData->primaryISBN];
		}

		if (count($ISBNs)){
			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = $this->apiUrl . "/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
//					$curl->setJsonDecoder('json_decode'); //This doesn't work because novelist doesn't send back a good response header for content-type to indicate JSON
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();

						$this->logger->warning($message);
						//No enrichment for this isbn, go to the next one
						continue;
					}

					//Related ISBNs

					if (!empty($data->FeatureContent)){
						//We got data!
						$novelistData->hasNovelistData = true;
						$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn ?? null;

						//Series Information
						if (isset($data->FeatureContent->SeriesInfo)){
							$this->loadSeriesInfo($groupedRecordId, $data->FeatureContent->SeriesInfo, $novelistData);
							break;
						}
					}
				}catch (Exception $e) {
					$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);

					if (isset($response)){
						$this->logger->debug($response);
					}
					$enrichment = null;
				}
			}//Loop on each ISBN
		}//Check for number of ISBNs

		$memCache->set($memCacheKey, $novelistData, 0, $this->cachingPeriod);
		return $novelistData;
	}

	/**
	 * @param stdClass $seriesData
	 * @param NovelistData $novelistData
	 */
	private function loadSeriesInfoFast($seriesData, &$novelistData){
		$seriesName = $seriesData->full_title;
		$items      = $seriesData->series_titles;
		foreach ($items as $item){
			if ($item->primary_isbn == $novelistData->primaryISBN){
				$volume               = $item->volume;
				$volume               = preg_replace('/^0+/', '', $volume);
				$novelistData->volume = $volume;
			}
		}
		$novelistData->seriesTitle = $seriesName;
		$novelistData->seriesNote  = $seriesData->series_note;
	}

	private function loadSeriesInfo($currentId, $seriesData, &$novelistData){
		$titlesOwned  = 0;
		$seriesTitles = [];
		$seriesName   = $seriesData->full_title;
		$items        = $seriesData->series_titles;
		$this->loadNoveListTitles($currentId, $items, $seriesTitles, $titlesOwned, $seriesName);
		foreach ($seriesTitles as $curTitle){
			if ($curTitle['isCurrent'] && !empty($curTitle['volume'])){
				$enrichment['volumeLabel'] = 'volume ' . $curTitle['volume'];
				$novelistData->volume      = $curTitle['volume'];
			}
		}
		$novelistData->seriesTitles = $seriesTitles;
		$novelistData->seriesTitle  = $seriesName;
		$novelistData->seriesNote   = $seriesData->series_note;

		$novelistData->seriesCount        = count($items);
		$novelistData->seriesCountOwned   = $titlesOwned;
		$novelistData->seriesDefaultIndex = 1;
		$curIndex                         = 0;
		foreach ($seriesTitles as $title){

			if ($title['isCurrent']){
				$novelistData->seriesDefaultIndex = $curIndex;
			}
			$curIndex++;
		}
	}

	private function loadSimilarSeries($similarSeriesData, &$enrichment){
		$similarSeries = [];
		foreach ($similarSeriesData->series as $similarSeriesInfo){
			$similarSeries[] = [
				'title'  => $similarSeriesInfo->full_name,
				'author' => $similarSeriesInfo->author,
				'reason' => $similarSeriesInfo->reason,
				'link'   => 'Union/Search/?lookfor=' . $similarSeriesInfo->full_name . " AND " . $similarSeriesInfo->author,
			];
		}
		$enrichment->similarSeries      = $similarSeries;
		$enrichment->similarSeriesCount = count($similarSeries);
	}

	private function loadSimilarTitleInfo($currentId, $similarTitles, &$enrichment){
		$items               = $similarTitles->titles;
		$titlesOwned         = 0;
		$similarTitlesReturn = [];
		$this->loadNoveListTitles($currentId, $items, $similarTitlesReturn, $titlesOwned);
		$enrichment->similarTitles          = $similarTitlesReturn;
		$enrichment->similarTitleCount      = count($items);
		$enrichment->similarTitleCountOwned = $titlesOwned;
	}

	/**
	 *  Searches search index for additional information to update $titleList and $titlesOwned
	 * @param string $currentId
	 * @param array $items
	 * @param array $titleList  Array of title information
	 * @param integer $titlesOwned  Count of total titles (owned in a series?)
	 * @param string $seriesName
	 */
	private function loadNoveListTitles($currentId, $items, &$titleList, &$titlesOwned, $seriesName = ''){
		if (!empty($items)){
			global $timer;
			$timer->logTime('Start loadNoveListTitle');

			/** @var SearchObject_Solr $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject();
			//$searchObject->disableScoping();
			if (function_exists('disableErrorHandler')){
				disableErrorHandler();
			}

			//Get all the records that could match based on ISBN
			$allIsbnsArray     = [];
			$limitISBNSperItem = (int)1000 / count($items);
			// We have to limit the number of ISBNs to search for because some titles will have so many isbns associated with it
			// that the isbn search we build will be too large for a search query.
			// Using a 1,000 ISBNs a.b66343562s a safe total number; then split the total so each series entry as the same upper limit of ISBNs to use.
			foreach ($items as $item){
				$allIsbnsArray = array_merge($allIsbnsArray, array_slice($item->isbns, 0, $limitISBNSperItem));
			}

			$allIsbns = implode(' OR ', $allIsbnsArray);
			$searchObject->setBasicQuery($allIsbns, "isbn");
			$searchObject->clearFacets();
			$searchObject->disableSpelling();
			$searchObject->disableLogging();
			// There is an array index mismatch. Count returns a number one less than the needed limit.
			// An extra value is added, for cases where this mismatch occurs. In the event that it isn't
			// needed, no value is found. Do not remove.
			//TODO: Investigate whether we need the limit at all
			$searchObject->setLimit(count($items)+1);
			$response = $searchObject->processSearch(true, false, false);

			//Get all the titles from the catalog
			$titlesFromCatalog = [];
			if (!empty($response['response']['docs'])){
				foreach ($response['response']['docs'] as $fields){
					$groupedWorkDriver = new GroupedWorkDriver($fields);
					$timer->logTime('Create driver');

					if ($groupedWorkDriver->isValid){
						//Load data about the record

						//See if we can get the series title from the record
						$curTitle = [
							'title'           => $groupedWorkDriver->getTitle(),
							'title_short'     => $groupedWorkDriver->getTitle(),
							'author'          => $groupedWorkDriver->getPrimaryAuthor(),
							//'publicationDate' => (string)$item->PublicationDate,
							'isbn'            => $groupedWorkDriver->getCleanISBN(), // This prefers the Novelist Primary ISBN
							'allIsbns'        => $groupedWorkDriver->getISBNs(),     // This will list the search index Primary ISBN first
							'isbn10'          => $groupedWorkDriver->getCleanISBN(), // This prefers the Novelist Primary ISBN
							'upc'             => $groupedWorkDriver->getCleanUPC(),
							'recordId'        => $groupedWorkDriver->getPermanentId(),
							'recordtype'      => 'grouped_work',
							'id'              => $groupedWorkDriver->getPermanentId(), //This allows the record to be displayed in various locations.
							'libraryOwned'    => true,
							'shortId'         => $groupedWorkDriver->getPermanentId(),
							'format_category' => $groupedWorkDriver->getFormatCategory(),
							'ratingData'      => $groupedWorkDriver->getRatingData(),
							'fullRecordLink'  => $groupedWorkDriver->getLinkUrl(),
							'recordDriver'    => $groupedWorkDriver,
							'smallCover'      => $groupedWorkDriver->getBookcoverUrl('small'),
							'mediumCover'     => $groupedWorkDriver->getBookcoverUrl('medium'),
							'volume'          => $item->volume ?? ''

						];
						$timer->logTime('Load title information');
						$titlesOwned++;
						$titlesFromCatalog[] = $curTitle;
					}
				}
			}elseif (isset($response['error'])){

				$this->logger->error('Error while searching for series titles : ' . $response['error']['msg']);
			}

			//Loop through items and match to records we found in the catalog.
			$titleList = [];
			foreach ($items as $index => $item){
				$titleList[$index] = null;
			}
			//Do 2 passes, one to check based on primary_isbn only and one to check based on all isbns
			foreach ($items as $index => $item){
				$isInCatalog             = false;
				$currentTitleFromCatalog = null;
				foreach ($titlesFromCatalog as $titleIndex => $currentTitleFromCatalog){
					if (!empty($item->primary_isbn) && in_array($item->primary_isbn, $currentTitleFromCatalog['allIsbns'])){
						$isInCatalog = true;
						break;
					}
				}
				if ($isInCatalog){
					$this->addTitleToTitleList($currentId, $titleList, $seriesName, $currentTitleFromCatalog, $titlesFromCatalog, $titleIndex, $item, $index);
					// updates $titleList
				}
			}

			global $configArray;
			foreach ($titleList as $index => $title){
				if ($titleList[$index] == null){
					$isInCatalog = false;
					$item        = $items[$index];
					foreach ($titlesFromCatalog as $titleIndex => $currentTitleFromCatalog){
						foreach ($item->isbns as $isbn){
							if (in_array($isbn, $currentTitleFromCatalog['allIsbns'])){
								$isInCatalog = true;
								break 2;
							}
						}
					}

					if ($isInCatalog){
						$this->addTitleToTitleList($currentId, $titleList, $seriesName, $currentTitleFromCatalog, $titlesFromCatalog, $titleIndex, $item, $index);
						// updates $titleList

						unset($titlesFromCatalog[$titleIndex]);
					}else{
						//TODO: replace with ISBN class
						$isbn     = reset($item->isbns);
						$isbn13   = strlen($isbn) == 13 ? $isbn : ISBNConverter::convertISBN10to13($isbn);
						$isbn10   = strlen($isbn) == 10 ? $isbn : ISBNConverter::convertISBN13to10($isbn);
						$curTitle = [
							'title'        => $item->full_title,
							'author'       => $item->author,
							//'publicationDate' => (string)$item->PublicationDate,
							'isbn'         => $isbn13,
							'isbn10'       => $isbn10,
							'recordId'     => -1,
							'libraryOwned' => false,
							'smallCover'   => $cover = $configArray['Site']['coverUrl'] . "/bookcover.php?size=small&isn=" . $isbn13,
							'mediumCover'  => $cover = $configArray['Site']['coverUrl'] . "/bookcover.php?size=medium&isn=" . $isbn13,
						];

						$curTitle['isCurrent'] = $currentId == $curTitle['recordId'];
						$curTitle['series']    = isset($seriesName) ? $seriesName : '';
						$curTitle['volume']    = isset($item->volume) ? $item->volume : '';
						$curTitle['reason']    = isset($item->reason) ? $item->reason : '';

						$titleList[$index] = $curTitle;
					}

				}

			}
		}
	}

//	private function loadRelatedContent($relatedContent, &$enrichment){
//		$relatedContentReturn = [];
//		foreach ($relatedContent->doc_types as $contentSection){
//			$section = [
//				'title'   => $contentSection->doc_type,
//				'content' => [],
//			];
//			foreach ($contentSection->content as $content){
//				//print_r($content);
//				$contentUrl           = $content->links[0]->url;
//				$section['content'][] = [
//					'author'     => $content->feature_author,
//					'title'      => $content->title,
//					'contentUrl' => $contentUrl,
//				];
//			}
//			$relatedContentReturn[] = $section;
//		}
//		$enrichment->relatedContent = $relatedContentReturn;
//	}

	private function loadGoodReads($goodReads, &$enrichment){
		$goodReadsInfo         = [
			'inGoodReads'      => $goodReads->is_in_goodreads,
			'averageRating'    => $goodReads->average_rating,
			'numRatings'       => $goodReads->ratings_count,
			'numReviews'       => $goodReads->reviews_count,
			'sampleReviewsUrl' => $goodReads->links[0]->url,
		];
		$enrichment->goodReads = $goodReadsInfo;
	}

	/**
	 * @param $currentId
	 * @param $titleList
	 * @param $seriesName
	 * @param $currentTitleFromCatalog
	 * @param $titlesFromCatalog
	 * @param $titleIndex
	 * @param $item
	 * @param $index
	 * @return array titleList
	 */
	private function addTitleToTitleList($currentId, &$titleList, $seriesName, $currentTitleFromCatalog, &$titlesFromCatalog, $titleIndex, $item, $index){

		$curTitle = $currentTitleFromCatalog;
		//Only use each title once if possible
		unset($titlesFromCatalog[$titleIndex]);

		$curTitle['isCurrent'] = $currentId == $curTitle['recordId'];
		$curTitle['series']    = isset($seriesName) ? $seriesName : '';
		$curTitle['volume']    = isset($item->volume) ? $item->volume : '';
		$curTitle['reason']    = isset($item->reason) ? $item->reason : '';

		$titleList[$index] = $curTitle;
	}
}
