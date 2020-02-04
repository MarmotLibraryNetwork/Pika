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
require_once ROOT_DIR . '/sys/Autocomplete/SolrAutocomplete.php';

/**
 * Solr Authority Autocomplete Module
 *
 * This class provides suggestions by using the local Solr authority index.
 *
 * @category Pika
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */
class SolrAuthAutocomplete extends SolrAutocomplete
{
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
        // Use a different default field; otherwise, behave the same as the parent:
        $this->defaultDisplayField = 'heading';
        parent::__construct($params);
    }

    /**
     * initSearchObject
     *
     * Initialize the search object used for finding recommendations.
     *
     * @return void
     * @access protected
     */
    protected function initSearchObject()
    {
        // Build a new search object:
        $this->searchObject = SearchObjectFactory::initSearchObject('SolrAuth');
    }
}

?>
