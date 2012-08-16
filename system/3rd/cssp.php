<?php

/* 
2010-11-06. version 0.2

CSS Patcher
http://css-patcher.geneegroup.com/
基于PHP的轻量级CSS解析器
作者: 黄嘉 (jia.huang@geneegroup.com)

本源码以MIT-LICENSE授权发布

Copyright (c) 2010 Jia Huang, http://css-patcher.geneegroup.com

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

class CSSP {
	
	static $css_prefix;
	
	static function node($type) {
		return new CSS_Node($type);
	}
	
	static function fragment($css) {
		
		$css = self::$css_prefix . $css;
	
		return new CSS_Fragment($css);
	}

	private static $_rule_patchers;
	static function register_rule_patcher($rule_name, $patcher, $params=NULL, $key=NULL) {
		if (is_array($rule_name)) {
			foreach ($rule_name as $r) {
				self::register_rule_patcher($r, $patcher, $params);
			}
		}
		else {
			if (!$key) $key = '*';
			if (!self::$_rule_patchers[$rule_name]) self::$_rule_patchers[$rule_name] = new ArrayIterator;
			self::$_rule_patchers[$rule_name][$key] = array(
				'handler' => $patcher,
				'params' => $params,
			);
		}
	}
	
	static function unregister_rule_patcher($rule_name, $key=NULL) {
		if (!$key) $key = '*';
		unset(self::$_rule_patchers[$rule_name][$key]);
	}
	
	static function rule_patchers($name) {
		$patchers = array();
		if (isset(self::$_rule_patchers['*']) && count(self::$_rule_patchers['*'])>0) {
			$patchers += (array) self::$_rule_patchers['*'];
		}
		if (isset(self::$_rule_patchers[$name]) && !self::$_rule_patchers[$name]->disabled) {
			$patchers += (array) self::$_rule_patchers[$name];
		}
		return $patchers;
	}

	static function disable_rule_patcher($name) {
		self::$_rule_patchers[$name]->disabled = TRUE;
	}

	static function enable_rule_patcher($name) {
		self::$_rule_patchers[$name]->disabled = FALSE;
	}

	const FORMAT_PATCH  = 0x0001;
	const FORMAT_MINIFY = 0x0002;
	const FORMAT_NOCOMMENTS = 0x0004;
	
	private static $_pseudo_handlers;
	static function register_pseudo_handler($name, $handler, $params=NULL) {
		if (is_array($name)) {
			foreach ($name as $n) {
				self::register_pseudo_handler($n, $handler, $params);
			}
		}
		else {
			self::$_pseudo_handlers[$name] = array(
				'handler' => $handler,
				'params' => $params
			);
		}
	}

	static function unregister_pseudo_handler($name) {
		unset(self::$_pseudo_handlers[$name]);
	}
	
	static function pseudo_handler($name) {
		return self::$_pseudo_handlers[$name];
	}
	
	
}

class CSS_Fragment {

	private $root;	// CSS_Node
	private $text;
	public $text_left;
	private $curr_pos = 0;
	
	function __construct($text) {
		$this->text = $text;
		$this->text_left = $text;
		$this->root = CSSP::node(CSS_Node::NODE_ROOT);
		$time = microtime(TRUE);
		$this->parse_content($this->root, self::PARSE_AT_RULE|self::PARSE_RULESET);
		$this->root->comment("源内容解析完成 耗时%0.5fs", microtime(TRUE) - $time);
	}

	function eof() {
		return 0 == mb_strlen($this->text_left);
	}

	function parse_comment($root) {
		if (!$this->chop('/^\/\*(.*?)\*\//us', $matches)) return FALSE;
		$node = CSSP::node(CSS_Node::NODE_COMMENT);
		$node->value = $matches[1];
		$root->append($node);
		return TRUE;
	}
	
	private function parse_at_rule($root) {
		if (!$this->chop('/^@([^{;]+?)\s*(\{|;)/us', $matches)) return FALSE;

		$bracket = $matches[2] == '{' ? TRUE : FALSE;

		//@rule如果是css-开头 则有可能是特殊命令
		if ( preg_match('/^(css-\w+)\b\s*(.*)\s*$/', $matches[1], $parts)) {
			$this->parse_pseudo_command($root, $parts[1], $parts[2], $bracket);
		}
		else {
			$at_rule = CSSP::node(CSS_Node::NODE_AT_RULE);
			$at_rule->name = $matches[1];
			if ($bracket) {
				$this->parse_content($at_rule);
				$this->skip_to('}', TRUE);
			}
			$this->trace($root);
			$root->append($at_rule);
		}
			
		return TRUE;
	}
	
	private function parse_pseudo_command($root, $command, $args, $bracket) {

		$pseudo = CSSP::pseudo_handler($command);
		if ($pseudo) {
			$handler = $pseudo['handler'];
			$params = $pseudo['params'];
			call_user_func($handler, $this, $root, $command, $args, $bracket, $params);
		}
		else {
			//无法找到相应的伪代码处理器 提示非法
			$root->comment('无法识别的伪命令%s', $command);
		}
		
		if ($bracket) $this->skip_to('}', TRUE);

	}
	
	private function parse_ruleset($parent) {
		if (!$this->chop('/^([\w\s-+>~,.:*#$()&]+?)\s*\{/us', $matches)) return FALSE;
			
		$ruleset = CSSP::node(CSS_Node::NODE_RULESET);
		$name = preg_replace('/[\s\n]+/',' ', $matches[1]);
		$name = $this->translate($name);
		
		//查找父节点中的CSS_Node::NODE_RULESET
		$n = $parent;
		$prefix = '';
		while ($n && $n != $n->root) {
			if ($n->type == CSS_Node::NODE_RULESET) {
				$prefix = explode(',', $n->name);
				break;
			}
			$n = $n->parent;
		}
		
		if ($prefix) {
			//如果有前缀 表示上级有ruleset节点
			
			$rname = array();
			foreach ($prefix as $p) {
				$rn = preg_replace('/(^|,\s*)/', '$1'.$p.' ', $name);
				$rn = preg_replace('/ &([:#.])/', '$1', $rn);
				$rname[] = $rn;
			}

			$ruleset->name = implode(', ', $rname);
			$parent = $n->parent;
	
		}
		else {
			$ruleset->name = $name;
		}
		
		$this->trace($parent);
		$parent->append($ruleset);
		
		$this->parse_content($ruleset);
		
		$this->skip_to('}', TRUE);

		$this->_rulesets_by_name[$ruleset->name][] = $ruleset;

		return TRUE;
	}
	
	private function parse_rule($root) {
		if (!$this->chop('/^([^{}:;]+)\s*:\s*([^{};]+)\s*(;|\})/us', $matches)) return FALSE;

		$name = $this->translate($matches[1]);
		$value = $this->translate($matches[2]);

		//是否存在针对该rule的patcher
		$patchers = CSSP::rule_patchers($name);
		if ($patchers) {
			foreach ((array) $patchers as $patcher) {
				$handler = $patcher['handler'];
				$params = $patcher['params'];
				call_user_func($handler, $this, $root, $name, $value, $params);
			}
		}
		else {
			$rule = CSSP::node(CSS_Node::NODE_RULE);
			$rule->name = $name;
			$rule->value = $value;
			$root->append($rule);
		}
		
		if ($matches[3] == '}') {
			$this->spit(1);
		}

		return TRUE;
	}
	
	const PARSE_AT_RULE = 0x01;
	const PARSE_RULESET = 0x02;
	const PARSE_RULE = 0x04;
	const PARSE_ALL = 0xff;
	
	function parse_content($root, $check_flag = self::PARSE_ALL) {

		while (!$this->eof() && !$this->comes('}')) {
			$got_something = FALSE;
			
			if (!$got_something) $this->skip_ws();
			$ret = $this->parse_comment($root);
			if ($ret) continue;
			$got_something |= $ret;
			
			if ($check_flag & self::PARSE_AT_RULE) {
				if (!$got_something) $this->skip_ws();
				$ret = $this->parse_at_rule($root);
				if ($ret) continue;
				$got_something |= $ret;
			}
			
			if ($check_flag & self::PARSE_RULESET) {
				if (!$got_something) $this->skip_ws();
				$ret = $this->parse_ruleset($root);
				if ($ret) continue;
				$got_something |= $ret;
			}
			
			if ($check_flag & self::PARSE_RULE) {
				if (!$got_something) $this->skip_ws();
				$ret = $this->parse_rule($root);
				if ($ret) continue;
				$got_something |= $ret;
			}
			
			if (!$got_something) break;
		}

	}
	
	function curr_pos($pos=NULL) {
		if ($pos === NULL) return $this->curr_pos;
		$this->curr_pos = $pos;
		$this->text_left = mb_substr($this->text, $this->curr_pos);
	}
	
	private $_marker_stack = array();
	function push_marker() {
		array_push($this->_marker_stack, $this->curr_pos);
		array_push($this->_vars_list, $this->_vars);
	}
	
	function restore_marker() {
		if (count($this->_marker_stack) > 0) {
			$this->curr_pos = end($this->_marker_stack);
			$this->text_left = mb_substr($this->text, $this->curr_pos);
		}
	}
	
	function pop_marker() {
		if (count($this->_marker_stack) > 0) {
			@array_pop($this->_marker_stack);
			$this->_vars = (array) @array_pop($this->_vars_list);
		}
	}
	
	private function spit($num) {
		$this->curr_pos -= $num;
		if ($this->curr_pos < 0) $this->curr_pos = 0;
		$this->text_left = mb_substr($this->text, $this->curr_pos);
	}
	
	private function chop($pattern, &$matches) {
		$ret = preg_match($pattern, $this->text_left, $matches);
		if ($ret) {
			$this->curr_pos += mb_strlen($matches[0]);
			$this->text_left = mb_substr($this->text, $this->curr_pos);
		}
		return $ret;
	}
	
	private function skip_ws() {
		$ret = preg_match('/^[\s\n]+/us', $this->text_left, $matches);
		if ($ret) {
			$this->curr_pos += mb_strlen($matches[0]);
			$this->text_left = mb_substr($this->text, $this->curr_pos);
		}
		return $ret;
	}
	
	private function comes($str) {
		$str_in_text = mb_substr($this->text_left, 0, mb_strlen($str));
		return $str_in_text == $str;
	}
	
	function skip_to($str, $include_str = FALSE) {
		$pos = mb_strpos($this->text_left, $str);
		if ($pos === FALSE) {
			$this->curr_pos = mb_strlen($this->text);
			$this->text_left = '';
		}
		else {
			$this->curr_pos += $pos;
			if ($include_str) {
				$this->curr_pos += mb_strlen($str);
			}
			$this->text_left = mb_substr($this->text, $this->curr_pos);
		}
	}
	
	private $_rulesets_by_name;
	function rulesets_by_name($name) {
		return @$this->_rulesets_by_name[$name];
	}

	private $_vars_list = array();
	private $_vars;
	function set_var($name, $value) {
		if ($value === NULL) {
			unset($this->_vars[$name]);
		}
		else {
			$this->_vars[$name] = $value;
		}
	}
	
	function get_var($name) {
		return $this->_vars[$name];
	}
	
	private function _translate($matches) {
		$name = strval($matches[1]===''?$matches[2]:$matches[1]);
		$value = @$this->_vars[$name];
		if (is_scalar($value)) return $value;
		return $matches[0];
	}
	
	private function _calc($matches) {
		list(,$n1,$op, $n2)=$matches;
		switch ($op) {
		case '*':
			return $n1 * $n2;
		case '/':
			return $n1 / $n2;
		case '+':
			return $n1 + $n2;
		case '-':
			return $n1 - $n2;
		}
		return $matches[0];
	}
	
	function translate($str) {
		$str = preg_replace_callback('/\$\(([a-z0-9-_]+)\)|\$([a-z0-9-_]+)/', array($this, '_translate'), $str);

		/* * & / */
		while (TRUE) {
			$new_str = preg_replace_callback('/(-?\d+(?:\.\d+)?)\s*([\*\/])\s*(-?\d+(?:\.\d+)?)/', array($this, '_calc'), $str);
			if ($new_str == $str) break;
			$str = $new_str;
		}
		
		/* + & - */
		while (TRUE) {
			$new_str = preg_replace_callback('/(-?\d+(?:\.\d+)?)\s*([+-])\s*(-?\d+(?:\.\d+)?)/', array($this, '_calc'), $str);
			if ($new_str == $str) break;
			$str = $new_str;
		}

		return $str;
	}

	function trace($root) {
		$args = func_get_args();
		array_shift($args);
		if (count($args) > 0) {
			$str = call_user_func_array('sprintf', $args);
		}
		else {
			$str = '';
		}
		
		// 检查当前所在的行号
		$line_no = 1 + preg_match_all('/\r\n|\n/', mb_substr($this->text, 0, $this->curr_pos), $matches);
		$root->comment('解析第%d行%s', $line_no, $str ? ': '.$str:'');
	}

	function format($mode = 0) {
		return $this->root->format($mode);
	}
	
}

