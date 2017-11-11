<?php
	
	/*
	 * Request
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Request {
		
		protected $_environment;
		
		protected $_method;
		protected $_scheme;
		protected $_languages;
		protected $_types;
		protected $_contentType = null;
		protected $_charset = null;
		
		protected $_URI;
		
		protected static $_body;
		
		const SCHEME_HTTP = 'http';
		const SCHEME_HTTPS = 'https';
		
		const METHOD_DELETE = 'DELETE';
		const METHOD_GET = 'GET';
		const METHOD_HEAD = 'HEAD';
		const METHOD_OPTIONS = 'OPTIONS';
		const METHOD_PATCH = 'PATCH';
		const METHOD_POST = 'POST';
		const METHOD_PUT = 'PUT';
		
		public function __construct(array $environment = null) {
			if ($environment === null) {
				$this->_environment = &$_SERVER;
			} else {
				$this->_environment = $environment;
			}
			
			if (!isset($this->_environment['HTTP_HOST'])) {
				$this->_environment['HTTP_HOST'] = '127.0.0.1';
			}
			
			if (!isset($this->_environment['REQUEST_METHOD'])) {
				$this->_method = 'GET';
			} else {
				$this->_method = strtoupper($this->_environment['REQUEST_METHOD']);
			}
			
			if ((
					isset($this->_environment['HTTPS']) and
					$this->_environment['HTTPS'] === 'on'
				) or (
					isset($this->_environment['HTTP_X_FORWARDED_SSL']) and
					$this->_environment['HTTP_X_FORWARDED_SSL'] === 'on'
				) or (
					isset($this->_environment['HTTP_X_FORWARDED_SCHEME']) and
					strcasecmp($this->_environment['HTTP_X_FORWARDED_SCHEME'], self::SCHEME_HTTPS)
			)) {
				$this->_scheme = self::SCHEME_HTTPS;
			} else {
				$this->_scheme = self::SCHEME_HTTP;
			}
			
			/*
			} elseif (isset($environment['HTTP_X_FORWARDED_PROTO'])) {
				$this->_scheme = current(explode(',', $environment['HTTP_X_FORWARDED_PROTO']));
			*/
			
			if (!empty($this->_environment['CONTENT_TYPE'])) {
				$contentType = self::_parseHeaderValue($this->_environment['CONTENT_TYPE'], 1);
				$this->_contentType = $contentType[0][0];
				
				if (isset($contentType[1]['charset'])) {
					$this->_charset = $contentType[1]['charset'];
				}
			}
			
			$this->_languages = array();
			
			if (!empty($this->_environment['HTTP_ACCEPT_LANGUAGE'])) {
				$this->_languages = self::_parseAcceptHeaderValue($this->_environment['HTTP_ACCEPT_LANGUAGE']);
			}
			
			$this->_types = array();
			
			if (!empty($this->_environment['HTTP_ACCEPT'])) {
				$this->_types = self::_parseAcceptHeaderValue($this->_environment['HTTP_ACCEPT']);
			}
			
			$this->_URI = $this->_parseURI($this->_environment);
		}
		
		public function isHEADRequest() {
			return $this->_method === self::METHOD_HEAD;
		}
		
		public function isGETRequest() {
			return $this->_method === self::METHOD_GET;
		}
		
		public function isPOSTRequest() {
			return $this->_method === self::METHOD_POST;
		}
		
		public function getMethod() {
			return $this->_method;
		}
		
		public function getScheme() {
			return $this->_scheme;
		}
		
		public function isSecure() {
			return $this->_scheme === self::SCHEME_HTTPS;
		}
		
		/*
		public function getHost() {
			
		}
		
		public function getPort() {
			
		}
		
		public function getHostWithPort() {
			
		}
		*/
		
		public function getRemoteIP() {
			if (!isset($this->_environment['REMOTE_ADDR'])) {
				return '127.0.0.1';
			}
			
			// TODO: X_FORWARDED_FOR or CLIENT_IP?
			return $this->_environment['REMOTE_ADDR'];
		}
		
		public function getPath() {
			return $this->_URI['path'];
		}
		
		public function getURL() {
			return $this->_scheme . '://' .
				$this->_environment['HTTP_HOST'] .
				$this->_URI['base'] .
				$this->_URI['path'];
		}
		
		public function getSegments() {
			return $this->_URI['segments'];
		}
		
		public function getRootURI() {
			return $this->_URI['base'];
		}
		
		public function getRootURL() {
			return $this->_scheme . '://' .
				$this->_environment['HTTP_HOST'] .
				$this->_URI['base'];
		}
		
		public function getSecureRootURL() {
			return 'https://' .
				$this->_environment['HTTP_HOST'] .
				$this->_URI['base'];
		}
		
		public function getContentType() {
			return $this->_contentType;
		}
		
		public function getCharset() {
			return $this->_charset;
		}
		
		public function isXMLHTTPRequest() {
			return isset($this->_environment['HTTP_X_REQUESTED_WITH']) and
				$this->_environment['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'; 
		}
		
		public function accepts($contentType) {
			if (isset($this->_types[$contentType])) {
				return true;
			}
			
			if (isset($this->_types['*/*'])) {
				return null;
			}
			
			return false;
		}
		
		public function acceptsAll() {
			return isset($this->_types['*/*']);
		}
		
		public function getQueryString() {
			if (!isset($this->_environment['QUERY_STRING'])) {
				return '';
			}
			
			return $this->_environment['QUERY_STRING'];
		}
		
		// TODO: getContentType() ?
		
		// TODO: getContentLength()
		
		public static function getBody() {
			if (self::$_body === null) {
				self::$_body = file_get_contents('php://input');
			}
			
			return self::$_body;
		}
		
		public function getParsedBody($contentType = null, $body = null) {
			if ($body === null) {
				$body = self::getBody();
			}
			
			if (
				$this->_contentType === null or (
					$contentType !== null and
					$contentType !== $this->_contentType
				)
			) {
				return false;
			}
			
			if ($body === '') {
				return null;
			}
			
			switch ($contentType) {
				case 'application/json':
					if (trim($body) === 'null') {
						return null;
					}
					
					if (($body = json_decode($body, true)) === null) {
						return false;
					}
					
					break;
			}
			
			return $body;
		}
		
		public static function parseStringFlat($string) {
			$parts = explode('&', $string);
			$values = [];
			
			foreach ($parts as $part) {
				if ($part === '') {
					continue;
				}
				
				$part = explode('=', $part, 2);
				$part[0] = trim($part[0]);
				
				if ($part[0] === '') {
					continue;
				}
				
				$pos = strpos($part[0], '[]');
				$part[0] = urldecode($part[0]);
				
				if (!isset($part[1])) {
					$part[1] = '';
				} else {
					$part[1] = urldecode($part[1]);
				}
				
				if ($pos !== false) {
					if (!isset($values[$part[0]])) {
						$values[$part[0]] = [];
					}
					
					$values[$part[0]][] = $part[1];
				} else {
					$values[$part[0]] = $part[1];
				}
			}
			
			return $values;
		}
		
		// TODO: is base included in path/segments?!
		protected function _parseURI(array &$environment) {
			$pathInfo = (!empty($environment['PATH_INFO']))?
				$environment['PATH_INFO'] : '/';
			
			$URI = array(
				'base' => '/',
				'path' => '',
				'segments' => array_filter(
					explode('/', trim($pathInfo, '/')),
					function ($segment) {
						return $segment !== '';
					}
				)
			);
			
			$URI['path'] = implode('/', $URI['segments']);
			
			if (empty($URI['segments'])) {
				$URI['segments'][] = '';
			}
			
			if (empty($_SERVER['REQUEST_URI'])) {
				return $URI;
			}
			
			$requestUri = $environment['REQUEST_URI'];
			
			if (!empty($environment['QUERY_STRING'])) {
				$requestUri = substr(
					$requestUri,
					0,
					-(strlen($environment['QUERY_STRING']) + 1)
				);
			} else {
				$requestUri = rtrim($requestUri, '?');
			}
			
			$requestUri = urldecode($requestUri);
			$filteredRequestUri = implode('/',
				array_filter(
					explode('/', urldecode($requestUri)),
					function ($segment) {
						return $segment !== '';
					}
				)
			);
			
			$URI['base'] = mb_substr(
				((substr($requestUri, 0, 1) === '/')? '/' : '') .
					$filteredRequestUri .
					((substr($requestUri, -1) === '/' and !empty($filteredRequestUri))? '/' : ''),
				0,
				-(mb_strlen($pathInfo))
			);
			
			$URI['base'] .= '/';
			
			return $URI;
		}
		
		protected static function _parseAcceptHeaderValue($header) {
			$values = [];
			
			foreach (self::_parseHeaderValue($header) as $value) {
				$values[$value[0]] = (
					isset($value[1]['q']) and
					filter_var($value[1]['q'], FILTER_VALIDATE_FLOAT)
				)? floatval($value[1]['q']) : 1;
			}
			
			arsort($values);
			
			return $values;
		}
		
		protected static function _parseHeaderValue($header, $limit = PHP_INT_MAX) {
			$values = [];
			$header = str_replace(' ', '', trim($header));
			
			foreach (explode(',', $header, $limit) as $item) {
				if (empty($item)) {
					continue;
				}
				
				$value = null;
				$position = strpos($item, ';');
				
				if ($position !== false) {
					$parameter = explode('=', substr($item, $position + 1), 2);
					$item = substr($item, 0, $position);
					
					if (isset($parameter[1])) {
						$value = [$parameter[0] => $parameter[1]];
					} else {
						$value = $parameter[0];
					}
				}
				
				if ($value === null) {
					$values[] = [$item];
				} else {
					$values[] = [$item, $value];
				}
			}
			
			return $values;
		}
		
	}
