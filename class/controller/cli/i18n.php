<?php

namespace Controller\CLI {

    class I18N extends \Controller\CLI
    {
        function actionHelp($argv)
        {
            echo "gini i18n scan [<locales>]\n";
            echo "gini i18n format <locales>\n";
        }

        function actionScan($argv)
        {
            if (count($argv) < 1) {
                exit("usage: \e[1;34mgini i18n scan\e[0m [<locales>]\n");
            }

            $info = \Gini\Core::moduleInfo(APP_ID);
            if (!isset($info->id)) {
                echo "\e[1;34mgini i18n scan\e[0m: Invalid app path!\n";
                exit;
            }

            $path = $info->path;
            $domain = str_replace('/', '-', $info->id);

            $l10n_path = $path . '/' . RAW_DIR . '/l10n';
            \Gini\File::ensureDir($l10n_path);

            $l10n_template = $l10n_path . '/template.pot';
            if (file_exists($l10n_template)) unlink($l10n_template);

            $keywords = '--keyword=T';
            //$package = sprintf('--package-name=%s --package-version=%s',
            $cmd = sprintf('cd %s && find . -name "*.php" -o -name "*.phtml" | xargs xgettext -LPHP %s --from-code=UTF-8 -i --copyright-holder=%s --foreign-user --package-name=%s --package-version=%s --msgid-bugs-address=%s -o %s',
                    escapeshellarg($path),
                    $keywords,
                    $info->author ?: 'Anonymous',
                    $info->id,
                    $info->version,
                    $info->email ?: 'l10n@geneegroup.com',
                    escapeshellarg($l10n_template)
                    );
            passthru($cmd);

            // extract msgid ""{context}\004{txt} to msgctxt and msgid
            $cmd = sprintf("sed 's/msgid   \"\\(.*\\)'\004'/msgctxt \"\\1\"\\'$'\\nmsgid \"/g' %s",
                    escapeshellarg($l10n_template));
            //echo $cmd . "\n"; die;
            file_put_contents($l10n_template, `$cmd`);

            $locales = $argv;
            foreach (glob($l10n_path.'/*.po') as $fname) {
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
                } else {
                       $cmd = sprintf('msgmerge --update --suffix=none --no-fuzzy-matching -q %1$s %2$s',
                           escapeshellarg($l10n_pofile),
                           escapeshellarg($l10n_template));
                }

                passthru($cmd);
            }

            //merge po file to different locale directory

        }

        function actionFormat($argv)
        {
            if (count($argv) < 1) {
                exit("usage: \e[1;34mgini i18n format\e[0m <locales>\n");
            }

            $appname = APP_ID;

            foreach ($argv as $locale) {
                $lodir = I18N_PATH.'/'.$locale.'/LC_MESSAGES';
                \Gini\File::ensureDir($lodir);

                $pofile = $lodir.'/'.$appname.'.po';
                $paths = \Gini\Core::filePaths(RAW_DIR . '/l10n/'.$locale.'.po');
                echo "merge: $appname.po\n";
                $cmd = sprintf('msgcat -o %1$s %2$s',
                       escapeshellarg($pofile),
                       implode(' ', array_map(escapeshellarg, $paths)));
                passthru($cmd);

                $mofile = $lodir . '/'.$appname.'.mo';
                echo "compile: $appname.po => $appname.mo\n";
                $cmd = sprintf('msgfmt -o %s %s',
                    escapeshellarg($mofile),
                    escapeshellarg($pofile)
                );
                passthru($cmd);
            }

        }

    }

}
