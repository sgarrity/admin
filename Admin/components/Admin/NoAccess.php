<?php

require_once 'Admin/pages/AdminPage.php';
require_once 'Swat/SwatMessageDisplay.php';

/**
 * Administrator No Access page
 *
 * @package Admin
 * @copyright silverorange 2004
 */
class AdminNoAccess extends AdminPage
{
	// {{{ private properties

	private $message = null;

	// }}}
	// {{{ public function setMessage()

	public function setMessage($msg)
	{
		$this->message = $msg;
	}

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		// do not link this sub-component's navbar to the base component
		$this->navbar->replaceEntryByPosition(1,
			new SwatNavBarEntry(Admin::_('No Access')));
	}

	// }}}

	// build phase
	// {{{ protected function display()

	protected function display()
	{
		$message_display = new SwatMessageDisplay();
		$message_display->title = 'No Access';

		if ($this->message !== null)
			$message_display->add($this->message); 

		$message_display->display();
	}

	// }}}
}

?>
