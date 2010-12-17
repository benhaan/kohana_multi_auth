<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'driver'       => 'ORM',
	'hash_method'  => 'sha1',
	'hash_key'     => NULL,
	'lifetime'     => 1209600,
	'session_key'  => 'multi_auth_user',
	'site_field'   => 'site_id',
);