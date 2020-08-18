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

class MyAccount_MyLists extends MyAccount{
    function __construct(){
        $this->requireLogin = true;
        parent::__construct();
        $this->cache = new Pika\Cache();
    }

    function launch()
    {
        global $configArray;
        global $interface;
        require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
        if (UserAccount::isLoggedIn()) {
            $user = UserAccount::getLoggedInUser();
        $staffUser = $user->isStaff();

            //Load a list of lists
            $userListsData = $this->cache->get('user_lists_data_' . UserAccount::getActiveUserId());
            if ($userListsData == null || isset($_REQUEST['reload'])) {
                $myLists = array();
                require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
                $tmpList = new UserList();
                $tmpList->user_id = UserAccount::getActiveUserId();
                $tmpList->deleted = 0;
                $tmpList->orderBy("title ASC");
                $defaultSort = "Title";
                if(!empty($tmp->defaultSort)){
                    $defaultSort = $tmp->defaultSort;
                }

                $tmpList->find();
                if ($tmpList->N > 0) {
                    while ($tmpList->fetch()) {
                        $myLists[$tmpList->id] = array(
                            'name' => $tmpList->title,
                            'url' => '/MyAccount/MyList/' . $tmpList->id,
                            'id' => $tmpList->id,
                            'numTitles' => $tmpList->numValidListItems(),
                            'description' => $tmpList->description,
                            'defaultSort' => $defaultSort,
                            'isPublic' => $tmpList->public,
                        );
                    }
                }
                //$this->cache->set('user_list_data_' . UserAccount::getActiveUserId(), $lists, $configArray['Caching']['user']);
                //$timer->logTime("Load Lists");
            } else {
                $myLists = $userListsData;
                //$timer->logTime("Load Lists from cache");
            }
            if (!empty($_REQUEST['myListActionHead'])){
                $actionToPerform = $_REQUEST['myListActionHead'];
                switch ($actionToPerform){

                    case 'exportToExcel':
                        $listId = $_REQUEST['myListActionData'];
                        $list = new UserList();
                        $list->get('id', $listId);
                        $this->exportToExcel($list);
                        break;
                }
            }

            $interface->assign('myLists', $myLists);
            $interface->assign('staff', $staffUser);

            $this->display('../MyAccount/myLists.tpl', 'My Lists');
        }
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
        if (UserAccount::isLoggedIn() && (UserAccount::getActiveUserId() == $list->user_id)) {
            $listUser = UserAccount::getActiveUserObj();
            $userCanEdit = $listUser->canEditList($list);
        } elseif ($list->user_id != 0) {
            $listUser = new User();
            $listUser->id = $list->user_id;
            if (!$listUser->find(true)) {
                $listUser = false;
            }
        } else {
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
        $itemEntry = [];

        //create subarray with notes and dateAdded
        foreach ($entries as $entry) {
            $itemEntry [$entry->groupedWorkPermanentId] = ["notes" => $entry->notes, "dateAdded" => $entry->dateAdded, "weight" => $entry->weight];
        }

        //create array including all data
        $typeString = "format_category_" . $GLOBALS['solrScope'];
        $itemArray = [];
        foreach ($favorites as $listItem) {
            $title = "";
            if (!is_null($listItem['title_display'])) {
                $title = $listItem['title_display'];
            }
            $author = "";
            if (!is_null($listItem['author_display'])) {
                $author = $listItem['author_display'];
            }
            $recordType = "";
            if (!is_null($listItem['recordtype'])) {
                $recordType = $listItem['recordtype'];
            }
            $type="";
            if(isset($listItem[$typeString]))
            {
                $type = $listItem[$typeString][0];
            }

            $recordID = $listItem['id'];

            $favoriteItem = ["Title" => $title, "Author" => $author, "recordType" => $recordType, "Type" => $type,"recordID" => $recordID, "Date" => $itemEntry[$recordID]['dateAdded'], "Notes" => $itemEntry[$recordID]['notes'], "Weight" => $itemEntry[$recordID]['weight']];
            array_push($itemArray, $favoriteItem);
        }

        $this->SortByValue($itemArray, $favList->getSort());

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', $list->title)
            ->setCellValue('B1', $list->description)
            ->setCellValue('A3', 'Title')
            ->setCellValue('B3', 'Author')
            ->setCellValue('C3', 'Notes')
            ->setCellValue('D3', 'Material Type')
            ->setCellValue('E3', 'Date Added');


        $a = 4;
        foreach ($itemArray as $listItem) {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A' . $a, $listItem['Title'])
                ->setCellValue('B' . $a, $listItem['Author'])
                ->setCellValue('C' . $a, $listItem['Notes'])
                ->setCellValue('D' . $a, $listItem['Type'])
                ->setCellValue('E' . $a, date('m/d/Y g:i a', $listItem['Date']));
            $a++;

        }

        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);

        // Rename sheet
        $objPHPExcel->getActiveSheet()->setTitle(substr($list->title,0,30));

        // Redirect output to a client's web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . substr($list->title,0,27) . '.xls"');
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

        switch($field)
        {
            case "title":
                $key = "Title";
                break;
            case "author":
                $key = "Author";
                break;
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
    function getCoversForList($listId)
    {

    }
}