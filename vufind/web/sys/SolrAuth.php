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
require_once 'Solr.php';

/**
 * Solr Authority Class
 *
 * Offers functionality for reading authority data from Solr.
 *
 * @category Pika
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
class SolrAuth extends Solr
{
	/**
	 * Constructor
	 *
	 * @param string $host The URL for the local Solr Server
	 *
	 * @access public
	 */
	public function __construct($host)
	{
		parent::__construct($host, 'authority');
		$this->searchSpecsFile = 'conf/authsearchspecs.yaml';
	}
}
