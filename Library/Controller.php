<?php
	
	/*
	 * Controller
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'MimeType',
		'Request',
		'Router',
		'Response'
	);
	
	class Controller {
		
		protected $requireAuthorization;
		
		protected $beforeAction;
		
		protected $catch = [];
		
		protected $Request;
		protected $Response;
		
		protected $arguments = [];
		
		private $_flash = [];
		private $_data = [];
		
		private $_formId = 1;
		
		const FLASH_NOTICE = 'notice';
		const FLASH_WARNING = 'warning';
		const FLASH_ERROR = 'error';
		
		const FLASH_SESSION_KEY = '__f';
		
		public function __isset($key) {
			return array_key_exists($key, $this->_data);
		}
		
		public function &__get($key) {
			return $this->_data[$key];
		}
		
		public function __set($key, $value) {
			$this->_data[$key] = $value;
		}
		
		public function __unset($key) {
			unset($this->_data[$key]);
		}
		
		protected function flash($message, $type = null, $payload = null) {
			if ($type === null) {
				$type = static::FLASH_NOTICE;
			}
			
			$this->_flash[] = [
				'message' => $message,
				'type' => $type,
				'payload' => $payload
			];
		}
		
		protected function flashNow($message, $type = null, $payload = null) {
			if ($type === null) {
				$type = static::FLASH_NOTICE;
			}
			
			if (!isset($this->_data['flash'])) {
				$this->_data['flash'] = [];
			}
			
			$this->_data['flash'][] = [
				'message' => $message,
				'type' => $type,
				'payload' => $payload
			];
		}
		
		protected function keepFlash() {
			if (!isset($this->_data['flash'])) {
				return;
			}
			
			$this->_flash = array_merge($this->_data['flash'], $this->_flash);
		}
		
		protected function redirect($controller = '', $action = null, ...$args) {
			$location = '';
			
			if ($action === null) {
				if ($controller === '') {
					$location = $this->Request->getRootURL();
				} else {
					$location = $controller;
				}
			} else {
				$arguments = [];
				
				foreach ($args as $argument) {
					if ($argument instanceOf Model) {
						$arguments += $argument->toArray();
					} elseif (is_array($argument)) {
						$arguments += $argument;
					}
				}
				
				$location = $this->Request->getRootURL() .
					Router::reverse($controller, $action, $arguments);
			}
			
			$this->Response->addHeader('Location', $location);
			
			if ($this->Response->getCode() === HTTP_Response::CODE_OK) {
				$this->Response->setCode(HTTP_Response::CODE_FOUND);
			}
			
			return $this->Response;
		}
		
		/*
			TODO: allow array for template, [respondTo => template]
			
			rendering type as extension?
			['tickets/feed.html', 'api/feed.json'] ?
			
			force via extension
			'tickets/feed.html', do not try to guess format in this case and
			when an array is given
		*/
		protected function render($template, array $options = array(), array $data = array()) {
			$format = null;
			
			/*
			if (is_array($template)) {
				foreach ($template as $format => $file) {
					if (!$this->_respondToFormat($format)) {
						continue;
					}
					
					$template = $file;
					$this->_setResponseFormat($format);
					break;
				}
			}
			*/
			
			$data = array_merge($this->_data, $data);
			
			if (!isset($data['arguments'])) {
				$data['arguments'] = $this->arguments;
			}
			
			if (isset($this->arguments['format']) and
				isset($options['format']) and
				in_array($this->arguments['format'], $options['format'])) {
				$this->_setResponseFormat($this->arguments['format']);
				$format = $this->arguments['format'];
			}
			
			// TODO: clone before set? Then we have to call set on $response
			$response = clone $this->Response;
			
			if (isset($options['responseCode'])) {
				$response->setCode($options['responseCode']);
			}
			
			$view = new View(
				$template . (($format !== null)?
					('.' . $format) : ''),
				$data,
				$this->Request
			);
			
			$response->setContent($view->render());
			
			return $response;
		}
		
		public static final function renderTemplate($template, array $data = array(), Request $request = null, Response $response = null) {
			if ($request === null) {
				$request = new Request();
			}
			
			$class = new Controller();
			$class->Request = $request;
			
			if ($response !== null) {
				$class->Response = $response;
			} else {
				$class->Response = new Response();
			}
			
			return $class->render($template, [], $data);
		}
		
		protected function respondTo($format) {
			if (!$this->_respondToFormat($format)) {
				return false;
			}
			
			$this->_setResponseFormat($format);
			
			return true;
		}
		
		protected function _respondToFormat($format) {
			if (
				isset($this->arguments['format']) and
				$this->arguments['format'] === $format
			) {
				return true;
			}
			
			if (!$this->Request->acceptsAll() and
				$this->Request->accepts(MimeType::getByExtension($format))) {
				return true;
			}
			
			// TODO: move to default reponse format
			if ($format === 'text/html') {
				return true;
			}
			
			return false;
		}
		
		protected function _setResponseFormat($format) {
			$this->Response->setContentType(MimeType::getByExtension($format));
		}
		
		// TODO: isStale(), see http://api.rubyonrails.org/classes/ActionController/ConditionalGet.html
		
		/*
			form([$controller[, $action[, $arguments[, $arguments[, …]]][, $method]]]);
		*/
		protected function form($controller = null, $action = null, ...$args) {
			$method = Request::METHOD_POST;
			$formId = null;
			
			if ($controller === null and $action === null) {
				$formId = $this->_formId;
				$this->_formId++;
			}
			
			if ($controller === null and isset($this->arguments['controller'])) {
				$controller = $this->arguments['controller'];
			}
			
			if ($action === null and isset($this->arguments['action'])) {
				$action = $this->arguments['action'];
			}
			
			$arguments = [];
			$argumentSupplied = false;
			
			foreach ($args as $argument) {
				if ($argument === null) {
					continue;
				}
				
				if ($argument instanceOf Model) {
					$arguments += $argument->toArray();
					$argumentSupplied = true;
				} elseif (is_array($argument)) {
					$arguments += $argument;
					$argumentSupplied = true;
				} else {
					$method = $argument;
					break;
				}
			}
			
			if (!$argumentSupplied) {
				$arguments = $this->arguments;
			}
			
			$form = new Form(
				$this->Request,
				($formId !== null and $formId > 1)? $formId : null
			);
			$formAction = null;
			
			if ($controller !== null and $action !== null) {
				$formAction = Router::reverse(
					$controller,
					$action,
					$arguments
				);
			}
			
			if ($formAction !== null) {
				$form->setAction(
					$this->Request->getRootURL() . $formAction,
					$method
				);
			}
			
			if (!isset($this->_data['form'])) {
				$this->_data['form'] = $form;
			}
			
			return $form;
		}
		
		// TODO: support multiple models (e.g. $project, $task), see nested resources
		protected function formFor($entries, $controller = null, $action = null, ...$arguments) {
			$form = $this->form($controller, $action, ...$arguments);
			$form->forEntries($entries);
			return $form;
		}
		
		public static final function run(Request $request = null, $controller = null, $action = null, array $arguments = array()) {
			if ($request === null) {
				$request = new Request();
			}
			
			if ($controller === null) {
				if (!$mapping = Router::map($request)) {
					// TODO: better name?
					// TODO: this is a problem, we're missing an ApplicationController here
				
					/*
						TODO:
						try to init Controller_Application here to handle
						this exception,
						add argument $defaultController
					*/
					throw new RoutingException('No route matched.');
				}
				
				$controller = $mapping['controller'];
				$action = $mapping['action'];
				$arguments = $mapping['arguments'];
			}
			
			$class = self::_fabricate($controller, $action, $arguments);
			
			$response = $class->_run($controller, $action, $arguments, $request);
			
			// TODO: check how to enable middleware, maybe return response? there should be no reason to store flash before response
			if ($response instanceOf Response) {
				echo $response;
			} elseif (is_array($response)) {
				echo Response::fromArray($response);
			} else {
				echo $class->Response;
			}
			
			return $class->arguments;
		}
		
		protected function _run($controller, $action, array $arguments, Request $request) {
			$methodName = '';
			
			$this->Request = $request;
			$this->Response = new Response();
			
			$this->arguments = $arguments;
			
			if (
				!is_callable(array($this, $action), false, $methodName) and
				!($this instanceOf ControllerSupportsDynamicCallInterface)
			) {
				return $this->_catch(new ActionNotFoundException(
					'Action ' . $methodName . ' not found.'
				));
			}
			
			if (!$this->isAuthorized($controller, $action)) {
				return $this->_catch(new ActionNotAllowedException());
			}
			
			$this->_importFlashFromSession();
			
			$beforeAction = $this->executeBeforeAction($action, $this->arguments);
			
			if ($beforeAction instanceOf Response) {
				$this->_exportFlashToSession();
				
				return $beforeAction;
			}
			
			if ($beforeAction === false) {
				return $this->_catch(new ActionNotAllowedException(
					'Before action canceled action call.'
				));
			}
			
			try {
				$response = $this->{$action}($this->arguments);
			} catch (Exception $exception) {
				return $this->_catch($exception);
			}
			
			$this->_exportFlashToSession();
			
			return $response;
		}
		
		protected function _catch($exception) {
			$action = null;
			
			foreach ($this->catch as $name => $function) {
				$class = $name . 'Exception';
				
				if ($exception instanceOf $class) {
					$action = $function;
					break;
				}
			}
			
			if ($action === null) {
				throw $exception;
			}
			
			$response = $this->{$action}($exception);
			$this->arguments['action'] = $action;
			
			$this->_exportFlashToSession();
			
			return $response;
		}
		
		protected final function isAuthorized($controller, $action) {
			if ((!isset($this->requireAuthorization['action']) or $this->requireAuthorization[$action] !== true) and $this->requireAuthorization !== true) {
				return true;
			}
			
			return User::isAllowed($controller, $action);
		}
		
		protected function executeBeforeAction($action, &$arguments) {
			if (empty($this->beforeAction)) {
				return true;
			}
			
			foreach ($this->beforeAction as $method => $actions) {
				if ($actions !== true and (!isset($actions[$action]) or $actions[$action] !== true)) {
					continue;
				}
				
				try {
					$result = $this->{$method}($action, $arguments);
				} catch (Exception $exception) {
					$result = $this->_catch($exception);
				}
				
				if ($result instanceOf Response) {
					return $result;
				}
				
				if ($result === false) {
					return false;
				}
			}
			
			return true;
		}
		
		private function _importFlashFromSession() {
			requiresSession();
			
			if (!isset($_SESSION[self::FLASH_SESSION_KEY])) {
				return;
			}
			
			$this->_data['flash'] = $_SESSION[self::FLASH_SESSION_KEY];
			unset($_SESSION[self::FLASH_SESSION_KEY]);
		}
		
		private function _exportFlashToSession() {
			requiresSession();
			
			if (empty($this->_flash)) {
				return;
			}
			
			$_SESSION[self::FLASH_SESSION_KEY] = $this->_flash;
			$this->_flash = [];
		}
		
		private static function _fabricate($controller, $action, array $arguments) {
			$class = 'Controller_' . ucfirst($controller);
			
			requires('/' . str_replace('_', DIRECTORY_SEPARATOR, $class));
			
			if (!class_exists($class, false)) {
				throw new ControllerNotFoundException();
			}
			
			return new $class($action, $arguments);
		}
	}
	
	interface ControllerSupportsDynamicCallInterface {
		public function __call($name, array $arguments);
	}
	
	class NotFoundException extends RuntimeException {
		protected $code = 404;
	}
	
	class RoutingException extends NotFoundException { }
	
	class ControllerNotFoundException extends NotFoundException { }
	
	class ActionNotFoundException extends NotFoundException { }
	
	class EntryNotFoundException extends NotFoundException { }
	
	class ActionNotAllowedException extends RuntimeException {
		protected $code = 403;
	}
