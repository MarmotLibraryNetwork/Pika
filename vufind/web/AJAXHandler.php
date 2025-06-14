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
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/26/2019
 */

use Pika\Cache;
use Pika\Logger;

require_once ROOT_DIR . '/Action.php';

abstract class AJAXHandler extends Action {

	/**
	 * Set these arrays with the names of your methods in your class extension you intend to expose for AJAX calls.
	 * The array that an AJAX call method is a part of determines how the output is handled.
	 */

	/*protected $methodsThatRespondWithJSONUnstructured;
	protected $methodsThatRespondWithJSONResultWrapper;
	protected $methodsThatRespondWithXML;
	protected $methodsThatRespondWithHTML;
	protected $methodsThatRespondThemselves;*/

	//private $cache;

	protected $logger;

	// Most if not all AJAX calls require javascript to be executed,
	// so most bots & crawls don't trigger AJAX calls.  But we have found
	// that at least one does execute javascript, and is also malicious
	// in the amount of requests it makes
	private $maliciousAgents = [
		'YandexRenderResourcesBot'
	];

	function __construct($error_class = null){
		global $pikaLogger;
		$this->logger = $pikaLogger->withName(get_class($this)); // note: get_class will return the class name for classes that extend this class
		parent::__construct($error_class);
	}

	function launch(){
		foreach ($this->maliciousAgents as $maliciousAgent){
			if (str_contains($_SERVER["HTTP_USER_AGENT"], $maliciousAgent)){
				$this->logger->info("Malicious agent found on AJAX call, aborting without response: " . $maliciousAgent);
				die;
			}
		}
		$method = (isset($_GET['method']) && !is_array($_GET['method'])) ? $_GET['method'] : '';

		if (!empty($method) && method_exists($this, $method)){
			if (property_exists($this, 'methodsThatRespondWithJSONUnstructured') && in_array($method, $this->methodsThatRespondWithJSONUnstructured)){
				$result = $this->$method();
				$this->sendHTTPHeaders('application/json');
				echo $this->jsonUTF8EncodeResponse($result);
			}elseif (property_exists($this, 'methodsThatRespondWithJSONResultWrapper') && in_array($method, $this->methodsThatRespondWithJSONResultWrapper)){
				$result = ['result' => $this->$method()];
				$this->sendHTTPHeaders('application/json');
				echo $this->jsonUTF8EncodeResponse($result);
			}elseif (property_exists($this, 'methodsThatRespondWithHTML') && in_array($method, $this->methodsThatRespondWithHTML)){
				$result = $this->$method();
				$this->sendHTTPHeaders('text/html');
				echo $result;
			}elseif (property_exists($this, 'methodsThatRespondWithXML') && in_array($method, $this->methodsThatRespondWithXML)){
				$result = $this->$method();
				$this->sendHTTPHeaders('text/xml');

				$xmlResponse = '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
				$xmlResponse .= "<AJAXResponse>\n";
				$xmlResponse .= $result;
				$xmlResponse .= '</AJAXResponse>';

				echo $xmlResponse;
			}elseif (in_array($method, $this->methodsThatRespondThemselves)){
				$this->$method();
			}else{
				$this->sendHTTPHeaders('application/json');
				echo $this->jsonUTF8EncodeResponse(['error' => "invalid_method '$method'"]);
			}
		}
	}

	/**
	 *  Encode an intended JSON response into UTF-8 and handle any errors with the encoding;
	 *
	 * @param $response
	 * @return false|string  UTF-8 encoded JSON string
	 */
	final function jsonUTF8EncodeResponse($response){
		global $pikaLogger;
		try {
			require_once ROOT_DIR . '/sys/Utils/ArrayUtils.php';
			$utf8EncodedValue = ArrayUtils::utf8EncodeArray($response);
			$json             = json_encode($utf8EncodedValue);

			$error            = json_last_error();
			if ($error != JSON_ERROR_NONE || $json === false){
				if (function_exists('json_last_error_msg')){
					$json = json_encode(['error' => 'error_encoding_data', 'message' => json_last_error_msg()]);
				}else{
					$json = json_encode(['error' => 'error_encoding_data', 'message' => json_last_error()]);
				}
				global $configArray;

				if ($configArray['System']['debug']){
					$pikaLogger->error("Error while JSON encoding: ", $json);
//					print_r($utf8EncodedValue);
				}
			}
		} catch (Exception $e){
			$json = json_encode(['error' => 'error_encoding_data', 'message' => $e]);
			$pikaLogger->error("Error encoding json data", ['stack_trace' => $e->getTraceAsString()]);
		}
		return $json;
	}

	final function sendHTTPHeaders($ContentType){
		header('Content-type: ' . $ContentType);
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');   // Date in the past
	}
}
