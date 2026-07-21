<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * Admin interface for Colorado State Book Club Kit Requests
 *
 * @category Pika
 */

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/BookClubKit/BookClubKitRequest.php';
class Admin_BookClubKitRequests extends ObjectEditor {
	function getObjectType(){
		return 'BookClubKit\BookClubKitRequest';
	}
	function getToolName(){
		return 'BookClubKitRequests';
	}
	function getPageTitle(){
		return 'Book Club Kit Requests';
	}
	function getAllObjects($orderBy = null){
		$list = [];

		$object = new BookClubKit\BookClubKitRequest();
		$object->orderBy($orderBy ?? 'dateCreated desc');
		$user = UserAccount::getLoggedInUser();
		if (!UserAccount::userHasRole('opacAdmin')){
			$homeLibrary = $user->getHomeLibrary();
			$object->whereAdd("libraryId = {$homeLibrary->libraryId}");
		}
		if ($object->find()){
			while ($object->fetch()){
				$list[$object->id] = clone $object;
			}
		}
		return $list;
	}
	function getObjectStructure(){
		return BookClubKit\BookClubKitRequest::getObjectStructure();
	}
	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'bookClubKitAdmin'];
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
		return UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin', 'bookClubKitAdmin']);
	}

}
