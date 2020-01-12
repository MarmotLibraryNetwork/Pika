<?php
/**
 *
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 *
 *
 */


namespace Pika\PatronDrivers;


use Location;
use Pika\SierraPatronListOperations;

require_once ROOT_DIR . "/sys/Pika/PatronDrivers/Traits/SierraPatronListOperations.php";

class Lion extends Sierra {

	use SierraPatronListOperations;

	public function __construct($accountProfile)
	{
		parent::__construct($accountProfile);
		$this->logger->info('Using Pika\PatronDrivers\Lion.');
	}

	public function getSelfRegistrationFields()
	{
		global $library;
		// get library code
		$location            = new Location();
		$location->libraryId = $library->libraryId;
		$location->find(true);
		if(!$location) {
			//return ['success'=>false, 'barcode'=>''];
		}
		$homeLibraryCode = $location->code;

		$fields = array();
		$fields[] = [
			'property' => 'homelibrarycode',
			'type'     => 'hidden',
			'default'  => $homeLibraryCode
		];
		$fields[] = array(
			'property' => 'firstname',
			'type' => 'text',
			'label' => 'First Name',
			'description' => 'Your first name',
			'maxLength' => 40,
			'required' => true
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
			'property' => 'email',
			'type' => 'email',
			'label' => 'E-Mail',
			'description' => 'E-Mail (for confirmation, notices and newsletters)',
			'maxLength' => 128,
			'required' => true
		);
		$fields[] = array(
			'property' => 'primaryphone',
			'type' => 'text',
			'label' => 'Phone Number',
			'description' => 'Phone Number',
			'maxLength' => 12,
			'required' => true
		);
		$fields[] = array(
			'property' => 'address',
			'type' => 'text',
			'label' => 'Address',
			'description' => 'Address',
			'maxLength' => 128,
			'required' => true
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
			'maxLength' => 32,
			'required' => true
		);
		$fields[] = array(
			'property' => 'zip',
			'type' => 'text',
			'label' => 'Zip Code',
			'description' => 'Zip Code',
			'maxLength' => 5,
			'required' => true
		);
		return $fields;
	}

	function allowFreezingPendingHolds(){
		return true;
	}

}
