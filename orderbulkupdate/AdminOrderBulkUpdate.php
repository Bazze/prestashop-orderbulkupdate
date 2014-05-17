<?php
require_once (dirname(__FILE__).'/../../classes/AdminTab.php');
if (!class_exists('SNSolutionsHelper'))
	include_once(dirname(__FILE__) . '/SNSolutionsHelper.class.php');

class AdminOrderBulkUpdate extends AdminTab {
	
	private $ordersPerPage = 20;
	
	public function __construct() {
		// Initialise the tab by linking it to a database table and setting its default permissions
		$this->name = "orderbulkupdate";
		$this->_path = __PS_BASE_URI__.'modules/'.$this->name.'/';
		
		parent::__construct();
	}

	public function postProcess() {
		// This function is executed when the Submit button is clicked
		// Use it to store the value of text fields in the database
		
		parent::postProcess();
	}
	
	/**
	  * Return customers list
	  *
	  * @return array Customers
	  */
	public function getCustomers() {
		return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
		SELECT `id_customer`, `email`, `firstname`, `lastname`
		FROM `'._DB_PREFIX_.'customer`
		ORDER BY `firstname` ASC, `lastname` ASC, `email` ASC');
	}
	
	public function recurse_category(&$html, $categories, $current, $id_category = 1, $id_selected = 1) {
		$html->add_html('<option value="'.$id_category.'"'.(($id_selected == $id_category && $id_category != 1) ? ' selected="selected"' : '').'>'.
		str_repeat('&nbsp;', $current['infos']['level_depth'] * 5).stripslashes($current['infos']['name']).'</option>');
		if (isset($categories[$id_category]))
			foreach (array_keys($categories[$id_category]) AS $key)
				$this->recurse_category($html, $categories, $categories[$id_category][$key], $key, $id_selected);
	}
	
