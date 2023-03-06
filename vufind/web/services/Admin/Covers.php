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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Covers extends ObjectEditor {

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'cataloging'];
	}

	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'Cover';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'Covers';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'Custom Covers';
	}

	/**
	 * @inheritDoc
	 */
	function getAllObjects($orderBy = null){
		$user  = UserAccount::getLoggedInUser();
		$cover = new Cover();
		$cover->find();
		$coverList = [];
		while ($cover->fetch()){
			$coverList[$cover->coverId] = clone $cover;
		}
		return $coverList;
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		return Cover::getObjectStructure();
	}

	/**
	 * @inheritDoc
	 */
	function getPrimaryKeyColumn(){
		return 'coverId';
	}

	/**
	 * @inheritDoc
	 */
	function getIdKeyColumn(){
		return 'coverId';
	}

	function getInstructions(){
		return 'For more information about records and custom covers, see the <a href="https://marmot-support.atlassian.net/l/c/t017oAV0">online documentation</a>.';
	}
}