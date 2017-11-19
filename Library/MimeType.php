<?php
	
	/*
	 * MimeType
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class MimeType {
		
		private static $_extensions = [
			'text' => 'text/plain',
			'txt' => 'text/plain',
			
			'html' => 'text/html',
			'xhtml' => 'text/html',
			
			'js' => 'text/javascript',
			
			'css' => 'text/css',
			
			'ics' => 'text/calendar',
			
			'csv' => 'text/csv',
			
			'xml' => 'application/xml',
			
			'rss' => 'application/rss+xml',
			
			'atom' => 'application/atom+xml',
			
			'json' => 'application/json',
			
			'jpeg' => 'image/jpep',
			'jpg' => 'image/jpeg',
			
			'png' => 'image/png',
			
			'gif' => 'image/gif',
			
			'mp4' => 'video/mp4',
			'webm' => 'video/webm',
			'mp3' => 'audio/mpeg',
			'ogg' => 'application/ogg'
		];
		
		private static $_types = [
			'text/plain' => [['text', 'txt']],
			
			'text/html' => [
				['html', 'xhtml'],
				['application/xhtml+xml']
			],
			'application/xhtml+xml' => 'text/html',
			
			'text/javascript' => [
				['js'],
				['application/javascript', 'application/x-javascript']
			],
			'application/javascript' => 'text/javascript',
			'application/x-javascript' => 'text/javascript',
			
			'text/css' => [['css']],
			'text/calendar' => [['ics']],
			'text/csv' => [['csv']],
			
			'application/xml' => [
				['xml'],
				['text/xml', 'application/x-xml']
			],
			'text/xml' => 'application/xml',
			'application/x-xml' => 'application/xml',
			
			'application/rss+xml' => [['rss']],
			'application/atom+xml' => [['atom']],
			
			'application/json' => [
				['json'],
				['text/x-json', 'application/jsonrequest']
			],
			'text/x-json' => 'application/json',
			'application/jsonrequest' => 'application/json',
			
			'image/jpeg' => [['jpeg', 'jpg']],
			'image/png' => [['png']],
			'image/gif' => [['gif']],
			
			'video/mp4' => [['mp4']],
			'video/webm' => [['webm']],
			'audio/mpeg' => [['mp3']],
			'application/ogg' => [['ogg']]
		];
		
		public static function register($type, $extensions, $synonyms = array()) {
			if (!is_array($extensions)) {
				$extensions = array($extensions);
			}
			
			self::$_types[$type] = array($extensions, $synonyms);
			
			foreach ($extensions as $extension) {
				self::$_extensions[$extension] = $type;
			}
			
			if (!empty($synonyms)) {
				foreach ($synonyms as $synonym) {
					self::$_types[$synonym] = $type;
				}
			}
			
			return true;
		}
		
		public static function registerExtension($type, $extension) {
			self::$_extensions[$extension] = $type;
			
			return true;
		}
		
		public static function get($type) {
			if (!isset(self::$_types[$type])) {
				return false;
			}
			
			if (!is_array(self::$_types[$type])) {
				return self::$_types[self::$_types[$type]];
			}
			
			return self::$_types[$type];
		}
		
		public static function getByExtension($extension) {
			if (!isset(self::$_extensions[$extension])) {
				return false;
			}
			
			return self::$_extensions[$extension];
		}
		
		public static function isEquivalentExtension($extension, $string) {
			if ($extension === $string) {
				return true;
			}
			
			if (!isset(self::$_types[self::$_extensions[$extension]])) {
				return false;
			}
			
			return in_array($string, self::$_types[self::$_extensions[$extension]][0]);
		}
		
	}
