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

class ArrayUtils {

	/**
	 * Return the last key of the given array
	 * http://stackoverflow.com/questions/2348205/how-to-get-last-key-in-an-array
	 */
	static public function getLastKey($array){
		end($array);
		return key($array);
	}

	static public function utf8EncodeArray($array){
		if (function_exists('mb_convert_encoding')){
			array_walk_recursive($array, 'ArrayUtils::encode_item');
		} else {
			array_walk_recursive($array, 'ArrayUtils::old_encode_item');
		}
		return $array;
	}

	function encode_item(&$item, &$key){
		$utf8               = 'UTF-8';
		$possible_encodings = 'UTF-8, ISO-8859-1'; //This will likely need expanded other encodings we encounter
		if (is_array($item)){
			ArrayUtils::encode_item($item, $key);
		}else{
			if (is_string($item)){
				if (!mb_check_encoding($key, $utf8)){
					$key = mb_convert_encoding($key, $utf8, $possible_encodings);
				}
				if (!mb_check_encoding($item, $utf8)){
					$item = mb_convert_encoding($item, $utf8, $possible_encodings);
				}
			}
		}
	}

	function old_encode_item(&$item, &$key){
		if (is_array($item)){
			ArrayUtils::old_encode_item($item, $key);
		}else{
			if (is_string($item)){
				$key  = utf8_encode($key);
				$item = utf8_encode($item);
			}
		}
	}

	}
