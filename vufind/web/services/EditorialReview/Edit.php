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

require_once ROOT_DIR . '/Action.php';
require_once(ROOT_DIR . '/services/Admin/Admin.php');
require_once(ROOT_DIR . '/sys/LocalEnrichment/EditorialReview.php');
require_once ROOT_DIR . '/sys/DataObjectUtil.php';

class EditorialReview_Edit extends Admin_Admin {

	function launch(){
		global $interface;

		$isNew = true;
		if (!empty($_REQUEST['id'])){
			$editorialReview                    = new EditorialReview();
			$editorialReview->editorialReviewId = $_REQUEST['id'];
			if ($editorialReview->find(true)){
				$interface->assign('object', $editorialReview);
				$isNew = false;
			}
		}
		$structure = EditorialReview::getObjectStructure();
		if ($isNew && isset($_REQUEST['recordId'])){
			$structure['recordId']['default'] = strip_tags($_REQUEST['recordId']);
		}

		if (isset($_REQUEST['submit']) || isset($_REQUEST['submitStay']) || isset($_REQUEST['submitReturnToList']) || isset($_REQUEST['submitAddAnother'])){
			//Save the object
			$results         = DataObjectUtil::saveObject($structure, 'EditorialReview');
			$editorialReview = $results['object'];
			//redirect to the view of the competency if we saved ok.
			if (!$results['validatedOk'] || !$results['saveOk']){
				//Display the errors for the user.
				$interface->assign('errors', $results['errors']);
				$interface->assign('object', $editorialReview);

				$_REQUEST['id'] = $editorialReview->editorialReviewId;
			}elseif (isset($_REQUEST['submitReturnToList'])){
				//Show the new review
				header("Location:/GroupedWork/{$editorialReview->recordId}/Home");
			}elseif (isset($_REQUEST['submitAddAnother'])){
				header("Location:/EditorialReview/Edit?recordId={$editorialReview->recordId}");
			}else{
				header("Location:/EditorialReview/{$editorialReview->editorialReviewId}/View");
				exit();
			}
		}

		$interface->assign('isNew', $isNew);
		$interface->assign('submitUrl', '/EditorialReview/Edit');
		$interface->assign('editForm', DataObjectUtil::getEditForm($structure));

		$this->display('edit.tpl', 'Editorial Review');
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'contentEditor');
	}
}
