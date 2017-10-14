<?php

/**
 * 模块操作命令行
 * usage: app command [args...]
 *    app new app_path [Name].
 *
 * @author Jia Huang
 **/

namespace Gini\Controller\CLI;

class App extends \Gini\Controller\CLI
{
    protected function _strPad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        $diff = mb_strwidth($input) - mb_strlen($input);

        return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
    }

    /**
     * 初始化模块.
     **/
    public function actionInit($args)
    {
        $path = $_SERVER['PWD'];

        $prompt = [
            'name' => 'Name',
            'description' => 'Description',
            'version' => 'Version',
            'dependencies' => 'Dependencies',
        ];

        $default = [
            'name' => ucwords(str_replace('-', ' ', basename($path))),
            'path' => $path,
            'description' => 'App description...',
            'version' => '0.1.0',
            'dependencies' => '{}',
        ];

        foreach ($prompt as $k => $v) {
            $data[$k] = readline($v.' ['.($default[$k] ?: 'N/A').']: ');
            if (!$data[$k]) {
                $data[$k] = $default[$k];
            }
        }

        $data['dependencies'] = @json_decode($data['dependencies']) ?: (object) [];

        $gini_json = J($data, JSON_PRETTY_PRINT);
        file_put_contents($path.'/gini.json', $gini_json);
    }

    public function __index($args)
    {
        echo "gini init\n";
        echo "gini modules\n";
        echo "gini cache [clean]\n";
        echo "gini version <version>\n";
        echo "gini install [module [version]]\n";
    }

    public function actionInfo($args)
    {
        $path = $args[0] ?: APP_ID;

        $info = \Gini\Core::moduleInfo($path);
        if ($info) {
            $info = (array) $info;
            unset($info['path']);
            echo yaml_emit($info, YAML_UTF8_ENCODING);
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

            printf(
                "%s %s %s %s %s\e[0m\n",
                $info->error ? "\e[31m" : '',
                $this->_strPad($name, 30, ' '),
                $this->_strPad($info->version, 15, ' '),
                $this->_strPad($info->name, 30, ' '),
                $info->error ?: $rPath
            );
        }
    }

    public function actionDoctor()
    {
        $errors = \Gini\App\Doctor::diagnose();
        if (!count($errors)) {
            echo "\e[32mYou are ready now! Let's roll!\e[0m\n\n";

            return;
        }
    }

    public function actionCache($args)
    {
        $opt = \Gini\Util::getOpt($args, 'he:', ['help', 'env:']);
        if (isset($opt['h']) || isset($opt['help'])) {
            echo "Usage: gini cache [-h|--help] [-e|--env=ENV] [clean]\n";

            return;
        }

        $env = $opt['e'] ?: $opt['env'] ?: null;
        if ($opt['_'][0] == 'clean') {
            \Gini\App\Cache::clean();
        } else {
            $errors = \Gini\App\Doctor::diagnose(['dependencies', 'composer']);
            if ($errors) {
                return;
            }

            \Gini\App\Cache::setup($env);
        }
    }

    public function actionWatch($args)
    {
        $watcher = new \Lurker\ResourceWatcher(in_array('-r', $args) ? new \Lurker\Tracker\RecursiveIteratorTracker() : null);

        // Config
        $paths = \Gini\Core::pharFilePaths(RAW_DIR, 'config');
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru('gini config update');
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
                passthru('gini cache');
            });
        });

        // ORM
        $paths = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');
        array_walk($paths, function ($path) use ($watcher) {
            $watcher->trackByListener($path, function (\Lurker\Event\FilesystemEvent $event) {
                passthru('gini orm update');
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
                passthru('gini web update');
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
     * Install related modules.
     *
     * @param string $argv
     */
    public function actionInstall($argv)
    {
        (count($argv) > 0 || APP_ID != 'gini') or die("Usage: gini install <module> <version>\n\n");

        $controller = \Gini\IoC::construct('\Gini\Controller\CLI\Index');
        $controller->actionInstall($argv);
    }

    /**
     * sh -lc.
     *
     * @param string $argv
     */
    public function actionSh($argv)
    {
        $command = implode(' ', $argv);
        $proc = proc_open($command ?: '/bin/sh -l', [STDIN, STDOUT, STDERR], $pipes);
        if (is_resource($proc)) {
            proc_close($proc);
        }
    }
}
