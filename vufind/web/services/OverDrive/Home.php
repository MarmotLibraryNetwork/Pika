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

require_once ROOT_DIR . '/RecordDrivers/OverDriveRecordDriver.php';

class OverDrive_Home extends Action{
	/** @var  SearchObject_Solr $db */
	private $id;

	function launch(){
		global $interface;

		if (isset($_REQUEST['searchId'])){
			if (ctype_digit($_REQUEST['searchId'])){
				$_SESSION['searchId'] = $_REQUEST['searchId'];
				$interface->assign('searchId', $_SESSION['searchId']);
			}
		}elseif (isset($_SESSION['searchId'])){
			$interface->assign('searchId', $_SESSION['searchId']);
		}

		$this->id = strip_tags($_REQUEST['id']);
		$interface->assign('id', $this->id);
		$recordDriver = new OverDriveRecordDriver($this->id);

		if (!$recordDriver->isValid()){
			$this->display('../Record/invalidRecord.tpl', 'Invalid Record');
			die();
		}

		$groupedWork = $recordDriver->getGroupedWorkDriver();
		if (is_null($groupedWork) || !$groupedWork->isValid()){  // initRecordDriverById itself does a validity check and returns null if not.
			$this->display('../Record/invalidRecord.tpl', 'Invalid Record');
			die();
		}else{
			$interface->assign('recordDriver', $recordDriver);
			$interface->assign('groupedWorkDriver', $recordDriver->getGroupedWorkDriver());

			//Load status summary
			$holdingsSummary = $recordDriver->getStatusSummary();
			$interface->assign('holdingsSummary', $holdingsSummary);

			//Load the citations
			$this->loadCitations($recordDriver);

			// Retrieve User Search History
			$interface->assign('lastsearch', $_SESSION['lastSearchURL'] ?? false);

			//Get Next/Previous Links
			$searchSource = $_REQUEST['searchSource'] ?? 'local';
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init($searchSource);
			$searchObject->getNextPrevLinks();

			// Set Show in Main Details Section options for templates
			// (needs to be set before moreDetailsOptions)
			global $library;
			foreach ($library->showInMainDetails as $detailoption) {
				$interface->assign($detailoption, true);
			}

			$interface->assign('moreDetailsOptions', $recordDriver->getMoreDetailsOptions());

			$interface->assign('semanticData', json_encode($recordDriver->getSemanticData()));

			// Display Page
			$title = $recordDriver->getTitle();
			if (!empty($recordDriver->getSubtitle())){
				if (!empty($title)){
					$title .= ' : ';
				}
				$title .=  $recordDriver->getSubtitle();
			}
			$this->display('view.tpl', $title);

		}
	}


	/**
	 * @param OverDriveRecordDriver $recordDriver
	 */
	function loadCitations($recordDriver){
		global $interface;

		$citationCount = 0;
		$formats = $recordDriver->getCitationFormats();
		foreach($formats as $current) {
			$interface->assign(strtolower($current), $recordDriver->getCitation($current));
			$citationCount++;
		}
		$interface->assign('citationCount', $citationCount);
	}
}
