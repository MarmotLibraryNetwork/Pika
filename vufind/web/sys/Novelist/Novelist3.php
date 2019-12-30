<?php
require_once ROOT_DIR . '/Drivers/marmot_inc/ISBNConverter.php';
require_once ROOT_DIR . '/sys/Novelist/NovelistData.php';

use Curl\Curl;

class Novelist3{
	private $novelistEnabled = false;
	private $profile;
	private $pwd;
	private $cachingPeriod = 43200; // 12 hours

	public function __construct(){
		global $configArray;
		if (!empty($configArray['Novelist']['profile'])){
			$this->novelistEnabled = true;
			$this->profile         = $configArray['Novelist']['profile'];
			$this->pwd             = $configArray['Novelist']['pwd'];
			if (!empty($configArray['Caching']['novelist_enrichment'])){
				$this->cachingPeriod = $configArray['Caching']['novelist_enrichment'];
			}
		}
	}

	function doesGroupedWorkHaveCachedSeries($groupedRecordId){
		if (!empty($groupedRecordId)){
			$novelistData                           = new NovelistData();
			$novelistData->groupedRecordPermanentId = $groupedRecordId;
			if ($novelistData->count()){
				return true;
			}
		}
		return false;
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
		$novelistData                           = new NovelistData();
		$novelistData->groupedRecordPermanentId = $groupedRecordId;

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
		return array($novelistData, $doUpdate);

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
		list($novelistData, $doUpdate) = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs, $allowReload);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;

