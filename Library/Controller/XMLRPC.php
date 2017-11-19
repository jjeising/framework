<?php
	
	/*
	 * Controller XMLRPC
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Controller_XMLRPC extends Controller {
		
		const XMLRPC_PREFIX = '';
		
		protected function _run($controller, $action, array $parentArguments, Request $request) {
			// TODO: ob_start()?
			
			$this->Request = $request;
			
			$this->Response = new Response();
			$this->Response->setContentType('text/xml');
			
			$xml = file_get_contents('php://input');
			
			if (empty($xml)) {
				return $this->_XMLRPCFault(-32700, 'empty request');
			}
			
			$method = null;
			if (!function_exists('xmlrpc_encode_request')) {
				throw new \Exception("The php-xmlrpc extension is not installed. Please install this to use this functionality.");
			}
			$this->arguments = xmlrpc_decode_request($xml, $method, 'utf-8');
			
			if ($this->arguments === null) {
				return $this->_XMLRPCFault(-32700, 'cannot parse request');
			}
			
			foreach ($this->arguments as $index => $argument) {
				switch (xmlrpc_get_type($argument)) {
					case 'base64':
					case 'datetime':
						$this->arguments[$index] = $argument->scalar;
						break;
					default:
						break;
				}
			}
			
			if (static::XMLRPC_PREFIX !== '') {
				if (strpos($method, static::XMLRPC_PREFIX) !== 0) {
					return $this->_XMLRPCFault(-32601, 'request method not found, invalid prefix');
				}
				
				$method = substr($method, strlen(static::XMLRPC_PREFIX));
			}
			
			$parentArguments['action'] = $method;
			
			if (
				!is_callable(array($this, $method)) and
				!($this instanceof ControllerSupportsDynamicCallInterface)
			) {
				return $this->_XMLRPCFault(-32601, 'request method not found');
			}
			
			try {
				$this->isAuthorized($controller, $method);
			} catch (ActionNotAllowedException $exception) {
				return $this->_XMLRPCFault(-32400, 'system error: unauthorized');
			}
			
			// TODO: check signature via reflection?
			// TODO: setup own error handler for traditional errors
			
			$beforeAction = $this->executeBeforeAction($method, $this->arguments);

			if ($beforeAction instanceOf Response) {
				// try to fix error caused by PHP N in index.php:52 on error
				$this->arguments = array_merge($parentArguments, $this->arguments);

				return $beforeAction;
			}
			
			if ($beforeAction === false) {
				return $this->_XMLRPCFault(-32500, 'application error: unauthorized');
			}
			
			try {
				$response = $this->{$method}(...$this->arguments);
			} catch (Exception $e) {
				// try to fix error caused by PHP N in index.php:52 on error
				$this->arguments = array_merge($parentArguments, $this->arguments);
				
				$faultCode = ($e->getCode() > 0) ? $e->getCode() : -32600;
				
				return $this->_XMLRPCFault($faultCode, $e->getMessage());
			}

			if ($response instanceOf Response) {
				return $response;
			}
			
			$this->Response->setContent(xmlrpc_encode_request(
				null,
				$response,
				array(
					'encoding' => $this->Response->getCharset(),
					'escaping' => 'markup'
				)
			));
			
			$this->arguments = array_merge($parentArguments, $this->arguments);

			return $this->Response;
		}
		
		protected function _XMLRPCFault($code, $string) {
			$this->Response->setContent(xmlrpc_encode_request(null, array(
				'faultCode' => $code,
				'faultString' => $string
			), array(
				'encoding' => $this->Response->getCharset(),
				'escaping' => 'markup'
			)));
			
			return $this->Response;
		}
		
	}