class CSS_Node {

	const NODE_ROOT = 1;
	const NODE_RULESET = 2;
	const NODE_RULE = 3;
	const NODE_AT_RULE = 4;
	const NODE_COMMENT = 5;

	private $uniqid;

	public $type;
	public $root;
	public $parent;
	public $children;
	public $name;
	public $value;
	
	function __construct($type, $parent=NULL) {
		$this->type = $type;
		$this->uniqid = uniqid();
		if ($parent) {
			$parent->append($this);
		}
		else {
			$this->parent = NULL;
			$this->root = $this;
		}
	}

	function append($node) {
		$this->children[$node->uniqid] = $node;
		$node->parent = $this;
		$node->update_root();
	}
	
	function update_root() {
		$parent = $this->parent;
		if ($parent) {
			$this->root = $parent->root;
		}
		else {
			$this->root = $this;
		}
	}
	
	function detach() {
		$parent = $this->parent;
		if ($parent) {
			unset($parent->children[$this->uniqid]);
		}
	}
	
	function remove($node) {
		unset($this->children[$node->uniqid]);
	}
	
	function before($node) {
		$parent = $this->parent;
		if ($parent) {
			//把$node插入到$this之前
			$children = NULL;
			foreach ((array)$parent->children as $child) {
				if ($child->uniqid === $this->uniqid) {
					$children[$node->uniqid] = $node;
				}
				$children[$child->uniqid] = $child;
			}
			$parent->children = $children;
			$node->parent = $parent;
			$node->update_root();
		}
	}
	
