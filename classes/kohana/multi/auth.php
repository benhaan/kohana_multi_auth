<?php defined('SYSPATH') or die('No direct script access.');

abstract class Kohana_Multi_Auth {

	// Auth instances
	protected static $instance;


	/**
	 * Singleton pattern
	 *
	 * @return Multi_Auth
	 */
	public static function instance()
	{
		if ( ! isset(Multi_Auth::$instance))
		{
			// Load the configuration for this type
			$config = Kohana::config('multi_auth');

			if ( ! $type = $config->get('driver'))
			{
				$type = 'ORM';
			}

			// Set the session class name
			$class = 'Multi_Auth_'.ucfirst($type);

			Multi_Auth::$instance = new $class($config);
		}

		return Multi_Auth::$instance;
	}

	/**
	 * Create an instance of Multi_Auth
	 *
	 * @return Multi_Auth
	 */
	public static function factory($config = array())
	{
		return new Multi_Auth($config);
	}

	protected $session;

	protected $config;

	/**
	 * Loads Session and configuration options
	 *
	 * @return void
	 */
	public function __construct($config = array())
	{
		// Save the config in the object

		if (empty($config) === TRUE)
		{
			$config = Kohana::config('multi_auth');
		}

		$this->config = $config;

		$this->session = Session::instance();
	}

	abstract protected function _login($site, $username, $password, $remember);

	abstract public function password($site, $username);

	abstract public function check_password($password);

	/**
	 * Gets the currently logged in user from the session.
	 * Returns FALSE if no user is currently logged in.
	 *
	 * @return  mixed
	 */
	public function get_user($default = FALSE)
	{
		return $this->session->get($this->config['session_key'], $default);
	}

	/**
	 * Attempt to log in a user by using an ORM object and plain-text password.
	 *
	 * @param   string   username to log in
	 * @param   string   password to check against
	 * @param   boolean  enable autologin
	 * @return  boolean
	 */
	public function login($site, $username, $password, $remember = FALSE)
	{
		if (empty($password))
		{
			return FALSE;
		}

		if (is_string($password))
		{
			// Create a hashed password
			$password = $this->hash_password($password);
		}

		return $this->_login($site, $username, $password, $remember);
	}

	/**
	 * Log out a user by removing the related session variables.
	 *
	 * @param   boolean  completely destroy the session
	 * @param   boolean  remove all tokens for user
	 * @return  boolean
	 */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		if ($destroy === TRUE)
		{
			// Destroy the session completely
			$this->session->destroy();
		}
		else
		{
			// Remove the user from the session
			$this->session->delete($this->config['session_key']);

			// Regenerate session_id
			$this->session->regenerate();
		}

		// Double check
		return ! $this->logged_in();
	}

	/**
	 * Check if there is an active session. Optionally allows checking for a
	 * specific role.
	 *
	 * @param   string   role name
	 * @return  mixed
	 */
	public function logged_in($role = NULL)
	{
		return ($this->get_user() !== FALSE);
	}

	/**
	 * Creates a hashed hmac password from a plaintext password
	 *
	 * @param   string  plaintext password
	 */
	public function hash_password($password)
	{
		return $this->hash($password);
	}

	/**
	 * Perform a hash, using the configured method.
	 *
	 * @param   string  string to hash
	 * @return  string
	 */
	public function hash($str)
	{
		// TODO: switch back to hmac when option is in place for users to update their current passwords
		//return hash_hmac($this->_config['hash_method'], $str, $this->_config['key']);

		return hash($this->config['hash_method'],$str);
	}

	/**
	 * Regenerate the session id and saves the user info into the session
	 *
	 * @param object user ORM object
	 */
	protected function complete_login($user)
	{
		// Regenerate session_id
		$this->session->regenerate();

		// Store username in session
		$this->session->set($this->config['session_key'], $user);

		return TRUE;
	}

} // End Multi_Auth