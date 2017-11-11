<?php
	
	/*
	 * Cache
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Cache {
		
		private static $_prefix = '';
		
		private static $_adapter;
		
		public static function setPrefix($prefix) {
			self::$_prefix = $prefix;
		}
		
		public static function hasAdapter() {
			return (self::$_adapter !== null);
		}
		
		public static function setAdapter(CacheAdapter $adapter) {
			self::$_adapter = $adapter;
		}
		
		public static function get($key, Callable $callback = null, $ttl = 0) {
			$value = self::$_adapter->get(self::$_prefix . $key);
			
			if ($value === false and $callback !== null) {
				$value = $callback($key);
				
				if ($value !== false) {
					self::set($key, $value, $ttl);
				}
			}
			
			return $value;
		}
		
		public static function add($key, $value, $ttl = 0) {
			return self::$_adapter->add(self::$_prefix . $key, $value, $ttl);
		}
		
		public static function set($key, $value, $ttl = 0) {
			return self::$_adapter->set(self::$_prefix . $key, $value, $ttl);
		}
		
		public static function delete($key) {
			return self::$_adapter->delete(self::$_prefix . $key);
		}
		
		public static function increment($key, $offset = 1) {
			return self::$_adapter->increment(self::$_prefix . $key, $offset);
		}
		
		public static function decrement($key, $offset = 1) {
			return self::$_adapter->decrement(self::$_prefix . $key, $offset);
		}
		
		public static function ns($namespace) {
			$key = self::get($namespace);
			
			if ($key === false) {
				$key = time();
				
				if (!self::add($namespace, $key)) {
					return self::ns($namespace);
				}
			}
			
			return $namespace . dechex($key);
		}
		
		public static function invalidateNamespace($namespace) {
			return self::increment(self::ns($namespace));
		}
	}
	
	interface CacheAdapter {
		
		public function get($key);
		public function add($key, $value, $ttl = null);
		public function set($key, $value, $ttl = null);
		public function delete($key);
		
		public function increment($key);
		public function decrement($key);
		
	}
