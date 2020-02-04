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

class ISBNConverter{
	public static function convertISBN10to13($isbn10){
		if (strlen($isbn10) != 10){
			return '';
		}
		$isbn = '978' . substr($isbn10, 0, 9);
		//Calculate the 13 digit checksum
		$sumOfDigits = 0;
		for ($i = 0; $i < 12; $i++){
			$multiplier = 1;
			if ($i % 2 == 1){
				$multiplier = 3;
			}
			$sumOfDigits += $multiplier * (int)($isbn[$i]);
		}
		$modValue = $sumOfDigits % 10;
		if ($modValue == 0){
			$checksumDigit = 0;
		}else{
			$checksumDigit = 10 - $modValue;
		}
		return  $isbn . $checksumDigit;
	}

	public static function convertISBN13to10($isbn13){
		if (substr($isbn13, 0, 3) == '978'){
			$isbn = substr($isbn13, 3, 9);
			$checksumDigit = 1;
			$sumOfDigits = 0;
			for ($i = 0; $i < 9; $i++){
				$sumOfDigits += ($i + 1) * (int)($isbn[$i]);
			}
			$modValue = $sumOfDigits % 11;
			if ($modValue == 10){
				$checksumDigit = 'X';
			}else{
				$checksumDigit = $modValue;
			}
			return  $isbn . $checksumDigit;
		}else{
			//Can't convert to 10 digit
			return '';
		}
	}
}
