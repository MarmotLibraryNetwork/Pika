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
		$availableDates = array_keys($indexingStatFiles);
		$today          = $availableDates[0];
		$yesterday      = $availableDates[1];
		$interface->assign('availableDates', $availableDates);
		$interface->assign('today', $today);
		$interface->assign('yesterday', $yesterday);

		if (count($indexingStatFiles) != 0){
			//Get the specified file, the file for today, or the most recent file
			$dateToRetrieve = date('Y-m-d');
			if (!empty($_REQUEST['day'])){
				$dateToRetrieve = $_REQUEST['day'];
			}
			if (isset($indexingStatFiles[$dateToRetrieve])){
				$fileToLoad = $indexingStatFiles[$dateToRetrieve];
			}else{
				$fileToLoad = reset($indexingStatFiles);
				preg_match('/reindex_stats_([\\d-]+)\\.csv/', $fileToLoad, $matches);
				$dateToRetrieve = $matches[1];
			}

			[$indexingStats, $indexingStatHeader] = $this->readIndexingStatsFile($fileToLoad);
			$interface->assign('indexingStatHeader', $indexingStatHeader);
			$interface->assign('indexingStatsDate', $dateToRetrieve);

			if (!empty($_REQUEST['compareTo'])){
				// When set we will compare differences between two days of stats
				$dateToCompare = new DateTime($_REQUEST['compareTo']);
				if ($dateToCompare){
					$isPrimaryDateOlderThanCompareDate = new DateTime($dateToRetrieve) < $dateToCompare;
					$interface->assign('pastDate', $isPrimaryDateOlderThanCompareDate ? $dateToRetrieve : $_REQUEST['compareTo']);
					$dateToRetrieve = $_REQUEST['compareTo'];
					if (isset($indexingStatFiles[$dateToRetrieve])){
						$fileToLoad = $indexingStatFiles[$dateToRetrieve];
						[$otherDayIndexingStats, $otherDayIndexingStatHeader] = $this->readIndexingStatsFile($fileToLoad);
						$columnHadChanges = $arrayOfDifferences = [];
						foreach ($indexingStats as $curRowNumber => $curRow){
							foreach ($curRow as $columnNumber => $curStat){
								if ($columnNumber == 0){
									$isSameSearchScope = $indexingStats[0][$columnNumber] == $otherDayIndexingStats[0][$columnNumber];
									// Double check that the search scopes are the same. (Search scopes can be added, deleted, or renamed)

									//The scope Name for the first column of each row
									$arrayOfDifferences[$curRowNumber][$columnNumber] = $curStat;
								}elseif ($isSameSearchScope){
									if ( isset($indexingStatHeader[$columnNumber]) && isset($otherDayIndexingStatHeader[$columnNumber]) && $indexingStatHeader[$columnNumber] == $otherDayIndexingStatHeader[$columnNumber]){
										// Double check that column labels are the same. (Columns will change as sideLoads are added or removed)

//										$difference                                       = ($isPrimaryDateOlderThanCompareDate ? $curStat - $otherDayIndexingStats[$curRowNumber][$columnNumber] : $otherDayIndexingStats[$curRowNumber][$columnNumber] - $curStat);
										$difference                                       = ($isPrimaryDateOlderThanCompareDate ? $otherDayIndexingStats[$curRowNumber][$columnNumber] - $curStat : $curStat - $otherDayIndexingStats[$curRowNumber][$columnNumber]);
										$arrayOfDifferences[$curRowNumber][$columnNumber] = $difference;
										if ($difference != 0){
											$buttonShowColumnIndex = $columnNumber - 1;
											if (!in_array($buttonShowColumnIndex, $columnHadChanges)){
												$columnHadChanges[] = $buttonShowColumnIndex;
											}
										}
									}else{
										$arrayOfDifferences[$curRowNumber][$columnNumber] = 'Column Changes';
										$buttonShowColumnIndex                            = $columnNumber - 1;
										if (!in_array($buttonShowColumnIndex, $columnHadChanges)){
											$columnHadChanges[] = $buttonShowColumnIndex;
										}
									}
								} else {
									$arrayOfDifferences[$curRowNumber][$columnNumber] = 'Scope Changes';
								}
							}

							$indexingStats = $arrayOfDifferences;
							$interface->assign('compareTo', $dateToRetrieve);
							$interface->assign('showTheseColumns', $columnHadChanges);
						}
					}
				}
			}

			$interface->assign('indexingStats', $indexingStats);
		}else{
			$interface->assign('noStatsFound', true);
		}

		$this->display('reindexStats.tpl', 'Indexing Statistics');
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'cataloging'];
	}

	/**
 * @param $fileToLoad
 * @return array
 */
	private function readIndexingStatsFile($fileToLoad): array{
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
		return [$indexingStats, $indexingStatHeader];
	}
} 
