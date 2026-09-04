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
require_once ROOT_DIR . '/sys/Language/SpellingWord.php';
require_once ROOT_DIR . '/sys/Search/SearchStatNew.php';

const SUGGESTION_LIMIT = 12;

class SearchSuggestions {
    
	static array $disallowedSearchTypesForTermReplacement = [
		'ISN',
		'Author'
	];
    
	/**
	 * Words that carry no meaning for suggestion matching and are stripped out of a search phrase before it is
	 * used to look up search or spelling suggestions.  See D-4982 and D-5425.
	 *
	 * Keep these lowercase; stripDisallowedWords() matches case-insensitively.
	 */
	public static $disallowedSearchSuggestionWords = ['a', 'an', 'be', 'in', 'is', 'it', 'of', 'on', 'or', 'the', 'to'];
		//TODO: read stopwords.txt; store in memcache

	/**
	 * Strip the disallowed words above out of a search phrase, matching whole words only.
	 *
	 * DO NOT change this back to str_replace()/str_ireplace().  Those match substrings, so every word in the list
	 * also gets cut out of the middle of ordinary words: 'the' turns "mother" into "mor", and with the short words
	 * added in D-5425 "The Lion King" becomes "Li Kg", "tortilla flat" becomes "ttill flt" and "beloved" becomes
	 * "loved".  That mangled phrase is what then gets handed to the fulltext match against search_stats and to the
	 * per-word spell checker, so it invents worse replacement terms than the ones this list exists to suppress --
	 * the exact failure D-4982 was opened for.  Commit 9c6f0ae302 fixed the same whole-word problem in the spelling
	 * replacement in getSpellingSearches() below.
	 *
	 * Returns an empty string when nothing is left after stripping (eg a search for "it is on"); callers must treat
	 * that as "no suggestions" rather than searching on it, because an empty phrase matches every stat row.
	 *
	 * @param string $searchTerm
	 * @return string
	 */
	private static function stripDisallowedWords(string $searchTerm): string{
		$quotedWords = array_map(function ($word){
			return preg_quote($word, '/');
		}, self::$disallowedSearchSuggestionWords);
		$strippedTerm = preg_replace('/\b(?:' . implode('|', $quotedWords) . ')\b/i', '', $searchTerm);
		//Collapse the whitespace the removed words leave behind so the phrase doesn't explode() into empty words
		return trim(preg_replace('/\s+/', ' ', $strippedTerm));
	}
    
	static function getCommonSearchesMySql($searchTerm, bool $sortByNumSearches = true){
		$searchTerm = self::stripDisallowedWords($searchTerm);
		if ($searchTerm === ''){
			//The whole phrase was disallowed words; there is nothing meaningful left to match on
			return [];
		}
		$suggestions = self::getSearchSuggestions($searchTerm);
		if ($sortByNumSearches){
			$array = [];
			foreach ($suggestions as $suggestion){
				$array[$suggestion['sortKey']] = $suggestion;
			}
			krsort($array);
			$suggestions = $array;

			if (count($suggestions) > SUGGESTION_LIMIT){
				$suggestions = array_slice($suggestions, 0, SUGGESTION_LIMIT);
			}
		}
		return $suggestions;
	}

	static function getSearchSuggestions($phrase){
		$phrase = trim($phrase);
		//Don't bother getting suggestions for numeric, spammy, or long searches
		if (SearchStatNew::isSearchPhraseToIgnore($phrase)){
			return [];
		}

		$suggestions = [];
		$searchStat  = new SearchStatNew();
		$searchStat->whereAdd("MATCH(phrase) AGAINST ('" . $searchStat->escape($phrase) . "')");
		//$searchStat->orderBy("numSearches DESC");
		// this matching works better when not sorted by num searches for phrases like "wired for love";
		// with numSearches ordering the top results: are : love, love stories; popular searches but unrelated to
		//TODO: might be an improvement to set a level for the match against. eg match against > 7 and then combine with the sort by numSearches
		// See D-1697
		$searchStat->limit(0, 20);
		if ($searchStat->find()){
			self::getResults($searchStat, $phrase, $suggestions);
		}else{
			//Try another search using like
			$searchStat = new SearchStatNew();
			$searchStat->whereAdd("phrase LIKE '" . $searchStat->escape($phrase, true) . "%'");
			$searchStat->orderBy("numSearches DESC");
			$searchStat->limit(0, SUGGESTION_LIMIT);
			if ($searchStat->find()){
				self::getResults($searchStat, $phrase, $suggestions);
			}
		}

		return $suggestions;
	}

	/**
	 * @param SearchStatNew $searchStat
	 * @param string $phrase
	 * @param array $results
	 */
	private static function getResults(SearchStatNew $searchStat, string $phrase, array &$results): void{
		while ($searchStat->fetch()){
			$cleanedPhrase = trim(str_replace('"', '', $searchStat->phrase));
			if ($cleanedPhrase != $phrase && !array_key_exists($cleanedPhrase, $results)){
				$sortKey                 = str_pad($searchStat->numSearches, 10, '0', STR_PAD_LEFT) . $cleanedPhrase;
				$results[$cleanedPhrase] = [
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
		$searchTerm = self::stripDisallowedWords($searchTerm);
		if ($searchTerm === ''){
			//The whole phrase was disallowed words; there is nothing meaningful left to match on
			return [];
		}
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
					$escapedWord   = preg_quote($word, '/'); // prevent compilation failures below
					$newSearchTerm = preg_replace("/\b($escapedWord)\b/", $suggestedWord, $searchTerm);
					if (!empty($newSearchTerm)){
						self::fetchSearchStatForSpellingSuggestion($newSearchTerm, $suggestions);
					}

					//Also try replacements on any suggestions we have so far
					foreach ($suggestionsSoFar as $tmpSearch){
						$newSearchTerm = str_replace($word, $suggestedWord, $tmpSearch['phrase']);
						self::fetchSearchStatForSpellingSuggestion($newSearchTerm, $suggestions);
					}
				}
			}
		}

		if (!empty($suggestions)){
			if ($sortByNumSearches){
				$array = [];
				foreach ($suggestions as $suggestion){
					$array[$suggestion['sortKey']] = $suggestion;
				}
				krsort($array);
				$suggestions = $array;

				//Return up to 12 results max
				if (count($suggestions) > SUGGESTION_LIMIT){
					$suggestions = array_slice($suggestions, 0, SUGGESTION_LIMIT);
				}
			}

		}

		return $suggestions;
	}

	/**
	 * Get Search suggestions and spelling suggestions for the searchbox search term autocomplete
	 *
	 * @param $searchTerm
	 * @param $searchType
	 * @return array
	 */
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
	 */
	private static function fetchSearchStatForSpellingSuggestion(string $newSearchTerm, array &$suggestions): void{
		$searchInfo         = new SearchStatNew();
		$searchInfo->phrase = $newSearchTerm;
		$numSearches        = 0;
		if ($searchInfo->find(true)){
			$numSearches = $searchInfo->numSearches;
		}
		$sortKey = str_pad($numSearches, 10, '0', STR_PAD_LEFT) . $newSearchTerm;
		$suggestions[$newSearchTerm] = [
			'phrase'      => $newSearchTerm,
			'numSearches' => $numSearches,
			'sortKey'     => $sortKey,
			'numResults'  => 1
		];
	}

}
