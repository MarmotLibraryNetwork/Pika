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
 * Pika
 *
 * Author: Pascal Brammeier
 * Date: 7/30/2015
 *
 */

require_once ROOT_DIR . '/sys/Administration/BlockPatronAccountLink.php'; // Database object
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_BlockPatronAccountLinks extends ObjectEditor {

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'libraryManager', 'locationManager');
	}

	/**
	 * The class name of the object which is being edited
	 */
	function getObjectType(){
		return 'BlockPatronAccountLink';
	}

	/**
	 * The page name of the tool (typically the plural of the object)
	 */
	function getToolName(){
		return 'BlockPatronAccountLinks';
	}

	/**
	 * The title of the page to be displayed
	 */
	function getPageTitle(){
		return 'Block Patron Account Links';
	}

	/**
	 * Load all objects into an array keyed by the primary key
	 * @param null $orderBy
	 * @return DB_DataObject[]
	 */
	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy);
	}

	/**
	 * Define the properties which are editable for the object
	 * as well as how they should be treated while editing, and a description for the property
	 */
	function getObjectStructure(){
		return BlockPatronAccountLink::getObjectStructure();
	}

	/**
	 * The name of the column which defines this as unique
	 */
	function getPrimaryKeyColumn(){
		return 'id';
	}

	/**
	 * The id of the column which serves to join other columns
	 */
	function getIdKeyColumn(){
		return 'id';
	}

	function getInstructions(){
		return '<p>To block a patron from viewing the information of another patron by linking accounts:</p>
		<br>
 		<ul>
 		<li>First enter the barcode of the user you want to prevent from seeing the other account as the <b>"The following blocked barcode will not have access to the account below."</b></li>
 		<li>Next enter the barcode of the user you want to prevent from being viewed by the other account as the <b>"The following barcode will not be accessible by the blocked barcode above."</b></li>
 		<li>If the user should not be able to see any other accounts at all, check <b>"Check this box to prevent the blocked barcode from accessing ANY linked accounts."</b></li>
 		<li>Now select a <b>Save Changes</b> button</li>
 		</ul>
 		<br>
 		<p class="alert alert-warning">
 		<span class="glyphicon glyphicon-warning-sign" aria-hidden="true"></span> Blocking a patron from linking accounts will not prevent a user from manually logging into other accounts.
 		If you suspect that someone has been accessing other accounts incorrectly, you should issue new cards or change PINs for the accounts they have accessed in addition to blocking them.
		</p>';
	}

	function getListInstructions(){
		return '';
	}

}
