<?php
/**
 *
 * @category Pika
 * @author   Chris Froese
 *
 *
 */
namespace Pika;

use Monolog\Handler\BrowserConsoleHandler;
use \Monolog\Logger as MonoLogger;
use \Monolog\Handler\StreamHandler;
use \Monolog\Handler\PHPConsoleHandler;
use \Monolog\ErrorHandler;

class Logger extends MonoLogger {

	public function __construct($name) {
		parent::__construct($name);
		global $configArray;

		$logFile = $configArray['Logging']['logFile'];
		if($configArray['System']['debug'] == true) {
			$this->pushHandler(new BrowserConsoleHandler(MonoLogger::DEBUG));
		}
		$this->pushHandler(new StreamHandler($logFile, MonoLogger::DEBUG));
		ErrorHandler::register($this);
	}
}