	function after($node) {
		$parent = $this->parent;
		if ($parent) {
			//把$node插入到$this之前
			$children = NULL;
			foreach ((array)$parent->children as $child) {
				$children[$child->uniqid] = $child;
				if ($child->uniqid === $this->uniqid) {
					$children[$node->uniqid] = $node;
				}
			}
			$parent->children = $children;
			$node->parent = $parent;
			$node->update_root();
		}
	}
	
	// 为该节点添加注释
	function comment() {
		$args = func_get_args();
		$str = call_user_func_array('sprintf', $args);
		$str = implode("\n * ", explode("\n", $str));
		$comment = CSSP::node(CSS_Node::NODE_COMMENT);
		$comment->value = $str;
		$this->append($comment);
	}
	
	function format($mode = 0, $level = 0) {

		$no_comment = ($mode & CSSP::FORMAT_NOCOMMENTS);
		
		$newline = ($mode & CSSP::FORMAT_MINIFY) ? '':"\n";
		$indent = ($mode & CSSP::FORMAT_MINIFY) ? '':str_repeat("\t", $level);
		
		$output = '';
		
		switch ($this->type) {
		case CSS_Node::NODE_COMMENT:
			if ($no_comment) continue;
			$output .= $indent . sprintf("/* %s */", $this->value);
			break;
		case CSS_Node::NODE_AT_RULE:
			$has_child = (count($this->children) > 0);
			if ($has_child) {
				$output .= $indent . '@'.$this->name.($newline ? ' ':'').'{';
				$output .= $newline;
				foreach ((array) $this->children as $child) {
					$output .= $child->format($mode, $level + 1);
				}
				$output .= $indent . '} ';
				$output .= $newline;
			}
			else {
				$output .= $indent . '@'.$this->name.';';
				$output .= $newline ? $newline : ' ';
			}
			break;
		case CSS_Node::NODE_RULESET:
			$output = $indent . $this->name.($newline ? ' ':'').'{';
			$output .= $newline;
			foreach ((array) $this->children as $child) {
				$output .= $child->format($mode, $level + 1);
			}
			if (!$newline) {
				//省略最后的;
				$output = preg_replace('/;$/', '', $output);
			}
			$output .= $indent . '}';
			$output .= $newline;
			break;
		case CSS_Node::NODE_RULE: 
			$output = $indent . $this->name .':'. ($newline ? ' ':'') . $this->value .';';
			break;
		default:
			foreach ((array) $this->children as $child) {
				$output .= $child->format($mode, $level);
			}
		}

		$output .= $newline;

		return $output;
	}
	
}

