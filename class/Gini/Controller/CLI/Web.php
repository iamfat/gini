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

    private function _convert_less()
    {
        printf("%s\n", "Converting LESS to CSS...");

        $css_dir = APP_PATH . '/web/assets/css';
        \Gini\File::ensureDir($css_dir);

        $pinfo = (array) \Gini\Core::$MODULE_INFO;
        $less_map = [];
        foreach ($pinfo as $p) {
            $less_dir = $p->path . '/' . RAW_DIR . '/less';
            if (!is_dir($less_dir)) continue;
            $dh = opendir($less_dir);
            if ($dh) {
                while (false !== ($name = readdir($dh))) {
                    if ($name[0] == '.') continue;
                    if (fnmatch('*.less', $name)) {
                        $css = basename($name, '.less') . '.css';
                        $src_path = $less_dir . '/' . $name;
                        $dst_path = $css_dir . '/' . $css;
                        if (!file_exists($dst_path)
                            || filemtime($src_path) > filemtime($dst_path)) {
                            // lessc -x raw/less/$$LESS.less web/assets/css/$$LESS.css ; \
                            printf("   %s => %s\n", $name, $css);
                            $command = sprintf("lessc -x %s %s"
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

        echo "   \e[32mdone.\e[0m\n";
    }

    private function _uglify_js()
    {
        printf("%s\n", "Uglifying JS...");

        $ugly_js_dir = APP_PATH . '/web/assets/js';
        \Gini\File::ensureDir($ugly_js_dir);

        $pinfo = (array) \Gini\Core::$MODULE_INFO;
        $less_map = [];
        foreach ($pinfo as $p) {
            $js_dir = $p->path . '/' . RAW_DIR . '/js';
            \Gini\File::eachFilesIn($js_dir, function ($file) use ($js_dir, $ugly_js_dir) {
                $src_path = $js_dir . '/' . $file;
                $dst_path = $ugly_js_dir . '/' . $file;

                if (!file_exists($dst_path) || filemtime($src_path) > filemtime($dst_path)) {
                    // uglifyjs raw/js/$$JS -o web/assets/js/$$JS ; \
                    \Gini\File::ensureDir(dirname($dst_path));
                    printf("   %s\n", $file);
                    $command = sprintf("uglifyjs %s -o %s",
                        escapeshellarg($src_path), escapeshellarg($dst_path));
                    exec($command);
                }
            });
        }

        echo "   \e[32mdone.\e[0m\n";
    }

    private function _merge_assets()
    {
        printf("%s\n", "Merging all assets...");
        $assets_dir = APP_PATH.'/web/assets';
        \Gini\File::ensureDir($assets_dir);

        $pinfo = (array) \Gini\Core::$MODULE_INFO;
        foreach ($pinfo as $p) {
            $src_dir = $p->path . '/' . RAW_DIR . '/assets';
            \Gini\File::eachFilesIn($src_dir, function ($file) use ($src_dir, $assets_dir) {
                $src_path = $src_dir . '/' . $file;

                $dst_path = $assets_dir . '/' . $file;
                \Gini\File::ensureDir(dirname($dst_path));

                if (!file_exists($dst_path)
                    || filesize($src_path) != filesize($dst_path)
                    || filemtime($src_path) > filemtime($dst_path)) {
                    printf("   copy %s\n", $file);
                    copy($src_path, $dst_path);
                }

            });
        }

        echo "   \e[32mdone.\e[0m\n";
    }

	public function actionUpdate($args)
	{
        $web_dir = APP_PATH . '/web';
        \Gini\File::ensureDir($web_dir);
        $cgi_path = realpath(dirname(realpath($_SERVER['SCRIPT_FILENAME'])) . '/../lib/cgi.php');
        $index_path = $web_dir . '/index.php';
        if (file_exists($index_path) || is_link($index_path)) unlink($index_path);
        file_put_contents($index_path, "<?php require \"$cgi_path\";\n");

        $this->_merge_assets();
        $this->_convert_less();
        $this->_uglify_js();
	}

    public function actionPreview($args)
    {
        $errors = \Gini\Doctor::diagnose(['dependencies', 'web']);
        if ($errors) return;

        $addr = $args[0] ?: 'localhost:3000';
        $command
            = sprintf("php -S %s -c %s -t %s 2>&1"
                , $addr
                , APP_PATH . '/raw/cli-server.ini'
                , APP_PATH.'/web');

        $descriptors = [
            ['file', '/dev/tty', 'r'],
            ['file', '/dev/tty', 'w'],
            ['file', '/dev/tty', 'w']
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