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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Grouping/GroupedWorkVersionMap.php';
require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
require_once ROOT_DIR . '/sys/Grouping/MergedGroupedWork.php';


class Admin_GroupedWorkVersionFourToFiveMigration extends ObjectEditor {

	function launch(){

		$objectAction = $_REQUEST['objectAction'] ?? null;
		switch ($objectAction){
			case 'buildMap':
				$this->buildMapping();
				break;

			case 'migrateLibrarianReviews' :
				// Librarian Reviews
				require_once ROOT_DIR . '/sys/LocalEnrichment/LibrarianReview.php';
				$this->updateUserData(new LibrarianReview());
				break;

			case 'migrateUserTags':
				// User Tags
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserTag.php';
				$this->updateUserData(new UserTag());
				break;

			case 'migrateUserNotInterested':
				// User Not Interested
				require_once ROOT_DIR . '/sys/LocalEnrichment/NotInterested.php';
				$this->updateUserData(new NotInterested(), true);
				break;

			case 'migrateUserReviews':
				// User Rating/Reviews
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserWorkReview.php';
				$this->updateUserData(new UserWorkReview(), true);
				break;

			case 'migrateUserLists':
				// User List Entries
				require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
				$this->updateUserListEntries(new UserListEntry(), true);
				break;

			case 'migrateUserReadingHistory':
				//Reading History
				require_once ROOT_DIR . '/sys/Account/ReadingHistoryEntry.php';
				$this->updateReadingHistoryEntries(new ReadingHistoryEntry());
				break;

			default :
				parent::launch();
		}

	}

	public function customListActions(){
		$actions[] = [
			'label'  => 'Build Version Mapping',
			'action' => 'buildMap',
		];
		$actions[] = [
			'label'  => 'Migrate Librarian Reviews',
			'action' => 'migrateLibrarianReviews',
		];
		$actions[] = [
			'label'  => 'Migrate User Tags',
			'action' => 'migrateUserTags',
		];
		$actions[] = [
			'label'  => 'Migrate User Not Interested',
			'action' => 'migrateUserNotInterested',
		];
		$actions[] = [
			'label'  => 'Migrate User Reviews',
			'action' => 'migrateUserReviews',
		];
		$actions[] = [
			'label'  => 'Migrate User Lists',
			'action' => 'migrateUserLists',
		];
		$actions[] = [
			'label'  => 'Migrate User Reading History',
			'action' => 'migrateUserReadingHistory',
		];

		return $actions;
	}


	//First version
//	private function updateUserData(DB_DataObject $userDataObject, bool $deleteNoMatches = false){
//		$updated    = 0;
//		$deleted    = 0;
//		$objectName = get_class($userDataObject);
//		if ($total = $userDataObject->find()){
//			while ($userDataObject->fetch()){
//				if (!empty($userDataObject->groupedWorkPermanentId)){
//					$map                                 = new GroupedWorkVersionMap();
//					$map->groupedWorkPermanentIdVersion4 = $userDataObject->groupedWorkPermanentId;
//					if ($map->find(true)){
//						if (!empty($map->groupedWorkPermanentIdVersion5)){
//							$userDataObject->groupedWorkPermanentId = $map->groupedWorkPermanentIdVersion5;
//							if ($userDataObject->update()){
//								$output .= "<p>Updated $objectName id {$userDataObject->id}.</p>\n";
//								$updated++;
//							}else{
//								$output .= "<p>Failed to update $objectName id {$userDataObject->id}.<br>" . $userDataObject->_lastError . "</p>\n";
//							}
//						}else{
//							$output .= "<p>$objectName id {$userDataObject->id} grouped Work Id {$userDataObject->groupedWorkPermanentId} has no new version Id in version map.</p>\n";
//							if ($deleteNoMatches){
//								if ($userDataObject->delete()){
//									$output .= "<p>Deleted $objectName id {$userDataObject->id}</p>\n";
//									$deleted++;
//								}else{
//									$output .= "<p>Failed to delete $objectName id {$userDataObject->id}</p>\n";
//								}
//							}
//						}
//					}else{
//						$output .= "<p>$objectName id {$userDataObject->id} grouped Work Id {$userDataObject->groupedWorkPermanentId} was not found in version map.</p>\n";
//					}
//				}else{
//					$output .= "<p>$objectName id {$userDataObject->id} had no grouped Work Id.</p>\n";
//				}
//			}
//		}
//		$output .= "<p>Updated $updated of $total " . $objectName . ($deleteNoMatches ? " Deleted $deleted" : ''). "</p>";
//	}

