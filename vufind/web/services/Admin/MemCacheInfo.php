<?php
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
		return array('opacAdmin');
	}
}
