<?php
/*
 * Copyright (C) 2021  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 3/17/2021
 *
 */


namespace Pika\PatronDrivers;

use Location;
use Pika\SierraPatronListOperations;

require_once ROOT_DIR . "/sys/Pika/PatronDrivers/Traits/SierraPatronListOperations.php";

class NorthernWaters extends Sierra {

	use SierraPatronListOperations {
		importListsFromIls as protected importListsFromIlsFromTrait;
	}


	public function getMyCheckouts($patron, $linkedAccount = false){
		$myCheckOuts = parent::getMyCheckouts($patron, $linkedAccount);
		foreach ($myCheckOuts as &$checkOut){
			if (!empty($checkOut['_callNumber']) && strpos($checkOut['_callNumber'], 'WISCAT') !== false){
				$title                  = trim(str_replace('WISCAT:', '', $checkOut['_callNumber']));
				$checkOut['title']      = $title;
				$checkOut['title_sort'] = $title;
				$checkOut['canrenew']   = false;
				unset($checkOut['groupedWorkId']);
				unset($checkOut['ratingData']);
				unset($checkOut['link']);
				unset($checkOut['format']);
			}
		}
		return $myCheckOuts;
	}

	function importListsFromIls(\User $patron){
		$this->classicListsRegex = '/<tr[^>]*?class="patFuncEntry"[^>]*?>.*?<a.*?listNum=(.*?)">(.*?)<\/a>.*?<td[^>]*class="patFuncDetails">(.*?)<\/td>.*?<\/tr>/si';
		// Regex to screen scrape Northern Waters' Sierra Classic Opac user lists

		return $this->importListsFromIlsFromTrait($patron);
	}

	public function getSelfRegistrationFields(){
		$fields = parent::getSelfRegistrationFields();

		if(isset($fields['success']) && $fields['success'] == false) {
			return $fields;
		}

		// get the valid home/pickup locations
		$l                        = new Location();
		$l->validHoldPickupBranch = '1';
		$l->find();
		if(!$l->N) {
			return ['success'=>false, 'barcode'=>''];
		}
		$l->orderBy('displayName');
		$homeLocations = $l->fetchAll('code', 'displayName');

		foreach ($fields as $field) {
			if ($field['property'] == 'homelibrarycode') {
				$field['values'] = $homeLocations;
			}
		}
		$fields[] = ['property'   => 'county',
		             'type'       => 'text',
		             'label'      => 'County of Residence',
		             'description'=> 'County of Residence',
		             'maxLength'  => 30,
		             'required'   => false];

		$fields[] = ['property'   => 'township',
		             'type'       => 'text',
		             'label'      => 'Township of Residence',
		             'description'=> 'Township of Residence',
		             'maxLength'  => 30,
		             'required'   => false];

		return $fields;
	}

	public function selfRegister($extraSelfRegParams = false)
	{
		$countyTownshipString = '';
		if (isset($_REQUEST['county']) && !empty($_REQUEST['county'])){
			$county = trim($_REQUEST['county']);
			$countyTownshipString .= "County: " . $county . "  ";
		}
		if (isset($_REQUEST['township']) && !empty($_REQUEST['township'])){
			$township = trim($_REQUEST['township']);
			$countyTownshipString .= "Township: " . $township;
		}
		$extraSelfRegParams['varFields'][] = ["fieldTag" => "x",
		                                      "content"  => $countyTownshipString];
		return parent::selfRegister($extraSelfRegParams);
	}
}