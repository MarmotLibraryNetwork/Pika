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
 * Sierra API functions specific to Sacramento Public Library.
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 */
namespace Pika\PatronDrivers;

use Curl\Curl;
use User;
use DateInterval;
use DateTime;
use InvalidArgumentException;

use Library;
use Location;

class Sacramento extends Sierra {

	public function __construct($accountProfile){
		parent::__construct($accountProfile);
		$this->logger->info('Using driver: Pika\PatronDrivers\Sacramento');
	}

	/**
	 * Override the default Id fetching to look in the 'i' varfield for Sacramento Patrons, which will include their
	 *student ids as well (which we use as barcodes).
	 *
	 * @param string|User $patronOrBarcode
	 * @param bool $searchSacramentoStudentIdField
	 * @return false|int
	 * @throws \ErrorException
	 */
	public function getPatronId($patronOrBarcode, $searchSacramentoStudentIdField = true){
		return parent::getPatronId($patronOrBarcode, true);
	}


	/**
	 * @param User $patron
	 * @param string $pageToCall
	 * @param string[] $postParams
	 * @param bool $patronAction
	 * @return Curl|false|mixed|null
	 * @throws \ErrorException
	 */
	private function _curlLegacy($patron, $pageToCall, $postParams = array(), $patronAction = true){

		$c = new Curl();

		// base url for following calls
		$vendorOpacUrl = $this->accountProfile->vendorOpacUrl;

		$headers       = [
			'Accept'          => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
			'Cache-Control'   => 'max-age=0',
			'Connection'      => 'keep-alive',
			'Accept-Charset'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Accept-Language' => 'en-us,en;q=0.5',
			'User-Agent'      => 'Pika'
		];
		$c->setHeaders($headers);

		$cookie   = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		// first log patron in
		$postData = $this->accountProfile->usingPins() ? [
			'code' => $patron->barcode,
			'pin'  => $patron->getPassword()
		] : [
			'name' => $patron->cat_username,
			'code' => $patron->barcode
		];

		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r        = $c->post($loginUrl, $postData);

		if ($c->isError()){
			$c->close();
			return false;
		}

		$sierraPatronId = $this->getPatronId($patron); //when logging in with pin, this is what we will find

		if(!strpos($r, (string) $sierraPatronId) && !stripos($r, (string) $patron->cat_username)) {
			// check for cas login. do cas login if possible
			$casUrl = '/iii/cas/login';
			if(stristr($r, $casUrl)) {
				$this->logger->info('Trying cas login.');
				preg_match('|<input type="hidden" name="lt" value="(.*)"|', $r, $m);
				if($m) {
					$postData['lt']       = $m[1];
					$postData['_eventId'] = 'submit';
				} else {
					return false;
				}
				$casLoginUrl = $vendorOpacUrl.$casUrl;
				$r = $c->post($casLoginUrl, $postData);
				if(!stristr($r, $patron->cat_username)) {
					$this->logger->warning('cas login failed.');
					return false;
				}
				$this->logger->info('cas login success.');
			} else {
				$this->logger->warning('login failed.');
				return false;
			}
		}

		$scope    = $this->getLibraryScope(); // IMPORTANT: Scope is needed for Bookings Actions to work
		$optUrl   = $patronAction ? $vendorOpacUrl . '/patroninfo~S' . $scope . '/' . $sierraPatronId . '/' . $pageToCall
			: $vendorOpacUrl . '/' . $pageToCall;
		// Most curl calls are patron interactions, getting the bookings calendar isn't

		$c->setUrl($optUrl);
		if (!empty($postParams)){
			$r = $c->post($postParams);
		}else{
			$r = $c->get($optUrl);
		}

		if ($c->isError()){
			return false;
		}

		if (stripos($pageToCall, 'webbook?/') !== false){
			// Hack to complete booking a record
			return $c;
		}
		return $r;
	}