class CSS_Rule_Helper {

	static function append($parent, $name, $value) {
		$nr = CSSP::node(CSS_Node::NODE_RULE);
		$nr->name = $name;
		$nr->value = $value;
		$parent->append($nr);
	}
/*	
	static function before($rule, $name, $value) {
		$nr = CSSP::node(CSS_Node::NODE_RULE);
		$nr->name = $name;
		$nr->value = $value;
		$rule->before($nr);
	}
	
	static function after($rule, $name, $value) {
		$nr = CSSP::node(CSS_Node::NODE_RULE);
		$nr->name = $name;
		$nr->value = $value;
		$rule->after($nr);
	}
*/	
}

class CSSP_BuildIn {
	
	static function patch_extends($fragment, $parent, $name, $value, $params) {		
		$rulesets = $fragment->rulesets_by_name($value);
		foreach ((array)$rulesets as $rs) {
			foreach ((array)$rs->children as $r) {
				if ($r->type == CSS_Node::NODE_RULE) {
					CSS_Rule_Helper::append($parent, $r->name, $r->value);
				}
			}
		}
	}

	static function pseudo_define ($fragment, $root, $command, $args, $bracket, $params) {
		if (!preg_match('/^([\w\-]+)\s*:\s*(.+)$/', $args, $matches)) {
			$fragment->trace($root, '格式错误: @css-define name: value;');
			return FALSE;
		}
		
		$name = $matches[1];
		$value = @json_decode($matches[2], TRUE);
		
		$fragment->set_var($name, $value);		
	}

