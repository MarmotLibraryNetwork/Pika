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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/FavoriteHandler.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/CitationBuilder.php';

class CiteList extends Action {
	function launch(){
		global $interface;

		//Get all lists for the user

		// Fetch List object
		if (isset($_REQUEST['listId'])){
			/** @var UserList $list */
			$list     = new UserList();
			$list->id = $_GET['listId'];
			$list->find(true);
		}
		$interface->assign('favList', $list);
		$params = [];
		if (!empty($_REQUEST['pagesize']) && is_numeric($_REQUEST['pagesize'])){
			$params['pagesize'] = $_REQUEST['pagesize'];
		}
		if (!empty($_REQUEST['page']) && is_numeric($_REQUEST['page'])){
			$params['page'] = $_REQUEST['page'];
		}
		if (!empty($_REQUEST['sort']) && in_array($_REQUEST['sort'], ['author', 'title', 'dateAdded', 'recentlyAdded', 'custom'])){
			$params['sort'] = $_REQUEST['sort'];
		}
		if (!empty($_REQUEST['filter'])){
			$params['filter'] = $_REQUEST['filter'];
		}
		$interface->assign('params', $params);
		// Get all titles on the list
		$favList         = new FavoriteHandler($list, false);
		$citationFormat  = $_REQUEST['citationFormat'];
		$page            = $_REQUEST['page'];
		$pageSize        = $_REQUEST['pagesize'];
		$citationFormats = CitationBuilder::getCitationFormats();
		$interface->assign('citationFormat', $citationFormats[$citationFormat]);
		$citations = $favList->getCitations($citationFormat,$page,$pageSize);

		$interface->assign('citations', $citations);

		// Display Page
		$interface->assign('listId', $list->id);
		$this->display('listCitations.tpl', 'Citations for ' . $list->title);
	}
}
