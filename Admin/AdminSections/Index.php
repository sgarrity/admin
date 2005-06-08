<?php

require_once 'Admin/AdminUI.php';
require_once 'SwatDB/SwatDB.php';
require_once 'Admin/Admin/Index.php';
require_once 'Admin/AdminTableStore.php';

/**
 * Index page for AdminSections
 * @package Admin
 * @copyright silverorange 2004
 */
class AdminSectionsIndex extends AdminIndex {

	public function init() {
		$this->ui = new AdminUI();
		$this->ui->loadFromXML('Admin/AdminSections/index.xml');
	}

	protected function getTableStore() {
		$view = $this->ui->getWidget('index_view');

		$sql = 'select sectionid, title, show 
				from adminsections 
				order by displayorder';

		$store = $this->app->db->query($sql, null, true, 'AdminTableStore');

		return $store;
	}

	public function processActions() {
		$view = $this->ui->getWidget('index_view');
		$actions = $this->ui->getWidget('index_actions');

		$num = count($view->checked_items);
		$msg = null;
		
		switch ($actions->selected->id) {
			case 'delete':
				$this->app->replacePage('AdminSections/Delete');
				$this->app->page->setItems($view->checked_items);
				break;

			case 'show':
				SwatDB::updateColumn($this->app->db, 'adminsections', 
					'boolean:show', true, 'sectionid', 
					$view->checked_items);

				$msg = new SwatMessage(sprintf(_nS("%d section has been shown.", 
					"%d sections have been shown.", $num), $num));

				break;

			case 'hide':
				SwatDB::updateColumn($this->app->db, 'adminsections', 
					'boolean:show', false, 'sectionid', 
					$view->checked_items);

				$msg = new SwatMessage(sprintf(_nS("%d section has been hidden.", 
					"%d sections have been hidden.", $num), $num));

				break;
		}
		
		if ($msg !== null)
			$this->app->addMessage($msg);
	}
}

?>
