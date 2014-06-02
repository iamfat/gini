<?php

/**
 * 模块操作命令行
 * usage: app command [args...]
 *    app new app_path [Name]
 *
 * @package default
 * @author Jia Huang
**/

namespace Gini\Controller\CLI;

class App extends \Gini\Controller\CLI
{

    protected function _strPad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        $diff = mb_strwidth( $input ) - mb_strlen( $input );

        return str_pad( $input, $pad_length + $diff, $pad_string, $pad_type );
    }

    private function _iniPHPUnit()
    {
        $gini = \Gini\Core::moduleInfo('gini');

        echo "Generating PHPUnit files...";

        $xml = APP_PATH.'/phpunit.xml';
        if (!file_exists($xml)) {
            copy($gini->path . '/raw/templates/phpunit/phpunit.xml', $xml);
        }

        $dir = APP_PATH . '/tests';
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        $base = APP_PATH . '/tests/gini.php';
        if (!file_exists($base)) {
            copy($gini->path . '/raw/templates/phpunit/gini.php', $base);
        }

        echo "\e[1mDONE.\e[0m\n";
    }

    /**
     * 初始化模块
     *
     * @return void
     **/
    public function actionInit($args)
    {
        if (count($args) > 0) {
            if (in_array('phpunit', $args)) {
                return $this->_iniPHPUnit();
            }

            return;
        }

        $path = $_SERVER['PWD'];

        $prompt = [
            'id' => 'Id',
            'name' => 'Name',
            'description' => 'Description',
            'version' => 'Version',
            'dependencies' => 'Dependencies',
        ];

        $default = [
            'name' => ucwords(basename($path)),
            'id' => strtolower(basename($path)),
            'path' => $path,
            'description' => 'App description...',
            'version' => '0.1.0',
            'dependencies' => '[]',
        ];

        foreach ($prompt as $k => $v) {
            $data[$k] = readline($v . " [\e[31m" . ($default[$k]?:'N/A') . "\e[0m]: ");
            if (!$data[$k]) $data[$k] = $default[$k];
        }

        $data['dependencies'] = (array) @json_decode($data['dependencies']);

        $gini_json = J($data, JSON_PRETTY_PRINT);
        file_put_contents($path . '/gini.json', $gini_json);
    }

    public function actionHelp($args)
    {
        echo "gini init\n";
        echo "gini modules\n";
        echo "gini cache [class|view|config]\n";
        echo "gini update [modules|orm|web]\n";
        echo "gini server <host:port>\n";
        echo "gini install\n";
        echo "gini build\n";
    }

    public function __index($args)
    {
        $this->actionHelp($args);
    }

    public function actionInfo($args)
    {
        $path = $args[0] ?: APP_ID;

        $info = \Gini\Core::moduleInfo($path);
        if ($info) {
            foreach ($info as $k => $v) {
                if (is_array($v)) $v = J($v);
                printf("%s = %s\n", $k, $v);
            }
        }

    }

    public function actionModules($args)
    {
        foreach (\Gini\Core::$MODULE_INFO as $name => $info) {

            if (!$info->error) {
                $rPath = \Gini\File::relativePath($info->path, APP_PATH);
                if ($rPath[0] == '.') {
                    $rPath = '@/'.\Gini\File::relativePath($info->path, dirname(SYS_PATH));
                }
            }

            printf("%s %s %s %s %s\e[0m\n",
                $info->error ? "\e[31m":'',
                $this->_strPad($name, 20, ' '),
                $this->_strPad($info->version, 15, ' '),
                $this->_strPad($info->name, 30, ' '),
                $info->error ?: $rPath
            );
        }
    }

    public function actionDoctor()
    {
        $errors = $this->_diagnose();
        if (!count($errors)) {
            echo "🍺  \e[32mYou are ready now! Let's roll!\e[0m\n\n";

            return;
        }
    }

    private function _outputErrors(array $errors)
    {
        foreach ($errors as $err) {
            echo "   \e[31m*\e[0m $err\n";
        }

    }

    // exit if there is error
    private function _diagnose($items=null)
    {
        $errors = [];

        if (!$items || in_array('dependencies', $items)) {
            echo "Checking module dependencies...\n";
            // check gini dependencies
            foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
                if (!$info->error) continue;
                $errors['dependencies'][] = "$name: $info->error";
            }
            if ($errors['dependencies']) {
                $this->_outputErrors($errors['dependencies']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        // check composer requires
        if (!$items || in_array('composer', $items)) {
            echo "Checking composer dependencies...\n";
            foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
                if ($info->composer) {
                    if (!file_exists(APP_PATH.'/vendor')) {
                        $errors['composer'][] = $name . ': composer packages missing!';
                    }
                    break;
                }
            }
            if ($errors['composer']) {
                $this->_outputErrors($errors['composer']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        if (!$items || in_array('file', $items)) {
            echo "Checking file/directory modes...\n";
            // check if /tmp/gini-session is writable
            $path_gini_session = sys_get_temp_dir() . '/gini-session';
            if (is_dir($path_gini_session) && !is_writable($path_gini_session)) {
                $errors['file'][] = "$path_gini_session is not writable!";
            }
            if ($errors['file']) {
                $this->_outputErrors($errors['file']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        if (!$items || in_array('web', $items)) {
            echo "Checking web dependencies...\n";
            if (!file_exists(APP_PATH . '/web')) {
                $errors['web'][] = "Please run \e[1m\"gini update web\"\e[0m to generate web directory!";
            }
            if ($errors['web']) {
                $this->_outputErrors($errors['web']);
            } else {
                echo "   \e[32mdone.\e[0m\n";
            }
            echo "\n";
        }

        return $errors;
    }

    private function _eachFilesIn($root, $callback)
    {
        $walk = function ($root, $prefix, $callback) use (&$walk) {
            $dir = $root . '/' . $prefix;
            if (!is_dir($dir)) return;
            $dh = opendir($dir);
            if ($dh) {
                while (false !== ($name = readdir($dh))) {
                    if ($name[0] == '.') continue;

                    $file = $prefix ? $prefix . '/' . $name : $name;
                    $full_path = $root . '/' . $file;

                    if (is_dir($full_path)) {
                        $walk($root, $file, $callback);
                        continue;
                    }

                    if ($callback) {
                        $callback($file);
                    }
                }
                closedir($dh);
            }
        };

        $walk($root, '', $callback);
    }

    private function _cache_config()
    {
        printf("%s\n", "Updating config cache...");

        $config_items = \Gini\Config::fetch();

        $config_file = APP_PATH . '/cache/config.json';

        \Gini\File::ensureDir(APP_PATH.'/cache');
        file_put_contents($config_file,
            J($config_items));

        \Gini\Config::setup();

        echo "   \e[32mdone.\e[0m\n";
    }

    private function _cache_class()
    {
        printf("%s\n", "Updating class cache...");

        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, '');
        $class_map = [];
        foreach ($paths as $class_dir) {
            $this->_eachFilesIn($class_dir, function ($file) use ($class_dir, &$class_map) {
                if (preg_match('/^(.+)\.php$/', $file, $parts)) {
                    $class_name = trim(strtolower($parts[1]), '/');
                    $class_name = strtr($class_name, '-', '_');
                    $class_map[$class_name] = $class_dir . '/' . $file;
                }
            });
        }

        \Gini\File::ensureDir(APP_PATH.'/cache');
        file_put_contents(APP_PATH.'/cache/class_map.json',
            J($class_map));
        echo "   \e[32mdone.\e[0m\n";
    }

    private function _cache_view()
    {
        printf("%s\n", "Updating view cache...");

        $paths = \Gini\Core::pharFilePaths(VIEW_DIR, '');
        $view_map = [];
        foreach ($paths as $view_dir) {
            $this->_eachFilesIn($view_dir, function ($file) use ($view_dir, &$view_map) {
                // if (preg_match('/^([^\/]+)\/(.+)\.\1$/', $file , $parts)) {
                //     $view_name = $parts[1] . '/' .$parts[2];
                //     $view_map[$view_name] = $view_dir . '/' . $file;
                // }

                if (preg_match('/^(.+)\.([^\.]+)$/', $file, $parts)) {
                    $view_name = $parts[1];
                    $view_map[$parts[1]] = "$view_dir/$file";
                    // echo $parts[1]." => $view_dir/$file\n";
                }
            });
        }

        \Gini\File::ensureDir(APP_PATH.'/cache');
        file_put_contents(APP_PATH.'/cache/view_map.json',
            J($view_map));
        echo "   \e[32mdone.\e[0m\n";
    }

    public function actionPreview($args)
    {
        $errors = $this->_diagnose(['dependencies', 'web']);
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
            $this->_eachFilesIn($js_dir, function ($file) use ($js_dir, $ugly_js_dir) {
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
            $this->_eachFilesIn($src_dir, function ($file) use ($src_dir, $assets_dir) {
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

    private function _update_web($args)
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

    private function _update_orm($args)
    {
        // ORM required class map.
        if (!isset($GLOBALS['gini.class_map'])) {
            echo "\e[31mYou need to run \e[1m\"gini cache class\"\e[0;31m before update ORM.\e[0m\n";

            return;
        }
        // enumerate orms
        echo "Updating database structures according ORM definition...\n";

        $orm_dirs = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) continue;

            $this->_eachFilesIn($orm_dir, function ($file) use ($orm_dir) {
                $oname = preg_replace('|.php$|', '', $file);
                if ($oname == 'Object') return;
                printf("   %s\n", $oname);
                $class_name = '\Gini\ORM\\'.str_replace('/', '\\', $oname);
                $o = \Gini\IoC::construct($class_name);
                // some object might not have database backend
                $db = $o->db();
                if ($db) {
                    $db->adjustTable($o->tableName(), $o->schema());
                }
            });

        }

        echo "   \e[32mdone.\e[0m\n";
    }

    private function _update_composer($args)
    {

        echo "Generating Composer configuration file...\n";

        $app = \Gini\Core::moduleInfo(APP_ID);

        $composer_json = [
            'name' => $app->id,
            'description' => $app->description ?: '',
            'license' => 'proprietary',
            'repositories' => [
                ['type'=>'composer', 'url'=>'http://satis.genee.cn'],
                ['packagist'=>false]
            ]
        ];

        $walked = [];
        $walk = function ($info) use (&$walk, &$walked, &$composer_json) {
            $walked[$info->id] = true;
            foreach ($info->dependencies as $name => $version) {
                if (isset($walked[$name])) continue;
                $app = \Gini\Core::moduleInfo($name);
                if ($app) {
                    $walk($app);
                }
            }
            $composer_json = \Gini\Util::arrayMergeDeep($info->composer ?: [], $composer_json);
        };

        $walk($app);

        if (isset($composer_json['require']) || isset($composer_json['require-dev'])) {
            file_put_contents(APP_PATH.'/composer.json', J($composer_json, JSON_PRETTY_PRINT));
            // echo "composer update for \e[1m$app->id\e[0m...\n";
            // // gini install path/to/modules
            // $composer_bin = getenv("COMPOSER_BIN")?:"composer";
            // $cmd = sprintf("$composer_bin update -d %s", escapeshellarg($app->path));
            // // echo "$ $cmd\n";
            // passthru($cmd);
        } else {
            unlink(APP_PATH.'/composer.json');
        }

        echo "   \e[32mdone.\e[0m\n";

    }

    public function actionCache($args)
    {
        $errors = $this->_diagnose(['dependencies']);
        if ($errors) return;

        if (count($args) == 0) $args = ['class', 'view', 'config'];

        if (in_array('class', $args)) {
            $this->_cache_class();
            echo "\n";
        }

        if (in_array('config', $args)) {
            $this->_cache_config();
            echo "\n";
        }

        if (in_array('view', $args)) {
            $this->_cache_view();
            echo "\n";
        }

    }

    public function actionUpdate($args)
    {
        if (count($args) == 0) $args = ['orm', 'web', 'composer'];

        if (in_array('composer', $args)) {
            $this->_update_composer($args);
            echo "\n";
        }

        if (in_array('orm', $args) && APP_ID != 'gini') {
            $this->_update_orm($args);
            echo "\n";
        }

        if (in_array('web', $args) && APP_ID != 'gini') {
            $this->_update_web($args);
            echo "\n";
        }

    }

    private function _export_config()
    {
        $items = \Gini\Config::fetch();
        // echo J($items, JSON_PRETTY_PRINT);
        echo yaml_emit($items, YAML_UTF8_ENCODING);
    }

    private function _export_orm()
    {
        printf("Exporting ORM structures...\n\n");

        $orm_dirs = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) continue;

            $this->_eachFilesIn($orm_dir, function ($file) use ($orm_dir) {
                $oname = strtolower(preg_replace('|.php$|', '', $file));
                if ($oname == 'object') return;
                printf("   %s\n", $oname);
                $class_name = '\Gini\ORM\\'.str_replace('/', '\\', $oname);
                $o = \Gini\IoC::construct($class_name);
                $structure = $o->structure();

                // unset system fields
                unset($structure['id']);
                unset($structure['_extra']);

                $i = 1; $max = count($structure);
                foreach ($structure as $k => $v) {
                    if ($i == $max) break;
                    printf("   ├─ %s (%s)\n", $k, implode(',', array_map(function ($k, $v) {
                        return $v ? "$k:$v" : $k;
                    }, array_keys($v), $v)));
                    $i++;
                }

                printf("   └─ %s (%s)\n\n", $k, implode(',', array_map(function ($k, $v) {
                    return $v ? "$k:$v" : $k;
                }, array_keys($v), $v)));
            });

        }

    }

    public function actionExport($args)
    {
        if (count($args) == 0) $args = ['config', 'orm'];

        if (in_array('config', $args)) {
            $this->_export_config();
            echo "\n";
        }

        if (in_array('orm', $args)) {
            $this->_export_orm($args);
            echo "\n";
        }

    }

    private function _build($build_base, $info)
    {
        echo "Building \e[4m$info->name\e[0m ($info->id-$info->version)...\n";

        if (!isset($info->build)) $info->build = (object) [];
        $build = (object) $info->build;
        if (!isset($build->copy)) {
            $build->copy = ['raw'];
        }
        if (!isset($build->pack)) {
            $build->pack = ['class', 'view'];
        }

        $app_dir = $info->path;
        $build_dir = $build_base . '/' . $info->id;

        if (!is_dir($build_dir)) {
            @mkdir($build_dir, 0755, true);
        }

        require_once(SYS_PATH.'/lib/packer.php');
        foreach ($build->pack as $dir) {
            if (!is_dir("$app_dir/$dir")) {
                continue;
            }

            echo "  Packing $dir.phar...\n";
            $packer = \Gini\IoC::construct('\Gini\Dev\Packer', "$build_dir/$dir.phar");
            $packer->import("$app_dir/$dir");
            echo "\n";
        }

        foreach ($build->copy as $dir) {
            $dir = preg_replace('/^[\/.]/', '', $dir);
            if (!file_exists("$app_dir/$dir")) {
                continue;
            }

            if (is_dir("$build_dir/$dir")) {
                passthru("rm -r $build_dir/$dir");
            }
            echo "  copy $dir...\n";
            passthru("cp -r $app_dir/$dir $build_dir");
        }

        echo ("  copy gini.json...\n");
        passthru("cp $app_dir/gini.json $build_dir/gini.json");
        echo "\n";
    }

    public function actionBuild($args)
    {
        $info = \Gini\Core::moduleInfo(APP_ID);
        $build_base = $info->path . '/build';

        if (is_dir($build_base)) {
            passthru("rm -rf $build_base");
        }

        @mkdir($build_base, 0755, true);

        foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
            $this->_build($build_base, $info);
        }
    }

    public function actionWatch($args)
    {
        $watcher = new \Lurker\ResourceWatcher(in_array('-r', $args) ? new \Lurker\Tracker\RecursiveIteratorTracker : null);

        // Config
        $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'config');
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru("gini cache config");
            });
        });

        // Class
        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, '');
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru("gini cache class");
            });
        });

        // View
        $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'view');
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru("gini cache view");
            });
        });

        // ORM
        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru("gini update orm");
            });
        });

        // Web
        $paths
            = array_merge(
                \Gini\Core::pharFilePaths(RAW_DIR, 'assets'),
                \Gini\Core::pharFilePaths(RAW_DIR, 'js'),
                \Gini\Core::pharFilePaths(RAW_DIR, 'less')
            );

        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru("gini update web");
            });
        });

        echo "watching config/class/view/orm/web...\n";
        $watcher->start();
    }

    public function actionVersion($argv)
    {
        $info = \Gini\Core::moduleInfo(APP_ID);

        $version = $argv[0];
        if ($version) {
            // set current version
            $path = $info->path;
            $info->version = $version;
            \Gini\Core::saveModuleInfo($info);
        }

        echo "$info->name ($info->id/$info->version)\n";
    }

    public function actionPublish($argv)
    {
        count($argv) > 0 or die("Usage: gini publish <version>\n\n");

        $appId = APP_ID;
        // TODO: publish current module to gini-index.genee.cn
        $version = $argv[0];
        $GIT_DIR = escapeshellarg(APP_PATH.'/.git');
        $command = "git --git-dir=$GIT_DIR archive $version --format tgz 2> /dev/null";

        $path = "$appId/$version.tgz";
        $ph = popen($command, 'r');
        if (is_resource($ph)) {

            $content = '';
            while (!feof($ph)) {
                $content .= fread($ph, 4096);
            }

            if (strlen($content) == 0) {
                die("\e[31mError: $appId/$version missing!\e[0m\n");
            }

            $uri = $_SERVER['GINI_INDEX_URI'] ?: 'http://gini-index.genee.cn/';

            // sometimes people will run publish before run composer
            if (!class_exists('\Sabre\DAV\Client')) {
                require_once SYS_PATH.'/vendor/autoload.php';
            }

            $userName = readline('User: ');
            echo 'Password: ';
            `stty -echo`;
            $password = rtrim(fgets(STDIN), "\n");
            `stty echo`;
            echo "\n";

            $options = [
                'baseUri' => $uri,
                'userName' => $userName,
                'password' => $password,
            ];

            $client = new \Sabre\DAV\Client($options);
            $response = $client->request('MKCOL', $appId);
            if ($response['statusCode'] == 401 && isset($response['headers']['www-authenticate'])) {
                // Authentication required
                // 'Authorization: Basic '. base64_encode("user:password")
                die ("Failed to publish $appId/$version.\n");
            }
            
            $response = $client->request('PUT', $path, $content);
            if ($response['statusCode'] >= 200 && $response['statusCode'] <= 206) {
                echo "$appId/$version was published successfully.\n";
            } else {
                die ("Failed to publish $appId/$version.\n");
            }

            pclose($ph);
        }

    }

    public function actionUnpublish($argv)
    {
        count($argv) > 0 or die("Usage: gini unpublish <version>\n\n");

        $version = $argv[0];
        $appId = APP_ID;
        $path = "$appId/$version.tgz";

        $uri = $_SERVER['GINI_INDEX_URI'] ?: 'http://gini-index.genee.cn/';

        if (!class_exists('\Sabre\DAV\Client')) {
            require_once SYS_PATH.'/vendor/autoload.php';
        }

        $userName = readline('User: ');
        echo 'Password: ';
        `stty -echo`;
        $password = rtrim(fgets(STDIN), "\n");
        `stty echo`;
        echo "\n";

        $options = [
            'baseUri' => $uri,
            'userName' => $userName,
            'password' => $password,
        ];

        $client = new \Sabre\DAV\Client($options);
        $response = $client->request('HEAD', $path);
        if ($response['statusCode'] == 200) {
            echo "Unpublishing $appId/$version...\n";
            $response = $client->request('DELETE', $path);
            if ($response['statusCode'] == 200) {
                echo "done.\n";
            } else {
                echo "failed.\n";
            }
        } else {
            echo "Failed to find $path\n";
        }

    }

    /**
     * Install related modules
     *
     * @param  string $argv
     * @return void
     */
    public function actionInstall($argv)
    {
        (count($argv) > 0 || APP_ID != 'gini') or die("Usage: gini install <module> <version>\n\n");

        if (!class_exists('\Sabre\DAV\Client')) {
            require_once SYS_PATH.'/vendor/autoload.php';
        }

        $uri = $_SERVER['GINI_INDEX_URI'] ?: 'http://gini-index.genee.cn/';
        $client = new \Sabre\DAV\Client(['baseUri' => $uri]);

        $installedModules = [];
        $installModule = function ($module, $versionRange, $targetDir, $isApp=false) use (&$installModule, &$installedModules, $client) {

            if (isset($installedModules[$module])) {
                $info = $installedModules[$module];
                $v = new \Gini\Version($info->version);
                // if installed version is incorrect, abort the operation.
                if (!$v->satisfies($versionRange)) {
                    die("Conflict detected on $module! Installed: {$v->fullVersion} Expecting: $versionRange\n");
                }
            } else {

                // fetch index.json
                echo "Fetching INDEX file for {$module}...\n";
                $response = $client->request('GET', $module.'/index.json');
                if ($response['statusCode'] !== 200) {
                    die("Failed to fetch INDEX file.\n");
                }

                $indexInfo = json_decode($response['body'], true);
                // find latest match version
                foreach ($indexInfo as $version => $info) {
                    $v = new \Gini\Version($version);
                    if ($v->satisfies($versionRange)) {
                        if ($matched) {
                            if ($matched->compare($v) <= 0) continue;
                        }
                        $matched = $v;
                    }
                }

                if (!$matched) {
                    die("Failed to locate required version!\n");
                }

                $version = $matched->fullVersion;
                $info = (object) $indexInfo[$version];

                $tarPath = "{$module}/{$version}.tgz";
                echo "Downloading {$module} from {$tarPath}...\n";
                $response = $client->request('GET', $tarPath);
                if ($response['statusCode'] !== 200) {
                    die("Failed to fetch INDEX file.\n");
                }

                if ($isApp) {
                    $modulePath = $targetDir;
                } else {
                    $modulePath = "$targetDir/modules/$module";
                }

                \Gini\File::ensureDir($modulePath);
                echo "Extracting {$module} to {$modulePath}...\n";
                $ph = popen('tar -zx -C '.escapeshellcmd($modulePath), 'w');
                if (is_resource($ph)) {
                    fwrite($ph, $response['body']);
                    pclose($ph);
                }

                $installedModules[$module] = $info;
            }

            if ($info) {
                foreach ((array) $info->dependencies as $m => $r) {
                    if ($m == 'gini') continue;
                    $installModule($m, $r, $targetDir, false);
                }
            }

         };

        if (count($argv) > 0) {
            // e.g. gini install xxx
            $module = $argv[0];

            if (count($argv) > 1) {
                $versionRange = $argv[1];
            } else {
                $versionRange = readline('Please provide a version constraint for the '.$module.' requirement:');
            }

            $installModule($module, $versionRange, getcwd()."/$module", true);

        } else {
            // run: gini install, then you should be in module directory
            if (APP_ID != 'gini') {
                // try to install its dependencies
                $app = \Gini\Core::moduleInfo(APP_ID);
                $installedModules[APP_ID] = $app;
                $installModule(APP_ID, $app->version, APP_PATH, true);
           }
        }

    }

}
