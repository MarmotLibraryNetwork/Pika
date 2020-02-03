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
require_once ROOT_DIR . '/sys/DataObjectUtil.php';
require_once(ROOT_DIR . '/sys/LocalEnrichment/EditorialReview.php');

class EditorialReview_View extends Admin_Admin {

	function launch(){
		global $interface;

		$interface->assign('id', $_REQUEST['id']);
		$editorialReview                    = new EditorialReview();
		$editorialReview->editorialReviewId = $_REQUEST['id'];
		$editorialReview->find();
		if ($editorialReview->N > 0){
			$editorialReview->fetch();
			$interface->assign('editorialReview', $editorialReview);
		}

		$this->display('view.tpl', 'Editorial Review');
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'contentEditor');
	}
}
