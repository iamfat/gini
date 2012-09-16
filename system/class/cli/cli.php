<?php

namespace CLI {

	class CLI extends \Model\CLI {
		
		/**
		 * 初始化模块
		 *
		 * @return void
		 * @author Jia Huang
		 **/
		static function command_new($argc, $argv) {

			if ($argc < 2) {
				die("usage: cli new <name>\n");
			}

			$name = $argv[1];

			$path = APP_PATH . '/' . CLASS_DIR . '/cli/' . $name . EXT;
			if (file_exists($path)) {
				die("CLI script '$name' already exists.\n");
			}

			\Model\File::check_path($path);

			$template_path = \Gini\Core::phar_file_exists(DATA_DIR, 'templates/cli'.EXT);
			if ($template_path) {
				$content = file_get_contents($template_path);
				$content = strtr($content, array('%CLI_NAME%'=>ucwords($name)));
				file_put_contents($path, $content);

				echo "CLI script '$name' was built succeeded.\n";
			}
			else {
				exit("Failed to found CLI templates!\n");
			}

		}

	}

}
