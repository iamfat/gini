<?php

function Q($selector, $context=NULL, $db=NULL){
	
	if($context === NULL && $selector instanceof Q){
		//复制 Q 对象
		return clone $selector;
	}
		
	if($context instanceof ORM_Model) {
		$context = new Q($context, $db);
	}
	
	if($context instanceof Q){
		return $context->find($selector);
	}

	return new Q($selector, $db);
}

abstract class _Q extends ORM_Iterator {
	
	public static $operators=array();
	public static $pseudo_filters=array();

	private $selector;
	private $prevQ;
	
	static function setup() {

	}
	
	function __construct($selector, $db=NULL){

		$this->db = $db instanceof Database ? $db : Database::factory($db);

		if($selector instanceof ORM_Model){
			$this->objects=array($selector->id=>$selector);
			$this->count = $this->length = 1;
			$this->name = $selector->name();
			$this->selector = $this->name.'#'.$selector->id;
			$this->current_id = $selector->id;
			//不需要再查询
			$this->set_query('*', TRUE);
		}
		//object:new 新建空数据对象
		elseif (preg_match(Q_Query::PATTERN_EMPTY, $selector, $parts)) {
			$this->objects = array();
			$this->current_id = 0;
			$this->count = $this->length = 0;
			$this->name = $parts[1];
			$this->selector = $selector;
			//不需要再查询
			$this->set_query('*', TRUE);
		}
		else {
			$this->selector = $selector;
		}

	}

	static function quote($s) {
		if(is_array($s)){
			foreach($s as &$i){
				$i = Q::quote($i);
			}			
			return implode(',', $s);
		} 
		elseif ( is_bool($s) || is_numeric($s) ) {
			return $s;
		} 
		elseif ( is_null($s) ) {
			return '';
		}
		
		return '"'.addcslashes($s, Q_Query::ESCAPE_CHARS).'"';
	}

	private $_is_parsed = FALSE;
	function parse() {
		if (!$this->_is_parsed) {
			$cache_key = 'Q:'.Misc::key($this->selector);
			$cache = Cache::factory('memcache');
			if (Config::get('debug.Q_nocache', FALSE) 
				|| NULL === ($cache_data = $cache->get($cache_key))) {
				$query = $this->parse_selector();
				$cache_data = array(
					'name' => $query->name,
					'SQL' => $query->SQL,
					'count_SQL' => $query->count_SQL,
					'sum_SQL' => 'SELECT SUM(`'.$query->table.'`.`%name`) FROM '.$query->from_SQL,
				);
				$cache->set($cache_key,	$cache_data);
			}
			$this->name = $cache_data['name'];
			$this->SQL = $cache_data['SQL'];
			$this->count_SQL = $cache_data['count_SQL'];
			$this->sum_SQL = $cache_data['sum_SQL'];

			$this->_is_parsed = TRUE;
		}
	}	

	protected function check_query($scope='fetch') {
		$this->parse();
		return parent::check_query($scope);			
	}
		
	function parse_selector(){

		$query = new Q_Query($this->db);
		$query->parse_selector($this->selector);
		$query->makeSQL();

		return $query;
	}
	
	private function push_stack($selector, $type, $Q=NULL){
		switch($type){
		case 'filter':
			$selector=$this->selector.$selector;
			break;
		case 'find':
			if(preg_match(Q_Query::PATTERN_NAME, $selector, $matches)) {
				$selector=$this->selector.' '.$selector;
			} else {
				$selector=$this->selector.$selector;
			}
			break;
		default:
			$selector=$this->selector.':'.$type.'('.$selector.')';
		}

		if ($Q) {
			$Q->selector = Q::rewrite_selector($selector, $selector_objects);
		} 
		else {
			$Q = new Q($selector, $this->db);
		}

		$Q->prevQ=$this;
		
		return $Q;
	}

	function limit() {
		$args = func_get_args();
		$args = array_slice($args, 0, 2);
		return $this->push_stack(implode(',', $args), 'limit');
	}

	function find($selector){
		return $this->push_stack($selector, 'find');
	}
	
	//TODO: 添加过滤 Q('user')->filter('[gender=female]') => Q('user:filter(user[gender=female])')
	function filter($selector){
		return $this->push_stack($selector, 'filter');
	}
	
	function not($selector){
		return $this->push_stack($selector, 'not');
	}
	
	function end(){
		return $this->prevQ;
	}
	
	function __get($name){
		$this->check_query();
		$object = $this->objects[$this->current_id];
		if(! $object){
			$object=reset($this->objects);
			$this->current_id = key($this->objects);
		}
		return $object->$name;
	}
	
	function __set($name, $value){
		$this->check_query();
		return $this->objects[$this->current_id]->$name=$value;
	}
	
	//和Q[$id]一样
	function eq($id=NULL){
		$this->check_query();
		if($id===NULL) return $this->objects[$this->current_id];
		return $this->objects[$id];
	}


	protected $sum_SQL;
	function sum($name) {
		$this->parse();
		$SQL = strtr($this->sum_SQL, array('%name'=>$this->db->escape($name)));	
		return $this->db->value($SQL);
	}

}


