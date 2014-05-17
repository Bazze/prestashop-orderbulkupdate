<?php

include(dirname(__FILE__).'/../../config/config.inc.php');

if (!Employee::checkPassword((int)Tools::getValue('id_employee'), Tools::getValue('passwd'))) {
	die(json_encode(array('error' => true)));
}

$_m = Module::getInstanceByName('orderbulkupdate');

function orderbulkupdate_write_status($msg) {
	@file_put_contents(dirname(__FILE__).'/bulk-update.txt', "" . $msg . @file_get_contents(dirname(__FILE__).'/bulk-update.txt'));
}

// Apply filters and return the orders to be included in the bulk update
function orderbulkupdate_get_orders($filters, $filterValues) {
	if (is_array($filters)) {
		$select = array();
		$where = array();
		$join = array();
		$having = array();
		if (!isset($filters['AllOrders'])) {
			foreach ($filters as $filter => $value) {
				if (!isset($filterValues[$filter])) {
					// No value were entered for this filter, skip it
					continue;
				}
				$data = $filterValues[$filter];
				switch ($filter) {
					
					case 'Date':
						if ($data['type'] == 'specific') {
							$where[] = "(
								o.`date_add` > '" . pSQL($data['specific']) . " 00:00:00' AND 
								o.`date_add` < '" . pSQL($data['specific']) . " 23:59:59'
							)";
						} else if ($data['type'] == 'interval') {
							$where[] = "(
								o.`date_add` > '" . pSQL($data['from']) . ":00' AND
								o.`date_add` < '" . pSQL($data['to']) . ":00'
							)";
						}
					break;
					
					case 'OrderStates':
						$orderIDs = Db::getInstance()->ExecuteS("
							SELECT oh.`id_order` FROM `" . _DB_PREFIX_ ."order_history` AS oh 
								LEFT OUTER JOIN `" . _DB_PREFIX_ ."order_history` AS oh2
									ON (oh.`id_order` = oh2.`id_order` AND oh.`date_add` < oh2.`date_add`)
							WHERE oh2.`id_order` IS NULL AND oh.`id_order_state` IN (" . pSQL(implode(",", $data)) . ")
						");
						$ids = array();
						foreach ($orderIDs as $order) {
							$ids[] = $order['id_order'];
						}
						$where[] = "(o.`id_order` IN (" . implode(",", $ids) . "))";
					break;
					
					case 'Suppliers':
						if (isset($data['id_supplier'])) {
							if ($data['condition'] == 'non_exclusive') {
								$where[] = "(p.`id_supplier` IN (" . pSQL(implode(",", $data['id_supplier'])) . "))";
							} else if ($data['condition'] == 'exclusive') {
								$select[] = "sum( if( p.`id_supplier` IN (" . pSQL(implode(",", $data['id_supplier'])) . "), 1, 0 )) as HasSupplierLookingFor";
								$select[] = "sum( if( p.`id_supplier` IN (" . pSQL(implode(",", $data['id_supplier'])) . "), 0, 1 )) as HasOtherSuppliers";
								$having[] = "HasSupplierLookingFor > 0";
								$having[] = "HasOtherSuppliers = 0";
							}
						}
					break;
					
					case 'Manufacturers':
						if (isset($data['id_manufacturer'])) {
							if ($data['condition'] == 'non_exclusive') {
								$where[] = "(p.`id_manufacturer` IN (" . pSQL(implode(",", $data['id_manufacturer'])) . "))";
							} else if ($data['condition'] == 'exclusive') {
								$select[] = "sum( if( p.`id_manufacturer` IN (" . pSQL(implode(",", $data['id_manufacturer'])) . "), 1, 0 )) as HasManufacturerLookingFor";
								$select[] = "sum( if( p.`id_manufacturer` IN (" . pSQL(implode(",", $data['id_manufacturer'])) . "), 0, 1 )) as HasOtherManufacturers";
								$having[] = "HasManufacturerLookingFor > 0";
								$having[] = "HasOtherManufacturers = 0";
							}
						}
					break;
					
					case 'Products':
						if (isset($data['id_product'])) {
							if ($data['condition'] == 'non_exclusive') {
								$where[] = "(od.`product_id` IN (" . pSQL(implode(",", $data['id_product'])) . "))";
							} else if ($data['condition'] == 'exclusive') {
								$select[] = "sum( if( od.`product_id` IN (" . pSQL(implode(",", $data['id_product'])) . "), 1, 0 )) as HasProductsLookingFor";
								$select[] = "sum( if( od.`product_id` IN (" . pSQL(implode(",", $data['id_product'])) . "), 0, 1 )) as HasOtherProducts";
								$having[] = "HasProductsLookingFor > 0";
								$having[] = "HasOtherProducts = 0";
							}
						}
					break;
					
					case 'Carriers':
						$where[] = "(o.`id_carrier` IN (" . pSQL(implode(",", $data)) . "))";
					break;
					
					case 'Customers':
						$where[] = "(o.`id_customer` IN (" . pSQL(implode(",", $data)) . "))";
					break;
					
					case 'Countries':
						if (isset($data['countries'])) {
							if ($data['countries_address'] == 'invoice') {
								$join[] = "
									LEFT JOIN `" . _DB_PREFIX_ . "address` a
										ON a.`id_address` = o.`id_address_invoice`
								";
								$where[] = "(a.`id_country` IN (" . pSQL(implode(",", $data['countries'])) . "))";
							} else if ($data['countries_address'] == 'delivery') {
								$join[] = "
									LEFT JOIN `" . _DB_PREFIX_ . "address` a
										ON a.`id_address` = o.`id_address_delivery`
								";
								$where[] = "(a.`id_country` IN (" . pSQL(implode(",", $data['countries'])) . "))";
							} else if ($data['countries_address'] == 'both') {
								$join[] = "
									LEFT JOIN `" . _DB_PREFIX_ . "address` a
										ON a.`id_address` = o.`id_address_invoice`
									LEFT JOIN `" . _DB_PREFIX_ . "address` a2
										ON a.`id_address` = o.`id_address_delivery`
								";
								$where[] = "(a.`id_country` IN (" . pSQL(implode(",", $data['countries'])) . ") AND a2.`id_country` IN (" . pSQL(implode(",", $data['countries'])) . "))";
							} else if ($data['countries_address'] == 'either') {
								$join[] = "
									LEFT JOIN `" . _DB_PREFIX_ . "address` a
										ON a.`id_address` = o.`id_address_invoice`
									LEFT JOIN `" . _DB_PREFIX_ . "address` a2
										ON a.`id_address` = o.`id_address_delivery`
								";
								$where[] = "(a.`id_country` IN (" . pSQL(implode(",", $data['countries'])) . ") OR a2.`id_country` IN (" . pSQL(implode(",", $data['countries'])) . "))";
							}
						}
					break;
					
					case 'PaymentMethods':
						// Secure the input
						foreach ($data as $key => $value) {
							$data[$key] = pSQL($value);
						}
						$where[] = "(o.`module` = '" . implode("' OR o.`module` = '", $data) . "')";
					break;
					
					case 'OrderAmount':
						$allowed_operators = array("=", "<", "<=", ">", ">=");
						if (in_array($data['operator'], $allowed_operators)) {
							$field = ($data['tax'] == 0 ? 'total_products' : 'total_products_wt');
							$where[] = "(
								" . ($data['id_currency'] != 'any' ? "o.`id_currency` = " . (int)$data['id_currency'] . " AND" : "") . 
								" ROUND(
									(o.`{$field}`" . ($data['shipping'] == 1 ? '+o.`total_shipping`' : '') . 
								($data['wrapping'] == 1 ? '+o.`total_wrapping`' : '') . 
								($data['discount'] == 1 ? '-o.`total_discounts`' : '') . ")/o.`conversion_rate`, 2) {$data['operator']} " .
								round((double)$data['amount'], 2) . "  
							)";
						}
					break;
					
					case 'GiftWrapping':
						$where[] = "(o.`gift` = " . (int)$data . ")";
					break;
					
					case 'RecyclePackaging':
						$where[] = "(o.`recyclable` = " . (int)$data . ")";
					break;
					
					case 'SpecificOrders':
						$where[] = "(o.`id_order` IN(" . pSQL(implode(",", $data)) . "))";
					break;
					
				}
			}
		}
		
		// Handle ignore orders
		if ($filterValues['IgnoreOrders'] != "") {
			$where[] = "(o.`id_order` NOT IN (" . trim(pSQL($filterValues['IgnoreOrders']), ",") . "))";
		}
		
		$sql = "SELECT o.`id_order`, o.`date_add`, c.`firstname`, c.`lastname`, cr.`iso_code`, (o.`total_products_wt` + o.`total_shipping` + o.`total_wrapping` - o.`total_discounts`) as total_amount" . (!empty($select) ? ',' . implode(",", $select) : '') . " FROM `" . _DB_PREFIX_ ."orders` o
				LEFT JOIN `" . _DB_PREFIX_ . "order_detail` od
					ON o.`id_order` = od.`id_order`
				LEFT JOIN `" . _DB_PREFIX_ . "product` p
					ON p.`id_product` = od.`product_id`
				LEFT JOIN `" . _DB_PREFIX_ . "customer` c
					ON c.`id_customer` = o.`id_customer`
				LEFT JOIN `" . _DB_PREFIX_ . "currency` cr
					ON cr.`id_currency` = o.`id_currency`";
		if (!empty($join)) {
			foreach ($join as $j) {
				$sql .= ' ' . $j . ' ';
			}
		}
		if (!empty($where)) {
			$sql .= " WHERE ";
			$i = 0;
			foreach ($where as $w) {
				$sql .= ' ' . ($i != 0 ? 'AND ' : '') . $w . ' ';
				$i++;
			}
		}
		$sql .= ' GROUP by o.`id_order` ';
		if (!empty($having)) {
			
			$sql .= " HAVING " . implode(" AND ", $having);
		}
		$sql .= " ORDER by o.`id_order` ASC ";
		return Db::getInstance()->ExecuteS($sql);
	}
	return false;
}

