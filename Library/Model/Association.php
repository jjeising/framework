<?php
	
	/*
	 * Model Association
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Inflector'
	);
	
	abstract class Model_Association {
		
		const JOINABLE_LEFT_KEY = 'primary_key';
		const JOINABLE_RIGHT_KEY = 'foreign_key';
		
		const PROPERTY_PRIMARY_KEY_SOURCE = '_parent';
		
		protected $_name;
		protected $_parent;
		protected $_to;
		
		protected $_properties = [
			'class_name' => null,
			
			'primary_key' => [],
			'foreign_key' => [],
			
			'select' => null,
			'where' => null,
			'params' => [],
			'join' => false,
			'order_by' => null,
			'limit' => null,
			
			'join_assoications' => null
		];
		
		public function __construct(Model $parent, $name, array $properties) {
			// change default for 'join' when association is joinable
			if ($this instanceOf Model_Association_Joinable) {
				$this->_properties['join'] = true;
			}
			
			$this->_name = $class = $name;
			$this->_parent = $parent;
			
			$this->_properties = array_merge($this->_properties, $properties);
			
			if ($this->_properties['class_name'] !== null) {
				$class = $this->_properties['class_name'];
			}
			
			$this->_to = new $class();
			
			// TODO: exception if foreign_key/primary_key !is_array?
			
			if (empty($this->_properties['foreign_key'])) {
				$this->_properties['foreign_key'] = [
					mb_strtolower(Inflector::singular($parent::TABLE)) . '_id'
				];
			}
			
			if (empty($this->_properties['primary_key'])) {
				$this->_properties['primary_key'] =
					$this->{static::PROPERTY_PRIMARY_KEY_SOURCE}->primaryKey;
			}
		}
		
		// This implements init for HasOne and BelongsTo. HasMany and HasAndBelongsToMany have their own method and return a resource instead of an object
		public function init(array $parent, Model_Resource $parentResource = null) {
			// TODO: warning when key (from second argument) is missing!
			$entry = array_combine(
				$this->_properties[static::JOINABLE_RIGHT_KEY],
				array_intersect_key($parent, array_fill_keys($this->_properties[static::JOINABLE_LEFT_KEY], true))
			);
			
			if (count($entry) !== count($this->_properties['foreign_key'])) {
				return null;
			}
			
			if ($parentResource !== null) {
				$associatedFields = $parentResource
					->getAssociatedFields($this->_name);
			}
			
			if (!empty($associatedFields)) {
				foreach ($associatedFields as $field => $name) {
					$entry[$field] = $parent[$name];
				}
				
				$associatedEntry = clone $this->_to;
				$associatedEntry->initWithPrimaryKey($entry);
				
				return $associatedEntry;
			}
			
			$to = $this->_to;
			$associatedEntry = $to::findAll()
				->where($entry)
				->limit(1);
			
			self::_applyProperties($associatedEntry, $this->_properties);
			
			return $associatedEntry->first();
		}
		
		public function join(Model_Resource $resource, array $properties = []) {
			$properties = array_merge($this->_properties, $properties);
			
			if ($properties['join'] === false) {
				return false;
			}
			
			$to = $this->_to;
			
			if ($properties['select'] === null) {
				$properties['select'] = [];
				$prefix = strtolower($this->_name);
				
				foreach ($to::getFields() as $name => $field) {
					$properties['select'][] = $name . ' AS ' .
						$prefix . '_' . $name;
				}
				
				$properties['select'] = implode(', ', $properties['select']);
			}
			
			$parent = $this->_parent;
			
			foreach (array_combine(
				$this->_properties[static::JOINABLE_LEFT_KEY],
				$properties[static::JOINABLE_RIGHT_KEY]
			) as $leftKey => $rightKey) {
				$on[] = '.' . $leftKey . ' = ' . $rightKey;
			}
			
			if ($properties['where'] !== null) {
				if (is_array($properties['where'])) {
					$on = array_merge($on, $properties['where']);
				} else {
					$on[] = $properties['where'];
				}
			}
			
			$reference = null;
			
			$resource->join(
				$to::TABLE,
				$on,
				$properties['params'],
				$properties['select'],
				(!is_bool($this->_properties['join']))?
					$this->_properties['join'] :
					'LEFT',
				$reference
			);
			
			if ($properties['order_by'] !== null) {
				$resource->orderBy($properties['order_by']);
			}
			
			return $reference;
		}
		
		// This implements save for HasOne and BelongsTo
		public function save(Model $parent, array $entry, array $properties) {
			$new = clone $this->_to;
			
			foreach (array_combine(
				$this->_properties[static::JOINABLE_LEFT_KEY],
				$this->_properties[static::JOINABLE_RIGHT_KEY]
			) as $leftKey => $rightKey) {
				$new[$rightKey] = $parent[$leftKey];
			}
			
			$new->set(array_diff_key(
				$entry,
				array_fill_keys(
					$this->_properties[static::JOINABLE_RIGHT_KEY],
					true
				)
			));
			
			if (!empty($entry['_destroy'])) {
				$new->destroy();
				return;
			}
			
			$new->save();
		}
		
		protected static function _applyProperties(Model_Resource $resource, array $properties) {
			if ($properties['select'] !== null) {
				$resource->select($properties['select']);
			}
			
			if ($properties['where'] !== null) {
				$resource->where($properties['where'], $properties['params']);
			}
			
			if ($properties['order_by'] !== null) {
				$resource->orderBy($properties['order_by']);
			}
			
			if ($properties['limit'] !== null) {
				$resource->limit($properties['limit']);
			}
			
			if ($properties['join_assoications'] !== null) {
				$resource->join($properties['join_assoications']);
			}
		}
	}
	
	interface Model_Association_Joinable { }
