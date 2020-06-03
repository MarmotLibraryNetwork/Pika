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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Network/subnet.php';

class IPAddresses extends ObjectEditor {
	function getObjectType(){
		return 'subnet';
	}

	function getToolName(){
		return 'IPAddresses';
	}

	function getPageTitle(){
		return 'Location IP Addresses';
	}

	function getAllObjects(){
		$object = new subnet();
		$object->orderBy('ip');
		$object->find();
		$objectList = [];
		while ($object->fetch()){
			$objectList[$object->id] = clone $object;
		}
		return $objectList;
	}

	function getObjectStructure(){
		//Look lookup information for display in the user interface
		$locationLookupList = Location::getLocationLookupList();

		$structure = [
			'ip'         => ['property' => 'ip', 'type' => 'text', 'label' => 'IP Address', 'description' => 'The IP Address to map to a location formatted as xxx.xxx.xxx.xxx/mask'],
			'location'   => ['property' => 'location', 'type' => 'text', 'label' => 'Display Name', 'description' => 'Descriptive information for the IP Address for internal use'],
			'locationid' => ['property' => 'locationid', 'type' => 'enum', 'values' => $locationLookupList, 'label' => 'Location', 'description' => 'The Location which this IP address maps to'],
			'isOpac'     => ['property' => 'isOpac', 'type' => 'checkbox', 'label' => 'Treat as a Public OPAC', 'description' => 'This IP address will be treated as a public OPAC with autologout features turned on.', 'default' => true],
		];
		return $structure;
	}

	function getPrimaryKeyColumn(){
		return 'ip';
	}

	function getIdKeyColumn(){
		return 'id';
	}

	function getAllowableRoles(){
		return ['opacAdmin'];
	}

	function getInstructions(){
		return 'For more information on how to use IP Addresses, see the <a href="https://docs.google.com/document/d/1M9UUzFUIV9G0KrsttgGV-MZK5zL7qdDx7I0-sIu5ZYc/edit">online documentation</a>.';
	}

}
