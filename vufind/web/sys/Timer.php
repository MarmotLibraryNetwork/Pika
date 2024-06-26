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

class Timer{
	private $lastTime = 0;
	private $firstTime = 0;
	private $timingMessages;
	private $timingsEnabled = false;
	private $minTimeToLog = 0;

	public function __construct($startTime = null){
		global $configArray;
		if ($configArray){
			if (isset($configArray['System']['timings'])) {
				$this->timingsEnabled = (bool) $configArray['System']['timings'];
			}
			if (isset($configArray['System']['minTimeToLog'])){
				$this->minTimeToLog = $configArray['System']['minTimeToLog'];
			}
		}else{
			$this->timingsEnabled = true;
		}

		if (!$startTime) {
			$startTime = microtime(true);
		}

		$this->lastTime  = $startTime;
		$this->firstTime = $startTime;
		$this->timingMessages = [];
	}

	public function logTime($message){
		if ($this->timingsEnabled){
			$curTime     = microtime(true);
			$elapsedTime = round($curTime - $this->lastTime, 4);
			if ($elapsedTime > $this->minTimeToLog){
				$totalElapsedTime       = round($curTime - $this->firstTime, 4);
				$this->timingMessages[] = "\"$message\",\"$elapsedTime\",\"$totalElapsedTime\"";
			}
			$this->lastTime = $curTime;
		}
	}

	public function enableTimings($enable){
		$this->timingsEnabled = $enable;
	}

	// Destruct method should generally do the write outs
	public function writeTimings(){
		if ($this->timingsEnabled){
			$minTimeToLog = 0;

			$curTime = microtime(true);
			$elapsedTime = round($curTime - $this->lastTime, 4);
			if ($elapsedTime > $minTimeToLog){
				$this->timingMessages[] = "Finished run: $curTime ($elapsedTime sec)";
			}
			$this->lastTime = $curTime;
			global $pikaLogger;
			$totalElapsedTime = round(microtime(true) - $this->firstTime, 4);
			$timingInfo       = "\r\nTiming for: " . $_SERVER['REQUEST_URI'] . "\r\n";
			$timingInfo       .= implode("\r\n", $this->timingMessages);
			$timingInfo       .= "\r\nTotal Elapsed time was: $totalElapsedTime seconds.\r\n";
			$pikaLogger->notice($timingInfo);
		}
	}

	function __destruct() {
		if ($this->timingsEnabled){
			global $pikaLogger;
			if ($pikaLogger){
				$pikaLogger->notice('Timing for: ' . $_SERVER['REQUEST_URI']);
				foreach ($this->timingMessages as $message){
					$pikaLogger->notice($message);
				}
				$totalElapsedTime = round(microtime(true) - $this->firstTime, 4);
				$pikaLogger->notice("Total Elapsed time was: $totalElapsedTime seconds.");
			}
		}
	}
}
