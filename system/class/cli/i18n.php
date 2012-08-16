<?php

namespace CLI {
	
	use \Model\File;

	class I18N extends \Model\CLI {

		static function command_scan($argc, $argv) {

			if ($argc <= 1) {
				echo "Usage: i18n scan path/to/app\n";
				exit;
			}

			$path = File::relative_path(realpath($argv[1]), ROOT_PATH);
			$domain = basename($path);
			$i18n_path = $path.'/'.I18N_DIR;
			$i18n_template = $i18n_path.$domain.'.pot';

			unlink($i18n_template);
			$cmd = sprintf('find %1$s -name *.ph* | xargs xgettext -LPHP --keyword=T:1 --keyword=T:1c,2 --from-code utf-8 -i -o %2$s', 
					escapeshellarg($path), escapeshellarg($i18n_template)
					);
			exec($cmd);

			$dh  = opendir($i18n_path);
			while (false !== ($fname = readdir($dh))) {
			    if ($fname[0] == '.') continue;

			    $locale_path = $i18n_path . $fname;
			    if (!is_dir($locale_path)) continue;

			   	$cmd = sprintf('msgmerge -U %1$s %2$s', $locale_path.'/LC_MESSAGES/'.$domain.'.po', $i18n_template);
			   	exec($cmd);
			}
			//merge po file to different locale directory
			
		}

	}

}
