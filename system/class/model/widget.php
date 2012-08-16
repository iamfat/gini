<?php

abstract class _Widget extends View{

	function __construct($name, $vars=NULL){
		list($category, $path) = explode(':', $name, 2);
		$name = $path ? $category.':widgets/'.$path : 'widgets/'.$category ;
		parent::__construct($name, $vars);
	}
	
	static function factory($name, $vars=NULL) {
		list($category, $path) = explode(':', $name, 2);

		if (!$path) {
			$path = $category;
			$category = NULL;
			$class_name = strtr($path, '/', '_').'_Widget';
			if (class_exists($class_name)) {
				return new $class_name($vars);
			}
		}
		
		return new Widget($name, $vars);
	}

}

