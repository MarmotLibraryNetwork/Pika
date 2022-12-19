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

use Curl\Curl;
use \Pika\Logger;

/**
 * ExternalReviews Class
 *
 * This class fetches reviews from various services for presentation to
 * the user.
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
class ExternalReviews {
	private $isbn;
	private $results;
	// @var $logger Pika/Logger instance
	private $logger;

	/**
	 * Constructor
	 *
	 * Do the actual work of loading the reviews.
	 *
	 * @access  public
	 * @param   string      $isbn           ISBN of title to find reviews for
	 */
	public function __construct($isbn){
		$this->isbn    = $isbn;
		$this->results = [];
		$this->logger  = new Logger(__CLASS__);

		// We can't proceed without an ISBN:
		if (empty($this->isbn)){
			return;
		}

		// Don't retrieve reviews if the library or location's settings have disabled use of Reviews
		global $library;
		if (isset($library) && ($library->showStandardReviews == 0)){
			return;
		}
		global $locationSingleton;
		$location = $locationSingleton->getActiveLocation();
		if ($location != null && ($location->showStandardReviews == 0)){
			return;
		}

		/** @var Memcache $memCache */
		global $memCache, $instanceName;
		$memCacheKey = "{$instanceName}_reviews_{$isbn}";
		$reviews     = $memCache->get($memCacheKey);
		if ($reviews && !isset($_REQUEST['reload'])){
			$this->results = $reviews;
		}else{
			// Fetch from provider
			global $configArray;
			if (!empty($configArray['Content']['reviews'])){
				$providers = explode(',', $configArray['Content']['reviews']);
				foreach ($providers as $provider){
					$provider             = explode(':', trim($provider));
					$func                 = strtolower($provider[0]);
					$key                  = $provider[1];
					$this->results[$func] = method_exists($this, $func) ? $this->$func($key) : false;

					// If the current provider had no valid reviews, store nothing:
					if (empty($this->results[$func]) || PEAR_Singleton::isError($this->results[$func])){
						unset($this->results[$func]);
					}else{
						if (is_array($this->results[$func])){
							foreach ($this->results[$func] as $key => $reviewData){
								$this->results[$func][$key] = self::cleanupReview($this->results[$func][$key]);
							}
						}else{
							$this->results[$func] = self::cleanupReview($this->results[$func]);
						}
					}
				}
				$memCache->set($memCacheKey, $this->results, 0, $configArray['Caching']['purchased_reviews']);
			}
		}
	}

	/**
	 * Get the excerpt information.
	 *
	 * @access  public
	 * @return  array                       Associative array of excerpts.
	 */
	public function fetch(){
		return $this->results;
	}

	/**
	 * syndetics
	 *
	 * This method is responsible for connecting to Syndetics and abstracting
	 * reviews from multiple providers.
	 *
	 * It first queries the master url for the ISBN entry seeking a review URL.
	 * If a review URL is found, the script will then use HTTP request to
	 * retrieve the script. The script will then parse the review according to
	 * US MARC (I believe). It will provide a link to the URL master HTML page
	 * for more information.
	 * Configuration:  Sources are processed in order - refer to $sourceList.
	 * If your library prefers one reviewer over another change the order.
	 * If your library does not like a reviewer, remove it.  If there are more
	 * syndetics reviewers add another entry.
	 *
	 * @param   string  $id Client access key
	 * @return  array       Returns array with review data, otherwise a
	 *                      PEAR_Error.
	 * @access  private
	 * @author  Joel Timothy Norman <joel.t.norman@wmich.edu>
	 * @author  Andrew Nagy <andrew.nagy@villanova.edu>
	 */
	private function syndetics($id){
		global $configArray;
		global $timer;

		//list of syndetic reviews
		if (isset($configArray['SyndeticsReviews']['SyndeticsReviewsSources'])){
			$sourceList = [];
			foreach ($configArray['SyndeticsReviews']['SyndeticsReviewsSources'] as $key => $label){
				$sourceList[$key] = ['title' => $label, 'file' => "$key.XML"];
			}
		}else{
			$sourceList = [
				'BLREVIEW'        => ['title' => 'Booklist Review',
				                      'file'  => 'BLREVIEW.XML'],
				'PWREVIEW'        => ['title' => "Publisher's Weekly Review",
				                      'file'  => 'PWREVIEW.XML'],
				'LJREVIEW'        => ['title' => 'Library Journal Review',
				                      'file'  => 'LJREVIEW.XML'],
				/*'CHREVIEW'        => ['title' => 'Choice Review',
				                      'file'  => 'CHREVIEW.XML'],
				'SLJREVIEW'       => ['title' => 'School Library Journal Review',
				                      'file'  => 'SLJREVIEW.XML'],
				'HBREVIEW'        => ['title' => 'Horn Book Review',
				                      'file'  => 'HBREVIEW.XML'],
				'KIREVIEW'        => ['title' => 'Kirkus Book Review',
				                      'file'  => 'KIREVIEW.XML'],
				'CRITICASEREVIEW' => ['title' => 'Criti Case Review',
				                      'file'  => 'CRITICASEREVIEW.XML']*/
			];
		}
		$timer->logTime('Got list of syndetic reviews to show');

		//first request url
		$baseUrl = $configArray['Syndetics']['url'] ?? 'http://syndetics.com';
		$url     = $baseUrl . '/index.aspx?isbn=' . $this->isbn . '/' .
			'index.xml&client=' . $id . '&type=rw12,hw7';

		//find out if there are any reviews
		try {
			$curl = new Curl();
			$curl->setXmlDecoder('DOMDocument::loadXML');
			/** @var DOMDocument $xmlDoc */
			$xmlDoc = $curl->get($url);
		} catch (Exception $e){
			$this->logger->error($e->getMessage(), ['stacktrace' => $e->getTraceAsString()]);
			return [];
		}
		if ($curl->isCurlError()){
			$message = 'curl Error: ' . $curl->getCurlErrorCode() . ': ' . $curl->getCurlErrorMessage();
			$this->logger->warning($message);
			return [];
		}


		$review = [];
		$i      = 0;
		foreach ($sourceList as $source => $sourceInfo){
			$nodes = $xmlDoc->getElementsByTagName($source);
			if ($nodes->length){
				// Load reviews
				$url = $baseUrl . '/index.aspx?isbn=' . $this->isbn . '/' .
					$sourceInfo['file'] . '&client=' . $id . '&type=rw12,hw7';

				/** @var DOMDocument $xmlDoc2 */
				$xmlDoc2 = $curl->get($url);
				if ($curl->isCurlError()){
					$message = 'curl Error: ' . $curl->getCurlErrorCode() . ': ' . $curl->getCurlErrorMessage();
					$this->logger->error($message);
					$this->logger->error($xmlDoc2);
					continue;
				}

				// Test XML Response
				if ($xmlDoc2 === false){
					$this->logger->error('Invalid XML from ' . $url);
					continue;
				}

				// Get the marc field for reviews (520)
				$nodes = $xmlDoc2->GetElementsbyTagName('Fld520');
				if (!$nodes->length){
					// Skip reviews with missing text
					continue;
				}
				$review[$i]['Content'] = html_entity_decode($xmlDoc2->saveXML($nodes->item(0)));
				$review[$i]['Content'] = str_ireplace("<a>", "<p>", $review[$i]['Content']);
				$review[$i]['Content'] = str_ireplace("</a>", "</p>", $review[$i]['Content']);

				// Get the marc field for copyright (997)
				$nodes                   = $xmlDoc2->GetElementsbyTagName("Fld997");
				$review[$i]['Copyright'] = ($nodes->length) ? null : html_entity_decode($xmlDoc2->saveXML($nodes->item(0)));

				//Check to see if the copyright is contained in the main body of the review and if so, remove it.
				//Does not happen often.
				if ($review[$i]['Copyright']){  //stop duplicate copyrights
					$location = strripos($review[0]['Content'], $review[0]['Copyright']);
					if ($location > 0){
						$review[$i]['Content'] = substr($review[0]['Content'], 0, $location);
					}
				}

				$review[$i]['Source']   = $sourceInfo['title'];  //changes the xml to actual title
				$review[$i]['ISBN']     = $this->isbn;           //show more link
				$review[$i]['username'] = isset($configArray['BookReviews']) ? $configArray['BookReviews']['id'] : '';

				$i++;
			}
		}

		return $review;
	}

	/**
	 * Load review information from Content Cafe based on the ISBN
	 *
	 * @param $key     Content Cafe Key
	 * @return array
	 */
	private function contentCafe($key){
		global $configArray;

		$pw = $configArray['Contentcafe']['pw'];
		if (!$key){
			$key = $configArray['Contentcafe']['id'];
		}

		$url = $configArray['Contentcafe']['url'] ?? 'https://contentcafe2.btol.com';
		$url .= '/ContentCafe/ContentCafe.asmx?WSDL';

		$SOAP_options = [
			'features'               => SOAP_SINGLE_ELEMENT_ARRAYS, // sets how the soap responses will be handled
			'soap_version'           => SOAP_1_2,
//			'trace' => 1, // turns on debugging features
//			'default_socket_timeout' => 20,
		];
		$params       = [
			'userID'   => $key,
			'password' => $pw,
			'key'      => $this->isbn,
			'content'  => 'ReviewDetail',
		];
		try {
			$review     = [];
			$soapClient = new SoapClient($url, $SOAP_options);
			$response   = $soapClient->Single($params);
//			$this->logger->debug($soapClient->__getLastRequest());  // for debugging

			if ($response){
				$this->logger->debug('Got response from Content cafe');
				if (!isset($response->ContentCafe->Error)){
					$i = 0;
					if (isset($response->ContentCafe->RequestItems->RequestItem)){
						foreach ($response->ContentCafe->RequestItems->RequestItem as $requestItem){
							if (isset($requestItem->ReviewItems->ReviewItem)){ // if there are reviews available.
								foreach ($requestItem->ReviewItems->ReviewItem as $reviewItem){
									$review[$i]['Content'] = $reviewItem->Review;
									$review[$i]['Source']  = $reviewItem->Publication->_;

									$copyright               = stristr($reviewItem->Review, 'copyright');
									$review[$i]['Copyright'] = $copyright ? strip_tags($copyright) : '';

									$review[$i]['ISBN'] = $this->isbn; // show more link
									//						$review[$i]['username']  = isset($configArray['BookReviews']) ? $configArray['BookReviews']['id'] : '';
									// this data doesn't look to be used in published reviews
									$i++;
								}
							}
						}
					}else{
						$this->logger->error('Unexpected Content Cafe Response retrieving Reviews');

					}
				}else{
					$this->logger->error('Content Cafe Error Response ' . $response->ContentCafe->Error );
				}
			}

		} catch (Exception $e){
			$this->logger->error('Failed ContentCafe SOAP Request : ' . $e->getMessage());
			return new PEAR_Error('Failed ContentCafe SOAP Request');
		}
		return $review;
	}

	static function cleanupReview($reviewData){
		//Cleanup the review data
		$fullReview              = strip_tags($reviewData['Content'], '<p><a><b><em><ul><ol><em><li><strong><i><br><iframe><div>');
		$reviewData['Content']   = $fullReview;
		$reviewData['Copyright'] = strip_tags($reviewData['Copyright'], '<a><p><b><em>');
		//Trim the review to the first paragraph or 240 characters whichever comes first.
		//Make sure we get at least 140 characters
		//Get rid of all tags for the teaser so we don't risk broken HTML
		$fullReview = strip_tags($fullReview, '<p>');
		if (strlen($fullReview) > 280){
			$matches    = array();
			$numMatches = preg_match_all('/<\/p>|\\r|\\n|[.,:;]/', substr($fullReview, 180, 60), $matches, PREG_OFFSET_CAPTURE);
			if ($numMatches > 0){
				$teaserBreakPoint = $matches[0][$numMatches - 1][1] + 181;
			}else{
				//Did not find a match at a paragraph or sentence boundary, just trim to the closest word.
				$teaserBreakPoint = strrpos(substr($fullReview, 0, 240), ' ');
			}
			$teaser               = substr($fullReview, 0, $teaserBreakPoint);
			$reviewData['Teaser'] = strip_tags($teaser);
		}
		return $reviewData;
	}
}
