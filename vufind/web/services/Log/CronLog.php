<?php
require_once ROOT_DIR . '/services/Log/LogAdmin.php';

class CronLog extends Log_Admin{

	public $pageTitle ='Cron Log';
	public $logTemplate ='cronLog.tpl';

	function getAllowableRoles(){
		return array('opacAdmin');
	}
}
