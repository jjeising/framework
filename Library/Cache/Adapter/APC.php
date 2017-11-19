<?php
	
	/*
	 * Cache Adpater APC
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Cache'
	);
	
	class Cache_Adapter_APC implements CacheAdapter {
		
		public function get($key) {
			return apc_fetch($key);
		}
		
		public function add($key, $value, $ttl = null) {
			return apc_add($key, $value, $ttl);
		}
		
		public function set($key, $value, $ttl = null) {
			return apc_store($key, $value, $ttl);
		}
		
		public function delete($key) {
			return apc_delete($key);
		}
		
		public function increment($key, $offset = 1) {
			return apc_inc($key, $offset);
		}
		
		public function decrement($key, $offset = 1) {
			return apc_dec($key, $offset);
		}
		
	}