	private function updateUserData(DB_DataObject $userDataObject, bool $deleteNoMatches = false){
		$output     = '';
		$updated    = 0;
		$deleted    = 0;
		$objectName = get_class($userDataObject);
		$userDataObject->selectAdd();
		$userDataObject->selectAdd('id, groupedWorkPermanentId, groupedWorkPermanentIdVersion5');
		$userDataObject->joinAdd(['groupedWorkPermanentId', 'grouped_work_versions_map:groupedWorkPermanentIdVersion4'], 'LEFT');
		if ($total = $userDataObject->find()){
			while ($userDataObject->fetch()){
				if (!empty($userDataObject->groupedWorkPermanentId)){ //TODO: ignore archive pids
					if (!empty($userDataObject->groupedWorkPermanentIdVersion5)){
						$query = "UPDATE {$userDataObject->__table} SET groupedWorkPermanentId = '{$userDataObject->groupedWorkPermanentIdVersion5}' WHERE (  {$userDataObject->__table}.id = {$userDataObject->id} )";
						if ($userDataObject->query($query)){
//								$output .= "<p class='alert alert-info'>Updated $objectName id {$userDataObject->id}.</p>\n";
							$updated++;
						}else{
							$output .= "<p class='alert alert-warning'>Failed to update $objectName id {$userDataObject->id}.<br>" . $userDataObject->_lastError . "</p>\n";
						}
					}else{
//						$output .= "<p class='alert alert-info'>$objectName id {$userDataObject->id} grouped Work Id {$userDataObject->groupedWorkPermanentId} has no new version Id in version map.</p>\n";
						if ($deleteNoMatches){
							$query = "DELETE FROM {$userDataObject->__table}  WHERE (  {$userDataObject->__table}.id = {$userDataObject->id} ) LIMIT 1";
							if ($userDataObject->query($query)){
//								$output .= "<p class='alert alert-info'>Deleted $objectName id {$userDataObject->id}</p>\n";
								$deleted++;
							}else{
								$output .= "<p class='alert alert-warning'>Failed to delete $objectName id {$userDataObject->id}</p>\n";
							}
						}
					}
				}else{
					$output .= "<p class='alert alert-warning'>$objectName id {$userDataObject->id} had no grouped Work Id.</p>\n";
				}
			}
		}
		$output .= "<p class='alert alert-success'>Updated $updated of $total " . $objectName . ($deleteNoMatches ? " Deleted $deleted" : '') . "</p>";
		global $interface;
		$interface->assign('output', $output);
		$this->display('groupedWorkMigration.tpl');
	}

	private function updateUserListEntries(UserListEntry $userDataObject, bool $deleteNoMatches = false){
		$output     = '';
		$updated    = 0;
		$deleted    = 0;
		$objectName = get_class($userDataObject);
		$userDataObject->selectAdd();
		$userDataObject->selectAdd('id, groupedWorkPermanentId, groupedWorkPermanentIdVersion5');
		$userDataObject->joinAdd(['groupedWorkPermanentId', 'grouped_work_versions_map:groupedWorkPermanentIdVersion4'], 'LEFT');
		if ($total = $userDataObject->find()){
			while ($userDataObject->fetch()){
				if (!empty($userDataObject->groupedWorkPermanentId)){ //TODO: ignore archive pids
					if (!empty($userDataObject->groupedWorkPermanentIdVersion5)){
						$query = "UPDATE {$userDataObject->__table} SET groupedWorkPermanentId = '{$userDataObject->groupedWorkPermanentIdVersion5}' WHERE (  {$userDataObject->__table}.id = {$userDataObject->id} )";
						if ($userDataObject->query($query)){
//								$output .= "<p class='alert alert-info'>Updated $objectName id {$userDataObject->id}.</p>\n";
							$updated++;
						}else{
							$output .= "<p class='alert alert-warning'>Failed to update $objectName id {$userDataObject->id}.<br>" . $userDataObject->_lastError . "</p>\n";
						}
					}else{
//						$output .= "<p class='alert alert-info'>$objectName id {$userDataObject->id} grouped Work Id {$userDataObject->groupedWorkPermanentId} has no new version Id in version map.</p>\n";
						if ($deleteNoMatches){
							$query = "DELETE FROM {$userDataObject->__table}  WHERE (  {$userDataObject->__table}.id = {$userDataObject->id} ) LIMIT 1";
							if ($userDataObject->query($query)){
//								$output .= "<p class='alert alert-info'>Deleted $objectName id {$userDataObject->id}</p>\n";
								$deleted++;
							}else{
								$output .= "<p class='alert alert-warning'>Failed to delete $objectName id {$userDataObject->id}</p>\n";
							}
						}
					}
				}else{
					$output .= "<p class='alert alert-warning'>$objectName id {$userDataObject->id} had no grouped Work Id.</p>\n";
				}
			}
		}
		$output .= "<p class='alert alert-success'>Updated $updated of $total " . $objectName . ($deleteNoMatches ? " Deleted $deleted" : '') . "</p>";
		global $interface;
		$interface->assign('output', $output);
		$this->display('groupedWorkMigration.tpl');
	}

