<?php
/**
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 6/11/2020
 *
 */

require_once ROOT_DIR . '/sys/Grouping/PreferredGroupingTitle.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_PreferredGroupingTitles extends ObjectEditor {

	function getAllowableRoles(){
		return ['opacAdmin', 'cataloging'];
	}

	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'PreferredGroupingTitle';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'PreferredGroupingTitles';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'Preferred Grouping Titles';
	}

	/**
	 * @inheritDoc
	 */
	function getAllObjects($orderBy = null){
		return parent::getAllObjects('sourceGroupingTitle');
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		return PreferredGroupingTitle::getObjectStructure();
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

	function getInstructions(){
		return '<p>For more information about preferred grouping titles, see the <a href="https://docs.google.com/document/d/10dlqFTtg3cROGbz9qeO0CdfxImy0g44_7AIK57HVhrg">online documentation</a>.</p><br>' .
			'<blockquote class="alert-warning">Note: The <em>Preferred Grouping Title</em> will replace <strong>every</strong> instance of the <em>Source Grouping Title</em> for <u><strong>any</strong> grouped work</u> with that Grouping Title, not just for a single grouped work of interest.</blockquote>';
	}

}