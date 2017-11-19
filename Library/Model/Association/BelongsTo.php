<?php
	
	/*
	 * Model Association BelongsTo
	 *
	 * (c) Jannes Jeising <jannes@jeising.net>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */
	
	requires('Model/Association');
	
	class Model_Association_BelongsTo extends Model_Association implements Model_Association_Joinable {
		
		const JOINABLE_LEFT_KEY = 'foreign_key';
		const JOINABLE_RIGHT_KEY = 'primary_key';
		
		const PROPERTY_PRIMARY_KEY_SOURCE = '_to';
		
		// TODO: add 'touch' => true, touch parent when updated, implement in Association?
		// TODO: polymorphic?
	}
