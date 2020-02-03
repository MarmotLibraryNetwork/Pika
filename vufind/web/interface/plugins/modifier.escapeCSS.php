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
 * Smarty escapeCSS modifier plugin
 *
 * Type:    modifier
 * Name:    escapeCSS
 * Purpose: Remove special characters so the string can be used as a css class or id
 *
 * @author  Mark Noble
 *
 * @version 2.0
 *
 * @param   string $string The string to escape
 * @return  string
 */
function smarty_modifier_escapeCSS($string) {
	$string = preg_replace('/[^a-zA-Z0-9_-]/', '_', $string);

	return $string;
}
