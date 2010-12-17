<?php defined('SYSPATH') or die('No direct script access.');

class Model_Multi_Auth_User extends ORM {

	// Relationships
	protected $_has_many = array(
		'user_tokens' => array('model' => 'user_token'),
		'permissions'       => array('model' => 'permission', 'through' => 'permissions_users'),
	);

	// Validation rules
	protected $_rules = array(
		'username' => array(
			'not_empty'  => NULL,
			'min_length' => array(4),
			'max_length' => array(32),
			'regex'      => array('/^[-\pL\pN_.]++$/uD'),
		),
		'password' => array(
			'not_empty'  => NULL,
			'min_length' => array(5),
			'max_length' => array(42),
		),
		'password_confirm' => array(
			'matches'    => array('password'),
		),
		'email' => array(
			'not_empty'  => NULL,
			'min_length' => array(4),
			'max_length' => array(127),
			'email'      => NULL,
		),
		$this->site_field => array(
			'not_empty'  => NULL,
		),
	);

	// Validation callbacks
	protected $_callbacks = array(
		'username' => array('username_available'),
		'email' => array('email_available'),
		// TODO: Add callback to make sure value passed for site id is valid
	);

	// Field labels
	protected $_labels = array(
		'username'         => 'username',
		'email'            => 'email address',
		'password'         => 'password',
		'password_confirm' => 'password confirmation',
		$this->site_field  => 'site id field'
	);

	// Columns to ignore
	protected $_ignored_columns = array('password_confirm',$this->site_field);

	// Site ID Field
	protected $site_field = Kohana::config('multi_auth.site_field');

	/**
	 * Validates login information from an array, and optionally redirects
	 * after a successful login.
	 *
	 * @param   array    values to check
	 * @param   string   URI or URL to redirect to
	 * @return  boolean
	 */
	public function login(array & $array, $redirect = FALSE)
	{
		$array = Validate::factory($array)
			->label('username', $this->_labels['username'])
			->label('password', $this->_labels['password'])
			->filter(TRUE, 'trim')
			->rules('username', $this->_rules['username'])
			->rules('password', $this->_rules['password']);

		// Get the remember login option
		$remember = isset($array['remember']);

		// Login starts out invalid
		$status = FALSE;

		if ($array->check())
		{
			// Attempt to load the user
			$this->where('username', '=', $array['username'])
			     ->where($this->site_field, '=', $array[$this->site_field])
			     ->find();

			if ($this->loaded() AND Multi_Auth::instance()->login($array[$this->site_field], $this, $array['password'], $remember))
			{
				if (is_string($redirect))
				{
					// Redirect after a successful login
					Request::instance()->redirect($redirect);
				}

				// Login is successful
				$status = TRUE;
			}
			else
			{
				$array->error('username', 'invalid');
			}
		}

		return $status;
	}

	/**
	 * Validates an array for a matching password and password_confirm field,
	 * and optionally redirects after a successful save.
	 *
	 * @param   array    values to check
	 * @param   string   URI or URL to redirect to
	 * @return  boolean
	 */
	public function change_password(array & $array, $redirect = FALSE)
	{
		$array = Validate::factory($array)
			->label('password', $this->_labels['password'])
			->label('password_confirm', $this->_labels['password_confirm'])
			->filter(TRUE, 'trim')
			->rules('password', $this->_rules['password'])
			->rules('password_confirm', $this->_rules['password_confirm']);

		if ($status = $array->check())
		{
			// Change the password
			$this->password = $array['password'];

			if ($status = $this->save() AND is_string($redirect))
			{
				// Redirect to the success page
				Request::instance()->redirect($redirect);
			}
		}

		return $status;
	}

	/**
	 * Complete the login for a user by incrementing the logins and saving login timestamp
	 *
	 * @return void
	 */
	public function complete_login()
	{
		if ( ! $this->_loaded)
		{
			// nothing to do
			return;
		}

		// Update the number of logins
		$this->logins += 1;

		// Set the last login date
		$this->last_login = time();

		// Save the user
		$this->save();
	}

	/**
	 * Does the reverse of unique_key_exists() by triggering error if username exists.
	 * Validation callback.
	 *
	 * @param   Validate  Validate object
	 * @param   string    field name
	 * @return  void
	 */
	public function username_available(Validate $array, $field)
	{
		if ($this->unique_key_exists($array[$this->site_field], $array[$field]))
		{
			$array->error($field, 'username_available', array($array[$field]));
		}
	}

	/**
	 * Does the reverse of unique_key_exists() by triggering error if email exists.
	 * Validation callback.
	 *
	 * @param   Validate  Validate object
	 * @param   string    field name
	 * @return  void
	 */
	public function email_available(Validate $array, $field)
	{
		if ($this->unique_key_exists($array[$this->site_field], $array[$field]))
		{
			$array->error($field, 'email_available', array($array[$field]));
		}
	}

	/**
	 * Tests if a unique key value exists in the database.
	 *
	 * @param   mixed    the value to test
	 * @return  boolean
	 */
	public function unique_key_exists($site, $value)
	{
		return (bool) DB::select(array('COUNT("*")', 'total_count'))
			->from($this->_table_name)
			->where($this->unique_key($value), '=', $value)
			->where($this->_primary_key, '!=', $this->pk())
			->where($this->site_field, '=', $site)
			->execute($this->_db)
			->get('total_count');
	}

	/**
	 * Allows a model use both email and username as unique identifiers for login
	 *
	 * @param   string  unique value
	 * @return  string  field name
	 */
	public function unique_key($value)
	{
		return Validate::email($value) ? 'email' : 'username';
	}

	/**
	 * Saves the current object. Will hash password if it was changed.
	 *
	 * @return  ORM
	 */
	public function save()
	{
		if (array_key_exists('password', $this->_changed))
		{
			$this->_object['password'] = Multi_Auth::instance()->hash_password($this->_object['password']);
		}

		return parent::save();
	}
}