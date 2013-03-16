<?php

namespace Controller\CLI {
	
	class ORM extends \Controller\CLI {

		function action_update() {
			// enumerate orms

			$paths = \Gini\Core::phar_file_paths(CLASS_DIR, 'orm');
			foreach($paths as $path) {
				$shortname = \Gini\Core::shortname($path);
				// printf("\033[30;1;4m%s\033[0m:\n", $shortname);
				if (!is_dir($path)) continue;

				$dh = opendir($path);
				if ($dh) {
					while ($name = readdir($dh)) {
						if ($name[0] == '.') continue;
						if (!is_file($path . '/' . $name)) continue;
						$oname = ucwords(basename($name, EXT));
						if ($oname == 'Object') continue;
						$str = sprintf("    Updating ORM\\%s...", $oname);
						printf("%-40s", $str);
						$class_name = '\\ORM\\'.$oname;
						$o = new $class_name();
						$o->db()->adjust_table($o->name(), $o->schema());
						echo "\t\033[32mDONE\033[0m\n";
					}
					closedir($dh);
				}

			}

			// $db->adjust_table($this->name(), $schema);
		}

	}

}