	public function getOrders($start) {
		global $cookie;
		$sql = 'SELECT a.id_order AS id_pdf, a.date_add, (a.`total_products_wt`+a.`total_shipping`+a.`total_wrapping`-a.`total_discounts`) as total_amount, a.`id_currency`,
			CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`,
			osl.`name` AS `osname`,
			os.`color`,
			IF((SELECT COUNT(so.id_order) FROM `'._DB_PREFIX_.'orders` so WHERE so.id_customer = a.id_customer) > 1, 0, 1) as new,
			(SELECT COUNT(od.`id_order`) FROM `'._DB_PREFIX_.'order_detail` od WHERE od.`id_order` = a.`id_order` GROUP BY `id_order`) AS product_number FROM 
			`'._DB_PREFIX_.'orders` a
		LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.`id_customer` = a.`id_customer`)
	 	LEFT JOIN `'._DB_PREFIX_.'order_history` oh ON (oh.`id_order` = a.`id_order`)
		LEFT JOIN `'._DB_PREFIX_.'order_state` os ON (os.`id_order_state` = oh.`id_order_state`)
		LEFT JOIN `'._DB_PREFIX_.'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` AND osl.`id_lang` = '.(int)($cookie->id_lang).')
		WHERE oh.`id_order_history` = (SELECT MAX(`id_order_history`) FROM `'._DB_PREFIX_.'order_history` moh WHERE moh.`id_order` = a.`id_order` GROUP BY moh.`id_order`) ORDER by a.`id_order` DESC LIMIT ' . (int)$start . ', ' . (int)($this->ordersPerPage+1);
		return Db::getInstance()->ExecuteS($sql);
	}

	public function display() {
		// This function can be used to create a form with text fields
		global $cookie; 
		
		$html = new SNSolutionsHelper(true);
		$html->add_html('
			<link type="text/css" rel="stylesheet" href="' . _PS_CSS_DIR_ . 'jquery-ui-1.8.10.custom.css" />
			<style type="text/css">
				fieldset {
					margin-bottom: 10px;
				}
				fieldset fieldset label {
					width: 186px;
				}
				fieldset fieldset .margin-form {
					padding-left: 196px;
				}
				fieldset fieldset .table {
					margin-left: 196px;
				}
				/* css for timepicker */
				.ui-timepicker-div .ui-widget-header { margin-bottom: 8px; }
				.ui-timepicker-div dl { text-align: left; }
				.ui-timepicker-div dl dt { height: 25px; margin-bottom: -25px; }
				.ui-timepicker-div dl dd { margin: 0 10px 10px 65px; }
				.ui-timepicker-div td { font-size: 90%; }
				.ui-tpicker-grid-label { background: none; border: none; margin: 0; padding: 0; }
				.ui-widget-content { display:block; }
			</style>
			<script type="text/javascript" src="' . _PS_JS_DIR_ . 'jquery/jquery-ui-1.8.10.custom.min.js"></script>
			<script type="text/javascript" src="' . _MODULE_DIR_ . $this->name . '/js/jquery-ui-timepicker-addon.js"></script>
		');
		
		$html->form_start("orderBulkUpdate", "orderBulkUpdate", Tools::safeOutput($_SERVER['REQUEST_URI']));
		$html->input("hidden", "id_employee", "id_employee", $cookie->id_employee);
		$html->input("hidden", "passwd", "passwd", $cookie->passwd);
		$html->input("hidden", "action", "action", "");
		
		$html->fieldset_start( $this->l('Step 1: Filter out orders') , 'filterOrders');
		$html->add_html('<div class="hint clear" style="display:block;padding-left:40px;"><p>' . $this->l('The filter part is where you specify which orders to include in the bulk update.') . '</p></div><br />');
		
		$filters = array(
			'Date' => $this->l('Date'),
			'OrderStates' => $this->l('Order states'),
			'Suppliers' => $this->l('Suppliers'),
			'Manufacturers' => $this->l('Manufacturers'),
			'Products' => $this->l('Products'),
			'Carriers' => $this->l('Carriers'),
			'Customers' => $this->l('Customers'),
			'Countries' => $this->l('Countries'),
			'PaymentMethods' => $this->l('Payment methods'),
			'OrderAmount' => $this->l('Order amount'),
			'GiftWrapping' => $this->l('Gift wrapping'),
			'RecyclePackaging' => $this->l('Recyclable packaging'),
			'SpecificOrders' => $this->l('Specific orders'),
			'AllOrders' => $this->l('All orders')
		);
		
		$html->add_html('<label>' . $this->l('Apply filters') . '</label>');
		$html->add_html('<div class="margin-form" id="filterList">');
		$html->table_start();
			$html->tr_start();
				$i = 0; $perRow = 4; $totalLines = ceil(count($filters)/$perRow);
				foreach ($filters as $uid => $label) {
					$html->td('
						<input type="checkbox" name="filters[' . $uid . ']" id="' . $uid . '" onclick="' . ($uid != 'AllOrders' ? 'if ($(\'#AllOrders\').is(\':checked\')) { $(\'#AllOrders\').removeAttr(\'checked\'); } $(\'#input' . $uid . '\').toggle();' : '$(\'#filterList\').find(\':checkbox:not(#AllOrders)\').removeAttr(\'checked\');$(\'#filterOrders fieldset\').hide();') . '" value="1" />', '', 
	($i%$perRow != 0 ? 'border-left: 1px solid #DEDEDE;' : '') . ($i >= $totalLines*$perRow-$perRow ? 'border-bottom: 0;' : ''));
					$html->td('<label for="' . $uid . '" style="width:auto;font-weight:normal;padding:0;text-align:left;float:none;">' . $label . '</label>', '', ($i >= $totalLines*$perRow-$perRow ? 'border-bottom: 0;' : ''), ($uid == 'AllOrders' ? 5 : false));
					$i++;
					if ($i%$perRow == 0) {
						$html->tr_end();
						$html->tr_start();
					}
				}
			$html->tr_end();
		$html->table_end();
		$html->add_html('<p>' . $this->l('When you choose a filter, its settings will be displayed below.') . '</p>');
		$html->input_end();
		
		$html->set_group_field_name('filterValues');
		$html->group_fields(true);
		
		/* DATE */
		$html->add_html('<fieldset id="inputDate" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Date') . '</legend>');
		$html->select_start( $this->l('Date type') );
		$html->select($id = 'dateType', $name = 'Date[type]', $multiple = false, $values = array('interval' => $this->l('Interval'), 'specific' => $this->l('Specific date')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = null, $disabled = false);
		$html->select_end();
		$html->add_html('
			<script type="text/javascript">
				$("#dateType").change( function() {
					if ($(this).val() == "specific") {
						$("#inputDate .date-interval").parent().parent().hide();
						$("#inputDate #dateSpecific").parent().parent().show();
					} else if ($(this).val() == "interval") {
						$("#inputDate .date-interval").parent().parent().show();
						$("#inputDate #dateSpecific").parent().parent().hide();
					}
				});
			</script>
		');
		$html->add_html('<div style="display:none;">');
		$html->input_start( $this->l('Specific date') );
		$html->input($type = 'text', $id = 'dateSpecific', $name = 'Date[specific]', $value = '', $class = '', $style = '', $selected = false, $disabled = false, $onClick = false, $help = $this->l('Orders must have been placed on this date to be included in the bulk update.'), $onKeyUp = false);
		$html->input_end();
		$html->add_html('</div>');
		$html->add_html('<div>');
		$html->input_start( $this->l('From') );
		$html->input($type = 'text', $id = 'dateIntervalFrom', $name = 'Date[from]', $value = '', $class = 'date-interval', $style = '', $selected = false, $disabled = false, $onClick = false, $help = $this->l('Orders placed from this date...'), $onKeyUp = false);
		$html->input_end();
		$html->add_html('</div>');
		$html->add_html('<div>');
		$html->input_start( $this->l('To') );
		$html->input($type = 'text', $id = 'dateIntervalTo', $name = 'Date[to]', $value = '', $class = 'date-interval', $style = '', $selected = false, $disabled = false, $onClick = false, $help = $this->l('...to this date will be included in the bulk update.'), $onKeyUp = false);
		$html->input_end();
		$html->add_html('</div>');
		$html->add_html('
			<script type="text/javascript">
				$("#dateSpecific").datepicker({
					dateFormat: "yy-mm-dd",
					maxDate: "now"
				});
				$("#dateIntervalFrom").datetimepicker({
					dateFormat: "yy-mm-dd",
					hourGrid: 4,
					minuteGrid: 10,
				    onClose: function(dateText, inst) {
				        var endDateTextBox = $("#dateIntervalTo");
				        if (endDateTextBox.val() != "") {
				            var testStartDate = new Date(dateText);
				            var testEndDate = new Date(endDateTextBox.val());
				            if (testStartDate > testEndDate)
				                endDateTextBox.val(dateText);
				        }
				        else {
				            endDateTextBox.val(dateText);
				        }
				    },
				    onSelect: function (selectedDateTime){
				        var start = $(this).datetimepicker("getDate");
				        $("#dateIntervalTo").datetimepicker("option", "minDate", new Date(start.getTime()));
				    }
				});
				$("#dateIntervalTo").datetimepicker({
					dateFormat: "yy-mm-dd",
					hourGrid: 4,
					minuteGrid: 10,
				    onClose: function(dateText, inst) {
				        var startDateTextBox = $("#dateIntervalFrom");
				        if (startDateTextBox.val() != "") {
				            var testStartDate = new Date(startDateTextBox.val());
				            var testEndDate = new Date(dateText);
				            if (testStartDate > testEndDate)
				                startDateTextBox.val(dateText);
				        }
				        else {
				            startDateTextBox.val(dateText);
				        }
				    },
				    onSelect: function (selectedDateTime){
				        var end = $(this).datetimepicker("getDate");
				        $("#dateIntervalFrom").datetimepicker("option", "maxDate", new Date(end.getTime()) );
				    }
				});
			</script>	
		');
		$html->fieldset_end();
		/* END DATE */
		
		/* ORDER STATES */
		$html->add_html('<fieldset id="inputOrderStates" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Order states') . '</legend>');
		$html->add_html('<label>' . $this->l('Order states') . '</label>');
		$html->add_html('<table class="table" cellspacing="0" cellpadding="0">');
		$html->add_html('<thead>');
		$html->add_html('	<th style="width:18px;"><input type="checkbox" name="check_all_order_states" onclick="if (!this.checked) {$(\'#inputOrderStates tbody\').find(\':checkbox\').removeAttr(\'checked\');} else {$(\'#inputOrderStates tbody\').find(\':checkbox\').attr(\'checked\', \'checked\');}" style="margin-left:3px;" /></th>');
		$html->add_html('	<th class="center" width="5%">ID</th>');
		$html->add_html('	<th>' . $this->l('Name') . '</th>');
		$html->add_html('	<th class="center">' . $this->l('Icon') . '</th>');
		$html->add_html('</thead>');
		
		$order_states = array();
		$html->add_html('<tbody>');
		foreach (OrderState::getOrderStates($cookie->id_lang) as $state) {
			$html->add_html('	<tr>');
			$html->add_html('		<td class="center" style="background-color:' . $state['color'] . ';"><input type="checkbox" name="filterValues[OrderStates][]" value="' . $state['id_order_state'] . '"');
			if (in_array($state['id_order_state'], $order_states)) {
				$html->add_html(' checked="checked"');
			}
			$html->add_html(' /></td>');
			$html->add_html('		<td class="center" style="background-color:' . $state['color'] . ';">' . $state['id_order_state'] . '</td>');
			$html->add_html('		<td style="background-color:' . $state['color'] . ';">' . $state['name'] . '</td>');
			$html->add_html('		<td class="center" style="background-color:' . $state['color'] . ';">');
			if (file_exists(_PS_TMP_IMG_DIR_ . 'order_state_mini_' . $state['id_order_state'] . '.gif')) {
				$html->add_html('<img src="'._PS_TMP_IMG_.'order_state_mini_' . $state['id_order_state'] . '.gif" alt="" />');
			}
			$html->add_html('</td>');
			$html->add_html('	</tr>');	
		}
		
		$html->add_html('</tbody>');
		
		$html->add_html('</table>');
		$html->add_html('<p style="margin-left:196px;color:#7F7F7F;font-size:0.85em;margin-bottom:10px;">');
		$html->add_html($this->l('Choose which order state(s) the orders should have.'));
		$html->add_html('</p>');
		$html->fieldset_end();
		/* END ORDER STATES */
		
		/* SUPPLIERS */
		$html->add_html('<fieldset id="inputSuppliers" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Suppliers') . '</legend>');
		$html->select_start( $this->l('Suppliers') );
		$suppliers = Supplier::getSuppliers(false, $cookie->id_lang);
		$ids = array();
		foreach ($suppliers as $supplier) {
			$ids[] = $supplier['id_supplier'];
		}
		$html->select('isupplier', 'Suppliers[id_supplier][]', true, $suppliers, '', 'min-width:300px;padding:5px;height:150px;', 'id_supplier', 'name', Tools::getValue('supplier'), false, null);
		$html->select_end();
		$html->input_start( $this->l('Condition') );
		$html->add_html('<div style="margin-bottom:10px;">');
		$html->input($type = 'radio', $id = 'conditionNonExclusive', $name = 'Suppliers[condition]', $value = 'non_exclusive', $class = '', $style = '', $selected = true, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$html->add_html(' ' . $this->l('The orders must contain') . ' <b>' . $this->l('one or more') . '</b> ' . $this->l('products from the supplier(s) chosen above') . '</div><div>');
		$html->input($type = 'radio', $id = 'conditionExclusive', $name = 'Suppliers[condition]', $value = 'exclusive', $class = '', $style = '', $selected = false, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$html->add_html(' ' . $this->l('The orders may') . ' <b>' . $this->l('only') . '</b> ' . $this->l('contain products from the supplier(s) chosen above') . '</div>');
		$html->input_end();
		$html->fieldset_end();
		/* END SUPPLIERS */
		
		/* MANUFACTURERS */
		$html->add_html('<fieldset id="inputManufacturers" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Manufacturers') . '</legend>');
		$html->select_start( $this->l('Manufacturers') );
		$manufacturers = Manufacturer::getManufacturers(false, $cookie->id_lang);
		$ids = array();
		foreach ($manufacturers as $manufacturer) {
			$ids[] = $manufacturer['id_manufacturer'];
		}
		$html->select('imanufacturer', 'Manufacturers[id_manufacturer][]', true, $manufacturers, '', 'min-width:300px;padding:5px;height:150px;', 'id_manufacturer', 'name', Tools::getValue('manufacturer'), false, null);
		$html->select_end();
		
		$html->input_start( $this->l('Condition') );
		$html->add_html('<div style="margin-bottom:10px;">');
		$html->input($type = 'radio', $id = 'conditionNonExclusive', $name = 'Manufacturers[condition]', $value = 'non_exclusive', $class = '', $style = '', $selected = true, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$html->add_html(' ' . $this->l('The orders must contain') . ' <b>' . $this->l('one or more') . '</b> ' . $this->l('products from the manufacturer(s) chosen above') . '</div><div>');
		$html->input($type = 'radio', $id = 'conditionExclusive', $name = 'Manufacturers[condition]', $value = 'exclusive', $class = '', $style = '', $selected = false, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$html->add_html(' ' . $this->l('The orders may') . ' <b>' . $this->l('only') . '</b> ' . $this->l('contain products from the manufacturer(s) chosen above') . '</div>');
		
		$html->input_end();
		$html->fieldset_end();
		/* END MANUFACTURERS */
		
		/* PRODUCTS */
		$html->add_html('<fieldset id="inputProducts" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Products') . '</legend>');
		$html->select_start( $this->l('Supplier') );
		$suppliers = Supplier::getSuppliers(false, $cookie->id_lang);
		$ids = array();
		foreach ($suppliers as $supplier) {
			$ids[] = $supplier['id_supplier'];
		}
		$suppliers[] = array('id_supplier' => implode(',', $ids), 'name' => '&raquo; ' . $this->l('All suppliers'));
		$html->select('supplier', 'Rubbish[id_supplier]', false, $suppliers, '', '', 'id_supplier', 'name', Tools::getValue('supplier'), array('0' => $this->l('-- Select --')), $this->l('Choose a supplier to find the product(s) you are looking for.'));
		$html->select_end();
		
		$html->select_start( $this->l('Manufacturer') );
		$manufacturers = Manufacturer::getManufacturers(false, $cookie->id_lang);
		$ids = array();
		foreach ($manufacturers as $manufacturer) {
			$ids[] = $manufacturer['id_manufacturer'];
		}
		$manufacturers[] = array('id_manufacturer' => implode(',', $ids), 'name' => '&raquo; ' . $this->l('All manufacturers'));
		$html->select('manufacturer', 'Rubbish[id_manufacturer]', false, $manufacturers, '', '', 'id_manufacturer', 'name', Tools::getValue('manufacturer'), array('0' => $this->l('-- Select --')), $this->l('Choose a manufacturer to find the product(s) you are looking for.'));
		$html->select_end();
		
		$categories = Category::getCategories($cookie->id_lang);
		$html->select_start($this->l('Categories'));
		$html->add_html('<select name="filterValues[Rubbish][id_category]" id="category">');
		$html->add_html('<option value="0">' . $this->l('-- Select --') . '</option>');
		$this->recurse_category($html, $categories, $categories[0][1]);
		$html->add_html('</select>');
		$html->add_html('<p>' . $this->l('Choose a category to find the product(s) you are looking for.') . '</p>');
		$html->select_end();
		
		$html->add_html('
			<script type="text/javascript">
			$("#supplier, #manufacturer, #category").change( function() {
				$.ajax({
					url: "' . $this->_path . 'get-products.php",
					type: "GET",
					dataType: "json",
					data: { 
						id_lang: 			"' . $cookie->id_lang . '",
						id_supplier: 		$("#supplier").val(),
						id_manufacturer: 	$("#manufacturer").val(),
						id_category:		$("#category").val(),
						id_employee:		' . $cookie->id_employee . ',
						passwd:				"' . $cookie->passwd . '"
					},
					beforeSend: function() {
						$("#loading-products").show();
					},
					success: function(json) {
						$("#product_list").find("option").remove();
						if (json != null) {
							if (json.error == null) {
								$.each(json, function(key, product) {
									if ($("#products option[value=\'" + product.id_product + "\']").size() == 0) {
										$("#product_list").append(\'<option value="\' + product.id_product + \'">\' + product.name + \' [id:\' + product.id_product + \']</option>\');
									}
								});
							} else {
								alert("' . $this->l('AJAX Error: Please refresh the page. Your cookie has expired.') . '");
							}
						}
					},
					complete: function() {
						$("#loading-products").hide();
					},
					error: function(msg) {
						alert("' . $this->l('AJAX Error: Could not load products') . '");
					}
				});
			});
			</script>
		');
		
		$html->select_start( $this->l('Orders with these products'), 'position:relative;');
		$html->select('products', 'Products[id_product][]', true, array(), '', 'padding:5px;width:250px;height:150px;');
		$html->add_html('&nbsp;');
		$html->select('product_list', 'product_list', true, array(), '', 'padding:5px;width:250px;height:150px;');
		$html->add_html('<br />');
		$html->add_html('<a id="removeException" style="float:left;width:244px;text-align:center;display:inline;border:1px solid #E0D0B1;text-decoration:none;background-color:#fafafa;color:#123456;margin:4px 3px 5px 0;padding:2px;cursor:pointer;">' . $this->l('Remove') . ' &raquo;</a>');
		$html->add_html('&nbsp;<a id="addException" style="float:left;width:244px;text-align:center;display:inline;border:1px solid #E0D0B1;text-decoration:none;background-color:#fafafa;color:#123456;margin:4px 0 5px 0;padding:2px;cursor:pointer;">&laquo; ' . $this->l('Add') . '</a>');
		$html->add_html('<p class="clear">' . $this->l('Select which products the orders must contain.') . '</p>');
		$html->add_html('<div id="loading-products" style="display:none;position:absolute;right:285px;top:65px;"><img src="' . _PS_IMG_ . 'loader.gif" alt="" /></div>');
		$html->select_end();
		
		$html->add_html('
			<script type="text/javascript">
				$("#addException").click(function() {
					return !$("#product_list option:selected").remove().appendTo("#products").removeAttr("selected");
				});
				$("#removeException").click(function() {
					return !$("#products option:selected").remove().appendTo("#product_list").removeAttr("selected");
				});
			</script>
		');
		
		$html->input_start( $this->l('Condition') );
		$html->add_html('<div style="margin-bottom:10px;">');
		$html->input($type = 'radio', $id = 'pconditionNonExclusive', $name = 'Products[condition]', $value = 'non_exclusive', $class = '', $style = '', $selected = true, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$html->add_html(' ' . $this->l('The orders must contain') . ' <b>' . $this->l('one or more') . '</b> ' . $this->l('of the product(s) chosen above') . '</div><div>');
		$html->input($type = 'radio', $id = 'pconditionExclusive', $name = 'Products[condition]', $value = 'exclusive', $class = '', $style = '', $selected = false, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$html->add_html(' ' . $this->l('The orders may') . ' <b>' . $this->l('only') . '</b> ' . $this->l('contain the product(s) chosen above') . '</div>');
		
		$html->fieldset_end();
		/* END PRODUCTS */
		
		/* CARRIERS */
		$html->add_html('<fieldset id="inputCarriers" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Carriers') . '</legend>');
		$html->select_start( $this->l('Carriers') );
		$carriers = Carrier::getCarriers($cookie->id_lang);
		$html->select($id = 'carriers', $name = 'Carriers[]', $multiple = true, $values = $carriers, $class = '', $style = 'min-width:300px;padding:5px;height:150px;', $alt_value = 'id_carrier', $alt_option = 'name', $selected = "", $default_option = false, $help = $this->l('Choose carrier(s) that should be present in the orders.'), $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END CARRIERS */
		
		/* CUSTOMERS */
		$html->add_html('<fieldset id="inputCustomers" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Customers') . '</legend>');
		$html->select_start( $this->l('Customers') );
		$customers = $this->getCustomers();
		$html->add_html('<select name="filterValues[Customers][]" id="customers" multiple="multiple" style="padding:5px;min-width:400px;height:150px;resize:both;">');
		foreach ($customers as $customer) {
			$html->add_html('<option value="' . $customer['id_customer'] . '">' . $customer['firstname'] . ' ' . $customer['lastname'] . ' (' . $customer['email'] . ')</option>');
		}
		$html->add_html('</select>');
		$html->add_html('<p>' . $this->l('Choose customers(s) that should be present in the orders.') . '</p>');
		$html->select_end();
		$html->fieldset_end();
		/* END CUSTOMERS */
		
		/* COUNTRIES */
		$html->add_html('<fieldset id="inputCountries" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Countries') . '</legend>');
		$html->select_start( $this->l('Countries') );
		$countries = Country::getCountries($cookie->id_lang);
		$html->select($id = 'countries', $name = 'Countries[countries][]', $multiple = true, $values = $countries, $class = '', $style = 'min-width:300px;padding:5px;height:150px;', $alt_value = 'id_country', $alt_option = 'name', $selected = "", $default_option = false, $help = $this->l('Choose a country or several countries that applies to the address type(s) you choose in the next field.'), $disabled = false);
		$html->select_end();
		$html->select_start( $this->l('Address type') );
		$html->select($id = 'countries_address', $name = 'Countries[countries_address]', $multiple = false, $values = array('invoice' => $this->l('Invoice address'), 'delivery' => $this->l('Delivery address'), 'both' => $this->l('Invoice and delivery address'), 'either' => $this->l('Invoice or delivery address')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = $this->l('Choose which address the countries should be present in.'), $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END COUNTRIES */
		
		/* PAYMENT METHODS */
		$html->add_html('<fieldset id="inputPaymentMethods" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Payment methods') . '</legend>');
		$html->select_start( $this->l('Payment methods') );
		$paymentMethods = PaymentModule::getInstalledPaymentModules();
		$html->select($id = 'paymentMethods', $name = 'PaymentMethods[]', $multiple = true, $values = $paymentMethods, $class = '', $style = 'min-width:300px;padding:5px;height:150px;', $alt_value = 'name', $alt_option = 'name', $selected = "", $default_option = false, $help = $this->l('Choose payment method(s) that were used in the orders.'), $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END PAYMENT METHODS */
		
		/* ORDER AMOUNT */
		$html->add_html('<fieldset id="inputOrderAmount" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Order amount') . '</legend>');
		$html->select_start( $this->l('Operator') );
		$values = array(
			'=' => '=&nbsp;&nbsp;&nbsp;' . $this->l('equal to'),
			'<' => '<&nbsp;&nbsp;&nbsp;' . $this->l('smaller than'),
			'<=' => '<= ' . $this->l('smaller than or equal to'),
			'>' => '>&nbsp;&nbsp;&nbsp;' . $this->l('greater than'),
			'>=' => '>= ' . $this->l('greater than or equal to')
		);
		$html->select($id = 'orderAmountOperator', $name = 'OrderAmount[operator]', $multiple = false, $values, $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = $this->l('Choose which operator to use when filtering on order amount.'), $disabled = false);
		$html->select_end();
		$html->select_start( $this->l('Currency'));
		$html->select($id = 'orderAmountCurrency', $name = 'OrderAmount[id_currency]', $multiple = false, Currency::getCurrencies(), $class = '', $style = '', $alt_value = 'id_currency', $alt_option = 'iso_code', $selected = "", $default_option = array('any' => $this->l('Any')), $help = $this->l('Choose which currency the orders should have been paid in. Only orders with the chosen currency will be included in the bulk update.'), $disabled = false);
		$html->select_end();
		$html->select_start( $this->l('Tax') );
		$html->select($id = 'orderAmountTax', $name = 'OrderAmount[tax]', $multiple = false, $value = array("0" => $this->l("Amount without tax"), "1" => $this->l('Amount with tax')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "1", $default_option = false, $help = $this->l('Is the amount with tax included or not?'), $disabled = false);
		$html->select_end();
		$html->select_start( $this->l('Shipping') );
		$html->select($id = 'orderAmountShipping', $name = 'OrderAmount[shipping]', $multiple = false, $value = array("0" => $this->l("Amount without shipping"), "1" => $this->l('Amount with shipping')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "1", $default_option = false, $help = $this->l('Is the amount with shipping included or not?'), $disabled = false);
		$html->select_end();
		$html->select_start( $this->l('Wrapping') );
		$html->select($id = 'orderAmountWrapping', $name = 'OrderAmount[wrapping]', $multiple = false, $value = array("0" => $this->l("Amount without wrapping"), "1" => $this->l('Amount with wrapping')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "1", $default_option = false, $help = $this->l('Is the amount with wrapping included or not?'), $disabled = false);
		$html->select_end();
		$html->select_start( $this->l('Dicounts') );
		$html->select($id = 'orderAmountDiscount', $name = 'OrderAmount[discount]', $multiple = false, $value = array("0" => $this->l("Amount without discounts"), "1" => $this->l('Amount with discounts')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "1", $default_option = false, $help = $this->l('Is the amount with discounts applied or not?'), $disabled = false);
		$html->select_end();
		$html->input_start( $this->l('Amount') );
		$html->input($type = 'text', $id = 'orderAmount', $name = 'OrderAmount[amount]', $value = '', $class = '', $style = '', $selected = false, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT', 1));
		$html->add_html(' ' . $currency->iso_code);
		$html->add_html('<p>' . $this->l('The order amount you enter will always be converted to the same currency the orders were paid in.') . '</p>');
		$html->input_end();
		$html->fieldset_end();
		/* END ORDER AMOUNT */
		
		/* GIFT WRAPPING */
		$html->add_html('<fieldset id="inputGiftWrapping" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Gift wrapping') . '</legend>');
		$html->select_start( $this->l('Gift wrapping') );
		$html->select($id = 'giftWrapping', $name = 'GiftWrapping', $multiple = false, $values = array('1' => $this->l('Yes'), '0' => $this->l('No')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "1", $default_option = false, $help = $this->l('Orders with gift wrapping selected or not.'), $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END GIFT WRAPPING */
		
		/* RECYCLE PACKAGING */
		$html->add_html('<fieldset id="inputRecyclePackaging" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Recyclable packaging') . '</legend>');
		$html->select_start( $this->l('Recyclable packaging') );
		$html->select($id = 'recyclePackaging', $name = 'RecyclePackaging', $multiple = false, $values = array('1' => $this->l('Yes'), '0' => $this->l('No')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "1", $default_option = false, $help = $this->l('Orders with Recyclable packaing selected or not.'), $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END RECYCLE PACKAGING */
		
		/* SPECIFIC ORDERS */
		$html->add_html('<fieldset id="inputSpecificOrders" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Specific orders') . '</legend>');
		
		$html->input_start($this->l('Choose order(s)'));
		$html->add_html('<table class="table" cellspacing="0" cellpadding="0" style="margin-left:0;font-size:12px;width:600px;">');
		$html->add_html('<thead>');
		$html->add_html('	<th style="width:18px;"><input type="checkbox" name="check_all_orders" id="check_all_orders" onclick="if (!this.checked) {$(\'#inputSpecificOrders tbody .page-\' + currentPage + \'\').find(\':checkbox\').removeAttr(\'checked\');} else {$(\'#inputSpecificOrders tbody .page-\' + currentPage + \'\').find(\':checkbox\').attr(\'checked\', \'checked\');}" style="margin-left:3px;" /></th>');
		$html->add_html('	<th class="center" width="5%">ID</th>');
		$html->add_html('	<th>' . $this->l('Customer') . '</th>');
		$html->add_html('	<th>' . $this->l('Total') . '</th>');
		$html->add_html('	<th>' . $this->l('Status') . '</th>');
		$html->add_html('	<th>' . $this->l('Date') . '</th>');
		$html->add_html('</thead>');
		
		$html->add_html('<tbody>');
		$i = 0;
		$orders = $this->getOrders(0);
		foreach ($orders as $order) {
			if ($i == $this->ordersPerPage) break;
			$currency = new Currency($order['id_currency']);
			$html->add_html('	<tr>');
			$html->add_html('		<td class="page-1 center" style="background-color:' . $order['color'] . ';"><input type="checkbox" name="filterValues[SpecificOrders][]" value="' . $order['id_pdf'] . '" /></td>');
			$html->add_html('		<td class="page-1 center" style="background-color:' . $order['color'] . ';">' . $order['id_pdf'] . '</td>');
			$html->add_html('		<td class="page-1" style="background-color:' . $order['color'] . ';">' . $order['customer'] . '</td>');
			$html->add_html('		<td class="page-1" style="background-color:' . $order['color'] . ';">' . ($currency->format == 1 || $currency->format == 3 ? $currency->sign : '') . $order['total_amount'] . ($currency->format == 2 || $currency->format ==  4 ? " " .$currency->sign : '') . '</td>');
			$html->add_html('		<td class="page-1" style="background-color:' . $order['color'] . ';">' . $order['osname'] . '</td>');
			$html->add_html('		<td class="page-1" style="background-color:' . $order['color'] . ';">' . $order['date_add'] . '</td>');
			$html->add_html('	</tr>');
			$i++;	
		}
		
		$html->add_html('</tbody>');
		
		$html->add_html('</table>');
		$html->add_html('
			<div style="width:600px;">
				<div style="display:none;float:left;padding:5px 0 0 0;" id="specificOrdersPrevPage">
					<a href="#prev-page">
						<img src="' . _PS_ADMIN_IMG_ . 'list-prev.gif" alt="" style="margin:2px 0 2px 0;padding:0;" /> ' . $this->l('Previous page') . '
					</a>
				</div>
				<div style="' . (count($orders) < $this->ordersPerPage ? 'display:none;' : '') . 'float:right;padding:5px 0 0 0;" id="specificOrdersNextPage">
					<a href="#next-page">
						' . $this->l('Next page') . ' <img src="' . _PS_ADMIN_IMG_ . 'list-next.gif" alt="" style="margin:2px 0 2px 0;padding:0;" />
					</a>
				</div>
			</div>');
		$html->add_html('
			<script type="text/javascript">
				var currentPage = 1;
				var totalPages = 1;
				$("#specificOrdersPrevPage a").click( function(e) {
					$("#inputSpecificOrders table .page-" + currentPage).hide();
					currentPage--;
					$("#inputSpecificOrders table .page-" + currentPage).show();
					$("#specificOrdersNextPage").show();
					if ($("#inputSpecificOrders table .page-" + (currentPage-1)).size() == 0) {
						$("#specificOrdersPrevPage").hide();
					}
					$("#check_all_orders").removeAttr("checked");
					e.preventDefault();
				});
				$("#specificOrdersNextPage a").click( function(e) {
					if ($("#inputSpecificOrders table .page-" + (currentPage+1)).size() == 0) {
						$.ajax({
							url: "' . $this->_path . 'get-orders.php",
							type: "post",
							dataType: "json",
							data: { 
								start:				(' . $this->ordersPerPage .'*currentPage),
								perPage:			' . $this->ordersPerPage . ',
								id_lang: 			"' . $cookie->id_lang . '",
								id_employee:		' . $cookie->id_employee . ',
								passwd:				"' . $cookie->passwd . '"
							},
							success: function(json) {
								if (json.error == undefined) {
									$.each(json.orders, function(key, order) {
										$("#inputSpecificOrders table tbody").append("\
											<tr> \
												<td class=\"page-" + (currentPage+1) + " center\" style=\"background-color:" + order.color + ";\"><input type=\"checkbox\" name=\"filterValues[SpecificOrders][]\" value=\"" + order.id_pdf + "\" /></td> \
												<td class=\"page-" + (currentPage+1) + " center\" style=\"background-color:" + order.color + ";\">" + order.id_pdf + "</td> \
												<td class=\"page-" + (currentPage+1) + "\" style=\"background-color:" + order.color + ";\">" + order.customer + "</td> \
												<td class=\"page-" + (currentPage+1) + "\" style=\"background-color:" + order.color + ";\">" + (order.currency_format == 1 || order.currency_format == 3 ? order.currency_sign : "") + order.total_amount + (order.currency_format == 2 || order.currency_format == 4 ? " " + order.currency_sign : "") + "</td> \
												<td class=\"page-" + (currentPage+1) + "\" style=\"background-color:" + order.color + ";\">" + order.osname + "</td> \
												<td class=\"page-" + (currentPage+1) + "\" style=\"background-color:" + order.color + ";\">" + order.date_add + "</td> \
											</tr>");
									});
									if (json.more == 0) {
										$("#specificOrdersNextPage").hide();
										totalPages = currentPage+1;
									}
									$("#inputSpecificOrders table .page-" + currentPage).hide();
									currentPage++;
									$("#inputSpecificOrders table .page-" + currentPage).show();
									$("#specificOrdersPrevPage").show();
								} else {
									alert("' . $this->l('AJAX Error: Could not load orders') . '");
								}
							},
							error: function() {
								alert("' . $this->l('AJAX Error: Could not load orders') . '");
							}
						});
					} else {
						$("#inputSpecificOrders table .page-" + currentPage).hide();
						currentPage++;
						$("#inputSpecificOrders table .page-" + currentPage).show();
						$("#specificOrdersPrevPage").show();
						if (currentPage == totalPages) {
							$("#specificOrdersNextPage").hide();
						}
					}
					$("#check_all_orders").removeAttr("checked");
					e.preventDefault();
				});
			</script>
		');
		$html->input_end();
		
		$html->fieldset_end();
		/* END SPECIFIC ORDERS */
		
		$html->group_fields(false);
		
		$html->fieldset_end();
		
		$html->fieldset_start( $this->l('Step 2: New values') );
		
		$update_values = array(
			'OrderState' => $this->l('Order state'),
			'Carrier' => $this->l('Carrier'),
			'Customer' => $this->l('Customer'),
			'PaymentMethod' => $this->l('Payment method'),
			'GiftWrapping' => $this->l('Gift wrapping'),
			'RecyclePackaging' => $this->l('Recyclable packaging'),
			'OrderMessage' => $this->l('Order message'),
			'TotalPaidReal' => $this->l('Total paid (real)')
		);
		
		$html->input_start( $this->l('I want to update/add') );
		$html->table_start();
			$html->tr_start();
				$i = 0; $perRow = 4; $totalLines = ceil(count($update_values)/$perRow);
				foreach ($update_values as $uid => $label) {
					$html->td('<input type="checkbox" name="update[' . $uid . ']" id="updateType' . $uid . '" onclick="$(\'#update' . $uid . '\').toggle();" value="1" />', '', ($i%$perRow != 0  ? 'border-left: 1px solid #DEDEDE;' : '') . ($i >= $totalLines*$perRow-$perRow ? 'border-bottom: 0;' : ''));
					$html->td('<label for="updateType' . $uid . '" style="width:auto;font-weight:normal;padding:0;text-align:left;float:none;">' . $label . '</label>', '', ($i >= $totalLines*$perRow-$perRow ? 'border-bottom: 0;' : ''));
					$i++;
					if ($i%$perRow == 0) {
						$html->tr_end();
						$html->tr_start();
					}
				}
			$html->tr_end();
		$html->table_end();
		$html->add_html('<p>' . $this->l('When you choose something to update, the input fields will be displayed below.') . '</p>');
		$html->input_end();
		
		$html->set_group_field_name('updateValues');
		$html->group_fields(true);
		/* ORDER STATE */
		$html->add_html('<fieldset id="updateOrderState" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Order state') . '</legend>');
		$html->add_html('<label>' . $this->l('Order state') . '</label>');
		$html->add_html('<table class="table" cellspacing="0" cellpadding="0">');
		$html->add_html('<thead>');
		$html->add_html('	<th style="width:18px;"></th>');
		$html->add_html('	<th class="center" width="5%">ID</th>');
		$html->add_html('	<th>' . $this->l('Name') . '</th>');
		$html->add_html('	<th class="center">' . $this->l('Icon') . '</th>');
		$html->add_html('</thead>');
		
		$order_states = array();
		$html->add_html('<tbody>');
		foreach (OrderState::getOrderStates($cookie->id_lang) as $state) {
			$html->add_html('	<tr>');
			$html->add_html('		<td class="center" style="background-color:' . $state['color'] . ';"><input type="radio" name="updateValues[OrderState]" value="' . $state['id_order_state'] . '"');
			if (in_array($state['id_order_state'], $order_states)) {
				$html->add_html(' checked="checked"');
			}
			$html->add_html(' /></td>');
			$html->add_html('		<td class="center" style="background-color:' . $state['color'] . ';">' . $state['id_order_state'] . '</td>');
			$html->add_html('		<td style="background-color:' . $state['color'] . ';">' . $state['name'] . '</td>');
			$html->add_html('		<td class="center" style="background-color:' . $state['color'] . ';">');
			if (file_exists(_PS_TMP_IMG_DIR_ . 'order_state_mini_' . $state['id_order_state'] . '.gif')) {
				$html->add_html('<img src="'._PS_TMP_IMG_.'order_state_mini_' . $state['id_order_state'] . '.gif" alt="" />');
			}
			$html->add_html('</td>');
			$html->add_html('	</tr>');	
		}
		
		$html->add_html('</tbody>');
		
		$html->add_html('</table>');
		$html->add_html('<p style="margin-left:196px;color:#7F7F7F;font-size:0.85em;margin-bottom:10px;">');
		$html->add_html($this->l('Choose which order state the orders should be updated with.'));
		$html->add_html('</p>');
		$html->fieldset_end();
		/* END ORDER STATE */
		
		/* CARRIER */
		$html->add_html('<fieldset id="updateCarrier" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Carrier') . '</legend>');
		$html->select_start( $this->l('Carriers') );
		$carriers = Carrier::getCarriers($cookie->id_lang);
		$html->select($id = 'carrier', $name = 'Carrier', $multiple = false, $values = $carriers, $class = '', $style = '', $alt_value = 'id_carrier', $alt_option = 'name', $selected = "", $default_option = false, $help = $this->l('Choose which carrier to update the orders with.'), $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END CARRIER */
		
		/* CUSTOMER */
		$html->add_html('<fieldset id="updateCustomer" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Customer') . '</legend>');
		$html->select_start( $this->l('Customer') );
		$customers = $this->getCustomers();
		$html->add_html('<select name="updateValues[Customer][id_customer]" id="customer">');
		$html->add_html('<option value="0">-- ' . $this->l('Choose customer') . ' --</option>');
		foreach ($customers as $customer) {
			$html->add_html('<option value="' . $customer['id_customer'] . '">' . $customer['firstname'] . ' ' . $customer['lastname'] . ' (' . $customer['email'] . ')</option>');
		}
		$html->add_html('</select>');
		$html->add_html('
			<script type="text/javascript">
			$("#customer").change( function() {
				$.ajax({
					url: "' . $this->_path . 'get-addresses.php",
					type: "GET",
					dataType: "json",
					data: { 
						id_lang: 			"' . $cookie->id_lang . '",
						id_customer: 		$("#customer").val(),
						id_employee:		' . $cookie->id_employee . ',
						passwd:				"' . $cookie->passwd . '"
					},
					beforeSend: function() {
						$("#updateCustomer .loading-address").show();
					},
					success: function(json) {
						$("#invoice_address, #delivery_address").find("option").remove();
						if (json != null) {
							if (json.error == null) {
								if (json.id_address == 0) {
									$("#invoice_address, #delivery_address").append(\'<option value="0">-- ' . $this->l('No address') . ' --</option>\');
									$("#invoice_address, #delivery_address").attr("disabled", "disabled");
								} else {
									$.each(json, function(key, address) {
										$("#invoice_address, #delivery_address").append(\'<option value="\' + address.id_address + \'">\' + address.alias + \': \' + address.address1 + \', \' + address.postcode + \' \' + address.city + \' [id:\' + address.id_address + \']</option>\');
									});
									$("#invoice_address, #delivery_address").removeAttr("disabled");
								}
							} else {
								alert("' . $this->l('AJAX Error: Please refresh the page. Your cookie has expired.') . '");
							}
						}
					},
					complete: function() {
						$("#updateCustomer .loading-address").hide();
					},
					error: function(msg) {
						alert("' . $this->l('AJAX Error: Could not load products') . '");
					}
				});
			});
			</script>
		');
		$html->add_html('<p>' . $this->l('Change the orders customer to this customer. A new invoice and delivery address must be specified below or else this update won\'t go through.') . '</p>');
		$html->select_end();
		
		$html->select_start( $this->l('Invoice address'));
		$html->select($id = 'invoice_address', $name = 'Customer[id_address_invoice]', $multiple = false, $values = array('0' => '-- ' . $this->l('Choose customer first') . ' --'), $class = '', $style = 'float:left;', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = null, $disabled = true);
		$html->add_html('<div class="loading-address" style="display:none;float:left;margin: -3px 0 3px 10px;"><img src="' . _PS_IMG_ . 'loader.gif" alt="" /></div>');
		$html->add_html('<p style="clear:both;">' . $this->l('Select the orders new invoice address.') . '</p>');
		$html->select_end();
		
		$html->select_start( $this->l('Delivery address') );
		$html->select($id = 'delivery_address', $name = 'Customer[id_address_delivery]', $multiple = false, $values = array('0' => '-- ' . $this->l('Choose customer first') . ' --'), $class = '', $style = 'float:left;', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = null, $disabled = true);
		$html->add_html('<div class="loading-address" style="display:none;float:left;margin: -3px 0 3px 10px;"><img src="' . _PS_IMG_ . 'loader.gif" alt="" /></div>');
		$html->add_html('<p style="clear:both;">' . $this->l('Select the orders new invoice address.') . '</p>');
		$html->select_end();
		
		$html->fieldset_end();
		/* END CUSTOMER */
		
		/* PAYMENT METHOD */
		$html->add_html('<fieldset id="updatePaymentMethod" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Payment method') . '</legend>');
		$html->select_start( $this->l('Payment method') );
		$paymentMethods = PaymentModule::getInstalledPaymentModules();
		$html->select($id = 'paymentMethod', $name = 'PaymentMethod', $multiple = false, $values = $paymentMethods, $class = '', $style = '', $alt_value = 'name', $alt_option = 'name', $selected = "", $default_option = false, $help = $this->l('Choose the new payment method for the orders.'), $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END PAYMENT METHOD */
		
		/* GIFT WRAPPING */
		$html->add_html('<fieldset id="updateGiftWrapping" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Gift wrapping') . '</legend>');
		$html->select_start( $this->l('Gift wrapping') );
		$html->select($id = 'giftWrapping', $name = 'GiftWrapping', $multiple = false, $values = array('1' => $this->l('Yes'), '0' => $this->l('No')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = null, $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END GIFT WRAPPING */
		
		/* RECYCLE PACKAGING */
		$html->add_html('<fieldset id="updateRecyclePackaging" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Recyclable packaging') . '</legend>');
		$html->select_start( $this->l('Recyclable packaging') );
		$html->select($id = 'recyclePackaging', $name = 'RecyclePackaging', $multiple = false, $values = array('1' => $this->l('Yes'), '0' => $this->l('No')), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = null, $disabled = false);
		$html->select_end();
		$html->fieldset_end();
		/* END RECYCLE PACKAGING */
		
		/* ORDER MESSAGE */
		$html->add_html('<fieldset id="updateOrderMessage" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Order message') . '</legend>');
		$html->select_start( $this->l('Standard message') );
		$html->add_html('<select name="updateValues[OrderMessage][premade]" id="orderMessagePremade" onchange="$(\'#orderMessage\').html(this.value);"><option value="" selected="selected">-- '.$this->l('Choose a standard message').' --</option>');
		$orderMessages = OrderMessage::getOrderMessages((int)($cookie->id_lang));
		foreach ($orderMessages AS $orderMessage) {
			$html->add_html('<option value="'.htmlentities($orderMessage['message'], ENT_COMPAT, 'UTF-8').'">'.$orderMessage['name'].'</option>');
		}
		$html->add_html('</select>');
		$html->select_end();
		$html->input_start( $this->l('Display to customer?') );
		$html->input($type = 'radio', $id = 'orderMessagePrivate', $name = 'OrderMessage[private]', $value = '0');
		$html->add_html(" " . $this->l('Yes') . " ");
		$html->input($type = 'radio', $id = 'orderMessagePrivate', $name = 'OrderMessage[private]', $value = '1', $class = '', $style = '', $selected = true);
		$html->add_html(" " . $this->l('No') );
		$html->input_end();
		$html->textarea_start( $this->l('New order message') );
		$html->textarea($id = 'orderMessage', $name = 'OrderMessage[message]', $content = '', $class = '', $style = 'width:300px;height:150px;', $disabled = false, $readonly = false, $help = null);
		$html->textarea_end();
		$html->fieldset_end();
		/* END ORDER MESSAGE */
		
		/* TOTAL PAID REAL */
		$html->add_html('<fieldset id="updateTotalPaidReal" style="display:none;font-size:13px">');
		$html->add_html('<legend>' . $this->l('Total paid (real)') . '</legend>');
		$html->input_start($this->l('Total paid (real)'));
		$html->input($type = 'text', $id = 'totalPaidReal', $name = 'TotalPaidReal', $value = '', $class = '', $style = '', $selected = false, $disabled = false, $onClick = false, $help = null, $onKeyUp = false);
		$currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT', 1));
		$html->add_html(' ' . $currency->iso_code);
		$html->add_html('<p>' . $this->l('Update the total amount paid (real) in the orders. If any of the orders were payed in another curency, the amount will be converted to that currency.') . '</p>');	
		$html->input_end();
		$html->fieldset_end();
		/* END TOTAL PAID REAL */
		
		$html->group_fields(false);
		
		$html->fieldset_end();
		
		$html->fieldset_start( $this->l('Step 3: Preview and submit') );
		$html->input_start( $this->l('Ignore these orders') );
		$html->input($type = "text", $id = "ignoreOrders", $name = "filterValues[IgnoreOrders]", $value = '', $class = '', $style = 'width:200px;', $selected = false, $disabled = false, $onClick = false, $help = $this->l('This must be a comma separated list of order IDs.'), $onKeyUp = false);
		$html->input_end();
		$html->input_start( $this->l('Orders that will be updated and update status') );
		$html->add_html('<div id="update_status" style="padding:5px;background-color:#fff;overflow-y:auto;border:1px solid #E0D0B1;width:500px;height:150px;font-size:12px;float:left;">' . $this->l('Press the refresh button to see which orders will be updated. Refresh this list when you change any of your filters.') . '</div>');
		$html->input($type = 'submit', $id = 'previewOrders', $name = 'previewOrders', $value = $this->l('Refresh list'), $class = 'button', $style = 'margin-left:10px;float:left;', $selected = false, $disabled = false, $onClick = "$('#action').val('preview');", $help = null, $onKeyUp = false);
		$html->add_html('<div id="refreshing-orders" style="display:none;margin-left:5px;float:left;"><img src="/img/loader.gif" alt=""></div>');
		$html->input_end();
		
		$html->input_start('&nbsp;', '', 'clear:both;');
		$html->input($type = 'submit', $id = 'updateOrders', $name = 'updateOrders', $value = $this->l('Update orders'), $class = 'button', $style = 'float:left;', $selected = false, $disabled = false, $onClick = "if (confirm('" . $this->l('Are you sure you want to perform this update? It is irreversible.') . "')) { $('#action').val('update'); return true; } else { return false; }", $help = null, $onKeyUp = false);
		$html->add_html('<div id="updating-orders" style="display:none;margin-left:5px;float:left;"><img src="/img/loader.gif" alt=""></div>');
		$html->fieldset_end();
		
		$html->form_end();
		
		$html->add_html('
			<script type="text/javascript">
				$("#orderBulkUpdate").submit( function(e) {
					var statusTimer;
					$("#inputProducts #products").find("option").attr("selected", "selected");
					var action = $("#action").val();
					$.ajax({
						url: "' . $this->_path . 'bulk-update.php",
						type: "POST",
						dataType: "json",
						data: $(this).serialize(),
						beforeSend: function() {
							if (action == "preview") {
								$("#refreshing-orders").show();
							}
							$("#update_status").html("");');
		if (is_writable(dirname(__FILE__).'/bulk-update.txt')) {					
			$html->add_html('
							if (action == "update") {
								$("#updating-orders").show();
								statusTimer = setInterval( function() {
									getStatus()
								}, 500);
							}');
		}	
		$html->add_html('
						},
						success: function(json) {
							if (action == "preview") {
								if (json != null) {
									if (json.empty == null) {
										$("#update_status").prepend("<b>" + json.length + "</b> ' . $this->l('order(s) matched your filter criteria') . '<br /><br />");
										$.each(json, function(key, order) {
											$("#update_status").append("<span style=\"font-size:14px;font-weight:bold;\">#" + order.id_order + "</span> ' . $this->l('by') . ' " + order.firstname + " " + order.lastname + " ' . $this->l('for') . ' " + order.total_amount + " " + order.iso_code + " (" + order.date_add + ")<br />");
										});
									} else {
										$("#update_status").html(\'<span style="color:red;">' . $this->l('No orders matched your filter criteria') . '</span>\');
									}
								}
							} else if (action == "update") {');
		if (!is_writable(dirname(__FILE__).'/bulk-update.txt')) {					
			$html->add_html('$("#update_status").prepend("<span style=\"color:green;\">' . $this->l('The orders have been updated') . '</span><br />");');
		}
		$html->add_html('					}
						},
						error: function() {
							alert("' . $this->l('AJAX Error: Could not perform the bulk update') . '");
						},
						complete: function() {
							$("#refreshing-orders").hide();
							setTimeout( function() {
								$("#bulkUpdateRunning").hide();
								$("#updating-orders").hide();
								clearInterval(statusTimer);
							}, 1000);
						}
					});
					e.preventDefault();
				});
				
				function getStatus() {
					$.ajax({
						url: "' . $this->_path . 'bulk-update.php",
						type: "GET",
						data: {
							action: 		"get-status",
							id_employee:	' . $cookie->id_employee . ',
							passwd:			"' . $cookie->passwd . '"
						},
						success: function(data) {
							$("#update_status").prepend(data);
						},
						error: function() {
							alert("error");
						}
					});
				}
			</script>
		');
	}
	
	
}