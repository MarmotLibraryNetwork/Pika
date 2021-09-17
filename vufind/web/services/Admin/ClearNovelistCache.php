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
 * Clear the Novelist cache.
 *
 * @category Pika
 * @author C.J. O'Hara <pika@marmot.org>
 * Date: 9/16/2021
 * Time: 4:42 PM
 */
require_once ROOT_DIR . '/services/Admin/Admin.php';

class Admin_ClearNovelistCache extends Admin_Admin{

	function launch(){
		global $interface;
		require_once ROOT_DIR . '/sys/Novelist/Novelist3.php';
		require_once ROOT_DIR . '/sys/Novelist/NovelistData.php';
		if(isset($_REQUEST['submit'])){
			$novelist = New NovelistData;
			$novelist->whereAdd("id like '%'");
			$novelist->delete(true);
		}

		$cache = new NovelistData();
		$numCachedObjects = $cache->count();
		$interface->assign('numCachedObjects', $numCachedObjects);
		$this->display('clearNovelistCache.tpl', 'Clear Novelist Cache');

	}

	function getAllowableRoles(){
		return array('opacAdmin','libraryAdmin');
	}
}