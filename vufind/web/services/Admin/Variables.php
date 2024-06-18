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

/**
 * Display a list of internal variables that have been defined in the database table 'variables'.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 4/27/14
 * Time: 2:21 PM
 */
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_Variables extends ObjectEditor {

	function getObjectType(){
		return 'Variable';
	}

	function getToolName(){
		return 'Variables';
	}

	function getPageTitle(){
		return 'System Variables';
	}

	function getAllObjects($orderBy = null){
		$variableList = [];

		$variable = new Variable();
		$variable->orderBy($orderBy ?? 'name');
		$variable->find();
		while ($variable->fetch()){
			// Add a human-readable date time  of timestamp values in an additional column
			$variable->timeDisplay = null;
			if (ctype_digit($variable->value) && $variable->value > strtotime('-10 years')){
				$variable->timeDisplay = date("Y-m-d H:i:s T", $variable->value);
			}
			$variableList[$variable->id] = clone $variable;
		}
		return $variableList;
	}

	function getObjectStructure(){
		return Variable::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'name';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	function canAddNew(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	function canDelete(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	/**
	 * @param Variable $existingObject
	 * @return array
	 */
	function getAdditionalObjectActions($existingObject){
		$actions = [];
		if ($existingObject && $existingObject->id != ''){
			$actions[] = [
				'text' => '<span class="glyphicon glyphicon-time" aria-hidden="true"></span> Set to Current Timestamp',
				'url'  => "/{$this->getModule()}/{$this->getToolName()}?objectAction=setToNow&amp;id=" . $existingObject->id,
			];
			$actions[] = [
				'text' => '<span class="glyphicon glyphicon-arrow-up" aria-hidden="true"></span> Increase by 10,000',
				'url'  => "/{$this->getModule()}/{$this->getToolName()}?objectAction=IncrementVariable&amp;direction=up&amp;id=" . $existingObject->id,
			];
			$actions[] = [
				'text' => '<span class="glyphicon glyphicon-arrow-down" aria-hidden="true"></span> Decrease by 1,000',
				'url'  => "/{$this->getModule()}/{$this->getToolName()}?objectAction=IncrementVariable&amp;direction=down&amp;id=" . $existingObject->id,
			];
			if ($existingObject->value == '1' || $existingObject->value == 'true'){
				$actions[] = [
					'text' => '<span class="glyphicon glyphicon-minus-sign" aria-hidden="true"></span> Set to false',
					'url'  => "/{$this->getModule()}/{$this->getToolName()}?objectAction=SwitchBooleanVariable&amp;id=" . $existingObject->id,
				];
			}elseif ( $existingObject->value == '0' || $existingObject->value == 'false'){
				$actions[] = [
					'text' => '<span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></span> Set to true',
					'url'  => "/{$this->getModule()}/{$this->getToolName()}?objectAction=SwitchBooleanVariable&amp;id=" . $existingObject->id,
				];
			}
		}
		return $actions;
	}

	/**
	 * Additional Object Action to set a variable to the current timestamp
	 */
	function setToNow(){
		$id              = $_REQUEST['id'];
		if (!empty($id) && ctype_digit($id)){
			$variable = new Variable();
			$variable->get($id);
			if ($variable){
				$variable->value = time();
				$variable->update();
			}
			header("Location: /{$this->getModule()}/{$this->getToolName()}?objectAction=edit&id=" . $id);
		}
	}

	/**
	 * Additional Object Action to increase or decrease the value of a variable
	 */
	function IncrementVariable(){
		$id = $_REQUEST['id'];
		if (!empty($id) && ctype_digit($id)){
			$variable = new Variable();
			$variable->get($id);
			if ($variable){
				switch ($_REQUEST['direction']){
					case 'up':
						$amount = 10000;
						break;
					case 'down':
						$amount = -1000;
						break;
					default:
						$amount = 0;
				}
				if ($amount){
					$variable->value += $amount;
					$variable->update();
				}
			}
			header("Location: /{$this->getModule()}/{$this->getToolName()}?objectAction=edit&id=" . $id);
		}
	}

	/**
	 * An additional object action to switch a boolean valued variable
	 */
	function SwitchBooleanVariable(){
		$id = $_REQUEST['id'];
		if (!empty($id) && ctype_digit($id)){
			$variable = new Variable();
			$variable->get($id);
			if ($variable){
				if ($variable->value == '1' || $variable->value == 'true'){
					$variable->value = '0';
					$variable->update();
				}elseif ($variable->value == '0' || $variable->value == 'false'){
					$variable->value = '1';
					$variable->update();
				}
			}
			header("Location: /{$this->getModule()}/{$this->getToolName()}?objectAction=edit&id=" . $id);
		}
	}

	function editObject($objectAction, $structure){
		if ($objectAction == 'save'){
			if (!empty($_REQUEST['name']) && $_REQUEST['name'] == 'offline_mode_when_offline_login_allowed'){
				if (!empty($_REQUEST['value']) && $_REQUEST['value'] == 'true' || $_REQUEST['value'] == 1){
					global $configArray;
					if (isset($configArray['Catalog']['enableLoginWhileOffline']) && empty($configArray['Catalog']['enableLoginWhileOffline'])){
						$_SESSION['lastError'] = "While offline logins are disabled offline mode can not be turned on with this variable.";
						header("Location: {$_SERVER['REQUEST_URI']}");
						die();
					}
				}
			}
		}
		parent::editObject($objectAction, $structure);
	}


}
