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

const SUGGESTION_LIMIT = 12;

class SearchSuggestions {


	static array $disallowedSearchTypesForTermReplacement = [
		'ISN',
		'Author'
	];


	static function getCommonSearchesMySql($searchTerm, bool $sortByNumSearches = true){
		$suggestions = self::getSearchSuggestions($searchTerm, $sortByNumSearches);
		if (count($suggestions) > SUGGESTION_LIMIT){
			$suggestions = array_slice($suggestions, 0, SUGGESTION_LIMIT);
		}
		return $suggestions;
	}

	static function getSearchSuggestions($phrase, bool $sortByNumSearches){
		$phrase = trim($phrase);
		//Don't bother getting suggestions for numeric, spammy, or long searches
		if (SearchStatNew::isSearchPhraseToIgnore($phrase)){
			return [];
		}

		$suggestions = [];
		$searchStat  = new SearchStatNew();
		$searchStat->whereAdd("MATCH(phrase) AGAINST ('" . $searchStat->escape($phrase) . "')");
		//$searchStat->orderBy("numSearches DESC");
		$searchStat->limit(0, 20);
		if ($searchStat->find()){
			self::getResults($searchStat, $phrase, $suggestions, $sortByNumSearches);
		}else{
			//Try another search using like
			$searchStat = new SearchStatNew();
			$searchStat->whereAdd("phrase LIKE '" . $searchStat->escape($phrase, true) . "%'");
			$searchStat->orderBy("numSearches DESC");
			$searchStat->limit(0, SUGGESTION_LIMIT);
			if ($searchStat->find()){
				self::getResults($searchStat, $phrase, $suggestions, $sortByNumSearches);
			}
		}
		return $suggestions;
	}

	/**
	 * @param SearchStatNew $searchStat
	 * @param string $phrase
	 * @param array $results
	 * @param bool $sortByNumSearches
	 */
	private static function getResults(SearchStatNew $searchStat, string $phrase, array &$results, bool $sortByNumSearches): void{
		while ($searchStat->fetch()){
			$cleanedPhrase = trim(str_replace('"', '', $searchStat->phrase));
			if ($cleanedPhrase != $phrase && !array_key_exists($cleanedPhrase, $results)){
				$sortKey                                                 = str_pad($searchStat->numSearches, 10, '0', STR_PAD_LEFT) . $cleanedPhrase;
				$results[$sortByNumSearches ? $sortKey : $cleanedPhrase] = [
					'phrase'      => $cleanedPhrase,
					'numSearches' => $searchStat->numSearches,
					'sortKey'     => $sortKey,
					'numResults'  => 1
				];
			}
		}
	}


	/**
	 * @param string $searchTerm
	 * @param bool $sortByNumSearches
	 * @return array
	 */
	static function getSpellingSearches(string $searchTerm, bool $sortByNumSearches = true){
		//First check for things we don't want to load spelling suggestions for
		if (SearchStatNew::isSearchPhraseToIgnore($searchTerm)){
			return [];
		}

		$spellingWord = new SpellingWord();
		$words        = explode(' ', $searchTerm);
		$suggestions  = [];
		foreach ($words as $word){
			//First check to see if the word is spelled properly
			$wordCheck       = new SpellingWord();
			$wordCheck->word = $word;
			if (!$wordCheck->find()){
				//This word is not spelled properly, get suggestions for how it should be spelled
				$suggestionsSoFar = $suggestions;

				$wordSuggestions = $spellingWord->getSpellingSuggestions($word); // (Use a separate object from $wordCheck so queries don't get mixed up)
				foreach ($wordSuggestions as $suggestedWord){
					$newSearchTerm = str_replace($word, $suggestedWord, $searchTerm);
					self::fetchSearchStatForSpellingSuggestion($newSearchTerm, $suggestions, $sortByNumSearches);

					//Also try replacements on any suggestions we have so far
					foreach ($suggestionsSoFar as $tmpSearch){
						$newSearchTerm = str_replace($word, $suggestedWord, $tmpSearch['phrase']);
						self::fetchSearchStatForSpellingSuggestion($newSearchTerm, $suggestions, $sortByNumSearches);
					}
				}
			}
		}

		if (!empty($suggestions)){
			if ($sortByNumSearches){
				krsort($suggestions);
//				$array = [];
//				foreach ($suggestions as $suggestion){
//					$array[$suggestion['sortKey']] = $suggestion;
//				}
//				krsort($array);
//				$suggestions = $array;
			}

			//Return up to 12 results max
			if (count($suggestions) > SUGGESTION_LIMIT){
				$suggestions = array_slice($suggestions, 0, SUGGESTION_LIMIT);
			}
		}
		return $suggestions;
	}

	static function getAllSuggestions($searchTerm, $searchType){
		global $timer;

		$searchSuggestions = self::getCommonSearchesMySql($searchTerm, false);
		$timer->logTime('Loaded common search suggestions');
		//ISN and Authors are not typically regular words
		if (!in_array($searchType, self::$disallowedSearchTypesForTermReplacement)){
			$spellingSearches = self::getSpellingSearches($searchTerm ,false);
			$timer->logTime('Loaded spelling suggestions');
			//Merge the two arrays together
			foreach ($spellingSearches as $key => $array){
				if (!array_key_exists($key, $searchSuggestions)){
					$searchSuggestions[$key] = $array;
				}
			}
		}
		if (!empty($searchSuggestions)){
			$array = [];
			foreach ($searchSuggestions as $suggestion){
				$array[$suggestion['sortKey']] = $suggestion;
			}
			krsort($array);
			$searchSuggestions = $array;
		}
		return $searchSuggestions;
	}

	/**
	 * @param string $newSearchTerm
	 * @param array $suggestions
	 * @param bool $sortByNumSearches
	 */
	private static function fetchSearchStatForSpellingSuggestion(string $newSearchTerm, array &$suggestions, bool $sortByNumSearches): void{
		$searchInfo         = new SearchStatNew();
		$searchInfo->phrase = $newSearchTerm;
		$numSearches        = 0;
		if ($searchInfo->find(true)){
			$numSearches = $searchInfo->numSearches;
		}
		$sortKey = str_pad($numSearches, 10, '0', STR_PAD_LEFT) . $newSearchTerm;
		$suggestions[$sortByNumSearches ? $sortKey : $newSearchTerm] = [
			'phrase'      => $newSearchTerm,
			'numSearches' => $numSearches,
			'sortKey'     => $sortKey,
			'numResults'  => 1
		];
	}

}
