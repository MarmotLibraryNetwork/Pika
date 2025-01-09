<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
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

namespace ExternalEnrichment;

class NPRBestBooks {

	const NPRListTitlePrefix = 'NPR Books We Love';

	const BASE_URL = 'https://apps.npr.org/best-books/';

	public function getList($year){
		$url = $this->buildUrl($year);

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
		// Allow cURL to use gzip compression, or any other supported encoding
// A blank string activates 'auto' mode
		curl_setopt($curl, CURLOPT_ENCODING , '');
		// Send the request & save response to $response
		$response = curl_exec($curl);
		// Close request to clear up some resources
		curl_close($curl);
		// return response
		return json_decode($response);
	}

	private function buildUrl($year){
		if (ctype_digit($year) && $year > 2012){
			$url = self::BASE_URL . $year . '-detail.json';
			return $url;
		}
		return false;
	}

}