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
	public function getSelfRegistrationFields()
	{
		global $library;
		// get library code
		$location            = new Location();
		$location->libraryId = $library->libraryId;
		$location->find(true);
		if(!$location) {
			return ['success'=>false, 'barcode'=>''];
		}
		$homeLibraryCode = $location->code;

		$fields = array();
		$fields[] = [
			'property' => 'homelibrarycode',
			'type'     => 'hidden',
			'default'  => $homeLibraryCode
		];
		$fields[] = array(
			'property' => 'firstName',
			'type' => 'text',
			'label' => 'First Name',
			'description' => 'Your first name',
			'maxLength' => 40,
			'required' => true
		);
		$fields[] = array(
			'property' => 'middlename',
			'type' => 'text',
			'label' => 'Middle Name',
			'description' => 'Your middle name',
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
			'property' => 'address',
			'type' => 'text',
			'label' => 'Mailing Address',
			'description' => 'Mailing Address',
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
			'maxLength' => 32,
			'required' => true
		);
		$fields[] = array(
			'property' => 'primaryphone',
			'type' => 'text',
			'label' => 'Phone Number',
			'description' => 'Phone Number',
			'maxLength' => 16,
			'required' => true
		);
		$fields[] = array(
			'property' => 'email',
			'type' => 'email',
			'label' => 'E-Mail',
			'description' => 'E-Mail',
			'maxLength' => 128,
			'required' => false
		);
		return $fields;
	}

	function selfRegister($extraSelfRegParams = false)
	{
		// Capitalize Mailing address
		$_REQUEST['address'] = strtoupper($_REQUEST['address']);
		$_REQUEST['city']    = strtoupper($_REQUEST['city']);
		$_REQUEST['state']   = strtoupper($_REQUEST['state']);
		$_REQUEST['zip']     = strtoupper($_REQUEST['zip']);

		$extraSelfRegParams                = [];
		$extraSelfRegParams['varFields'][] = ["fieldTag" => "x", "content"  => "Created Online"];
		$extraSelfRegParams['pMessage']    = 'o';

		return parent::selfRegister($extraSelfRegParams);
	}
}
