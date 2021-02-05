<?php

/*
 * Pika Discovery Layer
 * Copyright (C) 2021  Marmot Library Network
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

namespace Pika;

use Pika\Cache;
use Pika\Logger;
/**
 * SierraDNA.php
 *
 * @category Pika
 * @package
 * @author   Chris Froese
 *
 */
class SierraDNA {

	private $connectionString;
	private $configArray;


	public function __construct() {
		global $configArray;
		$this->configArray = $configArray;
		$this->connectionString = $configArray['Catalog']['sierra_conn_php'];
	}

}