	static function pseudo_foreach($fragment, $root, $command, $args, $bracket, $params) {

		if (!$bracket || !preg_match('/^\s*\(([\w-]+)\s+in\s+(.+)\)\s*$/', $args, $matches)) {
			$fragment->trace($root, '格式错误: @css-foreach(name in array){}');
			return FALSE;
		}
		
		$name = $matches[1];
		$arr_name = $matches[2];
		$arr = $fragment->get_var($arr_name);
		if (!is_array($arr)) $arr = (array) @json_decode($arr_name, TRUE);
		
		$fragment->push_marker();
		foreach ($arr as $value) {
			$fragment->restore_marker();
			$fragment->set_var($name, $value);
			$fragment->parse_content($root);
		}
		$fragment->pop_marker();
		
	}

	static function pseudo_for($fragment, $root, $command, $args, $bracket, $params) {
		/*
		@css-for(name = 1 to 10 step 2) {
		*/

		if (!$bracket || !preg_match('/^\s*\(\s*([\w-]+)\s*=\s*(\d+)\s*to\s*(\d+)\s*(?:step\s*(\d+)\s*)?\)\s*$/', $args, $matches)) {
			$fragment->trace($root, '格式错误: @css-for (name = 1 to 10 [step 1]){}');
			return FALSE;
		}

		$name = $matches[1];
		$from = $matches[2];
		$to = $matches[3];
		$step = $matches[4];
		if ($step === NULL) $step = 1;

		$fragment->push_marker();
		for ($i=$from; $i <= $to; $i+=$step) {
			$fragment->restore_marker();
			$fragment->set_var($name, $i);
			$fragment->parse_content($root);
		}
		$fragment->pop_marker();
		
	}

