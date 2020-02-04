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
 * Handler for 404 errors based on httpd conf file
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 1/22/2016
 * Time: 9:42 AM
 */
require_once ROOT_DIR . '/Action.php';

class Error_Handle404 extends Action {
	function launch(){
		global $interface;
		$interface->assign('showBreadcrumbs', false);
		$this->display('404.tpl', 'Page Not Found');
	}
}
