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

require_once ROOT_DIR . '/services/Admin/ArchiveUsage.php';

class Admin_ArchiveUsageByContentType extends Admin_ArchiveUsage {

	private $pageTitle= 'Archive Usage By Content Type';

	function launch(){
		$usageArray  = [];
		$solrField   = 'RELS_EXT_hasModel_uri_s';
		$facetValues = $this->getSolrFacetValues($solrField);
		if (!empty($facetValues)){
			foreach ($facetValues as $facetField){
				$key          = str_replace(['info:fedora/islandora:', 'sp_', 'sp-', '_cmodel', 'CModel', 'islandora:'], '', $facetField[0]);
				$displayLabel = implode(preg_split('/(?=[A-Z])/', $key), ' '); //Split by capitals in camelcase
				$displayLabel = ucwords(str_replace('_', ' ', $displayLabel));
				[$numObjects, $bytes] = $this->getUsageByFacetValue($solrField, $facetField[0]);
				$usageArray[$key] = [
					'displayName' => $displayLabel,
					'numObjects'  => $numObjects,
					'driveSpace'  => $bytes,
				];
			}
		}

		$totalDriveSpace = 0;
		$totalObjects    = 0;
		$totalBytes      = 0;
		foreach ($usageArray as &$usageStats){
			$totalObjects               += $usageStats['numObjects'];
			$totalBytes                 += $usageStats['driveSpace'];
			$diskSpaceGB                = round($this->bytesToGigabytes($usageStats['driveSpace']), 1);
			$totalDriveSpace            += $diskSpaceGB;
			$usageStats['driveSpaceGB'] = $diskSpaceGB;
		}

		global $interface;
		$interface->assign('usageArray', $usageArray);
		$interface->assign('totalBytes', $totalBytes);
		$interface->assign('totalDriveSpace', $totalDriveSpace);
		$interface->assign('totalObjects', $totalObjects);

		$this->display('archiveUsageByContentType.tpl', $this->pageTitle);
	}
}