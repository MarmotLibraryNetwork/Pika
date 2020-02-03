<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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
 * Handles integration with Prospector
 */
use Curl\Curl;
use \Pika\Logger;

class Prospector {

	// @var $logger Pika/Logger instance
	private $logger;

	/**
	 * Prospector constructor.
	 */
	public function __construct(){
		$this->logger  = new Logger(__CLASS__);
	}

	/**
	 * Load search results from Prospector using the encore interface.
	 * If $prospectorRecordDetails are provided, will sort the existing result to the
	 * top and tag it as being the record.
	 * @param string[] $searchTerms
	 * @param int $maxResults
	 * @return array|null
	 */
	function getTopSearchResults($searchTerms, $maxResults){
		$prospectorUrl = $this->getSearchLink($searchTerms);

		//Load the HTML from Prospector
		try {
			$curl            = new Curl();
			$prospectorInfo = $curl->get($prospectorUrl);
		} catch (ErrorException $e){
			$this->logger->error($e->getMessage(), ['stacktrace'=>$e->getTraceAsString()]);
			return null;
		}
		if ($curl->isCurlError()) {
			$message = 'curl Error: '.$curl->getCurlErrorCode().': '.$curl->getCurlErrorMessage();
			$this->logger->warning($message);
			return null;
		}

		//Get the total number of results
		if (preg_match('/<span class="noResultsHideMessage">.*?(\d+) - (\d+) of (\d+).*?<\/span>/s', $prospectorInfo, $summaryInfo)){
			$firstResult     = $summaryInfo[1];
			$lastResult      = $summaryInfo[2];
			$numberOfResults = $summaryInfo[3];

			//Parse the information to get the titles from the page
			preg_match_all('/gridBrowseCol2(.*?)bibLocations/si', $prospectorInfo, $titleInfo, PREG_SET_ORDER);
			$prospectorTitles = array();
			for ($matchi = 0;$matchi < count($titleInfo);$matchi++){
				$curTitleInfo = array();
				//Extract the title and bid from the titleTitleInfo
				$titleTitleInfo = $titleInfo[$matchi][1];

				//Get the cover
				if (preg_match('/<div class="itemBookCover">.*?<img.*?src="(.*?)".*<\/div>/s', $titleTitleInfo, $imageMatches)){
					$curTitleInfo['cover'] = $imageMatches[1];
					//echo "Found book cover " . $curTitleInfo['cover'];
				}

				if (preg_match('/<span class="title">.*?<a.*?href.*?__R(.*?)__.*?>\\s*(.*?)\\s*<\/a>.*?<\/span>/s', $titleTitleInfo, $titleMatches)){
					$curTitleInfo['id'] = $titleMatches[1];
					//Create the link to the title in Encore
					global $configArray;
					$innReachEncoreHostUrl = $configArray['InterLibraryLoan']['innReachEncoreHostUrl'];

					$curTitleInfo['link']  = $innReachEncoreHostUrl . '/iii/encore/record/C__R' . urlencode($curTitleInfo['id']) . '__Orightresult?lang=eng&amp;suite=def';
					$curTitleInfo['title'] = strip_tags($titleMatches[2]);
				}else{
					//Couldn't load information, skip to the next one.
					continue;
				}

				//Extract the format from the itemMediaDescription
				if (preg_match('/<span class="itemMediaDescription" id="mediaTypeInsertComponent">(.*?)<\/span>/s', $titleTitleInfo, $formatMatches)){
					$formatInfo = trim(strip_tags($formatMatches[1]));
					if (strlen($formatInfo) > 0){
						$curTitleInfo['format'] = $formatInfo;
					}
				}

				//Extract the author from the titleAuthorInfo
				$titleAuthorInfo = $titleInfo[$matchi][1];
				if (preg_match('/<div class="dpBibAuthor">(.*?)<\/div>/s', $titleAuthorInfo, $authorMatches)){
					$authorInfo = trim(strip_tags($authorMatches[1]));
					if (strlen($authorInfo) > 0){
						$curTitleInfo['author'] = $authorInfo;
					}
				}

				//Extract the publication date from the titlePubDateInfo
				$titlePubDateInfo = $titleInfo[$matchi][1];
				if (preg_match('/"itemMediaYear".*?>(.*?)<\/span>/s', $titlePubDateInfo, $pubMatches)){
					//Make sure we are not getting scripts and copy counts
					if (!preg_match('/img/', $pubMatches[1]) && !preg_match('/script/', $pubMatches[1])){
						$publicationInfo = trim(strip_tags($pubMatches[1]));
						if (strlen($publicationInfo) > 0){
							$curTitleInfo['pubDate'] = $publicationInfo;
						}
					}
				}

				//Extract format titlePubDateInfo
				$titleFormatInfo = $titleInfo[$matchi][1];
				if (preg_match('/"itemMediaDescription".*?>(.*?)<\/span>/s', $titleFormatInfo, $formatMatches)){
					//Make sure we are not getting scripts and copy counts
					$formatInfo = trim(strip_tags($formatMatches[1]));
					if (strlen($formatInfo) > 0){
						$curTitleInfo['format'] = $formatInfo;
					}
				}

				$prospectorTitles[] = $curTitleInfo;
			}

			$prospectorTitles = array_slice($prospectorTitles, 0, $maxResults, true);
			return array(
				'firstRecord' => $firstResult,
				'lastRecord'  => $lastResult,
				'resultTotal' => $numberOfResults,
				'records'     => $prospectorTitles,
			);
		}else{
			return array(
				'firstRecord' => 0,
				'lastRecord'  => 0,
				'resultTotal' => 0,
				'records'     => array(),
			);
		}

	}

