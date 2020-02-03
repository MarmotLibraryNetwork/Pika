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
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Smarty strip_tags modifier plugin
 *
 * Type:    modifier
 * Name:    strip_tags
 * Purpose: strip html tags from text
 * @link    http://www.smarty.net/manual/en/language.modifier.strip.tags.php
 *          strip_tags (Smarty online manual)
 *
 * @author  Monte Ohrt <monte at="" ohrt="" dot="" com="">
 * @author  Jordon Mears <jordoncm at="" gmail="" dot="" com="">
 *
 * @version 2.0
 *
 * @param   string
 * @param   boolean optional
 * @param   string optional
 * @return  string
 */
function smarty_modifier_stripTags($string) {
	switch(func_num_args()) {
		case 1:
			$replace_with_space = true;
			break;
		case 2:
			$arg = func_get_arg(1);
			if($arg === 1 || $arg === true || $arg === '1' || $arg === 'true') {
				// for full legacy support || $arg === 'false' should be included
				$replace_with_space = true;
				$allowable_tags = '';
			} elseif($arg === 0 || $arg === false || $arg === '0' || $arg === 'false') {
				// for full legacy support || $arg === 'false' should be removed
				$replace_with_space = false;
				$allowable_tags = '';
			} else {
				$replace_with_space = true;
				$allowable_tags = $arg;
			}
			break;
		case 3:
			$replace_with_space = func_get_arg(1);
			$allowable_tags = func_get_arg(2);
			break;
	}

	if($replace_with_space) {
		$string = preg_replace('!(<[^>]*?>)!', '$1 ', $string);
	}

	$string = strip_tags($string, $allowable_tags);

	if($replace_with_space) {
		$string = preg_replace('!(<[^>]*?>) !', '$1', $string);
	}

	return $string;
}
