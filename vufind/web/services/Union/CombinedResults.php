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

class Union_CombinedResults extends Action{
	function launch() {
		global $library;
		global $locationSingleton;
		global $interface;
		if (array_key_exists('lookfor', $_REQUEST)){
			$lookfor = $_REQUEST['lookfor'];
		}else{
			$lookfor = '';
		}
		if (array_key_exists('basicType', $_REQUEST)){
			$basicType = $_REQUEST['basicType'];
		}else{
			$basicType = 'Keyword';
		}
		$interface->assign('lookfor', $lookfor);
		$interface->assign('basicSearchType', $basicType);

		$location = $locationSingleton->getActiveLocation();
		$combinedResultsName = 'Combined Results';
		if ($location && !$location->useLibraryCombinedResultsSettings){
			$combinedResultsName = $location->combinedResultsLabel;
			$combinedResultSections = $location->combinedResultSections;
		}else if ($library){
			$combinedResultsName = $library->combinedResultsLabel;
			$combinedResultSections = $library->combinedResultSections;
		}

		$interface->assign('combinedResultSections', $combinedResultSections);

		$this->display('combined-results.tpl', $combinedResultsName);
	}
}
