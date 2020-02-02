<?php

require_once ROOT_DIR . '/sys/Log/LogEntry.php';

class ReindexLogEntry extends LogEntry {
	public $__table = 'reindex_log';   // table name
	public $id;
	public $startTime;
	public $lastUpdate;
	public $endTime;
	public $notes;
	public $numWorksProcessed;
	public $numListsProcessed;

}
