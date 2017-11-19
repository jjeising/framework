<?php
	
	/*
	 * HTTP Client Reponse
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'HTTP/Response'
	);
	
	class HTTP_Client_Response extends HTTP_Response {
		
		const CODE_FAILED = 0;
		
		public function toArray($contentType = null) {
			if ($contentType === null) {
				if (($contentType = $this->_parseOptionsHeader('Content-Type')) === false) {
					return false;
				}
				
				$contentType = $contentType[0];
			}
			
			$response = [];
			
			switch ($contentType) {
				case 'application/json':
					// TODO: check for "null" as $response
					if (($response = json_decode($this->_response[2], true)) === null) {
						return [];
					}
					
					break;
				default:
					return false;
			}
			
			return $response;
		}
		
		public function toObject($contentType = null) {
			if ($contentType === null) {
				if (($contentType = $this->_parseOptionsHeader('Content-Type')) === false) {
					return false;
				}
				
				$contentType = $contentType[0];
			}
			
			$response = false;
			
			switch ($contentType) {
				case 'application/json':
					// TODO: check for "null" as $response
					if (($response = json_decode($this->_response[2], false)) === null) {
						return false;
					}
					
					break;
				case 'application/xml':
				case 'text/xml':
					libxml_disable_entity_loader(true);
					
					// FIXME: disable warnings?
					if (($response = simplexml_load_string($this->_response[2])) === false) {
						return false;
					}
					
					break;
				default:
					return false;
			}
			
			return $response;
		}
		
		public function isFailed() {
			return $this->_response[0] === self::CODE_FAILED;
		}
		
		public function isNotFound() {
			return $this->_response[0] === self::CODE_NOT_FOUND;
		}
		
		public function convertEncoding($target = 'UTF-8') {
			$contentType = $this->_parseOptionsHeader('Content-Type');
			
			$this->_response[2] = mb_convert_encoding(
				$this->_response[2],
				$target,
				($contentType !== false and isset($contentType[1]['charset']))?
					$contentType[1]['charset'] :
					'ASCII,JIS,UTF-8,EUC-JP,SJIS,ISO-8859-1'
			);
			
			return $this->_response[2];
		}
		
		// TODO: is redirect?
		// TODO: what happens after follow redirect in curl?
		
		protected function _parseOptionsHeader($optionsHeader) {
			if (!isset($this->_response[1][$optionsHeader])) {
				return false;
			}
			
			$header = array('', array());
			$parts = explode(';', $this->_response[1][$optionsHeader], 2);
			
			$header[0] = trim($parts[0]);
			
			if (count($parts) < 2) {
				return $header;
			}
			
			foreach (explode(',', $parts[1]) as $option) {
				$option = explode('=', trim($option), 2);
				
				if (count($option) != 2) {
					return false;
				}
				
				if ($option[1][0] === substr($option[1], -1)) {
					$option[1] = substr($option[1], 1, -1);
				}
				
				$header[1][$option[0]] = $option[1];
			}
			
			return $header;
		}
		
	}
