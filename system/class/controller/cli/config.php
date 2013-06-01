<?php

namespace Controller\CLI {
	
	class Config extends \Controller\CLI {

		function action_print($argv) {
			// echo serialize(\Model\Config::export())."\n";
			$config = \Model\Config::export();
			
			//runtime setting should not be exposed
			unset($config['runtime']);

			echo yaml_emit($config, YAML_UTF8_ENCODING);
		}

	}

}
