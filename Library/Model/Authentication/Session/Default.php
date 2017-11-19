<?php
	
	/*
	 * Model Authentication Session Default
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Model_Authentication_Session_Default implements Model_Authentication_Session {
		
		const SESSION_KEY = '__u';
		
		protected $_user;
		
		public function __construct($regenerate = true) {
			requiresSession();
			
			if ($regenerate) {
				static::_regenerate();
			}
		}
		
		public function __destruct() {
			if ($this->_user === null) {
				if (isset($_SESSION[static::SESSION_KEY])) {
					$this->destroy();
				}
				
				return;
			}
			
			$_SESSION[static::SESSION_KEY] = $this->_user;
		}
		
		public function get() {
			return $this->_user;
		}
		
		public function update(Model_Authentication_User $user) {
			$this->_user = $user;
		}
		
		public function destroy() {
			$this->_user = null;
			unset($_SESSION[static::SESSION_KEY]);
			
			static::_regenerate();
		}
		
		public static function recall() {
			requiresSession();
			
			if (!isset($_SESSION[static::SESSION_KEY])) {
				return false;
			}
			
			$session = new static(false);
			$session->update($_SESSION[static::SESSION_KEY]);
			
			return $session;
		}
		
		protected static function _regenerate() {
			$unsafe = null;
			
			if (isset($_SESSION[static::SESSION_UNSAFE_KEY])) {
				$unsafe = $_SESSION[static::SESSION_UNSAFE_KEY];
			}
			
			// regenerate id and delete old session file
			session_regenerate_id(true);
			
			// prune old data
			$_SESSION = [];
			
			if ($unsafe !== null) {
				$_SESSION[static::SESSION_UNSAFE_KEY] = $unsafe;
			}
		}
		
	}
