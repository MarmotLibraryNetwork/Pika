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

/**
 * Clear the Novelist cache.
 *
 * @category Pika
 * @author C.J. O'Hara <pika@marmot.org>
 * Date: 9/16/2021
 * Time: 4:42 PM
 */
require_once ROOT_DIR . '/services/Admin/Admin.php';

class Admin_NovelistInfo extends Admin_Admin {

	function launch(){
		global $interface;
		global $memCache;

		require_once ROOT_DIR . '/sys/Novelist/NovelistData.php';
		$checkRecord = false;
		if (isset($_REQUEST['submit']) && UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin', 'cataloging'])){

			if (isset($_REQUEST['checkISBN'])){
				$checkRecord = true;
				$checkISBN   = $_REQUEST['checkISBN'];
				$interface->assign('checkISBN', $checkISBN);

				require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
				$novelist     = new Novelist3();
				$json         = $novelist->getRawNovelistJSON($checkISBN);
				$novelistData = json_encode($json);
				$interface->assign('novelistData', $novelistData);
			}
		}
		if (isset($_REQUEST['truncateData']) && UserAccount::userHasRole('opacAdmin')){
			$novelist = new NovelistData;
			$novelist->query('TRUNCATE `novelist_data`');
			$memCache->flush();
		}


		$cache            = new NovelistData();
		$numCachedObjects = $cache->count();

		$interface->assign('numCachedObjects', $numCachedObjects);
		$interface->assign('checkRecord', $checkRecord);

		$this->display('novelistInfo.tpl', 'NoveList Information');

	}

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'cataloging'];
	}
}