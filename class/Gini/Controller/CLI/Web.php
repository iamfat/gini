<?php

namespace Gini\Controller\CLI;

class Web extends \Gini\Controller\CLI
{
    public function __index($args)
    {
        echo "gini web update\n";
        echo "gini web preview\n";
        echo "gini web clean\n";
    }

    private function _process_css($force = false)
    {
        printf("%s\n", 'Converting LESS to CSS...');

        $css_dir = APP_PATH.'/web/assets/css';
        \Gini\File::ensureDir($css_dir);

        $filemtime = [];
        $pinfo = (array) \Gini\Core::$MODULE_INFO;
        foreach ($pinfo as $p) {
            foreach ([
                $p->path.'/'.RAW_DIR.'/assets/css',
                $p->path.'/'.RAW_DIR.'/less',
                $p->path.'/'.RAW_DIR.'/assets/less', ] as $less_dir) {
                if (!is_dir($less_dir)) {
                    continue;
                }
                $dh = opendir($less_dir);
                if ($dh) {
                    while (false !== ($name = readdir($dh))) {
                        if ($name[0] == '.') {
                            continue;
                        }
                        if (fnmatch('*.less', $name)) {
                            $css = basename($name, '.less').'.css';
                        } elseif (fnmatch('*.css', $name)) {
                            $css = $name;
                        } else {
                            continue;
                        }

                        $src_path = $less_dir.'/'.$name;
                        $dst_path = $css_dir.'/'.$css;

                        if (!isset($filemtime[$dst_path])) {
                            $filemtime[$dst_path] = file_exists($dst_path) ? filemtime($dst_path) : 0;
                        }

                        if ($force || filemtime($src_path) > $filemtime[$dst_path]) {
                            // lessc -x raw/less/$$LESS.less web/assets/css/$$LESS.css ; \
                            printf("   %s => %s\n", $name, $css);
                            $command = sprintf('lessc %s %s %s', escapeshellarg($src_path), escapeshellarg($dst_path), '--clean-css="--s1 --advanced --compatibility=ie8"'
                                    );
                            exec($command);
                        }
                    }
                    closedir($dh);
                }
            }
        }

        echo "   \e[32mdone.\e[0m\n";
    }

    private function _process_js($force = false, $debug = false)
    {
        printf("%s\n", 'Processing JS...');

        $ugly_js_dir = APP_PATH.'/web/assets/js';
        \Gini\File::ensureDir($ugly_js_dir);

        $filemtime = [];
        $pinfo = (array) \Gini\Core::$MODULE_INFO;
        foreach ($pinfo as $p) {
            foreach ([
                $p->path.'/'.RAW_DIR.'/js',
                $p->path.'/'.RAW_DIR.'/assets/js', ] as $js_dir) {
                \Gini\File::eachFilesIn($js_dir, function ($file) use ($js_dir, $ugly_js_dir, $force, &$filemtime, $debug) {
                    $src_path = $js_dir.'/'.$file;
                    $dst_path = $ugly_js_dir.'/'.$file;

                    if (!isset($filemtime[$dst_path])) {
                        $filemtime[$dst_path] = file_exists($dst_path) ? filemtime($dst_path) : 0;
                    }

                    if ($force || filemtime($src_path) > $filemtime[$dst_path]) {
                        printf("   %s\n", $file);
                        \Gini\File::ensureDir(dirname($dst_path));
                        if ($debug) {
                            $command = sprintf("sed -e 's/TIMESTAMP/%s/g' %s > %s", time(), escapeshellarg($src_path), escapeshellarg($dst_path));
                            exec($command);
                        } else {
                            // uglifyjs raw/js/$$JS -o web/assets/js/$$JS ; \
                            $command = sprintf('uglifyjs %s -c warnings=false -d TIMESTAMP=%s -o %s',
                                escapeshellarg($src_path), time(), escapeshellarg($dst_path));
                            exec($command);
                        }
                    }
                });
            }
        }

        echo "   \e[32mdone.\e[0m\n";
    }

    private function _merge_assets($force = false)
    {
        printf("%s\n", 'Merging all assets...');
        $assets_dir = APP_PATH.'/web/assets';
        \Gini\File::ensureDir($assets_dir);

        $filemtime = [];
        $pinfo = (array) \Gini\Core::$MODULE_INFO;
        foreach ($pinfo as $p) {
            $src_dir = $p->path.'/'.RAW_DIR.'/assets';
            \Gini\File::eachFilesIn($src_dir, function ($file) use ($src_dir, $assets_dir, $force, &$filemtime) {
                //ignore less|css|js since we will process them later.
                if (preg_match('/^(?:less|css|js)\//', $file)) {
                    return;
                }

                $src_path = $src_dir.'/'.$file;

                $dst_path = $assets_dir.'/'.$file;
                \Gini\File::ensureDir(dirname($dst_path));

                if (!isset($filemtime[$dst_path])) {
                    $filemtime[$dst_path] = file_exists($dst_path) ? filemtime($dst_path) : 0;
                }

                if ($force
                     || filemtime($src_path) > $filemtime[$dst_path]
                     || !file_exists($dst_path)
                     || filesize($src_path) != filesize($dst_path)
                ) {
                    printf("   copy %s\n", $file);
                    copy($src_path, $dst_path);
                }

            });
        }

        echo "   \e[32mdone.\e[0m\n";
    }

    public function actionUpdate($args)
    {
        if (APP_ID == 'gini') {
            echo "\e[31mPlease run it in your App directory!\e[0m\n";

            return;
        }

        $opt = \Gini\Util::getOpt($args, 'fd', ['force', 'debug']);
        $force = isset($opt['f']) || isset($opt['force']);
        $debug = isset($opt['d']) || isset($opt['debug']);

        $web_dir = APP_PATH.'/web';
        \Gini\File::ensureDir($web_dir);
        $cgi_path = realpath(dirname(realpath($_SERVER['SCRIPT_FILENAME'])).'/../lib/cgi.php');
        $index_path = $web_dir.'/index.php';
        if (file_exists($index_path) || is_link($index_path)) {
            unlink($index_path);
        }
        file_put_contents($index_path, "<?php require \"$cgi_path\";\n");

        $this->_merge_assets($force, $debug);
        $this->_process_css($force, $debug);
        $this->_process_js($force, $debug);
    }

    public function actionPreview($args)
    {
        $errors = \Gini\App\Doctor::diagnose(['dependencies', 'web']);
        if ($errors) {
            return;
        }

        $addr = $args[0] ?: 'localhost:3000';
        $command
            = sprintf('php -S %s -c %s -t %s 2>&1', $addr, APP_PATH.'/raw/cli-server.ini', APP_PATH.'/web');

        $descriptors = [
            ['file', '/dev/tty', 'r'],
            ['file', '/dev/tty', 'w'],
            ['file', '/dev/tty', 'w'],
                ];

        $proc = proc_open($command, $descriptors, $pipes);
        if (is_resource($proc)) {
            proc_close($proc);
        }
    }

    public function actionClean($args)
    {
        if (!file_exists(APP_PATH.'/web')) {
            die("You don't need to clean web dir since it does not exists.\n");
        }

        echo "Removing Web Directory...\n";
        \Gini\File::removeDir(APP_PATH.'/web');
        echo "   \e[32mdone.\e[0m\n";
    }
}
