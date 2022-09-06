<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2022  Marmot Library Network
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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 7/19/2022
 *
 */


trait UserListSearchObjectTrait {

	// Variables for building Search URLs
	public $userListSort;
	public $userListPageSize;

	/**
	 * Build a url for the current search
	 *
	 * @access  public
	 * @return  string   URL of a search
	 */
	public function renderSearchUrl(){
		// Get the base URL and initialize the parameters attached to it:
		$url    = $this->getBaseUrl();
		$params = [];

		// Add any filters
		if (count($this->filterList) > 0){
			foreach ($this->filterList as $field => $filter){
				foreach ($filter as $value){
					if (preg_match('/\\[.*?\\sTO\\s.*?\\]/', $value)){
						$params[] = "filter[]=$field:$value";
					}elseif (preg_match('/^\\(.*?\\)$/', $value)){
						$params[] = "filter[]=$field:$value";
					}else{
						if (is_numeric($field)){
							$params[] = 'filter[]=' . urlencode($value);
						}else{
							$params[] = 'filter[]=' . urlencode("$field:\"$value\"");
						}
					}
				}
			}
		}

		// Sorting
		if ($this->userListSort != null){
			$params[] = 'sort=' . urlencode($this->userListSort);
		}

		// Page Sizing
		if ($this->userListPageSize != null){
			$params[] = 'pagesize=' . urlencode($this->userListPageSize);
		}

		// Join all parameters with an escaped ampersand,
		//   add to the base url and return
		return $url . implode('&', $params);
	}


	/**
	 * Return a url for the current search with a new sort
	 *
	 * @access  public
	 * @param   string   $newSort   A field to sort by
	 * @return  string   URL of a new search
	 */
	public function renderLinkWithSort($newSort){
		$oldSort            = $this->userListSort;              // Stash our old data for a minute
		$this->userListSort = $newSort;                         // Add the new sort
		$url                = $this->renderSearchUrl();         // Get the new url
		$this->userListSort = $oldSort;                         // Restore the old data
		return $url;                                            // Return the URL
	}
}