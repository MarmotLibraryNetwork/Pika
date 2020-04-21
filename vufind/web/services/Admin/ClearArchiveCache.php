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
 * Control how subjects are handled when linking to the catalog.
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/22/2016
 * Time: 7:05 PM
 */
require_once ROOT_DIR . '/services/Admin/Admin.php';
class Admin_ClearArchiveCache extends Admin_Admin{

	function launch() {
		global $interface;

		require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
		if (isset($_REQUEST['submit'])){
			$cache = new IslandoraObjectCache();
			$cache->whereAdd("pid like '%'");
			$cache->delete(true);
		}

		$cache = new IslandoraObjectCache();
		$numCachedObjects = $cache->count();
		$interface->assign('numCachedObjects', $numCachedObjects);
		$this->display('clearArchiveCache.tpl', 'Clear Archive Cache');
	}

	function getAllowableRoles() {
		return array('archives');
	}
}
