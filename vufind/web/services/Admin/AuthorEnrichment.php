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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/AuthorEnrichment.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_AuthorEnrichment extends ObjectEditor {
	function getObjectType(){
		return 'AuthorEnrichment';
	}

	function getToolName(){
		return 'AuthorEnrichment';
	}

	function getPageTitle(){
		return 'Author Enrichment';
	}

	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'authorName');
	}

	function getObjectStructure(){
		return AuthorEnrichment::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'id';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return ['opacAdmin', 'cataloging'];
	}

	function getInstructions(){
		return "For more information on how to create update author enrichment information, see the <a href=\"https://marmot-support.atlassian.net/l/c/1RCdvM2b\">online documentation</a>.";
	}

}
