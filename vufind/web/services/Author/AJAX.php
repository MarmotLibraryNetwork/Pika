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
 * Handles AJAX related information for authors
 *
 * @category Pika
 * @author   Mark Noble <pika@marmot.org>
 * Date: 7/23/13
 * Time: 8:37 AM
 */

require_once ROOT_DIR . '/AJAXHandler.php';

class Author_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'Author_AJAX',
	);

	function getWikipediaData(){
		global $configArray;
		global $library;
		global $interface;
		/** @var Memcache $memCache */
		global $memCache;
		$returnVal = array();
		if (isset($configArray['Content']['authors'])
			&& stristr($configArray['Content']['authors'], 'wikipedia')
			&& (!$library || $library->showWikipediaContent == 1)
		){
			// Only use first two characters of language string; Wikipedia
			// uses language domains but doesn't break them up into regional
			// variations like pt-br or en-gb.
			$authorName = $_REQUEST['articleName'];
			if (is_array($authorName)){
				$authorName = reset($authorName);
			}
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
					require_once ROOT_DIR . '/sys/WikipediaParser.php';
					$wikipediaUrl = $authorEnrichment->wikipediaUrl;
					$authorName   = str_replace('https://en.wikipedia.org/wiki/', '', $wikipediaUrl);
					$authorName   = urldecode($authorName);
				}
			}
			if ($doLookup){
				$wiki_lang = substr($configArray['Site']['language'], 0, 2);
				$interface->assign('wiki_lang', $wiki_lang);
				$authorInfo = $memCache->get("wikipedia_article_{$authorName}_{$wiki_lang}");
				if ($authorInfo == false){
					require_once ROOT_DIR . '/services/Author/Wikipedia.php';
					$wikipediaParser = new Author_Wikipedia();
					$authorInfo      = $wikipediaParser->getWikipedia($authorName, $wiki_lang);
					$memCache->add("wikipedia_article_{$authorName}_{$wiki_lang}", $authorInfo, false, $configArray['Caching']['wikipedia_article']);
				}
				$returnVal['success'] = true;
				$returnVal['article'] = $authorInfo;
				$interface->assign('info', $authorInfo);
				$returnVal['formatted_article'] = $interface->fetch('Author/wikipedia_article.tpl');
			}else{
				$returnVal['success'] = false;
			}
		}else{
			$returnVal['success'] = false;
		}
		return json_encode($returnVal);
	}
}