function orderbulkupdate_update_orders($orders, $update, $updateValues, $id_employee) {
	global $_m;
	if (!empty($orders) && !empty($update)) {
		$total_orders = count($orders); $i = 1;
		foreach ($orders as $order) {
			$order = new Order((int)$order['id_order']);
			orderbulkupdate_write_status('['.$i.'/'.$total_orders.'] ' . $_m->l('Updating order #') . $order->id . '<br /><br />');
			foreach ($update as $type => $value) {
				if (!isset($updateValues[$type])) {
					// No value were entered, skip
					continue;
				}
				$data = $updateValues[$type];
				switch ($type) {
					case 'OrderState':
						if ($order->setCurrentState((int)$data, $id_employee) !== false){
							orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Updated order state') . '<br />');
						} else {
							orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not update order state') . '</span><br />');
						}
					break;

					case 'Carrier':
						$order->id_carrier = (int)$data;
						if ($order->update() !== false) {
							orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Updated carrier') . '<br />');
						} else {
							orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not update carrier') . '</span><br />');
						}
					break;

					case 'Customer':
						if ((int)$data['id_customer'] != 0 && (int)$data['id_address_invoice'] != 0 && (int)$data['id_address_delivery'] != 0) {
							$order->id_customer = (int)$data['id_customer'];
							$order->id_address_invoice = (int)$data['id_address_invoice'];
							$order->id_address_delivery = (int)$data['id_address_delivery'];
							if ($order->update() !== false) {
								orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Updated customer and addresses') . '<br />');
							} else {
								orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not update customer and addresses') . '</span><br />');
							}
						}
					break;

					case 'PaymentMethod':
						$paymentModule = Module::getInstanceByName(pSQL($data));
						$order->module = $paymentModule->name;
						$order->payment = $paymentModule->displayName;
						if ($order->update() !== false) {
							orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Updated payment method') . '<br />');
						} else {
							orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not update payment method') . '</span><br />');
						}
					break;

					case 'GiftWrapping':
						$order->gift = (int)$data;
						if ($order->update() !== false) {
							orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Updated gift wrapping') . '<br />');
						} else {
							orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not update gift wrapping') . '</span><br />');
						}
					break;

					case 'RecyclePackaging':
						$order->recyclable = (int)$data;
						if ($order->update() !== false) {
							orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Updated recyclable packaging') . '<br />');
						} else {
							orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not update recyclable') . '</span><br />');
						}
					break;
					
					case 'OrderMessage':
						$message = new Message();
						$message->id_employee = $id_employee;
						$message->message = htmlentities($data['message'], ENT_COMPAT, 'UTF-8');
						$message->id_order = $order->id;
						$message->private = (int)$data['private'];
						if (!($messageAdded = $message->add())) {
							orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not add customer message') . '</span><br />');
						} else {
							orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Added order message') . '<br />');
						}
						
						if ($messageAdded && !$message->private && Validate::isLoadedObject($customer = new Customer($order->id_customer))) {
							if (Validate::isLoadedObject($order)) {
								$varsTpl = array('{lastname}' => $customer->lastname, '{firstname}' => $customer->firstname, '{id_order}' => $message->id_order, '{message}' => (Configuration::get('PS_MAIL_TYPE') == 2 ? $message->message : nl2br2($message->message)));
								if (@Mail::Send((int)($order->id_lang), 'order_merchant_comment', Mail::l('New message regarding your order'), $varsTpl, $customer->email, $customer->firstname.' '.$customer->lastname, NULL, NULL, NULL, NULL, _PS_MAIL_DIR_, true)) {
									orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Sent order message to customer') . '<br />');
								} else {
									orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not send order message to customer') . '</span><br />');
								}
							}
						}
					break;
					
					case 'TotalPaidReal':
						$order->total_paid_real = round((double)$data * $order->conversion_rate, 2);
						if ($order->update() !== false) {
							orderbulkupdate_write_status('&nbsp;&nbsp;- ' . $_m->l('Updated total amount paid (real)') . '<br />');
						} else {
							orderbulkupdate_write_status('&nbsp;&nbsp;- <span style="color:red;font-weight:bold;">' . $_m->l('Could not update the total amount paid (real)') . '</span><br />');
						}
					break;
				}
			}
			orderbulkupdate_write_status('['.$i.'/'.$total_orders.'] ' . $_m->l('Finished updating order #') . $order->id . '<br />');
			$i++;
		}
	}
}

