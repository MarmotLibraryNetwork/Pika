<?php
class Timer{
	private $lastTime = 0;
	private $firstTime = 0;
	private $timingMessages;
	private $timingsEnabled = false;
	private $minTimeToLog = 0;

	public function __construct($startTime = null){
		$this->Timer($startTime = null);

	}

	public function Timer($startTime = null){
		global $configArray;
		if ($configArray){
			if (isset($configArray['System']['timings'])) {
				$this->timingsEnabled = $configArray['System']['timings'];
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
		$this->timingMessages = array();
	}

	public function logTime($message){
		if ($this->timingsEnabled){
			$curTime = microtime(true);
			$elapsedTime = round($curTime - $this->lastTime, 4);
			if ($elapsedTime > $this->minTimeToLog){
				$totalElapsedTime = round($curTime - $this->firstTime, 4);
				$this->timingMessages[] = "\"$message\",\"$elapsedTime\",\"$totalElapsedTime\"";
			}
			$this->lastTime = $curTime;
		}
	}

	public function enableTimings($enable){
		$this->timingsEnabled = $enable;
	}

	public function writeTimings(){
		if ($this->timingsEnabled){
			$minTimeToLog = 0;

			$curTime = microtime(true);
			$elapsedTime = round($curTime - $this->lastTime, 4);
			if ($elapsedTime > $minTimeToLog){
				$this->timingMessages[] = "Finished run: $curTime ($elapsedTime sec)";
			}
			$this->lastTime = $curTime;
			global $logger;
			$totalElapsedTime =round(microtime(true) - $this->firstTime, 4);
			$timingInfo = "\r\nTiming for: " . $_SERVER['REQUEST_URI'] . "\r\n";
			$timingInfo .= implode("\r\n", $this->timingMessages);
			$timingInfo .= "\r\nTotal Elapsed time was: $totalElapsedTime seconds.\r\n";
			$logger->log($timingInfo, PEAR_LOG_NOTICE);
		}
	}

	function __destruct() {
		if ($this->timingsEnabled){
			global $logger;
			if ($logger){
				$totalElapsedTime =round(microtime(true) - $this->firstTime, 4);
				$timingInfo = "\r\nTiming for: " . $_SERVER['REQUEST_URI'] . "\r\n";
				$timingInfo .= implode("\r\n", $this->timingMessages);
				$timingInfo .= "\r\nTotal Elapsed time was: $totalElapsedTime seconds.\r\n";
				$logger->log($timingInfo, PEAR_LOG_NOTICE);
			}
		}
	}
}
