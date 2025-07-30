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

	public function __construct($key){
		$this->api_key = $key;
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
				global $pikaLogger;
				$logger = $pikaLogger->withName(__CLASS__);
				$logger->error($error);
			}
		}
		// Close request to clear up some resources
		curl_close($curl);
		// return response
		return json_decode($response);
	}

}