	public function updateSms(User $patron) {
		$patronId = $this->getPatronId($patron);

		$cc = new Curl();
		// base url for following calls
		$vendorOpacUrl = $this->accountProfile->vendorOpacUrl;

		$headers = [
			'Accept'          => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
			'Cache-Control'   => 'max-age=0',
			'Connection'      => 'keep-alive',
			'Accept-Charset'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Accept-Language' => 'en-us,en;q=0.5',
			'User-Agent'      => 'Pika'
		];
		$cc->setHeaders($headers);

		$cookie = tempnam("/tmp", "CURLCOOKIE");
		$curlOpts  = [
		 CURLOPT_CONNECTTIMEOUT    => 20,
		 CURLOPT_TIMEOUT           => 60,
		 CURLOPT_RETURNTRANSFER    => true,
		 CURLOPT_FOLLOWLOCATION    => true,
		 CURLOPT_UNRESTRICTED_AUTH => true,
		 CURLOPT_COOKIEJAR         => $cookie,
		 CURLOPT_COOKIESESSION     => false,
		 CURLOPT_HEADER            => false,
		 CURLOPT_AUTOREFERER       => true,
		];
		$cc->setOpts($curlOpts);

		// first log patron in
		$postData = $this->accountProfile->usingPins() ? [
			'code' => $patron->barcode,
			'pin'  => $patron->password
		] : [
			'name' => $patron->cat_username,
			'code' => $patron->barcode
		];
		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r        = $cc->post($loginUrl, $postData);

		if ($cc->isError()){
			$cc->close();
			return false;
		}

		if (!stristr($r, $patron->cat_username)){
			// check for cas login. do cas login if possible
			$casUrl = '/iii/cas/login';
			if(stristr($r, $casUrl)) {
				$this->logger->info('Trying cas login.');
				preg_match('|<input type="hidden" name="lt" value="(.*)"|', $r, $m);
				if($m) {
					$postData['lt']       = $m[1];
					$postData['_eventId'] = 'submit';
				} else {
					return false;
				}
				$casLoginUrl = $vendorOpacUrl.$casUrl;
				$r = $cc->post($casLoginUrl, $postData);
				if(!stristr($r, $patron->cat_username)) {
					$this->logger->warning('cas login failed.');
					return false;
				}
				$this->logger->info('cas login success.');
			}
		}

		// first update mobile #
		if(isset($_POST['smsNotices']) && $_POST['smsNotices'] == 'on') {
			$mobileNumber = trim($_POST['mobileNumber']);
			$phones[]     = ['number' => $mobileNumber, 'type' => 'o'];
		}else{
			$phones[] = ['number' => ' ', 'type' => 'o']; #todo: does api accept empty for updates?
		}
		$apiParams = ['phones' => $phones];
		// to note: calling _doRequest will create a new instance of Curl so we won't destroy the cookie jar
		$operation = 'patrons/'.$patronId;
		$res = $this->_doRequest($operation, $apiParams, 'PUT');
		// even if this request fails we'll still try to opt out of sms messages
		if(!$res) {
			$this->logger->warn("Unable to update patron mobile phone.", ["message"=>$this->apiLastError]);
		}

		$patronCacheKey = $this->cache->makePatronKey('patron', $patron->id);
		$this->cache->delete($patronCacheKey);
		// next update sms notification option
		if(isset($_POST['smsNotices']) && $_POST['smsNotices'] == 'on') {
			$params = ['optin' => 'on'];
		} else {
			$params = ['optin' => 'off'];
		}
		$scope  = isset($this->configArray['OPAC']['defaultScope']) ? $this->configArray['OPAC']['defaultScope'] : '93';
		$optUrl = $vendorOpacUrl . "/patroninfo~S" . $scope . "/" . $patronId . "/modpinfo";

		$cc->setUrl($optUrl);
		$r = $cc->post($params);

		if($cc->isError()) {
			$cc->close();
			return false;
		}

		if (stristr($r, 'Patron information updated')) {
			$errors = false;
		} else {
			$errors = true;
		}

		$cc->close();
		return $errors;
	}

	public function hasUsernameField(){
		return true;
	}
	
