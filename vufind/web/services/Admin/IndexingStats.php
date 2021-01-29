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
 * Displays indexing statistics for the system
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 3/16/15
 * Time: 8:41 PM
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';

class IndexingStats extends Admin_Admin {
	function launch(){
		global $interface;
		global $configArray;

		//Load the latest indexing stats
		$baseDir           = dirname($configArray['Reindex']['marcPath']);
		$indexingStatFiles = [];
		$allFilesInDir     = scandir($baseDir);
		foreach ($allFilesInDir as $curFile){
			if (preg_match('/reindex_stats_([\\d-]+)\\.csv/', $curFile, $matches)){
				$indexingStatFiles[$matches[1]] = $baseDir . '/' . $curFile;
			}
		}
		krsort($indexingStatFiles);
		$interface->assign('availableDates', array_keys($indexingStatFiles));

		if (count($indexingStatFiles) != 0){
			//Get the specified file, the file for today, or the most recent file
			$dateToRetrieve = date('Y-m-d');
			if (!empty($_REQUEST['day'])){
				$dateToRetrieve = $_REQUEST['day'];
			}
			$fileToLoad = null;
			if (isset($indexingStatFiles[$dateToRetrieve])){
				$fileToLoad = $indexingStatFiles[$dateToRetrieve];
			}else{
				$fileToLoad = reset($indexingStatFiles);
				preg_match('/reindex_stats_([\\d-]+)\\.csv/', $fileToLoad, $matches);
				$dateToRetrieve = $matches[1];
			}

			$indexingStats      = $ilsColumns = [];
			$indexingStatFhnd   = fopen($fileToLoad, 'r');
			$allIndexingHeaders = fgetcsv($indexingStatFhnd);
			$j                  = 0;
			foreach ($allIndexingHeaders as $i => $value){
				// Majority of the columns are unneeded noise so we will filter them out for display
				if (strpos($value, ' ils ') !== false){
					// Keep any columns related to the ils indexing (for physical material)
					$indexingStatHeader[] = $value;
					$ilsColumns[]         = $i;
				}elseif ($i < 3){
					// Keep the first 3 columns: scope name, works owned, total works
					$indexingStatHeader[] = $value;
				}else{
					// There are a total of 8 columns per indexing profile, creating a column counting cycle
					$j++;
					if ($j == 5){
						// only retain the total records column for sideloads (econtent)
						$indexingStatHeader[] = $value;
					}elseif ($j == 8){
						$j = 0;
					}
				}
			}

			// Now process data rows for each scope
			while ($temp = fgetcsv($indexingStatFhnd)){
				$j      = 0;
				$curRow = [];
				foreach ($temp as $i => $value){
					if ($i < 3){
						$curRow[] = $value;
					}elseif (in_array($i, $ilsColumns)){
						$curRow[] = $value;
					}else{
						$j++;
						if ($j == 5){
							$curRow[] = $value;
						}elseif ($j == 8){
							$j = 0;
						}
					}
				}
				$indexingStats[] = $curRow;
			}
			fclose($indexingStatFhnd);

			$interface->assign('indexingStatHeader', $indexingStatHeader);
			$interface->assign('indexingStats', $indexingStats);
			$interface->assign('indexingStatsDate', $dateToRetrieve);
		}else{
			$interface->assign('noStatsFound', true);
		}

		$this->display('reindexStats.tpl', 'Indexing Statistics');
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'cataloging'];
	}
} 
