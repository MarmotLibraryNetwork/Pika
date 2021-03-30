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
				$title                  = str_replace('WISCAT:', '', $checkOut['_callNumber']);
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

}