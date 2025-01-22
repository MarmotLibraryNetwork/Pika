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

class ArrayUtils
{

    /**
     * Return the last key of the given array
     * http://stackoverflow.com/questions/2348205/how-to-get-last-key-in-an-array
     */
    public static function getLastKey($array)
    {
        end($array);
        return key($array);
    }

    /**
     * Recursively ensure all keys and values are encoded as UTF-8.
     * If mb_convert_encoding is available, it will be used;
     * otherwise fallback to utf8_encode.
     */
    public static function utf8EncodeArray(array $array): array
    {
        $useMb = function_exists('mb_convert_encoding');
        return self::recursiveUtf8Encode($array, $useMb);
    }

    /**
     * Internal helper that recursively encodes all keys/values as UTF-8.
     */
    private static function recursiveUtf8Encode(array $array, bool $useMb): array
    {
        $encodedArray = [];
        $utf8 = 'UTF-8';
        $possibleEncodings = 'UTF-8, ISO-8859-1';

        foreach($array as $key => $value) {
            // Encode the array key if it's a string
            if(is_string($key)) {
                $key = $useMb
                    ? (mb_check_encoding($key, $utf8)
                        ? $key
                        : mb_convert_encoding($key, $utf8, $possibleEncodings))
                    : utf8_encode($key);
            }

            // Recurse or encode the value
            if(is_array($value)) {
                // Recurse for sub-arrays
                $encodedArray[$key] = self::recursiveUtf8Encode($value, $useMb);
            } elseif(is_string($value)) {
                // Encode strings
                $encodedArray[$key] = $useMb
                    ? (mb_check_encoding($value, $utf8)
                        ? $value
                        : mb_convert_encoding($value, $utf8, $possibleEncodings))
                    : utf8_encode($value);
            } else {
                // For non-string, just assign them directly
                $encodedArray[$key] = $value;
            }
        }

        return $encodedArray;
    }
}
