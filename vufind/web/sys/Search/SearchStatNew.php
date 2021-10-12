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

require_once 'DB/DataObject.php';

class SearchStatNew extends DB_DataObject {
	public $__table = 'search_stats_new';    // table name
	public $id;                      //int(11)
	public $phrase;                    //varchar(500)
	public $lastSearch;       //timestamp
	public $numSearches;      //int(16)

	function keys(){
		return ['id', 'phrase'];
	}

	private function isSearchPhraseToIgnore(string $phrase){
		//Ignore numeric, spammy, complex or long searches
		return is_numeric($phrase)
			|| strlen($phrase) >= 256
			|| strpos($phrase, '(') !== false || strpos($phrase, ')') !== false
			|| preg_match('/http:|mailto:|https:/i', $phrase);
}

	function getSearchSuggestions($phrase, $type){
		$phrase     = trim($phrase);
		//Don't bother getting suggestions for numeric, spammy, or long searches
		if ($this->isSearchPhraseToIgnore($phrase)){
			return [];
		}

		//Don't suggest things to users that will result in them not getting any results
		$results    = [];
		$searchStat = new SearchStatNew();
		$searchStat->whereAdd("MATCH(phrase) AGAINST ('" . $searchStat->escape($phrase) . "')");
		//$searchStat->orderBy("numSearches DESC");
		$searchStat->limit(0, 20);
		if ($searchStat->find()){
			while ($searchStat->fetch()){
				$searchStat->phrase = trim(str_replace('"', '', $searchStat->phrase));
				if ($searchStat->phrase != $phrase && !array_key_exists($searchStat->phrase, $results)){
					$results[str_pad($searchStat->numSearches, 10, '0', STR_PAD_LEFT) . $searchStat->phrase] =
						[
							'phrase'      => $searchStat->phrase,
							'numSearches' => $searchStat->numSearches,
							'numResults'  => 1
						];
				}
			}
		}else{
			//Try another search using like
			$searchStat = new SearchStatNew();
			//Don't suggest things to users that will result in them not getting any results
			$searchStat->whereAdd("phrase LIKE '" . $searchStat->escape($phrase, true) . "%'");
			$searchStat->orderBy("numSearches DESC");
			$searchStat->limit(0, 11);
			if ($searchStat->find()){
				while ($searchStat->fetch()){
					$searchStat->phrase = trim(str_replace('"', '', $searchStat->phrase));
					if ($this->phrase != $phrase && !array_key_exists($searchStat->phrase, $results)){
						$results[str_pad($searchStat->numSearches, 10, '0', STR_PAD_LEFT) . $searchStat->phrase] = array('phrase' => $searchStat->phrase, 'numSearches' => $searchStat->numSearches, 'numResults' => 1);
					}
				}
			}else{
				//Try another search using like

			}
		}
		return $results;
	}

	function saveSearch($phrase, $type = false, $numResults){
		//Don't bother to count things that didn't return results.
		if (empty($numResults)){
			return;
		}

		if ($this->isSearchPhraseToIgnore($phrase)){
			return;
		}

		$phrase             = str_replace("\t", '', $phrase);
		$searchStat         = new SearchStatNew();
		$searchStat->phrase = trim(strtolower($phrase));
		$isNew = true;
		if ($searchStat->find()){
			$searchStat->fetch();
			$searchStat->numSearches++;
			$isNew = false;
		}else{
			$searchStat->numSearches = 1;
		}
		$searchStat->lastSearch = time();
		if ($isNew){
			$searchStat->insert();
		}else{
			$searchStat->update();
		}
	}

}
