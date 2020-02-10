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
require_once ROOT_DIR . '/sys/Language/SpellingWord.php';
require_once ROOT_DIR . '/sys/Search/SearchStatNew.php';

class SearchSuggestions{
	function getCommonSearchesMySql($searchTerm, $searchType){
		$searchStat = new SearchStatNew();
		$suggestions = $searchStat->getSearchSuggestions( $searchTerm, $searchType);
		if (count ($suggestions) > 12){
			$suggestions = array_slice($suggestions, 0, 12);
		}
		return $suggestions;
	}

	function getSpellingSearches($searchTerm){
		//First check for things we don't want to load spelling suggestions for
		if (is_numeric($searchTerm)){
			return array();
		}
		if (strpos($searchTerm, '(') !== FALSE || strpos($searchTerm, ')') !== FALSE){
			return array();
		}
		if (preg_match('/http:|mailto:|https:/i', $searchTerm)){
			return array();
		}
		if (strlen($searchTerm) >= 256){
			return array();
		}

		require_once ROOT_DIR . '/sys/Language/SpellingWord.php';
		$spellingWord = new SpellingWord();
		$words = explode(" ", $searchTerm);
		$suggestions = array();
		foreach ($words as $word){
			//First check to see if the word is spelled properly
			$wordCheck = new SpellingWord();
			$wordCheck->word = $word;
			if (!$wordCheck->find()){
				//This word is not spelled properly, get suggestions for how it should be spelled
				$suggestionsSoFar = $suggestions;

				$wordSuggestions = $spellingWord->getSpellingSuggestions($word);
				foreach ($wordSuggestions as $suggestedWord){
					$newSearch = str_replace($word, $suggestedWord, $searchTerm);
					$searchInfo = new SearchStatNew();
					$searchInfo->phrase = $newSearch;
					$numSearches = 0;
					if ($searchInfo->find(true)){
						$numSearches = $searchInfo->numSearches;
					}
					$suggestions[str_pad($numSearches, 10, '0', STR_PAD_LEFT) . $newSearch] = array('phrase'=>$newSearch, 'numSearches'=>$numSearches, 'numResults'=>1);

					//Also try replacements on any suggestions we have so far
					foreach ($suggestionsSoFar as $tmpSearch){
						$newSearch = str_replace($word, $suggestedWord, $tmpSearch['phrase']);
						$searchInfo = new SearchStatNew();
						$searchInfo->phrase = $newSearch;
						$numSearches = 0;
						if ($searchInfo->find(true)){
							$numSearches = $searchInfo->numSearches;
						}
						$suggestions[str_pad($numSearches, 10, '0', STR_PAD_LEFT) . $newSearch] = array('phrase'=>$newSearch, 'numSearches'=>$numSearches, 'numResults'=>1);
					}
				}
			}
		}

		krsort($suggestions);

		//Return up to 10 results max
		if (count ($suggestions) > 12){
			$suggestions = array_slice($suggestions, 0, 12);
		}
		return $suggestions;
	}

	function getAllSuggestions($searchTerm, $searchType){
		global $timer;

		$searchSuggestions = $this->getCommonSearchesMySql($searchTerm, $searchType);
		$timer->logTime('Loaded common search suggestions');
		//ISN and Authors are not typically regular words
		if ($searchType != 'ISN' && $searchType != 'Author'){
			$spellingSearches = $this->getSpellingSearches($searchTerm);
			$timer->logTime('Loaded spelling suggestions');
			//Merge the two arrays together
			foreach($spellingSearches as $key => $term){
				if (!in_array($term, $searchSuggestions)){
					$searchSuggestions[$key] = $term;
				}
			}
		}
		krsort($searchSuggestions);

		return $searchSuggestions;
	}

}
