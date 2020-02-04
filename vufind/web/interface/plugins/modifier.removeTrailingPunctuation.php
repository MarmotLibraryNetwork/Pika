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

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     modifier.removeTrailingPunctuation.php
 * Type:     modifier
 * Name:     removeTrailingPunctuation
 * Purpose:  Removes trailing punctuation from a string
 * -------------------------------------------------------------
 */
function smarty_modifier_removeTrailingPunctuation($str) {
	// We couldn't find the file, return an empty value:
	$str = trim($str);
	$str = preg_replace("/(\/|:)$/","", $str);
	$str = trim($str);
	return $str;
}
