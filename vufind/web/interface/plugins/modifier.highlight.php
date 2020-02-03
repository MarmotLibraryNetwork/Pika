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
 * -------------------------------------------------------------
 * Type:     modifier
 * Name:     highlight
 * Purpose:  Adds a span tag with class "highlight" around a
 *           specific phrase for highlighting
 * -------------------------------------------------------------
 *
 * @param string $haystack String to highlight
 * @param mixed  $needle   Array of words to highlight (null for none)
 *
 * @return string          Highlighted, HTML encoded string
 */
function smarty_modifier_highlight($haystack, $needle = null) {
	if ($needle == null){
		return $haystack;
	}
	// Normalize value to an array so we can loop through it; this saves us from
	// writing the highlighting code twice, once for arrays, once for non-arrays.
	// Also make sure our generated array is empty if needle itself is empty --
	// if $haystack already has highlighting markers in it, we may want to send
	// in a blank needle.
	if (!is_array($needle)) {
		$needle = empty($needle) ? array() : array($needle);
	}

	// Highlight search terms one phrase at a time; we just put in placeholders
	// for the start and end span tags at this point so we can do proper URL
	// encoding later.
	foreach ($needle as $phrase) {
		$phrase = trim(str_replace(array('"', '*', '?'), '', $phrase));
		if ($phrase != '') {
			$phrase = preg_quote($phrase, '/');
			$haystack = preg_replace(
                "/($phrase)/iu",
                '{{{{START_HILITE}}}}$1{{{{END_HILITE}}}}', $haystack
			);
		}
	}

	// URL encode the string, then put in the highlight spans:
	$haystack = str_replace(
		array('{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'),
		array('<span class="highlight">', '</span>'),
		$haystack
	);

	return $haystack;
}
