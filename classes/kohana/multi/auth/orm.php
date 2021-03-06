<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ORM Auth driver.
 *
 */
class Kohana_Multi_Auth_ORM extends Multi_Auth {

	/**
	 * Checks if a session is active.
	 *
	 * @param   mixed    permission name string, permission ORM object, or array with permission names
	 * @return  boolean
	 */
	public function logged_in($permission = NULL)
	{
		$status = FALSE;

		// Get the user from the session
		$user = $this->get_user();

		if (is_object($user) AND $user instanceof Model_User AND $user->loaded())
		{
			// Everything is okay so far
			$status = TRUE;

			if ( ! empty($permission))
			{
				// Multiple roles to check
				if (is_array($permission))
				{
					// Check each role
					foreach ($permission as $_permission)
					{
						if ( ! is_object($_permission))
						{
							$_permission = ORM::factory('permission', array('name' => $_permission));
						}

						// If the user doesn't have the permission
						if ( ! $user->has('permissions', $_permission))
						{
							// Set the status false and get outta here
							$status = FALSE;
							break;
						}
					}
				}
				// Single permission to check
				else
				{
					if ( ! is_object($permission))
					{
						// Load the role
						$permission = ORM::factory('permission', array('name' => $permission));
					}

					// Check that the user has the given role
					$status = $user->has('permissions', $permission);
				}
			}
		}

		return $status;
	}

	/**
	 * Logs a user in.
	 *
	 * @param   mixed    site id/key
	 * @param   string   username
	 * @param   string   password
	 * @param   boolean  enable autologin
	 * @return  boolean
	 */
	protected function _login($site, $user, $password, $remember)
	{
		$site_field = $this->config['site_field'];

		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			$user = ORM::factory('user');
			$user->where($user->unique_key($username), '=', $username)
			     ->where($site_field, '=', $site)
			     ->find();
		}

		// If the passwords match, perform a login
		if ($user->has('permissions', ORM::factory('permission', array('name' => 'login'))) AND $user->password === $password)
		{
			if ($remember === TRUE)
			{
				// Create a new autologin token
				$token = ORM::factory('user_token');

				// Set token data
				$token->user_id = $user->id;
				$token->site_id = $user->$site_field;
				$token->expires = time() + $this->config['lifetime'];
				$token->save();

				// Set the autologin cookie
				Cookie::set('authautologin', $token->token, $this->config['lifetime']);
			}

			// Finish the login
			$this->complete_login($user);

			return TRUE;
		}

		// Login failed
		return FALSE;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   mixed    site id/key
	 * @param   mixed    username string, or user ORM object
	 * @return  boolean
	 */
	public function force_login($site, $user)
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			$user = ORM::factory('user');
			$user->where($user->unique_key($username), '=', $username)
			     ->where($this->config['site_field'],'=', $site)
			     ->find();
		}

		// Mark the session as forced, to prevent users from changing account information
		$this->session->set('auth_forced', TRUE);

		// Run the standard completion
		$this->complete_login($user);
	}

	/**
	 * Logs a user in, based on the authautologin cookie.
	 *
	 * @return  mixed
	 */
	public function auto_login()
	{
		if ($token = Cookie::get('authautologin'))
		{
			// Load the token and user
			$token = ORM::factory('user_token', array('token' => $token));

			if ($token->loaded() AND $token->user->loaded())
			{
				if ($token->user_agent === sha1(Request::$user_agent))
				{
					// Save the token to create a new unique token
					$token->save();

					// Set the new token
					Cookie::set('authautologin', $token->token, $token->expires - time());

					// Complete the login with the found data
					$this->complete_login($token->user);

					// Automatic login was successful
					return $token->user;
				}

				// Token is invalid
				$token->delete();
			}
		}

		return FALSE;
	}

	/**
	 * Gets the currently logged in user from the session (with auto_login check).
	 * Returns FALSE if no user is currently logged in.
	 *
	 * @return  mixed
	 */
	public function get_user($default = FALSE)
	{
		$user = parent::get_user($default);

		if ($user === FALSE)
		{
			// check for "remembered" login
			$user = $this->auto_login();
		}

		return $user;
	}

	/**
	 * Log a user out and remove any autologin cookies.
	 *
	 * @param   boolean  completely destroy the session
	 * @param	boolean  remove all tokens for user
	 * @return  boolean
	 */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		// Set by force_login()
		$this->session->delete('auth_forced');

		if ($token = Cookie::get('authautologin'))
		{
			// Delete the autologin cookie to prevent re-login
			Cookie::delete('authautologin');

			// Clear the autologin token from the database
			$token = ORM::factory('user_token', array('token' => $token));

			if ($token->loaded() AND $logout_all)
			{
				ORM::factory('user_token')->where('user_id', '=', $token->user_id)
					->and_where('site_id', '=', $token->site_id)
					->delete_all();
			}
			elseif ($token->loaded())
			{
				$token->delete();
			}
		}

		return parent::logout($destroy);
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   mixed   site id/key
	 * @param   mixed   username string, or user ORM object
	 * @return  string
	 */
	public function password($site, $user)
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			$user = ORM::factory('user');
			$user->where($user->unique_key($username), '=', $username)
			     ->where(Kohana::config('multi_auth.site_field'),'=', $site)
			     ->find();
		}

		return $user->password;
	}

	/**
	 * Complete the login for a user by incrementing the logins and setting
	 * session data: user_id, username, roles.
	 *
	 * @param   object  user ORM object
	 * @return  void
	 */
	protected function complete_login($user)
	{
		$user->complete_login();

		return parent::complete_login($user);
	}

	/**
	 * Compare password with original (hashed). Works for current (logged in) user
	 *
	 * @param   string  $password
	 * @return  boolean
	 */
	public function check_password($password)
	{
		$user = $this->get_user();

		if ($user === FALSE)
		{
			// nothing to compare
			return FALSE;
		}

		$hash = $this->hash_password($password);

		return $hash == $user->password;
	}

} // End Multi Auth ORM