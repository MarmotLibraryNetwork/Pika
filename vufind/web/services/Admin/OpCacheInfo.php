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
 * User: pbrammeier
 * Date: 10/30/2014
 * Time: 4:23 PM
 *
 */

require_once ROOT_DIR . '/services/Admin/Admin.php';

class Admin_OpCacheInfo extends Admin_Admin {
	function launch() {
		global $interface;

		ob_start();
		include_once 'opcache-admin-include.php';
		$info = ob_get_contents();
		ob_end_clean();

		$interface->assign("info", $info);
		$interface->assign('title', 'OpCache Information');

		$this->display('adminInfo.tpl', 'OpCache Information');
	}

	function getAllowableRoles() {
		return array('opacAdmin');
	}
}
