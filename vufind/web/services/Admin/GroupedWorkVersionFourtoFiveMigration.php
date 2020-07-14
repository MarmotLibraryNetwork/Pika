<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 7/10/2020
 *
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Grouping/GroupedWorkVersionMap.php';
require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';

class Admin_GroupedWorkVersionFourToFiveMigration extends Admin_Admin {

	function launch(){
		$mapper = new GroupedWorkVersionMap();
		$mapper->joinAdd(['groupedWorkPermanentIdVersion4', 'grouped_work_old:permanent_id']);
		$mapper->whereAdd("groupedWorkPermanentIdVersion4 IS NOT NULL AND groupedWorkPermanentIdVersion5 IS NULL");
		if ($mapper->find()){
			$totalToMap = $mapper->N;
			$totalMapped = 0;
			while ($mapper->fetch()){
				// Map to potential version 5 Ids via related record matching
				$groupedWorkMatching = new GroupedWorkVersionMap();
				$groupedWorkMatching->query("
SELECT
COUNT(DISTINCT grouped_work.permanent_id) AS newIDcount
, group_concat(DISTINCT grouped_work.permanent_id SEPARATOR ',') AS newIDs
, group_concat(DISTINCT grouped_work.grouping_category SEPARATOR ',') AS newGroupingCategories
, group_concat(DISTINCT grouped_work.grouping_language SEPARATOR ',') AS newGroupingLanguages


FROM grouped_work_versions_map

LEFT JOIN grouped_work_old ON (grouped_work_versions_map.groupedWorkPermanentIdVersion4 = grouped_work_old.permanent_id)
LEFT JOIN grouped_work_primary_identifiers_old ON (grouped_work_primary_identifiers_old.grouped_work_id = grouped_work_old.id)
LEFT JOIN grouped_work_primary_identifiers ON (grouped_work_primary_identifiers.identifier = grouped_work_primary_identifiers_old.identifier AND grouped_work_primary_identifiers.type = grouped_work_primary_identifiers_old.type)
LEFT JOIN grouped_work ON (grouped_work_primary_identifiers.grouped_work_id = grouped_work.id)

WHERE groupedWorkPermanentIdVersion4 = '{$mapper->groupedWorkPermanentIdVersion4}' 
GROUP BY grouped_work_old.permanent_id
				");
				if ($groupedWorkMatching->fetch()){
					$numNewIDs             = $groupedWorkMatching->newIDcount;
					$newGroupingCategories = explode(',', $groupedWorkMatching->newGroupingCategories);
					$newGroupingLanguages  = explode(',', $groupedWorkMatching->newGroupingLanguages);
					$newGroupingIDs        = explode(',', $groupedWorkMatching->newIDs);
					if ($numNewIDs > 1){

							//Graphic Novel Scenario (Use the book version of the grouped work)
						if (count($newGroupingLanguages) == 1 && count($newGroupingCategories) > 1){
							foreach ($newGroupingIDs as $groupingID){
								$groupedWork = new GroupedWork();
								$groupedWork->get('permanent_id', $groupingID);
								if ($groupedWork->grouping_category == 'book'){
									$mapper->groupedWorkPermanentIdVersion5 = $groupingID;
									if (!$mapper->update()){
										//Failed to update Map
										echo  "Did not update grouped work version 4 map entry : " + $mapper->groupedWorkPermanentIdVersion4 + "\n";
									} else {
										$totalMapped++;
										break;
									}
								}
							}

							// Multiple Languages Scenario (Use the english work)
						} elseif (count($newGroupingCategories) == 1 && count($newGroupingLanguages) > 1){
							foreach ($newGroupingIDs as $groupingID){
								$groupedWork = new GroupedWork();
								$groupedWork->get('permanent_id', $groupingID);
								if ($groupedWork->grouping_language == 'eng'){
									$mapper->groupedWorkPermanentIdVersion5 = $groupingID;
									if (!$mapper->update()){
										//Failed to update Map
										echo  "Did not update grouped work version 4 map entry : " + $mapper->groupedWorkPermanentIdVersion4 + "\n";
									} else {
										$totalMapped++;
										break;
									}
								}
							}
						// Multiple Languages and new comic grouping category scenario (use the english book version)
						} elseif (in_array('comic', $newGroupingCategories) && in_array('eng', $newGroupingLanguages)){
							foreach ($newGroupingIDs as $groupingID){
								$groupedWork = new GroupedWork();
								$groupedWork->get('permanent_id', $groupingID);
								if ($groupedWork->grouping_language == 'eng' && $groupedWork->grouping_category == 'book'){
									$mapper->groupedWorkPermanentIdVersion5 = $groupingID;
									if (!$mapper->update()){
										//Failed to update Map
										echo  "Did not update grouped work version 4 map entry : " + $mapper->groupedWorkPermanentIdVersion4 + "\n";
									} else {
										$totalMapped++;
										break;
									}
								}
							}



					}
//					elseif ($numNewIDs == 0){
//
					}
				}

			}
			echo "mapped $totalMapped out of $totalToMap\n";
		}


	}

	function getAllowableRoles() {
		return ['opacAdmin'];
	}
}