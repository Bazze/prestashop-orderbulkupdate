<?php

include(dirname(__FILE__).'/../../config/config.inc.php');

if (!Employee::checkPassword((int)Tools::getValue('id_employee'), Tools::getValue('passwd'))) {
	die(json_encode(array('error' => true)));
}

$id_lang = (int)Tools::getValue('id_lang');
$start = (int)Tools::getValue('start');
$perPage = (int)Tools::getValue('perPage');

if ($id_lang != 0) {
	$sql = 'SELECT a.id_order AS id_pdf, a.date_add, (a.`total_products_wt`+a.`total_shipping`+a.`total_wrapping`-a.`total_discounts`) as total_amount, a.`id_currency`,
		CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`, curr.`format` as currency_format, curr.`sign` as currency_sign,
		osl.`name` AS `osname`,
		os.`color`,
		IF((SELECT COUNT(so.id_order) FROM `'._DB_PREFIX_.'orders` so WHERE so.id_customer = a.id_customer) > 1, 0, 1) as new,
		(SELECT COUNT(od.`id_order`) FROM `'._DB_PREFIX_.'order_detail` od WHERE od.`id_order` = a.`id_order` GROUP BY `id_order`) AS product_number FROM 
		`'._DB_PREFIX_.'orders` a
	LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = a.`id_customer`)
 	LEFT JOIN `'._DB_PREFIX_.'order_history` oh ON (oh.`id_order` = a.`id_order`)
	LEFT JOIN `'._DB_PREFIX_.'order_state` os ON (os.`id_order_state` = oh.`id_order_state`)
	LEFT JOIN `'._DB_PREFIX_.'currency` curr ON (curr.`id_currency` = a.`id_currency`)
	LEFT JOIN `'._DB_PREFIX_.'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = '.(int)($id_lang).')
	WHERE oh.`id_order_history` = (SELECT MAX(`id_order_history`) FROM `'._DB_PREFIX_.'order_history` moh WHERE moh.`id_order` = a.`id_order` GROUP BY moh.`id_order`) ORDER by a.`id_order` DESC LIMIT ' . (int)$start . ', ' . (int)($perPage+1);
	$orders = Db::getInstance()->ExecuteS($sql);
	$more = (count($orders) > $perPage);
	if ($more) {
		unset($orders[$perPage]);
	}
	$result = array(
		'orders' => $orders,
		'more' => $more
	);
	die(json_encode($result));
} else {
	die(json_encode(array('error' => true)));
}