	public function selfRegister($extraSelfRegParams = false) {
		global $library;
		// sacramento test and production, woodlands test and production
		if ($library->subdomain == 'catalog' || $library->subdomain == 'spl' || $library->subdomain == 'woodland' || $library->subdomain == 'cityofwoodland') {
			// Capitalize All Input, expect pin passwords
			foreach ($this->getSelfRegistrationFields() as $formField) {
				$formFieldName = $formField['property'];
				if ($formField != 'pin' && $formField != 'pin1') {
					$_POST[$formFieldName] = strtoupper($_POST[$formFieldName]);
				}
			}
		}

		// sanity checks
		if(!property_exists($library, 'selfRegistrationDefaultpType') || empty($library->selfRegistrationDefaultpType)) {
			$message = 'Missing configuration parameter selfRegistrationDefaultpType for ' . $library->displayName;
			$this->logger->error($message);
			throw new InvalidArgumentException($message);
		}
		if(!property_exists($library, 'selfRegistrationAgencyCode') || empty($library->selfRegistrationAgencyCode)) {
			$message = 'Missing configuration parameter selfRegistrationAgencyCode for ' . $library->displayName;
			$this->logger->error($message);
			throw new InvalidArgumentException($message);
		}

		$params = [];
		// ddepartment varfield d
		// library specific field
		// four letters of last name, first letter of first name, two digit birth month, two digit birth day
		// short last names get padded with Z
		$lastNameFourLetters = substr($_REQUEST['lastname'], 0, 4);
		$lastNameFourLetters = strtoupper($lastNameFourLetters);
		$lastNameFourLetters = str_pad($lastNameFourLetters, 4, "Z", STR_PAD_RIGHT);
		$firstNameOneLetter  = substr($_REQUEST['firstname'], 0, 1);
		$firstNameOneLetter  = strtoupper($firstNameOneLetter[0]);
		$birthDate           = trim($_REQUEST['birthdate']);
		$birthDate           = date_create_from_format('m-d-Y', $birthDate);
		$birthDay            = date_format($birthDate, 'd');
		$birthMonth          = date_format($birthDate, 'm');
		$ddepartment         = $lastNameFourLetters . $firstNameOneLetter . $birthMonth . $birthDay; //var field d
		$params['varFields'][] = [
			"fieldTag" => "d",
			"content"  => $ddepartment
		];

		foreach ($_POST as $key=>$val) {
			switch ($key) {
				case 'email':
					$val          = trim($val);
					$successEmail = false;
					if(!empty($val)) {
						$successEmail = $val;
						$params['emails'][] = $val;
					}
					break;
				case 'primaryphone':
					$val = trim($val);
					if(!empty($val)) {
						$params['phones'][] = ['number' => $val, 'type' => 't'];
					}
					break;
				case 'altphone':
					$val = trim($val);
					//$params['phones'][] = ['number'=>$val, 'type'=>'p'];
					break;
				case 'birthdate':
					if(isset($val) && $val != '') {
						// don't let registration occur if birthdate less than 30 days ago.
						$birthDate = DateTime::createFromFormat('m-d-Y', $val);
						$todayDate = new DateTime();
						$dateDiff  = $birthDate->diff($todayDate);
						$days      = (integer)$dateDiff->days;
						if ($days < 30){
							return ['success' => false, 'barcode' => ''];
						}
						$params['birthDate'] = $birthDate->format('Y-m-d');
					}else{
						return ['success' => false, 'barcode' => ''];
					}
					break;
			}
		}

		// get the right pCode3
		$librarySubDomain = $library->subdomain;
		switch($librarySubDomain) {
			case 'colusa':
			case 'countyofcolusa':
				$pCode3 = 30;
				break;
			case 'folsom':
				$pCode3 = 44;
				break;
			case 'spl':
			case 'catalog':
				$pCode3 = 117;
				break;
			case 'sutter':
			case 'suttercounty':
				$pCode3 = 158;
				break;
			case 'woodland':
			case 'cityofwoodland':
				$pCode3 = 172;
				break;
		}
		// sacramento defaults for pcodes
		$params['patronCodes'] = [
			"pcode1" => "e",
			"pcode2" => "3",
			"pcode3" => $pCode3,
			"pcode4" => 0
		];

		// sacramento default message field
		$params['pMessage'] = 'o';

		// sacramento defaults to this for self reg users
		$params['homeLibraryCode'] = 'yyy';

		// default patron type
		$params['patronType'] = (int)$library->selfRegistrationDefaultpType;

		// generate a random temp barcode
		$min = str_pad(1, $library->selfRegistrationBarcodeLength, 0);
		$max = str_pad(9, $library->selfRegistrationBarcodeLength, 9);
		// it's possible to register a patron with a barcode that is already in Sierra so make sure this doesn't happen
		$barcodeTest = true;
		do {
			$barcode = (string)mt_rand((int)$min, (int)$max);
			$barcodeTest = $this->getPatronId($barcode);
		} while ($barcodeTest === true);
		$params['barcodes'][] = $barcode;

		// agency code
		$params['fixedFields']["158"] = [
			"label" => "PAT AGENCY",
			"value" => $library->selfRegistrationAgencyCode
		];
		// notice preference -- default to z
		$params['fixedFields']['268'] = [
			"label" => "Notice Preference",
			"value" => 'z'
		];
		// expiration date
		$interval   = 'P' . $library->selfRegistrationDaysUntilExpire . 'D';
		$expireDate = new DateTime();
		$expireDate->add(new DateInterval($interval));
		$params['expirationDate'] = $expireDate->format('Y-m-d');

		// names -- standard is Last, First Middle for sacramento
		$name  = trim($_POST['lastname']) . ", ";
		$name .= trim($_POST['firstname']);
		if(!empty($_POST['middlename'])) {
			$name .= ' ' . trim($_POST['middlename']);
		}
		// for sacramento check if the name exists
		$nameCheckParams = [
		  'varFieldTag'     => 'n',
		  'varFieldContent' => $name,
		  'fields'          => 'names,birthDate'
		];
		$nameCheckOperation = 'patrons/find';
		$nameCheckRes = $this->_doRequest($nameCheckOperation, $nameCheckParams, 'GET');
		// the api returns an error if it finds more than one patron (silly!) so need to check for "duplicate"
		// the api returns ALSO returns an error if the name isn't found so be careful here
		// if $nameCheckRes is not false than a record was found matching the name.
		if(!$nameCheckRes) {
			//return false;
			if(stristr($this->apiLastError, "duplicate")) {
				return false;
			}
		} elseif($nameCheckRes) {
			return false;
		}
		$params['names'][] = $name;

		// address
		// Do these in order of lines
		// guardian
		if((isset($_POST['guardianFirstName']) && $_POST['guardianFirstName'] != '')
			&& (isset($_POST['guardianLastName']) && $_POST['guardianLastName'] != '')) {
			$params['addresses'][0]['lines'][] = 'C/O' . ' ' . trim($_POST['guardianFirstName']) . ' ' . trim($_POST['guardianLastName']);
		}
		// street address
		$address = trim($_POST['address']);
		// apt number
		if(isset($_POST['apartmentnumber']) && $_POST['apartmentnumber'] != '') {
			$address .= ' APT ' . trim($_POST['apartmentnumber']);
		}

		$params['addresses'][0]['lines'][] = $address;
		// city state and zip -- no comma for Sacramento
		$cityStateZip = trim($_POST['city']).' '.trim($_POST['state']).' '.trim($_POST['zip']);
		$params['addresses'][0]['lines'][] = $cityStateZip;
		$params['addresses'][0]['type'] = 'a';

		// if library uses pins
		if($this->accountProfile->usingPins()) {
			$pin = trim($_POST['pin']);
			$pinConfirm = trim($_POST['pinconfirm']);

			if(!($pin == $pinConfirm)) {
				return ['success'=>false, 'barcode'=>''];
			} else {
				$params['pin'] = $pin;
			}
		}

		$this->logger->debug('Self registering patron', ['params' => $params]);
		$operation = "patrons/";
		$r = parent::_doRequest($operation, $params, "POST");

		if(!$r) {
			$this->logger->warning('Failed to self register patron');
			return ['success'=>false, 'barcode'=>''];
		}

		if($successEmail) {
			$emailSent = $this->sendSelfRegSuccessEmail($barcode);
		}

		$this->logger->debug('Success self registering patron');
		return ['success' => true, 'barcode' => $barcode];

	}


