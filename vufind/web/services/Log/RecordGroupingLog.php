<?php
require_once ROOT_DIR . '/services/Log/LogAdmin.php';

class RecordGroupingLog extends Log_Admin {

	public $pageTitle = 'Record Grouping Log';
	public $logTemplate = 'recordGroupingLog.tpl';
//	public $columnToFilterBy = 'numWorksProcessed';

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'cataloging');
	}
}
