<?php

/**
 * 模块操作命令行
 * usage: app command [args...]
 *    app new app_path [Name]
 *
 * @package default
 * @author Jia Huang
**/

namespace {

    if (!function_exists('json_last_error_msg')) {

        function json_last_error_msg() {
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    return 'no errors';
                case JSON_ERROR_DEPTH:
                    return 'maximum stack depth exceeded';
                case JSON_ERROR_STATE_MISMATCH:
                    return 'underflow or the modes mismatch';
                case JSON_ERROR_CTRL_CHAR:
                    return 'unexpected control character found';
                case JSON_ERROR_SYNTAX:
                    return 'syntax error, malformed JSON';
                case JSON_ERROR_UTF8:
                    return 'malformed UTF-8 characters, possibly incorrectly encoded';
                default:
                    return 'unknown error';
            }
        }

    }
    
    if (!function_exists('mb_str_pad')) {
        
        function mb_str_pad( $input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT) {
            $diff = mb_strwidth( $input ) - mb_strlen( $input );
            return str_pad( $input, $pad_length + $diff, $pad_string, $pad_type );
        }
    }

}

namespace Controller\CLI {

    class App extends \Controller\CLI {
        
        /**
         * 初始化模块
         *
         * @return void
         * @author Jia Huang
         **/
        public function action_init($args) {

            $path = $_SERVER['PWD'];

            $prompt = array(
                'shortname' => 'Shortname',
                'name' => 'Name',
                'description' => 'Description',
                'version' => 'Version',
                'dependencies' => 'Dependencies',
                );

            $default = array(
                'name' => ucwords(basename($path)),
                'shortname' => basename($path),
                'path' => $path,
                'description' => 'App description...',
                'version' => '0.1',
                'dependencies' => '[]',
                );

            foreach($prompt as $k => $v) {
                $data[$k] = readline($v . " [\e[31m" . ($default[$k]?:'N/A') . "\e[0m]: ");
                if (!$data[$k]) $data[$k] = $default[$k];
            }
            
            $data['dependencies'] = (array) @json_decode($data['dependencies']);

            $gini_json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES| JSON_PRETTY_PRINT);
            file_put_contents($path . '/gini.json', $gini_json);
        }

        public function action_help($args) {
            echo "gini init\n";
            echo "gini modules\n";
            echo "gini cache [class|view|config]\n";
            echo "gini update [modules|orm|web]\n";
            echo "gini server <host:port>\n";
            echo "gini install\n";
            echo "gini build\n";
        }

        public function __index($args) {
            $this->action_help($args);
        }

        public function action_info($args) {
            $path = $args[0] ?: APP_SHORTNAME;

            $info = \Gini\Core::path_info($path);
            if ($info) {
                foreach($info as $k => $v) {
                    if (is_array($v)) $v = json_encode($v);
                    printf("%s = %s\n", $k, $v);
                }
            }

        }

        public function action_modules($args) {
            
            foreach (\Gini\Core::$PATH_INFO as $name => $info) {
                printf("%s %s %s %s %s\e[0m\n", 
                    $info->error ? "\e[31m":'',
                    mb_str_pad($name, 20, ' '), 
                    mb_str_pad($info->version, 10, ' '),
                    mb_str_pad($info->name, 30, ' '),
                    $info->error ? "($info->error)":''
                );
            }
            
            
        }

        private function _prepare_walkthrough($root, $prefix, $callback) {

            $dir = $root . '/' . $prefix;
            if (!is_dir($dir)) return;
            $dh = opendir($dir);
            if ($dh) {
                while (false !== ($name = readdir($dh))) {
                    if ($name[0] == '.') continue;
                    
                    $file = $prefix ? $prefix . '/' . $name : $name;
                    $full_path = $root . '/' . $file;

                    if (is_dir($full_path)) {
                        $this->_prepare_walkthrough($root, $file, $callback);
                        continue;
                    }

                    if ($callback) {
                        $callback($file);
                    }
                }
                closedir($dh);
            }
        }

