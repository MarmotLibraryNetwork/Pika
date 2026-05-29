<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * File:     modifier.stripRelatorCode.php
 * Type:     modifier
 * Name:     stripRelatorCode
 * Purpose:  Strips the three-letter MARC relator code (e.g. "(ack)")
 *           and any surrounding parentheses from a relation_label string.
 *           Example: " (Acknowledgement (ack))" → "Acknowledgement"
 * -------------------------------------------------------------
 */
function smarty_modifier_stripRelatorCode($str) {
	$str = trim((string)$str);
	// Strip outer parentheses wrapping the whole value, e.g. "(Label (ack))" → "Label (ack)"
	$str = preg_replace('/^\((.+)\)$/', '$1', trim($str));
	// Strip trailing three-letter relator code, e.g. "Label (ack)" → "Label"
	$str = preg_replace('/\s*\([a-zA-Z]{3}\)\s*$/', '', $str);
	return trim($str);
}