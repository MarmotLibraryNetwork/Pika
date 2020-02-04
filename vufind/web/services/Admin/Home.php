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

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once 'XML/Unserializer.php';

class Home extends Admin_Admin {
	function launch(){
		global $configArray;
		global $interface;

		require_once ROOT_DIR . '/services/API/SearchAPI.php';
		$indexStatus = new SearchAPI();
		$pikaStatus  = $indexStatus->getIndexStatus();
		$interface->assign('PikaStatus', $pikaStatus['status']);
		$interface->assign('PikaStatusMessages', explode(';', $pikaStatus['message']));

		// Load SOLR Statistics
		if ($configArray['Index']['engine'] == 'Solr'){
			$xml = @file_get_contents($configArray['Index']['url'] . '/admin/cores');

			if ($xml){
				$options = array(
					'parseAttributes' => 'true',
					'keyAttribute'    => 'name'
				);
				$unxml   = new XML_Unserializer($options);
				$unxml->unserialize($xml);
				$data = $unxml->getUnserializedData();
				$interface->assign('data', $data['status']);
			}

			$masterIndexUrl = str_replace(':80', ':81', $configArray['Index']['url']) . '/admin/cores';
			$masterXml      = @file_get_contents($masterIndexUrl);

			if ($masterXml){
				$options = array(
					'parseAttributes' => 'true',
					'keyAttribute'    => 'name'
				);
				$unxml   = new XML_Unserializer($options);
				$unxml->unserialize($masterXml);
				$masterData = $unxml->getUnserializedData();
				$interface->assign('master_data', $masterData['status']);
			}
		}

		$this->display('home.tpl', 'Solr Information');
	}

	function getAllowableRoles(){
		return array('userAdmin', 'opacAdmin');
	}
}
