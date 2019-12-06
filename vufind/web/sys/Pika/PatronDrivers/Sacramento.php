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

class Sacramento extends Sierra
{
	public function __construct($accountProfile)
	{
		parent::__construct($accountProfile);
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
		$lastNameFourLetters = substr($_REQUEST['lastName'], 0, 4);
		$lastNameFourLetters = strtoupper($lastNameFourLetters);
		$lastNameFourLetters = str_pad($lastNameFourLetters, 4, "Z", STR_PAD_RIGHT);
		$firstNameOneLetter  = substr($_REQUEST['firstName'], 0, 1);
		$firstNameOneLetter  = strtoupper($firstNameOneLetter[0]);
		$birthDate           = trim($_REQUEST['birthDate']);
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
						$date                = DateTime::createFromFormat('d-m-Y', $val);
						$params['birthDate'] = $date->format('Y-m-d');
					}
					break;
			}
		}

		// sacramento defaults to this for self reg users
		$params['homeLibraryCode'] = 'yyy';
		// sacramento defaults for pcodes
		$params['patronCodes'] = [
			"pcode1" => "e",
			"pcode2" => "3",
			"pcode3" => 117,
			"pcode4" => 0
		];

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
		$params['addresses'][0]['lines'][] = trim($_POST['address']);
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
				'property' => 'birthDate',
				'type' => 'date',
				'label' => 'Date of Birth (MM-DD-YYYY)',
				'description' => 'Date of birth',
				'maxLength' => 10,
				'required' => true
			);
		}
		$fields[] = array(
			'property' => 'firstName',
			'type' => 'text',
			'label' => 'First Name',
			'description' => 'Your first name',
			'maxLength' => 40,
			'required' => true
		);
		$fields[] = array(
			'property' => 'middleName',
			'type' => 'text',
			'label' => 'Middle Initial',
			'description' => 'Your middle initial',
			'maxLength' => 40,
			'required' => false
		);

		$fields[] = array(
			'property' => 'lastName',
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
			'property' => 'apartmentNumber',
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
			'property' => 'phone',
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
			'property' => 'pin1',
			'type' => 'pin',
			'label' => 'Confirm Pin',
			'description' => 'Re-type your desired pin',
			/*'maxLength' => 4, 'size' => 4,*/
			'required' => true
		);

		return $fields;
	}
}
