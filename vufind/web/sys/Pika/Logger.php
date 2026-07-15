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

		$logLevel     = isset($configArray['Logging']['logLevel']) ? strtoupper($configArray['Logging']['logLevel']) : 'ERROR';
		$logPath      = $configArray['Logging']['file'];
		$logPathParts = explode(":", $logPath);
		$logFile      = $logPathParts[0];
		if ($configArray['System']['debug'] == true){
			$this->pushHandler(new BrowserConsoleHandler(MonoLogger::DEBUG));
		}

		$this->pushHandler(new StreamHandler($logFile, constant(MonoLogger::class . '::' . $logLevel))); //constant(MonoLogger::class . '::' . $logLevel)

		if($registerErrorHandler) {
			// Route PHP warnings/notices/errors, uncaught exceptions, and fatal
			// errors into this Monolog logger. registerErrorHandler() is called
			// with $callPrevious = false so PHP's built-in handler does not also
			// run after Monolog logs the error -- that keeps warnings out of the
			// web output (display_errors) and avoids double-logging.
			$errorHandler = new ErrorHandler($this);
			$errorHandler->registerErrorHandler([], false);
			$errorHandler->registerExceptionHandler();
			$errorHandler->registerFatalHandler();
		}
	}
    
    public function warn(string $message, array $context = []): void
    {
       parent::warning($message, $context);
    }
}
