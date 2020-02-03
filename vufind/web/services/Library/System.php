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
 * Displays information about a particular library system
 * Organization in schema.org terminology
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 2/27/2016
 * Time: 2:31 PM
 */
class System extends Action {

	function launch(){
		global $interface;
		global $configArray;

		$librarySystem            = new Library();
		$librarySystem->libraryId = $_REQUEST['id'];
		if ($librarySystem->find(true)){
			$interface->assign('library', $librarySystem);
		}

		$semanticData = array(
			'@context' => 'http://schema.org',
			'@type'    => 'Organization',
			'name'     => $librarySystem->displayName,
		);
		//add branches
		$locations            = new Location();
		$locations->libraryId = $librarySystem->libraryId;
		$locations->orderBy('isMainBranch DESC, displayName'); // List Main Branches first, then sort by name
		$locations->find();
		$subLocations = array();
		$branches     = array();
		while ($locations->fetch()){
			$branches[]     = array(
				'name' => $locations->displayName,
				'link' => $configArray['Site']['url'] . "/Library/{$locations->locationId}/Branch"
			);
			$subLocations[] = array(
				'@type' => 'Organization',
				'name'  => $locations->displayName,
				'url'   => $configArray['Site']['url'] . "/Library/{$locations->locationId}/Branch"

			);
		}
		if (count($subLocations)){
			$semanticData['subOrganization'] = $subLocations;
			$interface->assign('branches', $branches);
		}
		$interface->assign('semanticData', json_encode($semanticData));

		$this->display('system.tpl', $librarySystem->displayName);
	}
}
