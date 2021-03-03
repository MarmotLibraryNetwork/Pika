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

require_once ROOT_DIR . '/services/Admin/Admin.php';

abstract class ObjectEditor extends Admin_Admin {
	function launch(){
		global $interface;

		if (isset($_SESSION['lastError'])){
			$interface->assign('lastError', $_SESSION['lastError']);
			unset($_SESSION['lastError']);
		}

		$interface->assign('canAddNew', $this->canAddNew());
		$interface->assign('canDelete', $this->canDelete());
		$interface->assign('showReturnToList', $this->showReturnToList());

		$interface->assign('objectType', $this->getObjectType());
		$interface->assign('toolName', $this->getToolName());

		//Define the structure of the object.
		$structure         = $this->getObjectStructure();
		$objectAction      = isset($_REQUEST['objectAction']) ? $_REQUEST['objectAction'] : null;
		$customListActions = $this->customListActions();
		$interface->assign('structure', $structure);
		$interface->assign('customListActions', $customListActions);
		if (is_null($objectAction) || $objectAction == 'list'){
			$interface->assign('instructions', $this->getListInstructions());
			$this->viewExistingObjects();
		}elseif (($objectAction == 'save' || $objectAction == 'delete') && isset($_REQUEST['id'])){
			$this->editObject($objectAction, $structure);
		}else{
			//check to see if a custom action is being called.
			if (method_exists($this, $objectAction)){
				$this->$objectAction();
			}else{
				$interface->assign('instructions', $this->getInstructions());
				$this->viewIndividualObject($structure);
			}
		}
		$this->display();
	}

	/**
	 * The class name of the object which is being edited
	 */
	abstract function getObjectType();

	/**
	 * The page name of the tool (typically the plural of the object)
	 */
	abstract function getToolName();

	/**
	 * The title of the page to be displayed
	 */
	abstract function getPageTitle();

	/**
	 * Load all objects into an array keyed by the primary key
	 * Override this method and set an order by to change how
	 * sorting order
	 *
	 * @param null $orderBy optional Order by clause to use
	 * @return DB_DataObject[]
	 */
	function getAllObjects($orderBy = null){
		/** @var DB_DataObject $object */
		$objectList  = [];
		$objectClass = $this->getObjectType();
		$objectIdCol = $this->getIdKeyColumn();
		$object      = new $objectClass;
		if ($orderBy){
			$object->orderBy($orderBy);
		}
		if ($object->find()){
			while ($object->fetch()){
				$objectList[$object->$objectIdCol] = clone $object;
			}
		}
		return $objectList;
	}

	/**
	 * Define the properties which are editable for the object
	 * as well as how they should be treated while editing, and a description for the property
	 */
	abstract function getObjectStructure();

	/**
	 * The name of the column which defines this as unique
	 */
	abstract function getPrimaryKeyColumn();

	/**
	 * The id of the column which serves to join other columns
	 */
	abstract function getIdKeyColumn();

//TODO: delete or use
	function getExistingObjectByPrimaryKey($objectType, $value){
		$primaryKeyColumn = $this->getPrimaryKeyColumn();
		/** @var DB_DataObject $dataObject */
		$dataObject                    = new $objectType();
		$dataObject->$primaryKeyColumn = $value;
		$dataObject->find();
		if ($dataObject->N == 1){
			$dataObject->fetch();
			return $dataObject;
		}else{
			return null;
		}
	}

	function getExistingObjectById($id){
		$objectType = $this->getObjectType();
		$idColumn   = $this->getIdKeyColumn();
		/** @var DB_DataObject $dataObject */
		$dataObject            = new $objectType;
		$dataObject->$idColumn = $id;
		$dataObject->find();
		if ($dataObject->N == 1){
			$dataObject->fetch();
			return $dataObject;
		}else{
			return null;
		}
	}

