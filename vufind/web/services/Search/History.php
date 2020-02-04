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

require_once ROOT_DIR . '/Action.php';

class History extends Action {
	private static $searchSourceLabels = array(
		'local'     => 'Catalog',
		'islandora' => 'Archive',
		'genealogy' => 'Genealogy'
	);

	function launch(){
		global $interface;

		// In some contexts, we want to require a login before showing search
		// history:
		if (isset($_REQUEST['require_login']) && !UserAccount::isLoggedIn()){
			require_once ROOT_DIR . '/services/MyAccount/Login.php';
			(new MyAccount_Login)->launch();
			exit();
		}

		// Retrieve search history
		$s             = new SearchEntry();
		$searchHistory = $s->getSearches(session_id(), UserAccount::isLoggedIn() ? UserAccount::getActiveUserId() : null);

		$noHistory = true;
		if (count($searchHistory) > 0){
			// Build an array of history entries
			$links = array();
			$saved = array();

			// Loop through the history
			foreach ($searchHistory as $search){
				if (isset($_REQUEST['deleteUnsavedSearches']) && $_REQUEST['deleteUnsavedSearches'] == 'true' && $search->saved == 0){
					$search->delete();

					// We don't want to remember the last search after a purge:
					unset($_SESSION['lastSearchURL']);
					// Otherwise add to the list
				}else{

					//$size              = strlen($search->search_object);
					$minSO             = unserialize($search->search_object);
					$searchObject      = SearchObjectFactory::deminify($minSO);
					$searchSourceLabel = $searchObject->getSearchSource();
					if (array_key_exists($searchSourceLabel, self::$searchSourceLabels)){
						$searchSourceLabel = self::$searchSourceLabels[$searchSourceLabel];
					}

					// Make sure all facets are active so we get appropriate
					// descriptions in the filter box.
					$searchObject->activateAllFacets();

					$newItem = array(
						'id'          => $search->id,
						'time'        => date("g:ia, jS M Y", $searchObject->getStartTime()),
						'url'         => $searchObject->renderSearchUrl(),
						'searchId'    => $searchObject->getSearchId(),
						'description' => $searchObject->displayQuery(),
						'filters'     => $searchObject->getFilterList(),
						'hits'        => number_format($searchObject->getResultTotal()),
						'source'      => $searchSourceLabel,
						'speed'       => round($searchObject->getQuerySpeed(), 2) . "s",
						// Size is purely for debugging. Not currently displayed in the template.
						// It's the size of the serialized, minified search in the database.
						//'size'        => round($size/1024, 3)."kb"
					);

					if ($search->saved == 1){
						// Saved searches
						$saved[] = $newItem;
					}else{
						// All the others
							$links[] = $newItem;
						}
					}
				}

			// One final check, after a purge make sure we still have a history
			if (count($links) > 0 || count($saved) > 0){
				$interface->assign('links', array_reverse($links));
				$interface->assign('saved', array_reverse($saved));
				$noHistory = false;
			}
		}
		$interface->assign('noHistory', $noHistory);
		$this->display('history.tpl', 'Search History');
	}
}