$filters = Tools::getValue("filters");
$filterValues = Tools::getValue("filterValues");
$update = Tools::getValue("update");
$updateValues = Tools::getValue("updateValues");
$id_employee = (int)Tools::getValue('id_employee');

if (Tools::getValue('action')) {
	set_time_limit(0);
	switch (Tools::getValue('action')) {
		
		case 'preview':
			$orders = orderbulkupdate_get_orders($filters, $filterValues);
			if ($orders !== false && !empty($orders)) {
				die(json_encode($orders));
			} else {
				die(json_encode(array('empty' => '1')));
			}
		break;
		
		case 'update':
			@file_put_contents(dirname(__FILE__).'/bulk-update.txt', "");
			orderbulkupdate_write_status($_m->l('Fetching orders that matches your filter criteria') . '<br />');
			$orders = orderbulkupdate_get_orders($filters, $filterValues);
			if (!empty($orders)) {
				orderbulkupdate_write_status('<b>' . count($orders) . '</b> ' . $_m->l('order(s) matched your filter criteria and will be updated') . '<br />');
				orderbulkupdate_update_orders($orders, $update, $updateValues, $id_employee);
				orderbulkupdate_write_status('<span style="color:green;font-weight:bold;">' . $_m->l('The bulk update is now finished.') . '</span><br /><br />');
			} else {
				orderbulkupdate_write_status('<span style="color:red;font-weight:bold;">' . $_m->l('No orders matched your filter criterias') . '</span><br />');
			}
		break;
		
		case 'get-status':
			echo @file_get_contents(dirname(__FILE__).'/bulk-update.txt');
			@file_put_contents(dirname(__FILE__).'/bulk-update.txt', "");
		break;
		
	}
}