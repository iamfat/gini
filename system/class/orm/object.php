<?php


namespace ORM;

class Object extends \Model\ORM {

	var $id = 'bigint,primary,serial';

	var $_extra = 'array';

	static $_db;	// database object
	static $_db_index ; // database index,

}
