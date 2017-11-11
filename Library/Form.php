<?php
	
	/*
	 * Form
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Request',
		'Random',
		'Form/Fields'
	);
	
	class Form {
		
		protected $_wasSubmitted;
		protected $_lastRequest;
		protected $_values;
		
		protected $_action;
		protected $_method;
		protected $_suffix;
		
		protected $_entries;
		
		protected $Request;
		
		protected static $_fieldsByToken;
		
		const REQUEST_METHOD_FORM = '?';
		
		const TOKEN_FIELD_NAME = 'tok';
		const TOKEN_SESSION_KEY = '__tok';
		
		const FIELD_TEXT = 1;
		const FIELD_PASSWORD = 3;
		
		const FIELD_BOOL = 2;
		
		const FIELD_BASE64 = 10;
		
		public function __construct(Request $request, $suffix = null) {
			$this->Request = $request;
			$this->_suffix = $suffix;
		}
		
		/*
			Check for a post request and a valid token. Also populate
			$_lastRequest with valid fields
		*/
		public function wasSubmitted() {
			if ($this->_wasSubmitted !== null) {
				return $this->_wasSubmitted;
			}
			
			$method = ($this->_method !== null)? $this->_method : Request::METHOD_POST;
			
			if ($method !== $this->Request->getMethod()) {
				$this->_wasSubmitted = false;
				return $this->_wasSubmitted;
			}
			
			switch ($method) {
				case Request::METHOD_POST:
					$values = $_POST;
					break;
				case Request::METHOD_GET:
					$values = $_GET;
					break;
			}
			
			if (!isset($values[static::TOKEN_FIELD_NAME])) {
				$this->_wasSubmitted = false;
				return $this->_wasSubmitted;
			}
			
			$this->_lastRequest = self::_getFields(
				$values[static::TOKEN_FIELD_NAME],
				($this->_action !== null)?
					$this->_action :
					$this->Request->getPath(),
				$this->_suffix
			);
			
			$this->_wasSubmitted = ($this->_lastRequest !== false);
			
			return $this->_wasSubmitted;
		}
		
		public function getValue($field) {
			if ($this->_values === null) {
				$this->getValues();
			}
			
			if (!isset($this->_values[$field])) {
				return null;
			}
			
			return $this->_values[$field];
		}
		
		public function getValues() {
			if (!$this->wasSubmitted()) {
				return array();
			}
			
			if ($this->_values !== null) {
				return $this->_values;
			}
			
			$this->_values = array();
			
			foreach ($this->_lastRequest as $field => $type) {
				$path = explode('[', str_replace(']', '', $field));
				self::_copyValues(
					$path,
					$type,
					$this->_values,
					($this->Request->isPOSTRequest())? $_POST : $_GET
				);
			}
			
			return $this->_values;
		}
		
		private static function _copyValues($path, $type, &$target, $value) {
			$maxDepth = count($path) - 1;
			
			foreach ($path as $index => $part) {
				if ($part === '') {
					if (!is_array($value)) {
						return;
					}
					
					$path = array_slice($path, $index + 1);
					array_unshift($path, null);
					
					foreach (array_keys($value) as $key) {
						if (!is_int($key)) {
							continue;
						}
						
						$path[0] = $key;
						
						self::_copyValues($path, $type, $target, $value);
					}
					
					return;
				}
				
				if (!isset($value[$part])) {
					if ($index === $maxDepth) {
						if ($type === self::FIELD_BOOL) {
							$target[$part] = false;
							return;
						}/* else {
							// TODO: do we really want this?
							$target[$part] = null;
						}*/
					}
					
					return;
				}
				
				if ($index < $maxDepth) {
					if (!isset($target[$part])) {
						$target[$part] = array();
					}
				
					$target = &$target[$part];
				}
				
				$value = &$value[$part];
			}
			
			if (is_array($value)) {
				return;
			}
			
			switch ($type) {
				case self::FIELD_PASSWORD:
					if (empty($value)) {
						return;
					}
					
					break;
				case self::FIELD_BOOL:
					$target[$part] = true;
					return;
			}
			
			if (is_array($type) and !in_array($value, $type, true)) {
				return;
			}
			
			if ($type === self::FIELD_BASE64 ) {
				$value = base64_decode($value);
			}
			
			// TODO: add configuration for encoding? read form property?
			if (!mb_check_encoding($value, 'UTF-8')) {
				$value = '';
			}
			
			$target[$part] = $value;
		}
		
		public function setAction($action, $method = Request::METHOD_POST) {
			$this->_action = $action;
			$this->_method = $method;
		}
		
		public function forEntries($entries) {
			if ($entries instanceof Model) {
				$this->_entries = $entries;
				return;
			}
			
			$this->_entries = [];
			
			foreach ($entries as $key => $entry) {
				if (!$entry instanceof Model) {
					continue;
				}
				
				if (!is_int($key)) {
					$this->_entries[$key] = $entry;
					continue;
				}
				
				$this->_entries[get_class($entry)] = $entry;
			}
		}
		
		public function save(array $entry = [], ...$arguments) {
			if (!$this->wasSubmitted()) {
				return false;
			}
			
			if ($this->_entries === null) {
				return false;
			}
			
			if ($this->_entries instanceof Model) {
				return $this->_entries->save(
					array_merge($this->getValues(), $entry),
					...$arguments
				);
			}
			
			// FIXME
		}
		
		public function saveOrThrow(array $entry = [], ...$arguments) {
			if (!$this->wasSubmitted()) {
				return false;
			}
			
			if ($this->_entries === null) {
				return false;
			}
			
			if ($this->_entries instanceof Model) {
				return $this->_entries->saveOrThrow(
					array_merge($this->getValues(), $entry),
					...$arguments
				);
			}
			
			// FIXME
		}
		
		/*
		public function save($conditions = null, array $params = array()) {
			if ($this->_entry === null) {
				return false;
			}
			
			return $this->_entry->save($this->getValues(), $conditions, $params);
		}
		*/
		
		public function __invoke(array $attributes = array(), $useToken = true) {
			if ($this->_action !== null) {
				$attributes['action'] = $this->_action;
			}
			
			if ($this->_method !== null) {
				$attributes['method'] = $this->_method;
			}
			
			$token = null;
			
			if ($useToken) {
				$token = self::getTokenForAction(
					($this->_action !== null)?
						$this->_action :
						$this->Request->getPath(),
					$this->_suffix
				);
			}
			
			if ($this->_entries === null) {
				return new Form_Fields(
					$this->Request,
					$attributes,
					$token
				);
			}
			
			if ($this->_entries instanceof Model) {
				return new Form_Fields_For(
					$this->_entries,
					$this->Request,
					$attributes,
					$token
				);
			}
			
			$fields = [];
			
			foreach ($this->_entries as $key => $entry) {
				$fields[$key] = new Form_Fields_For(
					$entry,
					$this->Request,
					$attributes,
					$token
				);
			}
			
			return $fields;
		}
		
		public static function getTokenForAction($action, $suffix = null) {
			if (isset($_SESSION[static::TOKEN_SESSION_KEY])) {
				$_SESSION[static::TOKEN_SESSION_KEY] = array_slice($_SESSION[static::TOKEN_SESSION_KEY], -20);
			}
			
			$token = Random::friendly(48);
			$key = $token . '/' . $action .
				(($suffix !== null)? ('/' . $suffix) : '');
			
			$_SESSION[static::TOKEN_SESSION_KEY][$key] = array();
			self::$_fieldsByToken[$token] = &$_SESSION[static::TOKEN_SESSION_KEY][$key];
			
			return $token;
		}
		
		public static function registerField($token, $field, array $values = null, $type = self::FIELD_TEXT) {
			if (!isset(self::$_fieldsByToken[$token])) {
				return;
			}
			
			if ($values === null) {
				self::$_fieldsByToken[$token][$field] = $type;
				return;
			}
			
			if (
				isset(self::$_fieldsByToken[$token][$field]) and
				is_array(self::$_fieldsByToken[$token][$field]) and
				$values !== null
			) {
				self::$_fieldsByToken[$token][$field] = array_merge(
					self::$_fieldsByToken[$token][$field],
					$values
				);
				return;
			}
			
			self::$_fieldsByToken[$token][$field] = $values;
		}
		
		protected static function _getFields($token, $action, $suffix = null) {
			$key = $token . '/' . $action .
				(($suffix !== null)? ('/' . $suffix) : '');
			
			if (!isset($_SESSION[static::TOKEN_SESSION_KEY][$key])) {
				return false;
			}

			$fields = $_SESSION[static::TOKEN_SESSION_KEY][$key];
			
			unset($_SESSION[static::TOKEN_SESSION_KEY][$key]);
			
			return $fields;
		}
		
	}
