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
 * @author   Chris Froese
 * Date: 12/10/19
 *
 */
namespace Pika\PatronDrivers;

use Location;

class Flatirons extends Sierra
{
	public function getSelfRegistrationFields(){
		global $library;

		$libSubDomain = strtolower($library->subdomain);
		// get library code
		$location            = new Location();
		$location->libraryId = $library->libraryId;
		$location->find(true);
		if (!$location){
			return ['success' => false, 'barcode' => ''];
		}
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
			'property'     => 'middlename',
			'type'         => 'text',
			'label'        => 'Middle name',
			'description'  => 'Your middle name or initial',
			'maxLength'    => 30,
			'required'     => false,
			'autocomplete' => 'additional-name',
		];
		$fields[] = [
			'property'     => 'lastname',
			'type'         => 'text',
			'label'        => 'Last name',
			'description'  => 'Your last name (surname)',
			'maxLength'    => 30,
			'required'     => true,
			'autocomplete' => 'family-name',
		];
		// if library would like a birthdate
		if ($library && $library->promptForBirthDateInSelfReg){
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
			'property'     => 'address',
			'type'         => 'text',
			'label'        => 'Mailing Address',
			'description'  => 'Mailing Address',
			'maxLength'    => 128,
			'required'     => true,
			'autocomplete' => 'shipping street-address',
		];
		$fields[] = [
			'property'     => 'city',
			'type'         => 'text',
			'label'        => 'City',
			'description'  => 'City',
			'maxLength'    => 48,
			'required'     => true,
			'autocomplete' => 'address-level2',
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
			'maxLength'    => 32,
			'required'     => true,
			'autocomplete' => 'postal-code',
		];
		$fields[] = [
			'property'     => 'primaryphone',
			'type'         => 'text',
			'label'        => 'Phone Number',
			'description'  => 'Phone Number',
			'maxLength'    => 16,
			'required'     => true,
			'autocomplete' => 'tel-national',
		];
		$fields[] = [
			'property'     => 'email',
			'type'         => 'email',
			'label'        => 'E-Mail',
			'description'  => 'E-Mail',
			'maxLength'    => 128,
			'required'     => in_array($libSubDomain, ['boulder', 'longmont']), // Required for boulder and longmont
			'autocomplete' => 'email',
		];
		// Username and PIN
		// allow usernames?
		if ($this->hasUsernameField()){
			$fields[] = [
				'property'    => 'username',
				'type'        => 'text',
				'label'       => 'Username',
				'description' => 'Set an optional username.',
				'maxLength'   => 20,
				'required'    => false,
				'autocomplete' => 'username',
			];
		}
		// if library uses pins
		if ($this->accountProfile->usingPins()){
			$fields[] = [
				'property'    => 'pin',
				'type'        => 'pin',
				'label'       => translate('PIN'),
				'description' => 'Please set a ' . translate('pin') . '.',
				'maxLength'   => 10,
				'required'    => true
			];

			$fields[] = [
				'property'    => 'pinconfirm',
				'type'        => 'pin',
				'label'       => 'Confirm ' . translate('PIN'),
				'description' => 'Please confirm your ' . translate('pin') . '.',
//				'maxLength'   => 10,
				'required'    => true
			];
		}

		return $fields;
	}

	function selfRegister($extraSelfRegParams = false){
		global $library;
		$libSubDomain       = strtolower($library->subdomain);
		$extraSelfRegParams = [];
		// set boulder home location code
		if($libSubDomain == 'boulder') {
			$extraSelfRegParams['homeLibraryCode'] = 'bm';
			if(isset($_POST['homelibrarycode'])) {
				unset($_POST['homelibrarycode']);
			}
		}

		if ($libSubDomain == 'broomfield'){
			$this->capitalizeAllSelfRegistrationInputs();
		} else {
			// Capitalize Mailing address
			$_POST['address'] = strtoupper($_POST['address']);
			$_POST['city']    = strtoupper($_POST['city']);
			$_POST['state']   = strtoupper($_POST['state']);
			$_POST['zip']     = strtoupper($_POST['zip']);
		}

		$extraSelfRegParams['varFields'][] = ['fieldTag' => 'x', 'content'  => 'Created Online'];
		$extraSelfRegParams['pMessage']    = 'o';

		return parent::selfRegister($extraSelfRegParams);
	}
}
