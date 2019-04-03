<?php

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
			array_walk_recursive($array, 'ArrayUtils::old encode_item');
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