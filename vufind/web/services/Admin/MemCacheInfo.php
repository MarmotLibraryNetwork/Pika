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
 * Created by PhpStorm.
 * User: Pascal Brammeier
 * Date: 10/28/2014
 * Time: 12:08 PM
 *
 * Based on PHPInfo.php
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';

class Admin_MemCacheInfo extends Admin_Admin {
	function launch() {
		global $interface;

		include_once 'memcache-admin-include.php';
		$info = new memcacheAdmin();

		$interface->assign("info", $info->output);
		$interface->assign('title', 'MemCache Information');

		$this->display('adminInfo.tpl', 'MemCache Information');
	}

	function getAllowableRoles() {
		return ['opacAdmin'];
	}
}