	public function getSelfRegistrationFields(){
		global $library;
		$fields = [];
		if ($library && $library->promptForBirthDateInSelfReg){
			$fields[] = [
				'property'    => 'birthdate',
				'type'        => 'date',
				'label'       => 'Date of Birth (MM-DD-YYYY)',
				'description' => 'Date of birth',
				'maxLength'   => 10,
				'required'    => true
			];
		}
		$fields[] = [
			'property'    => 'firstname',
			'type'        => 'text',
			'label'       => 'First Name',
			'description' => 'Your first name',
			'maxLength'   => 40,
			'required'    => true
		];
		$fields[] = [
			'property'    => 'middlename',
			'type'        => 'text',
			'label'       => 'Middle Initial',
			'description' => 'Your middle initial',
			'maxLength'   => 40,
			'required'    => false
		];

		$fields[] = [
			'property'    => 'lastname',
			'type'        => 'text',
			'label'       => 'Last Name',
			'description' => 'Your last name',
			'maxLength'   => 40,
			'required'    => true
		];
		$fields[] = [
			'property'    => 'address',
			'type'        => 'text',
			'label'       => 'Mailing Address',
			'description' => 'Mailing Address',
			'maxLength'   => 128,
			'required'    => true
		];
		$fields[] = [
			'property'    => 'apartmentnumber',
			'type'        => 'text',
			'label'       => 'Apartment Number',
			'description' => 'Apartment Number',
			'maxLength'   => 10,
			'required'    => false
		];
		$fields[] = [
			'property'    => 'city',
			'type'        => 'text',
			'label'       => 'City',
			'description' => 'City',
			'maxLength'   => 48,
			'required'    => true
		];
		$fields[] = [
			'property'    => 'state',
			'type'        => 'text',
			'label'       => 'State',
			'description' => 'State',
			'maxLength'   => 2,
			'required'    => true,
			'default'     => 'CA'
		];
		$fields[] = [
			'property'    => 'zip',
			'type'        => 'text',
			'label'       => 'Zip Code',
			'description' => 'Zip Code',
			'maxLength'   => 32,
			'required'    => true
		];
		// require phone for folsom
		if ($library->subdomain == "folsom"){
			$fields[] = [
				'property'    => 'primaryphone',
				'type'        => 'text',
				'label'       => 'Phone (xxx-xxx-xxxx)',
				'description' => 'Phone',
				'maxLength'   => 128,
				'required'    => true
			];
		}else{
			$fields[] = [
				'property'    => 'primaryphone',
				'type'        => 'text',
				'label'       => 'Phone (xxx-xxx-xxxx)',
				'description' => 'Phone',
				'maxLength'   => 128,
				'required'    => false
			];
		}
		// require email for folsom
		if ($library->subdomain == "folsom"){
			$fields[] = [
				'property'    => 'email',
				'type'        => 'email',
				'label'       => 'E-Mail',
				'description' => 'E-Mail',
				'maxLength'   => 128,
				'required'    => true
			];
		}else{
			$fields[] = [
				'property'    => 'email',
				'type'        => 'email',
				'label'       => 'E-Mail',
				'description' => 'E-Mail',
				'maxLength'   => 128,
				'required'    => false
			];
		}
		$fields[] = [
			'property'    => 'guardianFirstName',
			'type'        => 'text',
			'label'       => 'Parent/Guardian First Name',
			'description' => 'Your parent\'s or guardian\'s first name',
			'maxLength'   => 40,
			'required'    => false
		];
		$fields[] = [
			'property'    => 'guardianLastName',
			'type'        => 'text',
			'label'       => 'Parent/Guardian Last Name',
			'description' => 'Your parent\'s or guardian\'s last name',
			'maxLength'   => 40,
			'required'    => false
		];
		//These two fields will be made required by javascript in the template

		$fields[] = [
			'property'    => 'pin',
			'type'        => 'pin',
			'label'       => 'Pin',
			'description' => 'Your desired pin',
			/*'maxLength' => 4, 'size' => 4,*/
			'required'    => true
		];
		$fields[] = [
			'property'    => 'pinconfirm',
			'type'        => 'pin',
			'label'       => 'Confirm ' . translate('PIN'),
			'description' => 'Please confirm your ' . translate('pin') . '.',
			/*'maxLength' => 4, 'size' => 4,*/
			'required'    => true
		];

		return $fields;
	}

