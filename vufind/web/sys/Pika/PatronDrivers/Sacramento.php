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
						$birthDate = DateTime::createFromFormat('d-m-Y', $val);
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
