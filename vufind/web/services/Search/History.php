<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2025  Marmot Library Network
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
require_once ROOT_DIR . '/sys/Search/SearchEntry.php';

class History extends Action {
	private static $searchSourceLabels = [
		'local'     => 'Catalog',
		'islandora' => 'Archive',
		'genealogy' => 'Genealogy'
	];

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
		$activeUserId  = UserAccount::getActiveUserId() ?? null;
		$searchHistory = $s->getSearches(session_id(), $activeUserId);

		$noHistory = true;
		if (count($searchHistory) > 0){
			// Build an array of history entries
			$links = [];
			$saved = [];

			// Loop through the history
			/** @var SearchEntry $search */
			foreach ($searchHistory as $search){
				if (isset($_REQUEST['deleteUnsavedSearches']) && $_REQUEST['deleteUnsavedSearches'] == 'true' && $search->saved == 0){
					$search->delete();

					// We don't want to remember the last search after a purge:
					unset($_SESSION['lastSearchURL']);
					// Otherwise add to the list
				}else{
					$minSO             = unserialize($search->search_object);
					$searchObject      = SearchObjectFactory::deminify($minSO);
					$searchSourceLabel = $searchObject->getSearchSource();
					if (array_key_exists($searchSourceLabel, self::$searchSourceLabels)){
						$searchSourceLabel = self::$searchSourceLabels[$searchSourceLabel];
					}

					// Make sure all facets are active so we get appropriate
					// descriptions in the filter box.
					$searchObject->activateAllFacets();

					$historyEntry = [
						'id'          => $search->id,
						//'userId'      => $search->user_id, // debugging only
						'time'        => date('g:ia, jS M Y', $searchObject->getStartTime()),
						'url'         => $searchObject->renderSearchUrl(),
						'searchId'    => $searchObject->getSearchId(),
						'description' => $searchObject->displayQuery(),
						'filters'     => $searchObject->getFilterList(),
						'hits'        => number_format($searchObject->getResultTotal()),
						'source'      => $searchSourceLabel,
						'speed'       => round($searchObject->getQuerySpeed(), 2) . "s",
					];

					if ($search->saved == 1){
						if (!empty($activeUserId) && $search->user_id == $activeUserId){
							// Saved searches

							// When Masquerading, the session ID is shared between
							// the guiding user & masqueraded user, so we should
							// only display saved searches for the active user
							$saved[] = $historyEntry;
						}
						//else{
							// Exclude other saved searches that happened in the session
							// (e.g. when masquerading)
						//}
					}else{
						// All the others
						$links[] = $historyEntry;
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
