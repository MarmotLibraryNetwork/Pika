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

require_once ROOT_DIR . '/sys/ISBN.php';

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     modifier
 * Name:     FormatISBN
 * Purpose:  Formats an ISBN number
 * -------------------------------------------------------------
 */
function smarty_modifier_formatISBN($isbn) {
    // Normalize ISBN to an array if it is not already.
    $isbns = is_array($isbn) ? $isbn : array($isbn);

    // Loop through the ISBNs, trying to find an ISBN-10 if possible, and returning
    // the first ISBN-13 encountered as a last resort:
    $isbn13 = false;
    foreach($isbns as $isbn) {
        // Strip off any unwanted notes:
        if ($pos = strpos($isbn, ' ')) {
            $isbn = substr($isbn, 0, $pos);
        }

        // If we find an ISBN-10, return it immediately; otherwise, if we find
        // an ISBN-13, save it if it is the first one encountered.
        $isbnObj = new ISBN($isbn);
        if ($isbn10 = $isbnObj->get10()) {
            return $isbn10;
        }
        if (!$isbn13) {
            $isbn13 = $isbnObj->get13();
        }
    }
    return $isbn13;
}
?>
