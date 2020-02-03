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
 * @author: Pascal Brammeier
 * Date: 8/26/2019
 *
 */


class AutoGraphicsShareIt {

	/**
	 * Load search results from Prospector using the encore interface.
	 * If $prospectorRecordDetails are provided, will sort the existing result to the
	 * top and tag it as being the record.
	 * @param string $searchTerms
	 * @param int $maxResults
	 * @return array|null
	 */
	function getTopSearchResults($searchTerms, $maxResults){
		return array(
			'firstRecord' => 0,
			'lastRecord'  => 0,
			'resultTotal' => 0,
			'records'     => array(),
		);
	}

	/**
	 * Generate a search URL for the ILL website
	 *
	 * @param string[] $searchTerms
	 * @return string
	 */
	function getSearchLink($searchTerms){
		global $configArray;
		$searchURL = $configArray['InterLibraryLoan']['ILLSearchURL'];

		if (is_array($searchTerms)){

			//TODO: This ignores the various index settings of an advanced search
			$search = "";
			foreach ($searchTerms as $term){
				if (strlen($search) > 0){
					$search .= ' ';
				}
				if (is_array($term) && isset($term['group'])){
					foreach ($term['group'] as $groupTerm){
						if (strlen($search) > 0){
							$search .= ' ';
						}
						if (isset($groupTerm['lookfor'])){
							$termValue = $groupTerm['lookfor'];
							$search    .= $termValue;
						}
					}
				}elseif (isset($term['lookfor'])){
					$termValue = $term['lookfor'];
					$search    .= $termValue;
				}

			}
		}else{
			$search = $searchTerms;
		}
		$search = str_replace('+', '%20', urlencode(str_replace('/', '', $search)));
		$searchURL = str_replace ('{SEARCHTERM}', $search, $searchURL);
		return $searchURL;

	}
}
