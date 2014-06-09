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
        echo "gini cache [clean]\n";
        echo "gini preview <host:port>\n";
        echo "gini version <version>\n";
        echo "gini build\n";
        echo "gini install [module [version]]\n";
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
        $errors = \Gini\Doctor::diagnose();
        if (!count($errors)) {
            echo "🍺  \e[32mYou are ready now! Let's roll!\e[0m\n\n";

            return;
        }
    }

    private function _cacheClass()
    {
        printf("%s\n", "Updating class cache...");

        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, '');
        $class_map = [];
        foreach ($paths as $class_dir) {
            \Gini\File::eachFilesIn($class_dir, function ($file) use ($class_dir, &$class_map) {
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

    private function _cacheView()
    {
        printf("%s\n", "Updating view cache...");

        $paths = \Gini\Core::pharFilePaths(VIEW_DIR, '');
        $view_map = [];
        foreach ($paths as $view_dir) {
            \Gini\File::eachFilesIn($view_dir, function ($file) use ($view_dir, &$view_map) {
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

    public function actionCache($args)
    {
        if (count($args) == 0) {
            $errors = \Gini\Doctor::diagnose(['dependencies']);
            if ($errors) return;

            $this->_cacheClass();
            echo "\n";

            $this->_cacheView();
            echo "\n";
        } elseif (in_array('clean', $args)) {
            // clean cache
            echo "Cleanning Cache...\n";
            if (file_exists(APP_PATH.'/cache/class_map.json')) {
                unlink(APP_PATH.'/cache/class_map.json');
            }
            if (file_exists(APP_PATH.'/cache/view_map.json')) {
                unlink(APP_PATH.'/cache/view_map.json');
            }
            echo "   \e[32mdone.\e[0m\n";
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
                            if ($matched->compare($v) > 0) continue;
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
