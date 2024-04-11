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

require_once ROOT_DIR . '/sys/LocalEnrichment/FavoriteHandler.php';
require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';

/**
 * This class does not use MyResearch base class (we don't need to connect to
 * the catalog, and we need to bypass the "redirect if not logged in" logic to
 * allow public lists to work properly).
 * @version  $Revision$
 */
class MyAccount_MyList extends MyAccount {
	function __construct(){
		$this->requireLogin = false;
		parent::__construct();
	}

	function launch(){
		global $interface;

		$listId = $_REQUEST['id'];
		if (empty($listId)){
			header('Location: /MyAccount/MyLists');
			exit;
		}

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$list       = new UserList();
		$list->id   = $listId;
		$listExists = $list->find(true);
		$params = array();
		if ($listExists){
			// Ensure user has privileges to view the list
			if (!$list->public){
				if (!UserAccount::isLoggedIn()){
					require_once ROOT_DIR . '/services/MyAccount/Login.php';
					$myAccountAction = new MyAccount_Login();
					$myAccountAction->launch();
					exit();
				}

				if ($list->user_id != UserAccount::getActiveUserId()){
					//Only allow the user to view if they are admin
					if (!UserAccount::userHasRole('opacAdmin')){
						$this->display('invalidList.tpl', 'Invalid List');
						return;
					}
				}
			}

			if (isset($_SESSION['listNotes'])){ // can contain results from bulk add titles action, and is an array of strings
				$interface->assign('notes', $_SESSION['listNotes']);
				unset($_SESSION['listNotes']);
			}

			// Perform an action on the list, but verify that the user has permission to do so.
			// and load the User object for the owner of the list (if necessary):
			$userCanEdit = false;
			if (UserAccount::isLoggedIn()){
				$listUser    = UserAccount::getActiveUserObj();
				$userCanEdit = $listUser->canEditList($list);
			}


			if ($userCanEdit && (isset($_REQUEST['myListActionHead']) || isset($_REQUEST['delete']))){
				if (!empty($_REQUEST['myListActionHead'])){
					$actionToPerform = $_REQUEST['myListActionHead'];
					switch ($actionToPerform){
						case 'makePublic':
							$list->public = 1;
							$list->update();
							break;
						case 'makePrivate':
							$list->public = 0;
							$list->update();
							break;
						case 'saveList':
							$list->title       = $_REQUEST['newTitle'];
							$list->description = strip_tags($_REQUEST['newDescription']);
							if ($list->defaultSort != $_REQUEST['defaultSort'] && $_REQUEST['defaultSort'] == 'custom' ){
								// When the sort is user defined, ensure every entry in the list has a weight
								$list->initializeUserDefinedSort($list->defaultSort);
							}
							$list->defaultSort = $_REQUEST['defaultSort'];
							$list->update();
							break;
						case 'deleteList':
							$list->removeAllListEntries();
							$list->delete();
							header('Location: /MyAccount/MyLists');
							die;
						case 'deleteAll':
							$list->removeAllListEntries();
							header("Location: /MyAccount/MyList/$list->id");
							die;
						case 'deleteMarked':
							//get a list of all titles that were selected
							if (isset($_REQUEST['myListActionData'])){
								$itemsToRemove = explode(',', $_REQUEST['myListActionData']);
								foreach ($itemsToRemove as $id){
									//add back the leading . to get the full bib record
									$list->removeListEntry($id);
									$list->update();
								}
							}
							break;
						case 'bulkAddTitles':
							$notes                 = $this->bulkAddTitles($list);
							$_SESSION['listNotes'] = $notes;
							session_commit();
							break;
						case 'exportToExcel':
							self::exportToExcel($list);
							break;
					}
				}elseif (isset($_REQUEST['delete'])){
					$recordToDelete = $_REQUEST['delete'];
					$list->removeListEntry($recordToDelete);
					$list->update();
				}
				//Redirect back to avoid having the parameters stay in the URL (keeping both pagesize and current page).

				if (!empty($_REQUEST['pagesize']) && is_numeric($_REQUEST['pagesize'])){
					$params['pagesize'] = $_REQUEST['pagesize'];
				}
				if (!empty($_REQUEST['page']) && is_numeric($_REQUEST['page'])){
					$params['page'] = $_REQUEST['page'];
				}
				if (!empty($_REQUEST['sort']) && in_array($_REQUEST['sort'],array('author','title','dateAdded','recentlyAdded','custom'))){
					$params['sort'] = $_REQUEST['sort'];
				}
				if (!empty($_REQUEST['filter'])){
					$params['filter'] = $_REQUEST['filter'];
				}

				$queryString = empty($params) ? '' : '?' . http_build_query($params);
				header("Location: /MyAccount/MyList/{$list->id}" . $queryString);
				die;
			}elseif ($list->public && !empty($_REQUEST['myListActionHead'])){
				//if list is public the export to excel still needs to function
				$actionToPerform = $_REQUEST['myListActionHead'];
				switch ($actionToPerform){
					case 'exportToExcel':
						$this->exportToExcel($list);
						break;
					default:
						break;
				}
				header("Location: /MyAccount/MyList/{$list->id}");
				die();
			}

			// Send list to template so title/description can be displayed:
			if (!empty($_REQUEST['pagesize']) && is_numeric($_REQUEST['pagesize'])){
				$params['pagesize'] = $_REQUEST['pagesize'];
			}
			if (!empty($_REQUEST['page']) && is_numeric($_REQUEST['page'])){
				$params['page'] = $_REQUEST['page'];
			}
			if (!empty($_REQUEST['sort']) && in_array($_REQUEST['sort'],array('author','title','dateAdded','recentlyAdded','custom'))){
				$params['sort'] = $_REQUEST['sort'];
			}
			if (!empty($_REQUEST['filter'])){
				$params['filter'] = $_REQUEST['filter'];
			}
			$interface->assign('favList', $list);
//		$shortTitle = $list->title;
//		$interface->assign('shortPageTitle', $shortTitle);
			//shortPageTitle does get assigned in the class Action method of display()
			$interface->assign('params', $params);

			$interface->assign('listSelected', $list->id);

			// Create a handler for displaying favorites and use it to assign
			// appropriate template variables:
			$interface->assign('allowEdit', $userCanEdit);
			$favList = new FavoriteHandler($list, $userCanEdit);
			$favList->buildListForDisplay();
		}
		$interface->assign('metadataTemplate', 'MyAccount/listMetadata.tpl');
		$interface->assign('semanticData', $list);
		$this->display('../MyAccount/list.tpl', $list->title ?? 'My List', 'Search/results-sidebar.tpl');
		// this relative template path is used when an Archive object is in the list;
	}

