<?php
	
	/*
	 * Model Resource
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Database/Query/Abstract'
	);
	
	class Model_Resource extends Database_Query implements ArrayAccess, IteratorAggregate {
		
		protected $_type = self::TYPE_SELECT;
		
		protected $_parentModel;
		
		// TODO: move these to _query?
		protected $_options = [
			'indexBy' => null,
			'selectColumn' => null,
			
			'defaultScope' => true,
			
			'includes' => [],
			
			'filter' => null,
			'map' => null,
			'reverse' => false
		];
		
		protected $_entries;
		protected $_indexedEntries;
		protected $_parentResource;
		
		protected $_associationReferences = [];
		protected $_associatedFields = [];
		
		public function __construct(Model $parentModel) {
			parent::__construct($parentModel::TABLE);
			
			$this->_parentModel = $parentModel;
		}
		
		// TODO: according to php internals empty/isset may call offsetGet?
		public function offsetExists($offset) {
			if ($this->_indexedEntries === null) {
				$this->load();
				$this->_indexEntries();
			}
			
			return isset($this->_indexedEntries[$offset]);
		}
		
		public function offsetGet($offset) {
			if ($this->_indexedEntries === null) {
				$this->load();
				$this->_indexEntries();
			}
			
			if (is_array($this->_indexedEntries[$offset])) {
				$class = clone $this->_parentModel;
				$class->init(
					$this->_indexedEntries[$offset],
					$this->_parentResource
				);
				
				return $class;
			}
			
			return $this->_indexedEntries[$offset];
		}
		
		public function offsetSet($offset, $value) {
			// TODO: issue ->update? (->where?)
			throw new NotImplementedException();
		}
		
		public function offsetUnset($offset) {
			// TODO: delete? remove from result?
			throw new NotImplementedException();
		}
		
		// TODO: add default scope here?
		public function except(array $query) {
			// indexBy and includes can be removed even if the query is locked
			$indexBy = array_search('indexBy', $query, true);
			
			if ($indexBy !== false) {
				$this->_options['indexBy'] = null;
				$this->_options['selectColumn'] = null;
				
				if ($this->_indexedEntries !== null) {
					$this->_indexedEntries = null;
				}
				
				unset($query[$indexBy]);
			}
			
			$includes = array_search('includes', $query, true);
			
			if ($includes !== false) {
				$this->_options['includes'] = [];
				unset($query[$includes]);
			}
			
			if (empty($query)) {
				return;
			}
			
			return parent::except($query);
		}
		
		public function isMutable() {
			return !$this->_locked;
		}
		
		public function scoped(array $scopes) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_parentModel->applyScopes($this, $scopes);
			return $this;
		}
		
		public function withoutDefaultScope() {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_options['defaultScope'] = false;
			return $this;
		}
		
		public function includes(array $associations) {
			// includes can be altered even if the query is locked
			
			$this->_options['includes'] = array_merge_recursive(
				$this->_options['includes'],
				$associations
			);
			
			return $this;
		}
		
		public function getIncludes($association) {
			// TODO: check single primary key first?
			
			if (!in_array($association, $this->_options['includes'])) {
				return [];
			}
			
			return $this->pluck(current($this->_parentModel->primaryKey));
		}
		
		public function join($associations, $on = null, array $params = [], $select = null, $type = self::JOIN_INNER, &$reference = null) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			if (!is_array($associations) or func_num_args() > 1) {
				return parent::join(
					$associations,
					$on,
					$params,
					$select,
					$type,
					$reference
				);
			}
			
			$joinableAssociations = $this->_parentModel->getAssociations(
				$associations,
				['hasOne', 'belongsTo']
			);
			
			foreach ($joinableAssociations as $name => $association) {
				$reference = $association->join(
					$this,
					(isset($associations[$name]))? $associations[$name] : []
				);
				
				$this->_associationReferences[$reference] = $name;
			}
			
			return $this;
		}
		
		public function getAssociatedFields($association) {
			if (!isset($this->_associatedFields[$association])) {
				return [];
			}
			
			return $this->_associatedFields[$association];
		}
		
		public function indexBy($field, $column = null) {
			// indexBy can be called even if query is locked, in this case we reset _indexedEntries
			
			$this->_options['indexBy'] = $field;
			
			if ($column !== null) {
				$this->_options['selectColumn'] = $column;
			}
			
			$this->_indexedEntries = null;
			
			return $this;
		}
		
		public function filter(Callable $callback) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_options['filter'] = $callback;
			
			return $this;
		}
		
		public function map(Callable $callback) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_options['filter'] = $callback;
			
			return $this;
		}
		
		public function reverse() {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_options['reverse'] = !$this->_options['reverse'];
			
			return $this;
		}
		
		// TODO: think about returning the same object (clone/init) when iterating multiple times? Also via offsetGet
		public function getIterator() {
			$this->load();
			
			$isCallable = ($this->_options['selectColumn'] !== null and
				is_callable($this->_options['selectColumn']));
			
			foreach ($this->_entries as $key => $entry) {
				if ($this->_options['indexBy'] !== null) {
					$key = $entry[$this->_options['indexBy']];
				}
				
				if ($this->_options['selectColumn'] !== null) {
					if ($isCallable) {
						yield $key => $this->_options['selectColumn']($entry);
					} else {
						yield $key => $entry[$this->_options['selectColumn']];
					}
				} else {
					$class = clone $this->_parentModel;
					$class->init(
						$entry,
						$this->_parentResource
					);
					
					yield $key => $class;
				}
			}
		}
		
		// TODO: batch($x), returns Iterator
		
		public function toArray() {
			// This method just uses _indexedEntries since _entries is copied to _indexedEntries when no index is set
			if ($this->_indexedEntries === null) {
				$this->load();
				$this->_indexEntries();
			}
			
			return $this->_indexedEntries;
		}
		
		public function fetchAll(array &$joinedFields = null, $mode = PDO::FETCH_ASSOC) {
			if ($joinedFields !== null) {
				return parent::fetchAll($joinedFields, $mode);
			}
			
			$this->load();
			
			return $this->_entries;
		}
		
		// TODO: ensure limit(1)?
		// TODO: add order by primary key? In this case add ::take() which only requests limit(1)?
		public function first() {
			$this->load();
			
			if (empty($this->_entries)) {
				return null;
			}
			
			$entry = clone $this->_parentModel;
			$entry->init(
				$this->_entries[0],
				$this->_parentResource
			);
			
			return $entry;
		}
		
		public function getRows() {
			$this->load();
			
			return count($this->_entries);
		}
		
		public function count($row = null) {
			if ($this->_entries !== null) {
				return count($this->_entries);
			}
			
			if ($this->_query['limit'] !== null) {
				return $this->_query['limit'][0];
			}
			
			$query = clone $this;
			
			$result = $query
				->except(['fields'])
				->select('COUNT(*) AS row_count')
				->fetchRow();
			
			return (int) $result['row_count'];
		}
		
		public function exists() {
			if ($this->_entries !== null) {
				return true;
			}
			
			$query = clone $this;
			
			// only exclude fields and order (for speed), limit will be overwritten
			return $query->except(['fields', 'orderBy'])
				->select(static::SELECT_ONE_AS_ONE)
				->limit(1)
				->execute();
		}
		
		public function pluck($column) {
			if ($this->_entries !== null) {
				return array_column($this->_entries, $column);
			}
			
			$query = clone $this;
			
			return array_column(
				$query->select($column)->toArray(),
				$column
			);
		}
		
		public function insert(array $values = []) {
			// TODO: move to Database_Query::fromQuery
			$query = new Database_Query($this->_table[0]);
			$query->_table = $this->_table;
			$query->_query = $this->_query;
			
			$query->insert($values);
			
			return $query->execute();
		}
		
		public function update(array $values = []) {
			$query = new Database_Query($this->_table[0]);
			$query->_table = $this->_table;
			$query->_query = $this->_query;
			
			$query->update($values);
			
			return $query->execute();
		}
		
		public function delete() {
			$query = new Database_Query($this->_table[0]);
			$query->_table = $this->_table;
			$query->_query = $this->_query;
			
			$query->delete();
			
			return $query->execute();
		}
		
		public function reset() {
			$this->_locked = false;
			
			$this->_entries = null;
			$this->_indexedEntries = null;
			
			$this->_associatedFields = [];
			
			unset($this->_parentResource);
			
			return $this;
		}
		
		public function reload() {
			$this->reset();
			$this->load();
			
			return $this;
		}
		
		public function load() {
			if ($this->_entries !== null) {
				return $this;
			}
			
			$this->_parentResource = clone $this;
			
			if ($this->_options['defaultScope']) {
				$this->_parentModel->defaultScope($this->_parentResource);
			}
			
			// lock after default scope is executed, this may set additional parameters
			$this->_locked = true;
			
			$joinedFields = [];
			$this->_entries = $this->_parentResource->fetchAll($joinedFields);
			
			if ($this->_options['filter'] !== null) {
				// Reset indicies with array_values
				$this->_entries = array_values(array_filter(
					$this->_entries,
					$this->_options['filter']
				));
			}
			
			if ($this->_options['map'] !== null) {
				$this->_entries = array_map(
					$this->_options['map'],
					$this->_entries
				);
			}
			
			if ($this->_options['reverse'] === true) {
				$this->_entries = array_reverse($this->_entries);
			}
			
			if (empty($joinedFields)) {
				return $this;
			}
			
			foreach ($joinedFields as $reference => $fields) {
				if (!isset($this->_associationReferences[$reference])) {
					continue;
				}
					
				$this->_parentResource
					->_associatedFields[$this->_associationReferences[$reference]] =
					$fields;
			}
			
			return $this;
		}
		
		protected function _indexEntries() {
			if ($this->_indexedEntries !== null) {
				return;
			}
			
			if (empty($this->_entries)) {
				$this->_indexedEntries = [];
				return;
			}
			
			$options = $this->_parentResource->_options;
			
			if ($options['indexBy'] === null) {
				$this->_indexedEntries = $this->_entries;
				return;
			}
			
			// TODO: check only if $column is array? Or check for instanceOf Closure
			$isCallable = is_callable($options['selectColumn']);
			
			if ($options['selectColumn'] !== null and !$isCallable) {
				$this->_indexedEntries = array_column(
					$this->_entries,
					$options['selectColumn'],
					$options['indexBy']
				);
				
				return;
			}
			
			foreach ($this->_entries as $entry) {
				if ($options['selectColumn'] === null) {
					$this->_indexedEntries[$entry[$options['indexBy']]] = $entry;
					continue;
				}
				
				if ($isCallable) {
					$this->_indexedEntries[$entry[$options['indexBy']]] =
						$options['selectColumn']($entry);
					continue;
				}
				
				$this->_indexedEntries[$entry[$options['indexBy']]] =
					$entry[$options['selectColumn']];
			}
		}
		
	}
	
	class Model_Resource_Grouped implements IteratorAggregate {
		
		protected $_resource;
		protected $_field;
		
		public function __construct(Model_Resource $resource, $field) {
			if (!$resource->isMutable()) {
				throw new ImmutableQueryException(
					'Unable to group an immutable resource.'
				);
			}
			
			$this->_resource = $resource;
			$this->_field = $field;
			
			$this->_resource->orderBy($field);
		}
		
		public function toArray() {
			return iterator_to_array($this);
		}
		
		public function getIterator() {
			if ($this->_resource->getRows() <= 0) {
				return;
			}
			
			$entries = $this->_resource->getIterator();
			$entry = $entries->current();
			
			$key = null;
			
			do {
				if ($key !== $entry[$this->_field]) {
					if ($key !== null) {
						yield $key => $group;
					}
					
					$key = $entry[$this->_field];
					$group = [];
				}
				
				$group[] = $entry;
				$entries->next();
			} while (($entry = $entries->current()) !== null);
			
			if ($group !== []) {
				yield $key => $group;
			}
		}
		
	}
