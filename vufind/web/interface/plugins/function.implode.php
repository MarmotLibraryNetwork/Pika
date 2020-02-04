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
 * Smarty {implode} function plugin
 *
 * Name:     implode<br>
 * Purpose:  glue an array together as a string, with supplied string glue, and assign it to the template
 * @link http://smarty.php.net/manual/en/language.function.implode.php {implode}
 *       (Smarty online manual)
 * @author Will Mason <will at dontblinkdesign dot com>
 * @param array $params
 * @param UInterface $smarty
 * @return null|string
 */
function smarty_function_implode($params, &$smarty)
{
	if (!isset($params['subject'])) {
		$smarty->trigger_error("implode: missing 'subject' parameter");
		return;
	}

	if (!isset($params['glue'])) {
		$params['glue'] = ", ";
	}

	$subject = $params['subject'];

	$implodedValue = null;
	if (is_array($subject)){
		if (isset($params['sort'])){
			sort($subject);
		}
		$implodedValue = implode($params['glue'], $subject);
	}else{
		$implodedValue = $subject;
	}

	if (!isset($params['assign'])) {
		return $implodedValue;
	}else{
		$smarty->assign($params['assign'], $implodedValue);
	}
}