	/**
	 * At this time, Marmot is the only site that uses this.

	 * Legacy Screen Scraping to get holding_record information for periodicals. (This may require the Serials module.)
	 *
	 * @param $recordId
	 * @param $checkInGridId
	 * @return array
	 * @throws ErrorException
	 */
	function getCheckInGrid($recordId, $checkInGridId){
		//Issue summaries are loaded from the main record page.
		$sourceAndId = new \SourceAndId($recordId);

		$id_         = $this->getShortId($sourceAndId->getRecordId());
		$host        = $this->accountProfile->vendorOpacUrl;
		$branchScope = $this->getLibrarySierraScope();
		$url         = $host . "/search~S{$branchScope}/.b" . $id_ . "/.b" . $id_ . "/1,1,1,B/$checkInGridId&FF=1,0,";
		$c           = new Curl();
		$headers     = [
			'Accept'          => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
			'Cache-Control'   => 'max-age=0',
			'Connection'      => 'keep-alive',
			'Accept-Charset'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Accept-Language' => 'en-us,en;q=0.5',
			'User-Agent'      => 'Pika'
		];
		$c->setHeaders($headers);

		$cookie   = @tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		$result = $c->get($url);

		if ($c->isError()){
			return array();
		}

		//Extract the actual table
		$checkInData = array();
		if (preg_match('/<table  class="checkinCardTable">(.*?)<\/table>/s', $result, $matches)){
			$checkInTable = trim($matches[1]);

			//Extract each item from the grid.
			preg_match_all('/.*?<td valign="top" class="(.*?)">(.*?)<\/td>/s', $checkInTable, $checkInCellMatch, PREG_SET_ORDER);
			for ($matchi = 0;$matchi < count($checkInCellMatch);$matchi++){
				$cellData             = trim($checkInCellMatch[$matchi][2]);
				$checkInCell          = array();
				$checkInCell['class'] = $checkInCellMatch[$matchi][1];
				//Load issue date, status, date received, issue number, copies received
				if (preg_match('/(.*?)<br\\s*\/?>.*?<span class="(?:.*?)">(.*?)<\/span>.*?on (\\d{1,2}-\\d{1,2}-\\d{1,2})<br\\s*\/?>(.*?)(?:<!-- copies --> \\((\\d+) copy\\))?<br\\s*\/?>/s', $cellData, $matches)){
					$checkInCell['issueDate']   = trim($matches[1]);
					$checkInCell['status']      = trim($matches[2]);
					$checkInCell['statusDate']  = trim($matches[3]);
					$checkInCell['issueNumber'] = trim($matches[4]);
					if (isset($matches[5])){
						$checkInCell['copies'] = trim($matches[5]);
					}
				}
				$checkInData[] = $checkInCell;
			}
		}
		return $checkInData;
	}

