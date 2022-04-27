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

	public static function isSearchPhraseToIgnore(string $phrase){
		//Ignore numeric, spammy, complex or long searches
		return is_numeric($phrase)
			|| strlen($phrase) >= 256
			|| strpos($phrase, '(') !== false || strpos($phrase, ')') !== false
			|| preg_match('/http:|mailto:|https:/i', $phrase);
}

	function saveSearch($phrase, $numResults){
		//Don't bother to count things that didn't return results.
		if (empty($numResults) || SearchStatNew::isSearchPhraseToIgnore($phrase)){
			return;
		}

		$searchStat         = new SearchStatNew();
		$searchStat->phrase = strtolower(trim(str_replace("\t", '', $phrase)));
		if ($searchStat->find(true)){
			$searchStat->numSearches++;
			$searchStat->lastSearch = time();
			$searchStat->update();
		}else{
			$searchStat->numSearches = 1;
			$searchStat->lastSearch  = time();
			$searchStat->insert();
		}
	}

}
