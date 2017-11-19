<?php
	
	/*
	 * Form Fields
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires(
		'Request',
		'View'
	);
	
	class Form_Fields {
		
		/*protected $_parent;*/
		
		protected $_attributes;
		protected $_token;
		
		protected $_disabled = false;
		
		protected $Request;
		
		protected $_requestValues;
		
		public function __construct(/*Form $parent, */Request $request, array $attributes, $token) {
			/*$this->_parent = $parent;*/
			$this->Request = $request;
			
			if (isset($attributes['disabled'])) {
				$this->_disabled = (bool) $attributes['disabled'];
				unset($attributes['disabled']);
			}
			
			$this->_attributes = $attributes;
			
			if (!isset($this->_attributes['method'])) {
				$this->_attributes['method'] = Request::METHOD_POST;
			}
			
			$this->_token = $token;
			
			$this->_requestValues = [
				Request::METHOD_GET => (!empty($_GET))?
					Request::parseStringFlat($request->getQueryString()) : [],
				// TODO: validate contentType!
				Request::METHOD_POST => (!empty($_POST))?
					Request::parseStringFlat(Request::getBody()) : [],
			];
		}
		
		public function __toString() {
			$attributes = $this->_attributes;
			$attributes['method'] = strtolower($attributes['method']);
			
			$content = View::tag('form', $attributes, false);
			
			if ($this->_token === null) {
				return $content;
			}
			
			$content .= '<div>' . View::tag('input', array(
				'type' => 'hidden',
				// TODO: get this via parent?
				'name' => Form::TOKEN_FIELD_NAME,
				'value' => $this->_token
			)) . '</div>';
			
			return $content;
		}
		
		public function input($name, $label, $value = '', array $attributes = [], $useRequestValue = Form::REQUEST_METHOD_FORM) {
			if (!isset($attributes['type'])) {
				$attributes['type'] = 'text';
			}
			
			$attributes['name'] = $name;
			
			if (!isset($attributes['value'])) {
				$attributes['value'] = $value;
			}
			
			$this->_setRequestValue($attributes['value'], $name, $useRequestValue);
			$this->_addAttributes(
				$attributes,
				($attributes['type'] !== 'hidden')? 'text' : null
			);
			
			if (empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name'],
					(empty($attributes['readonly']))?
						null : [(string) $attributes['value']],
					($attributes['type'] !== 'password')?
						Form::FIELD_TEXT :
						Form::FIELD_PASSWORD
				);
			}
			
			return $this->_label($attributes['id'], $label) .
				View::tag('input', $attributes);
		}
		
		public function hidden(
			$name,
			$value = '',
			array $attributes = [],
			$useRequestValue = false,
			$registerType = Form::FIELD_TEXT
		) {
			$attributes['type'] = 'hidden';
			$attributes['name'] = $name;
			$attributes['value'] = $value;
			
			$this->_setRequestValue(
				$attributes['value'],
				$name,
				$useRequestValue
			);
			
			if (empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name'],
					(empty($attributes['readonly']))?
						null : [(string) $attributes['value']],
					$registerType
				);
			}
			
			return View::tag('input', $attributes);
		}
		
		public function hiddenEncoded($name, $value = '', array $attributes = [], $useRequestValue = false) {
			return self::hidden(
				$name,
				base64_encode($value),
				$attributes,
				$useRequestValue,
				Form::FIELD_BASE64
			);
		}
		
		public function password($name, $label, array $attributes = []) {
			$attributes['type'] = 'password';
			return $this->input($name, $label, '', $attributes, false);
		}
		
		public function checkbox($name, $label, $checked = false, array $attributes = [], $useRequestValue = Form::REQUEST_METHOD_FORM) {
			$attributes['type'] = 'checkbox';
			$attributes['name'] = $name;
			
			if ($checked) {
				$attributes['checked'] = true;
			}
			
			// TODO: created hidden field and disable if readonly is set
			
			$this->_setRequestChecked($attributes, $name, $useRequestValue);
			$this->_addAttributes(
				$attributes,
				'checkbox'
			);
			
			if (empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name'],
					(isset($attributes['value']))?
						[(string) $attributes['value']] : null,
					(isset($attributes['value']))?
						Form::FIELD_TEXT : Form::FIELD_BOOL
				);
			}
			
			return View::tag('input', $attributes) .
				$this->_label($attributes['id'], $label);
		}
		
		public function radio($name, $label, $value = '', $checked = false, array $attributes = [], $useRequestValue = Form::REQUEST_METHOD_FORM) {
			$attributes['type'] = 'radio';
			$attributes['name'] = $name;
			$attributes['value'] = $value;
			
			if ($checked) {
				$attributes['checked'] = true;
			}
			
			$this->_setRequestCheckedRadio($attributes, $name, $value, $useRequestValue);
			$this->_addAttributes(
				$attributes,
				'radio',
				$attributes['value']
			);
			
			if (empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name'],
					[(string) $attributes['value']]
				);
			}
			
			return View::tag('input', $attributes) .
				$this->_label($attributes['id'], $label);
		}
		
		public function select($name, $label, array $options, $selected = null, array $attributes = [], $useRequestValue = Form::REQUEST_METHOD_FORM) {
			$attributes['name'] = $name;
			
			$this->_setRequestValue($selected, $name, $useRequestValue);
			
			$content = '';
			$values = [];
			
			foreach ($options as $value => $text) {
				if (!is_array($text)) {
					$content .= $this->_option($value, $selected, $text);
					$values[] = (string) $value;
					continue;
				}
				
				$optgroup = '';
				$groupedOptions = $text;
				$groupedLabel = $value;
				
				foreach ($groupedOptions as $value => $text) {
					$optgroup .= $this->_option($value, $selected, $text);
					$values[] = (string) $value;
				}
				
				$content .= View::tag('optgroup', ['label' => $groupedLabel], $optgroup);
			}
			
			$this->_addAttributes($attributes);
			
			if (empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name'],
					$values
				);
			}
			
			return $this->_label($attributes['id'], $label) .
				View::tag('select', $attributes, $content);
		}
		
		protected function _option($value, $selected, $label) {
			return View::tag('option', [
				'value' => htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8'),
				// FIXME: noted that this is somehow broken (propably '' == 0), maybe works like this, tests needed
				'selected' => ($selected === '')? $selected === $value : $selected == $value
			], $label);
		}
		
		public function textarea($name, $label, $value = '', array $attributes = [], $useRequestValue = Form::REQUEST_METHOD_FORM) {
			$attributes['name'] = $name;
			
			$this->_setRequestValue($value, $name, $useRequestValue);
			$this->_addAttributes($attributes);
			
			if (empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name'],
					(empty($attributes['readonly']))?
						null : [$value]
				);
			}
			
			return $this->_label($attributes['id'], $label) .
				View::tag('textarea', $attributes,  htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
		}
		
		public function submit($value, array $attributes = []) {
			$attributes['value'] = $value;
			$attributes['type'] = 'submit';
			
			if (isset($attributes['name']) and empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name'],
					[(string) $attributes['value']]
				);
			}
			
			$this->_addAttributes($attributes, 'submit');
			
			return View::tag('input', $attributes);
		}
		
		public function button($name, $label, $content = '', array $attributes = []) {
			$attributes['name'] = $name;
			
			$this->_addAttributes($attributes);
			
			if (empty($attributes['disabled'])) {
				Form::registerField(
					$this->_token,
					$attributes['name']
				);
			}
			
			return $this->_label($attributes['id'], $label) .
				View::tag('button', $attributes, $content);
		}
		
		public function register($name, array $values = null, $type = Form::FIELD_TEXT) {
			Form::registerField($this->_token, $name, $values, $type);
		}
		
		protected function _addAttributes(array &$attributes, $class = null, $idAppendix = '') {
			if ($idAppendix !== false and empty($attributes['id']) and isset($attributes['name'])) {
				$attributes['id'] = ((!empty($this->_attributes['id']))? $this->_attributes['id'] : 'form') . '-' . $attributes['name'] . ((!empty($idAppendix))? '-' . $idAppendix : '');
			}
			
			if ($class !== null) {
				if (empty($attributes['class'])) {
					$attributes['class'] = $class;
				} else {
					$attributes['class'] .= ' ' . $class;
				}
			}
			
			if ($this->_disabled and !isset($attributes['disabled'])) {
				$attributes['disabled'] = $this->_disabled;
			}
		}
		
		protected function _label($id, $label) {
			if ($label === null) {
				return '';
			}
			
			return View::tag('label', ['for' => $id], $label);
		}
		
		protected function _setRequestValue(&$value, $name, $useRequestValue = Form::REQUEST_METHOD_FORM) {
			if (($method = $this->_useRequestValue($name, $useRequestValue)) === false) {
				return;
			}
			
			if (!isset($this->_requestValues[$method][$name])) {
				return;
			}
			
			if (is_array($this->_requestValues[$method][$name])) {
				if (!empty($this->_requestValues[$method][$name])) {
					$value = array_shift($this->_requestValues[$method][$name]);
				}
			} else {
				$value = $this->_requestValues[$method][$name];
			}
		}
		
		protected function _setRequestChecked(&$attributes, $name, $useRequestValue = Form::REQUEST_METHOD_FORM) {
			if (($method = $this->_useRequestValue($name, $useRequestValue)) === false) {
				return;
			}
			
			$attributes['checked'] = isset($this->_requestValues[$method][$name]);
		}
		
		protected function _setRequestCheckedRadio(&$attributes, $name, $value, $useRequestValue = Form::REQUEST_METHOD_FORM) {
			$requestValue = null;
			$this->_setRequestValue($requestValue, $name, $useRequestValue);
			
			if ($requestValue === null) {
				return;
			}
			
			$attributes['checked'] = ($requestValue == $value);
		}
		
		/*
			Check if $name was submitted with last request, according
			to the type in $useRequestValue. Do not check for a valid
			request/token because content is properly escaped in the
			fields. This enables form resubmission by the user in case
			of a lost token.
		*/
		protected function _useRequestValue($name, $useRequestValue) {
			if ($useRequestValue === false) {
				return false;
			}
			
			if ($useRequestValue === Form::REQUEST_METHOD_FORM) {
				$useRequestValue = $this->_attributes['method'];
			}
			
			if ($useRequestValue !== $this->Request->getMethod()) {
				return false;
			}
			
			return $useRequestValue;
		}
		
	}
