<?php
	
	/*
	 * Model
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Database/Query/Abstract',
		'Model/Resource',
		'Model/Association/BelongsTo',
		'Model/Association/HasAndBelongsToMany',
		'Model/Association/HasMany',
		'Model/Association/HasOne'
	);
	
	abstract class Model implements ArrayAccess {
		
		const TABLE = '';
		
		public $primaryKey = ['id'];
		
		const CREATE_IF_NOT_EXISTS = false;
		
		public $hasOne = [];
		public $hasMany = [];
		public $belongsTo = [];
		public $hasAndBelongsToMany = [];
		
		// TODO: create mapping, like 'workergroups' => 'WorkerGroups'?
		public $acceptNestedEntriesFor = [];
		
		public $fieldReader = [];
		public $fieldWriter = [];
		public $fieldAccessor = [];
		
		protected $_entry = [];
		protected $_stale = true;
		protected $_changedFields = [];
		
		protected $_parentResource;
		
		protected $_associations = [];
		protected $_associatedEntries;
		
		const FIELD_CREATED = 'created';
		const FIELD_MODIFIED = 'modified';
		
		public function __construct(array $entry = []) {
			if (!empty($entry)) {
				$this->init($entry);
				$this->_changedFields = array_fill_keys(
					array_keys($entry),
					true
				);
			}
		}
		
		public function __sleep() {
			return [
				'_entry',
				'_stale',
				'_changedFields',
				
				'_associatedEntries'
			];
		}
		
		public function __debugInfo() {
			return $this->_entry;
		}
		
		public function init(array $entry, Model_Resource $parentResource = null) {
			$this->_entry = $entry;
			$this->_changedFields = [];
			$this->_stale = false;
			
			$this->_parentResource = $parentResource;
		}
		
		public function initWithPrimaryKey(array $keys) {
			$this->_entry = array_merge($this->_entry, $keys);
			$this->_stale = true;
		}
		
		// TODO: initWithForeignKey?
		
		public function reinit() {
			if (!$keys = $this->getPrimaryKeyFields(true)) {
				return false;
			}
			
			$this->_entry = Database_Query::selectFrom(static::TABLE)
				->where($keys)
				->fetchRow();
			$this->_stale = false;
			return true;
		}
		
		public function __get($key) {
			if (!isset($this->_associatedEntries[$key])) {
				$associations = $this->getAssociations([$key => true]);
				
				if (!isset($associations[$key])) {
					throw new RuntimeException(
						'Unspecified association ' . $key
					);
				}
				
				// FIXME: $this->_entry can be empty
				
				$this->_associatedEntries[$key] = $associations[$key]
					->init(
						$this->_entry,
						$this->_parentResource
					);
			}
			
			// clone associated entry if it's a resources: user does not espect changes to this object to be store here
			if ($this->_associatedEntries[$key] instanceOf Model_Resource) {
				return clone $this->_associatedEntries[$key];
			}
			
			return $this->_associatedEntries[$key];
		}
		
		public function offsetExists($offset) {
			if ($this->_stale and !array_key_exists($offset, $this->_entry)) {
				$this->reinit();
			}
			
			return array_key_exists($offset, $this->_entry) or
				isset($this->fieldReader[$offset]) or
				isset($this->fieldAccessor[$offset]);
		}
		
		public function offsetGet($offset) {
			if ($this->_stale and !array_key_exists($offset, $this->_entry)) {
				$this->reinit();
			}
			
			if (
				isset($this->fieldReader[$offset]) or
				isset($this->fieldAccessor[$offset])
			) {
				// Method names are case insensitive so we don't need ucfirst
				return $this->{'get' . $offset}();
			}
			
			return $this->_entry[$offset];
		}
		
		public function offsetSet($offset, $value) {
			if ($offset === false or $offset === null) {
				// TODO: exception?
				return;
			}
			
			if (!array_key_exists($offset, $this->_entry) or $this->_entry[$offset] !== $value) {
				$this->_changedFields[$offset] = true;
			}
			
			if (
				isset($this->fieldWriter[$offset]) or
				isset($this->fieldAccessor[$offset])
			) {
				$value = $this->{'set' . $offset}($value);
			}
			
			$this->_entry[$offset] = $value;
		}
		
		public function offsetUnset($offset) {
			unset($this->_entry[$offset]);
			unset($this->_changedFields[$offset]);
		}
		
		public function toArray() {
			return $this->_entry;
		}
		
		public function set(array $entry) {
			foreach ($entry as $key => $value) {
				if (!array_key_exists($key, $this->_entry) or $this->_entry[$key] !== $value) {
					$this->_changedFields[$key] = true;
				}
				
				$this->_entry[$key] = $value;
			}
		}
		
		public function getPrimaryKeyFields($force = false) {
			$keys = array_intersect_key(
				$this->_entry,
				array_fill_keys($this->primaryKey, true)
			);
			
			if ($force and count($keys) !== count($this->primaryKey)) {
				return false;
			}
			
			return $keys;
		}
		
		public function changed($field = null) {
			if ($field !== null) {
				return isset($this->_changedFields[$field]);
			}
			
			if (empty($this->_changedFields)) {
				return false;
			}
			
			return $this->_changedFields;
		}
		
		public static function find($keys, array $join = []) {
			$parent = new static();
			
			if (!is_array($keys)) {
				$keys = array_combine($parent->primaryKey, [$keys]);
			}
			
			$resource = new Model_Resource($parent);
			$resource->where($keys);
			
			if ($join === null or !empty($join)) {
				$resource->join($join);
			}
			
			return $resource->first();
		}
		
		public static function findOrThrow($keys, array $join = []) {
			$result = static::find($keys, $join);
			
			if ($result === null) {
				throw new EntryNotFoundException();
			}
			
			return $result;
		}
		
		public static function findBy(array $conditions, array $params = [], array $join = []) {
			return self::findAll($join)
				->where($conditions, $params)
				->first();
		}
		
		public static function findByOrThrow(array $conditions, array $params = [], array $join = []) {
			$result = static::findBy($conditions, $params, $join);
			
			if ($result === null) {
				throw new EntryNotFoundException();
			}
			
			return $result;
		}
		
		public static function findFirst(array $join = []) {
			return self::findAll($join)
				->limit(1)
				->first();
		}
		
		public static function findFirstOrThrow(array $join = []) {
			$result = static::findFirst($join);
			
			if ($result === null) {
				throw new EntryNotFoundException();
			}
			
			return $result;
		}
		
		public static function findAll(array $join = []) {
			$parent = new static();
			
			$resource = new Model_Resource($parent);
			
			if ($join === null or !empty($join)) {
				$resource->join($join);
			}
			
			return $resource;
		}
		
		public static function exists(array $conditions, array $params = []) {
			return self::findAll()
				->where($conditions, $params)
				->exists();
		}
		
		public function applyScopes(Model_Resource $resource, array $scopes) {
			foreach ($scopes as $scope => $arguments) {
				if (!is_array($arguments)) {
					$scope = $arguments;
					$arguments = [$resource];
				} else {
					array_unshift($arguments, $resource);
				}
				
				$this->{$scope}(...$arguments);
			}
		}
		
		public function defaultScope(Model_Resource $resource) { }
		
		public static function create(array $entry) {
			try {
				return static::createOrThrow($entry);
			} catch (ModelException $e) {
				return false;
			} catch (DatabaseStatementException $e) {
				return false;
			}
		}
		
		public static function createOrThrow(array $entry) {
			// TODO: support creating empty entries with just (auto increment) key? To do this save cannot skip creating when no fields are added
			if (empty($entry)) {
				throw new ModelException('Cannot create empty entry');
			}
			
			$instance = new static($entry);
			
			if ($instance->saveOrThrow() === false) {
				return false;
			}
			
			return $instance;
		}
		
		public function save(array $entry = [], $conditions = null, array $params = []) {
			try {
				return $this->saveOrThrow($entry, $conditions, $params);
			} catch (ModelException $e) {
				return false;
			} catch (DatabaseStatementException $e) {
				return false;
			}
		}
		
		public function saveOrThrow(array $entry = [], $conditions = null, array $params = []) {
			if (!empty($entry)) {
				$this->set($entry);
			}
			
			// TODO: this is an extra query if no associations are used – there should be any if changed fields is empty?
			$fields = static::getFields();
			
			// TODO: can we reorder this to reduce depth?
			if (!empty($this->_changedFields)) {
				$values = array_intersect_key(
					$this->_entry,
					$fields,
					$this->_changedFields
				);
				
				if (!empty($values)) {
					$database = Database::$Instance;
					
					foreach ($values as $field => $value) {
						$values[$field] = $database::typify(
							$value,
							$fields[$field]
						);
					}
					
					if ($this->beforeSave($values, $fields) === false) {
						return false;
					}
					
					$created = $modified = $values;
					
					if (!empty($fields[static::FIELD_CREATED]) and
						static::FIELD_CREATED !== false and
						!isset($values[static::FIELD_CREATED])) {
						$created[] = [static::FIELD_CREATED, 'NOW()'];
					}
					
					// write only if field exists and not set in entry
					if (!empty($fields[static::FIELD_MODIFIED]) and
						static::FIELD_MODIFIED !== false and
						!isset($values[static::FIELD_MODIFIED])) {
						$created[] = [static::FIELD_MODIFIED, 'NOW()'];
						$modified[] = [static::FIELD_MODIFIED, 'NOW()'];
					}
					
					// TODO: move up, call before typify and use for beforeUpdate/beforeCreate
					$keys = $this->getPrimaryKeyFields(true);
					$returning = null;
					
					if ($keys !== false) {
						$query = Database_Query::updateTable(
							static::TABLE,
							$modified,
							$keys
						);
						
						if ($conditions !== null) {
							$query->where($conditions, $params);
						}
						
						$rows = $query->executeReturning($returning);
						
						if ($rows === false) {
							throw new ModelException('Failed to create entry');
						}
						
						if (
							($conditions !== null and $rows <= 0)
						) {
							return false;
						}
						
						if ($returning !== null) {
							$this->_entry = array_merge($this->_entry, $returning);
						}
						
						if ($this->afterUpdate() === false) {
							return false;
						}
					}
					
					if ($keys === false or ($rows <= 0 and static::CREATE_IF_NOT_EXISTS)) {
						$insertId = null;
						
						$rows = Database_Query::insertInto(
							static::TABLE,
							$created
						)->executeReturning($returning, $insertId);
						
						if ($rows === false) {
							throw new ModelException('Failed to insert entry');
						}
						
						if ($returning !== null) {
							$this->_entry = array_merge($this->_entry, $returning);
						} elseif ($insertId !== null and count($this->primaryKey) === 1) {
							$this->_entry[current($this->primaryKey)] = $insertId;
						}
						
						if ($this->afterCreate() === false) {
							return false;
						}
					}
				
					// TODO: handle constrain exceptions with 'return false'?
				
					$this->_changedFields = [];
				}
			}
			
			$associations = $this->getAssociations($this->acceptNestedEntriesFor);
			
			foreach (array_diff_key($this->_entry, $fields) as $association => $values) {
				$association = ucwords($association);
				
				if (!is_array($values) or !isset($associations[$association])) {
					continue;
				}
				
				$associations[$association]->save(
					$this,
					$values,
					(is_array($this->acceptNestedEntriesFor[$association]))?
						$this->acceptNestedEntriesFor[$association] :
						[]
				);
			}
			
			return true;
		}
		
		protected function beforeSave(&$values, array $fields) { }
		
		public function afterCreate() { }
		
		public function afterUpdate() { }
		
		// TODO: add option to include associations?
		public function duplicate($setCreated = false) {
			if ($this->_stale) {
				$this->reinit();
			}
			
			$model = clone $this;
			
			// Remove primary keys, this way a new record will be created
			$model->_entry = array_diff_key(
				$model->_entry,
				array_fill_keys($model->primaryKey, true)
			);
			
			// Mark all fields as changed, otherwise they won't be saved
			$model->_changedFields = array_fill_keys(
				array_keys($model->_entry),
				true
			);
			
			if ($setCreated) {
				// When the field is not set save() will add it
				unset($model->_entry[static::FIELD_CREATED]);
				unset($model->_changedFields[static::FIELD_CREATED]);
			}
			
			return $model;
		}
		
		public function touch(array $fields = null) {
			$fields = array_intersect_key(
				static::getFields(),
				($fields !== null)?
					array_fill_keys($fields, true) :
					[static::FIELD_MODIFIED => true]
			);
			
			$values = [];
			
			foreach ($fields as $field => $properties) {
				$values[] = [$field, 'NOW()'];
			}
			
			if (empty($values)) {
				return false;
			}
			
			return Database_Query::updateTable(
				static::TABLE,
				$values,
				array_intersect_key(
					$this->_entry,
					array_fill_keys($this->primaryKey, true)
				)
			)->execute();
		}
		
		// TODO: do anything with $this, unset persisted?
		public function destroy() {
			$keys = $this->getPrimaryKeyFields(true);
			
			if ($keys === false) {
				return false;
			}
			
			$query = Database_Query::deleteFrom(static::TABLE, $keys);
			return $query->execute() > 0;
		}
		
		public static function delete($keys) {
			$parent = new static();
			
			if (!is_array($keys)) {
				$keys = array_combine($parent->primaryKey, [$keys]);
			}
			
			$query = Database_Query::deleteFrom(static::TABLE, $keys);
			return $query->execute() > 0;
		}
		
		public static function deleteOrThrow($keys) {
			$entries = static::delete($keys);
			
			if ($entries < 1) {
				throw new EntryNotFoundException();
			}
			
			return $entries;
		}
		
		public static function getFields(): array {
			static $fields = [];
			
			if (!isset($fields[static::class])) {
				if (!$fields[static::class] = Database::$Instance->getFields(static::TABLE)) {
					throw new RuntimeException(
						'unable to get fields for table "' . static::TABLE . '"'
					);
				}
			}
			
			return $fields[static::class];
		}
		
		public function getAssociations(array $including = null, array $types = ['hasOne', 'belongsTo', 'hasMany', 'hasAndBelongsToMany']): array {
			// Associations do not store any state, only instantiate once per model base class
			static $associations = [];
			
			$result = [];
			
			foreach ($types as $type) {
				foreach ($this->{$type} as $name => $properties) {
					if ($including !== null and
						!isset($including[$name]) and
						!in_array($name, $including, true)) {
						continue;
					}
					
					if (!isset($associations[static::class][$name])) {
						$class = 'Model_Association_' . ucfirst($type);
						$associations[static::class][$name] = new $class(
							$this,
							$name,
							$properties
						);
					}
					
					$result[$name] = $associations[static::class][$name];
				}
			}
			
			return $result;
		}
	}
	
	class ModelException extends RuntimeException {}
