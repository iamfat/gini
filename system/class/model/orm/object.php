<?php


namespace Model\ORM;

abstract class Object extends \Model\ORM {

	var $id = 'int,primary';

	static $_db;	// database object
	static $_db_index ; // database index,

}