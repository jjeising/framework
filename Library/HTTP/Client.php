<?php
	
	/*
	 * HTTP Client
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'HTTP/Client/Response'
	);
	
	class HTTP_Client {
		
		protected $_curl;
		
		protected $_cookies;
		protected $_options;
		
		protected $_headers;
		
		const KEEP_REFERER = 4096;
		const KEEP_COOKIES = 4097;
		
		public function __construct(array $options = []) {
			$this->_curl = curl_init();
			$this->_cookies = [];
			$this->_options = [
				self::KEEP_REFERER => true,
				self::KEEP_COOKIES => true,
			];
			
			curl_setopt_array($this->_curl, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false, // TODO: as config option
				CURLOPT_HEADER => true
			]);
			
			if (!empty($options)) {
				foreach($options as $option => $value) {
					$this->setOption($option, $value);
				}
			}
		}
		
		public function __clone() {
			$this->_curl = curl_copy_handle($this->_curl);
		}
		
		public function __destruct() {
			curl_close($this->_curl);
		}
		
		public function setOption($option, $value) {
			switch($option) {
				case self::KEEP_REFERER:
				case self::KEEP_COOKIES:
					$this->_options[$option] = $value;
					break;
				default:
					curl_setopt($this->_curl, $option, $value);
					break;
			}
		}
		
		// TODO: store in _options
		public function setTimeout($timeout) {
			curl_setopt($this->_curl, CURLOPT_TIMEOUT, $timeout);
		}
		
		public function setProxy($proxy, $user = null, $password = '') {
			curl_setopt($this->_curl, CURLOPT_PROXY, $proxy);
			
			if ($user !== null) {
				curl_setopt($this->_curl, CURLOPT_PROXYUSERPWD, $user . ':' . $password);
			}
		}
		
		public function getCookie($cookie) {
			if (!isset($this->_cookies[$cookie])) {
				return false;
			}
			
			return $this->_cookies[$cookie];
		}
		
		// TODO: domain, path?
		public function setCookie($cookie, $value) {
			$this->_cookies[$cookie] = $value;
		}
		
		public function removeCookie($cookie) {
			unset($this->_cookies[$cookie]);
		}
		
		public function setReferer($referer) {
			curl_setopt($this->_curl, CURLOPT_REFERER, $referer);
		}
		
		public function setUserAgent($agent) {
			curl_setopt($this->_curl, CURLOPT_USERAGENT, $agent);
		}
		
		public function setAuthentication($user, $password = '', $safe = false) {
			curl_setopt($this->_curl, CURLOPT_HTTPAUTH, ($safe)? CURLAUTH_ANYSAFE : CURLAUTH_ANY); 
			curl_setopt($this->_curl, CURLOPT_USERPWD, $user . ':' . $password); 
		}
		
		public function addHeader($name, $value = null) {
			$this->_headers[$name] = ($value === null)?
				$name : ($name . ': ' . $value);
		}
		
		public function removeHeader($name) {
			unset($this->_headers[$name]);
		}
		
		public function head($url, $port = null) {
			return $this->_request($url, $port, [CURLOPT_NOBODY => true]);
		}
		
		public function get($url, $port = null) {
			return $this->_request($url, $port);
		}
		
		// TODO: move port to the end?
		public function post($url, array $data = [], $port = null, $formData = false) {
			return $this->_request($url, $port, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => ($formData)? $data : http_build_query($data)
			]);
		}
		
		// TODO: maybe add postFile/postMultipart?
		
		// TODO: DELETE/PUT via CURLOPT_CUSTOMREQUEST
		
		public function clearCookies() {
			$this->_cookies = [];
		}
		
		protected function _request($url, $port = null, array $options = []) {
			$curl = curl_copy_handle($this->_curl);
			
			curl_setopt($curl, CURLOPT_URL, $url);
			
			// TODO: handle path dependent cookies?
			// TODO: handle domain dependent cookies?
			if (!empty($this->_cookies)) {
				$cookies = '';
				
				foreach($this->_cookies as $cookie => $value) {
					$cookies .= $cookie . '=' . $value . ';';
				}
				
				curl_setopt($curl, CURLOPT_COOKIE, substr($cookies, 0, -1));
			}
			
			if ($port !== null) {
				curl_setopt($curl, CURLOPT_PORT, $port);
			}
			
			if (!empty($this->_headers)) {
				curl_setopt(
					$curl,
					CURLOPT_HTTPHEADER,
					array_values($this->_headers)
				);
			}
			
			if (!empty($options)) {
				curl_setopt_array($curl, $options);
			}
			
			$content = curl_exec($curl);
			$info = curl_getinfo($curl);
			
			if ($content === false) {
				$header = [];
				$response = new HTTP_Client_Response(
					HTTP_Client_Response::CODE_FAILED
				);
			} else {
				$header = self::_parseHeader(
					substr($content, 0, $info['header_size'])
				);
				
				$response = new HTTP_Client_Response(
					$info['http_code'],
					$header,
					substr($content, $info['header_size'])
				);
			}
			
			curl_close($curl);
			
			if ($this->_options[self::KEEP_REFERER] !== false) {
				curl_setopt($this->_curl, CURLOPT_REFERER, $url);
			}
			
			// TODO: this expects normalized names
			if (isset($header['Set-Cookie']) and
				$this->_options[self::KEEP_REFERER] !== false) {
				$this->_cookies = array_merge(
					$this->_cookies,
					self::_parseSetCookie($header['Set-Cookie'])
				);
			}
			
			return $response;
		}
		
		protected static function _parseHeader($content) {
			$header = [];
			
			foreach (explode("\r\n", $content) as $line) {
				$parts = explode(':', $line, 2);
				
				// TODO: implode further content?
				if (count($parts) !== 2) {
					continue;
				}
				
				// TODO: normalize names? See Set-Cookie in _request()
				// TODO: accept latest header? Append?
				$header[trim($parts[0])] = trim($parts[1]);
			}
			
			return $header;
		}
		
		protected static function _parseSetCookie($content) {
			$cookies = [];
			
			foreach (explode(';', $content) as $part) {
				$part = trim($part);
				
				if (strpos($part, '=') === false) {
					continue;
				}
				
				$cookie = explode('=', $part);
				
				// TODO: ?
				// TODO: check path?
				if ($cookie[0] === 'path') {
					continue;
				}
				
				$cookies[$cookie[0]] = $cookie[1];
			}
			
			return $cookies;
		}
		
	}
