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
require_once ROOT_DIR . '/sys/Autocomplete/Interface.php';

/**
 * OCLC Identities Autocomplete Module
 *
 * This class provides suggestions by using OCLC Identities.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */
class OCLCIdentitiesAutocomplete implements AutocompleteInterface
{
    protected $url;

    /**
     * Constructor
     *
     * Establishes base settings for making autocomplete suggestions.
     *
     * @param string $params Additional settings from searches.ini.
     *
     * @access public
     */
    public function __construct($params)
    {
        // For now, incoming parameters are ignored and a hard-coded URL is used:
        $this->url = 'http://worldcat.org/identities/AutoSuggest';
    }

    /**
     * getSuggestions
     *
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     *
     * @return array        The suggestions for the provided query
     * @access public
     */
    public function getSuggestions($query)
    {
        // Initialize return array:
        $results = array();

        // Build target URL:
        $target = $this->url . '?query=' . urlencode($query);

        // Retrieve and parse response:
        $tmp = file_get_contents($target);
        if ($tmp && ($json = json_decode($tmp)) && isset($json->result)
            && is_array($json->result)
        ) {
            foreach ($json->result as $current) {
                if (isset($current->term)) {
                    $results[] = $current->term;
                }
            }
        }

        // Send back results:
        return array_unique($results);
    }
}

?>
