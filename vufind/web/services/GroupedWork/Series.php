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

require_once ROOT_DIR . '/sys/NovelistFactory.php';

class GroupedWork_Series extends Action {
	function launch(){
		global $interface;
		global $timer;
		global $logger;

		// Hide Covers when the user has set that setting on the Search Results Page
		$this->setShowCovers();

		$id = $_REQUEST['id'];

		require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
		$recordDriver = new GroupedWorkDriver($id);
		if (!$recordDriver->isValid){
			$interface->assign('id', $id);
			$logger->log("Did not find a record for id {$id} in solr.", PEAR_LOG_DEBUG);
			$interface->setTemplate('../Record/invalidRecord.tpl');
			$this->display('../Record/invalidRecord.tpl', 'Invalid Record');
			die();
		}
		$timer->logTime('Initialized the Record Driver');

		$novelist   = NovelistFactory::getNovelist();
		$seriesData = $novelist->getSeriesTitles($id, $recordDriver->getISBNs());

		//Loading the series title is not reliable.  Do not try to load it.
		$seriesTitle   = null;
		$seriesAuthors = [];
		$resourceList  = [];
		$seriesTitles  = $seriesData->seriesTitles;
		$recordIndex   = 1;
		if (isset($seriesTitles) && is_array($seriesTitles)){
			foreach ($seriesTitles as $key => $title){
				if (isset($title['series']) && strlen($title['series']) > 0 && !(isset($seriesTitle))){
					$seriesTitle = $title['series'];
					$interface->assign('seriesTitle', $seriesTitle);
				}
				if (isset($title['author'])){
					$author                 = preg_replace('/[^\w]*$/i', '', $title['author']);
					$seriesAuthors[$author] = $author;
				}
				$interface->assign('recordIndex', $recordIndex);
				$interface->assign('resultIndex', $recordIndex++);
				if ($title['libraryOwned']){
					/** @var GroupedWorkDriver $tmpRecordDriver */
					$tmpRecordDriver = $title['recordDriver'];
					$resourceList[]  = $interface->fetch($tmpRecordDriver->getSearchResult('list'));
				}else{
					$interface->assign('record', $title);
					$resourceList[] = $interface->fetch('RecordDrivers/Index/nonowned_result.tpl');
				}
			}
		}

		$interface->assign('seriesAuthors', $seriesAuthors);
		$interface->assign('recordSet', $seriesTitles);
		$interface->assign('resourceList', $resourceList);

		$interface->assign('recordStart', 1);
		$interface->assign('recordEnd', count($seriesTitles));
		$interface->assign('recordCount', count($seriesTitles));

		$interface->assign('recordDriver', $recordDriver);

		$this->setShowCovers();

		// Display Page
		$this->display('view-series.tpl', $seriesTitle);
	}

}
