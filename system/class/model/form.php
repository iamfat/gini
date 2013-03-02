<?php

class Form extends ArrayIterator {
	
	static function hidden($name, $value, $extra=NULL) {
		if ($extra != '') $extra = ' '.$extra;
		return '<input type="hidden" name="'.H($name).'" value="'.H($value).'"'.$extra.' />';
	}


	protected static function _dropdown_item($key, $selected, $val) {
		$key = (string) $key;
		$val = (string) $val;

		$sel = (in_array($key, $selected))?' selected="selected"':'';

		return '<option value="'.$key.'"'.$sel.'>'.$val."</option>";
	}

	static function dropdown($name, $options, $selected=NULL, $extra=NULL) {
		if ( ! is_array($selected))
		{
			$selected = array((string)$selected);
		}

		if ($extra != '') $extra = ' '.$extra;

		$multiple = (count($selected) > 1 && strpos($extra, 'multiple') === FALSE) ? ' multiple="multiple"' : '';

		$form = '<select name="'.$name.'"'.$extra.$multiple.'>';
	
		foreach ($options as $key => $val)
		{
			if (is_array($val)) {
				$form .= '<optgroup label="'.strval($key).'">';
				foreach ($val as $k=>$v) {
					$form .= self::_dropdown_item($k, $selected, $v);
				}
				$form .= '</optgroup>';
			}
			else {
				$form .= self::_dropdown_item($key, $selected, $val);
			}
		}

		$form .= '</select>';

		return $form;
	}
	
	static function checkbox($name, $checked=FALSE, $label=NULL, $extra=NULL, $extra_label=NULL) {
		
		if (preg_match('/\bid\s*=\s*(["\'])(.+?)\1/', $extra, $parts)) {
			$rel_id = $parts[2];
		}
		else {
			$rel_id = 'checkbox'.uniqid();
		}
		
		$value = $checked ? ' checked="true"':'';
	
		if ($extra != '') $extra = ' '.$extra;

		$form = '<input id="'.$rel_id.'" name="'.$name.'" type="checkbox"'.$value.$extra.'/>';
		
		if($label) {
			if ($extra_label != '') $extra_label = ' '.$extra_label;
			$form .=' <label for="'.$rel_id.'"'.$extra_label.'>'.$label.'</label>';
		}
		
		return $form;
	}
	
	static function radio($name, $value=NULL, $selected=NULL, $label=NULL, $extra=NULL, $extra_label=NULL) {
		
		if (preg_match('/\bid\s*=\s*(["\'])(.+?)\1/', $extra, $parts)) {
			$rel_id = $parts[2];
		}
		else {
			$rel_id = 'radio'.uniqid();
		}
		
		if ($extra != '') $extra = ' '.$extra;
		$extra .= $value === NULL ? '':' value="'.H($value).'"';
		$extra .= ($selected == $value) ? ' checked="true"':'';
		
		$form = '<input id="'.$rel_id.'" name="'.$name.'" type="radio"'.$extra.'/>';
		
		if($label) {
			if ($extra_label != '') $extra_label = ' '.$extra_label;
			$form .=' <label for="'.$rel_id.'"'.$extra_label.'>'.$label.'</label>';
		}
		
		return $form;
	}
	
	static function filter($form) {
		return new Form($form);
	}

	public $no_error = TRUE;
	public $errors = array();
	
	function set_error($key, $error) {
		$this->no_error = FALSE;
		$this->errors[$key][] = $error;
	}
	
	function validate() {
		$args = func_get_args();
		$key = array_shift($args);
		$error = array_pop($args);
		foreach($args as $arg) {
			preg_match('/^\s*(\w+)\s*(?:\((\s*.+?\s*)\))?\s*$/', $arg, $parts);
			$method = 'validate_'.$parts[1];
			if(method_exists($this, $method)) {
				call_user_func(array($this, $method), $key, $error, $parts[2]);
			}
		}
		return $this;
	}
		