	/**
	 * @param UserList $list
	 * @return array
	 */
	function bulkAddTitles($list){
		global $interface;
		$numAdded        = 0;
		$notes           = [];
		$listItems       = $list->numValidListItems();
		$titlesToAdd     = $_REQUEST['titlesToAdd'];
		$titleSearches[] = preg_split("/\\r\\n|\\r|\\n/", $titlesToAdd);
		$archiveEnabled  = $interface->getVariable('enableArchive') ?? false;

		foreach ($titleSearches[0] as $titleSearch){
			$titleSearch = trim($titleSearch);
			if (!empty($titleSearch)){
				$_REQUEST['lookfor'] = $titleSearch;
				$isArchiveId         = $archiveEnabled && strpos($titleSearch, ':') !== false; // Only check for archive pids if the archive is available.
				$_REQUEST['type']    = $isArchiveId ? 'IslandoraKeyword' : 'Keyword';          // Initialise from the current search globals
				$searchObject        = SearchObjectFactory::initSearchObject($isArchiveId ? 'Islandora' : 'Solr');
				if (!empty($searchObject)){
					$searchObject->setLimit(1);
					$searchObject->init();
					$searchObject->clearFacets();
					$results = $searchObject->processSearch(false, false);
					if ($results['response'] && $results['response']['numFound'] >= 1){
						$firstDoc = $results['response']['docs'][0];
						$id       = $isArchiveId ? $firstDoc['PID'] : $firstDoc['id']; //Get the id of the document
						if (($listItems + $numAdded + 1) <= 2000){
							$numAdded++;
						}
						$userListEntry                         = new UserListEntry();
						$userListEntry->listId                 = $list->id;
						$userListEntry->groupedWorkPermanentId = $id;
						$existingEntry                         = false;
						if ($userListEntry->find(true)){
							$existingEntry = true;
						}
						$userListEntry->notes     = '';
						$userListEntry->dateAdded = time();
						if ($existingEntry){
							$userListEntry->update();
						}else{
							$userListEntry->insert();
						}
					}else{
						$notes[] = "Could not find a title matching " . $titleSearch;
					}
				}
			}
		}

		//Update solr
		$list->update();

		if ($numAdded > 0){
			$notes[] = "Added $numAdded titles to the list";
		}elseif ($numAdded === 0){
			$notes[] = 'No titles were added to the list';
		}

		return $notes;
	}

