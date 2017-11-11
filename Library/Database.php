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
		'String'
	);
	
	abstract class Database {
		
		protected $_pdo;
		
		private $_statementCache;
		private $_transactionLevel = 0;
		
		public static $Instance;
		
		const DATABASE_DRIVER = '';
		const DATABASE_CHARSET = '';
		const DATABASE_DEFAULT_CHARSET = '';
		
		const TYPE_STRING = 'string';
		
		const TYPE_INTEGER = 'integer';
		const TYPE_FLOAT = 'float';
		const TYPE_DECIMAL = 'decimal';
		
		const TYPE_TIMESTAMP = 'timestamp';
		const TYPE_DATETIME = 'datetime';
		const TYPE_DATE = 'date';
		const TYPE_TIME = 'time';
		
		const TYPE_BINARY = 'binary';
		const TYPE_ENUM = 'enum';
		
		const TYPE_BOOLEAN = 'boolean';
		
		protected static $_reverseTypes = [];
		
		public function __construct($host, $user = '', $password = '', $database = null, $port = null, $persistent = false, $charset = null) {
			try {
				$this->_pdo = new PDO(
					static::DATABASE_DRIVER . ':' .
						'host=' . $host . ';' .
						(($port !== null)? ('port=' . $port . ';') : '') .
						(($database !== null)? ('dbname=' . $database . ';') : '') .
						sprintf(static::DATABASE_CHARSET, ($charset !== null)? $charset : static::DATABASE_DEFAULT_CHARSET),
					$user,
					$password,
					array(
						PDO::ATTR_PERSISTENT => $persistent,
						
						// TODO: ERRMODE_EXCEPTION ?
						PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
						PDO::ATTR_CASE => PDO::CASE_NATURAL,
						PDO::ATTR_EMULATE_PREPARES => false
					)
				);
			} catch (Exception $e) {
				throw new DatabaseConnectionException($e->getMessage());
			}
			
			// TODO: PDO::ATTR_EMULATE_PREPARES
		}
		
		// TODO: check $this->_transactionLevel > 0 on __destruct
		
		public static function init($host, $user = '', $password = '', $database = null, $port = null, $charset = null) {
			self::$Instance = new static($host, $user, $password, $database, $port);
		}
		
		public function isConnected() {
			return $this->_pdo instanceOf PDO;
		}
		
		public function beginTransaction() {
			if ($this->_transactionLevel < 1 and !$this->_pdo->beginTransaction()) {
				return false;
			}
			
			$this->_transactionLevel ++;
			
			return true;
		}
		
		public function commit() {
			if ($this->_transactionLevel < 1) {
				return false;
			}
			
			if ($this->_transactionLevel === 1 and !$this->_pdo->commit()) {
				return false;
			}
			
			$this->_transactionLevel --;
			
			return true;
		}
		
		public function rollBack() {
			if ($this->_transactionLevel < 1) {
				return false;
			}
			
			if (!$this->_pdo->rollBack()) {
				return false;
			}
			
			$this->_transactionLevel = 0;
			
			return true;
			
		}
		
		public function query($query, array $data = []) {
			if (empty($data)) {
				$statement = $this->_pdo->query($query);
			} else {
				if (!isset($this->_statementCache[$query])) {
					$this->_statementCache[$query] =
						$statement =
						$this->_pdo->prepare($query);
				} else {
					$statement = $this->_statementCache[$query];
				}
			}
			
			if ($statement === false) {
				unset($this->_statementCache[$query]);
				
				if ($this->_transactionLevel > 0) {
					$this->rollBack();
				}
				
				throw static::_translateStatementError(
					$this->_pdo->errorInfo(),
					static::_debugFormat($query, $data)
				);
			}
			
			if (!empty($data)) {
				$statement->execute($data);
			}
			
			if ($statement->errorCode() !== PDO::ERR_NONE) {
				if ($this->_transactionLevel > 0) {
					$this->rollBack();
				}
				
				throw static::_translateStatementError(
					$statement->errorInfo(),
					static::_debugFormat($query, $data)
				);
			}
			
			// TODO: this needs rework
			Log::debug(static::_debugFormat($query, $data));
			
			return $statement;
		}
		
		protected static function _translateStatementError(array $error, $query) {
			return new DatabaseStatementException($error[2], 0, $query);
		}
		
		protected static function _debugFormat($query, array $data) {
			// TODO: replace NULL values with "= NULL" instead of "= ''"
			return vsprintf(
				str_replace(
					'?',
					Database_Query::VALUE_QUOTE .
						'%s' . Database_Query::VALUE_QUOTE,
					$query
				),
				$data
			);
		}
		
		public function getInsertId(PDOStatement $statement, $name = null) {
			$id = $this->_pdo->lastInsertId($name);
			
			return $id;
		}
		
		public function escapeString($string) {
			return mb_substr($this->quote($string), 1, -1);
		}
		
		public function quote($string) {
			return $this->_pdo->quote($string);
		}
		
		abstract public function listDatabases();
		
		abstract public function listTables($database = null);
		
		abstract public function listFields($table);
		
		abstract public function getFields($table);
		
		// abstract public static function parseColumnDefinition(array $columns);
		
		public static function typify($value, array $field) {
			if ($value instanceOf Database_Query) {
				return $value;
			}
			
			// TODO: add DateTimeImmutable
			if ($value instanceOf DateTime) {
				switch ($field['mapped_type']) {
					case Database::TYPE_DATETIME:
					case Database::TYPE_DATE:
					case Database::TYPE_TIME:
						return $value->format(DATE_ISO8601);
						break;
				}
			}
			
			if (is_object($value)) {
				return (string) $value;
			}
			
			if ($value === null and $field['null']) {
				return null;
			}
			
			switch ($field['mapped_type']) {
				case Database::TYPE_BOOLEAN:
					return (boolean) $value;
					break;
				case DATABASE::TYPE_ENUM:
					if (empty($field['options'])) {
						return $value;
					}
					
					if (in_array($value, $field['options'])) {
						return $value;
					}
					
					if (isset($field['default'])) {
						return $field['default'];
					}
					
					if ($field['null']) {
						return null;
					}
					
					break;
			}
			
			if ($value === false and $field['null']) {
				return null;
			}
			
			switch ($field['mapped_type']) {
				case Database::TYPE_STRING:
					return (string) $value;
					break;
				case Database::TYPE_FLOAT:
				case Database::TYPE_DECIMAL:
					if ($value === '') {
						return null;
					}
					
					if (is_string($value)) {
						$value = str_to_decimal($value);
					}
					
					return (float) $value;
					break;
				case Database::TYPE_INTEGER:
					if ($value === '') {
						return null;
					}
					
					return (int) $value;
					break;
				case Database::TYPE_DATETIME:
				case Database::TYPE_DATE:
				case Database::TYPE_TIME:
					if ((bool) filter_var($value, FILTER_VALIDATE_INT) and $value > 0) {
						return date(DATE_ISO8601, $value);
					}
					
					break;
			}
			
			return $value;
		}
		
		protected function _listColumn($data) {
			if (empty($data)) {
				return false;
			}
			
			$result = array();
			
			foreach ($data as $row) {
				$result[] = current($row);
			}
			
			return $result;
		}
		
	}
	
	class DatabaseException extends RuntimeException {}
		
	class DatabaseConnectionException extends DatabaseException {
		
		public function __construct($message = '', $code = 0) {
			// TODO: filter message?
			parent::__construct($message, $code);
			
			// Do some bad tricks to hide the stacktrace which could reveal database passwords and the like
			$property = new ReflectionProperty('Exception', 'trace');
			$property->setAccessible(true);
			$property->setValue($this, []);
			$property->setAccessible(false);
		}
		
	}
	
	class DatabaseStatementException extends DatabaseException {
		
		public function __construct($message = '', $code = 0, $query = '') {
			parent::__construct(
				$message . ((!empty($query))? (' (' . $query . ')') : ''),
				$code
			);
		}
		
	}
	
	class DatabaseStatementSyntaxException extends DatabaseStatementException {}
	
	class DuplicateKeyException extends DatabaseStatementException {}
	
	class InvalidDataException extends DatabaseStatementException {}
	
	class InvalidForeignKeyException extends DatabaseStatementException {}
