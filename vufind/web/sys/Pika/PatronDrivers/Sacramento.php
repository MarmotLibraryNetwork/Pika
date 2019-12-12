<?php
/**
 * Sierra API functions specific to Sacramento Public Library.
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 * Date: 5/13/2019
 */
namespace Pika\PatronDrivers;

use Curl\Curl;
use DateInterval;
use DateTime;
use InvalidArgumentException;

class Sacramento extends Sierra
{
	public function __construct($accountProfile)
	{
		parent::__construct($accountProfile);
		$this->logger->info('Using driver: Pika\PatronDrivers\Sacramento');
	}

	public function updateSms($patron) {
		$patronId = $this->getPatronId($patron);

		$cc = new Curl();
		// base url for following calls
		$vendorOpacUrl = $this->accountProfile->vendorOpacUrl;

		$headers = [
		 "Accept"         => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
		 "Cache-Control"  => "max-age=0",
		 "Connection"     => "keep-alive",
		 "Accept-Charset" => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
		 "Accept-Language"=> "en-us,en;q=0.5",
		 "User-Agent"     => "Pika"
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
		if($this->accountProfile->loginConfiguration == "barcode_pin") {
			$postData = [
			 'code' => $patron->cat_username,
			 'pin'  => $patron->cat_password
			];
		} else {
			$postData = [
			 'name' => $patron->cat_username,
			 'code' => $patron->cat_password
			];
		}
		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r = $cc->post($loginUrl, $postData);

		if($cc->isError()) {
			$cc->close();
			return false;
		}

		if(!stristr($r, $patron->cat_username)) {
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
			$phones[] = ['number'=>$mobileNumber, 'type'=>'o'];
		} else {
			$phones[] = ['number'=>' ', 'type'=>'o']; #todo: does api accept empty for updates?
		}
		$apiParams = ['phones' => $phones];
		// to note: calling _doRequest will create a new instance of Curl so we won't destroy the cookie jar
		$operation = 'patrons/'.$patronId;
		$res = $this->_doRequest($operation, $apiParams, 'PUT');
		// even if this request fails we'll still try to opt out of sms messages
		if(!$res) {
			$this->logger->warn("Unable to update patron mobile phone.", ["message"=>$this->apiLastError]);
			$errors[] = "Could not remove your mobile phone number.";
		}

		// remove patron object from cache
		$this->memCache->delete('patron_'.$patron->barcode.'_patron');
		// next update sms notification option
		if(isset($_POST['smsNotices']) && $_POST['smsNotices'] == 'on') {
			$params = ['optin' => 'on'];
		} else {
			$params = ['optin' => 'off'];
		}
		$scope = isset($this->configArray['OPAC']['defaultScope']) ? $this->configArray['OPAC']['defaultScope'] : '93';
		$optUrl = $vendorOpacUrl . "/patroninfo~S". $scope. "/" . $patronId . "/modpinfo";

		$cc->setUrl($optUrl);
		$r = $cc->post($params);

		if($cc->isError()) {
			$cc->close();
			return false;
		}

		if (stristr($r, 'Patron information updated')) {
			$success = true;
		} else {
			$success = false;
		}

		$cc->close();
		return ['SMS preferences updates.'];
	}

	public function hasUsernameField(){
		return true;
	}
	
	public function selfRegister() {
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
		$ddepartment = $lastNameFourLetters . $firstNameOneLetter . $birthMonth . $birthDay; //var field d
		$params['varFields'][] = ["fieldTag" => "d",
		                          "content"  => $ddepartment];

		foreach ($_POST as $key=>$val) {
			switch ($key) {
				case 'email':
					$val = trim($val);
					$params['emails'][] = $val;
					break;
				case 'primaryphone':
					$val = trim($val);
					$params['phones'][] = ['number'=>$val, 'type'=>'t'];
					break;
				case 'altphone':
					$val = trim($val);
					$params['phones'][] = ['number'=>$val, 'type'=>'p'];
					break;
				case 'birthdate':
					if(isset($val) && $val != '') {
						// don't let registration occur if birthdate less than 30 days ago.
						$birthDate = DateTime::createFromFormat('m-d-Y', $val);
						$todayDate = new DateTime();
						$dateDiff  = $birthDate->diff($todayDate);
						$days      = (integer)$dateDiff->days;
						if($days < 30) {
							return ['success'=>false, 'barcode'=>''];
						}
						$params['birthDate'] = $birthDate->format('Y-m-d');
					} else {
						return ['success'=>false, 'barcode'=>''];
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
		$params['fixedFields']["158"] = ["label" => "PAT AGENCY",
		                                 "value" => $library->selfRegistrationAgencyCode];
		// notice preference -- default to z
		$params['fixedFields']['268'] = ["label" => "Notice Preference",
		                                 "value" => 'z'];
		// expiration date
		$interval = 'P'.$library->selfRegistrationDaysUntilExpire.'D';
		$expireDate = new DateTime();
		$expireDate->add(new DateInterval($interval));
		$params['expirationDate'] = $expireDate->format('Y-m-d');

		// names -- standard is Last, First Middle for sacramento
		$name  = trim($_POST['lastname']) . ", ";
		$name .= trim($_POST['firstname']);
		if(!empty($_POST['middlename'])) {
			$name .= ' ' . trim($_POST['middlename']);
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
		if($this->accountProfile->loginConfiguration == "barcode_pin") {
			$pin = trim($_POST['pin']);
			$pinConfirm = trim($_POST['pinconfirm']);

			if(!($pin == $pinConfirm)) {
				return ['success'=>false, 'barcode'=>''];
			} else {
				$params['pin'] = $pin;
			}
		}

		$this->logger->debug('Self registering patron', ['params'=>$params]);
		$operation = "patrons/";
		$r = parent::_doRequest($operation, $params, "POST");

		if(!$r) {
			$this->logger->warning('Failed to self register patron');
			return ['success'=>false, 'barcode'=>''];
		}
		$this->logger->debug('Success self registering patron');
		return ['success' => true, 'barcode' => $barcode];

	}


	public function getSelfRegistrationFields()
	{
		global $library;
		$fields = array();
		if ($library && $library->promptForBirthDateInSelfReg) {
			$fields[] = array(
				'property' => 'birthdate',
				'type' => 'date',
				'label' => 'Date of Birth (MM-DD-YYYY)',
				'description' => 'Date of birth',
				'maxLength' => 10,
				'required' => true
			);
		}
		$fields[] = array(
			'property' => 'firstname',
			'type' => 'text',
			'label' => 'First Name',
			'description' => 'Your first name',
			'maxLength' => 40,
			'required' => true
		);
		$fields[] = array(
			'property' => 'middlename',
			'type' => 'text',
			'label' => 'Middle Initial',
			'description' => 'Your middle initial',
			'maxLength' => 40,
			'required' => false
		);

		$fields[] = array(
			'property' => 'lastname',
			'type' => 'text',
			'label' => 'Last Name',
			'description' => 'Your last name',
			'maxLength' => 40,
			'required' => true
		);
		$fields[] = array(
			'property' => 'address',
			'type' => 'text',
			'label' => 'Mailing Address',
			'description' => 'Mailing Address',
			'maxLength' => 128,
			'required' => true
		);
		$fields[] = array(
			'property' => 'apartmentnumber',
			'type' => 'text',
			'label' => 'Apartment Number',
			'description' => 'Apartment Number',
			'maxLength' => 10,
			'required' => false
		);
		$fields[] = array(
			'property' => 'city',
			'type' => 'text',
			'label' => 'City',
			'description' => 'City',
			'maxLength' => 48,
			'required' => true
		);
		$fields[] = array(
			'property' => 'state',
			'type' => 'text',
			'label' => 'State',
			'description' => 'State',
			'maxLength' => 2,
			'required' => true,
			'default' => 'CA'
		);
		$fields[] = array(
			'property' => 'zip',
			'type' => 'text',
			'label' => 'Zip Code',
			'description' => 'Zip Code',
			'maxLength' => 32,
			'required' => true
		);
		$fields[] = array(
			'property' => 'primaryphone',
			'type' => 'text',
			'label' => 'Phone (xxx-xxx-xxxx)',
			'description' => 'Phone',
			'maxLength' => 128,
			'required' => false
		);
		$fields[] = array(
			'property' => 'email',
			'type' => 'email',
			'label' => 'E-Mail',
			'description' => 'E-Mail',
			'maxLength' => 128,
			'required' => false
		);

		$fields[] = array(
			'property' => 'guardianFirstName',
			'type' => 'text',
			'label' => 'Parent/Guardian First Name',
			'description' => 'Your parent\'s or guardian\'s first name',
			'maxLength' => 40,
			'required' => false
		);
		$fields[] = array(
			'property' => 'guardianLastName',
			'type' => 'text',
			'label' => 'Parent/Guardian Last Name',
			'description' => 'Your parent\'s or guardian\'s last name',
			'maxLength' => 40,
			'required' => false
		);
		//These two fields will be made required by javascript in the template

		$fields[] = array(
			'property' => 'pin',
			'type' => 'pin',
			'label' => 'Pin',
			'description' => 'Your desired pin',
			/*'maxLength' => 4, 'size' => 4,*/
			'required' => true
		);
		$fields[] = array(
			'property' => 'pinconfirm',
			'type' => 'pin',
			'label' => 'Confirm Pin',
			'description' => 'Re-type your desired pin',
			/*'maxLength' => 4, 'size' => 4,*/
			'required' => true
		);

		return $fields;
	}
}