	private function updateReadingHistoryEntries(ReadingHistoryEntry $userDataObject){
		$output                           = '';
		$updated                          = 0;
		$updatedByBibMatch                = 0;
		$updatedByMapMatch                = 0;
		$removedGroupedWorkIdDueToNoMatch = 0;
		$noGroupedWorkId                  = 0;
		$objectName                       = get_class($userDataObject);

		$userDataObject->query("
		SELECT 
user_reading_history_work.id,
groupedWorkPermanentId
#, source
#,if(source = 'hoopla',concat('MWT', sourceId), sourceId) AS readingHistoryEnhanceSourceId
#,type, identifier
,permanent_id
,groupedWorkPermanentIdVersion5
FROM user_reading_history_work
LEFT JOIN grouped_work_primary_identifiers ON (source = type AND identifier = if(source = 'hoopla',concat('MWT', sourceId), sourceId))
LEFT JOIN grouped_work ON (grouped_work_primary_identifiers.grouped_work_id = grouped_work.id)
LEFT JOIN grouped_work_versions_map ON (groupedWorkPermanentId = groupedWorkPermanentIdVersion4)
WHERE groupedWorkPermanentId != ''
");


		if ($total = $userDataObject->N){
			while ($userDataObject->fetch()){
				if (!empty($userDataObject->groupedWorkPermanentId)){
					if (!empty($userDataObject->permanent_id)){
						// Match by Bib Ids
						$query = "UPDATE {$userDataObject->__table} SET groupedWorkPermanentId = '{$userDataObject->permanent_id}' WHERE (  {$userDataObject->__table}.id = {$userDataObject->id} )";
						$updatedByBibMatch++;
					}elseif (!empty($userDataObject->groupedWorkPermanentIdVersion5)){
						// Match by Grouped Work Version Mapping
						$query = "UPDATE {$userDataObject->__table} SET groupedWorkPermanentId = '{$userDataObject->groupedWorkPermanentIdVersion5}' WHERE (  {$userDataObject->__table}.id = {$userDataObject->id} )";
						$updatedByMapMatch++;
					}else{
						$query = "UPDATE {$userDataObject->__table} SET groupedWorkPermanentId = '' WHERE (  {$userDataObject->__table}.id = {$userDataObject->id} )";
						$removedGroupedWorkIdDueToNoMatch++;
					}

					$readingHistory = new ReadingHistoryEntry();
					if ($readingHistory->query($query)){
//						$output .= "<p class='alert alert-info'>Updated $objectName id {$userDataObject->id}.</p>\n";
						$updated++;
					}else{
						$output .= "<p class='alert alert-warning'>Failed to update $objectName id {$userDataObject->id}.<br>$query<br>" . $userDataObject->_lastError . "</p>\n";
					}

				}else{
					$noGroupedWorkId++;
//					$output .= "<p class='alert alert-warning'>$objectName id {$userDataObject->id} had no grouped Work Id.</p>\n";
				}
			}
		}
		$output .= "<p class='alert alert-success'>Updated $updated of $total <br>\n"
			. " Did not have a grouped work Id to start with: $noGroupedWorkId<br>\n"
			. " Updated by bib: $updatedByMapMatch<br>\n"
			. " Updated by Version map: $updatedByMapMatch<br>\n"
			. " Removed grouped work id due to no match: $updatedByMapMatch<br>\n"
			. "</p>";
		global $interface;
		$interface->assign('output', $output);
		$this->display('groupedWorkMigration.tpl');
	}


	private function buildMapping(){
		$output = '';
		$mapper = new GroupedWorkVersionMap();
		$mapper->joinAdd(['groupedWorkPermanentIdVersion4', 'grouped_work_old:permanent_id']);
		$mapper->whereAdd("groupedWorkPermanentIdVersion4 IS NOT NULL AND groupedWorkPermanentIdVersion5 IS NULL");
		if ($mapper->find()){
			$totalToMap  = $mapper->N;
			$totalMapped = 0;
			while ($mapper->fetch()){
				// Map to potential version 5 Ids via related record matching
				$groupedWorkMatching = new GroupedWorkVersionMap();
				$groupedWorkMatching->query("
SELECT
COUNT(DISTINCT grouped_work.permanent_id) AS newIDcount
, group_concat(DISTINCT grouped_work.permanent_id) AS newIDs
, group_concat(DISTINCT grouped_work.grouping_category) AS newGroupingCategories
, group_concat(DISTINCT grouped_work.grouping_language) AS newGroupingLanguages

, group_concat(DISTINCT grouped_work.author ORDER BY grouped_work_primary_identifiers.identifier) AS newGroupingAuthors
#, group_concat(DISTINCT grouped_work.full_title ORDER BY grouped_work_primary_identifiers.identifier) AS newGroupingTitles

#, group_concat(DISTINCT grouped_work_primary_identifiers.type, ':', grouped_work_primary_identifiers.identifier ORDER BY grouped_work_primary_identifiers.identifier) AS newPrimaryIdentifiers

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
										$output .= "<p class='alert alert-warning'>Did not update grouped work version 4 map entry : " + $mapper->groupedWorkPermanentIdVersion4 + "<p>\n";
									}else{
										$output .= "<p class='alert alert-success'>Mapping V4 {$mapper->groupedWorkPermanentIdVersion4} to V5 $groupingID</p>";
										$totalMapped++;
										break;
									}
								}
							}
							$output .= '<hr>';

							// Multiple Languages Scenario (Use the english work)
						}elseif (count($newGroupingCategories) == 1 && count($newGroupingLanguages) > 1){
							foreach ($newGroupingIDs as $groupingID){
								$groupedWork = new GroupedWork();
								$groupedWork->get('permanent_id', $groupingID);
								if ($groupedWork->grouping_language == 'eng'){
									$mapper->groupedWorkPermanentIdVersion5 = $groupingID;
									if (!$mapper->update()){
										//Failed to update Map
										$output .= "<p class='alert alert-warning'>Did not update grouped work version 4 map entry : " + $mapper->groupedWorkPermanentIdVersion4;
									}else{
										$output .= "<p class='alert alert-success'>Mapping V4 {$mapper->groupedWorkPermanentIdVersion4} to V5 $groupingID</p>";
										$totalMapped++;
										break;
									}
								}
							}
							$output .= '<hr>';

							// Multiple Languages and new comic grouping category scenario (use the english book version)
						}elseif (in_array('comic', $newGroupingCategories) && in_array('eng', $newGroupingLanguages)){
							foreach ($newGroupingIDs as $groupingID){
								$groupedWork = new GroupedWork();
								$groupedWork->get('permanent_id', $groupingID);
								if ($groupedWork->grouping_language == 'eng' && $groupedWork->grouping_category == 'book'){
									$mapper->groupedWorkPermanentIdVersion5 = $groupingID;
									if (!$mapper->update()){
										//Failed to update Map
										$output .= "<p class='alert alert-warning'>Did not update grouped work version 4 map entry : " + $mapper->groupedWorkPermanentIdVersion4;
									}else{
										$output .= "<p class='alert alert-success'>Mapping V4 {$mapper->groupedWorkPermanentIdVersion4} to V5 $groupingID</p>";
										$totalMapped++;
										break;
									}
								}
							}
							$output .= '<hr>';

						}elseif (count($newGroupingCategories) == 1 && count($newGroupingLanguages) == 1 && $newGroupingCategories != ['movie']){
							$newGroupingAuthors = explode(',', $groupedWorkMatching->newGroupingAuthors);
							if (count($newGroupingAuthors) == 1){
								$output .= "<div class='well'>";
								$output .= '<table class="table table-bordered">';
//								$newPrimaryIdentifiers = explode(',', $groupedWorkMatching->newPrimaryIdentifiers);
								$groupingTitles = [];
								foreach ($newGroupingIDs as $groupingID){
									$groupedWork = new GroupedWork();
									$groupedWork->get('permanent_id', $groupingID);
									$groupingTitles[$groupedWork->full_title] = $groupingID;
									$relatedRecordIds                         = new GroupedWorkPrimaryIdentifier();
									$relatedRecordIds->grouped_work_id        = $groupedWork->id;
									$relatedRecordIds->selectAdd();
									$relatedRecordIds->selectAdd('concat(type, ":", identifier) AS fullIdentifier');
									$records = $relatedRecordIds->fetchAll('fullIdentifier');
//									$output  .= "<p>" . $groupedWork->full_title . " -- " . $groupedWork->author . "<span style='float: right'>" . implode(', ', $records) . ' | ' . $groupingID . "</span></p>\n";
//									$output .= "<div class='row'><div class='col-tn-7'>" . $groupedWork->full_title . " -- " . $groupedWork->author . "</div><div class='col-tn-2'>" . implode(', ', $records) . "</div><div class='col-tn-3'>$groupingID</div></div>";
									$output .= "<tr><td>" . $groupedWork->full_title . " -- " . $groupedWork->author . "</td><td>" . implode(', ', $records) . "</td><td>$groupingID</td></tr>";
								}
								ksort($groupingTitles);
								$shortestTitle      = array_key_first($groupingTitles);
								$idForShortestTitle = array_shift($groupingTitles);
								$mergeWorks         = true;

								foreach ($groupingTitles as $title => $id_ignored){
									if (strlen($shortestTitle) > strlen($title) || strpos($title, $shortestTitle) === false){
										// Not candidate for merging if the title does not start with the shortest title (implying different titles rather than subtitle removal)
										$mergeWorks = false;
									}
								}
								$output .= '</table>';

								if ($mergeWorks){
									$output .= "Merge " . implode(', ', array_values($groupingTitles)) . " into target work " . $idForShortestTitle;
									foreach ($groupingTitles as $title => $workIdToMerge){
										$mergeWorkEntry                           = new MergedGroupedWork();
										$mergeWorkEntry->sourceGroupedWorkId      = $workIdToMerge;
										$mergeWorkEntry->destinationGroupedWorkId = $idForShortestTitle;
										$mergeWorkEntry->userId                   = UserAccount::getActiveUserId();
										$mergeWorkEntry->notes                    = "Merge title '$title' to '$shortestTitle'\n\n" . implode(', ', $records) . "\n\nGrouped Work Version Migration  " . date('n/j/y');
										if (!$mergeWorkEntry->insert()){
											$output .= "<p class='alert alert-warning'>Failed to add grouped work merging for source ID $workIdToMerge <p>\n";
										}
									}
									$mapper->groupedWorkPermanentIdVersion5 = $idForShortestTitle;
									if (!$mapper->update()){
										//Failed to update Map
										$output .= "<p class='alert alert-warning'>Did not update grouped work version 4 map entry : " + $mapper->groupedWorkPermanentIdVersion4;
									}else{
										$totalMapped++;
									}
								}else{
									$output .= "Did not merge";
								}
								$output .= '</div>';

							}
						}
					}
				}

			}
			$output .= "<p class='alert alert-success'>Mapped $totalMapped out of $totalToMap</p>\n";
			global $interface;
			$interface->assign('output', $output);
			$this->display('groupedWorkMigration.tpl');
		}


	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	function getObjectType(){
		// TODO: Implement getObjectType() method.
	}

	function getToolName(){
		// TODO: Implement getToolName() method.
	}

	function getPageTitle(){
		return 'Grouped Work Version Four To Five Migration';
	}

	function getObjectStructure(){
		return [];
	}

	function getPrimaryKeyColumn(){
		// TODO: Implement getPrimaryKeyColumn() method.
	}

	function getIdKeyColumn(){
		// TODO: Implement getIdKeyColumn() method.
	}

	function getAllObjects($orderBy = null){
		return [];
	}

	public function canAddNew(){
		return false;
	}

	public function canDelete(){
		return false;
	}


}