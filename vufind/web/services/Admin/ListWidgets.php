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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Widgets/ListWidget.php';
require_once ROOT_DIR . '/sys/Widgets/ListWidgetList.php';
require_once ROOT_DIR . '/sys/DataObjectUtil.php';

/**
 * Provides a method of running SQL updates to the database.
 * Shows a list of updates that are available with a description of the
 *
 * @author Mark Noble
 *
 */
class Admin_ListWidgets extends ObjectEditor {
	function getObjectType(){
		return 'ListWidget';
	}

	function getToolName(){
		return 'ListWidgets';
	}

	function getPageTitle(){
		return 'List Widgets';
	}

	function getAllObjects(){
		$list   = [];
		$user   = UserAccount::getLoggedInUser();
		$widget = new ListWidget();
		if (UserAccount::userHasRoleFromList(['libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager'])){
			$patronLibrary     = UserAccount::getUserHomeLibrary();
			$widget->libraryId = $patronLibrary->libraryId;
		}
		$widget->orderBy('name');
		$widget->find();
		while ($widget->fetch()){
			$list[$widget->id] = clone $widget;
		}

		return $list;
	}

	function getObjectStructure(){
		return ListWidget::getObjectStructure();
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager'];
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function canAddNew(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager']);
	}

	function canDelete(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin']);
	}

	function getAdditionalObjectActions($existingObject){
		$objectActions = [];
		if ($existingObject != null){
			$objectActions[] = [
				'text' => 'Preview Widget as Page',
				'url'  => '/API/SearchAPI?method=getListWidget&id=' . $existingObject->id,
			];
		}
		return $objectActions;
	}

	function getInstructions(){
		return 'For more information on how to create List Widgets, please see the <a href="https://docs.google.com/document/d/1nftHL4yrUWjWeuW7qSUz3L9XHR0vs8pTU4E-Aa6LPpk">online documentation</a>';
	}

	function viewIndividualObject($structure){
		if (!empty($_REQUEST['id'])){
			global $interface;
			/** @var ListWidget $existingObject */
			$id             = $_REQUEST['id'];
			$existingObject = $this->getExistingObjectById($id);
			// Set some default sizes for the iframe we embed on the view page
			switch ($existingObject->style){
				default :
				case 'horizontal':
					$width  = 650;
					$height = ($existingObject->coverSize == 'medium') ? 325 : 275;
					break;
				case 'vertical' :
					$width  = ($existingObject->coverSize == 'medium') ? 275 : 175;
					$height = ($existingObject->coverSize == 'medium') ? 700 : 400;
					break;
				case 'text-list' :
					$width  = 500;
					$height = 200;
					break;
				case 'single' :
				case 'single-with-next' :
					$width  = ($existingObject->coverSize == 'medium') ? 300 : 225;
					$height = ($existingObject->coverSize == 'medium') ? 350 : 275;
					break;
			}
			$interface->assign('width', $width);
			$interface->assign('height', $height);
			$interface->assign('selectedStyle', $existingObject->style);
			$interface->assign('object', $existingObject);

			$captcha = $interface->fetch('Admin/listWidget.tpl');
			$interface->assign('captcha', $captcha); // Use the captcha block of the form to display the List Widget integration notes

		}
			parent::viewIndividualObject($structure);
	}

}
