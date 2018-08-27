<?php

class ArrayUtils
{
	
	/**
	 * Return the last key of the given array
	 * http://stackoverflow.com/questions/2348205/how-to-get-last-key-in-an-array
	 */
	static public function getLastKey($array)
	{
		end($array);
		return key($array);
	}

	static public function utf8EncodeArray($array){
		array_walk_recursive($array, 'ArrayUtils::encode_item');
		return $array;
	}

	function encode_item(&$item, &$key)
	{
		if (is_array($item)){
			ArrayUtils::encode_item($item, $key);
		}else if (is_string($item)){
			// This only encodes an ISO-8859-1 string to UTF-8
			// You have to determine the sting in is ISO-8859-1 fist,
			$key = utf8_encode($key);
			$item = utf8_encode($item);
		}

	}
}