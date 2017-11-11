<?php
	
	/*
	 * View
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class View {
		
		protected $_templatePath;
		protected $_data;
		
		protected $_layouts = [];
		
		protected $_contents = [];
		protected $_contentsFor = [];
		protected $_resources = [];
		
		protected $_context = false;
		
		protected $Request;
		
		protected static $_templateDirectory = null;
		// TODO: move default layout and default view to Controller?
		protected static $_defaultLayouts = ['default'];
		
		const DEFAULT_EXTENSION = '.html.php';
		
		// TODO: "checked" was missing, check for HTML5 reference
		private static $_booleanAttributes = [
			'checked' => true,
			'selected' => true,
			'disabled' => true,
			'readonly' => true,
			'multiple' => true,
			'autofocus' => true
		];
		
		public function __construct($templatePath, array $data = [], Request $request = null) {
			$this->_templatePath = $templatePath;
			$this->_data = $data;
			
			if ($request !== null) {
				$this->Request = $request;
			}
		}
		
		public function getTemplatePath() {
			return $this->_templatePath;
		}
		
		public function setTemplatePath($templatePath) {
			$this->_templatePath = $templatePath;
		}
		
		public static function setDefaultLayout($layout) {
			self::$_defaultLayouts[] = $layout;
		}
		
		public static function resetDefaultLayout() {
			if (empty(self::$_defaultLayouts)) {
				return false;
			}
			
			return array_pop(self::$_defaultLayouts);
		}
		
		public static function setTemplateDirectory($directory) {
			self::$_templateDirectory = $directory;
		}
		
		public function render($__templatePath = null, array $__data = [], $__ignoreDefaultLayout = false) {
			if ($__templatePath === null) {
				$__templatePath = $this->_templatePath;
			}
			
			$__extension = pathinfo($__templatePath, PATHINFO_EXTENSION);
			$__templatePath = ((self::$_templateDirectory === null)?
				APPLICATION . 'View/' :
				self::$_templateDirectory) . $__templatePath;
			
			if (empty($__extension)) {
				$__templatePath .= self::DEFAULT_EXTENSION;
			} else {
				$__templatePath .= '.php';
			}
			
			if (!empty(self::$_defaultLayouts) and
				empty($__extension) and
				!$this->_context and
				!$__ignoreDefaultLayout) {
				$this->_layouts[] = end(self::$_defaultLayouts);
			} else {
				$this->_layouts[] = null;
			}
			
			$__context = $this->_context;
			$this->_context = true;
			
			extract(array_merge($this->_data, $__data), EXTR_PREFIX_SAME, '_');
			
			ob_start();
			
			try {
				$return = include($__templatePath);
			} catch (Exception $exception) {
				ob_end_clean();
				throw $exception;
			}
			
			$content = ob_get_clean();
			
			if ($return !== 1 and !empty($__extension)) {
				switch ($__extension) {
					case 'json':
						$content = json_encode($return);
						break;
				}
			}
			
			$layout = array_pop($this->_layouts);
			
			if (
				$layout !== null and
				$layout !== false and
				$layout !== $__templatePath
			) {
				$this->_contents[] = $content;
				$content = $this->render($layout);
				
				array_pop($this->_contents);
			}
			
			$this->_context = $__context;
			
			return $content;
		}
		
		protected function layout($file) {
			// FIXME: this seems wrong
			if (empty($this->_layouts)) {
				return false;
			}
			
			// TODO: set $file = null when file === false or remove $layout === null
			
			$this->_layouts[count($this->_layouts) - 1] = $file;
			
			return true;
		}
		
		protected function contentFor($name, $content) {
			$this->_contentsFor[$name] = $content;
		}
		
		protected function content($name = null, $default = null) {
			$content = null;
			
			if ($name === null and !empty($this->_contents)) {
				$content = end($this->_contents);
			} elseif (isset($this->_contentsFor[$name])) {
				if ($this->_contentsFor[$name] instanceOf Closure) {
					$this->_contentsFor[$name]->bindTo($this, 'View');
					$content = $this->_contentsFor[$name]();
				} else {
					$content = $this->_contentsFor[$name];
				}
			}
			
			if ($content === null and $default !== null) {
				return $default;
			}
			
			return $content;
		}
		
		protected function addResource($type, $resource) {
			if (!isset($this->_resources[$type])) {
				$this->_resources[$type] = [];
			}
			
			$this->_resources[$type][$resource] = true;
		}
		
		protected function removeResource($type, $resource) {
			if (!isset($this->_resources[$type][$resource])) {
				return;
			}
			
			unset($this->_resources[$type][$resource]);
		}
		
		protected function getResources($type) {
			if (!isset($this->_resources[$type])) {
				return [];
			}
			
			return array_keys($this->_resources[$type]);
		}
		
		protected function title($title = null, $append = true) {
			if ($title !== null) {
				$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8', false);
				
				if ($append and isset($this->_contentsFor['title'])) {
					$this->_contentsFor['title'] .= $title;
				} else {
					$this->_contentsFor['title'] = $title;
				}
			}
			
			if (!isset($this->_contentsFor['title'])) {
				return '';
			}
			
			return $this->_contentsFor['title'];
		}
		
		/*
			linkTo($controller, $action, $arguments[, $arguments[, $arguments[, …]]], $text[, $title][, $attributes]);
		*/
		protected function linkTo($controller, $action, ...$args) {
			$arguments = [];
			
			while (key($args) !== null) {
				$arg = current($args);
				
				if ($arg instanceOf Model) {
					$arguments += $arg->toArray();
				} elseif (is_array($arg)) {
					$arguments += $arg;
				} else {
					break;
				}
				
				next($args);
			}
			
			$text = current($args);
			$title = next($args);
			$attributes = [];
			
			if ($title === false) {
				$title = $text;
			} elseif (is_array($title)) {
				$attributes = $title;
				$title = $text;
			} elseif (is_array(next($args))) {
				$attributes = current($args);
			}
			
			$attributes['href'] = $this->Request->getRootURL() .
				Router::reverse($controller, $action, $arguments);
			
			if (!isset($attributes['title'])) {
				$attributes['title'] = $title;
			}
			
			return self::tag('a', $attributes, $text);
		}
		
		protected function a($uri, $text, $title = null, $attributes = []) {
			if (is_array($title)) {
				$attributes = $title;
				$title = null;
			}
			
			$attributes['href'] = $uri;
			
			if ($title !== null) {
				$attributes['title'] = $title;
			} else {
				$attributes['title'] = $text;
			}
			
			return self::tag('a', $attributes, $text);
		}
		
		public static function tag($name, array $attributes, $content = null) {
			$result = '';
			
			foreach ($attributes as $key => $value) {
				if ($value === null) {
					continue;
				}
				
				if (is_int($key)) {
					$result .= $value . ' ';
					continue;
				}
				
				if (isset(self::$_booleanAttributes[$key])) {
					if ($value === false) {
						continue;
					}
					
					$value = $key;
				} elseif ($value === true) {
					$value = 'true';
				} elseif ($value === false) {
					$value = 'false';
				} else {
					$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				}
				
				$result .= $key . '=' . '"' . $value . '" ';
			}
			
			return '<' . $name .
				((!empty($result))? ' ' . substr($result, 0, -1) : '') .
				(($content === false)?
					'>' : (($content === null)?
						' />' : ('>' . $content . '</' . $name . '>')));
		}
		
	}
	
	function h($string) {
		return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
	}