		//Check to see if we need to do an update
		if ($doUpdate){

			//Update the last update time to optimize caching
			$novelistData->lastUpdate      = time();
			$novelistData->hasNovelistData = false;

			if (!isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
				//Just check the primary ISBN since we know that was good.
				$ISBNs = array($novelistData->primaryISBN);
			}

			if (count($ISBNs)){
				//Check each ISBN for enrichment data
				foreach ($ISBNs as $isbn){
					$requestUrl = "http://novselect.ebscohost.com/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
					try{
						//Get the JSON from the service
						$curl     = new Curl();
						$curl->setDefaultDecoder('json_decode');
						$data = $curl->get($requestUrl);
						if ($curl->isError()) {
							$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();
							//TODO: update logging
//							$this->logger->warning($message);
							global $logger;
							$logger->log($message, PEAR_LOG_WARNING);
							//No enrichment for this isbn, go to the next one
							continue;
						}


						$timer->logTime("Made call to Novelist to get basic enrichment info $isbn");

						//Related ISBNs

						if (!empty($data->FeatureContent)){
							//We got data!
							$novelistData->hasNovelistData = true;
							$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
							$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn;

							//Series Information
							if (isset($data->FeatureContent->SeriesInfo)){
								$this->loadSeriesInfoFast($data->FeatureContent->SeriesInfo, $novelistData);
								$timer->logTime("loaded series data");

								if (!empty($data->FeatureContent->SeriesInfo->series_titles)) {
									//We got good data, quit looking at ISBNs
									break;
								}
							}
						}
					}catch (Exception $e) {
						//TODO: update logging
//						$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);

						global $logger;
						$logger->log("Error fetching data from NoveList $e", PEAR_LOG_ERR);
						if (isset($response)){
							$logger->log($response, PEAR_LOG_DEBUG);
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
		list($novelistData, $doFullUpdate) = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we always need to load the data since we can't cache due to terms of service
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = array($novelistData->primaryISBN);
		}

		if (count($ISBNs)){
			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = "http://novselect.ebscohost.com/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
//					$curl->setJsonDecoder('json_decode'); //This doesn't work because novelist doesn't send back a good response header for content-type to indicate JSON
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();

						//TODO: update logging
//							$this->logger->warning($message);
						global $logger;
						$logger->log($message, PEAR_LOG_WARNING);
						//No enrichment for this isbn, go to the next one
						continue;
					}

					//Related ISBNs

					if (!empty($data->FeatureContent)){
						//We got data!
						$novelistData->hasNovelistData = true;
						$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn;

						//Series Information
						if (isset($data->FeatureContent->SeriesInfo)){
							$this->loadSeriesInfo($groupedRecordId, $data->FeatureContent->SeriesInfo, $novelistData);
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
						if (isset($data->FeatureContent->RelatedContent)){
							$this->loadRelatedContent($data->FeatureContent->RelatedContent, $novelistData);
						}

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
					//TODO: update logging
//						$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);
					global $logger;
					$logger->log("Error fetching data from NoveList $e", PEAR_LOG_ERR);
					if (isset($response)){
						$logger->log($response, PEAR_LOG_DEBUG);
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

		list($novelistData, $doFullUpdate) = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we aways need to load the data since we can't cache due to terms of sevice
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = array($novelistData->primaryISBN);
		}

		//Update the last update time to optimize caching

		if (count($ISBNs)){

			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = "http://novselect.ebscohost.com/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();
						//TODO: update logging
//							$this->logger->warning($message);
						global $logger;
						$logger->log($message, PEAR_LOG_WARNING);
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
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn;

						//Similar Titles
						if (isset($data->FeatureContent->SimilarTitles)){
							$this->loadSimilarTitleInfo($groupedRecordId, $data->FeatureContent->SimilarTitles, $novelistData);
						}

						//We got good data, quit looking at ISBNs
						break;
					}
				}catch (Exception $e) {
					global $logger;
					$logger->log("Error fetching data from NoveList $e", PEAR_LOG_ERR);
					if (isset($response)){
						$logger->log($response, PEAR_LOG_DEBUG);
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

		list($novelistData, $doFullUpdate) = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we aways need to load the data since we can't cache due to terms of sevice
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = array($novelistData->primaryISBN);
		}

		//Update the last update time to optimize caching

		if (count($ISBNs)){

			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = "http://novselect.ebscohost.com/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();
						//TODO: update logging
//							$this->logger->warning($message);
						global $logger;
						$logger->log($message, PEAR_LOG_WARNING);
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
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn;

						//Similar Authors
						if (isset($data->FeatureContent->SimilarAuthors)){
							$this->loadSimilarAuthorInfo($data->FeatureContent->SimilarAuthors, $novelistData);
						}

						//We got good data, quit looking at ISBNs
						break;
					}
				}catch (Exception $e) {
					global $logger;
					$logger->log("Error fetching data from NoveList $e", PEAR_LOG_ERR);
					if (isset($response)){
						$logger->log($response, PEAR_LOG_DEBUG);
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
		$authors = array();
		$items   = $feature->authors;
		foreach ($items as $item){
			$authors[] = array(
				'name'   => $item->full_name,
				'reason' => $item->reason,
				'link'   => '/Author/Home/?author="' . urlencode($item->full_name) . '"',
			);
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
		list($novelistData, $doFullUpdate) = $this->doUpdateOfEnrichment($groupedRecordId, $ISBNs);

		$novelistData->groupedRecordHasISBN = count($ISBNs) > 0;
		$novelistData->hasNovelistData      = false;

		//When loading full data, we always need to load the data since we can't cache due to terms of service
		if (!$doFullUpdate && !isset($_REQUEST['reload']) && !empty($novelistData->primaryISBN)){
			//Just check the primary ISBN since we know that was good.
			$ISBNs = array($novelistData->primaryISBN);
		}

		if (count($ISBNs)){
			//Check each ISBN for enrichment data
			foreach ($ISBNs as $isbn){
				$requestUrl = "http://novselect.ebscohost.com/Data/ContentByQuery?profile={$this->profile}&password={$this->pwd}&ClientIdentifier={$isbn}&isbn={$isbn}&version=2.1&tmpstmp=" . time();
				try{
					//Get the JSON from the service
					$curl = new Curl();
//					$curl->setJsonDecoder('json_decode'); //This doesn't work because novelist doesn't send back a good response header for content-type to indicate JSON
					$curl->setDefaultDecoder('json_decode');
					$data = $curl->get($requestUrl);
					if ($curl->isError()) {
						$message = 'curl/http error:' . $curl->getErrorCode().': ' .$curl->getErrorMessage();

						//TODO: update logging
//							$this->logger->warning($message);
						global $logger;
						$logger->log($message, PEAR_LOG_WARNING);
						//No enrichment for this isbn, go to the next one
						continue;
					}

					//Related ISBNs

					if (!empty($data->FeatureContent)){
						//We got data!
						$novelistData->hasNovelistData = true;
						$novelistData->lastUpdate      = time(); //Update the last update time to optimize caching
						$novelistData->primaryISBN     = $data->TitleInfo->primary_isbn;

						//Series Information
						if (isset($data->FeatureContent->SeriesInfo)){
							$this->loadSeriesInfo($groupedRecordId, $data->FeatureContent->SeriesInfo, $novelistData);
							break;
						}
					}
				}catch (Exception $e) {
					//TODO: update logging
//						$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);
					global $logger;
					$logger->log("Error fetching data from NoveList $e", PEAR_LOG_ERR);
					if (isset($response)){
						$logger->log($response, PEAR_LOG_DEBUG);
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
		$seriesTitles = array();
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
		$similarSeries = array();
		foreach ($similarSeriesData->series as $similarSeriesInfo){
			$similarSeries[] = array(
				'title'  => $similarSeriesInfo->full_name,
				'author' => $similarSeriesInfo->author,
				'reason' => $similarSeriesInfo->reason,
				'link'   => 'Union/Search/?lookfor=' . $similarSeriesInfo->full_name . " AND " . $similarSeriesInfo->author,
			);
		}
		$enrichment->similarSeries      = $similarSeries;
		$enrichment->similarSeriesCount = count($similarSeries);
	}

	private function loadSimilarTitleInfo($currentId, $similarTitles, &$enrichment){
		$items               = $similarTitles->titles;
		$titlesOwned         = 0;
		$similarTitlesReturn = array();
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
		global $timer;
		$timer->logTime("Start loadNoveListTitle");

		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		//$searchObject->disableScoping();
		if (function_exists('disableErrorHandler')){
			disableErrorHandler();
		}

		//Get all of the records that could match based on ISBN
		$allIsbns = "";
		foreach ($items as $item){
			if (count($item->isbns) > 0){
				if (strlen($allIsbns) > 0){
					$allIsbns .= ' OR ';
				}
				$allIsbns .= implode(' OR ', $item->isbns);
			}
		}
		$searchObject->setBasicQuery($allIsbns, "isbn");
		$searchObject->clearFacets();
		$searchObject->disableSpelling();
		$searchObject->disableLogging();
		$searchObject->setLimit(count($items));
		$response = $searchObject->processSearch(true, false, false);

		//Get all the titles from the catalog
		$titlesFromCatalog = array();
		if (!empty($response['response']['docs'])) {
			foreach ($response['response']['docs'] as $fields){
				$groupedWorkDriver = new GroupedWorkDriver($fields);
				$timer->logTime("Create driver");

				if ($groupedWorkDriver->isValid){
					//Load data about the record

					//See if we can get the series title from the record
					$curTitle = array(
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
					);
					$timer->logTime("Load title information");
					$titlesOwned++;
					$titlesFromCatalog[] = $curTitle;
				}
			}
		}

		//Loop through items an match to records we found in the catalog.
		$titleList = array();
		foreach ($items as $index => $item){
			$titleList[$index] = null;
		}
		//Do 2 passes, one to check based on primary_isbn only and one to check based on all isbns
		foreach ($items as $index => $item){
			$isInCatalog      = false;
			$currentTitleFromCatalog = null;
			foreach ($titlesFromCatalog as $titleIndex => $currentTitleFromCatalog){
				if (in_array($item->primary_isbn, $currentTitleFromCatalog['allIsbns'])){
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

				if ($isInCatalog) {
					$this->addTitleToTitleList($currentId, $titleList, $seriesName, $currentTitleFromCatalog, $titlesFromCatalog, $titleIndex, $item, $index);
					// updates $titleList

					unset($titlesFromCatalog[$titleIndex]);
				}else{
					$isbn     = reset($item->isbns);
					$isbn13   = strlen($isbn) == 13 ? $isbn : ISBNConverter::convertISBN10to13($isbn);
					$isbn10   = strlen($isbn) == 10 ? $isbn : ISBNConverter::convertISBN13to10($isbn);
					$curTitle = array(
						'title'        => $item->full_title,
						'author'       => $item->author,
						//'publicationDate' => (string)$item->PublicationDate,
						'isbn'         => $isbn13,
						'isbn10'       => $isbn10,
						'recordId'     => -1,
						'libraryOwned' => false,
						'smallCover'   => $cover = $configArray['Site']['coverUrl'] . "/bookcover.php?size=small&isn=" . $isbn13,
						'mediumCover'  => $cover = $configArray['Site']['coverUrl'] . "/bookcover.php?size=medium&isn=" . $isbn13,
					);

					$curTitle['isCurrent'] = $currentId == $curTitle['recordId'];
					$curTitle['series']    = isset($seriesName) ? $seriesName : '';
					$curTitle['volume']    = isset($item->volume) ? $item->volume : '';
					$curTitle['reason']    = isset($item->reason) ? $item->reason : '';

					$titleList[$index] = $curTitle;
				}

			}

		}

	}

	private function loadRelatedContent($relatedContent, &$enrichment){
		$relatedContentReturn = array();
		foreach ($relatedContent->doc_types as $contentSection){
			$section = array(
				'title'   => $contentSection->doc_type,
				'content' => array(),
			);
			foreach ($contentSection->content as $content){
				//print_r($content);
				$contentUrl           = $content->links[0]->url;
				$section['content'][] = array(
					'author'     => $content->feature_author,
					'title'      => $content->title,
					'contentUrl' => $contentUrl,
				);
			}
			$relatedContentReturn[] = $section;
		}
		$enrichment->relatedContent = $relatedContentReturn;
	}

	private function loadGoodReads($goodReads, &$enrichment){
		$goodReadsInfo         = array(
			'inGoodReads'      => $goodReads->is_in_goodreads,
			'averageRating'    => $goodReads->average_rating,
			'numRatings'       => $goodReads->ratings_count,
			'numReviews'       => $goodReads->reviews_count,
			'sampleReviewsUrl' => $goodReads->links[0]->url,
		);
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