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

/**
 * Provides a method of running SQL updates to the database.
 * Shows a list of updates that are available with a description of the
 *
 * @author Mark Noble
 *
 */
class ListWidgetsListsLinks extends ObjectEditor {

//	function launch(){
//		$objectAction = $_REQUEST['objectAction'] ?? 'edit';
//		switch ($objectAction){
//			case 'save':
//				$this->launchSave();//Yes, there is not a break after this case.
//			case 'edit':
//			default :
//				$this->launchEdit($_REQUEST['widgetId'], $_REQUEST['widgetListId']);
//				break;
//		}
//		$this->display('listWidgetListLinks.tpl', 'List Widgets');
//	}


	private function launchSave(){
		if (!empty($_REQUEST['id']))//Save existing elements
		{
			$tmpREQUEST = $DATA = $_REQUEST;
			unset($_REQUEST);
			foreach ($DATA['id'] as $key => $val){
				if ($DATA['toDelete_' . $key] != 1){
					$this->setRequestValues($key, $DATA['name'][$key], $DATA['listWidgetListsId'][$key], $DATA['link'][$key], $DATA['weight'][$key]);
					$this->saveElement();
				}else{
					$this->deleteLink($key);
				}
				unset($_REQUEST, $listWidgetLinks);
			}
			$_REQUEST = $tmpREQUEST;
		}

		//New Elements?
		if (!empty($_REQUEST['newLink'])){
			$tmpREQUEST = $DATA = $_REQUEST;
			unset($_REQUEST);
			foreach ($DATA['newLink'] as $key => $val){
				if (!empty($DATA['nameNewLink'][$key]) && !empty($DATA['linkNewLink'][$key])){
					$this->setRequestValues('', $DATA['nameNewLink'][$key], $DATA['widgetListId'], $DATA['linkNewLink'][$key], $DATA['weightNewLink'][$key]);
					$this->saveElement();
					unset($_REQUEST, $listWidgetLinks);
				}
			}
			$_REQUEST = $tmpREQUEST;
		}

	}

	private function launchEdit($widgetId, $widgetListId){
		global $interface;

		//Get Info about the Widget
		$widget = new ListWidget();
		$widget->get($widgetId);
		$interface->assign('widgetName', $widget->name);
		$interface->assign('widgetId', $widget->id);

		//Get Info about the current TAB
		$widgetList     = new ListWidgetList();
		$widgetList->id = $widgetListId;
		$widgetList->find(true);
		$interface->assign('widgetListName', $widgetList->name);

		//Get all available links
		$availableLinks                     = [];
		$listWidgetLinks                    = new ListWidgetListsLinks();
		$listWidgetLinks->listWidgetListsId = $widgetListId;
		$listWidgetLinks->orderBy('weight ASC');
		if ($listWidgetLinks->find()){
			while ($listWidgetLinks->fetch()){
				$availableLinks[$listWidgetLinks->id] = clone($listWidgetLinks);
			}
		}
		$interface->assign('availableLinks', $availableLinks);
	}

	private function setRequestValues($id, $name, $listWidgetListsId, $link, $weight){
		$_REQUEST['id']                = $id;
		$_REQUEST['name']              = $name;
		$_REQUEST['listWidgetListsId'] = $listWidgetListsId;
		$_REQUEST['link']              = $link;
		$_REQUEST['weight']            = $weight;
	}

	private function deleteLink($linkId){
		$listWidgetLinks = new ListWidgetListsLinks();
		$listWidgetLinks->get($linkId);
		$listWidgetLinks->delete();
	}

	private function saveElement(){
		$validationResults = DataObjectUtil::saveObject(ListWidgetListsLinks::getObjectStructure(), "ListWidgetListsLinks");
	}

	public function getAllowableRoles(){
		return ['opacAdmin'];
	}

	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'ListWidgetListsLinks';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'ListWidgetListsLinks';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'List Widget Lists Links';
	}

	/**
	 * @inheritDoc
	 */
	function getAllObjects($orderBy = null){
		if (!empty($_REQUEST['widgetListId']) && ctype_digit($_REQUEST['widgetListId'])){
			//Get all available links
			$availableLinks                     = [];
			$listWidgetLinks                    = new ListWidgetListsLinks();
			$listWidgetLinks->listWidgetListsId = $_REQUEST['widgetListId'];
			$listWidgetLinks->orderBy($orderBy ?? 'weight ASC');
			if ($listWidgetLinks->find()){
				while ($listWidgetLinks->fetch()){
					$availableLinks[$listWidgetLinks->id] = clone($listWidgetLinks);
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		return ListWidgetListsLinks::getObjectStructure();
	}

	/**
	 * @inheritDoc
	 */
	function getPrimaryKeyColumn(){
		return 'id';
	}

	/**
	 * @inheritDoc
	 */
	function getIdKeyColumn(){
		return 'id';
	}
}
