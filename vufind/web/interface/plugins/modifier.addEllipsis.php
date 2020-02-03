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
 * Name:     addEllipsis
 * Purpose:  Adds "..." to the beginning and/or end of a
 *           highlighted phrase when incomplete text is
 *           detected.
 * -------------------------------------------------------------
 *
 * @param string $highlighted Highlighted, possibly abbreviated string
 * @param mixed  $fullString  Full, non-highlighted text
 *
 * @return string             Highlighted string with ellipsis added
 */ // @codingStandardsIgnoreStart
function smarty_modifier_addEllipsis($highlighted, $fullString)
{   // @codingStandardsIgnoreEnd
    // Remove highlighting markers from the string so we can perform a clean
    // comparison:
    $dehighlighted = str_replace(
        array('{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'), '', $highlighted
    );

    // If the dehighlighted string is shorter than the full string, we need
    // to figure out where things changed:
    if (strlen($dehighlighted) < strlen($fullString)) {
        // If the first five characters don't match, chances are something was cut
        // from the front:
        if (substr($dehighlighted, 0, 5) != substr($fullString, 0, 5)) {
            $highlighted = '...' . $highlighted;
        }
        
        // If the last five characters don't match, chances are something was cut
        // from the end:
        if (substr($dehighlighted, -5) != substr($fullString, -5)) {
            $highlighted .= '...';
        }
    }

    // Send back our augmented string:
    return $highlighted;
}
?>
