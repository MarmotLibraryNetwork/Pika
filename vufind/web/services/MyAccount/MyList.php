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
		global $configArray;
		global $interface;

		// Fetch List object
		$listId = $_REQUEST['id'];
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$list     = new UserList();
		$list->id = $listId;

		//QUESTION : When does this intentionally come into play?
		// It looks to be a way for users to create a list with the number of their own choosing. plb 1-25-2016
		// Pascal this would create the default "My Favorites" list if none currently exists.
		if (!$list->find(true)){
			//TODO: Use the first list?
			$list          = new UserList();
			$list->user_id = UserAccount::getActiveUserId();
			$list->public  = false;
			$list->title   = "My Favorites";
		}

		// Ensure user has privileges to view the list
		if (!isset($list) || (!$list->public && !UserAccount::isLoggedIn())){
			require_once ROOT_DIR . '/services/MyAccount/Login.php';
			$myAccountAction = new MyAccount_Login();
			$myAccountAction->launch();
			exit();
		}
		if (!$list->public && $list->user_id != UserAccount::getActiveUserId()){
			//Allow the user to view if they are admin
			if (UserAccount::isLoggedIn() && UserAccount::userHasRole('opacAdmin')){
				//Allow the user to view
			}else{
				$this->display('invalidList.tpl', 'Invalid List');
				return;
			}
		}

		if (isset($_SESSION['listNotes'])){ // can contain results from bulk add titles action, and is an array of strings
			$interface->assign('notes', $_SESSION['listNotes']);
			unset($_SESSION['listNotes']);
		}

		// Perform an action on the list, but verify that the user has permission to do so.
		// and load the User object for the owner of the list (if necessary):
		$userCanEdit = false;
		if (UserAccount::isLoggedIn() && (UserAccount::getActiveUserId() == $list->user_id)){
			$listUser    = UserAccount::getActiveUserObj();
			$userCanEdit = $listUser->canEditList($list);
		}elseif ($list->user_id != 0){
			$listUser     = new User();
			$listUser->id = $list->user_id;
			if (!$listUser->find(true)){
				$listUser = false;
			}
		}else{
			$listUser = false;
		}


		if ($userCanEdit && (isset($_REQUEST['myListActionHead']) || isset($_REQUEST['myListActionItem']) || isset($_REQUEST['delete']))){
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
						$list->defaultSort = $_REQUEST['defaultSort'];
						$list->update();
						break;
					case 'deleteList':
						$list->delete();
						header("Location: /MyAccount/Home");
						die();
						break;
					case 'bulkAddTitles':
						$notes                 = $this->bulkAddTitles($list);
						$_SESSION['listNotes'] = $notes;
						session_commit();
						break;
                    case 'exportToExcel':
                        $this->exportToExcel($list);
                        break;
				}
			}elseif (!empty($_REQUEST['myListActionItem'])){
				$actionToPerform = $_REQUEST['myListActionItem'];
				switch ($actionToPerform){
					case 'deleteMarked':
						//get a list of all titles that were selected
						$itemsToRemove = $_REQUEST['selected'];
						foreach ($itemsToRemove as $id => $selected){
							//add back the leading . to get the full bib record
							$list->removeListEntry($id);
						}
						break;
					case 'deleteAll':
						$list->removeAllListEntries();
						break;
				}
				$list->update();
			}elseif (isset($_REQUEST['delete'])){
				$recordToDelete = $_REQUEST['delete'];
				$list->removeListEntry($recordToDelete);
				$list->update();
			}
			//Redirect back to avoid having the parameters stay in the URL.
			header("Location: /MyAccount/MyList/{$list->id}");
			die();

		}

		// Send list to template so title/description can be displayed:
		$interface->assign('favList', $list);
		$interface->assign('listSelected', $list->id);

		// Create a handler for displaying favorites and use it to assign
		// appropriate template variables:
		$interface->assign('allowEdit', $userCanEdit);
		$favList = new FavoriteHandler($list, $listUser, $userCanEdit);
		$favList->buildListForDisplay();

		$this->display('../MyAccount/list.tpl', isset($list->title) ? $list->title : 'My List');
		// this relative template path is used when an Archive object is in the list;
	}

	/**
	 * @param UserList $list
	 * @return array
	 */
	function bulkAddTitles($list){
		$numAdded        = 0;
		$notes           = array();
		$titlesToAdd     = $_REQUEST['titlesToAdd'];
		$titleSearches[] = preg_split("/\\r\\n|\\r|\\n/", $titlesToAdd);

		foreach ($titleSearches[0] as $titleSearch){
			$titleSearch = trim($titleSearch);
			if (!empty($titleSearch)){
				$_REQUEST['lookfor'] = $titleSearch;
				$isArchiveId         = strpos($titleSearch, ':') !== false;
				$_REQUEST['type']    = $isArchiveId ? 'IslandoraKeyword' : 'Keyword';// Initialise from the current search globals
				$searchObject        = SearchObjectFactory::initSearchObject($isArchiveId ? 'Islandora' : 'Solr');
				if (!empty($searchObject)){
					$searchObject->setLimit(1);
					$searchObject->init();
					$searchObject->clearFacets();
					$results = $searchObject->processSearch(false, false);
					if ($results['response'] && $results['response']['numFound'] >= 1){
						$firstDoc = $results['response']['docs'][0];
						//Get the id of the document
						$id = $isArchiveId ? $firstDoc['PID'] : $firstDoc['id'];
						$numAdded++;
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
    public function exportToExcel(UserList $list){
        global $interface;
        $interface->assign('favList', $list);
        $interface->assign('listSelected', $list->id);
        $userCanEdit = false;
        if (UserAccount::isLoggedIn() && (UserAccount::getActiveUserId() == $list->user_id)){
            $listUser = UserAccount::getActiveUserObj();
            $userCanEdit = $listUser->canEditList($list);
        }elseif ($list->user_id != 0){
            $listUser     = new User();
            $listUser->id = $list->user_id;
            if (!$listUser->find(true)){
                $listUser = false;
            }
        }else{
            $listUser = false;
        }
        $favList = new FavoriteHandler($list, $listUser, $userCanEdit);
        $favorites = $favList->getTitles($list->id);


        //PHPEXCEL
        // Create new PHPExcel object
       $objPHPExcel = new PHPExcel();

        // Set properties
        $objPHPExcel->getProperties()->setCreator("DCL")
            ->setLastModifiedBy("DCL")
            ->setTitle("Office 2007 XLSX Document")
            ->setSubject("Office 2007 XLSX Document")
            ->setDescription("Office 2007 XLSX, generated using PHP.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("List Items");
        // Set Labeled Cell Values
        $entries = $list->getListTitles();
        $itemEntry =[];

        //create subarray with notes and dateAdded
        foreach($entries as $entry)
        {
            $itemEntry [$entry->groupedWorkPermanentId] = ["notes" => $entry->notes, "dateAdded" => $entry->dateAdded, "weight" => $entry->weight];
        }

        //create array including all data
                $itemArray =[];
                foreach($favorites as $listItem)
                {
                    $title = "";
                    if(!is_null($listItem['title_display'])) {
                        $title = $listItem['title_display'];
                    }
                    $author = "";
                    if(!is_null($listItem['author_display'])){
                        $author = $listItem['author_display'];
                    }
                    $recordType = "";
                    if(!is_null($listItem['recordtype'])){
                    $recordType = $listItem['recordtype'];
                    }
                    $recordID = $listItem['id'];

                    $favoriteItem = ["Title"=> $title, "Author"=>$author, "recordType"=>$recordType, "recordID"=>$recordID, "Date"=>$itemEntry[$recordID]['dateAdded'], "Notes"=>$itemEntry[$recordID]['notes'], "Weight"=>$itemEntry[$recordID]['weight']];
                    array_push($itemArray, $favoriteItem);
                }

              $this->SortByValue($itemArray,$favList->getSort());



        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', $list->title)
            ->setCellValue('B1', $list->description)
            ->setCellValue('A3', 'Title')
            ->setCellValue('B3', 'Author')
            ->setCellValue('C3', 'Notes')
            ->setCellValue('D3', 'Date Added');


                $a = 4;
                foreach ($itemArray as $listItem)
                {
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue('A' . $a, $listItem['Title'])
                        ->setCellValue('B' . $a, $listItem['Author'])
                        ->setCellValue('C' . $a, $listItem['Notes'])
                        ->setCellValue('D' . $a, date('m/d/Y g:i a', $listItem['Date']));
                    $a++;

                }

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);

        // Rename sheet
        $objPHPExcel->getActiveSheet()->setTitle('Favorites List -' . $list->title);

        // Redirect output to a client's web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="favorites_' . $list->title . '.xls"');
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
    private function SortByValue(array &$array, string $field)
    {
        $key = "title";
        switch($field)
        {
            case "title":
                $key = "Title";
                break;
            case "author":
                $key = "Author";
            case "dateAdded":
            case   "recentlyAdded":
                $key ="Date";
                break;
            case "custom";
                $key = "Weight";
                break;
            default:
                return $array;
                break;
        }
        $sorter=array();
        $ret=array();
        reset($array);
        foreach ($array as $ii => $va) {
            $sorter[$ii]=$va[$key];
            if($key == "custom")
            {
                $val = $va[$key];
                if (is_null($val))
                  $val = 0;

            }
            else $val = $va[$key];

        }
        if($field == "recentlyAdded")
        {
            arsort($sorter);
        }
        else{

            asort($sorter);
        }
        foreach ($sorter as $ii => $val) {
            $ret[$ii]=$array[$ii];
        }
        $array=$ret;
    }
}
