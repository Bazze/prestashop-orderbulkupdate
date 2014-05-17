<?php

include(dirname(__FILE__).'/../../config/config.inc.php');

if (!Employee::checkPassword((int)Tools::getValue('id_employee'), Tools::getValue('passwd'))) {
	die(json_encode(array('error' => true)));
}

$id_supplier = pSQL(urldecode(Tools::getValue('id_supplier')));
$id_manufacturer = pSQL(urldecode(Tools::getValue('id_manufacturer')));
$id_category = (int)Tools::getValue('id_category');
$id_lang = (int)Tools::getValue('id_lang');

if (($id_supplier != 0 || $id_manufacturer != 0 || $id_category != 0) && $id_lang != 0) {
	$sql = "SELECT p.`id_product`, pl.`name` from `"._DB_PREFIX_."product` p
			LEFT JOIN `"._DB_PREFIX_."product_lang` pl
				ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = {$id_lang}
			LEFT JOIN `" . _DB_PREFIX_ . "category_product` cp
				ON cp.`id_product` = p.`id_product`
			WHERE ";
	if ($id_supplier != 0) {
		$sql .= "`id_supplier` IN ({$id_supplier})";
	}
	if ($id_category != 0) {
		$sql .= ($id_supplier != 0 ? ' OR ' : '') . " (cp.`id_category` = {$id_category} OR p.`id_category_default` = {$id_category}) ";
	}
	if ($id_manufacturer != 0) {
		$sql .= ($id_supplier != 0 || $id_category != 0 ? ' OR ' : '') . "`id_manufacturer` IN ({$id_manufacturer})";
	}
	$sql .= " GROUP by p.`id_product` ORDER by pl.`name` ASC";
	$result = Db::getInstance()->ExecuteS($sql);
	die(json_encode($result));
} else {
	die(json_encode(array()));
}