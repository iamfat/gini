<?php

namespace Controller\CLI {
	
	use \Model\File;
	use \Gini\Core;

	class I18N extends \Controller\CLI {

		static function do_scan($argv) {

			if (count($argv) < 1) {
				exit("usage: \033[1;34mgini i18n scan\033[0m <path/to/app> [<locales>]\n");
			}

			echo "I18N_PATH=".I18N_PATH."\n";
			$info = Core::fetch_info($argv[1]);
			if (!isset($info->shortname)) {
				echo "\033[1;34mgini i18n scan\033[0m: Invalid app path!\n";
				exit;
			}

			$path = $info->path;
			$domain = str_replace('/', '-', $info->shortname);

			$opt = getopt('o:');
			$i18n_path = isset($opt['o']) ? realpath($opt['o']) : I18N_PATH;
			
			$l10n_path = $path . '/l10n';
			$l10n_template = $l10n_path.'/template.pot';

			File::check_path($l10n_template);
			if (file_exists($l10n_template)) unlink($l10n_template);

			$keywords = '--keyword=__:1 --keyword=_D:2 --keyword=_e:1 --keyword=_eD:2 --keyword=_eH:1 --keyword=_HD:1 --keyword=_eHD:1';
			//$package = sprintf('--package-name=%s --package-version=%s', 
			$cmd = sprintf('find %s -name *.ph* | xargs xgettext -LPHP %s %s -o %s', 
					escapeshellarg($path), 
					$keywords,
					'--omit-header --from-code utf-8 -i',
					escapeshellarg($l10n_template)
					);
			exec($cmd);

			$locales = array_splice($argv, 2);
			foreach(glob($l10n_path.'/*.po') as $fname) {
				$locale = basename($fname, '.po');
				$locales[] = $locale;
			}

			foreach (array_unique($locales) as $locale) {

			    $locale_arr = locale_parse($locale);
			    if (!isset($locale_arr['language'])) continue;

			    $l10n_pofile = $l10n_path . '/' . $locale . '.po';
			    if (!file_exists($l10n_pofile)) {
				   	$cmd = sprintf('msginit --no-translator -o %1$s -i %2$s -l %3$s', 
				   		escapeshellarg($l10n_pofile), 
				   		escapeshellarg($l10n_template), 
				   		escapeshellarg($locale));
			    }
			    else {
				   	$cmd = sprintf('msgmerge --suffix=none -q -U %1$s %2$s', 
				   		escapeshellarg($l10n_pofile), 
				   		escapeshellarg($l10n_template));
			    }
			   	exec($cmd);
			}
			//merge po file to different locale directory
			
		}

		static function do_format($argv) {
			if (count($argv) < 1) {
				exit("usage: \033[1;34mgini i18n format\033[0m <locales>\n");
			}

			array_shift($argv);
			foreach($argv as $locale) {
				$paths = Core::file_paths('l10n/'.$locale.'.po');
				$files = array();
				foreach($paths as $path) {
					$dir = dirname(dirname($path));
					$domain = Core::shortname($dir);
					echo "$domain => $path\n";
					$files[$domain][] = escapeshellarg($path);
				}

				foreach($files as $domain => $domain_files) {
					$target = I18N_PATH.'/'.$locale.'/LC_MESSAGES/'.$domain.'.mo';
					echo $target."\n";
					File::check_path($target);
					$cmd = sprintf('msgfmt -o %s %s', 
						escapeshellarg($target),
						implode(' ', $domain_files)
					);
					exec($cmd);
					// 'msgfmt'
				}
			}


			/*
			$i18n_path = $argc > 0 ? realpath($argv[0]) : I18N_PATH;
			$dh  = opendir($i18n_path);
			while (false !== ($fname = readdir($dh))) {
			    if ($fname[0] == '.' || $fname == '@') continue;

			    $locale_path = $i18n_path . '/' . $fname;
			    if (!is_dir($locale_path)) continue;

			    $po_file = $locale_path.'/'.$domain.'.po';
			    \Model\File::check_path($po_file);
			    if (!file_exists($po_file)) {
			    	touch($po_file);
			    }
			   	$cmd = sprintf('msgmerge --force-po --suffix=none -U %1$s %2$s', $po_file, $i18n_template);
			   	exec($cmd);
			}
			*/
		}

	}

}
