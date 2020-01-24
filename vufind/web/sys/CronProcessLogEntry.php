<?php

class CronProcessLogEntry extends LogEntry {
	public $__table = 'cron_process_log';
	public $cronId;
	public $processName;
	public $numErrors;
	public $numUpdated;
	public $notes;

}