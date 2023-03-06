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

require_once ROOT_DIR . '/sys/Log/LogEntry.php';

class CronLogEntry extends LogEntry {
	public $__table = 'cron_log';

	private $_processes = null;
	private $_hadErrors;

	function processes(){
		require_once ROOT_DIR . '/sys/Log/CronProcessLogEntry.php';
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
