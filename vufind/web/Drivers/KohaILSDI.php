<?php
/**
 *   Implementa Patron Interactions that would go through Koha's ILS-DI (ILS Discovery Interface) interface
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 12/18/2018
 *
 */

//require_once ROOT_DIR . '/Drivers/SIP2Driver.php';
////  I can imagine a version that fully implements the Driver Interface with out using the SIP2Driver. Pascal 12/18/2018
//abstract class KohaILSDI extends SIP2Driver {

abstract class KohaILSDI extends ScreenScrapingDriver {
	/**
	 * @var $dbConnection null
	 */
	protected $dbConnection = null;

	private $ilsdiscript = '/ilsdi.pl';

	private $webServiceURL = null;

	public function getWebServiceURL(){
		if (empty($this->webServiceURL)){
			$webServiceURL = null;
			if (!empty($this->accountProfile->patronApiUrl)){
				$webServiceURL = trim($this->accountProfile->patronApiUrl);
			}elseif (!empty($configArray['Catalog']['webServiceUrl'])){
				$webServiceURL = $configArray['Catalog']['webServiceUrl'];
			}else{
				global $logger;
				$logger->log('No Web Service URL defined in Horizon ROA API Driver', PEAR_LOG_CRIT);
			}
			$this->webServiceURL = rtrim($webServiceURL, '/'); // remove any trailing slash because other functions will add it.
		}
		return $this->webServiceURL;
	}

	public function getWebServiceResponse($url){
		$xml = $this->_curlGetPage($url);
		if ($xml !== false && $xml !== 'false'){
			if (strpos($xml, '<') !== false){
				//Strip any non-UTF-8 characters
				$xml = preg_replace('/[^(\x20-\x7F)]*/', '', $xml);

				libxml_use_internal_errors(true);
				$parsedXml = simplexml_load_string($xml);
				if ($parsedXml === false){
					//Failed to load xml
					global $logger;
					$logger->log("Error parsing xml", PEAR_LOG_ERR);
					$logger->log($xml, PEAR_LOG_DEBUG);
					foreach (libxml_get_errors() as $error){
						$logger->log("\t {$error->message}", PEAR_LOG_ERR);
					}
					return false;
				}else{
					return $parsedXml;
				}
			}else{
				return $xml;
			}
		}else{
			global $logger;
			$logger->log('Curl problem in getWebServiceResponse', PEAR_LOG_WARNING);
			return false;
		}
	}


	function initDatabaseConnection(){
		global $configArray;
		if ($this->dbConnection == null){
			$this->dbConnection = mysqli_connect($configArray['Catalog']['db_host'], $configArray['Catalog']['db_user'], $configArray['Catalog']['db_pwd'], $configArray['Catalog']['db_name'], $configArray['Catalog']['db_port']);

			if (!$this->dbConnection || mysqli_errno($this->dbConnection) != 0){
				global $logger;
				$logger->log("Error connecting to Koha database " . mysqli_error($this->dbConnection), PEAR_LOG_ERR);
				$this->dbConnection = null;
			}
			global $timer;
			$timer->logTime("Initialized connection to Koha");
		}
	}


	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param   User $patron The User to place a hold for
	 * @param   string $recordId The id of the bib record
	 * @param   string $pickupBranch The branch where the user wants to pickup the item when available
	 * @param   null|string $cancelIfNotFilledByDate The date to cancel the Hold if it isn't filled
	 * @return  array                 An array with the following keys
	 *                                success - true/false
	 *                                message - the message to display (if item holds are required, this is a form to select the item).
	 *                                needsItemLevelHold - An indicator that item level holds are required
	 *                                title - the title of the record the user is placing a hold on
	 * @access  public
	 */
	public function placeHold($patron, $recordId, $pickupBranch, $cancelIfNotFilledByDate = null){
		$result = $this->placeItemHold($patron, $recordId, null, $pickupBranch, $cancelIfNotFilledByDate);
		return $result;
	}

	function placeItemHold($patron, $recordId, $itemId, $pickupBranch = null, $cancelIfNotFilledByDate = null){
		$holdResult = array(
			'success' => false,
			'message' => 'Your hold could not be placed. '
		);

		$patronKohaId = $this->getKohaPatronId($patron);
		if (empty($pickupBranch)){
			$pickupBranch = strtoupper($patron->homeLocationCode);
		}

		$urlParameters = array(
			'service'          => empty($itemId) ? 'HoldTitle' : 'HoldItem',
			'patron_id'        => $patronKohaId,
			'bib_id'           => $recordId,
			'pickup_location'  => $pickupBranch,
		);
		if (!empty($itemId)){
			$urlParameters['item_id'] = $itemId;
		} else {
			// Hold Title request requires the user's end IP address
			$urlParameters['request_location'] = $_SERVER['REMOTE_ADDR']; //TODO: End user's IP. (yike's! Koha wants this?)
		}
		if (!empty($cancelIfNotFilledByDate)){
			$urlParameters['needed_before_date'] = $cancelIfNotFilledByDate;//TODO determine date format needed
		}
		//create the hold using the web service call
		$webServiceURL = $this->getWebServiceURL() . $this->ilsdiscript;
		$webServiceURL .= '?' . http_build_query($urlParameters);

		$success      = false;
		$title        = null;
		$holdResponse = $this->getWebServiceResponse($webServiceURL);
		if (!empty($holdResponse)){
			if (empty($holdResponse->message) && empty($holdResponse->code)){

			}else{
				//TODO: error message
				$message = 'Failed to place the hold';
				if (isset($holdResponse->message)){
					$message .= ' : ' . $holdResponse->message;
				}else{
					$message .= '. Error Code : ' . $holdResponse->code;
				}
			}
			$holdResult = array(
				'title' => $title,
				'bib' => $recordId,
				'success' => $success,
				'message' => $message
			);
		}

		return $holdResult;
	}

	private function getKohaPatronId(User $patron){
		//TODO: memcache KohaPatronIds
		$this->initDatabaseConnection();
		if ($this->dbConnection){
			$sql     = 'SELECT borrowernumber FROM borrowers WHERE cardnumber = "' . $patron->getBarcode() . '"';
			$results = mysqli_query($this->dbConnection, $sql);
			if ($results){
				$row          = $results->fetch_assoc();
				$kohaPatronId = $row['borrowernumber'];
				if (!empty($kohaPatronId)){
					//TODO: save to memcache
					return $kohaPatronId;
				}
			}
		}
		return false;
	}


}