	function insertObject($structure){
		$objectType = $this->getObjectType();
		/** @var DB_DataObject $newObject */
		$newObject = new $objectType;
		//Check to see if we are getting default values from the
		$validationResults = $this->updateFromUI($newObject, $structure);
		if ($validationResults['validatedOk']){
			$ret = $newObject->insert();
			if (!$ret){
				global $logger;
				if ($newObject->_lastError){
					$errorDescription = $newObject->_lastError->getUserInfo();
				}else{
					$errorDescription = 'Unknown error';
				}
				$logger->log('Could not insert new object ' . $ret . ' ' . $errorDescription, PEAR_LOG_DEBUG);
				@session_start();
				$_SESSION['lastError'] = "An error occurred inserting {$this->getObjectType()} <br/>{$errorDescription}";

				return false;
			}
		}else{
			global $logger;
			$errorDescription = implode(', ', $validationResults['errors']);
			$logger->log('Could not validate new object ' . $objectType . ' ' . $errorDescription, PEAR_LOG_DEBUG);
			@session_start();
			$_SESSION['lastError'] = "The information entered was not valid. <br>" . implode('<br>', $validationResults['errors']);

			return false;
		}
		return $newObject;
	}

	function setDefaultValues($object, $structure){
		foreach ($structure as $property){
			$propertyName = $property['property'];
			if (isset($_REQUEST[$propertyName])){
				$object->$propertyName = $_REQUEST[$propertyName];
			}
		}
	}

	function updateFromUI($object, $structure){
		require_once ROOT_DIR . '/sys/DataObjectUtil.php';
		DataObjectUtil::updateFromUI($object, $structure);
		$validationResults = DataObjectUtil::validateObject($structure, $object);
		return $validationResults;
	}

	function viewExistingObjects(){
		global $interface;
		//Basic List
		$interface->assign('dataList', $this->getAllObjects());
		$interface->setTemplate('../Admin/propertiesList.tpl');
	}

	function viewIndividualObject($structure){
		global $interface;
		//Viewing an individual record, get the id to show
		if (isset($_SERVER['HTTP_REFERER'])){
			$_SESSION['redirect_location'] = $_SERVER['HTTP_REFERER'];
		}else{
			unset($_SESSION['redirect_location']);
		}
		if (!empty($_REQUEST['id'])){
			$id             = $_REQUEST['id'];
			$existingObject = $this->getExistingObjectById($id);
			$interface->assign('id', $id);
			if (method_exists($existingObject, 'label')){
				$interface->assign('objectName', $existingObject->label());
			}
		}else{
			$existingObject = null;
		}
		if (!isset($_REQUEST['id']) || $existingObject == null){
			$objectType     = $this->getObjectType();
			$existingObject = new $objectType;
			$this->setDefaultValues($existingObject, $structure);
		}
		$interface->assign('object', $existingObject);
		//Check to see if the request should be multipart/form-data
		$contentType = null;
		foreach ($structure as $property){
			if ($property['type'] == 'image' || $property['type'] == 'file'){
				$contentType = 'multipart/form-data';
			}
		}
		$interface->assign('contentType', $contentType);

		$interface->assign('additionalObjectActions', $this->getAdditionalObjectActions($existingObject));
		$interface->setTemplate('../Admin/objectEditor.tpl');
	}

