<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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
 * Grouped Work Record View Page
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 11/27/13
 * Time: 12:14 PM
 */
require_once ROOT_DIR . '/Action.php';

class GroupedWork_Home extends Action {
	function launch(){
		global $interface;
		global $timer;

		$id = strip_tags($_REQUEST['id']);

		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		$recordDriver = new GroupedWorkDriver($id);
		if (!$recordDriver->isValid()){
			//Check Version Map and redirect to new id if needed
			require_once ROOT_DIR . '/sys/Grouping/GroupedWorkVersionMap.php';
			$versionCheck                                 = new GroupedWorkVersionMap();
			$versionCheck->groupedWorkPermanentIdVersion4 = $id;
			if ($versionCheck->find(true) && !empty($versionCheck->groupedWorkPermanentIdVersion5)){
				// Permanent redirect
				header("Location: /GroupedWork/{$versionCheck->groupedWorkPermanentIdVersion5}/Home", true, 301);
				die();
			}

			$interface->assign('id', $id);
			global $pikaLogger;
			$pikaLogger->notice("Did not find a grouped work for id $id in solr.");
			$this->display('../Record/invalidRecord.tpl', 'Invalid Record');
			die();
		}
		$interface->assign('recordDriver', $recordDriver);
		$timer->logTime('Loaded Grouped Work Driver');

		// Set Show in Search Results Main Details Section options for template
		// (needs to be set before moreDetailsOptions)
		global $library;
		foreach ($library->showInMainDetails as $detailOption){
			$interface->assign($detailOption, true);
		}

		$recordDriver->assignBasicTitleDetails();
		$timer->logTime('Initialized the Record Driver');

		// Retrieve User Search History
		$interface->assign('lastsearch', $_SESSION['lastSearchURL'] ?? false);

		//Get Next/Previous Links
		$searchSource = !empty($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init($searchSource);
		$searchObject->getNextPrevLinks();
		$timer->logTime('Got next and previous links');

		$interface->assign('moreDetailsOptions', $recordDriver->getMoreDetailsOptions());
		$timer->logTime('Got more details options');

		$exploreMoreInfo = $recordDriver->getExploreMoreInfo();
		$interface->assign('exploreMoreInfo', $exploreMoreInfo);
		$timer->logTime('Got explore more information');

		$interface->assign('metadataTemplate', 'GroupedWork/metadata.tpl');

		$interface->assign('semanticData', json_encode($recordDriver->getSemanticData()));
		$timer->logTime('Loaded semantic data');

		// Send down text for inclusion in breadcrumbs
		$interface->assign('breadcrumbText', $recordDriver->getBreadcrumb());
		$timer->logTime('Loaded breadcrumbs');

		// Display Page
		$this->display('full-record.tpl', $recordDriver->getTitle());
	}


}