	/**
	 * Generate a search URL for the ILL website
	 *
	 * @param string[] $searchTerms
	 * @return string
	 */
	function getSearchLink($searchTerms){
		$search = "";
		foreach ($searchTerms as $term){
			if (strlen($search) > 0){
				$search .= ' ';
			}
			//Parse Advanced Pika search
			if (is_array($term) && isset($term['group'])){
				foreach ($term['group'] as $groupTerm){
					if (strlen($search) > 0){
						$search .= ' ';
					}
					if (isset($groupTerm['lookfor'])){
						$search = $this->parseSearchTermsForProspectorURL($groupTerm['field'], $groupTerm['lookfor'], $search);
					}
				}
			}else{
				$search = $this->parseSearchTermsForProspectorURL($term['index'], $term['lookfor'], $search);
			}
		}
		//Setup the link to Prospector (search classic)
		//$prospectorUrl = "http://prospector.coalliance.org/search/?searchtype=X&searcharg=" . urlencode($search) . "&Da=&Db=&SORT=R";
		//Fix prospector url issue
		$search = str_replace('+', '%20', urlencode(str_replace('/', '', $search)));
		// Handle special exception: ? character in the search must be encoded specially
		$search = str_replace('%3F', 'Pw%3D%3D', $search);
		global $configArray;
		$innReachEncoreHostUrl = $configArray['InterLibraryLoan']['innReachEncoreHostUrl'];
		$prospectorUrl         = $innReachEncoreHostUrl . '/iii/encore/search/C__S' . $search . '__Orightresult__U1?lang=eng&amp;suite=def';
		return $prospectorUrl;
	}

	/**
	 * @param string $searchType Which type of search term is this, eg. title, author, subject
	 * @param string $termValue The search phrase
	 * @param string $searchString The search string that has already been built
	 * @return string  The search string
	 */
	private function parseSearchTermsForProspectorURL($searchType, $termValue, $searchString){
		if (isset($searchType)){
			switch ($searchType){
				case 'Author' :
					$searchString .= "a:($termValue)";
					break;
				case 'Title' :
					$searchString .= "t:($termValue)";
					break;
				case 'Subject' :
					$searchString .= "d:($termValue)";
					break;
				default:
					$searchString .= $termValue;
			}
		}else{
			$searchString .= $termValue;
		}
		return $searchString;
	}
}