	static function rule_patch($fragment, $parent, $name, $value, $params) {
		
		$count = (int) $params['count'];
		$pos = (int) $params['rule_pos'];
		$args = explode(' ', $value);

		if ($count == 0 || $count == count($args)) {

			$patcher_key = $count ? "override_$count" : NULL;

			CSSP::disable_rule_patcher($name);		

			$fragment->push_marker();
			$fragment->curr_pos($pos);

			$fragment->set_var('name', $name);
			$fragment->set_var('0', $value);

			foreach ($args as $i => $arg) {
				$fragment->set_var($i+1, $arg);
			}
			
			$fragment->parse_content($parent);

			$fragment->restore_marker();
			$fragment->pop_marker();

			CSSP::enable_rule_patcher($name);

		}

	}
	
	static function pseudo_rule($fragment, $root, $command, $args, $bracket, $params) {
		/*
		 @css-rule name(4) {
		 	xxx: $1 $2 $3
		 }
		*/

		if (!$bracket || !preg_match('/^\s*([\w-]+)\s*(?:\(\s*(\d+)\s*\))?\s*$/', $args, $matches)) {
			$fragment->trace($root, '格式错误: @css-rule name(argc){}');
			return FALSE;
		}
		$name = $matches[1];
		$count = isset($matches[2]) ? (int) $matches[2]:0;

		//读取整个rule结构　存放在
		$params = array(
			'count' => $count,
			'rule_pos' => $fragment->curr_pos()
			);
		
		$patcher_key = $count ? "override_$count" : NULL;
		
		CSSP::register_rule_patcher($name, 'CSSP_BuildIn::rule_patch', $params, $patcher_key);

		$skip_node = CSSP::node(CSS_Node::NODE_RULESET);
		$fragment->parse_content($skip_node);

	}

	static function pseudo_test($fragment, $root, $command, $args, $bracket, $params) {
		/*
		 @css-test ($1 == 'xxx') {

		 }
		*/

		$expr = $fragment->translate($args);
		
		if (!$bracket || !preg_match('/^\(\s*([\'"]?)(.+?)\1\s*(==|!=|>|<|>=|<=)\s*([\'"]?)(.+)\4\)$/', $expr, $matches)) {
			$fragment->trace($root, '格式错误: @css-test val==val {}');
			return FALSE;
		}
		
		$n1 = $matches[2];
		$n2 = $matches[5];
		
		switch ($matches[3]) {
		case '==':
			$ret = $n1 == $n2;
			break;
		case '!=':
			$ret = $n1 != $n2;
			break;
		case '>=':
			$ret = $n1 >= $n2;
			break;
		case '<=':
			$ret = $n1 <= $n2;
			break;
		case '>':
			$ret = $n1 > $n2;
			break;
		case '<':
			$ret = $n1 < $n2;
			break;
		}
		
		
		if (!$ret) {		
			$root = CSSP::node(CSS_Node::NODE_RULESET);
		}
		
		$fragment->parse_content($root);
	}

	static function pseudo_case($fragment, $root, $command, $args, $bracket, $params) {

		$expr = $fragment->translate($args);
		
		if (!$bracket || !preg_match('/^([\'"]?)(.+)\1$/', $expr, $matches)) {
			$fragment->trace($root, '格式错误: @css-case "string" {}');
			return FALSE;
		}

		if ($params['switch_val'] == $matches[2]) {
			$params['case_hit'] = TRUE;
			CSSP::register_pseudo_handler('css-default', 'CSSP_BuildIn::pseudo_default', $params);
		}
		else {
			// 建立空节点
			$root = CSSP::node(CSS_Node::NODE_RULESET);
		}
		
		$fragment->parse_content($root);
	}
	
