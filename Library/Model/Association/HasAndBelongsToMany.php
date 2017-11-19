<?php
	
	/*
	 * Model Association HasAndBelongsToMany
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires('Model/Association');
	
	class Model_Association_HasAndBelongsToMany extends Model_Association {
		
		const JOINABLE_SELF_KEY = 'self_key';
		
		public function __construct(Model $parent, $name, array $properties) {
			$this->_properties['self_key'] = [];
			$this->_properties['via'] = null;
			
			parent::__construct($parent, $name, $properties);
		}
		
		// TODO: check associated fields?
		public function init(array $parent, Model_Resource $parentResource = null) {
			$to = $this->_to;
			$associatedEntries = $to::findAll();
			
			foreach (array_combine(
				$this->_properties[static::JOINABLE_LEFT_KEY],
				$this->_properties[static::JOINABLE_SELF_KEY]
			) as $leftKey => $selfKey) {
				$associatedEntries->where([
					$this->_properties['via'] . '.' . $selfKey
						=> $parent[$leftKey]
				]);
			}
			
			// TODO: add via table to select, this needs support in Database_Query_Abstract, we currently only support ['table', 'field']
			self::_applyProperties($associatedEntries, $this->_properties);
			
			$on = [];
			
			foreach (array_combine(
				$to->primaryKey,
				$this->_properties[static::JOINABLE_RIGHT_KEY]
			) as $leftKey => $rightKey) {
				$on[] = '.' . $leftKey . ' = ' . $rightKey;
			}
			
			$associatedEntries->join(
				$this->_properties['via'],
				$on,
				[],
				$this->_properties['select']
			);
			
			return $associatedEntries;
		}
		
		public function save(Model $parent, array $entries, array $properties) {
			$foreignKeys = array_fill_keys(
				$this->_properties[static::JOINABLE_RIGHT_KEY],
				true
			);
			$foreignKeyCount = count($foreignKeys);
			
			$selfKeys = array_fill_keys(
				$this->_properties[static::JOINABLE_SELF_KEY],
				true
			);
			
			$selfKeyFields = array_combine(
				$this->_properties[static::JOINABLE_SELF_KEY],
				$parent->getPrimaryKeyFields()
			);
			
			$base = new Database_Query($this->_properties['via']);
			$base->where($selfKeyFields);
			
			foreach ($entries as $entry) {
				$foreignKeyFields = array_filter(
					array_intersect_key($entry, $foreignKeys)
				);
				
				if (count($foreignKeyFields) !== $foreignKeyCount) {
					continue;
				}
				
				$query = clone $base;
				$query->where($foreignKeyFields);
				
				if (!empty($entry['_destroy'])) {
					$query->delete()->execute();
					continue;
				}
				
				unset($entry['_destroy']);
				
				$values = array_diff_key($entry, $selfKeys, $foreignKeys);
				
				Database::$Instance->beginTransaction();
				
				if (!empty($values)) {
					// if there are additional values first try to update the entry with the specified keys
					$query->update($values);
				} else {
					// otherwise just check for an existing entry
					$query
						->select(Database_Query::SELECT_ONE_AS_ONE)
						->limit(1);
				}
				
				if ($query->execute() === 1) {
					Database::$Instance->commit();
					continue;
				}
				
				$query->insert($entry + $selfKeyFields)->execute();
				
				Database::$Instance->commit();
			}
		}
		
	}
