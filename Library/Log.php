<?php
	
	/*
	 * Log
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Log {
		
		protected static $_level = 2;
		protected static $_handleErrors = false;
		
		protected static $_flush = false;
		protected static $_path;
		
		protected static $_colorize = false;
		protected static $_format = "%s (%s) %s\n";
		protected static $_dateFormat = 'Y-m-d H:i:s';
		
		protected static $_messages = [];
		protected static $_timers = [];
		
		const DEBUG = 1;
		const INFO = 2;
		const NOTICE = 3;
		const WARNING = 4;
		const ERROR = 5;
		const EXCEPTION = 6;
		const FATAL = 7;
		
		const COLOR_RESET = "\e[0m";
		const COLOR_BLUE = "\e[0;34m";
		const COLOR_RED = "\e[0;31m";
		const COLOR_YELLOW = "\e[1;33m";
		const COLOR_LIGHT_GREEN = "\e[1;32m";
		const COLOR_LIGHT_GRAY = "\e[0;37m";
		const COLOR_LIGHT_RED = "\e[1;31m";
		
		protected static $_levels = [
			self::DEBUG => 'Debug',
			self::INFO => 'Info',
			self::NOTICE => 'Notice',
			self::WARNING => 'Warning',
			self::ERROR => 'Error',
			self::EXCEPTION => 'Exception',
			self::FATAL => 'Fatal'
		];
		
		protected static $_colors = [
			self::DEBUG => self::COLOR_BLUE,
			self::INFO => self::COLOR_LIGHT_GREEN,
			self::NOTICE => self::COLOR_YELLOW,
			self::WARNING => self::COLOR_LIGHT_RED,
			self::ERROR => self::COLOR_RED,
			self::EXCEPTION => self::COLOR_RED,
			self::FATAL => self::COLOR_RED
		];
		
		public static function getLevel() {
			return self::$_level;
		}
		
		public static function setLevel($level) {
			self::$_level = $level;
		}
		
		public static function setFlush($flush) {
			self::$_flush = (bool) $flush;
		}
		
		public static function setPath($path) {
			self::$_path = $path;
		}
		
		public static function colorize($colorize) {
			self::$_colorize = (bool) $colorize;
		}
		
		public static function setFormat($format) {
			self::$_format = $format;
		}
		
		public static function setDateFormat($format) {
			self::$_dateFormat = $format;
		}
		
		public static function enableErrorHandler() {
			error_reporting(-1);
			
			set_error_handler(['Log', 'handleError']);
			set_exception_handler(['Log', 'handleException']);
			
			self::$_handleErrors = true;
		}
		
		public static function disableErrorHandler() {
			if (!self::$_handleErrors) {
				return;
			}
			
			restore_error_handler();
			restore_exception_handler();
			
			self::$_handleErrors = false;
		}
		
		public static function backtrace() {
			if (self::DEBUG < self::$_level) {
				return;
			}
			
			static::debug(
				'Called from ' .
				self::_formatBacktrace(
					array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1),
					true
				)
			);
		}
		
		public static function debug($message) {
			if (self::DEBUG < self::$_level) return;
			self::$_messages[] = [time(), self::DEBUG, $message];
			if (self::$_flush) static::write();
		}
		
		public static function info($message) {
			if (self::INFO < self::$_level) return;
			self::$_messages[] = [time(), self::INFO, $message];
			if (self::$_flush) static::write();
		}
		
		// TODO: deprecate
		public static function warn($message) {
			if (self::WARNING < self::$_level) return;
			self::$_messages[] = [time(), self::WARNING, $message];
			if (self::$_flush) static::write();
		}
		
		public static function warning($message) {
			if (self::WARNING < self::$_level) return;
			self::$_messages[] = [time(), self::WARNING, $message];
			if (self::$_flush) static::write();
		}
		
		public static function error($message) {
			if (self::ERROR < self::$_level) return;
			self::$_messages[] = [time(), self::ERROR, $message];
			if (self::$_flush) static::write();
		}
		
		public static function handleError($type, $message, $file = null, $line = null) {
			switch ($type) {
				case E_PARSE:
					$level = self::FATAL;
					break;
				case E_WARNING:
				case E_CORE_WARNING:
				case E_COMPILE_WARNING:
				case E_USER_WARNING:
				case E_STRICT:
					$level = self::WARNING;
					break;
				case E_NOTICE:
				case E_USER_NOTICE:
					$level = self::NOTICE;
					break;
				case E_ERROR:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
				case E_RECOVERABLE_ERROR:
				default:
					$level = self::ERROR;
					break;
			}
			
			if ($level < self::$_level) {
				return false;
			}
			
			self::$_messages[] = [
				time(),
				$level,
				$message,
				$file,
				$line,
				array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 2)
			];
			
			return false;
		}
		
		public static function exception(Throwable $exception) {
			self::$_messages[] = [
				time(),
				($exception instanceof Error) ? self::ERROR : self::EXCEPTION,
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine(),
				$exception->getTrace()
			];
		}
		
		public static function handleException(Throwable $exception) {
			self::exception($exception);
			
			// PHP normaly exists with exit code 255 for uncaught exceptions,
			// simulate this here (via libphutil)
			exit(255);
		}
		
		public static function startTimer($name) {
			if (!isset(self::$_timers[$name])) {
				self::$_timers[$name] = ['start' => microtime(true), 'time' => 0];
			} else {
				self::$_timers[$name]['start'] = microtime(true);
			}
			
			return true;
		}
		
		public static function stopTimer($name) {
			if (!isset(self::$_timers[$name]['start'])) {
				return false;
			}
			
			self::$_timers[$name]['time'] += (microtime(true) - self::$_timers[$name]['start']);
		}
		
		public static function getTimer($name) {
			if (!isset(self::$_timers[$name]['time'])) {
				return false;
			}
			
			return self::$_timers[$name]['time'];
		}
		
		// TODO: syslog?
		public static function write() {
			if (empty(self::$_path)) {
				return;
			}
			
			$log = '';
			
			foreach (self::$_messages as $message) {
				if (!is_string($message[2])) {
					if (
						is_object($message[2]) and
						!method_exists($message[2], '__debugInfo')
					) {
						$message[2] = (string) new ReflectionObject($message[2]);
					} elseif (is_array($message[2])) {
						$message[2] = var_export($message[2], true);
					} elseif ($message[2] === true) {
						$message[2] = 'true';
					} elseif ($message[2] === false) {
						$message[2] = 'false';
					} else {
						$message[2] = (string) $message[2];
					}
				}
				
				if (isset($message[3])) {
					$message[2] .= "\nin " . $message[3];
					
					if (isset($message[4])) {
						$message[2] .= ':' . $message[4];
					}
					
					if (isset($message[5])) {
						$message[2] .= "\n" . self::_formatBacktrace($message[5]);
					}
				}
				
				$date = date(self::$_dateFormat, $message[0]);
				$formattedMessage = implode("\n\t", explode("\n", $message[2]));
								
				if (!self::$_colorize) {
					$log .= sprintf(
						self::$_format,
						$date,
						self::$_levels[$message[1]],
						$formattedMessage
					);
					continue;
				}
				
				$log .= sprintf(
					self::$_format,
					self::COLOR_YELLOW .
					$date .
					self::COLOR_RESET,
					self::$_colors[$message[1]] .
					self::$_levels[$message[1]] .
					self::COLOR_RESET,
					$formattedMessage
				);
			}
			
			if (file_put_contents(self::$_path, $log, FILE_APPEND) === false) {
				trigger_error('Failed writing log to ' . self::$_path, E_USER_ERROR);
				return;
			}
			
			self::$_messages = [];
		}
		
		public static function shutdown() {
			if (self::$_handleErrors) {
				$lastError = error_get_last();
				
				if ($lastError !== null) {
					switch ($lastError['type']) {
						case E_ERROR:
						case E_CORE_ERROR:
						case E_COMPILE_ERROR:
						case E_PARSE:
							self::$_messages[] = [
								time(),
								self::FATAL,
								$lastError['message'],
								$lastError['file'],
								$lastError['line']
							];
							break;
					}
				}
			}
			
			if (empty(self::$_messages)) {
				return;
			}
			
			static::write();
		}
		
		private static function _formatBacktrace($trace, $skipFirst = false) {
			$message = '';
			
			foreach ($trace as $i => $step) {
				if ($i !== 0 or !$skipFirst) {
					$message .= '⤷ ';
				}
				
				if (isset($step['class'])) {
					$message .= $step['class'] . $step['type'];
				}
				
				$message .= $step['function'];
				
				if (isset($step['file'])) {
					$message .= ' in ' . $step['file'] . ':' . $step['line'];
				}
				
				$message .= "\n";
			}
			
			if ($message === '') {
				return $message;
			}
			
			return substr($message, 0, -1);
		}
		
	}
	
	register_shutdown_function(['Log', 'shutdown']);
