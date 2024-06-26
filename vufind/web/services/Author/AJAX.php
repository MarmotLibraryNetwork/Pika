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
 * Handles AJAX related information for authors
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 7/23/13
 * Time: 8:37 AM
 */

require_once ROOT_DIR . '/AJAXHandler.php';

class Author_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = [
		'getWikipediaData',
		'nameVariations'
	];

	function getWikipediaData(){
		global $configArray;
		global $library;
		$returnVal = ['success' => false];
		if (isset($configArray['Content']['authors'])
			&& stristr($configArray['Content']['authors'], 'wikipedia')
			&& (!$library || $library->showWikipediaContent == 1)
		){
			$authorName = is_array($_REQUEST['articleName']) ? reset($_REQUEST['articleName']) : $_REQUEST['articleName'];
			$authorName = trim($authorName);

			//Check to see if we have an override
			require_once ROOT_DIR . '/sys/LocalEnrichment/AuthorEnrichment.php';
			$authorEnrichment             = new AuthorEnrichment();
			$authorEnrichment->authorName = $authorName;
			$doLookup                     = true;
			if ($authorEnrichment->find(true)){
				if ($authorEnrichment->hideWikipedia){
					$doLookup = false;
				}else{
					$wikipediaUrl = $authorEnrichment->wikipediaUrl;
					$authorName   = str_replace('https://en.wikipedia.org/wiki/', '', $wikipediaUrl);
					$authorName   = urldecode($authorName);
				}
			} else{
				// Leave the trailing . in place for the Author Enrichment lookup because the form suggests using the 100ad
				$authorName = rtrim($authorName, '.');
			}
			if ($doLookup){
				/** @var Memcache $memCache */
				global $memCache;
				// Only use first two characters of language string; Wikipedia
				// uses language domains but doesn't break them up into regional
				// variations like pt-br or en-gb.
				$wiki_lang   = substr($configArray['Site']['language'], 0, 2);
				$memCacheKey = "wikipedia_article_{$authorName}_{$wiki_lang}";
				$authorInfo  = $memCache->get($memCacheKey);
				if (empty($authorInfo)){
					$wikipediaParser = new ExternalEnrichment\WikipediaParser($wiki_lang);
					$author          = trim(str_replace('"', '', $authorName));
					$baseApiUrl      = "http://{$wiki_lang}.wikipedia.org/w/api.php" .
						'?action=query&prop=revisions&rvprop=content&format=json' .
						'&titles=';
					$authorInfo      = $wikipediaParser->getWikipediaPage($baseApiUrl, $author);
					if (empty($authorInfo)){
						if (preg_match('/(.*),\s\d+-?(\d+)?$/si', $author, $matches)){
							//Parse author string that end with year information
							$author     = $matches[1];
							$authorInfo = $wikipediaParser->getWikipediaPage($baseApiUrl, $author);
						}
						if (empty($authorInfo)){
							if (strpos($author, ',') > 0){
								//Try reversing the name
								$authorParts = explode(',', $author, 2);
								$author      = trim($authorParts[1] . ' ' . $authorParts[0]);
								$authorInfo  = $wikipediaParser->getWikipediaPage($baseApiUrl, $author);
							}
							if (empty($authorInfo)){
								//Try one last time with no periods
								$author     = str_replace('.', '', $author);
								$authorInfo = $wikipediaParser->getWikipediaPage($baseApiUrl, $author);
							}
						}
					}
					if (!empty($authorInfo)){
						$memCache->add($memCacheKey, $authorInfo, false, $configArray['Caching']['wikipedia_article']);
					}
				}
				if (!empty($authorInfo)){
					global $interface;
					$interface->assign('wiki_lang', $wiki_lang);
					$interface->assign('info', $authorInfo);
					$returnVal['success']           = true;
					$returnVal['article']           = $authorInfo;
					$returnVal['formatted_article'] = $interface->fetch('Author/wikipedia_article.tpl');
				}
			}
		}
		return $returnVal;
	}

	function nameVariations(){
		global $interface;
		global $configArray;

		/** @var Solr $db */
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		$db    = new $class($url);

		$authorOriginal = strip_tags($_REQUEST['author']);
		if (is_array($authorOriginal)){
			$authorOriginal = array_pop($authorOriginal);
		}
		$author = trim(str_replace('"', '', $authorOriginal));
		if (substr($author, strlen($author) - 1, 1) == ','){
			$author = substr($author, 0, strlen($author) - 1);
		}
		$author = explode(',', $author);

		$authAuthorSearch = $author[0];
		if (isset($author[1])){
			// Create First Name
			// Note: Sometimes we don't actually have a first name but just the dates eg "Muddy Waters, 1915-1983"
			$firstName        = $author[1];
			// Remove dates & initials explainer
			$firstName        = preg_replace('/[0-9]+-[0-9]*/', '', $firstName); // birth year - death year phrases
			$firstName        = trim(preg_replace('/\(.*\)$/i', '', $firstName));      // full names for initials in parentheses eg.  W. E. B. (William Edward Burghardt)
			if (strlen($firstName)){
				$authAuthorSearch .= ', ' . $firstName;
			}
		}

		$authorVariationSuggestions = $db->search("auth_author:\"$authAuthorSearch\"", null, null, 0, 0,
			[
				'field'             => 'authorStr',
				'additionalOptions' => [
					'facet.query' => 'author_left:'. substr($author[0], 0, 35), // text-left fields only match up to 35 characters
				]
			], null, null, null, 'authorStr');

		if (!empty($authorVariationSuggestions['facet_counts']['facet_fields']['authorStr'])){
			if (count($authorVariationSuggestions['facet_counts']['facet_fields']['authorStr']) > 1){
				// Don't return suggestions if there is only one
				if (count($authorVariationSuggestions['facet_counts']['facet_fields']['authorStr']) > 10){
					// Only use the first 10 suggestions
					$authorVariationSuggestions['facet_counts']['facet_fields']['authorStr'] = array_slice($authorVariationSuggestions['facet_counts']['facet_fields']['authorStr'], 0, 10, true);
				}
				$interface->assign('authorVariations', $authorVariationSuggestions['facet_counts']['facet_fields']['authorStr']);
				return [
					'success' => true,
					'body'    => $interface->fetch('Author/nameVariations.tpl')
				];
			}
		}
		return ['success' => false,];
	}

}
