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
 * Table Definition for search
 */
require_once 'DB/DataObject.php';

class SearchEntry extends DB_DataObject
{
	public $__table = 'search';							// table name
	public $id;															// int(11)	not_null primary_key auto_increment
	public $user_id;												 // int(11)	not_null multiple_key
	public $created;												 // date(10)	not_null binary
	public $saved;													 // int(1) not_null default 0
	public $search_object;									 // blob @todo: this should be a varchar field since it's not really a binary large object
	public $session_id;											// varchar(128)

	/**
	 * Get an array of SearchEntry objects for the specified user.
	 *
	 * @access	public
	 * @param	 int				 $sid						Session ID of current user.
	 * @param	 int				 $uid						User ID of current user (optional).
	 * @return	array											 Matching SearchEntry objects.
	 */
	function getSearches($sid, $uid = null)
	{
			$searches = array();

			$sql = "SELECT * FROM search WHERE session_id = '" . $this->escape($sid) . "'";
			if ($uid != null) {
					$sql .= " OR user_id = '" . $this->escape($uid) . "'";
			}
			$sql .= " ORDER BY id";

			$s = new SearchEntry();
			$s->query($sql);
			if ($s->N) {
					while ($s->fetch()) {
							$searches[] = clone($s);
					}
			}

			return $searches;
	}

	/**
	 * Get an array of SearchEntry objects representing expired, unsaved searches.
	 *
	 * @access	public
	 * @param	 int				 $daysOld				Age in days of an "expired" search.
	 * @return	array											 Matching SearchEntry objects.
	 */
	function getExpiredSearches($daysOld = 2)
	{
		// Determine the expiration date:
		$expirationDate = date('Y-m-d', time() - $daysOld * 24 * 60 * 60);

		// Find expired, unsaved searches:
		$sql = 'SELECT * FROM search WHERE saved=0 AND created<"' . $expirationDate . '"';
		$s = new SearchEntry();
		$s->query($sql);
		$searches = array();
		if ($s->N) {
				while ($s->fetch()) {
						$searches[] = clone($s);
				}
		}
		return $searches;
	}
}
