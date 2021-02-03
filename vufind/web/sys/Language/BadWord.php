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
 * Table Definition for bad words
 */
require_once 'DB/DataObject.php';

class BadWord extends DB_DataObject {
	public $__table = 'bad_words';    // table name
	public $id;                      //int(11)
	public $word;                    //varchar(50)
//	public $replacement;             //varchar(50)
//TODO: remove replacement column. it isn't used.

	function keys(){
		return ['id', 'word'];
	}

	function getBadWordExpressions(){
		global $memCache;
		global $configArray;
		global $timer;
		$badWordsList = $memCache->get('bad_words_list');
		if ($badWordsList == false){
			$badWordsList = array();
			$this->find();
			if ($this->N){
				while ($this->fetch()){
					$quotedWord = preg_quote(trim($this->word));
					//$badWordExpression = '/^(?:.*\W)?(' . preg_quote(trim($badWord->word)) . ')(?:\W.*)?$/';
					$badWordsList[] = "/^$quotedWord(?=\W)|(?<=\W)$quotedWord(?=\W)|(?<=\W)$quotedWord$|^$quotedWord$/i";
				}
			}
			$timer->logTime("Loaded bad words");
			$memCache->set('bad_words_list', $badWordsList, 0, $configArray['Caching']['bad_words_list']);
		}
		return $badWordsList;
	}

	function censorBadWords($search, $replacement = '***'){
		$badWordsList = $this->getBadWordExpressions();
		$result       = preg_replace($badWordsList, $replacement, $search);
		return $result;
	}

	function hasBadWords($search){
		$badWordsList = $this->getBadWordExpressions();
		foreach ($badWordsList as $badWord){
			if (preg_match($badWord, $search)){
				return true;
			}
		}
		return false;
	}

}
