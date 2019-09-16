<?php
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