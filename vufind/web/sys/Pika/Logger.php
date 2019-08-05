<?php
/**
 *
 *
 * @category Pika
 * @author   : Chris Froese
 * Date: 8/1/19
 *
 */
namespace Pika;

use \Monolog\Logger as MonoLogger;
use \Monolog\Handler\StreamHandler;
use \Monolog\Handler\PHPConsoleHandler;

class Logger extends MonoLogger {

	public function __construct($name) {
		parent::__construct($name);
		global $configArray;

		$logFile = $configArray['Logging']['logFile'];
		if($configArray['System']['debug'] == true) {
			$this->pushHandler(new \Monolog\Handler\BrowserConsoleHandler(\Monolog\Logger::DEBUG));
		}
		$this->pushHandler(new \Monolog\Handler\StreamHandler($logFile, \Monolog\Logger::DEBUG));
		\Monolog\ErrorHandler::register($this);
	}
}