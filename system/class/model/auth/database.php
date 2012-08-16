<?php

namespace Model\Auth;

class Database implements \Model\Auth_Handler {

	private $db_name;
	private $table;
	private $options;

	function __construct(array $opt){

		$this->options = $opt;
		$this->db_name = $opt['database.name'];
		$this->table = $opt['database.table'] ?: '_auth';

		$db = \Model\Database::db($this->db_name);		
		$db->prepare_table(
			$this->table, 
			array(
				'fields' => array(
					'token'=>array('type'=>'varchar(80)', 'null'=>FALSE, 'default'=>''),
					'password'=>array('type'=>'varchar(100)', 'null'=>FALSE, 'default'=>''),				
				),
				'indexes' => array(
					'PRIMARY'=>array('type'=>'primary', 'fields'=>array('token')),
				),
				'engine' => $opt['database.engine']
			)
		);
	}
	
	private static function encode($password){
		// crypt SHA512
		$salt = '$6$'.Misc::random_password(8, 2).'$';
		return crypt($password, $salt);
	}
	
	function verify($token, $password){
		$db = \Model\Database::db($this->db_name);
		$hash = $db->value('SELECT `password` FROM `%s` WHERE `token`="%s"', $this->table, $token);
		if ($hash) {
			if ($hash[0] == '$') {	
				// crypt method
				return crypt($password, $hash) == $hash;
			}
			else {
				// old md5 method
				return $hash == md5($password) || $hash == md5('GINI_'.$password);
			}
		}

		return FALSE;	
	}
	
	function change_password($token, $password){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('UPDATE `%s` SET `password`="%s" WHERE `token`="%s"', $this->table, self::encode($password), $token);
	}
	
	function change_token($token, $token_new){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('UPDATE `%s` SET `token`="%s" WHERE `token`="%s"', $this->table, $token_new, $token);
	}
	
	function add($token, $password){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('INSERT INTO `%s` (`token`, `password`) VALUES("%s", "%s")', $this->table, $token, self::encode($password));
	}
	
	function remove($token){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('DELETE FROM `%s` WHERE `token`="%s"', $this->table, $token);
	}
	
}
