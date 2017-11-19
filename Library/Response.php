<?php
	
	/*
	 * Response
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'HTTP/Response'
	);
	
	class Response extends HTTP_Response {
		
		protected $_contentType = 'text/html';
		protected $_charset = 'utf-8';
		
		public function __toString() {
			if (headers_sent($file, $line)) {
				trigger_error(
					'Can only render or redirect once per action, headers already sent (output started at ' .
						$file . ':' . $line . ')',
					E_USER_ERROR
				);
			}
			
			if ($this->_contentType !== null) {
				header('Content-Type: ' . $this->_contentType .
					(($this->_charset !== null)?
						('; charset=' . $this->_charset) : '')
				);
			} elseif ($this->_charset !== null) {
				header('Content-Type: text/html; charset=' . $this->_charset);
			}
			
			header('HTTP/ ' . $this->_response[0]);
			
			foreach ($this->_response[1] as $name => $value) {
				if ($value === null) {
					header($name, true);
				} elseif (is_int($name)) {
					header($value, true);
				} else {
					header($name . ': ' . $value, true);
				}
			}
			
			return $this->_response[2];
		}
		
	}
