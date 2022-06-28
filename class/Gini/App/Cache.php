<?php

namespace Gini\App;

class Cache
{
    private static function _outputErrors(array $errors)
    {
        foreach ($errors as $err) {
            echo "   \e[31m*\e[0m $err\n";
        }
    }

    private static function _cacheClass()
    {
        printf("%s\n", 'Updating class cache...');

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

        \Gini\File::ensureDir(APP_PATH . '/cache');
        file_put_contents(
            APP_PATH . '/cache/class_map.json',
            J($class_map)
        );
        echo "   \e[32mdone.\e[0m\n";

        // load class map
        $GLOBALS['gini.class_map'] = $class_map;
    }

    private static function _cacheView()
    {
        printf("%s\n", 'Updating view cache...');

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

        \Gini\File::ensureDir(APP_PATH . '/cache');
        file_put_contents(
            APP_PATH . '/cache/view_map.json',
            J($view_map)
        );
        echo "   \e[32mdone.\e[0m\n";

        // load view map
        $GLOBALS['gini.view_map'] = $view_map;
    }

    private static function _getORMPlurals()
    {
        $orm_dirs = \Gini\Core::pharFilePaths(CLASS_DIR, 'Gini/ORM');

        $onames = [];
        foreach ($orm_dirs as $orm_dir) {
            if (!is_dir($orm_dir)) {
                continue;
            }

            \Gini\File::eachFilesIn($orm_dir, function ($file) use ($orm_dir, &$onames) {
                if (!preg_match('/^(.+)\.php$/', $file, $parts)) {
                    return;
                }

                $oname = $parts[1];
                if ($oname == 'Object') {
                    return;
                }

                $class_name = '\Gini\ORM\\' . str_replace('/', '\\', $oname);

                // Check if it is abstract class
                $rc = new \ReflectionClass($class_name);
                if ($rc->isAbstract() || $rc->isTrait() || $rc->isInterface()) {
                    return;
                }

                $onames[] = strtolower($oname);
            });
        }

        if (count($onames) == 0) {
            return [];
        }

        // printf("%s\n", 'Generating ORM plurals cache...');
        $plurals = [];
        $onames = array_unique($onames);
        foreach ($onames as $oname) {
            $plural = \Gini\Util::pluralize($oname);
            if ($plural != $oname) {
                $plurals[$plural] = $oname;
            }
        }

        // echo "   \e[32mdone.\e[0m\n";

        return $plurals;
    }

    private static function _cacheConfig()
    {
        printf("%s\n", 'Updating config cache...');

        $config_items = \Gini\Config::fetch();
        $plurals = self::_getORMPlurals();

        // update orm plurals
        $c = ($config_items['orm'] ?? [])['plurals'] ?? [];
        $c += array_filter($plurals, function ($v) use ($c) {
            return in_array($v, $c);
        });

        $config_items['orm']['plurals'] = $c;

        $config_file = APP_PATH . '/cache/config.json';

        \Gini\File::ensureDir(APP_PATH . '/cache');
        file_put_contents(
            $config_file,
            J($config_items)
        );

        \Gini\Config::setup();

        echo "   \e[32mdone.\e[0m\n";
    }

    private static function _cacheRouters()
    {
        printf("%s\n", 'Updating router cache...');

        $router = \Gini\CGI::router(true);
        $router_cache = $router->toJSON();
        \Gini\File::ensureDir(APP_PATH . '/cache');
        file_put_contents(
            APP_PATH . '/cache/router.json',
            J($router_cache)
        );
        echo "   \e[32mdone.\e[0m\n";
    }

    public static function setup()
    {
        self::_cacheClass();
        echo "\n";

        self::_cacheView();
        echo "\n";

        self::_cacheConfig();
        echo "\n";

        self::_cacheRouters();
        echo "\n";

        // check gini dependencies
        foreach (\Gini\Core::$MODULE_INFO as $name => $info) {
            $class = '\Gini\Module\\' . strtr($name, ['-' => '', '_' => '', '/' => '']);
            $cache_func = "$class::cache";
            if (is_callable($cache_func)) {
                echo "Setting up cache for Module[$name]...\n";
                call_user_func($cache_func);
                echo "   \e[32mdone.\e[0m\n\n";
            }
        }
    }

    public static function clean()
    {
        echo "Cleaning Cache...\n";
        \Gini\File::removeDir(APP_PATH . '/cache');
        echo "   \e[32mdone.\e[0m\n";
    }
}
