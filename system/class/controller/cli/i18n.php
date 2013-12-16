<?php

namespace Controller\CLI {
    
    class I18N extends \Controller\CLI {

        function action_help(&$argv) {
            echo "gini i18n scan [<locales>]\n";
            echo "gini i18n format <locales>\n";
        }

        function action_scan(&$argv) {

            if (count($argv) < 1) {
                exit("usage: \e[1;34mgini i18n scan\e[0m [<locales>]\n");
            }

            $info = \Gini\Core::path_info(APP_SHORTNAME);
            if (!isset($info->shortname)) {
                echo "\e[1;34mgini i18n scan\e[0m: Invalid app path!\n";
                exit;
            }

            $path = $info->path;
            $domain = str_replace('/', '-', $info->shortname);
            
            $l10n_path = $path . '/' . RAW_DIR . '/l10n';
            $l10n_template = $l10n_path . '/template.pot';

            \Model\File::check_path($l10n_template);
            if (file_exists($l10n_template)) unlink($l10n_template);

            $keywords = '--keyword=T:1';
            //$package = sprintf('--package-name=%s --package-version=%s', 
            $cmd = sprintf('find %s -name "*.php" -o -name "*.phtml" | xargs xgettext -LPHP %s --from-code=UTF-8 -i --copyright-holder=%s --foreign-user --package-name=%s --package-version=%s --msgid-bugs-address=%s -o %s', 
                    escapeshellarg($path), 
                    $keywords,
                    $info->author ?: 'Anonymous',
                    $info->shortname,
                    $info->version,
                    $info->email ?: 'l10n@geneegroup.com',
                    escapeshellarg($l10n_template)
                    );
            passthru($cmd);

            $locales = $argv;
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
                       $cmd = sprintf('msgmerge --update --suffix=none -q %1$s %2$s', 
                           escapeshellarg($l10n_pofile), 
                           escapeshellarg($l10n_template));
                }
                   
                passthru($cmd);
            }

            //merge po file to different locale directory
            
        }

        function action_format(&$argv) {
            if (count($argv) < 1) {
                exit("usage: \e[1;34mgini i18n format\e[0m <locales>\n");
            }

            $appname = APP_SHORTNAME;

            foreach($argv as $locale) {
                $pofile = I18N_PATH.'/'.$locale.'/LC_MESSAGES/'.$appname.'.po';
                $paths = \Gini\Core::file_paths(RAW_DIR . '/l10n/'.$locale.'.po');
                echo "merge: $appname.po\n";
                $cmd = sprintf('msgcat -o %1$s %2$s', 
                       escapeshellarg($pofile), 
                       implode(' ', array_map(escapeshellarg, $paths)));
                passthru($cmd);

                $target = I18N_PATH.'/'.$locale.'/LC_MESSAGES/'.$appname.'.mo';
                echo "compile: $appname.po => $appname.mo\n";
                $cmd = sprintf('msgfmt -o %s %s', 
                    escapeshellarg($target),
                    escapeshellarg($pofile)
                );
                passthru($cmd);
            }

        }

    }

}
