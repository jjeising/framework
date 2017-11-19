<?php
	
	/*
	 * Hash
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Hash {
		
		public static function MD5($string) {
			return hash('md5', $string);
		}
		
		public static function SHA1($string) {
			return hash('sha1', $string);
		}
		
		public static function SHA512($string) {
			return hash('sha512', $string);
		}
		
		public static function CRC32($string) {
			return hash('crc32', $string);
		}
		
		public static function cryptMD5($string, $salt) {
			if (CRYPT_MD5 !== 1) {
				throw new RuntimeException('cryptMD5 is not supportet on this plattform');
			}
			
			return crypt($string, '$1$' . $salt . '$');
		}
		
		public static function sambaNT($string) {
			return strtoupper(bin2hex(hash('md4', mb_convert_encoding($string, 'UTF-16LE'), true)));
		}
		
		public static function ssha($string) {
			$salt = substr(pack("H*", hash('sha1', Random::hex(4) . $string)), 0, 4);
			return '{SSHA}' . base64_encode(hash('sha1', $string . $salt, true) . $salt);
		}
	}
