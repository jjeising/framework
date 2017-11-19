<?php
	
	/*
	 * Model Association HasMany
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires('Model/Association');
	
	// TODO: add :through, see rails has-many-through-association
	class Model_Association_HasMany extends Model_Association {
		
		public function init(array $parent, Model_Resource $parentResource = null) {
			$associatedEntries = new Model_Resource($this->_to);
			
			foreach (array_combine(
				$this->_properties['primary_key'],
				$this->_properties['foreign_key']
			) as $primaryKey => $foreignKey) {
				$associatedEntries->where([
					$foreignKey => $parent[$primaryKey]
				]);
			}
			
			// $includes = $parentResource->getIncludes($this->_name);
			/*
				TODO: inform $associatedEntries about included entries
				      (it has to sort them), also inform about further includes
				      via ->includes(…).
			
				      Only do this in case ->_properties['primary_key'] is
				      of count(1).
			*/
			
			self::_applyProperties($associatedEntries, $this->_properties);
			
			return $associatedEntries;
		}
		
		public function save(Model $parent, array $entries, array $properties) {
			$base = clone $this->_to;
			
			foreach (array_combine(
				$this->_properties[static::JOINABLE_LEFT_KEY],
				$this->_properties[static::JOINABLE_RIGHT_KEY]
			) as $leftKey => $rightKey) {
				$base[$rightKey] = $parent[$leftKey];
			}
			
			foreach ($entries as $entry) {
				if (!is_array($entry)) {
					continue;
				}
				
				$new = clone $base;
				$new->set(array_diff_key(
					$entry,
					array_fill_keys(
						$this->_properties[static::JOINABLE_RIGHT_KEY],
						true
					)
				));
				
				if (!empty($entry['_destroy'])) {
					$new->destroy();
					continue;
				}
				
				$new->save();
			}
		}
	}
