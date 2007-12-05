<?php

require_once 'Admin/dataobjects/AdminUser.php';
require_once 'Admin/dataobjects/AdminUserWrapper.php';
require_once 'Admin/exceptions/AdminException.php';
require_once 'Site/SiteSessionModule.php';
require_once 'Site/SiteCookieModule.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatDate.php';
require_once 'Swat/SwatForm.php';
require_once 'Swat/SwatString.php';

/**
 * Web application module for sessions
 *
 * @package   Admin
 * @copyright 2005-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class AdminSessionModule extends SiteSessionModule
{
	// {{{ protected properties

	/**
	 * @var array
	 * @see AdminSessionModule::registerLoginCallback()
	 */
	protected $login_callbacks = array();

	// }}}
	// {{{ public function __construct()

	/**
	 * Creates a admin session module
	 *
	 * @param SiteApplication $app the application this module belongs to.
	 *
	 * @throws AdminException if there is no cookie module loaded the session
	 *                         module throws an exception.
	 *
	 * @throws AdminException if there is no database module loaded the session
	 *                         module throws an exception.
	 */
	public function __construct(SiteApplication $app)
	{
		$this->registerRegenerateIdCallback(
			array($this, 'regenerateAuthenticationToken'));

		$this->registerLoginCallback(
			array($this, 'regenerateAuthenticationToken'));

		parent::__construct($app);
	}

	// }}}
	// {{{ public function init()

	public function init()
	{
		parent::init();

		// always activate the session for an admin
		if (!$this->isActive())
			$this->activate();

		if (!isset($this->user)) {
			$this->user = null;
			$this->history = array();
		} elseif ($this->user !== null) {
			$this->app->cookie->setCookie('email', $this->getEmailAddress(),
				strtotime('+1 day'), '/');
		}
	}

	// }}}
	// {{{ public function depends()

	/**
	 * Gets the module features this module depends on
	 *
	 * The admin session module depends on the SiteCookieModule and
	 * SiteDatabaseModule features.
	 *
	 * @return array an array of {@link SiteModuleDependency} objects defining
	 *                        the features this module depends on.
	 */
	public function depends()
	{
		$depends = parent::depends();
		$depends[] = new SiteApplicationModuleDependency('SiteCookieModule');
		$depends[] = new SiteApplicationModuleDependency('SiteDatabaseModule');
		return $depends;
	}

	// }}}
	// {{{ public function login()

	/**
	 * Logs an admin user into an admin
	 *
	 * @param string $email
	 * @param string $password
	 *
	 * @return boolean true if the admin user was logged in is successfully and
	 *                  false if the admin user could not log in.
	 */
	public function login($email, $password)
	{
		$this->logout(); // make sure user is logged out before logging in

		$sql = sprintf('select password_salt from AdminUser where email = %s
			and enabled = %s',
			$this->app->db->quote($email, 'text'),
			$this->app->db->quote(true, 'boolean'));

		$salt = SwatDB::queryOne($this->app->db, $sql, 'text');

		if ($salt !== null) {
			$md5_password = md5($password.$salt);

			$sql = sprintf('select *
				from AdminUser
				where email = %s and password = %s and enabled = %s',
				$this->app->db->quote($email, 'text'),
				$this->app->db->quote($md5_password, 'text'),
				$this->app->db->quote(true, 'boolean'));

			$this->user = SwatDB::query($this->app->db, $sql,
				'AdminUserWrapper')->getFirst();

			if ($this->user !== null &&
				$this->user->isAuthenticated($this->app)) {
				$this->insertUserHistory($this->user);
				$this->runLoginCallbacks();
			}
		}

		return $this->isLoggedIn();
	}

	// }}}
	// {{{ public function logout()

	/**
	 * Logs the current admin user out of an admin
	 */
	public function logout()
	{
		$this->clear();
		$this->user = null;
		unset($this->_authentication_token);
	}

	// }}}
	// {{{ public function isLoggedIn()

	/**
	 * Gets whether or not an admin user is logged in
	 *
	 * @return boolean true if an admin user is logged in and false if an
	 *                  admin user is not logged in.
	 */
	public function isLoggedIn()
	{
		return (isset($this->user) && $this->user !== null &&
			$this->user->isAuthenticated($this->app));
	}

	// }}}
	// {{{ public function getUserID()

	/**
	 * Gets the current admin user's user identifier
	 *
	 * @return string the current admin user's user identifier, or null if an
	 *                 admin user is not logged in.
	 */
	public function getUserID()
	{
		if (!$this->isLoggedIn())
			return null;

		return $this->user->id;
	}

	// }}}
	// {{{ public function getEmailAddress()

	/**
	 * Gets the current admin user's email address
	 *
	 * @return string the current admin user's email address, or null if an
	 *                 admin user is not logged in.
	 */
	public function getEmailAddress()
	{
		if (!$this->isLoggedIn())
			return null;

		return $this->user->email;
	}

	// }}}
	// {{{ public function getName()

	/**
	 * Gets the current admin user's name
	 *
	 * @return string the current admin user's name, or null if an admin user
	 *                 is not logged in.
	 */
	public function getName()
	{
		if (!$this->isLoggedIn())
			return null;

		return $this->user->name;
	}

	// }}}
	// {{{ public function registerLoginCallback()

	/**
	 * Registers a callback function that is executed when a successful session
	 * login is performed
	 *
	 * @param callback $callback the callback to call when a successful login
	 *                            is performed.
	 * @param array $parameters optional. The paramaters to pass to the
	 *                           callback.
	 *
	 * @throws AdminException when the <i>$callback</i> parameter is not
	 *                        callable.
	 * @throws AdminException when the <i>$parameters</i> parameter is not an
	 *                        array.
	 */
	public function registerLoginCallback($callback, $parameters = array())
	{
		if (!is_callable($callback))
			throw new AdminException('Cannot register invalid callback.');

		if (!is_array($parameters))
			throw new AdminException('Callback parameters must be specified '.
				'in an array.');

		$this->login_callbacks[] = array(
			'callback' => $callback,
			'parameters' => $parameters
		);
	}

	// }}}
	// {{{ protected function regenerateAuthenticationToken()

	protected function regenerateAuthenticationToken()
	{
		$this->_authentication_token = SwatString::hash(mt_rand());
		SwatForm::setAuthenticationToken($this->_authentication_token);
	}

	// }}}
	// {{{ protected function startSession()

	protected function startSession()
	{
		parent::startSession();
		if (isset($this->user) && $this->user instanceof AdminUser)
			$this->user->setDatabase($this->app->database->getConnection());

		if (isset($this->_authentication_token))
			SwatForm::setAuthenticationToken($this->_authentication_token);
	}

	// }}}
	// {{{ protected function insertUserHistory()

	/**
	 * Inserts login history for a user
	 *
	 * @param AdminUser $user_id the user to record login history for.
	 */
	protected function insertUserHistory(AdminUser $user)
	{
		$login_agent = (isset($_SERVER['HTTP_USER_AGENT'])) ?
			$_SERVER['HTTP_USER_AGENT'] : null;

		$remote_ip = (isset($_SERVER['REMOTE_ADDR'])) ?
			$_SERVER['REMOTE_ADDR'] : null;

		$login_date = new SwatDate();
		$login_date->toUTC();

		$fields = array('integer:usernum','date:login_date',
			'text:login_agent', 'text:remote_ip');

		$values = array(
			'usernum'     => $user->id,
			'login_date'  => $login_date->getDate(),
			'login_agent' => $login_agent,
			'remote_ip'   => $remote_ip,
		);

		SwatDB::insertRow($this->app->db, 'AdminUserHistory', $fields,
			$values);
	}

	// }}}
	// {{{ protected function runLoginCallbacks()

	protected function runLoginCallbacks()
	{
		foreach ($this->login_callbacks as $login_callback) {
			$callback = $login_callback['callback'];
			$parameters = $login_callback['parameters'];
			call_user_func_array($callback, $parameters);
		}
	}

	// }}}
}

?>
