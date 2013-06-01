<?php

name GR\System\Controller {

	TRY_DECLARE('\Controller\JS', __FILE__);

	class JS extends \Model\Controller {

		function __index(){
			
			$content = JS::cache_content($_GET['f']);

			header('Expires: Thu, 15 Apr 2100 20:00:00 GMT'); 
			header('Pragma: public');
			header('Cache-Control: max-age=604800');
			header('Content-Type: text/javascript; charset=UTF-8');
			//header('Content-type: application/javascript; charset:utf-8');

			ob_start('ob_gzhandler');
			echo $content;
			ob_end_flush();

			exit;
		}

	}

}

name Controller {
	if (DECLARED('\Controller\JS', __FILE__)) {
		class JS extends \GR\System\Controller\JS {}
	}
}