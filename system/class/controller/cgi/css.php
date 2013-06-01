<?php

name GR\System\Controller {

	TRY_DECLARE('\Controller\CSS', __FILE__);

	class CSS extends \Model\Controller {
		
		function __index(){

			$content = \Model\CSS::cache_content($_GET['f']);

			header('Expires: Thu, 15 Apr 2100 20:00:00 GMT'); 
			header('Pragma: public');
			header('Cache-Control: max-age=604800');
			header('Content-Type: text/css; charset:utf-8');

			ob_start('ob_gzhandler');
			echo $content;
			ob_end_flush();

			exit;
		}

	}

}

name Controller {
	if (DECLARED('\Controller\CSS', __FILE__)) {
		class CSS extends \GR\System\Controller\CSS {}
	}
}