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

class MemoryWatcher{
	private $lastMemory = 0;
	private $firstMemory = 0;
	private $memoryMessages;
	private $memoryLoggingEnabled = false;


	public function __construct() {
		$this->MemoryWatcher();
	}

	public function MemoryWatcher(){
		global $configArray;
		if ($configArray){
			if (isset($configArray['System']['logMemoryUsage'])) {
				$this->memoryLoggingEnabled = $configArray['System']['logMemoryUsage'];
			}
		}else{
			$this->memoryLoggingEnabled = true;
		}

		$startMemory = memory_get_usage();
		$this->lastMemory = $startMemory;
		$this->firstMemory = $startMemory;
		$this->memoryMessages = array();
	}

	public function logMemory($message){
		if ($this->memoryLoggingEnabled){
			$curTime = memory_get_usage();
			$elapsedTime = number_format($curTime - $this->lastMemory);
			$totalElapsedTime = number_format($curTime - $this->firstMemory);
			$this->memoryMessages[] = "\"$message\",\"$elapsedTime\",\"$totalElapsedTime\"";
			$this->lastMemory = $curTime;
		}
	}

	public function enableMemoryLogging($enable){
		$this->memoryLoggingEnabled = $enable;
	}

	public function writeMemory(){
		if ($this->memoryLoggingEnabled){
			$curMemoryUsage = memory_get_usage();
			$memoryChange = $curMemoryUsage - $this->lastMemory;
			$this->memoryMessages[] = "Finished run: $curMemoryUsage ($memoryChange bytes)";
			$this->lastMemory = $curMemoryUsage;
			global $logger;
			$totalMemoryUsage = number_format($curMemoryUsage - $this->firstMemory);
			$timingInfo = "\r\nMemory usage for: " . $_SERVER['REQUEST_URI'] . "\r\n";
			$timingInfo .= implode("\r\n", $this->memoryMessages);
			$timingInfo .= "\r\nFinal Memory usage was: $totalMemoryUsage bytes.";
			$peakUsage = number_format(memory_get_peak_usage());
			$timingInfo .= "\r\nPeak Memory usage was: $peakUsage bytes.\r\n";
			$logger->log($timingInfo, PEAR_LOG_NOTICE);
		}
	}

	function __destruct() {
		if ($this->memoryLoggingEnabled){
			global $logger;
			if ($logger){
				$curMemoryUsage = memory_get_usage();
				$totalMemoryUsage = number_format($curMemoryUsage - $this->firstMemory);
				$timingInfo = "\r\nMemory usage for: " . $_SERVER['REQUEST_URI'] . "\r\n";
				if (count($this->memoryMessages) > 0){
					$timingInfo .= implode("\r\n", $this->memoryMessages);
				}
				$timingInfo .= "\r\nFinal Memory usage in destructor was: $totalMemoryUsage bytes.\r\n";
				$logger->log($timingInfo, PEAR_LOG_NOTICE);
			}
		}
	}
}
