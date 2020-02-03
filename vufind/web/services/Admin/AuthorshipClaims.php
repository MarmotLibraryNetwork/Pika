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

/**
 * Admin interface for creating indexing profiles
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 6/30/2015
 * Time: 1:23 PM
 */

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Archive/ClaimAuthorshipRequest.php';
class Admin_AuthorshipClaims extends ObjectEditor {
	function getObjectType(){
		return 'ClaimAuthorshipRequest';
	}
	function getToolName(){
		return 'AuthorshipClaims';
	}
	function getPageTitle(){
		return 'Claims of Authorship for Archive Materials';
	}
	function getAllObjects(){
		$list = array();

		$object = new ClaimAuthorshipRequest();
		$user = UserAccount::getLoggedInUser();
		if (!UserAccount::userHasRole('opacAdmin')){
			$homeLibrary = $user->getHomeLibrary();
			$archiveNamespace = $homeLibrary->archiveNamespace;
			$object->whereAdd("pid LIKE '{$archiveNamespace}:%'");
		}
		$object->orderBy('dateRequested desc');
		$object->find();
		while ($object->fetch()){
			$list[$object->id] = clone $object;
		}

		return $list;
	}
	function getObjectStructure(){
		return ClaimAuthorshipRequest::getObjectStructure();
	}
	function getAllowableRoles(){
		return array('opacAdmin', 'archives');
	}
	function getPrimaryKeyColumn(){
		return 'id';
	}
	function getIdKeyColumn(){
		return 'id';
	}
	function canAddNew(){
		return false;
	}
	function canDelete(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

}