	protected function validate_compare($key, $error, $params){
		if ($params) {
			//如何匹配 ===，>==,!==等等
			if (0 == preg_match('/^\s*(!=|==|\^=|\$=|\*=|<=|>=|>|<|)\s*(\w+)\s*$/', $params, $parts)) return;
			
			$key2 = $parts[2]; 
		
		 	$op = $parts[1] ?: '==';	
		 	
		 	$val1 = isset($this[$key]) ? $this[$key] : $key;
		 	$val2 = isset($this[$key2]) ? $this[$key2] : $key2;
		 	
			switch($op) {
			case '>':
				$rs = ($val1 > $val2);
				break;
			case '<':
				$rs = ($val1 < $val2);
				break;
			case '!=':
				$rs = ($val1 != $val2);
				break;
			case '<=':
				$rs = ($val1 <= $val2);
				break;
			case '>=':
				$rs = ($val1 >= $val2);
				break;
			case '^=':
				$rs = 0 < preg_match('/^'.preg_quote($val2).'/', $val1);
				break;
			case '$=':
				$rs = 0 < preg_match('/'.preg_quote($val2).'$/', $val1);
				break;
			case '*=':
				$rs = 0 < preg_match('/'.preg_quote($val2).'/', $val1);
				break;
			default:
				$rs = ($val1 == $val2);
			}
			
			if (!$rs) {
				$this->no_error = FALSE;
				$this->errors[$key][]= $error ?: T('匹配失败');
			}
		}
	}
	
	protected function validate_is_token($key, $error, $params){
		$p = preg_match('/^[A-z0-9][A-z0-9_.-@]+(\|\w+)?$/', $this[$key]);
		if(!$p){
			$this->no_error = FALSE;
			$this->errors[$key][]= $error ?: T('不合法');
		}
	}
	
	protected function validate_not_empty($key, $error, $params){
		if(empty($this[$key]) || !isset($this[$key]) || is_null($this[$key]) || trim($this[$key])==''){
			$this->no_error = FALSE;
			$this->errors[$key][]= $error ?: T('不能为空');
		}
	}
	
	protected function validate_number($key, $error, $params){

		$this->validate_is_numeric($key, $error, $params);
		if(!isset($this->errors[$key])){
			$this->validate_compare($key, $error, $params);
		}
	}
		
	protected function validate_is_numeric($key, $error, $params){
		if(!is_numeric($this[$key])){
			$this->no_error = FALSE;
			$this->errors[$key][]= $error ?: T('请填写数字');
		}
	}

	protected function validate_is_email($key, $error, $params) {
		if(! preg_match(
			"/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/",
			$this[$key] ) ){
			$this->no_error = FALSE;
			$this->errors[$key][]= $error ?: T('非法的Email格式');
		}
	}
	
	// length(10), length(5,10), length(20, -1), length(-1, 10)
	protected function validate_length($key, $error, $params) {
		$arr = explode(',', $params);
		/*
		当length有一个参数时（v1）：限制该字符串(le)长度是:le=v1
		当length有两个参数时（v1，v2）：
			其中：
				当v1、v2都大于0时：该字符串长度限制是:
					其中：
						如果v1>v2:v2<le<v1
						如果v<v2:v1<le<v2
				当v1>0,v2<=0时：该字符串长度限制是:le>=20
				当v1<=0,v2>0时：该字符串长度限制是:le<=10
		*/
		if (!empty($arr)) {
			$v1 = (int) $arr[0];
			$v2 = isset($arr[1]) ? (int)$arr[1] : $v1;
			$no_min = $no_max = FALSE;
			if ($v1 <= 0) $no_min = TRUE;
			if ($v2 <=0 ) $no_max = TRUE;
			
			if (!$no_min && !$no_max && $v1>$v2) {
				$tmp = $v1;
				$v1 = $v2;
				$v2 = $tmp;
			}
			
			$str_len = strlen($this[$key]);
			if ((!$no_min && $str_len < $v1) || (!$no_max && $str_len > $v2)) {
				$this->no_error = FALSE;
				$this->errors[$key][]= $error ?: T('请填写规定长度的信息');
			}
		}
	}

}
