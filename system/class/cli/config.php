<?php

namespace CLI {
	
	class Config extends \CLI\Base {

		static function command_print($argc, $argv) {
			echo serialize(\Model\Config::export())."\n";
			/*
			if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
				echo json_encode(\Model\Config::export(), JSON_NUMERIC_CHECK|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
			}
			else {
				var_export(\Model\Config::export());
			}
			*/
		}

		static function command_clean($argc, $argv) {
			$config_file = APP_PATH . '/.config';
			if (file_exists($config_file)) {
				if (unlink($config_file)) {
					echo "Config cache cleaned.\n";
				}
				else {
					echo "Unknown error when removing config cache.\n";
				}
			}
			else {
				echo "It's already in clean state.\n";
			}
		}
	}

}