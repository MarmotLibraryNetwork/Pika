<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
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

require_once ROOT_DIR . '/services/API/ArchiveAPI.php';
require_once ROOT_DIR . '/services/Admin/ArchiveUsage.php';

class Admin_ArchiveUsageByNamespace extends Admin_ArchiveUsage {
	private $pageTitle  = 'Archive Usage By Name Space';

	function launch(){
		$usageArray  = [];
		$solrField   = 'namespace_s';
		$facetValues = $this->getSolrFacetValues($solrField);
		if (!empty($facetValues)){
			foreach ($facetValues as $facetField){
				$key          = $facetField[0];
				$translated   = translate($key);
				$displayLabel = ($translated == $key) ? '' : $translated; // only populate if we have a translation
				[$numObjects, $bytes] = $this->getUsageByFacetValue($solrField, $facetField[0]);
				$usageArray[$key] = [
					'displayName' => $displayLabel,
					'nameSpace'   => $key,
					'numObjects'  => $numObjects,
					'driveSpace'  => $bytes,
				];
			}
		}

		// Get the number of objects contributed to DPLA
		$archiveAPI = new API_ArchiveAPI();
		$dplaUsage  = $archiveAPI->getDPLACounts();

		$totalDriveSpace = 0;
		$totalObjects    = 0;
		$totalBytes      = 0;
		$totalDpla       = 0;
		foreach ($usageArray as $namespace => &$usageStats){
			$totalObjects               += $usageStats['numObjects'];
			$totalBytes                 += $usageStats['driveSpace'];
			$diskSpaceGB                = round($this->bytesToGigabytes($usageStats['driveSpace']), 1);
			$totalDriveSpace            += $diskSpaceGB;
			$usageStats['driveSpaceGB'] = $diskSpaceGB;
			$usageStats['numDpla']      = $dplaUsage[$namespace] ?? 0;
			$totalDpla                  += $usageStats['numDpla'];
		}

		global $interface;
		$interface->assign('usageArray', $usageArray);
		$interface->assign('totalBytes', $totalBytes);
		$interface->assign('totalDriveSpace', $totalDriveSpace);
		$interface->assign('totalObjects', $totalObjects);
		$interface->assign('totalDpla', $totalDpla);

		$this->display('archiveUsageByNamespace.tpl', $this->pageTitle);
	}
}