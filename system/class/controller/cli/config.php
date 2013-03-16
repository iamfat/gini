<?php

namespace Controller\CLI {
	
	class Config extends \Controller\CLI {

		static function action_print($argv) {
			// echo serialize(\Model\Config::export())."\n";
			$config = \Model\Config::export();
			
			//runtime setting should not be exposed
			unset($config['runtime']);

			echo yaml_emit($config, YAML_UTF8_ENCODING);
		}

		private static $items = array();

		private static function _load($category, $filename) {
			if (is_file($filename)) {
				if (!isset(self::$items[$category])) self::$items[$category] = array();
	
				$config = & self::$items[$category];
				// $config['#ROOT'] = & self::$items;
				include($filename);
				// unset($config['#ROOT']);
			}
			elseif(is_dir($filename)) {
				$base = $filename;
				$dh = opendir($base);
				if ($dh) {
					if (substr($base, -1) != '/') {
						$base .= '/';
					}
					while ($file = readdir($dh)) {
						if ($file[0] == '.') continue;
						self::_load($category, $base . '/' . $file);
					}
					closedir($dh);
				}
			}
		}
	
		private static function load($path, $category=NULL){
			$base = $path.'/'.RAW_DIR.'/config';
			if ($category) {
				$ffile = $base.'/'.$category.EXT;
				if (is_file($ffile)) {
					self::_load($category, $ffile);
				}
			}
			elseif (is_dir($base)) {
				$dh = opendir($base);
				if ($dh) {
					while($file = readdir($dh)) {
						if ($file[0] == '.') continue;
						self::_load(basename($file, EXT), $base . '/' . $file);
					}
					closedir($dh);
				}
			}
		}
		

		static function action_update($argv) {

			printf("    %-40s", "Updating config cache...");

			$config_file = APP_PATH . '/.config';

			foreach ((array) \Gini\Core::$PATH_INFO as $path => $info) {
				if ($info->enabled !== FALSE) {
					self::load($info->path);
				}
			}

			if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
				$opt = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;
			}
			else {
				$opt = 0;
			}

			file_put_contents($config_file, json_encode((array)self::$items, $opt));
			echo "\t\033[32mDONE\033[0m\n";
		}
	}

}
