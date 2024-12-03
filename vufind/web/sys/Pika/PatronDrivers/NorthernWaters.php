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
 * @author: Pascal Brammeier
 * Date: 3/17/2021
 *
 */


namespace Pika\PatronDrivers;

use Location;
use Pika\SierraPatronListOperations;

require_once ROOT_DIR . '/sys/Pika/PatronDrivers/Traits/SierraPatronListOperations.php';

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

	/**
	 * Return all locations that are valid pick up locations
	 *
	 * @param \Library $libraryIgnored Parameter not used
	 * @return array
	 */
	protected function getSelfRegHomeLocations($libraryIgnored): array{
		$loc                        = new Location();
		$loc->validHoldPickupBranch = '1';
		$loc->orderBy('displayName');
		$loc->find();
		$homeLocations = $loc->fetchAll('code', 'displayName');
		return $homeLocations;
	}
	public function getSelfRegistrationFields(){
		$fields = parent::getSelfRegistrationFields();

		if (isset($fields['success']) && $fields['success'] == false){
			return $fields;
		}

		for ($i = 0;$i < count($fields);$i++){
			if ($fields[$i]['property'] == 'zip'){
				$result = array_splice($fields, ++$i, 0, [[
					'property'    => 'otheraddress',
					'type'        => 'text',
					'label' => '(If this is not your permanent address, please include your permanent address.)',
//					'label'       => 'Other Address ',
//					'description' => '(If this is not your permanent address, please include your permanent address.)',
//					'showDescription' => true,
					'maxLength'   => 40,
				]]);
				// increase index count before array insertion so that next round of the for loop isn't zip again.

			}elseif ($fields[$i]['property'] == 'altaddress'){
				//TODO: I'm not  finding any reference to altaddress for northern waters
				$fields[$i]['label']       = 'Address of Residence';
				$fields[$i]['description'] = 'Address of Residence';
			}
		}
		$fields[] = [
			'property'    => 'county',
			'type'        => 'text',
			'label'       => 'County of Residence',
			'description' => 'County of Residence',
			'maxLength'   => 30,
			'required'    => false
		];

		$fields[] = [
			'property'    => 'township',
			'type'        => 'text',
			'label'       => 'Township, Village or City of Residence',
			'description' => 'Township, Village or City of Residence',
			'maxLength'   => 30,
			'required'    => false
		];

		return $fields;
	}

	public function selfRegister($extraSelfRegParams = false){
		$countyTownshipString = '';
		if (!empty($_REQUEST['county'])){
			$county               = trim($_REQUEST['county']);
			$countyTownshipString .= "County: $county  ";
		}
		if (!empty($_REQUEST['township'])){
			$township             = trim($_REQUEST['township']);
			$countyTownshipString .= "Township: $township";
		}
		$extraSelfRegParams['varFields'][] = [
			'fieldTag' => 'x',
			'content'  => $countyTownshipString
		];
		$extraSelfRegParams['varFields'][] = [
			'fieldTag' => 'm',
			'content'  => 'Self-registered patron. Issue physical card, update record and verify residence to check out physical items.'
		];
		if (!empty($_REQUEST['otheraddress'])){
			$extraSelfRegParams['varFields'][] = [
				'fieldTag' => 'd',
				'content'  => trim($_REQUEST['otheraddress']),
			];

		}
		return parent::selfRegister($extraSelfRegParams);
	}
}