	/**
	 * At this time, Marmot is the only site that uses this.
	 *
	 * Legacy Screen Scraping to get holding_record information for periodicals
	 *
	 * If there are issue summaries available, it will return them in an array.
	 * With holdings below them.
	 *
	 * If there are no issue summaries, null will be returned from the summary.
	 *
	 * @param string $recordId
	 * @return array|null - array or null
	 * @throws ErrorException
	 */
	public function getIssueSummaries($recordId){
		$scope    = $this->getLibrarySierraScope(true); // Use library scope if searching is restricted to the library
		$id_      = $this->getShortId($recordId);
		$host     = $this->accountProfile->vendorOpacUrl;
		$c        = new Curl();
		$headers  = [
			'Accept'          => 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
			'Cache-Control'   => 'max-age=0',
			'Connection'      => 'keep-alive',
			'Accept-Charset'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Accept-Language' => 'en-us,en;q=0.5',
			'User-Agent'      => 'Pika'
		];
		$cookie   = @tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setHeaders($headers);
		$c->setOpts($curlOpts);

		//Legacy process would load this page first. The results weren't used, and it doesn't appear required to read the URL further down
//		$url                       = $host . "/search~S{$scope}/." . $id_ . "/." . $id_ . "/1,1,1,B/holdings~" . $id_;
//		$issuesSummaryHoldingsInfo = $c->get($url);  // Not used any where
//		if ($c->isError()){
//			return null;
//		}

		$url                       = $host . "/search~S{$scope}/." . $id_ . "/." . $id_ . "/1,1,1,B/frameset~" . $id_;
		$issuesSummaryFrameSetInfo = $c->get($url);
		if ($c->isError()){
			return null;
		}

		if (preg_match('/class\\s*=\\s*\\"bibHoldings\\"/s', $issuesSummaryFrameSetInfo)){
			//There are issue summaries available
			//Extract the table with the holdings
			$issueSummaries = [];
			$matches        = [];
			if (preg_match('/<table\\s.*?class=\\"bibHoldings\\">(.*?)<\/table>/s', $issuesSummaryFrameSetInfo, $matches)){
				$issueSummaryTable = trim($matches[1]);
				//Each holdingSummary begins with a holdingsDivider statement
				$summaryMatches = explode('<tr><td colspan="2"><hr  class="holdingsDivider" /></td></tr>', $issueSummaryTable);
				if (count($summaryMatches) > 1){
					//Process each match independently
					foreach ($summaryMatches as $summaryData){
						$summaryData = trim($summaryData);
						if (strlen($summaryData) > 0){
							//Get each line within the summary
							$issueSummary         = [];
							$issueSummary['type'] = 'issueSummary';
							$summaryLines         = [];
							preg_match_all('/<tr\\s*>(.*?)<\/tr>/s', $summaryData, $summaryLines, PREG_SET_ORDER);
							for ($matchi = 0;$matchi < count($summaryLines);$matchi++){
								$summaryLine = trim(str_replace('&nbsp;', ' ', $summaryLines[$matchi][1]));
								$summaryCols = [];
								if (preg_match('/<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>/s', $summaryLine, $summaryCols)){
									$labelOriginal = $label = trim($summaryCols[1]);
									$value = trim(strip_tags($summaryCols[2]));
									//Check to see if this has a link to a check-in grid.
									if (preg_match('/.*?<a href="(.*?)">.*/s', $label, $linkData)){
										//Parse the check-in id
										$checkInLink = $linkData[1];
										if (preg_match('/\/search~S\\d+\\?\/.*?\/.*?\/.*?\/(.*?)&.*/', $checkInLink, $checkInGridInfo)){
											$issueSummary['checkInGridId'] = $checkInGridInfo[1];
										}
									}
									//Convert to camel case
									$label = lcfirst(preg_replace('/[^\\w]/', '', strip_tags($label)));
									if ($label == 'location'){
										//Try to trim the courier code if any
										if (preg_match('/(.*?)\\sC\\d{3}\\w{0,2}$/', $value, $locationParts)){
											$value = $locationParts[1];
										}
									}elseif ($label == 'holdings'){
										//Change the label to avoid conflicts with actual holdings
										$label = 'holdingStatement';
									}
									$issueSummary[$label] = $value;
								}
							}
							$issueSummaries[$issueSummary['location'] . count($issueSummaries)] = $issueSummary;
						}
					}
				}
			}

			return $issueSummaries;
		}
		return null;
	}


