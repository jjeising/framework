<?php
	
	/*
	 * Cache Adpater APC
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Database',
		'Database/Query/PostgreSQL'
	);
	
	class Database_PostgreSQL extends Database {
		
		const DATABASE_DRIVER = 'pgsql';
		
		// This only supports PostgreSQL 9.1 and higher
		const DATABASE_CHARSET = 'client_encoding=%s;';
		const DATABASE_DEFAULT_CHARSET = 'UTF8';
		
		const ERROR_DUPLICATE_KEY = '23505';
		const ERROR_FOREIGN_KEY_VIOLATION = '23503';
		const ERROR_SYNTAX = '42601';
		
		const ERROR_INVALID_DATA_PREFIX = '22';
		
		protected static $_reverseTypes = [
			'boolean' => self::TYPE_BOOLEAN,
			'character' => self::TYPE_STRING,
			'character varying' => self::TYPE_STRING,
			'text' => self::TYPE_STRING,
			'integer' => self::TYPE_INTEGER,
			'bigint' => self::TYPE_INTEGER,
			'real' => self::TYPE_FLOAT,
			'double precision' => self::TYPE_FLOAT,
			'float' => self::TYPE_FLOAT,
			'decimal' => self::TYPE_DECIMAL,
			'timestamp' => self::TYPE_DATETIME,
			'timestamp without time zone' => self::TYPE_DATETIME,
			'timestamp with time zone' => self::TYPE_DATETIME,
			'date' => self::TYPE_DATE,
			'time' => self::TYPE_TIME,
			'binary' => self::TYPE_BINARY,
			'enum' => self::TYPE_ENUM,
			'ltree' => self::TYPE_STRING,
			'xml' => self::TYPE_STRING,
			'json' => self::TYPE_STRING,
			'USER-DEFINED' => self::TYPE_STRING
		];
		
		protected static function _translateStatementError(array $error, $query) {
			if (mb_substr($error[0], 0, 2) === self::ERROR_INVALID_DATA_PREFIX) {
				return new InvalidDataException($error[2]);
			}
			
			switch ($error[0]) {
				case self::ERROR_DUPLICATE_KEY:
					return new DuplicateKeyException($error[2]);
					break;
				case self::ERROR_FOREIGN_KEY_VIOLATION:
					return new InvalidForeignKeyException($error[2]);
					break;
				case self::ERROR_SYNTAX:
					return new DatabaseStatementSyntaxException(
						$error[2], 0, $query
					);
					break;
				default:
					return parent::_translateStatementError($error, $query);
					break;
			}
		}
		
		public function getInsertId(PDOStatement $statement, $name = null, $primaryKey = null) {
			if ($primaryKey === null) {
				$id = $this->_pdo->lastInsertId($name);
				return $id;
			}
			
			// Fetch single row
			$data = $statement->fetch();
			
			if (isset($data[$primaryKey])) {
				return $data[$primaryKey];
			}
			
			$id = $this->_pdo->lastInsertId($name . '_' . $primaryKey . '_seq');
			
			return $id;
		}
		
		public function listDatabases() {
			$statement = $this->query('SELECT datname FROM pg_database');
			return $this->_listColumn($statement->fetchAll(PDO::FETCH_ASSOC));
		}
		
		public function listTables($database = null) {
			if ($database !== null) {
				throw new NotImplementedException(
					'Database_PostgreSQL doesn\'t support listing tables of a specific database'
				);
			}
			
			$statement = $this->query(
				'SELECT table_name
				FROM information_schema.tables
				WHERE table_schema = \'public\''
			);
			
			return $this->_listColumn($statement->fetchAll(PDO::FETCH_ASSOC));
		}
		
		public function listFields($table) {
			if (empty($table)) {
				return false;
			}
			
			$statement = $this->query(
				'SELECT column_name
				FROM information_schema.columns
				WHERE table_name = ?',
				[$table]
			);
			
			return $this->_listColumn($statement->fetchAll(PDO::FETCH_ASSOC));
		}
		
		public function getFields($table) {
			$statement = $this->query(
				'SELECT column_name, data_type, is_nullable,
					column_default, character_maximum_length, numeric_precision
				FROM information_schema.columns
				WHERE table_name = ?',
				[$table]
			);
			
			return static::parseColumnDefinition($statement->fetchAll(PDO::FETCH_ASSOC));
		}
		
		
		public static function parseColumnDefinition(array $columns) {
			$fields = [];
			
			foreach ($columns as $column) {
				$field = [
					'type' => null,
					'length' => null,
					'null' => ($column['is_nullable'] === 'YES'),
					'default' => null,
					'options' => []
				];
				
				$field['type'] = $column['data_type'];
				
				if (!isset(static::$_reverseTypes[$column['data_type']])) {
					throw new DatabaseException(
						'unknown column type ' . $column['data_type']
					);
				}
				
				$field['mapped_type'] =
					static::$_reverseTypes[$column['data_type']];
				
				if ($column['character_maximum_length'] !== null) {
					$field['length'] = $column['character_maximum_length'];
				} elseif ($column['numeric_precision'] !== null) {
					$field['length'] = $column['numeric_precision'];
				}
				
				if ($field['type'] === self::TYPE_BOOLEAN) {
					if ($column['column_default'] === 'true') {
						$field['default'] = true;
					} elseif ($column['column_default'] === 'false') {
						$field['default'] = false;
					}
				} elseif (
					mb_strpos($column['column_default'], 'nextval') === false
				) {
					$field['default'] = $column['column_default'];
				}
				
				$fields[$column['column_name']] = $field;
			}
			
			return $fields;
		}
		
	}
