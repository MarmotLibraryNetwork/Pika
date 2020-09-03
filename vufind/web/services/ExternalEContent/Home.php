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

require_once ROOT_DIR . '/RecordDrivers/ExternalEContentDriver.php';

class ExternalEContent_Home extends Action{
	/** @var  SearchObject_Solr $db */
	private $id;

	function launch(){
		global $interface;

		if (isset($_REQUEST['searchId'])){
			$_SESSION['searchId'] = $_REQUEST['searchId'];
		}
		if (isset($_SESSION['searchId'])){
			$interface->assign('searchId', $_SESSION['searchId']);
		}

		$this->id = strip_tags($_REQUEST['id']);
		$interface->assign('id', $this->id);
		//$recordDriver = new ExternalEContentDriver($this->id);

		global /** @var IndexingProfile $activeRecordIndexingProfile */
		$activeRecordIndexingProfile;
		if (isset($activeRecordIndexingProfile)){
			$subType = $activeRecordIndexingProfile->sourceName;
		}else{
			$indexingProfile             = new IndexingProfile();
			$indexingProfile->sourceName = 'ils';
			if ($indexingProfile->find(true)){
				$subType = $indexingProfile->sourceName;
			}else{
				$indexingProfile     = new IndexingProfile();
				$indexingProfile->id = 1;
				if ($indexingProfile->find(true)){
					$subType = $indexingProfile->sourceName;
				}
			}
		}

		/** @var ExternalEContentDriver $recordDriver */
		$recordDriver = RecordDriverFactory::initRecordDriverById('external_econtent:' . $subType . ':'. $this->id);

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

			$this->loadCitations($recordDriver);

			$interface->assign('cleanDescription', strip_tags($recordDriver->getDescriptionFast(), '<p><br><b><i><em><strong>'));

			// Retrieve User Search History
			$interface->assign('lastsearch', isset($_SESSION['lastSearchURL']) ?
			$_SESSION['lastSearchURL'] : false);

			//Get Next/Previous Links
			$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init($searchSource);
			$searchObject->getNextPrevLinks();

			//Get Related Records to make sure we initialize items
			$recordInfo = $groupedWork->getRelatedRecord('external_econtent:' . $recordDriver->getIdWithSource());
			$interface->assign('actions', $recordInfo['actions']);

			// Set Show in Main Details Section options for templates
			// (needs to be set before moreDetailsOptions)
			global $library;
			foreach ($library->showInMainDetails as $detailOption) {
				$interface->assign($detailOption, true);
			}

			$interface->assign('moreDetailsOptions', $recordDriver->getMoreDetailsOptions());

			$interface->assign('semanticData', json_encode($recordDriver->getSemanticData()));

			// Display Page
			$title = $recordDriver->getShortTitle();
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
	 * @param ExternalEContentDriver $recordDriver
	 */
	function loadCitations($recordDriver)
	{
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
