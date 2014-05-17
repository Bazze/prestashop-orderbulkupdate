<?php

class SNSolutionsHelper {
	
	private $html = "";
	private $group_fields = false;
	private $group_field_name = "form";
	private $echo = false;
	
	public function __construct($echo = false) {
		$this->echo = (bool)$echo;
	}
	
	public function add_html($html) {
		if ($this->echo) {
			echo $html;
		} else {
			$this->html .= $html;
		}
	}

	public function fieldset_start($legend, $id = null) {
		$this->add_html('<fieldset' . ($id != null ? ' id="' . $id . '"' : '') .'>');
		$this->add_html('<legend>' . $legend . '</legend>');
	}
	
	public function fieldset_end() {
		$this->add_html('</fieldset>');
	}
	
	public function form_start($id, $name, $action, $method = 'post', $enctype = NULL, $other = '') {
		$this->add_html('<form id="' . $id . '" name="' . $name . '" action="' . $action . '" method="' . $method . '"' . ($enctype != NULL ? ' enctype="' . $enctype . '"' : '') . $other . '>');
	}
	
	public function form_end() {
		$this->add_html('</form>');
	}
	
	public function input_start($label, $class = '', $style = '') {
		$this->add_html('<label>' . $label . '</label>');
		$this->add_html('<div class="margin-form ' . $class . '" ' . ($style != "" ? 'style="' . $style . '"' : '') . '>');
	}
	
	public function input($type, $id, $name, $value = '', $class = '', $style = '', $selected = false, $disabled = false, $onClick = false, $help = null, $onKeyUp = false) {
		$allowed_types = array('checkbox', 'text', 'password', 'radio', 'hidden', 'button', 'submit');
		if (in_array($type, $allowed_types)) {
			$this->add_html('<input id="' . $id . '" name="' . $this->group_field($name) . '" value="' . $value . '" ' . ($class != "" ? 'class="' . $class . '"' : '') . ' ' . ($style != "" ? 'style="' . $style . '"' : '') . ' type="' . $type . '"' . ($onClick !== false ? ' onClick="' . $onClick . '"' : '') . ($onKeyUp !== false ? ' onkeyup="' . $onKeyUp . '"' : '') . ($disabled ? ' disabled="disabled"' : ''));
			switch ($type) {
				case 'radio':
				case 'checkbox':
					if ($selected) {
						$this->add_html(' checked="checked"');
					}
				break;
			}
			$this->add_html(' />');
			if ($help !== NULL) {
				$this->add_html('<p class="clear">');
				$this->add_html($help);
				$this->add_html('</p>');
			}
		}
	}
	
	public function input_end() {
		$this->add_html('</div>');
	}
	
	public function select_start($label, $style = '') {
		$this->input_start($label, '', $style);
	}
	
	public function select($id, $name, $multiple = false, $values = array(), $class = '', $style = '', $alt_value = false, $alt_option = false, $selected = "", $default_option = false, $help = null, $disabled = false) {
		if (is_array($values)) {
			$this->add_html('<select id="' . $id . '"' . ($multiple !== false ? ' multiple="multiple"' : '') . ' name="' . $this->group_field($name) . '" ' . ($class != "" ? 'class="' . $class . '"' : '') . ' ' . ($style != "" ? 'style="' . $style . '"' : '') . ($disabled ? ' disabled="disabled"' : '') . '>');
			if ($default_option !== false) {
				foreach ($default_option as $key => $value) {
					$this->add_html('<option value="' . $key . '">' . $value . '</option>');
				}
			}
			foreach ($values as $value => $option) {
				$this->add_html('<option value= "' . ($alt_value === false ? $value : $option[$alt_value]) . '"');
				if (($alt_value === false && $value == $selected) || ($alt_value !== false && $option[$alt_value] == $selected)) {
					$this->add_html(' selected="selected"');
				}
				$this->add_html('>' . ($alt_option === false ? $option : $option[$alt_option]) . '</option>');
			}
			$this->add_html('</select>');
			if ($help !== NULL) {
				$this->add_html('<p>' . $help . '</p>');
			}
		}
	}
	
	public function select_end() {
		$this->input_end();
	}
	
	public function textarea_start($label, $class = '', $style = '') {
		$this->input_start($label, $class, $style);
	}
	
	public function textarea($id, $name, $content = '', $class = '', $style = '', $disabled = false, $readonly = false, $help = null) {
		$this->add_html('<textarea id="' . $id . '" name="' . $this->group_field($name) . '" ' . ($class != "" ? 'class="' . $class . '"' : '') . ' ' . ($style != "" ? 'style="' . $style . '"' : ''));
		if ($disabled) {
			$this->add_html(' disabled="disabled"');
		}
		if ($readonly) {
			$this->add_html(' readonly="readonly"');
		}
		$this->add_html('>');
		$this->add_html($content);
		$this->add_html('</textarea>');
		if ($help !== NULL) {
			$this->add_html('<p class="clear">');
			$this->add_html($help);
			$this->add_html('</p>');
		}
	}
	
	public function textarea_end() {
		$this->input_end();
	}
	
	public function table_start($class = 'table', $style = '', $cellspacing = 0, $cellpadding = 0) {
		$this->add_html('<table ' . ($class != "" ? 'class="' . $class . '"' : '') . ' cellspacing="' . $cellspacing . '" cellpadding="' . $cellpadding . '" ' . ($style != "" ? 'style="' . $style . '"' : '') . '>');
	}
	
	public function thead_start() {
		$this->add_html('<thead>');
		$this->tr_start();
	}
	
	public function th($content, $class = '', $style = '') {
		$this->add_html('<th ' . ($class != "" ? 'class="' . $class . '"' : '') . ' ' . ($style != "" ? 'style="' . $style . '"' : '') . '>' . $content . '</th>');
	}
	
	public function thead_end() {
		$this->tr_end();
		$this->add_html('</thead>');
	}
	
	public function tbody_start() {
		$this->add_html('<tbody>');
	}
	
	public function td($content, $class = '', $style = '', $colspan = false) {
		$this->add_html('<td ' . ($class != "" ? 'class="' . $class . '"' : '') . ' ' . ($style != "" ? 'style="' . $style . '"' : '') . ($colspan !== false ? ' colspan="' . $colspan . '"' : '') . '>' . $content . '</td>');
	}
	
	public function tbody_end() {
		$this->add_html('</tbody>');
	}
	
	public function tr_start() {
		$this->add_html('<tr>');
	}
	
	public function tr_end() {
		$this->add_html('</tr>');
	}
	
	public function table_end() {
		$this->add_html('</table>');
	}
	
	public function group_field($name) {
		if ($this->group_fields) {
			$name = $this->group_field_name . '[' . (($pos = strpos($name, '[')) === false ? $name : mb_substr($name, 0, $pos, 'utf-8')) . ']' . ($pos !== false ? mb_substr($name, $pos, strlen($name)-1, 'utf-8') : '');
		}
		return $name;
	}
	
	public function group_fields($status) {
		$this->group_fields = $status;
		if ($status == false) {
			$this->set_group_field_name('form');
		}
	}
	
	public function set_group_field_name($name) {
		$this->group_field_name = $name;
	}
	
	public function get_html() {
		return $this->html;
	}
	
	public function flush_html() {
		$this->html = "";
	}

}