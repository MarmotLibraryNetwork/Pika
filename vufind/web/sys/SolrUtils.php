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
 * Solr Utility Functions
 *
 * This class is designed to hold Solr-related support methods that may
 * be called statically.  This allows sharing of some Solr-related logic
 * between the Solr and Summon classes.
 *
 * @author      Demian Katz
 * @access      public
 */
class SolrUtils
{
	/**
	 * Capitalize boolean operators in a query string to allow case-insensitivity.
	 *
	 * @access  public
	 * @param   string  $query          The query to capitalize.
	 * @return  string                  The capitalized query.
	 */
	public static function capitalizeBooleans($query)
	{
		// This lookAhead detects whether or not we are inside quotes; it
		// is used to prevent switching case of Boolean reserved words
		// inside quotes, since that can cause problems in case-sensitive
		// fields when the reserved words are actually used as search terms.
		$lookAhead = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';
		$regs = array("/\\s+AND\\s+{$lookAhead}/i", "/\\s+OR\\s+{$lookAhead}/i",
		        "/(\\s+NOT\\s+|^NOT\\s+){$lookAhead}/i", "/\\(NOT\\s+{$lookAhead}/i");
		$replace = array(' AND ', ' OR ', ' NOT ', '(NOT ');
		return trim(preg_replace($regs, $replace, $query));
	}

}
