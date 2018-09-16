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
			'ENUM' => self::TYPE_ENUM,
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
				'SELECT
					a.attname AS column_name,
					pg_get_expr(ad.adbin, ad.adrelid) AS column_default,
					CASE
						WHEN a.attnotnull OR t.typtype = \'d\' AND t.typnotnull THEN \'NO\'
						ELSE \'YES\'
					END AS is_nullable,
					CASE
						WHEN t.typtype = \'e\' THEN \'ENUM\'
						WHEN t.typtype = \'d\' THEN
						CASE
							WHEN bt.typelem <> 0::oid AND bt.typlen = -1 THEN \'ARRAY\'
							WHEN nbt.nspname = \'pg_catalog\' THEN format_type(t.typbasetype, NULL)
							ELSE \'USER-DEFINED\'
						END
						ELSE
						CASE
							WHEN t.typelem <> 0::oid AND t.typlen = -1 THEN \'ARRAY\'
							WHEN nt.nspname = \'pg_catalog\' THEN format_type(a.atttypid, NULL)
							ELSE \'USER-DEFINED\'
						END
					END AS data_type,
					CASE
						WHEN t.typtype = \'e\' THEN
							(SELECT string_agg(e.enumlabel, \',\' ORDER BY e.enumsortorder)
								FROM pg_catalog.pg_enum e
								WHERE e.enumtypid = t.oid)
						ELSE NULL
					END AS options,
					information_schema._pg_char_max_length(information_schema._pg_truetypid(a.*, t.*), information_schema._pg_truetypmod(a.*, t.*))
						AS character_maximum_length,
					information_schema._pg_numeric_precision(information_schema._pg_truetypid(a.*, t.*), information_schema._pg_truetypmod(a.*, t.*))
						AS numeric_precision
				FROM pg_attribute a
				LEFT JOIN pg_attrdef ad ON a.attrelid = ad.adrelid AND a.attnum = ad.adnum
				JOIN pg_class c ON a.attrelid = c.oid
				JOIN (pg_type t
				JOIN pg_namespace nt ON t.typnamespace = nt.oid) ON a.atttypid = t.oid
				LEFT JOIN (pg_type bt
				JOIN pg_namespace nbt ON bt.typnamespace = nbt.oid) ON t.typtype = \'d\' AND t.typbasetype = bt.oid
				WHERE
					NOT pg_is_other_temp_schema(c.relnamespace) AND
					a.attnum > 0 AND
					NOT a.attisdropped AND
					(c.relkind = ANY (ARRAY[\'r\', \'v\', \'f\'])) AND
					(pg_has_role(c.relowner, \'USAGE\') OR
						has_column_privilege(c.oid, a.attnum, \'SELECT, INSERT, UPDATE, REFERENCES\')) AND
					c.relname = ?',
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
					'defaultOnUpdate' => null,
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
				
				if ($column['options'] !== null) {
					$field['options'] = explode(',', $column['options']);
				}
				
				$fields[$column['column_name']] = $field;
			}
			
			return $fields;
		}
		
	}
