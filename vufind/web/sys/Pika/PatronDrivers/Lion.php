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
 *
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 *
 *
 */
namespace Pika\PatronDrivers;

use MarcRecord;
use RecordDriverFactory;
use Location;
use Pika\SierraPatronListOperations;

require_once ROOT_DIR . "/sys/Pika/PatronDrivers/Traits/SierraPatronListOperations.php";

class Lion extends Sierra {

	use SierraPatronListOperations {
		importListsFromIls as importListsFromIlsOriginal;
	}

	public function __construct($accountProfile){
		parent::__construct($accountProfile);
		$this->logger->info('Using Pika\PatronDrivers\Lion.');
	}

	public function selfRegister($extraSelfRegParams = false){
		$extraSelfRegParams = [
			'pMessage' => 's',
		];
		return parent::selfRegister($extraSelfRegParams);
	}

	public function getSelfRegistrationFields(){
		global $library;
		// get library code
		$location            = new Location();
		$location->libraryId = $library->libraryId;
		$location->find(true);
		//if (!$location){
			//return ['success'=>false, 'barcode'=>''];
		//}
		$homeLibraryCode = $location->code;

		$fields   = [];
		$fields[] = [
			'property' => 'homelibrarycode',
			'type'     => 'hidden',
			'default'  => $homeLibraryCode
		];
		$fields[] = [
			'property'     => 'firstname',
			'type'         => 'text',
			'label'        => 'First name',
			'description'  => 'Your first name',
			'maxLength'    => 50,
			'required'     => true,
			'autocomplete' => 'given-name',
		];
		$fields[] = [
			'property'     => 'lastname',
			'type'         => 'text',
			'label'        => 'Last name',
			'description'  => 'Your last name (surname)',
			'maxLength'    => 40,
			'required'     => true,
			'autocomplete' => 'family-name',
		];
		// if library would like a birthdate
		if (isset($library) && $library->promptForBirthDateInSelfReg){
			$fields[] = [
				'property'     => 'birthdate',
				'type'         => 'date',
				'label'        => 'Date of Birth (MM-DD-YYYY)',
				'description'  => 'Date of birth',
				'maxLength'    => 10,
				'required'     => true,
				'autocomplete' => 'bday',
			];
		}
		$fields[] = [
			'property'     => 'email',
			'type'         => 'email',
			'label'        => 'E-Mail',
			'description'  => 'E-Mail (for confirmation, notices and newsletters)',
			'maxLength'    => 128,
			'required'     => true,
			'autocomplete' => 'email',
		];
		$fields[] = [
			'property'     => 'primaryphone',
			'type'         => 'tel',
			'label'        => 'Phone Number (XXX-XXX-XXXX)',
			'description'  => 'Phone Number',
			'maxLength'    => 12,
			'required'     => true,
			'autocomplete' => 'tel-national',
		];
		$fields[] = [
			'property'     => 'address',
			'type'         => 'text',
			'label'        => 'Address',
			'description'  => 'Address',
			'maxLength'    => 128,
			'required'     => true,
			'autocomplete' => 'street-address',
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
			'property'     => 'state',
			'type'         => 'text',
			'label'        => 'State',
			'description'  => 'State',
			'maxLength'    => 32,
			'required'     => true,
			'autocomplete' => 'address-level1',
		];
		$fields[] = [
			'property'     => 'zip',
			'type'         => 'text',
			'label'        => 'Zip Code',
			'description'  => 'Zip Code',
			'maxLength'    => 5,
			'required'     => true,
			'autocomplete' => 'postal-code',
		];

		return $fields;
	}

	function allowFreezingPendingHolds(){
		return true;
	}

	public function importListsFromIls(\User $patron){
		$this->classicListsRegex = '%<tr[^>]*?class="patFuncEntry"[^>]*?>.*?<input type="checkbox".*?<a.*?href="[^"]*?listNum=(\d+)".*?>(.*?)<\/a>.*?<td[^>]*class="patFuncDetails">(.*?)<\/td>.*?<\/tr>%si';
		return $this->importListsFromIlsOriginal($patron);

	}
}
