<?php
	
	/*
	 * Router
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	class Router {
		
		private static $_routes = [];
		private static $_properties = [];
		private static $_reverse = [
			'byController' => [],
			'allControllers' => [
				'byAction' => [],
				'allActions' => []
			]
		];
		
		const CONTROLLER = ':controller';
		const ACTION = ':action';
		
		public static function addRoutes($routes) {
			if (!is_array($routes)) {
				$routes = include $routes;
			}
			
			self::$_routes = $routes;
			
			foreach ($routes as $route => $params) {
				$properties = [
					'greedy' => (substr($route, -1, 1) === '/'),
					'segments' => [],
					'segmentCount' => 0,
					'minSegments' => 0,
					'map' => []
				];
				
				if ($route !== '/' and $route !== '') {
					$properties['segments'] = explode('/', $route);
				}
				
				$properties['segmentCount'] =
					$properties['minSegments'] =
					count($properties['segments']);
				
				foreach ($properties['segments'] as $index => $segment) {
					if ($segment === '') {
						continue;
					}
					
					if ($segment[0] === ':') {
						$properties['map'][] = '%' . ($index + 1) . '$s';
						
						if (substr($segment, -1, 1) === '?') {
							$properties['minSegments']--;
						}
						
						continue;
					}
					
					$properties['minSegments'] = $properties['segmentCount'];
					$properties['map'][] = $segment;
				}
				
				self::$_properties[$route] = $properties;
				
				if (isset(self::$_routes[$route][0]) and self::$_routes[$route][0] !== null) {
					$reverse = &self::$_reverse['byController'][self::$_routes[$route][0]];
					
					if ($reverse === null) {
						$reverse = ['byAction' => [], 'allActions' => []];
					}
				} else {
					$reverse = &self::$_reverse['allControllers'];
				}
				
				if (isset(self::$_routes[$route][1]) and self::$_routes[$route][1] !== null) {
					if (!isset($reverse['byAction'][self::$_routes[$route][1]])) {
						$reverse['byAction'][self::$_routes[$route][1]] = $route;
					}
				} else {
					$reverse['allActions'][] = $route;
				}
				
				unset($reverse);
			}
		}
		
		public static function map(Request $request) {
			$segments = $request->getSegments();
			
			$format = self::_extractFormat($segments);
			$route = self::_findRoute($request, $segments);
			
			if ($route === false) {
				return false;
			}
			
			$target = [
				'controller' => null,
				'action' => null,
				'arguments' => []
			];
			
			if (isset(self::$_routes[$route][2])) {
				$target['arguments'] = self::$_routes[$route][2];
			}
			
			$segmentCount = -1;
			
			foreach (self::$_properties[$route]['segments'] as $index => $segment) {
				$segmentCount++;
				
				if ($segment[0] !== ':') {
					continue;
				}
				
				$isOptional = (substr($segment, -1, 1) === '?');
				
				if ($isOptional) {
					$segment = substr($segment, 0, -1);
				}
				
				switch ($segment) {
					case self::CONTROLLER:
						$target['controller'] = $segments[$index];
						break;
					case self::ACTION:
						$target['action'] = $segments[$index];
						break;
					default:
						if (!isset($segments[$index])) {
							assert($isOptional);
							$target['arguments'][substr($segment, 1)] = null;
						} else {
							$target['arguments'][substr($segment, 1)] = $segments[$index];
						}
						break;
				}
			}
			
			if (isset(self::$_routes[$route][0]) and self::$_routes[$route][0] !== null) {
				$target['controller'] = self::$_routes[$route][0];
			}
			
			if (isset(self::$_routes[$route][1]) and self::$_routes[$route][1] !== null) {
				$target['action'] = self::$_routes[$route][1];
			}
			
			if ($target['controller'] === null or $target['action'] === null) {
				return false;
			}
			
			$target['arguments']['controller'] = $target['controller'];
			$target['arguments']['action'] = $target['action'];
			
			if ($format !== false) {
				$target['arguments']['format'] = $format;
			}
			
			$segmentCount++;
			
			if (self::$_properties[$route]['greedy'] and
				$segmentCount < count($segments)) {
				$target['arguments'] = array_merge($target['arguments'], array_slice($segments, $segmentCount));
			}
			
			return $target;
		}
		
		private static function _findRoute(Request $request, array $segments) {
			$URI = implode('/', $segments);
			$segmentCount = ($URI !== '')? count($segments) : 0;
			
			foreach (self::$_routes as $route => $params) {
				if (($segmentCount > self::$_properties[$route]['segmentCount'] and
					!self::$_properties[$route]['greedy']) or
					$segmentCount < self::$_properties[$route]['minSegments']) {
					continue;
				}
				
				$mapped = vsprintf(
					implode('/', array_slice(
						self::$_properties[$route]['map'],
						0,
						$segmentCount
					)),
					$segments
				);
				
				if ($URI === $mapped) {
					return $route;
				}
				
				if (!self::$_properties[$route]['greedy']) {
					continue;
				}
				
				if (substr_compare($URI, $mapped, 0) > 0) {
					return $route;
				}
			}
			
			return false;
		}
		
		private static function _extractFormat(array &$segments) {
			if (empty($segments)) {
				return false;
			}
			
			$lastElement = count($segments) - 1;
			$lastSegment = $segments[$lastElement];
			
			$pathInfo = pathinfo($lastSegment);
			
			if (!isset($pathInfo['extension'])) {
				return false;
			}
			
			$segments[$lastElement] = $pathInfo['filename'];
			
			return $pathInfo['extension'];
		}
		
		// TODO: rawurlencode? see phutil_escape_uri_path_component
		public static function reverse($controller, $action, array $arguments = array()) {
			if (isset(self::$_reverse['byController'][$controller])) {
				$reverse = &self::$_reverse['byController'][$controller];
			} else {
				$reverse = &self::$_reverse['allControllers'];
			}
			
			if (isset($reverse['byAction'][$action])) {
				$route = $reverse['byAction'][$action];
			} else {
				$route = end($reverse['allActions']);
			}
			
			if ($route === false) {
				// TODO: better result?
				return '';
			}
			
			$URI = [];
			
			foreach (self::$_properties[$route]['segments'] as $index => $segment) {
				if ($segment[0] !== ':') {
					$URI[] = $segment;
					continue;
				}
				
				$isOptional = (substr($segment, -1, 1) === '?');
				
				if ($isOptional) {
					$segment = substr($segment, 0, -1);
				}
				
				switch ($segment) {
					case self::CONTROLLER:
						$URI[] = $controller;
						break;
					case self::ACTION:
						$URI[] = $action;
						break;
					default:
						$segment = substr($segment, 1);
						
						if (isset($arguments[$segment])) {
							$URI[] = $arguments[$segment];
						} elseif (!$isOptional) {
							$URI[] = 'undefined';
						}
						
						break;
				}
			}
			
			$URI = implode('/', $URI);
			
			if (isset($arguments[0])) {
				for ($i = 0; isset($arguments[$i]); $i ++) {
					if (empty($arguments[$i])) {
						continue;
					}
					
					switch ($arguments[$i][0]) {
						case '.':
						case '#':
						case '?':
							$URI .= $arguments[$i];
							break;
						default:
							$URI .= '/' . $arguments[$i];
							break;
					}
				}
			}
			
			return $URI;
		}
		
	}
