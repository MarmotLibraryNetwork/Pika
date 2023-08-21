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
 * An abstract base class so screen scraping functionality can be stored in a single location
 *
 * @category Pka
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/23/2015
 * Time: 2:32 PM
 */
use \Pika\Logger;
abstract class ScreenScrapingDriver implements DriverInterface {
	/** @var  AccountProfile $accountProfile */
	public $accountProfile;
	private $cookieJar;
	public $curl_connection; // need access in order to check for curl errors.


	/**
	 * @param AccountProfile $accountProfile
	 */
	public function __construct($accountProfile){
		$this->accountProfile = $accountProfile;
	}

	public function __destruct(){
		$this->_close_curl();
	}

	public function setCookieJar(){
		$cookieJar       = @tempnam("/tmp", "CURLCOOKIE");
		$this->cookieJar = $cookieJar;
	}

	/**
	 * @return mixed CookieJar name
	 */
	public function getCookieJar() {
		if (!isset($this->cookieJar) || is_null($this->cookieJar)){ //tried empty(); may be a problem
			$this->setCookieJar();
		}
		return $this->cookieJar;
	}

	/**
	 * Initialize and configure curl connection
	 *
	 * @param null        $curlUrl optional url passed to curl_init
	 * @param null|array  $curl_options is an array of curl options to include or overwrite.
	 *                    Keys is the curl option constant, Values is the value to set the option to.
	 * @return resource
	 */
	public function _curl_connect($curlUrl = null, $curl_options = null, $additionalHeaders = null){
		//Make sure we only connect once
		if (!$this->curl_connection){
			$header = $this->getCustomHeaders();
			if ($header == null) {
				global $interface;
				/** @var string $gitBranch */
				$gitBranch = $interface->getVariable('gitBranch');
				if (substr($gitBranch, -1) == "\n"){
					$gitBranch = substr($gitBranch, 0, -1);
				}
				$userAgent = empty($configArray['Catalog']['catalogUserAgent']) ? 'Pika' : $configArray['Catalog']['catalogUserAgent'];
				$header    = [];
				$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
				$header[]  = "Cache-Control: max-age=0";
				$header[]  = "Connection: keep-alive";
				$header[]  = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
				$header[]  = "Accept-Language: en-us,en;q=0.5";
				$header[]  = "User-Agent: $userAgent $gitBranch";
			}
			if (!empty($additionalHeaders) && is_array($additionalHeaders)) {
				$header = array_merge($header, $additionalHeaders);
			}

			$cookie = $this->getCookieJar();

			$this->curl_connection = curl_init($curlUrl);
			$default_curl_options  = [
				CURLOPT_CONNECTTIMEOUT    => 20,
				CURLOPT_TIMEOUT           => 60,
				CURLOPT_HTTPHEADER        => $header,
				CURLOPT_RETURNTRANSFER    => true,
				CURLOPT_FOLLOWLOCATION    => true,
				CURLOPT_UNRESTRICTED_AUTH => true,
				CURLOPT_COOKIEJAR         => $cookie,
				CURLOPT_COOKIESESSION     => false,
				CURLOPT_FORBID_REUSE      => false,
				CURLOPT_HEADER            => false,
				CURLOPT_AUTOREFERER       => true,
				//  CURLOPT_HEADER => true, // debugging only
				//  CURLOPT_VERBOSE => true, // debugging only
			];

			if (!empty($curl_options)) {
				foreach ($curl_options as $curlOption => $setting){
					$default_curl_options[$curlOption] = $setting;
				}
			}
			curl_setopt_array($this->curl_connection, $default_curl_options);
		}else{
			//Reset to HTTP GET and set the active URL
			curl_setopt($this->curl_connection, CURLOPT_HTTPGET, true);
			curl_setopt($this->curl_connection, CURLOPT_URL, $curlUrl);
		}

		return $this->curl_connection;
	}

