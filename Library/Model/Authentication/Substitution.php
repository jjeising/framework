<?php
	
	/*
	 * Model Authentication Substitution
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	trait Model_Authentication_Substitution {
		
		public function substitute() {
			if (!static::isLoggedIn()) {
				return false;
			}
			
			$current = static::getCurrent()->getPrimaryKeyFields();
			
			// do not allow substitution as the current user
			if ($current === $this->getPrimaryKeyFields()) {
				return false;
			}
			
			$this['__substituted_user'] = $current;
			
			$this->updateSession();
			
			return true;
		}
		
		public function changeback() {
			if (!isset($this->_entry['__substituted_user'])) {
				return false;
			}
			
			if (!$substituted = static::findBy($this['__substituted_user'])) {
				// original user got lost in translation, we are locked in
				unset($this['__substituted_user']);
				$this->updateSession();
				
				return false;
			}
			
			$substituted->updateSession();
			
			return true;
		}
		
		public static function isSubstitute() {
			// access $_entry, otherwise another query will be issued because user entries are in most cases stale
			return isset(static::getCurrent()->_entry['__substituted_user']);
		}
		
	}
