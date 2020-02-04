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
 * A PSR-16 memcache exception implementation for Pika
 *
 * @category Pika
 * @author   : Chris Froese
 * Date: 8/6/19
 *
 */

namespace Pika\Cache;

use Psr\SimpleCache\CacheException;


class Exception extends \RuntimeException implements Psr\SimpleCache\CacheException
{

}
