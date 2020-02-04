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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
require_once ROOT_DIR . '/services/MyResearch/lib/FavoriteHandler.php';
require_once ROOT_DIR . '/services/MyResearch/lib/Suggestions.php';

/**
 *  Homepage for Suggested Titles for user based on users ratings
 */
class SuggestedTitles extends MyAccount {

	function launch(){
		global $interface;
		global $configArray;
		global $timer;

		$suggestions = Suggestions::getSuggestions();
		$timer->logTime("Loaded suggestions");

		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url   = $configArray['Index']['url'];
		/** @var Solr $solrDb */
		$solrDb = new $class($url);

		$resourceList = array();
		$curIndex     = 0;
		if (is_array($suggestions)){
			$suggestionIds = array_keys($suggestions);
			$records       = $solrDb->getRecords($suggestionIds);
			foreach ($records as $record){
				$interface->assign('resultIndex', ++$curIndex);
				/** @var IndexRecord $recordDriver */
				$recordDriver   = RecordDriverFactory::initRecordDriver($record);
				$resourceEntry  = $interface->fetch($recordDriver->getSearchResult());
				$resourceList[] = $resourceEntry;
			}
		}
		$timer->logTime("Loaded results for suggestions");
		$interface->assign('resourceList', $resourceList);

		//Check to see if the user has rated any titles
		$user = UserAccount::getLoggedInUser();
		$interface->assign('hasRatings', $user->hasRatings());

		$this->display('suggestedTitles.tpl', 'Recommended for You');
	}

}
