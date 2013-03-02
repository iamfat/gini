<?php

namespace Model\Auth;

class Database implements \Model\Auth\Driver {

	private $db_name;
	private $table;
	private $options;

	function __construct(array $opt){

		$this->options = $opt;
		$this->db_name = $opt['database.name'];
		$this->table = $opt['database.table'] ?: '_auth';

		$db = \Model\Database::db($this->db_name);		
		$db->adjust_table(
			$this->table, 
			array(
				'fields' => array(
					'username'=>array('type'=>'varchar(80)', 'null'=>FALSE, 'default'=>''),
					'password'=>array('type'=>'varchar(100)', 'null'=>FALSE, 'default'=>''),				
				),
				'indexes' => array(
					'PRIMARY'=>array('type'=>'primary', 'fields'=>array('username')),
				),
				'engine' => $opt['database.engine']
			)
		);

	}
	
	private static function encode($password){
		// crypt SHA512
		$salt = '$6$'.\Model\Util::random_password(8, 2).'$';
		return crypt($password, $salt);
	}
	
	function verify($username, $password){
		$db = \Model\Database::db($this->db_name);
		$hash = $db->value('SELECT `password` FROM `%s` WHERE `username`="%s"', $this->table, $username);
		if ($hash) {
			return crypt($password, $hash) == $hash;
		}

		return FALSE;	
	}
	
	function change_password($username, $password){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('UPDATE `%s` SET `password`="%s" WHERE `username`="%s"', $this->table, self::encode($password), $username);
	}
	
	function change_username($username, $username_new){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('UPDATE `%s` SET `username`="%s" WHERE `username`="%s"', $this->table, $username_new, $username);
	}
	
	function add($username, $password){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('INSERT INTO `%s` (`username`, `password`) VALUES("%s", "%s")', $this->table, $username, self::encode($password));
	}
	
	function remove($username){
		$db = \Model\Database::db($this->db_name);
		return FALSE != $db->query('DELETE FROM `%s` WHERE `username`="%s"', $this->table, $username);
	}
	
}
