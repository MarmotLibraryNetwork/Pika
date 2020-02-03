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

require_once(ROOT_DIR . '/services/Admin/Admin.php');
require_once(ROOT_DIR . '/sys/LocalEnrichment/EditorialReview.php');
require_once ROOT_DIR . '/sys/DataObjectUtil.php';
require_once ROOT_DIR . '/sys/Pager.php';

class EditorialReview_Search extends Admin_Admin {

	function launch(){
		global $interface;

		$results        = array();
		$currentPage    = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$recordsPerPage = 20;
		$searchUrl      = '/EditorialReview/Search';
		$searchParams   = array();
		foreach ($_REQUEST as $key => $value){
			if (!in_array($key, array('module', 'action', 'page'))){
				$searchParams[] = "$key=$value";
			}
		}
		$searchUrl .= '?page=%d&' . implode('&', $searchParams);
		$interface->assign('page', $currentPage);

		$editorialReview = new EditorialReview();
		if (isset($_REQUEST['sortOptions'])){
			$editorialReview->orderBy($_REQUEST['sortOptions']);
			$interface->assign('sort', $_REQUEST['sortOptions']);
		}
		$numTotalFiles = $editorialReview->count();
		$editorialReview->limit(($currentPage - 1) * $recordsPerPage, 20);
		$editorialReview->find();
		if ($editorialReview->N > 0){
			while ($editorialReview->fetch()){
				$results[] = clone $editorialReview;
			}
		}
		$interface->assign('results', $results);

		$options = array(
			'totalItems' => $numTotalFiles,
			'fileName'   => $searchUrl,
			'perPage'    => $recordsPerPage);
		$pager   = new VuFindPager($options);
		$interface->assign('pageLinks', $pager->getLinks());

		$this->display('search.tpl', 'Editorial Review');
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'contentEditor');
	}
}
