<?php
	
	/*
	 * Database Query Abstract
	 *
	 * Build queries for different database management systems
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	abstract class Database_Query_Abstract {
		
		protected $_table;
		protected $_type;
		
		// Queries are not locked in Database_Query_Abstract itself (because it stores no results), but in child classes like Model_Resource
		protected $_locked = false;
		
		protected $_query = [
			'select' => [],
			'distinct' => false,
			'as' => null,
			'values' => [],
			'join' => [],
			'join_order' => [],
			'where' => [],
			'orderBy' => null,
			'groupBy' => null,
			'limit' => null,
			'having' => []
		];
		
		// TODO: move to constants?
		protected static $_syntax = [
			'SELECT %s FROM %s' => 'SELECT %s FROM %s',
			'SELECT DISTINCT %s FROM %s' => 'SELECT DISTINCT %s FROM %s',
			
			'INSERT INTO %s %s' => 'INSERT INTO %s %s',
			'INSERT INTO %s (%s) VALUES (%s)' => 'INSERT INTO %s (%s) VALUES (%s)',
			'INSERT INTO %s () VALUES ()' => 'INSERT INTO %s () VALUES ()',
			
			'UPDATE %s SET %s' => 'UPDATE %s SET %s',
			
			'DELETE FROM %s' => 'DELETE FROM %s',
			
			' %s JOIN %s' => ' %s JOIN %s',
			' ON %s' => ' ON %s',
			' AS %s' => ' AS %s',
			
			' WHERE %s' => ' WHERE %s',
			' HAVING %s' => ' HAVING %s',
			
			' AND ' => ' AND ',
			' OR ' => ' OR ',
			'NOT (%s)' => 'NOT (%s)',
			
			'%s IN (%s)' => '%s IN (%s)',
			'%s NOT IN (%s)' => '%s NOT IN (%s)',
			'FALSE' => 'FALSE',
			
			'%s IS NULL' => '%s IS NULL',
			'%s IS NOT NULL' => '%s IS NOT NULL',
			
			'%s = 0' => '%s = 0',
			'%s = 1' => '%s = 1',
			
			'%s = ?' => '%s = ?',
			'%s != ?' => '%s != ?',
			
			' ORDER BY %s' => ' ORDER BY %s',
			' GROUP BY %s' => ' GROUP BY %s',
			
			' LIMIT %d' => ' LIMIT %d',
			' LIMIT %d OFFSET %d' => ' LIMIT %d OFFSET %d',
			
			'(%s) AS %s' => '(%s) AS %s'
		];
		
		const TYPE_SELECT = 1;
		const TYPE_INSERT = 2;
		const TYPE_UPDATE = 3;
		const TYPE_DELETE = 4;
		
		const JOIN_INNER = 'INNER';
		const JOIN_LEFT = 'LEFT';
		const JOIN_RIGHT = 'RIGHT';
		const JOIN_OUTER = 'OUTER';
		
		const WHERE_AND = 'AND';
		const WHERE_OR = 'OR';
		const WHERE_NOT = 'NOT';
		
		const KEYWORD_QUOTE = '`';
		const VALUE_QUOTE = '"';
		
		const SELECT_ONE_AS_ONE = '1 AS one';
		
		public function __construct($table) {
			$this->_table = [(string) $table];
			$this->_query['select'] = [self::_getQuotedTable($this->_table) . '.*'];
		}
		
		public function except(array $query) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			foreach ($query as $part) {
				switch ($part) {
					case 'distinct':
						$this->_query[$part] = false;
						break;
					case 'as':
					case 'orderBy':
					case 'groupBy':
					case 'limit':
						$this->_query[$part] = null;
						break;
					case 'join':
						$this->_query['join_order'] = [];
						$this->_query['join'] = [];
						break;
					case 'select':
					case 'values':
					case 'where':
					case 'having':
						$this->_query[$part] = [];
						break;
					case 'fields':
						$this->_query['select'] = [];
						
						foreach ($this->_query['join'] as $table => $joins) {
							foreach ($joins as $key => $join) {
								$this->_query['join'][$table][$key]['select'] = null;
							}
						}
						break;
				}
			}
			
			return $this;
		}
		
		public function only(array $query) {
			throw new NotImplementedException();
		}
		
		public function select(...$select) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_type = self::TYPE_SELECT;
			
			if (count($select) > 0) {
				$this->_query['select'] = $select;
			}
			
			return $this;
		}
		
		public function andSelect() {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_type = self::TYPE_SELECT;
			
			$this->_query['select'] = array_merge(
				$this->_query['select'],
				func_get_args()
			);
			
			return $this;
		}
		
		public function distinct($distinct = true) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['distinct'] = $distinct;
			return $this;
		}
		
		public function from($table, $as = null, array $params = null) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_table = [$table];
			
			if ($as !== null) {
				$this->_table[] = $as;
				
				if ($params !== null) {
					$this->_table[] = $params;
				}
			}
			
			return $this;
		}
		
		public function selectAs($as) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['as'] = $as;
			return $this;
		}
		
		public function join($table, $on, array $params = [], $select = null, $type = self::JOIN_INNER, &$reference = null) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			if (!is_array($table)) {
				$table = [$table];
			}
			
			if (!isset($this->_query['join'][$table[0]])) {
				$this->_query['join'][$table[0]] = [];
			}
			
			$length = array_push(
				$this->_query['join'][$table[0]],
				[
					'from' => $table,
					'select' => $select,
					'on' => [self::WHERE_AND, $on, $params],
					'type' => $type
				]
			);
			
			$this->_query['join_order'][] = [$table[0], $length - 1];
			
			if (!isset($table[1])) {
				$this->_query['join'][$table[0]][$length - 1]['from'][1] =
					$table[0] .
					(($length > 1 or $table === $this->_table[0])?
						('_' . $length) : '');
			}
			
			$reference = $this->_query['join'][$table[0]][$length - 1]['from'][1];
			
			return $this;
		}
		
		public function leftJoin($table, $on = null, array $params = [], $select = null, &$reference = null) {
			return $this->join($table, $on, $params, $select, self::JOIN_LEFT, $reference);
		}
		
		public function rightJoin($table, $on = null, array $params = [], $select = null, &$reference = null) {
			return $this->join($table, $on, $params, $select, self::JOIN_RIGHT, $reference);
		}
		
		public function outerJoin($table, $on = null, array $params = [], $select = null, &$reference = null) {
			return $this->join($table, $on, $params, $select, self::JOIN_OUTER, $reference);
		}
		
		public function where($conditions, array $params = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['where'][] = [self::WHERE_AND, $conditions, $params];
			return $this;
		}
		
		public function orWhere($conditions, array $params = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['where'][] = [self::WHERE_OR, $conditions, $params];
			return $this;
		}
		
		public function whereNot($conditions, array $params = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['where'][] = [self::WHERE_NOT, $conditions, $params];
			return $this;
		}
		
		public function having($conditions, array $params = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['having'][] = [self::WHERE_AND, $conditions, $params];
			return $this;
		}
		
		public function orHaving($conditions, array $params = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['having'][] = [self::WHERE_OR, $conditions, $params];
			return $this;
		}
		
		public function notHaving($conditions, array $params = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['having'][] = [self::WHERE_NOT, $conditions, $params];
			return $this;
		}
		
		public function orderBy($order) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['orderBy'] = $order;
			return $this;
		}
		
		public function groupBy($group) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['groupBy'] = $group;
			return $this;
		}
		
		public function limit($count, $offset = null) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_query['limit'] = [$count, $offset];
			return $this;
		}
		
		public function insert(array $values = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_type = self::TYPE_INSERT;
			$this->_query['values'] = $values;
			return $this;
		}
		
		public function insertFrom(Database_Query $query) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_type = self::TYPE_INSERT;
			$this->_query['values'] = $query;
			return $this;
		}
		
		public function update(array $values = []) {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_type = self::TYPE_UPDATE;
			$this->_query['values'] = $values;
			return $this;
		}
		
		public function delete() {
			if ($this->_locked) throw new ImmutableQueryException();
			
			$this->_type = self::TYPE_DELETE;
			return $this;
		}
		
		public static function selectFrom($table, $fields = null, $conditions = null, array $params = []) {
			$query = new static($table);
			
			if ($fields === null) {
				$query->select();
			} else {
				$query->select($fields);
			}
			
			if ($conditions !== null) {
				$query->where($conditions, $params);
			}
			
			return $query;
		}
		
		public static function insertInto($table, array $values) {
			$query = new static($table);
			return $query->insert($values);
		}
		
		public static function updateTable($table, array $values = [], $conditions = null, array $params = []) {
			$query = new static($table);
			$query->update($values);
			
			if ($conditions !== null) {
				$query->where($conditions, $params);
			}
			
			return $query;
		}
		
		public static function deleteFrom($table, $conditions = null, array $params = []) {
			$query = new static($table);
			$query->delete();
			
			if ($conditions !== null) {
				$query->where($conditions, $params);
			}
			
			return $query;
		}
		
		// TODO: only/except (by table name)
		// TODO: move to executer?
		public static function readOnly() {
			
		}
		
		public function execute(array &$joinedFields = []) {
			list($query, $params, $joinedFields) = $this->build();
			
			if (!$statement = Database::$Instance->query($query, $params)) {
				return false;
			}
			
			return $statement->rowCount();
		}
		
		public function executeReturning(array &$entry = null, &$insertId = null, array &$joinedFields = [], $mode = PDO::FETCH_ASSOC) {
			list($query, $params, $joinedFields) = $this->build();
			
			if (!$statement = Database::$Instance->query($query, $params)) {
				return false;
			}
			
			$rows = $statement->rowCount();
			
			if ($rows !== 1) {
				return $rows;
			}
			
			$result = $statement->fetch($mode);
			
			if ($result !== false) {
				$entry = $result;
			} elseif ($this->_type === self::TYPE_INSERT) {
				$insertId = Database::$Instance->getInsertId($statement);
			}
			
			return $rows;
		}
		
		public function fetchAll(array &$joinedFields = [], $mode = PDO::FETCH_ASSOC) {
			list($query, $params, $joinedFields) = $this->build();
			
			$statement = Database::$Instance->query($query, $params);
			return $statement->fetchAll($mode);
		}
		
		public function fetchRow(array &$joinedFields = [], $mode = PDO::FETCH_ASSOC) {
			list($query, $params, $joinedFields) = $this->build();
			
			$statement = Database::$Instance->query($query, $params);
			return $statement->fetch($mode);
		}
		
		public function __toString() {
			list($query) = $this->build();
			return $query;
		}
		
		public function build() {
			$query = '';
			$params = [];
			$queryDefinedFields = [];
			$joinedFields = [];
			
			switch ($this->_type) {
				case self::TYPE_SELECT:
					$fields = [];
					$joins = '';
					
					if (!empty($this->_query['join'])) {
						$joins = $this->_getJoins(
							$fields,
							$params,
							$queryDefinedFields,
							$joinedFields
						);
					}
					
					static::_setFields(
						$this->_query['select'],
						$fields,
						$this->_table,
						$this->_table,
						$params,
						$queryDefinedFields
					);
					
					if (!isset($this->_table[2])) {
						$from = self::_getQuotedTable($this->_table, true);
					} else {
						// Support select from function
						$from = $this->_table[0];
						$params = array_merge($params, $this->_table[2]);
					}
					
					$query .= sprintf(
						($this->_query['distinct'])?
							self::$_syntax['SELECT DISTINCT %s FROM %s'] :
							self::$_syntax['SELECT %s FROM %s'],
						implode(', ', $fields),
						$from
					);
					
					if (isset($this->_table[1])) {
						$query .= sprintf(' AS %s', static::KEYWORD_QUOTE .
							$this->_table[1] .
							static::KEYWORD_QUOTE);
					}
					
					$query .= $joins;
					
					if (!empty($this->_query['where'])) {
						$conditions = static::_getConditions(
							$this->_query['where'],
							$this->_table,
							$this->_table,
							$params,
							$queryDefinedFields
						);
						
						if ($conditions !== '') {
							$query .= sprintf(
								self::$_syntax[' WHERE %s'],
								$conditions
							);
						}
					}
					
					if ($this->_query['groupBy'] !== null) {
						$query .= sprintf(
							self::$_syntax[' GROUP BY %s'],
							self::_sanitize(
								$this->_query['groupBy'],
								$this->_table,
								$this->_table,
								$queryDefinedFields
							)
						);
					}
					
					if (!empty($this->_query['having'])) {
						$conditions = static::_getConditions(
							$this->_query['having'],
							$this->_table,
							$this->_table,
							$params,
							$queryDefinedFields
						);
						
						if ($conditions !== '') {
							$query .= sprintf(
								self::$_syntax[' HAVING %s'],
								$conditions
							);
						}
					}
					
					if ($this->_query['orderBy'] !== null) {
						$query .= sprintf(
							self::$_syntax[' ORDER BY %s'],
							self::_sanitize(
								$this->_query['orderBy'],
								$this->_table,
								$this->_table,
								$queryDefinedFields
							)
						);
					}
					
					if ($this->_query['limit'] !== null) {
						if ($this->_query['limit'][1] !== null) {
							$query .= sprintf(
								self::$_syntax[' LIMIT %d OFFSET %d'],
								$this->_query['limit'][0],
								$this->_query['limit'][1]
							);
						} else {
							$query .= sprintf(
								self::$_syntax[' LIMIT %d'],
								$this->_query['limit'][0]
							);
						}
					}
					
					if ($this->_query['as'] !== null) {
						$query = sprintf('(%s) AS %s', $query, $this->_query['as']);
					}
					
					break;
				case self::TYPE_INSERT:
					$fields = [];
					$values = [];
					
					if (empty($this->_query['values'])) {
						$query .= sprintf(
							self::$_syntax['INSERT INTO %s () VALUES ()'],
							$this->_table[0]
						);
						break;
					}
					
					if ($this->_query['values'] instanceOf Database_Query) {
						// TODO: ensure SELECT?
						list($subQuery, $subQueryParams) = $this->_query['values']
							->except(['as'])
							->build();
						
						$query .= sprintf(
							self::$_syntax['INSERT INTO %s %s'],
							self::_getQuotedTable($this->_table, true),
							$subQuery
						);
						$params = array_merge(
							$params,
							$subQueryParams
						);
						break;
					}
					
					foreach ($this->_query['values'] as $field => $value) {
						if (is_array($value)) {
							$fields[] = static::KEYWORD_QUOTE .
								$value[0] .
								static::KEYWORD_QUOTE;
							$values[] = $value[1];
							continue;
						}
						
						$fields[] = static::KEYWORD_QUOTE .
							$field .
							static::KEYWORD_QUOTE;
						
						$values[] = '?';
						$params[] = $value;
					}
					
					$query .= sprintf(
						self::$_syntax['INSERT INTO %s (%s) VALUES (%s)'],
						$this->_table[0],
						implode(', ', $fields),
						implode(', ', $values)
					);
					
					break;
				case self::TYPE_UPDATE:
					$sets = [];
					
					foreach ($this->_query['values'] as $field => $value) {
						// TODO: legacy, is there a better solution?
						if (is_array($value)) {
							$sets[] = static::KEYWORD_QUOTE .
								$value[0] .
								static::KEYWORD_QUOTE .
								' = ' .
								$value[1];
							continue;
						}
						
						if ($value instanceOf Database_Query) {
							list($subQuery, $subQueryParams) = $value
								->except(['as'])
								->build();
							
							$sets[] = static::KEYWORD_QUOTE .
								$field .
								static::KEYWORD_QUOTE .
								' = (' . $subQuery . ')';
							$params = array_merge($params, $subQueryParams);
							continue;
						}
						
						$sets[] = static::KEYWORD_QUOTE . $field . static::KEYWORD_QUOTE . ' = ?';
						$params[] = $value;
					}
					
					$query .= sprintf(
						self::$_syntax['UPDATE %s SET %s'],
						self::_getQuotedTable($this->_table, true),
						implode(', ', $sets)
					);
					
					if (!empty($this->_query['where'])) {
						$query .= sprintf(
							self::$_syntax[' WHERE %s'],
							static::_getConditions(
								$this->_query['where'],
								$this->_table,
								$this->_table,
								$params,
								$queryDefinedFields
							)
						);
					}
					
					break;
				case self::TYPE_DELETE:
					$query .= sprintf(
						self::$_syntax['DELETE FROM %s'],
						self::_getQuotedTable($this->_table, true)
					);
					
					if (!empty($this->_query['where'])) {
						$query .= sprintf(
							self::$_syntax[' WHERE %s'],
							static::_getConditions(
								$this->_query['where'],
								$this->_table,
								$this->_table,
								$params,
								$queryDefinedFields
							)
						);
					}
					
					break;
			}
			
			return [$query, $params, $joinedFields];
		}
		
		protected static function _getQuotedTable($table = null, $force = false) {
			if (isset($table[1]) and !$force) {
				$table = $table[1];
			} else {
				$table = $table[0];
				
				if (strpos($table, '.') !== false) {
					$table = implode(
						static::KEYWORD_QUOTE . '.' . static::KEYWORD_QUOTE,
						explode('.', $table)
					);
				}
			}
			
			return static::KEYWORD_QUOTE . $table . static::KEYWORD_QUOTE;
		}
		
		protected function _getJoins(array &$fields, array &$params, array &$queryDefinedFields, array &$joinedFields) {
			$joins = '';
			
			foreach ($this->_query['join_order'] as $index) {
				$table = $index[0];
				$join = $this->_query['join'][$table][$index[1]];
				
				$as = null;
				
				if ($join['select'] !== null) {
					$joinedFields[$join['from'][1]] = [];
					
					static::_setFields(
						[$join['select']],
						$fields,
						$join['from'],
						$this->_table,
						$params,
						$queryDefinedFields,
						$joinedFields[$join['from'][1]]
					);
				}
				
				$joins .= sprintf(
					self::$_syntax[' %s JOIN %s'],
					$join['type'],
					self::_getQuotedTable($join['from'], true)
				);
				
				if (isset($join['from'][1])) {
					$joins .= sprintf(
						self::$_syntax[' AS %s'],
						static::KEYWORD_QUOTE .
							$join['from'][1] .
							static::KEYWORD_QUOTE
					);
				}
				
				if ($join['on'] !== null) {
					$joins .= sprintf(
						self::$_syntax[' ON %s'],
						static::_getConditions(
							[$join['on']],
							$join['from'],
							$this->_table,
							$params,
							$queryDefinedFields
						)
					);
				}
			}
			
			return $joins;
		}
		
		protected static function _setFields(array $select, array &$fields, array $table, array $parentTable, array &$params, array &$queryDefinedFields, array &$fieldIndex = []) {
			foreach ($select as $part) {
				if ($part === false) {
					continue;
				}
				
				if ($part instanceOf Database_Query) {
					// TODO: ensure 'as'?
					// TODO: add to $fieldIndex with AS
					list($subQuery, $subQueryParams) = $part->build();
					
					$fields[] = $subQuery;
					$params = array_merge($params, $subQueryParams);
					continue;
				}
				
				if (!is_array($part)) {
					$fields[] = static::_sanitize(
						$part,
						$table,
						$parentTable,
						$queryDefinedFields,
						$fieldIndex
					);
					continue;
				}
				
				foreach ($part as $field) {
					if (is_array($field)) {
						$fields[] = static::KEYWORD_QUOTE .
							$field[0] .
							static::KEYWORD_QUOTE .
							'.' .
							static::KEYWORD_QUOTE .
							$field[1] .
							static::KEYWORD_QUOTE;
						$fieldIndex[$field[1]] = $field[1];
						continue;
					}
					
					$fields[] = $field;
					$fieldIndex[$field] = $field;
				}
			}
		}
		
		protected static function _getConditions(
			$where,
			array $table,
			array $parentTable,
			array &$params,
			array &$queryDefinedFields
		) {
			$conditions = '';
			
			foreach ($where as $part) {
				$params = array_merge($params, $part[2]);
				
				if (!is_array($part[1])) {
					if (!empty($conditions)) {
						$conditions .= ($part[0] === self::WHERE_OR)?
							self::$_syntax[' OR '] :
							self::$_syntax[' AND '];
					}
					
					$part[1] = self::_sanitize(
						$part[1],
						$table,
						$parentTable,
						$queryDefinedFields
					);
					
					if ($part[0] === self::WHERE_NOT) {
						$conditions .= sprintf(self::$_syntax['NOT (%s)'], $part[1]);
					} else {
						$conditions .= '(' . $part[1] . ')';
					}
					
					continue;
				}
				
				/*
					This enables special behaviour for orWhere:
					
						->where(['a' => 1])
						->orWhere(['b' => 1])
					
					results in
					
						WHERE (a = ?) OR (b = ?)
					
					while
						
						->where(['a' => 1])
						->orWhere(['b' => 1, 'c' => 1])
					
					results in
						
						WHERE (a = ?) AND (b = ? OR c = ?)
				
				*/
				$singleCondition = (count($part[1]) <= 1);
				$firstJunction = true;
				
				foreach ($part[1] as $key => $condition) {
					if (!empty($conditions)) {
						if ($part[0] === self::WHERE_OR and (!$firstJunction or $singleCondition)) {
							$conditions .= self::$_syntax[' OR '];
						} else {
							$conditions .= self::$_syntax[' AND '];
						}
					}
					
					if ($firstJunction) {
						$conditions .= '(';
						$firstJunction = false;
					}
					
					if (is_int($key)) {
						$conditions .= self::_sanitize(
							$condition,
							$table,
							$parentTable,
							$queryDefinedFields
						);
						continue;
					}
					
					if (strpos($key, '.') === false and !isset($queryDefinedFields[$key])) {
						$key = self::_getQuotedTable($table) .
							'.' .
							static::KEYWORD_QUOTE .
							$key .
							static::KEYWORD_QUOTE;
					}
					
					if (is_array($condition) or $condition instanceOf Database_Query) {
						$subQuery = null;
						
						if ($condition instanceOf Database_Query) {
							// TODO: ->except(['as'])?
							list($subQuery, $condition) = $condition
								->build();
						} elseif ($condition === []) {
							// We've got an empty set, this is always false
							$conditions .= self::$_syntax['FALSE'];
							continue;
						}
						
						$conditions .= sprintf(
							($part[0] === self::WHERE_NOT)?
								self::$_syntax['%s NOT IN (%s)'] :
								self::$_syntax['%s IN (%s)'],
							$key,
							(isset($subQuery))?
								$subQuery :
								substr(str_repeat('?, ', count($condition)), 0, -2)
						);
						
						$params = array_merge($params, $condition);
						
						continue;
					}
					
					if ($condition === null) {
						$conditions .= sprintf(
							($part[0] === self::WHERE_NOT)?
								self::$_syntax['%s IS NOT NULL'] :
								self::$_syntax['%s IS NULL'],
							$key
						);
						continue;
					}
					
					if (is_bool($condition)) {
						if ($part[0] === self::WHERE_NOT) {
							$condition = !$condition;
						}
						
						if ($condition === false) {
							$conditions .= sprintf(self::$_syntax['%s = 0'], $key);
						} else {
							$conditions .= sprintf(self::$_syntax['%s = 1'], $key);
						}
						
						continue;
					}
					
					if ($part[0] === self::WHERE_NOT) {
						$conditions .= sprintf(self::$_syntax['%s != ?'], $key);
					} else {
						$conditions .= sprintf(self::$_syntax['%s = ?'], $key);
					}
					
					$params[] = $condition;
				}
				
				if (!empty($part[1])) {
					$conditions .= ')';
				}
			}
			
			return $conditions;
		}
		
		protected static function _sanitize($originalQuery, array $table, array $parentTable, array &$queryDefinedFields, array &$fieldIndex = []) {
			$query = strtolower($originalQuery) . ' ';
			$length = strlen($query);
			$part = '';
			$result = '';
			
			// We probably need this, so get it before
			if (!empty($table)) {
				$table = self::_getQuotedTable($table);
			}
			
			$protectedUntil = null;
			$partHasPrefix = false;
			$partHasQuote = false;
			
			$currentIsSpace = false;
			$previousWasSpace = false;
			
			$nextIsUserField = false;
			$nextIsNoKeyword = false;
			$nextIsFunction = false;
			
			for ($i = 0; $i < $length; $i++) {
				if ($protectedUntil !== null and $query[$i] !== $protectedUntil) {
					$part .= $originalQuery[$i];
					continue;
				}
				
				// Treat all whitespace as space
				switch ($query[$i]) {
					case "\n":
					case "\r":
					case "\t":
						$query[$i] = ' ';
						// Fall through
					case ' ':
						$currentIsSpace = true;
						break;
					default:
						$currentIsSpace = false;
						break;
				}
				
				switch ($query[$i]) {
					case static::KEYWORD_QUOTE:
						$nextIsNoKeyword = true;
						$part .= $query[$i];
						break;
					
					case '\'':
					case '"':
						if ($protectedUntil === null) {
							$protectedUntil = $query[$i];
							break;
						}
						
						$result .= $query[$i] . $part . $query[$i];
						$part = '';
							
						$protectedUntil = null;
						break;
					
					case '?':
						$result .= $query[$i];
						break;
					
					case '(':
						if (!empty($part)) {
							$nextIsFunction = true;
						}
						// Fall through
					case ')':
					case ',':
					case '!':
					case '=':
					case '<':
					case '>':
					case '|':
					case '&':
					case '^':
					case '+':
					case '-':
					case '/':
					case '*':
					case '%':
					case '~':
					case ':':
					// TODO: can we queue [space] until next character? We need this for * to keep "a, * , b" and "a * b AS c" apart
					case ' ':
						if ($part === '' and $query[$i] !== '*') {
							// ignore duplicate spaces
							if ($query[$i] === ' ' and $previousWasSpace) {
								break;
							}
							
							$result .= $query[$i];
							break;
						}
						
						if (isset(self::$_reservedWords[$part]) and !$nextIsNoKeyword) {
							// TODO: why for FROM?
							if ($part === 'as' or $part === 'from') {
								$nextIsUserField = true;
							}
							
							$result .= self::$_reservedWords[$part] . $query[$i];
							$part = '';
							break;
						}
						
						// if * is an operator we don't want a prefix
						if ($query[$i] === '*' and isset($query[$i + 1]) and
							$query[$i + 1] !== ',' and
							$query[$i + 1] !== ' ') {
							// TODO: check if next char that is not a whitespace is not a comma
							$result .= $query[$i];
							break;
						}
						
						if ($nextIsNoKeyword) {
							$nextIsNoKeyword = false;
							$partHasQuote = true;
						}
						
						if ($nextIsFunction) {
							$part = strtoupper($part);
							$nextIsFunction = false;
							$nextIsUserField = false;
						} elseif ($nextIsUserField) {
							$queryDefinedFields[$part] = true;
							$nextIsUserField = false;
							
							// drop last field, use current from "AS"
							$fieldIndex[array_pop($fieldIndex)] = $part;
							
							$part = static::KEYWORD_QUOTE .
								$part .
								static::KEYWORD_QUOTE;
						} elseif ($partHasPrefix) {
							$partHasPrefix = false;
						} elseif (isset($queryDefinedFields[$part])) {
							$part = static::KEYWORD_QUOTE .
								$part .
								static::KEYWORD_QUOTE;
						} elseif (!empty($table) and !is_numeric($part)) {
							$fieldIndex[$part] = $part;
							$result .= $table . '.';
							
							if (!empty($part) and !$partHasQuote) {
								$part = static::KEYWORD_QUOTE .
									$part .
									static::KEYWORD_QUOTE;
							}
							
							$partHasQuote = false;
						}
						$result .= $part . $query[$i];
						$part = '';
						break;
					
					case '.':
						// Check if table is empty, use parent table (.field)
						if (empty($part)) {
							$part = self::_getQuotedTable($parentTable);
						}
						
						$partHasPrefix = true;
						// Fall through
					default:
						$part .= $query[$i];
						break;
				}
				
				$previousWasSpace = $currentIsSpace;
				$currentIsSpace = false;
			}
			
			return substr($result, 0, -1);
		}
		
		protected static $_reservedWords = [
			'add' => 'ADD',
			'all' => 'ALL',
			'alter' => 'ALTER',
			'analyze' => 'ANALYZE',
			'and' => 'AND',
			'as' => 'AS',
			'asc' => 'ASC',
			'asensitive' => 'ASENSITIVE',
			'before' => 'BEFORE',
			'between' => 'BETWEEN',
			'bigint' => 'BIGINT',
			'binary' => 'BINARY',
			'blob' => 'BLOB',
			'both' => 'BOTH',
			'by' => 'BY',
			'call' => 'CALL',
			'cascade' => 'CASCADE',
			'case' => 'CASE',
			'change' => 'CHANGE',
			'char' => 'CHAR',
			'character' => 'CHARACTER',
			'check' => 'CHECK',
			'collate' => 'COLLATE',
			'column' => 'COLUMN',
			'condition' => 'CONDITION',
			'constraint' => 'CONSTRAINT',
			'continue' => 'CONTINUE',
			'convert' => 'CONVERT',
			'create' => 'CREATE',
			'cross' => 'CROSS',
			'current_date' => 'CURRENT_DATE',
			'current_time' => 'CURRENT_TIME',
			'current_timestamp' => 'CURRENT_TIMESTAMP',
			'current_user' => 'CURRENT_USER',
			'cursor' => 'CURSOR',
			'database' => 'DATABASE',
			'databases' => 'DATABASES',
			'day_hour' => 'DAY_HOUR',
			'day_microsecond' => 'DAY_MICROSECOND',
			'day_minute' => 'DAY_MINUTE',
			'day_second' => 'DAY_SECOND',
			'dec' => 'DEC',
			'decimal' => 'DECIMAL',
			'declare' => 'DECLARE',
			'default' => 'DEFAULT',
			'delayed' => 'DELAYED',
			'delete' => 'DELETE',
			'desc' => 'DESC',
			'describe' => 'DESCRIBE',
			'deterministic' => 'DETERMINISTIC',
			'distinct' => 'DISTINCT',
			'distinctrow' => 'DISTINCTROW',
			'div' => 'DIV',
			'double' => 'DOUBLE',
			'drop' => 'DROP',
			'dual' => 'DUAL',
			'each' => 'EACH',
			'else' => 'ELSE',
			'elseif' => 'ELSEIF',
			'enclosed' => 'ENCLOSED',
			'end' => 'END',
			'epoch' => 'EPOCH',
			'escaped' => 'ESCAPED',
			'exists' => 'EXISTS',
			'exit' => 'EXIT',
			'explain' => 'EXPLAIN',
			'false' => 'FALSE',
			'fetch' => 'FETCH',
			'float' => 'FLOAT',
			'float4' => 'FLOAT4',
			'float8' => 'FLOAT8',
			'for' => 'FOR',
			'force' => 'FORCE',
			'foreign' => 'FOREIGN',
			'from' => 'FROM',
			'fulltext' => 'FULLTEXT',
			'grant' => 'GRANT',
			'group' => 'GROUP',
			'having' => 'HAVING',
			'high_priority' => 'HIGH_PRIORITY',
			'hour_microsecond' => 'HOUR_MICROSECOND',
			'hour_minute' => 'HOUR_MINUTE',
			'hour_second' => 'HOUR_SECOND',
			'if' => 'IF',
			'ignore' => 'IGNORE',
			'in' => 'IN',
			'index' => 'INDEX',
			'infile' => 'INFILE',
			'inner' => 'INNER',
			'inout' => 'INOUT',
			'insensitive' => 'INSENSITIVE',
			'insert' => 'INSERT',
			'int' => 'INT',
			'int1' => 'INT1',
			'int2' => 'INT2',
			'int3' => 'INT3',
			'int4' => 'INT4',
			'int8' => 'INT8',
			'integer' => 'INTEGER',
			'interval' => 'INTERVAL',
			'into' => 'INTO',
			'is' => 'IS',
			'iterate' => 'ITERATE',
			'join' => 'JOIN',
			'key' => 'KEY',
			'keys' => 'KEYS',
			'kill' => 'KILL',
			'leading' => 'LEADING',
			'leave' => 'LEAVE',
			'left' => 'LEFT',
			'like' => 'LIKE',
			'ilike' => 'ILIKE',
			'limit' => 'LIMIT',
			'lines' => 'LINES',
			'load' => 'LOAD',
			'localtime' => 'LOCALTIME',
			'localtimestamp' => 'LOCALTIMESTAMP',
			'lock' => 'LOCK',
			'long' => 'LONG',
			'longblob' => 'LONGBLOB',
			'longtext' => 'LONGTEXT',
			'loop' => 'LOOP',
			'low_priority' => 'LOW_PRIORITY',
			'match' => 'MATCH',
			'mediumblob' => 'MEDIUMBLOB',
			'mediumint' => 'MEDIUMINT',
			'mediumtext' => 'MEDIUMTEXT',
			'middleint' => 'MIDDLEINT',
			'minute_microsecond' => 'MINUTE_MICROSECOND',
			'minute_second' => 'MINUTE_SECOND',
			'mod' => 'MOD',
			'modifies' => 'MODIFIES',
			'natural' => 'NATURAL',
			'not' => 'NOT',
			'no_write_to_binlog' => 'NO_WRITE_TO_BINLOG',
			'null' => 'NULL',
			'numeric' => 'NUMERIC',
			'on' => 'ON',
			'optimize' => 'OPTIMIZE',
			'option' => 'OPTION',
			'optionally' => 'OPTIONALLY',
			'or' => 'OR',
			'order' => 'ORDER',
			'out' => 'OUT',
			'outer' => 'OUTER',
			'outfile' => 'OUTFILE',
			'precision' => 'PRECISION',
			'primary' => 'PRIMARY',
			'procedure' => 'PROCEDURE',
			'purge' => 'PURGE',
			'read' => 'READ',
			'reads' => 'READS',
			'real' => 'REAL',
			'references' => 'REFERENCES',
			'regexp' => 'REGEXP',
			'release' => 'RELEASE',
			'rename' => 'RENAME',
			'repeat' => 'REPEAT',
			'replace' => 'REPLACE',
			'require' => 'REQUIRE',
			'restrict' => 'RESTRICT',
			'return' => 'RETURN',
			'revoke' => 'REVOKE',
			'right' => 'RIGHT',
			'rlike' => 'RLIKE',
			'schema' => 'SCHEMA',
			'schemas' => 'SCHEMAS',
			'second_microsecond' => 'SECOND_MICROSECOND',
			'select' => 'SELECT',
			'sensitive' => 'SENSITIVE',
			'separator' => 'SEPARATOR',
			'set' => 'SET',
			'show' => 'SHOW',
			'smallint' => 'SMALLINT',
			'soname' => 'SONAME',
			'spatial' => 'SPATIAL',
			'specific' => 'SPECIFIC',
			'sql' => 'SQL',
			'sqlexception' => 'SQLEXCEPTION',
			'sqlstate' => 'SQLSTATE',
			'sqlwarning' => 'SQLWARNING',
			'sql_big_result' => 'SQL_BIG_RESULT',
			'sql_calc_found_rows' => 'SQL_CALC_FOUND_ROWS',
			'sql_small_result' => 'SQL_SMALL_RESULT',
			'ssl' => 'SSL',
			'starting' => 'STARTING',
			'straight_join' => 'STRAIGHT_JOIN',
			'table' => 'TABLE',
			'terminated' => 'TERMINATED',
			'then' => 'THEN',
			'tinyblob' => 'TINYBLOB',
			'tinyint' => 'TINYINT',
			'tinytext' => 'TINYTEXT',
			'to' => 'TO',
			'trailing' => 'TRAILING',
			'trigger' => 'TRIGGER',
			'true' => 'TRUE',
			'undo' => 'UNDO',
			'union' => 'UNION',
			'unique' => 'UNIQUE',
			'unlock' => 'UNLOCK',
			'unsigned' => 'UNSIGNED',
			'update' => 'UPDATE',
			'usage' => 'USAGE',
			'use' => 'USE',
			'using' => 'USING',
			'utc_date' => 'UTC_DATE',
			'utc_time' => 'UTC_TIME',
			'utc_timestamp' => 'UTC_TIMESTAMP',
			'values' => 'VALUES',
			'varbinary' => 'VARBINARY',
			'varchar' => 'VARCHAR',
			'varcharacter' => 'VARCHARACTER',
			'varying' => 'VARYING',
			'when' => 'WHEN',
			'where' => 'WHERE',
			'while' => 'WHILE',
			'with' => 'WITH',
			'write' => 'WRITE',
			'xor' => 'XOR',
			'year_month' => 'YEAR_MONTH',
			'zerofill' => 'ZEROFILL'
		];
		
	}
	
	// This exception is thrown if someone tries to modify a locked instance of Database_Query_Abstract
	class ImmutableQueryException extends Exception {
		
		public function __construct($message = '', $code = 0) {
			parent::__construct(
				(empty($message))?
					'The query is immutable since it\'s result is already loaded.' :
					$message
			, $code);
		}
		
	}
