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
 * Returns information about PHP
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 4/21/14
 * Time: 11:18 AM
 *
 * Modified 10-31-2014. plb
 */

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';

class Admin_PHPInfo extends Admin_Admin {
	function launch() {
		global $interface;

		ob_start();
		phpinfo();
		$info = ob_get_contents();
		ob_end_clean();

		// clean off unneeded html
		$info = strstr($info, '<div');
		$info = substr($info, 0,strrpos($info, '</div>')+6); //+6 to include closing tag
		// re-add slightly modified styling

		$info .= '<style>
#maincontent {background-color: #ffffff; color: #000000;}
#maincontent, td, th, h1, h2 {font-family: sans-serif;}
pre {margin: 0; font-family: monospace;}
#maincontent a:link {color: #000099; text-decoration: none; background-color: #ffffff;}
#maincontent a:hover {text-decoration: underline;}
#maincontent table {border-collapse: collapse;}
.center {text-align: center;}
.center table { margin-left: auto; margin-right: auto; text-align: left;}
.center th { text-align: center !important; }
td, th { border: 1px solid #000000; font-size: 75%; vertical-align: baseline;}
h1 {font-size: 150%;}
h2 {font-size: 125%;}
.p {text-align: left;}
.e {background-color: #ccccff; font-weight: bold; color: #000000;}
.h {background-color: #9999cc; font-weight: bold; color: #000000;}
.v {background-color: #cccccc; color: #000000;}
.vr {background-color: #cccccc; text-align: right; color: #000000;}
#maincontent img {float: right; border: 0;}
#maincontent hr {width: 600px; background-color: #cccccc; border: 0; height: 1px; color: #000000;}
</style>';

		$interface->assign("info", $info);
		$interface->assign('title', 'PHP Information');

		$this->display('adminInfo.tpl', 'PHP Information');
	}

	function getAllowableRoles() {
		return array('opacAdmin');
	}
}
