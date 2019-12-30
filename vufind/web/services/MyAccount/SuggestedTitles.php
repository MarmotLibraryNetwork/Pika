<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
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