<?php

/**
* 模块操作命令行
* usage: app command [args...]
*	app new app_path [Name]
* @package default
* @author Jia Huang
**/

namespace CLI {

	use \Gini\Core;
	use \Model\File;

	const INFO_TEMPLATE = <<<'PHP'
<?php

$shortname = '%shortname%';
$name = '%name%';
$description = '%description%';
$version = '%version%';

// if this app is dependent on other apps, put them in $dependiencies and separate them with comma
$dependencies = '%dependencies%';		

PHP;

	class App extends \Model\CLI {
		
		/**
		 * 初始化模块
		 *
		 * @return void
		 * @author Jia Huang
		 **/
		static function command_new($argc, $argv) {

			if ($argc < 2) {
				die("usage: app init path/to/app\n");
			}

			$path = $argv[1];

			$prompt = array(
				'shortname' => 'Shortname',
				'name' => 'Name',
				'description' => 'Description',
				'version' => 'Version',
				'dependencies' => 'Dependencies',
				);

			$default = array(
				'name' => 'My Untitled App',
				'shortname' => basename($path),
				'path' => $path,
				'description' => 'App description...',
				'version' => '0.1',
				'dependencies' => '',
				);

			foreach($prompt as $k => $v) {
				$data[$k] = readline($v . " [\033[31m" . ($default[$k]?:'N/A') . "\033[0m]: ");
				if (!$data[$k]) $data[$k] = $default[$k];
			}

			/*
			if (mkdir($path, 0755, true)) {
				$path .= '/';
				file_put_contents($path . 'info' . EXT, INFO_TEMPLATE);		
				mkdir($path . 'class', 0755, true);
				mkdir($path . 'config', 0755, true);
				mkdir($path . 'i18n', 0755, true);
			}
			*/
		}

		static function command_info($argc, $argv) {
			if ($argc < 2) {
				die("usage: app info path/to/app\n");
			}

			$info = \Gini\Core::path_info($argv[1]);
			foreach($info as $k => $v) {
				if (is_array($v)) $v = json_encode($v);
				printf("%s = %s\n", $k, $v);
			}
		}
	}

}
