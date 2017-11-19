<?php
	
	/*
	 * HTTP Response
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class HTTP_Response {
		
		protected $_response;
		
		protected $_contentType;
		protected $_charset;
		
		private static $_responseCodes = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			307 => 'Temporary Redirect',
			
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			509 => 'Bandwidth Limit Exceeded'
		);
		
		const CODE_CONTINUE = 100;
		
		const CODE_OK = 200;
		const CODE_NO_CONTENT = 204;
		
		const CODE_MOVED_PERMANENTLY = 301;
		const CODE_FOUND = 302;
		const CODE_NOT_MODIFIED = 304;
		
		const CODE_BAD_REQUEST = 400;
		const CODE_UNAUTHORIZED = 401;
		const CODE_FORBIDDEN = 403;
		const CODE_NOT_FOUND = 404;
		const CODE_GONE = 410;
		
		const CODE_INTERNAL_SERVER_ERROR = 500;
		
		public function __construct($code = 200, array $headers = array(), $content = '') {
			$this->_response = array(
				(int) $code,
				$headers,
				(string) $content
			);
		}
		
		public static function fromArray(array $response) {
			return new static(
				$response[0],
				$response[1],
				$response[2]
			);
		}
		
		public function __toString() {
			return $this->_response[2];
		}
		
		// TODO: "freeze" or similar (check rails naming), should be readonly afterwards (constructor?)
		
		public function setContentType($type, Request $request = null) {
			if ($request !== null && $request->accepts($type) !== true) {
				return false;
			}
			
			$this->_contentType = $type;
			
			return true;
		}
		
		public function getCharset() {
			return $this->_charset;
		}
		
		public function setCharset($charset) {
			$this->_charset = $charset;
		}
		
		public function getCode() {
			return $this->_response[0];
		}
		
		public function setCode($code) {
			$this->_response[0] = (int) $code;
		}
		
		public function getHeader($header) {
			if (!isset($this->_response[1][$header])) {
				return false;
			}
			
			return $this->_response[1][$header];
		}
		
		public function getHeaders() {
			return $this->_response[1];
		}
		
		public function addHeader($name, $value = null) {
			$this->_response[1][$name] = $value;
		}
		
		public function removeHeader($name) {
			unset($this->_response[1][$name]);
		}
		
		public function addContent($content) {
			$this->_response[2] .= $content;
		}
		
		public function setContent($content) {
			$this->_response[2] = $content;
		}
		
		public function resetContent() {
			$this->_response[2] = '';
		}
		
		public static function getMessage($code) {
			if (!isset(self::$_responseCodes[$code])) {
				return false;
			}
			
			return self::$_responseCodes[$code];
		}
		
		// TODO: move to ::fromError or similar
		public static function error($code) {
			if (!isset(self::$_responseCodes[$code])) {
				throw new InvalidArgumentException('invalid response code');
			}
			
			return new Response(
				$code,
				array(
					'Content-Type: text/html; charset=utf-8'
				),
				sprintf(
					"<!DOCTYPE html>\r\n<html>\r\n<head>\r\n<title>%1\$s - %2\$s</title>\r\n<meta charset=\"utf-8\" />\r\n</head>\r\n<body>\r\n<h1>%1\$s - %2\$s</h1>\r\n</body>\r\n</html>",
					$code,
					self::$_responseCodes[$code]
				)
			);
		}
		
		// TODO: streaming
	}
	
	class DoubeRenderExceptions extends Exception {}
