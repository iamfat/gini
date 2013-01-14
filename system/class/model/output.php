<?php

namespace Model {

	class Output {

		public static $AJAX = array();
		
		static function setup(){}
		
		static function AJAX(){
			if(sizeof($_FILES)>0){
				header('Content-Type: text/html; charset=utf-8');
				echo '<textarea>'.htmlentities(json_encode(Output::$AJAX)).'</textarea>';
			}else{
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(Output::$AJAX);
			}
		}
		
		static function H($str){
			return htmlentities(iconv('UTF-8', 'UTF-8//IGNORE', $str), ENT_QUOTES, 'UTF-8');
		}

		static function encode($str){
			return rawurlencode($str);
		}
		
	}

}

namespace {
	
	function H($str){
		return \Model\Output::H($str);
	}

	function eH($str) {
		echo H($str);
	}

	function e($str) {
		echo $str;
	}
}
	