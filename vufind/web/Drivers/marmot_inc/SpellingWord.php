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
 * Table Definition for spelling words
 */
require_once 'DB/DataObject.php';

class SpellingWord extends DB_DataObject
{
	public $__table = 'spelling_words';    // table name
	public $word;                    //varchar(50)
	public $commonality;             //int(11)
	 
	function keys() {
		return array('word');
	}

	function getSpellingSuggestions($word){
		if (empty($word)){
			return array();
		}
		//global $logger;
		//$logger->log("Loading spelling suggestions", PEAR_LOG_DEBUG);
		//Get suggestions, giving a little boost to words starting with what has been typed so far.
		$soundex = soundex($word);
		$query = "SELECT word, commonality FROM spelling_words WHERE soundex LIKE '{$soundex}%' OR word like '" . $this->escape($word, true) . "%' ORDER BY commonality, word LIMIT 10";
		$this->query($query);
		$suggestions = array();
		while ($this->fetch()){
			if ($this->word != $word){
				//$logger->log("Checking word {$this->word}", PEAR_LOG_DEBUG);
				$levenshteinDistance = levenshtein($this->word, $word);
				//$logger->log("  Levenshtein Distance is $levenshteinDistance", PEAR_LOG_DEBUG);
				similar_text($word, $this->word, $percent);
				//$logger->log("  Similarity is $percent", PEAR_LOG_DEBUG);
				$stringPosition = strpos($this->word, $word);
				//$logger->log("  String Position is $stringPosition", PEAR_LOG_DEBUG);
				if ($levenshteinDistance == 1 || $percent >= 75 || $stringPosition !== false){
					$suggestions[] = $this->word;
				}
			}
		}
		return $suggestions;
	}

}
