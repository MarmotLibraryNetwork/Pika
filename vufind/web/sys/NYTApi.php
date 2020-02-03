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

/***************************************
 * Simple class to retrieve feed of NYT best sellers
 * documentation:
 * http://developer.nytimes.com/docs/read/best_sellers_api
 *
 * Last Updated: 2016-02-26 JN
 ***************************************
 */
class NYTApi {

//	const BASE_URI = 'http://api.nytimes.com/svc/books/v2/lists/'; // old api url
	const BASE_URI = 'https://content.api.nytimes.com/svc/books/v2/lists/';
	protected $api_key;

	public function __construct($key) {
		$this->api_key = $key;
	}

	protected function build_url($list_name) {
		$url = self::BASE_URI . $list_name;
		$url .= '?api-key=' . $this->api_key;
		return $url;
	}

	public function get_list($list_name) {
		$url = $this->build_url($list_name);
		/*
		// super fast and easy way, but not as many options
		$response = file_get_contents($url);
		*/

		// array of request options
		$curl_opts = array(
			// set request url
			CURLOPT_URL => $url,
			// return data
			CURLOPT_RETURNTRANSFER => 1,
			// do not include header in result
			CURLOPT_HEADER => 0,
			// set user agent
			CURLOPT_USERAGENT => 'Pika app cURL Request',
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => true,
		);
		// Get cURL resource
		$curl = curl_init();
		// Set curl options
		curl_setopt_array($curl, $curl_opts);
		// Send the request & save response to $response
		$response = curl_exec($curl);
		// Close request to clear up some resources
		curl_close($curl);
		// return respone
		return $response;
	}

}
