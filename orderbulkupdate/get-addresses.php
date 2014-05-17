<?php

include(dirname(__FILE__).'/../../config/config.inc.php');

if (!Employee::checkPassword((int)Tools::getValue('id_employee'), Tools::getValue('passwd'))) {
	die(json_encode(array('error' => true)));
}

function getAddresses($id_customer, $id_lang) {
	return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
	SELECT a.*, cl.`name` AS country, s.name AS state, s.iso_code AS state_iso
	FROM `'._DB_PREFIX_.'address` a
	LEFT JOIN `'._DB_PREFIX_.'country` c ON (a.`id_country` = c.`id_country`)
	LEFT JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country`)
	LEFT JOIN `'._DB_PREFIX_.'state` s ON (s.`id_state` = a.`id_state`)
	WHERE `id_lang` = '.(int)($id_lang).' AND `id_customer` = '.(int)($id_customer).' AND a.`deleted` = 0');
}

$id_customer = (int)Tools::getValue('id_customer');
$id_lang = (int)Tools::getValue('id_lang');

if ($id_customer != 0 && $id_lang != 0) {
	$addresses = getAddresses($id_customer,$id_lang);
	die(json_encode( (empty($addresses) ? array('id_address' => '0') : $addresses) ));
} else {
	die(json_encode(array('id_address' => '0')));
}