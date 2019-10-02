<?php
/**
 * A PSR-16 memcache implementation for Pika
 *
 * @category Pika
 * @package  Cache
 * @author   Chris Froese
 * Date      8/5/19
 *
 */

namespace Pika;

use Psr\SimpleCache\CacheInterface;
use Pika\Cache\Exception as CacheException;
use Memcache;
use DateInterval;
use DateTime;

class Cache implements CacheInterface
{

	private $PSR16_RESERVED_CHARACTERS = ['{','}','(',')','/','@',':'];

	public  $handler;

	private $logger = false;

	/**
	 * Cache constructor.
	 * @param Memcache $handler Memcache handler object
	 */
	public function __construct(Memcache $handler)
	{
		global $configArray;
		$this->handler = $handler;
		if((bool)$configArray['System']['debug']) {
			$this->logger = new Logger('PikaCache');
		}
	}

	/**
	 * Fetches a value from the cache.
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 *
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 *
	 * @throws InvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function get($key, $default = null)
	{
		$return = $this->handler->get($key) ? $this->handler->get($key) : $default;
		$this->_log('Get', $key, $return);
		return $return;
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @param string                $key    The key of the item to store.
	 * @param mixed                 $value  The value of the item to store. Must be serializable.
	 * @param null|int|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
	 *                                      the driver supports TTL then the library may set a default value
	 *                                      for it or let the driver take care of that.
	 *
	 * @return bool True on success and false on failure.
	 *
	 * @throws InvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function set($key, $value, $ttl = null)
	{
		if ($ttl instanceof DateInterval) {
			$ttl = (new DateTime('now'))->add($ttl)->getTimeStamp() - time();
		}
		$return = (bool)$this->handler->set($key, $value, 0, (int)$ttl);
		$this->_log('Set', $key, $return);
		return $return;
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 *
	 * @return bool True if the item was successfully removed. False if there was an error.
	 *
	 * @throws InvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function delete($key)
	{
		$return = (bool)$this->handler->delete($key);
		$this->_log('Delete', $key, $return);
		return $return;
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool True on success and false on failure.
	 */
	public function clear()
	{
		$return = (bool)$this->handler->flush();
		$this->_log('Clear', 'All', $return);
		return $return;
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * @param iterable $keys    A list of keys that can obtained in a single operation.
	 * @param mixed    $default Default value to return for keys that do not exist.
	 *
	 * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as
	 *                  value.
	 *
	 * @throws InvalidArgumentException
	 *   MUST be thrown if $keys is neither an array nor a Traversable,
	 *   or if any of the $keys are not a legal value.
	 */
	public function getMultiple($keys, $default = null)
	{
		$defaults = array_fill(0, count($keys), $default);
		$return   = [];
		foreach ($keys as $key) {
			$return[$key] = $this->handler->get($key) ? $this->handler->get($key) : $default;
		}
		return $return;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @param iterable              $values  A list of key => value pairs for a multiple-set operation.
	 * @param null|int|DateInterval $ttl     Optional. The TTL value of this item. If no value is sent and
	 *                                       the driver supports TTL then the library may set a default value
	 *                                       for it or let the driver take care of that.
	 *
	 * @return bool True on success and false on failure.
	 *
	 * @throws InvalidArgumentException
	 *   MUST be thrown if $values is neither an array nor a Traversable,
	 *   or if any of the $values are not a legal value.
	 */
	public function setMultiple($values, $ttl = null)
	{
		if ($ttl instanceof DateInterval) {
			$ttl = (new DateTime('now'))->add($ttl)->getTimeStamp() - time();
		}

		foreach ($values as $key => $value) {
			if (!$this->handler->set($key, $value, 0, (int)$ttl)) {
				return false;
			}
			return true;
		}
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 *
	 * @return bool True if the items were successfully removed. False if there was an error.
	 *
	 * @throws InvalidArgumentException
	 *   MUST be thrown if $keys is neither an array nor a Traversable,
	 *   or if any of the $keys are not a legal value.
	 */
	public function deleteMultiple($keys)
	{
		foreach($keys as $key) {
			$this->handler->delete($key);
		}
		return true;
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * NOTE: It is recommended that has() is only to be used for cache warming type purposes
	 * and not to be used within your live applications operations for get/set, as this method
	 * is subject to a race condition where your has() will return true and immediately after,
	 * another script can remove it, making the state of your app out of date.
	 *
	 * @param string $key The cache item key.
	 *
	 * @return bool
	 *
	 * @throws InvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function has($key)
	{
		return $this->handler->get($key) ? true : false;
	}

	private function checkReservedCharacters($key)
	{
		if (!is_string($key)) {
			$message = sprintf('key %s is not a string.', $key);
			throw new CacheException($message);
		}
		foreach ($this->PSR16_RESERVED_CHARACTERS as $needle) {
			if (strpos($key, $needle) !== false) {
				$message = sprintf('%s string is not a legal value.', $key);
				throw new CacheException($message);
			}
		}
	}

	private function _log($action, $key, $result) {
		if($this->logger) {
			if($result != false) {
				$result = 'true';
			} else {
				$result = 'false';
			}
			$this->logger->info($action . ':' . $key . ':' . strval($result));
		}
	}
}