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
			$json = @file_get_contents($configArray['Index']['url'] . '/admin/cores');

			if (!empty($json)){
				$data = json_decode($json, true);
				$interface->assign('data', $data['status']);
			}

			$masterIndexUrl = str_replace('8080', $configArray['Reindex']['solrPort'], $configArray['Index']['url']) . '/admin/cores';
			$masterJson      = @file_get_contents($masterIndexUrl);

			if ($masterJson){
				$masterData = json_decode($masterJson, true);
				$interface->assign('master_data', $masterData['status']);
			}
		}

		$this->display('home.tpl', 'Solr Information');
	}

	function getAllowableRoles(){
		return ['userAdmin', 'opacAdmin'];
	}
}
