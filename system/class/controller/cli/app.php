<?php

/**
* 模块操作命令行
* usage: app command [args...]
*	app new app_path [Name]
* @package default
* @author Jia Huang
**/

namespace {

	if (!function_exists('json_last_error_msg')) {

		function json_last_error_msg() {
		    switch (json_last_error()) {
		        case JSON_ERROR_NONE:
		            return 'no errors';
		        case JSON_ERROR_DEPTH:
		            return 'maximum stack depth exceeded';
		        case JSON_ERROR_STATE_MISMATCH:
		            return 'underflow or the modes mismatch';
		        case JSON_ERROR_CTRL_CHAR:
		            return 'unexpected control character found';
		        case JSON_ERROR_SYNTAX:
		            return 'syntax error, malformed JSON';
		        case JSON_ERROR_UTF8:
		            return 'malformed UTF-8 characters, possibly incorrectly encoded';
		        default:
		            return 'unknown error';
		    }
    	}

	}

}

namespace Controller\CLI {

	class App extends \Controller\CLI {
		
		/**
		 * 初始化模块
		 *
		 * @return void
		 * @author Jia Huang
		 **/
		function action_init(&$args) {

			$path = $_SERVER['PWD'];

			$prompt = array(
				'shortname' => 'Shortname',
				'name' => 'Name',
				'description' => 'Description',
				'version' => 'Version',
				'dependencies' => 'Dependencies',
				);

			$default = array(
				'name' => ucwords(basename($path)),
				'shortname' => basename($path),
				'path' => $path,
				'description' => 'App description...',
				'version' => '0.1',
				'dependencies' => '',
				);

			foreach($prompt as $k => $v) {
				$data[$k] = readline($v . " [\x1b[31m" . ($default[$k]?:'N/A') . "\x1b[0m]: ");
				if (!$data[$k]) $data[$k] = $default[$k];
			}

			$gini_json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES| JSON_PRETTY_PRINT);
			file_put_contents($path . '/gini.json', $gini_json);
		}

		function action_info(&$args) {
			$path = $argv[0] ?: basename($_SERVER['PWD']);

			$info = \Gini\Core::path_info($path);
			if ($info) {
				foreach($info as $k => $v) {
					if (is_array($v)) $v = json_encode($v);
					printf("%s = %s\n", $k, $v);
				}
			}

		}

		function action_ls(&$args) {
			
		}

		private function _load_config_category($category, $filename, &$items) {
			if (is_file($filename)) {
				if (!isset($items[$category])) $items[$category] = [];	
				$config = & $items[$category];
				include($filename);
			}
			elseif(is_dir($filename)) {
				$base = $filename;
				$dh = opendir($base);
				if ($dh) {
					if (substr($base, -1) != '/') $base .= '/';
					while ($file = readdir($dh)) {
						if ($file[0] == '.') continue;
						$this->_load_config_category($category, $base . '/' . $file);
					}
					closedir($dh);
				}
			}
		}
	
		private function _load_config_dir($base, &$items){
			if (!is_dir($base)) return;
			
			$dh = opendir($base);
			if ($dh) {
				while($file = readdir($dh)) {
					if ($file[0] == '.') continue;
					$this->_load_config_category(basename($file, EXT), $base . '/' . $file, $items);
				}
				closedir($dh);
			}
		}
		

		private function _prepare_walkthrough($root, $prefix, $callback) {

			$dir = $root . '/' . $prefix;
			if (!is_dir($dir)) return;
			$dh = opendir($dir);
			if ($dh) {
				while (FALSE !== ($name = readdir($dh))) {
					if ($name[0] == '.') continue;
					
					$file = $prefix ? $prefix . '/' . $name : $name;
					$full_path = $root . '/' . $file;

					if (is_dir($full_path)) {
						$this->_prepare_walkthrough($root, $file, $callback);
						continue;
					}

					if ($callback) {
						$callback($file);
					}
				}
				closedir($dh);
			}
		}

