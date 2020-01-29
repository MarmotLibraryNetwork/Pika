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
 * App.php
 *
 * Controller for Pika dependencies
 *
 * @category Pika
 * @package  App
 * @author   Chris Froese
 *
 */
namespace Pika;

use Pika\Logger;
use Pika\Cache;

class App {

	public $cache;
	public $logger;


	public function __construct($loggerName = false) {
		global $configArray;

		if(!$loggerName | !is_string($loggerName)) {
			$this->logger = new Logger('Pika');
		} else {
			$this->logger = new Logger($loggerName);
		}
		$cacheHandler = initCache();
		$this->cache = new Cache($cacheHandler);

	}
}