        private function _cache_config() {

            printf("%s\n", "Updating config cache...");

            $config_items = \Gini\Config::fetch();

            $config_file = APP_PATH . '/cache/config.json';

            \Gini\File::check_path(APP_PATH.'/cache/foo');
            file_put_contents($config_file, json_encode($config_items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

            \Gini\Config::setup();

            echo "   \e[32mdone.\e[0m\n";
        }

        private function _cache_class() {
            printf("%s\n", "Updating class cache...");

            $paths = \Gini\Core::phar_file_paths(CLASS_DIR, '');
            $class_map = array();
            foreach ($paths as $class_dir) {
                $this->_prepare_walkthrough($class_dir, '', function($file) use ($class_dir, &$class_map) {
                    if (preg_match('/^(.+)\.php$/', $file, $parts)) {
                        $class_name = trim(strtolower($parts[1]), '/');
                        $class_name = strtr($class_name, '-', '_');
                        $class_map[$class_name] = $class_dir . '/' . $file;
                    }
                });
            }

            \Gini\File::check_path(APP_PATH.'/cache/foo');
            file_put_contents(APP_PATH.'/cache/class_map.json', json_encode($class_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            echo "   \e[32mdone.\e[0m\n";
        }

        private function _cache_view() {
            printf("%s\n", "Updating view cache...");

            $paths = \Gini\Core::phar_file_paths(VIEW_DIR, '');
            $view_map = array();
            foreach ($paths as $view_dir) {
                $this->_prepare_walkthrough($view_dir, '', function($file) use ($view_dir, &$view_map) {
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

            \Gini\File::check_path(APP_PATH.'/cache/foo');
            file_put_contents(APP_PATH.'/cache/view_map.json', json_encode($view_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            echo "   \e[32mdone.\e[0m\n";
        }

        private function _convert_less() {
            printf("%s\n", "Converting LESS to CSS...");

            $css_dir = APP_PATH . '/web/assets/css';
            \Gini\File::check_path($css_dir . '/foo');

            $pinfo = (array)\Gini\Core::$PATH_INFO;
            $less_map = array();
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

        private function _uglify_js() {
            printf("%s\n", "Uglifying JS...");

            $ugly_js_dir = APP_PATH . '/web/assets/js';
            \Gini\File::check_path($ugly_js_dir . '/foo');

            $pinfo = (array)\Gini\Core::$PATH_INFO;
            $less_map = array();
            foreach ($pinfo as $p) {
                $js_dir = $p->path . '/' . RAW_DIR . '/js';
                if (!is_dir($js_dir)) continue;
                $dh = opendir($js_dir);
                if ($dh) {
                    while (false !== ($name = readdir($dh))) {
                        if ($name[0] == '.') continue;
                        if (fnmatch('*.js', $name)) {
                            $src_path = $js_dir . '/' . $name;
                            $dst_path = $ugly_js_dir . '/' . $name;
                            if (!file_exists($dst_path) 
                                || filemtime($src_path) > filemtime($dst_path)) {
                                // uglifyjs raw/js/$$JS -o web/assets/js/$$JS ; \
                                printf("   %s\n", $name);
                                $command = sprintf("uglifyjs %s -o %s"
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

            // \Gini\File::check_path(APP_PATH.'/cache/view_map.json');
            // file_put_contents(APP_PATH.'/cache/view_map.json', json_encode($view_map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES| JSON_PRETTY_PRINT));
            echo "   \e[32mdone.\e[0m\n";
        }

        private function _merge_assets() {
    
            printf("%s\n", "Merging all assets...");
            $assets_dir = APP_PATH.'/web/assets';
            \Gini\File::check_path($assets_dir.'/foo');

            $pinfo = (array)\Gini\Core::$PATH_INFO;
            foreach ($pinfo as $p) {
                $src_dir = $p->path . '/' . RAW_DIR . '/assets';
                $this->_prepare_walkthrough($src_dir, '', function($file) use ($src_dir, $assets_dir) {
                    $src_path = $src_dir . '/' . $file;
                    $dst_path = $assets_dir . '/' . $file;

                    \Gini\File::check_path($dst_path);
                    if (!file_exists($dst_path) 
                        || filesize($src_path) != filesize($dst_path) 
                        || filemtime($src_path) > filemtime($dst_path)) {
                        printf("   copy %s\n", $file);
                        \copy($src_path, $dst_path);
                    }

                });
            }

            echo "   \e[32mdone.\e[0m\n";
        }

        public function action_update_web($args) {
            $web_dir = APP_PATH . '/web';
            \Gini\File::check_path($web_dir.'/foo');
            $cgi_path = realpath(dirname(realpath($_SERVER['SCRIPT_FILENAME'])) . '/../lib/cgi.php');
            $index_path = $web_dir . '/index.php';
            if (file_exists($index_path) || is_link($index_path)) unlink($index_path);
            file_put_contents($index_path, "<?php require \"$cgi_path\";\n");

            $this->_merge_assets();
            $this->_convert_less();
            $this->_uglify_js();
        }

        public function action_preview($args) {
            $addr = $args[0] ?: 'localhost:3000';
            $command 
                = sprintf("php -S %s -c %s -t %s 2>&1"
                    , $addr
                    , APP_PATH . '/raw/cli-server.ini'
                    , APP_PATH.'/web');

            $descriptors = array(
                    array('file', '/dev/tty', 'r'),
                    array('file', '/dev/tty', 'w'),
                    array('file', '/dev/tty', 'w')
            );

            $proc = proc_open($command, $descriptors, $pipes);
            if (is_resource($proc)) {
                proc_close($proc);
            }
        }

        public function action_update_orm($args) {
            // enumerate orms
            printf("Updating database structures according ORM definition...\n");

            $orm_dirs = \Gini\Core::phar_file_paths(CLASS_DIR, 'orm');
            foreach($orm_dirs as $orm_dir) {
                if (!is_dir($orm_dir)) continue;

                $this->_prepare_walkthrough($orm_dir, '', function($file) use ($orm_dir) {
                    $oname = preg_replace('|.php$|', '', $file);
                    if ($oname == 'object') return;
                    printf("   %s\n", $oname);
                    $class_name = '\\ORM\\'.str_replace('/', '\\', $oname);
                    $o = new $class_name();
                    $o->db()->adjust_table($o->table_name(), $o->schema());
                });

            }

            echo "   \e[32mdone.\e[0m\n";
        }

        function action_cache($args) {
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

        function action_update($args) {            
            if (count($args) == 0) $args = ['orm', 'web'];

            if (in_array('orm', $args)) {
                $this->action_update_orm($args);
                echo "\n";
            }

            if (in_array('web', $args)) {
                $this->action_update_web($args);
                echo "\n";
            }

        }

        private function _export_config() {
            $items = \Gini\Config::fetch();
            echo yaml_emit($items, YAML_UTF8_ENCODING);
        }

        private function _export_orm() {

            printf("Exporting ORM structures...\n\n");

            $orm_dirs = \Gini\Core::phar_file_paths(CLASS_DIR, 'orm');
            foreach($orm_dirs as $orm_dir) {
                if (!is_dir($orm_dir)) continue;

                $this->_prepare_walkthrough($orm_dir, '', function($file) use ($orm_dir) {
                    $oname = preg_replace('|.php$|', '', $file);
                    if ($oname == 'object') return;
                    printf("   %s\n", $oname);
                    $class_name = '\\ORM\\'.str_replace('/', '\\', $oname);
                    $o = new $class_name();
                    $structure = $o->structure();

                    // unset system fields
                    unset($structure['id']);
                    unset($structure['_extra']);

                    $i = 1; $max = count($structure);
                    foreach ($structure as $k => $v) {
                        if ($i == $max) break;
                        printf("   ├─ %s (%s)\n", $k, implode(',', array_map(function($k, $v) {
                            return $v ? "$k:$v" : $k;
                        }, array_keys($v), $v)));
                        $i++;
                    }

                    printf("   └─ %s (%s)\n\n", $k, implode(',', array_map(function($k, $v) {
                        return $v ? "$k:$v" : $k;
                    }, array_keys($v), $v)));
                });

            }

        }

        public function action_export($args) {
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
        
        private function _build($build_base, $info) {
            echo "Building \e[4m$info->name\e[0m ($info->shortname-$info->version)...\n";

            if (!isset($info->build)) $info->build = (object)[];
            $build = (object)$info->build;
            if (!isset($build->copy)) {
                $build->copy = ['raw'];
            }
            if (!isset($build->pack)) {
                $build->pack = ['class', 'view'];
            }
            
            $app_dir = $info->path;
            $build_dir = $build_base . '/' . $info->shortname;
            
            if (!is_dir($build_dir)) {
                @mkdir($build_dir, 0755, true);
            }
            
            require_once(SYS_PATH.'/lib/packer.php');
            foreach ($build->pack as $dir) {
                if (!is_dir("$app_dir/$dir")) {
                    continue;
                }

                echo "  Packing $dir.phar...\n";
                $packer = new \Gini\Dev\Packer("$build_dir/$dir.phar");
                $packer->import("$app_dir/$dir");
                echo "\n";
            }
            
            foreach ($build->copy as $dir) {
                $dir = preg_replace('/^[\/.]/', '', $dir);
                if (!is_dir("$app_dir/$dir")) {
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
        
        public function action_build($args) {
            $info = \Gini\Core::path_info(APP_SHORTNAME);
            $build_base = $info->path . '/build';

            if (is_dir($build_base)) {
                passthru("rm -rf $build_base");
            }

            @mkdir($build_base, 0755, true);
            
            foreach (\Gini\Core::$PATH_INFO as $name => $info) {
                $this->_build($build_base, $info);
            }
        }
        
        private function _install($info) {
            foreach ($info->dependencies as $name => $version) {
                $app = \Gini\Core::path_info($name);
                if ($app === false) {
                    // need to install
                    echo "Installing $name...\n";
                    // gini install path/to/modules
                    $cmd = strtr(
                        getenv("GINI_INSTALL_COMMAND") ?: 'git clone git@gini.genee.cn:gini/%name %base/%name',
                        ['%name'=>escapeshellcmd($name), '%base'=>escapeshellcmd($_SERVER['GINI_MODULE_BASE_PATH'])]
                    );
                    passthru($cmd);
                    $app = \Gini\Core::import($name, $version, $info);
                    if ($app) {
                        $this->_install($app);
                    }
                }
                elseif (!\Gini\Core::checkVersion($app->version, $version)) {
                    
                }
                else {
                    $this->_install($app);
                }
            }
        }
        
        public function action_install($args) {
            
            $app = \Gini\Core::path_info(APP_SHORTNAME);
            $this->_install($app);
        }

    }

}
