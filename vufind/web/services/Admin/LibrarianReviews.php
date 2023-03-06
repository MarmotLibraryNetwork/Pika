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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 2/1/2020
 *
 */
require_once ROOT_DIR . '/sys/LocalEnrichment/LibrarianReview.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class LibrarianReviews extends ObjectEditor {

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager');
	}

	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'LibrarianReview';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'LibrarianReviews';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'Librarian Reviews';
	}

	/**
	 * @inheritDoc
	 */
	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'pubDate DESC');
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		return LibrarianReview::getObjectStructure();
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