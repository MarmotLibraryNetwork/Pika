<?php
/**
 * Copyright (C) 2020  Marmot Library Network
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
 * Table Definition for spelling words
 */
require_once 'DB/DataObject.php';

class SpellingWord extends DB_DataObject {
	public $__table = 'spelling_words';    // table name
	public $word;                    //varchar(50)
	public $commonality;             //int(11)

	const SIMILAR_TEXT_LIMIT = 70;   // Think percent, 1 to 100
	const LEVENSHTEIN_DISTANCE_LIMIT = 1;

	function keys(){
		return ['word'];
	}

	function getSpellingSuggestions($word){
		$suggestions = [];
		if (!empty($word)){

			//Get suggestions, giving a little boost to words starting with what has been typed so far.
			$soundex = soundex($word);
			$query   = "SELECT word, commonality FROM spelling_words WHERE soundex LIKE '{$soundex}%' OR word LIKE '" . $this->escape($word, true) . "%' ORDER BY commonality, word LIMIT 30";
			$this->query($query);
			while ($this->fetch()){
				if (strcasecmp($this->word, $word) != 0){
					$stringPosition      = stripos($this->word, $word);
					$levenshteinDistance = levenshtein($this->word, $word);
					similar_text($word, $this->word, $percent);
					if ($stringPosition !== false || $levenshteinDistance == self::LEVENSHTEIN_DISTANCE_LIMIT || $percent >= self::SIMILAR_TEXT_LIMIT){
						$suggestions[] = $this->word;
					}
					global $pikaLogger;
					$logger = $pikaLogger->withName(__CLASS__);
					$logger->debug("Spell-Checking word $word against $this->word", [
							"Levenshtein Distance is $levenshteinDistance",
							"Similarity is $percent",
							'Suggestion contains phrase at position ' . (($stringPosition) ? $stringPosition : 'false'),
						]
					);
				}
			}
		}
		return $suggestions;
	}

}
