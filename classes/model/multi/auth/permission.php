<?php defined('SYSPATH') or die('No direct script access.');

class Model_Multi_Auth_Permission extends ORM {

	// Relationships
		protected $_has_many = array('users' => array('through' => 'permissions_users'));

		// Validation rules
		protected $_rules = array(
			'name' => array(
				'not_empty'  => NULL,
				'min_length' => array(4),
				'max_length' => array(32),
			),
			'description' => array(
				'max_length' => array(255),
			),
		);
}