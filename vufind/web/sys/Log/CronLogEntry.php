<?php

require_once ROOT_DIR . '/sys/Log/LogEntry.php';

class CronLogEntry extends LogEntry {
	public $__table = 'cron_log';
	public $notes;

	private $_processes = null;
	private $_hadErrors;

	function processes(){
		if (is_null($this->_processes)){
			$this->_processes            = array();
			$cronProcessLogEntry         = new CronProcessLogEntry();
			$cronProcessLogEntry->cronId = $this->id;
			$cronProcessLogEntry->orderBy('processName');
			if ($cronProcessLogEntry->find()){
				$this->_hadErrors = false;
				while ($cronProcessLogEntry->fetch()){
					$this->_processes[] = clone $cronProcessLogEntry;
					if ($cronProcessLogEntry->numErrors > 0){
						$this->_hadErrors = true;
					}
				}
			}
		}
		return $this->_processes;
	}

	function getNumProcesses(){
		return count($this->processes());
	}

	function getHadErrors(){
		$this->processes();
		return $this->_hadErrors;
	}

}