	/**
	 *  Cleans up after curl operations.
	 *  Is ran automatically as the class is being shutdown.
	 */
	public function _close_curl() {
		if ($this->curl_connection) {
			curl_close($this->curl_connection);
			unset($this->curl_connection);
		}
		if (!empty($this->cookieJar) && file_exists($this->cookieJar)) {
			unlink($this->cookieJar);
			unset($this->cookieJar);
		}
	}

	/**
	 * Uses the GET method to retrieve content from a page
	 *
	 * @param string    $url          The url to post to
	 *
	 * @return string   The response from the web page if any
	 */
	public function _curlGetPage($url, $curlOptions = []){
		$this->_curl_connect($url, $curlOptions);
		$return = curl_exec($this->curl_connection);
//		$info = curl_getinfo($this->curl_connection);
		if (!$return) { // log curl error

			$this->logger->error('curl get error : ' . curl_error($this->curl_connection));
		}
		return $return;
	}

	/**
	 * Uses the POST Method to retrieve content from a page
	 *
	 * @param string    $url          The url to post to
	 * @param string[]  $postParams   Additional Post Params to use
	 *
	 * @return string   The response from the web page if any
	 */
	public function _curlPostPage($url, $postParams){
		$post_string = http_build_query($postParams);

		$this->_curl_connect($url);
		curl_setopt_array($this->curl_connection, [
			CURLOPT_POST       => true,
			CURLOPT_POSTFIELDS => $post_string
		]);

//		global $instanceName;
//		$usingLocalDevelopment = stripos($instanceName, 'localhost') !== false;
//		if ($usingLocalDevelopment) {
//			$this->setupDebugging();
//		}

		$return = curl_exec($this->curl_connection);

//		// Debugging only, comment out later.
//		if ($usingLocalDevelopment) {
//			$info          = curl_getinfo($this->curl_connection);
//			$headerRequest = curl_getinfo($this->curl_connection, CURLINFO_HEADER_OUT);
//			$error         = curl_error($this->curl_connection);
//		}

		if (!$return) { // log curl error

			$this->logger->error('curl post error : ' . curl_error($this->curl_connection));
		}
		return $return;
	}

	/**
	 * Uses the POST Method to retrieve content from a page
	 *
	 * @param string           $url          The url to post to
	 * @param string[]|string  $postParams   Additional Post Params to use
	 * @param boolean   $jsonEncode
	 *
	 * @return string   The response from the web page if any
	 */
	public function _curlPostBodyData($url, $postParams, $jsonEncode = true){
		if ($jsonEncode){
			$post_string = json_encode($postParams);
		}else{
			$post_string  = $postParams;
		}

		$this->_curl_connect($url);
		curl_setopt_array($this->curl_connection, [
			CURLOPT_POST       => true,
			CURLOPT_POSTFIELDS => $post_string,
		]);


		$return = curl_exec($this->curl_connection);
		if (!$return){
			$error = curl_error($this->curl_connection);
			$this->logger->error('curl post error : ' . $error, [$url, $postParams]);

		}
		return $return;
	}

	protected function setupDebugging(){
		curl_setopt($this->curl_connection, CURLINFO_HEADER_OUT, true);
		$result1 = curl_setopt($this->curl_connection, CURLOPT_HEADER, true);
		$result2 = curl_setopt($this->curl_connection, CURLOPT_VERBOSE, true);
		return $result1 && $result2;
	}

	/**
	 * @deprecated Only used by the probable obsolete Library Solution patron integration driver
	 * @return false|string
	 */
	public function getVendorOpacUrl(){
		if ($this->accountProfile && $this->accountProfile->vendorOpacUrl ){
			$host = $this->accountProfile->vendorOpacUrl;
		}else{
			global $pikaLogger;
			$pikaLogger->error('No account profile set for screen scraping driver.');
		}

		if (substr($host, -1) == '/') {
			$host = substr($host, 0, -1);
		}
		return $host;
	}

	protected function getCustomHeaders() {
		return null;
	}
}
