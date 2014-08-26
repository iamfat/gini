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
            'name' => ucwords(str_replace('-', ' ', basename($path))),
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

    public function __index($args)
    {
        echo "gini init\n";
        echo "gini modules\n";
        echo "gini cache [clean]\n";
        echo "gini version <version>\n";
        echo "gini build\n";
        echo "gini install [module [version]]\n";
    }

    public function actionInfo($args)
    {
        $path = $args[0] ?: APP_ID;

        $info = \Gini\Core::moduleInfo($path);
        if ($info) {
            $info = (array) $info;
            unset($info['path']);
            echo \Symfony\Component\Yaml\Yaml::dump($info);
        }

    }

    public function actionModules($args)
    {
        foreach (\Gini\Core::$MODULE_INFO as $name => $info) {

            if (!$info->error) {
                $rPath = \Gini\File::relativePath($info->path, APP_PATH);
                if ($rPath[0] == '.') {
                    $rPath = \Gini\File::relativePath($info->path, dirname(SYS_PATH));

                    if ($rPath[0] == '.') {
                        $rPath = '@/'.\Gini\File::relativePath($info->path, $_SERVER['GINI_MODULE_BASE_PATH']);
                    } else {
                        $rPath = '!/'.$rPath;
                    }
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
                passthru("gini config update");
            });
        });

        // Class && View
        $paths
            = array_merge(
                \Gini\Core::pharFilePaths(CLASS_DIR, ''),
                \Gini\Core::pharFilePaths(VIEW_DIR, '')
            );
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru("gini cache");
            });
        });

        // ORM
        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru("gini orm update");
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
                passthru("gini web update");
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
            $v = new \Gini\Version($version);
            $v->compare($info->version) > 0 or die("A newer version (>{$info->version}) is required!\n");

            $info->version = $version;
            \Gini\Core::saveModuleInfo($info);

            // commit it if it is a git repo
            if (is_dir(APP_PATH.'/.git')) {
                $WORK_TREE = escapeshellarg(APP_PATH);
                $GIT_DIR = escapeshellarg(APP_PATH.'/.git');
                $GIT_MSG = escapeshellarg("Bumped version to $version");
                $command = "git --git-dir=$GIT_DIR --work-tree=$WORK_TREE commit -m $GIT_MSG gini.json && git --git-dir=$GIT_DIR tag $version";
                passthru($command);
                return;
            }
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

        $controller = \Gini\IoC::construct('\Gini\Controller\CLI\Index');
        $controller->actionInstall($argv);
    }

}
