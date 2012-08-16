<?php

namespace GR\System\Model {

	TRY_DECLARE('\Model\Output', __FILE__);
	
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

		static function E($str){
			return rawurlencode($str);
		}
		
	}

}

namespace Model {

	if (DECLARED('\Model\Output', __FILE__)) {
		class Output extends \GR\System\Model\Output {}
	}

}

namespace {
	
	function H($str){
		return \Model\Output::H($str);
	}

	function E($str) {
		return \Model\Output::E($str);
	}

}
	