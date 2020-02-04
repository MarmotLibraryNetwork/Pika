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

/**
 * Display a report of usage based on namespace for each library connected to the archive
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 5/4/2017
 * Time: 8:19 AM
 */
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/ArchiveSubject.php';
require_once ROOT_DIR . '/services/API/ArchiveAPI.php';
class Admin_ArchiveUsage extends Admin_Admin{

	function launch() {
		global $interface;

		$archiveLibraries = new Library();
		$archiveLibraries->whereAdd("archiveNamespace != ''");
		$archiveLibraries->orderBy('displayName');
		$archiveLibraries->find();

		//Get the number of records contributed to DPLA
		$archiveAPI = new API_ArchiveAPI();
		$dplaUsage = $archiveAPI->getDPLACounts();

		$totalDriveSpace = 0;
		$totalObjects = 0;
		$totalDpla = 0;

		$usageByNamespace = array();
		while ($archiveLibraries->fetch()){
			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->setLimit(250);
			$searchObject->clearFilters();
			$searchObject->clearHiddenFilters();

			$searchObject->setBasicQuery($archiveLibraries->archiveNamespace, 'namespace_s');
			$searchObject->addFieldsToReturn(array('fedora_datastream_latest_OBJ_SIZE_ms'));
			$searchObject->setApplyStandardFilters(false);

			$usageByNamespace[$archiveLibraries->ilsCode] = array(
					'displayName' => $archiveLibraries->displayName,
					'numObjects' => 0,
					'numDpla' => 0,
					'driveSpace' => 0
			);

			if (isset($dplaUsage[$archiveLibraries->archiveNamespace])){
				$usageByNamespace[$archiveLibraries->ilsCode]['numDpla'] = $dplaUsage[$archiveLibraries->archiveNamespace];
			}

			$response = $searchObject->processSearch(true, false);
			if ($response && $response['response']['numFound'] > 0) {
				$numProcessed = 0;
				$usageByNamespace[$archiveLibraries->ilsCode]['numObjects'] = $response['response']['numFound'];
				while ($numProcessed < $response['response']['numFound']){
					foreach ($response['response']['docs'] as $doc){
						if (isset ($doc['fedora_datastream_latest_OBJ_SIZE_ms'])){
							$usageByNamespace[$archiveLibraries->ilsCode]['driveSpace'] += $doc['fedora_datastream_latest_OBJ_SIZE_ms'][0];
						}
						$numProcessed++;
					}
					if ($numProcessed < $response['response']['numFound']){
						$searchObject->setPage($searchObject->getPage() + 1);
						$response = $searchObject->processSearch(true, false);
					}
				}
			}
		}

		foreach ($usageByNamespace as $ilsCode => $namespaceStats){
			$totalObjects += $namespaceStats['numObjects'];
			$totalDpla += $namespaceStats['numDpla'];
			$diskSpace = ceil($namespaceStats['driveSpace'] * 0.000000001);
			$totalDriveSpace += $diskSpace;
			$usageByNamespace[$ilsCode]['driveSpace'] = $diskSpace . ' GB';
		}


		$interface->assign('totalDriveSpace', $totalDriveSpace);
		$interface->assign('totalDpla', $totalDpla);
		$interface->assign('totalObjects', $totalObjects);

		$interface->assign('usageByNamespace', $usageByNamespace);

		$this->display('archiveUsage.tpl', 'Archive Usage By Library');
	}

	function getAllowableRoles() {
		return array('opacAdmin', 'archives');
	}
}
