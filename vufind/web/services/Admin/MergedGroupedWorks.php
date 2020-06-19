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

require_once ROOT_DIR . '/sys/Grouping/MergedGroupedWork.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_MergedGroupedWorks extends ObjectEditor {
	function getObjectType(){
		return 'MergedGroupedWork';
	}

	function getToolName(){
		return 'MergedGroupedWorks';
	}

	function getPageTitle(){
		return 'Merged Grouped Works';
	}

	function getAllObjects($orderBy = null){
		return parent::getAllObjects('id DESC');
	}

	function getObjectStructure(){
		return MergedGroupedWork::getObjectStructure();
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
		global $interface;
		return $interface->fetch('Admin/merge_grouped_work_instructions.tpl');
	}

	function getListInstructions(){
		return 'For more information on how to merge grouped works, see the <a href="https://docs.google.com/document/d/13e1lM5kveL_mu8I1iUpVELNW11q2Yi6ZGm0wc9Z3xQE">online documentation</a>.';
	}

}
