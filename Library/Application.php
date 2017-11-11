<?php
	
	/*
	 * Application
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	function requires(...$dependencies) {
		static $_index = array();
		
		foreach ($dependencies as $file) {
			if (isset($_index[$file])) {
				continue;
			}
			
			if ($file[0] === '/') {
				require APPLICATION . $file . '.php';
			} else {
				require LIBRARY . $file . '.php';
			}
			
			$_index[$file] = true;
		}
	}
	
	function requiresSession() {
		if (session_status() !== PHP_SESSION_NONE) {
			return;
		}
		
		session_start();
	}
	
	class NotImplementedException extends LogicException { }
