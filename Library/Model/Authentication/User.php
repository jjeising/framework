<?php
	
	/*
	 * Model Authentication User
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Model',
		'Model/Authentication/Session/Default',
		'Random'
	);
	
	class Model_Authentication_User extends Model {
		
		protected static $Session;
		
		private static $_sessionPrimaryKeyCache;
		private static $_sessionRoleCache;
		
		const FIELD_ACTIVE = 'active';
		const FIELD_USER = 'mail';
		
		const FIELD_PASSWORD = 'password';
		
		const FIELD_ACL_TOKEN = 'acl_token';
		const FIELD_PERSISTENCE_TOKEN = 'persistence_token';
		const FIELD_REMEMBER_TOKEN = 'remember_token';
		
		const FIELD_LOGIN_COUNT = 'login_count';
		const FIELD_LOGIN_COUNT_FAILED = 'failed_login_count';
		
		const FIELD_LAST_REQUEST = 'last_request';
		const FIELD_LAST_LOGIN = 'last_login';
		
		const TOKEN_LENGTH = 32;
		
		const REMEMBER_COOKIE_NAME = 'login';
		const REMEMBER_COOKIE_LIFETIME = 1209600;
		
		const FAILED_LOGIN_LIMIT = 20;
		
		public function __sleep() {
			unset($this->_entry[static::FIELD_PASSWORD]);
			$this->_stale = true;
			
			return parent::__sleep();
		}
		
		public static function recall() {
			self::$Session = static::_recallSession();
			
			if (!static::isLoggedIn()) {
				if (
					static::REMEMBER_COOKIE_NAME === false or
					!isset($_COOKIE[static::REMEMBER_COOKIE_NAME])
				) {
					return false;
				}
				
				if (!$user = static::findBy([
					static::FIELD_REMEMBER_TOKEN => $_COOKIE[static::REMEMBER_COOKIE_NAME]
				])) {
					return false;
				}
				
				// TODO: check lifetime with last request, or better remember_token_access?
				/*
					$this->lastRequestField . ' > ?'), array(date(Date::MYSQL, time() - $this->rememberCookieLifetime))
				*/
				
				self::$Session = static::_createSession();
				$user->updateSession();
			} else {
				$user = self::$Session->get();
			}
			
			
			if (static::FIELD_LAST_REQUEST !== false) {
				// TODO: fix notation (array(array()))
				$query = Database_Query::updateTable(
					static::TABLE,
					[[static::FIELD_LAST_REQUEST, 'NOW()']]
				);
			}
			
			if (static::FIELD_PERSISTENCE_TOKEN !== false) {
				// create new query when last request field is missing
				if (!isset($query)) {
					$query = Database_Query::selectFrom(
						static::TABLE,
						Database_Query::SELECT_ONE_AS_ONE
					);
				}
				
				$query->where([
					self::FIELD_PERSISTENCE_TOKEN => $user[static::FIELD_PERSISTENCE_TOKEN]
				]);
				
				if ($query->execute() <= 0) {
					static::logout();
					return false;
				}
			} elseif (isset($query)) {
				// update last request only without persistence token
				$query
					->where($user->getPrimaryKeyFields())
					->execute();
			}
			
			// TODO: update remembertoken only after specific time?
			// TODO: update only if currently available! (do not transfer between browsers)
			/*
			if ($this->isRememberCookieValid()) {
				$this->setRememberCookie($this->UserSession->get($this->rememberTokenField));
			}
			*/
			
			return $user;
		}
		
		// TODO: validation messages?
		public static function login($user, $password, $remember = false) {
			if (static::isLoggedIn()) {
				return true;
			}
			
			if (($user = static::findBy([static::FIELD_USER => $user])) === null) {
				return false;
			}
			
			if (static::FIELD_ACTIVE !== false and !$user->_entry[static::FIELD_ACTIVE]) {
				return false;
			}
			
			if (
				static::FIELD_LOGIN_COUNT_FAILED !== false and
				static::FAILED_LOGIN_LIMIT !== false and
				$user->_entry[static::FIELD_LOGIN_COUNT_FAILED] >= static::FAILED_LOGIN_LIMIT
			) {
				return false;
			}
			
			if (!$user->verifyPassword($password)) {
				if (static::FIELD_LOGIN_COUNT_FAILED !== false) {
					$user[static::FIELD_LOGIN_COUNT_FAILED] = $user[static::FIELD_LOGIN_COUNT_FAILED] + 1;
					$user->save();
				}
				
				return false;
			}
			
			if ($user->shouldRehashPassword()) {
				$user[static::FIELD_PASSWORD] = static::_hashPassword($password);
			}
			
			if (
				static::FIELD_PERSISTENCE_TOKEN !== false and
				empty($user->_entry[static::FIELD_PERSISTENCE_TOKEN])
			) {
				$user->resetPersistenceToken();
			}
			
			if (static::FIELD_LOGIN_COUNT !== false) {
				if (!empty($user[static::FIELD_LOGIN_COUNT])) {
					$user[static::FIELD_LOGIN_COUNT] = $user[static::FIELD_LOGIN_COUNT] + 1;
				} else {
					$user[static::FIELD_LOGIN_COUNT] = 1;
				}
			}
			
			// TODO: use NOW() ?
			$now = new DateTime();
			
			if (static::FIELD_LOGIN_COUNT_FAILED !== false) {
				$user[static::FIELD_LOGIN_COUNT_FAILED] = 0;
			}
			
			if (static::FIELD_LAST_LOGIN !== false) {
				$user[static::FIELD_LAST_LOGIN] = $now;
			}
			
			if (static::FIELD_LAST_REQUEST !== false) {
				$user[static::FIELD_LAST_REQUEST] = $now;
			}
			
			
			$user->setRememberToken($remember);
			
			$user->save();
			
			self::$Session = static::_createSession();
			$user->updateSession();
			
			if ($remember) {
				$user->setRememberCookie();
			}
			
			return true;
		}
		
		public static function logout() {
			if (!static::isLoggedIn()) {
				return false;
			}
			
			$user = self::$Session->get();
			$user->setRememberToken(false);
			$user->save();
			
			$user->unsetRememberCookie();
			
			self::$Session->destroy();
			self::$Session = null;
			
			return true;
		}
		
		public static function isLoggedIn() {
			return self::$Session instanceof Model_Authentication_Session;
		}
		
		// TODO: remodel: use check for role 'owner' and set result as default for isAllowed (allow if owner is allowed and user is not explicitly forbidden)
		// TODO: change to __owner and use Model_Authentication_User::ACCESS_CONTROL_OWNER or similar
		public static function isAllowed($object = null, $priviledge = null, $item = null, $owner = false) {
			$role = static::getCurrentRole();
			
			if (!is_bool($owner)) {
				if (static::isLoggedIn()) {
					if (!is_array($owner)) {
						$owner = array_combine(
							self::$Session->get()->primaryKey,
							[$owner]
						);
					}
					
					$owner = (
						self::$Session->get()->getPrimaryKeyFields() === $owner
					);
				} else {
					$owner = false;
				}
			}
			
			if ($owner) {
				AccessControl::addRoleParents($role, ['owner']);
			}
			
			$isAllowed = AccessControl::isAllowed($role, $object, $priviledge, $item);
			
			if ($owner) {
				AccessControl::removeRoleParents($role, ['owner']);
			}
			
			return $isAllowed;
		}
		
		// TODO: add $key = null
		public static function getCurrent() {
			if (!static::isLoggedIn()) {
				return null;
			}
			
			return self::$Session->get();
		}
		
		public static function getCurrentRole() {
			if (!static::isLoggedIn()) {
				return null;
			}
			
			if (self::$_sessionRoleCache !== null) {
				return self::$_sessionRoleCache;
			}
			
			$current = self::$Session->get();
			
			if (static::FIELD_ACL_TOKEN !== false) {
				$role = $current[static::FIELD_ACL_TOKEN];
			} else {
				$role = $current[static::FIELD_USER];
			}
			
			self::$_sessionRoleCache = $role;
			
			return $role;
		}
		
		public function isCurrent() {
			return static::isLoggedIn() and
				self::$Session->get()->getPrimaryKeyFields(true) === $this->getPrimaryKeyFields(true);
		}
		
		public function verifyPassword($password) {
			return password_verify($password, $this[static::FIELD_PASSWORD]);
		}
		
		public function shouldRehashPassword() {
			return password_needs_rehash($this[static::FIELD_PASSWORD], PASSWORD_DEFAULT);
		}
		
		protected static function _hashPassword($password) {
			return password_hash($password, PASSWORD_DEFAULT);
		}
		
		public function resetPersistenceToken() {
			$this[static::FIELD_PERSISTENCE_TOKEN] = Random::friendly(static::TOKEN_LENGTH);
		}
		
		// TODO: currently we only allow one remembered browser
		public function setRememberToken($remember) {
			if ($remember) {
				$this[static::FIELD_REMEMBER_TOKEN] = Random::friendly(static::TOKEN_LENGTH);
			} else {
				$this[static::FIELD_REMEMBER_TOKEN] = null;
			}
		}
		
		public function setRememberCookie() {
			// FIXME: we need global cookie params or static methods for Response
			setcookie(
				static::REMEMBER_COOKIE_NAME,
				$this[static::FIELD_REMEMBER_TOKEN],
				time() + static::REMEMBER_COOKIE_LIFETIME,
				'/'/*,
				null,
				false,
				true*/
			);
			
			return true;
		}
		
		public function unsetRememberCookie() {
			setcookie(static::REMEMBER_COOKIE_NAME, '', time() - 3600);
		}
		
		public function isRememberCookieValid() {
			return isset($_COOKIE[static::REMEMBER_COOKIE_NAME]) and
				$_COOKIE[static::REMEMBER_COOKIE_NAME] == $this[static::FIELD_REMEMBER_TOKEN];
		}
		
		protected function beforeSave(&$values, array $fields) {
			
		}
		
		public function saveOrThrow(array $entry = array(), $conditions = null, array $params = array()) {
			if (!empty($entry)) {
				$this->set($entry);
			}
			
			// TODO: after validation
			if ($this->changed(static::FIELD_PASSWORD)) {
				$this[static::FIELD_PASSWORD] = static::_hashPassword($this[static::FIELD_PASSWORD]);
				$this->resetPersistenceToken();
				
				if ($this->isRememberCookieValid()) {
					$this->setRememberToken(true);
					$this->setRememberCookie();
				} else {
					$this->setRememberToken(false);
				}
			}
			
			if (!parent::saveOrThrow(array(), $conditions, $params)) {
				return false;
			}
			
			if ($this->isCurrent()) {
				$this->updateSession();
			}
			
			return true;
		}
		
		public function updateSession() {
			self::$Session->update(clone $this);
		}
		
		protected static function _createSession() {
			return new Model_Authentication_Session_Default();
		}
		
		protected static function _recallSession() {
			return Model_Authentication_Session_Default::recall();
		}
		
	}
	
	interface Model_Authentication_Session {
		
		const SESSION_UNSAFE_KEY = 'unsafe';
		
		public function get();
		public function update(Model_Authentication_User $user);
		public function destroy();
		
		public static function recall();
		
	}
