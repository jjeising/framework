<?php
	
	/*
	 * Random
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Random {
		
		public static function hex($length) {
			return bin2hex(openssl_random_pseudo_bytes(ceil($length / 2))); 
		}
		
		public static function base64($bytes) {
			return base64_encode(openssl_random_pseudo_bytes($bytes));
		}
		
		public static function friendly($length) {		
			return substr(
				str_replace(
					['+', '/', '='],
					'0',
					base64_encode(
						openssl_random_pseudo_bytes($length)
					)
				),
				0,
				$length
			);
		}
		
	}
