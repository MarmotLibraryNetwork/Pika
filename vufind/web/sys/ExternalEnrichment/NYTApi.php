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

/***************************************
 * Simple class to retrieve feed of NYT best sellers
 * documentation:
 * http://developer.nytimes.com/docs/read/best_sellers_api
 *
 * Last Updated: 2016-02-26 JN
 ***************************************
 */

namespace ExternalEnrichment;

class NYTApi {

//	const BASE_URI = 'http://api.nytimes.com/svc/books/v2/lists/'; // old api url
//	const BASE_URI = 'https://content.api.nytimes.com/svc/books/v2/lists/';
//	const BASE_URI = 'https://content.api.nytimes.com/svc/books/v3/lists/';
	const BASE_URI = 'https://api.nytimes.com/svc/books/v3/lists/';
	protected $api_key;
	protected $logger;

	public function __construct($key){
		global $pikaLogger;
		$this->api_key = $key;
		$this->logger  = $pikaLogger->withName(__CLASS__);
	}

	protected function buildUrl($listName = null): string{
		$url = self::BASE_URI;
		if (empty($listName)){
			// Get all lists
			$url .= 'overview.json';
		} else {
			$url .= '/current/' . $listName;
		}
		$url .= '?api-key=' . $this->api_key;
		return $url;
	}

	public function getLists(){
		//return $this->getList('names'); // call for fetching lists prior to May 2025
		return $this->getList();
	}

	public function getList($listName = null){
		$url = $this->buildUrl($listName);

		// array of request options
		global $configArray;
		$userAgent = empty($configArray['Catalog']['catalogUserAgent']) ? 'Pika' : $configArray['Catalog']['catalogUserAgent'];
		$curl_opts = [
			// set request url
			CURLOPT_URL            => $url,
			// return data
			CURLOPT_RETURNTRANSFER => 1,
			// do not include header in result
			CURLOPT_HEADER         => 0,
			// set user agent
			CURLOPT_USERAGENT      => $userAgent,
			//CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => true,
		];
		// Get cURL resource
		$curl = curl_init();
		// Set curl options
		curl_setopt_array($curl, $curl_opts);
		// Send the request & save response to $response
		$response = curl_exec($curl);

		if (!$response){
			$error = curl_error($curl);
			if (!empty($error)){
				$this->logger->error($error);
			}
		}
		// Close request to clear up some resources
		// curl_close($curl); - deprecated in PHP 8.5, the CurlHandle frees itself

		$decodedResponse = json_decode($response);

		$faultMessage = self::getFaultMessage($decodedResponse);
		if ($faultMessage !== null){
			$this->logger->error($faultMessage, ['list' => empty($listName) ? 'overview' : $listName]);
		}

		// return response
		return $decodedResponse;
	}

	/**
	 * The New York Times API reports rate limit violations and similar problems in the body of a response that curl
	 * considers successful, e.g.
	 * {"fault":{"faultstring":"Rate limit quota violation. Quota limit  exceeded. Identifier : 671b6860-427c-4dd2-9d08-daa63d434fe4","detail":{"errorcode":"policies.ratelimit.QuotaViolation"}}}
	 * A response carrying a fault has no results, so callers should stop rather than read the data they asked for.
	 *
	 * @param mixed $response decoded response from the New York Times API
	 * @return string|null the fault as a readable message, or null when the response does not contain one
	 */
	public static function getFaultMessage($response): ?string{
		if (empty($response->fault)){
			return null;
		}
		$fault   = $response->fault;
		$message = $fault->faultstring ?? 'Unknown fault';
		if (!empty($fault->detail->errorcode)){
			$message .= ' [' . $fault->detail->errorcode . ']';
		}
		return 'The New York Times API returned a fault : ' . $message;
	}

}
