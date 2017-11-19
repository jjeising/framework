<?php
	
	/*
	 * Database Query PostgreSQL
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Database/Query/Abstract'
	);
	
	class Database_Query extends Database_Query_Abstract {
		
		const KEYWORD_QUOTE = '"';
		const VALUE_QUOTE = '\'';
		
		public function __construct($table) {
			parent::__construct($table);
			
			self::$_syntax['INSERT INTO %s () VALUES ()'] = 'INSERT INTO %s DEFAULT VALUES';
			
			self::$_syntax['%s = 0'] = '%s IS FALSE';
			self::$_syntax['%s = 1'] = '%s IS TRUE';
		}
		
		public function build($forceSanitize = false) {
			list($query, $params, $joinedFields) = parent::build($forceSanitize);
			
			switch ($this->_type) {
				case self::TYPE_INSERT:
				case self::TYPE_UPDATE:
					$query .= ' RETURNING *';
				case self::TYPE_SELECT:
					foreach ($params as $i => $param) {
						if ($param === false) {
							$params[$i] = 'FALSE';
						} elseif ($param === true) {
							$params[$i] = 'TRUE';
						}
					}
					break;
			}
			
			return [$query, $params, $joinedFields];
		}
		
	}
