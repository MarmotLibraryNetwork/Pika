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
/**
 * Pika Logger
 *
 * @category Pika
 * @package  Logger
 * @author   Chris Froese
 */
namespace Pika;

use Monolog\Handler\BrowserConsoleHandler;
use \Monolog\Logger as MonoLogger;
use \Monolog\Handler\StreamHandler;
use \Monolog\ErrorHandler;

class Logger extends MonoLogger {

	/**
	 * Logger constructor.
	 * @param string    $name
	 * @param bool      $registerErrorHandler
	 * @throws \Exception
	 */
	public function __construct($name, $registerErrorHandler = false) {
		parent::__construct($name);
		global $configArray;

		$logLevel = isset($configArray['Logging']['logLevel']) ? strtoupper($configArray['Logging']['logLevel']) : "ERROR";
		$logPath = $configArray['Logging']['file'];
		$logPathParts = explode(":", $logPath);
		$logFile = $logPathParts[0];
		if($configArray['System']['debug'] == true) {
			$this->pushHandler(new BrowserConsoleHandler(MonoLogger::DEBUG));
		}

		$this->pushHandler(new StreamHandler($logFile, constant(MonoLogger::class . '::' . $logLevel))); //constant(MonoLogger::class . '::' . $logLevel)

		if($registerErrorHandler) {
			ErrorHandler::register($this);
		}
	}
}
