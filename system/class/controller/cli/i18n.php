<?php

namespace Controller\CLI {
	
	class I18N extends \Controller\CLI {

		static function action_scan(&$argv) {

			if (count($argv) < 1) {
				exit("usage: \x1b[1;34mgini i18n scan\x1b[0m <path/to/app> [<locales>]\n");
			}

			$info = \Gini\Core::fetch_info($argv[0]);
			if (!isset($info->shortname)) {
				echo "\x1b[1;34mgini i18n scan\x1b[0m: Invalid app path!\n";
				exit;
			}

			$path = $info->path;
			$domain = str_replace('/', '-', $info->shortname);
			
			$l10n_path = $path . '/' . RAW_DIR . '/l10n';
			$l10n_template = $l10n_path . '/template.pot';

			\Model\File::check_path($l10n_template);
			if (file_exists($l10n_template)) unlink($l10n_template);

			$keywords = '--keyword=T:1 --keyword=DT:2 --keyword=NT:1 --keyword=NT:2 --keyword=DNT:2 --keyword=DNT:3';
			//$package = sprintf('--package-name=%s --package-version=%s', 
			$cmd = sprintf('find %s -name *.ph* | xargs xgettext -LPHP %s --from-code=UTF-8 -i --copyright-holder=%s --foreign-user --package-name=%s --package-version=%s --msgid-bugs-address=%s -o %s', 
					escapeshellarg($path), 
					$keywords,
					$info->author ?: 'Anonymous',
					$info->name,
					$info->version,
					$info->email,
					escapeshellarg($l10n_template)
					);
			exec($cmd);

			$locales = array_splice($argv, 1);
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

		static function action_format($argv) {
			if (count($argv) < 1) {
				exit("usage: \x1b[1;34mgini i18n format\x1b[0m <locales>\n");
			}

			array_shift($argv);
			foreach($argv as $locale) {
				$paths = \Gini\Core::file_paths(RAW_DIR . '/l10n/'.$locale.'.po');
				$files = array();
				foreach($paths as $path) {
					$dir = dirname(dirname($path));
					$domain = \Gini\Core::shortname($dir);
					echo "$domain => $path\n";
					$files[$domain][] = escapeshellarg($path);
				}

				foreach($files as $domain => $domain_files) {
					$target = I18N_PATH.'/'.$locale.'/LC_MESSAGES/'.$domain.'.mo';
					echo $target."\n";
					\Model\File::check_path($target);
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
