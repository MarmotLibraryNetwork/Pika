<?php

use Administration\UserMigration;
require_once ROOT_DIR . '/services/Admin/Admin.php';
class Admin_UserMigration extends Admin_Admin
{

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	function getPageTitle(){
		return 'User Migration';
	}

	/**
	 * Process File
	 *
	 * Submits file to the User Migration function
	 *
	 * @return string error message on false; number of successfully imported users on success
	 */
	function processFile(){
			global $interface;
			$interface->setTemplate('../Admin/migrateUsers.tpl');
			$instructions = $this->getInstructions()?:"";
			$interface->assign('instructions', $instructions);
			$file = $_FILES['migrationBarcodes']['tmp_name'];
			$migration = new UserMigration();
			if (!empty($processed = $migration->migrateUsers($file))){
				$interface->assign('submit', true);
				$interface->assign('migratedUsers', $processed);
				return $processed;
			}else{
				$interface->assign('error','No Users were migrated');
				return false;
			}
		return false;
	}

	function getMigrationCount(){
		$migrations = new UserMigration();
		return $migrations->count();
	}

	function getInstructions(){
		return 'For more information on User Migration please see our documentation.';
	}

	function display($mainContentTemplate = null, $pageTitle = null, $sidebarTemplate = 'Search/home-sidebar.tpl'){
		global $interface;
		$instructions = $this->getInstructions()?:"";
		$interface->assign('instructions', $instructions);
		if (empty($mainContentTemplate)){
			$mainContentTemplate = $interface->getVariable('pageTemplate'); // The main template may get set in other places in Object Editor
		}
		if (empty($pageTitle)){
			$pageTitle = $this->getPageTitle();
		}
		$interface->assign('shortPageTitle', $pageTitle);
		parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate);
	}
	function getMigrationHome(){
		global $interface;
		//Basic List
		$interface->setTemplate('../Admin/migrateUsers.tpl');
	}
	function launch(){
		global $interface;
		$objectAction      = $_REQUEST['objectAction'] ?? null;
		$interface->assign('migrationCount', $this->getMigrationCount());
		if (is_null($objectAction) || $objectAction == 'list'){
			$this->getMigrationHome();
		}else{
			//check to see if a custom action is being called.
			if (method_exists($this, $objectAction)){
				$this->$objectAction();
			}
		}
		$this->display();
	}
}