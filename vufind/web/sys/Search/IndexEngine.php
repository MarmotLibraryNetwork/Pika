<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Index Engine Interface
 *
 * @version     $Revision$
 * @author      Andrew S. Nagy <asnagy@gmail.com>
 * @access      public
 */
Interface IndexEngine {
	/**
	 * Retrieves a document specified by the ID.
	 *
	 * @param string $id The document to retrieve from Solr
	 * @access  public
	 * @return  string              The requested resource
	 * @throws  object              PEAR Error
	 */
	function getRecord($id);

	/**
	 * Get records similiar to one record
	 *
	 * @access  public
	 * @param id          The record id
	 * @return  array       An array of query results
	 * @throws  object      PEAR Error
	 */
	function getMoreLikeThis($id);

	/**
	 * Get record data based on the provided field and phrase.
	 * Used for AJAX suggestions.
	 *
	 * @access  public
	 * @param string $phrase The input phrase
	 * @param string $field The field to search on
	 * @param int $limit The number of results to return
	 * @return  array   An array of query results
	 */
	function getSuggestion($phrase, $field, $limit);

	/**
	 * Get spelling suggestions based on input phrase.
	 *
	 * @access  public
	 * @param string $phrase The input phrase
	 * @return  array   An array of spelling suggestions
	 */
	function checkSpelling($phrase);

	/**
	 * Build Query string from search parameters
	 *
	 * @access  public
	 * @param array $search An array of search parameters
	 * @param string $sortBy The value to be used by for sorting
	 * @return  string              The query
	 * @throws  object              PEAR Error
	 * @static
	 */
	function buildQuery($search);

	/**
	 * Execute a search.
	 *
	 * @param string $query The XQuery script in binary encoding.
	 * @param string $handler The Query Handler/Index to search on
	 * @param array $filter The fields and values to filter results on
	 * @param int $start The record to start with
	 * @param string $limit The amount of records to return
	 * @param array $facet An array of faceting options
	 * @param string $spell Phrase to spell check
	 * @param null $sort
	 * @param string $fields A list of fields to be returned
	 * @param string $method
	 * @return  array               An array of query results
	 * @access  public
	 */
	function search($query, $handler = null, $filter = null, $start = 0,
	                $limit = null, $facet = null, $spell = null, $sort = null,
	                $fields = null, $method = 'POST');


}
