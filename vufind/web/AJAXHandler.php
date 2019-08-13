<?php
/**
 *
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/26/2019
 *
 */

require_once ROOT_DIR . '/Action.php';

abstract class AJAXHandler extends Action {

	/**
	 * Set these arrays with the names of your methods in your class extension you intend to expose for AJAX calls.
	 * The array that an AJAX call method is a part of determines how the output is handled.
	 */

	protected $methodsThatRespondWithJSONUnstructured  = array();
	protected $methodsThatRespondWithJSONResultWrapper = array();
	protected $methodsThatRespondWithXML              = array();
	protected $methodsThatRespondWithHTML             = array();
	protected $methodsThatRespondThemselves           = array();

	function launch(){
		global $analytics;
		$analytics->disableTracking();

		$method = (isset($_GET['method']) && !is_array($_GET['method'])) ? $_GET['method'] : '';

		if (!empty($method) && method_exists($this, $method)){
			if (in_array($method, $this->methodsThatRespondWithJSONUnstructured)){
				$result = $this->$method();
				$this->sendHTTPHeaders('application/json');
				echo $this->jsonUTF8EncodeResponse($result);
			}elseif (in_array($method, $this->methodsThatRespondWithJSONResultWrapper)){
				$result = array('result' => $this->$method());
				$this->sendHTTPHeaders('application/json');
				echo $this->jsonUTF8EncodeResponse($result);
			}elseif (in_array($method, $this->methodsThatRespondWithHTML)){
				$result = $this->$method();

				$this->sendHTTPHeaders('text/html');
				echo $result;
			}elseif (in_array($method, $this->methodsThatRespondWithXML)){
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
				echo $this->jsonUTF8EncodeResponse(array('error' => "invalid_method '$method'"));
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
		try {
			require_once ROOT_DIR . '/sys/Utils/ArrayUtils.php';
			$utf8EncodedValue = ArrayUtils::utf8EncodeArray($response);
			$json             = json_encode($utf8EncodedValue);
			$error            = json_last_error();
			if ($error != JSON_ERROR_NONE || $json === false){
				if (function_exists('json_last_error_msg')){
					$json = json_encode(array('error' => 'error_encoding_data', 'message' => json_last_error_msg()));
				}else{
					$json = json_encode(array('error' => 'error_encoding_data', 'message' => json_last_error()));
				}
				global $configArray;
				if ($configArray['System']['debug']){
					print_r($utf8EncodedValue);
				}
			}
		} catch (Exception $e){
			$json = json_encode(array('error' => 'error_encoding_data', 'message' => $e));
			global $logger;
			$logger->log("Error encoding json data $e", PEAR_LOG_ERR);
		}
		return $json;
	}

	final function sendHTTPHeaders($ContentType){
		header('Content-type: ' . $ContentType);
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
	}
}