	static function pseudo_default($fragment, $root, $command, $args, $bracket, $params) {
		if (!$bracket || $args) {
			$fragment->trace($root, '格式错误: @css-default {}');
			return FALSE;
		}

		if (isset($params['case_hit'])) {
			// 建立空节点
			$root = CSSP::node(CSS_Node::NODE_RULESET);
		}
			
		$fragment->parse_content($root);
	}
	
	static function pseudo_switch($fragment, $root, $command, $args, $bracket, $params) {

		$expr = $fragment->translate($args);
		
		if (!$bracket || !preg_match('/^\(\s*([\'"]?)(.+)\1\s*\)$/', $expr, $matches)) {
			$fragment->trace($root, '格式错误: @css-switch (val) {}');
			return FALSE;
		}
		
		$params = array(
			'switch_val' => $matches[2]
			);
		
		CSSP::register_pseudo_handler('css-case', 'CSSP_BuildIn::pseudo_case', $params);
		CSSP::register_pseudo_handler('css-default', 'CSSP_BuildIn::pseudo_default', $params);
		$fragment->parse_content($root);

	}

	static function pseudo_patcher($fragment, $root, $command, $args, $bracket, $params) {
		/*
		 @css-patcher name {
		 }
		*/

		if (!$bracket || !preg_match('/^\s*([\w-]+)\s*$/', $args, $matches)) {
			$fragment->trace($root, '格式错误: @css-patcher name{}');
			return FALSE;
		}

		$name = $matches[1];
		
		$fragment->set_var($name, $fragment->curr_pos());	
		
		$skip_node = CSSP::node(CSS_Node::NODE_RULESET);
		$fragment->parse_content($skip_node);

	}

	static function do_patch($fragment, $parent, $name, $value, $params) {
		
		$pos = (int) $params['patcher_pos'];

		CSSP::unregister_rule_patcher('*', 'do_patch');		

		$fragment->push_marker();
		$fragment->curr_pos($pos);

		$fragment->set_var('name', $name);
		$fragment->set_var('value', $value);

		$fragment->parse_content($parent);

		$fragment->restore_marker();
		$fragment->pop_marker();

		CSSP::register_rule_patcher('*', 'CSSP_BuildIn::do_patch', $params, 'do_patch');		

	}

	static function pseudo_patch($fragment, $root, $command, $args, $bracket, $params) {
		/*
		 @css-patch name {
		 }
		*/

		if (!$bracket || !preg_match('/^\s*([\w-]+)\s*$/', $args, $matches)) {
			$fragment->trace($root, '格式错误: @css-patcher name{}');
			return FALSE;
		}

		$name = $matches[1];
		$pos = $fragment->get_var($name);

		if ($pos === NULL) {
			$fragment->trace($root, '未定义的补丁: '. $name);
			return FALSE;
		}

		CSSP::register_rule_patcher('*', 'CSSP_BuildIn::do_patch', array('patcher_pos'=>$pos), 'do_patch');
		$fragment->parse_content($root);
		CSSP::unregister_rule_patcher('*', 'do_patch');
	}
	
}

// 注册内置CSS Rule Patcher
CSSP::register_pseudo_handler('css-define', 'CSSP_BuildIn::pseudo_define');
CSSP::register_pseudo_handler('css-foreach', 'CSSP_BuildIn::pseudo_foreach');
CSSP::register_pseudo_handler('css-for', 'CSSP_BuildIn::pseudo_for');
CSSP::register_pseudo_handler('css-rule', 'CSSP_BuildIn::pseudo_rule');
CSSP::register_pseudo_handler('css-test', 'CSSP_BuildIn::pseudo_test');
CSSP::register_pseudo_handler('css-switch', 'CSSP_BuildIn::pseudo_switch');

CSSP::register_pseudo_handler('css-patcher', 'CSSP_BuildIn::pseudo_patcher');
CSSP::register_pseudo_handler('css-patch', 'CSSP_BuildIn::pseudo_patch');
CSSP::register_rule_patcher('extends', 'CSSP_BuildIn::patch_extends');

CSSP::$css_prefix = @file_get_contents(dirname(__FILE__).'/cssp_prefix.cssp');