	/**
	 * Exports list to Excel.
	 *
	 * @param $list : List of Books
	 *
	 * @throws Exception
	 *
	 */
	static public function exportToExcel(UserList $list){

		global $interface;
		$interface->assign('favList', $list);
		$interface->assign('listSelected', $list->id);
		$userCanEdit = false;
		if (UserAccount::isLoggedIn() && (UserAccount::getActiveUserId() == $list->user_id)){
			$listUser    = UserAccount::getActiveUserObj();
			$userCanEdit = $listUser->canEditList($list);
		}
		$favList   = new FavoriteHandler($list, $userCanEdit);
		$favorites = $favList->getTitles($list->id);


		//PHPEXCEL
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		// Set properties
		$gitBranch = $interface->getVariable('gitBranch');
		$objPHPExcel->getProperties()->setCreator('Pika ' . $gitBranch)
			->setLastModifiedBy('Pika ' . $gitBranch)
			->setTitle('Office 2007 XLSX Document')
			->setSubject('Office 2007 XLSX Document')
			->setDescription('Office 2007 XLSX, generated using PHP.')
			->setKeywords('office 2007 openxml php')
			->setCategory('List Items');
		// Set Labeled Cell Values
		$entries   = $list->getListTitles();
		$itemEntry = [];

		//create subarray with notes and dateAdded
		foreach ($entries as $entry){
			$itemEntry[$entry->groupedWorkPermanentId] = [
				'notes'     => $entry->notes,
				'dateAdded' => $entry->dateAdded,
				'weight'    => $entry->weight
			];
		}

		//create array including all data
		$itemArray  = [];
		foreach ($favorites as $listItem){
			$recordID = $listItem['id'] ?? $listItem['PID'];
			$isArchive = isset($listItem['PID']);
			$isCatalog = isset($listItem['id']);

			$title = '';
			if ($isCatalog && !empty($listItem['title_display'])){
				$title = $listItem['title_display'];
			} elseif ($isArchive && !empty($listItem['fgs_label_s'])){
				$title = $listItem['fgs_label_s'];
			}
			$author = '';
			if (!empty($listItem['author_display'])){
				$author = $listItem['author_display'];
			}

			//TODO: recordType needed?
//			$recordType = '';
//			if (!empty($listItem['recordtype'])){
//				$recordType = $listItem['recordtype'];
//			} elseif ($isArchive){
//				$recordType = 'archive';
//			}

			$type = '';
			$typeString = 'format_category_' . $GLOBALS['solrScope'];
			if ($isCatalog && isset($listItem[$typeString])){
				$type = $listItem[$typeString][0];
			} elseif ($isArchive && isset($listItem['mods_genre_s'])){
				$type = $listItem['mods_genre_s'];
			}


			$favoriteItem = [
				'Title'      => $title,
				'Author'     => $author,
//				'recordType' => $recordType,
				'Type'       => $type,
				'recordID'   => $recordID,
				'Date'       => $itemEntry[$recordID]['dateAdded'],
				'Notes'      => $itemEntry[$recordID]['notes'],
				'Weight'     => $itemEntry[$recordID]['weight']
			];
			array_push($itemArray, $favoriteItem);
		}

		self::SortByValue($itemArray, $favList->getSort());

		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', $list->title)
			->setCellValue('B1', $list->description)
			->setCellValue('A3', 'Title')
			->setCellValue('B3', 'Author')
			->setCellValue('C3', 'Notes')
			->setCellValue('D3', 'Material Type')
			->setCellValue('E3', 'Date Added')
			->setCellValue('F3', 'Id');


		$a = 4;
		foreach ($itemArray as $listItem){
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A' . $a, $listItem['Title'])
				->setCellValue('B' . $a, $listItem['Author'])
				->setCellValue('C' . $a, $listItem['Notes'])
				->setCellValue('D' . $a, $listItem['Type'])
				->setCellValue('E' . $a, date('m/d/Y g:i a', $listItem['Date']))
				->setCellValue('F' . $a, $listItem['recordID']);
			$a++;

		}

		$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);

		$strip     = ["~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
		              "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
		              "â€”", "â€“", ",", "<", ".", ">", "/", "?"];
		$listTitle = trim(str_replace($strip, "", strip_tags($list->title)));
		// Rename sheet
		$objPHPExcel->getActiveSheet()->setTitle(substr($listTitle, 0, 30));


		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="' . substr($listTitle, 0, 27) . '.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');

		exit;


	}


	/**
	 * Sorts array by provided string.
	 *
	 * @param array $array
	 * @param string $field
	 * @return array
	 */
	static private function SortByValue(array &$array, string $field){
		switch ($field){
			case "title":
				$key = "Title";
				break;
			case "author":
				$key = "Author";
				break;
			case "dateAdded":
			case   "recentlyAdded":
				$key = "Date";
				break;
			case "custom";
				$key = "Weight";
				break;
			default:
				return $array;
		}
		$sorter = [];
		$ret    = [];
		reset($array);
		foreach ($array as $ii => $va){
			$sorter[$ii] = $va[$key];
			if ($key == "custom"){
				$val = $va[$key];
				if (is_null($val)){
					$val = 0;
				}
			}else{
				$val = $va[$key];
			}
		}
		if ($field == "recentlyAdded"){
			arsort($sorter);
		}else{
			asort($sorter);
		}
		foreach ($sorter as $ii => $val){
			$ret[$ii] = $array[$ii];
		}
		$array = $ret;
	}
}
