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
		// Preset the self reg user's homelibarycode to the first location for the library
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
			if ($libSubDomain == 'broomfield'){
				$fields[] = [
					'property'        => 'guardianName',
					'type'            => 'text',
					'label'           => 'Name(s) of ALL Parent(s)/Legal Guardian(s)',
					'description'     => 'If under 16, please also complete parent/guardian field.',
					'showDescription' => true,
					'required'        => false,
					'autocomplete'    => 'off',
				];
			}
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
			'type'         => 'tel',
			'label'        => 'Phone Number (xxx-xxx-xxxx)',
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

		if ($libSubDomain == 'broomfield'){
//			$fields[] = [
//				'property'     => 'notices',
//				'type'         => 'enum',
//				'label'        => 'Notification Preference',
//				'values' => [
//					// Default labels for the sierra options
//					//'-' => 'No Preference',
//					'z' => 'E-mail',
//					't' => 'Text',
//					'p' => 'Phone',
//					//'a' => 'Mail',
//				]
//			];
//			$fields[] = [
//				'property'     => 'langPref',
//				'type'         => 'enum',
//				'label'        => 'Notification Language Preference',
//				'values' => [
//					'eng' => 'English',
//					'spi' => 'Spanish',
//				]
//			];
			$fields[] = [
				'property'     => 'textInSpanish',
				'type'         => 'checkbox',
				'label'        => 'Use Spanish for text message notices?',
				'boldTheLabel' => true,

			];

		}
		// Username and PIN
		// allow usernames?
		if ($this->hasUsernameField()){
			$fields[] = [
				'property'     => 'username',
				'type'         => 'text',
				'label'        => 'Username',
				'description'  => 'Set an optional username.',
				'maxLength'    => 20,
				'required'     => false,
				'autocomplete' => 'username',
			];
		}
		// if library uses pins
		if ($this->accountProfile->usingPins()){
			$PIN = translate('PIN');
			$fields[]  = [
				'property'        => 'pin',
				'type'            => 'pin',
				'label'           => $PIN,
				'description'     => "Please set a $PIN. <br>Your $PIN must be at least 4 characters long. Do not repeat a number or letter more than two times in a row (<kbd>1112</kbd> or <kbd>zeee</kbd> will not work). Do not repeat the same two numbers or letters in a row (<kbd>1212</kbd> or <kbd>bebe</kbd> will not work).",
				'showDescription' => true,
				'maxLength'       => 10,
				'required'        => true
			];

			$fields[] = [
				'property'                 => 'pinconfirm',
				'type'                     => 'pin',
				'label'                    => 'Confirm ' . $PIN,
				'description'              => "Please confirm your $PIN.",
				'showPasswordRequirements' => true,
				'required'                 => true
			];
		}

		return $fields;
	}

	function selfRegister($extraSelfRegParams = false){
		global $library;
		$libSubDomain       = strtolower($library->subdomain);
		$extraSelfRegParams = [];
		// set boulder home location code
		if ($libSubDomain == 'boulder'){
			$extraSelfRegParams['homeLibraryCode'] = 'bm';
			if (isset($_POST['homelibrarycode'])){
				unset($_POST['homelibrarycode']);
			}
		}

		if (in_array($libSubDomain, ['boulder', 'broomfield'])){
			$this->capitalizeAllSelfRegistrationInputs(/*[Any fields that shouldn't be capitalized]*/);
		} elseif ($libSubDomain != 'longmont') {
			// Capitalize Mailing address
			$_POST['address'] = strtoupper($_POST['address']);
			$_POST['city']    = strtoupper($_POST['city']);
			$_POST['state']   = strtoupper($_POST['state']);
			$_POST['zip']     = strtoupper($_POST['zip']);
		}

		if ($libSubDomain == 'broomfield'){
			// MLN2 uses variable field i = spi for shoutbomb to use spanish
			if (!empty($_REQUEST['textInSpanish'])){
				$extraSelfRegParams['varFields'][] = ['fieldTag' => 'i', 'content'  => 'spi'];
			}

			// Set this field after capitalization above
			if (!empty($_REQUEST['guardianName'])){
				$extraSelfRegParams['varFields'][] = ['fieldTag' => 'g', 'content'  => trim($_POST['guardianName'])];
			}
		}

		$extraSelfRegParams['varFields'][] = ['fieldTag' => 'x', 'content'  => 'Created Online'];
		$extraSelfRegParams['pMessage']    = 'o';

		return parent::selfRegister($extraSelfRegParams);
	}
}