		// private function _import_app_config(&$config_items) {
		// 	$config_dir = APP_PATH . '/' . DATA_DIR . '/config';
		// 	if (is_dir($config_dir)) {
		// 		$dh = opendir($config_dir);
		// 		if ($dh) {
		// 			while (FALSE !== ($name = readdir($dh))) {
		// 				if ($name[0] == '.') continue;
		// 				if (fnmatch('*.json', $name)) {
		// 					$file = $config_dir . '/' . $name;
		// 					$category = basename($name, '.json');
		// 					$content = file_get_contents($file);
		// 					$content = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t](//).*)#", '', $content); 
		// 					$items= (array)json_decode($content, TRUE);
		// 					if (JSON_ERROR_NONE != json_last_error()) {
		// 						TRACE("\x1b[31m%s\x1b[0m on \x1b[4m%s\x1b[0m.", json_last_error_msg(), $name);
		// 					}
		// 					else {
		// 						$config_items[$category] = array_merge((array)$config_items[$category], $items);
		// 					}
		// 				}
		// 			}
		// 			closedir($dh);
		// 		}
		// 	}
		// }

		private function _update_config_cache() {

			printf("%s\n", "Updating config cache...");

			$config_items = [];

			$paths = \Gini\Core::phar_file_paths(RAW_DIR, 'config');
			foreach ($paths as $path) {
				$this->_load_config_dir($path, $config_items);
			}

			$config_file = APP_PATH . '/cache/config.json';
			file_put_contents($config_file, json_encode($config_items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
			echo "   \x1b[32mdone.\x1b[0m\n";
		}

		private function _update_class_cache() {
			printf("%s\n", "Updating class cache...");

			$paths = \Gini\Core::phar_file_paths(CLASS_DIR, '');
			$class_map = array();
			foreach ($paths as $class_dir) {
				$this->_prepare_walkthrough($class_dir, '', function($file) use ($class_dir, &$class_map) {
					if (preg_match('/^(.+)\.php$/', $file, $parts)) {
						$class_name = trim(strtolower($parts[1]), '/');
						$class_map[$class_name] = $class_dir . '/' . $file;
					}
				});
			}

			file_put_contents(APP_PATH.'/cache/class_map.json', json_encode($class_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES| JSON_PRETTY_PRINT));
			echo "   \x1b[32mdone.\x1b[0m\n";
		}

		private function _update_view_cache() {
			printf("%s\n", "Updating view cache...");

			$paths = \Gini\Core::phar_file_paths(VIEW_DIR, '');
			$view_map = array();
			foreach ($paths as $view_dir) {
				$this->_prepare_walkthrough($view_dir, '', function($file) use ($view_dir, &$view_map) {
					if (preg_match('/^([^\/]+)\/(.+)\.\1$/', $file , $parts)) {
						$view_name = $parts[1] . '/' .$parts[2];
						$view_map[$view_name] = $view_dir . '/' . $file;
					}
				});
			}

			file_put_contents(APP_PATH.'/cache/view_map.json', json_encode($view_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES| JSON_PRETTY_PRINT));
			echo "   \x1b[32mdone.\x1b[0m\n";
		}

		function action_update(&$args) {
			\Model\File::check_path(APP_PATH.'/cache/foo');
			$this->_update_class_cache();
			$this->_update_view_cache();
			$this->_update_config_cache();
		}

		private function _convert_less() {
			printf("%s\n", "Converting LESS to CSS...");

			$css_dir = APP_PATH . '/web/assets/css';
			\Model\File::check_path($css_dir . '/foo');

			$pinfo = (array)\Gini\Core::$PATH_INFO;
			$less_map = array();
			foreach ($pinfo as $p) {
				$less_dir = $p->path . '/' . RAW_DIR . '/less';
				if (!is_dir($less_dir)) continue;
				$dh = opendir($less_dir);
				if ($dh) {
					while (FALSE !== ($name = readdir($dh))) {
						if ($name[0] == '.') continue;
						if (fnmatch('*.less', $name)) {
							$css = basename($name, '.less') . '.css';
							$src_path = $less_dir . '/' . $name;
							$dst_path = $css_dir . '/' . $css;
							if (!file_exists($dst_path) || filemtime($src_path) > filemtime($dst_path)) {
								// lessc -x raw/less/$$LESS.less -o web/assets/css/$$LESS.css ; \
								printf("   %s => %s\n", $name, $css);
								$command = sprintf("lessc -x %s -o %s"
									, escapeshellarg($src_path)
									, escapeshellarg($dst_path)
									);
								exec($command);
							}
						}
					}
					closedir($dh);
				}

			}

			echo "   \x1b[32mdone.\x1b[0m\n";
		}

		private function _uglify_js() {
			printf("%s\n", "Uglifying JS...");

			$ugly_js_dir = APP_PATH . '/web/assets/js';
			\Model\File::check_path($ugly_js_dir . '/foo');

			$pinfo = (array)\Gini\Core::$PATH_INFO;
			$less_map = array();
			foreach ($pinfo as $p) {
				$js_dir = $p->path . '/' . RAW_DIR . '/js';
				if (!is_dir($js_dir)) continue;
				$dh = opendir($js_dir);
				if ($dh) {
					while (FALSE !== ($name = readdir($dh))) {
						if ($name[0] == '.') continue;
						if (fnmatch('*.js', $name)) {
							$src_path = $js_dir . '/' . $name;
							$dst_path = $ugly_js_dir . '/' . $name;
							if (!file_exists($dst_path) || filemtime($src_path) > filemtime($dst_path)) {
								// uglifyjs raw/js/$$JS -o web/assets/js/$$JS ; \
								printf("   %s\n", $name);
								$command = sprintf("uglifyjs %s -o %s"
									, escapeshellarg($src_path)
									, escapeshellarg($dst_path)
									);
								exec($command);
							}
						}
					}
					closedir($dh);
				}

			}

			// \Model\File::check_path(APP_PATH.'/cache/view_map.json');
			// file_put_contents(APP_PATH.'/cache/view_map.json', json_encode($view_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES| JSON_PRETTY_PRINT));
			echo "   \x1b[32mdone.\x1b[0m\n";
		}

		private function _merge_assets() {
	
			printf("%s\n", "Merging all assets...");
			$assets_dir = APP_PATH.'/web/assets';
			\Model\File::check_path($assets_dir.'/foo');

			$pinfo = (array)\Gini\Core::$PATH_INFO;
			foreach ($pinfo as $p) {
				$src_dir = $p->path . '/web/assets';
				$this->_prepare_walkthrough($src_dir, '', function($file) use ($src_dir, $assets_dir) {
					$src_path = $src_dir . '/' . $file;
					$dst_path = $assets_dir . '/' . $file;

					\Model\File::check_path($dst_path);
					if (!file_exists($dst_path) 
						|| filesize($src_path) != filesize($dst_path) 
						|| filemtime($src_path) > filemtime($dst_path)) {
						printf("   copy %s\n", $file);
						\copy($src_path, $dst_path);
					}

				});
			}

			echo "   \x1b[32mdone.\x1b[0m\n";
		}

		function action_update_web(&$args) {
			$this->_merge_assets();
			$this->_convert_less();
			$this->_uglify_js();
		}

		function action_server(&$args) {
			$addr = $args[0] ?: 'localhost:3000';
			$command 
				= sprintf("php -S %s -c %s -t %s"
					, $addr
					, APP_PATH . '/raw/cli-server.ini'
					, APP_PATH.'/web');
			echo $command;
			//passthru($command);
		}

		function action_update_orm(&$args) {
			// enumerate orms
			printf("Updating database structures according ORM definition...\n");

			$paths = \Gini\Core::phar_file_paths(CLASS_DIR, 'orm');
			foreach($paths as $path) {
				$shortname = \Gini\Core::shortname($path);
				// printf("\x1b[30;1;4m%s\x1b[0m:\n", $shortname);
				if (!is_dir($path)) continue;

				$dh = opendir($path);
				if ($dh) {
					while ($name = readdir($dh)) {
						if ($name[0] == '.') continue;
						if (!is_file($path . '/' . $name)) continue;
						$oname = ucwords(basename($name, EXT));
						if ($oname == 'Object') continue;
						printf("   %s\n", $oname);
						$class_name = '\\ORM\\'.$oname;
						$o = new $class_name();
						$o->db()->adjust_table($o->name(), $o->schema());
					}
					closedir($dh);
				}
			}

			echo "   \x1b[32mdone.\x1b[0m\n";

			// $db->adjust_table($this->name(), $schema);
		}


	}

}
