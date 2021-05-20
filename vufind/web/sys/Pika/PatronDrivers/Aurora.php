<?php

/*
 * Pika Discovery Layer
 * Copyright (C) 2021  Marmot Library Network
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

namespace Pika\PatronDrivers;

/**
 * Aurora.php
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 *
 */
class Aurora extends Sierra {


	public function __construct($accountProfile) {
		parent::__construct($accountProfile);
	}

	public function getSelfRegistrationFields(){
		$fields = parent::getSelfRegistrationFields();
		for ($i = 0;$i < count($fields);$i++){
			if ($fields[$i]['property'] == 'email'){
				$fields[$i]['required'] = true;
			}
		}
		return $fields;
	}

	public function selfRegister($extraSelfRegParams = false){
		$extraSelfRegParams = [];

		$extraSelfRegParams['patronCodes']['pcode1'] = 's';
		$extraSelfRegParams['patronCodes']['pcode3'] = 32;
		$extraSelfRegParams['fixedFields'] = ['268'=>["label" => "Notice Preference", "value" => 'z']];

		return parent::selfRegister($extraSelfRegParams);
	}
}