<?php


namespace ORM;

abstract class Object extends \Model\ORM {

	var $id = 'int,primary';
	var $_extra = 'array';

	static $_db;	// database object
	static $_db_index ; // database index,

}