	/**
	 * Classic OPAC scope for legacy screen scraping calls
	 * @param bool $checkLibraryRestrictions  Whether or not to condition the use of Sierra OPAC scope by the library setting $restrictSearchByLibrary;
	 * @return mixed|string
	 */
	protected function getLibrarySierraScope($checkLibraryRestrictions = false){

		//Load the holding label for the branch where the user is physically.
		$searchLocation = Location::getSearchLocation();
		if (!empty($searchLocation->scope)){
			return $searchLocation->scope;
		}

		$searchLibrary = Library::getSearchLibrary();
		if (!empty($searchLibrary->scope)){
			if (!$checkLibraryRestrictions || $searchLibrary->restrictSearchByLibrary){
				return $searchLibrary->scope;
			}
		}
		return $this->getDefaultSierraScope();
	}

	protected function getDefaultSierraScope(){
		global $configArray;
		return $configArray['OPAC']['defaultScope'] ?? '93';
	}
	/**
	 * Taken from the class MarcRecord method getShortId.
	 *
	 * @param string $longId III record Id with a trailing check digit included
	 * @return mixed|string   the initial dot & the trailing check digit removed
	 */
	protected static function getShortId($longId){
		$shortId = str_replace('.b', 'b', $longId);
		$shortId = substr($shortId, 0, strlen($shortId) - 1);
		return $shortId;
	}

}
