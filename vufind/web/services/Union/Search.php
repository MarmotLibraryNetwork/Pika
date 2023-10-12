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
require_once ROOT_DIR . '/sys/Search/SearchSources.php';

/**
 * Union Results
 * Provides a way of unifying searching disparate sources either by
 * providing joined results between the sources or by including results from
 * a single source
 *
 * @author Mark Noble
 *
 */
class Union_Search extends Action {
	function launch(){
		global $module;
		global $action;
		global $interface;
		//Get the search source and determine what to show.
		$searchSource = empty($_REQUEST['searchSource']) ? 'local' : $_REQUEST['searchSource'];
		$searches     = SearchSources::getSearchSources();
		if ($searchSource == 'marmot' && !isset($searches[$searchSource])){
			$searchSource = 'local';
		}
		$searchInfo = $searches[$searchSource];
		if (isset($searchInfo['external']) && $searchInfo['external'] == true){
			//Reset to a local search source so the external search isn't remembered
			$_SESSION['searchSource'] = 'local';
			//Need to redirect to the appropriate search location with the new value for look for
			$type    = $_REQUEST['basicType'] ?? $_REQUEST['type'];
			$lookfor = $_REQUEST['lookfor'] ?? '';
			$link    = SearchSources::getExternalLink($searchSource, $type, $lookfor);
			header('Location: ' . $link);
			die();
		}else{
			switch ($searchSource){
				case 'genealogy':
					require_once ROOT_DIR . '/services/Genealogy/Results.php';
					$module = 'Genealogy';
					$action = 'Results';
					$interface->assign('module', $module);
					$interface->assign('action', $action);
					$results = new Genealogy_Results();
					$results->launch();
					break;
				case 'islandora':
					require_once ROOT_DIR . '/services/Archive/Results.php';
					$module = 'Archive';
					$action = 'Results';
					$interface->assign('module', $module);
					$interface->assign('action', $action);
					$results = new Archive_Results();
					$results->launch();
					break;
				case 'ebsco':
					require_once ROOT_DIR . '/services/EBSCO/Results.php';
					$module = 'EBSCO';
					$action = 'Results';
					$interface->assign('module', $module);
					$interface->assign('action', $action);
					$results = new EBSCO_Results();
					$results->launch();
					break;
				case 'combinedResults':
					require_once ROOT_DIR . '/services/Union/CombinedResults.php';
					$module = 'Union';
					$action = 'CombinedResults';
					$interface->assign('module', $module);
					$interface->assign('action', $action);
					$results = new Union_CombinedResults();
					$results->launch();
					break;
				default:
					require_once ROOT_DIR . '/services/Search/Results.php';
					$module = 'Search';
					$action = 'Results';
					$interface->assign('module', $module);
					$interface->assign('action', $action);
					$results = new Search_Results();
					$results->launch();
					break;
			}
		}
	}
}
