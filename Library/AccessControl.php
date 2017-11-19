<?php
	
	/*
	 * Access Control List
	 *
	 * Provides a role based rights management.
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class AccessControl {
		
		protected static $_roles = [];
		protected static $_rules = [];
		
		protected static $_roleRuleCache = null;
		
		public static function getRoles() {
			return array_keys(self::$_roles);
		}
		
		public static function hasRole($role) {
			return isset(self::$_roles[$role]);
		}
		
		public static function hasChildren($role) {
			return isset(self::$_roles[$role]['children']);
		}
		
		public static function isChild($role, $parent) {
			return isset(self::$_roles[$parent]['children'][$role]);
		}
		
		public static function isParent($role, $child) {
			return isset(self::$_roles[$child]['parents'][$role]);
		}
		
		public static function addRole($role, array $parents = null) {	
			self::$_roles[$role] = [
				'parents' => [],
				'children' => []
			];
			
			if ($parents !== null) {
				static::addRoleParents($role, $parents);
			}
		}
		
		public static function addRoleParents($role, array $parents) {
			if (!isset(self::$_roles[$role])) {
				return;
			}
			
			foreach ($parents as $parent) {
				if (!isset(self::$_roles[$parent])) {
					continue;
				}
				
				self::$_roles[$parent]['children'][$role] = $role;
				self::$_roles[$role]['parents'][$parent] = $parent;
			}
			
			self::$_roleRuleCache = [];
		}
		
		public static function removeRoleParents($role, array $parents) {
			if (!isset(self::$_roles[$role])) {
				return;
			}
			
			foreach ($parents as $parent) {
				unset(self::$_roles[$parent]['children'][$role]);
				unset(self::$_roles[$role]['parents'][$parent]);
			}
			
			self::$_roleRuleCache = [];
		}
		
		public static function removeRole($role) {
			if (!isset(self::$_roles[$role])) {
				return false;
			}
			
			unset(self::$_rules['byRole'][$role]);
			
			foreach (self::$_roles[$role]['children'] as $child) {
				unset(self::$_roles[$child]['parents'][$role]);
			}
			
			foreach (self::$_roles[$role]['parents'] as $parent) {
				unset(self::$_roles[$parent]['children'][$role]);
			}
			
			unset(self::$_roles[$role]);
			
			self::$_roleRuleCache = [];
			
			return true;
		}
		
		public static function toArray() {	
			return [self::$_roles, self::$_rules];
		}
		
		public static function fromArray(array $array) {
			self::$_roles = $array[0];
			self::$_rules = $array[1];
		}
		
		public static function allow($role, array $objects = null, array $privileges = null, array $items = null) {	
			return self::_setRule($role, $objects, $privileges, $items, true);
		}
		
		public static function deny($role, array $objects = null, array $privileges = null, array $items = null) {
			return self::_setRule($role, $objects, $privileges, $items, false);
		}
		
		public static function addRule($role, array $objects = null, array $privileges = null, array $items = null, $allow = true) {
			return self::_setRule($role, $objects, $privileges, $items, $allow);			
		}
		
		public static function removeRule($role, array $objects = null, array $privileges = null, array $items = null) {
			return self::_setRule($role, $objects, $privileges, $items, false, true);
		}
		
		public static function clearRules() {
			self::$_rules = [];
		}
		
		private static function _setRule($role, array $objects = null, array $privileges = null, array $items = null, $allow = false, $delete = false) {
			if ($role === null) {
				$rule =& self::$_rules['allRoles'];
			} else {
				$rule =& self::$_rules['byRole'][$role];
			}
			
			if ($objects === null) {
				$objects = [null];
			}
			
			if ($privileges === null) {
				$privileges = [null];
			}
			
			if ($items === null) {
				$items = [null];
			}
			
			$ruleObject = null;
			$rulePrivilege = null;
			$ruleItem = null;
			
			foreach ($objects as $object) {
				if ($object === null) {
					$ruleObject =& $rule['allObjects'];
				} else {				
					$ruleObject =& $rule['byObject'][$object];
				}
				
				foreach ($privileges as $privilege) {
					if ($privilege === null) {
						$rulePrivilege =& $ruleObject['allPrivileges'];
					} else {
						$rulePrivilege =& $ruleObject['byPrivilege'][$privilege];
					}
					
					foreach ($items as $item) {
						if ($item === null) {
							$ruleItem =& $rulePrivilege['allItems'];
						} else {
							$ruleItem =& $rulePrivilege['byItem'][$item];
						}
						
						if ($delete) {
							$ruleItem = null;
						} else {
							$ruleItem = (bool) $allow;
						}
					}
				}
			}
			
			self::$_roleRuleCache = [];
			
			return true;
			
		}
		
		// TODO: add default ($isAllowed) as parameter
		public static function isAllowed($role = null, $object = null, $privilege = null, $item = null) {
			$isAllowed = false;
			
			if (!empty(self::$_rules['allRoles'])) {
				$isAllowed = self::_checkObject(self::$_rules['allRoles'], $isAllowed, $object, $privilege, $item);
			}
			
			$rulesByRole = static::_getRulesByRole($role);
			
			if (!empty($rulesByRole)) {
				$isAllowed = self::_checkObject($rulesByRole, $isAllowed, $object, $privilege, $item);
			}
			
			return $isAllowed;
			
		}
		
		private static function _checkObject(&$tree, $isAllowed, $object, $privilege, $item) {
			if (!empty($tree['allObjects'])) {
				$isAllowed =  self::_checkPrivilege($tree['allObjects'], $isAllowed, $privilege, $item);
			}
			
			if (!empty($tree['byObject'][$object])) {
				$isAllowed = self::_checkPrivilege($tree['byObject'][$object], $isAllowed, $privilege, $item);
			}
			
			return $isAllowed;
		}
		
		private static function _checkPrivilege(&$tree, $isAllowed, $privilege, $item) {
			if (isset($tree['allPrivileges'])) {
				$isAllowed = self::_checkItem($tree['allPrivileges'], $isAllowed, $item);
			}
			
			if (isset($tree['byPrivilege'][$privilege])) {
				$isAllowed = self::_checkItem($tree['byPrivilege'][$privilege], $isAllowed, $item);
			}
			
			return $isAllowed;
		}
		
		private static function _checkItem(&$tree, $isAllowed, $item) {
			if (isset($tree['allItems'])) {
				$isAllowed = $tree['allItems'];
			}
			
			if (isset($tree['byItem'][$item])) {
				$isAllowed = $tree['byItem'][$item];
			}
			
			if (is_array($isAllowed)) {
				$isAllowed = end($isAllowed);
			}
			
			return $isAllowed;
		}
		
		protected static function _getRulesByRole($role) {
			if ($role === null) {
				return null;
			}
			
			if (isset(self::$_roleRuleCache[$role])) {
				return self::$_roleRuleCache[$role];
			}
			
			if (!isset(self::$_roles[$role])) {
				return [];
			}
			
			$rules = [];
			
			foreach (self::$_roles[$role]['parents'] as $parent) {
				$rules = array_merge_recursive($rules, static::_getRulesByRole($parent));
			}
			
			if (!empty(self::$_rules['byRole'][$role])) {
				$rules = array_merge_recursive($rules, self::$_rules['byRole'][$role]);
			}
			
			self::$_roleRuleCache[$role] = $rules;
			
			return $rules;
		}
		
	}
