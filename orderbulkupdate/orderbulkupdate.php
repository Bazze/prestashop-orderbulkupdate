<?php

if ( !defined( '_PS_VERSION_' ) )
  exit;

class orderbulkupdate extends Module {

	private $message;
	// 1 = success, 0 = error
	private $message_type;

	public function __construct() {
		$this->name = 'orderbulkupdate';
		$this->tab = 'administration';
		$this->version = 1.0;
		$this->author = 'SN Solutions';
		$this->need_instance = 0;
		$this->module_key = 'f991d24ad28a036844b7955af7151796';
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall this module and delete its settings?');

		parent::__construct();

		$this->displayName = $this->l('Order bulk update');
		$this->description = $this->l('Perform bulk updates on your orders.');
    }

	public function install() {
		return (parent::install() && $this->installTab());
	}

	public function uninstall() {
		return (parent::uninstall() && $this->uninstallTab());
	}

	private function installTab() {
		@copy(_PS_MODULE_DIR_.$this->name.'/logo.gif', _PS_IMG_DIR_.'t/AdminOrderBulkUpdate.gif');
	  	$tab = new Tab();
		$langs = Language::getLanguages();
		foreach ($langs as $lang) {
			$tab->name[$lang['id_lang']] = 'Bulk update';
		}
		$tab->class_name = 'AdminOrderBulkUpdate';
		$tab->module = $this->name;
		$tab->id_parent = Tab::getIdFromClassName('AdminOrders');
		if (!$tab->save()) {
			return false;
		}
		return true;
	}

	private function uninstallTab() {
		$idTab = Tab::getIdFromClassName('AdminOrderBulkUpdate');
		if ($idTab != 0) {
			$tab = new Tab($idTab);
			$tab->delete();
			return true;
		}
		return false;
	}

}
?>
