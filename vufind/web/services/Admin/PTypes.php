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
require_once ROOT_DIR . '/sys/Account/PType.php';

class PTypes extends ObjectEditor {

	function getObjectType(){
		return 'PType';
	}

	function getToolName(){
		return 'PTypes';
	}

	function getPageTitle(){
		return 'PTypes';
	}

	function getAllObjects($orderBy = null){
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('opacAdmin')){
			return parent::getAllObjects($orderBy ?? 'pType');
		}

		return [];
	}

	function getObjectStructure(){
		return PType::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'pType';
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

	function customListActions(){
		if(UserAccount::userHasRole('opacAdmin')){
			return array(
				array('label' => 'Load Patron Types', 'onclick' =>'Pika.Admin.loadPtypes()'),
			);
		}
		return false;
	}

}
