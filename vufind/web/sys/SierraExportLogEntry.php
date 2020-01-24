<?php

class SierraExportLogEntry extends LogEntry {
	public $__table = 'sierra_api_export_log';   // table name
	public $numRecordsToProcess;
	public $numRecordsProcessed;
	public $numErrors;
	public $numRemainingRecords;
	public $notes;

}