	function editObject($objectAction, $structure){
		$errorOccurred = false;
		//Save or create a new object
		$id = $_REQUEST['id'];
		if (empty($id) || $id < 0){
			//Insert a new record
			$curObject = $this->insertObject($structure);
			if ($curObject == false){
				//The session lastError is updated
				$errorOccurred = true;
			}
		}else{
			//Work with an existing object
			$curObject = $this->getExistingObjectById($id);
			if (!is_null($curObject)){
				switch ($objectAction){
					case 'save':
						//Update the object
						$validationResults = $this->updateFromUI($curObject, $structure);
						if ($validationResults['validatedOk']){
							$ret = $curObject->update();
							if ($ret === false){
								if ($curObject->_lastError){
									$errorDescription = $curObject->_lastError->getUserInfo();
								}else{
									$errorDescription = 'Unknown error';
								}
								session_start();
								$_SESSION['lastError'] = "An error occurred updating {$this->getObjectType()} with id of $id <br><br><blockquote class=\"alert-warning\">{$errorDescription}</blockquote>";
								$errorOccurred         = true;
							}
						}else{
							$errorDescription = '<blockquote class="alert-warning">' . implode('</blockquote><blockquote class="alert-warning">', $validationResults['errors']) . '</blockquote>';
							session_start();
							$_SESSION['lastError'] = "An error occurred validating {$this->getObjectType()} with id of $id <br><br>{$errorDescription}";
							$errorOccurred         = true;
						}
						break;
					case 'delete':
						if ($this->canDelete()){
							//Delete the object
							$ret = $curObject->delete();
							if ($ret === false){
								$_SESSION['lastError'] = "Unable to delete {$this->getObjectType()} with id of $id";
								$errorOccurred         = true;
							}
						}else{
							$_SESSION['lastError'] = "Not allowed to delete {$this->getObjectType()} with id of $id";
							$errorOccurred         = true;
						}
						break;
				}
			}else{
				//Couldn't find the object.  Something went haywire.
				session_start();
				$_SESSION['lastError'] = "An error occurred, could not find {$this->getObjectType()} with id of $id";
				$errorOccurred         = true;
			}
		}
		global $configArray;
		if (isset($_REQUEST['submitStay']) || $errorOccurred){
			header("Location: /{$this->getModule()}/{$this->getToolName()}?objectAction=edit&id=$id");
		}elseif (isset($_REQUEST['submitAddAnother'])){
			header("Location: /{$this->getModule()}/{$this->getToolName()}?objectAction=addNew");
		}else{
			$redirectLocation = $this->getRedirectLocation($objectAction, $curObject);
			if (is_null($redirectLocation)){
				if (isset($_SESSION['redirect_location']) && $objectAction != 'delete'){
					header("Location: " . $_SESSION['redirect_location']);
				}else{
					header("Location: /{$this->getModule()}/{$this->getToolName()}");
				}
			}else{
				header("Location: {$redirectLocation}");
			}
		}
		die();
	}

	function getRedirectLocation($objectAction, $curObject){
		return null;
	}

	function showReturnToList(){
		return true;
	}

	function getFilters(){
		return array();
	}

	function getModule(){
		return 'Admin';
	}

	function getFilterValues(){
		$filters = $this->getFilters();
		foreach ($filters as $filter){
			if ($_REQUEST[$filter['filter']]){
				$filter['value'] = $_REQUEST[$filter['filter']];
			}else{
				$filter['value'] = '';
			}
		}
		return $filters;
	}

	public function canAddNew(){
		return true;
	}

	public function canDelete(){
		return true;
	}

	public function customListActions(){
		return array();
	}

	function getAdditionalObjectActions($existingObject){
		return array();
	}

	/** An instruction blurb displayed at the top of object view page.
	 * @return string
	 */
	function getInstructions(){
		return '';
	}

	/** An instruction blurb displayed at the top of objects listing page.
	 *
	 *  Default behaviour is to return the instructions from getInstructions().
	 *  Override to change.
	 * @return string
	 */
	function getListInstructions(){
		return $this->getInstructions();
	}

	/**
	 * @param string|null $mainContentTemplate Name of the SMARTY template file for the main content of the full pages
	 * @param string|null $pageTitle What to display is the html title tag
	 * @param bool|string $sidebarTemplate Sets the sidebar template, set to false or empty string for no sidebar
	 */
	function display($mainContentTemplate = null, $pageTitle = null, $sidebarTemplate = 'Search/home-sidebar.tpl'){
		global $interface;
		if (empty($mainContentTemplate)){
			$mainContentTemplate = $interface->getVariable('pageTemplate'); // The main template may get set in other places in Object Editor
		}
		if (empty($pageTitle)){
			$pageTitle = $this->getPageTitle();
		}
		$interface->assign('shortPageTitle', $pageTitle);
		parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate);
	}

}
