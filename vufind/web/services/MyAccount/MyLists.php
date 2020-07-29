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
            $userListData = $this->cache->get('user_list_data_' . UserAccount::getActiveUserId());
            if ($userListData == null || isset($_REQUEST['reload'])) {
                $lists = array();
                require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
                $tmpList = new UserList();
                $tmpList->user_id = UserAccount::getActiveUserId();
                $tmpList->deleted = 0;
                $tmpList->orderBy("title ASC");
                $tmpList->find();
                if ($tmpList->N > 0) {
                    while ($tmpList->fetch()) {
                        $lists[$tmpList->id] = array(
                            'name' => $tmpList->title,
                            'url' => '/MyAccount/MyList/' . $tmpList->id,
                            'id' => $tmpList->id,
                            'numTitles' => $tmpList->numValidListItems(),
                            'description' => $tmpList->description,
                            'defaultSort' => $tmpList->defaultSort,
                            'isPublic' => $tmpList->public,
                        );
                    }
                }
                //$this->cache->set('user_list_data_' . UserAccount::getActiveUserId(), $lists, $configArray['Caching']['user']);
                //$timer->logTime("Load Lists");
            } else {
                $lists = $userListData;
                //$timer->logTime("Load Lists from cache");
            }

            $interface->assign('lists', $lists);
            $interface->assign('staff', $staffUser);

            $this->display('../MyAccount/MyLists.tpl', 'My Lists');
        }
    }

    function getCoversForList($listId)
    {

    }
}