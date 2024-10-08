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

require_once 'Record.php';

class Record_Home extends Record_Record {

	function launch(){
		global $interface;
		global $timer;

		$this->loadCitations();
		$timer->logTime('Loaded Citations');

		if (isset($_REQUEST['searchId'])){
			if (ctype_digit($_REQUEST['searchId'])){
				$_SESSION['searchId'] = $_REQUEST['searchId'];
				$interface->assign('searchId', $_SESSION['searchId']);
			}
		}elseif (isset($_SESSION['searchId'])){
			$interface->assign('searchId', $_SESSION['searchId']);
		}

		// Set Show in Main Details Section options for templates
		// (needs to be set before moreDetailsOptions)
		global $library;
		foreach ($library->showInMainDetails as $detailOption){
			$interface->assign($detailOption, true);
		}

		//Get the actions for the record
		$actions = $this->recordDriver->getRecordActionsFromIndex();
		$interface->assign('actions', $actions);

		$interface->assign('moreDetailsOptions', $this->recordDriver->getMoreDetailsOptions());
		$exploreMoreInfo = $this->recordDriver->getExploreMoreInfo();
		$interface->assign('exploreMoreInfo', $exploreMoreInfo);

		if(($semanticData = $this->recordDriver->getSemanticData()) && !empty($semanticData)) {
			$interface->assign('metadataTemplate', 'GroupedWork/metadata.tpl');
			$interface->assign('semanticData', json_encode($semanticData, JSON_PRETTY_PRINT));
		} else {
			$interface->assign('semanticData', false);
		}

		// Display Page
		global $configArray;
		if ($configArray['Catalog']['showExploreMoreForFullRecords']){
			$interface->assign('showExploreMore', true);
		}

		$title = $this->recordDriver->getShortTitle();
		if (!empty($this->recordDriver->getSubtitle())){
			if (!empty($title)){
				$title .= ' : ';
			}
			$title .=  $this->recordDriver->getSubtitle();
		}
		$this->display('view.tpl', $title);

	}

	function loadCitations(){
		global $interface;

		$citationCount = 0;
		$formats       = $this->recordDriver->getCitationFormats();
		foreach ($formats as $current){
			$interface->assign(strtolower($current),
				$this->recordDriver->getCitation($current));
			$citationCount++;
		}
		$interface->assign('citationCount', $citationCount);
	}
}
